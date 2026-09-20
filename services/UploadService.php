<?php

declare(strict_types=1);

/**
 * THD Digital Publishing
 *
 * Upload Service.
 *
 * Responsibilities:
 * - Validate uploaded PDF
 * - Validate uploaded cover
 * - Verify MIME/content signatures
 * - Generate safe random storage names
 * - Store files inside publication storage
 * - Remove stored files safely
 *
 * This service must NOT:
 * - Render HTML
 * - Handle authentication
 * - Handle CSRF
 * - Write publication XML
 * - Decide publication status
 * - Trust original uploaded filenames
 *
 * Reader page generation is intentionally separated from
 * the basic upload operation. ReaderService can process
 * the stored PDF later.
 */

require_once THD_ROOT
    . DIRECTORY_SEPARATOR
    . 'core'
    . DIRECTORY_SEPARATOR
    . 'security.php';

require_once THD_ROOT
    . DIRECTORY_SEPARATOR
    . 'core'
    . DIRECTORY_SEPARATOR
    . 'validator.php';


class UploadService
{
    /**
     * Store an uploaded PDF for a publication.
     *
     * @param array<string, mixed> $file
     *
     * @return array<string, mixed>
     */
    public function storePdf(
        string $publicationId,
        array $file
    ): array {
        $publicationId =
            $this->validatePublicationId(
                $publicationId
            );

        $this->validateUploadArray(
            $file
        );

        $this->validatePdf(
            $file
        );

        $directory =
            $this->getPublicationDirectory(
                $publicationId
            );

        ensure_directory(
            $directory
        );

        $storedName =
            'source_'
            . bin2hex(
                random_bytes(16)
            )
            . '.pdf';

        $destination =
            $directory
            . DIRECTORY_SEPARATOR
            . $storedName;

        $this->assertSafeDestination(
            $directory,
            $destination
        );

        if (
            !move_uploaded_file(
                (string) $file['tmp_name'],
                $destination
            )
        ) {
            throw new RuntimeException(
                'Không thể lưu file PDF.'
            );
        }

        /*
         * Permissions on shared hosting can vary.
         * Do not make the file executable.
         */
        @chmod(
            $destination,
            0644
        );

        return [
            'original_name' =>
                sanitize_filename(
                    (string) (
                        $file['name']
                        ?? 'publication.pdf'
                    )
                ),

            'stored_name' =>
                $storedName,

            'path' =>
                $destination,

            'relative_path' =>
                $this->relativePublicationPath(
                    $publicationId,
                    $storedName
                ),

            'size' =>
                (int) $file['size'],

            'mime' =>
                $this->detectMime(
                    $destination
                ),
        ];
    }


    /**
     * Store an uploaded cover image.
     *
     * @param array<string, mixed> $file
     *
     * @return array<string, mixed>
     */
    public function storeCover(
        string $publicationId,
        array $file
    ): array {
        $publicationId =
            $this->validatePublicationId(
                $publicationId
            );

        $this->validateUploadArray(
            $file
        );

        $this->validateCover(
            $file
        );

        $directory =
            $this->getPublicationDirectory(
                $publicationId
            );

        ensure_directory(
            $directory
        );

        $extension =
            $this->detectCoverExtension(
                $file
            );

        $storedName =
            'cover_'
            . bin2hex(
                random_bytes(16)
            )
            . '.'
            . $extension;

        $destination =
            $directory
            . DIRECTORY_SEPARATOR
            . $storedName;

        $this->assertSafeDestination(
            $directory,
            $destination
        );

        if (
            !move_uploaded_file(
                (string) $file['tmp_name'],
                $destination
            )
        ) {
            throw new RuntimeException(
                'Không thể lưu ảnh bìa.'
            );
        }

        @chmod(
            $destination,
            0644
        );

        return [
            'original_name' =>
                sanitize_filename(
                    (string) (
                        $file['name']
                        ?? 'cover'
                    )
                ),

            'stored_name' =>
                $storedName,

            'path' =>
                $destination,

            'relative_path' =>
                $this->relativePublicationPath(
                    $publicationId,
                    $storedName
                ),

            'size' =>
                (int) $file['size'],

            'mime' =>
                $this->detectMime(
                    $destination
                ),

            'extension' =>
                $extension,
        ];
    }


