import { redirect } from "next/navigation";
import { auth } from "@/lib/auth-nextauth";
import { prisma } from "@/lib/prisma";
import PublicationCard from "@/components/PublicationCard";

export default async function HistoryPage() {
  const session = await auth();
  if (!session?.user?.id) redirect("/login");

  const history = await prisma.readingHistory.findMany({
    where: { userId: session.user.id },
    orderBy: { lastReadAt: "desc" },
    include: { publication: true },
  });

  const items = history
    .map((h) => h.publication)
    .filter((p) => p.status === "published");

  return (
    <section className="library">
      <div className="library__inner">
        <h1>Lịch sử đọc</h1>

        {items.length === 0 ? (
          <div className="empty-state">
            <h2>Chưa có lịch sử</h2>
            <p>Các ấn phẩm bạn đã xem sẽ xuất hiện ở đây.</p>
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