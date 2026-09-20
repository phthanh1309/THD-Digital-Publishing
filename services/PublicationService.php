<?php

declare(strict_types=1);

/**
 * THD Digital Publishing
 *
 * Publication Service.
 *
 * Responsibilities:
 * - Validate publication business rules
 * - Create/update/delete publications
 * - Generate unique slugs
 * - Manage publication status transitions
 * - Manage publication timestamps
 * - Keep UI independent from XML storage
 *
 * This service must NOT:
 * - Read/write XML directly
 * - Render HTML
 * - Handle HTTP requests directly
 * - Handle CSRF
 * - Handle authentication/session
 * - Process uploaded files
 *
 * File operations belong to UploadService.
 */

require_once THD_ROOT
    . DIRECTORY_SEPARATOR
    . 'repositories'
    . DIRECTORY_SEPARATOR
    . 'PublicationRepository.php';


class PublicationService
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
    | Read operations
    |--------------------------------------------------------------------------
    */

    /**
     * Get all publications.
     *
     * Intended for admin/internal use.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array
    {
        return $this->repository->all();
    }


    /**
     * Get all publicly visible publications.
     *
     * Only published publications are returned.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPublished(): array
    {
        return $this->repository->allPublished();
    }


    /**
     * Find publication by ID.
     *
     * @return array<string, mixed>|null
     */
    public function findById(
        string $id
    ): ?array {
        $id = trim($id);

        if ($id === '') {
            return null;
        }

        return $this->repository->findById(
            $id
        );
    }


    /**
     * Find publication by slug.
     *
     * @return array<string, mixed>|null
     */
    public function findBySlug(
        string $slug
    ): ?array {
        $slug = trim($slug);

        if ($slug === '') {
            return null;
        }

        return $this->repository->findBySlug(
            $slug
        );
    }


    /**
     * Find a publication that is publicly accessible.
     *
     * Draft and archived publications are never returned here.
     *
     * @return array<string, mixed>|null
     */
    public function findPublicBySlug(
        string $slug
    ): ?array {
        $publication =
            $this->findBySlug($slug);

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
     * Count all publications.
     */
    public function count(): int
    {
        return $this->repository->count();
    }


    /*
    |--------------------------------------------------------------------------
    | Create
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new publication.
     *
     * Expected payload:
     *
     * [
     *     'title'          => string,
     *     'subtitle'       => string,
     *     'year'           => int,
     *     'description'    => string,
     *     'cover'          => string,
     *     'source_pdf'     => string,
     *     'pdf'            => string,
     *     'page_count'     => int,
     *     'status'         => string,
     *     'published_at'   => string|null,
     *     'author'         => string,
     *     'editor'         => string,
     *     'language'       => string,
     *     'settings'       => array,
     * ]
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function create(
        array $data
    ): array {
        $data = $this->preparePayload(
            $data
        );

        $this->validatePayload(
            $data
        );

        $data['id'] =
            $this->generateUniqueId();

        $data['slug'] =
            $this->generateUniqueSlug(
                $data['slug'] !== ''
                    ? $data['slug']
                    : $data['title']
            );

        $now = now_datetime();

        $data['created_at'] = $now;
        $data['updated_at'] = $now;

        if (
            $data['status'] === 'published'
        ) {
            $data['published_at'] =
                $data['published_at']
                ?? $now;
        } else {
            $data['published_at'] = null;
        }

        $this->repository->create(
            $data
        );

        $publication =
            $this->repository->findById(
                $data['id']
            );

        if ($publication === null) {
            throw new RuntimeException(
                'Không thể đọc lại ấn phẩm vừa tạo.'
            );
        }

        return $publication;
    }


    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    /**
     * Update an existing publication.
     *
     * The payload is merged with the current record,
     * so callers can update only the fields they need.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function update(
        string $id,
        array $data
    ): array {
        $id = trim($id);

        if ($id === '') {
            throw new InvalidArgumentException(
                'ID ấn phẩm không hợp lệ.'
            );
        }

        $current =
            $this->repository->findById(
                $id
            );

        if ($current === null) {
            throw new RuntimeException(
                'Không tìm thấy ấn phẩm.'
            );
        }

        /*
         * Never allow a caller to replace the ID
         * through the update payload.
         */
        unset($data['id']);

        $merged =
            array_merge(
                $current,
                $data
            );

        $merged =
            $this->preparePayload(
                $merged
            );

        $merged['id'] = $id;

        /*
         * Handle slug separately.
         *
         * If title changes and no explicit slug was
         * provided, keep the existing slug stable.
         */
        if (
            !array_key_exists(
                'slug',
                $data
            )
            || trim(
                (string) ($data['slug'] ?? '')
            ) === ''
        ) {
            $merged['slug'] =
                (string) $current['slug'];
        } else {
            $merged['slug'] =
                $this->generateUniqueSlug(
                    (string) $data['slug'],
                    $id
                );
        }

        $this->validatePayload(
            $merged
        );

        $merged['updated_at'] =
            now_datetime();

        /*
         * Published timestamp rules:
         *
         * draft → published:
         *     set published_at now
         *
         * published → published:
         *     preserve existing published_at
         *
         * published → draft/archived:
         *     clear published_at
         *
         * draft/archived → draft/archived:
         *     keep null
         */
        $oldStatus =
            (string) (
                $current['status']
                ?? DEFAULT_PUBLICATION_STATUS
            );

        $newStatus =
            (string) $merged['status'];

        if (
            $newStatus === 'published'
        ) {
            if (
                $oldStatus !== 'published'
                || empty(
                    $current['published_at']
                )
            ) {
                $merged['published_at'] =
                    now_datetime();
            } else {
                $merged['published_at'] =
                    $current['published_at'];
            }
        } else {
            $merged['published_at'] = null;
        }

        $this->repository->update(
            $id,
            $merged
        );

        $publication =
            $this->repository->findById(
                $id
            );

        if ($publication === null) {
            throw new RuntimeException(
                'Không thể đọc lại ấn phẩm sau khi cập nhật.'
            );
        }

        return $publication;
    }


    /*
    |--------------------------------------------------------------------------
    | Status transitions
    |--------------------------------------------------------------------------
    */

    /**
     * Publish a publication.
     *
     * @return array<string, mixed>
     */
    public function publish(
        string $id
    ): array {
        return $this->update(
            $id,
            [
                'status' => 'published',
            ]
        );
    }


    /**
     * Unpublish a publication.
     *
     * Publication returns to draft.
     *
     * @return array<string, mixed>
     */
    public function unpublish(
        string $id
    ): array {
        return $this->update(
            $id,
            [
                'status' => 'draft',
            ]
        );
    }


    /**
     * Archive a publication.
     *
     * @return array<string, mixed>
     */
    public function archive(
        string $id
    ): array {
        return $this->update(
            $id,
            [
                'status' => 'archived',
            ]
        );
    }


    /**
     * Restore an archived publication to draft.
     *
     * @return array<string, mixed>
     */
    public function restore(
        string $id
    ): array {
        return $this->update(
            $id,
            [
                'status' => 'draft',
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Delete
    |--------------------------------------------------------------------------
    */

    /**
     * Delete publication metadata.
     *
     * Physical PDF/cover/page files are intentionally
     * NOT deleted here.
     *
     * UploadService / a dedicated cleanup operation
     * should handle physical files.
     */
    public function delete(
        string $id
    ): void {
        $id = trim($id);

        if ($id === '') {
            throw new InvalidArgumentException(
                'ID ấn phẩm không hợp lệ.'
            );
        }

        $publication =
            $this->repository->findById(
                $id
            );

        if ($publication === null) {
            throw new RuntimeException(
                'Không tìm thấy ấn phẩm.'
            );
        }

        $deleted =
            $this->repository->delete(
                $id
            );

        if (!$deleted) {
            throw new RuntimeException(
                'Không thể xóa ấn phẩm.'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Payload preparation
    |--------------------------------------------------------------------------
    */

    /**
     * Normalize publication input.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function preparePayload(
        array $data
    ): array {
        $stringFields = [
            'slug',
            'title',
            'subtitle',
            'description',
            'cover',
            'source_pdf',
            'pdf',
            'author',
            'editor',
            'language',
        ];

        foreach ($stringFields as $field) {
            if (
                array_key_exists(
                    $field,
                    $data
                )
            ) {
                $data[$field] =
                    trim(
                        (string) $data[$field]
                    );
            }
        }

        if (
            isset($data['year'])
            && $data['year'] !== ''
        ) {
            $data['year'] =
                (int) $data['year'];
        }

        if (
            isset($data['page_count'])
            && $data['page_count'] !== ''
        ) {
            $data['page_count'] =
                max(
                    0,
                    (int) $data['page_count']
                );
        }

        if (
            !isset($data['status'])
            || $data['status'] === ''
        ) {
            $data['status'] =
                DEFAULT_PUBLICATION_STATUS;
        }

        $data['settings'] =
            $this->normalizeReaderSettings(
                $data['settings'] ?? []
            );

        return $data;
    }


    /**
     * Normalize reader permissions.
     *
     * @param mixed $settings
     *
     * @return array<string, bool>
     */
    private function normalizeReaderSettings(
        mixed $settings
    ): array {
        if (!is_array($settings)) {
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


    /*
    |--------------------------------------------------------------------------
    | Business validation
    |--------------------------------------------------------------------------
    */

    /**
     * Validate publication payload.
     *
     * @param array<string, mixed> $data
     */
    private function validatePayload(
        array $data
    ): void {
        $errors =
            validate_publication_payload(
                $data
            );

        /*
         * Repository requires these fields to exist.
         */
        if (
            empty(
                trim(
                    (string) (
                        $data['id'] ?? ''
                    )
                )
            )
        ) {
            /*
             * ID is generated during create.
             * Therefore do not treat this as an
             * error before create().
             */
            if (
                isset($data['id'])
            ) {
                $errors['id'] =
                    'ID ấn phẩm không hợp lệ.';
            }
        }

        if (
            !empty(
                $data['slug']
            )
        ) {
            $slug =
                trim(
                    (string) $data['slug']
                );

            if (
                preg_match(
                    '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                    $slug
                ) !== 1
            ) {
                $errors['slug'] =
                    'Slug chỉ được chứa chữ thường, số và dấu gạch ngang.';
            }
        }

        /*
         * If a publication is published, a PDF
         * should normally exist before it becomes
         * publicly accessible.
         *
         * We intentionally allow metadata-only draft
         * records because upload can happen later.
         */
        if (
            ($data['status'] ?? null)
            === 'published'
            && empty(
                trim(
                    (string) (
                        $data['pdf']
                        ?? $data['source_pdf']
                        ?? ''
                    )
                )
            )
        ) {
            $errors['pdf'] =
                'Ấn phẩm đã xuất bản phải có file PDF.';
        }

        if ($errors !== []) {
            require_valid(
                $errors
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Slug generation
    |--------------------------------------------------------------------------
    */

    /**
     * Generate a unique slug.
     *
     * @param string $source
     * @param string|null $ignoreId
     */
    private function generateUniqueSlug(
        string $source,
        ?string $ignoreId = null
    ): string {
        $base =
            slugify($source);

        if ($base === '') {
            $base = 'publication';
        }

        $slug = $base;
        $counter = 2;

        while (
            $this->repository->existsBySlug(
                $slug,
                $ignoreId
            )
        ) {
            $slug =
                $base
                . '-'
                . $counter;

            $counter++;
        }

        return $slug;
    }


    /*
    |--------------------------------------------------------------------------
    | ID generation
    |--------------------------------------------------------------------------
    */

    /**
     * Generate a unique publication ID.
     */
    private function generateUniqueId(): string
    {
        do {
            /*
             * Example:
             * pub_68f3a91c2e4d
             */
            $id =
                'pub_'
                . bin2hex(
                    random_bytes(6)
                );
        } while (
            $this->repository->existsById(
                $id
            )
        );

        return $id;
    }
}