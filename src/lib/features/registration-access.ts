/**
 * Server-side registration feature gate.
 *
 * Rules:
 * - `register` enabled  -> allow registration unchanged.
 * - `register` disabled -> deny new account creation for all paths
 *   (credentials registration and OAuth user creation).
 * - Existing users must remain unaffected.
 * - Any feature-service or database error denies registration (fail closed).
 */

export type RegistrationAccessDependencies = {
  isRegisterEnabled(): Promise<boolean>;
  logError?(message: string, error: unknown): void;
};

const reportError = (
  deps: RegistrationAccessDependencies,
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

export async function evaluateRegistrationAccess(
  deps: RegistrationAccessDependencies,
): Promise<boolean> {
  try {
    return await deps.isRegisterEnabled();
  } catch (error) {
    reportError(
      deps,
      "Không thể đọc feature flag register; từ chối đăng ký tài khoản mới.",
      error,
    );
    return false;
  }
};

export class RegistrationDisabledError extends Error {
  constructor(message = "Đăng ký tài khoản hiện đang tạm khóa.") {
    super(message);
    this.name = "RegistrationDisabledError";
  }
}

export async function assertRegistrationAllowed(
  deps: RegistrationAccessDependencies,
): Promise<void> {
  const allowed = await evaluateRegistrationAccess(deps);
  if (!allowed) {
    throw new RegistrationDisabledError();
  }
};
