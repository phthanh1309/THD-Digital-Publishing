import Link from "next/link";
import { prisma } from "@/lib/prisma";
import { deletePublication } from "./publications/actions";

const statusLabel: Record<string, string> = {
  published: "Đã xuất bản",
  draft: "Bản nháp",
  archived: "Lưu trữ",
};

function formatDateTime(d: Date) {
  return d.toLocaleString("vi-VN", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

export default async function AdminDashboard() {
  const publications = await prisma.publication.findMany({
    orderBy: { updatedAt: "desc" },
  });

  const total = publications.length;
  const published = publications.filter((p) => p.status === "published").length;
  const draft = publications.filter((p) => p.status === "draft").length;
  const archived = publications.filter((p) => p.status === "archived").length;

  const recent = publications.slice(0, 8);

  return (
    <>
      <header className="admin-header">
        <div className="admin-header-inner">
          <div className="admin-brand">
            <Link href="/admin" className="admin-brand-link">
              <span className="admin-brand-text">
                <span className="admin-brand-school">THPT A Trần Hưng Đạo</span>
                <span className="admin-brand-name">Thư viện Ấn phẩm số</span>
              </span>
            </Link>
          </div>

          <nav className="admin-nav" aria-label="Điều hướng quản trị">
            <Link href="/admin" aria-current="page">Dashboard</Link>
            <Link href="/">Xem thư viện</Link>
          </nav>

          <div className="admin-account">
            <form action="/admin/logout" method="post">
              <button type="submit" className="button button-secondary">
                Đăng xuất
              </button>
            </form>
          </div>
        </div>
      </header>

      <main className="admin-main">
        <header className="admin-page-header">
          <div>
            <p className="admin-eyebrow">Quản trị hệ thống</p>
            <h1>Dashboard</h1>
            <p className="admin-page-description">
              Tổng quan tình trạng các ấn phẩm trong thư viện.
            </p>
          </div>

          <div className="admin-page-actions">
            <Link href="/admin/publications/new" className="button button-primary">
              + Thêm ấn phẩm
            </Link>
          </div>
        </header>

        <section className="admin-stats" aria-label="Tổng quan ấn phẩm">
          <article className="admin-stat-card">
            <span className="admin-stat-label">Tổng ấn phẩm</span>
            <strong className="admin-stat-value">{total}</strong>
            <span className="admin-stat-note">Tất cả trạng thái</span>
          </article>
          <article className="admin-stat-card">
            <span className="admin-stat-label">Đã xuất bản</span>
            <strong className="admin-stat-value">{published}</strong>
            <span className="admin-stat-note">Hiển thị công khai</span>
          </article>
          <article className="admin-stat-card">
            <span className="admin-stat-label">Bản nháp</span>
            <strong className="admin-stat-value">{draft}</strong>
            <span className="admin-stat-note">Chưa công khai</span>
          </article>
          <article className="admin-stat-card">
            <span className="admin-stat-label">Lưu trữ</span>
            <strong className="admin-stat-value">{archived}</strong>
            <span className="admin-stat-note">Không hiển thị công khai</span>
          </article>
        </section>

        <section className="admin-section">
          <div className="admin-section-header">
            <div>
              <h2>Danh sách ấn phẩm</h2>
              <p>Toàn bộ ấn phẩm hiện có, mới cập nhật lên đầu.</p>
            </div>
          </div>

          {recent.length === 0 ? (
            <div className="admin-empty-state">
              <h3>Chưa có ấn phẩm</h3>
              <p>Hãy tạo ấn phẩm đầu tiên để bắt đầu xây dựng thư viện.</p>
              <Link href="/admin/publications/new" className="button button-primary">
                Tạo ấn phẩm
              </Link>
            </div>
          ) : (
            <div className="admin-table-wrapper">
              <table className="admin-table">
                <thead>
                  <tr>
                    <th scope="col">Ấn phẩm</th>
                    <th scope="col">Năm</th>
                    <th scope="col">Trạng thái</th>
                    <th scope="col">Cập nhật</th>
                    <th scope="col">Thao tác</th>
                  </tr>
                </thead>
                <tbody>
                  {recent.map((p) => (
                    <tr key={p.id}>
                      <td>
                        <div className="admin-publication-cell">
                          <strong>{p.title}</strong>
                          <small>{p.slug}</small>
                        </div>
                      </td>
                      <td>{p.year}</td>
                      <td>
                        <span className={`status-badge status-${p.status}`}>
                          {statusLabel[p.status] ?? "Không xác định"}
                        </span>
                      </td>
                      <td>{formatDateTime(p.updatedAt)}</td>
                      <td style={{ display: "flex", gap: 8 }}>
                        <Link href={`/admin/publications/${p.id}/edit`}>Chỉnh sửa</Link>
                        <form
                          action={async () => {
                            "use server";
                            await deletePublication(p.id);
                          }}
                        >
                          <button
                            type="submit"
                            className="button button-secondary"
                            style={{ padding: "2px 8px" }}
                          >
                            Xóa
                          </button>
                        </form>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>
      </main>

      <footer className="admin-footer">
        <p>THPT A Trần Hưng Đạo · Thư viện Ấn phẩm số</p>
      </footer>
    </>
  );
}