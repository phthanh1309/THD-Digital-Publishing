/**
 * Server-side gate for GLOBAL_AND_PUBLICATION features.
 *
 * This module is intentionally feature-agnostic: `like`, `comment`, `favorite`
 * and `history` all use it by passing their feature key and publication id.
 * Feature-specific modules only provide the key, publication id, optional
 * user-facing disabled message, and their own workflow.
 *
 * Fail-closed rules (delegated to the feature service/resolver):
 * - global OFF blocks even when a publication override is ENABLED;
 * - an explicit DISABLED override blocks;
 * - a missing FeatureFlag row, missing publication or database error blocks;
 * - any thrown error is treated as disabled and logged.
 */

import type {
  PublicationFeatureSource,
  PublicationFeatureState,
} from "./types";

export type PublicationFeatureAccessDependencies = {
  getPublicationFeatureState(
    featureKey: string,
    publicationId: string
  ): Promise<PublicationFeatureState>;
  logError?(message: string, error: unknown): void;
};

export type PublicationFeatureAccessDecision = {
  enabled: boolean;
  source: PublicationFeatureSource;
};

const reportError = (
  deps: PublicationFeatureAccessDependencies,
  message: string,
  error: unknown
): void => {
  if (deps.logError) {
    deps.logError(message, error);
    return;
  }

  const detail =
    error instanceof Error
      ? error.message
      : typeof error === "string"
        ? error
        : "Unknown error";

  console.error(`[features] ${message}`, detail);
};

export async function evaluatePublicationFeatureAccess(
  featureKey: string,
  publicationId: string,
  deps: PublicationFeatureAccessDependencies
): Promise<PublicationFeatureAccessDecision> {
  try {
    const state = await deps.getPublicationFeatureState(
      featureKey,
      publicationId
    );

    return { enabled: state.enabled, source: state.source };
  } catch (error) {
    reportError(
      deps,
      `Không thể đọc feature flag ${featureKey}; từ chối truy cập.`,
      error
    );
    return { enabled: false, source: "database-error" };
  }
}

export class PublicationFeatureDisabledError extends Error {
  readonly featureKey: string;

  constructor(
    featureKey: string,
    message = "Tính năng này hiện đang tạm khóa."
  ) {
    super(message);
    this.name = "PublicationFeatureDisabledError";
    this.featureKey = featureKey;
  }
}

export async function assertPublicationFeatureEnabled(
  featureKey: string,
  publicationId: string,
  deps: PublicationFeatureAccessDependencies,
  options?: { message?: string }
): Promise<void> {
  const decision = await evaluatePublicationFeatureAccess(
    featureKey,
    publicationId,
    deps
  );

  if (!decision.enabled) {
    throw new PublicationFeatureDisabledError(featureKey, options?.message);
  }
}
