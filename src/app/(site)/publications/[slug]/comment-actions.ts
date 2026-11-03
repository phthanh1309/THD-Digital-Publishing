"use server";

import { prisma } from "@/lib/prisma";
import { auth } from "@/lib/auth-nextauth";
import { revalidatePath } from "next/cache";
import { publicationFeatureAccessDependencies } from "@/lib/features/publication-feature-gate";
import { runCreateComment, runDeleteComment } from "@/lib/comment";

async function getSessionUser() {
  const session = await auth();
  const user = session?.user as { id?: string; role?: string } | undefined;
  if (!user?.id) return null;

  return { id: user.id, role: user.role ?? null };
}

export async function createComment(
  publicationId: string,
  slug: string,
  formData: FormData
) {
  await runCreateComment(
    { publicationId, slug, formData },
    {
      access: publicationFeatureAccessDependencies,

      getSessionUser,

      async findPublication(id, publicationSlug) {
        return prisma.publication.findFirst({
          where: { id, slug: publicationSlug, status: "published" },
          select: { id: true },
        });
      },

      async createComment(data) {
        await prisma.comment.create({ data });
      },

      revalidate(publicationSlug) {
        revalidatePath(`/publications/${publicationSlug}`);
      },
    }
  );
}

export async function deleteComment(commentId: string, slug: string) {
  await runDeleteComment(
    { commentId, slug },
    {
      getSessionUser,

      async findComment(id) {
        return prisma.comment.findUnique({
          where: { id },
          select: { id: true, userId: true, publicationId: true },
        });
      },

      async deleteComment(id) {
        await prisma.comment.delete({ where: { id } });
      },

      revalidate(publicationSlug) {
        revalidatePath(`/publications/${publicationSlug}`);
      },
    }
  );
}
