"use server";

import { prisma } from "@/lib/prisma";
import { auth } from "@/lib/auth-nextauth";
import { revalidatePath } from "next/cache";

const MAX_LENGTH = 1000;

export async function createComment(
  publicationId: string,
  slug: string,
  formData: FormData
) {
  const session = await auth();
  if (!session?.user?.id) {
    throw new Error("Vui lòng đăng nhập để bình luận.");
  }

  const content = String(formData.get("content") ?? "").trim();
  if (!content) throw new Error("Bình luận không được để trống.");
  if (content.length > MAX_LENGTH) {
    throw new Error(`Bình luận tối đa ${MAX_LENGTH} ký tự.`);
  }

  await prisma.comment.create({
    data: { content, userId: session.user.id, publicationId },
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