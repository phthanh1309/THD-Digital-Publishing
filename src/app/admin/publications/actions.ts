"use server";

import { prisma } from "@/lib/prisma";
import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import { writeFile, mkdir } from "fs/promises";
import path from "path";

const STORAGE_ROOT = path.join(process.cwd(), "storage");
const MAX_COVER_SIZE = 10 * 1024 * 1024; // 10MB
const MAX_PDF_SIZE = 100 * 1024 * 1024; // 100MB

function slugify(text: string): string {
  return text
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/đ/g, "d")
    .replace(/[^a-z0-9\s-]/g, "")
    .trim()
    .replace(/\s+/g, "-");
}

async function uniqueSlug(base: string): Promise<string> {
  let slug = base;
  let counter = 2;
  while (await prisma.publication.findUnique({ where: { slug } })) {
    slug = `${base}-${counter}`;
    counter++;
  }
  return slug;
}

function readForm(formData: FormData) {
  return {
    title: String(formData.get("title") ?? "").trim(),
    subtitle: String(formData.get("subtitle") ?? "").trim(),
    year: Number(formData.get("year")) || new Date().getFullYear(),
    description: String(formData.get("description") ?? "").trim(),
    pageCount: Number(formData.get("pageCount")) || 0,
    status: String(formData.get("status") ?? "draft") as
      | "draft"
      | "published"
      | "archived",
    author: String(formData.get("author") ?? "").trim(),
    editor: String(formData.get("editor") ?? "").trim(),
    language: String(formData.get("language") ?? "Tiếng Việt").trim(),
    allowDownload: formData.get("allowDownload") === "on",
    allowPrint: formData.get("allowPrint") === "on",
    allowShare: formData.get("allowShare") === "on",
  };
}

function newId(): string {
  return "pub_" + Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
}

async function saveUploadedFile(
  file: File,
  subfolder: "covers" | "pdfs",
  maxSize: number
): Promise<string> {
  if (file.size === 0) return "";
  if (file.size > maxSize) {
    throw new Error(
      `File quá lớn (tối đa ${Math.round(maxSize / 1024 / 1024)}MB).`
    );
  }

  const ext = path.extname(file.name).toLowerCase();
  const allowedExt =
    subfolder === "covers" ? [".jpg", ".jpeg", ".png", ".webp"] : [".pdf"];
  if (!allowedExt.includes(ext)) {
    throw new Error("Định dạng file không được hỗ trợ.");
  }

  const dir = path.join(STORAGE_ROOT, subfolder);
  await mkdir(dir, { recursive: true });

  const filename = `${Date.now()}-${Math.random().toString(36).slice(2, 8)}${ext}`;
  const filePath = path.join(dir, filename);

  const buffer = Buffer.from(await file.arrayBuffer());
  await writeFile(filePath, buffer);

  return `${subfolder}/${filename}`;
}

export async function createPublication(formData: FormData) {
  const data = readForm(formData);
  if (!data.title) throw new Error("Thiếu tiêu đề ấn phẩm.");

  const coverFile = formData.get("coverFile") as File | null;
  const pdfFile = formData.get("pdfFile") as File | null;

  const coverPath = coverFile
    ? await saveUploadedFile(coverFile, "covers", MAX_COVER_SIZE)
    : "";
  const pdfPath = pdfFile
    ? await saveUploadedFile(pdfFile, "pdfs", MAX_PDF_SIZE)
    : "";

  const slug = await uniqueSlug(slugify(data.title));

  await prisma.publication.create({
    data: {
      ...data,
      cover: coverPath,
      pdf: pdfPath,
      id: newId(),
      slug,
      publishedAt: data.status === "published" ? new Date().toISOString() : "",
    },
  });

  revalidatePath("/admin");
  redirect("/admin");
}

export async function updatePublication(id: string, formData: FormData) {
  const data = readForm(formData);
  if (!data.title) throw new Error("Thiếu tiêu đề ấn phẩm.");

  const existing = await prisma.publication.findUnique({ where: { id } });
  if (!existing) throw new Error("Không tìm thấy ấn phẩm.");

  const coverFile = formData.get("coverFile") as File | null;
  const pdfFile = formData.get("pdfFile") as File | null;

  const coverPath = coverFile && coverFile.size > 0
    ? await saveUploadedFile(coverFile, "covers", MAX_COVER_SIZE)
    : existing.cover;
  const pdfPath = pdfFile && pdfFile.size > 0
    ? await saveUploadedFile(pdfFile, "pdfs", MAX_PDF_SIZE)
    : existing.pdf;

  await prisma.publication.update({
    where: { id },
    data: {
      ...data,
      cover: coverPath,
      pdf: pdfPath,
      publishedAt:
        data.status === "published"
          ? existing.publishedAt || new Date().toISOString()
          : existing.publishedAt,
    },
  });

  revalidatePath("/admin");
  redirect("/admin");
}

export async function deletePublication(id: string) {
  await prisma.publication.delete({ where: { id } });
  revalidatePath("/admin");
}