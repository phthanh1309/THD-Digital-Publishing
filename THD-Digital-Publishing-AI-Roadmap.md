# THD Digital Publishing --- AI Development Roadmap & Technical Specification

> Tài liệu này là context kỹ thuật dành cho AI/developer khi tiếp tục
> phát triển dự án THD Digital Publishing. Mục tiêu: biến website tập
> san điện tử thành một nền tảng đọc tập san hiện đại, có tài khoản
> người dùng, tương tác cộng đồng, CMS quản trị, hiệu năng tốt và kiến
> trúc dễ mở rộng.

------------------------------------------------------------------------

# 1. Tổng quan dự án

## 1.1. Mục tiêu

THD Digital Publishing là nền tảng tập san điện tử cho THPT A Trần Hưng
Đạo.

Hệ thống có ba nhóm người dùng chính:

1.  Guest --- người chưa đăng nhập.
2.  User --- người dùng có tài khoản.
3.  Admin --- quản trị viên.

Hệ thống cần phục vụ hai mục tiêu:

-   Public publishing: đọc, tìm kiếm và khám phá các tập san.
-   Community: người dùng có thể tạo profile, like, comment, lưu nội
    dung và theo dõi lịch sử đọc.
-   Administration: admin quản lý publication, file, user và nội dung
    tương tác.

------------------------------------------------------------------------

# 2. Nguyên tắc phát triển

AI/developer khi sửa code phải tuân thủ các nguyên tắc:

## 2.1. Không over-engineering

Không thêm công nghệ chỉ vì nó phổ biến.

Không tự ý đưa vào:

-   Redux nếu chưa có nhu cầu thực tế.
-   GraphQL nếu REST/Route Handler/Server Actions đã đủ.
-   Express nếu Next.js backend hiện tại đáp ứng được.
-   Microservices.
-   Kafka/RabbitMQ.
-   Kubernetes.
-   Elasticsearch.
-   MongoDB thay cho PostgreSQL.
-   Một state-management library lớn chỉ để xử lý vài state nhỏ.

Ưu tiên:

Next.js + TypeScript + PostgreSQL + Prisma + Auth.js + Zod.

Redis chỉ được dùng khi có workload phù hợp.

------------------------------------------------------------------------

# 3. Stack mục tiêu

## Core

-   Next.js
-   React
-   TypeScript
-   PostgreSQL
-   Prisma

## Authentication

-   Auth.js

## Validation

-   Zod

## UI

-   Tailwind CSS
-   shadcn/ui
-   Lucide

## Animation / Interactive UI

-   React Bits
-   Repository tham khảo: https://github.com/DavidHDev/react-bits

Chỉ dùng những component/animation thực sự phù hợp.

## Performance / Infrastructure

-   Redis
-   Docker
-   GitHub Actions
-   Testing framework phù hợp như Vitest + Playwright

## File / Publication

Hiện tại local filesystem có thể tiếp tục dùng trong development.

Production nên hướng tới object storage.

------------------------------------------------------------------------

# 4. Kiến trúc tổng thể

``` text
                         THD Digital Publishing
                                  |
              +-------------------+-------------------+
              |                   |                   |
           Public                User               Admin
           Website              Area                CMS
              |                   |                   |
              +-------------------+-------------------+
                                  |
                              Next.js
                                  |
              +-------------------+-------------------+
              |                   |                   |
           Auth.js              Zod              Services
              |                   |                   |
              +-------------------+-------------------+
                                  |
                         Prisma / PostgreSQL
                                  |
                    +-------------+-------------+
                    |             |             |
                  User       Publication      Interaction
                    |             |             |
                  Like         Comment        History
                    |
                 Redis
                    |
          Cache / Rate Limit / Temporary Data
```

------------------------------------------------------------------------

# 5. User system

## 5.1. Guest

Guest có thể:

-   Xem homepage.
-   Xem publication đã publish.
-   Tìm kiếm.
-   Lọc publication.
-   Đọc PDF.
-   Xem thông tin publication.
-   Xem comment.
-   Xem số like.

