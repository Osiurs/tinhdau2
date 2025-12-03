<?php
/**
 * Middleware kiểm tra đăng nhập
 * Include file này ở đầu các trang cần bảo vệ
 */

require_once __DIR__ . '/auth_helper.php';

// Kiểm tra đăng nhập
requireLogin();

