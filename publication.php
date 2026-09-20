<?php
declare(strict_types=1);

require_once __DIR__ . '/core/bootstrap.php';

require_once THD_ROOT . '/repositories/PublicationRepository.php';
require_once THD_ROOT . '/repositories/SettingsRepository.php';
require_once THD_ROOT . '/services/PublicationService.php';
require_once THD_ROOT . '/services/ReaderService.php';

$settingsRepository = new SettingsRepository();
$publicationService = new PublicationService();
$readerService = new ReaderService();

$schoolName = (string) ($settingsRepository->get('school.name') ?? APP_SCHOOL_NAME);
$platformName = (string) ($settingsRepository->get('platform.name') ?? APP_DISPLAY_NAME);
$platformDescription = (string) ($settingsRepository->get('platform.description') ?? DEFAULT_META_DESCRIPTION);
$logo = (string) ($settingsRepository->get('school.logo') ?? '');
$favicon = (string) ($settingsRepository->get('platform.favicon') ?? '');

/*
 * Publication được xác định bằng slug.
 *
 * Ví dụ:
 * publication.php?slug=tap-san-ky-niem-60-nam
 */
$slug = get_string('slug');

if ($slug === null || $slug === '') {
    abort_not_found();
}

$publication = $publicationService->findPublicBySlug($slug);

if ($publication === null) {
    abort_not_found();
}

/*
 * Chuẩn hóa dữ liệu trước khi render.
 */
$publicationTitle = (string) ($publication['title'] ?? '');
$publicationSubtitle = (string) ($publication['subtitle'] ?? '');
$publicationYear = (int) ($publication['year'] ?? 0);
$publicationDescription = (string) ($publication['description'] ?? '');
$publicationCover = (string) ($publication['cover'] ?? '');
$pageCount = (int) ($publication['page_count'] ?? 0);
$author = (string) ($publication['author'] ?? '');
$editor = (string) ($publication['editor'] ?? '');
$language = (string) ($publication['language'] ?? '');
$publicationSlug = (string) ($publication['slug'] ?? $slug);
$publishedAt = (string) ($publication['published_at'] ?? '');

/*
 * ReaderService chịu trách nhiệm kiểm tra
 * quyền download / print / share.
 */
$allowDownload = $readerService->canDownload($publication);
$allowPrint = $readerService->canPrint($publication);
$allowShare = $readerService->canShare($publication);

/*
 * Reader source:
 * - pages
 * - pdf
 * - unavailable
 */
$readerSource = $readerService->detectReaderSource($publication);
$readerAvailable = $readerSource !== 'unavailable';

$pageTitle = $publicationTitle . ' — ' . $platformName;
$metaDescription = excerpt($publicationDescription !== '' ? $publicationDescription : $publicationTitle, 160);
$canonicalUrl = publication_url($publicationSlug);
$readerUrl = reader_url($publicationSlug);

/*
 * URL download không được tự ghép từ filename.
 * ReaderService sẽ kiểm tra permission ở endpoint sau này.
 */
$downloadUrl = app_url('download.php?slug=' . rawurlencode($publicationSlug));
$printUrl = app_url('print.php?slug=' . rawurlencode($publicationSlug));

$faviconUrl = $favicon !== '' ? app_url($favicon) : app_url('assets/favicon.ico');
$logoUrl = $logo !== '' ? app_url($logo) : app_url('assets/logo.webp');
$stylesheetUrl = app_url('assets/css/publication.css');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($metaDescription) ?>">
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">

    <link rel="icon" href="<?= e($faviconUrl) ?>">
    <link rel="stylesheet" href="<?= e($stylesheetUrl) ?>">

    <meta property="og:type" content="book">
    <meta property="og:title" content="<?= e($publicationTitle) ?>">
    <meta property="og:description" content="<?= e($metaDescription) ?>">
    <meta property="og:url" content="<?= e($canonicalUrl) ?>">

    <?php if ($publicationCover !== ''): ?>
        <meta property="og:image" content="<?= e(app_url($publicationCover)) ?>">
    <?php endif; ?>
</head>
<body>

<header class="site-header">
    <div class="site-header__inner">

        <a class="site-brand" href="<?= e(app_url('')) ?>" aria-label="<?= e($platformName) ?>">
            <img class="site-brand__logo" src="<?= e($logoUrl) ?>" alt="<?= e($schoolName) ?>">
            <span class="site-brand__text">
                <span class="site-brand__school"><?= e($schoolName) ?></span>
                <span class="site-brand__platform"><?= e($platformName) ?></span>
            </span>
        </a>

        <nav class="site-nav" aria-label="Điều hướng chính">
            <a href="<?= e(app_url('')) ?>">Trang chủ</a>
            <a href="<?= e(app_url('publications.php')) ?>">Thư viện</a>
            <a href="<?= e(app_url('search.php')) ?>">Tìm kiếm</a>
        </nav>

    </div>
</header>

