<?php
/**
 * API endpoint để preview tính toán nhiên liệu khi sửa điểm (không lưu vào database)
 */

header('Content-Type: application/json; charset=utf-8');

// Bắt đầu output buffering để tránh lỗi khi có warning/notice
while (ob_get_level() > 0) { @ob_end_clean(); }
@ob_start();
@ini_set('display_errors', '0');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/TinhToanNhienLieu.php';

try {
    $tenTau = trim($_GET['ten_tau'] ?? '');
    $diemDi = trim($_GET['diem_di'] ?? '');
    $diemDen = trim($_GET['diem_den'] ?? '');
    $khoiLuong = isset($_GET['khoi_luong']) ? floatval($_GET['khoi_luong']) : 0;

    // Validate input
    if (empty($tenTau)) {
        throw new Exception('Thiếu tên tàu');
    }
    if (empty($diemDi)) {
        throw new Exception('Thiếu điểm đi');
    }
    if (empty($diemDen)) {
        throw new Exception('Thiếu điểm đến');
    }

    // Tách tên điểm gốc (loại bỏ ghi chú trong ngoặc)
    $diemDiGoc = preg_replace('/\s*（[^）]*）\s*$/', '', $diemDi);
    $diemDiGoc = preg_replace('/\s*\([^)]*\)\s*$/', '', $diemDiGoc);
    $diemDenGoc = preg_replace('/\s*（[^）]*）\s*$/', '', $diemDen);
    $diemDenGoc = preg_replace('/\s*\([^)]*\)\s*$/', '', $diemDenGoc);

    $tinhToan = new TinhToanNhienLieu();
    $ketQua = null;
    $lastError = null;

    // Thử tính toán với các biến thể tên điểm
    // 1. Thử với tên đầy đủ (có phần trong ngoặc) trước
    try {
        $ketQua = $tinhToan->tinhNhienLieu($tenTau, $diemDi, $diemDen, $khoiLuong);
    } catch (Exception $e1) {
        $lastError = $e1;
        // 2. Nếu không được, thử với tên đã loại bỏ ngoặc
        try {
            $ketQua = $tinhToan->tinhNhienLieu($tenTau, $diemDiGoc, $diemDenGoc, $khoiLuong);
        } catch (Exception $e2) {
            $lastError = $e2;
            // 3. Thử với điểm đi đầy đủ và điểm đến đã loại bỏ ngoặc
            try {
                $ketQua = $tinhToan->tinhNhienLieu($tenTau, $diemDi, $diemDenGoc, $khoiLuong);
            } catch (Exception $e3) {
                // 4. Thử với điểm đi đã loại bỏ ngoặc và điểm đến đầy đủ
                try {
                    $ketQua = $tinhToan->tinhNhienLieu($tenTau, $diemDiGoc, $diemDen, $khoiLuong);
                } catch (Exception $e4) {
                    $lastError = $e4;
                }
            }
        }
    }

    if ($ketQua === null) {
        throw new Exception('Không tìm thấy tuyến đường giữa "' . $diemDi . '" và "' . $diemDen . '". Vui lòng kiểm tra lại tên điểm hoặc thêm tuyến đường trong hệ thống.');
    }

    // Trả về kết quả preview
    while (ob_get_level() > 0) { @ob_end_clean(); }
    echo json_encode([
        'success' => true,
        'data' => [
            'khoang_cach_km' => $ketQua['thong_tin']['khoang_cach_km'] ?? 0,
            'nhien_lieu_lit' => $ketQua['nhien_lieu_lit'] ?? 0,
            'he_so_co_hang' => $ketQua['thong_tin']['he_so_co_hang'] ?? 0,
            'he_so_khong_hang' => $ketQua['thong_tin']['he_so_ko_hang'] ?? 0,
            'nhom_cu_ly' => $ketQua['thong_tin']['nhom_cu_ly_label'] ?? '',
            'sch' => $ketQua['chi_tiet']['sch'] ?? 0,
            'skh' => $ketQua['chi_tiet']['skh'] ?? 0,
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    while (ob_get_level() > 0) { @ob_end_clean(); }
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
