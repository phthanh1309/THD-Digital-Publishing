<?php
declare(strict_types=1);

require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/services/ReaderService.php';

$readerService = new ReaderService();

$slug = get_string('slug', '');

if ($slug === '') {
    abort_not_found('Không tìm thấy ấn phẩm.');
}

try {
    $publication = $readerService->getPublicPublication($slug);

    if ($publication === null) {
        abort_not_found('Không tìm thấy ấn phẩm.');
    }

    /*
     * Quyền in được kiểm tra hoàn toàn ở server.
     *
     * Việc giao diện có hoặc không có nút "In" không phải
     * là một cơ chế bảo mật.
     */
    if (!$readerService->canPrint($publication)) {
        http_response_code(403);
        header('Content-Type: text/html; charset=UTF-8');

        echo '<!DOCTYPE html>';
        echo '<html lang="vi">';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '<title>Không được phép in</title>';
        echo '</head>';
        echo '<body>';
        echo '<h1>403 - Không được phép in</h1>';
        echo '<p>Ấn phẩm này hiện không cho phép in.</p>';
        echo '<p><a href="'
            . e(publication_url((string) $publication['slug']))
            . '">Quay lại ấn phẩm</a></p>';
        echo '</body>';
        echo '</html>';

        exit;
    }

    /*
     * ReaderService chịu trách nhiệm xác định PDF nào được phép sử dụng.
     */
    $pdfPath = $readerService->resolvePrint($publication);

    if ($pdfPath === null || $pdfPath === '') {
        http_response_code(404);
        header('Content-Type: text/html; charset=UTF-8');

        echo '<!DOCTYPE html>';
        echo '<html lang="vi">';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '<title>Không tìm thấy tệp</title>';
        echo '</head>';
        echo '<body>';
        echo '<h1>404 - Không tìm thấy tệp</h1>';
        echo '<p>Tệp PDF của ấn phẩm chưa được cung cấp hoặc đã bị xóa.</p>';
        echo '<p><a href="'
            . e(publication_url((string) $publication['slug']))
            . '">Quay lại ấn phẩm</a></p>';
        echo '</body>';
        echo '</html>';

        exit;
    }

    $pdfPath = normalize_path($pdfPath);

    if (!is_file($pdfPath) || !is_readable($pdfPath)) {
        http_response_code(404);
        header('Content-Type: text/html; charset=UTF-8');

        echo '<!DOCTYPE html>';
        echo '<html lang="vi">';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '<title>Không tìm thấy tệp</title>';
        echo '</head>';
        echo '<body>';
        echo '<h1>404 - Không tìm thấy tệp</h1>';
        echo '<p>Tệp PDF không tồn tại hoặc không thể đọc.</p>';
        echo '<p><a href="'
            . e(publication_url((string) $publication['slug']))
            . '">Quay lại ấn phẩm</a></p>';
        echo '</body>';
        echo '</html>';

        exit;
    }

    /*
     * PDF phải nằm bên trong vùng lưu trữ publication.
     */
    if (!is_path_inside(PUBLICATION_STORAGE_PATH, $pdfPath)) {
        throw new RuntimeException(
            'Đường dẫn PDF nằm ngoài vùng lưu trữ cho phép.',
            500
        );
    }

    /*
     * Không tin extension hoặc dữ liệu metadata.
     * Kiểm tra MIME thực tế.
     */
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($pdfPath);

    if ($mimeType !== 'application/pdf') {
        throw new RuntimeException(
            'Tệp được khai báo là PDF nhưng MIME không hợp lệ.',
            500
        );
    }

    $fileSize = filesize($pdfPath);

    if ($fileSize === false || $fileSize < 1) {
        throw new RuntimeException(
            'Không thể xác định kích thước PDF.',
            500
        );
    }

    /*
     * Tên hiển thị cho trình đọc PDF của trình duyệt.
     */
    $title = (string) (
        $publication['title']
        ?? 'publication'
    );

    $fileName = sanitize_filename($title);

    if ($fileName === '') {
        $fileName = 'publication';
    }

    $fileName .= '.pdf';

    /*
     * Xóa output buffer trước khi truyền binary.
     */
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code(200);

    header('Content-Type: application/pdf');
    header('Content-Length: ' . (string) $fileSize);

    /*
     * inline:
     * trình duyệt được phép mở PDF trực tiếp thay vì ép tải xuống.
     */
    header(
        'Content-Disposition: inline; filename="'
        . str_replace('"', '', $fileName)
        . '"'
    );

    header('X-Content-Type-Options: nosniff');

    /*
     * Không cache response chứa nội dung có kiểm soát quyền.
     */
    header('Cache-Control: private, no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    $handle = fopen($pdfPath, 'rb');

    if ($handle === false) {
        throw new RuntimeException(
            'Không thể mở PDF để in.',
            500
        );
    }

    try {
        fpassthru($handle);
    } finally {
        fclose($handle);
    }

    exit;
} catch (RuntimeException $exception) {
    $code = $exception->getCode();

    if ($code === 403) {
        http_response_code(403);
        exit;
    }

    if ($code === 404) {
        abort_not_found($exception->getMessage());
    }

    throw $exception;
}