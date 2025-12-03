<?php
/**
 * HELPER FUNCTIONS GLOBAL
 * Các hàm hỗ trợ được sử dụng trong toàn bộ hệ thống
 */

// Các hằng số đã được định nghĩa trong config/database.php

// Hàm truy cập array an toàn
function safe_array_get($array, $key, $default = '') {
    return isset($array[$key]) ? $array[$key] : $default;
}

// Hàm parse ngày từ format VN với validation cải tiến
function parse_date_vn($dateStr) {
    if (empty($dateStr) || !is_string($dateStr)) return false;
    
    // Trim whitespace
    $dateStr = trim($dateStr);
    
    // Xử lý format dd/mm/yyyy hoặc d/m/yyyy (có thể thiếu leading zero)
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $dateStr, $matches)) {
        $day = intval($matches[1]);
        $month = intval($matches[2]);
        $year = intval($matches[3]);
        
        // Validate date
        if (checkdate($month, $day, $year)) {
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }
    }
    
    // Xử lý format yyyy-mm-dd hoặc yyyy-m-d (có thể thiếu leading zero)
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $dateStr, $matches)) {
        $year = intval($matches[1]);
        $month = intval($matches[2]);
        $day = intval($matches[3]);
        
        // Validate date
        if (checkdate($month, $day, $year)) {
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }
    }
    
    // Try strtotime as fallback for other formats
    $timestamp = strtotime($dateStr);
    if ($timestamp !== false) {
        $parsed = date('Y-m-d', $timestamp);
        // Double-check the parsed date is valid
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $parsed, $matches)) {
            $year = intval($matches[1]);
            $month = intval($matches[2]);
            $day = intval($matches[3]);
            if (checkdate($month, $day, $year)) {
                return $parsed;
            }
        }
    }
    
    return false;
}

// Hàm format ngày sang format VN
function format_date_vn($dateStr) {
    if (empty($dateStr) || !is_string($dateStr)) return '';
    
    $timestamp = strtotime($dateStr);
    if ($timestamp === false) return '';
    
    return date('d/m/Y', $timestamp);
}

// Hàm phân loại cự ly
function phan_loai_cu_ly($khoangCach) {
    // Theo mô tả cấu hình: Ngắn: <= CU_LY_NGAN_MAX_KM
    if ($khoangCach <= CU_LY_NGAN_MAX_KM) return 'ngan';
    if ($khoangCach <= CU_LY_TRUNG_BINH_MAX_KM) return 'trung_binh';
    return 'dai';
}

// Hàm lấy nhãn cự ly
function label_cu_ly($nhom) {
    $labels = [
        'ngan' => 'Ngắn',
        'trung_binh' => 'Trung bình',
        'dai' => 'Dài'
    ];
    return $labels[$nhom] ?? 'Không xác định';
}

// ============================================
// NUMBER FORMATTING FUNCTIONS
// ============================================

/**
 * Format số với 1 chữ số thập phân (dùng cho XML export)
 * Dấu chấm thập phân, không phân cách nghìn
 */
function fmt1($value) {
    $value = (float)$value;
    return ($value === 0.0 ? '0.0' : number_format($value, 1, '.', ''));
}

/**
 * Format số với 2 chữ số thập phân (dùng cho XML export)
 * Dấu chấm thập phân, không phân cách nghìn
 */
function fmt2($value) {
    $value = (float)$value;
    return ($value === 0.0 ? '0.00' : number_format($value, 2, '.', ''));
}

/**
 * Format số với 7 chữ số thập phân (ví dụ hệ số)
 * Dấu chấm thập phân, không phân cách nghìn
 */
function fmt7($value) {
    $value = (float)$value;
    return number_format($value, 7, '.', '');
}

/**
 * Làm tròn xuống về số nguyên (floor)
 * Dùng để đồng bộ hiển thị giữa web và báo cáo
 */
function round0($value) {
    return (int)floor((float)$value);
}

/**
 * Làm tròn xuống số nguyên (floor) - alias của round0
 */
function floor0($value) {
    return (int)floor((float)$value);
}

/**
 * Format số cho WEB DISPLAY - Hiển thị trên web
 * - Giữ phần thập phân với dấu phẩy (,) làm dấu thập phân
 * - Dấu chấm (.) phân cách phần nghìn
 * - Nếu giá trị = 0 thì trả về chuỗi rỗng
 * 
 * @param float $value Giá trị cần format
 * @param int $decimals Số chữ số thập phân (mặc định 2)
 * @return string Chuỗi đã format hoặc rỗng nếu = 0
 */