    /**
     * Delete a single stored publication file.
     */
    public function deleteFile(
        string $publicationId,
        string $storedName
    ): void {
        $publicationId =
            $this->validatePublicationId(
                $publicationId
            );

        $storedName =
            trim($storedName);

        if (
            $storedName === ''
            || has_path_traversal($storedName)
            || preg_match(
                '/^[a-zA-Z0-9._-]+$/',
                $storedName
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Tên file lưu trữ không hợp lệ.'
            );
        }

        $directory =
            $this->getPublicationDirectory(
                $publicationId
            );

        $path =
            $directory
            . DIRECTORY_SEPARATOR
            . $storedName;

        $this->assertSafeDestination(
            $directory,
            $path
        );

        if (!file_exists($path)) {
            return;
        }

        if (!is_file($path)) {
            throw new RuntimeException(
                'Đường dẫn lưu trữ không phải file.'
            );
        }

        if (
            !unlink($path)
        ) {
            throw new RuntimeException(
                'Không thể xóa file lưu trữ.'
            );
        }
    }


    /**
     * Delete all physical files belonging to a publication.
     *
     * This should normally be called AFTER the publication
     * metadata has been removed successfully.
     */
    public function deletePublicationFiles(
        string $publicationId
    ): void {
        $publicationId =
            $this->validatePublicationId(
                $publicationId
            );

        $directory =
            $this->getPublicationDirectory(
                $publicationId
            );

        if (!file_exists($directory)) {
            return;
        }

        if (!is_dir($directory)) {
            throw new RuntimeException(
                'Publication storage không phải thư mục.'
            );
        }

        /*
         * Only delete a directory that is definitely
         * inside PUBLICATION_STORAGE_PATH.
         */
        $this->assertSafePublicationDirectory(
            $directory
        );

        $entries =
            scandir($directory);

        if ($entries === false) {
            throw new RuntimeException(
                'Không thể đọc publication storage.'
            );
        }

        foreach ($entries as $entry) {
            if (
                $entry === '.'
                || $entry === '..'
            ) {
                continue;
            }

            /*
             * Never accept nested paths here.
             */
            if (
                preg_match(
                    '/^[a-zA-Z0-9._-]+$/',
                    $entry
                ) !== 1
            ) {
                continue;
            }

            $path =
                $directory
                . DIRECTORY_SEPARATOR
                . $entry;

            if (
                is_file($path)
                && !unlink($path)
            ) {
                throw new RuntimeException(
                    'Không thể xóa file: '
                    . $entry
                );
            }
        }

        /*
         * Remove the publication directory only if
         * it is empty.
         */
        @rmdir($directory);
    }


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    /**
     * Validate generic PHP upload structure.
     *
     * @param array<string, mixed> $file
     */
    private function validateUploadArray(
        array $file
    ): void {
        if (
            !isset($file['error'])
            || !isset($file['tmp_name'])
            || !isset($file['name'])
            || !isset($file['size'])
        ) {
            throw new InvalidArgumentException(
                'Dữ liệu upload không hợp lệ.'
            );
        }

        if (
            !is_string($file['tmp_name'])
            || $file['tmp_name'] === ''
        ) {
            throw new InvalidArgumentException(
                'File tạm không hợp lệ.'
            );
        }

        if (
            !is_uploaded_file(
                $file['tmp_name']
            )
        ) {
            throw new RuntimeException(
                'Upload không đến từ HTTP upload hợp lệ.'
            );
        }

        if (
            !is_int($file['error'])
            && !ctype_digit(
                (string) $file['error']
            )
        ) {
            throw new InvalidArgumentException(
                'Mã lỗi upload không hợp lệ.'
            );
        }

        if (
            (int) $file['error']
            !== UPLOAD_ERR_OK
        ) {
            throw new RuntimeException(
                upload_error_message(
                    (int) $file['error']
                )
            );
        }

        if (
            !is_int($file['size'])
            && !is_numeric($file['size'])
        ) {
            throw new InvalidArgumentException(
                'Kích thước file không hợp lệ.'
            );
        }

        if (
            (int) $file['size'] <= 0
        ) {
            throw new InvalidArgumentException(
                'File upload đang trống.'
            );
        }
    }


