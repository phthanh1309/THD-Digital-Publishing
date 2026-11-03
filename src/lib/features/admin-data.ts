/**
 * Admin-only data readers for the Feature Flag management screen.
 *
 * These helpers take an injected `requireAdmin` guard so the access rule
 * ("only ADMIN may read audit logs") can be tested without a Next.js session.
 * The guard is always awaited before any data loader runs.
 */

export type FeatureAuditLogEntry = {
  id: string;
  createdAt: Date;
  actorId: string | null;
  actorName: string | null;
  actorUsername: string | null;
  featureKey: string;
  scope: "GLOBAL" | "PUBLICATION";
  publicationId: string | null;
  publicationTitle: string | null;
  publicationSlug: string | null;
  action: "SET_GLOBAL" | "SET_OVERRIDE" | "RESET_OVERRIDE";
  oldValue: unknown;
  newValue: unknown;
};

export type AdminGuard = {
  requireAdmin(): Promise<unknown>;
};

export async function withAdminGuard<T>(
  deps: AdminGuard,
  work: () => Promise<T>
): Promise<T> {
  await deps.requireAdmin();
  return work();
}

export type AuditLogDependencies = AdminGuard & {
  fetchAuditLogs(limit: number): Promise<FeatureAuditLogEntry[]>;
};

export async function loadAuditLogsForAdmin(
  deps: AuditLogDependencies,
  limit = 20
): Promise<FeatureAuditLogEntry[]> {
  return withAdminGuard(deps, () => deps.fetchAuditLogs(limit));
}
