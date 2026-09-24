import { site } from "@/data/site";

export default function SiteFooter() {
  return (
    <footer className="site-footer">
      <div className="site-footer__inner">
        <div>
          <strong>{site.schoolName}</strong>
          <p>{site.platformName}</p>
        </div>
        <p>
          © {new Date().getFullYear()} {site.schoolName}
        </p>
      </div>
    </footer>
  );
}