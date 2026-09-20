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

$platformName = (string) (
    $settingsRepository->get('platform.name')
    ?? APP_DISPLAY_NAME
);

$schoolName = (string) (
    $settingsRepository->get('school.name')
    ?? APP_SCHOOL_NAME
);

$logo = (string) (
    $settingsRepository->get('school.logo')
    ?? ''
);

$favicon = (string) (
    $settingsRepository->get('platform.favicon')
    ?? ''
);

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

        'status' => post_string(
            'status',
            DEFAULT_PUBLICATION_STATUS
        ),

        'allow_download' => to_bool(
            $_POST['allow_download'] ?? false
        ),

        'allow_print' => to_bool(
            $_POST['allow_print'] ?? false
        ),

        'allow_share' => to_bool(
            $_POST['allow_share'] ?? false
        ),
    ];


    try {

        /*
         * --------------------------------------------------------
         * CSRF
         * --------------------------------------------------------
         */

        require_csrf_token();


        /*
         * --------------------------------------------------------
         * Validate requested status
         * --------------------------------------------------------
         */

        $allowedStatuses = [
            'draft',
            'published',
            'archived',
        ];

        if (
            !in_array(
                $old['status'],
                $allowedStatuses,
                true
            )
        ) {
            $old['status'] = 'draft';
        }


        /*
         * --------------------------------------------------------
         * Create metadata FIRST as draft.
         *
         * This is important because PublicationService does not
         * allow a "published" publication without a PDF.
         *
         * The PDF cannot be uploaded until the publication has
         * received a stable ID.
         * --------------------------------------------------------
         */

        $payload = [
            'title' => $old['title'],
            'subtitle' => $old['subtitle'],
            'slug' => $old['slug'],
            'year' => $old['year'],
            'description' => $old['description'],
            'author' => $old['author'],
            'editor' => $old['editor'],
            'language' => $old['language'],

            /*
             * ALWAYS create as draft first.
             */
            'status' => 'draft',

            'settings' => [
                'allow_download' =>
                    $old['allow_download'],

                'allow_print' =>
                    $old['allow_print'],

                'allow_share' =>
                    $old['allow_share'],
            ],
        ];


        /*
         * --------------------------------------------------------
         * Create publication
         * --------------------------------------------------------
         */

        $publication =
            $publicationService->create(
                $payload
            );


        $publicationId =
            (string) (
                $publication['id']
                ?? ''
            );


        if ($publicationId === '') {
            throw new RuntimeException(
                'Không tạo được mã ấn phẩm.'
            );
        }


        /*
         * --------------------------------------------------------
         * Upload files
         * --------------------------------------------------------
         */

        $uploadedFiles = [];


        try {

            /*
             * ----------------------------------------------------
             * PDF
             * ----------------------------------------------------
             */

            if (
                isset($_FILES['pdf'])
                && is_array($_FILES['pdf'])
            ) {

                $pdfError =
                    (int) (
                        $_FILES['pdf']['error']
                        ?? UPLOAD_ERR_NO_FILE
                    );


                if (
                    $pdfError !== UPLOAD_ERR_NO_FILE
                ) {

                    if (
                        $pdfError !== UPLOAD_ERR_OK
                    ) {

                        $uploadErrors = [
                            UPLOAD_ERR_INI_SIZE =>
                                'PDF vượt quá upload_max_filesize của PHP.',

                            UPLOAD_ERR_FORM_SIZE =>
                                'PDF vượt quá giới hạn kích thước của biểu mẫu.',

                            UPLOAD_ERR_PARTIAL =>
                                'PDF chỉ được tải lên một phần.',

                            UPLOAD_ERR_NO_TMP_DIR =>
                                'Server không có thư mục tạm để upload.',

                            UPLOAD_ERR_CANT_WRITE =>
                                'Server không thể ghi file PDF.',

                            UPLOAD_ERR_EXTENSION =>
                                'Một extension PHP đã chặn việc upload PDF.',
                        ];


                        throw new RuntimeException(
                            $uploadErrors[$pdfError]
                            ?? (
                                'Upload PDF thất bại. '
                                . 'Mã lỗi: '
                                . $pdfError
                            )
                        );
                    }


                    $uploadedFiles['pdf'] =
                        $uploadService->storePdf(
                            $publicationId,
                            $_FILES['pdf']
                        );
                }
            }


            /*
             * ----------------------------------------------------
             * COVER
             * ----------------------------------------------------
             */

            if (
                isset($_FILES['cover'])
                && is_array($_FILES['cover'])
            ) {

                $coverError =
                    (int) (
                        $_FILES['cover']['error']
                        ?? UPLOAD_ERR_NO_FILE
                    );


                if (
                    $coverError !== UPLOAD_ERR_NO_FILE
                ) {

                    if (
                        $coverError !== UPLOAD_ERR_OK
                    ) {

                        $uploadErrors = [
                            UPLOAD_ERR_INI_SIZE =>
                                'Ảnh bìa vượt quá upload_max_filesize của PHP.',

                            UPLOAD_ERR_FORM_SIZE =>
                                'Ảnh bìa vượt quá giới hạn kích thước của biểu mẫu.',

                            UPLOAD_ERR_PARTIAL =>
                                'Ảnh bìa chỉ được tải lên một phần.',

                            UPLOAD_ERR_NO_TMP_DIR =>
                                'Server không có thư mục tạm để upload.',

                            UPLOAD_ERR_CANT_WRITE =>
                                'Server không thể ghi ảnh bìa.',

                            UPLOAD_ERR_EXTENSION =>
                                'Một extension PHP đã chặn việc upload ảnh bìa.',
                        ];


                        throw new RuntimeException(
                            $uploadErrors[$coverError]
                            ?? (
                                'Upload ảnh bìa thất bại. '
                                . 'Mã lỗi: '
                                . $coverError
                            )
                        );
                    }


                    $uploadedFiles['cover'] =
                        $uploadService->storeCover(
                            $publicationId,
                            $_FILES['cover']
                        );
                }
            }


        } catch (Throwable $uploadException) {

            /*
             * Metadata đã được tạo nhưng upload thất bại.
             *
             * Xóa toàn bộ file đã upload và metadata.
             */

            $uploadService->deletePublicationFiles(
                $publicationId
            );


            $publicationService->delete(
                $publicationId
            );


            throw $uploadException;
        }


        /*
         * --------------------------------------------------------
         * Prepare update data
         * --------------------------------------------------------
         */

        $updateData = [];


        /*
         * PDF
         */

        if (
            isset($uploadedFiles['pdf'])
        ) {

            $pdf =
                $uploadedFiles['pdf'];


            $pdfPath =
                (string) (
                    $pdf['relative_path']
                    ?? ''
                );


            if ($pdfPath === '') {
                throw new RuntimeException(
                    'Upload PDF thành công nhưng không xác định được đường dẫn file.'
                );
            }


            $updateData['source_pdf'] =
                $pdfPath;

            $updateData['pdf'] =
                $pdfPath;
        }


        /*
         * Cover
         */

        if (
            isset($uploadedFiles['cover'])
        ) {

            $cover =
                $uploadedFiles['cover'];


            $coverPath =
                (string) (
                    $cover['relative_path']
                    ?? ''
                );


            if ($coverPath === '') {
                throw new RuntimeException(
                    'Upload ảnh bìa thành công nhưng không xác định được đường dẫn file.'
                );
            }


            $updateData['cover'] =
                $coverPath;
        }


        /*
         * --------------------------------------------------------
         * Determine final status
         * --------------------------------------------------------
         *
         * Nếu admin chọn published:
         *
         *     Có PDF  -> published
         *     Không PDF -> draft
         *
         * Nếu admin chọn draft/archived:
         *     giữ nguyên lựa chọn.
         * --------------------------------------------------------
         */

        if (
            isset($uploadedFiles['pdf'])
        ) {

            $updateData['status'] =
                $old['status'];

        } else {

            /*
             * Không có PDF thì không được publish.
             */
            if (
                $old['status'] === 'published'
            ) {
                $updateData['status'] =
                    'draft';
            } else {
                $updateData['status'] =
                    $old['status'];
            }
        }


        /*
         * --------------------------------------------------------
         * Update publication
         * --------------------------------------------------------
         */

        if (
            $updateData !== []
        ) {

            $publication =
                $publicationService->update(
                    $publicationId,
                    $updateData
                );

        } else {

            $publication =
                $publicationService->findById(
                    $publicationId
                );
        }


        if (
            $publication === null
        ) {
            throw new RuntimeException(
                'Không thể đọc lại ấn phẩm vừa tạo.'
            );
        }


        /*
         * --------------------------------------------------------
         * Success
         * --------------------------------------------------------
         */

        redirect(
            app_url(
                'admin/publication-edit.php?id='
                . rawurlencode(
                    $publicationId
                )
                . '&created=1'
            )
        );


    } catch (
        ValidationException $exception
    ) {

        $errors =
            $exception->errors();


    } catch (
        RuntimeException $exception
    ) {

        if (
            $exception->getCode() === 422
        ) {

            $errors['form'] =
                'Dữ liệu gửi lên không hợp lệ. '
                . 'Vui lòng kiểm tra lại biểu mẫu.';

        } else {

            /*
             * Hiển thị lỗi thực tế thay vì nuốt lỗi.
             *
             * Điều này đặc biệt hữu ích khi upload PDF
             * trên shared hosting.
             */

            $errors['form'] =
                $exception->getMessage();
        }


    } catch (
        Throwable $exception
    ) {

        /*
         * Không để lỗi upload/server biến thành
         * một lỗi "không xác định".
         */

        $errors['form'] =
            $exception->getMessage();
    }
}


