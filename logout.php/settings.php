<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/validator.php';
require_once __DIR__ . '/../repositories/SettingsRepository.php';

auth_start_session();
require_admin_role();

$settingsRepository = new SettingsRepository();
$settings = $settingsRepository->all();

$errors = [];
$saved = get_string('saved', '') === '1';

/*
 * Giá trị hiện tại.
 */
$schoolName = (string) ($settings['school']['name'] ?? APP_SCHOOL_NAME);
$schoolLogo = (string) ($settings['school']['logo'] ?? '');
$platformName = (string) ($settings['platform']['name'] ?? APP_DISPLAY_NAME);
$platformDescription = (string) ($settings['platform']['description'] ?? DEFAULT_META_DESCRIPTION);
$platformFavicon = (string) ($settings['platform']['favicon'] ?? '');

if (is_post()) {

    try {

        require_csrf_token();

        $action = post_string('action', '');

        if ($action !== 'save') {
            throw new RuntimeException('Thao tác không hợp lệ.', 400);
        }

        $schoolNameInput = trim(post_string('school_name', ''));
        $schoolLogoInput = trim(post_string('school_logo', ''));
        $platformNameInput = trim(post_string('platform_name', ''));
        $platformDescriptionInput = trim(post_string('platform_description', ''));
        $platformFaviconInput = trim(post_string('platform_favicon', ''));

        /*
         * Validate.
         */
        if ($schoolNameInput === '') {
            $errors[] = 'Tên trường không được để trống.';
        } elseif (mb_strlen($schoolNameInput) > 190) {
            $errors[] = 'Tên trường không được vượt quá 190 ký tự.';
        }

        if ($schoolLogoInput !== '') {
            if (mb_strlen($schoolLogoInput) > 500) {
                $errors[] = 'Đường dẫn logo quá dài.';
            } elseif (has_path_traversal($schoolLogoInput) || preg_match('/[\x00-\x1F\x7F]/', $schoolLogoInput)) {
                $errors[] = 'Đường dẫn logo không hợp lệ.';
            }
        }

        if ($platformNameInput === '') {
            $errors[] = 'Tên nền tảng không được để trống.';
        } elseif (mb_strlen($platformNameInput) > 190) {
            $errors[] = 'Tên nền tảng không được vượt quá 190 ký tự.';
        }

        if ($platformDescriptionInput === '') {
            $errors[] = 'Mô tả nền tảng không được để trống.';
        } elseif (mb_strlen($platformDescriptionInput) > 500) {
            $errors[] = 'Mô tả nền tảng không được vượt quá 500 ký tự.';
        }

        if ($platformFaviconInput !== '') {
            if (mb_strlen($platformFaviconInput) > 500) {
                $errors[] = 'Đường dẫn favicon quá dài.';
            } elseif (has_path_traversal($platformFaviconInput) || preg_match('/[\x00-\x1F\x7F]/', $platformFaviconInput)) {
                $errors[] = 'Đường dẫn favicon không hợp lệ.';
            }
        }

        if ($errors === []) {

            $settingsRepository->setMany([
                'school.name' => $schoolNameInput,
                'school.logo' => $schoolLogoInput,
                'platform.name' => $platformNameInput,
                'platform.description' => $platformDescriptionInput,
                'platform.favicon' => $platformFaviconInput,
            ]);

            redirect(app_url('admin/settings.php?saved=1'));
        }

        /*
         * Nếu validation thất bại, giữ lại dữ liệu người dùng vừa nhập
         * để không phải nhập lại toàn bộ biểu mẫu.
         */
        $schoolName = $schoolNameInput;
        $schoolLogo = $schoolLogoInput;
        $platformName = $platformNameInput;
        $platformDescription = $platformDescriptionInput;
        $platformFavicon = $platformFaviconInput;

    } catch (ValidationException $exception) {

        $errors = array_merge($errors, $exception->errors());
    }
}

$csrfField = csrf_field();
$pageTitle = 'Cài đặt hệ thống';

/*
 * ------------------------------------------------------------
 * Asset URLs
 *
 * CSS/JS được đặt trong: admin/assets/
 *
 * Với favicon/logo:
 * - Nếu Settings có giá trị thì dùng giá trị đó.
 * - Nếu không, fallback về admin/assets/.
 * ------------------------------------------------------------
 */

$faviconUrl = $platformFavicon !== '' ? app_url($platformFavicon) : app_url('assets/favicon.ico');
$logoUrl = $schoolLogo !== '' ? app_url($schoolLogo) : app_url('assets/logo.webp');
$stylesheetUrl = app_url('admin/assets/css/settings.css');
$scriptUrl = app_url('assets/js/admin.js');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($pageTitle) ?> · <?= e($platformName) ?></title>
    <link rel="icon" href="<?= e($faviconUrl) ?>">
    <link rel="stylesheet" href="<?= e($stylesheetUrl) ?>">
</head>
<body class="admin-page">