Guest không thể:

-   Like.
-   Comment.
-   Lưu yêu thích.
-   Quản lý profile.

Khi thực hiện hành động yêu cầu tài khoản, hệ thống nên chuyển tới login
hoặc yêu cầu đăng nhập.

------------------------------------------------------------------------

# 6. User account

## 6.1. User model

Thiết kế dự kiến:

``` text
User
- id
- name
- username
- email
- passwordHash
- image
- bio
- role
- createdAt
- updatedAt
```

Nếu Auth.js Adapter yêu cầu các model chuẩn của Auth.js thì phải thiết
kế Prisma schema tương thích với Auth.js.

Không tự tạo một hệ thống session song song nếu Auth.js đã quản lý
session.

------------------------------------------------------------------------

# 7. Role system

Role tối thiểu:

``` text
USER
ADMIN
```

Có thể mở rộng sau:

``` text
USER
EDITOR
ADMIN
```

Nhưng không cần thêm EDITOR nếu hiện tại chưa có nhu cầu.

## Quyền USER

-   Đọc publication.
-   Like.
-   Unlike.
-   Comment.
-   Xóa comment của chính mình.
-   Sửa profile.
-   Xem lịch sử đọc.
-   Quản lý favorites.

## Quyền ADMIN

-   Tất cả quyền USER.
-   Quản lý publication.
-   Upload cover.
-   Upload PDF.
-   Publish/unpublish.
-   Edit publication.
-   Delete publication.
-   Quản lý user.
-   Quản lý comment nếu cần moderation.
-   Xem dashboard/statistics.

------------------------------------------------------------------------

# 8. Auth.js

## 8.1. Quyết định kiến trúc

Dự án hiện có custom login/session.

Khi chuyển sang Auth.js:

-   Chuyển sang Auth.js hoàn toàn.
-   Không duy trì custom session song song.
-   Xóa hoặc refactor logic session cũ sau khi migration thành công.
-   Các route admin phải kiểm tra authorization.
-   Không chỉ kiểm tra frontend.

## 8.2. Authentication flow

``` text
User
  |
  v
Login
  |
  v
Auth.js
  |
  +---- invalid ----> Error
  |
  +---- valid ------> Session
                         |
                         v
                      User ID
                         |
                         v
                    Authorization
```

Authentication = xác định người dùng là ai.

Authorization = xác định người dùng được làm gì.

Hai phần này phải được tách biệt.

------------------------------------------------------------------------

# 9. Admin security

Đây là ưu tiên cao.

Mọi route/action/API có quyền admin phải kiểm tra role ở server.

Không được dựa vào:

``` text
ẩn button
```

hoặc:

``` text
client-side condition
```

để bảo vệ API.

Phải kiểm tra:

``` text
session
  ↓
user
  ↓
role === ADMIN
```

Các route `/admin` và các API mutation tương ứng phải được bảo vệ.

------------------------------------------------------------------------

# 10. Profile page

User cần có trang:

``` text
/profile
```

Có thể mở rộng:

``` text
/profile
/profile/settings
/profile/history
/profile/favorites
```

## Profile hiển thị

-   Avatar.
-   Username.
-   Display name.
-   Bio.
-   Ngày tham gia.
-   Số publication đã like.
-   Số comment.
-   Lịch sử đọc nếu user chọn xem.

Không nên hiển thị thông tin nhạy cảm.

------------------------------------------------------------------------

# 11. Like system

User có thể like publication.

Database nên dùng quan hệ unique:

``` text
UserLike
- userId
- publicationId
- createdAt
```

Constraint:

``` text
unique(userId, publicationId)
```

Điều này ngăn một user like cùng một publication nhiều lần.

Flow:

``` text
User click Like
      |
      v
Server Action / Route Handler
      |
      v
Validate session
      |
      v
Validate publication
      |
      v
Create/Delete UserLike
      |
      v
Return new state
```

Không tin giá trị like count từ client.

