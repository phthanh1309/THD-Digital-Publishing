import Image from "next/image";
import Link from "next/link";
import { site } from "@/data/site";
import SiteNav from "./SiteNav";
import { auth } from "@/lib/auth-nextauth";

export default async function SiteHeader() {
  const session = await auth();
  return (
    <header className="site-header">
      <div className="site-header__inner">
        <Link className="site-brand" href="/" aria-label={site.platformName}>
          <Image
            className="site-brand__logo"
            src={site.logo}
            alt={site.schoolName}
            width={48}
            height={48}
          />
          <span className="site-brand__text">
            <span className="site-brand__school">{site.schoolName}</span>
            <span className="site-brand__platform">{site.platformName}</span>
          </span>
        </Link>
        <SiteNav />
        <div className="site-header__actions">
          {session?.user ? (
            <>
              <Link className="site-header__action" href="/favorites">
                Đã lưu
              </Link>
              <Link className="site-header__action" href="/history">
                Lịch sử
              </Link>
              {(session.user as { role?: string }).role === "ADMIN" && (
                <Link className="site-header__admin" href="/admin">
                  ADMIN
                </Link>
              )}
              <Link
                className="site-header__action site-header__user"
                href="/profile"
              >
                {session.user.name ?? "Tài khoản"}
              </Link>
            </>
          ) : (
            <Link className="site-header__login" href="/login">
              Đăng nhập
            </Link>
          )}
        </div>
      </div>
    </header>
  );
}