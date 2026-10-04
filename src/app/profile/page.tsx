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

  return (
    <section style={{ maxWidth: 480, margin: "40px auto" }}>
      <h1>Hồ sơ cá nhân</h1>

      <p style={{ color: "#666" }}>
        @{user.username} {user.email && `· ${user.email}`}
      </p>

      {user.image && (
        <Image
          src={`/api/files/${user.image}`}
          alt={user.name ?? user.username}
          width={96}
          height={96}
          style={{ borderRadius: "50%", objectFit: "cover" }}
        />
      )}

      <form
        action={updateProfile}
        style={{ display: "flex", flexDirection: "column", gap: 16, marginTop: 24 }}
      >
        <div className="form-field">
          <label htmlFor="name">Tên hiển thị</label>
          <input id="name" name="name" type="text" defaultValue={user.name ?? ""} />
        </div>

        <div className="form-field">
          <label htmlFor="bio">Giới thiệu</label>
          <textarea id="bio" name="bio" rows={3} defaultValue={user.bio ?? ""} />
        </div>

        <div className="form-field">
          <label htmlFor="avatarFile">Ảnh đại diện</label>
          <input id="avatarFile" name="avatarFile" type="file" accept="image/jpeg,image/png,image/webp" />
        </div>

        <button type="submit" className="button button-primary">
          Lưu thay đổi
        </button>
      </form>
    </section>
  );
}