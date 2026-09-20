<?php
declare(strict_types=1);

require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/services/ReaderService.php';

$readerService = new ReaderService();

$slug = get_string('slug', '');
$page = get_int('page', DEFAULT_READER_PAGE);

if ($slug === '') {
    abort_not_found('Không tìm thấy ấn phẩm.');
}

if ($page < 1) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');

    echo 'Số trang không hợp lệ.';
    exit;
}

try {
    /*
     * ReaderService chịu trách nhiệm:
     * - chỉ lấy publication đã published;
     * - kiểm tra page;
     * - xác định nguồn page image;
     * - không để endpoint tự suy luận filesystem path.
     */
    $publication = $readerService->getPublicPublication($slug);

    if ($publication === null) {
        abort_not_found('Không tìm thấy ấn phẩm.');
    }

    /*
     * Lấy thông tin trang thông qua ReaderService.
     */
    $pageData = $readerService->getPage(
        $publication,
        $page
    );

    if (!is_array($pageData)) {
        abort_not_found('Không tìm thấy trang.');
    }

    $assetPath = (string) (
        $pageData['path']
        ?? $pageData['asset_path']
        ?? ''
    );

    if ($assetPath === '') {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');

        echo 'Trang hiện chưa có tài nguyên hình ảnh.';
        exit;
    }

    $assetPath = normalize_path($assetPath);

    if (!is_file($assetPath) || !is_readable($assetPath)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');

        echo 'Không tìm thấy tài nguyên trang.';
        exit;
    }

    /*
     * Không cho asset endpoint phục vụ file bên ngoài
     * storage/publications.
     *
     * QUAN TRỌNG:
     * is_path_inside($path, $baseDirectory)
     *
     * Phải truyền $assetPath trước,
     * PUBLICATION_STORAGE_PATH sau.
     */
    if (!is_path_inside(
        $assetPath,
        PUBLICATION_STORAGE_PATH
    )) {
        throw new RuntimeException(
            'Đường dẫn reader asset nằm ngoài vùng lưu trữ cho phép.',
            403
        );
    }

    /*
     * Chỉ cho phép các định dạng ảnh được hỗ trợ.
     */
    $finfo = new finfo(FILEINFO_MIME_TYPE);

    $mimeType = $finfo->file($assetPath);

    $allowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    if (!in_array(
        $mimeType,
        $allowedMimeTypes,
        true
    )) {
        throw new RuntimeException(
            'Reader asset có MIME type không hợp lệ.',
            500
        );
    }

    /*
     * Lấy kích thước file.
     */
    $fileSize = filesize($assetPath);

    if (
        $fileSize === false
        || $fileSize < 1
    ) {
        throw new RuntimeException(
            'Không thể xác định kích thước reader asset.',
            500
        );
    }

    /*
     * Kiểm tra file thực sự là image.
     */
    $imageInfo = @getimagesize(
        $assetPath
    );

    if ($imageInfo === false) {
        throw new RuntimeException(
            'Reader asset không phải hình ảnh hợp lệ.',
            500
        );
    }

    /*
     * Kiểm tra MIME từ image metadata.
     */
    $imageMimeType = (string) (
        $imageInfo['mime']
        ?? ''
    );

    if (
        $imageMimeType !== ''
        && !in_array(
            $imageMimeType,
            $allowedMimeTypes,
            true
        )
    ) {
        throw new RuntimeException(
            'Định dạng hình ảnh của reader asset không được phép.',
            500
        );
    }

    /*
     * Xóa output buffer trước khi gửi binary image.
     */
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code(200);

    header(
        'Content-Type: ' . $mimeType
    );

    header(
        'Content-Length: ' . (string) $fileSize
    );

    /*
     * Hiển thị trực tiếp trong trình duyệt.
     */
    header(
        'Content-Disposition: inline'
    );

    header(
        'X-Content-Type-Options: nosniff'
    );

    /*
     * Cho phép cache page image.
     */
    header(
        'Cache-Control: public, max-age=3600'
    );

    /*
     * Last-Modified.
     */
    $lastModified = filemtime(
        $assetPath
    );

    if ($lastModified !== false) {
        header(
            'Last-Modified: '
            . gmdate(
                'D, d M Y H:i:s',
                $lastModified
            )
            . ' GMT'
        );
    }

    /*
     * Mở và stream image.
     */
    $handle = fopen(
        $assetPath,
        'rb'
    );

    if ($handle === false) {
        throw new RuntimeException(
            'Không thể mở reader asset.',
            500
        );
    }

    try {
        fpassthru($handle);
    } finally {
        fclose($handle);
    }

    exit;

} catch (OutOfBoundsException $exception) {

    /*
     * Page nằm ngoài phạm vi publication.
     */
    http_response_code(404);

    header(
        'Content-Type: text/plain; charset=UTF-8'
    );

    echo 'Không tìm thấy trang.';
    exit;

} catch (RuntimeException $exception) {

    $code = $exception->getCode();

    if ($code === 403) {

        http_response_code(403);

        header(
            'Content-Type: text/plain; charset=UTF-8'
        );

        echo 'Không được phép truy cập tài nguyên này.';
        exit;
    }

    if ($code === 404) {

        abort_not_found(
            $exception->getMessage()
        );
    }

    throw $exception;
}