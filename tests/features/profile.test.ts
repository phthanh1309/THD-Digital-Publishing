import assert from "node:assert/strict";
import test from "node:test";
import {
  getProfileView,
  runProfileUpdate,
  PROFILE_MAX_AVATAR_SIZE,
  type ProfileUpdateData,
  type ProfileUpdateDependencies,
  type ProfileUser,
  type ProfileViewDependencies,
} from "../../src/lib/profile";
import {
  ProfileDisabledError,
  type ProfileAccessDependencies,
} from "../../src/lib/features/profile-access";
import { createFeatureService } from "../../src/lib/features/service";

const DEFAULT_USER: ProfileUser = {
  id: "user-1",
  username: "alice",
  email: "alice@example.com",
  name: "Alice",
  bio: "Hello",
  image: null,
};

const NOW = 1_700_000_000_000;

type FakeOptions = {
  profileEnabled?: boolean;
  profileError?: Error;
  sessionUserId?: string | null;
  user?: ProfileUser | null;
};

type Calls = {
  flag: number;
  errors: string[];
  sessions: number;
  userLookups: string[];
  avatarReads: number;
  avatarSaves: { userId: string; fileName: string; size: number }[];
  updates: { id: string; data: ProfileUpdateData }[];
  revalidations: number;
};

function createAccess(
  options: FakeOptions,
  calls: Calls,
): ProfileAccessDependencies {
  return {
    async isProfileEnabled() {
      calls.flag += 1;
      if (options.profileError) throw options.profileError;
      return options.profileEnabled ?? false;
    },
    logError(message) {
      calls.errors.push(message);
    },
  };
}

function createCalls(): Calls {
  return {
    flag: 0,
    errors: [],
    sessions: 0,
    userLookups: [],
    avatarReads: 0,
    avatarSaves: [],
    updates: [],
    revalidations: 0,
  };
}

function createViewDeps(options: FakeOptions = {}): {
  deps: ProfileViewDependencies;
  calls: Calls;
} {
  const calls = createCalls();

  const deps: ProfileViewDependencies = {
    access: createAccess(options, calls),
    async getSessionUserId() {
      calls.sessions += 1;
      return options.sessionUserId === undefined
        ? DEFAULT_USER.id
        : options.sessionUserId;
    },
    async findUserById(id) {
      calls.userLookups.push(id);
      return options.user === undefined ? DEFAULT_USER : options.user;
    },
  };

  return { deps, calls };
}

function createUpdateDeps(options: FakeOptions = {}): {
  deps: ProfileUpdateDependencies;
  calls: Calls;
} {
  const calls = createCalls();

  const deps: ProfileUpdateDependencies = {
    access: createAccess(options, calls),
    async getSessionUserId() {
      calls.sessions += 1;
      return options.sessionUserId === undefined
        ? DEFAULT_USER.id
        : options.sessionUserId;
    },
    async saveAvatar({ userId, fileName, data }) {
      calls.avatarSaves.push({ userId, fileName, size: data.length });
      return `avatars/${fileName}`;
    },
    async updateUser(id, data) {
      calls.updates.push({ id, data });
    },
    revalidate() {
      calls.revalidations += 1;
    },
    now: () => NOW,
  };

  return { deps, calls };
}

test("profile enabled returns the signed-in user's own profile", async () => {
  const { deps, calls } = createViewDeps({ profileEnabled: true });

  const view = await getProfileView(deps);

  assert.deepEqual(view, { status: "ok", user: DEFAULT_USER });
  assert.deepEqual(calls.userLookups, [DEFAULT_USER.id]);
});

test("profile disabled blocks the profile view without reading the session", async () => {
  const { deps, calls } = createViewDeps({ profileEnabled: false });

  const view = await getProfileView(deps);

  assert.equal(view.status, "disabled");
  assert.equal(calls.sessions, 0);
  assert.deepEqual(calls.userLookups, []);
});

