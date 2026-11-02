import assert from "node:assert/strict";
import test from "node:test";
import {
  evaluateSignInAccess,
  type SignInAccessDependencies,
  type SignInIdentity,
} from "../../src/lib/features/sign-in-access";

type FakeOptions = {
  loginEnabled?: boolean;
  loginError?: Error;
  userRoles?: Record<string, string>;
  userError?: Error;
  oauthRoles?: Record<string, string>;
  oauthError?: Error;
};

function createDeps(options: FakeOptions = {}): {
  deps: SignInAccessDependencies;
  calls: {
    login: number;
    users: string[];
    oauth: string[];
    errors: string[];
  };
} {
  const calls = {
    login: 0,
    users: [] as string[],
    oauth: [] as string[],
    errors: [] as string[],
  };

  const deps: SignInAccessDependencies = {
    async isLoginEnabled() {
      calls.login += 1;
      if (options.loginError) throw options.loginError;
      return options.loginEnabled ?? false;
    },
    async findUserRoleById(userId) {
      calls.users.push(userId);
      if (options.userError) throw options.userError;
      return options.userRoles?.[userId] ?? null;
    },
    async findOAuthUserRole(provider, providerAccountId) {
      calls.oauth.push(`${provider}:${providerAccountId}`);
      if (options.oauthError) throw options.oauthError;
      return options.oauthRoles?.[`${provider}:${providerAccountId}`] ?? null;
    },
    logError(message) {
      calls.errors.push(message);
    },
  };

  return { deps, calls };
}

const credentialsUser: SignInIdentity = {
  kind: "credentials",
  userId: "user-1",
};

const oauthUser: SignInIdentity = {
  kind: "oauth",
  provider: "google",
  providerAccountId: "google-account-1",
};

test("login enabled allows an ordinary Credentials user", async () => {
  const { deps, calls } = createDeps({
    loginEnabled: true,
    userRoles: { "user-1": "USER" },
  });

  const allowed = await evaluateSignInAccess(credentialsUser, deps);

  assert.equal(allowed, true);
  // The enabled path must not add a role lookup to the normal sign-in flow.
  assert.deepEqual(calls.users, []);
  assert.deepEqual(calls.oauth, []);
});

test("login enabled allows an ADMIN Credentials user", async () => {
  const { deps } = createDeps({
    loginEnabled: true,
    userRoles: { "user-1": "ADMIN" },
  });

  assert.equal(await evaluateSignInAccess(credentialsUser, deps), true);
});

test("login disabled denies an ordinary Credentials user", async () => {
  const { deps, calls } = createDeps({
    loginEnabled: false,
    userRoles: { "user-1": "USER" },
  });

  const allowed = await evaluateSignInAccess(credentialsUser, deps);

  assert.equal(allowed, false);
  assert.deepEqual(calls.users, ["user-1"]);
  assert.deepEqual(calls.oauth, []);
});

test("login disabled allows an ADMIN Credentials user", async () => {
  const { deps } = createDeps({
    loginEnabled: false,
    userRoles: { "user-1": "ADMIN" },
  });

  assert.equal(await evaluateSignInAccess(credentialsUser, deps), true);
});

test("login disabled denies an existing ordinary OAuth user", async () => {
  const { deps, calls } = createDeps({
    loginEnabled: false,
    oauthRoles: { "google:google-account-1": "USER" },
  });

  const allowed = await evaluateSignInAccess(oauthUser, deps);

  assert.equal(allowed, false);
  assert.deepEqual(calls.oauth, ["google:google-account-1"]);
  assert.deepEqual(calls.users, []);
});

test("login disabled allows an existing ADMIN OAuth user", async () => {
  const { deps } = createDeps({
    loginEnabled: false,
    oauthRoles: { "google:google-account-1": "ADMIN" },
  });

  assert.equal(await evaluateSignInAccess(oauthUser, deps), true);
});

test("login disabled denies a new OAuth identity", async () => {
  // Auth.js calls the signIn callback before adapter.createUser, so a new
  // identity has no Account row to resolve. It must not inherit ADMIN from
  // provider profile fields.
  const { deps, calls } = createDeps({ loginEnabled: false });

  const allowed = await evaluateSignInAccess(oauthUser, deps);

  assert.equal(allowed, false);
  assert.deepEqual(calls.oauth, ["google:google-account-1"]);
  assert.equal(calls.errors.length, 0);
});

test("login disabled fails closed when the feature service throws", async () => {
  const { deps, calls } = createDeps({
    loginError: new Error("feature database unavailable"),
    userRoles: { "user-1": "ADMIN" },
  });

  const allowed = await evaluateSignInAccess(credentialsUser, deps);

  assert.equal(allowed, false);
  assert.deepEqual(calls.users, []);
  assert.equal(calls.errors.length, 1);
});

test("login disabled fails closed on a Credentials role lookup error", async () => {
  const { deps, calls } = createDeps({
    loginEnabled: false,
    userError: new Error("user table unavailable"),
  });

  const allowed = await evaluateSignInAccess(credentialsUser, deps);

  assert.equal(allowed, false);
  assert.deepEqual(calls.users, ["user-1"]);
  assert.equal(calls.errors.length, 1);
});

test("login disabled fails closed on an OAuth account lookup error", async () => {
  const { deps, calls } = createDeps({
    loginEnabled: false,
    oauthError: new Error("account table unavailable"),
  });

  const allowed = await evaluateSignInAccess(oauthUser, deps);

  assert.equal(allowed, false);
  assert.deepEqual(calls.oauth, ["google:google-account-1"]);
  assert.equal(calls.errors.length, 1);
});

test("login enabled leaves existing sign-in and sessions untouched", async () => {
  // The gate runs only inside the signIn callback and returns early when the
  // feature is enabled. It never reads or writes a session, and it does not
  // perform role lookups, so existing JWT sessions remain valid.
  const identity = Object.freeze({ ...credentialsUser });
  const { deps, calls } = createDeps({
    loginEnabled: true,
    userError: new Error("role lookup must not run while login is enabled"),
  });

  const allowed = await evaluateSignInAccess(identity, deps);

  assert.equal(allowed, true);
  assert.deepEqual(calls.users, []);
  assert.deepEqual(calls.oauth, []);
  assert.equal(calls.login, 1);
});
