import Image from "next/image";
import Link from "next/link";
import type { Publication } from "@/data/publications";
import { excerpt } from "@/lib/utils";

type Props = {
  publication: Publication;
  headingLevel?: 2 | 3;
  excerptLength?: number;
};

export default function PublicationCard({
  publication: p,
  headingLevel = 3,
  excerptLength = 140,
}: Props) {
  const href = `/publications/${p.slug}`;
  const Heading = `h${headingLevel}` as "h2" | "h3";

  return (
    <article className="publication-card">
      <Link
        className="publication-card__cover"
        href={href}
        aria-label={`Xem ${p.title}`}
      >
        {p.cover ? (
          <Image
            src={p.cover}
            alt={p.title}
            fill
            sizes="(max-width: 479px) 108px, (max-width: 1024px) 50vw, 300px"
          />
        ) : (
          <span
            className="publication-card__cover-placeholder"
            aria-hidden="true"
          >
            ẤN PHẨM
          </span>
        )}
      </Link>

      <div className="publication-card__content">
        <p className="publication-card__year">{p.year}</p>

        <Heading className="publication-card__title">
          <Link href={href}>{p.title}</Link>
        </Heading>

        {p.subtitle && (
          <p className="publication-card__subtitle">{p.subtitle}</p>
        )}

        {p.description && (
          <p className="publication-card__description">
            {excerpt(p.description, excerptLength)}
          </p>
        )}

        <div className="publication-card__meta">
          {p.pageCount > 0 && <span>{p.pageCount} trang</span>}
          <Link href={href}>Xem ấn phẩm →</Link>
        </div>
      </div>
    </article>
  );
}