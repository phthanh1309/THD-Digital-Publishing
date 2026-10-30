"use client";

import { useEffect, useState } from "react";

export default function ShareButton({
  url,
  title,
}: {
  url: string;
  title: string;
}) {
  const [copied, setCopied] = useState(false);

  useEffect(() => {
    if (!copied) return;
    const timer = setTimeout(() => setCopied(false), 2500);
    return () => clearTimeout(timer);
  }, [copied]);

  async function handleClick() {
    if (navigator.share) {
      try {
        await navigator.share({ title, url });
        return;
      } catch {
        return;
      }
    }
    try {
      await navigator.clipboard.writeText(url);
      setCopied(true);
    } catch {
      window.prompt("Sao chép liên kết:", url);
    }
  }

  return (
    <button
      type="button"
      className="button button--secondary"
      onClick={handleClick}
    >
      <span aria-hidden="true">{copied ? "✓" : "🔗"}</span>
      <span>{copied ? "Đã chép" : "Chia sẻ"}</span>
      {/* Vùng live có sẵn từ lần render đầu để trình đọc màn hình đọc được
          thay đổi (chèn mới một vùng live đã có nội dung sẽ không được đọc). */}
      <span className="visually-hidden" role="status">
        {copied ? "Đã sao chép liên kết." : ""}
      </span>
    </button>
  );
}
