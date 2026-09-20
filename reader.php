<?php
declare(strict_types=1);

require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/repositories/PublicationRepository.php';
require_once __DIR__ . '/services/ReaderService.php';

// Repository / Service
$publicationRepository = new PublicationRepository();
$readerService = new ReaderService($publicationRepository);
$platformName = 'Thư viện Ấn phẩm số'; // Không dùng setting() vì bootstrap hiện tại không có helper này.

// Publication slug — hỗ trợ reader.php?publication=test hoặc reader.php?slug=test
$slug = trim((string) ($_GET['publication'] ?? $_GET['slug'] ?? ''));
if ($slug === '') { http_response_code(404); exit('Không tìm thấy ấn phẩm.'); }

// Find public publication
try {
    $publication = $readerService->findPublicPublication($slug);
} catch (Throwable $exception) {
    error_log('[THD Reader] findPublicPublication: ' . $exception->getMessage());
    http_response_code(500);
    exit('Không thể tải thông tin ấn phẩm.');
}
if (!is_array($publication) || $publication === []) { http_response_code(404); exit('Không tìm thấy ấn phẩm.'); }

// Basic publication data
$publicationId = trim((string) ($publication['id'] ?? ''));
$publicationSlug = trim((string) ($publication['slug'] ?? $slug));
$title = trim((string) ($publication['title'] ?? 'Ấn phẩm số'));
$subtitle = trim((string) ($publication['subtitle'] ?? ''));
if ($publicationId === '') { http_response_code(500); exit('Ấn phẩm không có Publication ID hợp lệ.'); }

// Resolve PDF
try {
    $pdf = $readerService->resolvePdf($publication);
} catch (Throwable $exception) {
    error_log('[THD Reader] resolvePdf: ' . $exception->getMessage());
    http_response_code(500);
    exit('Không thể xác định file PDF của ấn phẩm.');
}
if (!is_array($pdf)) { http_response_code(404); exit('Không tìm thấy file PDF của ấn phẩm.'); }

$pdfFilename = basename((string) ($pdf['filename'] ?? ''));
$pdfUrl = trim((string) ($pdf['url'] ?? ''));

// Nếu ReaderService trả filename nhưng chưa có URL, tạo URL protected qua reader-pdf.php.
if ($pdfFilename !== '' && $pdfUrl === '') {
    $pdfUrl = app_url('reader-pdf.php' . '?publication=' . rawurlencode($publicationId) . '&file=' . rawurlencode($pdfFilename));
}
if ($pdfFilename === '' || $pdfUrl === '') { http_response_code(404); exit('File PDF của ấn phẩm không hợp lệ.'); }

// Reader permissions
try {
    $readerSettings = $readerService->getReaderSettings($publication);
} catch (Throwable $exception) {
    error_log('[THD Reader] getReaderSettings: ' . $exception->getMessage());
    $readerSettings = [];
}
if (!is_array($readerSettings)) { $readerSettings = []; }

$permissions = [
    'download' => !empty($readerSettings['allow_download']),
    'print' => !empty($readerSettings['allow_print']),
    'share' => !empty($readerSettings['allow_share']),
];

$publicationUrl = publication_url($publicationSlug);
$pageCount = max(0, (int) ($publication['page_count'] ?? 0)); // Chỉ giữ page count làm metadata, KHÔNG dùng để điều khiển reader.

// 3D FlipBook assets
$flipbookJqueryUrl = app_url('assets/js/flipbook/jquery.min.js');
$flipbookHtml2CanvasUrl = app_url('assets/js/flipbook/html2canvas.min.js');
$flipbookThreeUrl = app_url('assets/js/flipbook/three.min.js');
$flipbookPdfUrl = app_url('assets/js/flipbook/pdf.min.js');
$flipbookWorkerUrl = app_url('assets/js/flipbook/pdf.worker.js');
$flipbookCoreUrl = app_url('assets/js/flipbook/3dflipbook.min.js');
$flipbookTemplateUrl = app_url('assets/templates/default-book-view.html');
$flipbookCssUrl = app_url('assets/css/flipbook/black-book-view.css');
$flipbookFontCssUrl = app_url('assets/css/flipbook/font-awesome.min.css');
$flipbookTemplateScriptUrl = app_url('assets/js/flipbook/default-book-view.js');

