# THD Digital Publishing

**Thư viện Ấn phẩm số — THPT A Trần Hưng Đạo**

Nền tảng web để giới thiệu, đọc và quản lý **tập san, kỷ yếu và các ấn phẩm số** của THPT A Trần Hưng Đạo.

> **Branch đang phát triển:** `whisle`
>
> **Mục đích README này:** tài liệu handoff để Claude/AI khác tiếp tục phát triển project từ đúng trạng thái hiện tại, không làm lại những phần đã hoàn thành.

---

## 🚦 TRẠNG THÁI HIỆN TẠI — 2026-10

### Đã hoàn thành

- [x] PostgreSQL + Prisma
- [x] Public homepage / publication library
- [x] Search, filter, sort, pagination
- [x] Publication detail
- [x] PDF reader / flipbook
- [x] Admin CMS
- [x] Publication CRUD
- [x] Upload cover/PDF
- [x] Publication status: `draft` / `published` / `archived`
- [x] Download / print / share permission theo publication
- [x] Auth.js / NextAuth unified authentication
- [x] Một bảng `User` duy nhất cho USER + ADMIN
- [x] Role-based access: `USER` / `ADMIN`
- [x] Credentials login
- [x] Google OAuth
- [x] Facebook OAuth
- [x] Email verification qua Resend
- [x] User profile: name / bio / avatar
- [x] Like
- [x] Favorite
- [x] Comment
- [x] Reading History
- [x] Zod validation cho các luồng chính
- [x] Server-side ADMIN authorization cho admin dashboard/publication CRUD
- [x] Dọn file cover/PDF cũ khi thay thế/xóa publication

### Chưa làm / đang chờ

- [ ] Kiểm tra `npm run lint` + `npm run build` sau đợt Zod/RBAC mới
- [ ] Rà soát toàn bộ Server Actions/API để validation + authorization đồng nhất
- [ ] Hoàn thiện UI redesign
- [ ] Tailwind CSS
- [ ] shadcn/ui
- [ ] Lucide
- [ ] React Bits — chỉ dùng effect phù hợp, không lạm dụng animation
- [ ] Xác minh domain Resend
- [ ] Facebook App Review
- [ ] Deploy production
- [ ] Redis — **không bắt buộc**, chỉ thêm nếu deploy thực tế chứng minh cần cache/rate-limit/performance

---

# 🧭 ROADMAP ĐÃ CHỐT

## 1. Like / Favorite / Comment / Reading History

**Trạng thái: ✅ XONG**

Không làm lại các tính năng này trừ khi phát hiện bug khi test.

## 2. Zod

**Trạng thái: 🟢 Đã triển khai phần lõi**

Shared validation:

`src/lib/validation.ts`

Schema hiện có:

- `loginSchema`
- `registerSchema`
- `profileSchema`
- `commentSchema`
- `publicationSchema`
- `parsePublicationForm()`
- `firstZodError()`

Đã áp dụng vào:

- Register
- Login
- Profile
- Comment
- Admin Create Publication
- Admin Edit Publication

### Việc còn lại của Zod

Rà soát thêm các input/action/API nếu cần.

> **Server-side validation là bắt buộc. Frontend validation chỉ để UX, không phải security boundary.**

Không tạo schema trùng lặp nếu đã có schema dùng chung.

---

# 🔐 AUTHENTICATION & AUTHORIZATION

Project đã chuyển từ custom admin auth sang:

`Auth.js → User → Role`

- `USER`
- `ADMIN`

**Không tạo lại hệ thống Admin riêng.**

### Auth

File chính:

`src/lib/auth-nextauth.ts`
`src/lib/auth-adapter.ts`
`src/app/api/auth/[...nextauth]/route.ts`
`src/middleware.ts`

Providers:

- Credentials
- Google
- Facebook

Credentials yêu cầu email đã được verify.

### Authorization

Middleware bảo vệ `/admin/*`.

Ngoài middleware còn có:

`src/lib/authorization.ts`

`requireAdmin()` kiểm tra session và **đọc role trực tiếp từ database**.

Admin Server Actions phải tự kiểm tra quyền, không được chỉ dựa vào frontend/middleware.

---

# 🗄️ DATABASE

Stack:

- PostgreSQL
- Prisma 6.x

> **QUAN TRỌNG:** Project cố định dùng **Prisma 6.x**. `prisma` và `@prisma/client` phải cùng major version. Không tự nâng lên Prisma 7/8.

Các model chính:

`User
Account
Session
VerificationToken
Publication
Like
Favorite
Comment
ReadingHistory`

### User

Một bảng User duy nhất:

`Role:
  USER
  ADMIN`

### Publication

Status:

`draft
published
archived`

Draft/archived không được hiển thị công khai.

### Social features

- Like: unique `userId + publicationId`
- Favorite: unique `userId + publicationId`
- ReadingHistory: unique `userId + publicationId`
- Comment: user + publication + content

---

# 📁 STORAGE

File local:

`storage/
├── covers/
├── pdfs/
└── avatars/`

Không đặt file upload trực tiếp trong `public/`.

File được phục vụ qua:

`/api/files/[...path]`

Production sau này có thể chuyển sang object storage nếu cần.

**Không tự ý đưa Redis/object storage vào chỉ vì muốn stack hiện đại.**

---

# 🎨 UI — GIAI ĐOẠN TIẾP THEO

Đây là bước lớn tiếp theo sau khi xác nhận build/lint ổn.

## Stack UI

- Tailwind CSS
- shadcn/ui
- Lucide
- React Bits — chọn lọc

### Nguyên tắc

