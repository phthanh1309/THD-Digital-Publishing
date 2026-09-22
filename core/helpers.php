<?php

declare(strict_types=1);

/**
 * THD Digital Publishing
 *
 * Common helper functions.
 *
 * This file must remain independent from business logic.
 */

/*
|--------------------------------------------------------------------------
| HTML escaping
|--------------------------------------------------------------------------
*/

/**
 * Escape a value for safe HTML output.
 */
function e(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| URL helpers
|--------------------------------------------------------------------------
*/

/**
 * Get the application's base URL.
 *
 * If APP_URL is explicitly configured, use it.
 * Otherwise, detect the current scheme and host.
 */
function app_url(string $path = ''): string
{
    $baseUrl = trim((string) APP_URL);

    if ($baseUrl === '') {
        $https = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 80) === 443)
        );

        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        $baseUrl = $scheme . '://' . $host;

        $documentRoot = realpath(
            $_SERVER['DOCUMENT_ROOT'] ?? ''
        );

        $projectRoot = realpath(THD_ROOT);

        if (
            $documentRoot !== false
            && $projectRoot !== false
            && (
                $projectRoot === $documentRoot
                || str_starts_with(
                    $projectRoot,
                    $documentRoot . DIRECTORY_SEPARATOR
                )
            )
        ) {
            $relativePath = substr(
                $projectRoot,
                strlen($documentRoot)
            );

            $relativePath = str_replace(
                DIRECTORY_SEPARATOR,
                '/',
                $relativePath
            );

            $baseUrl .= rtrim($relativePath, '/');
        }
    }

    $baseUrl = rtrim($baseUrl, '/');

    if ($path === '') {
        return $baseUrl;
    }

    return $baseUrl . '/' . ltrim($path, '/');
}


/**
 * Build a public URL for a publication.
 */
function publication_url(string $slug): string
{
    return app_url(
        'publication.php?slug=' . rawurlencode($slug)
    );
}


/**
 * Build a reader URL for a publication.
 */
function reader_url(
    string $slug,
    ?int $page = null
): string {
    $url = app_url(
        'reader.php?publication=' . rawurlencode($slug)
    );

    if ($page !== null && $page > 0) {
        $url .= '&page=' . $page;
    }

    return $url;
}


/*
|--------------------------------------------------------------------------
| Input helpers
|--------------------------------------------------------------------------
*/

/**
 * Get a GET parameter as a trimmed string.
 */
function get_string(
    string $key,
    string $default = ''
): string {
    $value = $_GET[$key] ?? $default;

    if (!is_string($value)) {
        return $default;
    }

    return trim($value);
}


/**
 * Get a POST parameter as a trimmed string.
 */
function post_string(
    string $key,
    string $default = ''
): string {
    $value = $_POST[$key] ?? $default;

    if (!is_string($value)) {
        return $default;
    }

    return trim($value);
}


/**
 * Get a positive integer from GET.
 */
function get_int(
    string $key,
    int $default = 0
): int {
    $value = $_GET[$key] ?? null;

    if (is_array($value)) {
        return $default;
    }

    if (
        is_string($value)
        && preg_match('/^\d+$/', $value) !== 1
    ) {
        return $default;
    }

    $integer = filter_var(
        $value,
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'min_range' => 0,
            ],
        ]
    );

    return $integer === false
        ? $default
        : (int) $integer;
}


/**
 * Get a positive integer from POST.
 */
function post_int(
    string $key,
    int $default = 0
): int {
    $value = $_POST[$key] ?? null;

    if (is_array($value)) {
        return $default;
    }

    if (
        is_string($value)
        && preg_match('/^\d+$/', $value) !== 1
    ) {
        return $default;
    }

    $integer = filter_var(
        $value,
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'min_range' => 0,
            ],
        ]
    );

    return $integer === false
        ? $default
        : (int) $integer;
}


/*
|--------------------------------------------------------------------------
| Boolean helpers
|--------------------------------------------------------------------------
*/

/**
 * Convert common boolean-like values to bool.
 */
function to_bool(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }

    if (is_int($value)) {
        return $value === 1;
    }

    if (is_string($value)) {
        return in_array(
            strtolower(trim($value)),
            [
                '1',
                'true',
                'yes',
                'on',
            ],
            true
        );
    }

    return false;
}


/*
|--------------------------------------------------------------------------
| String helpers
|--------------------------------------------------------------------------
*/

/**
 * Create a URL-friendly slug from a string.
 *
 * Vietnamese characters are transliterated where possible.
 */
function slugify(string $value): string
{
    $value = trim($value);

    if ($value === '') {
        return '';
    }

    $transliterated = iconv(
        'UTF-8',
        'ASCII//TRANSLIT//IGNORE',
        $value
    );

    if ($transliterated !== false) {
        $value = $transliterated;
    }

    $value = strtolower($value);

    $value = preg_replace(
        '/[^a-z0-9]+/',
        '-',
        $value
    ) ?? '';

    return trim($value, '-');
}


/**
 * Limit a string to a maximum number of characters.
 *
 * This is intended for display text, not security validation.
 */