// Reader configuration — không còn currentPage / page từ GET / logic đổi URL theo trang.
$readerConfig = [
    'slug' => $publicationSlug,
    'publicationId' => $publicationId,
    'title' => $title,
    'subtitle' => $subtitle,
    'pdfUrl' => $pdfUrl,
    'pdfFilename' => $pdfFilename,
    'pageCount' => $pageCount,
    'publicationUrl' => $publicationUrl,
    'permissions' => $permissions,
];
?>
<!DOCTYPE html>
<html lang="vi">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?> — <?= e($platformName) ?></title>
<meta name="description" content="<?= e($subtitle !== '' ? $subtitle : $title) ?>">

<!-- 3D FLIPBOOK CSS -->
<link rel="stylesheet" href="<?= e($flipbookCssUrl) ?>">
<link rel="stylesheet" href="<?= e($flipbookFontCssUrl) ?>">

<!-- THD reader layout -->
<link rel="stylesheet" href="<?= e(app_url('assets/css/reader.css')) ?>">
<link rel="icon" href="<?= e(app_url('assets/favicon.ico')) ?>">
</head>

<body>

<div class="reader-page">

<!-- HEADER -->
<header class="reader-header">
<a class="reader-back" href="<?= e($publicationUrl) ?>" aria-label="Quay lại" title="Quay lại"><i class="fa fa-arrow-left"></i></a>
<div class="reader-heading">
<h1 class="reader-title"><?= e($title) ?></h1>
<?php if ($subtitle !== ''): ?><div class="reader-subtitle"><?= e($subtitle) ?></div><?php endif; ?>
</div>
<div class="reader-actions">
<?php if ($permissions['download']): ?><a class="reader-action" href="<?= e($pdfUrl) ?>" download="<?= e($pdfFilename) ?>" title="Tải PDF"><i class="fa fa-download"></i><span class="reader-action-label">Tải PDF</span></a><?php endif; ?>
<?php if ($permissions['print']): ?><button type="button" class="reader-action" data-reader-print title="In"><i class="fa fa-print"></i><span class="reader-action-label">In</span></button><?php endif; ?>
<?php if ($permissions['share']): ?><button type="button" class="reader-action" data-reader-share title="Chia sẻ"><i class="fa fa-share-alt"></i><span class="reader-action-label">Chia sẻ</span></button><?php endif; ?>
</div>
</header>

<!-- MAIN READER -->
<main class="reader-main">
<div class="flipbook-wrapper" data-flipbook-wrapper>
<!-- 3D FlipBook tự tạo toolbar mặc định. Không có toolbar custom THD ở đây. -->
<div id="thd-flipbook" class="thd-flipbook" data-flipbook></div>
</div>

<!-- Loading -->
<div class="reader-loading" data-reader-loading>
<div class="reader-spinner"></div>
<div class="reader-loading-text">Đang mở ấn phẩm…</div>
</div>

<!-- Error -->
<div class="reader-error" data-reader-error>
<div class="reader-error-card">
<h2 class="reader-error-title">Không thể mở ấn phẩm</h2>
<p class="reader-error-message" data-reader-error-message>Đã xảy ra lỗi khi tải tài liệu.</p>
</div>
</div>

<!-- Status -->
<div class="reader-status" data-reader-status></div>
</main>

</div>

