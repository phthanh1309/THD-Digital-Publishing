<?php

declare(strict_types=1);

/**
 * THD Digital Publishing
 *
 * Search Service.
 *
 * Responsibilities:
 * - Search publications
 * - Filter by year
 * - Filter by status
 * - Sort results
 * - Paginate results
 * - Provide available years for filters
 *
 * This service must NOT:
 * - Read XML directly
 * - Render HTML
 * - Handle HTTP requests directly
 * - Handle authentication
 * - Handle CSRF
 * - Modify publication data
 */

require_once THD_ROOT
    . DIRECTORY_SEPARATOR
    . 'repositories'
    . DIRECTORY_SEPARATOR
    . 'PublicationRepository.php';


class SearchService
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
    | Public search
    |--------------------------------------------------------------------------
    */

    /**
     * Search publicly visible publications.
     *
     * Supported filters:
     *
     * [
     *     'q'         => 'kỷ niệm',
     *     'year'      => 2026,
     *     'sort'      => 'newest',
     *     'page'      => 1,
     *     'per_page'  => 12,
     * ]
     *
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    public function search(
        array $filters = []
    ): array {
        $filters =
            $this->normalizeFilters(
                $filters,
                false
            );

        $publications =
            $this->repository->allPublished();

        $filtered =
            $this->filterPublications(
                $publications,
                $filters
            );

        $filtered =
            $this->sortPublications(
                $filtered,
                $filters['sort']
            );

        return $this->paginate(
            $filtered,
            $filters['page'],
            $filters['per_page']
        );
    }


    /**
     * Search publications for admin.
     *
     * Admin can search all statuses.
     *
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    public function searchAdmin(
        array $filters = []
    ): array {
        $filters =
            $this->normalizeFilters(
                $filters,
                true
            );

        $publications =
            $this->repository->all();

        $filtered =
            $this->filterPublications(
                $publications,
                $filters
            );

        $filtered =
            $this->sortPublications(
                $filtered,
                $filters['sort']
            );

        return $this->paginate(
            $filtered,
            $filters['page'],
            $filters['per_page']
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Filters
    |--------------------------------------------------------------------------
    */

    /**
     * Normalize search filters.
     *
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    private function normalizeFilters(
        array $filters,
        bool $adminMode
    ): array {
        $query =
            trim(
                (string) (
                    $filters['q']
                    ?? ''
                )
            );

        /*
         * Avoid unexpectedly huge search strings.
         */
        if (
            strlen($query) > 200
        ) {
            $query =
                substr(
                    $query,
                    0,
                    200
                );
        }

        $year = null;

        if (
            isset($filters['year'])
            && $filters['year'] !== ''
        ) {
            $yearValue =
                filter_var(
                    $filters['year'],
                    FILTER_VALIDATE_INT
                );

            if (
                $yearValue !== false
                && $yearValue >= 1900
                && $yearValue <= 2200
            ) {
                $year =
                    (int) $yearValue;
            }
        }

        $status = null;

        if (
            $adminMode
            && isset($filters['status'])
        ) {
            $requestedStatus =
                trim(
                    (string) $filters['status']
                );

            if (
                is_valid_publication_status(
                    $requestedStatus
                )
            ) {
                $status =
                    $requestedStatus;
            }
        }

        $allowedSorts = [
            'newest',
            'oldest',
            'title_asc',
            'title_desc',
        ];

        $sort =
            trim(
                (string) (
                    $filters['sort']
                    ?? 'newest'
                )
            );

        if (
            !in_array(
                $sort,
                $allowedSorts,
                true
            )
        ) {
            $sort = 'newest';
        }

        $page =
            filter_var(
                $filters['page'] ?? 1,
                FILTER_VALIDATE_INT
            );

        if (
            $page === false
            || $page < 1
        ) {
            $page = 1;
        }

        $perPage =
            filter_var(
                $filters['per_page']
                ?? DEFAULT_PUBLICATIONS_PER_PAGE,
                FILTER_VALIDATE_INT
            );

        if (
            $perPage === false
            || $perPage < 1
        ) {
            $perPage =
                DEFAULT_PUBLICATIONS_PER_PAGE;
        }

        /*
         * Prevent an URL such as:
         *
         * ?per_page=999999
         *
         * from causing excessive processing.
         */
        $perPage =
            min(
                $perPage,
                100
            );

        return [
            'q' =>
                $query,

            'year' =>
                $year,

            'status' =>
                $status,

            'sort' =>
                $sort,

            'page' =>
                $page,

            'per_page' =>
                $perPage,
        ];
    }


    /**
     * Filter publications according to normalized filters.
     *
     * @param array<int, array<string, mixed>> $publications
     * @param array<string, mixed> $filters
     *
     * @return array<int, array<string, mixed>>
     */
    private function filterPublications(
        array $publications,
        array $filters
    ): array {
        $query =
            trim(
                (string) $filters['q']
            );

        $year =
            $filters['year'];

        $status =
            $filters['status'];

        $result = [];

        foreach (
            $publications as $publication
        ) {
            /*
             * Public search must never expose unpublished
             * records, even if a Repository implementation
             * accidentally returns one.
             */
            if (
                $status === null
                && !is_publication_public(
                    $publication
                )
            ) {
                continue;
            }

            /*
             * Admin search can explicitly request
             * a status. If no status is requested,
             * all statuses remain available.
             */
            if (
                $status !== null
                && (
                    string) (
                        $publication['status']
                        ?? ''
                    )
                    !== $status
            ) {
                continue;
            }

            if (
                $year !== null
                && (int) (
                    $publication['year']
                    ?? 0
                )
                !== $year
            ) {
                continue;
            }

            if (
                $query !== ''
                && !$this->matchesQuery(
                    $publication,
                    $query
                )
            ) {
                continue;
            }

            $result[] =
                $publication;
        }

        return $result;
    }


    /**
     * Search across relevant publication fields.
     *
     * Fields:
     * - title
     * - subtitle
     * - description
     * - author
     * - editor
     * - language
     * - year
     */
    private function matchesQuery(
        array $publication,
        string $query
    ): bool {
        $searchFields = [
            $publication['title'] ?? '',
            $publication['subtitle'] ?? '',
            $publication['description'] ?? '',
            $publication['author'] ?? '',
            $publication['editor'] ?? '',
            $publication['language'] ?? '',
            $publication['year'] ?? '',
        ];

        $haystack =
            implode(
                ' ',
                array_map(
                    static function ($value): string {
                        return (string) $value;
                    },
                    $searchFields
                )
            );

        return $this->containsText(
            $haystack,
            $query
        );
    }


    /**
     * UTF-8 aware case-insensitive text search.
     */
    private function containsText(
        string $haystack,
        string $needle
    ): bool {
        if (
            function_exists('mb_stripos')
        ) {
            return mb_stripos(
                $haystack,
                $needle,
                0,
                'UTF-8'
            ) !== false;
        }

        /*
         * Fallback for hosting environments without mbstring.
         */
        return stripos(
            $haystack,
            $needle
        ) !== false;
    }


    /*
    |--------------------------------------------------------------------------
    | Sorting
    |--------------------------------------------------------------------------
    */

    /**
     * Sort publications.
     *
     * @param array<int, array<string, mixed>> $publications
     *
     * @return array<int, array<string, mixed>>
     */
    private function sortPublications(
        array $publications,
        string $sort
    ): array {
        usort(
            $publications,
            function (
                array $a,
                array $b
            ) use (
                $sort
            ): int {
                return match ($sort) {
                    'oldest' =>
                        $this->compareDates(
                            $a,
                            $b,
                            'oldest'
                        ),

                    'title_asc' =>
                        $this->compareTitles(
                            $a,
                            $b,
                            false
                        ),

                    'title_desc' =>
                        $this->compareTitles(
                            $a,
                            $b,
                            true
                        ),

                    default =>
                        $this->compareDates(
                            $a,
                            $b,
                            'newest'
                        ),
                };
            }
        );

        return $publications;
    }


    /**
     * Compare publication dates.
     */
    private function compareDates(
        array $a,
        array $b,
        string $direction
    ): int {
        $dateA =
            strtotime(
                (string) (
                    $a['published_at']
                    ?? $a['created_at']
                    ?? ''
                )
            );

        $dateB =
            strtotime(
                (string) (
                    $b['published_at']
                    ?? $b['created_at']
                    ?? ''
                )
            );

        /*
         * Invalid dates are always treated as older.
         */
        if ($dateA === false) {
            $dateA = 0;
        }

        if ($dateB === false) {
            $dateB = 0;
        }

        if (
            $dateA === $dateB
        ) {
            /*
             * Stable-ish secondary sort.
             */
            return $this->compareTitles(
                $a,
                $b,
                false
            );
        }

        if (
            $direction === 'oldest'
        ) {
            return $dateA <=> $dateB;
        }

        return $dateB <=> $dateA;
    }


    /**
     * Compare titles.
     */
    private function compareTitles(
        array $a,
        array $b,
        bool $descending
    ): int {
        $titleA =
            trim(
                (string) (
                    $a['title'] ?? ''
                )
            );

        $titleB =
            trim(
                (string) (
                    $b['title'] ?? ''
                )
            );

        if (
            function_exists('mb_strtolower')
        ) {
            $titleA =
                mb_strtolower(
                    $titleA,
                    'UTF-8'
                );

            $titleB =
                mb_strtolower(
                    $titleB,
                    'UTF-8'
                );
        } else {
            $titleA =
                strtolower($titleA);

            $titleB =
                strtolower($titleB);
        }

        $comparison =
            strnatcmp(
                $titleA,
                $titleB
            );

        return $descending
            ? -$comparison
            : $comparison;
    }


    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

    /**
     * Paginate result set.
     *
     * @param array<int, array<string, mixed>> $items
     *
     * @return array<string, mixed>
     */
    private function paginate(
        array $items,
        int $page,
        int $perPage
    ): array {
        $total =
            count($items);

        $totalPages =
            $total > 0
                ? (int) ceil(
                    $total / $perPage
                )
                : 1;

        /*
         * If someone requests page 999,
         * return the last valid page.
         */
        $page =
            min(
                max(
                    1,
                    $page
                ),
                $totalPages
            );

        $offset =
            ($page - 1)
            * $perPage;

        $results =
            array_slice(
                $items,
                $offset,
                $perPage
            );

        return [
            'items' =>
                array_values(
                    $results
                ),

            'pagination' => [
                'page' =>
                    $page,

                'per_page' =>
                    $perPage,

                'total_items' =>
                    $total,

                'total_pages' =>
                    $totalPages,

                'has_previous' =>
                    $page > 1,

                'has_next' =>
                    $page < $totalPages,

                'previous_page' =>
                    $page > 1
                        ? $page - 1
                        : null,

                'next_page' =>
                    $page < $totalPages
                        ? $page + 1
                        : null,
            ],

            'filters' => [
                'q' =>
                    (string) $this->safeFilterValue(
                        $items,
                        ''
                    ),
            ],
        ];
    }


    /**
     * Get available publication years.
     *
     * Useful for the year filter in the library.
     *
     * @param bool $publishedOnly
     *
     * @return array<int, int>
     */
    public function getAvailableYears(
        bool $publishedOnly = true
    ): array {
        $publications =
            $publishedOnly
                ? $this->repository->allPublished()
                : $this->repository->all();

        $years = [];

        foreach (
            $publications as $publication
        ) {
            $year =
                (int) (
                    $publication['year']
                    ?? 0
                );

            if (
                $year >= 1900
                && $year <= 2200
            ) {
                $years[$year] = true;
            }
        }

        $years =
            array_keys($years);

        rsort(
            $years,
            SORT_NUMERIC
        );

        return array_values(
            $years
        );
    }


    /**
     * Return a safe scalar filter value.
     *
     * This helper exists to keep pagination output
     * independent from raw request data.
     */
    private function safeFilterValue(
        array $items,
        mixed $default
    ): mixed {
        /*
         * Pagination itself does not need to inspect
         * the result items. The method is intentionally
         * conservative and currently returns default.
         *
         * The public filters are supplied separately
         * by normalizeFilters() in search().
         */
        return $default;
    }
}