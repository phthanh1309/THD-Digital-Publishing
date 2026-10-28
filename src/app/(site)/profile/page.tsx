import { redirect } from "next/navigation";
import Image from "next/image";
import { auth } from "@/lib/auth-nextauth";
import { prisma } from "@/lib/prisma";
import { updateProfile } from "./actions";

export default async function ProfilePage() {
  const session = await auth();
  if (!session?.user?.id) redirect("/login");

  const user = await prisma.user.findUnique({
    where: { id: session.user.id },
  });
  if (!user) redirect("/login");

  const displayName = user.name?.trim() || user.username;
  const initials =
    displayName
      .split(/\s+/)
      .filter(Boolean)
      .slice(0, 2)
      .map((part) => part.charAt(0).toUpperCase())
      .join("") || "@";

  return (
    <section className="profile-page">
      <div className="profile-page__inner">
        <header className="profile-page__header">
          <div className="profile-page__avatar-wrap">
            {user.image ? (
              <Image
                src={`/api/files/${user.image}`}
                alt={user.name ?? user.username}
                width={96}
                height={96}
                className="profile-page__avatar-img"
              />
            ) : (
              <span className="profile-page__avatar-initials" aria-hidden="true">
                {initials}
              </span>
            )}
          </div>

          <div className="profile-page__intro">
            <p className="profile-page__eyebrow">Tài khoản</p>
            <h1 className="profile-page__title">Hồ sơ cá nhân</h1>

            <div className="profile-page__meta">
              <span className="profile-page__username">@{user.username}</span>
              {user.email && (
                <span className="profile-page__email">{user.email}</span>
              )}
            </div>
          </div>
        </header>

        <form action={updateProfile} className="profile-page__form">
          <div className="profile-page__form-head">
            <h2 className="profile-page__form-title">Chỉnh sửa thông tin</h2>
            <p className="profile-page__form-desc">
              Cập nhật tên hiển thị, giới thiệu và ảnh đại diện của bạn.
            </p>
          </div>

          <div className="profile-page__field">
            <label htmlFor="name" className="profile-page__label">
              Tên hiển thị
            </label>
            <input
              id="name"
              name="name"
              type="text"
              defaultValue={user.name ?? ""}
              maxLength={80}
              placeholder="Tên bạn muốn hiển thị"
              className="profile-page__control"
            />
            <p className="profile-page__hint">Tối đa 80 ký tự.</p>
          </div>

          <div className="profile-page__field">
            <label htmlFor="bio" className="profile-page__label">
              Giới thiệu
            </label>
            <textarea
              id="bio"
              name="bio"
              rows={4}
              defaultValue={user.bio ?? ""}
              maxLength={500}
              placeholder="Vài dòng giới thiệu về bạn…"
              className="profile-page__control profile-page__control--textarea"
            />
            <p className="profile-page__hint">Tối đa 500 ký tự.</p>
          </div>

          <div className="profile-page__field">
            <label htmlFor="avatarFile" className="profile-page__label">
              Ảnh đại diện
            </label>
            <input
              id="avatarFile"
              name="avatarFile"
              type="file"
              accept="image/jpeg,image/png,image/webp"
              className="profile-page__file"
            />
            <p className="profile-page__hint">JPG, PNG hoặc WebP, tối đa 5MB.</p>
          </div>

          <div className="profile-page__actions">
            <button
              type="submit"
              className="button button--primary profile-page__submit"
            >
              Lưu thay đổi
            </button>
          </div>
        </form>
      </div>
    </section>
  );
}