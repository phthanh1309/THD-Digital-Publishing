import Link from "next/link";
import { prisma } from "@/lib/prisma";
import PublicationCard from "@/components/PublicationCard";
import { buttonVariants } from "@/components/ui/button";

export default async function HomePage() {
  const latest = await prisma.publication.findMany({
    where: { status: "published" },
    orderBy: { year: "desc" },
    take: 8,
  });

  return (
    <>
      <section className="bg-gradient-to-br from-primary to-primary/70 px-4 py-20 text-center text-primary-foreground">
        <div className="mx-auto max-w-2xl space-y-4">
          <p className="inline-block rounded-full bg-white/15 px-3 py-1 text-xs font-semibold tracking-wide">
            THƯ VIỆN ẤN PHẨM SỐ
          </p>
          <h1 className="text-4xl font-extrabold sm:text-5xl">Thư viện Ấn phẩm số</h1>
          <p className="text-primary-foreground/90">
            Thư viện Ấn phẩm số của THPT A Trần Hưng Đạo.
          </p>
          <div className="flex justify-center gap-3 pt-2">
            <Link href="/publications" className={buttonVariants({ size: "lg", variant: "secondary" })}>
              Khám phá thư viện
            </Link>
            <Link
              href="/search"
              className={buttonVariants({ size: "lg", variant: "outline" }) + " bg-transparent text-primary-foreground hover:bg-white/10"}
            >
              Tìm kiếm ấn phẩm
            </Link>
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-6xl px-4 py-12">
        <div className="mb-6 flex items-end justify-between">
          <div>
            <p className="text-xs font-semibold tracking-wide text-muted-foreground">ẤN PHẨM</p>
            <h2 className="text-2xl font-bold">Ấn phẩm mới nhất</h2>
          </div>
          <Link href="/publications" className="text-sm font-medium hover:underline">
            Xem toàn bộ thư viện →
          </Link>
        </div>

        {latest.length === 0 ? (
          <div className="rounded-lg border border-dashed p-10 text-center">
            <h3 className="font-semibold">Thư viện đang được cập nhật</h3>
            <p className="text-sm text-muted-foreground">
              Hiện chưa có ấn phẩm nào được xuất bản.
            </p>
          </div>
        ) : (
          <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            {latest.map((p) => (
              <PublicationCard key={p.id} publication={p} />
            ))}
          </div>
        )}
      </section>
    </>
  );
}