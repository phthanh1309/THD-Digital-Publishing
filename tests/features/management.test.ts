import assert from "node:assert/strict";
import test from "node:test";
import {
  MANAGED_FEATURE_KEYS,
  getManagedFeatureDefinitions,
  isManagedFeatureKey,
  parseGlobalEnabled,
  parseOverrideState,
  runSetGlobalFeature,
  runSetPublicationOverride,
  setGlobalFeature,
  setPublicationOverride,
  type FeatureAuditRecord,
  type FeatureManagementDependencies,
  type FeatureManagementTransaction,
} from "../../src/lib/features/management";
import {
  loadAuditLogsForAdmin,
  type FeatureAuditLogEntry,
} from "../../src/lib/features/admin-data";
import { createFeatureService } from "../../src/lib/features/service";

type CommittedCall =
  | { type: "update"; key: string; enabled: boolean }
  | {
      type: "upsert";
      featureKey: string;
      publicationId: string;
      state: string;
    }
  | { type: "delete"; featureKey: string; publicationId: string };

type Calls = {
  requireAdmin: number;
  flagLookups: string[];
  publicationLookups: string[];
  overrideLookups: { featureKey: string; publicationId: string }[];
  committed: CommittedCall[];
  audits: FeatureAuditRecord[];
  revalidated: string[][];
};

type FakeOptions = {
  role?: "ADMIN" | "USER";
  flag?: { key: string; enabled: boolean } | null;
  publication?: { id: string; slug: string } | null;
  override?: { state: "INHERIT" | "ENABLED" | "DISABLED" } | null;
  failAudit?: boolean;
  failUpdate?: boolean;
};

function createDeps(options: FakeOptions = {}): {
  deps: FeatureManagementDependencies & {
    requireAdmin(): Promise<{ user: { id: string } }>;
  };
  calls: Calls;
} {
  const calls: Calls = {
    requireAdmin: 0,
    flagLookups: [],
    publicationLookups: [],
    overrideLookups: [],
    committed: [],
    audits: [],
    revalidated: [],
  };

  const deps = {
    async requireAdmin() {
      calls.requireAdmin += 1;
      if ((options.role ?? "ADMIN") !== "ADMIN") {
        throw new Error("Bạn không có quyền thực hiện thao tác này.");
      }
      return { user: { id: "admin-1" } };
    },

    async findFeatureFlag(key: string) {
      calls.flagLookups.push(key);
      if (options.flag === null) return null;
      return options.flag ?? { key, enabled: true };
    },

    async findPublication(publicationId: string) {
      calls.publicationLookups.push(publicationId);
      if (options.publication === null) return null;
      return options.publication ?? { id: publicationId, slug: "tap-san" };
    },

    async findOverride(featureKey: string, publicationId: string) {
      calls.overrideLookups.push({ featureKey, publicationId });
      return options.override ?? null;
    },

    async transaction<T>(
      work: (tx: FeatureManagementTransaction) => Promise<T>
    ): Promise<T> {
      const pending: CommittedCall[] = [];
      const audits: FeatureAuditRecord[] = [];

      const tx: FeatureManagementTransaction = {
        async updateFeatureFlag(key, enabled) {
          if (options.failUpdate) throw new Error("flag write failed");
          pending.push({ type: "update", key, enabled });
        },
        async upsertOverride(featureKey, publicationId, state) {
          pending.push({ type: "upsert", featureKey, publicationId, state });
        },
        async deleteOverride(featureKey, publicationId) {
          pending.push({ type: "delete", featureKey, publicationId });
        },
        async writeAudit(record) {
          if (options.failAudit) throw new Error("audit write failed");
          audits.push(record);
        },
      };

      const result = await work(tx);

      // Commit only when the whole unit of work succeeds, mirroring a real
      // database transaction.
      calls.committed.push(...pending);
      calls.audits.push(...audits);
      return result;
    },

    revalidate(paths: string[]) {
      calls.revalidated.push(paths);
    },
  };

  return { deps, calls };
}

test("managed keys are exactly the six roadmap features", () => {
  assert.deepEqual([...MANAGED_FEATURE_KEYS], [
    "login",
    "register",
    "profile",
    "like",
    "comment",
    "favorite",
  ]);

  for (const key of MANAGED_FEATURE_KEYS) {
    assert.equal(isManagedFeatureKey(key), true);
  }

  for (const key of ["history", "share", "download", "print"]) {
    assert.equal(isManagedFeatureKey(key), false);
  }

  assert.deepEqual(
    getManagedFeatureDefinitions().map((definition) => definition.key),
    [...MANAGED_FEATURE_KEYS]
  );
});

