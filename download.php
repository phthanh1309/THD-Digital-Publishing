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
     * Kiểm tra quyền tải xuống từ dữ liệu của ấn phẩm.
     *
     * Đây là kiểm tra bảo mật phía server.
     * Việc ẩn/hiện nút "Tải xuống" ở giao diện không đủ để bảo vệ file.
     */
    if (!$readerService->canDownload($publication)) {
        http_response_code(403);
        header('Content-Type: text/html; charset=UTF-8');

        echo '<!DOCTYPE html>';
        echo '<html lang="vi">';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '<title>Không được phép tải xuống</title>';
        echo '</head>';
        echo '<body>';
        echo '<h1>403 - Không được phép tải xuống</h1>';
        echo '<p>Ấn phẩm này hiện không cho phép tải xuống.</p>';
        echo '<p><a href="'
            . e(publication_url((string) $publication['slug']))
            . '">Quay lại ấn phẩm</a></p>';
        echo '</body>';
        echo '</html>';

        exit;
    }

    /*
     * ReaderService chịu trách nhiệm resolve đường dẫn PDF.
     * Endpoint này không tự ghép đường dẫn từ dữ liệu GET.
     */
    $pdfPath = $readerService->resolveDownload($publication);

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

    /*
     * Chuẩn hóa và kiểm tra path trước khi đọc file.
     */
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
     * Kiểm tra file phải nằm trong vùng lưu trữ publication.
     * Không cho ReaderService hoặc XML vô tình trỏ ra ngoài storage.
     */
    if (!is_path_inside(PUBLICATION_STORAGE_PATH, $pdfPath)) {
        throw new RuntimeException(
            'Đường dẫn PDF nằm ngoài vùng lưu trữ cho phép.',
            500
        );
    }

    /*
     * Kiểm tra lại MIME bằng finfo.
     * Không tin extension hoặc metadata trong XML.
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
     * Tên file tải xuống:
     * - không sử dụng trực tiếp tên file lưu trữ random;
     * - tạo từ title đã được sanitize.
     */
    $title = (string) (
        $publication['title']
        ?? 'publication'
    );

    $downloadName = sanitize_filename($title);

    if ($downloadName === '') {
        $downloadName = 'publication';
    }

    $downloadName .= '.pdf';

    /*
     * Xóa mọi output buffer trước khi gửi binary.
     */
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    /*
     * Headers bảo vệ việc truyền file.
     */
    http_response_code(200);

    header('Content-Type: application/pdf');
    header('Content-Length: ' . (string) $fileSize);
    header(
        'Content-Disposition: attachment; filename="'
        . str_replace('"', '', $downloadName)
        . '"'
    );

    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    /*
     * Không dùng readfile() trước khi xác nhận toàn bộ điều kiện phía trên.
     */
    $handle = fopen($pdfPath, 'rb');

    if ($handle === false) {
        throw new RuntimeException(
            'Không thể mở PDF để tải xuống.',
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
    /*
     * Các lỗi HTTP có chủ đích được xử lý rõ ràng.
     * Lỗi còn lại để bootstrap exception handler xử lý.
     */
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