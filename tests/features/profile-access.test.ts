import assert from "node:assert/strict";
import test from "node:test";
import {
  assertProfileEnabled,
  evaluateProfileAccess,
  ProfileDisabledError,
  type ProfileAccessDependencies,
} from "../../src/lib/features/profile-access";

type FakeOptions = {
  profileEnabled?: boolean;
  profileError?: Error;
};

function createDeps(options: FakeOptions = {}): {
  deps: ProfileAccessDependencies;
  calls: { profile: number; errors: string[] };
} {
  const calls = { profile: 0, errors: [] as string[] };

  const deps: ProfileAccessDependencies = {
    async isProfileEnabled() {
      calls.profile += 1;
      if (options.profileError) throw options.profileError;
      return options.profileEnabled ?? false;
    },
    logError(message) {
      calls.errors.push(message);
    },
  };

  return { deps, calls };
}

test("profile enabled allows profile access", async () => {
  const { deps, calls } = createDeps({ profileEnabled: true });
  assert.equal(await evaluateProfileAccess(deps), true);
  assert.equal(calls.profile, 1);
});

test("profile disabled denies profile access", async () => {
  const { deps, calls } = createDeps({ profileEnabled: false });
  assert.equal(await evaluateProfileAccess(deps), false);
  assert.equal(calls.profile, 1);
});

test("profile flag error fails closed and logs", async () => {
  const err = new Error("feature database unavailable");
  const { deps, calls } = createDeps({ profileError: err });
  assert.equal(await evaluateProfileAccess(deps), false);
  assert.equal(calls.profile, 1);
  assert.equal(calls.errors.length, 1);
});

test("assertProfileEnabled resolves when enabled", async () => {
  const { deps } = createDeps({ profileEnabled: true });
  await assertProfileEnabled(deps);
});

test("assertProfileEnabled throws when disabled", async () => {
  const { deps } = createDeps({ profileEnabled: false });
  await assert.rejects(() => assertProfileEnabled(deps), ProfileDisabledError);
});

test("assertProfileEnabled throws when flag errors (fail closed)", async () => {
  const { deps, calls } = createDeps({ profileError: new Error("boom") });
  await assert.rejects(() => assertProfileEnabled(deps), ProfileDisabledError);
  assert.equal(calls.errors.length, 1);
});
