import Link from "next/link";
import { prisma } from "@/lib/prisma";
import "../admin/admin.css";

export default async function VerifyPage({
  searchParams,
}: {
  searchParams: Promise<{ token?: string; email?: string }>;
}) {
  const { token, email } = await searchParams;

  if (!token || !email) {
    return <VerifyResult success={false} message="Liên kết không hợp lệ." />;
  }

  const record = await prisma.verificationToken.findUnique({
    where: { identifier_token: { identifier: email, token } },
  });

  if (!record) {
    return (
      <VerifyResult
        success={false}
        message="Liên kết không hợp lệ hoặc đã được sử dụng."
      />
    );
  }

  if (record.expires < new Date()) {
    await prisma.verificationToken.delete({
      where: { identifier_token: { identifier: email, token } },
    });
    return (
      <VerifyResult
        success={false}
        message="Liên kết đã hết hạn. Vui lòng đăng ký lại."
      />
    );
  }

  await prisma.user.update({
    where: { email },
    data: { emailVerified: new Date() },
  });

  await prisma.verificationToken.delete({
    where: { identifier_token: { identifier: email, token } },
  });

  return (
    <VerifyResult
      success={true}
      message="Xác thực thành công! Bạn có thể đăng nhập ngay."
    />
  );
}

function VerifyResult({ success, message }: { success: boolean; message: string }) {
  return (
    <div className="admin-login-page">
      <main className="admin-login">
        <section className="admin-login-card">
          <h1>{success ? "Thành công" : "Có lỗi xảy ra"}</h1>
          <p>{message}</p>
          <Link href="/login">→ Đến trang đăng nhập</Link>
        </section>
      </main>
    </div>
  );
}