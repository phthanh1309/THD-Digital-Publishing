<?php
declare(strict_types=1);

require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/repositories/SettingsRepository.php';
require_once __DIR__ . '/services/SearchService.php';

$settingsRepository = new SettingsRepository();
$searchService = new SearchService();

$platformName = (string) ($settingsRepository->get('platform.name') ?? APP_DISPLAY_NAME);
$platformDescription = (string) ($settingsRepository->get('platform.description') ?? DEFAULT_META_DESCRIPTION);
$logo = (string) ($settingsRepository->get('school.logo') ?? '');
$favicon = (string) ($settingsRepository->get('platform.favicon') ?? '');

$query = get_string('q');
$year = get_int('year', 0);
$sort = get_string('sort') ?: 'newest';
$page = max(1, get_int('page', 1));

$filters = [
    'q' => $query,
    'year' => $year > 0 ? $year : null,
    'sort' => $sort,
    'page' => $page,
    'per_page' => DEFAULT_PUBLICATIONS_PER_PAGE,
];

$result = $searchService->search($filters);

$items = is_array($result['items'] ?? null) ? $result['items'] : [];
$perPage = max(1, (int) ($result['per_page'] ?? DEFAULT_PUBLICATIONS_PER_PAGE));
// Chấp nhận nhiều tên key khác nhau mà SearchService có thể trả về
$totalItems = (int) ($result['total_items'] ?? $result['total'] ?? $result['totalItems'] ?? $result['count'] ?? count($items));
$totalPages = (int) ($result['total_pages'] ?? $result['pages'] ?? $result['totalPages'] ?? ceil($totalItems / $perPage));
$totalPages = max(1, $totalPages);
$currentPage = (int) ($result['page'] ?? $result['current_page'] ?? $page);
$hasPrevious = (bool) ($result['has_previous'] ?? $currentPage > 1);
$hasNext = (bool) ($result['has_next'] ?? $currentPage < $totalPages);

$availableYears = $searchService->getAvailableYears(true);

/**
 * Build a search URL while preserving the current filters.
 */
function searchQueryUrl(string $query, ?int $year, string $sort, int $page): string
{
    $params = [];

    if ($query !== '') {
        $params['q'] = $query;
    }

    if ($year !== null && $year > 0) {
        $params['year'] = $year;
    }

    if ($sort !== '') {
        $params['sort'] = $sort;
    }

    if ($page > 1) {
        $params['page'] = $page;
    }

    $queryString = http_build_query($params);

    return app_url('search.php' . ($queryString !== '' ? '?' . $queryString : ''));
}

$searchTitle = $query !== '' ? 'Tìm kiếm: ' . $query : 'Tìm kiếm ấn phẩm';
$pageTitle = $searchTitle . ' | ' . $platformName;

$canonicalUrl = searchQueryUrl($query, $year > 0 ? $year : null, $sort, $currentPage);

$faviconUrl = $favicon !== '' ? app_url($favicon) : app_url('assets/favicon.ico');
$logoUrl = $logo !== '' ? app_url($logo) : app_url('assets/logo.webp');
$stylesheetUrl = app_url('assets/css/search.css');
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
    <div class="site-header-inner">

        <a class="site-brand" href="<?= e(app_url()) ?>" aria-label="<?= e(APP_SCHOOL_NAME) ?>">
            <img class="site-brand-logo" src="<?= e($logoUrl) ?>" alt="<?= e(APP_SCHOOL_NAME) ?>">
            <span class="site-brand-text">
                <span class="site-brand-school"><?= e(APP_SCHOOL_NAME) ?></span>
                <span class="site-brand-name"><?= e($platformName) ?></span>
            </span>
        </a>

        <nav class="site-nav" aria-label="Điều hướng chính">
            <a href="<?= e(app_url()) ?>">Trang chủ</a>
            <a href="<?= e(app_url('publications.php')) ?>">Thư viện</a>
            <a href="<?= e(app_url('search.php')) ?>" aria-current="page">Tìm kiếm</a>
        </nav>

    </div>
</header>

