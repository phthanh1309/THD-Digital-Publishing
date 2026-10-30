"use client";

import { useRef, useState, useTransition } from "react";
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
  const [error, setError] = useState<string | null>(null);
  const formRef = useRef<HTMLFormElement>(null);

  function handleSubmit(formData: FormData) {
    setError(null);
    startTransition(async () => {
      try {
        await createComment(publicationId, slug, formData);
        formRef.current?.reset();
      } catch (e) {
        setError(
          e instanceof Error
            ? e.message
            : "Không gửi được bình luận. Vui lòng thử lại."
        );
      }
    });
  }

  function handleDelete(commentId: string) {
    setError(null);
    startTransition(async () => {
      try {
        await deleteComment(commentId, slug);
      } catch (e) {
        setError(
          e instanceof Error
            ? e.message
            : "Không xóa được bình luận. Vui lòng thử lại."
        );
      }
    });
  }

  return (
    <section className="publication-comments" aria-labelledby="comments-title">
      <div className="publication-comments__header">
        <h2 id="comments-title" className="publication-comments__title">
          Bình luận
        </h2>
        <span className="publication-comments__count">{comments.length}</span>
      </div>

      {error ? (
        <p className="publication-comments__error" role="alert">
          {error}
        </p>
      ) : null}

      {currentUserId ? (
        <form ref={formRef} action={handleSubmit} className="publication-comment-form">
          <label htmlFor="comment-content" className="publication-comment-form__label">
            Viết bình luận
          </label>
          <textarea
            id="comment-content"
            name="content"
            className="publication-comment-form__input"
            rows={3}
            maxLength={1000}
            placeholder="Chia sẻ cảm nhận của bạn về ấn phẩm..."
            required
            aria-describedby="comment-hint"
          />
          <div className="publication-comment-form__actions">
            <p id="comment-hint" className="publication-comment-form__hint">
              Tối đa 1000 ký tự.
            </p>
            <button
              type="submit"
              className="button button--primary"
              disabled={isPending}
              aria-busy={isPending}
            >
              {isPending ? "Đang gửi…" : "Gửi bình luận"}
            </button>
          </div>
        </form>
      ) : (
        <p className="publication-comments__signin">
          <a href="/login">Đăng nhập</a> để bình luận.
        </p>
      )}

      {comments.length === 0 ? (
        <p className="publication-comments__empty">
          Chưa có bình luận nào. Hãy là người đầu tiên chia sẻ cảm nhận về ấn phẩm này.
        </p>
      ) : (
        <ul className="publication-comments__list">
          {comments.map((c) => {
            const displayName = c.user.name ?? c.user.username;
            const created = new Date(c.createdAt);
            return (
              <li key={c.id} className="publication-comment">
                <span className="publication-comment__avatar" aria-hidden="true">
                  {displayName.trim().charAt(0).toUpperCase()}
                </span>
                <div className="publication-comment__body">
                  <div className="publication-comment__meta">
                    <span className="publication-comment__author">{displayName}</span>
                    <time
                      className="publication-comment__time"
                      dateTime={created.toISOString()}
                    >
                      {created.toLocaleString("vi-VN")}
                    </time>
                  </div>
                  <p className="publication-comment__content">{c.content}</p>
                  {c.userId === currentUserId || isAdmin ? (
                    <button
                      type="button"
                      className="publication-comment__delete"
                      onClick={() => handleDelete(c.id)}
                      aria-busy={isPending}
                    >
                      Xóa
                    </button>
                  ) : null}
                </div>
              </li>
            );
          })}
        </ul>
      )}
    </section>
  );
}
