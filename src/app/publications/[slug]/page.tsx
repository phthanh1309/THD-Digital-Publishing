import { notFound } from "next/navigation";
import Image from "next/image";
import Link from "next/link";
import { publications } from "@/data/publications";
import { formatDate } from "@/lib/utils";
import ShareButton from "@/components/ShareButton";

export default async function PublicationDetailPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const p = publications.find(
    (item) => item.slug === slug && item.status === "published"
  );

  if (!p) notFound();

  const readerParams = new URLSearchParams({
  title: p.title,
  subtitle: p.subtitle,
  pdf: p.pdf,
  filename: `${p.slug}.pdf`,
  back: `/publications/${p.slug}`,
  download: p.allowDownload ? "1" : "0",
  print: p.allowPrint ? "1" : "0",
  share: p.allowShare ? "1" : "0",
});
const readerUrl = `/reader.html?${readerParams.toString()}`;
  const canonicalUrl = `/publications/${p.slug}`;
  const readerAvailable = p.pdf !== "";

  return (
    <article className="publication-detail">
      <div className="publication-detail__inner">
        <div className="publication-detail__cover">
          {p.cover ? (
            <Image src={p.cover} alt={p.title} fill sizes="420px" />
          ) : (
            <div className="publication-detail__cover-placeholder" aria-hidden="true">
              <span>{p.year > 0 ? p.year : "ẤN PHẨM"}</span>
            </div>
          )}
        </div>

        <div className="publication-detail__content">
          <p className="publication-detail__eyebrow">ẤN PHẨM SỐ</p>
          <h1>{p.title}</h1>

          {p.subtitle && <p className="publication-detail__subtitle">{p.subtitle}</p>}

          <dl className="publication-meta">
            {p.year > 0 && (
              <div className="publication-meta__item">
                <dt>Năm</dt>
                <dd>{p.year}</dd>
              </div>
            )}
            {p.pageCount > 0 && (
              <div className="publication-meta__item">
                <dt>Số trang</dt>
                <dd>{p.pageCount}</dd>
              </div>
            )}
            {p.language && (
              <div className="publication-meta__item">
                <dt>Ngôn ngữ</dt>
                <dd>{p.language}</dd>
              </div>
            )}
            {p.author && (
              <div className="publication-meta__item">
                <dt>Tác giả</dt>
                <dd>{p.author}</dd>
              </div>
            )}
            {p.editor && (
              <div className="publication-meta__item">
                <dt>Biên tập</dt>
                <dd>{p.editor}</dd>
              </div>
            )}
            {p.publishedAt && (
              <div className="publication-meta__item">
                <dt>Ngày xuất bản</dt>
                <dd>{formatDate(p.publishedAt)}</dd>
              </div>
            )}
          </dl>

          {p.description && (
            <div className="publication-description">
              <h2>Giới thiệu</h2>
              <p>{p.description}</p>
            </div>
          )}

          <div className="publication-actions">
            {readerAvailable ? (
              <Link className="button button--primary" href={readerUrl}>
                Đọc ấn phẩm
              </Link>
            ) : (
              <span className="button button--disabled" aria-disabled="true">
                Reader chưa khả dụng
              </span>
            )}

            {p.allowDownload && (
              <a className="button button--secondary" href={`/api/publications/${p.slug}/download`}>
                Tải xuống
              </a>
            )}
            {p.allowPrint && (
              <a className="button button--secondary" href={`/api/publications/${p.slug}/print`}>
                In ấn phẩm
              </a>
            )}
            {p.allowShare && <ShareButton url={canonicalUrl} title={p.title} />}
          </div>

          {!readerAvailable && (
            <p className="publication-notice">Ấn phẩm hiện chưa có dữ liệu đọc trực tuyến.</p>
          )}
        </div>
      </div>

      {readerAvailable && (
        <section className="publication-preview" aria-labelledby="preview-title">
          <div className="publication-preview__header">
            <div>
              <p className="section-eyebrow">XEM TRƯỚC</p>
              <h2 id="preview-title">Đọc ấn phẩm</h2>
            </div>
            <Link href={readerUrl}>Mở reader →</Link>
          </div>

          <div className="publication-preview__content">
            {p.cover ? (
              <Link href={readerUrl} aria-label={`Mở reader ${p.title}`}>
                <Image src={p.cover} alt={p.title} fill sizes="100vw" />
              </Link>
            ) : (
              <Link href={readerUrl} className="publication-preview__placeholder">
                Mở reader
              </Link>
            )}
          </div>
        </section>
      )}
    </article>
  );
}