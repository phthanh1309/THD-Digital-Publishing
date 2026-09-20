<?php
declare(strict_types=1);

/**
 * THD Digital Publishing
 * Installer
 *
 * Cài đặt lần đầu:
 * - Kiểm tra môi trường PHP
 * - Kiểm tra extension
 * - Tạo thư mục storage/upload
 * - Tạo XML mặc định nếu chưa tồn tại
 * - Tạo tài khoản admin đầu tiên
 * - Ghi settings mặc định
 * - Tạo installation lock
 *
 * Không được chạy lại sau khi cài đặt hoàn tất.
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once THD_ROOT . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'auth.php';

/**
 * Session phải được khởi tạo trước khi dùng CSRF.
 */
auth_start_session();

$installLockFile = STORAGE_PATH . DIRECTORY_SEPARATOR . '.installed';

$faviconUrl = app_url('assets/favicon.ico');
$stylesheetUrl = app_url('assets/css/install.css');

if (is_file($installLockFile)) {
    http_response_code(403);
    ?>
    <!doctype html>
    <html lang="vi">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex,nofollow,noarchive">
        <title>Installer đã bị khóa</title>
        <link rel="icon" href="<?= e($faviconUrl) ?>">
        <link rel="stylesheet" href="<?= e($stylesheetUrl) ?>">
    </head>
    <body>
    <main>
        <h1>Hệ thống đã được cài đặt</h1>
        <p>Installer đã bị khóa để tránh cài đặt lại và ghi đè dữ liệu.</p>
        <p>Hãy xóa hoặc đổi tên <code>install.php</code> sau khi triển khai production.</p>
        <p><a href="<?= e(app_url('admin/login.php')) ?>">Đến trang quản trị</a></p>
    </main>
    </body>
    </html>
    <?php
    exit;
}

/**
 * ------------------------------------------------------------
 * Helpers nội bộ của installer
 * ------------------------------------------------------------
 */

/**
 * Kiểm tra một path có thể ghi hoặc có thể tạo được hay không.
 */
function installer_is_writable_or_creatable(string $path): bool
{
    if (file_exists($path)) {
        return is_writable($path);
    }

    $parent = dirname($path);

    while (!file_exists($parent) && $parent !== dirname($parent)) {
        $parent = dirname($parent);
    }

    return is_dir($parent) && is_writable($parent);
}

/**
 * Ghi file với LOCK_EX.
 */
function installer_write_file(string $path, string $contents): void
{
    $directory = dirname($path);

    if (!is_dir($directory)) {
        ensure_directory($directory);
    }

    $result = file_put_contents($path, $contents, LOCK_EX);

    if ($result === false) {
        throw new RuntimeException('Không thể ghi file: ' . $path);
    }
}

/**
 * XML mặc định cho publications.
 */
function installer_default_publications_xml(): string
{
    return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<publications>
</publications>
XML;
}

/**
 * XML mặc định cho users.
 */
function installer_default_users_xml(): string
{
    return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<users>
</users>
XML;
}

/**
 * XML mặc định cho settings.
 */
function installer_default_settings_xml(): string
{
    return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<settings>
    <school>
        <name>THPT A Trần Hưng Đạo</name>
        <logo></logo>
    </school>

    <platform>
        <name>Thư viện Ấn phẩm số</name>
        <description>Thư viện Ấn phẩm số của THPT A Trần Hưng Đạo.</description>
        <favicon></favicon>
    </platform>
</settings>
XML;
}

/**
 * XML mặc định cho logs.
 */
function installer_default_logs_xml(): string
{
    return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<logs>
</logs>
XML;
}

/**
 * Kiểm tra xem hệ thống đã có dữ liệu XML hay chưa.
 */
function installer_old_data_exists(): bool
{
    $files = [PUBLICATIONS_XML, USERS_XML, SETTINGS_XML, LOGS_XML];

    foreach ($files as $file) {
        if (is_file($file) && filesize($file) > 0) {
            return true;
        }
    }

    return false;
}

/**
 * ------------------------------------------------------------
 * Environment checks
 * ------------------------------------------------------------
 */

$errors = [];
$warnings = [];
$success = false;

$requiredPhpVersion = '8.0.0';

