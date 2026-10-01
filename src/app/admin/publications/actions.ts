"use server";

import { prisma } from "@/lib/prisma";
import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";

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

function readForm(formData: FormData) {
  return {
    title: String(formData.get("title") ?? "").trim(),
    subtitle: String(formData.get("subtitle") ?? "").trim(),
    year: Number(formData.get("year")) || new Date().getFullYear(),
    description: String(formData.get("description") ?? "").trim(),
    cover: String(formData.get("cover") ?? "").trim(),
    pdf: String(formData.get("pdf") ?? "").trim(),
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

export async function createPublication(formData: FormData) {
  const data = readForm(formData);
  if (!data.title) throw new Error("Thiếu tiêu đề ấn phẩm.");

  const slug = slugify(data.title);

  await prisma.publication.create({
    data: {
      ...data,
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

  await prisma.publication.update({
    where: { id },
    data: {
      ...data,
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