    /**
     * Validate uploaded PDF.
     *
     * This validates:
     * - extension
     * - size
     * - MIME from server-side inspection
     * - PDF signature
     */
    private function validatePdf(
        array $file
    ): void {
        $extension =
            safe_extension(
                (string) $file['name']
            );

        if (
            !in_array(
                $extension,
                ALLOWED_PDF_EXTENSIONS,
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Chỉ cho phép upload file PDF.'
            );
        }

        if (
            (int) $file['size']
            > MAX_PDF_SIZE
        ) {
            throw new InvalidArgumentException(
                'File PDF vượt quá dung lượng cho phép.'
            );
        }

        $tmpPath =
            (string) $file['tmp_name'];

        $mime =
            $this->detectMime(
                $tmpPath
            );

        /*
         * finfo can return:
         * application/pdf
         *
         * Some hosting environments can return
         * application/octet-stream, so the signature
         * check below remains mandatory.
         */
        if (
            $mime !== 'application/pdf'
        ) {
            if (
                !$this->hasPdfSignature(
                    $tmpPath
                )
            ) {
                throw new InvalidArgumentException(
                    'Nội dung file không phải PDF hợp lệ.'
                );
            }
        }

        if (
            !$this->hasPdfSignature(
                $tmpPath
            )
        ) {
            throw new InvalidArgumentException(
                'File không có chữ ký PDF hợp lệ.'
            );
        }
    }


