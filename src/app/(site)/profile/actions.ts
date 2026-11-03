"use server";

import { prisma } from "@/lib/prisma";
import { auth } from "@/lib/auth-nextauth";
import { revalidatePath } from "next/cache";
import { writeFile, mkdir } from "fs/promises";
import path from "path";
import { profileAccessDependencies } from "@/lib/features/profile-gate";
import {
  runProfileUpdate,
  type ProfileAvatarInput,
} from "@/lib/profile";

const STORAGE_ROOT = path.join(process.cwd(), "storage");

function getAvatarInput(formData: FormData): ProfileAvatarInput | null {
  const file = formData.get("avatarFile");
  if (!(file instanceof File) || file.size <= 0) return null;

  return {
    name: file.name,
    size: file.size,
    read: () => file.arrayBuffer(),
  };
}

export async function updateProfile(formData: FormData) {
  await runProfileUpdate(
    {
      name: String(formData.get("name") ?? ""),
      bio: String(formData.get("bio") ?? ""),
      avatar: getAvatarInput(formData),
    },
    {
      access: profileAccessDependencies,

      async getSessionUserId() {
        const session = await auth();
        return session?.user?.id ?? null;
      },

      async saveAvatar({ fileName, data }) {
        const dir = path.join(STORAGE_ROOT, "avatars");
        await mkdir(dir, { recursive: true });
        await writeFile(path.join(dir, fileName), data);
        return `avatars/${fileName}`;
      },

      async updateUser(id, data) {
        await prisma.user.update({ where: { id }, data });
      },

      revalidate() {
        revalidatePath("/profile");
      },
    },
  );
}
