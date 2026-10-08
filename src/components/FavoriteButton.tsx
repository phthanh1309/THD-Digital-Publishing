"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";

export default function FavoriteButton({
  publicationId,
  slug,
  action,
  initialSaved,
  loggedIn,
}: {
  publicationId: string;
  slug: string;
  action: (publicationId: string, slug: string) => Promise<void>;
  initialSaved: boolean;
  loggedIn: boolean;
}) {
  const router = useRouter();
  const [saved, setSaved] = useState(initialSaved);
  const [isPending, startTransition] = useTransition();

  function handleClick() {
    if (!loggedIn) {
      router.push("/login");
      return;
    }
    setSaved((v) => !v); // optimistic: đổi UI ngay, không chờ server
    startTransition(() => {
      action(publicationId, slug);
    });
  }

  return (
    <button
      type="button"
      onClick={handleClick}
      disabled={isPending}
      className="button button--secondary"
    >
      {saved ? "⭐ Đã lưu" : "☆ Lưu ấn phẩm"}
    </button>
  );
}