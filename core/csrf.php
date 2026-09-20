<?php

declare(strict_types=1);

/**
 * THD Digital Publishing
 *
 * CSRF protection helpers.
 *
 * Requirements:
 * - Session must be started before generating/verifying tokens.
 * - Every state-changing form must include a CSRF token.
 * - Tokens are stored server-side in the session.
 */


/*
|--------------------------------------------------------------------------
| Token storage
|--------------------------------------------------------------------------
*/

/**
 * Get the session key used for the CSRF token.
 */
function csrf_session_key(): string
{
    return '_thd_csrf_token';
}


/**
 * Generate and store a new CSRF token.
 *
 * If a valid token already exists, reuse it.
 */
function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new RuntimeException(
            'Session phải được khởi tạo trước khi tạo CSRF token.'
        );
    }

    $sessionKey = csrf_session_key();

    $existingToken = $_SESSION[$sessionKey] ?? null;

    if (
        is_string($existingToken)
        && strlen($existingToken) >= 32
    ) {
        return $existingToken;
    }

    $token = bin2hex(
        random_bytes(32)
    );

    $_SESSION[$sessionKey] = $token;

    return $token;
}


/**
 * Return the CSRF token as a hidden HTML input.
 *
 * Intended usage:
 *
 * <?= csrf_field() ?>
 */
function csrf_field(): string
{
    return sprintf(
        '<input type="hidden" name="%s" value="%s">',
        e(CSRF_TOKEN_NAME),
        e(csrf_token())
    );
}


/*
|--------------------------------------------------------------------------
| Token verification
|--------------------------------------------------------------------------
*/

/**
 * Get the submitted CSRF token from POST.
 */
function submitted_csrf_token(): string
{
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';

    if (!is_string($token)) {
        return '';
    }

    return trim($token);
}


/**
 * Verify a submitted CSRF token.
 */
function verify_csrf_token(
    ?string $submittedToken = null
): bool {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }

    $sessionToken = $_SESSION[csrf_session_key()] ?? null;

    if (
        !is_string($sessionToken)
        || $sessionToken === ''
    ) {
        return false;
    }

    if ($submittedToken === null) {
        $submittedToken = submitted_csrf_token();
    }

    if (
        !is_string($submittedToken)
        || $submittedToken === ''
    ) {
        return false;
    }

    return hash_equals(
        $sessionToken,
        $submittedToken
    );
}


/**
 * Require a valid CSRF token.
 *
 * Throws an exception when validation fails.
 * The global error handler in bootstrap.php will later
 * convert this into a clean HTTP response.
 */
function require_csrf_token(
    ?string $submittedToken = null
): void {
    if (!verify_csrf_token($submittedToken)) {
        throw new RuntimeException(
            'CSRF token không hợp lệ hoặc đã hết hạn.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Token rotation
|--------------------------------------------------------------------------
*/

/**
 * Rotate the CSRF token.
 *
 * Useful after authentication/session regeneration.
 */
function rotate_csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new RuntimeException(
            'Session phải được khởi tạo trước khi tạo CSRF token.'
        );
    }

    $token = bin2hex(
        random_bytes(32)
    );

    $_SESSION[csrf_session_key()] = $token;

    return $token;
}


/**
 * Remove the current CSRF token.
 *
 * The next csrf_token() call will generate a new one.
 */
function forget_csrf_token(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    unset(
        $_SESSION[csrf_session_key()]
    );
}