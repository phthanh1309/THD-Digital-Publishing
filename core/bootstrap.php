<?php

declare(strict_types=1);

/**
 * THD Digital Publishing
 *
 * Application bootstrap.
 *
 * Responsibilities:
 * - Load configuration
 * - Load core helpers
 * - Load security utilities
 * - Load CSRF utilities
 * - Load validators
 * - Load repositories
 * - Load services
 * - Prepare required storage directories
 * - Configure error handling
 * - Send baseline security headers
 *
 * This file must not contain business logic.
 */


/*
|--------------------------------------------------------------------------
| Prevent direct access
|--------------------------------------------------------------------------
*/

if (!defined('THD_ROOT')) {
    define(
        'THD_ROOT',
        dirname(__DIR__)
    );
}


/*
|--------------------------------------------------------------------------
| Load configuration
|--------------------------------------------------------------------------
*/

require_once THD_ROOT
    . DIRECTORY_SEPARATOR
    . 'config'
    . DIRECTORY_SEPARATOR
    . 'config.php';


/*
|--------------------------------------------------------------------------
| Load core modules
|--------------------------------------------------------------------------
*/

$coreFiles = [
    THD_ROOT
        . DIRECTORY_SEPARATOR
        . 'core'
        . DIRECTORY_SEPARATOR
        . 'helpers.php',

    THD_ROOT
        . DIRECTORY_SEPARATOR
        . 'core'
        . DIRECTORY_SEPARATOR
        . 'security.php',

    THD_ROOT
        . DIRECTORY_SEPARATOR
        . 'core'
        . DIRECTORY_SEPARATOR
        . 'csrf.php',

    THD_ROOT
        . DIRECTORY_SEPARATOR
        . 'core'
        . DIRECTORY_SEPARATOR
        . 'validator.php',
];

foreach ($coreFiles as $coreFile) {
    if (!is_file($coreFile)) {
        throw new RuntimeException(
            'Không tìm thấy core file: ' . $coreFile
        );
    }

    require_once $coreFile;
}


/*
|--------------------------------------------------------------------------
| Load repositories
|--------------------------------------------------------------------------
|
| Repositories are loaded before services because services depend
| on repository classes.
|
*/

$repositoryFiles = [
    THD_ROOT
        . DIRECTORY_SEPARATOR
        . 'repositories'
        . DIRECTORY_SEPARATOR
        . 'PublicationRepository.php',

    THD_ROOT
        . DIRECTORY_SEPARATOR
        . 'repositories'
        . DIRECTORY_SEPARATOR
        . 'UserRepository.php',

    THD_ROOT
        . DIRECTORY_SEPARATOR
        . 'repositories'
        . DIRECTORY_SEPARATOR
        . 'SettingsRepository.php',
];

foreach ($repositoryFiles as $repositoryFile) {
    if (!is_file($repositoryFile)) {
        throw new RuntimeException(
            'Không tìm thấy repository file: '
            . $repositoryFile
        );
    }

    require_once $repositoryFile;
}


/*
|--------------------------------------------------------------------------
| Load services
|--------------------------------------------------------------------------
|
| Services are loaded after repositories because they depend
| on repository classes.
|
*/

$serviceFiles = [
    THD_ROOT
        . DIRECTORY_SEPARATOR
        . 'services'
        . DIRECTORY_SEPARATOR
        . 'PublicationService.php',

    THD_ROOT
        . DIRECTORY_SEPARATOR
        . 'services'
        . DIRECTORY_SEPARATOR
        . 'UploadService.php',

    THD_ROOT
        . DIRECTORY_SEPARATOR
        . 'services'
        . DIRECTORY_SEPARATOR
        . 'SearchService.php',

    THD_ROOT
        . DIRECTORY_SEPARATOR
        . 'services'
        . DIRECTORY_SEPARATOR
        . 'ReaderService.php',
];

foreach ($serviceFiles as $serviceFile) {
    if (!is_file($serviceFile)) {
        throw new RuntimeException(
            'Không tìm thấy service file: '
            . $serviceFile
        );
    }

    require_once $serviceFile;
}


/*
|--------------------------------------------------------------------------
| Application error handling
|--------------------------------------------------------------------------
*/

/**
 * Convert PHP warnings/notices into ErrorException.
 *
 * This allows the application to handle unexpected PHP errors
 * consistently instead of silently continuing with corrupted state.
 */
set_error_handler(
    static function (
        int $severity,
        string $message,
        string $file,
        int $line
    ): bool {
        /*
         * Respect the @ operator.
         */
        if (
            !(error_reporting() & $severity)
        ) {
            return false;
        }

        throw new ErrorException(
            $message,
            0,
            $severity,
            $file,
            $line
        );
    }
);


/**
 * Handle uncaught exceptions and fatal errors.
 *
 * Production:
 * - Do not expose internal paths or stack traces.
 * - Return a clean error response.
 *
 * Development:
 * - PHP's normal error output remains available.
 */
