<?php

declare(strict_types=1);

/**
 * THD Digital Publishing
 *
 * Reader Service.
 *
 * Responsibilities:
 * - Resolve public publication
 * - Validate requested page
 * - Resolve reader page assets
 * - Resolve source PDF
 * - Enforce reader permissions
 * - Build reader state for the frontend
 * - Provide safe URLs/paths for reader resources
 *
 * This service must NOT:
 * - Render HTML
 * - Render JavaScript
 * - Read XML directly
 * - Handle authentication
 * - Handle CSRF
 * - Generate page images
 *
 * PDF/page generation belongs to the reader asset pipeline.
 */

require_once THD_ROOT
    . DIRECTORY_SEPARATOR
    . 'repositories'
    . DIRECTORY_SEPARATOR
    . 'PublicationRepository.php';

class ReaderService
{
    private PublicationRepository $repository;

    /**
     * @param PublicationRepository|null $repository
     */
    public function __construct(
        ?PublicationRepository $repository = null
    ) {
        $this->repository =
            $repository
            ?? new PublicationRepository();
    }

    /*
    |--------------------------------------------------------------------------
    | Reader state
    |--------------------------------------------------------------------------
    */

    /**
     * Build the complete initial reader state.
     *
     * @return array<string, mixed>
     */
    public function getReaderState(
        string $slug,
        int $page = DEFAULT_READER_PAGE
    ): array {
        $publication =
            $this->findPublicPublication($slug);

        if ($publication === null) {
            throw new RuntimeException(
                'Không tìm thấy ấn phẩm.'
            );
        }

        $page =
            $this->normalizePage($page);

        $pageCount =
            $this->getPageCount($publication);

        /*
         * If page_count is known, clamp the requested page.
         */
        if ($pageCount > 0) {
            $page = min(
                $page,
                $pageCount
            );
        }

        $settings =
            $this->getReaderSettings(
                $publication
            );

        $currentPage =
            $this->getPage(
                $publication,
                $page
            );

        $readerSource =
            $this->detectReaderSource(
                $publication
            );

        return [
            'publication' => [
                'id' =>
                    (string) (
                        $publication['id']
                        ?? ''
                    ),

                'slug' =>
                    (string) (
                        $publication['slug']
                        ?? ''
                    ),

                'title' =>
                    (string) (
                        $publication['title']
                        ?? ''
                    ),

                'subtitle' =>
                    (string) (
                        $publication['subtitle']
                        ?? ''
                    ),

                'year' =>
                    (int) (
                        $publication['year']
                        ?? 0
                    ),

                'description' =>
                    (string) (
                        $publication['description']
                        ?? ''
                    ),

                'cover' =>
                    $this->buildAssetUrl(
                        $publication['cover']
                        ?? ''
                    ),

                'page_count' =>
                    $pageCount,

                'published_at' =>
                    (string) (
                        $publication['published_at']
                        ?? ''
                    ),
            ],

            'reader' => [
                'current_page' =>
                    $page,

                'page_count' =>
                    $pageCount,

                'has_previous' =>
                    $page > 1,

                /*
                 * Khi page_count = 0 nhưng PDF tồn tại,
                 * vẫn cho phép frontend đi tiếp.
                 */
                'has_next' =>
                    $pageCount > 0
                        ? $page < $pageCount
                        : $readerSource === 'pdf',

                'previous_page' =>
                    $page > 1
                        ? $page - 1
                        : null,

                'next_page' =>
                    $pageCount > 0
                        && $page < $pageCount
                        ? $page + 1
                        : null,

                'first_page' =>
                    1,

                'last_page' =>
                    $pageCount > 0
                        ? $pageCount
                        : null,

                'deep_link' =>
                    reader_url(
                        (string) (
                            $publication['slug']
                            ?? ''
                        ),
                        $page
                    ),
            ],

            'page' =>
                $currentPage,

            'permissions' =>
                $settings,

            'source' => [
                'type' =>
                    $readerSource,

                /*
                 * Trả thêm PDF URL nếu PDF tồn tại.
                 * Frontend có thể dùng trực tiếp cho PDF.js.
                 */
                'pdf' =>
                    $this->resolvePdf(
                        $publication
                    ),
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Publication
    |--------------------------------------------------------------------------
    */

    /**
     * Find a publication that is publicly readable.
     *
     * @return array<string, mixed>|null
     */
    public function findPublicPublication(
        string $slug
    ): ?array {
        $slug = trim($slug);

        if ($slug === '') {
            return null;
        }

        $publication =
            $this->repository->findBySlug(
                $slug
            );

        if ($publication === null) {
            return null;
        }

        if (
            !is_publication_public(
                $publication
            )
        ) {
            return null;
        }

        return $publication;
    }

    /**
     * Backward-compatible alias for reader endpoints.
     *
     * Some reader endpoints use getPublicPublication()
     * while the service internally exposes
     * findPublicPublication().
     *
     * @return array<string, mixed>|null
     */
    public function getPublicPublication(
        string $slug
    ): ?array {
        return $this->findPublicPublication($slug);
    }

    /*
    |--------------------------------------------------------------------------
    | Page
    |--------------------------------------------------------------------------
    */

    /**
     * Get one reader page.
     *
     * @return array<string, mixed>
     */
    public function getPage(
        array $publication,
        int $page
    ): array {
        $page =
            $this->normalizePage($page);

        $pageCount =
            $this->getPageCount(
                $publication
            );

        if (
            $pageCount > 0
            && $page > $pageCount
        ) {
            throw new OutOfBoundsException(
                'Trang yêu cầu không tồn tại.'
            );
        }

        $publicationId =
            (string) (
                $publication['id']
                ?? ''
            );

        if ($publicationId === '') {
            throw new RuntimeException(
                'Publication ID không hợp lệ.'
            );
        }

        /*
         * Generated page assets:
         *
         * storage/publications/pub_xxx/pages/page-0001.jpg
         */
        $pageDirectory =
            PUBLICATION_STORAGE_PATH
            . DIRECTORY_SEPARATOR
            . $publicationId
            . DIRECTORY_SEPARATOR
            . 'pages';

        $candidates = [
            $pageDirectory
                . DIRECTORY_SEPARATOR
                . sprintf(
                    'page-%04d.jpg',
                    $page
                ),

            $pageDirectory
                . DIRECTORY_SEPARATOR
                . sprintf(
                    'page-%04d.jpeg',
                    $page
                ),

            $pageDirectory
                . DIRECTORY_SEPARATOR
                . sprintf(
                    'page-%04d.webp',
                    $page
                ),

            $pageDirectory
                . DIRECTORY_SEPARATOR
                . sprintf(
                    'page-%04d.png',
                    $page
                ),
        ];

        foreach ($candidates as $path) {
            if (
                is_file($path)
                && is_readable($path)
            ) {
                $filename =
                    basename($path);

                return [
                    'number' =>
                        $page,

                    'exists' =>
                        true,

                    'type' =>
                        'image',

                    'filename' =>
                        $filename,

                    'path' =>
                        $path,

                    'url' =>
                        $this->buildPageUrl(
                            $publicationId,
                            $filename
                        ),
                ];
            }
        }

        /*
         * No generated image exists.
         *
         * Fallback to PDF.js.
         */
        $pdf =
            $this->resolvePdf(
                $publication
            );

        if ($pdf !== null) {
            return [
                'number' =>
                    $page,

                'exists' =>
                    false,

                'type' =>
                    'pdf',

                'filename' =>
                    null,

                'path' =>
                    null,

                'url' =>
                    null,

                'fallback' =>
                    true,

                'pdf_available' =>
                    true,

                'pdf' =>
                    $pdf,
            ];
        }

        throw new RuntimeException(
            'Không có reader asset hoặc PDF cho trang này.'
        );
    }

    /**
     * Get multiple nearby pages for preloading.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPreloadPages(
        array $publication,
        int $currentPage,
        ?int $radius = null
    ): array {
        $radius =
            $radius ?? READER_PRELOAD_PAGES;

        $radius =
            max(
                0,
                min(
                    10,
                    $radius
                )
            );

        $pageCount =
            $this->getPageCount(
                $publication
            );

        /*
         * PDF fallback does not have a known page
         * count, so there is nothing to preload.
         */
        if ($pageCount <= 0) {
            return [];
        }

        $start =
            max(
                1,
                $currentPage - $radius
            );

        $end =
            min(
                $pageCount,
                $currentPage + $radius
            );

        $pages = [];

        for (
            $page = $start;
            $page <= $end;
            $page++
        ) {
            if (
                $page === $currentPage
            ) {
                continue;
            }

            try {
                $pages[] =
                    $this->getPage(
                        $publication,
                        $page
                    );
            } catch (Throwable $exception) {
                /*
                 * Preloading is optional.
                 * A missing preload asset must not
                 * crash the current reader page.
                 */
                continue;
            }
        }

        return $pages;
    }

    /**
     * Validate and normalize a page number.
     */
    private function normalizePage(
        int $page
    ): int {
        return max(
            1,
            $page
        );
    }

    /**
     * Get publication page count.
     */
    private function getPageCount(
        array $publication
    ): int {
        $pageCount =
            (int) (
                $publication['page_count']
                ?? 0
            );

        return max(
            0,
            $pageCount
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PDF
    |--------------------------------------------------------------------------
    */

    /**
     * Resolve the source PDF.
     *
     * XML may contain either:
     *
     * source_xxx.pdf
     *
     * or:
     *
     * storage/publications/pub_xxx/source_xxx.pdf
     *
     * @return array<string, mixed>|null
     */
    public function resolvePdf(
        array $publication
    ): ?array {
        $publicationId =
            (string) (
                $publication['id']
                ?? ''
            );

        if ($publicationId === '') {
            return null;
        }

        /*
         * Never allow the publication ID itself
         * to escape the expected directory.
         */
        if (
            has_path_traversal(
                $publicationId
            )
            || preg_match(
                '/^[a-zA-Z0-9._-]+$/',
                $publicationId
            ) !== 1
        ) {
            return null;
        }

        $candidates = [];

        /*
         * Preferred PDF.
         */
        if (
            !empty(
                $publication['pdf']
            )
        ) {
            $candidates[] =
                (string) $publication['pdf'];
        }

        /*
         * Source/original PDF fallback.
         */
        if (
            !empty(
                $publication['source_pdf']
            )
        ) {
            $candidates[] =
                (string) $publication['source_pdf'];
        }

        foreach ($candidates as $candidate) {
            $candidate =
                trim($candidate);

            if ($candidate === '') {
                continue;
            }

            /*
             * Remove query string / URL information if
             * the metadata happens to contain a URL.
             */
            $parsedPath =
                parse_url(
                    $candidate,
                    PHP_URL_PATH
                );

            if (
                is_string($parsedPath)
                && $parsedPath !== ''
            ) {
                $candidatePath =
                    $parsedPath;
            } else {
                $candidatePath =
                    $candidate;
            }

            /*
             * XML stores a relative path such as:
             *
             * storage/publications/pub_xxx/source.pdf
             *
             * We only use the final filename.
             */
            $filename =
                basename(
                    $candidatePath
                );

            if ($filename === '') {
                continue;
            }

            /*
             * Only allow safe PDF filenames.
             */
            if (
                has_path_traversal(
                    $filename
                )
            ) {
                continue;
            }

            if (
                preg_match(
                    '/^[a-zA-Z0-9._-]+\.pdf$/i',
                    $filename
                ) !== 1
            ) {
                continue;
            }

            /*
             * PDF must physically exist inside:
             *
             * PUBLICATION_STORAGE_PATH/{publicationId}/
             */
            $path =
                PUBLICATION_STORAGE_PATH
                . DIRECTORY_SEPARATOR
                . $publicationId
                . DIRECTORY_SEPARATOR
                . $filename;

            if (
                is_file($path)
                && is_readable($path)
            ) {
                return [
                    'filename' =>
                        $filename,

                    'path' =>
                        $path,

                    'url' =>
                        $this->buildPdfUrl(
                            $publicationId,
                            $filename
                        ),
                ];
            }
        }

        return null;
    }

    /**
     * Determine which reader source is available.
     *
     * Priority:
     *
     * 1. Generated pages
     * 2. Source PDF
     * 3. unavailable
     */
    public function detectReaderSource(
        array $publication
    ): string {
        $pageCount =
            $this->getPageCount(
                $publication
            );

        /*
         * Generated page images.
         *
         * page_count must be known and at least
         * one generated page must exist.
         */
        if (
            $pageCount > 0
            && $this->hasGeneratedPages(
                $publication
            )
        ) {
            return 'pages';
        }

        /*
         * PDF fallback.
         *
         * IMPORTANT:
         * page_count == 0 does NOT prevent PDF reader.
         */
        if (
            $this->resolvePdf(
                $publication
            ) !== null
        ) {
            return 'pdf';
        }

        return 'unavailable';
    }

    /**
     * Check whether generated page assets exist.
     */
    public function hasGeneratedPages(
        array $publication
    ): bool {
        $publicationId =
            (string) (
                $publication['id']
                ?? ''
            );

        if ($publicationId === '') {
            return false;
        }

        if (
            has_path_traversal(
                $publicationId
            )
        ) {
            return false;
        }

        $pageDirectory =
            PUBLICATION_STORAGE_PATH
            . DIRECTORY_SEPARATOR
            . $publicationId
            . DIRECTORY_SEPARATOR
            . 'pages';

        if (
            !is_dir($pageDirectory)
        ) {
            return false;
        }

        $entries =
            scandir(
                $pageDirectory
            );

        if ($entries === false) {
            return false;
        }

        foreach ($entries as $entry) {
            /*
             * Correct regex:
             *
             * page-0001.jpg
             * page-0002.jpeg
             * page-0003.png
             * page-0004.webp
             */
            if (
                preg_match(
                    '/^page-\d{4}\.(jpg|jpeg|png|webp)$/i',
                    $entry
                ) === 1
            ) {
                return true;
            }
        }

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    */

    /**
     * Get normalized reader permissions.
     *
     * @return array<string, bool>
     */
    public function getReaderSettings(
        array $publication
    ): array {
        $settings =
            $publication['settings']
            ?? [];

        if (
            !is_array($settings)
        ) {
            $settings = [];
        }

        return [
            'allow_download' =>
                to_bool(
                    $settings['allow_download']
                    ?? false
                ),

            'allow_print' =>
                to_bool(
                    $settings['allow_print']
                    ?? false
                ),

            'allow_share' =>
                to_bool(
                    $settings['allow_share']
                    ?? true
                ),
        ];
    }

    /**
     * Whether download is allowed.
     */
    public function canDownload(
        array $publication
    ): bool {
        $settings =
            $this->getReaderSettings(
                $publication
            );

        return $settings['allow_download'];
    }

    /**
     * Whether print is allowed.
     */
    public function canPrint(
        array $publication
    ): bool {
        $settings =
            $this->getReaderSettings(
                $publication
            );

        return $settings['allow_print'];
    }

    /**
     * Whether sharing is allowed.
     */
    public function canShare(
        array $publication
    ): bool {
        $settings =
            $this->getReaderSettings(
                $publication
            );

        return $settings['allow_share'];
    }

    /**
     * Resolve a downloadable PDF only if permitted.
     *
     * @return array<string, mixed>
     */
    public function resolveDownload(
        array $publication
    ): array {
        if (
            !$this->canDownload(
                $publication
            )
        ) {
            throw new RuntimeException(
                'Ấn phẩm này không cho phép tải xuống.'
            );
        }

        $pdf =
            $this->resolvePdf(
                $publication
            );

        if ($pdf === null) {
            throw new RuntimeException(
                'Không tìm thấy file PDF.'
            );
        }

        return $pdf;
    }

    /**
     * Resolve the PDF for a server-side print flow.
     *
     * Browser print itself cannot be technically
     * prevented in every environment. This method
     * enforces the platform's print permission for
     * any server-controlled print endpoint.
     *
     * @return array<string, mixed>
     */
    public function resolvePrint(
        array $publication
    ): array {
        if (
            !$this->canPrint(
                $publication
            )
        ) {
            throw new RuntimeException(
                'Ấn phẩm này không cho phép in.'
            );
        }

        $pdf =
            $this->resolvePdf(
                $publication
            );

        if ($pdf === null) {
            throw new RuntimeException(
                'Không tìm thấy file PDF.'
            );
        }

        return $pdf;
    }

    /*
    |--------------------------------------------------------------------------
    | URLs
    |--------------------------------------------------------------------------
    */

    /**
     * Build URL for a generated page.
     */
    private function buildPageUrl(
        string $publicationId,
        string $filename
    ): string {
        return app_url(
            'reader-asset.php'
            . '?publication='
            . rawurlencode(
                $publicationId
            )
            . '&file='
            . rawurlencode(
                $filename
            )
        );
    }

    /**
     * Build URL for PDF access.
     *
     * This points to the controlled PDF endpoint,
     * allowing download/print permissions to be
     * enforced server-side.
     */
    private function buildPdfUrl(
        string $publicationId,
        string $filename
    ): string {
        return app_url(
            'reader-pdf.php'
            . '?publication='
            . rawurlencode(
                $publicationId
            )
            . '&file='
            . rawurlencode(
                $filename
            )
        );
    }

    /**
     * Build a public asset URL for cover metadata.
     */
    private function buildAssetUrl(
        mixed $value
    ): ?string {
        if (
            !is_string($value)
            || trim($value) === ''
        ) {
            return null;
        }

        $value =
            trim($value);

        /*
         * If the repository already stores a full URL,
         * preserve it.
         */
        if (
            preg_match(
                '#^https?://#i',
                $value
            ) === 1
        ) {
            return $value;
        }

        /*
         * Prevent arbitrary filesystem-style values.
         */
        if (
            has_path_traversal(
                $value
            )
        ) {
            return null;
        }

        return app_url(
            ltrim(
                $value,
                '/\\'
            )
        );
    }
}