<?php

declare(strict_types=1);

/**
 * THD Digital Publishing
 *
 * Settings XML Repository.
 *
 * Responsibilities:
 * - Read settings.xml
 * - Read individual settings
 * - Update settings
 * - Persist XML safely with file locking
 *
 * This repository must NOT:
 * - Render HTML
 * - Handle authentication
 * - Handle CSRF
 * - Contain business logic
 * - Handle uploaded files
 */


/*
|--------------------------------------------------------------------------
| XML structure
|--------------------------------------------------------------------------
|
| <settings>
|     <school>
|         <name>THPT A Trần Hưng Đạo</name>
|         <logo>...</logo>
|     </school>
|
|     <platform>
|         <name>Thư viện Ấn phẩm số</name>
|         <description>...</description>
|         <favicon>...</favicon>
|     </platform>
| </settings>
|
*/


class SettingsRepository
{
    /**
     * XML file path.
     */
    private string $xmlPath;


    /**
     * Create repository.
     *
     * @param string|null $xmlPath Optional path for testing.
     */
    public function __construct(
        ?string $xmlPath = null
    ) {
        $this->xmlPath = $xmlPath ?? SETTINGS_XML;
    }


    /*
    |--------------------------------------------------------------------------
    | Read operations
    |--------------------------------------------------------------------------
    */

