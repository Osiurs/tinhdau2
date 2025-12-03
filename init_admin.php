<?php
/**
 * Script khởi tạo tài khoản admin đầu tiên
 * Chạy file này một lần để tạo tài khoản admin mặc định
 */

require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/config/database.php';

echo "=== KHỞI TẠO TÀI KHOẢN ADMIN ===\n\n";

try {
    $userModel = new User();
    
    // Kiểm tra xem đã có admin chưa
    $existingUsers = $userModel->getAll();
    $hasAdmin = false;
    
    foreach ($existingUsers as $user) {
        if ($user['role'] === 'admin') {
            $hasAdmin = true;
            echo "⚠️  Đã tồn tại tài khoản admin: " . $user['username'] . "\n";
        }
    }
    
    if ($hasAdmin) {
        echo "\nBạn có muốn tạo thêm tài khoản admin khác không? (y/n): ";
        $answer = trim(fgets(STDIN));
        if (strtolower($answer) !== 'y') {
            echo "\nHủy bỏ.\n";
            exit(0);
        }
    }
    
    // Nhập thông tin admin
    echo "\n--- Nhập thông tin tài khoản admin mới ---\n";
    
    echo "Tên đăng nhập: ";
    $username = trim(fgets(STDIN));
    
    if (empty($username)) {
        throw new Exception("Tên đăng nhập không được để trống");
    }
    
    echo "Mật khẩu: ";
    $password = trim(fgets(STDIN));
    
    if (empty($password)) {
        throw new Exception("Mật khẩu không được để trống");
    }
    
    echo "Họ tên (có thể để trống): ";
    $fullName = trim(fgets(STDIN));
    
    // Tạo tài khoản admin
    $admin = $userModel->create($username, $password, $fullName, 'admin');
    
    echo "\n✅ Tạo tài khoản admin thành công!\n";
    echo "   - ID: " . $admin['id'] . "\n";
    echo "   - Username: " . $admin['username'] . "\n";
    echo "   - Họ tên: " . ($admin['full_name'] ?: '(chưa có)') . "\n";
    echo "   - Role: " . $admin['role'] . "\n";
    echo "\nBạn có thể đăng nhập tại: /auth/login.php\n";
    
} catch (Exception $e) {
    echo "\n❌ Lỗi: " . $e->getMessage() . "\n";
    exit(1);
}

