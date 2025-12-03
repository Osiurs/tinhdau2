<?php
/**
 * Class Logger - Hệ thống logging chuyên nghiệp
 *
 * CÁCH SỬ DỤNG:
 *   Logger::debug('Message', ['data' => 'value'], 'category');
 *   Logger::info('Info message');
 *   Logger::warning('Warning message');
 *   Logger::error('Error message', ['exception' => $e]);
 *
 * HOẶC dùng helper function:
 *   debug_log('Message', $data, 'DEBUG', 'category');
 */

require_once __DIR__ . '/../config/debug.php';

class Logger {

    private static $timers = [];

    /**
     * Log DEBUG level
     */
    public static function debug($message, $data = null, $category = null) {
        self::log($message, $data, 'DEBUG', $category);
    }

    /**
     * Log INFO level
     */
    public static function info($message, $data = null, $category = null) {
        self::log($message, $data, 'INFO', $category);
    }

    /**
     * Log WARNING level
     */
    public static function warning($message, $data = null, $category = null) {
        self::log($message, $data, 'WARNING', $category);
    }

    /**
     * Log ERROR level
     */
    public static function error($message, $data = null, $category = null) {
        self::log($message, $data, 'ERROR', $category);
    }

    /**
     * Hàm log chính
     */
    public static function log($message, $data = null, $level = 'DEBUG', $category = null) {
        // Kiểm tra có nên log không
        if (!should_log($level)) {
            return;
        }

        // Kiểm tra category có được bật không
        if ($category && !is_category_enabled($category)) {
            return;
        }

        // Format log entry
        $logEntry = self::formatLogEntry($message, $data, $level, $category);

        // Ghi vào file
        if (LOG_TO_FILE) {
            self::writeToFile($logEntry);
        }

        // Ghi vào console (browser)
        if (DEBUG_MODE && LOG_TO_CONSOLE) {
            self::writeToConsole($logEntry, $level);
        }
    }

    /**
     * Format log entry thành chuỗi
     */
    private static function formatLogEntry($message, $data, $level, $category) {
        $parts = [];

        // Timestamp
        if (LOG_TIMESTAMP) {
            $parts[] = '[' . date('Y-m-d H:i:s') . ']';
        }

        // Level
        $parts[] = '[' . str_pad($level, 7) . ']';

        // Category
        if ($category) {
            $parts[] = '[' . $category . ']';
        }

        // Source (file:line)
        if (LOG_SOURCE) {
            $caller = get_caller_info();
            $parts[] = '[' . $caller . ']';
        }

        // Message
        $parts[] = $message;

        // Data
        if ($data !== null) {
            // Mask sensitive data
            $maskedData = mask_sensitive_data($data);
            $parts[] = "\n" . self::formatData($maskedData);
        }

        return implode(' ', $parts);
    }

    /**
     * Format data thành string dễ đọc
     */
    private static function formatData($data) {
        if (is_array($data) || is_object($data)) {
            return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        return (string)$data;
    }

    /**
     * Ghi vào file log
     */
    private static function writeToFile($logEntry) {
        try {
            $logFile = LOG_FILE;

            // Tạo thư mục nếu chưa có
            $logDir = dirname($logFile);
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0777, true);
            }

            // Kiểm tra và rotate log file nếu cần
            if (AUTO_ROTATE_LOG && file_exists($logFile)) {
                $fileSize = filesize($logFile);
                if ($fileSize > MAX_LOG_SIZE) {
                    self::rotateLogFile($logFile);
                }
            }

            // Ghi log (append mode)
            $logLine = $logEntry . "\n" . str_repeat('-', 80) . "\n";
            @file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);

        } catch (Exception $e) {
            // Nếu không ghi được file, silent fail
            // (không muốn crash app vì lỗi logging)
        }
    }

    /**
     * Rotate log file (xoay vòng)
     */
    private static function rotateLogFile($logFile) {
        // Xóa file backup cũ nhất
        $oldestBackup = $logFile . '.' . LOG_BACKUP_COUNT;
        if (file_exists($oldestBackup)) {
            @unlink($oldestBackup);
        }

        // Dời các file backup
        for ($i = LOG_BACKUP_COUNT - 1; $i >= 1; $i--) {
            $oldFile = $logFile . '.' . $i;
            $newFile = $logFile . '.' . ($i + 1);
            if (file_exists($oldFile)) {
                @rename($oldFile, $newFile);
            }
        }

        // Rename file hiện tại thành .1
        @rename($logFile, $logFile . '.1');
    }

    /**
     * Ghi vào console (browser)
     */
    private static function writeToConsole($logEntry, $level) {
        $safeEntry = addslashes($logEntry);
        $safeEntry = str_replace("\n", "\\n", $safeEntry);

        $consoleMethod = 'log';
        if ($level === 'ERROR') {
            $consoleMethod = 'error';
        } elseif ($level === 'WARNING') {
            $consoleMethod = 'warn';
        } elseif ($level === 'INFO') {
            $consoleMethod = 'info';
        }

        echo "<script>console.{$consoleMethod}('{$safeEntry}');</script>";
    }

    /**
     * Bắt đầu timer
     */
    public static function startTimer($label) {
        self::$timers[$label] = microtime(true);

        if (LOG_EXECUTION_TIME) {
            self::debug("Timer started: {$label}");
        }
    }

    /**
     * Kết thúc timer và log kết quả
     */
    public static function endTimer($label) {
        if (!isset(self::$timers[$label])) {
            self::warning("Timer '{$label}' was not started");
            return;
        }

        $startTime = self::$timers[$label];
        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        unset(self::$timers[$label]);

        if (LOG_EXECUTION_TIME) {
            $level = ($duration > SLOW_QUERY_THRESHOLD) ? 'WARNING' : 'DEBUG';
            $message = "Timer ended: {$label} - Duration: " . number_format($duration, 4) . "s";

            if ($duration > SLOW_QUERY_THRESHOLD) {
                $message .= " ⚠️ SLOW!";
            }

            self::log($message, null, $level);
        }

        return $duration;
    }

    /**
     * Log memory usage
     */
    public static function logMemoryUsage($label = '') {
        if (!LOG_MEMORY_USAGE) {
            return;
        }

        $memory = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);

        $data = [
            'current' => self::formatBytes($memory),
            'peak' => self::formatBytes($peak),
        ];

        $message = $label ? "Memory usage ({$label})" : "Memory usage";
        self::debug($message, $data);
    }

    /**
     * Format bytes thành human-readable
     */
    private static function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Log request info (dùng ở đầu mỗi request)
     */
    public static function logRequest() {
        if (!DEBUG_MODE) {
            return;
        }

        $data = [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'Unknown',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        ];

        self::info('Request received', $data, 'api');
    }

    /**
     * Log exception
     */
    public static function logException($exception, $context = []) {
        $data = [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'context' => $context,
        ];

        self::error('Exception caught', $data);
    }

    /**
     * Clear log file
     */
    public static function clearLog() {
        if (file_exists(LOG_FILE)) {
            @file_put_contents(LOG_FILE, '');
            self::info('Log file cleared');
        }
    }
}

?>
