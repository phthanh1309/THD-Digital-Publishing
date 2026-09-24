<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../core/auth.php';

auth_start_session();

if (is_admin_authenticated()) {
    redirect(app_url('admin/index.php'));
}

$error = '';
$oldUsername = '';

if (is_post()) {

    try {

        require_csrf_token();

        $oldUsername = post_string('username');
        $password = post_string('password');

        $result = auth_login($oldUsername, $password);

        if (!$result['success']) {

            $error = (string) (
                $result['error']
                ?? 'Đăng nhập không thành công.'
            );

        } else {

            $redirect = post_string('redirect');

            /*
             * Redirect is taken from the POST field first.
             * The GET value is used only as an initial form value.
             *
             * auth_safe_redirect() prevents external redirects.
             */
            $target = auth_safe_redirect(
                $redirect,
                'admin/index.php'
            );

            redirect($target);
        }

    } catch (RuntimeException $exception) {

        $error = $exception->getMessage();

        if ($exception->getCode() === 422) {
            $error = 'Phiên biểu mẫu không hợp lệ. Vui lòng thử lại.';
        }
    }
}

$redirectValue = get_string('redirect');

if ($redirectValue === '') {
    $redirectValue = post_string('redirect');
}

$loginRedirect = auth_safe_redirect(
    $redirectValue,
    'admin/index.php'
);

$settingsRepository = new SettingsRepository();

$platformName = (string) (
    $settingsRepository->get('platform.name')
    ?? APP_DISPLAY_NAME
);

$schoolName = (string) (
    $settingsRepository->get('school.name')
    ?? APP_SCHOOL_NAME
);

$favicon = (string) (
    $settingsRepository->get('platform.favicon')
    ?? ''
);

$pageTitle = 'Đăng nhập quản trị | ' . $platformName;

/*
 * ------------------------------------------------------------
 * Asset URLs
 *
 * CSS được đặt trong: admin/assets/css/
 * ------------------------------------------------------------
 */

$faviconUrl = $favicon !== ''
    ? app_url($favicon)
    : app_url('admin/assets/favicon.ico');

$stylesheetUrl = app_url(
    'admin/assets/css/login.css'
);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= e($pageTitle) ?></title>
    <meta name="robots" content="noindex,nofollow">

    <link rel="icon" href="<?= e($faviconUrl) ?>">
    <link rel="stylesheet" href="<?= e($stylesheetUrl) ?>">
</head>
<body class="admin-login-page">

<main class="admin-login">

    <section class="admin-login-card" aria-labelledby="login-title">

        <header class="admin-login-header">
            <p class="admin-login-school"><?= e($schoolName) ?></p>
            <h1 id="login-title"><?= e($platformName) ?></h1>
            <p class="admin-login-description">Khu vực quản trị</p>
        </header>

        <?php if ($error !== ''): ?>
            <div class="admin-alert admin-alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form class="admin-login-form" method="post" action="<?= e(app_url('admin/login.php')) ?>" autocomplete="on">

            <?= csrf_field() ?>

            <input type="hidden" name="redirect" value="<?= e($loginRedirect) ?>">

            <div class="form-field">
                <label for="username">Tên đăng nhập</label>
                <input id="username" name="username" type="text" value="<?= e($oldUsername) ?>" autocomplete="username" maxlength="100" required autofocus>
            </div>

            <div class="form-field">
                <label for="password">Mật khẩu</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
            </div>

            <button type="submit" class="button button-primary admin-login-submit">Đăng nhập</button>

        </form>

        <footer class="admin-login-footer">
            <a href="<?= e(app_url()) ?>">← Quay lại thư viện</a>
        </footer>

    </section>

</main>

</body>
</html>