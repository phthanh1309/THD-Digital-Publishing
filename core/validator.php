<?php

declare(strict_types=1);

/**
 * THD Digital Publishing
 *
 * Input validation helpers.
 *
 * This file validates data shape and basic business constraints.
 * It must NOT:
 * - Read/write XML
 * - Access database
 * - Handle authentication
 * - Save uploaded files
 * - Render HTML
 *
 * Services will use these validators before changing application data.
 */


/*
|--------------------------------------------------------------------------
| Generic validation helpers
|--------------------------------------------------------------------------
*/

/**
 * Validate a required string.
 */
function validate_required_string(
    mixed $value,
    string $field,
    int $maxLength = 255
): ?string {
    if (!is_string($value)) {
        return $field . ' phải là chuỗi.';
    }

    $value = trim($value);

    if ($value === '') {
        return $field . ' không được để trống.';
    }

    if (
        mb_strlen($value, 'UTF-8') > $maxLength
    ) {
        return sprintf(
            '%s không được vượt quá %d ký tự.',
            $field,
            $maxLength
        );
    }

    return null;
}


/**
 * Validate an optional string.
 */
function validate_optional_string(
    mixed $value,
    string $field,
    int $maxLength = 255
): ?string {
    if ($value === null || $value === '') {
        return null;
    }

    if (!is_string($value)) {
        return $field . ' phải là chuỗi.';
    }

    if (
        mb_strlen(
            trim($value),
            'UTF-8'
        ) > $maxLength
    ) {
        return sprintf(
            '%s không được vượt quá %d ký tự.',
            $field,
            $maxLength
        );
    }

    return null;
}


/**
 * Validate an integer within a range.
 */
function validate_integer(
    mixed $value,
    string $field,
    int $min,
    int $max
): ?string {
    if (
        is_string($value)
        && !preg_match('/^-?\d+$/', trim($value))
    ) {
        return $field . ' phải là số nguyên.';
    }

    if (
        !is_int($value)
        && !is_string($value)
    ) {
        return $field . ' phải là số nguyên.';
    }

    $integer = filter_var(
        $value,
        FILTER_VALIDATE_INT
    );

    if ($integer === false) {
        return $field . ' phải là số nguyên.';
    }

    if ($integer < $min || $integer > $max) {
        return sprintf(
            '%s phải nằm trong khoảng %d đến %d.',
            $field,
            $min,
            $max
        );
    }

    return null;
}


/**
 * Validate a boolean-like value.
 */
function validate_boolean(
    mixed $value,
    string $field
): ?string {
    if (
        is_bool($value)
        || is_int($value) && in_array(
            $value,
            [0, 1],
            true
        )
    ) {
        return null;
    }

    if (
        is_string($value)
        && in_array(
            strtolower(trim($value)),
            [
                '0',
                '1',
                'true',
                'false',
                'yes',
                'no',
                'on',
                'off',
            ],
            true
        )
    ) {
        return null;
    }

    return $field . ' phải có giá trị boolean hợp lệ.';
}


/*
|--------------------------------------------------------------------------
| Publication validation
|--------------------------------------------------------------------------
*/

/**
 * Validate publication title.
 */
function validate_publication_title(
    mixed $title
): ?string {
    return validate_required_string(
        $title,
        'Tiêu đề',
        255
    );
}


/**
 * Validate publication subtitle.
 */
function validate_publication_subtitle(
    mixed $subtitle
): ?string {
    return validate_optional_string(
        $subtitle,
        'Tiêu đề phụ',
        500
    );
}


/**
 * Validate publication description.
 */
function validate_publication_description(
    mixed $description
): ?string {
    return validate_optional_string(
        $description,
        'Mô tả',
        10000
    );
}


/**
 * Validate publication year.
 */
function validate_publication_year(
    mixed $year
): ?string {
    return validate_integer(
        $year,
        'Năm xuất bản',
        1900,
        2200
    );
}


/**
 * Validate publication status.
 */
function validate_publication_status(
    mixed $status
): ?string {
    if (!is_string($status)) {
        return 'Trạng thái không hợp lệ.';
    }

    $status = trim($status);

    if (!is_valid_publication_status($status)) {
        return 'Trạng thái không được phép.';
    }

    return null;
}


