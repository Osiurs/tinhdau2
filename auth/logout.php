<?php
/**
 * Trang đăng xuất
 */

require_once __DIR__ . '/../auth/auth_helper.php';

// Đăng xuất
logoutUser();

// Chuyển về trang login (dùng relative path)
header('Location: login.php?message=' . urlencode('Đã đăng xuất thành công'));
exit;