    /**
     * Get all settings as a nested associative array.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $xml = $this->loadXml();

        return $this->nodeToArray(
            $xml
        );
    }


    /**
     * Get a setting using dot notation.
     *
     * Example:
     *
     * get('school.name')
     * get('platform.description')
     *
     * @param mixed $default
     */
    public function get(
        string $key,
        mixed $default = null
    ): mixed {
        $key = trim($key);

        if ($key === '') {
            return $default;
        }

        $settings = $this->all();

        $segments = explode(
            '.',
            $key
        );

        $value = $settings;

        foreach ($segments as $segment) {
            if (
                !is_array($value)
                || !array_key_exists(
                    $segment,
                    $value
                )
            ) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }


    /**
     * Get multiple settings.
     *
     * Example:
     *
     * getMany([
     *     'school.name',
     *     'platform.name',
     * ])
     *
     * @param array<int, string> $keys
     *
     * @return array<string, mixed>
     */
    public function getMany(
        array $keys
    ): array {
        $result = [];

        foreach ($keys as $key) {
            if (!is_string($key)) {
                continue;
            }

            $result[$key] = $this->get(
                $key
            );
        }

        return $result;
    }


    /*
    |--------------------------------------------------------------------------
    | Write operations
    |--------------------------------------------------------------------------
    */

    /**
     * Set a single setting using dot notation.
     *
     * Example:
     *
     * set('school.name', 'THPT A Trần Hưng Đạo')
     *
     * @param mixed $value
     */
    public function set(
        string $key,
        mixed $value
    ): void {
        $key = trim($key);

        if ($key === '') {
            throw new InvalidArgumentException(
                'Tên setting không được để trống.'
            );
        }

        $segments = explode(
            '.',
            $key
        );

        foreach ($segments as $segment) {
            if (
                $segment === ''
                || preg_match(
                    '/^[a-zA-Z0-9_-]+$/',
                    $segment
                ) !== 1
            ) {
                throw new InvalidArgumentException(
                    'Tên setting không hợp lệ.'
                );
            }
        }

        $this->withExclusiveLock(
            function ($handle) use (
                $segments,
                $value
            ): void {
                $xml = $this->loadXmlFromHandle(
                    $handle
                );

                $node = $xml;

                foreach ($segments as $segment) {
                    if (
                        !isset(
                            $node->{$segment}
                        )
                    ) {
                        $node = $node->addChild(
                            $segment
                        );
                    } else {
                        $node = $node->{$segment};
                    }
                }

                $node = $this->normalizeValue(
                    $value
                );

                /*
                 * The previous variable now contains the scalar
                 * value, so update the actual XML node again.
                 */
                $targetNode = $xml;

                foreach (
                    $segments as $index => $segment
                ) {
                    if (
                        $index === count($segments) - 1
                    ) {
                        $targetNode->{$segment} =
                            $node;

                        break;
                    }

                    $targetNode =
                        $targetNode->{$segment};
                }

                $this->saveXmlToHandle(
                    $xml,
                    $handle
                );
            }
        );
    }


    /**
     * Set multiple settings in one XML write operation.
     *
     * Example:
     *
     * setMany([
     *     'school.name' => 'THPT A Trần Hưng Đạo',
     *     'platform.name' => 'Thư viện Ấn phẩm số',
     * ])
     *
     * @param array<string, mixed> $settings
     */
    public function setMany(
        array $settings
    ): void {
        if ($settings === []) {
            return;
        }

        $this->withExclusiveLock(
            function ($handle) use (
                $settings
            ): void {
                $xml = $this->loadXmlFromHandle(
                    $handle
                );

                foreach (
                    $settings as $key => $value
                ) {
                    if (!is_string($key)) {
                        throw new InvalidArgumentException(
                            'Tên setting không hợp lệ.'
                        );
                    }

                    $segments = $this->validateKey(
                        $key
                    );

                    $targetNode = $xml;

                    foreach (
                        $segments as $index => $segment
                    ) {
                        $isLast =
                            $index
                            === count($segments) - 1;

                        if (
                            !isset(
                                $targetNode->{$segment}
                            )
                        ) {
                            $targetNode =
                                $targetNode->addChild(
                                    $segment
                                );
                        } else {
                            $targetNode =
                                $targetNode->{$segment};
                        }

                        if ($isLast) {
                            $targetNode =
                                $this->normalizeValue(
                                    $value
                                );

                            /*
                             * Re-locate the actual node because
                             * SimpleXML assignment should happen
                             * on the XML element, not the scalar.
                             */
                            $parent = $xml;

                            $lastIndex =
                                count($segments) - 1;

                            foreach (
                                $segments as $i => $part
                            ) {
                                if ($i === $lastIndex) {
                                    $parent->{$part} =
                                        $targetNode;

                                    break;
                                }

                                $parent =
                                    $parent->{$part};
                            }
                        }
                    }
                }

                $this->saveXmlToHandle(
                    $xml,
                    $handle
                );
            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | XML conversion
    |--------------------------------------------------------------------------
    */

    /**
     * Convert XML recursively to an associative array.
     *
     * @return array<string, mixed>
     */
    private function nodeToArray(
        SimpleXMLElement $node
    ): array {
        $result = [];

        foreach ($node->children() as $child) {
            $name = $child->getName();

            if ($child->count() > 0) {
                $result[$name] =
                    $this->nodeToArray(
                        $child
                    );

                continue;
            }

            $result[$name] =
                $this->parseScalar(
                    (string) $child
                );
        }

        return $result;
    }


    /**
     * Convert simple XML values into useful PHP scalar values.
     */
    private function parseScalar(
        string $value
    ): mixed {
        $value = trim($value);

        if ($value === 'true') {
            return true;
        }

        if ($value === 'false') {
            return false;
        }

        return $value;
    }


    /**
     * Normalize values before writing XML.
     */
    private function normalizeValue(
        mixed $value
    ): string {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (
            is_int($value)
            || is_float($value)
            || is_string($value)
        ) {
            return (string) $value;
        }

        throw new InvalidArgumentException(
            'Giá trị setting phải là scalar.'
        );
    }


    /**
     * Validate dot-notation setting key.
     *
     * @return array<int, string>
     */
    private function validateKey(
        string $key
    ): array {
        $key = trim($key);

        if ($key === '') {
            throw new InvalidArgumentException(
                'Tên setting không được để trống.'
            );
        }

        $segments = explode(
            '.',
            $key
        );

        foreach ($segments as $segment) {
            if (
                $segment === ''
                || preg_match(
                    '/^[a-zA-Z0-9_-]+$/',
                    $segment
                ) !== 1
            ) {
                throw new InvalidArgumentException(
                    'Tên setting không hợp lệ.'
                );
            }
        }

        return $segments;
    }


    /*
    |--------------------------------------------------------------------------
    | XML loading
    |--------------------------------------------------------------------------
    */

    /**
     * Load settings.xml.
     */
    private function loadXml(): SimpleXMLElement
    {
        $this->ensureXmlFile();

        $previousUseErrors =
            libxml_use_internal_errors(true);

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
     * Load XML from a locked file handle.
     *
     * @param resource $handle
     */
    private function loadXmlFromHandle(
        $handle
    ): SimpleXMLElement {
        if (
            fseek(
                $handle,
                0,
                SEEK_SET
            ) !== 0
        ) {
            throw new RuntimeException(
                'Không thể đọc settings.xml.'
            );
        }

        $contents = stream_get_contents(
            $handle
        );

        if ($contents === false) {
            throw new RuntimeException(
                'Không thể đọc dữ liệu settings.xml.'
            );
        }

        if (trim($contents) === '') {
            return new SimpleXMLElement(
                '<settings></settings>'
            );
        }

        $previousUseErrors =
            libxml_use_internal_errors(true);

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
     * Save XML through an already locked handle.
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
                'Không thể tạo dữ liệu settings.xml.'
            );
        }

        if (
            ftruncate(
                $handle,
                0
            ) === false
        ) {
            throw new RuntimeException(
                'Không thể làm trống settings.xml.'
            );
        }

        if (
            fseek(
                $handle,
                0,
                SEEK_SET
            ) !== 0
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
                'Không thể ghi đầy đủ settings.xml.'
            );
        }

        fflush($handle);
    }


    /**
     * Execute callback while holding an exclusive XML lock.
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
                'Không thể mở settings.xml để ghi.'
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
                    'Không thể khóa settings.xml.'
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
    | XML initialization
    |--------------------------------------------------------------------------
    */

    /**
     * Ensure settings.xml exists.
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

        if (
            !file_exists(
                $this->xmlPath
            )
        ) {
            $initialXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<settings>
    <school>
        <name>THPT A Trần Hưng Đạo</name>
        <logo></logo>
    </school>

    <platform>
        <name>Thư viện Ấn phẩm số</name>
        <description>Thư viện Ấn phẩm số của THPT A Trần Hưng Đạo.</description>
        <favicon></favicon>
    </platform>
</settings>
XML;

            if (
                file_put_contents(
                    $this->xmlPath,
                    $initialXml,
                    LOCK_EX
                ) === false
            ) {
                throw new RuntimeException(
                    'Không thể tạo settings.xml.'
                );
            }

            return;
        }

        if (
            !is_readable(
                $this->xmlPath
            )
        ) {
            throw new RuntimeException(
                'Không thể đọc settings.xml.'
            );
        }

        if (
            !is_writable(
                $this->xmlPath
            )
        ) {
            throw new RuntimeException(
                'Không thể ghi settings.xml.'
            );
        }
    }


    /**
     * Throw an XML parsing exception.
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

        $message =
            'Không thể đọc dữ liệu settings.xml.';

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