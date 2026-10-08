import { z } from "zod";

export const registerSchema = z.object({
  username: z
    .string()
    .trim()
    .min(3, "Tên đăng nhập phải có ít nhất 3 ký tự.")
    .max(30, "Tên đăng nhập tối đa 30 ký tự.")
    .regex(/^[a-zA-Z0-9_]+$/, "Tên đăng nhập chỉ được chứa chữ, số và dấu gạch dưới."),
  email: z.email("Email không hợp lệ.").trim().toLowerCase(),
  password: z
    .string()
    .min(8, "Mật khẩu phải có ít nhất 8 ký tự.")
    .max(72, "Mật khẩu tối đa 72 ký tự."),
});

export const profileSchema = z.object({
  name: z.string().trim().max(80, "Tên hiển thị tối đa 80 ký tự."),
  bio: z.string().trim().max(500, "Giới thiệu tối đa 500 ký tự."),
});

export const commentSchema = z.object({
  content: z
    .string()
    .trim()
    .min(1, "Bình luận không được để trống.")
    .max(1000, "Bình luận tối đa 1000 ký tự."),
});

export const publicationSchema = z.object({
  title: z.string().trim().min(1, "Thiếu tiêu đề ấn phẩm.").max(200, "Tiêu đề tối đa 200 ký tự."),
  subtitle: z.string().trim().max(200, "Phụ đề tối đa 200 ký tự."),
  year: z.number().int("Năm phải là số nguyên.").min(1900, "Năm không hợp lệ.").max(2100, "Năm không hợp lệ."),
  description: z.string().trim().max(10000, "Mô tả tối đa 10000 ký tự."),
  pageCount: z.number().int("Số trang phải là số nguyên.").min(0, "Số trang không được âm.").max(10000, "Số trang không hợp lệ."),
  status: z.enum(["draft", "published", "archived"]),
  author: z.string().trim().max(200, "Tác giả tối đa 200 ký tự."),
  editor: z.string().trim().max(200, "Biên tập tối đa 200 ký tự."),
  language: z.string().trim().max(50, "Ngôn ngữ tối đa 50 ký tự."),
  allowDownload: z.boolean(),
  allowPrint: z.boolean(),
  allowShare: z.boolean(),
});

export function firstZodError(error: z.ZodError): string {
  return error.issues[0]?.message ?? "Dữ liệu không hợp lệ.";
}

export function parsePublicationForm(formData: FormData) {
  return publicationSchema.parse({
    title: String(formData.get("title") ?? ""),
    subtitle: String(formData.get("subtitle") ?? ""),
    year: Number(formData.get("year")),
    description: String(formData.get("description") ?? ""),
    pageCount: Number(formData.get("pageCount")),
    status: String(formData.get("status") ?? "draft"),
    author: String(formData.get("author") ?? ""),
    editor: String(formData.get("editor") ?? ""),
    language: String(formData.get("language") ?? "Tiếng Việt"),
    allowDownload: formData.get("allowDownload") === "on",
    allowPrint: formData.get("allowPrint") === "on",
    allowShare: formData.get("allowShare") === "on",
  });
}
