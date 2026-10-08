"use server";

import { prisma } from "@/lib/prisma";
import { auth } from "@/lib/auth-nextauth";

export async function recordReadingHistory(publicationId: string) {
  const session = await auth();
  if (!session?.user?.id) return; // chưa đăng nhập thì không ghi, không báo lỗi

  await prisma.readingHistory.upsert({
    where: {
      userId_publicationId: { userId: session.user.id, publicationId },
    },
    update: { lastReadAt: new Date() },
    create: { userId: session.user.id, publicationId },
  });
}