test("profile flag error blocks the profile view (fail closed)", async () => {
  const { deps, calls } = createViewDeps({
    profileError: new Error("feature database unavailable"),
  });

  const view = await getProfileView(deps);

  assert.equal(view.status, "disabled");
  assert.equal(calls.errors.length, 1);
  assert.equal(calls.sessions, 0);
  assert.deepEqual(calls.userLookups, []);
});

test("profile enabled still requires a session", async () => {
  const { deps, calls } = createViewDeps({
    profileEnabled: true,
    sessionUserId: null,
  });

  const view = await getProfileView(deps);

  assert.equal(view.status, "unauthenticated");
  assert.deepEqual(calls.userLookups, []);
});

test("profile enabled reports a missing session user as not found", async () => {
  const { deps } = createViewDeps({ profileEnabled: true, user: null });

  const view = await getProfileView(deps);

  assert.equal(view.status, "not_found");
});

test("an ADMIN session is blocked when profile is disabled (no role bypass)", async () => {
  const { deps, calls } = createViewDeps({
    profileEnabled: false,
    sessionUserId: "admin-1",
    user: { ...DEFAULT_USER, id: "admin-1", username: "admin" },
  });

  const view = await getProfileView(deps);

  assert.equal(view.status, "disabled");
  assert.equal(calls.sessions, 0);
  assert.deepEqual(calls.userLookups, []);
});

test("profile enabled updates the signed-in user's own profile", async () => {
  const { deps, calls } = createUpdateDeps({ profileEnabled: true });

  await runProfileUpdate({ name: "New name", bio: "New bio" }, deps);

  assert.deepEqual(calls.updates, [
    {
      id: DEFAULT_USER.id,
      data: { name: "New name", bio: "New bio" },
    },
  ]);
  assert.equal(calls.revalidations, 1);
  assert.equal(calls.avatarReads, 0);
});

test("profile disabled rejects an update before any session, validation, file or write", async () => {
  const { deps, calls } = createUpdateDeps({ profileEnabled: false });

  await assert.rejects(
    () =>
      runProfileUpdate(
        {
          name: "x".repeat(81),
          bio: "",
          avatar: {
            name: "avatar.png",
            size: 3,
            async read() {
              calls.avatarReads += 1;
              return new Uint8Array([1, 2, 3]).buffer as ArrayBuffer;
            },
          },
        },
        deps,
      ),
    ProfileDisabledError,
  );

  assert.equal(calls.flag, 1);
  assert.equal(calls.sessions, 0);
  assert.equal(calls.avatarReads, 0);
  assert.deepEqual(calls.avatarSaves, []);
  assert.deepEqual(calls.updates, []);
  assert.equal(calls.revalidations, 0);
});

test("profile flag error rejects an update (fail closed)", async () => {
  const { deps, calls } = createUpdateDeps({
    profileError: new Error("feature database unavailable"),
  });

  await assert.rejects(
    () => runProfileUpdate({ name: "New", bio: "" }, deps),
    ProfileDisabledError,
  );

  assert.equal(calls.errors.length, 1);
  assert.equal(calls.sessions, 0);
  assert.deepEqual(calls.updates, []);
  assert.equal(calls.revalidations, 0);
});

test("profile update still requires a session when enabled", async () => {
  const { deps, calls } = createUpdateDeps({
    profileEnabled: true,
    sessionUserId: null,
  });

  await assert.rejects(
    () => runProfileUpdate({ name: "New", bio: "" }, deps),
    /Chưa đăng nhập/,
  );
  assert.deepEqual(calls.updates, []);
});

test("profile update preserves validation errors", async () => {
  const { deps, calls } = createUpdateDeps({ profileEnabled: true });

  await assert.rejects(
    () => runProfileUpdate({ name: "x".repeat(81), bio: "" }, deps),
    /Tên hiển thị tối đa 80 ký tự/,
  );
  assert.deepEqual(calls.updates, []);
});

