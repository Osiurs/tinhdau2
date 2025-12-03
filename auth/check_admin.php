<?php
/**
 * Middleware kiểm tra quyền admin
 * Include file này ở đầu các trang admin
 */

require_once __DIR__ . '/auth_helper.php';

// Kiểm tra quyền admin
requireAdmin();