<!-- GLOBAL READER CONFIG -->
<script>
window.THD_READER = <?= json_encode($readerConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>

<!-- PDF.JS WORKER CONFIG -->
<script>
window.PDFJS_LOCALE = { pdfJsWorker: <?= json_encode($flipbookWorkerUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?> };
</script>

<!-- FLIPBOOK LIBRARIES -->
<script src="<?= e($flipbookJqueryUrl) ?>"></script>
<script src="<?= e($flipbookHtml2CanvasUrl) ?>"></script>
<script src="<?= e($flipbookThreeUrl) ?>"></script>
<script src="<?= e($flipbookPdfUrl) ?>"></script>
<script src="<?= e($flipbookCoreUrl) ?>"></script>

<!-- INITIALIZATION -->
<script>
(function () {
'use strict';

const config = window.THD_READER || {};
const loading = document.querySelector('[data-reader-loading]');
const errorBox = document.querySelector('[data-reader-error]');
const errorMessage = document.querySelector('[data-reader-error-message]');
const statusBox = document.querySelector('[data-reader-status]');
let flipbook = null;

// Loading
function hideLoading() { if (loading) loading.classList.add('is-hidden'); }

// Error
function showError(message) {
    hideLoading();
    if (errorBox) errorBox.classList.add('is-visible');
    if (errorMessage) errorMessage.textContent = message || 'Không thể tải ấn phẩm.';
}

// Status
function showStatus(message) {
    if (!statusBox) return;
    statusBox.textContent = message || '';
    statusBox.classList.toggle('is-visible', Boolean(message));
}

// Print
function printDocument() {
    if (config.permissions && config.permissions.print) window.print();
}

// Share
async function shareDocument() {
    const shareData = {
        title: config.title || document.title,
        text: config.subtitle || config.title || '',
        url: window.location.href,
    };
    try {
        if (navigator.share) { await navigator.share(shareData); return; }
        if (navigator.clipboard) {
            await navigator.clipboard.writeText(window.location.href);
            showStatus('Đã sao chép liên kết.');
            window.setTimeout(function () { showStatus(''); }, 1800);
        }
    } catch (error) {
        if (error && error.name === 'AbortError') return;
    }
}

// Header button events
const printButton = document.querySelector('[data-reader-print]');
if (printButton) printButton.addEventListener('click', printDocument);
const shareButton = document.querySelector('[data-reader-share]');
if (shareButton) shareButton.addEventListener('click', shareDocument);

// Check jQuery
if (typeof window.jQuery === 'undefined') { showError('Không tải được jQuery của FlipBook.'); return; }

// Check FlipBook
if (typeof window.jQuery.fn.FlipBook !== 'function') { showError('Không tải được thư viện 3D FlipBook.'); return; }

// Check PDF URL
if (!config.pdfUrl || typeof config.pdfUrl !== 'string') { showError('Không xác định được file PDF.'); return; }

// Initialize 3D FlipBook
const $book = window.jQuery('#thd-flipbook');

try {
    flipbook = $book.FlipBook({
        pdf: config.pdfUrl,
        propertiesCallback: function (props) {
            props.rtl = false;
            props.cachedPages = 8;
            props.pagesForPredicting = 3;
            props.preloadPages = 3;
            props.renderInactivePages = false;
            props.renderInactivePagesOnMobile = false;
            props.renderWhileFlipping = false;
            props.bookStyle = 'volume';
            return props;
        },
        template: {
            html: <?= json_encode($flipbookTemplateUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
            styles: [<?= json_encode($flipbookCssUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>],
            links: [{ rel: 'stylesheet', href: <?= json_encode($flipbookFontCssUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?> }],
            script: <?= json_encode($flipbookTemplateScriptUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
        },
        ready: function (scene) {
            hideLoading();
            console.log('THD FlipBook ready:', scene);
            try {
                if (scene && scene.book) flipbook = scene.book;
            } catch (error) { /* Không ảnh hưởng reader. */ }
        },
        error: function (error) {
            console.error('THD FlipBook error:', error);
            showError(error && error.message ? error.message : 'Không thể tải file PDF.');
        }
    });

    console.log('THD FlipBook instance:', flipbook);
} catch (error) {
    console.error('THD FlipBook initialization error:', error);
    showError(error && error.message ? error.message : 'Không thể khởi tạo trình đọc ấn phẩm.');
}
})();
</script>

</body>
</html>