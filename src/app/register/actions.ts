"use server";

import { prisma } from "@/lib/prisma";
import { resend } from "@/lib/resend";
import { runRegistration } from "@/lib/registration";
import { registrationAccessDependencies } from "@/lib/features/registration-gate";
import bcrypt from "bcryptjs";

export async function registerAction(
  _prevState: string,
  formData: FormData
): Promise<string> {
  return runRegistration(
    {
      username: String(formData.get("username") ?? ""),
      email: String(formData.get("email") ?? ""),
      password: String(formData.get("password") ?? ""),
    },
    {
      access: registrationAccessDependencies,
      findUserByUsername: (username) =>
        prisma.user.findUnique({ where: { username }, select: { id: true } }),
      findUserByEmail: (email) =>
        prisma.user.findUnique({ where: { email }, select: { id: true } }),
      hashPassword: (password) => bcrypt.hash(password, 10),
      createUser: async (data) => {
        await prisma.user.create({ data });
      },
      createVerificationToken: async (data) => {
        await prisma.verificationToken.create({ data });
      },
      sendVerificationEmail: async ({ to, subject, html }) => {
        await resend.emails.send({
          from: "THD Digital Publishing <onboarding@resend.dev>",
          to,
          subject,
          html,
        });
      },
      appUrl: process.env.APP_URL ?? "http://localhost:3000",
    }
  );
}
