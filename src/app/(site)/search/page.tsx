import Link from "next/link";
import PublicationCard from "@/components/PublicationCard";
import { publications } from "@/data/publications";

const PER_PAGE = 12;

type SearchParams = {
  q?: string;
  year?: string;
  sort?: string;
  page?: string;
};

function buildUrl(params: Record<string, string | number | undefined>) {
  const usp = new URLSearchParams();
  for (const [k, v] of Object.entries(params)) {
    if (v !== undefined && v !== "" && v !== null) usp.set(k, String(v));
  }
  const qs = usp.toString();
  return `/search${qs ? `?${qs}` : ""}`;
}

export default async function SearchPage({
  searchParams,
}: {
  searchParams: Promise<SearchParams>;
}) {
  const sp = await searchParams;
  const q = sp.q?.trim() ?? "";
  const year = sp.year ? Number(sp.year) : undefined;
  const sort = sp.sort ?? "newest";
  const page = Math.max(1, Number(sp.page) || 1);

  let items = publications.filter((p) => p.status === "published");

  if (q) {
    const needle = q.toLowerCase();
    items = items.filter(
      (p) =>
        p.title.toLowerCase().includes(needle) ||
        p.author.toLowerCase().includes(needle) ||
        p.description.toLowerCase().includes(needle)
    );
  }

  if (year) {
    items = items.filter((p) => p.year === year);
  }

  items = [...items].sort((a, b) => {
    switch (sort) {
      case "oldest":
        return a.year - b.year;
      case "title_asc":
        return a.title.localeCompare(b.title, "vi");
      case "title_desc":
        return b.title.localeCompare(a.title, "vi");
      default:
        return b.year - a.year;
    }
  });

  const totalItems = items.length;
  const totalPages = Math.max(1, Math.ceil(totalItems / PER_PAGE));
  const currentPage = Math.min(page, totalPages);
  const pageItems = items.slice(
    (currentPage - 1) * PER_PAGE,
    currentPage * PER_PAGE
  );

  const availableYears = [
    ...new Set(
      publications.filter((p) => p.status === "published").map((p) => p.year)
    ),
  ].sort((a, b) => b - a);

  const startPage = Math.max(1, currentPage - 2);
  const endPage = Math.min(totalPages, currentPage + 2);
  const pageNumbers = Array.from(
    { length: endPage - startPage + 1 },
    (_, i) => startPage + i
  );

  const searchTitle = q ? `Tìm kiếm: ${q}` : "Tìm kiếm ấn phẩm";
  const hasActiveFilter = q !== "" || year !== undefined || sort !== "newest";

  return (
    <>
      <section className="library-header">
        <div className="library-header__inner">
          <p className="section-eyebrow">THƯ VIỆN ẤN PHẨM SỐ</p>
          <h1>{searchTitle}</h1>
          <p>Tìm kiếm các ấn phẩm đã được công bố trong thư viện.</p>
        </div>
      </section>

      <section className="library">
        <div className="library__inner">
          <form className="library-filters" method="get" action="/search">
            <div className="filter-field">
              <label htmlFor="search-q">Từ khóa</label>
              <input
                type="search"
                id="search-q"
                name="q"
                defaultValue={q}
                placeholder="Tên ấn phẩm, tác giả, nội dung..."
                autoComplete="off"
              />
            </div>

            <div className="filter-field">
              <label htmlFor="search-year">Năm</label>
              <select id="search-year" name="year" defaultValue={year ?? ""}>
                <option value="">Tất cả các năm</option>
                {availableYears.map((y) => (
                  <option key={y} value={y}>
                    {y}
                  </option>
                ))}
              </select>
            </div>

            <div className="filter-field">
              <label htmlFor="search-sort">Sắp xếp</label>
              <select id="search-sort" name="sort" defaultValue={sort}>
                <option value="newest">Mới nhất</option>
                <option value="oldest">Cũ nhất</option>
                <option value="title_asc">Tên A–Z</option>
                <option value="title_desc">Tên Z–A</option>
              </select>
            </div>

            <div className="filter-actions">
              <button type="submit" className="button button--primary">
                Tìm kiếm
              </button>
              {hasActiveFilter && (
                <Link href="/search" className="button button--secondary">
                  Xóa bộ lọc
                </Link>
              )}
            </div>
          </form>

          <div className="library-results__summary">
            <p>
              <strong>{totalItems.toLocaleString("vi-VN")}</strong> ấn phẩm được tìm thấy.
            </p>
          </div>

          {pageItems.length === 0 ? (
            <div className="empty-state">
              <h2>Không tìm thấy ấn phẩm</h2>
              <p>
                Không có ấn phẩm phù hợp với điều kiện tìm kiếm. Hãy thử từ
                khóa khác hoặc bỏ bớt bộ lọc.
              </p>
              <Link href="/publications" className="button button--secondary">
                Xem toàn bộ thư viện
              </Link>
            </div>
          ) : (
            <>
              <div className="publication-grid">
                {pageItems.map((p) => (
                  <PublicationCard key={p.id} publication={p} excerptLength={150} />
                ))}
              </div>

              {totalPages > 1 && (
                <nav className="pagination" aria-label="Phân trang kết quả tìm kiếm">
                  {currentPage > 1 && (
                    <Link
                      href={buildUrl({ q, year, sort, page: currentPage - 1 })}
                      rel="prev"
                    >
                      ← Trang trước
                    </Link>
                  )}

                  <div className="pagination__pages">
                    {pageNumbers.map((n) =>
                      n === currentPage ? (
                        <span key={n} aria-current="page">
                          {n}
                        </span>
                      ) : (
                        <Link key={n} href={buildUrl({ q, year, sort, page: n })}>
                          {n}
                        </Link>
                      )
                    )}
                  </div>

                  {currentPage < totalPages && (
                    <Link
                      href={buildUrl({ q, year, sort, page: currentPage + 1 })}
                      rel="next"
                    >
                      Trang sau →
                    </Link>
                  )}
                </nav>
              )}
            </>
          )}
        </div>
      </section>
    </>
  );
}