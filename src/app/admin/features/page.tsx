import Link from "next/link";
import { prisma } from "@/lib/prisma";
import { requireAdmin } from "@/lib/authorization";
import {
  getGlobalFeatureState,
  getPublicationFeatureState,
} from "@/lib/features/service";
import {
  getFeatureDefinitionForManagement,
  getManagedFeatureDefinitions,
} from "@/lib/features/management";
import {
  loadAuditLogsForAdmin,
  type FeatureAuditLogEntry,
} from "@/lib/features/admin-data";
import {
  GlobalFeatureToggle,
  PublicationOverrideForm,
} from "./feature-controls";

type SearchParams = {
  q?: string | string[];
  publication?: string | string[];
};

const sourceLabels: Record<string, string> = {
  global: "Global",
  "global-disabled": "Global đang tắt",
  inherit: "Theo Global (INHERIT)",
  "override-enabled": "Override bật",
  "override-disabled": "Override tắt",
  "missing-flag": "Thiếu bản ghi — fail-closed",
  "database-error": "Lỗi dữ liệu — fail-closed",
  "unknown-key": "Key không hợp lệ",
  "publication-not-found": "Không tìm thấy ấn phẩm",
};

const actionLabels: Record<string, string> = {
  SET_GLOBAL: "Đặt Global",
  SET_OVERRIDE: "Đặt override",
  RESET_OVERRIDE: "Bỏ override",
};

const statusLabels: Record<string, string> = {
  published: "Đã xuất bản",
  draft: "Bản nháp",
  archived: "Lưu trữ",
};

