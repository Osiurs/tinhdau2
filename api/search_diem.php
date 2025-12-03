<?php
// API endpoint to search for location names (autocomplete)
// Hỗ trợ cả format cũ (keyword, diem_dau) và mới (q)
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../models/KhoangCach.php';

try {
    // Hỗ trợ cả 2 parameter names: 'q' (mới) và 'keyword' (cũ)
    $query = isset($_GET['q']) ? trim($_GET['q']) : (isset($_GET['keyword']) ? trim($_GET['keyword']) : '');
    $diemDau = isset($_GET['diem_dau']) ? trim($_GET['diem_dau']) : '';

    $model = new KhoangCach();

    // Nếu không có query, trả về tất cả điểm (hoặc điểm có kết nối với diem_dau)
    if (empty($query)) {
        $results = $model->getAllDiemForSearch($diemDau);

        // Format response cho cả 2 formats
        $data = [];
        foreach ($results as $item) {
            $data[] = [
                'diem' => $item['diem'],
                'khoang_cach' => $item['khoang_cach']
            ];
        }

        echo json_encode([
            'success' => true,
            'results' => array_column($data, 'diem'), // Format mới
            'data' => $data // Format cũ
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Tìm kiếm với keyword
    $results = $model->searchDiemWithDistance($query, $diemDau);

    // Format response cho cả 2 formats
    $data = [];
    foreach ($results as $item) {
        $data[] = [
            'diem' => $item['diem'],
            'khoang_cach' => $item['khoang_cach']
        ];
    }

    echo json_encode([
        'success' => true,
        'results' => array_column($data, 'diem'), // Format mới (array of strings)
        'data' => $data // Format cũ (array of objects)
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
