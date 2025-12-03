<?php
/**
 * DEBUG CONFIGURATION
 * Cấu hình debug và logging cho dự án
 *
 * HƯỚNG DẪN SỬ DỤNG:
 * - Development: DEBUG_MODE = true, LOG_LEVEL = 'DEBUG'
 * - Production:  DEBUG_MODE = false, LOG_LEVEL = 'ERROR'
 */

// ============================================
// DEBUG MODE - BẬT/TẮT DEBUG
// ============================================
// true  = Bật debug (development)
// false = Tắt debug (production)
define('DEBUG_MODE', true);

// ============================================
// LOG LEVEL - MỨC ĐỘ LOG
// ============================================
// 'DEBUG'   = Ghi tất cả (chi tiết nhất)
// 'INFO'    = Ghi thông tin quan trọng
// 'WARNING' = Chỉ ghi cảnh báo
// 'ERROR'   = Chỉ ghi lỗi
define('LOG_LEVEL', 'DEBUG');

// ============================================
// LOG FILE - FILE GHI LOG
// ============================================
// Đường dẫn file log (tự động tạo nếu chưa có)
define('LOG_FILE', __DIR__ . '/../data/debug.log');

// Kích thước tối đa file log (bytes) - 5MB
define('MAX_LOG_SIZE', 5 * 1024 * 1024);

// ============================================
// LOG SETTINGS - CÀI ĐẶT LOG
// ============================================
// Có ghi timestamp không
define('LOG_TIMESTAMP', true);

// Có ghi source (file:line) không
define('LOG_SOURCE', true);

// Có ghi vào console (browser) không (chỉ khi DEBUG_MODE = true)
define('LOG_TO_CONSOLE', true);

// Có ghi vào file không
define('LOG_TO_FILE', true);

// ============================================
// LOG ROTATION - XOAY VÒNG LOG
// ============================================
// Tự động xóa log cũ khi quá MAX_LOG_SIZE
define('AUTO_ROTATE_LOG', true);

// Số file backup giữ lại (debug.log.1, debug.log.2, ...)
define('LOG_BACKUP_COUNT', 3);

// ============================================
// DEBUG CATEGORIES - PHÂN LOẠI DEBUG
// ============================================
// Có thể bật/tắt debug theo từng module
define('DEBUG_CATEGORIES', [
    'database' => true,     // Debug database/CSV operations
    'api' => true,          // Debug API calls
    'calculation' => true,  // Debug tính toán nhiên liệu
    'export' => true,       // Debug xuất Excel
    'auth' => true,         // Debug authentication (nếu có)
    'form' => true,         // Debug form submission
    'session' => true,      // Debug session
]);

// ============================================
// SENSITIVE DATA MASKING - CHE DỮ LIỆU NHẠY CẢM
// ============================================
// Các key cần che khi log (tránh log password, token, ...)
define('SENSITIVE_KEYS', [
    'password',
    'passwd',
    'pwd',
    'token',
    'api_key',
    'secret',
    'auth',
]);

// ============================================
// PERFORMANCE MONITORING - GIÁM SÁT HIỆU NĂNG
// ============================================
// Có log execution time không
define('LOG_EXECUTION_TIME', true);

// Có log memory usage không
define('LOG_MEMORY_USAGE', true);

// Ngưỡng cảnh báo chậm (seconds)
define('SLOW_QUERY_THRESHOLD', 1.0);

// ============================================
// ERROR HANDLING - XỬ LÝ LỖI
// ============================================
// Có hiển thị lỗi chi tiết không (chỉ development)
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
}

// ============================================
// HELPER FUNCTIONS - HÀM TRỢ GIÚP
// ============================================

/**
 * Kiểm tra có nên log level này không
 */
function should_log($level) {
    if (!DEBUG_MODE && $level === 'DEBUG') {
        return false;
    }

    $levels = ['DEBUG' => 0, 'INFO' => 1, 'WARNING' => 2, 'ERROR' => 3];
    $currentLevel = $levels[LOG_LEVEL] ?? 0;
    $messageLevel = $levels[$level] ?? 0;

    return $messageLevel >= $currentLevel;
}

/**
 * Kiểm tra category có được bật không
 */
function is_category_enabled($category) {
    $categories = DEBUG_CATEGORIES;
    return isset($categories[$category]) && $categories[$category];
}

/**
 * Lấy thông tin caller (file:line)
 */
function get_caller_info() {
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
    $caller = $trace[2] ?? $trace[1] ?? [];

    $file = isset($caller['file']) ? basename($caller['file']) : 'unknown';
    $line = $caller['line'] ?? '?';

    return "{$file}:{$line}";
}

/**
 * Mask sensitive data trong array
 */
function mask_sensitive_data($data) {
    if (!is_array($data)) {
        return $data;
    }

    $masked = $data;
    foreach (SENSITIVE_KEYS as $key) {
        if (isset($masked[$key])) {
            $masked[$key] = '***MASKED***';
        }
    }

    return $masked;
}

// ============================================
// QUICK REFERENCE - THAM KHẢO NHANH
// ============================================
/*

CÁC HÀM SỬ DỤNG:

1. debug_log($message, $data = null, $level = 'DEBUG', $category = null)
   - Ghi log debug với level và category

2. debug_start($label)
   - Bắt đầu đo thời gian thực thi

3. debug_end($label)
   - Kết thúc và log thời gian thực thi

VÍ DỤ:
    debug_log('User login', ['user' => 'admin'], 'INFO', 'auth');

    debug_start('calculate_fuel');
    // ... code tính toán ...
    debug_end('calculate_fuel'); // Tự động log thời gian

    debug_log('Critical error!', ['error' => $e->getMessage()], 'ERROR');

*/
?>
