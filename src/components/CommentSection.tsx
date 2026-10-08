"use client";

import { useTransition } from "react";
import { createComment, deleteComment } from "@/app/(site)/publications/[slug]/comment-actions";

type CommentItem = {
  id: string;
  content: string;
  userId: string;
  createdAt: Date;
  user: { name: string | null; username: string; image: string | null };
};

export default function CommentSection({
  publicationId,
  slug,
  comments,
  currentUserId,
  isAdmin,
}: {
  publicationId: string;
  slug: string;
  comments: CommentItem[];
  currentUserId?: string;
  isAdmin: boolean;
}) {
  const [isPending, startTransition] = useTransition();

  function handleSubmit(formData: FormData) {
    startTransition(() => {
      createComment(publicationId, slug, formData);
    });
  }

  function handleDelete(commentId: string) {
    startTransition(() => {
      deleteComment(commentId, slug);
    });
  }

  return (
    <section style={{ marginTop: 32 }}>
      <h2>Bình luận ({comments.length})</h2>

      {currentUserId ? (
        <form action={handleSubmit} style={{ marginBottom: 24 }}>
          <textarea
            name="content"
            rows={3}
            maxLength={1000}
            placeholder="Viết bình luận..."
            required
            style={{ width: "100%", padding: 8 }}
          />
          <button type="submit" className="button button--primary" disabled={isPending} style={{ marginTop: 8 }}>
            Gửi bình luận
          </button>
        </form>
      ) : (
        <p>
          <a href="/login">Đăng nhập</a> để bình luận.
        </p>
      )}

      <ul style={{ listStyle: "none", padding: 0, display: "flex", flexDirection: "column", gap: 16 }}>
        {comments.map((c) => (
          <li key={c.id} style={{ borderBottom: "1px solid #eee", paddingBottom: 12 }}>
            <strong>{c.user.name ?? c.user.username}</strong>{" "}
            <span style={{ color: "#999", fontSize: 13 }}>
              {new Date(c.createdAt).toLocaleString("vi-VN")}
            </span>
            <p style={{ margin: "4px 0 0" }}>{c.content}</p>
            {c.userId === currentUserId || isAdmin ? (
              <button
                type="button"
                onClick={() => handleDelete(c.id)}
                style={{ fontSize: 13, color: "#c00", background: "none", border: "none", cursor: "pointer", padding: 0 }}
              >
                Xóa
              </button>
            ) : null}
          </li>
        ))}
      </ul>
    </section>
  );
}