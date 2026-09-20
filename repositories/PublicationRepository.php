<?php

declare(strict_types=1);

/**
 * THD Digital Publishing
 *
 * Publication XML Repository.
 *
 * Responsibilities:
 * - Read publications.xml
 * - Find publications
 * - Insert/update/delete publication records
 * - Persist XML safely with file locking
 *
 * This repository owns XML persistence.
 *
 * It must NOT:
 * - Render HTML
 * - Handle authentication
 * - Handle CSRF
 * - Save uploaded files
 * - Decide whether an admin is authorized
 * - Contain presentation logic
 */


/*
|--------------------------------------------------------------------------
| XML structure
|--------------------------------------------------------------------------
|
| <publications>
|     <publication>
|         <id>...</id>
|         <slug>...</slug>
|         <title>...</title>
|         ...
|     </publication>
| </publications>
|
*/


class PublicationRepository
{
    /**
     * @var string
     */
    private string $xmlPath;


    /**
     * Create a publication repository.
     *
     * @param string|null $xmlPath Optional custom XML path,
     *                             useful for testing.
     */
    public function __construct(
        ?string $xmlPath = null
    ) {
        $this->xmlPath = $xmlPath ?? PUBLICATIONS_XML;
    }


    /*
    |--------------------------------------------------------------------------
    | Read operations
    |--------------------------------------------------------------------------
    */

    /**
     * Return all publications.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(
        bool $includeArchived = true
    ): array {
        $xml = $this->loadXml();

        $publications = [];

        foreach ($xml->publication as $node) {
            $publication = $this->nodeToArray($node);

            if (
                !$includeArchived
                && ($publication['status'] ?? '')
                    === 'archived'
            ) {
                continue;
            }

            $publications[] = $publication;
        }

        return $publications;
    }


    /**
     * Return only published publications.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allPublished(): array
    {
        $xml = $this->loadXml();

        $publications = [];

        foreach ($xml->publication as $node) {
            $publication = $this->nodeToArray($node);

            if (
                ($publication['status'] ?? '')
                !== 'published'
            ) {
                continue;
            }

            $publications[] = $publication;
        }

        return $publications;
    }


    /**
     * Find a publication by ID.
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

        $xml = $this->loadXml();

        foreach ($xml->publication as $node) {
            if (
                (string) $node->id === $id
            ) {
                return $this->nodeToArray($node);
            }
        }

        return null;
    }


    /**
     * Find a publication by slug.
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

        $xml = $this->loadXml();

        foreach ($xml->publication as $node) {
            if (
                (string) $node->slug === $slug
            ) {
                return $this->nodeToArray($node);
            }
        }

        return null;
    }


    /**
     * Check whether an ID already exists.
     */
    public function existsById(
        string $id
    ): bool {
        return $this->findById($id) !== null;
    }


    /**
     * Check whether a slug already exists.
     *
     * When updating an existing publication, $excludeId can be
     * supplied so the publication can keep its own slug.
     */
    public function existsBySlug(
        string $slug,
        ?string $excludeId = null
    ): bool {
        $slug = trim($slug);

        if ($slug === '') {
            return false;
        }

        $xml = $this->loadXml();

        foreach ($xml->publication as $node) {
            $nodeId = (string) $node->id;
            $nodeSlug = (string) $node->slug;

            if (
                $excludeId !== null
                && $nodeId === $excludeId
            ) {
                continue;
            }

            if ($nodeSlug === $slug) {
                return true;
            }
        }

        return false;
    }


    /**
     * Count publications.
     */
    public function count(
        ?string $status = null
    ): int {
        $xml = $this->loadXml();

        $count = 0;

        foreach ($xml->publication as $node) {
            if (
                $status !== null
                && (string) $node->status !== $status
            ) {
                continue;
            }

            $count++;
        }

        return $count;
    }


