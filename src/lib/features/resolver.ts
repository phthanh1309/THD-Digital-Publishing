import { getFeatureDefinition } from "./keys";
import type {
  FlagsSnapshot,
  GlobalFeatureState,
  PublicationFeatureState,
  PublicationSnapshot,
} from "./types";

function unknownGlobalState(key: string): GlobalFeatureState {
  return {
    key,
    name: null,
    scope: null,
    exists: false,
    enabled: false,
    source: "unknown-key",
  };
}

function unknownPublicationState(key: string): PublicationFeatureState {
  return {
    key,
    name: null,
    scope: null,
    globalEnabled: false,
    override: null,
    enabled: false,
    source: "unknown-key",
  };
}

/**
 * Resolve the global state of one feature.
 *
 * Fail-closed rules:
 * - unknown registry key  -> enabled false (no database knowledge required)
 * - database error        -> enabled false
 * - registry key has no FeatureFlag row -> enabled false
 */
export function resolveGlobalState(
  key: string,
  flags: FlagsSnapshot
): GlobalFeatureState {
  const definition = getFeatureDefinition(key);
  if (!definition) return unknownGlobalState(key);

  if (flags.status === "error") {
    return {
      key,
      name: definition.name,
      scope: definition.scope,
      exists: false,
      enabled: false,
      source: "database-error",
    };
  }

  const row = flags.flags.get(key);
  if (!row) {
    return {
      key,
      name: definition.name,
      scope: definition.scope,
      exists: false,
      enabled: false,
      source: "missing-flag",
    };
  }

  const enabled = row.enabled === true;

  return {
    key,
    name: definition.name,
    scope: definition.scope,
    exists: true,
    enabled,
    source: "global",
  };
}

/**
 * Resolve the effective state of one feature for a publication.
 *
 * `publication` must be `null` for GLOBAL-scope features, because publication
 * overrides do not apply to them. For GLOBAL_AND_PUBLICATION features the
 * service passes the loaded snapshot.
 *
 * Rules:
 * - global OFF always wins;
 * - GLOBAL scope ignores publication data entirely;
 * - no override row is equivalent to INHERIT;
 * - INHERIT / ENABLED follow global; DISABLED turns the feature off;
 * - a missing publication fails closed.
 */
export function resolvePublicationState(
  key: string,
  flags: FlagsSnapshot,
  publication: PublicationSnapshot | null
): PublicationFeatureState {
  const definition = getFeatureDefinition(key);
  if (!definition) return unknownPublicationState(key);

  if (flags.status === "error") {
    return {
      key,
      name: definition.name,
      scope: definition.scope,
      globalEnabled: false,
      override: null,
      enabled: false,
      source: "database-error",
    };
  }

  const row = flags.flags.get(key);
  if (!row) {
    return {
      key,
      name: definition.name,
      scope: definition.scope,
      globalEnabled: false,
      override: null,
      enabled: false,
      source: "missing-flag",
    };
  }

  const globalEnabled = row.enabled === true;

  if (definition.scope === "GLOBAL") {
    return {
      key,
      name: definition.name,
      scope: definition.scope,
      globalEnabled,
      override: null,
      enabled: globalEnabled,
      source: globalEnabled ? "global" : "global-disabled",
    };
  }

  // Defensive: the service always loads a snapshot for GLOBAL_AND_PUBLICATION.
  if (!publication) {
    return {
      key,
      name: definition.name,
      scope: definition.scope,
      globalEnabled,
      override: null,
      enabled: false,
      source: "database-error",
    };
  }

  if (publication.status === "error") {
    return {
      key,
      name: definition.name,
      scope: definition.scope,
      globalEnabled,
      override: null,
      enabled: false,
      source: "database-error",
    };
  }

  if (!publication.exists) {
    return {
      key,
      name: definition.name,
      scope: definition.scope,
      globalEnabled,
      override: null,
      enabled: false,
      source: "publication-not-found",
    };
  }

  const override = publication.overrides.get(key) ?? null;

  if (!globalEnabled) {
    return {
      key,
      name: definition.name,
      scope: definition.scope,
      globalEnabled,
      override,
      enabled: false,
      source: "global-disabled",
    };
  }

  if (override === "DISABLED") {
    return {
      key,
      name: definition.name,
      scope: definition.scope,
      globalEnabled,
      override,
      enabled: false,
      source: "override-disabled",
    };
  }

  if (override === "ENABLED") {
    return {
      key,
      name: definition.name,
      scope: definition.scope,
      globalEnabled,
      override,
      enabled: true,
      source: "override-enabled",
    };
  }

  // No row or an explicit INHERIT row both follow the global state.
  return {
    key,
    name: definition.name,
    scope: definition.scope,
    globalEnabled,
    override,
    enabled: true,
    source: "inherit",
  };
}
