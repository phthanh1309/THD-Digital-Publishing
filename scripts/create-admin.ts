import { prisma } from "../src/lib/prisma";
import bcrypt from "bcryptjs";

async function main() {
  const username = process.argv[2];
  const password = process.argv[3];

  if (!username || !password) {
    console.error("Dùng: npx tsx scripts/create-admin.ts <username> <password>");
    process.exit(1);
  }

  const passwordHash = await bcrypt.hash(password, 10);

    const user = await prisma.user.upsert({
    where: { username },
    update: { passwordHash, role: "ADMIN", emailVerified: new Date() },
    create: { username, passwordHash, role: "ADMIN", emailVerified: new Date() },
    });

  console.log("Đã tạo/cập nhật admin:", user.username, user.role);
}

main().finally(() => process.exit());