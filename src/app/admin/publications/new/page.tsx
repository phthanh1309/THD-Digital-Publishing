import { createPublication } from "../actions";

export default function NewPublicationPage() {
  return (
    <main className="admin-main">
      <header className="admin-page-header">
        <div>
          <p className="admin-eyebrow">Quản trị hệ thống</p>
          <h1>Thêm ấn phẩm</h1>
        </div>
      </header>

      <section className="admin-section">
        <form action={createPublication} className="admin-login-form" style={{ maxWidth: 640, display: "flex", flexDirection: "column", gap: 16 }}>
          <div className="form-field">
            <label htmlFor="title">Tiêu đề *</label>
            <input id="title" name="title" type="text" required maxLength={200} />
          </div>

          <div className="form-field">
            <label htmlFor="subtitle">Phụ đề</label>
            <input id="subtitle" name="subtitle" type="text" maxLength={200} />
          </div>

          <div className="form-field">
            <label htmlFor="year">Năm *</label>
            <input id="year" name="year" type="number" required defaultValue={new Date().getFullYear()} />
          </div>

          <div className="form-field">
            <label htmlFor="description">Mô tả</label>
            <textarea id="description" name="description" rows={4} />
          </div>

          <div className="form-field">
            <label htmlFor="coverFile">Ảnh bìa (jpg/png/webp)</label>
            <input id="coverFile" name="coverFile" type="file" accept="image/jpeg,image/png,image/webp" />
          </div>

          <div className="form-field">
            <label htmlFor="pdfFile">File PDF</label>
            <input id="pdfFile" name="pdfFile" type="file" accept="application/pdf" />
          </div>

          <div className="form-field">
            <label htmlFor="pageCount">Số trang</label>
            <input id="pageCount" name="pageCount" type="number" defaultValue={0} />
          </div>

          <div className="form-field">
            <label htmlFor="author">Tác giả</label>
            <input id="author" name="author" type="text" />
          </div>

          <div className="form-field">
            <label htmlFor="editor">Biên tập</label>
            <input id="editor" name="editor" type="text" />
          </div>

          <div className="form-field">
            <label htmlFor="language">Ngôn ngữ</label>
            <input id="language" name="language" type="text" defaultValue="Tiếng Việt" />
          </div>

          <div className="form-field">
            <label htmlFor="status">Trạng thái</label>
            <select id="status" name="status" defaultValue="draft">
              <option value="draft">Bản nháp</option>
              <option value="published">Đã xuất bản</option>
              <option value="archived">Lưu trữ</option>
            </select>
          </div>

          <div style={{ display: "flex", gap: 16 }}>
            <label>
              <input type="checkbox" name="allowDownload" /> Cho phép tải xuống
            </label>
            <label>
              <input type="checkbox" name="allowPrint" /> Cho phép in
            </label>
            <label>
              <input type="checkbox" name="allowShare" defaultChecked /> Cho phép chia sẻ
            </label>
          </div>

          <button type="submit" className="button button-primary">
            Tạo ấn phẩm
          </button>
        </form>
      </section>
    </main>
  );
}