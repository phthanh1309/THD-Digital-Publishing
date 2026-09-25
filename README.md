# THD Digital Publishing

**Thư viện Ấn phẩm số — THPT A Trần Hưng Đạo**

Một nền tảng web dành cho việc giới thiệu và quản lý thư viện **tập san, kỷ yếu và các ấn phẩm số** của THPT A Trần Hưng Đạo.

> **Trạng thái:** Prototype / đang phát triển  
> Hiện tại dữ liệu ấn phẩm được khai báo tĩnh bằng TypeScript. Chưa tích hợp PostgreSQL, Prisma hoặc hệ thống quản trị nội dung.

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
| CSS | Styling giao diện |
| ESLint | Kiểm tra chất lượng mã nguồn |
| Next/Image | Tối ưu hình ảnh |
| Next/Link | Điều hướng phía client |

### Lưu ý về database

Tên/mô tả cũ của repository có đề cập **PostgreSQL**, nhưng code hiện tại **chưa sử dụng PostgreSQL** và cũng chưa có Prisma schema.

Dữ liệu mẫu hiện được lưu trực tiếp trong:

`src/data/publications.ts`

Điều này phù hợp cho giai đoạn dựng giao diện/prototype. Khi chuyển sang production, có thể thay lớp dữ liệu này bằng PostgreSQL + ORM/API.

## 📁 Cấu trúc dự án

```text
THD-Digital-Publishing/
├── public/
│   └── logo.webp
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
│   │   ├── publications.ts
│   │   └── site.ts
│   │
│   └── lib/
│       └── utils.ts
│
├── eslint.config.mjs
├── next.config.ts
├── package.json
├── package-lock.json
└── tsconfig.json
```

## 🚀 Bắt đầu

### Yêu cầu

- Node.js 20+
- npm

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

### 3. Chạy môi trường development

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
```

## 📰 Dữ liệu ấn phẩm

Mỗi ấn phẩm hiện được biểu diễn bởi kiểu `Publication`:

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
  allowDownload: boolean;
  allowPrint: boolean;
  allowShare: boolean;
};
```

Các trường này đã được chuẩn bị để có thể phát triển thành hệ thống quản lý ấn phẩm đầy đủ hơn.

Để thêm hoặc chỉnh sửa dữ liệu mẫu, chỉnh sửa:

```text
src/data/publications.ts
```

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

- [ ] Tích hợp PostgreSQL.
- [ ] Tích hợp Prisma hoặc ORM tương đương.
- [ ] Xây dựng API cho ấn phẩm.
- [ ] Hoàn thiện trang thư viện `/publications`.
- [ ] Hoàn thiện chức năng tìm kiếm `/search`.
- [ ] Trang chi tiết cho từng ấn phẩm.
- [ ] Hỗ trợ đọc PDF trực tiếp trên trình duyệt.
- [ ] Quản lý quyền tải xuống, in và chia sẻ.
- [ ] Hệ thống quản trị nội dung cho ban biên tập.
- [ ] Upload và quản lý ảnh bìa/PDF.
- [ ] Phân loại ấn phẩm theo năm, loại và chủ đề.
- [ ] Tối ưu SEO và metadata.
- [ ] Triển khai production.

## 📌 Trạng thái hiện tại

Phiên bản hiện tại tập trung vào **kiến trúc giao diện và dữ liệu mẫu**. Một số liên kết điều hướng đã được chuẩn bị trong UI nhưng các route tương ứng vẫn cần được triển khai đầy đủ.

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

---

**THD Digital Publishing**  
*Thư viện Ấn phẩm số — THPT A Trần Hưng Đạo*