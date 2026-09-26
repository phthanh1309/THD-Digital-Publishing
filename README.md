# THD Digital Publishing

**Thư viện Ấn phẩm số — THPT A Trần Hưng Đạo**

Một nền tảng web dành cho việc giới thiệu và quản lý thư viện **tập san, kỷ yếu và các ấn phẩm số** của THPT A Trần Hưng Đạo.

> **Trạng thái:** Đang phát triển (Work in progress)
> Đã tích hợp **PostgreSQL + Prisma** cho tầng dữ liệu. **Chưa có** hệ thống quản trị (admin) cho ban biên tập.

## ✨ Tổng quan

THD Digital Publishing được xây dựng với mục tiêu tạo một không gian tập trung để học sinh, giáo viên và độc giả có thể:

- Khám phá các ấn phẩm số của nhà trường.
- Xem thông tin cơ bản của từng ấn phẩm.
- Tìm kiếm ấn phẩm.
- Hiển thị bìa, năm xuất bản, mô tả và số trang.
- Mở rộng về sau thành một hệ thống thư viện ấn phẩm hoàn chỉnh.

Giao diện được thiết kế theo hướng tối giản, ưu tiên khả năng đọc và trải nghiệm trên nhiều kích thước màn hình.

## 🛠️ Công nghệ

