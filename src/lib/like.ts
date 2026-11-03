/**
 * Like toggle workflow with a server-side `like` feature gate.
 *
 * The shared gate lives in `features/publication-feature-access.ts`, so the
 * same module is reused by comment, favorite and history. This workflow only
 * adds Like-specific dependencies and messages.
 *
 * The gate runs before the session lookup and before every database read or
 * write, so a direct Server Action call cannot bypass it.
 */

import {
  assertPublicationFeatureEnabled,
  type PublicationFeatureAccessDependencies,
} from "./features/publication-feature-access";

export const LIKE_DISABLED_MESSAGE =
  "Tính năng thích ấn phẩm hiện đang tạm khóa.";

export type LikeToggleInput = {
  publicationId: string;
  slug: string;
};

export type LikeToggleDependencies = {
  access: PublicationFeatureAccessDependencies;
  getSessionUserId(): Promise<string | null>;
  findLike(
    userId: string,
    publicationId: string
  ): Promise<{ id: string } | null>;
  deleteLike(id: string): Promise<void>;
  createLike(userId: string, publicationId: string): Promise<void>;
  revalidate(slug: string): void | Promise<void>;
};

export async function runToggleLike(
  input: LikeToggleInput,
  deps: LikeToggleDependencies
): Promise<void> {
  // Fail closed before auth and before any database access.
  await assertPublicationFeatureEnabled(
    "like",
    input.publicationId,
    deps.access,
    { message: LIKE_DISABLED_MESSAGE }
  );

  const userId = await deps.getSessionUserId();
  if (!userId) {
    throw new Error("Vui lòng đăng nhập để thích ấn phẩm.");
  }

  const existing = await deps.findLike(userId, input.publicationId);

  if (existing) {
    await deps.deleteLike(existing.id);
  } else {
    await deps.createLike(userId, input.publicationId);
  }

  await deps.revalidate(input.slug);
}