if (version_compare(PHP_VERSION, $requiredPhpVersion, '<')) {
    $errors[] = sprintf('PHP %s trở lên là bắt buộc. Phiên bản hiện tại: %s.', $requiredPhpVersion, PHP_VERSION);
}

/**
 * Extension bắt buộc.
 */
$requiredExtensions = ['SimpleXML', 'fileinfo', 'mbstring'];

foreach ($requiredExtensions as $extension) {
    if (!extension_loaded($extension)) {
        $errors[] = 'Thiếu PHP extension: ' . $extension;
    }
}

/**
 * Extension khuyến nghị.
 */
$recommendedExtensions = ['exif'];

foreach ($recommendedExtensions as $extension) {
    if (!extension_loaded($extension)) {
        $warnings[] = 'Extension khuyến nghị chưa bật: ' . $extension;
    }
}

/**
 * ------------------------------------------------------------
 * Directory checks
 * ------------------------------------------------------------
 */

$requiredDirectories = [STORAGE_PATH, XML_PATH, PUBLICATION_STORAGE_PATH, CACHE_PATH, UPLOAD_PATH];

foreach ($requiredDirectories as $directory) {

    if (!is_dir($directory)) {
        try {
            ensure_directory($directory);
        } catch (Throwable $e) {
            $errors[] = 'Không thể tạo thư mục: ' . $directory;
        }
    }

    if (is_dir($directory) && !is_writable($directory)) {
        $errors[] = 'Thư mục không có quyền ghi: ' . $directory;
    }
}

/**
 * ------------------------------------------------------------
 * XML checks
 * ------------------------------------------------------------
 */

$xmlFiles = [
    PUBLICATIONS_XML => installer_default_publications_xml(),
    USERS_XML => installer_default_users_xml(),
    SETTINGS_XML => installer_default_settings_xml(),
    LOGS_XML => installer_default_logs_xml(),
];

foreach ($xmlFiles as $file => $defaultContent) {

    if (is_file($file)) {
        if (!is_readable($file)) {
            $errors[] = 'File XML không thể đọc: ' . $file;
        }

        if (!is_writable($file)) {
            $errors[] = 'File XML không có quyền ghi: ' . $file;
        }

        continue;
    }

    if (!installer_is_writable_or_creatable($file)) {
        $errors[] = 'Không thể tạo file XML: ' . $file;
    }
}

/**
 * ------------------------------------------------------------
 * Existing data warning
 * ------------------------------------------------------------
 */

if (installer_old_data_exists()) {
    $warnings[] = 'Một hoặc nhiều file XML đã tồn tại. Installer sẽ không ghi đè dữ liệu hiện có.';
}

/**
 * ------------------------------------------------------------
 * Form data
 * ------------------------------------------------------------
 */

$formData = [
    'username' => '',
    'display_name' => '',
    'email' => '',
];

/**
 * ------------------------------------------------------------
 * POST processing
 * ------------------------------------------------------------
 */

