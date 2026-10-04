"use client";

import { useTransition } from "react";
import { toggleLike } from "@/app/(site)/publications/[slug]/like-actions";
import { useRouter } from "next/navigation";

export default function LikeButton({
  publicationId,
  slug,
  liked,
  count,
  loggedIn,
}: {
  publicationId: string;
  slug: string;
  liked: boolean;
  count: number;
  loggedIn: boolean;
}) {
  const [isPending, startTransition] = useTransition();
  const router = useRouter();

  function handleClick() {
    if (!loggedIn) {
      router.push("/login");
      return;
    }
    startTransition(() => {
      toggleLike(publicationId, slug);
    });
  }

  return (
    <button
      type="button"
      onClick={handleClick}
      disabled={isPending}
      className="button button--secondary"
    >
      {liked ? "❤️ Đã thích" : "🤍 Thích"} ({count})
    </button>
  );
}