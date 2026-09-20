<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../repositories/SettingsRepository.php';
require_once __DIR__ . '/../services/PublicationService.php';
require_once __DIR__ . '/../services/UploadService.php';
require_once __DIR__ . '/../services/ReaderService.php';

require_admin_role();

$settingsRepository = new SettingsRepository();
$publicationService = new PublicationService();
$uploadService = new UploadService();
$readerService = new ReaderService();

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

$errors = [];
$successMessage = '';

if (get_string('created') === '1') {
    $successMessage = 'Đã tạo ấn phẩm thành công. Bạn có thể tiếp tục kiểm tra và xuất bản.';
}

$settings = $publication['settings'] ?? [];

$old = [
    'title' => (string) ($publication['title'] ?? ''),
    'subtitle' => (string) ($publication['subtitle'] ?? ''),
    'slug' => (string) ($publication['slug'] ?? ''),
    'year' => (string) ($publication['year'] ?? ''),
    'description' => (string) ($publication['description'] ?? ''),
    'author' => (string) ($publication['author'] ?? ''),
    'editor' => (string) ($publication['editor'] ?? ''),
    'language' => (string) ($publication['language'] ?? 'vi'),
    'status' => (string) ($publication['status'] ?? DEFAULT_PUBLICATION_STATUS),
    'allow_download' => to_bool($settings['allow_download'] ?? false),
    'allow_print' => to_bool($settings['allow_print'] ?? false),
    'allow_share' => to_bool($settings['allow_share'] ?? true),
];

/**
 * Lấy tên file vật lý từ relative path / URL.
 *
 * Ví dụ:
 * storage/publications/pub_xxx/source_abc.pdf
 * => source_abc.pdf
 */
function publicationStoredName(string $path): string
{
    if ($path === '') {
        return '';
    }

    $parsedPath = parse_url($path, PHP_URL_PATH);

    if (!is_string($parsedPath) || $parsedPath === '') {
        $parsedPath = $path;
    }

    $parsedPath = rawurldecode($parsedPath);

    return basename($parsedPath);
}

/**
 * Xóa file cũ an toàn.
 *
 * UploadService::deleteFile() yêu cầu:
 *   publicationId
 *   storedName
 */
function deletePublicationStoredFile(UploadService $uploadService, string $publicationId, string $relativePath): void
{
    if ($relativePath === '') {
        return;
    }

    $storedName = publicationStoredName($relativePath);

    if ($storedName === '' || $storedName === '.' || $storedName === '..') {
        return;
    }

    $uploadService->deleteFile($publicationId, $storedName);
}