<main>

    <article class="publication-detail">
        <div class="publication-detail__inner">

            <!-- Cover -->

            <div class="publication-detail__cover">
                <?php if ($publicationCover !== ''): ?>
                    <img src="<?= e(app_url($publicationCover)) ?>" alt="<?= e($publicationTitle) ?>">
                <?php else: ?>
                    <div class="publication-detail__cover-placeholder" aria-hidden="true">
                        <span><?= e($publicationYear > 0 ? (string) $publicationYear : 'ẤN PHẨM') ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Thông tin -->

            <div class="publication-detail__content">

                <p class="publication-detail__eyebrow">ẤN PHẨM SỐ</p>
                <h1><?= e($publicationTitle) ?></h1>

                <?php if ($publicationSubtitle !== ''): ?>
                    <p class="publication-detail__subtitle"><?= e($publicationSubtitle) ?></p>
                <?php endif; ?>

                <dl class="publication-meta">

                    <?php if ($publicationYear > 0): ?>
                        <div class="publication-meta__item">
                            <dt>Năm</dt>
                            <dd><?= e((string) $publicationYear) ?></dd>
                        </div>
                    <?php endif; ?>

                    <?php if ($pageCount > 0): ?>
                        <div class="publication-meta__item">
                            <dt>Số trang</dt>
                            <dd><?= e((string) $pageCount) ?></dd>
                        </div>
                    <?php endif; ?>

                    <?php if ($language !== ''): ?>
                        <div class="publication-meta__item">
                            <dt>Ngôn ngữ</dt>
                            <dd><?= e($language) ?></dd>
                        </div>
                    <?php endif; ?>

                    <?php if ($author !== ''): ?>
                        <div class="publication-meta__item">
                            <dt>Tác giả</dt>
                            <dd><?= e($author) ?></dd>
                        </div>
                    <?php endif; ?>

                    <?php if ($editor !== ''): ?>
                        <div class="publication-meta__item">
                            <dt>Biên tập</dt>
                            <dd><?= e($editor) ?></dd>
                        </div>
                    <?php endif; ?>

                    <?php if ($publishedAt !== ''): ?>
                        <div class="publication-meta__item">
                            <dt>Ngày xuất bản</dt>
                            <dd><?= e(format_date($publishedAt)) ?></dd>
                        </div>
                    <?php endif; ?>

                </dl>

                <?php if ($publicationDescription !== ''): ?>
                    <div class="publication-description">
                        <h2>Giới thiệu</h2>
                        <p><?= nl2br(e($publicationDescription)) ?></p>
                    </div>
                <?php endif; ?>

                <!-- Actions -->

                <div class="publication-actions">

                    <?php if ($readerAvailable): ?>
                        <a class="button button--primary" href="<?= e($readerUrl) ?>">Đọc ấn phẩm</a>
                    <?php else: ?>
                        <span class="button button--disabled" aria-disabled="true">Reader chưa khả dụng</span>
                    <?php endif; ?>

                    <?php if ($allowDownload): ?>
                        <a class="button button--secondary" href="<?= e($downloadUrl) ?>">Tải xuống</a>
                    <?php endif; ?>

                    <?php if ($allowPrint): ?>
                        <a class="button button--secondary" href="<?= e($printUrl) ?>">In ấn phẩm</a>
                    <?php endif; ?>

                    <?php if ($allowShare): ?>
                        <button type="button" class="button button--secondary" data-share-url="<?= e($canonicalUrl) ?>" data-share-title="<?= e($publicationTitle) ?>" data-share-publication>Chia sẻ</button>
                    <?php endif; ?>

                </div>

                <?php if (!$readerAvailable): ?>
                    <p class="publication-notice">Ấn phẩm hiện chưa có dữ liệu đọc trực tuyến.</p>
                <?php endif; ?>

            </div>

        </div>

        <!-- Preview -->

        <?php if ($readerAvailable): ?>

            <section class="publication-preview" aria-labelledby="preview-title">
                <div class="publication-preview__header">

                    <div>
                        <p class="section-eyebrow">XEM TRƯỚC</p>
                        <h2 id="preview-title">Đọc ấn phẩm</h2>
                    </div>

                    <a href="<?= e($readerUrl) ?>">Mở reader →</a>

                </div>

                <div class="publication-preview__content">
                    <?php if ($publicationCover !== ''): ?>
                        <a href="<?= e($readerUrl) ?>" aria-label="Mở reader <?= e($publicationTitle) ?>">
                            <img src="<?= e(app_url($publicationCover)) ?>" alt="<?= e($publicationTitle) ?>" loading="lazy">
                        </a>
                    <?php else: ?>
                        <a href="<?= e($readerUrl) ?>" class="publication-preview__placeholder">Mở reader</a>
                    <?php endif; ?>
                </div>
            </section>

        <?php endif; ?>

    </article>

</main>

<footer class="site-footer">
    <div class="site-footer__inner">

        <div>
            <strong><?= e($schoolName) ?></strong>
            <p><?= e($platformName) ?></p>
        </div>

        <p>© <?= e((string) date('Y')) ?> <?= e($schoolName) ?></p>

    </div>
</footer>

<script>
(function () {
    'use strict';

    const shareButton = document.querySelector('[data-share-publication]');

    if (!shareButton) {
        return;
    }

    shareButton.addEventListener('click', async function () {
        const url = shareButton.dataset.shareUrl || '';
        const title = shareButton.dataset.shareTitle || '';

        if (!url) {
            return;
        }

        if (navigator.share && typeof navigator.share === 'function') {
            try {
                await navigator.share({
                    title: title,
                    url: url
                });

                return;
            } catch (error) {
                /*
                 * Người dùng có thể đóng native share dialog.
                 * Không cần coi đây là lỗi hệ thống.
                 */
            }
        }

        try {
            await navigator.clipboard.writeText(url);

            shareButton.textContent = 'Đã sao chép liên kết';

            window.setTimeout(function () {
                shareButton.textContent = 'Chia sẻ';
            }, 2000);
        } catch (error) {
            window.prompt('Sao chép liên kết:', url);
        }
    });
})();
</script>

</body>
</html>