function excerpt(
    string $value,
    int $length = 160
): string {
    $value = trim($value);

    if ($length <= 0) {
        return '';
    }

    if (mb_strlen($value, 'UTF-8') <= $length) {
        return $value;
    }

    return rtrim(
        mb_substr(
            $value,
            0,
            $length,
            'UTF-8'
        )
    ) . '…';
}


/**
 * Generate a cryptographically secure random identifier.
 */
function random_id(int $bytes = 16): string
{
    if ($bytes < 1) {
        $bytes = 16;
    }

    return bin2hex(random_bytes($bytes));
}


/*
|--------------------------------------------------------------------------
| Date helpers
|--------------------------------------------------------------------------
*/

/**
 * Format an application date for Vietnamese display.
 */
function format_date(
    ?string $date,
    string $fallback = ''
): string {
    if ($date === null || trim($date) === '') {
        return $fallback;
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return $fallback;
    }

    return date('d/m/Y', $timestamp);
}


/**
 * Format a datetime for Vietnamese display.
 */
function format_datetime(
    ?string $date,
    string $fallback = ''
): string {
    if ($date === null || trim($date) === '') {
        return $fallback;
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return $fallback;
    }

    return date('d/m/Y H:i', $timestamp);
}


/**
 * Get current application datetime.
 */
function now_datetime(): string
{
    return date('Y-m-d H:i:s');
}


/*
|--------------------------------------------------------------------------
| File/path helpers
|--------------------------------------------------------------------------
*/

/**
 * Ensure a directory exists.
 *
 * Returns true when the directory exists or was created.
 */
function ensure_directory(
    string $path,
    int $permissions = 0755
): bool {
    if (is_dir($path)) {
        return true;
    }

    if (file_exists($path)) {
        return false;
    }

    return mkdir(
        $path,
        $permissions,
        true
    );
}


/**
 * Normalize a filesystem path.
 */
function normalize_path(string $path): string
{
    return rtrim(
        str_replace(
            ['/', '\\'],
            DIRECTORY_SEPARATOR,
            $path
        ),
        DIRECTORY_SEPARATOR
    );
}


/**
 * Check whether a path is safely inside a base directory.
 *
 * This is useful for preventing path traversal.
 */
function is_path_inside(
    string $path,
    string $baseDirectory
): bool {
    $realPath = realpath($path);
    $realBase = realpath($baseDirectory);

    if ($realPath === false || $realBase === false) {
        return false;
    }

    $realPath = rtrim(
        $realPath,
        DIRECTORY_SEPARATOR
    ) . DIRECTORY_SEPARATOR;

    $realBase = rtrim(
        $realBase,
        DIRECTORY_SEPARATOR
    ) . DIRECTORY_SEPARATOR;

    return str_starts_with(
        $realPath,
        $realBase
    );
}


/*
|--------------------------------------------------------------------------
| Request helpers
|--------------------------------------------------------------------------
*/

/**
 * Determine whether the current request is POST.
 */
function is_post(): bool
{
    return strtoupper(
        $_SERVER['REQUEST_METHOD'] ?? 'GET'
    ) === 'POST';
}


/**
 * Determine whether the current request is GET.
 */
function is_get(): bool
{
    return strtoupper(
        $_SERVER['REQUEST_METHOD'] ?? 'GET'
    ) === 'GET';
}


/**
 * Redirect to a URL and stop execution.
 */
function redirect(
    string $url,
    int $statusCode = 302
): never {
    if (
        $statusCode < 300
        || $statusCode > 399
    ) {
        $statusCode = 302;
    }

    header(
        'Location: ' . $url,
        true,
        $statusCode
    );

    exit;
}


/**
 * Redirect to an application-relative path.
 */
function redirect_to(
    string $path,
    int $statusCode = 302
): never {
    redirect(
        app_url($path),
        $statusCode
    );
}


/*
|--------------------------------------------------------------------------
| HTTP response helpers
|--------------------------------------------------------------------------
*/

/**
 * Send a 404 response.
 */
function abort_not_found(
    string $message = 'Không tìm thấy nội dung.'
): never {
    http_response_code(404);

    throw new RuntimeException(
        $message,
        404
    );
}


/**
 * Send a JSON response.
 */
function json_response(
    mixed $data,
    int $statusCode = 200
): never {
    http_response_code($statusCode);

    header(
        'Content-Type: application/json; charset=UTF-8'
    );

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_THROW_ON_ERROR
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Publication helpers
|--------------------------------------------------------------------------
*/

/**
 * Check whether a publication status is valid.
 */
function is_valid_publication_status(
    string $status
): bool {
    return in_array(
        $status,
        PUBLICATION_STATUSES,
        true
    );
}


/**
 * Determine whether a publication is publicly visible.
 */
function is_publication_public(
    array $publication
): bool {
    return (
        ($publication['status'] ?? '')
        === 'published'
    );
}


/**
 * Get a safe publication directory name.
 *
 * Publication IDs are generated by the application,
 * but this helper still restricts the resulting value.
 */
function safe_storage_name(string $value): string
{
    $value = trim($value);

    if ($value === '') {
        return '';
    }

    return preg_replace(
        '/[^a-zA-Z0-9_-]/',
        '',
        $value
    ) ?? '';
}