function formatDateTime(value: Date) {
  return value.toLocaleString("vi-VN", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

function formatAuditValue(value: unknown): string {
  if (value === null || value === undefined) return "—";

  if (typeof value === "object") {
    const record = value as Record<string, unknown>;
    if (typeof record.enabled === "boolean") {
      return record.enabled ? "Bật" : "Tắt";
    }
    if (typeof record.state === "string") return record.state;
  }

  return String(value);
}

function firstParam(value: string | string[] | undefined): string {
  if (Array.isArray(value)) return value[0] ?? "";
  return value ?? "";
}

export default async function AdminFeaturesPage({
  searchParams,
}: {
  searchParams: Promise<SearchParams>;
}) {
  await requireAdmin();

  const params = await searchParams;
  const query = firstParam(params.q).trim();
  const selectedPublicationId = firstParam(params.publication).trim();

  const definitions = getManagedFeatureDefinitions();

  const globalFlags = await Promise.all(
    definitions.map(async (definition) => ({
      definition,
      state: await getGlobalFeatureState(definition.key),
    }))
  );

  const publications = await prisma.publication.findMany({
    where: query
      ? {
          OR: [
            { title: { contains: query, mode: "insensitive" } },
            { author: { contains: query, mode: "insensitive" } },
          ],
        }
      : undefined,
    orderBy: { updatedAt: "desc" },
    take: 8,
    select: { id: true, title: true, slug: true, status: true },
  });

  const selectedPublication = selectedPublicationId
    ? await prisma.publication.findUnique({
        where: { id: selectedPublicationId },
        select: { id: true, title: true, slug: true, status: true },
      })
    : null;

  const publicationFlags = selectedPublication
    ? await Promise.all(
        definitions.map(async (definition) => ({
          definition,
          state: await getPublicationFeatureState(
            definition.key,
            selectedPublication.id
          ),
        }))
      )
    : [];

  let auditLogs: FeatureAuditLogEntry[] = [];
  let auditError = false;

  try {
    auditLogs = await loadAuditLogsForAdmin({
      requireAdmin,
      fetchAuditLogs: async (limit) => {
        const rows = await prisma.featureSettingAuditLog.findMany({
          orderBy: { createdAt: "desc" },
          take: limit,
          select: {
            id: true,
            createdAt: true,
            actorId: true,
            featureKey: true,
            scope: true,
            publicationId: true,
            action: true,
            oldValue: true,
            newValue: true,
            actor: { select: { name: true, username: true } },
            publication: { select: { title: true, slug: true } },
          },
        });

        return rows.map((row) => ({
          id: row.id,
          createdAt: row.createdAt,
          actorId: row.actorId,
          actorName: row.actor?.name ?? null,
          actorUsername: row.actor?.username ?? null,
          featureKey: row.featureKey,
          scope: row.scope,
          publicationId: row.publicationId,
          publicationTitle: row.publication?.title ?? null,
          publicationSlug: row.publication?.slug ?? null,
          action: row.action,
          oldValue: row.oldValue,
          newValue: row.newValue,
        }));
      },
    });
  } catch {
    auditError = true;
  }

  return (
    <>
      <header className="admin-header">
        <div className="admin-header-inner">
          <div className="admin-brand">
            <Link href="/admin" className="admin-brand-link">
              <span className="admin-brand-text">
                <span className="admin-brand-school">THPT A Trần Hưng Đạo</span>
                <span className="admin-brand-name">Thư viện Ấn phẩm số</span>
              </span>
            </Link>
          </div>

          <nav className="admin-nav" aria-label="Điều hướng quản trị">
            <Link href="/admin">Dashboard</Link>
            <Link href="/admin/features" aria-current="page">
              Feature Flags
            </Link>
            <Link href="/">Xem thư viện</Link>
          </nav>

          <div className="admin-account">
            <form
              action={async () => {
                "use server";
                const { signOut } = await import("@/lib/auth-nextauth");
                await signOut({ redirectTo: "/login" });
              }}
            >
              <button type="submit" className="button button-secondary">
                Đăng xuất
              </button>
            </form>
          </div>
        </div>
      </header>

      <main className="admin-main">
        <header className="admin-page-header">
          <div>
            <p className="admin-eyebrow">Quản trị hệ thống</p>
            <h1>Feature Flags</h1>
            <p className="admin-page-description">
              Quản lý sáu tính năng toàn hệ thống, override theo từng ấn phẩm và
              theo dõi Audit Log. Global đang tắt luôn thắng override ENABLED.
            </p>
          </div>

          <div className="admin-page-actions">
            <Link href="/admin" className="button button-secondary">
              ← Dashboard
            </Link>
          </div>
        </header>

        <section className="admin-section" aria-labelledby="global-flags-title">
          <div className="admin-section-header">
            <div>
              <h2 id="global-flags-title">Global Feature Flags</h2>
              <p>
                Sáu flag được quản lý trong giao diện này. Tắt Global sẽ tắt
                tính năng trên toàn hệ thống, kể cả khi ấn phẩm có override
                ENABLED.
              </p>
            </div>
          </div>

          <div className="feature-flag-grid">
            {globalFlags.map(({ definition, state }) => (
              <article
                key={definition.key}
                className={`feature-flag-card ${
                  state.enabled ? "is-enabled" : "is-disabled"
                }`}
              >
                <div className="feature-flag-card-header">
                  <div>
                    <h3>{definition.name}</h3>
                    <p>{definition.description}</p>
                  </div>
                  <span
                    className={`feature-status-pill ${
                      state.enabled ? "is-on" : "is-off"
                    }`}
                  >
                    {state.enabled ? "Bật" : "Tắt"}
                  </span>
                </div>

                <dl className="feature-flag-meta">
                  <div>
                    <dt>Key</dt>
                    <dd>
                      <code>{definition.key}</code>
                    </dd>
                  </div>
                  <div>
                    <dt>Phạm vi</dt>
                    <dd>
                      {definition.scope === "GLOBAL"
                        ? "Toàn hệ thống"
                        : "Toàn hệ thống + ấn phẩm"}
                    </dd>
                  </div>
                  <div>
                    <dt>Nguồn</dt>
                    <dd>{sourceLabels[state.source] ?? state.source}</dd>
                  </div>
                </dl>

                {!state.exists && (
                  <p className="feature-note is-warning">
                    Chưa có bản ghi FeatureFlag. Hệ thống đang fail-closed và
                    coi tính năng là tắt.
                  </p>
                )}

                <GlobalFeatureToggle
                  featureKey={definition.key}
                  enabled={state.enabled}
                />
              </article>
            ))}
          </div>
        </section>

        <section
          className="admin-section"
          aria-labelledby="publication-overrides-title"
        >
          <div className="admin-section-header">
            <div>
              <h2 id="publication-overrides-title">Override theo ấn phẩm</h2>
              <p>
                Tìm ấn phẩm để xem trạng thái Global, override hiện tại và hiệu
                lực thực tế. Chỉ các feature có phạm vi ấn phẩm mới nhận
                override.
              </p>
            </div>
          </div>

          <form action="/admin/features" method="get" className="feature-search-form">
            <label htmlFor="feature-publication-search" className="feature-search-label">
              Tìm ấn phẩm
            </label>
            <div className="feature-search-row">
              <input
                id="feature-publication-search"
                name="q"
                type="search"
                defaultValue={query}
                placeholder="Nhập tiêu đề hoặc tác giả"
              />
              <button type="submit" className="button button-primary">
                Tìm
              </button>
            </div>
            {selectedPublicationId && (
              <input type="hidden" name="publication" value={selectedPublicationId} />
            )}
          </form>

          {publications.length === 0 ? (
            <div className="admin-empty-state">
              <h3>Không tìm thấy ấn phẩm</h3>
              <p>Thử từ khóa khác hoặc xóa bộ lọc để xem ấn phẩm mới nhất.</p>
              <Link href="/admin/features" className="button button-secondary">
                Xóa bộ lọc
              </Link>
            </div>
          ) : (
            <div className="admin-table-wrapper">
              <table className="admin-table">
                <thead>
                  <tr>
                    <th scope="col">Ấn phẩm</th>
                    <th scope="col">Trạng thái</th>
                    <th scope="col">Override</th>
                  </tr>
                </thead>
                <tbody>
                  {publications.map((publication) => (
                    <tr key={publication.id}>
                      <td>
                        <div className="admin-publication-cell">
                          <strong>{publication.title}</strong>
                          <small>{publication.slug}</small>
                        </div>
                      </td>
                      <td>
                        <span
                          className={`status-badge status-${publication.status}`}
                        >
                          {statusLabels[publication.status] ?? publication.status}
                        </span>
                      </td>
                      <td>
                        <Link
                          href={`/admin/features?q=${encodeURIComponent(
                            query
                          )}&publication=${publication.id}`}
                        >
                          Quản lý override
                        </Link>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {selectedPublication ? (
            <div className="feature-publication-panel">
              <div className="feature-publication-heading">
                <div>
                  <h3>{selectedPublication.title}</h3>
                  <p>
                    <code>{selectedPublication.slug}</code> ·{" "}
                    {statusLabels[selectedPublication.status] ??
                      selectedPublication.status}
                  </p>
                </div>
                <Link
                  href={`/admin/features?q=${encodeURIComponent(query)}`}
                  className="button button-secondary button-sm"
                >
                  Bỏ chọn
                </Link>
              </div>

              <p className="feature-note">
                Quy tắc ưu tiên: Global OFF luôn thắng. Khi Global ON, override
                DISABLED tắt riêng ấn phẩm, override ENABLED bật, và INHERIT
                theo Global.
              </p>

              <div className="admin-table-wrapper">
                <table className="admin-table feature-override-table">
                  <thead>
                    <tr>
                      <th scope="col">Tính năng</th>
                      <th scope="col">Global</th>
                      <th scope="col">Override</th>
                      <th scope="col">Hiệu lực</th>
                      <th scope="col">Điều khiển</th>
                    </tr>
                  </thead>
                  <tbody>
                    {publicationFlags.map(({ definition, state }) => {
                      const supportsOverride =
                        definition.scope === "GLOBAL_AND_PUBLICATION";

                      return (
                        <tr key={definition.key}>
                          <td>
                            <div className="admin-publication-cell">
                              <strong>{definition.name}</strong>
                              <small>{definition.key}</small>
                            </div>
                          </td>
                          <td>
                            <span
                              className={`feature-inline-state ${
                                state.globalEnabled ? "is-on" : "is-off"
                              }`}
                            >
                              {state.globalEnabled ? "Bật" : "Tắt"}
                            </span>
                          </td>
                          <td>
                            {supportsOverride
                              ? state.override ?? "INHERIT"
                              : "Không áp dụng"}
                          </td>
                          <td>
                            <span
                              className={`feature-inline-state ${
                                state.enabled ? "is-on" : "is-off"
                              }`}
                            >
                              {state.enabled ? "Bật" : "Tắt"}
                            </span>
                            <small className="feature-source">
                              {sourceLabels[state.source] ?? state.source}
                            </small>
                          </td>
                          <td>
                            {supportsOverride ? (
                              <PublicationOverrideForm
                                featureKey={definition.key}
                                publicationId={selectedPublication.id}
                                currentState={state.override ?? "INHERIT"}
                                globalEnabled={state.globalEnabled}
                              />
                            ) : (
                              <span className="feature-source">
                                Chỉ cấu hình Global
                              </span>
                            )}
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            </div>
          ) : (
            selectedPublicationId !== "" && (
              <div className="admin-alert admin-alert-error" role="alert">
                Không tìm thấy ấn phẩm đã chọn. Có thể ấn phẩm đã bị xóa.
              </div>
            )
          )}
        </section>

        <section className="admin-section" aria-labelledby="audit-log-title">
          <div className="admin-section-header">
            <div>
              <h2 id="audit-log-title">Audit Log gần đây</h2>
              <p>
                20 thay đổi Feature Flag mới nhất. Chỉ ADMIN có quyền xem khu
                vực này.
              </p>
            </div>
          </div>

          {auditError ? (
            <div className="admin-alert admin-alert-error" role="alert">
              Không thể tải Audit Log. Vui lòng thử lại sau.
            </div>
          ) : auditLogs.length === 0 ? (
            <div className="admin-empty-state">
              <h3>Chưa có thay đổi nào</h3>
              <p>
                Audit Log sẽ xuất hiện sau khi bạn cập nhật Global Flag hoặc
                override của một ấn phẩm.
              </p>
            </div>
          ) : (
            <div className="admin-table-wrapper">
              <table className="admin-table feature-audit-table">
                <thead>
                  <tr>
                    <th scope="col">Thời gian</th>
                    <th scope="col">Người thực hiện</th>
                    <th scope="col">Hành động</th>
                    <th scope="col">Feature</th>
                    <th scope="col">Ấn phẩm</th>
                    <th scope="col">Trước → Sau</th>
                  </tr>
                </thead>
                <tbody>
                  {auditLogs.map((entry) => (
                    <tr key={entry.id}>
                      <td>{formatDateTime(entry.createdAt)}</td>
                      <td>
                        {entry.actorName ??
                          entry.actorUsername ??
                          entry.actorId ??
                          "Không xác định"}
                      </td>
                      <td>
                        {actionLabels[entry.action] ?? entry.action}
                        <small className="feature-source">{entry.scope}</small>
                      </td>
                      <td>
                        {getFeatureDefinitionForManagement(entry.featureKey)
                          ?.name ?? entry.featureKey}
                        <small className="feature-source">
                          {entry.featureKey}
                        </small>
                      </td>
                      <td>
                        {entry.publicationTitle ?? "—"}
                        {entry.publicationSlug && (
                          <small className="feature-source">
                            {entry.publicationSlug}
                          </small>
                        )}
                      </td>
                      <td>
                        {formatAuditValue(entry.oldValue)} →{" "}
                        <strong>{formatAuditValue(entry.newValue)}</strong>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>
      </main>

      <footer className="admin-footer">
        <p>THPT A Trần Hưng Đạo · Thư viện Ấn phẩm số</p>
      </footer>
    </>
  );
}