test("parseGlobalEnabled only accepts real booleans and exact strings", () => {
  assert.equal(parseGlobalEnabled(true), true);
  assert.equal(parseGlobalEnabled(false), false);
  assert.equal(parseGlobalEnabled("true"), true);
  assert.equal(parseGlobalEnabled("false"), false);

  for (const value of ["TRUE", "1", "0", "yes", "", null, undefined, 1]) {
    assert.equal(parseGlobalEnabled(value), null);
  }
});

test("parseOverrideState normalizes and rejects unknown states", () => {
  assert.equal(parseOverrideState("inherit"), "INHERIT");
  assert.equal(parseOverrideState(" enabled "), "ENABLED");
  assert.equal(parseOverrideState("DISABLED"), "DISABLED");

  for (const value of ["MAYBE", "", null, undefined, 1]) {
    assert.equal(parseOverrideState(value), null);
  }
});

test("ADMIN can toggle a global flag and gets an exact audit record", async () => {
  const { deps, calls } = createDeps({
    flag: { key: "like", enabled: true },
  });

  await runSetGlobalFeature(
    { featureKey: "like", enabled: "false" },
    deps
  );

  assert.equal(calls.requireAdmin, 1);
  assert.deepEqual(calls.flagLookups, ["like"]);
  assert.deepEqual(calls.committed, [
    { type: "update", key: "like", enabled: false },
  ]);
  assert.deepEqual(calls.audits, [
    {
      actorId: "admin-1",
      featureKey: "like",
      scope: "GLOBAL",
      publicationId: null,
      action: "SET_GLOBAL",
      oldValue: { enabled: true },
      newValue: { enabled: false },
    },
  ]);
  assert.deepEqual(calls.revalidated, [["/admin/features", "/"]]);
});

test("non-admin is rejected before any validation, read or write", async () => {
  const { deps, calls } = createDeps({ role: "USER" });

  await assert.rejects(
    () =>
      runSetGlobalFeature(
        { featureKey: "not-a-real-key", enabled: "not-a-boolean" },
        deps
      ),
    /không có quyền/
  );

  assert.equal(calls.requireAdmin, 1);
  assert.deepEqual(calls.flagLookups, []);
  assert.deepEqual(calls.committed, []);
  assert.deepEqual(calls.audits, []);
  assert.deepEqual(calls.revalidated, []);
});

test("a client-supplied actorId or role cannot change the audit actor", async () => {
  const { deps, calls } = createDeps();

  const forged = {
    featureKey: "comment",
    enabled: false,
    actorId: "victim",
    role: "ADMIN",
    userId: "victim",
  } as unknown as { featureKey: string; enabled: boolean };

  await runSetGlobalFeature(forged, deps);

  assert.equal(calls.audits[0]?.actorId, "admin-1");
});

test("a key outside the managed six is rejected", async () => {
  const { deps, calls } = createDeps();

  await assert.rejects(
    () => runSetGlobalFeature({ featureKey: "history", enabled: true }, deps),
    /không hợp lệ hoặc không được quản lý/
  );

  assert.deepEqual(calls.flagLookups, []);
  assert.deepEqual(calls.committed, []);
});

test("an invalid enabled value is rejected", async () => {
  const { deps, calls } = createDeps();

  await assert.rejects(
    () => runSetGlobalFeature({ featureKey: "login", enabled: "yes" }, deps),
    /Trạng thái Feature Flag không hợp lệ/
  );

  assert.deepEqual(calls.flagLookups, []);
  assert.deepEqual(calls.committed, []);
});

test("a missing FeatureFlag row fails closed without writing", async () => {
  const { deps, calls } = createDeps({ flag: null });

  await assert.rejects(
    () => runSetGlobalFeature({ featureKey: "login", enabled: true }, deps),
    /Không tìm thấy Feature Flag/
  );

  assert.deepEqual(calls.committed, []);
  assert.deepEqual(calls.audits, []);
  assert.deepEqual(calls.revalidated, []);
});