$pageTitle =
    'Tạo ấn phẩm | '
    . $platformName;


/*
 * ------------------------------------------------------------
 * Asset URLs
 * ------------------------------------------------------------
 */

$faviconUrl =
    $favicon !== ''
        ? app_url($favicon)
        : app_url('assets/favicon.ico');


$logoUrl =
    $logo !== ''
        ? app_url($logo)
        : app_url('assets/logo.webp');


$stylesheetUrl =
    app_url(
        'admin/assets/css/publication-create.css'
    );

?>
<!DOCTYPE html>

<html lang="vi">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="robots"
        content="noindex,nofollow"
    >

    <title>
        <?= e($pageTitle) ?>
    </title>

    <link
        rel="icon"
        href="<?= e($faviconUrl) ?>"
    >

    <link
        rel="stylesheet"
        href="<?= e($stylesheetUrl) ?>"
    >

</head>


<body class="admin-page">


<header class="admin-header">

    <div class="admin-header-inner">


        <div class="admin-brand">

            <a
                href="<?= e(app_url('admin/index.php')) ?>"
                class="admin-brand-link"
                aria-label="<?= e($schoolName) ?>"
            >

                <img
                    class="admin-brand-logo"
                    src="<?= e($logoUrl) ?>"
                    alt="<?= e($schoolName) ?>"
                >

                <span class="admin-brand-text">

                    <span class="admin-brand-school">
                        <?= e($schoolName) ?>
                    </span>

                    <span class="admin-brand-name">
                        <?= e($platformName) ?>
                    </span>

                </span>

            </a>

        </div>


        <nav
            class="admin-nav"
            aria-label="Điều hướng quản trị"
        >

            <a
                href="<?= e(app_url('admin/index.php')) ?>"
            >
                Dashboard
            </a>

            <a
                href="<?= e(app_url('admin/publications.php')) ?>"
                aria-current="page"
            >
                Ấn phẩm
            </a>

            <a
                href="<?= e(app_url('admin/users.php')) ?>"
            >
                Người dùng
            </a>

            <a
                href="<?= e(app_url('admin/settings.php')) ?>"
            >
                Cài đặt
            </a>

            <a
                href="<?= e(app_url()) ?>"
                target="_blank"
                rel="noopener"
            >
                Xem thư viện
            </a>

        </nav>


        <div class="admin-account">

            <span class="admin-account-name">
                <?= e(auth_username() ?? 'admin') ?>
            </span>


            <form
                method="post"
                action="<?= e(app_url('admin/logout.php')) ?>"
                class="admin-logout-form"
            >

                <?= csrf_field() ?>

                <button
                    type="submit"
                    class="button button-secondary"
                >
                    Đăng xuất
                </button>

            </form>

        </div>


    </div>

