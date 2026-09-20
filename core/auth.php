<?php
declare(strict_types=1);

/**
 * THD Digital Publishing
 *
 * Authentication layer for the administration area.
 *
 * Responsibilities:
 * - Start secure admin sessions
 * - Authenticate users
 * - Maintain authentication state
 * - Enforce session timeout
 * - Authorize administrative access
 * - Logout
 *
 * This file does NOT:
 * - render HTML
 * - handle CSRF forms
 * - access XML directly
 * - manage publication permissions
 */

require_once __DIR__ . '/../repositories/UserRepository.php';

function auth_start_session(): void
{
    start_secure_session(ADMIN_SESSION_NAME);

    enforce_session_timeout();

    if (is_admin_authenticated()) {
        refresh_session_activity();
    }
}

/**
 * Attempt to authenticate an admin user.
 *
 * @return array{
 *     success: bool,
 *     user: array<string, mixed>|null,
 *     error: string|null
 * }
 */
function auth_login(
    string $username,
    string $password
): array {
    $username = trim($username);

    if ($username === '' || $password === '') {
        return [
            'success' => false,
            'user' => null,
            'error' => 'Vui lòng nhập tên đăng nhập và mật khẩu.',
        ];
    }

    $repository = new UserRepository();

    $user = $repository->findByUsername($username);

    if ($user === null) {
        return [
            'success' => false,
            'user' => null,
            'error' => 'Tên đăng nhập hoặc mật khẩu không chính xác.',
        ];
    }

    $status = (string) ($user['status'] ?? '');

    if ($status !== 'active') {
        return [
            'success' => false,
            'user' => null,
            'error' => 'Tài khoản hiện không được phép đăng nhập.',
        ];
    }

    $passwordHash = (string) (
        $user['password_hash'] ?? ''
    );

    if (
        $passwordHash === ''
        || !password_verify($password, $passwordHash)
    ) {
        return [
            'success' => false,
            'user' => null,
            'error' => 'Tên đăng nhập hoặc mật khẩu không chính xác.',
        ];
    }

    /*
     * Regenerate the session ID after successful authentication
     * to prevent session fixation.
     */
    regenerate_session();

    $_SESSION['auth'] = [
        'authenticated' => true,
        'user_id' => (string) ($user['id'] ?? ''),
        'username' => (string) ($user['username'] ?? ''),
        'role' => (string) ($user['role'] ?? ''),
        'login_at' => time(),
        'last_activity' => time(),
    ];

    $repository->markLogin(
        (string) ($user['id'] ?? ''),
        now_datetime()
    );

    return [
        'success' => true,
        'user' => $user,
        'error' => null,
    ];
}

/**
 * Determine whether an authenticated admin session exists.
 */
function is_admin_authenticated(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }

    if (
        !isset($_SESSION['auth'])
        || !is_array($_SESSION['auth'])
    ) {
        return false;
    }

    if (
        ($_SESSION['auth']['authenticated'] ?? false)
        !== true
    ) {
        return false;
    }

    $userId = (string) (
        $_SESSION['auth']['user_id'] ?? ''
    );

    $username = (string) (
        $_SESSION['auth']['username'] ?? ''
    );

    if ($userId === '' || $username === '') {
        return false;
    }

    return true;
}

/**
 * Require an authenticated admin session.
 *
 * Redirects unauthenticated users to the login page.
 */
function require_admin_auth(
    string $loginPath = 'admin/login.php'
): void {
    auth_start_session();

    if (!is_admin_authenticated()) {
        $currentUrl = $_SERVER['REQUEST_URI'] ?? '';

        $query = http_build_query([
            'redirect' => $currentUrl,
        ]);

        redirect(
            app_url(
                $loginPath
                . ($query !== '' ? '?' . $query : '')
            )
        );
    }
}