function fmt_web($value, $decimals = 2) {
    $value = (float)$value;
    if ($value == 0) return '';
    return number_format($value, $decimals, ',', '.');
}

/**
 * Format số nguyên cho WEB DISPLAY
 * - Không có phần thập phân
 * - Dấu chấm (.) phân cách phần nghìn
 * - Nếu giá trị = 0 thì trả về chuỗi rỗng
 */
function fmt_web_int($value) {
    $value = (float)$value;
    if ($value == 0) return '';
    return number_format($value, 0, '', '.');
}

/**
 * Format số cho EXCEL EXPORT - Xuất báo cáo
 * - Làm tròn xuống (floor) thành số nguyên
 * - Dấu chấm (.) phân cách phần nghìn
 * - Nếu giá trị = 0 thì trả về chuỗi rỗng (để ô Excel trống)
 * 
 * @param float $value Giá trị cần format
 * @return string Chuỗi đã format hoặc rỗng nếu = 0
 */
function fmt_export($value) {
    $rounded = (int)floor((float)$value);
    if ($rounded == 0) return '';
    return number_format($rounded, 0, '', '.');
}

/**
 * Format số cho EXCEL EXPORT (trả về số nguyên thô, không format)
 * - Làm tròn xuống (floor) thành số nguyên
 * - Trả về 0 nếu giá trị = 0 (để có thể dùng trong tính toán)
 * 
 * @param float $value Giá trị cần làm tròn
 * @return int Số nguyên đã làm tròn xuống
 */
function fmt_export_raw($value) {
    return (int)floor((float)$value);
}

/**
 * Định dạng số nguyên theo chuẩn VN (legacy function - giữ lại để tương thích)
 * Dấu chấm phần ngàn, không thập phân
 */
function vn_int($value) {
    $value = (float)$value;
    return number_format($value, 0, '', '.');
}

// Hàm validation cơ bản
function validate_required($value, $fieldName) {
    if (empty($value)) {
        throw new InvalidArgumentException("$fieldName không được để trống");
    }
    return trim($value);
}

function validate_numeric($value, $fieldName, $min = null, $max = null) {
    $value = floatval($value);
    
    if ($min !== null && $value < $min) {
        throw new InvalidArgumentException("$fieldName không được nhỏ hơn $min");
    }
    
    if ($max !== null && $value > $max) {
        throw new InvalidArgumentException("$fieldName không được lớn hơn $max");
    }
    
    return $value;
}

// Hàm log error
function log_error($message, $context = []) {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $message,
        'context' => $context,
        'file' => debug_backtrace()[0]['file'] ?? 'unknown',
        'line' => debug_backtrace()[0]['line'] ?? 0
    ];
    
    error_log('ERROR: ' . json_encode($logEntry, JSON_UNESCAPED_UNICODE));
}

