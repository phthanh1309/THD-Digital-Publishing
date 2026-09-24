<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../repositories/PublicationRepository.php';
require_once __DIR__ . '/../repositories/SettingsRepository.php';
require_once __DIR__ . '/../services/PublicationService.php';

require_admin_role();

$publicationRepository = new PublicationRepository();
$publicationService = new PublicationService($publicationRepository);
$settingsRepository = new SettingsRepository();

$platformName = (string) ($settingsRepository->get('platform.name') ?? APP_DISPLAY_NAME);
$schoolName = (string) ($settingsRepository->get('school.name') ?? APP_SCHOOL_NAME);
$logo = (string) ($settingsRepository->get('school.logo') ?? '');
$favicon = (string) ($settingsRepository->get('platform.favicon') ?? '');

$publications = $publicationService->getAll();
$totalPublications = count($publications);

$draftCount = 0;
$publishedCount = 0;
$archivedCount = 0;

foreach ($publications as $publication) {
    $status = (string) ($publication['status'] ?? '');

    switch ($status) {
        case 'draft':
            $draftCount++;
            break;
        case 'published':
            $publishedCount++;
            break;
        case 'archived':
            $archivedCount++;
            break;
    }
}

/**
 * Recent publications are taken from actual XML records.
 * No fake view/download analytics are generated here.
 */
usort($publications, static function (array $first, array $second): int {
    $firstDate = (string) ($first['updated_at'] ?? $first['created_at'] ?? '');
    $secondDate = (string) ($second['updated_at'] ?? $second['created_at'] ?? '');

    return strcmp($secondDate, $firstDate);
});

$recentPublications = array_slice($publications, 0, 8);
$currentUser = auth_user();

$pageTitle = 'Dashboard quản trị | ' . $platformName;

