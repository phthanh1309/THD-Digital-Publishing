<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../repositories/SettingsRepository.php';
require_once __DIR__ . '/../services/SearchService.php';

require_admin_role();

$settingsRepository = new SettingsRepository();
$searchService = new SearchService();

$platformName = (string) ($settingsRepository->get('platform.name') ?? APP_DISPLAY_NAME);
$schoolName = (string) ($settingsRepository->get('school.name') ?? APP_SCHOOL_NAME);
$logo = (string) ($settingsRepository->get('school.logo') ?? '');
$favicon = (string) ($settingsRepository->get('platform.favicon') ?? '');

$query = get_string('q');
$year = get_int('year', 0);
$status = get_string('status');
$sort = get_string('sort', 'newest');
$page = get_int('page', 1);

$filters = [
    'q' => $query,
    'year' => $year > 0 ? $year : null,
    'status' => $status,
    'sort' => $sort,
    'page' => max(1, $page),
    'per_page' => 15,
];

$result = $searchService->searchAdmin($filters);

$items = $result['items'] ?? [];
$totalItems = (int) ($result['total_items'] ?? 0);
$totalPages = (int) ($result['total_pages'] ?? 1);
$currentPage = (int) ($result['current_page'] ?? 1);

$availableYears = $searchService->getAvailableYears(false);

$buildQueryUrl = static function (array $changes = []) use ($query, $year, $status, $sort): string {
    $params = [
        'q' => $query,
        'year' => $year > 0 ? $year : null,
        'status' => $status,
        'sort' => $sort,
    ];

    foreach ($changes as $key => $value) {
        $params[$key] = $value;
    }

    if (isset($params['page']) && (int) $params['page'] <= 1) {
        unset($params['page']);
    }

    $params = array_filter($params, static function ($value): bool {
        return $value !== null && $value !== '';
    });

    $queryString = http_build_query($params);

    return app_url('admin/publications.php' . ($queryString !== '' ? '?' . $queryString : ''));
};

$pageTitle = 'Quản lý ấn phẩm | ' . $platformName;

/*
 * ------------------------------------------------------------
 * Asset URLs
 *
 * CSS được đặt trong: admin/assets/css/
 * ------------------------------------------------------------
 */

$faviconUrl = $favicon !== '' ? app_url($favicon) : app_url('admin/assets/favicon.ico');
$logoUrl = $logo !== '' ? app_url($logo) : app_url('assets/logo.webp');
$stylesheetUrl = app_url('admin/assets/css/publications.css');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" href="<?= e($faviconUrl) ?>">
    <link rel="stylesheet" href="<?= e($stylesheetUrl) ?>">
</head>
<body class="admin-page">

<header class="admin-header">
    <div class="admin-header-inner">

        <div class="admin-brand">
            <a href="<?= e(app_url('admin/index.php')) ?>" class="admin-brand-link" aria-label="<?= e($schoolName) ?>">
                <img class="admin-brand-logo" src="<?= e($logoUrl) ?>" alt="<?= e($schoolName) ?>">
                <span class="admin-brand-text">
                    <span class="admin-brand-school"><?= e($schoolName) ?></span>
                    <span class="admin-brand-name"><?= e($platformName) ?></span>
                </span>
            </a>
        </div>

        <nav class="admin-nav" aria-label="Điều hướng quản trị">
            <a href="<?= e(app_url('admin/index.php')) ?>">Dashboard</a>
            <a href="<?= e(app_url('admin/publications.php')) ?>" aria-current="page">Ấn phẩm</a>
            <a href="<?= e(app_url('admin/users.php')) ?>">Người dùng</a>
            <a href="<?= e(app_url('admin/settings.php')) ?>">Cài đặt</a>
            <a href="<?= e(app_url()) ?>" target="_blank" rel="noopener">Xem thư viện</a>
        </nav>

        <div class="admin-account">
            <span class="admin-account-name"><?= e(auth_username() ?? 'admin') ?></span>
            <form method="post" action="<?= e(app_url('admin/logout.php')) ?>" class="admin-logout-form">
                <?= csrf_field() ?>
                <button type="submit" class="button button-secondary">Đăng xuất</button>
            </form>
        </div>

    </div>
</header>