<main class="search-page">

    <section class="search-header">
        <div class="search-header-inner">

            <p class="eyebrow">THƯ VIỆN ẤN PHẨM SỐ</p>
            <h1><?= e($searchTitle) ?></h1>
            <p>Tìm kiếm các ấn phẩm đã được công bố trong thư viện.</p>

        </div>
    </section>

    <section class="search-section">
        <div class="search-container">

            <form class="search-form" method="get" action="<?= e(app_url('search.php')) ?>">

                <div class="search-form-main">
                    <label for="search-query">Từ khóa</label>
                    <input id="search-query" name="q" type="search" value="<?= e($query) ?>" placeholder="Nhập tên ấn phẩm, tác giả, nội dung..." autocomplete="off">
                </div>

                <div class="search-form-field">
                    <label for="search-year">Năm</label>
                    <select id="search-year" name="year">
                        <option value="">Tất cả các năm</option>
                        <?php foreach ($availableYears as $availableYear): ?>
                            <?php $availableYear = (int) $availableYear; ?>
                            <option value="<?= $availableYear ?>" <?= $year === $availableYear ? 'selected' : '' ?>><?= $availableYear ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="search-form-field">
                    <label for="search-sort">Sắp xếp</label>
                    <select id="search-sort" name="sort">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Mới nhất</option>
                        <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Cũ nhất</option>
                        <option value="title_asc" <?= $sort === 'title_asc' ? 'selected' : '' ?>>Tên A–Z</option>
                        <option value="title_desc" <?= $sort === 'title_desc' ? 'selected' : '' ?>>Tên Z–A</option>
                    </select>
                </div>

                <div class="search-form-actions">
                    <button type="submit" class="button button-primary">Tìm kiếm</button>
                    <?php if ($query !== '' || $year > 0 || $sort !== 'newest'): ?>
                        <a href="<?= e(app_url('search.php')) ?>" class="button button-secondary">Xóa bộ lọc</a>
                    <?php endif; ?>
                </div>

            </form>

        </div>
    </section>

    <section class="search-results">
        <div class="search-container">

            <div class="search-results-header">
                <div>
                    <h2>Kết quả tìm kiếm</h2>
                    <p><?= number_format($totalItems, 0, ',', '.') ?> ấn phẩm được tìm thấy.</p>
                </div>
            </div>

            <?php if ($items === []): ?>

                <div class="empty-state">
                    <h2>Không tìm thấy ấn phẩm</h2>
                    <p>Không có ấn phẩm phù hợp với điều kiện tìm kiếm. Hãy thử từ khóa khác hoặc bỏ bớt bộ lọc.</p>
                    <a href="<?= e(app_url('publications.php')) ?>" class="button button-secondary">Xem toàn bộ thư viện</a>
                </div>

            <?php else: ?>

                <div class="publication-grid">
                    <?php foreach ($items as $publication): ?>
                        <?php
                        $slug = (string) ($publication['slug'] ?? '');
                        $title = (string) ($publication['title'] ?? '');
                        $subtitle = (string) ($publication['subtitle'] ?? '');
                        $description = (string) ($publication['description'] ?? '');
                        $publicationYear = (int) ($publication['year'] ?? 0);
                        $cover = (string) ($publication['cover'] ?? '');
                        ?>

                        <article class="publication-card">

                            <a class="publication-card-cover" href="<?= e(publication_url($slug)) ?>" aria-label="Xem <?= e($title) ?>">
                                <?php if ($cover !== ''): ?>
                                    <img src="<?= e(app_url($cover)) ?>" alt="<?= e($title) ?>" loading="lazy">
                                <?php else: ?>
                                    <div class="publication-card-cover-placeholder" aria-hidden="true">
                                        <span><?= e((string) $publicationYear) ?></span>
                                    </div>
                                <?php endif; ?>
                            </a>

                            <div class="publication-card-body">

                                <p class="publication-card-year"><?= $publicationYear > 0 ? e((string) $publicationYear) : 'Chưa xác định năm' ?></p>

                                <h2 class="publication-card-title">
                                    <a href="<?= e(publication_url($slug)) ?>"><?= e($title) ?></a>
                                </h2>

                                <?php if ($subtitle !== ''): ?>
                                    <p class="publication-card-subtitle"><?= e($subtitle) ?></p>
                                <?php endif; ?>

                                <?php if ($description !== ''): ?>
                                    <p class="publication-card-description"><?= e(excerpt($description, 150)) ?></p>
                                <?php endif; ?>

                                <a class="publication-card-link" href="<?= e(publication_url($slug)) ?>">Xem ấn phẩm</a>

                            </div>

                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>

                    <nav class="pagination" aria-label="Phân trang kết quả tìm kiếm">

                        <?php if ($hasPrevious): ?>
                            <a class="pagination-link pagination-prev" href="<?= e(searchQueryUrl($query, $year > 0 ? $year : null, $sort, $currentPage - 1)) ?>" rel="prev">← Trang trước</a>
                        <?php endif; ?>

                        <div class="pagination-pages">
                            <?php
                            $startPage = max(1, $currentPage - 2);
                            $endPage = min($totalPages, $currentPage + 2);
                            ?>

                            <?php if ($startPage > 1): ?>
                                <a class="pagination-link" href="<?= e(searchQueryUrl($query, $year > 0 ? $year : null, $sort, 1)) ?>">1</a>
                                <?php if ($startPage > 2): ?>
                                    <span class="pagination-ellipsis" aria-hidden="true">…</span>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($paginationPage = $startPage; $paginationPage <= $endPage; $paginationPage++): ?>
                                <?php if ($paginationPage === $currentPage): ?>
                                    <span class="pagination-link is-current" aria-current="page"><?= $paginationPage ?></span>
                                <?php else: ?>
                                    <a class="pagination-link" href="<?= e(searchQueryUrl($query, $year > 0 ? $year : null, $sort, $paginationPage)) ?>"><?= $paginationPage ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?>
                                    <span class="pagination-ellipsis" aria-hidden="true">…</span>
                                <?php endif; ?>
                                <a class="pagination-link" href="<?= e(searchQueryUrl($query, $year > 0 ? $year : null, $sort, $totalPages)) ?>"><?= $totalPages ?></a>
                            <?php endif; ?>
                        </div>

                        <?php if ($hasNext): ?>
                            <a class="pagination-link pagination-next" href="<?= e(searchQueryUrl($query, $year > 0 ? $year : null, $sort, $currentPage + 1)) ?>" rel="next">Trang sau →</a>
                        <?php endif; ?>

                    </nav>

                <?php endif; ?>

            <?php endif; ?>

        </div>
    </section>

</main>

<footer class="site-footer">
    <div class="site-footer-inner">

        <p><?= e(APP_SCHOOL_NAME) ?></p>
        <p><?= e($platformName) ?></p>

    </div>
</footer>

</body>
</html>