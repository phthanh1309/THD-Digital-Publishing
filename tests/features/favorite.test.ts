import assert from "node:assert/strict";
import test from "node:test";
import {
  FAVORITE_DISABLED_MESSAGE,
  runToggleFavorite,
  type FavoriteToggleDependencies,
} from "../../src/lib/favorite";
import {
  PublicationFeatureDisabledError,
  type PublicationFeatureAccessDependencies,
} from "../../src/lib/features/publication-feature-access";
import { createFeatureService } from "../../src/lib/features/service";

type FakeOptions = {
  favoriteEnabled?: boolean;
  favoriteError?: Error;
  sessionUserId?: string | null;
  existingFavoriteId?: string | null;
  existingFavoriteOwner?: string;
  publication?: { id: string } | null;
};

type Calls = {
  featureKeys: string[];
  publicationIds: string[];
  featureErrors: string[];
  sessions: number;
  lookups: { userId: string; publicationId: string }[];
  publications: { publicationId: string; slug: string }[];
  creates: { userId: string; publicationId: string }[];
  deletes: string[];
  revalidations: string[];
};

function createDeps(options: FakeOptions = {}): {
  deps: FavoriteToggleDependencies;
  calls: Calls;
} {
  const calls: Calls = {
    featureKeys: [],
    publicationIds: [],
    featureErrors: [],
    sessions: 0,
    lookups: [],
    publications: [],
    creates: [],
    deletes: [],
    revalidations: [],
  };

  const access: PublicationFeatureAccessDependencies = {
    async getPublicationFeatureState(featureKey, publicationId) {
      calls.featureKeys.push(featureKey);
      calls.publicationIds.push(publicationId);
      if (options.favoriteError) throw options.favoriteError;

      return {
        key: featureKey,
        name: "Lưu ấn phẩm",
        scope: "GLOBAL_AND_PUBLICATION",
        globalEnabled: options.favoriteEnabled ?? false,
        override: null,
        enabled: options.favoriteEnabled ?? false,
        source: "global",
      };
    },
    logError(message) {
      calls.featureErrors.push(message);
    },
  };

  const deps: FavoriteToggleDependencies = {
    access,
    async getSessionUserId() {
      calls.sessions += 1;
      return options.sessionUserId === undefined
        ? "user-1"
        : options.sessionUserId;
    },
    async findFavorite(userId, publicationId) {
      calls.lookups.push({ userId, publicationId });
      if (!options.existingFavoriteId) return null;

      const owner = options.existingFavoriteOwner ?? "user-1";
      return userId === owner ? { id: options.existingFavoriteId } : null;
    },
    async deleteFavorite(id) {
      calls.deletes.push(id);
    },
    async createFavorite(userId, publicationId) {
      calls.creates.push({ userId, publicationId });
    },
    revalidate(slug) {
      calls.revalidations.push(slug);
    },
    async findPublication(publicationId, slug) {
      calls.publications.push({ publicationId, slug });
      return options.publication === undefined
        ? { id: publicationId }
        : options.publication;
    },
  };

  return { deps, calls };
}

function withServiceAccess(
  service: ReturnType<typeof createFeatureService>,
  deps: FavoriteToggleDependencies
): FavoriteToggleDependencies {
  deps.access = {
    getPublicationFeatureState: (featureKey, publicationId) =>
      service.getPublicationFeatureState(featureKey, publicationId),
    logError() {},
  };
  return deps;
}

const input = { publicationId: "pub-1", slug: "tap-san" };

test("favorite enabled creates a favorite for the signed-in user", async () => {
  const { deps, calls } = createDeps({ favoriteEnabled: true });

  await runToggleFavorite(input, deps);

  assert.deepEqual(calls.featureKeys, ["favorite"]);
  assert.deepEqual(calls.publicationIds, ["pub-1"]);
  assert.deepEqual(calls.creates, [
    { userId: "user-1", publicationId: "pub-1" },
  ]);
  assert.deepEqual(calls.deletes, []);
  assert.deepEqual(calls.revalidations, ["tap-san"]);
});

test("favorite enabled removes the existing favorite", async () => {
  const { deps, calls } = createDeps({
    favoriteEnabled: true,
    existingFavoriteId: "favorite-1",
  });

  await runToggleFavorite(input, deps);

  assert.deepEqual(calls.deletes, ["favorite-1"]);
  assert.deepEqual(calls.creates, []);
  assert.deepEqual(calls.revalidations, ["tap-san"]);
});

test("favorite disabled blocks adding before auth and any write", async () => {
  const { deps, calls } = createDeps({ favoriteEnabled: false });

  await assert.rejects(
    () => runToggleFavorite(input, deps),
    (error: unknown) => {
      assert.ok(error instanceof PublicationFeatureDisabledError);
      assert.equal(error.featureKey, "favorite");
      assert.equal(error.message, FAVORITE_DISABLED_MESSAGE);
      return true;
    }
  );

  assert.equal(calls.sessions, 0);
  assert.deepEqual(calls.publications, []);
  assert.deepEqual(calls.creates, []);
  assert.deepEqual(calls.deletes, []);
});

test("favorite disabled blocks removing before auth and any write", async () => {
  const { deps, calls } = createDeps({
    favoriteEnabled: false,
    existingFavoriteId: "favorite-1",
  });

  await assert.rejects(
    () => runToggleFavorite(input, deps),
    PublicationFeatureDisabledError
  );

  assert.equal(calls.sessions, 0);
  assert.deepEqual(calls.lookups, []);
  assert.deepEqual(calls.deletes, []);
  assert.deepEqual(calls.creates, []);
});

