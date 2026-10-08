import { notFound } from "next/navigation";
import { prisma } from "@/lib/prisma";
import { updatePublication, deletePublication } from "../../actions";
import { requireAdmin } from "@/lib/authorization";

export default async function EditPublicationPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  await requireAdmin();

  const { id } = await params;
  const p = await prisma.publication.findUnique({ where: { id } });

  if (!p) notFound();

  const updateWithId = updatePublication.bind(null, id);
  const deleteWithId = deletePublication.bind(null, id);

  return (
    <main className="admin-main">
      <header className="admin-page-header">
        <div>
          <p className="admin-eyebrow">Quản trị hệ thống</p>
          <h1>Chỉnh sửa ấn phẩm</h1>
        </div>
      </header>

      <section className="admin-section">
        <form action={updateWithId} className="admin-login-form" style={{ maxWidth: 640, display: "flex", flexDirection: "column", gap: 16 }}>
          <div className="form-field">
            <label htmlFor="title">Tiêu đề *</label>
            <input id="title" name="title" type="text" required maxLength={200} defaultValue={p.title} />
          </div>

          <div className="form-field">
            <label htmlFor="subtitle">Phụ đề</label>
            <input id="subtitle" name="subtitle" type="text" maxLength={200} defaultValue={p.subtitle} />
          </div>

          <div className="form-field">
            <label htmlFor="year">Năm *</label>
            <input id="year" name="year" type="number" required defaultValue={p.year} />
          </div>

          <div className="form-field">
            <label htmlFor="description">Mô tả</label>
            <textarea id="description" name="description" rows={4} defaultValue={p.description} />
          </div>

          <div className="form-field">
            <label htmlFor="coverFile">Ảnh bìa (jpg/png/webp)</label>
            {p.cover && (
                <p style={{ fontSize: 13, color: "#666", margin: "4px 0" }}>
                Hiện tại: {p.cover}
                </p>
            )}
            <input id="coverFile" name="coverFile" type="file" accept="image/jpeg,image/png,image/webp" />
            </div>

          <div className="form-field">
            <label htmlFor="pdfFile">File PDF</label>
            {p.pdf && (
                <p style={{ fontSize: 13, color: "#666", margin: "4px 0" }}>
                Hiện tại: {p.pdf}
                </p>
            )}
            <input id="pdfFile" name="pdfFile" type="file" accept="application/pdf" />
          </div>

          <div className="form-field">
            <label htmlFor="pageCount">Số trang</label>
            <input id="pageCount" name="pageCount" type="number" defaultValue={p.pageCount} />
          </div>

          <div className="form-field">
            <label htmlFor="author">Tác giả</label>
            <input id="author" name="author" type="text" defaultValue={p.author} />
          </div>

          <div className="form-field">
            <label htmlFor="editor">Biên tập</label>
            <input id="editor" name="editor" type="text" defaultValue={p.editor} />
          </div>

          <div className="form-field">
            <label htmlFor="language">Ngôn ngữ</label>
            <input id="language" name="language" type="text" defaultValue={p.language} />
          </div>

          <div className="form-field">
            <label htmlFor="status">Trạng thái</label>
            <select id="status" name="status" defaultValue={p.status}>
              <option value="draft">Bản nháp</option>
              <option value="published">Đã xuất bản</option>
              <option value="archived">Lưu trữ</option>
            </select>
          </div>

          <div style={{ display: "flex", gap: 16 }}>
            <label>
              <input type="checkbox" name="allowDownload" defaultChecked={p.allowDownload} /> Cho phép tải xuống
            </label>
            <label>
              <input type="checkbox" name="allowPrint" defaultChecked={p.allowPrint} /> Cho phép in
            </label>
            <label>
              <input type="checkbox" name="allowShare" defaultChecked={p.allowShare} /> Cho phép chia sẻ
            </label>
          </div>

          <div style={{ display: "flex", gap: 12 }}>
            <button type="submit" className="button button-primary">
              Lưu thay đổi
            </button>
          </div>
        </form>

        <form action={deleteWithId} style={{ marginTop: 24 }}>
          <button type="submit" className="button button-secondary">
            Xóa ấn phẩm này
          </button>
        </form>
      </section>
    </main>
  );
}