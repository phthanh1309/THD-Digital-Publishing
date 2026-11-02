import Link from "next/link";
import { isRegistrationEnabled } from "@/lib/features/registration-gate";
import RegisterForm from "./register-form";
import "../admin/admin.css";

// The register feature flag is read from the database, so this page must be
// rendered per request and never baked into a static build.
export const dynamic = "force-dynamic";

export default async function RegisterPage() {
  const enabled = await isRegistrationEnabled();

  if (!enabled) {
    return (
      <div className="admin-login-page">
        <main className="admin-login">
          <section className="admin-login-card" aria-labelledby="register-closed-title">
            <header className="admin-login-header">
              <h1 id="register-closed-title">Đăng ký tạm khóa</h1>
            </header>
            <div className="admin-alert admin-alert-error" role="alert">
              Tính năng đăng ký tài khoản hiện đang tạm khóa. Vui lòng quay lại sau.
            </div>
            <footer className="admin-login-footer">
              <Link href="/login">← Quay lại đăng nhập</Link>
            </footer>
          </section>
        </main>
      </div>
    );
  }

  return <RegisterForm />;
}