------------------------------------------------------------------------

# 12. Comment system

Comment thuộc về:

``` text
User
Publication
```

Model dự kiến:

``` text
Comment
- id
- content
- userId
- publicationId
- createdAt
- updatedAt
- deletedAt
```

## Quy tắc

-   User phải đăng nhập.
-   Comment phải qua Zod validation.
-   Giới hạn độ dài.
-   Không cho content rỗng.
-   Server phải xác nhận user.
-   Server phải xác nhận publication tồn tại.
-   User chỉ được sửa/xóa comment của mình.
-   Admin có thể moderate comment.

## Soft delete

Ưu tiên:

``` text
deletedAt != null
```

thay vì xóa vật lý ngay.

Điều này giúp:

-   Audit.
-   Moderation.
-   Debug.
-   Tránh mất dữ liệu.

------------------------------------------------------------------------

# 13. Comment moderation

Admin cần có khả năng:

-   Xem comment.
-   Xóa/ẩn comment vi phạm.
-   Xem publication chứa comment.
-   Xem user tạo comment.

Không nên xây hệ thống moderation quá phức tạp ở phiên bản đầu.

------------------------------------------------------------------------

# 14. Reading history

Nên triển khai sau Like/Comment.

Model:

``` text
ReadingHistory
- id
- userId
- publicationId
- progress
- lastReadAt
```

`progress` có thể là:

``` text
0 → 100
```

hoặc lưu page hiện tại:

``` text
currentPage
totalPages
```

Nếu PDF reader đã hỗ trợ page tracking thì có thể lưu:

``` text
currentPage
```

Thay vì lưu cả hai nếu không cần.

------------------------------------------------------------------------

# 15. Favorites

Favorites có thể dùng chung logic với Like hoặc tách riêng.

Khuyến nghị tách:

``` text
Like
```

là hành động tương tác công khai.

``` text
Favorite
```

là hành động cá nhân.

Ví dụ:

``` text
UserLike
UserFavorite
```

Không nên dùng Like làm Favorite.

------------------------------------------------------------------------

# 16. Zod

Zod được sử dụng để validate dữ liệu từ:

-   Form.
-   Server Action.
-   Route Handler.
-   API request.
-   Search parameters.
-   User profile.
-   Comment.
-   Publication.
-   Authentication input.

Ví dụ:

``` ts
const commentSchema = z.object({
  content: z
    .string()
    .trim()
    .min(1)
    .max(1000),
});
```

Không được chỉ validate ở client.

Client validation chỉ giúp UX.

Server validation mới là lớp bảo vệ thực sự.

------------------------------------------------------------------------

# 17. Service layer

Không để toàn bộ business logic trong:

``` text
page.tsx
actions.ts
route.ts
```

Nên tổ chức:

``` text
src/
├── app/
├── components/
├── lib/
│   ├── auth/
│   ├── db/
│   ├── redis/
│   ├── storage/
│   └── validation/
│
├── services/
│   ├── auth.service.ts
│   ├── user.service.ts
│   ├── publication.service.ts
│   ├── comment.service.ts
│   ├── like.service.ts
│   └── history.service.ts
│
└── types/
```

Ví dụ:

``` text
comment.service.ts
    |
    +-- createComment()
    +-- updateComment()
    +-- deleteComment()
    +-- listComments()
```

Server Action chỉ gọi service.

------------------------------------------------------------------------

# 18. Tailwind CSS

Tailwind dùng làm styling system chính.

Mục tiêu:

-   Consistent spacing.
-   Responsive design.
-   Dark mode nếu cần.
-   Reusable utility classes.
-   Giảm CSS global không cần thiết.

Không cần rewrite toàn bộ project ngay.

Migration từng khu vực:

``` text
Admin
  ↓
Auth pages
  ↓
Common components
  ↓
Public pages
```

Giữ CSS hiện tại nếu việc rewrite không đem lại lợi ích rõ ràng.

------------------------------------------------------------------------

# 19. shadcn/ui

