<?php
// Bắt đầu output buffering để tránh output không mong muốn
ob_start();

// Bật error reporting nhưng không hiển thị
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Biến lưu response
$response = ['success' => false, 'message' => 'Yêu cầu không hợp lệ.'];

try {
    // Require các file cần thiết
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../models/DauTon.php';
    require_once __DIR__ . '/../includes/helpers.php';

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $id = trim($_POST['id'] ?? '');
        $ngay = trim($_POST['ngay'] ?? '');
        $soLuong = $_POST['so_luong'] ?? '';
        $lyDo = trim($_POST['ly_do'] ?? '');

        // Log dữ liệu nhận được
        log_error('update_tinh_chinh_request', [
            'id' => $id,
            'ngay' => $ngay,
            'so_luong' => $soLuong,
            'ly_do' => $lyDo
        ]);

        if ($id === '') {
            $response['message'] = 'ID không được để trống.';
        } elseif ($ngay === '') {
            $response['message'] = 'Ngày không được để trống.';
        } elseif ($soLuong === '' || !is_numeric($soLuong)) {
            $response['message'] = 'Số lượng phải là số hợp lệ.';
        } else {
            $dauTon = new DauTon();
            $dauTon->updateTinhChinh($id, $ngay, $soLuong, $lyDo);
            $response['success'] = true;
            $response['message'] = 'Đã cập nhật lệnh tinh chỉnh thành công.';
        }
    }
} catch (Throwable $e) {
    $response['success'] = false;
    $response['message'] = 'Lỗi: ' . $e->getMessage();
    log_error('update_tinh_chinh_error', [
        'id' => $_POST['id'] ?? null,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

// Xóa tất cả output buffer và gửi JSON
ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>
