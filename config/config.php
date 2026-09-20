<?php

declare(strict_types=1);

/**
 * THD Digital Publishing
 * Global application configuration.
 */

if (!defined('THD_ROOT')) {
    define('THD_ROOT', dirname(__DIR__));
}

/*
|--------------------------------------------------------------------------
| Application
|--------------------------------------------------------------------------
*/

define('APP_NAME', 'THD Digital Publishing');
define('APP_DISPLAY_NAME', 'Thư viện Ấn phẩm số');
define('APP_SCHOOL_NAME', 'THPT A Trần Hưng Đạo');

define('APP_ENV', 'development');

/*
|--------------------------------------------------------------------------
| URL
|--------------------------------------------------------------------------
|
| Leave APP_URL empty when the application should determine the base URL
| automatically.
|
| In production on shared hosting, it can be explicitly configured:
|
| define('APP_URL', 'https://example.com');
|
*/

define('APP_URL', '');

/*
|--------------------------------------------------------------------------
| Timezone
|--------------------------------------------------------------------------
*/

date_default_timezone_set('Asia/Ho_Chi_Minh');

/*
|--------------------------------------------------------------------------
| Storage paths
|--------------------------------------------------------------------------
*/

define('STORAGE_PATH', THD_ROOT . DIRECTORY_SEPARATOR . 'storage');
define('XML_PATH', STORAGE_PATH . DIRECTORY_SEPARATOR . 'xml');
define('PUBLICATION_STORAGE_PATH', STORAGE_PATH . DIRECTORY_SEPARATOR . 'publications');
define('CACHE_PATH', STORAGE_PATH . DIRECTORY_SEPARATOR . 'cache');

define(
    'UPLOAD_PATH',
    THD_ROOT . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'publications'
);

/*
|--------------------------------------------------------------------------
| XML database files
|--------------------------------------------------------------------------
*/

define(
    'PUBLICATIONS_XML',
    XML_PATH . DIRECTORY_SEPARATOR . 'publications.xml'
);

define(
    'USERS_XML',
    XML_PATH . DIRECTORY_SEPARATOR . 'users.xml'
);

define(
    'SETTINGS_XML',
    XML_PATH . DIRECTORY_SEPARATOR . 'settings.xml'
);

define(
    'LOGS_XML',
    XML_PATH . DIRECTORY_SEPARATOR . 'logs.xml'
);

/*
|--------------------------------------------------------------------------
| Cache
|--------------------------------------------------------------------------
*/

define('CACHE_ENABLED', true);

define(
    'PUBLICATION_CACHE_FILE',
    CACHE_PATH . DIRECTORY_SEPARATOR . 'publications.cache.php'
);

/*
|--------------------------------------------------------------------------
| Upload limits
|--------------------------------------------------------------------------
|
| These are application-level limits.
| PHP's upload_max_filesize and post_max_size must also permit the upload.
|
*/

define('MAX_PDF_SIZE', 100 * 1024 * 1024); // 100 MB
define('MAX_COVER_SIZE', 10 * 1024 * 1024); // 10 MB

/*
|--------------------------------------------------------------------------
| Allowed upload types
|--------------------------------------------------------------------------
*/

define(
    'ALLOWED_PDF_EXTENSIONS',
    [
        'pdf',
    ]
);

define(
    'ALLOWED_COVER_EXTENSIONS',
    [
        'jpg',
        'jpeg',
        'png',
        'webp',
    ]
);

/*
|--------------------------------------------------------------------------
| Publication settings
|--------------------------------------------------------------------------
*/

define(
    'PUBLICATION_STATUSES',
    [
        'draft',
        'published',
        'archived',
    ]
);

define('DEFAULT_PUBLICATION_STATUS', 'draft');

define('DEFAULT_PUBLICATIONS_PER_PAGE', 12);

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

define('ADMIN_SESSION_NAME', 'thd_admin_session');

define('ADMIN_SESSION_TIMEOUT', 60 * 60); // 1 hour

/*
|--------------------------------------------------------------------------
| Security
|--------------------------------------------------------------------------
*/

define('CSRF_TOKEN_NAME', 'csrf_token');

define('PASSWORD_MIN_LENGTH', 10);

/*
|--------------------------------------------------------------------------
| Reader
|--------------------------------------------------------------------------
*/

define('DEFAULT_READER_PAGE', 1);

define('READER_PRELOAD_PAGES', 2);

define('READER_MIN_ZOOM', 0.5);
define('READER_MAX_ZOOM', 3.0);
define('READER_DEFAULT_ZOOM', 1.0);

/*
|--------------------------------------------------------------------------
| SEO
|--------------------------------------------------------------------------
*/

define(
    'DEFAULT_META_DESCRIPTION',
    'Thư viện Ấn phẩm số của THPT A Trần Hưng Đạo.'
);

/*
|--------------------------------------------------------------------------
| Error handling
|--------------------------------------------------------------------------
*/

if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}