<?php
declare(strict_types=1);

require_once __DIR__ . '/core/bootstrap.php';

require_once THD_ROOT . '/repositories/PublicationRepository.php';
require_once THD_ROOT . '/repositories/SettingsRepository.php';
require_once THD_ROOT . '/services/SearchService.php';

$settingsRepository = new SettingsRepository();
$searchService = new SearchService();

$platformName = (string) ($settingsRepository->get('platform.name') ?? APP_DISPLAY_NAME);
$schoolName = (string) ($settingsRepository->get('school.name') ?? APP_SCHOOL_NAME);
$platformDescription = (string) ($settingsRepository->get('platform.description') ?? DEFAULT_META_DESCRIPTION);
$logo = (string) ($settingsRepository->get('school.logo') ?? '');
$favicon = (string) ($settingsRepository->get('platform.favicon') ?? '');

/*
 * Nhận filter từ URL.
 *
 * Ví dụ:
 * publications.php?q=60+năm
 * publications.php?year=2026
 * publications.php?sort=oldest
 * publications.php?page=2
 */
$query = get_string('q');
$year = get_int('year');
$sort = get_string('sort') ?? 'newest';
$page = get_int('page') ?? 1;

$filters = [
    'q' => $query,
    'year' => $year,
    'sort' => $sort,
    'page' => $page,
    'per_page' => DEFAULT_PUBLICATIONS_PER_PAGE,
];

$result = $searchService->search($filters);

$items = $result['items'] ?? [];
$totalItems = (int) ($result['total_items'] ?? 0);
$totalPages = (int) ($result['total_pages'] ?? 1);
$currentPage = (int) ($result['page'] ?? 1);
$availableYears = $searchService->getAvailableYears(true);

$pageTitle = 'Thư viện ấn phẩm — ' . $platformName;
$canonicalUrl = app_url('publications.php');

$faviconUrl = $favicon !== '' ? app_url($favicon) : app_url('assets/favicon.ico');
$logoUrl = $logo !== '' ? app_url($logo) : app_url('assets/logo.webp');
$stylesheetUrl = app_url('assets/css/publications.css');

/*
 * Helper để giữ filter hiện tại khi chuyển trang.
 */
function publicationQueryUrl(
    int $page,
    ?string $query,
    ?int $year,
    string $sort
): string {
    $params = [
        'page' => $page,
    ];

    if ($query !== null && $query !== '') {
        $params['q'] = $query;
    }

    if ($year !== null && $year > 0) {
        $params['year'] = $year;
    }

    if ($sort !== '') {
        $params['sort'] = $sort;
    }

    return app_url('publications.php?' . http_build_query($params));
}
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
            <a href="<?= e(app_url('publications.php')) ?>" aria-current="page">Thư viện</a>
            <a href="<?= e(app_url('search.php')) ?>">Tìm kiếm</a>
        </nav>

    </div>
</header>

