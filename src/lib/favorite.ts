/**
 * Favorite toggle workflow with a server-side `favorite` feature gate.
 *
 * The shared gate lives in `features/publication-feature-access.ts`, so the
 * same module is reused by like, comment and favorite. This workflow only adds
 * Favorite-specific dependencies and messages.
 *
 * Both adding and removing a favorite are mutations, so both are gated. The
 * gate runs before the session lookup and before every database read or write,
 * so a direct Server Action call cannot bypass it.
 */

import {
  assertPublicationFeatureEnabled,
  type PublicationFeatureAccessDependencies,
} from "./features/publication-feature-access";

export const FAVORITE_DISABLED_MESSAGE =
  "Tính năng lưu ấn phẩm hiện đang tạm khóa.";

export type FavoriteToggleInput = {
  publicationId: string;
  slug: string;
};

export type FavoritePublication = { id: string };

export type FavoriteToggleDependencies = {
  access: PublicationFeatureAccessDependencies;
  getSessionUserId(): Promise<string | null>;
  findFavorite(
    userId: string,
    publicationId: string
  ): Promise<{ id: string; userId?: string } | null>;
  deleteFavorite(id: string): Promise<void>;
  createFavorite(userId: string, publicationId: string): Promise<void>;
  revalidate(slug: string): void | Promise<void>;
  /** Optional extra identity check; the shared gate already fails closed when
   * the publication does not exist. */
  findPublication?(
    publicationId: string,
    slug: string
  ): Promise<FavoritePublication | null>;
};

export async function runToggleFavorite(
  input: FavoriteToggleInput,
  deps: FavoriteToggleDependencies
): Promise<void> {
  // Fail closed before auth and before any database access. This blocks both
  // adding and removing favorites when the feature is disabled.
  await assertPublicationFeatureEnabled(
    "favorite",
    input.publicationId,
    deps.access,
    { message: FAVORITE_DISABLED_MESSAGE }
  );

  const userId = await deps.getSessionUserId();
  if (!userId) {
    throw new Error("Vui lòng đăng nhập để lưu ấn phẩm.");
  }

  if (typeof deps.findPublication === "function") {
    const publication = await deps.findPublication(
      input.publicationId,
      input.slug
    );
    if (!publication) throw new Error("Không tìm thấy ấn phẩm.");
  }

  // Scope the lookup to the authenticated user so one user can never read,
  // create or delete another user's favorite by forging a client value.
  const existing = await deps.findFavorite(userId, input.publicationId);

  if (existing) {
    // Defensive ownership check: even if a data source ever returns a row for
    // another user, the authenticated session user is the only one allowed to
    // remove it.
    if (existing.userId && existing.userId !== userId) {
      throw new Error("Bạn không có quyền xóa ấn phẩm đã lưu này.");
    }
    await deps.deleteFavorite(existing.id);
  } else {
    await deps.createFavorite(userId, input.publicationId);
  }

  await deps.revalidate(input.slug);
}

// Explicit aliases kept for callers that prefer the shorter/alternative names.
export const runFavoriteToggle = runToggleFavorite;
export const toggleFavorite = runToggleFavorite;
