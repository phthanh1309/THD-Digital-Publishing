/**
 * Server-side write workflows for Feature Flag administration.
 *
 * The read/resolve path lives in `service.ts`/`resolver.ts`; this module only
 * validates admin input and applies global/override mutations together with an
 * audit record. Every mutation runs inside a caller-provided transaction so a
 * failed audit write rolls the flag change back and a failed flag change never
 * leaves a success audit behind.
 */

import { FEATURES, getFeatureDefinition } from "./keys";
import type { FeatureOverrideState } from "./types";

export const MANAGED_FEATURE_KEYS = [
  "login",
  "register",
  "profile",
  "like",
  "comment",
  "favorite",
] as const;

export type ManagedFeatureKey = (typeof MANAGED_FEATURE_KEYS)[number];

const MANAGED_FEATURE_KEY_SET = new Set<string>(MANAGED_FEATURE_KEYS);

export const FEATURE_OVERRIDE_STATES = [
  "INHERIT",
  "ENABLED",
  "DISABLED",
] as const satisfies readonly FeatureOverrideState[];

export type FeatureAuditAction =
  | "SET_GLOBAL"
  | "SET_OVERRIDE"
  | "RESET_OVERRIDE";

export type FeatureAuditScope = "GLOBAL" | "PUBLICATION";

export type FeatureAuditRecord = {
  actorId: string | null;
  featureKey: string;
  scope: FeatureAuditScope;
  publicationId: string | null;
  action: FeatureAuditAction;
  oldValue: unknown;
  newValue: unknown;
};

export type FeatureFlagSnapshot = {
  key: string;
  enabled: boolean;
};

export type PublicationSnapshot = {
  id: string;
  slug?: string;
};

export type FeatureManagementTransaction = {
  updateFeatureFlag(key: string, enabled: boolean): Promise<void>;
  upsertOverride(
    featureKey: string,
    publicationId: string,
    state: Exclude<FeatureOverrideState, "INHERIT">
  ): Promise<void>;
  deleteOverride(featureKey: string, publicationId: string): Promise<void>;
  writeAudit(record: FeatureAuditRecord): Promise<void>;
};

export type FeatureManagementDependencies = {
  findFeatureFlag(key: string): Promise<FeatureFlagSnapshot | null>;
  findPublication(publicationId: string): Promise<PublicationSnapshot | null>;
  findOverride(
    featureKey: string,
    publicationId: string
  ): Promise<{ state: FeatureOverrideState } | null>;
  transaction<T>(
    work: (tx: FeatureManagementTransaction) => Promise<T>
  ): Promise<T>;
  revalidate?(paths: string[]): void | Promise<void>;
};

export type SetGlobalFeatureInput = {
  featureKey: string;
  enabled: boolean;
  actorId?: string | null;
};

export type SetPublicationOverrideInput = {
  featureKey: string;
  publicationId: string;
  state: string;
  actorId?: string | null;
};

export function isManagedFeatureKey(
  value: unknown
): value is ManagedFeatureKey {
  return typeof value === "string" && MANAGED_FEATURE_KEY_SET.has(value);
}

export function isFeatureOverrideState(
  value: unknown
): value is FeatureOverrideState {
  return (
    typeof value === "string" &&
    (FEATURE_OVERRIDE_STATES as readonly string[]).includes(value)
  );
}

export function getManagedFeatureDefinitions() {
  return MANAGED_FEATURE_KEYS.map((key) => ({
    key,
    ...FEATURES[key],
  }));
}

/**
 * Parse the toggle value coming from an admin form. Only real booleans and the
 * exact strings "true"/"false" are accepted; everything else is rejected so a
 * malformed request can never silently disable a feature.
 */
export function parseGlobalEnabled(value: unknown): boolean | null {
  if (value === true || value === "true") return true;
  if (value === false || value === "false") return false;
  return null;
}

export function parseOverrideState(value: unknown): FeatureOverrideState | null {
  if (typeof value !== "string") return null;
  const state = value.trim().toUpperCase();
  return isFeatureOverrideState(state) ? state : null;
}

function assertManagedFeatureKey(value: string): asserts value is ManagedFeatureKey {
  if (!isManagedFeatureKey(value)) {
    throw new Error("Feature Flag không hợp lệ hoặc không được quản lý.");
  }
}

export async function setGlobalFeature(
  input: SetGlobalFeatureInput,
  deps: FeatureManagementDependencies
): Promise<void> {
  const featureKey = String(input.featureKey ?? "").trim();
  assertManagedFeatureKey(featureKey);

  if (typeof input.enabled !== "boolean") {
    throw new Error("Trạng thái Feature Flag không hợp lệ.");
  }

  const existing = await deps.findFeatureFlag(featureKey);
  if (!existing) {
    throw new Error("Không tìm thấy Feature Flag trong hệ thống.");
  }

  await deps.transaction(async (tx) => {
    await tx.updateFeatureFlag(featureKey, input.enabled);
    await tx.writeAudit({
      actorId: input.actorId ?? null,
      featureKey,
      scope: "GLOBAL",
      publicationId: null,
      action: "SET_GLOBAL",
      oldValue: { enabled: existing.enabled },
      newValue: { enabled: input.enabled },
    });
  });

  await deps.revalidate?.(["/admin/features", "/"]);
}

