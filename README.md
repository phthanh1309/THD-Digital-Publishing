# THD Digital Publishing

**Thư viện Ấn phẩm số — THPT A Trần Hưng Đạo**

Nền tảng web để giới thiệu, đọc và quản lý **tập san, kỷ yếu và các ấn phẩm số** của nhà trường.

> **Branch nguồn:** `whisle` — README này là tài liệu handoff, phản ánh đúng code tại thời điểm cập nhật (2026-10-31). Không dựa vào README/branch cũ để kết luận trạng thái.

---

## 1. Trạng thái

### Đã hoàn thành

| Nhóm | Nội dung |
|---|---|
| Nền tảng | Next.js 16 App Router, React 19, TypeScript, PostgreSQL + Prisma 6 |
| Công khai | Trang chủ, thư viện ấn phẩm, trang chi tiết, tìm kiếm / lọc / sắp xếp / phân trang, trình đọc PDF + flipbook |
| Nội dung | Admin CMS, CRUD ấn phẩm, upload cover/PDF, trạng thái `draft` / `published` / `archived`, quyền tải/in/chia sẻ theo từng ấn phẩm, dọn file cũ khi thay thế hoặc xóa |
| Auth | Auth.js (next-auth v5 beta): Credentials + Google + Facebook, xác minh email qua Resend, một bảng `User` duy nhất, role `USER` / `ADMIN` |
| Tương tác | Like, Favorite, Comment, Reading History, hồ sơ người dùng (tên / bio / avatar) |
| Chất lượng | Zod validation cho các luồng chính, phân quyền ADMIN phía server, `lint` + `build` pass |
| Hạ tầng request | `src/proxy.ts` (Next 16 Proxy, thay cho `middleware.ts`) chặn `/admin/*` |

**Đã verify (2026-10-31):**

- `npm run lint` → PASS, không lỗi/warning
- `npm run build` → PASS, 15 route, TypeScript OK, **không còn cảnh báo deprecated `middleware`**
- Smoke test production: `GET /admin` → `307 → /login`

### Chưa làm / đang chờ

- [ ] Rà soát toàn bộ Server Actions/API để validation + authorization đồng nhất
- [ ] Hoàn thiện UI redesign (site + admin)
- [ ] Resend: xác minh domain thật, đổi sender khỏi `onboarding@resend.dev`
- [ ] Facebook App Review, chuyển app khỏi Development
- [ ] Deploy production
- [ ] Redis — **không bắt buộc**, chỉ thêm nếu production chứng minh cần cache/rate-limit

---

## 2. Tech stack

| Công nghệ | Version | Vai trò |
|---|---|---|
| Next.js | 16.3.x | App Router, Server Actions, Route Handlers, Proxy |
| React | 19.2.x | UI |
| TypeScript | 5.x | Type safety |
| PostgreSQL | — | Database, source of truth |
| Prisma | 6.19.x | ORM — **pin major 6** |
| Auth.js (next-auth) | 5 beta | Authentication, session JWT |
| bcryptjs | 3.x | Hash mật khẩu |
| Zod | 4.x | Validation dùng chung server/client |
| Resend | 6.x | Email xác minh |
| Tailwind CSS | 4.x | Đã cấu hình, dùng song song CSS thuần |
| shadcn/ui | 4.x CLI | `components.json` + `src/components/ui` |
| lucide-react, @hugeicons, @base-ui | — | Icon và primitive UI |

---

## 3. Cấu trúc

```text
src/
├── proxy.ts                  # Next 16 Proxy — chặn /admin/*
├── app/
│   ├── (site)/               # Route group công khai: layout, trang chủ, publications,
│   │                         # publications/[slug], search, favorites, history, profile
│   │   ├── globals.css       # Design tokens + CSS thuần của site
│   │   └── layout.tsx        # Root layout (html/body, header, footer)
│   ├── admin/                # Dashboard + publications/new + publications/[id]/edit
│   │   ├── admin.css         # CSS thuần của admin
│   │   └── layout.tsx
│   ├── login/ + login/success/
│   ├── register/ + verify/
│   ├── api/
│   │   ├── auth/[...nextauth]/route.ts
│   │   └── files/[...path]/route.ts   # phục vụ file trong storage/
│   └── tailwind.css          # @import "tailwindcss" + tw-animate-css + shadcn
├── components/               # SiteHeader, SiteFooter, PublicationCard,
│                             # Like/Favorite/Comment/Share, ui/
├── data/                     # site.ts, publications.ts (nội dung tĩnh)
└── lib/                      # prisma, auth-nextauth, auth-adapter,
                              # authorization, validation, resend, utils

prisma/schema.prisma
scripts/create-admin.ts
storage/{covers,pdfs,avatars}
```

---

## 4. Authentication & Authorization

Kiến trúc chuẩn, **không tạo hệ thống auth thứ hai**:

```text
Auth.js → User → Role (USER | ADMIN)
```

