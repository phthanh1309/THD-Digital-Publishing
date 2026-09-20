<?php
declare(strict_types=1);

require_once __DIR__ . '/core/bootstrap.php';

require_once THD_ROOT . '/repositories/PublicationRepository.php';
require_once THD_ROOT . '/services/ReaderService.php';

$readerService = new ReaderService();

/*
|--------------------------------------------------------------------------
| Reader PDF endpoint
|--------------------------------------------------------------------------
|
| Hỗ trợ 2 dạng:
|
| 1. reader-pdf.php?slug=test
|
| 2. reader-pdf.php
|      ?publication=pub_xxx
|      &file=source_xxx.pdf
|
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| Helper: trả lỗi text/plain
|--------------------------------------------------------------------------
*/

$sendError = static function (
    int $status,
    string $message
): never {
    http_response_code($status);

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header(
        'Content-Type: text/plain; charset=UTF-8'
    );

    header(
        'X-Content-Type-Options: nosniff'
    );

    echo $message;

    exit;
};


/*
|--------------------------------------------------------------------------
| Lấy publication
|--------------------------------------------------------------------------
*/

$slug = trim(
    (string) (
        get_string('slug', '')
        ?? ''
    )
);

$publicationId = trim(
    (string) (
        get_string('publication', '')
        ?? ''
    )
);

$file = trim(
    (string) (
        get_string('file', '')
        ?? ''
    )
);


/*
|--------------------------------------------------------------------------
| Mode 1:
| ?slug=test
|--------------------------------------------------------------------------
*/

$publication = null;