shadcn/ui dùng cho các UI component có tính hệ thống.

Ưu tiên:

-   Button.
-   Input.
-   Textarea.
-   Dialog.
-   Alert Dialog.
-   Dropdown Menu.
-   Sheet.
-   Tabs.
-   Table.
-   Pagination.
-   Toast/Sonner.
-   Form.
-   Select.
-   Skeleton.

Đặc biệt phù hợp với Admin CMS.

Không biến toàn bộ public website thành giao diện mặc định của shadcn.

Public website cần giữ branding riêng của THD.

------------------------------------------------------------------------

# 20. Lucide

Lucide dùng làm icon system chính.

Ví dụ:

``` text
Search
Heart
MessageCircle
User
BookOpen
Download
Share2
Settings
Trash2
Edit
Upload
```

Không trộn quá nhiều icon library.

Mục tiêu là UI nhất quán.

------------------------------------------------------------------------

# 21. React Bits

React Bits được dùng cho animation/interactive components.

Repository:

https://github.com/DavidHDev/react-bits

Nguyên tắc:

-   Chỉ dùng khi animation có mục đích.
-   Không animation mọi component.
-   Không làm reader nặng.
-   Không làm admin chậm.
-   Kiểm tra mobile.
-   Kiểm tra accessibility.
-   Kiểm tra reduced-motion.

Các khu vực phù hợp:

``` text
Hero
Featured publication
Publication cards
Landing sections
Empty states
Page transitions
Interactive decorative effects
```

Không ưu tiên animation ở:

``` text
PDF reader
Admin data tables
Forms
Login
Heavy data pages
```

------------------------------------------------------------------------

# 22. Responsive UI

Phải ưu tiên:

``` text
Mobile
Tablet
Desktop
```

Đặc biệt publication reader phải sử dụng tốt trên mobile.

Các component mới phải kiểm tra:

-   Touch target.
-   Font size.
-   Overflow.
-   Horizontal scrolling.
-   Modal trên mobile.
-   Keyboard navigation.

------------------------------------------------------------------------

# 23. Accessibility

Mọi UI mới cần chú ý:

-   Semantic HTML.
-   `button` thay vì clickable `div`.
-   `label` cho input.
-   `aria-label` khi icon button không có text.
-   Focus state.
-   Keyboard navigation.
-   Color contrast.
-   Reduced motion.

Animation không được là điều kiện duy nhất để hiểu nội dung.

------------------------------------------------------------------------

# 24. Frontend performance

Next.js App Router tiếp tục được sử dụng.

## Server Components

Mặc định dùng Server Component.

Chỉ dùng:

``` text
"use client"
```

khi thực sự cần:

-   useState.
-   useEffect.
-   Browser API.
-   Interactive UI.
-   Client-only library.

Không biến toàn bộ page thành Client Component.

------------------------------------------------------------------------

# 25. Image optimization

Dùng:

``` text
next/image
```

cho cover/avatar/image.

Có:

-   width/height hoặc fill phù hợp.
-   lazy loading khi phù hợp.
-   responsive sizes.
-   tránh ảnh quá lớn.

------------------------------------------------------------------------

# 26. Font

Dùng:

``` text
next/font
```

thay vì import font trực tiếp từ CSS nếu có thể.

Mục tiêu:

-   Giảm layout shift.
-   Tối ưu loading.
-   Kiểm soát typography.

Font phải phù hợp với tiếng Việt.

------------------------------------------------------------------------

# 27. SEO

Public pages cần:

-   Metadata.
-   Title.
-   Description.
-   Open Graph.
-   Twitter/X metadata nếu cần.
-   Sitemap.
-   Robots.
-   Canonical URL nếu cần.
-   Structured data nếu phù hợp.

Publication detail page nên có metadata riêng theo publication.

------------------------------------------------------------------------

# 28. Loading / Error states

Mỗi khu vực quan trọng nên có:

``` text
loading.tsx
error.tsx
not-found.tsx
```

hoặc component tương đương.

