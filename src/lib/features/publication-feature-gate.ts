/**
 * Production wiring for the GLOBAL_AND_PUBLICATION feature gate.
 *
 * Keep this module server-only. Feature-specific workflows (like, comment,
 * favorite, history) call these helpers with their own key and publication id.
 */

import {
  assertPublicationFeatureEnabled,
  evaluatePublicationFeatureAccess,
  type PublicationFeatureAccessDependencies,
} from "./publication-feature-access";
import { getPublicationFeatureState } from "./service";

export const publicationFeatureAccessDependencies = {
  getPublicationFeatureState,
} satisfies PublicationFeatureAccessDependencies;

export async function isPublicationFeatureAvailable(
  featureKey: string,
  publicationId: string
): Promise<boolean> {
  const decision = await evaluatePublicationFeatureAccess(
    featureKey,
    publicationId,
    publicationFeatureAccessDependencies
  );

  return decision.enabled;
}

export function requirePublicationFeature(
  featureKey: string,
  publicationId: string,
  options?: { message?: string }
): Promise<void> {
  return assertPublicationFeatureEnabled(
    featureKey,
    publicationId,
    publicationFeatureAccessDependencies,
    options
  );
}