if ($slug !== '') {

    try {

        $publication = $readerService->getPublicPublication(
            $slug
        );

    } catch (Throwable $exception) {

        $sendError(
            404,
            'Không tìm thấy ấn phẩm.'
        );
    }

    if (
        !is_array($publication)
        || $publication === []
    ) {
        $sendError(
            404,
            'Không tìm thấy ấn phẩm.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Mode 2:
| ?publication=pub_xxx&file=xxx.pdf
|--------------------------------------------------------------------------
*/

if (
    $publication === null
    && $publicationId !== ''
) {

    /*
     * ID publication chỉ cho phép:
     * chữ, số, _, -
     */
    if (
        !preg_match(
            '/^[a-zA-Z0-9_-]+$/',
            $publicationId
        )
    ) {
        $sendError(
            400,
            'Publication ID không hợp lệ.'
        );
    }


    /*
     * File phải là basename.
     *
     * Không cho phép:
     * ../
     * đường dẫn thư mục
     * URL
     * query string
     */
    if ($file === '') {
        $sendError(
            400,
            'Thiếu tên file PDF.'
        );
    }

    if (
        basename($file) !== $file
        || !preg_match(
            '/^[a-zA-Z0-9._-]+\.pdf$/i',
            $file
        )
    ) {
        $sendError(
            400,
            'Tên file PDF không hợp lệ.'
        );
    }


    /*
     * Đọc publication XML thông qua repository.
     */
    $publicationRepository = new PublicationRepository();

    try {

        $publication = $publicationRepository->findById(
            $publicationId
        );

    } catch (Throwable $exception) {

        $publication = null;
    }


    if (
        !is_array($publication)
        || $publication === []
    ) {
        $sendError(
            404,
            'Không tìm thấy ấn phẩm.'
        );
    }


    /*
     * Chỉ publication public mới được đọc.
     */
    $status = (string) (
        $publication['status']
        ?? ''
    );

    if ($status !== 'published') {
        $sendError(
            404,
            'Không tìm thấy ấn phẩm.'
        );
    }


    /*
     * Nếu publication có slug thì xác minh
     * lại thông qua ReaderService.
     */
    $publicationSlug = trim(
        (string) (
            $publication['slug']
            ?? ''
        )
    );

    if ($publicationSlug === '') {
        $sendError(
            404,
            'Ấn phẩm không có slug hợp lệ.'
        );
    }

    try {

        $publicPublication =
            $readerService->getPublicPublication(
                $publicationSlug
            );

    } catch (Throwable $exception) {

        $sendError(
            404,
            'Không tìm thấy ấn phẩm.'
        );
    }

    if (
        !is_array($publicPublication)
        || $publicPublication === []
    ) {
        $sendError(
            404,
            'Không tìm thấy ấn phẩm.'
        );
    }

    /*
     * Dùng dữ liệu publication đã được xác nhận public.
     */
    $publication = $publicPublication;
}


/*
|--------------------------------------------------------------------------
| Không có publication
|--------------------------------------------------------------------------
*/

if (
    !is_array($publication)
    || $publication === []
) {
    $sendError(
        404,
        'Không tìm thấy ấn phẩm.'
    );
}


/*
|--------------------------------------------------------------------------
| Resolve PDF
|--------------------------------------------------------------------------
*/

$pdfPath = null;


/*
 * Nếu URL truyền file cụ thể:
 *
 * publication=pub_xxx
 * file=source_xxx.pdf
 *
 * thì sử dụng file đó.
 */
if (
    $publicationId !== ''
    && $file !== ''
) {

    $resolvedPublicationId = trim(
        (string) (
            $publication['id']
            ?? ''
        )
    );

    if (
        $resolvedPublicationId === ''
        || $resolvedPublicationId !== $publicationId
    ) {
        $sendError(
            404,
            'Ấn phẩm không hợp lệ.'
        );
    }


    /*
     * PUBLICATION_STORAGE_PATH là thư mục
     * storage/publications.
     */
    $basePath = rtrim(
        PUBLICATION_STORAGE_PATH,
        DIRECTORY_SEPARATOR
    );

    $publicationPath = $basePath
        . DIRECTORY_SEPARATOR
        . $publicationId;

    $pdfPath = $publicationPath
        . DIRECTORY_SEPARATOR
        . $file;
}


/*
|--------------------------------------------------------------------------
| Nếu không có file cụ thể:
| dùng ReaderService resolvePdf()
|--------------------------------------------------------------------------
*/

if (
    $pdfPath === null
    && $slug !== ''
) {

    $resolved = $readerService->resolvePdf(
        $publication
    );

    if (is_array($resolved)) {

        $pdfPath = (string) (
            $resolved['path']
            ?? ''
        );

    } else {

        $pdfPath = (string) (
            $resolved
            ?? ''
        );
    }
}


/*
|--------------------------------------------------------------------------
| Không tìm thấy PDF
|--------------------------------------------------------------------------
*/

$pdfPath = trim(
    (string) ($pdfPath ?? '')
);

if ($pdfPath === '') {

    $sendError(
        404,
        'Không tìm thấy tài liệu PDF.'
    );
}


/*
|--------------------------------------------------------------------------
| Normalize path
|--------------------------------------------------------------------------
*/

$pdfPath = normalize_path(
    $pdfPath
);


/*
|--------------------------------------------------------------------------
| Kiểm tra file
|--------------------------------------------------------------------------
*/

if (!is_file($pdfPath)) {

    $sendError(
        404,
        'Tài liệu PDF không tồn tại.'
    );
}

if (!is_readable($pdfPath)) {

    $sendError(
        404,
        'Tài liệu PDF không thể đọc.'
    );
}


/*
|--------------------------------------------------------------------------
| Bảo vệ storage path
|--------------------------------------------------------------------------
|
| QUAN TRỌNG:
|
| is_path_inside(
|     $path,
|     $baseDirectory
| )
|
| nên phải truyền:
|
|     $pdfPath
|     PUBLICATION_STORAGE_PATH
|
| Không được đảo ngược.
|--------------------------------------------------------------------------
*/

if (
    !is_path_inside(
        $pdfPath,
        PUBLICATION_STORAGE_PATH
    )
) {

    $sendError(
        403,
        'Không được phép truy cập tài liệu.'
    );
}


/*
|--------------------------------------------------------------------------
| Kiểm tra MIME
|--------------------------------------------------------------------------
*/

$finfo = new finfo(
    FILEINFO_MIME_TYPE
);

$mimeType = $finfo->file(
    $pdfPath
);

if ($mimeType !== 'application/pdf') {

    http_response_code(500);

    header(
        'Content-Type: text/plain; charset=UTF-8'
    );

    echo 'Tệp không phải PDF hợp lệ.';

    exit;
}


/*
|--------------------------------------------------------------------------
| Kiểm tra PDF signature
|--------------------------------------------------------------------------
*/

$handle = fopen(
    $pdfPath,
    'rb'
);

if ($handle === false) {

    throw new RuntimeException(
        'Không thể mở PDF.'
    );
}

$signature = fread(
    $handle,
    5
);

fclose($handle);

if ($signature !== '%PDF-') {

    http_response_code(500);

    header(
        'Content-Type: text/plain; charset=UTF-8'
    );

    echo 'Tệp không có PDF signature hợp lệ.';

    exit;
}


/*
|--------------------------------------------------------------------------
| File size
|--------------------------------------------------------------------------
*/

$fileSize = filesize(
    $pdfPath
);

if (
    $fileSize === false
    || $fileSize < 1
) {

    throw new RuntimeException(
        'Không thể xác định kích thước PDF.'
    );
}


/*
|--------------------------------------------------------------------------
| HTTP Range
|--------------------------------------------------------------------------
*/

$rangeHeader = trim(
    (string) (
        $_SERVER['HTTP_RANGE']
        ?? ''
    )
);


/*
|--------------------------------------------------------------------------
| Không có Range
|--------------------------------------------------------------------------
*/

if ($rangeHeader === '') {

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code(200);

    header(
        'Content-Type: application/pdf'
    );

    header(
        'Content-Length: ' . (string) $fileSize
    );

    header(
        'Content-Disposition: inline'
    );

    header(
        'Accept-Ranges: bytes'
    );

    header(
        'X-Content-Type-Options: nosniff'
    );

    header(
        'Cache-Control: private, max-age=300, must-revalidate'
    );

    $handle = fopen(
        $pdfPath,
        'rb'
    );

    if ($handle === false) {

        throw new RuntimeException(
            'Không thể mở PDF để đọc.'
        );
    }

    try {

        fpassthru($handle);

    } finally {

        fclose($handle);
    }

    exit;
}


/*
|--------------------------------------------------------------------------
| Parse Range
|--------------------------------------------------------------------------
|
| Ví dụ:
|
| bytes=0-999
| bytes=500-
| bytes=-500
|--------------------------------------------------------------------------
*/

if (
    !preg_match(
        '/^bytes=(\d*)-(\d*)$/',
        $rangeHeader,
        $matches
    )
) {

    http_response_code(416);

    header(
        'Content-Type: text/plain; charset=UTF-8'
    );

    header(
        'Content-Range: bytes */'
        . (string) $fileSize
    );

    echo 'Range request không hợp lệ.';

    exit;
}


$rangeStart = $matches[1] !== ''
    ? (int) $matches[1]
    : null;

$rangeEnd = $matches[2] !== ''
    ? (int) $matches[2]
    : null;


/*
|--------------------------------------------------------------------------
| Suffix range
|--------------------------------------------------------------------------
|
| bytes=-500
|--------------------------------------------------------------------------
*/

if ($rangeStart === null) {

    if (
        $rangeEnd === null
        || $rangeEnd < 1
    ) {

        http_response_code(416);

        header(
            'Content-Type: text/plain; charset=UTF-8'
        );

        header(
            'Content-Range: bytes */'
            . (string) $fileSize
        );

        echo 'Range request không hợp lệ.';

        exit;
    }

    $length = min(
        $rangeEnd,
        $fileSize
    );

    $rangeStart = $fileSize - $length;

    $rangeEnd = $fileSize - 1;
}


/*
|--------------------------------------------------------------------------
| Range mở
|--------------------------------------------------------------------------
|
| bytes=500-
|--------------------------------------------------------------------------
*/

if ($rangeEnd === null) {

    $rangeEnd = $fileSize - 1;
}


/*
|--------------------------------------------------------------------------
| Validate range
|--------------------------------------------------------------------------
*/

if (
    $rangeStart < 0
    || $rangeEnd < $rangeStart
    || $rangeStart >= $fileSize
) {

    http_response_code(416);

    header(
        'Content-Type: text/plain; charset=UTF-8'
    );

    header(
        'Content-Range: bytes */'
        . (string) $fileSize
    );

    echo 'Range nằm ngoài phạm vi PDF.';

    exit;
}


/*
|--------------------------------------------------------------------------
| Không vượt quá byte cuối
|--------------------------------------------------------------------------
*/

$rangeEnd = min(
    $rangeEnd,
    $fileSize - 1
);

$contentLength =
    $rangeEnd
    - $rangeStart
    + 1;


/*
|--------------------------------------------------------------------------
| Response 206
|--------------------------------------------------------------------------
*/

while (ob_get_level() > 0) {
    ob_end_clean();
}

http_response_code(206);

header(
    'Content-Type: application/pdf'
);

header(
    'Content-Length: ' . (string) $contentLength
);

header(
    'Content-Range: bytes '
    . $rangeStart
    . '-'
    . $rangeEnd
    . '/'
    . $fileSize
);

header(
    'Content-Disposition: inline'
);

header(
    'Accept-Ranges: bytes'
);

header(
    'X-Content-Type-Options: nosniff'
);

header(
    'Cache-Control: private, max-age=300, must-revalidate'
);


/*
|--------------------------------------------------------------------------
| Stream PDF
|--------------------------------------------------------------------------
*/

$handle = fopen(
    $pdfPath,
    'rb'
);

if ($handle === false) {

    throw new RuntimeException(
        'Không thể mở PDF để đọc.'
    );
}

try {

    if (
        fseek(
            $handle,
            $rangeStart,
            SEEK_SET
        ) !== 0
    ) {

        throw new RuntimeException(
            'Không thể seek tới byte range yêu cầu.'
        );
    }

    $remaining = $contentLength;

    while (
        $remaining > 0
        && !feof($handle)
    ) {

        /*
         * Đọc tối đa 1 MB mỗi lần.
         */
        $chunkSize = min(
            1024 * 1024,
            $remaining
        );

        $chunk = fread(
            $handle,
            $chunkSize
        );

        if (
            $chunk === false
            || $chunk === ''
        ) {
            break;
        }

        echo $chunk;

        $remaining -= strlen(
            $chunk
        );

        if (connection_aborted()) {
            break;
        }
    }

} finally {

    fclose($handle);
}

exit;