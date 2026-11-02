/**
 * Server-side login feature gate used by the NextAuth `signIn` callback.
 *
 * Rules:
 * - `login` enabled  -> allow sign-in unchanged (no extra database reads).
 * - `login` disabled -> allow only an ADMIN resolved from trusted database
 *   records. Credentials resolve the authenticated user by id; OAuth resolves
 *   the existing Account row by provider + providerAccountId.
 * - New OAuth identities have no Account row yet, so they are denied.
 * - Any feature-service or database error denies sign-in (fail closed).
 *
 * This module never reads a role from client input or OAuth profile fields and
 * never touches sessions, so existing JWT sessions are not invalidated.
 */

export type SignInIdentity =
  | { kind: "credentials"; userId: string }
  | { kind: "oauth"; provider: string; providerAccountId: string };

export type SignInAccessDependencies = {
  isLoginEnabled(): Promise<boolean>;
  findUserRoleById(userId: string): Promise<string | null>;
  findOAuthUserRole(
    provider: string,
    providerAccountId: string
  ): Promise<string | null>;
  logError?(message: string, error: unknown): void;
};

const ADMIN_ROLE = "ADMIN";

function reportError(
  deps: SignInAccessDependencies,
  message: string,
  error: unknown
): void {
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
}

export async function evaluateSignInAccess(
  identity: SignInIdentity,
  deps: SignInAccessDependencies
): Promise<boolean> {
  let loginEnabled: boolean;

  try {
    loginEnabled = await deps.isLoginEnabled();
  } catch (error) {
    reportError(
      deps,
      "Không thể đọc feature flag login; từ chối đăng nhập.",
      error
    );
    return false;
  }

  // The common path stays exactly as before: no role lookup, no session
  // change, and no additional database round-trip.
  if (loginEnabled) return true;

  try {
    const role =
      identity.kind === "credentials"
        ? await deps.findUserRoleById(identity.userId)
        : await deps.findOAuthUserRole(
            identity.provider,
            identity.providerAccountId
          );

    return role === ADMIN_ROLE;
  } catch (error) {
    reportError(
      deps,
      "Không thể xác minh quyền ADMIN; từ chối đăng nhập.",
      error
    );
    return false;
  }
}
