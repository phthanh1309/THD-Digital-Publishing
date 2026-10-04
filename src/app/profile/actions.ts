"use server";

import { prisma } from "@/lib/prisma";
import { auth } from "@/lib/auth-nextauth";
import { revalidatePath } from "next/cache";
import { writeFile, mkdir } from "fs/promises";
import path from "path";

const STORAGE_ROOT = path.join(process.cwd(), "storage");
const MAX_AVATAR_SIZE = 5 * 1024 * 1024; // 5MB

export async function updateProfile(formData: FormData) {
  const session = await auth();
  if (!session?.user?.id) throw new Error("Chưa đăng nhập.");

  const name = String(formData.get("name") ?? "").trim();
  const bio = String(formData.get("bio") ?? "").trim();
  const avatarFile = formData.get("avatarFile") as File | null;

  let imagePath: string | undefined;

  if (avatarFile && avatarFile.size > 0) {
    if (avatarFile.size > MAX_AVATAR_SIZE) {
      throw new Error("Ảnh đại diện tối đa 5MB.");
    }
    const ext = path.extname(avatarFile.name).toLowerCase();
    if (![".jpg", ".jpeg", ".png", ".webp"].includes(ext)) {
      throw new Error("Chỉ chấp nhận ảnh jpg/png/webp.");
    }

    const dir = path.join(STORAGE_ROOT, "avatars");
    await mkdir(dir, { recursive: true });

    const filename = `${session.user.id}-${Date.now()}${ext}`;
    await writeFile(
      path.join(dir, filename),
      Buffer.from(await avatarFile.arrayBuffer())
    );

    imagePath = `avatars/${filename}`;
  }

  await prisma.user.update({
    where: { id: session.user.id },
    data: {
      name: name || undefined,
      bio,
      ...(imagePath ? { image: imagePath } : {}),
    },
  });

  revalidatePath("/profile");
}