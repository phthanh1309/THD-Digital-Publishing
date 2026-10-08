import { auth } from "@/lib/auth-nextauth";
import { prisma } from "@/lib/prisma";

export async function requireAdmin() {
  const session = await auth();

  if (!session?.user?.id) {
    throw new Error("Vui lòng đăng nhập.");
  }

  const user = await prisma.user.findUnique({
    where: { id: session.user.id },
    select: { role: true },
  });

  if (user?.role !== "ADMIN") {
    throw new Error("Bạn không có quyền thực hiện thao tác này.");
  }

  return session;
}
