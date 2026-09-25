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
  return `/publications${qs ? `?${qs}` : ""}`;
}

export default async function PublicationsPage({
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
        p.author.toLowerCase().includes(needle)
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

  return (
    <>
      <section className="library-header">
        <div className="library-header__inner">
          <p className="section-eyebrow">THƯ VIỆN</p>
          <h1>Ấn phẩm số</h1>
          <p>
            Khám phá các ấn phẩm đã được xuất bản trong thư viện số của nhà
            trường.
          </p>
        </div>
      </section>

      <section className="library" aria-labelledby="library-title">
        <div className="library__inner">
          <h2 id="library-title" className="visually-hidden">
            Danh sách ấn phẩm
          </h2>

          <form className="library-filters" method="get" action="/publications">
            <div className="filter-field">
              <label htmlFor="publication-search">Tìm kiếm</label>
              <input
                type="search"
                id="publication-search"
                name="q"
                defaultValue={q}
                placeholder="Tên ấn phẩm, tác giả..."
                maxLength={200}
              />
            </div>

            <div className="filter-field">
              <label htmlFor="publication-year">Năm</label>
              <select id="publication-year" name="year" defaultValue={year ?? ""}>
                <option value="">Tất cả năm</option>
                {availableYears.map((y) => (
                  <option key={y} value={y}>
                    {y}
                  </option>
                ))}
              </select>
            </div>

            <div className="filter-field">
              <label htmlFor="publication-sort">Sắp xếp</label>
              <select id="publication-sort" name="sort" defaultValue={sort}>
                <option value="newest">Mới nhất</option>
                <option value="oldest">Cũ nhất</option>
                <option value="title_asc">Tên A–Z</option>
                <option value="title_desc">Tên Z–A</option>
              </select>
            </div>

            <div className="filter-actions">
              <button type="submit" className="button button--primary">
                Lọc
              </button>
              <Link href="/publications" className="button button--secondary">
                Xóa lọc
              </Link>
            </div>
          </form>

          <div className="library-results__summary">
            {totalItems > 0 ? (
              <p>
                Tìm thấy <strong>{totalItems}</strong> ấn phẩm.
              </p>
            ) : (
              <p>Không tìm thấy ấn phẩm phù hợp.</p>
            )}
          </div>

          {pageItems.length === 0 ? (
            <div className="empty-state">
              <h2>Chưa có kết quả</h2>
              <p>
                {q || year
                  ? "Hãy thử thay đổi từ khóa hoặc bộ lọc năm."
                  : "Thư viện hiện chưa có ấn phẩm được xuất bản."}
              </p>
            </div>
          ) : (
            <>
              <div className="publication-grid">
                {pageItems.map((p) => (
                  <PublicationCard key={p.id} publication={p} excerptLength={160} />
                ))}
              </div>

              {totalPages > 1 && (
                <nav className="pagination" aria-label="Phân trang">
                  {currentPage > 1 && (
                    <Link
                      href={buildUrl({ q, year, sort, page: currentPage - 1 })}
                      rel="prev"
                    >
                      ← Trước
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
                      Sau →
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