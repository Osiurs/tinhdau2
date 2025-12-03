<?php
/**
 * Auth Helper - Các hàm hỗ trợ authentication
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../models/User.php';

/**
 * Kiểm tra user đã đăng nhập chưa
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

/**
 * Kiểm tra user có phải admin không
 */
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Lấy thông tin user hiện tại
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'] ?? '',
        'role' => $_SESSION['role'] ?? 'user'
    ];
}

/**
 * Đăng nhập user
 */
function loginUser($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'] ?? '';
    $_SESSION['role'] = $user['role'] ?? 'user';
    $_SESSION['login_time'] = time();
}

/**
 * Đăng xuất user
 */
function logoutUser() {
    // Xóa tất cả session variables
    $_SESSION = array();
    
    // Xóa session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Hủy session
    session_destroy();
}

/**
 * Chuyển hướng đến trang login
 */
function redirectToLogin($message = '') {
    // Xác định đường dẫn tương đối đến auth/login.php
    $scriptPath = $_SERVER['SCRIPT_NAME'];

    // Nếu đang ở trong thư mục auth, dùng đường dẫn tương đối
    if (strpos($scriptPath, '/auth/') !== false) {
        $url = 'login.php';
    }
    // Nếu đang ở trong thư mục admin, quay lại thư mục gốc rồi vào auth
    elseif (strpos($scriptPath, '/admin/') !== false) {
        $url = '../auth/login.php';
    }
    // Nếu đang ở thư mục gốc
    else {
        $url = 'auth/login.php';
    }

    if (!empty($message)) {
        $url .= '?message=' . urlencode($message);
    }

    // Lưu trang hiện tại để redirect về sau khi login
    $currentPage = $_SERVER['REQUEST_URI'];
    if (!empty($currentPage) && strpos($currentPage, 'login.php') === false) {
        $url .= (strpos($url, '?') !== false ? '&' : '?') . 'redirect=' . urlencode($currentPage);
    }

    header('Location: ' . $url);
    exit;
}

/**
 * Chuyển hướng đến trang chủ
 */
function redirectToHome() {
    // Xác định đường dẫn tương đối từ thư mục hiện tại
    $scriptPath = $_SERVER['SCRIPT_NAME'];
    if (strpos($scriptPath, '/auth/') !== false) {
        // Nếu đang ở trong thư mục auth, quay lại thư mục gốc
        header('Location: ../index.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

/**
 * Kiểm tra quyền truy cập
 */
function requireLogin() {
    if (!isLoggedIn()) {
        redirectToLogin('Vui lòng đăng nhập để tiếp tục');
    }
}

/**
 * Kiểm tra quyền admin
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('HTTP/1.1 403 Forbidden');
        die('Bạn không có quyền truy cập trang này');
    }
}

