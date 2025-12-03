<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/DauTon.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Yêu cầu không hợp lệ.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $cayXang = $_POST['cay_xang'] ?? ''; // Cho phép giá trị rỗng

    if ($id) {
        try {
            $dauTon = new DauTon();
            $dauTon->updateCayXang($id, $cayXang);
            $response['success'] = true;
            $response['message'] = 'Đã cập nhật cây xăng thành công.';
        } catch (Exception $e) {
            $response['message'] = 'Lỗi: ' . $e->getMessage();
        }
    } else {
        $response['message'] = 'Không có ID được cung cấp.';
    }
}

echo json_encode($response);
?>