Dùng Skeleton từ shadcn/ui khi phù hợp.

Không để trang trắng trong lúc loading.

------------------------------------------------------------------------

# 29. Redis

Redis KHÔNG thay PostgreSQL.

PostgreSQL:

``` text
Source of Truth
```

Redis:

``` text
Cache / Temporary State / Rate Limit
```

## Các use case phù hợp

### Cache

Ví dụ:

``` text
homepage latest publications
publication lists
popular publications
statistics
```

Flow:

``` text
Request
  |
  v
Redis
  |
  +-- HIT --> return
  |
  +-- MISS
        |
        v
    PostgreSQL
        |
        v
      Redis
        |
        v
      return
```

------------------------------------------------------------------------

# 30. Redis rate limiting

Redis rất phù hợp để chống spam:

``` text
Login
Comment
Like
Search
Admin APIs
```

Ví dụ:

``` text
IP/user
   |
   v
Redis counter
   |
   +-- under limit --> allow
   |
   +-- over limit --> reject
```

Không nên tự xây rate limiter bằng biến memory của Node.js vì không ổn
định khi deploy nhiều instance.

------------------------------------------------------------------------

# 31. Redis session

Không cần chuyển session sang Redis chỉ vì đã cài Redis.

Auth.js phải là nguồn quyết định về session architecture.

Chỉ sử dụng Redis cho session nếu kiến trúc deployment và Auth.js
strategy thực sự cần.

------------------------------------------------------------------------

# 32. Cache invalidation

Khi publication thay đổi:

``` text
Create publication
Edit publication
Publish
Unpublish
Delete
```

phải xem xét invalidate:

``` text
homepage cache
publication list cache
publication detail cache
search cache
popular cache
```

Không để Redis trả dữ liệu cũ vô thời hạn.

------------------------------------------------------------------------

# 33. PostgreSQL + Prisma

PostgreSQL tiếp tục là database chính.

Prisma là ORM.

Các relation dự kiến:

``` text
User
 |
 +---- UserLike ---- Publication
 |
 +---- UserFavorite - Publication
 |
 +---- Comment ----- Publication
 |
 +---- ReadingHistory - Publication
```

Cần index cho các truy vấn thường xuyên.

Ví dụ:

``` text
Publication.slug
Publication.status
Publication.createdAt

Comment.publicationId
Comment.userId

UserLike.publicationId
UserLike.userId

ReadingHistory.userId
ReadingHistory.publicationId
```

Composite unique/index nên được dùng khi cần.

------------------------------------------------------------------------

# 34. Publication

Publication hiện là core entity.

Status:

``` text
draft
published
archived
```

Public website chỉ nên hiển thị:

``` text
published
```

Admin có quyền xem toàn bộ.

------------------------------------------------------------------------

# 35. File storage

Hiện tại:

``` text
storage/
├── covers/
└── pdfs/
```

có thể tiếp tục sử dụng cho development.

API file phải:

-   Validate path.
-   Không cho path traversal.
-   Kiểm tra publication.
-   Kiểm tra status.
-   Kiểm tra permission download.
-   Set MIME type chính xác.
-   Không expose arbitrary filesystem path.

Production lâu dài:

``` text
Object Storage
```

Ví dụ có thể chuyển sang:

-   S3-compatible storage.
-   Cloud object storage.

Không cần thực hiện migration này ngay.

------------------------------------------------------------------------

# 36. PDF reader

PDF reader cần ưu tiên:

-   Performance.
-   Mobile.
-   Download permission.
-   Print permission.
-   Share permission.
-   Page navigation.

Không thêm animation nặng vào reader.

------------------------------------------------------------------------

# 37. Search

Search hiện có thể tiếp tục dùng PostgreSQL.

Không cần Elasticsearch ngay.

Khi dữ liệu còn nhỏ/trung bình:

``` text
PostgreSQL search
```

là đủ.

Nếu tương lai số lượng publication rất lớn và search trở thành
bottleneck thì mới đánh giá search engine riêng.

------------------------------------------------------------------------

