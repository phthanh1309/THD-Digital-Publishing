/**
 * Production wiring for the profile feature gate.
 *
 * Keep this module server-only: it reads the `profile` feature flag. Client
 * Components must receive the already-resolved state, never this module.
 */

import { isFeatureEnabled } from "./service";
import {
  assertProfileEnabled,
  evaluateProfileAccess,
  type ProfileAccessDependencies,
} from "./profile-access";

export const profileAccessDependencies: ProfileAccessDependencies = {
  isProfileEnabled: () => isFeatureEnabled("profile"),
};

export function isProfileEnabled(): Promise<boolean> {
  return evaluateProfileAccess(profileAccessDependencies);
}

export function requireProfileEnabled(): Promise<void> {
  return assertProfileEnabled(profileAccessDependencies);
}
