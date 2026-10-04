import { signIn } from "@/lib/auth-nextauth";
import "../admin/admin.css";
import Link from "next/link";

export default function LoginPage({
  searchParams,
}: {
  searchParams: Promise<{ error?: string }>;
}) {
  return (
    <div className="admin-login-page">
      <main className="admin-login">
        <section className="admin-login-card" aria-labelledby="login-title">
          <header className="admin-login-header">
            <p className="admin-login-school">THPT A Trần Hưng Đạo</p>
            <h1 id="login-title">Thư viện Ấn phẩm số</h1>
            <p className="admin-login-description">Đăng nhập</p>
          </header>

          <LoginError searchParams={searchParams} />

          <form
            className="admin-login-form"
            action={async (formData) => {
              "use server";
              await signIn("credentials", {
                username: formData.get("username"),
                password: formData.get("password"),
                redirectTo: "/",
              });
            }}
          >
            <div className="form-field">
              <label htmlFor="username">Tên đăng nhập</label>
              <input id="username" name="username" type="text" required autoFocus />
            </div>

            <div className="form-field">
              <label htmlFor="password">Mật khẩu</label>
              <input id="password" name="password" type="password" required />
            </div>

            <button type="submit" className="button button-primary admin-login-submit">
              Đăng nhập
            </button>
          </form>
          <div style={{ textAlign: "center", margin: "16px 0", color: "#999" }}>
            hoặc
          </div>

          <form
            action={async () => {
              "use server";
              const { signIn } = await import("@/lib/auth-nextauth");
              await signIn("google", { redirectTo: "/" });
            }}
          >
            <button type="submit" className="button button-secondary" style={{ width: "100%" }}>
              Đăng nhập bằng Google
            </button>
          </form>

          <form
            action={async () => {
              "use server";
              const { signIn } = await import("@/lib/auth-nextauth");
              await signIn("facebook", { redirectTo: "/" });
            }}
            style={{ marginTop: 8 }}
          >
            <button type="submit" className="button button-secondary" style={{ width: "100%" }}>
              Đăng nhập bằng Facebook
            </button>
          </form>

          <footer className="admin-login-footer">
            <Link href="/">← Quay lại thư viện</Link>
          </footer>
        </section>
      </main>
    </div>
  );
}

async function LoginError({
  searchParams,
}: {
  searchParams: Promise<{ error?: string }>;
}) {
  const sp = await searchParams;
  if (!sp.error) return null;
  return (
    <div className="admin-alert admin-alert-error" role="alert">
      Tên đăng nhập hoặc mật khẩu không đúng.
    </div>
  );
}