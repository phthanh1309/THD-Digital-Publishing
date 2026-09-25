"use server";

import { redirect } from "next/navigation";
import { verifyLogin, createSession } from "@/lib/auth";

export async function loginAction(_prevState: string, formData: FormData) {
  const username = String(formData.get("username") ?? "").trim();
  const password = String(formData.get("password") ?? "");

  const ok = await verifyLogin(username, password);
  if (!ok) return "Tên đăng nhập hoặc mật khẩu không đúng.";

  await createSession(username);
  redirect("/admin");
}