$faviconUrl = $favicon !== '' ? app_url($favicon) : app_url('/assets/favicon.ico');
$logoUrl = $logo !== '' ? app_url($logo) : app_url('/assets/logo.webp');
$stylesheetUrl = app_url('/admin/assets/css/index.css');
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
            <a href="<?= e(app_url('admin/index.php')) ?>" aria-current="page">Dashboard</a>
            <a href="<?= e(app_url('admin/publications.php')) ?>">Ấn phẩm</a>
            <a href="<?= e(app_url('admin/users.php')) ?>">Người dùng</a>
            <a href="<?= e(app_url('admin/settings.php')) ?>">Cài đặt</a>
            <a href="<?= e(app_url()) ?>" target="_blank" rel="noopener">Xem thư viện</a>
        </nav>

        <div class="admin-account">
            <span class="admin-account-name"><?= e((string) ($currentUser['username'] ?? 'admin')) ?></span>
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
            <p class="admin-eyebrow">Quản trị hệ thống</p>
            <h1>Dashboard</h1>
            <p class="admin-page-description">Tổng quan tình trạng các ấn phẩm và dữ liệu hiện có trong thư viện.</p>
        </div>

        <div class="admin-page-actions">
            <a href="<?= e(app_url('admin/publication-create.php')) ?>" class="button button-primary">+ Thêm ấn phẩm</a>
        </div>
    </header>

    <section class="admin-stats" aria-label="Tổng quan ấn phẩm">

        <article class="admin-stat-card">
            <span class="admin-stat-label">Tổng ấn phẩm</span>
            <strong class="admin-stat-value"><?= e((string) $totalPublications) ?></strong>
            <span class="admin-stat-note">Tất cả trạng thái</span>
        </article>

        <article class="admin-stat-card">
            <span class="admin-stat-label">Đã xuất bản</span>
            <strong class="admin-stat-value"><?= e((string) $publishedCount) ?></strong>
            <span class="admin-stat-note">Hiển thị công khai</span>
        </article>

        <article class="admin-stat-card">
            <span class="admin-stat-label">Bản nháp</span>
            <strong class="admin-stat-value"><?= e((string) $draftCount) ?></strong>
            <span class="admin-stat-note">Chưa công khai</span>
        </article>

        <article class="admin-stat-card">
            <span class="admin-stat-label">Lưu trữ</span>
            <strong class="admin-stat-value"><?= e((string) $archivedCount) ?></strong>
            <span class="admin-stat-note">Không hiển thị công khai</span>
        </article>

    </section>

    <section class="admin-section">
        <div class="admin-section-header">
            <div>
                <h2>Ấn phẩm cập nhật gần đây</h2>
                <p>Danh sách được lấy trực tiếp từ dữ liệu ấn phẩm hiện tại.</p>
            </div>
            <a href="<?= e(app_url('admin/publications.php')) ?>">Quản lý tất cả →</a>
        </div>

        <?php if ($recentPublications === []): ?>

            <div class="admin-empty-state">
                <h3>Chưa có ấn phẩm</h3>
                <p>Hãy tạo ấn phẩm đầu tiên để bắt đầu xây dựng thư viện.</p>
                <a href="<?= e(app_url('admin/publication-create.php')) ?>" class="button button-primary">Tạo ấn phẩm</a>
            </div>

        <?php else: ?>

            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Ấn phẩm</th>
                            <th scope="col">Năm</th>
                            <th scope="col">Trạng thái</th>
                            <th scope="col">Cập nhật</th>
                            <th scope="col">Thao tác</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($recentPublications as $publication): ?>
                        <?php
                        $publicationId = (string) ($publication['id'] ?? '');
                        $publicationTitle = (string) ($publication['title'] ?? '');
                        $publicationSlug = (string) ($publication['slug'] ?? '');
                        $publicationYear = (string) ($publication['year'] ?? '');
                        $publicationStatus = (string) ($publication['status'] ?? '');
                        $updatedAt = (string) ($publication['updated_at'] ?? '');

                        $statusLabel = match ($publicationStatus) {
                            'published' => 'Đã xuất bản',
                            'draft' => 'Bản nháp',
                            'archived' => 'Lưu trữ',
                            default => 'Không xác định',
                        };

                        $editUrl = app_url('admin/publication-edit.php?id=' . rawurlencode($publicationId));
                        ?>

                        <tr>
                            <td>
                                <div class="admin-publication-cell">
                                    <strong><?= e($publicationTitle) ?></strong>
                                    <?php if ($publicationSlug !== ''): ?>
                                        <small><?= e($publicationSlug) ?></small>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td><?= e($publicationYear) ?></td>

                            <td>
                                <span class="status-badge status-<?= e($publicationStatus) ?>"><?= e($statusLabel) ?></span>
                            </td>

                            <td><?= e(format_datetime($updatedAt)) ?></td>

                            <td><a href="<?= e($editUrl) ?>">Chỉnh sửa</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>
    </section>

    <section class="admin-section">
        <div class="admin-section-header">
            <div>
                <h2>Thao tác nhanh</h2>
                <p>Các chức năng quản trị thường dùng.</p>
            </div>
        </div>

        <div class="admin-quick-actions">

            <a href="<?= e(app_url('admin/publication-create.php')) ?>" class="admin-quick-action">
                <strong>Tạo ấn phẩm</strong>
                <span>Nhập metadata và tải PDF.</span>
            </a>

            <a href="<?= e(app_url('admin/publications.php')) ?>" class="admin-quick-action">
                <strong>Quản lý ấn phẩm</strong>
                <span>Chỉnh sửa, xuất bản hoặc lưu trữ.</span>
            </a>

            <a href="<?= e(app_url('admin/settings.php')) ?>" class="admin-quick-action">
                <strong>Cài đặt</strong>
                <span>Quản lý branding và thông tin nền tảng.</span>
            </a>

        </div>
    </section>

</main>

<footer class="admin-footer">
    <p><?= e($schoolName) ?> · <?= e($platformName) ?></p>
</footer>

</body>
</html>