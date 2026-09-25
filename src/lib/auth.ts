import { cookies } from "next/headers";
import bcrypt from "bcryptjs";

const COOKIE_NAME = "thd_admin_session";

export async function verifyLogin(username: string, password: string) {
  const validUsername = process.env.ADMIN_USERNAME ?? "";
  const validHash = process.env.ADMIN_PASSWORD_HASH ?? "";

  if (username !== validUsername || !validHash) return false;
  return bcrypt.compare(password, validHash);
}

export async function createSession(username: string) {
  const store = await cookies();
  store.set(COOKIE_NAME, username, {
    httpOnly: true,
    secure: process.env.NODE_ENV === "production",
    sameSite: "lax",
    path: "/",
    maxAge: 60 * 60, // 1 giờ, giống ADMIN_SESSION_TIMEOUT bản PHP
  });
}

export async function destroySession() {
  const store = await cookies();
  store.delete(COOKIE_NAME);
}

export async function getSessionUser() {
  const store = await cookies();
  return store.get(COOKIE_NAME)?.value ?? null;
}