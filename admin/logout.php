<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../core/auth.php';

auth_start_session();

if (!is_post()) {
    http_response_code(405);
    header('Allow: POST');
    header('Content-Type: text/html; charset=UTF-8');

    echo '<!DOCTYPE html>';
    echo '<html lang="vi">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<link rel="stylesheet" href="' . e(app_url('admin/assets/css/logout.css')) . '">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>Phương thức không được phép</title>';
    echo '</head>';
    echo '<body>';
    echo '<h1>405 - Phương thức không được phép</h1>';
    echo '<p>Thao tác đăng xuất phải được thực hiện bằng biểu mẫu POST.</p>';
    echo '<p><a href="' . e(app_url('admin/index.php')) . '">Quay lại quản trị</a></p>';
    echo '</body>';
    echo '</html>';

    exit;
}

try {
    require_csrf_token();

    auth_logout();

    redirect(
        app_url('admin/login.php')
    );
} catch (RuntimeException $exception) {
    if ($exception->getCode() === 422) {
        http_response_code(422);

        echo '<!DOCTYPE html>';
        echo '<html lang="vi">';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<link rel="stylesheet" href="' . e(app_url('admin/assets/css/logout.css')) . '">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '<title>Phiên không hợp lệ</title>';
        echo '</head>';
        echo '<body>';
        echo '<h1>Phiên không hợp lệ</h1>';
        echo '<p>Vui lòng quay lại trang quản trị và thử đăng xuất lại.</p>';
        echo '<p><a href="' . e(app_url('admin/index.php')) . '">Quay lại quản trị</a></p>';
        echo '</body>';
        echo '</html>';

        exit;
    }

    throw $exception;
}