if (is_post()) {

    try {

        require_csrf_token();

        $action = post_string('action', 'save');

        /*
         * --------------------------------------------------------
         * STATUS ACTIONS
         * --------------------------------------------------------
         */

        if ($action === 'publish') {

            $publication = $publicationService->publish($publicationId);
            $successMessage = 'Ấn phẩm đã được xuất bản.';

        } elseif ($action === 'unpublish') {

            $publication = $publicationService->unpublish($publicationId);
            $successMessage = 'Ấn phẩm đã được chuyển về bản nháp.';

        } elseif ($action === 'archive') {

            $publication = $publicationService->archive($publicationId);
            $successMessage = 'Ấn phẩm đã được lưu trữ.';

        } elseif ($action === 'restore') {

            $publication = $publicationService->restore($publicationId);
            $successMessage = 'Ấn phẩm đã được khôi phục về bản nháp.';

        /*
         * --------------------------------------------------------
         * SAVE
         * --------------------------------------------------------
         */

        } elseif ($action === 'save') {

            /*
             * Lấy publication mới nhất trước khi xử lý.
             */
            $currentPublication = $publicationService->findById($publicationId);

            if ($currentPublication === null) {
                abort_not_found();
            }

            $oldPdf = (string) ($currentPublication['pdf'] ?? $currentPublication['source_pdf'] ?? '');
            $oldCover = (string) ($currentPublication['cover'] ?? '');

            /*
             * Đọc metadata từ form.
             */
            $formData = [
                'title' => post_string('title'),
                'subtitle' => post_string('subtitle'),
                'slug' => post_string('slug'),
                'year' => post_string('year'),
                'description' => post_string('description'),
                'author' => post_string('author'),
                'editor' => post_string('editor'),
                'language' => post_string('language'),
                'status' => post_string('status', DEFAULT_PUBLICATION_STATUS),
                'allow_download' => to_bool($_POST['allow_download'] ?? false),
                'allow_print' => to_bool($_POST['allow_print'] ?? false),
                'allow_share' => to_bool($_POST['allow_share'] ?? false),
            ];

            /*
             * ----------------------------------------------------
             * XÁC ĐỊNH FILE MỚI
             * ----------------------------------------------------
             */

            $hasNewPdf = (
                isset($_FILES['pdf'])
                && is_array($_FILES['pdf'])
                && (int) ($_FILES['pdf']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
            );

            $hasNewCover = (
                isset($_FILES['cover'])
                && is_array($_FILES['cover'])
                && (int) ($_FILES['cover']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
            );

            /*
             * Nếu đang published nhưng không có PDF cũ
             * và cũng không upload PDF mới thì không thể publish.
             *
             * Không gọi update() trong trạng thái invalid.
             */
            if ($formData['status'] === 'published' && $oldPdf === '' && !$hasNewPdf) {
                $errors['pdf'] = 'Ấn phẩm đã xuất bản phải có file PDF.';
            }

            if ($errors !== []) {

                /*
                 * Giữ dữ liệu form để người dùng sửa.
                 */
                $old = [
                    'title' => $formData['title'],
                    'subtitle' => $formData['subtitle'],
                    'slug' => $formData['slug'],
                    'year' => $formData['year'],
                    'description' => $formData['description'],
                    'author' => $formData['author'],
                    'editor' => $formData['editor'],
                    'language' => $formData['language'],
                    'status' => $formData['status'],
                    'allow_download' => $formData['allow_download'],
                    'allow_print' => $formData['allow_print'],
                    'allow_share' => $formData['allow_share'],
                ];

            } else {

                /*
                 * ------------------------------------------------
                 * UPLOAD FILE MỚI TRƯỚC
                 * ------------------------------------------------
                 *
                 * Cực kỳ quan trọng:
                 *
                 * Không xóa file cũ ở đây.
                 * Không update XML ở đây.
                 *
                 * Nếu upload thất bại thì publication cũ
                 * vẫn nguyên vẹn.
                 */

                $newPdf = null;
                $newCover = null;

                try {

                    if ($hasNewPdf) {
                        $newPdf = $uploadService->storePdf($publicationId, $_FILES['pdf']);
                    }

                    if ($hasNewCover) {
                        $newCover = $uploadService->storeCover($publicationId, $_FILES['cover']);
                    }

                    /*
                     * ------------------------------------------------
                     * CHUẨN BỊ PAYLOAD METADATA
                     * ------------------------------------------------
                     */

                    $payload = [
                        'title' => $formData['title'],
                        'subtitle' => $formData['subtitle'],
                        'slug' => $formData['slug'],
                        'year' => $formData['year'],
                        'description' => $formData['description'],
                        'author' => $formData['author'],
                        'editor' => $formData['editor'],
                        'language' => $formData['language'],
                        'status' => $formData['status'],
                        'settings' => [
                            'allow_download' => $formData['allow_download'],
                            'allow_print' => $formData['allow_print'],
                            'allow_share' => $formData['allow_share'],
                        ],
                    ];

                    /*
                     * ------------------------------------------------
                     * FILE METADATA
                     * ------------------------------------------------
                     */

                    if ($newPdf !== null) {

                        $newPdfPath = (string) ($newPdf['relative_path'] ?? '');

                        if ($newPdfPath === '') {
                            throw new RuntimeException('Upload PDF thành công nhưng không nhận được đường dẫn file.');
                        }

                        $payload['source_pdf'] = $newPdfPath;
                        $payload['pdf'] = $newPdfPath;
                    }

                    if ($newCover !== null) {

                        $newCoverPath = (string) ($newCover['relative_path'] ?? '');

                        if ($newCoverPath === '') {
                            throw new RuntimeException('Upload ảnh bìa thành công nhưng không nhận được đường dẫn file.');
                        }

                        $payload['cover'] = $newCoverPath;
                    }

                    /*
                     * ------------------------------------------------
                     * UPDATE PUBLICATION
                     * ------------------------------------------------
                     *
                     * Lúc này:
                     *
                     * - file mới đã tồn tại
                     * - file cũ vẫn còn
                     *
                     * Nếu update thất bại, rollback file mới.
                     */

                    try {

                        $updatedPublication = $publicationService->update($publicationId, $payload);

                    } catch (Throwable $updateException) {

                        /*
                         * Rollback PDF mới.
                         */
                        if ($newPdf !== null) {
                            deletePublicationStoredFile($uploadService, $publicationId, (string) ($newPdf['relative_path'] ?? ''));
                        }

                        /*
                         * Rollback cover mới.
                         */
                        if ($newCover !== null) {
                            deletePublicationStoredFile($uploadService, $publicationId, (string) ($newCover['relative_path'] ?? ''));
                        }

                        throw $updateException;
                    }

                    /*
                     * ------------------------------------------------
                     * XÓA FILE CŨ SAU KHI UPDATE THÀNH CÔNG
                     * ------------------------------------------------
                     */

                    if ($newPdf !== null) {

                        $newPdfPath = (string) ($newPdf['relative_path'] ?? '');

                        if ($oldPdf !== '' && $oldPdf !== $newPdfPath) {
                            deletePublicationStoredFile($uploadService, $publicationId, $oldPdf);
                        }
                    }

                    if ($newCover !== null) {

                        $newCoverPath = (string) ($newCover['relative_path'] ?? '');

                        if ($oldCover !== '' && $oldCover !== $newCoverPath) {
                            deletePublicationStoredFile($uploadService, $publicationId, $oldCover);
                        }
                    }

                    /*
                     * ------------------------------------------------
                     * REFRESH
                     * ------------------------------------------------
                     */

                    $publication = $publicationService->findById($publicationId);

                    if ($publication === null) {
                        throw new RuntimeException('Không thể đọc lại ấn phẩm sau khi lưu.');
                    }

                    $settings = $publication['settings'] ?? [];

                    $old = [
                        'title' => (string) ($publication['title'] ?? ''),
                        'subtitle' => (string) ($publication['subtitle'] ?? ''),
                        'slug' => (string) ($publication['slug'] ?? ''),
                        'year' => (string) ($publication['year'] ?? ''),
                        'description' => (string) ($publication['description'] ?? ''),
                        'author' => (string) ($publication['author'] ?? ''),
                        'editor' => (string) ($publication['editor'] ?? ''),
                        'language' => (string) ($publication['language'] ?? 'vi'),
                        'status' => (string) ($publication['status'] ?? DEFAULT_PUBLICATION_STATUS),
                        'allow_download' => to_bool($settings['allow_download'] ?? false),
                        'allow_print' => to_bool($settings['allow_print'] ?? false),
                        'allow_share' => to_bool($settings['allow_share'] ?? true),
                    ];

                    $successMessage = 'Đã lưu thay đổi ấn phẩm.';

                } catch (Throwable $uploadException) {

                    /*
                     * Nếu upload cover/PDF đã thành công nhưng
                     * một file upload sau đó bị lỗi, rollback tất cả
                     * file mới đã tạo.
                     */

                    if ($newPdf !== null) {
                        try {
                            deletePublicationStoredFile($uploadService, $publicationId, (string) ($newPdf['relative_path'] ?? ''));
                        } catch (Throwable) {
                            // Không che mất lỗi gốc.
                        }
                    }

                    if ($newCover !== null) {
                        try {
                            deletePublicationStoredFile($uploadService, $publicationId, (string) ($newCover['relative_path'] ?? ''));
                        } catch (Throwable) {
                            // Không che mất lỗi gốc.
                        }
                    }

                    throw $uploadException;
                }
            }

        } else {

            throw new RuntimeException('Thao tác quản trị không hợp lệ.', 422);
        }

    } catch (ValidationException $exception) {

        $errors = $exception->errors();

    } catch (RuntimeException $exception) {

        if ($exception->getCode() === 422) {
            $errors['form'] = $exception->getMessage();
        } else {
            throw $exception;
        }

    } catch (Throwable $exception) {

        /*
         * Các lỗi upload PHP/runtime khác.
         */
        $errors['form'] = $exception->getMessage();
    }
}

/*
 * ------------------------------------------------------------
 * REFRESH DATA AFTER ACTION
 * ------------------------------------------------------------
 */

$publication = $publicationService->findById($publicationId);

if ($publication === null) {
    abort_not_found();
}

$settings = $publication['settings'] ?? [];

/*
 * Nếu POST save có lỗi validation,
 * giữ dữ liệu người dùng nhập.
 *
 * Nếu không có lỗi thì lấy dữ liệu từ publication.
 */
if ($errors === []) {

    $old['title'] = (string) ($publication['title'] ?? $old['title']);
    $old['subtitle'] = (string) ($publication['subtitle'] ?? $old['subtitle']);
    $old['slug'] = (string) ($publication['slug'] ?? $old['slug']);
    $old['year'] = (string) ($publication['year'] ?? $old['year']);
    $old['description'] = (string) ($publication['description'] ?? $old['description']);
    $old['author'] = (string) ($publication['author'] ?? $old['author']);
    $old['editor'] = (string) ($publication['editor'] ?? $old['editor']);
    $old['language'] = (string) ($publication['language'] ?? $old['language']);
    $old['status'] = (string) ($publication['status'] ?? $old['status']);

    $old['allow_download'] = to_bool($settings['allow_download'] ?? $old['allow_download']);
    $old['allow_print'] = to_bool($settings['allow_print'] ?? $old['allow_print']);
    $old['allow_share'] = to_bool($settings['allow_share'] ?? $old['allow_share']);
}

$publicationStatus = (string) ($publication['status'] ?? $old['status']);
$publicationSlug = (string) ($publication['slug'] ?? '');
$publicationTitle = (string) ($publication['title'] ?? '');
$publicationPdf = (string) ($publication['pdf'] ?? $publication['source_pdf'] ?? '');
$publicationCover = (string) ($publication['cover'] ?? '');
$pageCount = (int) ($publication['page_count'] ?? 0);
$publishedAt = (string) ($publication['published_at'] ?? '');

$readerSource = $readerService->detectReaderSource($publication);
$readerAvailable = $readerSource !== 'unavailable';

$publicUrl = $publicationSlug !== '' ? publication_url($publicationSlug) : '';
$readerUrl = $publicationSlug !== '' ? reader_url($publicationSlug) : '';

$pageTitle = 'Chỉnh sửa: ' . $publicationTitle . ' | ' . $platformName;

$editActionUrl = app_url('admin/publication-edit.php?id=' . rawurlencode($publicationId));
$deleteUrl = app_url('admin/publication-delete.php?id=' . rawurlencode($publicationId));
$maxPdfSizeMb = (string) (MAX_PDF_SIZE / 1024 / 1024);

/*
 * ------------------------------------------------------------
 * ASSET URLS
 *
 * CSS/JS được đặt trong: admin/assets/
 * ------------------------------------------------------------
 */

$faviconUrl = $favicon !== '' ? app_url($favicon) : app_url('assets/favicon.ico');
$logoUrl = $logo !== '' ? app_url($logo) : app_url('assets/logo.webp');
$stylesheetUrl = app_url('admin/assets/css/publication-edit.css');
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
            <h1><?= e($publicationTitle) ?></h1>
            <p class="admin-page-description">Chỉnh sửa metadata, tệp nguồn và trạng thái xuất bản.</p>
        </div>

        <div class="admin-page-actions">

            <?php if ($publicUrl !== ''): ?>
                <a href="<?= e($publicUrl) ?>" target="_blank" rel="noopener" class="button button-secondary">Xem trang ấn phẩm</a>
            <?php endif; ?>

            <?php if ($readerAvailable && $readerUrl !== ''): ?>
                <a href="<?= e($readerUrl) ?>" target="_blank" rel="noopener" class="button button-secondary">Mở reader</a>
            <?php endif; ?>

        </div>

    </header>

    <?php if ($successMessage !== ''): ?>
        <div class="admin-alert admin-alert-success" role="status"><?= e($successMessage) ?></div>
    <?php endif; ?>

    <?php if (isset($errors['form'])): ?>
        <div class="admin-alert admin-alert-error" role="alert"><?= e((string) $errors['form']) ?></div>
    <?php endif; ?>

    <?php if ($errors !== [] && !isset($errors['form'])): ?>

        <div class="admin-alert admin-alert-error" role="alert">

            <strong>Không thể lưu dữ liệu.</strong>

            <ul>
                <?php foreach ($errors as $field => $messages): ?>
                    <?php $messages = is_array($messages) ? $messages : [$messages]; ?>
                    <?php foreach ($messages as $message): ?>
                        <li><?= e((string) $message) ?></li>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </ul>

        </div>

    <?php endif; ?>

    <section class="admin-section">

        <div class="admin-section-header">
            <div>
                <h2>Trạng thái xuất bản</h2>
                <p>Trạng thái hiện tại: <strong><?= e(match ($publicationStatus) { 'published' => 'Đã xuất bản', 'archived' => 'Lưu trữ', default => 'Bản nháp' }) ?></strong></p>
            </div>
        </div>

        <div class="admin-status-actions">

            <?php if ($publicationStatus === 'draft'): ?>

                <form method="post" action="<?= e($editActionUrl) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="publish">
                    <button type="submit" class="button button-primary">Xuất bản</button>
                </form>

            <?php elseif ($publicationStatus === 'published'): ?>

                <form method="post" action="<?= e($editActionUrl) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="unpublish">
                    <button type="submit" class="button button-secondary">Gỡ xuất bản</button>
                </form>

                <form method="post" action="<?= e($editActionUrl) ?>" data-confirm="Bạn có chắc muốn lưu trữ ấn phẩm này?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="archive">
                    <button type="submit" class="button button-secondary">Lưu trữ</button>
                </form>

            <?php elseif ($publicationStatus === 'archived'): ?>

                <form method="post" action="<?= e($editActionUrl) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="restore">
                    <button type="submit" class="button button-primary">Khôi phục</button>
                </form>

            <?php endif; ?>

        </div>

    </section>

    <form method="post" action="<?= e($editActionUrl) ?>" enctype="multipart/form-data" class="admin-form">

        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">

        <section class="admin-section">

            <div class="admin-section-header">
                <div>
                    <h2>Metadata</h2>
                    <p>Thông tin hiển thị trên thư viện công khai.</p>
                </div>
            </div>

            <div class="admin-form-grid">

                <div class="form-field form-field-full">
                    <label for="title">Tên ấn phẩm <span aria-hidden="true">*</span></label>
                    <input id="title" name="title" type="text" value="<?= e($old['title']) ?>" maxlength="255" required>
                </div>

                <div class="form-field form-field-full">
                    <label for="subtitle">Phụ đề</label>
                    <input id="subtitle" name="subtitle" type="text" value="<?= e($old['subtitle']) ?>" maxlength="255">
                </div>

                <div class="form-field">
                    <label for="slug">Slug</label>
                    <input id="slug" name="slug" type="text" value="<?= e($old['slug']) ?>" maxlength="255" pattern="[a-z0-9]+(?:-[a-z0-9]+)*">
                </div>

                <div class="form-field">
                    <label for="year">Năm <span aria-hidden="true">*</span></label>
                    <input id="year" name="year" type="number" value="<?= e($old['year']) ?>" min="1900" max="2200" required>
                </div>

                <div class="form-field">
                    <label for="author">Tác giả</label>
                    <input id="author" name="author" type="text" value="<?= e($old['author']) ?>" maxlength="255">
                </div>

                <div class="form-field">
                    <label for="editor">Biên tập</label>
                    <input id="editor" name="editor" type="text" value="<?= e($old['editor']) ?>" maxlength="255">
                </div>

                <div class="form-field">
                    <label for="language">Ngôn ngữ</label>
                    <input id="language" name="language" type="text" value="<?= e($old['language']) ?>" maxlength="20">
                </div>

                <div class="form-field">
                    <label for="status">Trạng thái</label>
                    <select id="status" name="status">
                        <option value="draft" <?= $old['status'] === 'draft' ? 'selected' : '' ?>>Bản nháp</option>
                        <option value="published" <?= $old['status'] === 'published' ? 'selected' : '' ?>>Đã xuất bản</option>
                        <option value="archived" <?= $old['status'] === 'archived' ? 'selected' : '' ?>>Lưu trữ</option>
                    </select>
                </div>

                <div class="form-field form-field-full">
                    <label for="description">Mô tả</label>
                    <textarea id="description" name="description" rows="8" maxlength="10000"><?= e($old['description']) ?></textarea>
                </div>

            </div>

        </section>

        <section class="admin-section">

            <div class="admin-section-header">
                <div>
                    <h2>Tệp hiện tại</h2>
                    <p>Thay tệp chỉ khi cần thiết.</p>
                </div>
            </div>

            <div class="admin-file-summary">

                <div>
                    <strong>PDF</strong>
                    <span><?= $publicationPdf !== '' ? 'Đã có tệp PDF' : 'Chưa có PDF' ?></span>
                </div>

                <div>
                    <strong>Cover</strong>
                    <span><?= $publicationCover !== '' ? 'Đã có ảnh bìa' : 'Chưa có ảnh bìa' ?></span>
                </div>

                <div>
                    <strong>Số trang</strong>
                    <span><?= e($pageCount > 0 ? (string) $pageCount : 'Chưa xác định') ?></span>
                </div>

            </div>

            <div class="admin-form-grid">

                <div class="form-field form-field-full">
                    <label for="pdf">Thay PDF</label>
                    <input id="pdf" name="pdf" type="file" accept="application/pdf,.pdf">
                    <small>Để trống nếu muốn giữ PDF hiện tại. Tối đa <?= e($maxPdfSizeMb) ?> MB.</small>
                </div>

                <div class="form-field form-field-full">
                    <label for="cover">Thay ảnh bìa</label>
                    <input id="cover" name="cover" type="file" accept="image/jpeg,image/png,image/webp">
                    <small>Để trống nếu muốn giữ ảnh bìa hiện tại.</small>
                </div>

            </div>

        </section>

        <section class="admin-section">

            <div class="admin-section-header">
                <div>
                    <h2>Quyền đọc</h2>
                    <p>Server sẽ kiểm tra lại các quyền này tại endpoint tương ứng.</p>
                </div>
            </div>

            <div class="admin-permission-list">

                <label class="admin-checkbox">
                    <input type="checkbox" name="allow_download" value="1" <?= $old['allow_download'] ? 'checked' : '' ?>>
                    <span>Cho phép tải PDF</span>
                </label>

                <label class="admin-checkbox">
                    <input type="checkbox" name="allow_print" value="1" <?= $old['allow_print'] ? 'checked' : '' ?>>
                    <span>Cho phép in</span>
                </label>

                <label class="admin-checkbox">
                    <input type="checkbox" name="allow_share" value="1" <?= $old['allow_share'] ? 'checked' : '' ?>>
                    <span>Cho phép chia sẻ liên kết</span>
                </label>

            </div>

        </section>

        <div class="admin-form-actions">
            <a href="<?= e(app_url('admin/publications.php')) ?>" class="button button-secondary">Hủy</a>
            <button type="submit" class="button button-primary">Lưu thay đổi</button>
        </div>

    </form>

    <section class="admin-section admin-danger-zone">

        <div class="admin-section-header">
            <div>
                <h2>Vùng nguy hiểm</h2>
                <p>Xóa ấn phẩm sẽ là thao tác riêng và phải có bước xác nhận.</p>
            </div>
        </div>

        <a href="<?= e($deleteUrl) ?>" class="button button-danger">Xóa ấn phẩm</a>

    </section>

</main>

<footer class="admin-footer">
    <p><?= e($schoolName) ?> · <?= e($platformName) ?></p>
</footer>

<script src="<?= e($scriptUrl) ?>" defer></script>

</body>
</html>