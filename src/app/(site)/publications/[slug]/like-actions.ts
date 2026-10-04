"use server";

import { prisma } from "@/lib/prisma";
import { auth } from "@/lib/auth-nextauth";
import { revalidatePath } from "next/cache";

export async function toggleLike(publicationId: string, slug: string) {
  const session = await auth();
  if (!session?.user?.id) {
    throw new Error("Vui lòng đăng nhập để thích ấn phẩm.");
  }

  const existing = await prisma.like.findUnique({
    where: {
      userId_publicationId: {
        userId: session.user.id,
        publicationId,
      },
    },
  });

  if (existing) {
    await prisma.like.delete({ where: { id: existing.id } });
  } else {
    await prisma.like.create({
      data: { userId: session.user.id, publicationId },
    });
  }

  revalidatePath(`/publications/${slug}`);
}