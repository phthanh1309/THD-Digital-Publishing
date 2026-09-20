<?php
declare(strict_types=1);

require_once __DIR__ . '/core/bootstrap.php';

require_once THD_ROOT . '/repositories/PublicationRepository.php';
require_once THD_ROOT . '/repositories/SettingsRepository.php';
require_once THD_ROOT . '/services/PublicationService.php';

$settingsRepository = new SettingsRepository();
$publicationService = new PublicationService();

$schoolName = (string) ($settingsRepository->get('school.name') ?? APP_SCHOOL_NAME);
$platformName = (string) ($settingsRepository->get('platform.name') ?? APP_DISPLAY_NAME);
$platformDescription = (string) ($settingsRepository->get('platform.description') ?? DEFAULT_META_DESCRIPTION);
$logo = (string) ($settingsRepository->get('school.logo') ?? '');
$favicon = (string) ($settingsRepository->get('platform.favicon') ?? '');

$publications = $publicationService->getPublished();

// Chỉ lấy 6 ấn phẩm mới nhất; không tạo số liệu giả.
$latestPublications = array_slice($publications, 0, 6);

$pageTitle = $platformName . ' — ' . $schoolName;
$canonicalUrl = app_url('');

$faviconUrl = $favicon !== '' ? app_url($favicon) : app_url('assets/favicon.ico');
$logoUrl = $logo !== '' ? app_url($logo) : app_url('assets/logo.webp');
$stylesheetUrl = app_url('assets/css/index.css');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($platformDescription) ?>">
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <link rel="icon" href="<?= e($faviconUrl) ?>">
    <link rel="stylesheet" href="<?= e($stylesheetUrl) ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($platformDescription) ?>">
    <meta property="og:url" content="<?= e($canonicalUrl) ?>">
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

    <section class="hero">
        <div class="hero__inner">

            <p class="hero__eyebrow">THƯ VIỆN ẤN PHẨM SỐ</p>
            <h1 class="hero__title"><?= e($platformName) ?></h1>
            <p class="hero__description"><?= e($platformDescription) ?></p>

            <div class="hero__actions">
                <a class="button button--primary" href="<?= e(app_url('publications.php')) ?>">Khám phá thư viện</a>
                <a class="button button--secondary" href="<?= e(app_url('search.php')) ?>">Tìm kiếm ấn phẩm</a>
            </div>

        </div>
    </section>

    <section class="publication-section" aria-labelledby="latest-publications-title">
        <div class="publication-section__header">

            <div>
                <p class="section-eyebrow">ẤN PHẨM</p>
                <h2 id="latest-publications-title">Ấn phẩm mới nhất</h2>
            </div>

            <a href="<?= e(app_url('publications.php')) ?>" class="section-link">Xem toàn bộ thư viện</a>

        </div>

        <?php if ($latestPublications === []): ?>

            <div class="empty-state">
                <h3>Thư viện đang được cập nhật</h3>
                <p>Hiện chưa có ấn phẩm nào được xuất bản.</p>
            </div>

        <?php else: ?>

            <div class="publication-grid">
                <?php foreach ($latestPublications as $publication): ?>
                    <?php
                    $slug = (string) ($publication['slug'] ?? '');
                    $title = (string) ($publication['title'] ?? '');
                    $subtitle = (string) ($publication['subtitle'] ?? '');
                    $year = (int) ($publication['year'] ?? 0);
                    $description = (string) ($publication['description'] ?? '');
                    $cover = (string) ($publication['cover'] ?? '');
                    $pageCount = (int) ($publication['page_count'] ?? 0);
                    ?>

                    <article class="publication-card">

                        <a class="publication-card__cover" href="<?= e(publication_url($slug)) ?>" aria-label="Xem <?= e($title) ?>">
                            <?php if ($cover !== ''): ?>
                                <img src="<?= e(app_url($cover)) ?>" alt="<?= e($title) ?>" loading="lazy">
                            <?php else: ?>
                                <span class="publication-card__cover-placeholder" aria-hidden="true">ẤN PHẨM</span>
                            <?php endif; ?>
                        </a>

                        <div class="publication-card__content">

                            <p class="publication-card__year"><?= e((string) $year) ?></p>

                            <h3 class="publication-card__title">
                                <a href="<?= e(publication_url($slug)) ?>"><?= e($title) ?></a>
                            </h3>

                            <?php if ($subtitle !== ''): ?>
                                <p class="publication-card__subtitle"><?= e($subtitle) ?></p>
                            <?php endif; ?>

                            <?php if ($description !== ''): ?>
                                <p class="publication-card__description"><?= e(excerpt($description, 140)) ?></p>
                            <?php endif; ?>

                            <div class="publication-card__meta">
                                <?php if ($pageCount > 0): ?>
                                    <span><?= e((string) $pageCount) ?> trang</span>
                                <?php endif; ?>
                                <a href="<?= e(publication_url($slug)) ?>">Xem ấn phẩm →</a>
                            </div>

                        </div>

                    </article>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>

    </section>

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

</body>
</html>