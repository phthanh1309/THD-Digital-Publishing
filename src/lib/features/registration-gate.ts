/**
 * Production wiring for the registration feature gate.
 *
 * Keep this module server-only: it reads the `register` feature flag. Client
 * Components must receive the already-resolved state, never this module.
 */

import { isFeatureEnabled } from "./service";
import {
  assertRegistrationAllowed,
  evaluateRegistrationAccess,
  type RegistrationAccessDependencies,
} from "./registration-access";

export const registrationAccessDependencies: RegistrationAccessDependencies = {
  isRegisterEnabled: () => isFeatureEnabled("register"),
};

export function isRegistrationEnabled(): Promise<boolean> {
  return evaluateRegistrationAccess(registrationAccessDependencies);
}

export function requireRegistrationEnabled(): Promise<void> {
  return assertRegistrationAllowed(registrationAccessDependencies);
}