/**
 * Validate a publication slug.
 */
function validate_publication_slug(
    mixed $slug
): ?string {
    if (!is_string($slug)) {
        return 'Slug không hợp lệ.';
    }

    $slug = trim($slug);

    if ($slug === '') {
        return 'Slug không được để trống.';
    }

    if (mb_strlen($slug, 'UTF-8') > 180) {
        return 'Slug không được vượt quá 180 ký tự.';
    }

    /*
     * Slug must already be normalized.
     *
     * Vietnamese title → slugify() should happen before
     * this validator is called.
     */
    if (
        preg_match(
            '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            $slug
        ) !== 1
    ) {
        return 'Slug chỉ được chứa chữ thường, số và dấu gạch ngang.';
    }

    return null;
}


/**
 * Validate an optional author/editor name.
 */
function validate_publication_person(
    mixed $value,
    string $field
): ?string {
    return validate_optional_string(
        $value,
        $field,
        255
    );
}


/**
 * Validate publication language.
 */
function validate_publication_language(
    mixed $language
): ?string {
    if (
        $language === null
        || $language === ''
    ) {
        return null;
    }

    if (!is_string($language)) {
        return 'Ngôn ngữ không hợp lệ.';
    }

    if (
        preg_match(
            '/^[a-zA-Z]{2,10}(?:-[a-zA-Z]{2,10})?$/',
            trim($language)
        ) !== 1
    ) {
        return 'Mã ngôn ngữ không hợp lệ.';
    }

    return null;
}


/*
|--------------------------------------------------------------------------
| Reader settings validation
|--------------------------------------------------------------------------
*/

/**
 * Validate reader permission settings.
 */
function validate_reader_settings(
    mixed $settings
): array {
    $errors = [];

    if (!is_array($settings)) {
        return [
            'settings' => 'Thiết lập ấn phẩm không hợp lệ.',
        ];
    }

    $booleanFields = [
        'allow_download' => 'Cho phép tải xuống',
        'allow_print' => 'Cho phép in',
        'allow_share' => 'Cho phép chia sẻ',
    ];

    foreach ($booleanFields as $field => $label) {
        if (!array_key_exists($field, $settings)) {
            continue;
        }

        $error = validate_boolean(
            $settings[$field],
            $label
        );

        if ($error !== null) {
            $errors[$field] = $error;
        }
    }

    return $errors;
}


/*
|--------------------------------------------------------------------------
| Publication payload validation
|--------------------------------------------------------------------------
*/

/**
 * Validate the editable publication metadata.
 *
 * Returns an associative array of errors.
 *
 * Empty array means valid.
 */
function validate_publication_payload(
    array $data
): array {
    $errors = [];

    $validators = [
        'title' => fn () =>
            validate_publication_title(
                $data['title'] ?? null
            ),

        'subtitle' => fn () =>
            validate_publication_subtitle(
                $data['subtitle'] ?? null
            ),

        'description' => fn () =>
            validate_publication_description(
                $data['description'] ?? null
            ),

        'year' => fn () =>
            validate_publication_year(
                $data['year'] ?? null
            ),

        'status' => fn () =>
            validate_publication_status(
                $data['status'] ?? DEFAULT_PUBLICATION_STATUS
            ),

        'author' => fn () =>
            validate_publication_person(
                $data['author'] ?? null,
                'Tác giả'
            ),

        'editor' => fn () =>
            validate_publication_person(
                $data['editor'] ?? null,
                'Biên tập'
            ),

        'language' => fn () =>
            validate_publication_language(
                $data['language'] ?? null
            ),
    ];

    foreach ($validators as $field => $validator) {
        $error = $validator();

        if ($error !== null) {
            $errors[$field] = $error;
        }
    }

    if (
        isset($data['slug'])
        && ($error = validate_publication_slug(
            $data['slug']
        )) !== null
    ) {
        $errors['slug'] = $error;
    }

    if (
        isset($data['settings'])
    ) {
        $settingsErrors = validate_reader_settings(
            $data['settings']
        );

        if ($settingsErrors !== []) {
            $errors['settings'] = $settingsErrors;
        }
    }

    return $errors;
}