    /**
     * Validate uploaded cover image.
     */
    private function validateCover(
        array $file
    ): void {
        $extension =
            safe_extension(
                (string) $file['name']
            );

        if (
            !in_array(
                $extension,
                ALLOWED_COVER_EXTENSIONS,
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Định dạng ảnh bìa không được hỗ trợ.'
            );
        }

        if (
            (int) $file['size']
            > MAX_COVER_SIZE
        ) {
            throw new InvalidArgumentException(
                'Ảnh bìa vượt quá dung lượng cho phép.'
            );
        }

        $tmpPath =
            (string) $file['tmp_name'];

        $mime =
            $this->detectMime(
                $tmpPath
            );

        $allowedMimes = [
            'image/jpeg',
            'image/png',
            'image/webp',
        ];

        if (
            !in_array(
                $mime,
                $allowedMimes,
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Nội dung ảnh bìa không hợp lệ.'
            );
        }

        /*
         * getimagesize() performs an additional
         * structural check for actual image data.
         */
        $imageInfo =
            @getimagesize(
                $tmpPath
            );

        if (
            $imageInfo === false
            || empty($imageInfo['mime'])
        ) {
            throw new InvalidArgumentException(
                'Không thể xác thực cấu trúc ảnh bìa.'
            );
        }

        if (
            !in_array(
                $imageInfo['mime'],
                $allowedMimes,
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Loại ảnh bìa không được hỗ trợ.'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | MIME / file inspection
    |--------------------------------------------------------------------------
    */

    /**
     * Detect MIME using PHP's fileinfo extension.
     */
    private function detectMime(
        string $path
    ): string {
        if (
            !is_file($path)
            || !is_readable($path)
        ) {
            throw new RuntimeException(
                'Không thể đọc file để xác định MIME.'
            );
        }

        if (
            !class_exists('finfo')
        ) {
            throw new RuntimeException(
                'Server chưa bật PHP Fileinfo extension.'
            );
        }

        $finfo =
            new finfo(
                FILEINFO_MIME_TYPE
            );

        $mime =
            $finfo->file($path);

        if (
            !is_string($mime)
            || $mime === ''
        ) {
            throw new RuntimeException(
                'Không thể xác định MIME của file.'
            );
        }

        return strtolower(
            trim($mime)
        );
    }


    /**
     * Check PDF magic bytes.
     */
    private function hasPdfSignature(
        string $path
    ): bool {
        $handle =
            fopen(
                $path,
                'rb'
            );

        if ($handle === false) {
            return false;
        }

        try {
            $signature =
                fread(
                    $handle,
                    5
                );
        } finally {
            fclose($handle);
        }

        return $signature === '%PDF-';
    }


    /**
     * Determine cover extension from actual MIME.
     */
    private function detectCoverExtension(
        array $file
    ): string {
        $mime =
            $this->detectMime(
                (string) $file['tmp_name']
            );

        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',

            default => throw new InvalidArgumentException(
                'Không thể xác định định dạng ảnh bìa.'
            ),
        };
    }


    /*
    |--------------------------------------------------------------------------
    | Storage paths
    |--------------------------------------------------------------------------
    */

    /**
     * Validate publication ID.
     */
    private function validatePublicationId(
        string $publicationId
    ): string {
        $publicationId =
            trim($publicationId);

        if (
            $publicationId === ''
            || preg_match(
                '/^[a-zA-Z0-9_-]+$/',
                $publicationId
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Publication ID không hợp lệ.'
            );
        }

        return $publicationId;
    }


    /**
     * Get storage directory for a publication.
     */
    private function getPublicationDirectory(
        string $publicationId
    ): string {
        $directory =
            PUBLICATION_STORAGE_PATH
            . DIRECTORY_SEPARATOR
            . $publicationId;

        $this->assertSafePublicationDirectory(
            $directory
        );

        return $directory;
    }


    /**
     * Return a public/internal relative storage path.
     */
    private function relativePublicationPath(
        string $publicationId,
        string $storedName
    ): string {
        return 'storage/publications/'
            . rawurlencode($publicationId)
            . '/'
            . rawurlencode($storedName);
    }


    /**
     * Ensure publication directory cannot escape
     * PUBLICATION_STORAGE_PATH.
     */
    private function assertSafePublicationDirectory(
        string $directory
    ): void {
        $base =
            realpath(
                PUBLICATION_STORAGE_PATH
            );

        if ($base === false) {
            if (
                !ensure_directory(
                    PUBLICATION_STORAGE_PATH
                )
            ) {
                throw new RuntimeException(
                    'Không thể tạo publication storage.'
                );
            }

            $base =
                realpath(
                    PUBLICATION_STORAGE_PATH
                );
        }

        if ($base === false) {
            throw new RuntimeException(
                'Không thể xác định publication storage.'
            );
        }

        $normalizedBase =
            rtrim(
                normalize_path($base),
                DIRECTORY_SEPARATOR
            );

        $normalizedDirectory =
            normalize_path($directory);

        /*
         * The directory itself may not exist yet,
         * so compare its normalized path instead of
         * relying only on realpath().
         */
        $prefix =
            $normalizedBase
            . DIRECTORY_SEPARATOR;

        if (
            $normalizedDirectory !== $normalizedBase
            && !str_starts_with(
                $normalizedDirectory,
                $prefix
            )
        ) {
            throw new RuntimeException(
                'Đường dẫn publication storage không an toàn.'
            );
        }
    }


    /**
     * Ensure a file destination is inside its
     * intended publication directory.
     */
    private function assertSafeDestination(
        string $directory,
        string $destination
    ): void {
        $normalizedDirectory =
            rtrim(
                normalize_path(
                    $directory
                ),
                DIRECTORY_SEPARATOR
            );

        $normalizedDestination =
            normalize_path(
                $destination
            );

        $prefix =
            $normalizedDirectory
            . DIRECTORY_SEPARATOR;

        if (
            !str_starts_with(
                $normalizedDestination,
                $prefix
            )
        ) {
            throw new RuntimeException(
                'Đường dẫn file upload không an toàn.'
            );
        }

        $filename =
            basename(
                $normalizedDestination
            );

        if (
            $filename === ''
            || preg_match(
                '/^[a-zA-Z0-9._-]+$/',
                $filename
            ) !== 1
        ) {
            throw new RuntimeException(
                'Tên file lưu trữ không an toàn.'
            );
        }
    }
}