# 38. API / Server Actions

Không cần Express.

Next.js cung cấp:

-   Route Handlers.
-   Server Actions.
-   Server Components.

Dùng:

``` text
Server Action
```

cho mutation từ UI khi phù hợp.

Dùng:

``` text
Route Handler
```

cho endpoint cần HTTP API rõ ràng hoặc external access.

------------------------------------------------------------------------

# 39. Client state

Không dùng Redux mặc định.

Ưu tiên:

``` text
URL state
Server state
React state
```

Ví dụ:

Search/filter:

``` text
URL query params
```

Modal:

``` text
useState
```

Server data:

``` text
Server Components
```

Chỉ thêm state library nếu project thật sự phát sinh nhu cầu.

------------------------------------------------------------------------

# 40. Testing

Nên có:

## Unit / Integration

Vitest hoặc công cụ tương đương.

Test:

-   Zod schemas.
-   Services.
-   Auth helpers.
-   Permission checks.
-   Like logic.
-   Comment logic.

## E2E

Playwright.

Các flow quan trọng:

``` text
Guest opens publication
User logs in
User likes publication
User comments
User edits profile
Admin logs in
Admin creates publication
Admin publishes publication
Admin deletes publication
Unauthorized user accesses admin
```

------------------------------------------------------------------------

# 41. Security checklist

Mọi mutation phải:

``` text
Authentication
    ↓
Authorization
    ↓
Validation
    ↓
Business logic
    ↓
Database
```

Không:

``` text
Client
  ↓
Database
```

Các vấn đề cần kiểm tra:

-   SQL injection.
-   XSS.
-   CSRF/session issues.
-   Broken authorization.
-   Path traversal.
-   Upload validation.
-   File type validation.
-   File size limits.
-   Rate limiting.
-   Password hashing.
-   Sensitive error messages.
-   Exposed environment variables.

------------------------------------------------------------------------

# 42. Upload security

Cover/PDF upload phải kiểm tra:

-   File size.
-   Extension.
-   MIME.
-   Generated filename.
-   Storage path.
-   User permission.

Không tin filename từ client.

Không cho user upload vào arbitrary filesystem location.

------------------------------------------------------------------------

# 43. Error handling

Không trả stack trace cho user production.

Server log:

``` text
useful diagnostic information
```

Client nhận:

``` text
safe error message
```

Ví dụ:

``` text
Không thể thực hiện thao tác. Vui lòng thử lại.
```

------------------------------------------------------------------------

# 44. Environment variables

Các secret phải nằm trong:

``` text
.env
```

Ví dụ:

``` text
DATABASE_URL
AUTH_SECRET
REDIS_URL
```

Không commit secret vào Git.

`.env.example` phải có placeholder.

------------------------------------------------------------------------

# 45. Code organization

Đề xuất:

``` text
src/
├── app/
│   ├── (site)/
│   ├── publications/
│   ├── search/
│   ├── profile/
│   ├── admin/
│   └── api/
│
├── components/
│   ├── ui/
│   ├── publication/
│   ├── comment/
│   ├── user/
│   └── admin/
│
├── lib/
│   ├── auth/
│   ├── db/
│   ├── redis/
│   ├── storage/
│   └── validation/
│
├── services/
│   ├── auth.service.ts
│   ├── user.service.ts
│   ├── publication.service.ts
│   ├── comment.service.ts
│   ├── like.service.ts
│   └── history.service.ts
│
└── types/
```

Tên folder có thể điều chỉnh theo codebase hiện tại.

Không cần di chuyển hàng loạt file nếu migration gây rủi ro.

------------------------------------------------------------------------

# 46. UI design direction

THD Digital Publishing nên có visual identity riêng.

Không biến website thành:

``` text
"shadcn demo"
```

shadcn chỉ cung cấp building blocks.

Visual identity nên tập trung:

-   Typography.
-   THD branding.
-   Publication cover.
-   Editorial layout.
-   Khoảng trắng.
-   Grid.
-   Card.
-   Accent color.
-   Motion nhẹ.

