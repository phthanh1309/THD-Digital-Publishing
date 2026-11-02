import assert from "node:assert/strict";
import test from "node:test";
import {
  createFeatureService,
  type FeatureDataSource,
  type Memoize,
} from "../../src/lib/features/service";
import type {
  FeatureFlagRecord,
  PublicationOverrideRecord,
} from "../../src/lib/features/types";

type FakeOptions = {
  flags?: FeatureFlagRecord[];
  flagsError?: Error;
  publications?: Record<
    string,
    { exists: boolean; overrides: PublicationOverrideRecord[] }
  >;
  publicationError?: Error;
};

type Calls = {
  flags: number;
  publications: string[];
};

function createFakeSource(options: FakeOptions = {}): {
  source: FeatureDataSource;
  calls: Calls;
} {
  const calls: Calls = { flags: 0, publications: [] };

  const source: FeatureDataSource = {
    async loadFlags() {
      calls.flags += 1;
      if (options.flagsError) throw options.flagsError;
      return options.flags ?? [];
    },
    async loadPublication(publicationId) {
      calls.publications.push(publicationId);
      if (options.publicationError) throw options.publicationError;
      return (
        options.publications?.[publicationId] ?? {
          exists: false,
          overrides: [],
        }
      );
    },
  };

  return { source, calls };
}

const requestMemoize: Memoize = <Args extends unknown[], Result>(
  loader: (...args: Args) => Promise<Result>
) => {
  const cache = new Map<string, Promise<Result>>();
  return (...args: Args): Promise<Result> => {
    const key = JSON.stringify(args);
    const cached = cache.get(key);
    if (cached) return cached;

    const result = loader(...args);
    cache.set(key, result);
    return result;
  };
};

const ALL_FLAGS: FeatureFlagRecord[] = [
  { key: "login", enabled: true },
  { key: "like", enabled: true },
  { key: "share", enabled: true },
];

test("deduplicates flag and publication reads within one service instance", async () => {
  const { source, calls } = createFakeSource({
    flags: ALL_FLAGS,
    publications: {
      pub1: { exists: true, overrides: [] },
    },
  });

  const service = createFeatureService({
    dataSource: source,
    memoize: requestMemoize,
  });

  await service.getGlobalFeatureState("login");
  await service.getGlobalFeatureState("like");
  await service.isFeatureEnabled("login");
  await service.isPublicationFeatureEnabled("like", "pub1");
  await service.getPublicationFeatureState("like", "pub1");
  await service.isPublicationFeatureEnabled("like", "pub1");

  assert.equal(calls.flags, 1);
  assert.deepEqual(calls.publications, ["pub1"]);
});

test("a new service instance performs fresh reads (no cross-request cache)", async () => {
  const { source, calls } = createFakeSource({ flags: ALL_FLAGS });

  const first = createFeatureService({
    dataSource: source,
    memoize: requestMemoize,
  });
  await first.isFeatureEnabled("login");
  assert.equal(calls.flags, 1);

  const second = createFeatureService({
    dataSource: source,
    memoize: requestMemoize,
  });
  await second.isFeatureEnabled("login");
  assert.equal(calls.flags, 2);
});

test("GLOBAL scope never loads publication data", async () => {
  const { source, calls } = createFakeSource({ flags: ALL_FLAGS });
  const service = createFeatureService({
    dataSource: source,
    memoize: requestMemoize,
  });

  const state = await service.getPublicationFeatureState("share", "pub1");
  assert.equal(state.enabled, true);
  assert.equal(state.source, "global");
  assert.equal(await service.isPublicationFeatureEnabled("share", "pub1"), true);

  assert.deepEqual(calls.publications, []);
});

test("unknown keys fail closed without any database read", async () => {
  const { source, calls } = createFakeSource({ flags: ALL_FLAGS });
  const service = createFeatureService({
    dataSource: source,
    memoize: requestMemoize,
  });

  const global = await service.getGlobalFeatureState("not-a-feature");
  assert.equal(global.enabled, false);
  assert.equal(global.source, "unknown-key");

  const scoped = await service.getPublicationFeatureState(
    "not-a-feature",
    "pub1"
  );
  assert.equal(scoped.enabled, false);
  assert.equal(scoped.source, "unknown-key");

  assert.equal(await service.isFeatureEnabled("not-a-feature"), false);
  assert.equal(
    await service.isPublicationFeatureEnabled("not-a-feature", "pub1"),
    false
  );

  assert.equal(calls.flags, 0);
  assert.deepEqual(calls.publications, []);
});

