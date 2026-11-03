"use server";

import { prisma } from "@/lib/prisma";
import { auth } from "@/lib/auth-nextauth";
import { revalidatePath } from "next/cache";
import { publicationFeatureAccessDependencies } from "@/lib/features/publication-feature-gate";
import { runToggleLike } from "@/lib/like";

export async function toggleLike(publicationId: string, slug: string) {
  await runToggleLike(
    { publicationId, slug },
    {
      access: publicationFeatureAccessDependencies,

      async getSessionUserId() {
        const session = await auth();
        return session?.user?.id ?? null;
      },

      async findLike(userId, publicationId) {
        return prisma.like.findUnique({
          where: {
            userId_publicationId: { userId, publicationId },
          },
          select: { id: true },
        });
      },

      async deleteLike(id) {
        await prisma.like.delete({ where: { id } });
      },

      async createLike(userId, publicationId) {
        await prisma.like.create({
          data: { userId, publicationId },
        });
      },

      revalidate(slug) {
        revalidatePath(`/publications/${slug}`);
      },
    }
  );
}
