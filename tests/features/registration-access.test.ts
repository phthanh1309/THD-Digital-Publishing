import assert from "node:assert/strict";
import test from "node:test";
import {
  assertRegistrationAllowed,
  evaluateRegistrationAccess,
  RegistrationDisabledError,
  type RegistrationAccessDependencies,
} from "../../src/lib/features/registration-access";

type FakeOptions = {
  registerEnabled?: boolean;
  registerError?: Error;
};

function createDeps(options: FakeOptions = {}): {
  deps: RegistrationAccessDependencies;
  calls: { register: number; errors: string[] };
} {
  const calls = { register: 0, errors: [] as string[] };

  const deps: RegistrationAccessDependencies = {
    async isRegisterEnabled() {
      calls.register += 1;
      if (options.registerError) throw options.registerError;
      return options.registerEnabled ?? false;
    },
    logError(message) {
      calls.errors.push(message);
    },
  };

  return { deps, calls };
}

test("register enabled allows registration", async () => {
  const { deps, calls } = createDeps({ registerEnabled: true });
  assert.equal(await evaluateRegistrationAccess(deps), true);
  assert.equal(calls.register, 1);
});

test("register disabled denies registration", async () => {
  const { deps, calls } = createDeps({ registerEnabled: false });
  assert.equal(await evaluateRegistrationAccess(deps), false);
  assert.equal(calls.register, 1);
});

test("register flag error fails closed and logs", async () => {
  const err = new Error("feature database unavailable");
  const { deps, calls } = createDeps({ registerError: err });
  assert.equal(await evaluateRegistrationAccess(deps), false);
  assert.equal(calls.register, 1);
  assert.equal(calls.errors.length, 1);
});

test("assertRegistrationAllowed resolves when enabled", async () => {
  const { deps } = createDeps({ registerEnabled: true });
  await assertRegistrationAllowed(deps);
});

test("assertRegistrationAllowed throws when disabled", async () => {
  const { deps } = createDeps({ registerEnabled: false });
  await assert.rejects(() => assertRegistrationAllowed(deps), RegistrationDisabledError);
});

test("assertRegistrationAllowed throws when flag errors (fail closed)", async () => {
  const { deps, calls } = createDeps({ registerError: new Error("boom") });
  await assert.rejects(() => assertRegistrationAllowed(deps), RegistrationDisabledError);
  assert.equal(calls.errors.length, 1);
});
