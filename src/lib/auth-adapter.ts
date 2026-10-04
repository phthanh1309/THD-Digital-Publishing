import { PrismaAdapter } from "@auth/prisma-adapter";
import { prisma } from "./prisma";
import type { AdapterUser } from "next-auth/adapters";

function slugifyUsername(base: string): string {
  return base
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-z0-9]/g, "")
    .slice(0, 20) || "user";
}

async function uniqueUsername(base: string): Promise<string> {
  let username = base;
  let counter = 1;
  while (await prisma.user.findUnique({ where: { username } })) {
    counter++;
    username = `${base}${counter}`;
  }
  return username;
}

export function buildAuthAdapter() {
  const base = PrismaAdapter(prisma);

  return {
    ...base,
    createUser: async (data: Omit<AdapterUser, "id">) => {
      const baseUsername = slugifyUsername(
        data.email ? data.email.split("@")[0] : data.name ?? "user"
      );
      const username = await uniqueUsername(baseUsername);

      const user = await prisma.user.create({
        data: {
          username,
          name: data.name,
          email: data.email,
          emailVerified: data.emailVerified,
          image: data.image,
          passwordHash: "", // tài khoản OAuth không có mật khẩu
          role: "USER",
        },
      });

      return {
        id: user.id,
        name: user.name,
        email: user.email!,
        emailVerified: user.emailVerified,
        image: user.image,
      };
    },
  };
}