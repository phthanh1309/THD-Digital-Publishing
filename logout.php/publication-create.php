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

$errors = [];

$old = [
    'title' => '',
    'subtitle' => '',
    'slug' => '',
    'year' => (string) date('Y'),
    'description' => '',
    'author' => '',
    'editor' => '',
    'language' => 'vi',
    'status' => DEFAULT_PUBLICATION_STATUS,
    'allow_download' => false,
    'allow_print' => false,
    'allow_share' => true,
];

if (is_post()) {

    $old = [
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

    try {

        require_csrf_token();

        $payload = [
            'title' => $old['title'],
            'subtitle' => $old['subtitle'],
            'slug' => $old['slug'],
            'year' => $old['year'],
            'description' => $old['description'],
            'author' => $old['author'],
            'editor' => $old['editor'],
            'language' => $old['language'],
            'status' => $old['status'],
            'settings' => [
                'allow_download' => $old['allow_download'],
                'allow_print' => $old['allow_print'],
                'allow_share' => $old['allow_share'],
            ],
        ];

        /*
         * Create metadata first.
         * UploadService will be called only after the
         * publication has received a stable ID.
         */
        $publication = $publicationService->create($payload);

        $publicationId = (string) ($publication['id'] ?? '');

        if ($publicationId === '') {
            throw new RuntimeException('Không tạo được mã ấn phẩm.');
        }

        $uploadedFiles = [];

        try {

            if (
                isset($_FILES['pdf'])
                && is_array($_FILES['pdf'])
                && (int) ($_FILES['pdf']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
            ) {
                $uploadedFiles['pdf'] = $uploadService->storePdf($publicationId, $_FILES['pdf']);
            }

            if (
                isset($_FILES['cover'])
                && is_array($_FILES['cover'])
                && (int) ($_FILES['cover']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
            ) {
                $uploadedFiles['cover'] = $uploadService->storeCover($publicationId, $_FILES['cover']);
            }

        } catch (Throwable $uploadException) {

            /*
             * Metadata was created but the upload failed.
             * Remove the newly-created metadata record
             * and any files already stored for this record.
             */
            $uploadService->deletePublicationFiles($publicationId);
            $publicationService->delete($publicationId);

            throw $uploadException;
        }

        /*
         * Update the publication with the stored file information.
         */
        $updateData = [];

        if (isset($uploadedFiles['pdf'])) {
            $pdf = $uploadedFiles['pdf'];

            $updateData['source_pdf'] = (string) ($pdf['relative_path'] ?? '');
            $updateData['pdf'] = (string) ($pdf['relative_path'] ?? '');
        }

        if (isset($uploadedFiles['cover'])) {
            $cover = $uploadedFiles['cover'];

            $updateData['cover'] = (string) ($cover['relative_path'] ?? '');
        }

        /*
         * A publication without a PDF cannot be published.
         * The service already enforces this rule, but we
         * explicitly keep newly-created records as draft
         * unless the uploaded PDF exists.
         */
        if ($old['status'] === 'published' && !isset($uploadedFiles['pdf'])) {
            $updateData['status'] = 'draft';
        }

        if ($updateData !== []) {
            $publication = $publicationService->update($publicationId, $updateData);
        } else {
            $publication = $publicationService->findById($publicationId);
        }

        if ($publication === null) {
            throw new RuntimeException('Không thể đọc lại ấn phẩm vừa tạo.');
        }

        redirect(app_url('admin/publication-edit.php?id=' . rawurlencode($publicationId) . '&created=1'));

    } catch (ValidationException $exception) {

        $errors = $exception->errors();

    } catch (RuntimeException $exception) {

        if ($exception->getCode() === 422) {
            $errors['form'] = 'Dữ liệu gửi lên không hợp lệ. Vui lòng kiểm tra lại biểu mẫu.';
        } else {
            throw $exception;
        }

    } catch (Throwable $exception) {

        throw $exception;
    }
}

$pageTitle = 'Tạo ấn phẩm | ' . $platformName;

/*
 * ------------------------------------------------------------
 * Asset URLs
 *
 * CSS được đặt trong: admin/assets/css/
 * ------------------------------------------------------------
 */

$faviconUrl = $favicon !== '' ? app_url($favicon) : app_url('assets/favicon.ico');
$logoUrl = $logo !== '' ? app_url($logo) : app_url('assets/logo.webp');
$stylesheetUrl = app_url('admin/assets/css/publication-create.css');
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
            <h1>Tạo ấn phẩm mới</h1>
            <p class="admin-page-description">Nhập thông tin metadata và tải lên tài liệu nguồn cho ấn phẩm.</p>
        </div>

        <div class="admin-page-actions">
            <a href="<?= e(app_url('admin/publications.php')) ?>" class="button button-secondary">← Quay lại</a>
        </div>
    </header>

    <?php if (isset($errors['form'])): ?>
        <div class="admin-alert admin-alert-error" role="alert"><?= e((string) $errors['form']) ?></div>
    <?php endif; ?>

    <?php if ($errors !== []): ?>

        <div class="admin-alert admin-alert-error" role="alert">

            <strong>Vui lòng kiểm tra các trường sau:</strong>

            <ul>
                <?php foreach ($errors as $field => $messages): ?>
                    <?php if ($field === 'form') { continue; } ?>
                    <?php $messages = is_array($messages) ? $messages : [$messages]; ?>
                    <?php foreach ($messages as $message): ?>
                        <li><?= e((string) $message) ?></li>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </ul>

        </div>

    <?php endif; ?>

    <form method="post" action="<?= e(app_url('admin/publication-create.php')) ?>" enctype="multipart/form-data" class="admin-form" novalidate>

        <?= csrf_field() ?>

        <section class="admin-section">
            <div class="admin-section-header">
                <div>
                    <h2>Thông tin cơ bản</h2>
                    <p>Metadata được lưu cùng bản ghi ấn phẩm.</p>
                </div>
            </div>

            <div class="admin-form-grid">

                <div class="form-field form-field-full">
                    <label for="title">Tên ấn phẩm <span aria-hidden="true"></span></label>
                    <input id="title" name="title" type="text" value="<?= e((string) $old['title']) ?>" maxlength="255" required autofocus>
                </div>

                <div class="form-field form-field-full">
                    <label for="subtitle">Phụ đề</label>
                    <input id="subtitle" name="subtitle" type="text" value="<?= e((string) $old['subtitle']) ?>" maxlength="255">
                </div>

                <div class="form-field">
                    <label for="slug">Slug</label>
                    <input id="slug" name="slug" type="text" value="<?= e((string) $old['slug']) ?>" maxlength="255" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" placeholder="tap-san-60-nam">
                    <small>Để trống để hệ thống tự tạo từ tên ấn phẩm.</small>
                </div>

                <div class="form-field">
                    <label for="year">Năm <span aria-hidden="true"></span></label>
                    <input id="year" name="year" type="number" value="<?= e((string) $old['year']) ?>" min="1900" max="2200" required>
                </div>

                <div class="form-field">
                    <label for="author">Tác giả</label>
                    <input id="author" name="author" type="text" value="<?= e((string) $old['author']) ?>" maxlength="255">
                </div>

                <div class="form-field">
                    <label for="editor">Biên tập</label>
                    <input id="editor" name="editor" type="text" value="<?= e((string) $old['editor']) ?>" maxlength="255">
                </div>

                <div class="form-field">
                    <label for="language">Ngôn ngữ</label>
                    <input id="language" name="language" type="text" value="<?= e((string) $old['language']) ?>" maxlength="20">
                </div>

                <div class="form-field">
                    <label for="status">Trạng thái ban đầu</label>
                    <select id="status" name="status">
                        <option value="draft" <?= $old['status'] === 'draft' ? 'selected' : '' ?>>Bản nháp</option>
                        <option value="published" <?= $old['status'] === 'published' ? 'selected' : '' ?>>Xuất bản</option>
                        <option value="archived" <?= $old['status'] === 'archived' ? 'selected' : '' ?>>Lưu trữ</option>
                    </select>
                </div>

                <div class="form-field form-field-full">
                    <label for="description">Mô tả</label>
                    <textarea id="description" name="description" rows="8" maxlength="10000"><?= e((string) $old['description']) ?></textarea>
                </div>

            </div>
        </section>

        <section class="admin-section">

            <div class="admin-section-header">
                <div>
                    <h2>Tệp ấn phẩm</h2>
                    <p>PDF là nguồn chính của reader. Cover là ảnh đại diện.</p>
                </div>
            </div>

            <div class="admin-form-grid">

                <div class="form-field form-field-full">
                    <label for="pdf">PDF ấn phẩm</label>
                    <input id="pdf" name="pdf" type="file" accept="application/pdf,.pdf">
                    <small>Dung lượng tối đa: <?= e((string) (MAX_PDF_SIZE / 1024 / 1024)) ?> MB.</small>
                </div>

                <div class="form-field form-field-full">
                    <label for="cover">Ảnh bìa</label>
                    <input id="cover" name="cover" type="file" accept="image/jpeg,image/png,image/webp">
                    <small>Hỗ trợ JPG, PNG, WebP.</small>
                </div>

            </div>

        </section>

        <section class="admin-section">

            <div class="admin-section-header">
                <div>
                    <h2>Quyền đọc</h2>
                    <p>Các tùy chọn này được kiểm tra lại ở server khi người dùng thực hiện thao tác tương ứng.</p>
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
            <button type="submit" class="button button-primary">Tạo ấn phẩm</button>

        </div>

    </form>

</main>

<footer class="admin-footer">
    <p><?= e($schoolName) ?> · <?= e($platformName) ?></p>
</footer>

</body>
</html>