test("a failed audit write rolls the mutation back and reports failure", async () => {
  const { deps, calls } = createDeps({ failAudit: true });

  await assert.rejects(
    () => runSetGlobalFeature({ featureKey: "like", enabled: false }, deps),
    /audit write failed/
  );

  assert.deepEqual(calls.committed, []);
  assert.deepEqual(calls.audits, []);
  assert.deepEqual(calls.revalidated, []);
});

test("a failed flag write never records a success audit", async () => {
  const { deps, calls } = createDeps({ failUpdate: true });

  await assert.rejects(
    () => runSetGlobalFeature({ featureKey: "like", enabled: false }, deps),
    /flag write failed/
  );

  assert.deepEqual(calls.committed, []);
  assert.deepEqual(calls.audits, []);
  assert.deepEqual(calls.revalidated, []);
});

test("setting a publication override writes the override and an audit record", async () => {
  const { deps, calls } = createDeps({
    publication: { id: "pub-1", slug: "tap-san" },
    override: { state: "DISABLED" },
  });

  await runSetPublicationOverride(
    { featureKey: "comment", publicationId: "pub-1", state: "enabled" },
    deps
  );

  assert.deepEqual(calls.publicationLookups, ["pub-1"]);
  assert.deepEqual(calls.overrideLookups, [
    { featureKey: "comment", publicationId: "pub-1" },
  ]);
  assert.deepEqual(calls.committed, [
    {
      type: "upsert",
      featureKey: "comment",
      publicationId: "pub-1",
      state: "ENABLED",
    },
  ]);
  assert.deepEqual(calls.audits, [
    {
      actorId: "admin-1",
      featureKey: "comment",
      scope: "PUBLICATION",
      publicationId: "pub-1",
      action: "SET_OVERRIDE",
      oldValue: { state: "DISABLED" },
      newValue: { state: "ENABLED" },
    },
  ]);
  assert.deepEqual(calls.revalidated, [
    ["/admin/features", "/publications/tap-san"],
  ]);
});

test("INHERIT deletes an existing override and audits the reset", async () => {
  const { deps, calls } = createDeps({
    override: { state: "ENABLED" },
  });

  await runSetPublicationOverride(
    { featureKey: "favorite", publicationId: "pub-1", state: "INHERIT" },
    deps
  );

  assert.deepEqual(calls.committed, [
    {
      type: "delete",
      featureKey: "favorite",
      publicationId: "pub-1",
    },
  ]);
  assert.deepEqual(calls.audits, [
    {
      actorId: "admin-1",
      featureKey: "favorite",
      scope: "PUBLICATION",
      publicationId: "pub-1",
      action: "RESET_OVERRIDE",
      oldValue: { state: "ENABLED" },
      newValue: { state: "INHERIT" },
    },
  ]);
});

test("INHERIT is a no-op when no override row exists", async () => {
  const { deps, calls } = createDeps({ override: null });

  await runSetPublicationOverride(
    { featureKey: "favorite", publicationId: "pub-1", state: "INHERIT" },
    deps
  );

  assert.deepEqual(calls.committed, []);
  assert.deepEqual(calls.audits, []);
  assert.deepEqual(calls.revalidated, []);
});

test("GLOBAL-scope features reject publication overrides", async () => {
  const { deps, calls } = createDeps();

  for (const featureKey of ["login", "register", "profile"]) {
    await assert.rejects(
      () =>
        runSetPublicationOverride(
          { featureKey, publicationId: "pub-1", state: "ENABLED" },
          deps
        ),
      /chỉ áp dụng toàn hệ thống/
    );
  }

  assert.deepEqual(calls.publicationLookups, []);
  assert.deepEqual(calls.committed, []);
});

test("a missing publication is rejected before any override write", async () => {
  const { deps, calls } = createDeps({ publication: null });

  await assert.rejects(
    () =>
      runSetPublicationOverride(
        { featureKey: "like", publicationId: "missing", state: "ENABLED" },
        deps
      ),
    /Không tìm thấy ấn phẩm/
  );

  assert.deepEqual(calls.overrideLookups, []);
  assert.deepEqual(calls.committed, []);
});

