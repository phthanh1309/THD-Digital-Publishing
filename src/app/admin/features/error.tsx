"use client";

import Link from "next/link";

export default function AdminFeaturesError({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  return (
    <main className="admin-main">
      <header className="admin-page-header">
        <div>
          <p className="admin-eyebrow">Quản trị hệ thống</p>
          <h1>Không thể tải Feature Flags</h1>
          <p className="admin-page-description">
            {error.message || "Đã xảy ra lỗi khi tải giao diện quản lý."}
          </p>
        </div>
      </header>

      <section className="admin-section">
        <div className="admin-alert admin-alert-error" role="alert">
          Yêu cầu không được xử lý. Bạn có thể thử lại hoặc quay về Dashboard.
        </div>
        <div className="feature-error-actions">
          <button type="button" onClick={reset} className="button button-primary">
            Thử lại
          </button>
          <Link href="/admin" className="button button-secondary">
            Về Dashboard
          </Link>
        </div>
      </section>
    </main>
  );
}