test("a registry key without a FeatureFlag row fails closed", async () => {
  const { source, calls } = createFakeSource({
    flags: [{ key: "login", enabled: true }],
  });
  const service = createFeatureService({
    dataSource: source,
    memoize: requestMemoize,
  });

  const global = await service.getGlobalFeatureState("like");
  assert.equal(global.enabled, false);
  assert.equal(global.source, "missing-flag");

  const scoped = await service.getPublicationFeatureState("like", "pub1");
  assert.equal(scoped.enabled, false);
  assert.equal(scoped.source, "missing-flag");
  assert.deepEqual(calls.publications, []);
});

test("flag database errors fail closed, log once, and skip publication reads", async () => {
  const { source, calls } = createFakeSource({
    flagsError: new Error("connection refused"),
  });
  const logs: string[] = [];
  const service = createFeatureService({
    dataSource: source,
    memoize: requestMemoize,
    logError: (message) => {
      logs.push(message);
    },
  });

  const global = await service.getGlobalFeatureState("login");
  assert.equal(global.enabled, false);
  assert.equal(global.source, "database-error");

  const scoped = await service.getPublicationFeatureState("like", "pub1");
  assert.equal(scoped.enabled, false);
  assert.equal(scoped.source, "database-error");

  assert.equal(calls.flags, 1);
  assert.deepEqual(calls.publications, []);
  assert.equal(logs.length, 1);
});

test("publication database errors fail closed and log", async () => {
  const { source, calls } = createFakeSource({
    flags: ALL_FLAGS,
    publicationError: new Error("timeout"),
  });
  const logs: string[] = [];
  const service = createFeatureService({
    dataSource: source,
    memoize: requestMemoize,
    logError: (message) => {
      logs.push(message);
    },
  });

  const state = await service.getPublicationFeatureState("like", "pub1");
  assert.equal(state.enabled, false);
  assert.equal(state.source, "database-error");

  assert.deepEqual(calls.publications, ["pub1"]);
  assert.equal(logs.length, 1);
});

test("a missing publication fails closed", async () => {
  const { source } = createFakeSource({ flags: ALL_FLAGS });
  const service = createFeatureService({
    dataSource: source,
    memoize: requestMemoize,
  });

  const state = await service.getPublicationFeatureState("like", "missing");
  assert.equal(state.enabled, false);
  assert.equal(state.source, "publication-not-found");
});

test("publication overrides are applied through the service", async () => {
  const { source } = createFakeSource({
    flags: ALL_FLAGS,
    publications: {
      disabled: {
        exists: true,
        overrides: [{ featureKey: "like", state: "DISABLED" }],
      },
      enabled: {
        exists: true,
        overrides: [{ featureKey: "like", state: "ENABLED" }],
      },
    },
  });
  const service = createFeatureService({
    dataSource: source,
    memoize: requestMemoize,
  });

  const disabled = await service.getPublicationFeatureState("like", "disabled");
  assert.equal(disabled.enabled, false);
  assert.equal(disabled.source, "override-disabled");
  assert.equal(disabled.override, "DISABLED");

  const enabled = await service.getPublicationFeatureState("like", "enabled");
  assert.equal(enabled.enabled, true);
  assert.equal(enabled.source, "override-enabled");
  assert.equal(enabled.override, "ENABLED");
});

test("global OFF wins an ENABLED publication override", async () => {
  const { source } = createFakeSource({
    flags: [{ key: "like", enabled: false }],
    publications: {
      pub1: {
        exists: true,
        overrides: [{ featureKey: "like", state: "ENABLED" }],
      },
    },
  });
  const service = createFeatureService({
    dataSource: source,
    memoize: requestMemoize,
  });

  const state = await service.getPublicationFeatureState("like", "pub1");
  assert.equal(state.enabled, false);
  assert.equal(state.source, "global-disabled");
});

test("service only accesses read methods on its data source", async () => {
  const { source } = createFakeSource({
    flags: ALL_FLAGS,
    publications: {
      pub1: { exists: true, overrides: [] },
    },
  });

  const guarded = new Proxy(source, {
    get(target, property, receiver) {
      if (property === "loadFlags" || property === "loadPublication") {
        return Reflect.get(target, property, receiver);
      }
      throw new Error(`Unexpected data source access: ${String(property)}`);
    },
  });

  const service = createFeatureService({
    dataSource: guarded,
    memoize: requestMemoize,
  });

  await service.getGlobalFeatureState("login");
  await service.getPublicationFeatureState("like", "pub1");
});
