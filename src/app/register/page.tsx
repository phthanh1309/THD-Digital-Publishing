"use client";

import { useActionState } from "react";
import Link from "next/link";
import { registerAction } from "./actions";
import "../admin/admin.css";

export default function RegisterPage() {
  const [message, formAction, pending] = useActionState(registerAction, "");

  if (message === "SUCCESS") {
    return (
      <div className="admin-login-page">
        <main className="admin-login">
          <section className="admin-login-card">
            <h1>Kiểm tra email của bạn</h1>
            <p>Mình đã gửi liên kết xác thực. Bấm vào liên kết trong email để kích hoạt tài khoản.</p>
            <Link href="/login">← Quay lại đăng nhập</Link>
          </section>
        </main>
      </div>
    );
  }

  return (
    <div className="admin-login-page">
      <main className="admin-login">
        <section className="admin-login-card">
          <header className="admin-login-header">
            <h1>Đăng ký tài khoản</h1>
          </header>

          {message && (
            <div className="admin-alert admin-alert-error" role="alert">
              {message}
            </div>
          )}

          <form className="admin-login-form" action={formAction}>
            <div className="form-field">
              <label htmlFor="username">Tên đăng nhập</label>
              <input id="username" name="username" type="text" required />
            </div>
            <div className="form-field">
              <label htmlFor="email">Email</label>
              <input id="email" name="email" type="email" required />
            </div>
            <div className="form-field">
              <label htmlFor="password">Mật khẩu</label>
              <input id="password" name="password" type="password" required minLength={8} />
            </div>
            <button type="submit" className="button button-primary" disabled={pending}>
              {pending ? "Đang xử lý..." : "Đăng ký"}
            </button>
          </form>

          <footer className="admin-login-footer">
            <Link href="/login">Đã có tài khoản? Đăng nhập</Link>
          </footer>
        </section>
      </main>
    </div>
  );
}