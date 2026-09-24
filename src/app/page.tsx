import Link from "next/link";
import PublicationCard from "@/components/PublicationCard";
import { publications } from "@/data/publications";
import { site } from "@/data/site";

export default function HomePage() {
  const latest = publications
    .filter((p) => p.status === "published")
    .sort((a, b) => b.year - a.year)
    .slice(0, 6);

  return (
    <>
      <section className="hero">
        <div className="hero__inner">
          <p className="hero__eyebrow">THƯ VIỆN ẤN PHẨM SỐ</p>
          <h1 className="hero__title">{site.platformName}</h1>
          <p className="hero__description">{site.description}</p>

          <div className="hero__actions">
            <Link className="button button--primary" href="/publications">
              Khám phá thư viện
            </Link>
            <Link className="button button--secondary" href="/search">
              Tìm kiếm ấn phẩm
            </Link>
          </div>
        </div>
      </section>

      <section
        className="publication-section"
        aria-labelledby="latest-publications-title"
      >
        <div className="publication-section__header">
          <div>
            <p className="section-eyebrow">ẤN PHẨM</p>
            <h2 id="latest-publications-title">Ấn phẩm mới nhất</h2>
          </div>
          <Link href="/publications" className="section-link">
            Xem toàn bộ thư viện
          </Link>
        </div>

        {latest.length === 0 ? (
          <div className="empty-state">
            <h3>Thư viện đang được cập nhật</h3>
            <p>Hiện chưa có ấn phẩm nào được xuất bản.</p>
          </div>
        ) : (
          <div className="publication-grid">
            {latest.map((p) => (
              <PublicationCard key={p.id} publication={p} />
            ))}
          </div>
        )}
      </section>
    </>
  );
}