</header>


<main class="admin-main">


    <header class="admin-page-header">

        <div>

            <p class="admin-eyebrow">
                Ấn phẩm
            </p>

            <h1>
                Tạo ấn phẩm mới
            </h1>

            <p class="admin-page-description">
                Nhập thông tin metadata và tải lên tài liệu nguồn cho ấn phẩm.
            </p>

        </div>


        <div class="admin-page-actions">

            <a
                href="<?= e(app_url('admin/publications.php')) ?>"
                class="button button-secondary"
            >
                ← Quay lại
            </a>

        </div>

    </header>


    <?php if (isset($errors['form'])): ?>

        <div
            class="admin-alert admin-alert-error"
            role="alert"
        >

            <?= e((string) $errors['form']) ?>

        </div>

    <?php endif; ?>


    <?php if ($errors !== []): ?>

        <div
            class="admin-alert admin-alert-error"
            role="alert"
        >

            <strong>
                Vui lòng kiểm tra các trường sau:
            </strong>


            <ul>

                <?php foreach ($errors as $field => $messages): ?>

                    <?php
                    if ($field === 'form') {
                        continue;
                    }

                    $messages =
                        is_array($messages)
                            ? $messages
                            : [$messages];
                    ?>


                    <?php foreach ($messages as $message): ?>

                        <li>
                            <?= e((string) $message) ?>
                        </li>

                    <?php endforeach; ?>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <form
        method="post"
        action="<?= e(app_url('admin/publication-create.php')) ?>"
        enctype="multipart/form-data"
        class="admin-form"
        novalidate
    >


        <?= csrf_field() ?>


        <!-- ======================================================
             BASIC INFORMATION
             ====================================================== -->

        <section class="admin-section">


            <div class="admin-section-header">

                <div>

                    <h2>
                        Thông tin cơ bản
                    </h2>

                    <p>
                        Metadata được lưu cùng bản ghi ấn phẩm.
                    </p>

                </div>

            </div>


            <div class="admin-form-grid">


                <div class="form-field form-field-full">

                    <label for="title">
                        Tên ấn phẩm
                    </label>

                    <input
                        id="title"
                        name="title"
                        type="text"
                        value="<?= e((string) $old['title']) ?>"
                        maxlength="255"
                        required
                        autofocus
                    >

                </div>


                <div class="form-field form-field-full">

                    <label for="subtitle">
                        Phụ đề
                    </label>

                    <input
                        id="subtitle"
                        name="subtitle"
                        type="text"
                        value="<?= e((string) $old['subtitle']) ?>"
                        maxlength="255"
                    >

                </div>


                <div class="form-field">

                    <label for="slug">
                        Slug
                    </label>

                    <input
                        id="slug"
                        name="slug"
                        type="text"
                        value="<?= e((string) $old['slug']) ?>"
                        maxlength="255"
                        pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                        placeholder="tap-san-60-nam"
                    >

                    <small>
                        Để trống để hệ thống tự tạo từ tên ấn phẩm.
                    </small>

                </div>


                <div class="form-field">

                    <label for="year">
                        Năm
                    </label>

                    <input
                        id="year"
                        name="year"
                        type="number"
                        value="<?= e((string) $old['year']) ?>"
                        min="1900"
                        max="2200"
                        required
                    >

                </div>


                <div class="form-field">

                    <label for="author">
                        Tác giả
                    </label>

                    <input
                        id="author"
                        name="author"
                        type="text"
                        value="<?= e((string) $old['author']) ?>"
                        maxlength="255"
                    >

                </div>


                <div class="form-field">

                    <label for="editor">
                        Biên tập
                    </label>

                    <input
                        id="editor"
                        name="editor"
                        type="text"
                        value="<?= e((string) $old['editor']) ?>"
                        maxlength="255"
                    >

                </div>


                <div class="form-field">

                    <label for="language">
                        Ngôn ngữ
                    </label>

                    <input
                        id="language"
                        name="language"
                        type="text"
                        value="<?= e((string) $old['language']) ?>"
                        maxlength="20"
                    >

                </div>


                <div class="form-field">

                    <label for="status">
                        Trạng thái ban đầu
                    </label>

                    <select
                        id="status"
                        name="status"
                    >

                        <option
                            value="draft"
                            <?= $old['status'] === 'draft'
                                ? 'selected'
                                : '' ?>
                        >
                            Bản nháp
                        </option>

                        <option
                            value="published"
                            <?= $old['status'] === 'published'
                                ? 'selected'
                                : '' ?>
                        >
                            Xuất bản
                        </option>

                        <option
                            value="archived"
                            <?= $old['status'] === 'archived'
                                ? 'selected'
                                : '' ?>
                        >
                            Lưu trữ
                        </option>

                    </select>

                </div>


                <div class="form-field form-field-full">

                    <label for="description">
                        Mô tả
                    </label>

                    <textarea
                        id="description"
                        name="description"
                        rows="8"
                        maxlength="10000"
                    ><?= e((string) $old['description']) ?></textarea>

                </div>


            </div>

        </section>


        <!-- ======================================================
             FILES
             ====================================================== -->

        <section class="admin-section">


            <div class="admin-section-header">

                <div>

                    <h2>
                        Tệp ấn phẩm
                    </h2>

                    <p>
                        PDF là nguồn chính của reader. Cover là ảnh đại diện.
                    </p>

                </div>

            </div>


            <div class="admin-form-grid">


                <div class="form-field form-field-full">

                    <label for="pdf">
                        PDF ấn phẩm
                    </label>

                    <input
                        id="pdf"
                        name="pdf"
                        type="file"
                        accept="application/pdf,.pdf"
                    >

                    <small>
                        Dung lượng tối đa:
                        <?= e(
                            (string) (
                                MAX_PDF_SIZE
                                / 1024
                                / 1024
                            )
                        ) ?>
                        MB.
                    </small>

                </div>


                <div class="form-field form-field-full">

                    <label for="cover">
                        Ảnh bìa
                    </label>

                    <input
                        id="cover"
                        name="cover"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                    >

                    <small>
                        Hỗ trợ JPG, PNG, WebP.
                    </small>

                </div>


            </div>

        </section>


        <!-- ======================================================
             PERMISSIONS
             ====================================================== -->

        <section class="admin-section">


            <div class="admin-section-header">

                <div>

                    <h2>
                        Quyền đọc
                    </h2>

                    <p>
                        Các tùy chọn này được kiểm tra lại ở server khi
                        người dùng thực hiện thao tác tương ứng.
                    </p>

                </div>

            </div>


            <div class="admin-permission-list">


                <label class="admin-checkbox">

                    <input
                        type="checkbox"
                        name="allow_download"
                        value="1"
                        <?= $old['allow_download']
                            ? 'checked'
                            : '' ?>
                    >

                    <span>
                        Cho phép tải PDF
                    </span>

                </label>


                <label class="admin-checkbox">

                    <input
                        type="checkbox"
                        name="allow_print"
                        value="1"
                        <?= $old['allow_print']
                            ? 'checked'
                            : '' ?>
                    >

                    <span>
                        Cho phép in
                    </span>

                </label>


                <label class="admin-checkbox">

                    <input
                        type="checkbox"
                        name="allow_share"
                        value="1"
                        <?= $old['allow_share']
                            ? 'checked'
                            : '' ?>
                    >

                    <span>
                        Cho phép chia sẻ liên kết
                    </span>

                </label>


            </div>

        </section>


        <!-- ======================================================
             ACTIONS
             ====================================================== -->

        <div class="admin-form-actions">


            <a
                href="<?= e(app_url('admin/publications.php')) ?>"
                class="button button-secondary"
            >
                Hủy
            </a>


            <button
                type="submit"
                class="button button-primary"
            >
                Tạo ấn phẩm
            </button>


        </div>


    </form>


</main>


<footer class="admin-footer">

    <p>
        <?= e($schoolName) ?>
        ·
        <?= e($platformName) ?>
    </p>

</footer>


</body>

</html>
