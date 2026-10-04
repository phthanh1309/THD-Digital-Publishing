# THD Digital Publishing

**Thư viện Ấn phẩm số — THPT A Trần Hưng Đạo**

Một nền tảng web để giới thiệu và quản lý thư viện **tập san, kỷ yếu và các ấn phẩm số** của THPT A Trần Hưng Đạo. Người đọc có thể khám phá, tìm kiếm, đọc trực tuyến (flipbook) và tương tác (thích) với ấn phẩm; ban biên tập quản lý toàn bộ nội dung qua khu vực quản trị riêng.

> **Trạng thái:** Đang phát triển tích cực. Các tính năng cốt lõi (đọc, tìm kiếm, quản trị, tài khoản người dùng) đã hoạt động. Đang mở rộng thêm tương tác xã hội (Like/Comment/Favorite/Reading History) và sẽ làm lại giao diện bằng Tailwind CSS ở giai đoạn cuối.

## ✨ Tổng quan

- Khám phá, tìm kiếm ấn phẩm theo tên/tác giả/năm, sắp xếp và phân trang.
- Trang chi tiết: bìa, mô tả, tác giả, biên tập, ngày xuất bản, số trang.
- Đọc trực tuyến bằng flipbook 3D (3DFlipBook + pdf.js), hỗ trợ tải xuống/in theo quyền từng ấn phẩm.
- Tài khoản người dùng: đăng ký bằng email (có xác thực qua mail), hoặc đăng nhập bằng Google/Facebook. Mỗi người có trang hồ sơ (tên, giới thiệu, ảnh đại diện).
- Người dùng đã đăng nhập có thể **thích** ấn phẩm.
- Khu vực quản trị (`/admin`) dành riêng cho tài khoản có vai trò `ADMIN`: thêm/sửa/xóa ấn phẩm, upload ảnh bìa + file PDF thật, quản lý trạng thái draft/published/archived và quyền tải xuống/in/chia sẻ.
- Ấn phẩm ở trạng thái draft/archived **không** hiển thị ở bất kỳ trang công khai nào.

## 🛠️ Công nghệ

