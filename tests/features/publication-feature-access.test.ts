import assert from "node:assert/strict";
import test from "node:test";
import {
  assertPublicationFeatureEnabled,
  evaluatePublicationFeatureAccess,
  PublicationFeatureDisabledError,
  type PublicationFeatureAccessDependencies,
} from "../../src/lib/features/publication-feature-access";
import { createFeatureService } from "../../src/lib/features/service";
import type { PublicationFeatureSource } from "../../src/lib/features/types";

type FakeOptions = {
  enabled?: boolean;
  source?: PublicationFeatureSource;
  error?: Error;
};

function createDeps(options: FakeOptions = {}): {
  deps: PublicationFeatureAccessDependencies;
  calls: { keys: string[]; ids: string[]; errors: string[] };
} {
  const calls = {
    keys: [] as string[],
    ids: [] as string[],
    errors: [] as string[],
  };

  const deps: PublicationFeatureAccessDependencies = {
    async getPublicationFeatureState(featureKey, publicationId) {
      calls.keys.push(featureKey);
      calls.ids.push(publicationId);
      if (options.error) throw options.error;

      return {
        key: featureKey,
        name: null,
        scope: "GLOBAL_AND_PUBLICATION",
        globalEnabled: options.enabled ?? false,
        override: null,
        enabled: options.enabled ?? false,
        source: options.source ?? "global",
      };
    },
    logError(message) {
      calls.errors.push(message);
    },
  };

  return { deps, calls };
}

test("publication feature enabled allows access", async () => {
  const { deps, calls } = createDeps({ enabled: true });

  const decision = await evaluatePublicationFeatureAccess(
    "like",
    "pub-1",
    deps
  );

  assert.equal(decision.enabled, true);
  assert.deepEqual(calls.keys, ["like"]);
  assert.deepEqual(calls.ids, ["pub-1"]);
});

test("publication feature disabled denies access", async () => {
  const { deps } = createDeps({ enabled: false });

  const decision = await evaluatePublicationFeatureAccess(
    "like",
    "pub-1",
    deps
  );

  assert.equal(decision.enabled, false);
});

test("publication feature error fails closed and logs", async () => {
  const { deps, calls } = createDeps({ error: new Error("database down") });

  const decision = await evaluatePublicationFeatureAccess(
    "like",
    "pub-1",
    deps
  );

  assert.equal(decision.enabled, false);
  assert.equal(decision.source, "database-error");
  assert.equal(calls.errors.length, 1);
});

test("the shared gate works for every GLOBAL_AND_PUBLICATION key", async () => {
  const { deps, calls } = createDeps({ enabled: true });

  for (const key of ["like", "comment", "favorite", "history"]) {
    const decision = await evaluatePublicationFeatureAccess(key, "pub-1", deps);
    assert.equal(decision.enabled, true);
  }

  assert.deepEqual(calls.keys, ["like", "comment", "favorite", "history"]);
});

test("assertPublicationFeatureEnabled resolves when enabled", async () => {
  const { deps } = createDeps({ enabled: true });

  await assertPublicationFeatureEnabled("favorite", "pub-1", deps);
});

test("assertPublicationFeatureEnabled throws when disabled", async () => {
  const { deps } = createDeps({ enabled: false });

  await assert.rejects(
    () => assertPublicationFeatureEnabled("favorite", "pub-1", deps),
    PublicationFeatureDisabledError
  );
});

test("assertPublicationFeatureEnabled fails closed on error", async () => {
  const { deps, calls } = createDeps({ error: new Error("boom") });

  await assert.rejects(
    () => assertPublicationFeatureEnabled("history", "pub-1", deps),
    PublicationFeatureDisabledError
  );
  assert.equal(calls.errors.length, 1);
});

test("shared gate respects a DISABLED publication override", async () => {
  const service = createFeatureService({
    dataSource: {
      async loadFlags() {
        return [{ key: "like", enabled: true }];
      },
      async loadPublication() {
        return {
          exists: true,
          overrides: [{ featureKey: "like", state: "DISABLED" as const }],
        };
      },
    },
    memoize: (loader) => loader,
  });

  const decision = await evaluatePublicationFeatureAccess("like", "pub-1", {
    getPublicationFeatureState: service.getPublicationFeatureState,
  });

  assert.equal(decision.enabled, false);
  assert.equal(decision.source, "override-disabled");
});

test("shared gate cannot be revived by an ENABLED override when global is OFF", async () => {
  const service = createFeatureService({
    dataSource: {
      async loadFlags() {
        return [{ key: "like", enabled: false }];
      },
      async loadPublication() {
        return {
          exists: true,
          overrides: [{ featureKey: "like", state: "ENABLED" as const }],
        };
      },
    },
    memoize: (loader) => loader,
  });

  const decision = await evaluatePublicationFeatureAccess("like", "pub-1", {
    getPublicationFeatureState: service.getPublicationFeatureState,
  });

  assert.equal(decision.enabled, false);
  assert.equal(decision.source, "global-disabled");
});

test("assertPublicationFeatureEnabled keeps a feature-specific message", async () => {
  const { deps } = createDeps({ enabled: false });

  await assert.rejects(
    () =>
      assertPublicationFeatureEnabled("like", "pub-1", deps, {
        message: "Like đang tạm khóa.",
      }),
    (error: unknown) => {
      assert.ok(error instanceof PublicationFeatureDisabledError);
      assert.equal(error.featureKey, "like");
      assert.equal(error.message, "Like đang tạm khóa.");
      return true;
    }
  );
});
