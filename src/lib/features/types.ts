import type { FeatureScopeValue } from "./keys";

/**
 * Mirrors the `FeatureOverrideState` Prisma enum.
 */
export type FeatureOverrideState = "INHERIT" | "ENABLED" | "DISABLED";

/**
 * The only FeatureFlag fields the resolver needs.
 * Display name and scope always come from the static registry.
 */
export type FeatureFlagRecord = {
  key: string;
  enabled: boolean;
};

export type PublicationOverrideRecord = {
  featureKey: string;
  state: FeatureOverrideState;
};

/**
 * Result of loading all global feature flags.
 *
 * `error` represents a database/query failure and must fail closed. It is
 * intentionally separate from a successful snapshot with a missing key.
 */
export type FlagsSnapshot =
  | { status: "ok"; flags: ReadonlyMap<string, FeatureFlagRecord> }
  | { status: "error" };

/**
 * Result of loading one publication and its feature overrides.
 *
 * `exists: false` is a normal result (the publication was not found), not a
 * database error.
 */
export type PublicationSnapshot =
  | { status: "error" }
  | {
      status: "ok";
      exists: boolean;
      overrides: ReadonlyMap<string, FeatureOverrideState>;
    };

export type GlobalFeatureSource =
  | "global"
  | "missing-flag"
  | "database-error"
  | "unknown-key";

export type PublicationFeatureSource =
  | "global"
  | "global-disabled"
  | "inherit"
  | "override-enabled"
  | "override-disabled"
  | "missing-flag"
  | "database-error"
  | "unknown-key"
  | "publication-not-found";

export type GlobalFeatureState = {
  key: string;
  name: string | null;
  scope: FeatureScopeValue | null;
  exists: boolean;
  enabled: boolean;
  source: GlobalFeatureSource;
};

export type PublicationFeatureState = {
  key: string;
  name: string | null;
  scope: FeatureScopeValue | null;
  globalEnabled: boolean;
  override: FeatureOverrideState | null;
  enabled: boolean;
  source: PublicationFeatureSource;
};

export type PublicationPermissions = {
  share: boolean;
  download: boolean;
  print: boolean;
};
