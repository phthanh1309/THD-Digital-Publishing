<?php

declare(strict_types=1);

/**
 * THD Digital Publishing
 *
 * Security helpers:
 * - HTTPS detection
 * - Secure session configuration
 * - Security headers
 * - Session timeout support
 * - Safe comparison
 * - Safe filename/path checks
 *
 * Authentication logic belongs to core/auth.php.
 */


/*
|--------------------------------------------------------------------------
| Request security
|--------------------------------------------------------------------------
*/

/**
 * Determine whether the current request uses HTTPS.
 */
function is_https(): bool
{
    return (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 80) === 443)
    );
}


/**
 * Get the remote client IP.
 *
 * We intentionally do not trust HTTP_X_FORWARDED_FOR here.
 * Shared hosting environments may expose that header without
 * a trusted reverse proxy configuration.
 */
function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    if (!is_string($ip)) {
        return '';
    }

    return filter_var(
        $ip,
        FILTER_VALIDATE_IP
    ) !== false
        ? $ip
        : '';
}


/*
|--------------------------------------------------------------------------
| Security headers
|--------------------------------------------------------------------------
*/

/**
 * Send baseline security headers.
 *
 * CSP is intentionally not included yet because the final frontend
 * may use PDF.js or another pinned third-party reader library.
 * CSP will be configured after the asset architecture is finalized.
 */
function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header(
        'X-Content-Type-Options: nosniff'
    );

    header(
        'X-Frame-Options: SAMEORIGIN'
    );

    header(
        'Referrer-Policy: strict-origin-when-cross-origin'
    );

    header(
        'Permissions-Policy: camera=(), microphone=(), geolocation=()'
    );

    /*
     * Do not enable HSTS automatically during development.
     *
     * HSTS should only be enabled after the production domain
     * is confirmed to work entirely over HTTPS.
     */
    if (
        defined('APP_ENV')
        && APP_ENV === 'production'
        && is_https()
    ) {
        header(
            'Strict-Transport-Security: max-age=31536000'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Secure sessions
|--------------------------------------------------------------------------
*/

/**
 * Configure secure PHP session settings.
 *
 * This function does not perform authentication.
 * It only establishes a safer session environment.
 */
function configure_secure_session(
    string $sessionName = ADMIN_SESSION_NAME
): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    if (headers_sent()) {
        throw new RuntimeException(
            'Không thể khởi tạo session sau khi response đã được gửi.'
        );
    }

    $cookieSecure = is_https();

    ini_set(
        'session.use_strict_mode',
        '1'
    );

    ini_set(
        'session.use_only_cookies',
        '1'
    );

    ini_set(
        'session.cookie_httponly',
        '1'
    );

    ini_set(
        'session.cookie_secure',
        $cookieSecure ? '1' : '0'
    );

    ini_set(
        'session.cookie_samesite',
        'Lax'
    );

    session_name($sessionName);

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $cookieSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}


/**
 * Start a secure PHP session.
 */
function start_secure_session(
    string $sessionName = ADMIN_SESSION_NAME
): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    configure_secure_session($sessionName);

    if (!session_start()) {
        throw new RuntimeException(
            'Không thể khởi tạo session.'
        );
    }
}


/**
 * Regenerate the session ID safely.
 *
 * Call this after successful authentication to prevent
 * session fixation.
 */
function regenerate_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new RuntimeException(
            'Session chưa được khởi tạo.'
        );
    }

    if (!session_regenerate_id(true)) {
        throw new RuntimeException(
            'Không thể tạo lại session ID.'
        );
    }
}


/**
 * Destroy the current session completely.
 */
function destroy_secure_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $_SESSION = [];

    $params = session_get_cookie_params();

    if (ini_get('session.use_cookies')) {
        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 42000,
                'path' => $params['path'] ?? '/',
                'domain' => $params['domain'] ?? '',
                'secure' => (bool) ($params['secure'] ?? false),
                'httponly' => (bool) ($params['httponly'] ?? true),
                'samesite' => $params['samesite'] ?? 'Lax',
            ]
        );
    }

    session_destroy();
}


/*
|--------------------------------------------------------------------------
| Session timeout
|--------------------------------------------------------------------------
*/

/**
 * Check whether the current session has expired.
 *
 * Returns true when the session is expired.
 */
function is_session_expired(
    int $timeout = ADMIN_SESSION_TIMEOUT
): bool {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return true;
    }

    if ($timeout <= 0) {
        return false;
    }

    $lastActivity = $_SESSION['last_activity'] ?? null;

    if (!is_int($lastActivity)) {
        if (
            is_string($lastActivity)
            && ctype_digit($lastActivity)
        ) {
            $lastActivity = (int) $lastActivity;
        } else {
            return false;
        }
    }

    return (
        $lastActivity > 0
        && (time() - $lastActivity) > $timeout
    );
}


/**
 * Refresh the current session activity timestamp.
 */
function refresh_session_activity(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new RuntimeException(
            'Session chưa được khởi tạo.'
        );
    }

    $_SESSION['last_activity'] = time();
}


/**
 * Check session timeout and destroy the session when expired.
 *
 * Returns true if the session was expired.
 */
function enforce_session_timeout(
    int $timeout = ADMIN_SESSION_TIMEOUT
): bool {
    if (!is_session_expired($timeout)) {
        refresh_session_activity();

        return false;
    }

    destroy_secure_session();

    return true;
}


