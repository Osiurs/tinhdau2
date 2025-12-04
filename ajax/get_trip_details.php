<?php
/**
 * AJAX endpoint để lấy chi tiết của một chuyến cụ thể
 */
// Bảo đảm chỉ trả về JSON thuần (tránh HTML từ warning)
while (ob_get_level() > 0) { @ob_end_clean(); }
@ob_start();
@ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';
require_once '../models/LuuKetQua.php';
require_once '../includes/helpers.php';
require_once '../models/TauPhanLoai.php';

if (!isset($_GET['ten_tau']) || !isset($_GET['so_chuyen']) || empty($_GET['ten_tau']) || empty($_GET['so_chuyen'])) {
    echo json_encode(['success' => false, 'error' => 'Tên tàu và số chuyến không được để trống']);
    exit;
}

try {
    $luuKetQua = new LuuKetQua();
    $tenTau = trim($_GET['ten_tau']);
    $soChuyen = (int)$_GET['so_chuyen'];
    
    // Đọc thô toàn bộ rồi lọc (tránh mọi sai khác do chuẩn hóa tên/định dạng)
    $all = $luuKetQua->docTatCa();
    
    // Kiểm tra dữ liệu
    if (empty($all)) {
        echo json_encode([
            'success' => false, 
            'error' => 'Không có dữ liệu chuyến',
            'debug' => ['tenTau' => $tenTau, 'soChuyen' => $soChuyen]
        ]);
        exit;
    }
    $normalize = function($s){
        $s = trim((string)$s);
        if (preg_match('/^(HTL|HTV)-0(\d+)$/', $s, $m)) { return $m[1].'-'.$m[2]; }
        return $s;
    };
    $tenTauNorm = $normalize($tenTau);
    $cacDoan = [];
    $capThem = [];
    $i = 0;
    foreach ($all as $row) {
        // CSV sử dụng field 'ten_phuong_tien', không phải 'ten_tau'
        $ship = $normalize($row['ten_phuong_tien'] ?? '');
        $trip = (int)($row['so_chuyen'] ?? 0);
        if ($ship !== $tenTauNorm || $trip !== $soChuyen) continue;
        $row['___idx'] = $row['___idx'] ?? (++$i);
        if ((int)($row['cap_them'] ?? 0) === 1) {
            $capThem[] = $row;
        } else {
            $cacDoan[] = $row;
        }
    }
    // Sắp xếp giữ nguyên thứ tự nhập theo ___idx
    usort($cacDoan, function($a,$b){ return (int)($a['___idx']??0) <=> (int)($b['___idx']??0); });
    usort($capThem, function($a,$b){ return (int)($a['___idx']??0) <=> (int)($b['___idx']??0); });
    // Xác định last_segment theo ngày/cuối danh sách
    $lastSegment = null;
    if (!empty($cacDoan)) { $lastSegment = end($cacDoan); }
    
    $tauModel = new TauPhanLoai();
    $soDangKy = $tauModel->getSoDangKy($tenTau);
    $resp = [
        'success' => true,
        'segments' => $cacDoan,
        'cap_them' => $capThem,
        'last_segment' => $lastSegment,
        'has_data' => !empty($cacDoan) || !empty($capThem),
        'so_dang_ky' => $soDangKy,
        'debug' => [
            'tenTau' => $tenTau,
            'soChuyen' => $soChuyen,
            'segments_count' => count($cacDoan),
            'cap_them_count' => count($capThem)
        ]
    ];
    $json = json_encode($resp, JSON_UNESCAPED_UNICODE);
    while (ob_get_level() > 0) { @ob_end_clean(); }
    echo $json;
    exit;
    
} catch (Exception $e) {
    while (ob_get_level() > 0) { @ob_end_clean(); }
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'debug' => [
            'tenTau' => $_GET['ten_tau'] ?? 'N/A',
            'soChuyen' => $_GET['so_chuyen'] ?? 'N/A'
        ]
    ]);
}
?>