// Hàm response JSON
function json_response($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Hàm redirect
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

// Hàm sanitize input
function sanitize_input($input) {
    if (is_array($input)) {
        return array_map('sanitize_input', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Lightweight storage for transfer fractional idx overrides
function td2_read_transfer_overrides(): array {
    $file = __DIR__ . '/../data/transfer_overrides.json';
    if (!file_exists($file)) { return []; }
    $json = @file_get_contents($file);
    if ($json === false || $json === '') { return []; }
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function td2_write_transfer_overrides(array $data): void {
    $file = __DIR__ . '/../data/transfer_overrides.json';
    $dir = dirname($file);
    if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
    @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
}

// Generate a UUID v4 for pairing transfer rows
function td2_generate_uuid_v4(): string {
    $data = random_bytes(16);
    // Set version to 0100
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    // Set bits 6-7 to 10
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function td2_make_transfer_key(string $date, string $src, string $dst, float $absLiters): string {
    return trim($date) . '|' . trim($src) . '|' . trim($dst) . '|' . number_format(abs($absLiters), 3, '.', '');
}

// Find the two ledger rows in dau_ton.csv that represent a specific transfer
// identified by date (ISO), source ship, destination ship and absolute liters.
// Returns array with keys 'src' and 'dst' mapping to 0-based data indexes (excluding header),
// and the loaded headers/rows for reuse: ['indexes'=>['src'=>i,'dst'=>j],'headers'=>[], 'rows'=>[]]
function td2_find_transfer_rows(string $dateIso, string $srcShip, string $dstShip, float $absLiters): array {
    $file = __DIR__ . '/../data/dau_ton.csv';
    if (!file_exists($file)) { return ['indexes'=>[], 'headers'=>[], 'rows'=>[]]; }
    $fh = fopen($file, 'r');
    if (!$fh) { return ['indexes'=>[], 'headers'=>[], 'rows'=>[]]; }
    $headers = fgetcsv($fh) ?: [];
    $rows = [];
    while (($row = fgetcsv($fh)) !== false) { $rows[] = $row; }
    fclose($fh);

    // Convert to associative for matching convenience
    $srcIdx = null; $dstIdx = null;
    $matchLitersStr = number_format(abs($absLiters), 3, '.', '');
    foreach ($rows as $i => $data) {
        $assoc = @array_combine($headers, array_pad($data, count($headers), '')) ?: [];
        $tenTau = trim((string)($assoc['ten_phuong_tien'] ?? ''));
        $loai = (string)($assoc['loai'] ?? '');
        $ngay = trim((string)($assoc['ngay'] ?? ''));
        $so = (float)($assoc['so_luong_lit'] ?? 0);
        $lyDo = (string)($assoc['ly_do'] ?? '');

        if ($ngay !== $dateIso || $loai !== 'tinh_chinh') { continue; }

        // Source row: negative amount and reason contains "chuyển sang <dstShip>"
        if ($tenTau === $srcShip && $so < 0 && number_format(abs($so), 3, '.', '') === $matchLitersStr && strpos($lyDo, 'chuyển sang ' . $dstShip) !== false) {
            $srcIdx = $i;
        }
        // Destination row: positive amount and reason contains "nhận từ <srcShip>"
        if ($tenTau === $dstShip && $so > 0 && number_format(abs($so), 3, '.', '') === $matchLitersStr && strpos($lyDo, 'nhận từ ' . $srcShip) !== false) {
            $dstIdx = $i;
        }
        if ($srcIdx !== null && $dstIdx !== null) { break; }
    }

    return ['indexes' => ['src' => $srcIdx, 'dst' => $dstIdx], 'headers' => $headers, 'rows' => $rows];
}

// Find transfer rows by transfer_pair_id if column exists
function td2_find_transfer_rows_by_pair_id(string $pairId): array {
    $file = __DIR__ . '/../data/dau_ton.csv';
    if (!file_exists($file)) { return ['indexes'=>[], 'headers'=>[], 'rows'=>[]]; }
    $fh = fopen($file, 'r');
    if (!$fh) { return ['indexes'=>[], 'headers'=>[], 'rows'=>[]]; }
    $headers = fgetcsv($fh) ?: [];
    $rows = [];
    while (($row = fgetcsv($fh)) !== false) { $rows[] = $row; }
    fclose($fh);

    $pairIdx = array_search('transfer_pair_id', $headers, true);
    if ($pairIdx === false) {
        return ['indexes'=>[], 'headers'=>$headers, 'rows'=>$rows];
    }

    $srcIdx = null; $dstIdx = null;
    foreach ($rows as $i => $data) {
        $assoc = @array_combine($headers, array_pad($data, count($headers), '')) ?: [];
        if (($assoc['transfer_pair_id'] ?? '') !== $pairId) { continue; }
        $loai = (string)($assoc['loai'] ?? '');
        $so = (float)($assoc['so_luong_lit'] ?? 0);
        if ($loai !== 'tinh_chinh') { continue; }
        if ($so < 0 && $srcIdx === null) { $srcIdx = $i; }
        if ($so > 0 && $dstIdx === null) { $dstIdx = $i; }
        if ($srcIdx !== null && $dstIdx !== null) { break; }
    }

    return ['indexes' => ['src' => $srcIdx, 'dst' => $dstIdx], 'headers' => $headers, 'rows' => $rows];
}

// Delete specific row indexes (0-based excluding header) from dau_ton.csv atomically
function td2_delete_csv_rows(string $file, array $indexesToDelete): bool {
    if (!file_exists($file)) { 
        log_error('td2_delete_csv_rows', "File not found: {$file}");
        return false; 
    }
    $indexes = [];
    foreach ($indexesToDelete as $idx) { 
        if ($idx !== null) { 
            $indexes[(int)$idx] = true; 
        } 
    }
    if (empty($indexes)) {
        log_error('td2_delete_csv_rows', "No indexes to delete");
        return false;
    }
    
    // Read all rows first
    $rh = @fopen($file, 'r');
    if (!$rh) { 
        log_error('td2_delete_csv_rows', "Cannot open file for reading: {$file}");
        return false; 
    }
    
    // Try to acquire lock (may not work on Windows, but we'll try)
    if (function_exists('flock')) {
        if (!flock($rh, LOCK_EX | LOCK_NB)) {
            // If lock fails, try without lock (Windows may not support it)
            fclose($rh);
            $rh = @fopen($file, 'r');
            if (!$rh) {
                log_error('td2_delete_csv_rows', "Cannot reopen file after lock attempt: {$file}");
                return false;
            }
        }
    }
    
    $headers = fgetcsv($rh) ?: [];
    if (empty($headers)) {
        fclose($rh);
        log_error('td2_delete_csv_rows', "No headers found in file: {$file}");
        return false;
    }
    
    $rows = [];
    $i = 0;
    while (($row = fgetcsv($rh)) !== false) { 
        if (!isset($indexes[$i])) { 
            $rows[] = $row; 
        }
        $i++; 
    }
    fclose($rh);
    
    // Write to temporary file
    $tmp = $file . '.tmp.' . uniqid();
    $wh = @fopen($tmp, 'w');
    if (!$wh) { 
        log_error('td2_delete_csv_rows', "Cannot create temp file: {$tmp}");
        return false; 
    }
    
    // Write BOM for UTF-8 if needed
    fwrite($wh, "\xEF\xBB\xBF");
    
    fputcsv($wh, $headers);
    foreach ($rows as $r) { 
        fputcsv($wh, $r); 
    }
    fclose($wh);
    
    // Replace original file
    $ok = @rename($tmp, $file);
    if (!$ok) {
        // Try copy + unlink as fallback
        if (@copy($tmp, $file)) {
            @unlink($tmp);
            $ok = true;
        } else {
            @unlink($tmp);
            log_error('td2_delete_csv_rows', "Cannot replace file: {$file}");
        }
    }
    
    return $ok;
}

// Update specific row indexes with new associative data mapped to headers
function td2_update_csv_rows(string $file, array $indexToAssocRow, array $headers): bool {
    if (!file_exists($file)) { return false; }
    // Acquire exclusive lock on the target file during the whole operation
    $lock = fopen($file, 'c+'); if (!$lock) { return false; }
    if (!flock($lock, LOCK_EX)) { fclose($lock); return false; }
    $rh = fopen($file, 'r'); if (!$rh) { flock($lock, LOCK_UN); fclose($lock); return false; }
    $origHeaders = fgetcsv($rh) ?: [];
    $rows = [];
    while (($row = fgetcsv($rh)) !== false) { $rows[] = $row; }
    fclose($rh);
    // Ensure headers consistent
    $headers = !empty($headers) ? $headers : $origHeaders;
    foreach ($indexToAssocRow as $idx => $assoc) {
        $ordered = [];
        foreach ($headers as $h) {
            $ordered[] = $assoc[$h] ?? '';
        }
        $rows[(int)$idx] = $ordered;
    }
    $tmp = $file . '.tmp';
    $wh = fopen($tmp, 'w'); if (!$wh) { flock($lock, LOCK_UN); fclose($lock); return false; }
    fputcsv($wh, $headers);
    foreach ($rows as $r) { fputcsv($wh, $r); }
    fclose($wh);
    $ok = @rename($tmp, $file);
    flock($lock, LOCK_UN);
    fclose($lock);
    return $ok;
}

// Format tên tàu kèm số đăng ký nếu có
function formatTau(string $tenTau): string {
    require_once __DIR__ . '/../models/TauPhanLoai.php';
    $model = new TauPhanLoai();
    $sdk = $model->getSoDangKy(trim($tenTau));
    return $sdk ? ($tenTau . ' (' . $sdk . ')') : $tenTau;
}

// Format nhãn cho lệnh chuyển dầu theo hướng chuyển
function td2_format_transfer_label(string $currentShip, string $otherShip, string $dir): string {
    $other = trim($otherShip);
    if ($dir === 'out') {
        return 'Chuyển dầu cho ' . $other;
    }
    return 'Nhận dầu từ ' . $other;
}

// ============================================
// DEBUG & LOGGING HELPERS
// ============================================

/**
 * Helper function: Debug log
 * Ghi log debug với level và category
 *
 * @param string $message Thông điệp
 * @param mixed $data Dữ liệu đi kèm (array, object, string...)
 * @param string $level Level: DEBUG, INFO, WARNING, ERROR
 * @param string|null $category Category: database, api, calculation, export, auth, form, session
 *
 * VÍ DỤ:
 *   debug_log('User login', ['user' => 'admin'], 'INFO', 'auth');
 *   debug_log('Calculating fuel', ['tau' => $tenTau, 'km' => $km], 'DEBUG', 'calculation');
 *   debug_log('Export failed', ['error' => $e->getMessage()], 'ERROR', 'export');
 */
function debug_log($message, $data = null, $level = 'DEBUG', $category = null) {
    // Load Logger nếu chưa load
    if (!class_exists('Logger')) {
        require_once __DIR__ . '/../models/Logger.php';
    }

    Logger::log($message, $data, $level, $category);
}

/**
 * Helper function: Bắt đầu đo thời gian thực thi
 *
 * @param string $label Nhãn timer
 *
 * VÍ DỤ:
 *   debug_start('calculate_fuel');
 *   // ... code tính toán ...
 *   debug_end('calculate_fuel'); // Tự động log: "Timer ended: calculate_fuel - Duration: 0.1234s"
 */
function debug_start($label) {
    if (!class_exists('Logger')) {
        require_once __DIR__ . '/../models/Logger.php';
    }

    Logger::startTimer($label);
}

/**
 * Helper function: Kết thúc timer và log thời gian
 *
 * @param string $label Nhãn timer
 * @return float Thời gian thực thi (seconds)
 */
function debug_end($label) {
    if (!class_exists('Logger')) {
        require_once __DIR__ . '/../models/Logger.php';
    }

    return Logger::endTimer($label);
}

/**
 * Helper function: Log memory usage
 *
 * @param string $label Nhãn (optional)
 *
 * VÍ DỤ:
 *   debug_memory('After loading CSV');
 */
function debug_memory($label = '') {
    if (!class_exists('Logger')) {
        require_once __DIR__ . '/../models/Logger.php';
    }

    Logger::logMemoryUsage($label);
}

/**
 * Helper function: Log request info
 * Ghi thông tin request (method, URI, IP, user agent)
 * Thường dùng ở đầu file API
 *
 * VÍ DỤ:
 *   debug_request(); // Tự động log: "Request received [GET /api/search_diem.php]"
 */
function debug_request() {
    if (!class_exists('Logger')) {
        require_once __DIR__ . '/../models/Logger.php';
    }

    Logger::logRequest();
}

/**
 * Helper function: Log exception
 *
 * @param Exception $exception Exception object
 * @param array $context Context bổ sung
 *
 * VÍ DỤ:
 *   try {
 *       // ... code ...
 *   } catch (Exception $e) {
 *       debug_exception($e, ['tau' => $tenTau, 'action' => 'save']);
 *       throw $e;
 *   }
 */
function debug_exception($exception, $context = []) {
    if (!class_exists('Logger')) {
        require_once __DIR__ . '/../models/Logger.php';
    }

    Logger::logException($exception, $context);
}

/**
 * Helper function: Quick debug (tương đương debug_log với level DEBUG)
 *
 * @param string $message
 * @param mixed $data
 */
function dd($message, $data = null) {
    debug_log($message, $data, 'DEBUG');
}

/**
 * Helper function: Dump and die (debug và dừng script)
 * CẢNH BÁO: Chỉ dùng khi development, không dùng trong production
 *
 * @param mixed $data Dữ liệu cần dump
 */
function ddd($data) {
    if (!defined('DEBUG_MODE') || !DEBUG_MODE) {
        return; // Không làm gì nếu không phải debug mode
    }

    echo '<pre style="background:#f4f4f4;padding:20px;margin:20px;border:1px solid #ddd;border-radius:5px;">';
    echo '<strong style="color:#c00;">DEBUG DUMP (ddd):</strong><br><br>';
    var_dump($data);
    echo '</pre>';
    exit;
}