Không rewrite backend/auth/database chỉ để redesign UI.

Giữ nhận diện THD hiện tại, nhưng làm UI:

- responsive
- hiện đại
- accessible
- consistent
- mobile-friendly
- loading/error state rõ ràng

### shadcn/ui

Ưu tiên cho:

- Button
- Input
- Select
- Dialog
- Dropdown
- Tabs
- Card
- Toast
- Form-related UI
- Admin dashboard

### React Bits

Chỉ dùng cho:

- Hero
- Card hover
- Landing visual
- Một số transition

Không lạm dụng animation trong:

- PDF reader
- Admin CRUD
- Login/Register
- Form
- Accessibility-sensitive areas

---

# 📨 VIỆC HÀNH CHÍNH

Có thể làm xen kẽ, không cần chặn development.

## Resend

Đang dùng Resend để gửi email verification.

Còn lại:

- verify domain thật
- đổi sender khỏi `onboarding@resend.dev`
- kiểm tra email production

## Facebook

Facebook OAuth đã tích hợp.

Còn lại:

- hoàn thiện App Review
- chuyển app khỏi Development khi đủ điều kiện

---

# ⚡ REDIS

**KHÔNG BẮT BUỘC.**

Chỉ thêm Redis nếu sau khi deploy thật phát hiện:

- response chậm
- database query quá nhiều
- cần caching
- cần rate limiting
- cần counter/temporary state

Không thêm Redis trước chỉ để “đủ công nghệ”.

PostgreSQL vẫn là source of truth.

---

# 🚀 DEPLOY

Deploy sau khi:

1. Zod/RBAC ổn
2. UI redesign hoàn thành
3. Resend production config
4. OAuth production config
5. build/lint/test ổn

Mục tiêu:

- domain thật
- HTTPS
- PostgreSQL production
- storage phù hợp production
- environment variables production
- backup database
- monitoring nếu cần

---

# 🧰 TECH STACK

| Công nghệ | Vai trò |
|---|---|
| Next.js 16 | App Router, Server Actions, Route Handlers |
| React 19 | UI |
| TypeScript | Type safety |
| PostgreSQL | Database |
| Prisma 6 | ORM |
| Auth.js / next-auth beta | Authentication |
| bcryptjs | Password hashing |
| Resend | Email verification |
| Zod | Server-side validation |
| Tailwind CSS | UI styling — sắp triển khai |
| shadcn/ui | UI components — sắp triển khai |
| Lucide | Icons — sắp triển khai |
| React Bits | Chọn lọc visual effects |
| 3DFlipBook / pdf.js / three.js | PDF reader |

---

# 📂 CẤU TRÚC QUAN TRỌNG

`src/
├── app/
│   ├── (site)/
│   ├── login/
│   ├── register/
│   ├── verify/
│   ├── profile/
│   ├── admin/
│   │   ├── page.tsx
│   │   └── publications/
│   └── api/
│
├── components/
│
└── lib/
    ├── prisma.ts
    ├── auth-nextauth.ts
    ├── auth-adapter.ts
    ├── authorization.ts
    ├── validation.ts
    └── resend.ts

prisma/
└── schema.prisma

storage/
├── covers/
├── pdfs/
└── avatars/`

---

# 🤖 QUY TẮC CHO AI TIẾP TỤC PROJECT

### 1. Không làm lại tính năng đã xong

Đặc biệt:

- Auth.js
- User/Role
- Like
- Favorite
- Comment
- Reading History

Trước khi sửa, kiểm tra code hiện tại.

### 2. Không tạo hệ thống auth thứ hai

Không quay lại custom admin session.

Kiến trúc chuẩn:

`Auth.js → User → Role`

### 3. Không thêm Redis ngay

Chỉ thêm khi có lý do từ production.

### 4. Không nâng Prisma major tự ý

Giữ Prisma 6.x và đồng bộ:

`prisma`
`@prisma/client`

### 5. Server là security boundary

Mutation quan trọng phải đi theo:

`session
↓
user
↓
role / ownership
↓
validation
↓
database mutation`

### 6. Không rewrite toàn project khi làm UI

UI redesign không được làm mất logic đang hoạt động.

### 7. Sau mỗi nhóm thay đổi

Chạy:

`npm run lint`
`npm run build`

Nếu có lỗi, sửa trước khi tiếp tục feature mới.

---

# 📌 VIỆC CẦN LÀM NGAY

`NOW
 ↓
1. npm run lint
 ↓
2. npm run build
 ↓
3. Fix mọi lỗi TypeScript/runtime
 ↓
4. Rà soát Zod + server authorization
 ↓
5. Tailwind + shadcn + Lucide + React Bits
 ↓
6. Resend domain / Facebook App Review (làm xen kẽ)
 ↓
7. Deploy
 ↓
8. Quan sát production
 ↓
9. Redis nếu thực sự cần`

**Không nhảy thẳng vào Redis.**

---

# 📝 DEVELOPMENT COMMANDS

`npm install
npm run dev

npm run lint
npm run build

npx prisma migrate dev
npx prisma studio

npx tsx scripts/create-admin.ts <username> <password>`

---

## ⚠️ HANDOFF NOTE

README này được cập nhật trực tiếp trên branch **`whisle`** sau khi tiếp quản phần việc còn dang dở.

Nếu Claude tiếp tục từ đây:

> **Coi `whisle` là source of truth hiện tại.**

Không dựa vào README cũ hoặc branch cũ để kết luận trạng thái project.

Trước task mới:

`git status
git branch
git log --oneline -10
npm run lint
npm run build`

Sau đó mới bắt đầu task tiếp theo.