<header class="admin-header">
    <div class="admin-header__inner">

        <div class="admin-brand">
            <a href="<?= e(app_url('admin/index.php')) ?>" class="admin-brand__link" aria-label="<?= e($schoolName) ?>">
                <img class="admin-brand__logo" src="<?= e($logoUrl) ?>" alt="<?= e($schoolName) ?>">
                <span class="admin-brand__text">
                    <strong><?= e($platformName) ?></strong>
                    <span><?= e($schoolName) ?></span>
                </span>
            </a>
        </div>

        <nav class="admin-nav" aria-label="Quản trị">
            <a href="<?= e(app_url('admin/index.php')) ?>">Tổng quan</a>
            <a href="<?= e(app_url('admin/publications.php')) ?>">Ấn phẩm</a>
            <a href="<?= e(app_url('admin/users.php')) ?>">Tài khoản</a>
            <a href="<?= e(app_url('admin/settings.php')) ?>" aria-current="page">Cài đặt</a>
        </nav>

        <form method="post" action="<?= e(app_url('admin/logout.php')) ?>" class="admin-logout-form">
            <?= $csrfField ?>
            <button type="submit">Đăng xuất</button>
        </form>

    </div>
</header>

<main class="admin-main">
    <div class="admin-container">

        <div class="admin-page-heading">
            <div>
                <p class="admin-eyebrow">Cấu hình nền tảng</p>
                <h1><?= e($pageTitle) ?></h1>
                <p>Quản lý tên trường, nhận diện nền tảng và thông tin hiển thị cơ bản của thư viện.</p>
            </div>
        </div>

        <?php if ($saved): ?>
            <div class="admin-alert admin-alert--success">Đã lưu cài đặt thành công.</div>
        <?php endif; ?>

        <?php if ($errors !== []): ?>
            <div class="admin-alert admin-alert--error">
                <strong>Không thể lưu cài đặt:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e(app_url('admin/settings.php')) ?>" class="admin-form">

            <?= $csrfField ?>
            <input type="hidden" name="action" value="save">

            <section class="admin-card">

                <div class="admin-card__header">
                    <div>
                        <h2>Thông tin trường</h2>
                        <p>Thông tin nhận diện chính của nhà trường.</p>
                    </div>
                </div>

                <div class="admin-form-grid">

                    <div class="admin-form-field">
                        <label for="school_name">Tên trường</label>
                        <input id="school_name" name="school_name" type="text" maxlength="190" required value="<?= e($schoolName) ?>">
                        <small>Tên này được sử dụng ở header, footer và metadata của nền tảng.</small>
                    </div>

                    <div class="admin-form-field">
                        <label for="school_logo">Logo</label>
                        <input id="school_logo" name="school_logo" type="text" maxlength="500" value="<?= e($schoolLogo) ?>" placeholder="admin/assets/logo.webp">
                        <small>Đường dẫn tương đối từ thư mục gốc ứng dụng. Chưa thực hiện upload logo ở phiên bản này.</small>
                    </div>

                </div>

            </section>

            <section class="admin-card">

                <div class="admin-card__header">
                    <div>
                        <h2>Nhận diện nền tảng</h2>
                        <p>Nội dung hiển thị của thư viện ấn phẩm số.</p>
                    </div>
                </div>

                <div class="admin-form-grid">

                    <div class="admin-form-field">
                        <label for="platform_name">Tên nền tảng</label>
                        <input id="platform_name" name="platform_name" type="text" maxlength="190" required value="<?= e($platformName) ?>">
                        <small>Ví dụ: Thư viện Ấn phẩm số.</small>
                    </div>

                    <div class="admin-form-field">
                        <label for="platform_favicon">Favicon</label>
                        <input id="platform_favicon" name="platform_favicon" type="text" maxlength="500" value="<?= e($platformFavicon) ?>" placeholder="admin/assets/favicon.ico">
                        <small>Đường dẫn tương đối từ thư mục gốc ứng dụng.</small>
                    </div>

                    <div class="admin-form-field admin-form-field--full">
                        <label for="platform_description">Mô tả nền tảng</label>
                        <textarea id="platform_description" name="platform_description" rows="5" maxlength="500" required><?= e($platformDescription) ?></textarea>
                        <small>Được sử dụng làm mô tả mặc định cho SEO và các khu vực giới thiệu nền tảng.</small>
                    </div>

                </div>

            </section>

            <section class="admin-card">

                <div class="admin-card__header">
                    <div>
                        <h2>Trạng thái cấu hình</h2>
                        <p>Các giá trị dưới đây được lưu trong XML thông qua SettingsRepository.</p>
                    </div>
                </div>

                <dl class="admin-definition-list">

                    <div>
                        <dt>Môi trường</dt>
                        <dd><?= e(APP_ENV) ?></dd>
                    </div>

                    <div>
                        <dt>Múi giờ</dt>
                        <dd><?= e(date_default_timezone_get()) ?></dd>
                    </div>

                    <div>
                        <dt>PHP</dt>
                        <dd><?= e(PHP_VERSION) ?></dd>
                    </div>

                    <div>
                        <dt>Bộ nhớ XML</dt>
                        <dd>SimpleXML</dd>
                    </div>

                </dl>

            </section>

            <div class="admin-form-actions">
                <button type="submit" class="admin-button admin-button--primary">Lưu cài đặt</button>
                <a href="<?= e(app_url('admin/index.php')) ?>" class="admin-button">Hủy</a>
            </div>

        </form>

        <section class="admin-note">
            <strong>Lưu ý:</strong>
            logo và favicon hiện chỉ nhận đường dẫn đã có sẵn.
            Chức năng upload tài sản thương hiệu sẽ được tách riêng để kiểm soát MIME, kích thước, tên file và đường dẫn lưu trữ.
        </section>

    </div>
</main>

<script src="<?= e($scriptUrl) ?>" defer></script>

</body>
</html>