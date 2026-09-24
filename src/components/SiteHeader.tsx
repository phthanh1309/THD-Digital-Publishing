import Image from "next/image";
import Link from "next/link";
import { site } from "@/data/site";
import SiteNav from "./SiteNav";

export default function SiteHeader() {
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
      </div>
    </header>
  );
}