/**
 * Server-side profile feature gate.
 *
 * Rules:
 * - `profile` enabled  -> allow viewing and updating the signed-in profile.
 * - `profile` disabled -> deny every profile read and write for every role.
 *   The existing profile surface is self-service only; there is no separate
 *   ADMIN profile capability, so no role bypass is granted here.
 * - Any feature-service or database error denies access (fail closed).
 */

export type ProfileAccessDependencies = {
  isProfileEnabled(): Promise<boolean>;
  logError?(message: string, error: unknown): void;
};

const reportError = (
  deps: ProfileAccessDependencies,
  message: string,
  error: unknown,
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

export async function evaluateProfileAccess(
  deps: ProfileAccessDependencies,
): Promise<boolean> {
  try {
    return await deps.isProfileEnabled();
  } catch (error) {
    reportError(
      deps,
      "Không thể đọc feature flag profile; từ chối truy cập hồ sơ.",
      error,
    );
    return false;
  }
}

export class ProfileDisabledError extends Error {
  constructor(message = "Hồ sơ cá nhân hiện đang tạm khóa.") {
    super(message);
    this.name = "ProfileDisabledError";
  }
}

export async function assertProfileEnabled(
  deps: ProfileAccessDependencies,
): Promise<void> {
  const allowed = await evaluateProfileAccess(deps);
  if (!allowed) {
    throw new ProfileDisabledError();
  }
}
