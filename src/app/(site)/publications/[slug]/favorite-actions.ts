"use server";

import { prisma } from "@/lib/prisma";
import { auth } from "@/lib/auth-nextauth";
import { revalidatePath } from "next/cache";

export async function toggleFavorite(publicationId: string, slug: string) {
  const session = await auth();
  if (!session?.user?.id) {
    throw new Error("Vui lòng đăng nhập để lưu ấn phẩm.");
  }

  const existing = await prisma.favorite.findUnique({
    where: {
      userId_publicationId: {
        userId: session.user.id,
        publicationId,
      },
    },
  });

  if (existing) {
    await prisma.favorite.delete({ where: { id: existing.id } });
  } else {
    await prisma.favorite.create({
      data: { userId: session.user.id, publicationId },
    });
  }

  revalidatePath(`/publications/${slug}`);
  revalidatePath("/favorites");
}