------------------------------------------------------------------------

# 47. Trang homepage dự kiến

``` text
Header
  |
Hero
  |
Featured Publications
  |
Latest Publications
  |
Popular Publications
  |
Categories / Years
  |
Call to action
  |
Footer
```

User interaction:

-   Search.
-   Open publication.
-   Like nếu logged in.
-   Share.
-   Save favorite.

------------------------------------------------------------------------

# 48. Publication detail

``` text
Cover
Title
Description
Author / metadata
Publication date
Like count
Comment count

[Read]
[Like]
[Favorite]
[Share]

Reader

Comments
```

Comments nên load theo pagination/infinite loading tùy UX.

------------------------------------------------------------------------

# 49. Profile

``` text
Avatar
Username
Bio
Joined date

Statistics
- Likes
- Comments
- Favorites

Tabs
- Favorites
- Reading history
- Comments
```

Không cần public toàn bộ history.

------------------------------------------------------------------------

# 50. Admin dashboard

``` text
Dashboard
├── Total publications
├── Published
├── Draft
├── Archived
├── Users
├── Comments
└── Engagement
```

Các statistic có thể cache bằng Redis khi dữ liệu lớn.

Không cần cache mọi statistic ngay từ đầu.

------------------------------------------------------------------------

# 51. Migration strategy

Không rewrite toàn bộ project.

Làm theo từng phase.

## Phase 1 --- Foundation

-   Audit codebase.
-   Chuẩn hóa TypeScript.
-   Cấu trúc service.
-   Zod.
-   Environment.
-   Error handling.

## Phase 2 --- Auth

-   Auth.js.
-   User model.
-   Session.
-   Role.
-   Admin authorization.
-   Login/register/logout.

## Phase 3 --- User

-   Profile.
-   Like.
-   Favorite.
-   Comment.
-   Reading history.

## Phase 4 --- UI

-   Tailwind.
-   shadcn/ui.
-   Lucide.
-   Refactor shared components.

## Phase 5 --- Animation

-   React Bits.
-   Hero.
-   Cards.
-   Selected interactive areas.
-   Reduced motion.

## Phase 6 --- Redis

-   Redis connection.
-   Cache.
-   Rate limiting.
-   Cache invalidation.

## Phase 7 --- Performance

-   Server Components audit.
-   Image optimization.
-   Font optimization.
-   Lazy loading.
-   Streaming/Suspense.
-   Database indexes.

## Phase 8 --- Testing

-   Unit.
-   Integration.
-   E2E.

## Phase 9 --- Deployment

-   Docker.
-   CI/CD.
-   Production database.
-   Object storage.
-   Monitoring/logging.

------------------------------------------------------------------------

# 52. Priority

Nếu nguồn lực hạn chế, thứ tự ưu tiên:

``` text
P0
Security
Auth
Authorization
Validation
Database integrity

P1
User
Profile
Like
Comment

P2
UI system
Tailwind
shadcn
Lucide

P3
Favorites
Reading history
Notifications

P4
Redis
Cache
Rate limiting

P5
Advanced animations
Advanced analytics
Object storage
```

Không ưu tiên animation trước security.

------------------------------------------------------------------------

# 53. Definition of Done

Một feature chỉ được xem là hoàn thành khi:

-   UI hoạt động.
-   Server validation hoạt động.
-   Authorization đúng.
-   Database constraint đúng.
-   Error handling có.
-   Mobile hoạt động.
-   Không expose secret.
-   Không tạo security hole.
-   Có test cho logic quan trọng.
-   Không phá feature hiện tại.

------------------------------------------------------------------------

# 54. Quy tắc dành cho AI khi tiếp tục code

AI phải:

1.  Đọc code hiện tại trước khi sửa.
2.  Không giả định cấu trúc project nếu chưa kiểm tra.
3.  Không rewrite file lớn nếu chỉ cần sửa nhỏ.
4.  Không thay đổi database schema mà không kiểm tra relation hiện tại.
5.  Không xóa custom logic đang hoạt động nếu chưa có migration plan.
6.  Không thêm dependency nếu dependency hiện tại đã giải quyết được vấn
    đề.