| Công nghệ | Vai trò |
| --- | --- |
| [Next.js](https://nextjs.org/) 16 (App Router) | Framework React, Server Actions, Route Handlers |
| [React](https://react.dev/) 19 | Giao diện |
| TypeScript | Kiểu dữ liệu, an toàn khi phát triển |
| **PostgreSQL** | Cơ sở dữ liệu chính |
| **Prisma ORM 6** | Schema, migration, truy vấn database |
| **Auth.js (next-auth@beta)** | Xác thực: Credentials (email+mật khẩu), Google OAuth, Facebook OAuth |
| **Resend** | Gửi email xác thực tài khoản |
| **bcryptjs** | Băm mật khẩu |
| CSS thuần | Styling (kế thừa từ bản thiết kế gốc; sẽ chuyển sang Tailwind CSS ở giai đoạn sau) |
| 3DFlipBook, pdf.js, three.js | Reader đọc PDF dạng lật trang |

> ⚠️ Dự án cố định dùng **Prisma 6.x** (ổn định), không dùng Prisma 7/8 (đang major update). Bỏ qua cảnh báo "Update available" khi chạy lệnh Prisma.

## 📁 Cấu trúc dự án (rút gọn)

```text
thd-publishing/
├── public/
│   ├── logo.webp
│   ├── reader.html              # Trang đọc flipbook (tĩnh, không qua layout Next.js)
│   └── assets/                  # Thư viện flipbook (jQuery, three.js, pdf.js...)
│
├── storage/                     # File thật: covers/, pdfs/, avatars/ — KHÔNG public trực tiếp
│                                 # (phục vụ qua /api/files/..., có kiểm tra quyền)
│
├── prisma/
│   └── schema.prisma             # User, Account, Session, VerificationToken,
│                                  # Publication, Like...
│
├── scripts/
│   └── create-admin.ts           # Tạo/cập nhật tài khoản ADMIN đầu tiên
│
├── src/
│   ├── middleware.ts              # Bảo vệ /admin/* bằng Auth.js (role === ADMIN)
│   ├── lib/
│   │   ├── prisma.ts
│   │   ├── auth-nextauth.ts       # Cấu hình Auth.js (providers, callbacks)
│   │   ├── auth-adapter.ts        # Adapter tùy chỉnh: tự sinh username cho tài khoản OAuth
│   │   └── resend.ts
│   ├── components/
│   │   ├── PublicationCard.tsx
│   │   ├── SiteHeader.tsx / SiteFooter.tsx / SiteNav.tsx
│   │   ├── LikeButton.tsx
│   │   └── ShareButton.tsx
│   └── app/
│       ├── (site)/                # Toàn bộ trang công khai, có SiteHeader/Footer riêng
│       │   ├── layout.tsx
│       │   ├── page.tsx                      # Trang chủ
│       │   ├── publications/page.tsx         # Thư viện (lọc/sắp xếp/phân trang)
│       │   ├── publications/[slug]/page.tsx  # Chi tiết ấn phẩm + Like + reader
│       │   └── search/page.tsx
│       ├── login/page.tsx          # Đăng nhập chung (user & admin), Google/Facebook
│       ├── register/page.tsx       # Đăng ký email + mật khẩu
│       ├── verify/page.tsx         # Xác thực email qua link gửi bằng Resend
│       ├── profile/page.tsx        # Hồ sơ cá nhân (tên, bio, avatar)
│       ├── admin/                  # Khu vực quản trị, bảo vệ bởi middleware
│       │   ├── layout.tsx          # CSS + layout riêng cho admin, không có header công khai
│       │   ├── page.tsx            # Dashboard: thống kê + danh sách ấn phẩm
│       │   └── publications/
│       │       ├── actions.ts      # Server Actions: tạo/sửa/xóa, upload file
│       │       ├── new/page.tsx
│       │       └── [id]/edit/page.tsx
│       └── api/
│           ├── auth/[...nextauth]/route.ts
│           └── files/[...path]/route.ts   # Phục vụ file từ storage/, có kiểm tra quyền
```

## 🚀 Bắt đầu

### Yêu cầu

- Node.js 20+, npm
- PostgreSQL (cài local hoặc Docker)
- Tài khoản Resend (gửi email xác thực)
- Google Cloud OAuth Client + Meta for Developers App (đăng nhập Google/Facebook)

### 1. Cài đặt

```bash
git clone https://github.com/phthanh1309/THD-Digital-Publishing.git
cd THD-Digital-Publishing
npm install
```

### 2. Cấu hình môi trường

Tạo **cả hai** file `.env` và `.env.local` ở thư mục gốc (Prisma CLI đọc `.env`, Next.js đọc `.env.local` — nên để trùng giá trị ở cả hai cho chắc):

```env
DATABASE_URL="postgresql://postgres:MAT_KHAU@localhost:5432/thd_publishing"
AUTH_SECRET="chuoi-ngau-nhien-dai"
AUTH_GOOGLE_ID="..."
AUTH_GOOGLE_SECRET="..."
AUTH_FACEBOOK_ID="..."
AUTH_FACEBOOK_SECRET="..."
RESEND_API_KEY="re_..."
```

> Lưu ý Google/Facebook OAuth cần khai báo đúng **Redirect URI**:
> `http://localhost:3000/api/auth/callback/google` và `.../facebook`.
>
> Resend ở gói miễn phí chỉ gửi được email tới đúng địa chỉ dùng đăng ký tài khoản Resend — cần xác minh domain riêng để gửi cho người dùng thật.

### 3. Database

```bash
npx prisma migrate dev
npx tsx scripts/create-admin.ts <username> <mat_khau>
```

Lệnh thứ hai tạo tài khoản quản trị đầu tiên (role `ADMIN`, đã xác thực sẵn, không cần qua email).

### 4. Storage cho file upload

```bash
mkdir -p storage/covers storage/pdfs storage/avatars
```

Thư mục `storage/` nằm **ngoài** `public/`, không được truy cập trực tiếp — mọi file (ảnh bìa, PDF, avatar) được phục vụ qua `/api/files/...`, route này kiểm tra quyền (ấn phẩm phải `published`, phải `allowDownload` mới tải được PDF) trước khi trả file.

### 5. Chạy

```bash
npm run dev
```

Mở `http://localhost:3000`. Đăng nhập quản trị tại `/login` bằng tài khoản vừa tạo, sau đó vào `/admin`.

## 📜 Các lệnh thường dùng

```bash
npm run dev            # Development server
npm run build           # Build production
npm run lint             # ESLint

npx prisma migrate dev   # Tạo/áp dụng migration
npx prisma studio        # Xem/sửa dữ liệu trực quan
npx tsx scripts/create-admin.ts <user> <pass>   # Tạo/cập nhật tài khoản admin
```

## 🔐 Xác thực & phân quyền

- Một bảng `User` duy nhất cho mọi tài khoản — admin **không phải** hệ thống riêng, chỉ là `User` có `role = ADMIN`.
- 3 cách đăng nhập: email/mật khẩu (`Credentials`, bắt buộc xác thực email trước khi đăng nhập được), Google, Facebook.
- Tài khoản tạo qua Google/Facebook được tự sinh `username` duy nhất (vì schema bắt buộc có, nhưng Auth.js không cung cấp sẵn).
- `middleware.ts` chặn toàn bộ `/admin/*` nếu session không có `role === ADMIN`, kiểm tra phía server (không chỉ ẩn nút ở giao diện).
- Đăng ký ở `/register`, xác thực qua link gửi bằng Resend (`/verify`), hết hạn sau 24 giờ.

## 📰 Dữ liệu ấn phẩm

Model `Publication` (xem đầy đủ tại `prisma/schema.prisma`) gồm: `title`, `subtitle`, `year`, `description`, `cover`, `pdf` (đường dẫn tương đối trong `storage/`), `pageCount`, `status` (`draft`/`published`/`archived`), `author`, `editor`, `language`, `publishedAt`, `allowDownload`, `allowPrint`, `allowShare`.

Quản lý dữ liệu qua:
- **Trang quản trị** (`/admin`) — cách chính thức, có validate + upload file.
- **Prisma Studio** (`npx prisma studio`) — chỉ dùng khi dev, không dùng cho production.

## 🗺️ Định hướng phát triển

- [x] PostgreSQL + Prisma
- [x] Thư viện, tìm kiếm, chi tiết ấn phẩm, reader PDF
- [x] Hệ thống quản trị (đăng nhập, CRUD ấn phẩm, upload file thật)
- [x] Kiểm tra quyền phía server (draft ẩn, tải xuống theo `allowDownload`)
- [x] Tài khoản người dùng: đăng ký/xác thực email, Google/Facebook OAuth, hồ sơ cá nhân
- [x] Thích (Like) ấn phẩm
- [ ] Yêu thích (Favorite), Bình luận (Comment), Lịch sử đọc (Reading History)
- [ ] Xác minh domain Resend để gửi email cho người dùng thật (không chỉ email test)
- [ ] Đưa app Facebook ra khỏi chế độ Development (App Review)
- [ ] Thiết kế lại giao diện bằng Tailwind CSS + shadcn/ui (dự kiến làm sau khi xong các tính năng còn lại)
- [ ] Triển khai production (VPS + domain io.vn)

## 📌 Trạng thái hiện tại

Phần lõi của sản phẩm — đọc, tìm kiếm, quản trị nội dung, tài khoản người dùng — đã hoạt động đầy đủ và được kiểm thử thủ công. Phần còn thiếu chủ yếu là tính năng tương tác xã hội mở rộng, polish giao diện, và triển khai lên server thật.

## 🤝 Đóng góp

1. Fork repository, tạo nhánh mới: `git checkout -b feature/ten-tinh-nang`
2. Thực hiện thay đổi, chạy `npm run lint && npm run build`
3. Commit, push, tạo Pull Request

## 📄 License

Repository hiện chưa khai báo license riêng.