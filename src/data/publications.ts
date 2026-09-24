export type Publication = {
  id: string;
  slug: string;
  title: string;
  subtitle: string;
  year: number;
  description: string;
  cover: string;
  pdf: string;
  pageCount: number;
  status: "draft" | "published" | "archived";
  author: string;
  allowDownload: boolean;
  allowPrint: boolean;
  allowShare: boolean;
};

export const publications: Publication[] = [
  {
    id: "pub_001",
    slug: "tap-san-2026",
    title: "Tập san 2026",
    subtitle: "Số đặc biệt",
    year: 2026,
    description: "Ấn phẩm mẫu để thử giao diện.",
    cover: "/logo.webp",
    pdf: "",
    pageCount: 24,
    status: "published",
    author: "Ban biên tập",
    allowDownload: true,
    allowPrint: false,
    allowShare: true,
  },
  {
    id: "pub_002",
    slug: "ky-yeu-2025",
    title: "Kỷ yếu 2025",
    subtitle: "",
    year: 2025,
    description: "Ấn phẩm mẫu thứ hai.",
    cover: "/logo.webp",
    pdf: "",
    pageCount: 40,
    status: "published",
    author: "Ban biên tập",
    allowDownload: false,
    allowPrint: false,
    allowShare: true,
  },
];