import { NextRequest, NextResponse } from "next/server";
import { readFile, stat } from "fs/promises";
import path from "path";
import { prisma } from "@/lib/prisma";

const STORAGE_ROOT = path.join(process.cwd(), "storage");

const MIME_TYPES: Record<string, string> = {
  ".jpg": "image/jpeg",
  ".jpeg": "image/jpeg",
  ".png": "image/png",
  ".webp": "image/webp",
  ".pdf": "application/pdf",
};

export async function GET(
  req: NextRequest,
  { params }: { params: Promise<{ path: string[] }> }
) {
  const { path: segments } = await params;

  if (segments.some((s) => s.includes("..") || s.includes("/"))) {
    return new NextResponse("Không hợp lệ.", { status: 400 });
  }

  const relativePath = segments.join("/");
  const isPdf = relativePath.startsWith("pdfs/");
  const isCover = relativePath.startsWith("covers/");
  const isAvatar = relativePath.startsWith("avatars/");

  if (!isPdf && !isCover && !isAvatar) {
    return new NextResponse("Không hợp lệ.", { status: 400 });
  }

  let isDownloadRequest = false;
  let downloadName = "file";

  if (!isAvatar) {
    const publication = await prisma.publication.findFirst({
      where: isPdf ? { pdf: relativePath } : { cover: relativePath },
    });

    if (!publication) {
      return new NextResponse("Không tìm thấy file.", { status: 404 });
    }

    if (publication.status !== "published") {
      return new NextResponse("Không có quyền truy cập.", { status: 403 });
    }

    downloadName = publication.slug;

    isDownloadRequest = req.nextUrl.searchParams.get("download") === "1";
    if (isPdf && isDownloadRequest && !publication.allowDownload) {
      return new NextResponse("Ấn phẩm này không cho phép tải xuống.", {
        status: 403,
      });
    }
  }

  const filePath = path.join(STORAGE_ROOT, relativePath);
  const ext = path.extname(filePath).toLowerCase();
  const mime = MIME_TYPES[ext];
  if (!mime) {
    return new NextResponse("Định dạng không được hỗ trợ.", { status: 400 });
  }

  try {
    const fileStat = await stat(filePath);
    if (!fileStat.isFile()) throw new Error("not a file");

    const buffer = await readFile(filePath);
    return new NextResponse(new Uint8Array(buffer), {
      headers: {
        "Content-Type": mime,
        "Content-Length": String(fileStat.size),
        "Cache-Control": "public, max-age=3600",
        ...(isDownloadRequest
          ? { "Content-Disposition": `attachment; filename="${downloadName}${ext}"` }
          : {}),
      },
    });
  } catch {
    return new NextResponse("Không tìm thấy file.", { status: 404 });
  }
}