if (is_post() && !$errors) {

    try {

        /**
         * CSRF.
         * auth_start_session() đã được gọi ở đầu file.
         */
        require_csrf_token();

        /**
         * ----------------------------------------------------
         * Read input
         * ----------------------------------------------------
         */

        $username = trim(post_string('username'));
        $displayName = trim(post_string('display_name'));
        $email = trim(post_string('email'));
        $password = post_string('password');
        $passwordConfirmation = post_string('password_confirmation');

        $formData['username'] = $username;
        $formData['display_name'] = $displayName;
        $formData['email'] = $email;

        /**
         * ----------------------------------------------------
         * Validate username
         * ----------------------------------------------------
         */

        if ($username === '') {
            throw new ValidationException(['username' => 'Tên đăng nhập không được để trống.']);
        }

        if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
            throw new ValidationException(['username' => 'Tên đăng nhập chỉ được chứa chữ cái, số, dấu chấm, gạch ngang và gạch dưới; dài 3–50 ký tự.']);
        }

        /**
         * ----------------------------------------------------
         * Validate display name
         * ----------------------------------------------------
         */

        if ($displayName === '') {
            throw new ValidationException(['display_name' => 'Tên hiển thị không được để trống.']);
        }

        if (mb_strlen($displayName) > 120) {
            throw new ValidationException(['display_name' => 'Tên hiển thị không được vượt quá 120 ký tự.']);
        }

        /**
         * ----------------------------------------------------
         * Validate email
         * ----------------------------------------------------
         */

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException(['email' => 'Địa chỉ email không hợp lệ.']);
        }

        /**
         * ----------------------------------------------------
         * Validate password
         * ----------------------------------------------------
         */

        if (mb_strlen($password) < PASSWORD_MIN_LENGTH) {
            throw new ValidationException(['password' => sprintf('Mật khẩu phải có ít nhất %d ký tự.', PASSWORD_MIN_LENGTH)]);
        }

        /**
         * Không dùng hash_equals() cho password.
         * So sánh password nhập lần hai bằng === là đủ ở bước validation này.
         */
        if (!hash_equals($password, $passwordConfirmation)) {
            throw new ValidationException(['password_confirmation' => 'Mật khẩu xác nhận không khớp.']);
        }

        /**
         * ----------------------------------------------------
         * Make sure XML files exist
         * ----------------------------------------------------
         */

        foreach ($xmlFiles as $file => $defaultContent) {
            if (!is_file($file)) {
                installer_write_file($file, $defaultContent);
            }
        }

        /**
         * ----------------------------------------------------
         * Load repositories
         * ----------------------------------------------------
         */

        $userRepository = new UserRepository();
        $settingsRepository = new SettingsRepository();

        /**
         * ----------------------------------------------------
         * Check duplicate username
         * ----------------------------------------------------
         */

        $existingAdmin = $userRepository->findByUsername($username);

        if ($existingAdmin !== null) {
            throw new RuntimeException('Tên đăng nhập đã tồn tại. Hãy chọn tên khác.');
        }

        /**
         * ----------------------------------------------------
         * Check existing active admin
         * ----------------------------------------------------
         */

        $existingUsers = $userRepository->all();

        foreach ($existingUsers as $existingUser) {

            $role = (string) ($existingUser['role'] ?? '');
            $status = (string) ($existingUser['status'] ?? '');

            if ($role === 'admin' && $status === 'active') {
                throw new RuntimeException('Hệ thống đã có tài khoản quản trị viên đang hoạt động.');
            }
        }

        /**
         * ----------------------------------------------------
         * Create admin
         * ----------------------------------------------------
         */

        $now = now_datetime();
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        if ($passwordHash === false) {
            throw new RuntimeException('Không thể tạo password hash.');
        }

        $userRepository->create([
            'id' => 'usr_' . bin2hex(random_bytes(16)),
            'username' => $username,
            'password_hash' => $passwordHash,
            'display_name' => $displayName,
            'email' => $email,
            'role' => 'admin',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
            'last_login_at' => '',
        ]);

        /**
         * ----------------------------------------------------
         * Write default settings
         * ----------------------------------------------------
         */

        $settingsRepository->setMany([
            'school.name' => APP_SCHOOL_NAME,
            'school.logo' => '',
            'platform.name' => APP_DISPLAY_NAME,
            'platform.description' => DEFAULT_META_DESCRIPTION,
            'platform.favicon' => '',
        ]);

        /**
         * ----------------------------------------------------
         * Create installation lock
         * ----------------------------------------------------
         */

        $lockContent = implode("\n", [
            'THD Digital Publishing installation completed.',
            'Installed at: ' . $now,
            'PHP version: ' . PHP_VERSION,
            'App version: 1.0.0',
            '',
        ]);

        installer_write_file($installLockFile, $lockContent);

        /**
         * ----------------------------------------------------
         * Installation successful
         * ----------------------------------------------------
         */

        $success = true;

        /**
         * Rotate CSRF token.
         */
        rotate_csrf_token();

    } catch (ValidationException $e) {
        $errors = array_merge($errors, array_values($e->errors()));
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

/**
 * ------------------------------------------------------------
 * Page metadata
 * ------------------------------------------------------------
 */

$pageTitle = 'Cài đặt ' . APP_DISPLAY_NAME;
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" href="<?= e($faviconUrl) ?>">
    <link rel="stylesheet" href="<?= e($stylesheetUrl) ?>">
</head>
<body>

<main>

    <?php if ($success): ?>

        <!-- =============================================
             SUCCESS
             ============================================= -->

        <section>

            <h1>Cài đặt hoàn tất</h1>
            <p>Hệ thống <?= e(APP_DISPLAY_NAME) ?> đã được cài đặt thành công.</p>
            <p>Tài khoản quản trị đầu tiên đã được tạo.</p>

            <h2>Bước tiếp theo</h2>

            <ol>
                <li>Xóa hoặc đổi tên <code>install.php</code>.</li>
                <li>Đăng nhập trang quản trị.</li>
                <li>Kiểm tra thông tin trường và nền tảng.</li>
                <li>Thêm ấn phẩm đầu tiên.</li>
            </ol>

            <p><a href="<?= e(app_url('admin/login.php')) ?>">Đến trang đăng nhập quản trị</a></p>

        </section>

    <?php else: ?>

        <!-- =============================================
             HEADER
             ============================================= -->

        <header>
            <p><?= e(APP_SCHOOL_NAME) ?></p>
            <h1><?= e(APP_DISPLAY_NAME) ?></h1>
            <p>Trình cài đặt hệ thống</p>
        </header>

        <!-- =============================================
             ERRORS
             ============================================= -->

        <?php if ($errors): ?>

            <section>

                <h2>Cần xử lý trước khi cài đặt</h2>

                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>

            </section>

        <?php endif; ?>

        <!-- =============================================
             WARNINGS
             ============================================= -->

        <?php if ($warnings): ?>

            <section>

                <h2>Lưu ý</h2>

                <ul>
                    <?php foreach ($warnings as $warning): ?>
                        <li><?= e($warning) ?></li>
                    <?php endforeach; ?>
                </ul>

            </section>

        <?php endif; ?>

        <!-- =============================================
             ENVIRONMENT
             ============================================= -->

        <section>

            <h2>Kiểm tra môi trường</h2>

            <dl>

                <div>
                    <dt>PHP</dt>
                    <dd><?= e(PHP_VERSION) ?></dd>
                </div>

                <div>
                    <dt>SimpleXML</dt>
                    <dd><?= extension_loaded('SimpleXML') ? 'OK' : 'Thiếu' ?></dd>
                </div>

                <div>
                    <dt>Fileinfo</dt>
                    <dd><?= extension_loaded('fileinfo') ? 'OK' : 'Thiếu' ?></dd>
                </div>

                <div>
                    <dt>mbstring</dt>
                    <dd><?= extension_loaded('mbstring') ? 'OK' : 'Thiếu' ?></dd>
                </div>

            </dl>

        </section>

        <!-- =============================================
             ADMIN ACCOUNT
             ============================================= -->

        <section>

            <h2>Tài khoản quản trị</h2>

            <form method="post" action="" autocomplete="off">

                <?= csrf_field() ?>

                <!-- Username -->

                <div>
                    <label for="username">Tên đăng nhập</label>
                    <input id="username" name="username" type="text" value="<?= e($formData['username']) ?>" minlength="3" maxlength="50" pattern="[A-Za-z0-9._-]{3,50}" autocomplete="username" required>
                </div>

                <!-- Display name -->

                <div>
                    <label for="display_name">Tên hiển thị</label>
                    <input id="display_name" name="display_name" type="text" value="<?= e($formData['display_name']) ?>" maxlength="120" autocomplete="name" required>
                </div>

                <!-- Email -->

                <div>
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="<?= e($formData['email']) ?>" maxlength="190" autocomplete="email">
                </div>

                <!-- Password -->

                <div>
                    <label for="password">Mật khẩu</label>
                    <input id="password" name="password" type="password" minlength="<?= e((string) PASSWORD_MIN_LENGTH) ?>" autocomplete="new-password" required>
                    <p>Tối thiểu <?= e((string) PASSWORD_MIN_LENGTH) ?> ký tự.</p>
                </div>

                <!-- Password confirmation -->

                <div>
                    <label for="password_confirmation">Xác nhận mật khẩu</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" minlength="<?= e((string) PASSWORD_MIN_LENGTH) ?>" autocomplete="new-password" required>
                </div>

                <!-- Submit -->

                <button type="submit">Cài đặt hệ thống</button>

            </form>

        </section>

    <?php endif; ?>

</main>

</body>
</html>