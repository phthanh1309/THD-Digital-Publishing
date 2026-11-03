"use server";

import { prisma } from "@/lib/prisma";
import { auth } from "@/lib/auth-nextauth";
import { revalidatePath } from "next/cache";
import { publicationFeatureAccessDependencies } from "@/lib/features/publication-feature-gate";
import { runToggleFavorite } from "@/lib/favorite";

export async function toggleFavorite(publicationId: string, slug: string) {
  await runToggleFavorite(
    { publicationId, slug },
    {
      access: publicationFeatureAccessDependencies,

      async getSessionUserId() {
        const session = await auth();
        return session?.user?.id ?? null;
      },

      async findPublication(id, publicationSlug) {
        return prisma.publication.findFirst({
          where: { id, slug: publicationSlug, status: "published" },
          select: { id: true },
        });
      },

      async findFavorite(userId, id) {
        return prisma.favorite.findUnique({
          where: {
            userId_publicationId: { userId, publicationId: id },
          },
          select: { id: true },
        });
      },

      async deleteFavorite(id) {
        await prisma.favorite.delete({ where: { id } });
      },

      async createFavorite(userId, id) {
        await prisma.favorite.create({
          data: { userId, publicationId: id },
        });
      },

      revalidate(publicationSlug) {
        revalidatePath(`/publications/${publicationSlug}`);
        revalidatePath("/favorites");
      },
    }
  );
}