test("an empty publication id and an invalid state are rejected", async () => {
  const { deps, calls } = createDeps();

  await assert.rejects(
    () =>
      runSetPublicationOverride(
        { featureKey: "like", publicationId: "  ", state: "ENABLED" },
        deps
      ),
    /Thiếu ấn phẩm/
  );

  await assert.rejects(
    () =>
      runSetPublicationOverride(
        { featureKey: "like", publicationId: "pub-1", state: "MAYBE" },
        deps
      ),
    /Trạng thái override không hợp lệ/
  );

  assert.deepEqual(calls.publicationLookups, []);
  assert.deepEqual(calls.committed, []);
});

test("low-level setGlobalFeature still validates input for direct callers", async () => {
  const { deps, calls } = createDeps();

  await assert.rejects(
    () => setGlobalFeature({ featureKey: "nope", enabled: true }, deps),
    /không hợp lệ hoặc không được quản lý/
  );
  await assert.rejects(
    () => setGlobalFeature({ featureKey: "like", enabled: "true" as never }, deps),
    /Trạng thái Feature Flag không hợp lệ/
  );

  assert.deepEqual(calls.committed, []);
});

test("low-level setPublicationOverride still validates input for direct callers", async () => {
  const { deps, calls } = createDeps();

  await assert.rejects(
    () =>
      setPublicationOverride(
        { featureKey: "nope", publicationId: "pub-1", state: "ENABLED" },
        deps
      ),
    /không hợp lệ hoặc không được quản lý/
  );

  assert.deepEqual(calls.committed, []);
});

test("global OFF wins over a publication override ENABLED", async () => {
  const service = createFeatureService({
    dataSource: {
      async loadFlags() {
        return [{ key: "like", enabled: false }];
      },
      async loadPublication() {
        return {
          exists: true,
          overrides: [{ featureKey: "like", state: "ENABLED" }],
        };
      },
    },
    memoize: (loader) => loader,
  });

  const state = await service.getPublicationFeatureState("like", "pub-1");

  assert.equal(state.globalEnabled, false);
  assert.equal(state.override, "ENABLED");
  assert.equal(state.enabled, false);
  assert.equal(state.source, "global-disabled");
});

test("INHERIT follows the global state", async () => {
  const enabledService = createFeatureService({
    dataSource: {
      async loadFlags() {
        return [{ key: "favorite", enabled: true }];
      },
      async loadPublication() {
        return { exists: true, overrides: [] };
      },
    },
    memoize: (loader) => loader,
  });

  const enabled = await enabledService.getPublicationFeatureState(
    "favorite",
    "pub-1"
  );
  assert.equal(enabled.enabled, true);
  assert.equal(enabled.source, "inherit");

  const disabledService = createFeatureService({
    dataSource: {
      async loadFlags() {
        return [{ key: "favorite", enabled: false }];
      },
      async loadPublication() {
        return { exists: true, overrides: [] };
      },
    },
    memoize: (loader) => loader,
  });

  const disabled = await disabledService.getPublicationFeatureState(
    "favorite",
    "pub-1"
  );
  assert.equal(disabled.enabled, false);
  assert.equal(disabled.source, "global-disabled");
});

test("audit logs are not read for a non-admin caller", async () => {
  let fetched = false;

  await assert.rejects(
    () =>
      loadAuditLogsForAdmin({
        async requireAdmin() {
          throw new Error("Bạn không có quyền thực hiện thao tác này.");
        },
        async fetchAuditLogs() {
          fetched = true;
          return [];
        },
      }),
    /không có quyền/
  );

  assert.equal(fetched, false);
});

test("audit logs are returned to an admin caller with the requested limit", async () => {
  const entry: FeatureAuditLogEntry = {
    id: "audit-1",
    createdAt: new Date("2026-01-01T00:00:00Z"),
    actorId: "admin-1",
    actorName: "Admin",
    actorUsername: "admin",
    featureKey: "like",
    scope: "GLOBAL",
    publicationId: null,
    publicationTitle: null,
    publicationSlug: null,
    action: "SET_GLOBAL",
    oldValue: { enabled: true },
    newValue: { enabled: false },
  };

  let requestedLimit = 0;
  const logs = await loadAuditLogsForAdmin(
    {
      async requireAdmin() {
        return { user: { id: "admin-1" } };
      },
      async fetchAuditLogs(limit) {
        requestedLimit = limit;
        return [entry];
      },
    },
    5
  );

  assert.equal(requestedLimit, 5);
  assert.deepEqual(logs, [entry]);
});
