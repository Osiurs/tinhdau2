<?php
require_once __DIR__ . '/../models/LoaiHang.php';
require_once __DIR__ . '/../includes/helpers.php';
header('Content-Type: application/json; charset=utf-8');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

try {
    $name = trim((string)($_POST['ten_loai_hang'] ?? ''));
    if ($name === '') { throw new Exception('Tên loại hàng không được để trống'); }
    $lh = new LoaiHang();
    $rec = $lh->add($name, '');
    echo json_encode(['success' => true, 'data' => $rec], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>