| Công nghệ | Vai trò |
| --- | --- |
| [Next.js](https://nextjs.org/) 16 | Framework React cho ứng dụng web |
| [React](https://react.dev/) 19 | Xây dựng giao diện |
| TypeScript | Kiểu dữ liệu và phát triển an toàn hơn |
| **PostgreSQL** | Cơ sở dữ liệu quan hệ, lưu trữ dữ liệu ấn phẩm |
| **Prisma ORM 6** | Định nghĩa schema, migration và truy vấn database |
| CSS | Styling giao diện |
| ESLint | Kiểm tra chất lượng mã nguồn |
| Next/Image | Tối ưu hình ảnh |
| Next/Link | Điều hướng phía client |

### Về database

Dự án hiện đã chuyển từ dữ liệu tĩnh (TypeScript) sang **PostgreSQL**, truy cập qua **Prisma ORM**. Schema được định nghĩa tại `prisma/schema.prisma`, khớp với type `Publication` gốc từng dùng trong `src/data/publications.ts`.

Dữ liệu mẫu ban đầu (2 ấn phẩm: *Tập san 2026*, *Kỷ yếu 2025*) được seed vào database thông qua `prisma/seed.ts`.

> ⚠️ **Lưu ý về Prisma:** dự án dùng Prisma bản ổn định (6.x), **không** dùng Prisma 8 (đang ở giai đoạn release candidate). Nếu `npx prisma init` tạo ra file `prisma.config.ts` thay vì `prisma/schema.prisma` + `.env`, có nghĩa là npm đã cài nhầm bản Prisma 8 RC — cần gỡ và cài lại đúng `prisma@6 @prisma/client@6`.

## 📁 Cấu trúc dự án

```text
THD-Digital-Publishing/
├── public/
│   └── logo.webp
│
├── prisma/
│   ├── schema.prisma
│   └── seed.ts
│
├── src/
│   ├── app/
│   │   ├── globals.css
│   │   ├── layout.tsx
│   │   └── page.tsx
│   │
│   ├── components/
│   │   ├── PublicationCard.tsx
│   │   ├── SiteFooter.tsx
│   │   ├── SiteHeader.tsx
│   │   └── SiteNav.tsx
│   │
│   ├── data/
│   │   └── site.ts
│   │
│   └── lib/
│       ├── prisma.ts
│       └── utils.ts
│
├── .env
├── eslint.config.mjs
├── next.config.ts
├── package.json
├── package-lock.json
└── tsconfig.json
```

> Ghi chú: `src/data/publications.ts` (dữ liệu tĩnh cũ) có thể vẫn còn tồn tại trong quá trình chuyển đổi, nhưng không còn được các trang chính sử dụng nữa — dữ liệu thật giờ nằm trong PostgreSQL.

## 🚀 Bắt đầu

### Yêu cầu

- Node.js 20+
- npm
- PostgreSQL (chạy local hoặc qua Docker)

Kiểm tra phiên bản:

```bash
node -v
npm -v
```

### 1. Clone repository

```bash
git clone https://github.com/phthanh1309/THD-Digital-Publishing.git
cd THD-Digital-Publishing
```

### 2. Cài đặt dependencies

```bash
npm install
```

### 3. Cấu hình database

Tạo file `.env` ở thư mục gốc:

```env
DATABASE_URL="postgresql://user:password@localhost:5432/thd_publishing?schema=public"
```

Nếu chưa có PostgreSQL sẵn, có thể chạy nhanh bằng Docker:

```bash
docker run --name thd-pg -e POSTGRES_PASSWORD=password -e POSTGRES_DB=thd_publishing -p 5432:5432 -d postgres
```

### 4. Chạy migration và seed dữ liệu mẫu

```bash
npx prisma migrate dev
npx prisma db seed
```

Kiểm tra dữ liệu bằng Prisma Studio:

```bash
npx prisma studio
```

### 5. Chạy môi trường development

```bash
npm run dev
```

Sau đó mở:

```text
http://localhost:3000
```

## 📜 Các lệnh

```bash
# Chạy development server
npm run dev

# Build production
npm run build

# Chạy production server
npm run start

# Kiểm tra ESLint
npm run lint

# Chạy migration Prisma
npx prisma migrate dev

# Seed dữ liệu mẫu
npx prisma db seed

# Mở giao diện xem/sửa dữ liệu
npx prisma studio
```

## 📰 Dữ liệu ấn phẩm

Mỗi ấn phẩm được định nghĩa trong `prisma/schema.prisma` (model `Publication`), khớp với type gốc:

```ts
type Publication = {
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
  editor: string;
  language: string;
  publishedAt: string;
  allowDownload: boolean;
  allowPrint: boolean;
  allowShare: boolean;
};
```

Trong Next.js, type này được suy ra tự động từ Prisma Client:

```ts
import type { Publication } from "@prisma/client";
```

Để thêm hoặc chỉnh sửa dữ liệu, dùng một trong các cách sau:

- **Prisma Studio:** `npx prisma studio` — chỉnh trực tiếp qua giao diện.
- **Seed script:** sửa `prisma/seed.ts` rồi chạy lại `npx prisma db seed`.
- *(Sắp tới)* qua trang quản trị (admin) — xem phần "Định hướng phát triển".

Thông tin chung của website nằm tại:

```text
src/data/site.ts
```

## 🧩 Thành phần chính

### `PublicationCard`

Hiển thị một ấn phẩm dưới dạng card, bao gồm:

- Ảnh bìa.
- Năm xuất bản.
- Tiêu đề và phụ đề.
- Mô tả rút gọn.
- Số trang.
- Liên kết xem ấn phẩm.

### `SiteHeader` / `SiteNav`

Cung cấp phần đầu trang và điều hướng chính:

- Trang chủ
- Thư viện
- Tìm kiếm

### `SiteFooter`

Hiển thị thông tin nhà trường và bản quyền.

## 🗺️ Định hướng phát triển

Các hạng mục dự kiến cho những phiên bản tiếp theo:

- [x] Tích hợp PostgreSQL.
- [x] Tích hợp Prisma ORM.
- [ ] **Xây dựng hệ thống quản trị (admin) cho ban biên tập** — đăng nhập, thêm/sửa/xoá ấn phẩm, quản lý trạng thái draft/published/archived.
- [ ] Xây dựng API cho ấn phẩm (REST hoặc Server Actions).
- [ ] Hoàn thiện trang thư viện `/publications`.
- [ ] Hoàn thiện chức năng tìm kiếm `/search`.
- [ ] Trang chi tiết cho từng ấn phẩm.
- [ ] Hỗ trợ đọc PDF trực tiếp trên trình duyệt.
- [ ] Quản lý quyền tải xuống, in và chia sẻ.
- [ ] Upload và quản lý ảnh bìa/PDF (hiện đang dùng đường dẫn tĩnh trong `public/`).
- [ ] Phân loại ấn phẩm theo năm, loại và chủ đề.
- [ ] Tối ưu SEO và metadata.
- [ ] Triển khai production.

## 📌 Trạng thái hiện tại

Tầng dữ liệu đã chuyển từ TypeScript tĩnh sang **PostgreSQL + Prisma**, trang chủ (`/`) đã đọc dữ liệu trực tiếp từ database qua Prisma Client.

**Chưa hoàn thành:**
- Hệ thống quản trị (admin) — hiện việc thêm/sửa dữ liệu chỉ làm được qua Prisma Studio hoặc chỉnh seed script, chưa có giao diện dành cho ban biên tập.
- Các route `/publications`, `/search`, trang chi tiết ấn phẩm vẫn cần được triển khai đầy đủ.

Đây là nền móng ban đầu cho hệ thống **thư viện ấn phẩm số của THPT A Trần Hưng Đạo**, không phải phiên bản production hoàn chỉnh.

## 🤝 Đóng góp

Nếu muốn đóng góp:

1. Fork repository.
2. Tạo branch mới:

```bash
git checkout -b feature/ten-tinh-nang
```

3. Thực hiện thay đổi.
4. Chạy kiểm tra:

```bash
npm run lint
npm run build
```

5. Commit và push branch.
6. Tạo Pull Request.

## 📄 License

Repository hiện chưa khai báo một license riêng. Nếu dự án được phát hành công khai với mục đích cho phép tái sử dụng, nên bổ sung file `LICENSE` và lựa chọn license phù hợp.