/*
|--------------------------------------------------------------------------
| Upload validation
|--------------------------------------------------------------------------
*/

/**
 * Validate a PDF upload at the basic PHP upload layer.
 *
 * Deeper MIME/content validation belongs to UploadService.
 */
function validate_pdf_upload(
    mixed $file
): ?string {
    if (!is_array($file)) {
        return 'File PDF không hợp lệ.';
    }

    if (!isset($file['error'])) {
        return 'Không xác định được trạng thái upload.';
    }

    $error = $file['error'];

    if (
        !is_int($error)
        || $error !== UPLOAD_ERR_OK
    ) {
        return upload_error_message(
            is_int($error) ? $error : -1
        );
    }

    $size = $file['size'] ?? null;

    if (
        !is_int($size)
        || $size <= 0
    ) {
        return 'File PDF rỗng hoặc không hợp lệ.';
    }

    if ($size > MAX_PDF_SIZE) {
        return sprintf(
            'File PDF không được vượt quá %d MB.',
            (int) (
                MAX_PDF_SIZE / 1024 / 1024
            )
        );
    }

    $filename = $file['name'] ?? '';

    if (!is_string($filename)) {
        return 'Tên file PDF không hợp lệ.';
    }

    $extension = safe_extension(
        $filename
    );

    if (
        !in_array(
            $extension,
            ALLOWED_PDF_EXTENSIONS,
            true
        )
    ) {
        return 'Chỉ chấp nhận file PDF.';
    }

    return null;
}


/**
 * Validate a cover image at the basic upload layer.
 *
 * MIME/content validation belongs to UploadService.
 */
function validate_cover_upload(
    mixed $file
): ?string {
    if (!is_array($file)) {
        return 'File ảnh bìa không hợp lệ.';
    }

    if (!isset($file['error'])) {
        return 'Không xác định được trạng thái upload.';
    }

    $error = $file['error'];

    /*
     * Empty cover is allowed because a publication may
     * initially have no custom cover.
     */
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (
        !is_int($error)
        || $error !== UPLOAD_ERR_OK
    ) {
        return upload_error_message(
            is_int($error) ? $error : -1
        );
    }

    $size = $file['size'] ?? null;

    if (
        !is_int($size)
        || $size <= 0
    ) {
        return 'Ảnh bìa rỗng hoặc không hợp lệ.';
    }

    if ($size > MAX_COVER_SIZE) {
        return sprintf(
            'Ảnh bìa không được vượt quá %d MB.',
            (int) (
                MAX_COVER_SIZE / 1024 / 1024
            )
        );
    }

    $filename = $file['name'] ?? '';

    if (!is_string($filename)) {
        return 'Tên ảnh bìa không hợp lệ.';
    }

    $extension = safe_extension(
        $filename
    );

    if (
        !in_array(
            $extension,
            ALLOWED_COVER_EXTENSIONS,
            true
        )
    ) {
        return 'Định dạng ảnh bìa không được hỗ trợ.';
    }

    return null;
}


/*
|--------------------------------------------------------------------------
| Validation utilities
|--------------------------------------------------------------------------
*/

/**
 * Throw a validation exception when errors exist.
 *
 * The errors are stored in the exception object through
 * a dedicated custom property attached to the exception.
 */
function require_valid(
    array $errors
): void {
    if ($errors === []) {
        return;
    }

    throw new ValidationException(
        'Dữ liệu không hợp lệ.',
        $errors
    );
}


/*
|--------------------------------------------------------------------------
| Validation exception
|--------------------------------------------------------------------------
*/

class ValidationException extends RuntimeException
{
    /**
     * @var array<string, mixed>
     */
    private array $errors;

    /**
     * @param array<string, mixed> $errors
     */
    public function __construct(
        string $message,
        array $errors = []
    ) {
        parent::__construct(
            $message,
            422
        );

        $this->errors = $errors;
    }


    /**
     * Get validation errors.
     *
     * @return array<string, mixed>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}