    /*
    |--------------------------------------------------------------------------
    | Write operations
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new publication.
     *
     * The caller is responsible for validating the data.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function create(
        array $data
    ): array {
        $this->assertRequiredFields($data);

        return $this->withExclusiveLock(
            function ($handle) use ($data): array {
                $xml = $this->loadXmlFromHandle($handle);

                $publicationNode = $xml->addChild(
                    'publication'
                );

                $this->writePublicationNode(
                    $publicationNode,
                    $data
                );

                $this->saveXmlToHandle(
                    $xml,
                    $handle
                );

                return $this->nodeToArray(
                    $publicationNode
                );
            }
        );
    }


    /**
     * Update an existing publication.
     *
     * @param string $id
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
                'Publication ID không được để trống.'
            );
        }

        $this->assertRequiredFields($data);

        return $this->withExclusiveLock(
            function ($handle) use (
                $id,
                $data
            ): array {
                $xml = $this->loadXmlFromHandle($handle);

                foreach ($xml->publication as $node) {
                    if (
                        (string) $node->id !== $id
                    ) {
                        continue;
                    }

                    $this->writePublicationNode(
                        $node,
                        $data
                    );

                    $this->saveXmlToHandle(
                        $xml,
                        $handle
                    );

                    return $this->nodeToArray(
                        $node
                    );
                }

                throw new RuntimeException(
                    'Không tìm thấy ấn phẩm cần cập nhật.',
                    404
                );
            }
        );
    }


    /**
     * Delete a publication record.
     *
     * Physical publication files are NOT deleted here.
     * File cleanup belongs to PublicationService.
     */
    public function delete(
        string $id
    ): bool {
        $id = trim($id);

        if ($id === '') {
            return false;
        }

        return $this->withExclusiveLock(
            function ($handle) use ($id): bool {
                $xml = $this->loadXmlFromHandle($handle);

                $index = 0;

                foreach ($xml->publication as $node) {
                    if (
                        (string) $node->id !== $id
                    ) {
                        $index++;
                        continue;
                    }

                    unset(
                        $xml->publication[$index]
                    );

                    $this->saveXmlToHandle(
                        $xml,
                        $handle
                    );

                    return true;
                }

                return false;
            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | XML loading
    |--------------------------------------------------------------------------
    */

    /**
     * Load publications.xml.
     */
    private function loadXml(): SimpleXMLElement
    {
        $this->ensureXmlFile();

        $previousUseErrors = libxml_use_internal_errors(
            true
        );

        try {
            $xml = simplexml_load_file(
                $this->xmlPath,
                SimpleXMLElement::class,
                LIBXML_NONET
            );

            if ($xml === false) {
                $this->throwXmlError();
            }

            return $xml;
        } finally {
            libxml_use_internal_errors(
                $previousUseErrors
            );
        }
    }


    /**
     * Load XML from an already locked file handle.
     *
     * This prevents the classic:
     *
     * read → unlock → another request writes → write old data
     *
     * race condition.
     *
     * @param resource $handle
     */
    private function loadXmlFromHandle(
        $handle
    ): SimpleXMLElement {
        if (
            fseek($handle, 0, SEEK_SET) !== 0
        ) {
            throw new RuntimeException(
                'Không thể đọc file XML.'
            );
        }

        $contents = stream_get_contents(
            $handle
        );

        if ($contents === false) {
            throw new RuntimeException(
                'Không thể đọc dữ liệu XML.'
            );
        }

        if (trim($contents) === '') {
            return new SimpleXMLElement(
                '<publications></publications>'
            );
        }

        $previousUseErrors = libxml_use_internal_errors(
            true
        );

        try {
            $xml = simplexml_load_string(
                $contents,
                SimpleXMLElement::class,
                LIBXML_NONET
            );

            if ($xml === false) {
                $this->throwXmlError();
            }

            return $xml;
        } finally {
            libxml_use_internal_errors(
                $previousUseErrors
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | XML persistence
    |--------------------------------------------------------------------------
    */

    /**
     * Save XML safely through an exclusive lock.
     *
     * @param resource $handle
     */
    private function saveXmlToHandle(
        SimpleXMLElement $xml,
        $handle
    ): void {
        $contents = $xml->asXML();

        if (
            $contents === false
            || trim($contents) === ''
        ) {
            throw new RuntimeException(
                'Không thể tạo dữ liệu XML.'
            );
        }

        if (
            ftruncate($handle, 0) === false
        ) {
            throw new RuntimeException(
                'Không thể làm trống file XML.'
            );
        }

        if (
            fseek($handle, 0, SEEK_SET) !== 0
        ) {
            throw new RuntimeException(
                'Không thể đưa con trỏ file về đầu.'
            );
        }

        $written = fwrite(
            $handle,
            $contents
        );

        if (
            $written === false
            || $written < strlen($contents)
        ) {
            throw new RuntimeException(
                'Không thể ghi đầy đủ dữ liệu XML.'
            );
        }

        fflush($handle);
    }


    /**
     * Execute a callback while holding an exclusive file lock.
     *
     * @param callable $callback
     */
    private function withExclusiveLock(
        callable $callback
    ): mixed {
        $this->ensureXmlFile();

        $handle = fopen(
            $this->xmlPath,
            'c+b'
        );

        if ($handle === false) {
            throw new RuntimeException(
                'Không thể mở file XML để ghi.'
            );
        }

        try {
            if (
                !flock(
                    $handle,
                    LOCK_EX
                )
            ) {
                throw new RuntimeException(
                    'Không thể khóa file XML.'
                );
            }

            try {
                return $callback($handle);
            } finally {
                flock(
                    $handle,
                    LOCK_UN
                );
            }
        } finally {
            fclose($handle);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Publication XML mapping
    |--------------------------------------------------------------------------
    */

    /**
     * Convert XML node to application array.
     *
     * @return array<string, mixed>
     */
    private function nodeToArray(
        SimpleXMLElement $node
    ): array {
        return [
            'id' => (string) $node->id,
            'slug' => (string) $node->slug,
            'title' => (string) $node->title,
            'subtitle' => (string) $node->subtitle,
            'year' => $this->xmlInteger(
                $node->year
            ),
            'description' => (string) $node->description,
            'cover' => (string) $node->cover,
            'source_pdf' => (string) $node->source_pdf,
            'pdf' => (string) $node->pdf,
            'page_count' => $this->xmlInteger(
                $node->page_count
            ),
            'status' => (string) $node->status,
            'created_at' => (string) $node->created_at,
            'updated_at' => (string) $node->updated_at,
            'published_at' => (string) $node->published_at,
            'author' => (string) $node->author,
            'editor' => (string) $node->editor,
            'language' => (string) $node->language,

            'settings' => [
                'allow_download' => $this->xmlBoolean(
                    $node->settings->allow_download
                ),
                'allow_print' => $this->xmlBoolean(
                    $node->settings->allow_print
                ),
                'allow_share' => $this->xmlBoolean(
                    $node->settings->allow_share
                ),
            ],
        ];
    }


    /**
     * Write application data into an XML publication node.
     *
     * @param SimpleXMLElement $node
     * @param array<string, mixed> $data
     */
    private function writePublicationNode(
        SimpleXMLElement $node,
        array $data
    ): void {
        $fields = [
            'id',
            'slug',
            'title',
            'subtitle',
            'year',
            'description',
            'cover',
            'source_pdf',
            'pdf',
            'page_count',
            'status',
            'created_at',
            'updated_at',
            'published_at',
            'author',
            'editor',
            'language',
        ];

        foreach ($fields as $field) {
            $value = $data[$field] ?? '';

            /*
             * For update operations, keep the existing created_at
             * if no replacement value is supplied.
             */
            if (
                $field === 'created_at'
                && $value === ''
                && isset($node->created_at)
            ) {
                $value = (string) $node->created_at;
            }

            $this->setXmlValue(
                $node,
                $field,
                $value
            );
        }

        $settings = $data['settings'] ?? [];

        if (!isset($node->settings)) {
            $node->addChild(
                'settings'
            );
        }

        $this->setXmlValue(
            $node->settings,
            'allow_download',
            to_bool(
                $settings['allow_download'] ?? false
            ) ? '1' : '0'
        );

        $this->setXmlValue(
            $node->settings,
            'allow_print',
            to_bool(
                $settings['allow_print'] ?? false
            ) ? '1' : '0'
        );

        $this->setXmlValue(
            $node->settings,
            'allow_share',
            to_bool(
                $settings['allow_share'] ?? true
            ) ? '1' : '0'
        );
    }


    /**
     * Set an XML child value.
     *
     * SimpleXML handles XML escaping.
     */
    private function setXmlValue(
        SimpleXMLElement $parent,
        string $name,
        mixed $value
    ): void {
        if (isset($parent->{$name})) {
            $parent->{$name} = (string) $value;

            return;
        }

        $parent->addChild(
            $name,
            (string) $value
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Validation of repository contract
    |--------------------------------------------------------------------------
    */

    /**
     * Ensure fields required by the repository exist.
     *
     * Detailed validation belongs to validator.php.
     *
     * @param array<string, mixed> $data
     */
    private function assertRequiredFields(
        array $data
    ): void {
        $requiredFields = [
            'id',
            'slug',
            'title',
            'year',
            'status',
        ];

        foreach ($requiredFields as $field) {
            if (
                !array_key_exists($field, $data)
                || $data[$field] === ''
                || $data[$field] === null
            ) {
                throw new InvalidArgumentException(
                    'Thiếu trường bắt buộc: ' . $field
                );
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | XML utility methods
    |--------------------------------------------------------------------------
    */

    /**
     * Convert XML value to integer.
     */
    private function xmlInteger(
        SimpleXMLElement $value
    ): int {
        return filter_var(
            (string) $value,
            FILTER_VALIDATE_INT
        ) ?: 0;
    }


    /**
     * Convert XML value to boolean.
     */
    private function xmlBoolean(
        SimpleXMLElement $value
    ): bool {
        return to_bool(
            (string) $value
        );
    }


    /**
     * Ensure the XML file exists and has a valid root.
     */
    private function ensureXmlFile(): void
    {
        $directory = dirname(
            $this->xmlPath
        );

        if (
            !ensure_directory($directory)
        ) {
            throw new RuntimeException(
                'Không thể tạo thư mục XML.'
            );
        }

        if (!file_exists($this->xmlPath)) {
            $initialXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<publications>
</publications>
XML;

            if (
                file_put_contents(
                    $this->xmlPath,
                    $initialXml,
                    LOCK_EX
                ) === false
            ) {
                throw new RuntimeException(
                    'Không thể tạo publications.xml.'
                );
            }

            return;
        }

        if (!is_readable($this->xmlPath)) {
            throw new RuntimeException(
                'Không thể đọc publications.xml.'
            );
        }

        if (!is_writable($this->xmlPath)) {
            throw new RuntimeException(
                'Không thể ghi publications.xml.'
            );
        }
    }


    /**
     * Throw a useful XML parsing exception.
     */
    private function throwXmlError(): never
    {
        $errors = libxml_get_errors();

        $details = [];

        foreach ($errors as $error) {
            $details[] = trim(
                $error->message
            );
        }

        libxml_clear_errors();

        $message = 'Không thể đọc dữ liệu publications.xml.';

        if ($details !== []) {
            $message .= ' '
                . implode(
                    ' | ',
                    $details
                );
        }

        throw new RuntimeException(
            $message
        );
    }
}