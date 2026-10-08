import { redirect } from "next/navigation";
import { auth } from "@/lib/auth-nextauth";
import { prisma } from "@/lib/prisma";
import PublicationCard from "@/components/PublicationCard";

export default async function FavoritesPage() {
  const session = await auth();
  if (!session?.user?.id) redirect("/login");

  const favorites = await prisma.favorite.findMany({
    where: { userId: session.user.id },
    orderBy: { createdAt: "desc" },
    include: { publication: true },
  });

  const items = favorites
    .map((f) => f.publication)
    .filter((p) => p.status === "published"); // phòng khi ấn phẩm đã bị admin chuyển về draft/archived

  return (
    <section className="library">
      <div className="library__inner">
        <h1>Ấn phẩm đã lưu</h1>

        {items.length === 0 ? (
          <div className="empty-state">
            <h2>Chưa có ấn phẩm nào</h2>
            <p>Bấm &quot;Lưu ấn phẩm&quot; ở trang chi tiết để lưu lại đọc sau.</p>
          </div>
        ) : (
          <div className="publication-grid">
            {items.map((p) => (
              <PublicationCard key={p.id} publication={p} excerptLength={140} />
            ))}
          </div>
        )}
      </div>
    </section>
  );
}