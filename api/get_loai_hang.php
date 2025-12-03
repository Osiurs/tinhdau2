<?php
require_once __DIR__ . '/../models/LoaiHang.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $lh = new LoaiHang();
    $list = $lh->getAll();
    echo json_encode(['success' => true, 'data' => $list], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>


