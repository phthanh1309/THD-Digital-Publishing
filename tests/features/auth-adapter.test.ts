import assert from "node:assert/strict";
import test from "node:test";
import type { AdapterUser } from "next-auth/adapters";
import {
  createOAuthUser,
  type OAuthUserCreationDependencies,
} from "../../src/lib/auth-adapter";
import { RegistrationDisabledError } from "../../src/lib/features/registration-access";
import { evaluateSignInAccess } from "../../src/lib/features/sign-in-access";

type FakeOptions = {
  registerEnabled?: boolean;
  registerError?: Error;
  existingUsernames?: string[];
};

type Calls = {
  register: number;
  usernameLookups: string[];
  created: unknown[];
  errors: string[];
};

function createDeps(options: FakeOptions = {}): {
  deps: OAuthUserCreationDependencies;
  calls: Calls;
} {
  const calls: Calls = {
    register: 0,
    usernameLookups: [],
    created: [],
    errors: [],
  };

  const taken = new Set(options.existingUsernames ?? []);

  const deps: OAuthUserCreationDependencies = {
    access: {
      async isRegisterEnabled() {
        calls.register += 1;
        if (options.registerError) throw options.registerError;
        return options.registerEnabled ?? false;
      },
      logError(message) {
        calls.errors.push(message);
      },
    },
    async findUserByUsername(username) {
      calls.usernameLookups.push(username);
      return taken.has(username) ? { id: "existing" } : null;
    },
    async createUser(data) {
      calls.created.push(data);
      return {
        id: "user-1",
        name: data.name ?? null,
        email: data.email,
        emailVerified: data.emailVerified,
        image: data.image ?? null,
      };
    },
  };

  return { deps, calls };
}

const oauthData: Omit<AdapterUser, "id"> = {
  email: "new.user@example.com",
  name: "New User",
  emailVerified: null,
  image: null,
};

test("register enabled creates a new OAuth user with role USER", async () => {
  const { deps, calls } = createDeps({ registerEnabled: true });

  const user = await createOAuthUser(oauthData, deps);

  assert.equal(user.id, "user-1");
  assert.deepEqual(calls.created, [
    {
      username: "newuser",
      name: "New User",
      email: "new.user@example.com",
      emailVerified: null,
      image: null,
      passwordHash: "",
      role: "USER",
    },
  ]);
});

test("register enabled keeps usernames unique", async () => {
  const { deps, calls } = createDeps({
    registerEnabled: true,
    existingUsernames: ["newuser", "newuser2"],
  });

  await createOAuthUser(oauthData, deps);

  assert.deepEqual(calls.usernameLookups, ["newuser", "newuser2", "newuser3"]);
  assert.equal((calls.created[0] as { username: string }).username, "newuser3");
});

test("register disabled rejects a new OAuth user before any lookup/write", async () => {
  const { deps, calls } = createDeps({ registerEnabled: false });

  await assert.rejects(
    () => createOAuthUser(oauthData, deps),
    RegistrationDisabledError
  );

  assert.deepEqual(calls.usernameLookups, []);
  assert.deepEqual(calls.created, []);
});

test("register flag error rejects a new OAuth user (fail closed)", async () => {
  const { deps, calls } = createDeps({
    registerError: new Error("feature database unavailable"),
  });

  await assert.rejects(
    () => createOAuthUser(oauthData, deps),
    RegistrationDisabledError
  );

  assert.equal(calls.errors.length, 1);
  assert.deepEqual(calls.created, []);
});

test("register disabled does not affect existing OAuth users", async () => {
  // Auth.js resolves existing users through getUser/getUserByAccount; it never
  // calls adapter.createUser for them. The sign-in gate therefore stays the
  // only authority for existing identities.
  const identity = {
    kind: "oauth" as const,
    provider: "google",
    providerAccountId: "google-1",
  };

  const allowed = await evaluateSignInAccess(identity, {
    isLoginEnabled: async () => true,
    findUserRoleById: async () => null,
    findOAuthUserRole: async () => "USER",
  });

  assert.equal(allowed, true);
});

test("ADMIN authentication remains possible while login is disabled", async () => {
  const allowed = await evaluateSignInAccess(
    { kind: "credentials", userId: "admin-1" },
    {
      isLoginEnabled: async () => false,
      findUserRoleById: async () => "ADMIN",
      findOAuthUserRole: async () => null,
    }
  );

  assert.equal(allowed, true);
});

test("ADMIN OAuth authentication remains possible while login is disabled", async () => {
  const allowed = await evaluateSignInAccess(
    {
      kind: "oauth",
      provider: "google",
      providerAccountId: "google-admin",
    },
    {
      isLoginEnabled: async () => false,
      findUserRoleById: async () => null,
      findOAuthUserRole: async () => "ADMIN",
    }
  );

  assert.equal(allowed, true);
});
