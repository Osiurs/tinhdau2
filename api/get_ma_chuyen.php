<?php
/**
 * API để lấy mã chuyến cao nhất của một tàu
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/LuuKetQua.php';
require_once __DIR__ . '/../models/HeSoTau.php';

try {
    $tenTau = trim((string)($_GET['ten_tau'] ?? ''));
    
    if ($tenTau === '') {
        throw new Exception('Thiếu thông tin tên tàu');
    }
    $hs = new HeSoTau();
    if (!$hs->isTauExists($tenTau)) { throw new Exception('Tàu không tồn tại'); }
    
    $luuKetQua = new LuuKetQua();
    $maChuyenCaoNhat = $luuKetQua->layMaChuyenCaoNhat($tenTau);
    
    echo json_encode([
        'success' => true,
        'ma_chuyen_cao_nhat' => $maChuyenCaoNhat,
        'message' => 'Lấy mã chuyến thành công'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
