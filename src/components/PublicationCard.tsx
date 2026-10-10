import Image from "next/image";
import Link from "next/link";
import { excerpt } from "@/lib/utils";
import { Card, CardContent } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";

type PublicationCardData = {
  id: string;
  slug: string;
  title: string;
  subtitle: string;
  year: number;
  description: string;
  cover: string;
  pageCount: number;
  author: string;
};

type Props = {
  publication: PublicationCardData;
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
    <Card className="overflow-hidden py-0 gap-0 transition-shadow hover:shadow-md">
      <Link href={href} aria-label={`Xem ${p.title}`}>
        <div className="relative aspect-[3/4] bg-muted">
          {p.cover ? (
            <Image
              src={`/api/files/${p.cover}`}
              alt={p.title}
              fill
              sizes="(max-width: 479px) 50vw, (max-width: 1024px) 33vw, 240px"
              className="object-cover"
            />
          ) : (
            <div className="flex h-full items-center justify-center text-sm font-medium text-muted-foreground">
              ẤN PHẨM
            </div>
          )}
        </div>
      </Link>

      <CardContent className="space-y-1.5 p-3">
        <Badge variant="secondary">{p.year}</Badge>

        <Heading className="line-clamp-2 text-sm font-semibold leading-snug">
          <Link href={href} className="hover:underline">
            {p.title}
          </Link>
        </Heading>

        {p.subtitle && (
          <p className="line-clamp-1 text-xs text-muted-foreground">{p.subtitle}</p>
        )}

        {p.description && (
          <p className="line-clamp-2 text-xs text-muted-foreground">
            {excerpt(p.description, excerptLength)}
          </p>
        )}

        <div className="flex items-center justify-between pt-1 text-xs">
          {p.pageCount > 0 && (
            <span className="text-muted-foreground">{p.pageCount} trang</span>
          )}
          <Link href={href} className="font-medium hover:underline">
            Xem ấn phẩm →
          </Link>
        </div>
      </CardContent>
    </Card>
  );
}