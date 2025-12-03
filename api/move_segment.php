<?php
/**
 * API để di chuyển một đoạn từ chuyến này sang chuyến khác
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/LuuKetQua.php';

try {
    $tenTau = $_POST['ten_tau'] ?? '';
    $fromTrip = $_POST['from_trip'] ?? '';
    $toTrip = $_POST['to_trip'] ?? '';
    $segmentIndex = $_POST['segment_index'] ?? '';
    
    // Validate input parameters
    if (empty($tenTau) || empty($fromTrip) || empty($toTrip) || $segmentIndex === '') {
        throw new Exception('Thiếu thông tin cần thiết');
    }
    
    // Validate trip numbers are numeric
    if (!is_numeric($fromTrip) || !is_numeric($toTrip)) {
        throw new Exception('Mã chuyến phải là số');
    }
    
    // Validate segment index is numeric and non-negative
    if (!is_numeric($segmentIndex) || (int)$segmentIndex < 0) {
        throw new Exception('Chỉ số đoạn không hợp lệ');
    }
    
    // Convert to proper types
    $fromTrip = (int)$fromTrip;
    $toTrip = (int)$toTrip;
    $segmentIndex = (int)$segmentIndex;
    
    // Validate trip numbers are positive
    if ($fromTrip <= 0 || $toTrip <= 0) {
        throw new Exception('Mã chuyến phải là số dương');
    }
    
    $luuKetQua = new LuuKetQua();
    
    // Lấy tất cả dữ liệu
    $allData = $luuKetQua->docTatCa();
    
    // Tìm đoạn cần di chuyển
    $segmentToMove = null;
    $segmentRowIndex = -1;
    
    foreach ($allData as $index => $row) {
        if ($row['ten_phuong_tien'] === $tenTau && 
            $row['so_chuyen'] == $fromTrip && 
            (int)($row['cap_them'] ?? 0) === 0) {
            
            if ($segmentIndex == 0) {
                $segmentToMove = $row;
                $segmentRowIndex = (int)($row['___idx'] ?? 0);
                break;
            }
            $segmentIndex--;
        }
    }
    
    if (!$segmentToMove || $segmentRowIndex <= 0) {
        throw new Exception('Không tìm thấy đoạn cần di chuyển');
    }
    
    // Cập nhật mã chuyến của đoạn
    $segmentToMove['so_chuyen'] = $toTrip;
    
    // Cập nhật lại vào file với file locking
    $handle = fopen(KET_QUA_FILE, 'r+');
    if (!$handle) {
        throw new Exception('Không thể mở file dữ liệu');
    }
    
    // Acquire exclusive lock
    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        throw new Exception('Không thể khóa file để cập nhật dữ liệu');
    }
    
    $lines = [];
    while (($line = fgets($handle)) !== false) {
        $lines[] = rtrim($line, "\r\n");
    }
    
    // Đọc headers
    $headers = str_getcsv($lines[0]);
    
    // Tạo dòng mới với mã chuyến đã cập nhật
    $newRow = [];
    foreach ($headers as $header) {
        $newRow[] = $segmentToMove[$header] ?? '';
    }
    
    // Cập nhật dòng trong file ($segmentRowIndex đã là 1-based từ ___idx)
    $lines[$segmentRowIndex] = toCsvLine($newRow);
    
    // Rewind and truncate file
    rewind($handle);
    ftruncate($handle, 0);
    
    // Write all lines back
    foreach ($lines as $line) {
        fwrite($handle, $line . PHP_EOL);
    }
    
    // Release lock and close file
    flock($handle, LOCK_UN);
    fclose($handle);
    
    echo json_encode([
        'success' => true,
        'message' => 'Đã di chuyển đoạn thành công',
        'segment_info' => [
            'tuyen' => $segmentToMove['diem_di'] . ' → ' . $segmentToMove['diem_den'],
            'from_trip' => $fromTrip,
            'to_trip' => $toTrip
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// Helper function để tạo CSV line
function toCsvLine(array $fields): string {
    $fh = fopen('php://temp', 'r+');
    fputcsv($fh, $fields);
    rewind($fh);
    $line = stream_get_contents($fh);
    fclose($fh);
    return rtrim($line, "\r\n");
}
?>