set_exception_handler(
    static function (
        Throwable $exception
    ): void {
        error_log(
            sprintf(
                '[THD] %s: %s in %s:%d',
                $exception::class,
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            )
        );

        $statusCode = 500;

        if (
            $exception instanceof ValidationException
        ) {
            $statusCode = 422;
        } elseif (
            $exception->getCode() >= 400
            && $exception->getCode() <= 599
        ) {
            $statusCode = $exception->getCode();
        }

        http_response_code($statusCode);

        /*
         * During development, keep the normal PHP exception
         * behavior useful for debugging.
         */
        if (
            defined('APP_ENV')
            && APP_ENV !== 'production'
        ) {
            echo '<pre>';
            echo e(
                $exception::class
            );
            echo "\n\n";
            echo e(
                $exception->getMessage()
            );
            echo "\n\n";
            echo e(
                $exception->getFile()
            );
            echo ':';
            echo e(
                (string) $exception->getLine()
            );
            echo "\n\n";
            echo e(
                $exception->getTraceAsString()
            );
            echo '</pre>';

            return;
        }

        /*
         * Production response.
         *
         * Do not expose:
         * - filesystem paths
         * - stack traces
         * - SQL/XML internals
         * - uploaded filenames
         * - session information
         */
        $title = match ($statusCode) {
            404 => 'Không tìm thấy nội dung',
            422 => 'Dữ liệu không hợp lệ',
            403 => 'Không có quyền truy cập',
            401 => 'Yêu cầu đăng nhập',
            default => 'Đã xảy ra lỗi',
        };

        $message = match ($statusCode) {
            404 =>
                'Nội dung bạn yêu cầu không tồn tại hoặc đã được di chuyển.',

            422 =>
                'Dữ liệu gửi lên không hợp lệ. Vui lòng kiểm tra lại.',

            403 =>
                'Bạn không có quyền thực hiện thao tác này.',

            401 =>
                'Vui lòng đăng nhập để tiếp tục.',

            default =>
                'Hệ thống đang gặp sự cố. Vui lòng thử lại sau.',
        };

        echo '<!doctype html>';
        echo '<html lang="vi">';
        echo '<head>';
        echo '<meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>';
        echo e($title);
        echo ' — ';
        echo e(APP_DISPLAY_NAME);
        echo '</title>';
        echo '</head>';
        echo '<body>';
        echo '<main>';
        echo '<h1>';
        echo e($title);
        echo '</h1>';
        echo '<p>';
        echo e($message);
        echo '</p>';
        echo '</main>';
        echo '</body>';
        echo '</html>';
    }
);


/*
|--------------------------------------------------------------------------
| Shutdown handling
|--------------------------------------------------------------------------
*/

/**
 * Convert fatal PHP errors into a controlled production response.
 *
 * The exception handler cannot catch every fatal error.
 */
register_shutdown_function(
    static function (): void {
        $error = error_get_last();

        if ($error === null) {
            return;
        }

        $fatalTypes = [
            E_ERROR,
            E_PARSE,
            E_CORE_ERROR,
            E_COMPILE_ERROR,
        ];

        if (
            !in_array(
                $error['type'],
                $fatalTypes,
                true
            )
        ) {
            return;
        }

        error_log(
            sprintf(
                '[THD FATAL] %s in %s:%d',
                $error['message'],
                $error['file'],
                $error['line']
            )
        );

        if (
            defined('APP_ENV')
            && APP_ENV === 'production'
        ) {
            if (!headers_sent()) {
                http_response_code(500);

                header(
                    'Content-Type: text/html; charset=UTF-8'
                );
            }

            echo '<!doctype html>';
            echo '<html lang="vi">';
            echo '<head>';
            echo '<meta charset="utf-8">';
            echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
            echo '<title>Lỗi hệ thống</title>';
            echo '</head>';
            echo '<body>';
            echo '<main>';
            echo '<h1>Đã xảy ra lỗi hệ thống</h1>';
            echo '<p>Vui lòng thử lại sau.</p>';
            echo '</main>';
            echo '</body>';
            echo '</html>';
        }
    }
);


/*
|--------------------------------------------------------------------------
| Prepare storage directories
|--------------------------------------------------------------------------
*/

$requiredDirectories = [
    STORAGE_PATH,
    XML_PATH,
    PUBLICATION_STORAGE_PATH,
    CACHE_PATH,
    UPLOAD_PATH,
];

foreach ($requiredDirectories as $directory) {
    if (!ensure_directory($directory)) {
        throw new RuntimeException(
            'Không thể tạo hoặc truy cập thư mục: '
            . $directory
        );
    }
}


/*
|--------------------------------------------------------------------------
| Security headers
|--------------------------------------------------------------------------
*/

send_security_headers();


/*
|--------------------------------------------------------------------------
| Bootstrap state
|--------------------------------------------------------------------------
*/

/**
 * Prevent bootstrap from being initialized multiple times
 * in the same request.
 */
if (!defined('THD_BOOTSTRAPPED')) {
    define(
        'THD_BOOTSTRAPPED',
        true
    );
}