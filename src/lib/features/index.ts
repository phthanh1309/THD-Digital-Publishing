/**
 * Public server-side API for feature flags.
 *
 * Never import this module from Client Components: feature state must be
 * resolved on the server. `server-only` is not installed in this project, so
 * this convention is enforced by module boundaries and code review.
 */

export {
  FEATURES,
  FEATURE_KEYS,
  getFeatureDefinition,
  isFeatureKey,
} from "./keys";
export type {
  FeatureDefinition,
  FeatureKey,
  FeatureScopeValue,
} from "./keys";

export type {
  FeatureFlagRecord,
  FeatureOverrideState,
  GlobalFeatureSource,
  GlobalFeatureState,
  PublicationFeatureSource,
  PublicationFeatureState,
  PublicationPermissions,
  PublicationOverrideRecord,
} from "./types";

export {
  createFeatureService,
  getGlobalFeatureState,
  getPublicationFeatureState,
  isFeatureEnabled,
  isPublicationFeatureEnabled,
} from "./service";
export type {
  FeatureDataSource,
  FeatureService,
  FeatureServiceDependencies,
  Memoize,
  PublicationData,
} from "./service";

export { getEffectivePublicationPermissions } from "./permissions";
export type {
  FeatureFlagReader,
  PublicationPermissionFlags,
} from "./permissions";