7.  Ưu tiên Server Components.
8.  Validate server-side bằng Zod.
9.  Kiểm tra authentication và authorization cho mọi mutation.
10. PostgreSQL là source of truth.
11. Redis chỉ là cache/temporary infrastructure, không thay database.
12. Không thêm Redux nếu chưa có use case rõ ràng.
13. Không thêm Express nếu Next.js đã đủ.
14. Không dùng animation quá mức.
15. Không biến public UI thành bản sao mặc định của shadcn.
16. Ưu tiên accessibility.
17. Ưu tiên performance trên mobile.
18. Sau mỗi migration lớn phải kiểm tra build, lint, typecheck và test.
19. Khi sửa Prisma schema phải kiểm tra migration và dữ liệu hiện tại.
20. Khi sửa Auth.js phải kiểm tra toàn bộ route/action cần
    authentication.

------------------------------------------------------------------------

# 55. Các công nghệ KHÔNG cần thêm ngay

Không thêm chỉ để làm stack "xịn":

``` text
Redux
GraphQL
Express
NestJS
MongoDB
Elasticsearch
Kafka
RabbitMQ
Kubernetes
Microservices
```

Chỉ thêm nếu có requirement thực tế chứng minh cần thiết.

------------------------------------------------------------------------

# 56. Target architecture

Mục tiêu cuối:

``` text
                         USERS
                           |
             +-------------+-------------+
             |             |             |
           Guest          User          Admin
             |             |             |
             +-------------+-------------+
                           |
                        Next.js
                           |
       +-------------------+-------------------+
       |                   |                   |
     Public              Auth.js             Admin
       |                   |                   |
       |                User/Role              |
       |                   |                   |
       +-------------------+-------------------+
                           |
                        Services
                           |
       +-------------------+-------------------+
       |                   |                   |
     Prisma              Zod                Redis
       |                   |                   |
       v                   |             Cache/Rate Limit
   PostgreSQL              |
       |
       +---- User
       +---- Publication
       +---- Comment
       +---- Like
       +---- Favorite
       +---- ReadingHistory
```

------------------------------------------------------------------------

# 57. Final development philosophy

THD Digital Publishing không cần trở thành một project có thật nhiều
technology.

Mục tiêu là:

``` text
Simple architecture
        +
Strong security
        +
Good UX
        +
Good performance
        +
Maintainable code
        +
Room to scale
```

Công nghệ được chọn vì giải quyết vấn đề cụ thể:

``` text
Next.js
→ Application framework

PostgreSQL
→ Main database

Prisma
→ Database access

Auth.js
→ Authentication/session

Zod
→ Validation

Tailwind
→ Styling system

shadcn/ui
→ Reusable UI primitives

Lucide
→ Icon system

React Bits
→ Selected animation/interaction

Redis
→ Cache/rate limit/temporary workloads
```

Không nên đánh đổi tính ổn định của hệ thống để lấy số lượng technology.

------------------------------------------------------------------------

# 58. Immediate next steps

Khi bắt đầu implementation, AI nên làm theo thứ tự:

\`\`\`text 1. Inspect current repository and branch. 2. Inspect current
Prisma schema. 3. Inspect current custom authentication. 4. Design
Auth.js migration. 5. Add/adjust User + Role models. 6. Implement
server-side authorization. 7. Add Zod. 8. Implement Profile. 9.
Implement Like. 10. Implement Comment. 11. Implement Favorite. 12.
Implement Reading History. 13. Introduce Tailwind. 14. Introduce
shadcn/ui. 15. Introduce Lucide. 16. Add selected React Bits effects.
17. Add Redis. 18. Add caching/rate limiting. 19. Add tests. 20.
Optimize production/deployment.

Do not jump directly to Redis or animations before authentication,
authorization, validation and database design are stable.
