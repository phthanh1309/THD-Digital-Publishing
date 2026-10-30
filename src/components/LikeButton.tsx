"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { toggleLike } from "@/app/(site)/publications/[slug]/like-actions";

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
  const router = useRouter();
  const [localLiked, setLocalLiked] = useState(liked);
  const [localCount, setLocalCount] = useState(count);
  const [isPending, startTransition] = useTransition();

  function handleClick() {
    if (!loggedIn) {
      router.push("/login");
      return;
    }
    setLocalLiked((v) => !v);
    setLocalCount((c) => (localLiked ? c - 1 : c + 1));
    startTransition(() => {
      toggleLike(publicationId, slug);
    });
  }

  return (
    <button
      type="button"
      onClick={handleClick}
      disabled={isPending}
      aria-pressed={localLiked}
      aria-busy={isPending}
      className="button button--secondary button--toggle"
    >
      <span aria-hidden="true">{localLiked ? "❤️" : "🤍"}</span>
      <span>
        {localLiked ? "Đã thích" : "Thích"} ({localCount})
      </span>
    </button>
  );
}
