"use server";

import { prisma } from "@/lib/prisma";
import { requireAdmin } from "@/lib/authorization";
import { parsePublicationForm } from "@/lib/validation";
import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import { writeFile, mkdir, unlink } from "fs/promises";
import path from "path";

const STORAGE_ROOT = path.join(process.cwd(), "storage");
const MAX_COVER_SIZE = 10 * 1024 * 1024;
const MAX_PDF_SIZE = 100 * 1024 * 1024;

function slugify(text: string): string {
  const slug = text
    .toLowerCase()
    .normalize("NFD")
    .replace(/[̀-ͯ]/g, "")
    .replace(/đ/g, "d")
    .replace(/[^a-z0-9\s-]/g, "")
    .trim()
    .replace(/\s+/g, "-");

  return slug || `publication-${Date.now().toString(36)}`;
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

  await writeFile(filePath, Buffer.from(await file.arrayBuffer()));
  return `${subfolder}/${filename}`;
}

async function removeStoredFile(relativePath: string) {
  if (!relativePath) return;
  const target = path.resolve(STORAGE_ROOT, relativePath);
  const root = path.resolve(STORAGE_ROOT);

  if (!target.startsWith(root + path.sep)) return;

  try {
    await unlink(target);
  } catch {
    // File may already be missing; the database record is still authoritative.
  }
}

export async function createPublication(formData: FormData) {
  await requireAdmin();

  const data = parsePublicationForm(formData);
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
      id: "pub_" + Date.now().toString(36) + Math.random().toString(36).slice(2, 8),
      slug,
      publishedAt: data.status === "published" ? new Date().toISOString() : "",
    },
  });

  revalidatePath("/admin");
  revalidatePath("/publications");
  redirect("/admin");
}

export async function updatePublication(id: string, formData: FormData) {
  await requireAdmin();

  const data = parsePublicationForm(formData);
  const existing = await prisma.publication.findUnique({ where: { id } });
  if (!existing) throw new Error("Không tìm thấy ấn phẩm.");

  const coverFile = formData.get("coverFile") as File | null;
  const pdfFile = formData.get("pdfFile") as File | null;

  const coverChanged = !!coverFile && coverFile.size > 0;
  const pdfChanged = !!pdfFile && pdfFile.size > 0;

  const coverPath = coverChanged
    ? await saveUploadedFile(coverFile!, "covers", MAX_COVER_SIZE)
    : existing.cover;
  const pdfPath = pdfChanged
    ? await saveUploadedFile(pdfFile!, "pdfs", MAX_PDF_SIZE)
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

  if (coverChanged && existing.cover) await removeStoredFile(existing.cover);
  if (pdfChanged && existing.pdf) await removeStoredFile(existing.pdf);

  revalidatePath("/admin");
  revalidatePath("/publications");
  revalidatePath(`/publications/${existing.slug}`);
  redirect("/admin");
}

export async function deletePublication(id: string) {
  await requireAdmin();

  const existing = await prisma.publication.findUnique({ where: { id } });
  if (!existing) return;

  await prisma.publication.delete({ where: { id } });

  await Promise.all([
    removeStoredFile(existing.cover),
    removeStoredFile(existing.pdf),
  ]);

  revalidatePath("/admin");
  revalidatePath("/publications");
}
