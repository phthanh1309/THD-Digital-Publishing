"use server";

import { prisma } from "@/lib/prisma";
import { resend } from "@/lib/resend";
import { registerSchema, firstZodError } from "@/lib/validation";
import bcrypt from "bcryptjs";
import crypto from "crypto";

export async function registerAction(
  _prevState: string,
  formData: FormData
): Promise<string> {
  const parsed = registerSchema.safeParse({
    username: String(formData.get("username") ?? ""),
    email: String(formData.get("email") ?? ""),
    password: String(formData.get("password") ?? ""),
  });

  if (!parsed.success) return firstZodError(parsed.error);

  const { username, email, password } = parsed.data;

  const existingUsername = await prisma.user.findUnique({ where: { username } });
  if (existingUsername) return "Tên đăng nhập đã được sử dụng.";

  const existingEmail = await prisma.user.findUnique({ where: { email } });
  if (existingEmail) return "Email đã được sử dụng.";

  const passwordHash = await bcrypt.hash(password, 10);

  await prisma.user.create({
    data: { username, email, passwordHash, role: "USER" },
  });

  const token = crypto.randomBytes(32).toString("hex");
  const expires = new Date(Date.now() + 1000 * 60 * 60 * 24);

  await prisma.verificationToken.create({
    data: { identifier: email, token, expires },
  });

  const verifyUrl = `${process.env.APP_URL ?? "http://localhost:3000"}/verify?token=${token}&email=${encodeURIComponent(email)}`;

  const safeUsername = username.replace(/[&<>"']/g, (char) => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#39;",
  })[char]!);

  await resend.emails.send({
    from: "THD Digital Publishing <onboarding@resend.dev>",
    to: email,
    subject: "Xác thực tài khoản - Thư viện Ấn phẩm số",
    html: `
      <p>Chào ${safeUsername},</p>
      <p>Bấm vào liên kết dưới đây để xác thực tài khoản (hết hạn sau 24 giờ):</p>
      <p><a href="${verifyUrl}">Xác thực tài khoản</a></p>
    `,
  });

  return "SUCCESS";
}
