import { auth } from "@/lib/auth-nextauth";

export async function requireAdmin() {
  const session = await auth();

  if (!session?.user?.id) {
    throw new Error("Vui lòng đăng nhập.");
  }

  if ((session.user as { role?: string }).role !== "ADMIN") {
    throw new Error("Bạn không có quyền thực hiện thao tác này.");
  }

  return session;
}