/*
|--------------------------------------------------------------------------
| Cryptographic helpers
|--------------------------------------------------------------------------
*/

/**
 * Perform a timing-safe string comparison.
 */
function secure_equals(
    string $knownValue,
    string $userValue
): bool {
    return hash_equals(
        $knownValue,
        $userValue
    );
}


/**
 * Generate a secure random token.
 */
function secure_token(int $bytes = 32): string
{
    if ($bytes < 16) {
        $bytes = 32;
    }

    return bin2hex(
        random_bytes($bytes)
    );
}


/*
|--------------------------------------------------------------------------
| Filename security
|--------------------------------------------------------------------------
*/

/**
 * Check whether a filename contains dangerous path components.
 */
function has_path_traversal(
    string $path
): bool {
    if ($path === '') {
        return true;
    }

    $normalized = str_replace(
        '\\',
        '/',
        $path
    );

    /*
     * Reject:
     * ../
     * ..\
     * absolute Unix paths
     * Windows drive paths
     */
    if (
        str_contains($normalized, '../')
        || str_contains($normalized, '/..')
        || str_starts_with($normalized, '/')
        || preg_match(
            '/^[a-zA-Z]:[\/\\\\]/',
            $path
        ) === 1
    ) {
        return true;
    }

    return false;
}


/**
 * Sanitize an uploaded filename.
 *
 * This is NOT used as the final stored filename.
 * Uploaded files should ultimately be stored using
 * an application-generated random name.
 */
function sanitize_filename(
    string $filename
): string {
    $filename = basename($filename);

    /*
     * Remove control characters.
     */
    $filename = preg_replace(
        '/[\x00-\x1F\x7F]/u',
        '',
        $filename
    ) ?? '';

    /*
     * Keep only a conservative set of characters.
     */
    $filename = preg_replace(
        '/[^a-zA-Z0-9._-]/',
        '-',
        $filename
    ) ?? '';

    $filename = preg_replace(
        '/-+/',
        '-',
        $filename
    ) ?? '';

    return trim(
        $filename,
        '.-'
    );
}


/**
 * Get a safe file extension.
 */
function safe_extension(
    string $filename
): string {
    $extension = pathinfo(
        $filename,
        PATHINFO_EXTENSION
    );

    if (!is_string($extension)) {
        return '';
    }

    return strtolower(
        preg_replace(
            '/[^a-zA-Z0-9]/',
            '',
            $extension
        ) ?? ''
    );
}


/*
|--------------------------------------------------------------------------
| Storage path security
|--------------------------------------------------------------------------
*/

/**
 * Ensure a generated storage path stays inside the expected directory.
 *
 * The target directory must already exist.
 */
function assert_safe_storage_path(
    string $targetPath,
    string $baseDirectory
): void {
    if (has_path_traversal($targetPath)) {
        throw new RuntimeException(
            'Đường dẫn lưu trữ không hợp lệ.'
        );
    }

    $baseRealPath = realpath($baseDirectory);

    if ($baseRealPath === false) {
        throw new RuntimeException(
            'Thư mục lưu trữ không tồn tại.'
        );
    }

    $targetDirectory = dirname($targetPath);

    $targetRealDirectory = realpath($targetDirectory);

    if ($targetRealDirectory === false) {
        throw new RuntimeException(
            'Thư mục đích không tồn tại.'
        );
    }

    $baseRealPath = rtrim(
        $baseRealPath,
        DIRECTORY_SEPARATOR
    ) . DIRECTORY_SEPARATOR;

    $targetRealDirectory = rtrim(
        $targetRealDirectory,
        DIRECTORY_SEPARATOR
    ) . DIRECTORY_SEPARATOR;

    if (
        !str_starts_with(
            $targetRealDirectory,
            $baseRealPath
        )
    ) {
        throw new RuntimeException(
            'Đường dẫn lưu trữ vượt ra ngoài thư mục cho phép.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Upload error helpers
|--------------------------------------------------------------------------
*/

/**
 * Convert a PHP upload error code into a readable message.
 */
function upload_error_message(
    int $errorCode
): string {
    return match ($errorCode) {
        UPLOAD_ERR_OK =>
            'Upload thành công.',

        UPLOAD_ERR_INI_SIZE =>
            'File vượt quá giới hạn upload của máy chủ.',

        UPLOAD_ERR_FORM_SIZE =>
            'File vượt quá giới hạn cho phép của biểu mẫu.',

        UPLOAD_ERR_PARTIAL =>
            'File chỉ được upload một phần.',

        UPLOAD_ERR_NO_FILE =>
            'Không có file được upload.',

        UPLOAD_ERR_NO_TMP_DIR =>
            'Máy chủ thiếu thư mục tạm.',

        UPLOAD_ERR_CANT_WRITE =>
            'Không thể ghi file lên máy chủ.',

        UPLOAD_ERR_EXTENSION =>
            'Một PHP extension đã chặn việc upload.',

        default =>
            'Lỗi upload không xác định.',
    };
}


/**
 * Check whether an upload error indicates a valid upload.
 */
function is_successful_upload(
    mixed $file
): bool {
    if (!is_array($file)) {
        return false;
    }

    return (
        isset($file['error'])
        && is_int($file['error'])
        && $file['error'] === UPLOAD_ERR_OK
    );
}