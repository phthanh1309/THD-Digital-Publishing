export default function AdminFeaturesLoading() {
  return (
    <main className="admin-main">
      <header className="admin-page-header">
        <div>
          <p className="admin-eyebrow">Quản trị hệ thống</p>
          <h1>Feature Flags</h1>
          <p className="admin-page-description">
            Đang tải cấu hình Feature Flag...
          </p>
        </div>
      </header>

      <section className="admin-section" aria-busy="true">
        <div className="feature-flag-grid">
          {Array.from({ length: 6 }, (_, index) => (
            <div key={index} className="feature-skeleton-card" />
          ))}
        </div>
        <p className="feature-note" role="status">
          Đang tải Global Feature Flags, override và Audit Log.
        </p>
      </section>
    </main>
  );
}