test("feature flag database error fails closed and is logged", async () => {
  const { deps, calls } = createDeps({
    favoriteError: new Error("feature database unavailable"),
  });

  await assert.rejects(
    () => runToggleFavorite(input, deps),
    PublicationFeatureDisabledError
  );

  assert.equal(calls.featureErrors.length, 1);
  assert.equal(calls.sessions, 0);
  assert.deepEqual(calls.creates, []);
  assert.deepEqual(calls.deletes, []);
});

test("missing publication fails closed before any write", async () => {
  const { deps, calls } = createDeps({
    favoriteEnabled: true,
    publication: null,
  });

  await assert.rejects(
    () => runToggleFavorite(input, deps),
    /Không tìm thấy ấn phẩm/
  );

  assert.deepEqual(calls.publications, [
    { publicationId: "pub-1", slug: "tap-san" },
  ]);
  assert.deepEqual(calls.creates, []);
  assert.deepEqual(calls.deletes, []);
});

test("a missing publication is also rejected by the shared gate", async () => {
  const service = createFeatureService({
    dataSource: {
      async loadFlags() {
        return [{ key: "favorite", enabled: true }];
      },
      async loadPublication() {
        return { exists: false, overrides: [] };
      },
    },
    memoize: (loader) => loader,
  });

  const { deps, calls } = createDeps({ favoriteEnabled: true });
  withServiceAccess(service, deps);

  await assert.rejects(
    () => runToggleFavorite(input, deps),
    PublicationFeatureDisabledError
  );

  assert.equal(calls.sessions, 0);
  assert.deepEqual(calls.creates, []);
});

test("global OFF plus publication override ENABLED remains blocked", async () => {
  const service = createFeatureService({
    dataSource: {
      async loadFlags() {
        return [{ key: "favorite", enabled: false }];
      },
      async loadPublication() {
        return {
          exists: true,
          overrides: [{ featureKey: "favorite", state: "ENABLED" }],
        };
      },
    },
    memoize: (loader) => loader,
  });

  const { deps, calls } = createDeps({ favoriteEnabled: true });
  withServiceAccess(service, deps);

  await assert.rejects(
    () => runToggleFavorite(input, deps),
    PublicationFeatureDisabledError
  );

  assert.equal(calls.sessions, 0);
  assert.deepEqual(calls.creates, []);
});

test("publication override DISABLED blocks mutation", async () => {
  const service = createFeatureService({
    dataSource: {
      async loadFlags() {
        return [{ key: "favorite", enabled: true }];
      },
      async loadPublication() {
        return {
          exists: true,
          overrides: [{ featureKey: "favorite", state: "DISABLED" }],
        };
      },
    },
    memoize: (loader) => loader,
  });

  const { deps, calls } = createDeps({
    favoriteEnabled: true,
    existingFavoriteId: "favorite-1",
  });
  withServiceAccess(service, deps);

  await assert.rejects(
    () => runToggleFavorite(input, deps),
    PublicationFeatureDisabledError
  );

  assert.deepEqual(calls.deletes, []);
  assert.deepEqual(calls.creates, []);
});

test("publication override ENABLED works when global is enabled", async () => {
  const service = createFeatureService({
    dataSource: {
      async loadFlags() {
        return [{ key: "favorite", enabled: true }];
      },
      async loadPublication() {
        return {
          exists: true,
          overrides: [{ featureKey: "favorite", state: "ENABLED" }],
        };
      },
    },
    memoize: (loader) => loader,
  });

  const { deps, calls } = createDeps({ favoriteEnabled: true });
  withServiceAccess(service, deps);

  await runToggleFavorite(input, deps);

  assert.deepEqual(calls.creates, [
    { userId: "user-1", publicationId: "pub-1" },
  ]);
});

test("favorite still requires a session when enabled", async () => {
  const { deps, calls } = createDeps({
    favoriteEnabled: true,
    sessionUserId: null,
  });

  await assert.rejects(
    () => runToggleFavorite(input, deps),
    /Vui lòng đăng nhập để lưu ấn phẩm/
  );

  assert.deepEqual(calls.lookups, []);
  assert.deepEqual(calls.creates, []);
  assert.deepEqual(calls.deletes, []);
});

test("favorite ignores a request-supplied user id", async () => {
  const { deps, calls } = createDeps({ favoriteEnabled: true });

  const forgedInput = {
    ...input,
    userId: "victim",
  } as unknown as typeof input;

  await runToggleFavorite(forgedInput, deps);

  assert.deepEqual(calls.creates, [
    { userId: "user-1", publicationId: "pub-1" },
  ]);
  assert.deepEqual(calls.lookups, [
    { userId: "user-1", publicationId: "pub-1" },
  ]);
});

test("favorite ignores a forged ADMIN role", async () => {
  const { deps, calls } = createDeps({ favoriteEnabled: true });

  const forgedInput = {
    ...input,
    role: "ADMIN",
    isAdmin: true,
  } as unknown as typeof input;

  await runToggleFavorite(forgedInput, deps);

  assert.deepEqual(calls.creates, [
    { userId: "user-1", publicationId: "pub-1" },
  ]);
});

test("a user cannot delete another user's favorite by forging its id", async () => {
  const { deps, calls } = createDeps({
    favoriteEnabled: true,
    existingFavoriteId: "victim-favorite",
    existingFavoriteOwner: "victim",
  });

  const forgedInput = {
    ...input,
    favoriteId: "victim-favorite",
    userId: "victim",
  } as unknown as typeof input;

  await runToggleFavorite(forgedInput, deps);

  // The lookup is scoped to the session user, so the victim's favorite is not
  // found and nothing is deleted.
  assert.deepEqual(calls.lookups, [
    { userId: "user-1", publicationId: "pub-1" },
  ]);
  assert.deepEqual(calls.deletes, []);
  assert.deepEqual(calls.creates, [
    { userId: "user-1", publicationId: "pub-1" },
  ]);
});
