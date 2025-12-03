<?php
/**
 * API endpoint để tìm kiếm điểm theo từ khóa
 * Trả về kết quả dưới dạng JSON với thông tin khoảng cách
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/helpers.php';
require_once '../config/database.php';
require_once '../models/KhoangCach.php';

try {
    // Kiểm tra method
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Method không được hỗ trợ');
    }
    
    // Lấy từ khóa tìm kiếm và điểm đầu (nếu có)
    $keyword = $_GET['keyword'] ?? '';
    // Chuẩn hóa Unicode về NFC để so khớp ổn định với tiếng Việt
    if (function_exists('normalizer_normalize')) {
        $keyword = normalizer_normalize($keyword, Normalizer::FORM_C);
    }
    $diemDau = $_GET['diem_dau'] ?? ''; // Điểm đầu đã chọn
    
    // Khởi tạo đối tượng khoảng cách
    $khoangCach = new KhoangCach();
    
    // Reload dữ liệu để đảm bảo có dữ liệu mới nhất
    $khoangCach->reloadData();
    
    // Nếu không có từ khóa, trả về tất cả điểm (nhưng vẫn lọc theo điểm đầu nếu có)
    if (empty($keyword)) {
        $ketQua = $khoangCach->getAllDiemForSearch($diemDau);
    } else {
        // Thực hiện tìm kiếm
        $ketQua = $khoangCach->searchDiemWithDistance($keyword, $diemDau);
    }
    
    // Trả về kết quả
    echo json_encode([
        'success' => true,
        'data' => $ketQua,
        'count' => count($ketQua),
        'keyword' => $keyword,
        'diem_dau' => $diemDau,
        'debug' => [
            'diem_dau_received' => $diemDau,
            'diem_dau_length' => strlen($diemDau),
            'has_diem_dau' => !empty($diemDau)
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Trả về lỗi
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
