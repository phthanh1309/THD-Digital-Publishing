<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../repositories/SettingsRepository.php';
require_once __DIR__ . '/../services/PublicationService.php';
require_once __DIR__ . '/../services/UploadService.php';

require_admin_role();

$settingsRepository = new SettingsRepository();
$publicationService = new PublicationService();
$uploadService = new UploadService();

$platformName = (string) ($settingsRepository->get('platform.name') ?? APP_DISPLAY_NAME);
$schoolName = (string) ($settingsRepository->get('school.name') ?? APP_SCHOOL_NAME);
$logo = (string) ($settingsRepository->get('school.logo') ?? '');
$favicon = (string) ($settingsRepository->get('platform.favicon') ?? '');

$publicationId = get_string('id');

if ($publicationId === '') {
    abort_not_found();
}

$publication = $publicationService->findById($publicationId);

if ($publication === null) {
    abort_not_found();
}

$error = '';

if (is_post()) {

    try {

        require_csrf_token();

        $action = post_string('action');

        if ($action !== 'delete') {
            throw new RuntimeException('Thao tác xóa không hợp lệ.', 422);
        }

        /*
         * Delete physical files first.
         *
         * If file deletion fails, the XML record is kept
         * so that we do not create a successful-looking
         * deletion while files are still attached to the
         * publication.
         */
        $uploadService->deletePublicationFiles($publicationId);

        /*
         * Delete the publication metadata after the
         * publication directory has been handled.
         */
        $publicationService->delete($publicationId);

        redirect(app_url('admin/publications.php?deleted=1'));

    } catch (RuntimeException $exception) {

        if ($exception->getCode() === 422) {
            $error = $exception->getMessage();
        } else {
            throw $exception;
        }
    }
}

$title = (string) ($publication['title'] ?? '');
$slug = (string) ($publication['slug'] ?? '');
$status = (string) ($publication['status'] ?? '');
$year = (string) ($publication['year'] ?? '');
$pageCount = (int) ($publication['page_count'] ?? 0);

$statusLabel = match ($status) {
    'published' => 'Đã xuất bản',
    'archived' => 'Lưu trữ',
    default => 'Bản nháp',
};

$pageTitle = 'Xóa ấn phẩm | ' . $platformName;

$deleteActionUrl = app_url('admin/publication-delete.php?id=' . rawurlencode($publicationId));
$editUrl = app_url('admin/publication-edit.php?id=' . rawurlencode($publicationId));

/*
 * ------------------------------------------------------------
 * Asset URLs
 *
 * CSS/JS được đặt trong: admin/assets/
 * ------------------------------------------------------------
 */

$faviconUrl = $favicon !== '' ? app_url($favicon) : app_url('assets/favicon.ico');
$logoUrl = $logo !== '' ? app_url($logo) : app_url('assets/logo.webp');
$stylesheetUrl = app_url('admin/assets/css/publication-delete.css');
$scriptUrl = app_url('assets/js/admin.js');
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
            <p class="admin-eyebrow">Ấn phẩm</p>
            <h1>Xóa ấn phẩm</h1>
            <p class="admin-page-description">Thao tác này sẽ xóa metadata và các tệp vật lý thuộc về ấn phẩm.</p>
        </div>
    </header>

    <?php if ($error !== ''): ?>
        <div class="admin-alert admin-alert-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <section class="admin-section admin-danger-zone">

        <div class="admin-section-header">
            <div>
                <h2>Xác nhận xóa</h2>
                <p>Hãy kiểm tra lại thông tin trước khi thực hiện thao tác.</p>
            </div>
        </div>

        <dl class="admin-delete-summary">

            <div>
                <dt>Tên ấn phẩm</dt>
                <dd><?= e($title) ?></dd>
            </div>

            <div>
                <dt>Slug</dt>
                <dd><?= e($slug !== '' ? $slug : '—') ?></dd>
            </div>

            <div>
                <dt>Năm</dt>
                <dd><?= e($year !== '' ? $year : '—') ?></dd>
            </div>

            <div>
                <dt>Trạng thái</dt>
                <dd><?= e($statusLabel) ?></dd>
            </div>

            <div>
                <dt>Số trang</dt>
                <dd><?= e($pageCount > 0 ? (string) $pageCount : 'Chưa xác định') ?></dd>
            </div>

        </dl>

        <div class="admin-alert admin-alert-error" role="alert">
            <strong>Cảnh báo:</strong>
            Sau khi xóa, ấn phẩm và các tệp thuộc về ấn phẩm sẽ không còn được quản lý trong hệ thống.
        </div>

        <div class="admin-form-actions">

            <a href="<?= e($editUrl) ?>" class="button button-secondary">Hủy</a>

            <form method="post" action="<?= e($deleteActionUrl) ?>" data-confirm="Bạn có chắc chắn muốn xóa ấn phẩm này? Thao tác này không thể hoàn tác.">

                <?= csrf_field() ?>

                <input type="hidden" name="action" value="delete">

                <button type="submit" class="button button-danger">Xác nhận xóa</button>

            </form>

        </div>

    </section>

</main>

<footer class="admin-footer">
    <p><?= e($schoolName) ?> · <?= e($platformName) ?></p>
</footer>

<script src="<?= e($scriptUrl) ?>" defer></script>

</body>
</html>