<main class="admin-main">

    <header class="admin-page-header">
        <div>
            <p class="admin-eyebrow">Nội dung</p>
            <h1>Quản lý ấn phẩm</h1>
            <p class="admin-page-description">Tìm kiếm, kiểm tra trạng thái và quản lý các ấn phẩm trong hệ thống.</p>
        </div>

        <div class="admin-page-actions">
            <a href="<?= e(app_url('admin/publication-create.php')) ?>" class="button button-primary">+ Thêm ấn phẩm</a>
        </div>
    </header>

    <section class="admin-section">

        <form method="get" action="<?= e(app_url('admin/publications.php')) ?>" class="admin-filters">

            <div class="form-field">
                <label for="q">Tìm kiếm</label>
                <input id="q" name="q" type="search" value="<?= e($query) ?>" placeholder="Tên, slug, tác giả..." maxlength="200">
            </div>

            <div class="form-field">
                <label for="year">Năm</label>
                <select id="year" name="year">
                    <option value="">Tất cả các năm</option>
                    <?php foreach ($availableYears as $availableYear): ?>
                        <?php $availableYear = (int) $availableYear; ?>
                        <option value="<?= e((string) $availableYear) ?>" <?= $year === $availableYear ? 'selected' : '' ?>><?= e((string) $availableYear) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field">
                <label for="status">Trạng thái</label>
                <select id="status" name="status">
                    <option value="" <?= $status === '' ? 'selected' : '' ?>>Tất cả</option>
                    <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Bản nháp</option>
                    <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Đã xuất bản</option>
                    <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Lưu trữ</option>
                </select>
            </div>

            <div class="form-field">
                <label for="sort">Sắp xếp</label>
                <select id="sort" name="sort">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Mới cập nhật</option>
                    <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Cũ nhất</option>
                    <option value="title_asc" <?= $sort === 'title_asc' ? 'selected' : '' ?>>Tên A → Z</option>
                    <option value="title_desc" <?= $sort === 'title_desc' ? 'selected' : '' ?>>Tên Z → A</option>
                </select>
            </div>

            <div class="admin-filter-actions">
                <button type="submit" class="button button-primary">Lọc</button>
                <a href="<?= e(app_url('admin/publications.php')) ?>" class="button button-secondary">Xóa lọc</a>
            </div>

        </form>

    </section>

    <section class="admin-section">

        <div class="admin-section-header">
            <div>
                <h2>Danh sách ấn phẩm</h2>
                <p>Tìm thấy <strong><?= e((string) $totalItems) ?></strong> ấn phẩm.</p>
            </div>
        </div>

        <?php if ($items === []): ?>

            <div class="admin-empty-state">
                <h3>Không tìm thấy ấn phẩm</h3>
                <?php if ($query !== '' || $year > 0 || $status !== ''): ?>
                    <p>Không có ấn phẩm nào phù hợp với điều kiện lọc hiện tại.</p>
                    <a href="<?= e(app_url('admin/publications.php')) ?>" class="button button-secondary">Xóa bộ lọc</a>
                <?php else: ?>
                    <p>Hệ thống chưa có ấn phẩm nào.</p>
                    <a href="<?= e(app_url('admin/publication-create.php')) ?>" class="button button-primary">Tạo ấn phẩm đầu tiên</a>
                <?php endif; ?>
            </div>

        <?php else: ?>

            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Ấn phẩm</th>
                            <th scope="col">Năm</th>
                            <th scope="col">Trang</th>
                            <th scope="col">Trạng thái</th>
                            <th scope="col">Cập nhật</th>
                            <th scope="col">Thao tác</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($items as $publication): ?>
                        <?php
                        $id = (string) ($publication['id'] ?? '');
                        $title = (string) ($publication['title'] ?? '');
                        $slug = (string) ($publication['slug'] ?? '');
                        $yearValue = (string) ($publication['year'] ?? '');
                        $pageCount = (int) ($publication['page_count'] ?? 0);
                        $publicationStatus = (string) ($publication['status'] ?? '');
                        $updatedAt = (string) ($publication['updated_at'] ?? '');

                        $statusLabel = match ($publicationStatus) {
                            'draft' => 'Bản nháp',
                            'published' => 'Đã xuất bản',
                            'archived' => 'Lưu trữ',
                            default => 'Không xác định',
                        };

                        $editUrl = app_url('admin/publication-edit.php?id=' . rawurlencode($id));
                        $publicUrl = $slug !== '' ? publication_url($slug) : null;
                        ?>

                        <tr>
                            <td>
                                <div class="admin-publication-cell">
                                    <strong><?= e($title) ?></strong>
                                    <?php if ($slug !== ''): ?>
                                        <small><?= e($slug) ?></small>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td><?= e($yearValue) ?></td>

                            <td><?= e($pageCount > 0 ? (string) $pageCount : '—') ?></td>

                            <td>
                                <span class="status-badge status-<?= e($publicationStatus) ?>"><?= e($statusLabel) ?></span>
                            </td>

                            <td><?= e(format_datetime($updatedAt)) ?></td>

                            <td>
                                <div class="admin-table-actions">
                                    <a href="<?= e($editUrl) ?>">Chỉnh sửa</a>
                                    <?php if ($publicUrl !== null && $publicationStatus === 'published'): ?>
                                        <a href="<?= e($publicUrl) ?>" target="_blank" rel="noopener">Xem</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>

                <nav class="admin-pagination" aria-label="Phân trang ấn phẩm">

                    <?php if ($currentPage > 1): ?>
                        <a href="<?= e($buildQueryUrl(['page' => $currentPage - 1])) ?>">← Trước</a>
                    <?php endif; ?>

                    <span>Trang <?= e((string) $currentPage) ?> / <?= e((string) $totalPages) ?></span>

                    <?php if ($currentPage < $totalPages): ?>
                        <a href="<?= e($buildQueryUrl(['page' => $currentPage + 1])) ?>">Sau →</a>
                    <?php endif; ?>

                </nav>

            <?php endif; ?>

        <?php endif; ?>

    </section>

</main>

<footer class="admin-footer">
    <p><?= e($schoolName) ?> · <?= e($platformName) ?></p>
</footer>

</body>
</html>