<main>

    <section class="library-header">
        <div class="library-header__inner">

            <p class="section-eyebrow">THƯ VIỆN</p>
            <h1>Ấn phẩm số</h1>
            <p>Khám phá các ấn phẩm đã được xuất bản trong thư viện số của nhà trường.</p>

        </div>
    </section>

    <section class="library" aria-labelledby="library-title">
        <div class="library__inner">

            <h2 id="library-title" class="visually-hidden">Danh sách ấn phẩm</h2>

            <!-- Bộ lọc -->

            <form class="library-filters" method="get" action="<?= e(app_url('publications.php')) ?>">

                <div class="filter-field">
                    <label for="publication-search">Tìm kiếm</label>
                    <input type="search" id="publication-search" name="q" value="<?= e($query ?? '') ?>" placeholder="Tên ấn phẩm, tác giả..." maxlength="200">
                </div>

                <div class="filter-field">
                    <label for="publication-year">Năm</label>
                    <select id="publication-year" name="year">
                        <option value="">Tất cả năm</option>
                        <?php foreach ($availableYears as $availableYear): ?>
                            <option value="<?= e((string) $availableYear) ?>" <?= ($year === (int) $availableYear ? 'selected' : '') ?>><?= e((string) $availableYear) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-field">
                    <label for="publication-sort">Sắp xếp</label>
                    <select id="publication-sort" name="sort">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Mới nhất</option>
                        <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Cũ nhất</option>
                        <option value="title_asc" <?= $sort === 'title_asc' ? 'selected' : '' ?>>Tên A–Z</option>
                        <option value="title_desc" <?= $sort === 'title_desc' ? 'selected' : '' ?>>Tên Z–A</option>
                    </select>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="button button--primary">Lọc</button>
                    <a href="<?= e(app_url('publications.php')) ?>" class="button button--secondary">Xóa lọc</a>
                </div>

            </form>

            <!-- Kết quả -->

            <div class="library-results">

                <div class="library-results__summary">
                    <?php if ($totalItems > 0): ?>
                        <p>Tìm thấy <strong><?= e((string) $totalItems) ?></strong> ấn phẩm.</p>
                    <?php else: ?>
                        <p>Không tìm thấy ấn phẩm phù hợp.</p>
                    <?php endif; ?>
                </div>

                <?php if ($items === []): ?>

                    <div class="empty-state">
                        <h2>Chưa có kết quả</h2>
                        <?php if (($query !== null && $query !== '') || $year !== null): ?>
                            <p>Hãy thử thay đổi từ khóa hoặc bộ lọc năm.</p>
                        <?php else: ?>
                            <p>Thư viện hiện chưa có ấn phẩm được xuất bản.</p>
                        <?php endif; ?>
                    </div>

                <?php else: ?>

                    <div class="publication-grid">
                        <?php foreach ($items as $publication): ?>
                            <?php
                            $slug = (string) ($publication['slug'] ?? '');
                            $title = (string) ($publication['title'] ?? '');
                            $subtitle = (string) ($publication['subtitle'] ?? '');
                            $yearValue = (int) ($publication['year'] ?? 0);
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

                                    <p class="publication-card__year"><?= e((string) $yearValue) ?></p>

                                    <h2 class="publication-card__title">
                                        <a href="<?= e(publication_url($slug)) ?>"><?= e($title) ?></a>
                                    </h2>

                                    <?php if ($subtitle !== ''): ?>
                                        <p class="publication-card__subtitle"><?= e($subtitle) ?></p>
                                    <?php endif; ?>

                                    <?php if ($description !== ''): ?>
                                        <p class="publication-card__description"><?= e(excerpt($description, 160)) ?></p>
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

                    <!-- Pagination -->

                    <?php if ($totalPages > 1): ?>

                        <nav class="pagination" aria-label="Phân trang">

                            <?php if ($currentPage > 1): ?>
                                <a href="<?= e(publicationQueryUrl($currentPage - 1, $query, $year, $sort)) ?>" rel="prev">← Trước</a>
                            <?php endif; ?>

                            <div class="pagination__pages">
                                <?php
                                $startPage = max(1, $currentPage - 2);
                                $endPage = min($totalPages, $currentPage + 2);
                                ?>

                                <?php for ($pageNumber = $startPage; $pageNumber <= $endPage; $pageNumber++): ?>
                                    <?php if ($pageNumber === $currentPage): ?>
                                        <span aria-current="page"><?= e((string) $pageNumber) ?></span>
                                    <?php else: ?>
                                        <a href="<?= e(publicationQueryUrl($pageNumber, $query, $year, $sort)) ?>"><?= e((string) $pageNumber) ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </div>

                            <?php if ($currentPage < $totalPages): ?>
                                <a href="<?= e(publicationQueryUrl($currentPage + 1, $query, $year, $sort)) ?>" rel="next">Sau →</a>
                            <?php endif; ?>

                        </nav>

                    <?php endif; ?>

                <?php endif; ?>

            </div>

        </div>
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