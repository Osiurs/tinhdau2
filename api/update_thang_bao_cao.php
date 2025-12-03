<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/LuuKetQua.php';

try {
    $idx = isset($_POST['idx']) ? (int)$_POST['idx'] : 0; // ___idx 1-based
    $thang = isset($_POST['thang_bao_cao']) ? trim((string)$_POST['thang_bao_cao']) : '';

    if ($idx <= 0) { throw new Exception('Thiếu hoặc sai idx'); }
    if ($thang === '' || !preg_match('/^\d{4}-\d{2}$/', $thang)) { throw new Exception('Tháng không hợp lệ'); }

    $handle = fopen(KET_QUA_FILE, 'c+');
    if (!$handle) { throw new Exception('Không thể mở file dữ liệu'); }
    if (!flock($handle, LOCK_EX)) { fclose($handle); throw new Exception('Không thể khóa file'); }

    $lines = [];
    while (($line = fgets($handle)) !== false) { $lines[] = rtrim($line, "\r\n"); }
    if (empty($lines)) { throw new Exception('File rỗng'); }

    $headers = str_getcsv($lines[0]);
    $colIndex = array_search('thang_bao_cao', $headers, true);
    if ($colIndex === false) { flock($handle, LOCK_UN); fclose($handle); throw new Exception('Không tìm thấy cột thang_bao_cao'); }

    if (!isset($lines[$idx])) { flock($handle, LOCK_UN); fclose($handle); throw new Exception('Không tìm thấy dòng dữ liệu'); }
    $row = str_getcsv($lines[$idx]);
    $row[$colIndex] = $thang;

    // Ghi lại
    $lines[$idx] = toCsvLine($row);
    rewind($handle);
    ftruncate($handle, 0);
    foreach ($lines as $l) { fwrite($handle, $l . PHP_EOL); }
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function toCsvLine(array $fields): string {
    $fh = fopen('php://temp', 'r+');
    fputcsv($fh, $fields);
    rewind($fh);
    $line = stream_get_contents($fh);
    fclose($fh);
    return rtrim($line, "\r\n");
}
?>