- Session strategy: **JWT** (không query DB mỗi request).
- Credentials yêu cầu email đã verify.
- `src/proxy.ts`: chặn `/admin/*`, redirect người không phải ADMIN về `/login`; `/admin/login` được bỏ qua. Proxy chạy **Node.js runtime** (mặc định của Next 16 — không khai báo `runtime` trong file proxy).
- `src/lib/authorization.ts` → `requireAdmin()` kiểm tra session và **đọc role trực tiếp từ database**.

**Server là security boundary.** Mọi mutation quan trọng phải đi theo thứ tự:

```text
session → user → role / ownership → validation → database mutation
```

Không được chỉ dựa vào frontend hay proxy để phân quyền.

---

## 5. Database

PostgreSQL + Prisma 6.x. `prisma` và `@prisma/client` phải cùng major — **không tự nâng lên Prisma 7/8**.

**Model:** `User`, `Account`, `Session`, `VerificationToken`, `Publication`, `Like`, `Favorite`, `Comment`, `ReadingHistory`.

- **User**: một bảng duy nhất cho USER + ADMIN, role `USER` / `ADMIN`.
- **Publication**: status `draft` / `published` / `archived`; draft và archived không hiển thị công khai.
- **Ràng buộc duy nhất**: Like / Favorite / ReadingHistory theo `userId + publicationId`; Comment gắn user + publication + nội dung.

---

## 6. Storage

```text
storage/
├── covers/
├── pdfs/
└── avatars/
```

- Không đặt file upload trong `public/`.
- File được phục vụ qua `/api/files/[...path]`.
- Khi thay thế/xóa ấn phẩm, file cover/PDF cũ được dọn tương ứng.
- Production có thể chuyển sang object storage nếu thực sự cần — không thêm trước.

---

## 7. UI

Hiện tại site và admin dùng **CSS thuần** với design tokens (`globals.css`, `admin.css`), Tailwind v4 + shadcn/ui đã được nối sẵn (`src/app/tailwind.css`, `components/ui`) để dùng dần.

Nguyên tắc khi redesign:

- Không rewrite backend/auth/database chỉ để làm UI.
- Giữ nhận diện THD; ưu tiên responsive, accessible, nhất quán, mobile-friendly.
- Loading / empty / error state phải rõ ràng.
- shadcn/ui ưu tiên cho form, dialog, dropdown, tabs, card, toast và admin dashboard.
- Animation tiết chế; tránh trong PDF reader, admin CRUD, login/register và các khu vực nhạy cảm accessibility.

---

## 8. Quy ước bắt buộc

1. **Không làm lại tính năng đã xong** (auth, role, like, favorite, comment, reading history). Kiểm tra code hiện tại trước khi sửa.
2. **Không tạo hệ thống auth thứ hai**, không quay lại custom admin session.
3. **Không thêm Redis** nếu chưa có lý do từ production.
4. **Không nâng Prisma major** tự ý.
5. **Server-side validation là bắt buộc**; frontend validation chỉ để UX. Không tạo schema trùng lặp — dùng `src/lib/validation.ts`.
6. **Không rewrite toàn project khi làm UI**; không làm mất logic đang hoạt động.
7. **Sau mỗi nhóm thay đổi**: chạy `npm run lint` và `npm run build`, sửa lỗi trước khi sang feature mới.

---

## 9. Việc tiếp theo (theo thứ tự)

```text
1. Rà soát Zod + authorization ở toàn bộ Server Actions/API
2. Hoàn thiện UI redesign (site trước, admin sau)
3. Resend domain + Facebook App Review (làm xen kẽ)
4. Deploy production
5. Quan sát production → chỉ thêm Redis nếu thực sự cần
```

---

## 10. Lệnh thường dùng

```bash
npm install
npm run dev
npm run lint
npm run build

npx prisma migrate dev
npx prisma studio

# Tạo/cập nhật tài khoản ADMIN (username + password)
npx tsx scripts/create-admin.ts <username> <password>
```

---

## 11. Deploy checklist

- [ ] `DATABASE_URL` trỏ PostgreSQL production
- [ ] `AUTH_SECRET` mới, đủ mạnh
- [ ] `AUTH_URL` (hoặc `AUTH_TRUST_HOST=true`) — bắt buộc, nếu thiếu Auth.js báo `UntrustedHost`
- [ ] OAuth production: Google, Facebook (callback URL đúng domain)
- [ ] Resend: domain đã verify, sender riêng
- [ ] HTTPS + domain thật
- [ ] Storage phù hợp môi trường production
- [ ] Backup database
- [ ] Monitoring nếu cần
- [ ] `npm run lint` + `npm run build` pass trên CI/local trước khi release

---

## 12. Handoff

Trước khi bắt đầu task mới:

```bash
git status
git branch
git log --oneline -10
npm run lint
npm run build
```

Coi `whisle` là source of truth hiện tại. Nếu phát hiện README lệch với code, **code thắng** — cập nhật lại README trong cùng commit với thay đổi.