export async function setPublicationOverride(
  input: SetPublicationOverrideInput,
  deps: FeatureManagementDependencies
): Promise<void> {
  const featureKey = String(input.featureKey ?? "").trim();
  assertManagedFeatureKey(featureKey);

  const publicationId = String(input.publicationId ?? "").trim();
  if (!publicationId) {
    throw new Error("Thiếu ấn phẩm cần cập nhật.");
  }

  const state = String(input.state ?? "").trim().toUpperCase();
  if (!isFeatureOverrideState(state)) {
    throw new Error("Trạng thái override không hợp lệ.");
  }

  const definition = getFeatureDefinition(featureKey);
  if (definition?.scope === "GLOBAL" && state !== "INHERIT") {
    throw new Error("Feature Flag này chỉ áp dụng toàn hệ thống.");
  }

  const publication = await deps.findPublication(publicationId);
  if (!publication) {
    throw new Error("Không tìm thấy ấn phẩm.");
  }

  const existing = await deps.findOverride(featureKey, publicationId);

  if (state === "INHERIT") {
    if (!existing) return;

    await deps.transaction(async (tx) => {
      await tx.deleteOverride(featureKey, publicationId);
      await tx.writeAudit({
        actorId: input.actorId ?? null,
        featureKey,
        scope: "PUBLICATION",
        publicationId,
        action: "RESET_OVERRIDE",
        oldValue: { state: existing.state },
        newValue: { state: "INHERIT" },
      });
    });
  } else {
    await deps.transaction(async (tx) => {
      await tx.upsertOverride(featureKey, publicationId, state);
      await tx.writeAudit({
        actorId: input.actorId ?? null,
        featureKey,
        scope: "PUBLICATION",
        publicationId,
        action: "SET_OVERRIDE",
        oldValue: existing ? { state: existing.state } : null,
        newValue: { state },
      });
    });
  }

  const publicationPath = publication.slug
    ? `/publications/${publication.slug}`
    : null;

  await deps.revalidate?.(
    publicationPath
      ? ["/admin/features", publicationPath]
      : ["/admin/features"]
  );
}

export function getFeatureDefinitionForManagement(key: string) {
  return isManagedFeatureKey(key) ? getFeatureDefinition(key) : null;
}

/**
 * Admin-session shape needed by the write workflow. Only the server session
 * user id is used; any `id`/`role` supplied by the client is ignored because it
 * is not part of the input objects.
 */
export type FeatureAdminSession = {
  user?: { id?: string | null } | null;
};

export type FeatureAdminGuard = {
  requireAdmin(): Promise<FeatureAdminSession>;
};

export type RunSetGlobalFeatureDependencies = FeatureManagementDependencies &
  FeatureAdminGuard;

export type RunSetPublicationOverrideDependencies =
  FeatureManagementDependencies & FeatureAdminGuard;

/**
 * Authorize first, then validate, then mutate. Authorization happens before any
 * validation or database read so a non-admin caller cannot use error messages
 * to probe the feature registry.
 */
export async function runSetGlobalFeature(
  input: { featureKey: unknown; enabled: unknown },
  deps: RunSetGlobalFeatureDependencies
): Promise<void> {
  const session = await deps.requireAdmin();

  const featureKey = String(input.featureKey ?? "").trim();
  assertManagedFeatureKey(featureKey);

  const enabled = parseGlobalEnabled(input.enabled);
  if (enabled === null) {
    throw new Error("Trạng thái Feature Flag không hợp lệ.");
  }

  await setGlobalFeature(
    {
      featureKey,
      enabled,
      actorId: session.user?.id ?? null,
    },
    deps
  );
}

export async function runSetPublicationOverride(
  input: { featureKey: unknown; publicationId: unknown; state: unknown },
  deps: RunSetPublicationOverrideDependencies
): Promise<void> {
  const session = await deps.requireAdmin();

  const featureKey = String(input.featureKey ?? "").trim();
  assertManagedFeatureKey(featureKey);

  const publicationId = String(input.publicationId ?? "").trim();
  if (!publicationId) {
    throw new Error("Thiếu ấn phẩm cần cập nhật.");
  }

  const state = parseOverrideState(input.state);
  if (!state) {
    throw new Error("Trạng thái override không hợp lệ.");
  }

  await setPublicationOverride(
    {
      featureKey,
      publicationId,
      state,
      actorId: session.user?.id ?? null,
    },
    deps
  );
}
