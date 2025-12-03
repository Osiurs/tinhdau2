<?php
// Bắt đầu output buffering
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/DauTon.php';

$response = ['success' => false, 'message' => 'Yêu cầu không hợp lệ.'];

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $id = $_POST['id'] ?? null;
        $id = is_string($id) ? trim($id) : '';

        log_error('delete_dau_ton_request', [
            'id_received' => $id,
            'id_length' => strlen($id),
            'id_raw' => $_POST['id'] ?? null
        ]);

        if ($id !== '') {
            $dauTon = new DauTon();

            log_error('delete_dau_ton_attempt', [
                'requested_id' => $id
            ]);

            // Gọi deleteEntry - nếu thành công thì OK, nếu lỗi sẽ throw exception
            $dauTon->deleteEntry($id);

            // Nếu đến đây nghĩa là xóa thành công
            $response['success'] = true;
            $response['message'] = 'Đã xóa thành công.';
        } else {
            $response['message'] = 'Không có ID được cung cấp.';
            log_error('delete_dau_ton_no_id', ['post_data' => $_POST]);
        }
    }
} catch (Throwable $e) {
    $response['success'] = false;
    $response['message'] = 'Lỗi: ' . $e->getMessage();
    log_error('delete_dau_ton_error', [
        'id' => $_POST['id'] ?? null,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>
