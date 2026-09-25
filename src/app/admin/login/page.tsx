"use client";

import { useActionState } from "react";
import Link from "next/link";
import { loginAction } from "./actions";

export default function AdminLoginPage() {
  const [error, formAction, pending] = useActionState(loginAction, "");

  return (
    <div className="admin-login-page">
      <main className="admin-login">
        <section className="admin-login-card" aria-labelledby="login-title">
          <header className="admin-login-header">
            <p className="admin-login-school">THPT A Trần Hưng Đạo</p>
            <h1 id="login-title">Thư viện Ấn phẩm số</h1>
            <p className="admin-login-description">Khu vực quản trị</p>
          </header>

          {error && (
            <div className="admin-alert admin-alert-error" role="alert">
              {error}
            </div>
          )}

          <form className="admin-login-form" action={formAction}>
            <div className="form-field">
              <label htmlFor="username">Tên đăng nhập</label>
              <input
                id="username"
                name="username"
                type="text"
                autoComplete="username"
                required
                autoFocus
              />
            </div>

            <div className="form-field">
              <label htmlFor="password">Mật khẩu</label>
              <input
                id="password"
                name="password"
                type="password"
                autoComplete="current-password"
                required
              />
            </div>

            <button
              type="submit"
              className="button button-primary admin-login-submit"
              disabled={pending}
            >
              {pending ? "Đang đăng nhập..." : "Đăng nhập"}
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