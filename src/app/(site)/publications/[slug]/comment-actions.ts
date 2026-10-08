"use server";

import { prisma } from "@/lib/prisma";
import { auth } from "@/lib/auth-nextauth";
import { commentSchema, firstZodError } from "@/lib/validation";
import { revalidatePath } from "next/cache";

export async function createComment(
  publicationId: string,
  slug: string,
  formData: FormData
) {
  const session = await auth();
  if (!session?.user?.id) {
    throw new Error("Vui lòng đăng nhập để bình luận.");
  }

  const parsed = commentSchema.safeParse({
    content: String(formData.get("content") ?? ""),
  });
  if (!parsed.success) throw new Error(firstZodError(parsed.error));

  const publication = await prisma.publication.findFirst({
    where: { id: publicationId, slug, status: "published" },
    select: { id: true },
  });
  if (!publication) throw new Error("Không tìm thấy ấn phẩm.");

  await prisma.comment.create({
    data: {
      content: parsed.data.content,
      userId: session.user.id,
      publicationId: publication.id,
    },
  });

  revalidatePath(`/publications/${slug}`);
}

export async function deleteComment(commentId: string, slug: string) {
  const session = await auth();
  if (!session?.user?.id) throw new Error("Vui lòng đăng nhập.");

  const comment = await prisma.comment.findUnique({ where: { id: commentId } });
  if (!comment) return;

  const isOwner = comment.userId === session.user.id;
  const isAdmin = (session.user as { role?: string }).role === "ADMIN";

  if (!isOwner && !isAdmin) {
    throw new Error("Bạn không có quyền xóa bình luận này.");
  }

  await prisma.comment.delete({ where: { id: commentId } });
  revalidatePath(`/publications/${slug}`);
}