test("profile update rejects an oversized avatar before reading it", async () => {
  const { deps, calls } = createUpdateDeps({ profileEnabled: true });

  await assert.rejects(
    () =>
      runProfileUpdate(
        {
          name: "New",
          bio: "",
          avatar: {
            name: "avatar.png",
            size: PROFILE_MAX_AVATAR_SIZE + 1,
            async read() {
              calls.avatarReads += 1;
              return new ArrayBuffer(0);
            },
          },
        },
        deps,
      ),
    /Ảnh đại diện tối đa 5MB/,
  );

  assert.equal(calls.avatarReads, 0);
  assert.deepEqual(calls.updates, []);
});

test("profile update rejects an unsupported avatar extension before reading it", async () => {
  const { deps, calls } = createUpdateDeps({ profileEnabled: true });

  await assert.rejects(
    () =>
      runProfileUpdate(
        {
          name: "New",
          bio: "",
          avatar: {
            name: "avatar.gif",
            size: 3,
            async read() {
              calls.avatarReads += 1;
              return new ArrayBuffer(0);
            },
          },
        },
        deps,
      ),
    /Chỉ chấp nhận ảnh jpg\/png\/webp/,
  );

  assert.equal(calls.avatarReads, 0);
  assert.deepEqual(calls.updates, []);
});

test("profile update stores a valid avatar under the session user id", async () => {
  const { deps, calls } = createUpdateDeps({ profileEnabled: true });

  await runProfileUpdate(
    {
      name: "New",
      bio: "",
      avatar: {
        name: "avatar.PNG",
        size: 3,
        async read() {
          calls.avatarReads += 1;
          return new Uint8Array([1, 2, 3]).buffer as ArrayBuffer;
        },
      },
    },
    deps,
  );

  assert.equal(calls.avatarReads, 1);
  assert.deepEqual(calls.avatarSaves, [
    {
      userId: DEFAULT_USER.id,
      fileName: `${DEFAULT_USER.id}-${NOW}.png`,
      size: 3,
    },
  ]);
  assert.deepEqual(calls.updates, [
    {
      id: DEFAULT_USER.id,
      data: {
        name: "New",
        bio: "",
        image: `avatars/${DEFAULT_USER.id}-${NOW}.png`,
      },
    },
  ]);
});

test("profile update ignores a request-supplied target user id", async () => {
  const { deps, calls } = createUpdateDeps({ profileEnabled: true });

  const forgedInput = {
    name: "Forged",
    bio: "",
    userId: "victim",
    id: "victim",
  } as unknown as { name: string; bio: string };

  await runProfileUpdate(forgedInput, deps);

  assert.deepEqual(calls.updates, [
    {
      id: DEFAULT_USER.id,
      data: { name: "Forged", bio: "" },
    },
  ]);
});

test("an ADMIN can update their own profile when the feature is enabled", async () => {
  const { deps, calls } = createUpdateDeps({
    profileEnabled: true,
    sessionUserId: "admin-1",
  });

  await runProfileUpdate({ name: "Admin", bio: "" }, deps);

  assert.deepEqual(calls.updates, [
    { id: "admin-1", data: { name: "Admin", bio: "" } },
  ]);
});

test("an empty display name keeps the existing name", async () => {
  const { deps, calls } = createUpdateDeps({ profileEnabled: true });

  await runProfileUpdate({ name: "   ", bio: "Bio" }, deps);

  assert.deepEqual(calls.updates, [
    { id: DEFAULT_USER.id, data: { name: undefined, bio: "Bio" } },
  ]);
});

test("disabling profile leaves login and register flags enabled", async () => {
  const service = createFeatureService({
    dataSource: {
      async loadFlags() {
        return [
          { key: "profile", enabled: false },
          { key: "login", enabled: true },
          { key: "register", enabled: true },
        ];
      },
      async loadPublication() {
        throw new Error("publication data is not used by GLOBAL flags");
      },
    },
    memoize: (loader) => loader,
  });

  assert.equal(await service.isFeatureEnabled("profile"), false);
  assert.equal(await service.isFeatureEnabled("login"), true);
  assert.equal(await service.isFeatureEnabled("register"), true);
});