/**
 * Return the currently authenticated user from session data.
 *
 * This is intentionally a small session representation,
 * not the complete UserRepository record.
 *
 * @return array<string, mixed>|null
 */
function auth_user(): ?array
{
    if (!is_admin_authenticated()) {
        return null;
    }

    return [
        'id' => (string) (
            $_SESSION['auth']['user_id'] ?? ''
        ),
        'username' => (string) (
            $_SESSION['auth']['username'] ?? ''
        ),
        'role' => (string) (
            $_SESSION['auth']['role'] ?? ''
        ),
        'login_at' => (int) (
            $_SESSION['auth']['login_at'] ?? 0
        ),
        'last_activity' => (int) (
            $_SESSION['auth']['last_activity'] ?? 0
        ),
    ];
}

/**
 * Get the current authenticated user's ID.
 */
function auth_user_id(): ?string
{
    $user = auth_user();

    if ($user === null) {
        return null;
    }

    $id = (string) ($user['id'] ?? '');

    return $id !== '' ? $id : null;
}

/**
 * Get the current authenticated username.
 */
function auth_username(): ?string
{
    $user = auth_user();

    if ($user === null) {
        return null;
    }

    $username = (string) (
        $user['username'] ?? ''
    );

    return $username !== ''
        ? $username
        : null;
}

/**
 * Get the current authenticated role.
 */
function auth_role(): ?string
{
    $user = auth_user();

    if ($user === null) {
        return null;
    }

    $role = (string) (
        $user['role'] ?? ''
    );

    return $role !== ''
        ? $role
        : null;
}

/**
 * Check whether the authenticated user has a given role.
 */
function auth_has_role(string $role): bool
{
    if (!is_admin_authenticated()) {
        return false;
    }

    return auth_role() === $role;
}

/**
 * Require a specific role.
 */
function require_auth_role(
    string $role,
    int $statusCode = 403
): void {
    require_admin_auth();

    if (!auth_has_role($role)) {
        throw new RuntimeException(
            'Bạn không có quyền thực hiện thao tác này.',
            $statusCode
        );
    }
}

/**
 * Require the current account to be an administrator.
 *
 * V1 only has one admin role, but keeping this as a
 * separate authorization function allows future roles.
 */
function require_admin_role(): void
{
    require_auth_role('admin');
}

/**
 * Log the current user out.
 */
function auth_logout(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    destroy_secure_session();
}

/**
 * Return the path to the login page.
 */
function auth_login_url(
    ?string $redirect = null
): string {
    $params = [];

    if (
        $redirect !== null
        && trim($redirect) !== ''
    ) {
        $params['redirect'] = $redirect;
    }

    $query = http_build_query($params);

    return app_url(
        'admin/login.php'
        . ($query !== '' ? '?' . $query : '')
    );
}

/**
 * Validate a post-login redirect.
 *
 * Only local paths are accepted.
 *
 * This prevents an attacker from turning the login
 * endpoint into an open redirect.
 */
function auth_safe_redirect(
    ?string $redirect,
    string $fallback = 'admin/index.php'
): string {
    $redirect = trim((string) $redirect);

    if ($redirect === '') {
        return app_url($fallback);
    }

    /*
     * Reject absolute URLs and protocol-relative URLs.
     */
    if (
        str_contains($redirect, '://')
        || str_starts_with($redirect, '//')
    ) {
        return app_url($fallback);
    }

    /*
     * Only local application paths are accepted.
     */
    if (!str_starts_with($redirect, '/')) {
        return app_url($fallback);
    }

    /*
     * Prevent javascript/data-like schemes even if
     * malformed input bypasses the checks above.
     */
    $lowerRedirect = strtolower($redirect);

    if (
        str_starts_with($lowerRedirect, 'javascript:')
        || str_starts_with($lowerRedirect, 'data:')
        || str_starts_with($lowerRedirect, 'vbscript:')
    ) {
        return app_url($fallback);
    }

    return $redirect;
}