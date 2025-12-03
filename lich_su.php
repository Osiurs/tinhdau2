<?php
if (function_exists('ob_start')) { ob_start(); }
// DEBUG: bật hiển thị lỗi để xác định nguyên nhân HTTP 500 khi xuất
if (!headers_sent()) {
    @ini_set('display_errors', '1');
    @ini_set('display_startup_errors', '1');
}
@error_reporting(E_ALL);
// In case of fatal errors before output, dump them instead of blank 500
if (!defined('TD_FATAL_WATCH')) {
    define('TD_FATAL_WATCH', 1);
    register_shutdown_function(function(){
        $e = error_get_last();
        if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            if (!headers_sent()) header('Content-Type: text/plain; charset=UTF-8');
            echo "FATAL ERROR:\n";
            echo ($e['message'] ?? '') . "\n";
            echo ($e['file'] ?? '') . ':' . ($e['line'] ?? '') . "\n";
        }
    });
}
require_once __DIR__ . '/auth/check_auth.php';
require_once 'includes/helpers.php';
require_once 'config/database.php';
require_once 'models/LuuKetQua.php';
require_once 'models/TauPhanLoai.php';
require_once 'models/DauTon.php';
require_once 'includes/excel_xml_helper.php'; // Helper xuất Excel với header đẹp
require_once 'includes/add_header_to_sheet.php'; // Helper thêm header template vào sheet

// Đọc file CSV
$store = new LuuKetQua();
$rows = $store->docTatCa();

// Load ship type data
$phanLoaiModel = new TauPhanLoai();
$plMap = $phanLoaiModel->getAll();

// Đọc và merge dữ liệu lệnh chuyển dầu từ dau_ton.csv
$dauTonModel = new DauTon();
$transferRows = [];

// Lấy tất cả giao dịch dầu tồn
$allDauTonTransactions = [];
$heSoTau = new HeSoTau();
$danhSachTau = $heSoTau->getDanhSachTau();

foreach ($danhSachTau as $tenTau) {
    $giaoDich = $dauTonModel->getLichSuGiaoDich($tenTau);
    foreach ($giaoDich as $gd) {
        $allDauTonTransactions[] = $gd;
    }
}

// Lọc và chuyển đổi lệnh chuyển dầu
$processedTransfers = []; // Để tránh trùng lặp
// Load persisted transfer idx overrides
$__transferIdxOverrides = td2_read_transfer_overrides();
foreach ($allDauTonTransactions as $gd) {
    $loai = (string)($gd['loai'] ?? '');
    $lyDo = (string)($gd['ly_do'] ?? '');
    
    // Chỉ xử lý lệnh chuyển đi để tránh trùng lặp
    if ($loai === 'tinh_chinh' && strpos($lyDo, '→ chuyển sang') !== false) {
        $ngay = (string)($gd['ngay'] ?? '');
        $soLuong = (float)($gd['so_luong_lit'] ?? 0);
        $tenTau = (string)($gd['ten_phuong_tien'] ?? '');
        
        // Extract tàu đích
        if (preg_match('/chuyển sang\s+([^\s]+)/u', $lyDo, $matches)) {
            $tauDich = $matches[1];
            $tauNguon = $tenTau;
            
            // Tạo key duy nhất cho giao dịch (gồm ngày|nguon|dich|liters)
            $transferKey = td2_make_transfer_key($ngay, $tauNguon, $tauDich, abs($soLuong));
            
            if (!isset($processedTransfers[$transferKey])) {
                $processedTransfers[$transferKey] = true;
                
                // Tìm anchor theo tàu và ngày: lấy bản ghi cùng tàu có ngày ≤ ngày chuyển gần nhất
                $anchorIdxByShip = function(array $rows, string $ship, string $date) {
                    $dateYmd = parse_date_vn($date) ?: $date;
                    $bestIdx = null;
                    $bestDate = '';
                    foreach ($rows as $r) {
                        if ((int)($r['cap_them'] ?? 0) === 2) { continue; }
                        $shipName = trim((string)($r['ten_phuong_tien'] ?? ''));
                        if (mb_strtolower($shipName) !== mb_strtolower($ship)) { continue; }
                        $d = '';
                        if (!empty($r['ngay_di'])) { $d = parse_date_vn($r['ngay_di']) ?: ''; }
                        if ($d === '' && !empty($r['ngay_den'])) { $d = parse_date_vn($r['ngay_den']) ?: ''; }
                        if ($d === '' && !empty($r['ngay_do_xong'])) { $d = parse_date_vn($r['ngay_do_xong']) ?: ''; }
                        if ($d === '') { continue; }
                        if ($d <= $dateYmd && $d >= $bestDate) {
                            $bestDate = $d;
                            $bestIdx = (float)($r['___idx'] ?? 0);
                        }
                    }
                    if ($bestIdx === null) {
                        // nếu không có chuyến trước đó, đặt nhẹ trước chuyến đầu tiên
                        $firstIdx = null;
                        foreach ($rows as $r) {
                            $shipName = trim((string)($r['ten_phuong_tien'] ?? ''));
                            if (mb_strtolower($shipName) !== mb_strtolower($ship)) { continue; }
                            $idx = (float)($r['___idx'] ?? 0);
                            if ($idx > 0 && ($firstIdx === null || $idx < $firstIdx)) { $firstIdx = $idx; }
                        }
                        if ($firstIdx !== null) return $firstIdx - 0.1;
                        return 0.1;
                    }
                    return $bestIdx + 0.1;
                };

                // Tính idx cho nguồn và đích, có nhớ qua overrides để ổn định giữa các lần tải
                $srcIdx = $dstIdx = null;
                if (isset($__transferIdxOverrides[$transferKey])) {
                    $srcIdx = (float)($__transferIdxOverrides[$transferKey]['source_idx'] ?? 0);
                    $dstIdx = (float)($__transferIdxOverrides[$transferKey]['dest_idx'] ?? 0);
                }
                if (!$srcIdx) { $srcIdx = $anchorIdxByShip($rows, $tauNguon, $ngay); }
                if (!$dstIdx) { $dstIdx = $anchorIdxByShip($rows, $tauDich, $ngay) + 0.1; }
                $__transferIdxOverrides[$transferKey] = ['source_idx' => $srcIdx, 'dest_idx' => $dstIdx];
                
                // Tạo bản ghi cho tàu nguồn (chuyển đi)
                $transferRows[] = [
                    '___idx' => $srcIdx,
                    'ten_phuong_tien' => $tauNguon,
                    'so_chuyen' => '', // Lệnh chuyển dầu không có số chuyến
                    'cap_them' => 2, // Đánh dấu là lệnh chuyển dầu
                    'ngay_do_xong' => $ngay, // Sử dụng ngày chuyển dầu
                    'ngay_di' => $ngay,
                    'ngay_den' => '',
                    'diem_di' => '',
                    'diem_den' => '',
                    'loai_hang' => '',
                    'khoi_luong_van_chuyen_t' => 0,
                    'cu_ly_co_hang_km' => 0,
                    'cu_ly_khong_hang_km' => 0,
                    'he_so_co_hang' => 0,
                    'he_so_khong_hang' => 0,
                    'dau_tinh_toan_lit' => 0,
                    'so_luong_cap_them_lit' => 0,
                    'ly_do_cap_them' => '',
                    'cay_xang_cap_them' => '',
                    'tuyen_duong' => '',
                    'doi_lenh' => '',
                    'ghi_chu' => '',
                    'thang_bao_cao' => date('Y-m', strtotime($ngay)), // Tự động tính tháng từ ngày
                    'created_at' => $gd['created_at'] ?? date('Y-m-d H:i:s'),
                    // Thông tin bổ sung cho chuyển dầu
                    'ly_do_chuyen_dau' => $lyDo,
                    'so_luong_chuyen_dau' => -abs($soLuong), // Âm cho chuyển đi
                    'tau_nguon' => $tauNguon,
                    'tau_dich' => $tauDich,
                    'is_chuyen_out' => true,
                    'is_chuyen_in' => false
                ];
                
                // Tạo bản ghi cho tàu đích (nhận vào)  
                $transferRows[] = [
                    '___idx' => $dstIdx,
                    'ten_phuong_tien' => $tauDich,
                    'so_chuyen' => '', // Lệnh chuyển dầu không có số chuyến
                    'cap_them' => 2, // Đánh dấu là lệnh chuyển dầu
                    'ngay_do_xong' => $ngay, // Sử dụng ngày chuyển dầu
                    'ngay_di' => $ngay,
                    'ngay_den' => '',
                    'diem_di' => '',
                    'diem_den' => '',
                    'loai_hang' => '',
                    'khoi_luong_van_chuyen_t' => 0,
                    'cu_ly_co_hang_km' => 0,
                    'cu_ly_khong_hang_km' => 0,
                    'he_so_co_hang' => 0,
                    'he_so_khong_hang' => 0,
                    'dau_tinh_toan_lit' => 0,
                    'so_luong_cap_them_lit' => 0,
                    'ly_do_cap_them' => '',
                    'cay_xang_cap_them' => '',
                    'tuyen_duong' => '',
                    'doi_lenh' => '',
                    'ghi_chu' => '',
                    'thang_bao_cao' => date('Y-m', strtotime($ngay)), // Tự động tính tháng từ ngày
                    'created_at' => $gd['created_at'] ?? date('Y-m-d H:i:s'),
                    // Thông tin bổ sung cho chuyển dầu
                    'ly_do_chuyen_dau' => $lyDo,
                    'so_luong_chuyen_dau' => abs($soLuong), // Dương cho nhận vào
                    'tau_nguon' => $tauNguon,
                    'tau_dich' => $tauDich,
                    'is_chuyen_out' => false,
                    'is_chuyen_in' => true
                ];
            }
        }
    }
}

// Merge lệnh chuyển dầu vào $rows
$rows = array_merge($rows, $transferRows);
// Persist overrides for stability
td2_write_transfer_overrides($__transferIdxOverrides);


// Sắp xếp dữ liệu
usort($rows, function($a, $b) {
    // 1. Tên tàu
    $ta = mb_strtolower(trim($a['ten_phuong_tien'] ?? ''));
    $tb = mb_strtolower(trim($b['ten_phuong_tien'] ?? ''));
    if ($ta !== $tb) return $ta <=> $tb;

    // 2. Số chuyến (chỉ cho chuyến thường/cấp thêm)
    $capThemA = (int)($a['cap_them'] ?? 0);
    $capThemB = (int)($b['cap_them'] ?? 0);
    
    if ($capThemA !== 2 && $capThemB !== 2) {
        $tripA = (int)($a['so_chuyen'] ?? 0);
        $tripB = (int)($b['so_chuyen'] ?? 0);
        if ($tripA !== $tripB) return $tripA <=> $tripB;
        
        // 3. Cùng chuyến: ưu tiên chuyến thường (0) trước cấp thêm (1)
        if ($tripA === $tripB && $capThemA !== $capThemB) {
            return $capThemA <=> $capThemB; // 0 (chuyến thường) trước 1 (cấp thêm)
        }
    }

    // 4. ___idx (thứ tự trong CSV) - đây là thứ tự đã nhập vào hệ thống
    return ((float)($a['___idx'] ?? 0)) <=> ((float)($b['___idx'] ?? 0));
});

// Helper function để redirect giữ nguyên bộ lọc
function redirectWithFilters($baseUrl = 'lich_su.php') {
    $queryParams = $_GET;
    $redirectUrl = $baseUrl;
    if (!empty($queryParams)) {
        $redirectUrl .= '?' . http_build_query($queryParams);
    }
    header('Location: ' . $redirectUrl);
    exit;
}

// CRUD actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    $idx = isset($_POST['idx']) ? (int)$_POST['idx'] : 0;
    
    // Debug log (can be removed after testing)
    // file_put_contents('debug_log.txt', "DEBUG POST: act=$act, idx=$idx, POST data: " . print_r($_POST, true) . "\n", FILE_APPEND);
    
    if ($idx > 0) {
        if ($act === 'delete') {
            $result = $store->xoa($idx);
            redirectWithFilters();
        } elseif ($act === 'update_klvc') {
            $klvcStr = $_POST['khoi_luong_van_chuyen_t'] ?? '';
            $newKlvc = ($klvcStr === '' ? null : (float)$klvcStr);
            $ngayDiStr = trim($_POST['ngay_di'] ?? '');
            $ngayDenStr = trim($_POST['ngay_den'] ?? '');
            $ngayDxStr = trim($_POST['ngay_do_xong'] ?? '');
            $ngayDi = parse_date_vn($ngayDiStr);
            $ngayDen = parse_date_vn($ngayDenStr);
            $ngayDx = parse_date_vn($ngayDxStr);
            
                // Lấy bản ghi hiện tại theo idx
                $all = $store->docTatCa();
                $target = null;
                foreach ($all as $r0) {
                    $currentIdx = (int)($r0['___idx'] ?? 0);
                    if ($currentIdx === $idx) { 
                        $target = $r0; 
                        break; 
                    }
                }

            if ($target) {
                $isCapThem = (int)($target['cap_them'] ?? 0) === 1;
                
                if ($isCapThem) {
                    // Cập nhật cấp thêm
                    $lyDoCapThem = trim($_POST['ly_do_cap_them'] ?? '');
                    $soLuongCapThem = floatval($_POST['so_luong_cap_them'] ?? 0);
                    
                    $target['ly_do_cap_them'] = $lyDoCapThem;
                    $target['so_luong_cap_them_lit'] = $soLuongCapThem;
                    $target['cay_xang_cap_them'] = ''; // Không cần cây xăng vì dầu được múc từ trong tàu
                    
                    // Cập nhật ngày
                    if ($ngayDi !== false) $target['ngay_di'] = $ngayDi; else $target['ngay_di'] = '';
                    
                    $store->capNhat($idx, $target);
                } else {
                    // Cập nhật chuyến thường
                    if ($newKlvc !== null && $newKlvc >= 0) {
                    $schOld = (float)($target['cu_ly_co_hang_km'] ?? 0);
                    $skhOld = (float)($target['cu_ly_khong_hang_km'] ?? 0);
                    $totalKm = $schOld + $skhOld;
                    $kkh = (float)($target['he_so_khong_hang'] ?? 0);
                    $kch = (float)($target['he_so_co_hang'] ?? 0);

                    // Nếu D > 0: tính có hàng (Sch = tổng km, Skh = 0). Nếu D = 0: không hàng (Sch = 0, Skh = tổng km)
                    $sch = ($newKlvc > 0) ? $totalKm : 0.0;
                    $skh = ($newKlvc > 0) ? 0.0 : $totalKm;

                    $kllc = $sch * $newKlvc; // Khối lượng luân chuyển = Sch × D
                    $Q = (($sch + $skh) * $kkh) + ($sch * $newKlvc * $kch);

                    // Cập nhật dữ liệu
                    $target['khoi_luong_van_chuyen_t'] = $newKlvc;
                    $target['khoi_luong_luan_chuyen'] = $kllc;
                    $target['dau_tinh_toan_lit'] = round($Q, 2);
                    $target['cu_ly_co_hang_km'] = $sch;
                    $target['cu_ly_khong_hang_km'] = $skh;
                    // Cập nhật loại hàng nếu có
                    $loaiHangNew = trim($_POST['loai_hang'] ?? '');
                    if ($loaiHangNew !== '') {
                        $target['loai_hang'] = $loaiHangNew;
                    }
                        
                        // Cập nhật ngày
                    if ($ngayDi !== false) $target['ngay_di'] = $ngayDi; else $target['ngay_di'] = '';
                    if ($ngayDen !== false) $target['ngay_den'] = $ngayDen; else $target['ngay_den'] = '';
                    if ($ngayDx !== false) $target['ngay_do_xong'] = $ngayDx; else $target['ngay_do_xong'] = '';

                    $store->capNhat($idx, $target);
                    }
                }
            }
            redirectWithFilters();
        }
    }
}

// Nhận filter
$q = [
    'ten_phuong_tien' => $_GET['ten_phuong_tien'] ?? '',
    'so_chuyen' => $_GET['so_chuyen'] ?? '',
    'diem_di' => $_GET['diem_di'] ?? '',
    'diem_den' => $_GET['diem_den'] ?? '',
    'tu_ngay' => $_GET['tu_ngay'] ?? '',
    'den_ngay' => $_GET['den_ngay'] ?? '',
    'loai_hang' => $_GET['loai_hang'] ?? '',
    'thang' => $_GET['thang'] ?? '',
    // Loại bản ghi: '', 'cap_them', 'chuyen'
    'loai' => $_GET['loai'] ?? ''
];

// Bảo vệ biến ngày dùng ở các đoạn phía dưới để tránh Undefined variable
$q_from = parse_date_vn($q['tu_ngay']) ?: '';
$q_to   = parse_date_vn($q['den_ngay']) ?: '';

// Xác định chế độ xuất chi tiết (IN TINH DAU) trước khi filter
$extraShipsRaw = isset($_GET['extra_ships']) && is_array($_GET['extra_ships']) ? array_filter(array_map('trim', $_GET['extra_ships'])) : [];
$hasExtraShips = count($extraShipsRaw) > 0;
$isDetailedExport = $hasExtraShips; // true nếu có chọn tàu để xuất chi tiết

// Chuẩn hóa tham số tháng về định dạng yyyy-mm để dùng thống nhất
$filterYm = '';
if (!empty($q['thang'])) {
	$thangRaw = trim((string)$q['thang']);
	if (preg_match('/^(\d{4})-(\d{2})$/', $thangRaw, $m)) {
		$filterYm = sprintf('%04d-%02d', (int)$m[1], (int)$m[2]);
	} else {
		$parts = explode('/', $thangRaw);
		if (count($parts) === 2) {
			$filterYm = sprintf('%04d-%02d', (int)$parts[1], (int)$parts[0]);
		}
	}
}
$q['filterYm'] = $filterYm;

// LOGIC ĐẶC BIỆT: Khi xuất IN TINH DAU (chi tiết), BỎ QUA filter theo tháng
// Lấy tất cả dữ liệu từ quá khứ đến ngày hiện tại
if ($isDetailedExport) {
    $q['filterYm'] = ''; // Bỏ filter tháng
    // Tự động set "đến ngày" = ngày hiện tại nếu chưa có
    if (empty($q['den_ngay'])) {
        $q['den_ngay'] = date('d/m/Y'); // Ngày hiện tại
    }
}

// Lọc
$getActualDate = function(array $row): string {
    foreach (['ngay_do_xong','ngay_den','ngay_di'] as $field) {
        $val = trim((string)($row[$field] ?? ''));
        if ($val === '') continue;
        $iso = parse_date_vn($val);
        if ($iso) { return $iso; }
    }
    $createdAt = substr((string)($row['created_at'] ?? ''), 0, 10);
    if ($createdAt !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $createdAt)) {
        return $createdAt;
    }
    return '';
};

$filtered = array_filter($rows, function ($r) use ($q, $rows, $getActualDate) {
    $ok = true;
    $q_ship = trim((string)($q['ten_phuong_tien'] ?? ''));
    $q_trip = trim((string)($q['so_chuyen'] ?? ''));
    $q_from = parse_date_vn($q['tu_ngay']);
    $q_to = parse_date_vn($q['den_ngay']);

    // Chuẩn hóa tên tàu: bỏ dấu ngoặc kép hai đầu nếu có
    if ($q_ship !== '') {
        $targetShip = trim((string)($r['ten_phuong_tien'] ?? ''));
        $targetShip = trim($targetShip, '"');
        $ok = $ok && (stripos($targetShip, $q_ship) !== false);
    }

    // Số chuyến: so sánh chính xác (không match một phần)
    if ($q_trip !== '') {
        $ok = $ok && (trim((string)($r['so_chuyen'] ?? '')) === $q_trip);
    }
    
    if (($q['diem_di'] ?? '') !== '') {
        $ok = $ok && stripos((string)($r['diem_di'] ?? ''), (string)$q['diem_di']) !== false;
    }
    if (($q['diem_den'] ?? '') !== '') {
        $ok = $ok && stripos((string)($r['diem_den'] ?? ''), (string)$q['diem_den']) !== false;
    }
    if (($q['loai_hang'] ?? '') !== '') {
        $ok = $ok && stripos((string)($r['loai_hang'] ?? ''), (string)$q['loai_hang']) !== false;
    }

    if (($q['loai'] ?? '') === 'cap_them') $ok = $ok && (int)($r['cap_them'] ?? 0) === 1;
    if (($q['loai'] ?? '') === 'chuyen')   $ok = $ok && (int)($r['cap_them'] ?? 0) === 0;
    if (($q['loai'] ?? '') === 'chuyen_dau') $ok = $ok && (int)($r['cap_them'] ?? 0) === 2;

    // Chuẩn hóa tháng báo cáo & ngày tham chiếu
    $manualMonth = '';
    $tbcRaw = trim((string)($r['thang_bao_cao'] ?? ''));
    if ($tbcRaw !== '' && preg_match('/^(\d{4})-(\d{2})$/', $tbcRaw)) {
        $manualMonth = $tbcRaw;
    }
    $actualDate = $getActualDate($r);

    // Lọc ngày: chuyến dùng 'ngay_do_xong', cấp thêm dùng 'ngay_di' (ngày cấp), chuyển dầu dùng ngày chuyển
    $isCapThem = ((int)($r['cap_them'] ?? 0) === 1);
    $isChuyenDau = ((int)($r['cap_them'] ?? 0) === 2);
    $dateField = $isCapThem ? ((string)($r['ngay_di'] ?? '')) : ((string)($r['ngay_do_xong'] ?? ''));
    
    // Ưu tiên lọc theo THÁNG = ngày dỡ xong; nếu trống dùng thang_bao_cao
    if (!empty($q['filterYm'])) {
        $matchesMonth = false;
        $filterMonth = $q['filterYm'];
        $monthCandidate = $manualMonth !== '' ? $manualMonth : ($actualDate !== '' ? substr($actualDate, 0, 7) : '');
        if ($monthCandidate !== '' && $monthCandidate === $filterMonth) {
            $matchesMonth = true;
        }
        $ok = $ok && $matchesMonth;
    } else if ($q_from || $q_to) {
        if ($dateField !== '') {
            // Có ngày dỡ xong: lọc bình thường
            if ($q_from) { $ok = $ok && $dateField >= $q_from; }
            if ($q_to)   { $ok = $ok && $dateField <= $q_to; }
        } else if (!$isCapThem && !$isChuyenDau) {
            // Chuyến không có ngày dỡ xong: dùng ngày suy luận hoặc tháng báo cáo
            $compareDate = '';
            if ($actualDate !== '') {
                $compareDate = $actualDate;
            } elseif ($manualMonth !== '') {
                $compareDate = $manualMonth . '-15';
            } else {
                // Tìm chuyến cùng mã có ngày dỡ xong để suy luận
                $shipName = trim((string)($r['ten_phuong_tien'] ?? ''));
                $tripNumber = trim((string)($r['so_chuyen'] ?? ''));
                if ($shipName !== '' && $tripNumber !== '') {
                    foreach ($rows as $otherRow) {
                        $otherIsCapThem = ((int)($otherRow['cap_them'] ?? 0) === 1);
                        if ($otherIsCapThem) continue; // Chỉ xét chuyến thường
                        
                        $otherShip = trim((string)($otherRow['ten_phuong_tien'] ?? ''));
                        $otherTrip = trim((string)($otherRow['so_chuyen'] ?? ''));
                        $otherDateRaw = (string)($otherRow['ngay_do_xong'] ?? '');
                        if ($otherShip === $shipName && $otherTrip === $tripNumber && $otherDateRaw !== '') {
                            $candidate = parse_date_vn($otherDateRaw);
                            if ($candidate) {
                                $compareDate = $candidate;
                                break;
                            }
                        }
                    }
                }
            }

            if ($compareDate !== '') {
                if ($q_from && $compareDate < $q_from) { $ok = false; }
                if ($q_to && $compareDate > $q_to)   { $ok = false; }
            } else {
                $ok = false;
            }
        } else if ($isCapThem) {
            // Cấp thêm không có ngày đi: loại bỏ
            $ok = false;
        } else if ($isChuyenDau) {
            // Lệnh chuyển dầu không có ngày: loại bỏ
            $ok = false;
        }
    }

    return $ok;
});

// Tập tàu từ tất cả dữ liệu để hiển thị đầy đủ trong dropdown
$shipOptionsSet = [];
foreach ($rows as $r) {
    $shipName = trim((string)($r['ten_phuong_tien'] ?? ''));
    if ($shipName === '') continue;
    $shipOptionsSet[$shipName] = true;
}
$shipOptions = array_keys($shipOptionsSet);
sort($shipOptions, SORT_NATURAL | SORT_FLAG_CASE);

// Các dropdown phụ thuộc theo tàu đã chọn trong khoảng tháng
$tripSet = [];
$diemDiSet = [];
$diemDenSet = [];
$loaiHangSet = [];
$chosenShip = trim((string)($q['ten_phuong_tien'] ?? ''));
if ($chosenShip !== '') {
    foreach ($rows as $r) {
        $shipName = trim((string)($r['ten_phuong_tien'] ?? ''));
        if ($shipName === '' || $shipName !== $chosenShip) continue;
        $isCapThem = ((int)($r['cap_them'] ?? 0) === 1);
        $dateField = $isCapThem ? ((string)($r['ngay_di'] ?? '')) : ((string)($r['ngay_do_xong'] ?? ''));
        if ($q_from && ($dateField === '' || $dateField < $q_from)) continue;
        if ($q_to && ($dateField === '' || $dateField > $q_to)) continue;
        $soChuyen = trim((string)($r['so_chuyen'] ?? ''));
        if ($soChuyen !== '') { $tripSet[$soChuyen] = true; }
        $diemDi = trim((string)($r['diem_di'] ?? ''));
        if ($diemDi !== '') { $diemDiSet[$diemDi] = true; }
        $diemDen = trim((string)($r['diem_den'] ?? ''));
        if ($diemDen !== '') { $diemDenSet[$diemDen] = true; }
        $loaiHang = trim((string)($r['loai_hang'] ?? ''));
        if ($loaiHang !== '') { $loaiHangSet[$loaiHang] = true; }
    }
}
$tripOptions = array_keys($tripSet);
sort($tripOptions, SORT_NATURAL | SORT_FLAG_CASE);
$diemDiOptions = array_keys($diemDiSet);
sort($diemDiOptions, SORT_NATURAL | SORT_FLAG_CASE);
$diemDenOptions = array_keys($diemDenSet);
sort($diemDenOptions, SORT_NATURAL | SORT_FLAG_CASE);
require_once __DIR__ . '/models/LoaiHang.php';
$__lhModel = new LoaiHang();
$__lhAll = $__lhModel->getAll();
$loaiHangOptions = array_values(array_map(function($r){ return (string)($r['ten_loai_hang'] ?? ''); }, $__lhAll));
sort($loaiHangOptions, SORT_NATURAL | SORT_FLAG_CASE);

// Xuất Excel theo bộ lọc hiện tại
if ((isset($_GET['export']) && $_GET['export'] === 'excel') && !isset($_GET['filter'])) {
    // Bảo đảm không có dữ liệu nào đã đẩy ra trước khi in XML (tránh hỏng cấu trúc Workbook)
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) { @ob_end_clean(); }
    }
    // Tránh mọi warning/notice chen vào XML
    if (function_exists('ini_set')) {
        @ini_set('display_errors', '0');
        @ini_set('display_startup_errors', '0');
    }
    @error_reporting(0);
    // Nhóm theo Phân loại tàu (cong_ty|thue_ngoai) - chỉ 2 sheet
    $exportRows = array_values($filtered);
    $plModel = new TauPhanLoai();
    $plMap = $plModel->getAll(); // ten_tau => phan_loai

    // Tạo 2 nhóm: SLCTY (công ty) và SLN (thuê ngoài)
    $groups = ['cong_ty' => [], 'thue_ngoai' => []];
    foreach ($exportRows as $r) {
        $ship = $r['ten_phuong_tien'] ?? '';
        // Chuẩn hóa tên tàu: bỏ dấu ngoặc kép nếu có
        $shipClean = trim($ship, '"');
        $pl = $plMap[$shipClean] ?? 'cong_ty';
        $groups[$pl][] = $r;
    }

    // Xác định tháng cho tên file từ bộ lọc hoặc từ dữ liệu xuất
    $currentMonth = null;
    $currentYear = null;

    // 1) Ưu tiên filter tháng (yyyy-mm hoặc mm/yyyy)
    if (!empty($q['thang'])) {
        $thangValue = trim($q['thang']);
        if (preg_match('/^(\d{4})-(\d{2})$/', $thangValue, $m)) {
            $currentYear = (int)$m[1];
            $currentMonth = (int)$m[2];
        } else {
            $parts = explode('/', $thangValue);
            if (count($parts) === 2) {
                $currentMonth = (int)$parts[0];
                $currentYear = (int)$parts[1];
            }
        }
    }

    // 2) Nếu chưa xác định được, suy ra từ dữ liệu export (đa số bản ghi)
    if ($currentMonth === null || $currentYear === null) {
        $monthCount = [];
        foreach ($exportRows as $r) {
            // Ưu tiên ngày dỡ xong; nếu trống dùng tháng_bao_cao (yyyy-mm)
            $rowYm = '';
            $ngayDoXong = (string)($r['ngay_do_xong'] ?? '');
            if ($ngayDoXong !== '') {
                $iso = parse_date_vn($ngayDoXong);
                if ($iso) { $rowYm = date('Y-m', strtotime($iso)); }
            }
            if ($rowYm === '') {
                $tbc = (string)($r['thang_bao_cao'] ?? '');
                if (preg_match('/^(\d{4})-(\d{2})$/', $tbc)) { $rowYm = $tbc; }
            }
            if ($rowYm === '') continue;
            [$y, $m] = array_map('intval', explode('-', $rowYm));
            $key = $y . '-' . $m;
            $monthCount[$key] = ($monthCount[$key] ?? 0) + 1;
        }
        if (!empty($monthCount)) {
            // Chọn tháng có tần suất cao nhất trong dữ liệu xuất
            arsort($monthCount);
            $topKey = array_key_first($monthCount);
            [$y, $m] = array_map('intval', explode('-', $topKey));
            $currentYear = $y; $currentMonth = $m;
        }
    }

    // 3) Cuối cùng, fallback về tháng hiện tại nếu vẫn chưa có
    if ($currentMonth === null || $currentYear === null) {
        $currentMonth = (int)date('n');
        $currentYear = (int)date('Y');
    }

    // NOTE: Biến $isDetailedExport đã được xác định ở phía trên (trước phần filter)
    // để có thể sử dụng trong logic filter đặc biệt cho IN TINH DAU

    // Nếu yêu cầu xuất XLSX với template (logo/header chuẩn)
    if (isset($_GET['xlsx']) && $_GET['xlsx'] == '1') {
        // Dọn sạch mọi buffer/echo trước khi gửi file nhị phân
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) { @ob_end_clean(); }
        }
        require_once __DIR__ . '/includes/excel_export_full.php';

        // Xác định tháng/năm hiển thị tiêu đề
        $cm = $currentMonth ?? (int)date('n');
        $cy = $currentYear ?? (int)date('Y');

        // Thử xuất và bắt lỗi để hiển thị ra màn hình (tránh 500 trắng)
        try {
            exportLichSuFull($groups, $cm, $cy, $isDetailedExport);
            exit;
        } catch (Exception $e) {
            if (!headers_sent()) {
                header('Content-Type: text/plain; charset=UTF-8');
            }
            echo "EXPORT ERROR:\n";
            echo $e->getMessage() . "\n\n";
            echo $e->getFile() . ':' . $e->getLine() . "\n";
            echo $e->getTraceAsString();
            exit;
        }
    }

    // Debug tạm thời - sẽ xóa sau
    if (isset($_GET['debug'])) {
        echo "DEBUG INFO:<br>";
        echo "q[thang] = " . var_export($q['thang'], true) . "<br>";
        echo "q[tu_ngay] = " . var_export($q['tu_ngay'], true) . "<br>";
        echo "currentMonth = $currentMonth<br>";
        echo "currentYear = $currentYear<br>";
	        echo "filename = " . (($isDetailedExport ? 'CT_T' : 'BCTHANG_T') . $currentMonth . '_' . $currentYear . '.xls') . "<br>";
        exit;
    }
    
	    // Header tải về (SpreadsheetML 2 sheet)
	    $filename = ($isDetailedExport ? 'CT_T' : 'BCTHANG_T') . $currentMonth . '_' . $currentYear . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<?mso-application progid=\"Excel.Sheet\"?>\n";
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
        . ' xmlns:o="urn:schemas-microsoft-com:office:office"'
        . ' xmlns:x="urn:schemas-microsoft-com:office:excel"'
        . ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"'
        . ' xmlns:html="http://www.w3.org/TR/REC-html40">';

    try {

    // Styles
    echo '<Styles>'
        // Style cho logo cell (TitleSub đã được định nghĩa bên dưới)
        . '<Style ss:ID="LogoCell">'
            . '<Font ss:Bold="1" ss:Size="10" ss:Color="#7F7F7F"/>'
            . '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
        . '</Style>'
        . '<Style ss:ID="Header">'
            . '<Font ss:Bold="1" ss:Color="#FFFFFF"/>'
            . '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
            . '<Interior ss:Color="#2C3E50" ss:Pattern="Solid"/>'
            . '<Borders>'
                . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>'
                . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>'
                . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>'
                . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>'
            . '</Borders>'
        . '</Style>'
        // Kiểu không viền, đậm, căn giữa (dùng cho các mục ngoài bảng như "Cộng:")
        . '<Style ss:ID="BoldCenter">'
            . '<Font ss:Bold="1" ss:Color="#000000"/>'
            . '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
        . '</Style>'
        // Kiểu không viền, số 2 chữ số thập phân
        . '<Style ss:ID="NBRight2">'
            . '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
            . '<NumberFormat ss:Format="0.00"/>'
        . '</Style>'
        . '<Style ss:ID="Body">'
            . '<Alignment ss:Vertical="Center"/>'
        . '</Style>'
        . '<Style ss:ID="Left">'
            . '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
            . '<Borders>'
                . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>'
                . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>'
                . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>'
                . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>'
            . '</Borders>'
        . '</Style>'
        // Style for notes column with text wrapping and auto row height
        . '<Style ss:ID="LeftWrap">'
            . '<Alignment ss:Horizontal="Left" ss:Vertical="Top" ss:WrapText="1"/>'
            . '<Borders>'
                . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>'
                . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>'
                . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>'
                . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>'
            . '</Borders>'
        . '</Style>'
        . '<Style ss:ID="Center">'
            . '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
            . '<Borders>'
                . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>'
                . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>'
                . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>'
                . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>'
            . '</Borders>'
        . '</Style>'
        . '<Style ss:ID="Right0">'
            . '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
            . '<NumberFormat ss:Format="0"/>'
            . '<Borders>'
                . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>'
                . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>'
                . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>'
                . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>'
            . '</Borders>'
        . '</Style>'
        . '<Style ss:ID="Right1">'
            . '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
            . '<NumberFormat ss:Format="0.0"/>'
            . '<Borders>'
                . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>'
                . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>'
                . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>'
                . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>'
            . '</Borders>'
        . '</Style>'
        . '<Style ss:ID="Right2">'
            . '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
            . '<NumberFormat ss:Format="0.00"/>'
            . '<Borders>'
                . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>'
                . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>'
                . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>'
                . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>'
            . '</Borders>'
        . '</Style>'
        . '<Style ss:ID="Right">'
            . '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
            . '<Borders>'
                . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>'
                . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>'
                . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>'
                . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>'
            . '</Borders>'
        . '</Style>'
        . '<Style ss:ID="Right6">'
            . '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
            . '<NumberFormat ss:Format="0.000000"/>'
            . '<Borders>'
                . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>'
                . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>'
                . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>'
                . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>'
            . '</Borders>'
        . '</Style>'
        . '<Style ss:ID="Right7">'
            . '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
            . '<NumberFormat ss:Format="0.0000000"/>'
            . '<Borders>'
                . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>'
                . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>'
                . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>'
                . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>'
            . '</Borders>'
        . '</Style>'
        . '<Style ss:ID="Date">'
            . '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
            . '<NumberFormat ss:Format="dd/mm/yyyy"/>'
            . '<Borders>'
                . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>'
                . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>'
                . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>'
                . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>'
            . '</Borders>'
        . '</Style>'
        . '<Style ss:ID="Subtotal">'
            . '<Font ss:Bold="1" ss:Color="#000000"/>'
            . '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
            . '<Interior ss:Color="#FFFF99" ss:Pattern="Solid"/>'
            . '<Borders>'
                . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>'
                . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2"/>'
                . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>'
                . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2"/>'
            . '</Borders>'
        . '</Style>'
        . '<Style ss:ID="Title">'
            . '<Font ss:Bold="1" ss:Size="16"/>'
            . '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
        . '</Style>'
        . '<Style ss:ID="TitleSub">'
            . '<Font ss:Bold="1" ss:Size="11" ss:Color="#34495E"/>'
            . '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
        . '</Style>'
        . '<Style ss:ID="SubtotalPlain">'
            . '<Font ss:Bold="1" ss:Color="#000000"/>'
            . '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
            . '<Borders>'
                . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>'
                . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2"/>'
                . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>'
                . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2"/>'
            . '</Borders>'
        . '</Style>'
        . '<Style ss:ID="Total">'
            . '<Font ss:Bold="1" ss:Color="#FFFFFF"/>'
            . '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
            . '<Interior ss:Color="#0066CC" ss:Pattern="Solid"/>'
            . '<Borders>'
                . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>'
                . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2"/>'
                . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>'
                . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2"/>'
            . '</Borders>'
        . '</Style>'
        . '</Styles>';

    // Helper formatters (dùng helpers.php)
    $fmt1 = function($v){ return fmt1($v); };
    $fmt2 = function($v){ return fmt2($v); };

    // Chỉ tạo các sheet tổng hợp khi KHÔNG phải xuất chi tiết
    $extraShipsRaw = isset($_GET['extra_ships']) && is_array($_GET['extra_ships']) ? array_filter(array_map('trim', $_GET['extra_ships'])) : [];
    $hasExtraShips = count($extraShipsRaw) > 0;
    $isDetailedExport = $hasExtraShips; // Chỉ xuất chi tiết khi có extra_ships
    
    if (!$isDetailedExport) {
        // Tạo 2 worksheet: BCTHANG-SLCTY và BCTHANG-SLN
        foreach ($groups as $phanLoai => $rowsInGroup) {
        // Luôn tạo sheet ngay cả khi không có dữ liệu
        
        // Xây map ngày sớm nhất của từng mã chuyến theo tàu (để đặt vị trí Cấp thêm đúng theo chuyến)
        $tripEarliestByShip = [];
        $tripLatestByShip = [];
        foreach ($rowsInGroup as $rr) {
            $isCapThemR = ((int)($rr['cap_them'] ?? 0) === 1);
            if ($isCapThemR) { continue; }
            $shipR = trim((string)($rr['ten_phuong_tien'] ?? ''));
            $tripR = trim((string)($rr['so_chuyen'] ?? ''));
            if ($shipR === '' || $tripR === '') { continue; }
            $dateR = parse_date_vn($rr['ngay_di'] ?? '') ?: substr((string)($rr['created_at'] ?? ''), 0, 10);
            if ($dateR === '') { $dateR = '9999-12-31'; }
            if (!isset($tripEarliestByShip[$shipR])) { $tripEarliestByShip[$shipR] = []; }
            if (!isset($tripEarliestByShip[$shipR][$tripR]) || strcmp($dateR, $tripEarliestByShip[$shipR][$tripR]) < 0) {
                $tripEarliestByShip[$shipR][$tripR] = $dateR;
            }
            if (!isset($tripLatestByShip[$shipR])) { $tripLatestByShip[$shipR] = []; }
            if (!isset($tripLatestByShip[$shipR][$tripR]) || strcmp($dateR, $tripLatestByShip[$shipR][$tripR]) > 0) {
                $tripLatestByShip[$shipR][$tripR] = $dateR;
            }
        }

        // Sắp xếp theo thứ tự logic: tàu -> mã chuyến -> thứ tự nhập liệu
        usort($rowsInGroup, function ($a, $b) use ($tripEarliestByShip, $tripLatestByShip) {
            $ta = mb_strtolower(trim($a['ten_phuong_tien'] ?? ''));
            $tb = mb_strtolower(trim($b['ten_phuong_tien'] ?? ''));
            if ($ta !== $tb) return $ta <=> $tb;

            // Sắp xếp theo mã chuyến (số tăng dần)
            $tripA = (int)($a['so_chuyen'] ?? 0);
            $tripB = (int)($b['so_chuyen'] ?? 0);
            if ($tripA !== $tripB) return $tripA <=> $tripB;

            // Sắp xếp theo ___idx (thứ tự trong CSV)
            $idxA = (int)($a['___idx'] ?? 0);
            $idxB = (int)($b['___idx'] ?? 0);
            if ($idxA !== $idxB) return $idxA <=> $idxB;

            // Fallback: so sánh created_at
            return strcmp($a['created_at'] ?? '', $b['created_at'] ?? '');
        });

        // Tên sheet theo format yêu cầu
        $suffix = ($phanLoai === 'cong_ty') ? 'SLCTY' : 'SLN';
        $sheetName = 'BCTHANG-' . $suffix;
        
        // Worksheet
        echo '<Worksheet ss:Name="' . htmlspecialchars($sheetName, ENT_QUOTES, 'UTF-8') . '"><Table>';
        // Define 22 columns with sensible default widths (points) for readability
        $colWidths = [
            35,   // STT
            120,  // TÊN PT
            60,   // SỐ ĐK
            70,   // SỐ CHUYẾN
            240,  // TUYẾN ĐƯỜNG
            80,   // CỰ LY KH
            80,   // CỰ LY CH
            90,   // TỔNG CỰ LY
            80,   // HS KH
            90,   // HS CH
            80,   // KLVC (T)
            110,  // SL LUÂN CHUYỂN
            100,  // DẦU SD (Lit)
            90,   // NGÀY ĐI
            90,   // NGÀY ĐẾN
            100,  // NGÀY DỠ XONG
            90,   // LOẠI HÀNG
            100,  // TÊN TÀU
            200,  // GHI CHÚ (increased width for wrapping)
            70,   // <80 Km
            80,   // 80-200 Km
            80    // >200 Km
        ];
        foreach ($colWidths as $w) {
            echo '<Column ss:Width="' . (float)$w . '"/>';
        }

        // HEADER TEMPLATE (dòng 1-5)
        printSheetHeaderTemplate(22);

        // Title row: merged across all 22 columns (giờ thành dòng 6)
        $titleText = 'BẢNG TỔNG HỢP NHIÊN LIỆU VÀ KHỐI LƯỢNG VẬN CHUYỂN HÀNG HÓA THÁNG ' . $currentMonth . ' NĂM ' . $currentYear;
        echo '<Row><Cell ss:MergeAcross="21" ss:StyleID="Title"><Data ss:Type="String">' . htmlspecialchars($titleText, ENT_QUOTES, 'UTF-8') . '</Data></Cell></Row>';
        // Empty spacer row
        echo '<Row><Cell ss:MergeAcross="21" ss:StyleID="Body"><Data ss:Type="String"></Data></Cell></Row>';

        // Đã xác định thứ tự bằng usort; không cần ordinal mapping riêng

        // Header row theo format yêu cầu
        $headers = ['STT','TÊN PT','SỐ ĐK','SỐ CHUYẾN','TUYẾN ĐƯỜNG','CỰ LY KH (Km)','CỰ LY CH (Km)','TỔNG CỰ LY (Km)','HS KH','HS CH','KLVC (T)','SL LUÂN CHUYỂN (T.Km)','DẦU SD (Lit)','NGÀY ĐI','NGÀY ĐẾN','NGÀY DỠ XONG','LOẠI HÀNG','GHI CHÚ','<80 Km','80-200 Km','>200 Km'];
        echo '<Row>';
        foreach ($headers as $h) {
            echo '<Cell ss:StyleID="Header"><Data ss:Type="String">' . htmlspecialchars($h, ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
        }
        echo '</Row>';

        // Subtotal accumulators per ship
        $currentShip = null;
        $sumSch = 0.0; $sumSkh = 0.0; $sumKlvc = 0.0; $sumKllc = 0.0; $sumDau = 0.0; $tripKeySet = [];
        // Buckets accumulate FUEL (Lit)
        $bucketFuelLT80 = 0.0; $bucketFuel80200 = 0.0; $bucketFuelGT200 = 0.0;
        $stt = 1; // STT counter
        
        // Total accumulators for the entire sheet
        $totalSch = 0.0; $totalSkh = 0.0; $totalKlvc = 0.0; $totalKllc = 0.0; $totalDau = 0.0; $totalTripCount = 0;
        $totalBucketFuelLT80 = 0.0; $totalBucketFuel80200 = 0.0; $totalBucketFuelGT200 = 0.0;
        
        // Tạo mapping từ mã chuyến gốc sang số thứ tự tuần tự cho mỗi tàu
        $tripSequentialMapping = [];
        $capEntryCountByShip = [];
        $currentShipForMapping = null;
        $tripSequentialCounter = 1;
        
        foreach ($rowsInGroup as $r) {
            $ship = $r['ten_phuong_tien'] ?? '';
            $isCapThem = ((int)($r['cap_them'] ?? 0) === 1);
            $soChuyenStr = (string)($r['so_chuyen'] ?? '');
            $klvc = (float)($r['khoi_luong_van_chuyen_t'] ?? 0);
            
            // Reset counter for each ship
            if ($currentShipForMapping !== $ship) {
                $currentShipForMapping = $ship;
                $tripSequentialCounter = 1;
            }
            
            if (!$isCapThem && $soChuyenStr !== '') {
                $tripKey = $ship . '|' . $soChuyenStr;
                if (!isset($tripSequentialMapping[$tripKey])) {
                    $tripSequentialMapping[$tripKey] = $tripSequentialCounter++;
                }
            } elseif ($isCapThem && $klvc > 0) {
                // Cấp thêm có hàng: đánh số tuần tự như chuyến thường
                $tripKey = $ship . '|cap_them_' . $tripSequentialCounter;
                if (!isset($tripSequentialMapping[$tripKey])) {
                    $tripSequentialMapping[$tripKey] = $tripSequentialCounter++;
                }
            } elseif ($isCapThem) {
                // Cấp thêm không có hàng: không đánh số
                $capEntryCountByShip[$ship] = ($capEntryCountByShip[$ship] ?? 0) + 1;
            }
        }
        
        $flushSubtotalXml = function($ship) use (&$sumSch,&$sumSkh,&$sumKlvc,&$sumKllc,&$sumDau,&$tripKeySet,&$bucketFuelLT80,&$bucketFuel80200,&$bucketFuelGT200,&$totalSch,&$totalSkh,&$totalKlvc,&$totalKllc,&$totalDau,&$totalTripCount,&$totalBucketFuelLT80,&$totalBucketFuel80200,&$totalBucketFuelGT200,$fmt1,$fmt2,&$tripSequentialMapping,&$capEntryCountByShip) {
            if ($ship === null) return;
            
            // Đếm số chuyến duy nhất từ mapping cho tàu này
            $soChuyen = 0;
            foreach ($tripSequentialMapping as $tripKey => $seqNum) {
                if (strpos($tripKey, $ship . '|') === 0) {
                    $soChuyen++;
                }
            }
            $soChuyen += (int)($capEntryCountByShip[$ship] ?? 0);
            
            // Add to totals
            $totalSch += $sumSch; $totalSkh += $sumSkh; $totalKlvc += $sumKlvc; $totalKllc += $sumKllc; $totalDau += $sumDau;
            $totalTripCount += $soChuyen;
            $totalBucketFuelLT80 += $bucketFuelLT80; $totalBucketFuel80200 += $bucketFuel80200; $totalBucketFuelGT200 += $bucketFuelGT200;
            
            echo '<Row>';
            $totalKmSum = $sumSch + $sumSkh;
            $cells = [
                '', $ship . ' Cộng', '', $soChuyen, '', $fmt1($sumSkh), $fmt1($sumSch), $fmt1($totalKmSum), '', '', $fmt2($sumKlvc), $fmt2($sumKllc), (int)round($sumDau), '', '', '', '', '', (int)round($bucketFuelLT80), (int)round($bucketFuel80200), (int)round($bucketFuelGT200)
            ];
            // Types for each col (21 columns total)
            $types = ['String','String','String','Number','String','Number','Number','Number','Number','Number','Number','Number','Number','String','String','String','String','String','Number','Number','Number'];
            foreach ($cells as $i => $v) {
                $t = $types[$i] ?? 'String';
                if ($t === 'Number' && $v !== '' && $v !== null) {
                    echo '<Cell ss:StyleID="Subtotal"><Data ss:Type="Number">' . htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
                } else {
                    echo '<Cell ss:StyleID="Subtotal"><Data ss:Type="String">' . htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
                }
            }
            echo '</Row>';
            $sumSch = $sumSkh = $sumKlvc = $sumKllc = $sumDau = 0.0; $tripKeySet = [];
            $bucketFuelLT80 = $bucketFuel80200 = $bucketFuelGT200 = 0.0;
        };

        // Không cần aggregation cho format đơn giản

        // Xử lý dữ liệu nếu có
        if (!empty($rowsInGroup)) {
        // Không xây bucket theo mã chuyến nữa; dùng tổng Km của từng dòng để phân loại
        $displayedTrips = []; // Track which trip numbers have been displayed
        foreach ($rowsInGroup as $r) {
            $ship = $r['ten_phuong_tien'] ?? '';
            if ($currentShip !== null && $ship !== $currentShip) {
                $flushSubtotalXml($currentShip);
            }
            if ($currentShip === null || $ship !== $currentShip) {
                $currentShip = $ship;
            }

            $isCapThem = ((int)($r['cap_them'] ?? 0) === 1);
            
            $sch = $isCapThem ? '' : (float)($r['cu_ly_co_hang_km'] ?? 0);
            $skh = $isCapThem ? '' : (float)($r['cu_ly_khong_hang_km'] ?? 0);
            $klvc = $isCapThem ? '' : (float)($r['khoi_luong_van_chuyen_t'] ?? 0);
            $kllc = $isCapThem ? '' : (float)($r['khoi_luong_luan_chuyen'] ?? 0);
            $dau = $isCapThem ? (float)($r['so_luong_cap_them_lit'] ?? 0) : (float)($r['dau_tinh_toan_lit'] ?? 0);
            $hsKH = $isCapThem ? '' : (float)($r['he_so_khong_hang'] ?? 0);
            $hsCH = $isCapThem ? '' : (float)($r['he_so_co_hang'] ?? 0);

            if (!$isCapThem) {
                $sumSch += (float)$sch; $sumSkh += (float)$skh; $sumKlvc += (float)$klvc; $sumKllc += (float)$kllc;
                $soChuyen = trim((string)($r['so_chuyen'] ?? ''));
                    $tripKeySet[] = $soChuyen;
                // Buckets theo tổng cự ly của dòng - sử dụng giá trị đã làm tròn để đồng bộ với BC TH
                $dauRounded = round0($dau);
                $totalKm = (float)$sch + (float)$skh;
                if ($totalKm > 0) {
                    if ($totalKm < CU_LY_NGAN_MAX_KM) $bucketFuelLT80 += $dauRounded;
                    elseif ($totalKm <= CU_LY_TRUNG_BINH_MAX_KM) $bucketFuel80200 += $dauRounded;
                    else $bucketFuelGT200 += $dauRounded;
                }

                // Không cần aggregation nữa
            }
            $sumDau += round0($dau); // Sử dụng giá trị làm tròn để đồng bộ
            // Với cấp thêm: cộng vào bucket <80 để phản ánh lượng dầu cấp
            if ($isCapThem) {
                $bucketFuelLT80 += round0($dau);
            }

            $lyDoCapThem = trim((string)($r['ly_do_cap_them'] ?? ''));
            $route = '';
            if ($isCapThem) {
                // Chỉ hiển thị lý do cấp thêm, không có prefix
                // Xóa prefix "CẤP THÊM:" nếu có trong $lyDoCapThem
                $route = preg_replace('/^CẤP THÊM:\s*/i', '', $lyDoCapThem);
            } else {
                $diemDi = trim((string)($r['diem_di'] ?? ''));
                $diemDen = trim((string)($r['diem_den'] ?? ''));
                $diemDuKien = trim((string)($r['diem_du_kien'] ?? ''));
                $isDoiLenh = ($r['doi_lenh'] ?? '0') == '1';
                
                if ($diemDi !== '' || $diemDen !== '') {
                    if ($isDoiLenh) {
                        $route = $diemDi . ' → ' . $diemDuKien . ' (đổi lệnh) → ' . $diemDen;
                    } else {
                        $route = $diemDi . ' → ' . $diemDen;
                    }
                }
            }
            $ngayDi = $isCapThem ? '' : format_date_vn($r['ngay_di'] ?? '');
            $ngayDen = $isCapThem ? '' : format_date_vn($r['ngay_den'] ?? '');
            $ngayDoXong = $isCapThem ? '' : format_date_vn($r['ngay_do_xong'] ?? '');
            $ghiChu = (string)($r['ghi_chu'] ?? '');

            // Hiển thị FUEL theo bucket; với Cấp thêm: luôn ghi vào cột <80 Km
            // Làm tròn tất cả số liệu dầu thành số nguyên
            if ($isCapThem) {
                $cellLT80 = round0($dau); $cell80200 = ''; $cellGT200 = '';
            } else {
                $totalKm = (float)$sch + (float)$skh;
                if ($totalKm > 0 && $totalKm < CU_LY_NGAN_MAX_KM) { $cellLT80 = round0($dau); $cell80200 = ''; $cellGT200 = ''; }
                elseif ($totalKm >= CU_LY_NGAN_MAX_KM && $totalKm <= CU_LY_TRUNG_BINH_MAX_KM) { $cellLT80 = ''; $cell80200 = round0($dau); $cellGT200 = ''; }
                else { $cellLT80 = ''; $cell80200 = ''; $cellGT200 = round0($dau); }
            }

                // Write row theo format mẫu (22 columns)
                $soChuyenStr = (string)($r['so_chuyen'] ?? '');
                
                // Hiển thị số chuyến chỉ 1 lần cho mỗi số chuyến (không merge cells)
                // Chỉ hiển thị khi có hàng (klvc > 0) - áp dụng cho cả chuyến thường và cấp thêm
                $soChuyenDisplay = '';
                $klvcForDisplay = $isCapThem ? (float)($r['khoi_luong_van_chuyen_t'] ?? 0) : (float)$klvc;
                if ($klvcForDisplay > 0) {
                    if (!$isCapThem && $soChuyenStr !== '') {
                        $tripKey = $ship . '|' . $soChuyenStr;
                        $sequentialNumber = $tripSequentialMapping[$tripKey] ?? '';
                        if (!isset($displayedTrips[$tripKey])) {
                            $soChuyenDisplay = (string)$sequentialNumber;
                            $displayedTrips[$tripKey] = true;
                        }
                    } elseif ($isCapThem) {
                        // Cấp thêm có hàng: tìm số chuyến từ mapping
                        $capThemKey = $ship . '|cap_them_';
                        foreach ($tripSequentialMapping as $key => $seqNum) {
                            if (strpos($key, $capThemKey) === 0 && !isset($displayedTrips[$key])) {
                                $soChuyenDisplay = (string)$seqNum;
                                $displayedTrips[$key] = true;
                                break;
                            }
                        }
                    }
                }
                
                $totalKm = ($isCapThem ? 0.0 : ((float)$sch + (float)$skh));
            
            echo '<Row>';
                // 0: STT (center number)
                echo '<Cell ss:StyleID="Center"><Data ss:Type="Number">' . $stt . '</Data></Cell>';
                // 1: TÊN PT (left)
                echo '<Cell ss:StyleID="Left"><Data ss:Type="String">' . htmlspecialchars((string)$ship, ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
                // 2: SỐ DK (empty)
                echo '<Cell ss:StyleID="Center"><Data ss:Type="String"></Data></Cell>';
                // 3: SỐ CHUYẾN (center string) - chỉ hiển thị một lần, các dòng còn lại để trống
                echo '<Cell ss:StyleID="Center"><Data ss:Type="String">' . htmlspecialchars($soChuyenDisplay, ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
                // 4: TUYẾN ĐƯỜNG (left)
                echo '<Cell ss:StyleID="Left"><Data ss:Type="String">' . htmlspecialchars((string)$route, ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
                // 5: CỰ LY KH (Km) (Right0) - Empty distance
                if ($isCapThem || $skh == 0) {
                    echo '<Cell ss:StyleID="Center"><Data ss:Type="String"></Data></Cell>';
                } else {
                    echo '<Cell ss:StyleID="Right0"><Data ss:Type="Number">' . (int)$skh . '</Data></Cell>';
                }
                // 6: CỰ LY CH (Km) (Right0) - Loaded distance
                if ($isCapThem || $sch == 0) {
                    echo '<Cell ss:StyleID="Center"><Data ss:Type="String"></Data></Cell>';
                } else {
                    echo '<Cell ss:StyleID="Right0"><Data ss:Type="Number">' . (int)$sch . '</Data></Cell>';
                }
                // 7: TỔNG CỰ LY (Right0)
                if ($isCapThem || $totalKm == 0) {
                    echo '<Cell ss:StyleID="Center"><Data ss:Type="String"></Data></Cell>';
                } else {
                    echo '<Cell ss:StyleID="Right0"><Data ss:Type="Number">' . (int)$totalKm . '</Data></Cell>';
                }
                // 8: HS KH (Right7 - 7 decimals)
                if ($isCapThem || $hsKH == 0) {
                    echo '<Cell ss:StyleID="Center"><Data ss:Type="String"></Data></Cell>';
                } else {
                    echo '<Cell ss:StyleID="Right7"><Data ss:Type="Number">' . htmlspecialchars((string)number_format((float)$hsKH, 7, '.', ''), ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
                }
                // 9: HS CH (Right7 - 7 decimals)
                if ($isCapThem || $hsCH == 0) {
                    echo '<Cell ss:StyleID="Center"><Data ss:Type="String"></Data></Cell>';
                } else {
                    echo '<Cell ss:StyleID="Right7"><Data ss:Type="Number">' . htmlspecialchars((string)number_format((float)$hsCH, 7, '.', ''), ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
                }
                // 10: KL VC (T) (Right2)
                if ($isCapThem || $klvc == 0) {
                    echo '<Cell ss:StyleID="Center"><Data ss:Type="String"></Data></Cell>';
                } else {
                    echo '<Cell ss:StyleID="Right2"><Data ss:Type="Number">' . htmlspecialchars((string)$fmt2($klvc), ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
                }
                // 11: SL LUÂN CHUYỂN (T.Km) (Right2)
                if ($isCapThem || $kllc == 0) {
                    echo '<Cell ss:StyleID="Center"><Data ss:Type="String"></Data></Cell>';
                } else {
                    echo '<Cell ss:StyleID="Right2"><Data ss:Type="Number">' . htmlspecialchars((string)$fmt2($kllc), ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
                }
                // 12: DẦU SỬ DỤNG (Lit) (Right0) - làm tròn số nguyên
                echo '<Cell ss:StyleID="Right0"><Data ss:Type="Number">' . round0($dau) . '</Data></Cell>';
                // 13: NGÀY ĐI (Date)
                if ($ngayDi !== '') {
                    echo '<Cell ss:StyleID="Date"><Data ss:Type="String">' . htmlspecialchars((string)$ngayDi, ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
                } else { echo '<Cell ss:StyleID="Center"><Data ss:Type="String"></Data></Cell>'; }
                // 14: NGÀY ĐẾN (Date)
                if ($ngayDen !== '') {
                    echo '<Cell ss:StyleID="Date"><Data ss:Type="String">' . htmlspecialchars((string)$ngayDen, ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
                } else { echo '<Cell ss:StyleID="Center"><Data ss:Type="String"></Data></Cell>'; }
                // 15: NGÀY DỠ XONG (Date)
                if ($ngayDoXong !== '') {
                    echo '<Cell ss:StyleID="Date"><Data ss:Type="String">' . htmlspecialchars((string)$ngayDoXong, ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
                } else { echo '<Cell ss:StyleID="Center"><Data ss:Type="String"></Data></Cell>'; }
                // 16: LOẠI HÀNG (left)
                echo '<Cell ss:StyleID="Left"><Data ss:Type="String">' . htmlspecialchars((string)($r['loai_hang'] ?? ''), ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
                // 17: GHI CHÚ (left with wrapping)
                echo '<Cell ss:StyleID="LeftWrap"><Data ss:Type="String">' . htmlspecialchars((string)$ghiChu, ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
                // 18-20: buckets (Right0) - ghi FUEL làm tròn số nguyên
                if ($cellLT80 === '') {
                    echo '<Cell ss:StyleID="Center"><Data ss:Type="String"></Data></Cell>';
                } else {
                    echo '<Cell ss:StyleID="Right0"><Data ss:Type="Number">' . $cellLT80 . '</Data></Cell>';
                }
                if ($cell80200 === '') {
                    echo '<Cell ss:StyleID="Center"><Data ss:Type="String"></Data></Cell>';
                } else {
                    echo '<Cell ss:StyleID="Right0"><Data ss:Type="Number">' . $cell80200 . '</Data></Cell>';
                }
                if ($cellGT200 === '') {
                    echo '<Cell ss:StyleID="Center"><Data ss:Type="String"></Data></Cell>';
                } else {
                    echo '<Cell ss:StyleID="Right0"><Data ss:Type="Number">' . $cellGT200 . '</Data></Cell>';
                }
            echo '</Row>';
                $stt++;
        }
        $flushSubtotalXml($currentShip);
        }
        
        // Add total row at the end of the sheet
        if ($totalTripCount > 0) {
            echo '<Row>';
            $totalKmSum = $totalSch + $totalSkh;
            $cells = [
                '', 'TỔNG CỘNG', '', $totalTripCount, '', $fmt1($totalSkh), $fmt1($totalSch), $fmt1($totalKmSum), '', '', $fmt2($totalKlvc), $fmt2($totalKllc), round0($totalDau), '', '', '', '', '', round0($totalBucketFuelLT80), round0($totalBucketFuel80200), round0($totalBucketFuelGT200)
            ];
            // Types for each col (21 columns total)
            $types = ['String','String','String','Number','String','Number','Number','Number','Number','Number','Number','Number','Number','String','String','String','String','String','Number','Number','Number'];
            foreach ($cells as $i => $v) {
                $t = $types[$i] ?? 'String';
                if ($t === 'Number' && $v !== '' && $v !== null) {
                    echo '<Cell ss:StyleID="Total"><Data ss:Type="Number">' . htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
                } else {
                    echo '<Cell ss:StyleID="Total"><Data ss:Type="String">' . htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
                }
            }
            echo '</Row>';
        }

        echo '</Table></Worksheet>';
    }

    // Summary BC TH sheet
    // Gom nhóm theo tàu để tính các chỉ số theo yêu cầu
    $rowsByShip = [];
    foreach ($exportRows as $r) {
        $ship = trim((string)($r['ten_phuong_tien'] ?? ''));
        if ($ship === '') continue;
        $rowsByShip[$ship][] = $r;
    }

    $agg = [];
    foreach ($rowsByShip as $ship => $rows) {
        $soChuyenSet = [];
        $tongKm = 0.0; $sumKlvc = 0.0; $sumKllc = 0.0; $fuelNo = 0.0; $fuelYes = 0.0;
        $bucketFuelLT80 = 0.0; $bucketFuel80200 = 0.0; $bucketFuelGT200 = 0.0;

        // Xác định bucket theo mã chuyến (dựa trên tổng Km của các đoạn trong chuyến)
        $kmByTrip = [];
        foreach ($rows as $r) {
            if ((int)($r['cap_them'] ?? 0) === 1) continue; // chỉ tính km từ dòng chuyến
            $trip = trim((string)($r['so_chuyen'] ?? ''));
            if ($trip === '') continue;
            $kmByTrip[$trip] = ($kmByTrip[$trip] ?? 0)
                + (float)($r['cu_ly_co_hang_km'] ?? 0)
                + (float)($r['cu_ly_khong_hang_km'] ?? 0);
        }
        $tripBucket = [];
        foreach ($kmByTrip as $trip => $km) {
            if ($km < CU_LY_NGAN_MAX_KM) $tripBucket[$trip] = 'lt80';
            elseif ($km <= CU_LY_TRUNG_BINH_MAX_KM) $tripBucket[$trip] = '80200';
            else $tripBucket[$trip] = 'gt200';
        }

        // Tính các tổng theo tàu
        foreach ($rows as $r) {
            $isCap = (int)($r['cap_them'] ?? 0) === 1;
            $klvcRow = (float)($r['khoi_luong_van_chuyen_t'] ?? 0);
            $kllcRow = (float)($r['khoi_luong_luan_chuyen'] ?? 0);
            if (!$isCap) {
                $trip = trim((string)($r['so_chuyen'] ?? ''));
                if ($trip !== '') { $soChuyenSet[$trip] = true; }
                $tongKm += (float)($r['cu_ly_co_hang_km'] ?? 0) + (float)($r['cu_ly_khong_hang_km'] ?? 0);
                $sumKlvc += $klvcRow;
                $sumKllc += $kllcRow;
            }

            // Lít dầu của dòng
            $litRaw = $isCap ? (float)($r['so_luong_cap_them_lit'] ?? 0) : (float)($r['dau_tinh_toan_lit'] ?? 0);
            // Quy ước: BC TH dùng lít đã làm tròn nguyên như subtotal ở BCTHANG
            $lit = round($litRaw, 0);

            if (!$isCap) {
                if ($klvcRow > 0) { $fuelYes += $lit; }
                else { $fuelNo += $lit; }
                // Phân bucket theo tổng cự ly của CHÍNH DÒNG (đồng bộ BCTHANG)
                $kmLine = (float)($r['cu_ly_co_hang_km'] ?? 0) + (float)($r['cu_ly_khong_hang_km'] ?? 0);
                if ($kmLine > 0 && $kmLine < CU_LY_NGAN_MAX_KM) { $bucketFuelLT80 += $lit; }
                elseif ($kmLine >= CU_LY_NGAN_MAX_KM && $kmLine <= CU_LY_TRUNG_BINH_MAX_KM) { $bucketFuel80200 += $lit; }
                else { $bucketFuelGT200 += $lit; }
            } else {
                // Cấp thêm → tính vào KHÔNG HÀNG và luôn đưa vào <80 Km
                $fuelNo += $lit;
                $bucketFuelLT80 += $lit;
            }
        }

        $agg[$ship] = [
            'so_chuyen' => count($soChuyenSet),
            'tong_km' => $tongKm,
            'klvc' => $sumKlvc,
            'kllc' => $sumKllc,
            'fuel_no' => $fuelNo,
            'fuel_yes' => $fuelYes,
            'fuel_total' => ($fuelNo + $fuelYes),
            'km_lt80' => $bucketFuelLT80,
            'km_80_200' => $bucketFuel80200,
            'km_gt200' => $bucketFuelGT200,
        ];
    }
    ksort($agg, SORT_NATURAL | SORT_FLAG_CASE);

    // Chuẩn hóa số liệu tiêu hao cho DAUTON lấy trực tiếp từ BC TH (đồng bộ nguồn)
    $usageByShipBCTH = [];
    foreach ($agg as $ship => $a) {
        $usageByShipBCTH[$ship] = [
            'fuel_no'  => (int)$a['fuel_no'],
            'fuel_yes' => (int)$a['fuel_yes'],
        ];
    }

    echo '<Worksheet ss:Name="BC TH"><Table>';
    $sumColW = [35,120,80,110,90,120,140,140,160,80,90,90];
    foreach ($sumColW as $w) { echo '<Column ss:Width="' . (float)$w . '"/>'; }

    // HEADER TEMPLATE (dòng 1-5)
    printSheetHeaderTemplate(12);

    $title1 = 'BẢNG TỔNG HỢP NHIÊN LIỆU SỬ DỤNG';
    $title2 = 'THÁNG ' . $currentMonth . ' NĂM ' . $currentYear;
    echo '<Row><Cell ss:MergeAcross="11" ss:StyleID="Title"><Data ss:Type="String">' . htmlspecialchars($title1, ENT_QUOTES, 'UTF-8') . '</Data></Cell></Row>';
    echo '<Row><Cell ss:MergeAcross="11" ss:StyleID="Title"><Data ss:Type="String">' . htmlspecialchars($title2, ENT_QUOTES, 'UTF-8') . '</Data></Cell></Row>';
    echo '<Row><Cell ss:MergeAcross="11" ss:StyleID="Body"><Data ss:Type="String"></Data></Cell></Row>';
    $sumHeaders = ['STT','PHƯƠNG TIỆN','SỐ CHUYẾN','TỔNG CỰ LY (Km)','KLVC (T)','SL LUÂN CHUYỂN (T.Km)','DẦU SỬ DỤNG KHÔNG HÀNG','DẦU SỬ DỤNG CÓ HÀNG','TỔNG DẦU SD TẠM TÍNH (Lít)','<80 Km','80-200 Km','>200 Km'];
    echo '<Row>'; foreach ($sumHeaders as $h) { echo '<Cell ss:StyleID="Header"><Data ss:Type="String">' . htmlspecialchars($h, ENT_QUOTES, 'UTF-8') . '</Data></Cell>'; } echo '</Row>';
    echo '<Row><Cell ss:StyleID="Center"><Data ss:Type="String"></Data></Cell><Cell ss:StyleID="Center"><Data ss:Type="String">THÁNG ' . htmlspecialchars((string)$currentMonth, ENT_QUOTES, 'UTF-8') . ' NĂM ' . htmlspecialchars((string)$currentYear, ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
    for ($i=0;$i<10;$i++){ echo '<Cell ss:StyleID="Center"><Data ss:Type="String"></Data></Cell>'; } echo '</Row>';

    $iStt = 1; $gt = ['so_chuyen'=>0,'tong_km'=>0,'klvc'=>0,'kllc'=>0,'fuel_no'=>0,'fuel_yes'=>0,'fuel_total'=>0,'km_lt80'=>0,'km_80_200'=>0,'km_gt200'=>0];
    foreach ($agg as $ship => $a) {
        echo '<Row>';
        echo '<Cell ss:StyleID="Center"><Data ss:Type="Number">' . $iStt . '</Data></Cell>';
        echo '<Cell ss:StyleID="Left"><Data ss:Type="String">' . htmlspecialchars($ship, ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
        echo '<Cell ss:StyleID="Right0"><Data ss:Type="Number">' . (int)$a['so_chuyen'] . '</Data></Cell>';
        echo '<Cell ss:StyleID="Right0"><Data ss:Type="Number">' . (int)$a['tong_km'] . '</Data></Cell>';
        echo '<Cell ss:StyleID="Right2"><Data ss:Type="Number">' . htmlspecialchars((string)$fmt2($a['klvc']), ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
        echo '<Cell ss:StyleID="Right2"><Data ss:Type="Number">' . htmlspecialchars((string)$fmt2($a['kllc']), ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
        echo '<Cell ss:StyleID="Right0"><Data ss:Type="Number">' . (int)$a['fuel_no'] . '</Data></Cell>';
        echo '<Cell ss:StyleID="Right0"><Data ss:Type="Number">' . (int)$a['fuel_yes'] . '</Data></Cell>';
        echo '<Cell ss:StyleID="Right0"><Data ss:Type="Number">' . (int)($a['fuel_total']) . '</Data></Cell>';
        echo '<Cell ss:StyleID="Right0"><Data ss:Type="Number">' . (int)$a['km_lt80'] . '</Data></Cell>';
        echo '<Cell ss:StyleID="Right0"><Data ss:Type="Number">' . (int)$a['km_80_200'] . '</Data></Cell>';
        echo '<Cell ss:StyleID="Right0"><Data ss:Type="Number">' . (int)$a['km_gt200'] . '</Data></Cell>';
        echo '</Row>';
        $iStt++; foreach ($gt as $k=>$v){ $gt[$k] += $a[$k]; }
    }

    echo '<Row>';
    echo '<Cell ss:StyleID="SubtotalPlain"><Data ss:Type="String"></Data></Cell>';
    echo '<Cell ss:StyleID="SubtotalPlain"><Data ss:Type="String">Tổng cộng:</Data></Cell>';
    echo '<Cell ss:StyleID="SubtotalPlain"><Data ss:Type="Number">' . (int)$gt['so_chuyen'] . '</Data></Cell>';
    echo '<Cell ss:StyleID="SubtotalPlain"><Data ss:Type="Number">' . (int)$gt['tong_km'] . '</Data></Cell>';
    echo '<Cell ss:StyleID="SubtotalPlain"><Data ss:Type="Number">' . htmlspecialchars((string)$fmt2($gt['klvc']), ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
    echo '<Cell ss:StyleID="SubtotalPlain"><Data ss:Type="Number">' . htmlspecialchars((string)$fmt2($gt['kllc']), ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
    echo '<Cell ss:StyleID="SubtotalPlain"><Data ss:Type="Number">' . (int)$gt['fuel_no'] . '</Data></Cell>';
    echo '<Cell ss:StyleID="SubtotalPlain"><Data ss:Type="Number">' . (int)$gt['fuel_yes'] . '</Data></Cell>';
    echo '<Cell ss:StyleID="SubtotalPlain"><Data ss:Type="Number">' . (int)($gt['fuel_total']) . '</Data></Cell>';
    echo '<Cell ss:StyleID="SubtotalPlain"><Data ss:Type="Number">' . (int)$gt['km_lt80'] . '</Data></Cell>';
    echo '<Cell ss:StyleID="SubtotalPlain"><Data ss:Type="Number">' . (int)$gt['km_80_200'] . '</Data></Cell>';
    echo '<Cell ss:StyleID="SubtotalPlain"><Data ss:Type="Number">' . (int)$gt['km_gt200'] . '</Data></Cell>';
    echo '</Row>';

    echo '</Table></Worksheet>';
    } // End of regular export (BCTHANG-SLCTY, BCTHANG-SLN, BC TH)

    // Bật/tắt sinh 2 sheet DAUTON; khi tắt thì không chạy bất kỳ chuẩn bị dữ liệu nào (an toàn XML)
    $ENABLE_DAUTON = true; // bật lại xuất 2 sheet DAUTON an toàn

    // IN TINH DAU: chỉ sinh khi có chọn tàu từ wizard extra_ships[] (xuất chi tiết)
    
    if ($isDetailedExport) {
        // ===================== IN TINH DAU-SLCTY (mỗi tàu công ty một sheet) =====================
        // Khoảng thời gian theo tháng (sẽ có thể được hiệu chỉnh theo tàu đã chọn ở dưới)
        $monthStartIso = date('Y-m-01', strtotime($currentYear . '-' . $currentMonth . '-01'));
        $monthEndIso   = date('Y-m-t',  strtotime($currentYear . '-' . $currentMonth . '-01'));
        $prevEndIso    = date('Y-m-d', strtotime($monthStartIso . ' -1 day'));
        $prevEndVN     = format_date_vn($prevEndIso);

        $dauTonModel = new DauTon();

        // Nguồn dữ liệu cho xuất chi tiết: sử dụng cùng dữ liệu với BCTHANG để đồng bộ
        $sourceRows = $exportRows;

        // Chuẩn bị danh sách tàu theo phân loại để sinh sheet theo tàu công ty/thuê ngoài
        $shipsByPL = ['cong_ty' => [], 'thue_ngoai' => []];
        foreach ($sourceRows as $r) {
            if ((int)($r['cap_them'] ?? 0) === 1) { continue; }
            $ship = trim((string)($r['ten_phuong_tien'] ?? ''));
            if ($ship === '') continue;
            $shipClean = trim($ship, '"');
            $pl = $plMap[$shipClean] ?? 'cong_ty';
            // Lọc theo tháng đã chọn: ưu tiên ngày dỡ xong, fallback ngày đi
            $ngayDoXong = (string)($r['ngay_do_xong'] ?? '');
            $ngayDi = (string)($r['ngay_di'] ?? '');
            $createdAt = (string)($r['created_at'] ?? '');
            $createdIso = $createdAt !== '' ? substr($createdAt, 0, 10) : '';
            $dayIso = $isCapThem ? 
                ($ngayDi !== '' ? parse_date_vn($ngayDi) : ($createdIso !== '' ? $createdIso : '')) : 
                ($ngayDoXong !== '' ? parse_date_vn($ngayDoXong) : ($ngayDi !== '' ? parse_date_vn($ngayDi) : ''));
            // Đã được lọc sẵn từ $exportRows, không cần lọc thêm theo tháng
            $shipsByPL[$pl][$ship] = true;
        }

        // Nếu lọc theo Tên tàu hoặc chọn thêm tàu khi export, chỉ giữ các tàu đã chọn để sinh sheet chi tiết
        $shipFilterRaw = trim((string)($q['ten_phuong_tien'] ?? ''));
        $extraShips = $extraShipsRaw;
        $selectedShipsSet = [];
        if ($shipFilterRaw !== '') { $selectedShipsSet[$shipFilterRaw] = true; }
        foreach ($extraShips as $ex) { if ($ex !== '') { $selectedShipsSet[$ex] = true; } }
        // Chuẩn hóa so sánh theo tên sạch (bỏ ") và lowercase
        $selectedClean = [];
        foreach ($selectedShipsSet as $k => $_) { $selectedClean[strtolower(trim($k, '"'))] = true; }

        // Bản đồ hỗ trợ: tên tàu (lowercase, bỏ ") => [tên gốc, phân loại]
        $selectedLookup = [];
        foreach ($plMap as $origName => $plVal) {
            $selectedLookup[strtolower(trim($origName, '"'))] = ['orig' => $origName, 'pl' => ($plVal ?: 'cong_ty')];
        }
        $shouldGenerateInTinhDau = (count($selectedClean) > 0);

        // Nếu có chọn tàu, suy luận lại THÁNG/NĂM từ dữ liệu của chính các tàu đó
        if ($shouldGenerateInTinhDau) {
            $monthCountSel = [];
            $allForInfer = (new LuuKetQua())->layTatCaKetQua();
            // Chỉ suy luận lại tháng nếu người dùng KHÔNG truyền bộ lọc tháng
            if ($currentMonth === null || $currentYear === null) {
            foreach ($allForInfer as $rowInf) {
                $shipInf = strtolower(trim((string)($rowInf['ten_phuong_tien'] ?? ''), '"'));
                if ($shipInf === '' || !isset($selectedClean[$shipInf])) continue;
                $ngayDoXongInf = (string)($rowInf['ngay_do_xong'] ?? '');
                $ngayDiInf = (string)($rowInf['ngay_di'] ?? '');
                $isoInf = $ngayDoXongInf !== '' ? parse_date_vn($ngayDoXongInf) : ($ngayDiInf !== '' ? parse_date_vn($ngayDiInf) : '');
                if (!$isoInf) continue;
                $yInf = (int)date('Y', strtotime($isoInf));
                $mInf = (int)date('n', strtotime($isoInf));
                $keyInf = $yInf . '-' . $mInf;
                $monthCountSel[$keyInf] = ($monthCountSel[$keyInf] ?? 0) + 1;
            }
            if (!empty($monthCountSel)) {
                arsort($monthCountSel);
                $topKeySel = array_key_first($monthCountSel);
                [$yS, $mS] = array_map('intval', explode('-', $topKeySel));
                $currentYear = $yS; $currentMonth = $mS;
                $monthStartIso = date('Y-m-01', strtotime($currentYear . '-' . $currentMonth . '-01'));
                $monthEndIso   = date('Y-m-t',  strtotime($currentYear . '-' . $currentMonth . '-01'));
                $prevEndIso    = date('Y-m-d', strtotime($monthStartIso . ' -1 day'));
                $prevEndVN     = format_date_vn($prevEndIso);
            }
            }
        }
        if ($shouldGenerateInTinhDau) {
            $filteredShipsByPL = ['cong_ty' => [], 'thue_ngoai' => []];
            foreach ($shipsByPL as $plKey => $set) {
                foreach ($set as $shipName => $_v) {
                    $nameClean = strtolower(trim($shipName, '"'));
                    if (isset($selectedClean[$nameClean])) {
                        $filteredShipsByPL[$plKey][$shipName] = true;
                    }
                }
            }
            $shipsByPL = $filteredShipsByPL;

            // Đảm bảo những tàu được chọn vẫn được sinh sheet
            foreach ($selectedClean as $selNameLc => $_v) {
                if (!isset($selectedLookup[$selNameLc])) continue;
                $orig = $selectedLookup[$selNameLc]['orig'];
                $plSel = $selectedLookup[$selNameLc]['pl'];
                if (!isset($shipsByPL[$plSel][$orig])) {
                    $shipsByPL[$plSel][$orig] = true; // thêm vào tập sinh sheet dù không có chuyến trong tháng
                }
            }
        }

        // Chỉ xây dữ liệu khi có lọc theo tàu
        $rowsByShip = [];
        if (!empty($shouldGenerateInTinhDau) && $shouldGenerateInTinhDau) {
            // Giữ ngày chuyến trước đó theo tàu để suy luận ngày cho dòng cấp thêm không có ngày
            $prevTripDateByShip = [];
            foreach ($sourceRows as $r) {
                $ship = trim((string)($r['ten_phuong_tien'] ?? ''));
                if ($ship === '') continue;
                $shipClean = trim($ship, '"');
                $pl = $plMap[$shipClean] ?? 'cong_ty';
                if ($pl !== 'cong_ty') continue;
                
                // Xử lý ngày cho cả chuyến thường và cấp thêm
                $isCapThem = (int)($r['cap_them'] ?? 0) === 1;
                $ngayDoXong = (string)($r['ngay_do_xong'] ?? '');
                $ngayDi = (string)($r['ngay_di'] ?? '');
                $createdAt = (string)($r['created_at'] ?? '');
                $createdIso = $createdAt !== '' ? substr($createdAt, 0, 10) : '';
                
                // Suy luận ngày cho dòng cấp thêm không có ngày: ưu tiên dùng ngày chuyến trước đó của cùng tàu
                $inferredIso = '';
                if ($isCapThem) {
                    if ($ngayDi !== '') {
                        $inferredIso = parse_date_vn($ngayDi) ?: '';
                    } elseif (!empty($prevTripDateByShip[$ship])) {
                        $inferredIso = $prevTripDateByShip[$ship];
                    } elseif ($createdIso !== '') {
                        $inferredIso = $createdIso;
                    }
                } else {
                    // Chuyến thường: cập nhật ngày tham chiếu cho tàu
                    $inferredIso = $ngayDoXong !== '' ? (parse_date_vn($ngayDoXong) ?: '') : ($ngayDi !== '' ? (parse_date_vn($ngayDi) ?: '') : '');
                    // Fallback nếu không có ngày đi/đến: dùng created_at để không bị bỏ sót
                    if ($inferredIso === '' && $createdIso !== '') {
                        $inferredIso = $createdIso;
                    }
                    if ($inferredIso !== '') {
                        $prevTripDateByShip[$ship] = $inferredIso;
                    }
                }
                
                $dayIso = $isCapThem ? $inferredIso : $inferredIso;
                // Đã được lọc sẵn từ $exportRows, không cần lọc thêm
                // Lưu lại ngày suy luận để dùng khi render
                $r['__inferred_day'] = $dayIso;
                $rowsByShip[$ship][] = $r;
            }
        }

        // Helper: tạo số chuyến theo tàu (ordinal)
        $buildTripOrdinal = function(array $rows): array {
            $ord = []; $map = []; $i = 1;
            foreach ($rows as $row) {
                $sc = (string)($row['so_chuyen'] ?? '');
                if ($sc === '') continue;
                if (!isset($map[$sc])) { $map[$sc] = $i++; }
            }
            return $map;
        };

        // Render một sheet IN TINH DAU cho 1 tàu theo phân loại
        $renderInTinhDauForShip = function(string $ship, string $suffixTitle) use (&$rowsByShip,$currentMonth,$currentYear,$prevEndVN,$dauTonModel,$monthStartIso,$monthEndIso) {
            $rows = $rowsByShip[$ship] ?? [];
            // Sắp xếp theo thứ tự logic: mã chuyến -> thứ tự nhập liệu
            usort($rows, function($a,$b){
                // Sắp xếp theo tên tàu (alphabet) - THÊM VÀO
                $ta = mb_strtolower(trim($a['ten_phuong_tien'] ?? ''));
                $tb = mb_strtolower(trim($b['ten_phuong_tien'] ?? ''));
                if ($ta !== $tb) return $ta <=> $tb;

                // Sắp xếp theo mã chuyến (số tăng dần)
                $tripA = (int)($a['so_chuyen'] ?? 0);
                $tripB = (int)($b['so_chuyen'] ?? 0);
                if ($tripA !== $tripB) return $tripA <=> $tripB;

                // Sắp xếp theo ___idx (thứ tự trong CSV)
                $idxA = (int)($a['___idx'] ?? 0);
                $idxB = (int)($b['___idx'] ?? 0);
                if ($idxA !== $idxB) return $idxA <=> $idxB;

                // Fallback: created_at
                return strcmp($a['created_at'] ?? '', $b['created_at'] ?? '');
            });

            // No longer need ordinal mapping since we show trip numbers directly

            // Tính tổng dầu sử dụng (để tính tồn cuối)
            $sumFuelUsed = 0.0;
            foreach ($rows as $r) {
                $isCapThem = (int)($r['cap_them'] ?? 0) === 1;
                if ($isCapThem) {
                    // Cấp thêm từ trang tính toán: cộng trực tiếp số lượng tiêu hao
                    $sumFuelUsed += (float)($r['so_luong_cap_them_lit'] ?? 0);
                } else {
                    // Chuyến thường: tính theo công thức
                    $sch = (float)($r['cu_ly_co_hang_km'] ?? 0);
                    $skh = (float)($r['cu_ly_khong_hang_km'] ?? 0);
                    $kkh = (float)($r['he_so_khong_hang'] ?? 0);
                    $kch = (float)($r['he_so_co_hang'] ?? 0);
                    $kl  = (float)($r['khoi_luong_van_chuyen_t'] ?? 0);
                    $sumFuelUsed += ($skh * $kkh) + ($sch * $kl * $kch);
                }
            }

            // Dầu tồn đầu kỳ cho IN TINH DAU: lấy số dư CUỐI THÁNG TRƯỚC
            // (không bao gồm các giao dịch phát sinh trong ngày 01 của tháng hiện tại)
            $nhatKy = $dauTonModel->getNhatKyHienThi($ship);
            $prevEndIso = date('Y-m-d', strtotime($monthStartIso . ' -1 day'));
            $tonDau = 0.0;
            foreach ($nhatKy as $entry) {
                $dIso = (string)($entry['ngay'] ?? '');
                if ($dIso !== '' && strcmp($dIso, $prevEndIso) <= 0) {
                    $tonDau = (float)($entry['so_du'] ?? 0);
                }
            }
            $tongCapTrongThang = 0.0; 
            $receiptEntries = []; // Danh sách các dòng hiển thị trong mục "Nhận dầu tại"
            $cleanUseThang = 0.0; // dầu dùng vệ sinh/chà rửa khi neo đậu
            foreach ($dauTonModel->getLichSuGiaoDich($ship) as $gd) {
                $ngay = (string)($gd['ngay'] ?? '');
                if (!$ngay || strcmp($ngay, $monthStartIso) < 0 || strcmp($ngay, $monthEndIso) > 0) continue;
                $loai = strtolower((string)($gd['loai'] ?? ''));
                if ($loai === 'cap_them') {
                    $soLuong = (float)($gd['so_luong_lit'] ?? 0);
                    $tongCapTrongThang += $soLuong;
                    $label = trim((string)($gd['cay_xang'] ?? ''));
                    if ($label === '') { $label = ''; }
                    $receiptEntries[] = ['label' => $label, 'date' => $ngay, 'amount' => $soLuong];
                } elseif ($loai === 'tinh_chinh') {
                    // Tinh chỉnh: hiển thị nhãn theo hướng chuyển dầu nếu có metadata chuyển dầu trong ly_do
                    $soLuong = (float)($gd['so_luong_lit'] ?? 0);
                    $tongCapTrongThang += $soLuong;
                    $rawLyDo = (string)($gd['ly_do'] ?? '');
                    $dir = 'in'; $other = '';
                    if (preg_match('/chuyển sang\s+([^\s]+)/u', $rawLyDo, $m)) { $dir = 'out'; $other = $m[1]; }
                    elseif (preg_match('/nhận từ\s+([^\s]+)/u', $rawLyDo, $m)) { $dir = 'in'; $other = $m[1]; }
                    $label = $other !== '' ? td2_format_transfer_label($ship, $other, $dir) : 'Tinh chỉnh';
                    $receiptEntries[] = ['label' => $label, 'date' => $ngay, 'amount' => $soLuong];
                } elseif (in_array($loai, ['ve_sinh','cha_rua','ve sinh','cha rua'], true)) {
                    $cleanUseThang += (float)($gd['so_luong_lit'] ?? 0);
                }
            }
            // tonCuoi sẽ được tính lại sau theo yêu cầu hiển thị
            $tonCuoi = 0.0;

            // Tên sheet phải duy nhất → nối thêm tên tàu
            // Nếu là thuê ngoài (suffixTitle = 'THUÊ NGOÀI') thì dùng nhãn SLN, ngược lại SLCTY
            $sheetType = (trim($suffixTitle) === 'THUÊ NGOÀI') ? 'SLN' : 'SLCTY';
            $sheetName = 'IN TINH DAU-' . $sheetType . ' - ' . $ship;
            echo '<Worksheet ss:Name="' . htmlspecialchars($sheetName, ENT_QUOTES, 'UTF-8') . '"><Table>';
            // Định nghĩa độ rộng cột theo header yêu cầu
            $colW = [35,70,90,110,300,90,80,110,160];
            for ($ci = 0; $ci < count($colW); $ci++) {
                $attrs = ' ss:Width="' . (float)$colW[$ci] . '"';
                // Tự động dãn cột D (LOẠI HÀNG) để hiển thị đầy đủ nhãn "Dầu tồn trên sà lan đến ngày"
                if ($ci === 3) { // cột D
                    $attrs .= ' ss:AutoFitWidth="1"';
                }
                echo '<Column' . $attrs . '/>';
            }

            // HEADER TEMPLATE (dòng 1-5)
            printSheetHeaderTemplate(9);

            // Tiêu đề lớn
            $title = 'BÁO CÁO TÍNH DẦU SÀ LAN TỰ HÀNH ' . $ship . ($suffixTitle !== '' ? (' ' . $suffixTitle) : '');
            echo '<Row><Cell ss:MergeAcross="8" ss:StyleID="Title"><Data ss:Type="String">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</Data></Cell></Row>';
            echo '<Row><Cell ss:MergeAcross="8" ss:StyleID="Body"><Data ss:Type="String"></Data></Cell></Row>';

            // Header
            $headers = ['STT','SỐ CHUYẾN','KLVC (Tấn)','LOẠI HÀNG','PHƯƠNG TIỆN CHẠY','NGÀY ĐI','CỰ LY (Km)','DẦU DO tạm tính (Lít)','GHI CHÚ'];
            echo '<Row>'; foreach ($headers as $h) { echo '<Cell ss:StyleID="Header"><Data ss:Type="String">' . htmlspecialchars($h, ENT_QUOTES, 'UTF-8') . '</Data></Cell>'; } echo '</Row>';

            // Dòng dữ liệu
            $stt = 1;
            // Track displayed trips to show trip number only once per group
            $displayedTripsInTinhDau = [];
            $sumKmDisplay = 0;   // Tổng cự ly hiển thị
            $sumFuelDisplay = 0; // Tổng dầu DO hiển thị (đơn vị)
            $uniqueTrips = []; // Đếm số chuyến duy nhất trong tháng
            foreach ($rows as $r) {
                $isCapThem = (int)($r['cap_them'] ?? 0) === 1;
                $tripCode = (string)($r['so_chuyen'] ?? '');
                
                if ($isCapThem) {
                    // Cấp thêm từ trang tính toán: hiển thị theo format chuẩn
                    $fuel = (float)($r['so_luong_cap_them_lit'] ?? 0);
                    // Không hiển thị nhãn ở cột SỐ CHUYẾN cho dòng Cấp thêm (để thống nhất format)
                    $soChuyenDisplay = '';
                    // Format chuẩn: hiển thị lý do cấp thêm
                    $lyDo = trim((string)($r['ly_do_cap_them'] ?? ''));
                    // Xóa prefix "CẤP THÊM:" nếu có trong $lyDo
                    $lyDoClean = preg_replace('/^CẤP THÊM:\s*/i', '', $lyDo);
                    $route = $lyDoClean !== '' ? $lyDoClean : 'Dầu ma no tại bến Vĩnh Xương( AG) 01 chuyến 70 lít';
                    $dateIso = (string)($r['__inferred_day'] ?? '');
                    if ($dateIso === '') {
                        if ((string)($r['ngay_di'] ?? '') !== '') {
                            $dateIso = parse_date_vn((string)($r['ngay_di'] ?? '')) ?: '';
                        } else {
                            $dateIso = substr((string)($r['created_at'] ?? ''), 0, 10);
                        }
                    }
                    $dateVN = format_date_vn($dateIso);
                    $totalKm = 0; // Cấp thêm không có cự ly
                    $kl = 0; // Cấp thêm không có khối lượng
                    // Không hiển thị nhãn "Cấp thêm" ở cột LOẠI HÀNG theo yêu cầu
                    $loaiHang = '';
                } else {
                    // Chuyến thường: tính theo công thức
                    $sch = (float)($r['cu_ly_co_hang_km'] ?? 0);
                    $skh = (float)($r['cu_ly_khong_hang_km'] ?? 0);
                    $kkh = (float)($r['he_so_khong_hang'] ?? 0);
                    $kch = (float)($r['he_so_co_hang'] ?? 0);
                    $kl  = (float)($r['khoi_luong_van_chuyen_t'] ?? 0);
                    // Ưu tiên dùng giá trị đã lưu trong lịch sử để khớp với trang Lịch Sử
                    $fuelStored = (float)($r['dau_tinh_toan_lit'] ?? 0);
                    $fuel = $fuelStored > 0 ? $fuelStored : (($skh * $kkh) + ($sch * $kl * $kch));
                    
                    // Hiển thị số chuyến chỉ 1 lần cho mỗi nhóm chuyến
                    $soChuyenDisplay = '';
                    if ($tripCode !== '') {
                        if (!isset($displayedTripsInTinhDau[$tripCode])) {
                            // Hiển thị đúng số chuyến gốc khi gặp lần đầu trong tháng
                            $soChuyenDisplay = $tripCode;
                            $displayedTripsInTinhDau[$tripCode] = true;
                        }
                    }
                    
                    $route = '';
                    $diemDi = trim((string)($r['diem_di'] ?? ''));
                    $diemDen = trim((string)($r['diem_den'] ?? ''));
                    $diemDuKien = trim((string)($r['diem_du_kien'] ?? ''));
                    $isDoiLenh = ($r['doi_lenh'] ?? '0') == '1';

                    if ($diemDi !== '' || $diemDen !== '') {
                        if ($isDoiLenh) {
                            $route = $diemDi . ' → ' . $diemDuKien . ' (đổi lệnh) → ' . $diemDen;
                        } else {
                            $route = $diemDi . ' → ' . $diemDen;
                        }
                    }
                    $dateVN = format_date_vn((string)($r['ngay_di'] ?? ''));
                    $totalKm = $sch + $skh;
                    $loaiHang = (string)($r['loai_hang'] ?? '');
                }

                // Làm tròn dầu hiển thị đến hàng đơn vị (khớp trang Lịch sử)
                $fuelDisplay = (int)round($fuel);

                echo '<Row>';
                echo '<Cell ss:StyleID="Center"><Data ss:Type="Number">' . $stt . '</Data></Cell>';
                echo '<Cell ss:StyleID="Center"><Data ss:Type="String">' . htmlspecialchars($soChuyenDisplay, ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
                echo '<Cell ss:StyleID="Right2"><Data ss:Type="Number">' . htmlspecialchars(number_format($kl, 2, '.', ''), ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
                echo '<Cell ss:StyleID="Left"><Data ss:Type="String">' . htmlspecialchars($loaiHang, ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
                echo '<Cell ss:StyleID="Left"><Data ss:Type="String">' . htmlspecialchars($route, ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
                echo '<Cell ss:StyleID="Date"><Data ss:Type="String">' . htmlspecialchars($dateVN, ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
                echo '<Cell ss:StyleID="Right0"><Data ss:Type="Number">' . (int)$totalKm . '</Data></Cell>';
                echo '<Cell ss:StyleID="Right0"><Data ss:Type="Number">' . $fuelDisplay . '</Data></Cell>';
                echo '<Cell ss:StyleID="Left"><Data ss:Type="String">' . htmlspecialchars((string)($r['ghi_chu'] ?? ''), ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
                echo '</Row>';
                $stt++;

                // Cộng dồn tổng
                $sumKmDisplay += (int)$totalKm;
                $sumFuelDisplay += $fuelDisplay;

                // Đếm số chuyến duy nhất trong tháng
                if (!$isCapThem && $tripCode !== '' && !isset($uniqueTrips[$tripCode])) {
                    $uniqueTrips[$tripCode] = true;
                }
            }

            // Dòng Tổng cộng: hiển thị cuối bảng dữ liệu chi tiết
            $soChuyenTrongThang = count($displayedTripsInTinhDau); // Đếm số chuyến duy nhất đã hiển thị
            echo '<Row>'
                . '<Cell ss:StyleID="SubtotalPlain"><Data ss:Type="String"></Data></Cell>'
                . '<Cell ss:StyleID="SubtotalPlain"><Data ss:Type="String">' . htmlspecialchars((string)$soChuyenTrongThang, ENT_QUOTES, 'UTF-8') . '</Data></Cell>'
                . '<Cell ss:StyleID="SubtotalPlain"><Data ss:Type="String"></Data></Cell>'
                . '<Cell ss:StyleID="SubtotalPlain"><Data ss:Type="String">Tổng cộng:</Data></Cell>'
                . '<Cell ss:StyleID="SubtotalPlain"><Data ss:Type="String"></Data></Cell>'
                . '<Cell ss:StyleID="SubtotalPlain"><Data ss:Type="String"></Data></Cell>'
                . '<Cell ss:StyleID="SubtotalPlain"><Data ss:Type="Number">' . (int)$sumKmDisplay . '</Data></Cell>'
                . '<Cell ss:StyleID="SubtotalPlain"><Data ss:Type="Number">' . (int)$sumFuelDisplay . '</Data></Cell>'
                . '<Cell ss:StyleID="SubtotalPlain"><Data ss:Type="String"></Data></Cell>'
                . '</Row>';

            // Hai dòng "Nợ tại" và "Nhận dầu tại" (nằm ngoài bảng, theo bố cục mô tả)
            // Cột KLVC: hiển thị nhãn; Cột PHƯƠNG TIỆN CHẠY: "Bảng tính ngày"/tên cây xăng; Cột NGÀY ĐI: ngày; Cột CỰ LY: số liệu
            $thangNamLabel = 'THÁNG ' . $currentMonth . ' NĂM ' . $currentYear;
            // Ngày đại diện: đầu tháng cho "Bảng tính ngày"
            $ngayBangTinh = format_date_vn($monthStartIso);
            // Sắp xếp danh sách nhận dầu theo ngày tăng dần để hiển thị
            usort($receiptEntries, function($a, $b){ return strcmp($a['date'], $b['date']); });

            // Override thủ công cho dòng "Nợ tại" nếu có tham số truyền vào (ưu tiên theo tên tàu hiện tại)
            $notaiDateOverrideVN = '';
            $notaiAmountOverride = '';
            if (!empty($_GET['notai_date']) && is_array($_GET['notai_date'])) {
                $notaiDateOverrideVN = trim((string)($_GET['notai_date'][$ship] ?? ''));
            } elseif (isset($_GET['notai_date'])) {
                $notaiDateOverrideVN = trim((string)$_GET['notai_date']);
            }
            if (!empty($_GET['notai_amount']) && is_array($_GET['notai_amount'])) {
                $notaiAmountOverride = trim((string)($_GET['notai_amount'][$ship] ?? ''));
            } elseif (isset($_GET['notai_amount'])) {
                $notaiAmountOverride = trim((string)$_GET['notai_amount']);
            }
            if ($notaiDateOverrideVN !== '') {
                // Chấp nhận dd/mm/yyyy; nếu không hợp lệ sẽ giữ mặc định $ngayBangTinh
                $parsed = parse_date_vn($notaiDateOverrideVN);
                if ($parsed) { $ngayBangTinh = $notaiDateOverrideVN; }
            }
            if ($notaiAmountOverride !== '') {
                // Hỗ trợ nhập số có dấu phẩy
                $raw = str_replace([',',' '], ['.',''], $notaiAmountOverride);
                if (is_numeric($raw)) { $tonDau = (float)$raw; }
            }

            // Nợ tại (tồn đầu kỳ) - làm tròn số nguyên
            echo '<Row>'
                . '<Cell><Data ss:Type="String"></Data></Cell>'
                . '<Cell><Data ss:Type="String"></Data></Cell>'
                . '<Cell><Data ss:Type="String">Nợ tại</Data></Cell>'
                . '<Cell><Data ss:Type="String">Bảng tính ngày</Data></Cell>'
                . '<Cell><Data ss:Type="String"></Data></Cell>'
                . '<Cell><Data ss:Type="String">' . htmlspecialchars($ngayBangTinh, ENT_QUOTES, 'UTF-8') . '</Data></Cell>'
                . '<Cell ss:StyleID="NBRight2"><Data ss:Type="Number">' . htmlspecialchars(number_format(round($tonDau, 0), 2, '.', ''), ENT_QUOTES, 'UTF-8') . '</Data></Cell>'
                . '<Cell><Data ss:Type="String"></Data></Cell>'
                . '<Cell><Data ss:Type="String"></Data></Cell>'
                . '</Row>';

            // Nhận dầu tại (tổng cấp trong tháng) - hiển thị từng dòng theo thời gian
            if (!empty($receiptEntries)) {
                $isFirst = true;
                foreach ($receiptEntries as $rc) {
                    $cayXang = (string)$rc['label'];
                    $soLuong = (float)$rc['amount'];
                    $dateForStationVN = format_date_vn((string)$rc['date']);
                    echo '<Row>'
                        . '<Cell><Data ss:Type="String"></Data></Cell>'
                        . '<Cell><Data ss:Type="String"></Data></Cell>'
                        . '<Cell><Data ss:Type="String">' . ($isFirst ? 'Nhận dầu tại' : '') . '</Data></Cell>'
                        . '<Cell><Data ss:Type="String">' . htmlspecialchars($cayXang, ENT_QUOTES, 'UTF-8') . '</Data></Cell>'
                        . '<Cell><Data ss:Type="String"></Data></Cell>'
                        . '<Cell><Data ss:Type="String">' . htmlspecialchars($dateForStationVN, ENT_QUOTES, 'UTF-8') . '</Data></Cell>'
                        . '<Cell ss:StyleID="NBRight2"><Data ss:Type="Number">' . htmlspecialchars(number_format($soLuong, 2, '.', ''), ENT_QUOTES, 'UTF-8') . '</Data></Cell>'
                        . '<Cell><Data ss:Type="String"></Data></Cell>'
                        . '<Cell><Data ss:Type="String"></Data></Cell>'
                        . '</Row>';
                    $isFirst = false;
                }
            }

            // Ghi chú chuyển dầu theo format yêu cầu
            // Hiển thị rõ tàu nào chuyển cho tàu nào
            $transferNotes = [];
            foreach ($entries as $entry) {
                $ngay = (string)($entry['ngay'] ?? '');
                if (!$ngay || strcmp($ngay, $monthStartIso) < 0 || strcmp($ngay, $monthEndIso) > 0) continue;
                $typeShow = $entry['loai_hien_thi'] ?? '';
                if ($typeShow !== 'chuyen') continue;
                $other = $entry['transfer']['other_ship'] ?? '';
                $amount = abs((float)($entry['so_luong'] ?? 0));
                if ($amount <= 0 || $other === '') continue;
                
                $dir = $entry['transfer']['dir'] ?? '';
                if ($dir === 'out') {
                    // Lệnh chuyển đi: "HTL-1 chuyển dầu cho HTV-05 ngày 05/09/2025 là 500 lít"
                    $note = $ship . ' chuyển dầu cho ' . $other . ' ngày ' . format_date_vn($ngay) . ' là ' . number_format($amount, 0) . ' lít';
                } else {
                    // Lệnh nhận vào: "HTL-1 nhận dầu từ HTV-05 ngày 05/09/2025 là 500 lít"
                    $note = $ship . ' nhận dầu từ ' . $other . ' ngày ' . format_date_vn($ngay) . ' là ' . number_format($amount, 0) . ' lít';
                }
                $transferNotes[] = $note;
            }
            foreach ($transferNotes as $note) {
                echo '<Row>'
                    . '<Cell><Data ss:Type="String"></Data></Cell>'
                    . '<Cell><Data ss:Type="String"></Data></Cell>'
                    . '<Cell><Data ss:Type="String"></Data></Cell>'
                    . '<Cell ss:StyleID="Body"><Data ss:Type="String">' . htmlspecialchars($note, ENT_QUOTES, 'UTF-8') . '</Data></Cell>'
                    . '<Cell><Data ss:Type="String"></Data></Cell>'
                    . '<Cell><Data ss:Type="String"></Data></Cell>'
                    . '<Cell><Data ss:Type="String"></Data></Cell>'
                    . '<Cell><Data ss:Type="String"></Data></Cell>'
                    . '<Cell><Data ss:Type="String"></Data></Cell>'
                    . '</Row>';
            }

            // Tính tổng nhận trong kỳ để hiển thị cho dòng "Cộng:" (cột G) - làm tròn số nguyên
            $tongNoNhan = round($tonDau, 0) + $tongCapTrongThang;
            echo '<Row>'
                . '<Cell><Data ss:Type="String"></Data></Cell>'
                . '<Cell><Data ss:Type="String"></Data></Cell>'
                . '<Cell><Data ss:Type="String"></Data></Cell>'
                . '<Cell ss:StyleID="BoldCenter"><Data ss:Type="String">Cộng:</Data></Cell>'
                . '<Cell><Data ss:Type="String"></Data></Cell>'
                . '<Cell><Data ss:Type="String"></Data></Cell>'
                . '<Cell ss:StyleID="NBRight2"><Data ss:Type="Number">' . htmlspecialchars(number_format($tongNoNhan, 2, '.', ''), ENT_QUOTES, 'UTF-8') . '</Data></Cell>'
                . '<Cell><Data ss:Type="String"></Data></Cell>'
                . '<Cell><Data ss:Type="String"></Data></Cell>'
                . '</Row>';

            // Dầu tồn trên sà lan đến ngày = Số nhập tay (form xuất) + Cấp thêm - Dầu sử dụng
            // tongNoNhan: tổng ở cột G (Nợ tại + Nhận dầu); sumFuelDisplay: tổng ở cột H (dầu sử dụng)
            // Logic: Số nhập tay là số liệu mới nhất từ báo cáo dầu tồn gần nhất
            $tonCuoi = round(($tongNoNhan - $sumFuelDisplay), 0);

            // Dòng trống, rồi "Dầu tồn trên sà lan đến ngày" ... "Lít" (không khung)
            echo '<Row><Cell ss:MergeAcross="8"><Data ss:Type="String"></Data></Cell></Row>';
            echo '<Row>'
                . '<Cell><Data ss:Type="String"></Data></Cell>'
                . '<Cell><Data ss:Type="String"></Data></Cell>'
                . '<Cell><Data ss:Type="String"></Data></Cell>'
                . '<Cell ss:StyleID="BoldCenter"><Data ss:Type="String">Dầu tồn trên sà lan đến ngày</Data></Cell>'
                . '<Cell ss:StyleID="BoldCenter"><Data ss:Type="String">' . htmlspecialchars(format_date_vn($monthEndIso), ENT_QUOTES, 'UTF-8') . '</Data></Cell>'
                . '<Cell><Data ss:Type="String"></Data></Cell>'
                . '<Cell ss:StyleID="NBRight2"><Data ss:Type="Number">' . htmlspecialchars(number_format($tonCuoi, 2, '.', ''), ENT_QUOTES, 'UTF-8') . '</Data></Cell>'
                . '<Cell ss:StyleID="BoldCenter"><Data ss:Type="String">Lít</Data></Cell>'
                . '<Cell><Data ss:Type="String"></Data></Cell>'
                . '</Row>';
            // Nếu có dầu dùng vệ sinh trong tháng, thêm ghi chú một dòng bên dưới (không viền)
            if ($cleanUseThang > 0) {
                echo '<Row>'
                    . '<Cell><Data ss:Type="String"></Data></Cell>'
                    . '<Cell><Data ss:Type="String"></Data></Cell>'
                    . '<Cell><Data ss:Type="String"></Data></Cell>'
                    . '<Cell><Data ss:Type="String">Trong đó: Dầu dùng vệ sinh khi neo đậu</Data></Cell>'
                    . '<Cell><Data ss:Type="String"></Data></Cell>'
                    . '<Cell><Data ss:Type="String"></Data></Cell>'
                    . '<Cell ss:StyleID="NBRight2"><Data ss:Type="Number">' . htmlspecialchars(number_format($cleanUseThang, 2, '.', ''), ENT_QUOTES, 'UTF-8') . '</Data></Cell>'
                    . '<Cell><Data ss:Type="String"></Data></Cell>'
                    . '<Cell><Data ss:Type="String"></Data></Cell>'
                    . '</Row>';
            }

            echo '</Table></Worksheet>';
        };

        // Sinh sheet cho từng tàu công ty có dữ liệu trong tháng (khi có danh sách chọn)
        if (!empty($shouldGenerateInTinhDau) && $shouldGenerateInTinhDau) {
            $companyShips = array_keys($shipsByPL['cong_ty']);
            sort($companyShips, SORT_NATURAL | SORT_FLAG_CASE);
            foreach ($companyShips as $ship) { $renderInTinhDauForShip($ship, ''); }
        }

        // Sinh sheet cho từng sà lan thuê ngoài (khi có danh sách chọn)
        if (!empty($shouldGenerateInTinhDau) && $shouldGenerateInTinhDau) {
            $rentShips = array_keys($shipsByPL['thue_ngoai']);
            sort($rentShips, SORT_NATURAL | SORT_FLAG_CASE);
            // Gom dữ liệu cho thuê ngoài — dùng cùng cơ chế suy luận ngày như tàu công ty
            $rowsByShipRent = [];
            $prevTripDateByShipRent = [];
            foreach ($sourceRows as $r) {
                $ship = trim((string)($r['ten_phuong_tien'] ?? ''));
                if ($ship === '') continue;
                $shipClean = trim($ship, '"');
                $pl = $plMap[$shipClean] ?? 'cong_ty';
                if ($pl !== 'thue_ngoai') continue;

                $isCapThem = (int)($r['cap_them'] ?? 0) === 1;
                $ngayDoXong = (string)($r['ngay_do_xong'] ?? '');
                $ngayDi = (string)($r['ngay_di'] ?? '');
                $createdAt = (string)($r['created_at'] ?? '');
                $createdIso = $createdAt !== '' ? substr($createdAt, 0, 10) : '';

                $inferredIso = '';
                if ($isCapThem) {
                    if ($ngayDi !== '') {
                        $inferredIso = parse_date_vn($ngayDi) ?: '';
                    } elseif (!empty($prevTripDateByShipRent[$ship])) {
                        $inferredIso = $prevTripDateByShipRent[$ship];
                    } elseif ($createdIso !== '') {
                        $inferredIso = $createdIso;
                    }
                } else {
                    $inferredIso = $ngayDoXong !== '' ? (parse_date_vn($ngayDoXong) ?: '') : ($ngayDi !== '' ? (parse_date_vn($ngayDi) ?: '') : '');
                    // Fallback khi thiếu ngày: dùng created_at
                    if ($inferredIso === '' && $createdIso !== '') {
                        $inferredIso = $createdIso;
                    }
                    if ($inferredIso !== '') {
                        $prevTripDateByShipRent[$ship] = $inferredIso;
                    }
                }

                $dayIso = $inferredIso;
                // Đã được lọc sẵn từ $exportRows, không cần lọc thêm
                $r['__inferred_day'] = $dayIso;
                $rowsByShipRent[$ship][] = $r;
            }
            foreach ($rentShips as $ship) {
                // Luôn sinh sheet cho thuê ngoài nếu đã được chọn, kể cả khi không có dòng chuyến trong tháng
                // Tạm ánh xạ $rowsByShip dùng chung để tái sử dụng renderer
                $rowsByShip[$ship] = $rowsByShipRent[$ship] ?? [];
                $renderInTinhDauForShip($ship, 'THUÊ NGOÀI');
                unset($rowsByShip[$ship]);
            }
        }

    }

    if ($ENABLE_DAUTON && !$isDetailedExport) {
        // ===================== DAUTON SHEETS (SLCTY & SLN) =====================
        // Khoảng thời gian theo tháng đã xác định ở trên
        $monthStartIso = date('Y-m-01', strtotime($currentYear . '-' . $currentMonth . '-01'));
        $monthEndIso   = date('Y-m-t',  strtotime($currentYear . '-' . $currentMonth . '-01'));
        // Ngày cuối kỳ trước để tính tồn đầu kỳ
        $prevEndIso    = date('Y-m-d', strtotime($monthStartIso . ' -1 day'));
        $prevEndVN     = format_date_vn($prevEndIso);

        $dauTonModel = new DauTon();

        // Chuẩn bị danh sách tàu theo phân loại để tổng hợp
        $shipsByPL = ['cong_ty' => [], 'thue_ngoai' => []];
        foreach ($exportRows as $r) {
            $ship = trim((string)($r['ten_phuong_tien'] ?? ''));
            if ($ship === '') continue;
            $shipClean = trim($ship, '"');
            $pl = $plMap[$shipClean] ?? 'cong_ty';
            $shipsByPL[$pl][$ship] = true;
        }

        // Tiêu hao cho DAUTON: lấy TRỰC TIẾP từ kết quả BC TH đã tổng hợp
        $usageByShip = $usageByShipBCTH;

        // Helper: lấy tổng dầu cấp (cap_them), tinh_chinh và tiêu hao trong tháng từ trang Quản lý dầu tồn
        $capByShip = [];
        $adjByShip = [];
        $capDetailsByShip = []; // Chi tiết cấp thêm: cây xăng, ngày, lý do
        // Loại bỏ usageByShipFromMgmt để tránh trùng lặp tính toán tiêu hao
        $allShips = array_unique(array_merge(array_keys($shipsByPL['cong_ty']), array_keys($shipsByPL['thue_ngoai'])));
        sort($allShips, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($allShips as $ship) {
            $entries = $dauTonModel->getNhatKyHienThi($ship);
            foreach ($entries as $entry) {
                $ngay = $entry['ngay'] ?? '';
                if (!$ngay || strcmp($ngay, $monthStartIso) < 0 || strcmp($ngay, $monthEndIso) > 0) continue;
                
                $val = (float)($entry['so_luong'] ?? 0);
                $loai = $entry['loai'] ?? '';
                
                if ($loai === 'cap_them') {
                    if (!isset($capByShip[$ship])) $capByShip[$ship] = 0.0;
                    $capByShip[$ship] += $val;
                    
                    // Lưu chi tiết cấp thêm
                    if (!isset($capDetailsByShip[$ship])) $capDetailsByShip[$ship] = [];
                    $capDetailsByShip[$ship][] = [
                        'ngay' => $entry['ngay_vn'] ?? format_date_vn($ngay),
                        'so_luong' => $val,
                        'cay_xang' => $entry['cay_xang'] ?? '',
                        'ly_do' => $entry['ly_do'] ?? ''
                    ];
                } elseif ($loai === 'tinh_chinh') {
                    if (!isset($adjByShip[$ship])) $adjByShip[$ship] = 0.0;
                    $adjByShip[$ship] += $val; // có thể âm hoặc dương
                }
                // Loại bỏ tính toán tiêu hao ở đây để tránh trùng lặp với usageByShip
            }
        }

    // Hàm render một sheet DAUTON theo phân loại
        $renderDauTonSheet = function(string $phanLoaiSuffix, array $shipSet) use ($currentMonth,$currentYear,$monthStartIso,$monthEndIso,$prevEndVN,$dauTonModel,$usageByShip,$capByShip,$adjByShip,$capDetailsByShip,$fmt2,$agg) {
        $sheetName = 'DAUTON-' . $phanLoaiSuffix;
        echo '<Worksheet ss:Name="' . htmlspecialchars($sheetName, ENT_QUOTES, 'UTF-8') . '"><Table>';
        $colW = [35,140,120,120,140,140,140,140,280];
        foreach ($colW as $w) { echo '<Column ss:Width="' . (float)$w . '"/>'; }

        // HEADER TEMPLATE (dòng 1-5)
        printSheetHeaderTemplate(9);

        $title1 = 'BÁO CÁO NHIÊN LIỆU SỬ DỤNG VÀ TỒN KHO';
        $title2 = 'THÁNG ' . $currentMonth . ' NĂM ' . $currentYear;
        echo '<Row><Cell ss:MergeAcross="8" ss:StyleID="Title"><Data ss:Type="String">' . htmlspecialchars($title1, ENT_QUOTES, 'UTF-8') . '</Data></Cell></Row>';
        echo '<Row><Cell ss:MergeAcross="8" ss:StyleID="Title"><Data ss:Type="String">' . htmlspecialchars($title2, ENT_QUOTES, 'UTF-8') . '</Data></Cell></Row>';
        echo '<Row><Cell ss:MergeAcross="8" ss:StyleID="Body"><Data ss:Type="String"></Data></Cell></Row>';

        $headers = ['STT','PHƯƠNG TIỆN','DẦU TỒN ĐẦU KỲ','DẦU CẤP','DẦU SỬ DỤNG KHÔNG HÀNG','DẦU SỬ DỤNG CÓ HÀNG','TỔNG DẦU SỬ DỤNG','DẦU TỒN CUỐI KỲ','GHI CHÚ'];
        echo '<Row>'; foreach ($headers as $h) { echo '<Cell ss:StyleID="Header"><Data ss:Type="String">' . htmlspecialchars($h, ENT_QUOTES, 'UTF-8') . '</Data></Cell>'; } echo '</Row>';
        // Dòng tháng năm trước STT đầu
        echo '<Row><Cell ss:StyleID="Center"><Data ss:Type="String"></Data></Cell><Cell ss:StyleID="Center"><Data ss:Type="String">THÁNG ' . htmlspecialchars((string)$currentMonth, ENT_QUOTES, 'UTF-8') . ' NĂM ' . htmlspecialchars((string)$currentYear, ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
        for ($i=0;$i<7;$i++){ echo '<Cell ss:StyleID="Center"><Data ss:Type="String"></Data></Cell>'; } echo '</Row>';

        $stt = 1;
        $gt = ['ton_dau'=>0,'cap'=>0,'use_no'=>0,'use_yes'=>0,'use_total'=>0,'ton_cuoi'=>0];
        ksort($shipSet, SORT_NATURAL | SORT_FLAG_CASE);
        foreach (array_keys($shipSet) as $ship) {
            // Tồn đầu kỳ: LẤY SỐ DƯ TẠI NGÀY CUỐI CÙNG CỦA THÁNG TRƯỚC (prevEndIso)
            // Không phụ thuộc vào thời điểm tiêu hao đầu tiên trong tháng hiện tại.
            $entriesForShip = $dauTonModel->getNhatKyHienThi($ship);
            $prevEndIsoLocal = date('Y-m-d', strtotime($monthStartIso . ' -1 day'));
            // Tính tồn đầu kỳ theo quy tắc mới:
            // - Lấy số dư đến prevEndIso (cuối tháng trước)
            // - Phần CẤP THÊM trong tháng hiện tại sẽ được tính ở cột "DẦU CẤP"
            $baseSoDu = (float)$dauTonModel->tinhSoDu($ship, $prevEndIsoLocal);
            $capBefore = 0.0; $adjBefore = 0.0; $capInMonth = 0.0; $adjInMonth = 0.0;
            foreach ($entriesForShip as $entry) {
                $ngay = (string)($entry['ngay'] ?? '');
                if ($ngay === '' || strcmp($ngay, $monthStartIso) < 0 || strcmp($ngay, $monthEndIso) > 0) continue;
                $loai = (string)($entry['loai'] ?? '');
                $val  = (float)($entry['so_luong'] ?? 0);
                if ($loai === 'cap_them') {
                    $capInMonth += $val;
                    // cutoffDate = prevEndIsoLocal < monthStartIso, nên nhánh này luôn không kích hoạt trong phạm vi tháng hiện tại
                    if (strcmp($ngay, $prevEndIsoLocal) <= 0) { $capBefore += $val; }
                } elseif ($loai === 'tinh_chinh') {
                    $adjInMonth += $val;
                    if (strcmp($ngay, $prevEndIsoLocal) <= 0) { $adjBefore += $val; }
                }
            }
            $tonDau = round($baseSoDu - $capBefore, 0);
            // Dầu cấp trong tháng: tính toàn bộ CẤP THÊM trong tháng (đã loại trừ phần gộp vào tồn đầu kỳ bằng bước trên)
            $cap  = round($capInMonth, 0);
            $adj  = round((float)($adjByShip[$ship] ?? 0), 0);
            // Tính dầu vệ sinh (chà rửa) trong tháng từ trang Quản lý dầu tồn
            $cleanUse = 0.0;
            $entries = $entriesForShip; // đã lấy ở trên, tái sử dụng
            foreach ($entries as $entry) {
                $ngay = (string)($entry['ngay'] ?? '');
                if (!$ngay || strcmp($ngay, $monthStartIso) < 0 || strcmp($ngay, $monthEndIso) > 0) continue;
                $loai = strtolower((string)($entry['loai'] ?? ''));
                $lyDo = strtolower((string)($entry['ly_do'] ?? ''));
                // Kiểm tra cả loại và lý do để xác định dầu vệ sinh
                if (in_array($loai, ['ve_sinh','cha_rua','ve sinh','cha rua'], true) || 
                    strpos($lyDo, 'vệ sinh') !== false || strpos($lyDo, 'chà rửa') !== false) {
                    $cleanUse += abs((float)($entry['so_luong'] ?? 0));
                }
            }
            
            // Tiêu hao trong tháng: sử dụng dữ liệu từ BC TH đã tính toán
            $useNo  = round((float)($usageByShip[$ship]['fuel_no']  ?? 0), 0);
            $useYes = round((float)($usageByShip[$ship]['fuel_yes'] ?? 0), 0);
            
            // Debug: nếu không có dữ liệu tiêu hao, thử từ aggregated data
            if ($useNo == 0 && $useYes == 0) {
                // Fallback: tính từ aggregated data nếu có
                if (isset($agg[$ship])) {
                    $useNo = round((float)($agg[$ship]['fuel_no'] ?? 0), 0);
                    $useYes = round((float)($agg[$ship]['fuel_yes'] ?? 0), 0);
                }
            }
            $cleanUse = round($cleanUse, 0);
            $useTotal = $useNo + $useYes + $cleanUse; // giữ bố cục: không thêm cột mới
            
            // Phần tinh chỉnh sau cutoffDate ảnh hưởng đến tồn cuối kỳ
            $adjAfter = $adjInMonth - $adjBefore;
            $tonCuoi = $tonDau + $cap + round($adjAfter, 0) - $useTotal;
            // Ghi chú: liệt kê chi tiết các lệnh chuyển dầu (không gộp thành "TC:")
            $transferLines = [];
            foreach ($entriesForShip as $entry) {
                $ngay = (string)($entry['ngay'] ?? '');
                if ($ngay === '' || strcmp($ngay, $monthStartIso) < 0 || strcmp($ngay, $monthEndIso) > 0) continue;
                $typeShow = (string)($entry['loai_hien_thi'] ?? '');
                if ($typeShow !== 'chuyen') continue;
                $dir = (string)($entry['transfer']['dir'] ?? '');
                $other = (string)($entry['transfer']['other_ship'] ?? '');
                $lit = abs((float)($entry['so_luong'] ?? 0));
                if ($other === '' || $lit <= 0) continue;
                $dateVN = format_date_vn($ngay);
                if ($dir === 'out') {
                    $transferLines[] = '• ' . $dateVN . ': chuyển cho ' . $other . ' ' . number_format($lit, 0) . 'L';
                } else {
                    $transferLines[] = '• ' . $dateVN . ': nhận từ ' . $other . ' ' . number_format($lit, 0) . 'L';
                }
            }
            $ghiChu = '';
            if (!empty($transferLines)) {
                $ghiChu = "Chuyển dầu:\n" . implode("\n", $transferLines);
            }
            if ($cleanUse > 0) { $ghiChu = trim($ghiChu . (($ghiChu!=='') ? "\n" : '') . 'VT: ' . $fmt2($cleanUse) . 'L'); }
            
            // Thêm chi tiết cấp thêm từ cây xăng
            if (!empty($capDetailsByShip[$ship])) {
                // Hiển thị đầy đủ thông tin, không rút gọn
                $capDetailsStr = 'Lấy dầu:';
                foreach ($capDetailsByShip[$ship] as $detail) {
                    $ngay = $detail['ngay']; // Hiển thị đầy đủ ngày
                    $soLuong = number_format($detail['so_luong'], 0);
                    $cayXang = $detail['cay_xang'];
                    
                    $capDetailsStr .= "\n• {$ngay}: {$soLuong}L";
                    if (!empty($cayXang)) {
                        $capDetailsStr .= " ({$cayXang})"; // Hiển thị đầy đủ tên cây xăng
                    }
                }
                $ghiChu = trim($ghiChu . (($ghiChu!=='') ? "\n" : '') . $capDetailsStr);
            }

            echo '<Row>';
            echo '<Cell ss:StyleID="Center"><Data ss:Type="Number">' . $stt . '</Data></Cell>';
            echo '<Cell ss:StyleID="Left"><Data ss:Type="String">' . htmlspecialchars($ship, ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
            echo '<Cell ss:StyleID="Right0"><Data ss:Type="Number">' . (int)$tonDau . '</Data></Cell>';
            echo '<Cell ss:StyleID="Right0"><Data ss:Type="Number">' . (int)$cap . '</Data></Cell>';
            echo '<Cell ss:StyleID="Right0"><Data ss:Type="Number">' . (int)$useNo . '</Data></Cell>';
            echo '<Cell ss:StyleID="Right0"><Data ss:Type="Number">' . (int)$useYes . '</Data></Cell>';
            echo '<Cell ss:StyleID="Right0"><Data ss:Type="Number">' . (int)$useTotal . '</Data></Cell>';
            echo '<Cell ss:StyleID="Right0"><Data ss:Type="Number">' . (int)$tonCuoi . '</Data></Cell>';
            // Hiển thị ghi chú với xuống dòng đẹp trong Excel: chuyển \n thành &#10;
            $ghiChuXml = htmlspecialchars($ghiChu, ENT_QUOTES, 'UTF-8');
            $ghiChuXml = str_replace(["\r\n", "\n", "\r"], '&#10;', $ghiChuXml);
            echo '<Cell ss:StyleID="LeftWrap"><Data ss:Type="String">' . $ghiChuXml . '</Data></Cell>';
            echo '</Row>';

            $stt++;
            $gt['ton_dau']  += $tonDau;
            $gt['cap']      += $cap;
            $gt['use_no']   += $useNo;
            $gt['use_yes']  += $useYes;
            $gt['use_total']+= $useTotal;
            $gt['ton_cuoi'] += $tonCuoi;
        }

        // Subtotal cuối bảng (đậm, không highlight)
        echo '<Row>';
        echo '<Cell ss:StyleID="SubtotalPlain"><Data ss:Type="String"></Data></Cell>';
        echo '<Cell ss:StyleID="SubtotalPlain"><Data ss:Type="String">Tổng</Data></Cell>';
        echo '<Cell ss:StyleID="SubtotalPlain"><Data ss:Type="Number">' . htmlspecialchars((string)$fmt2($gt['ton_dau']), ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
        echo '<Cell ss:StyleID="SubtotalPlain"><Data ss:Type="Number">' . htmlspecialchars((string)$fmt2($gt['cap']), ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
        echo '<Cell ss:StyleID="SubtotalPlain"><Data ss:Type="Number">' . htmlspecialchars((string)$fmt2($gt['use_no']), ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
        echo '<Cell ss:StyleID="SubtotalPlain"><Data ss:Type="Number">' . htmlspecialchars((string)$fmt2($gt['use_yes']), ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
        echo '<Cell ss:StyleID="SubtotalPlain"><Data ss:Type="Number">' . htmlspecialchars((string)$fmt2($gt['use_total']), ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
        echo '<Cell ss:StyleID="SubtotalPlain"><Data ss:Type="Number">' . htmlspecialchars((string)$fmt2($gt['ton_cuoi']), ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
        echo '<Cell ss:StyleID="SubtotalPlain"><Data ss:Type="String"></Data></Cell>';
        echo '</Row>';

        echo '</Table></Worksheet>';
    };


        // Render 2 sheets theo phân loại với bảo đảm đóng Workbook
        try {
            // Xuất 2 sheet DAUTON theo phân loại
            $renderDauTonSheet('SLCTY', $shipsByPL['cong_ty']);
            $renderDauTonSheet('SLN',   $shipsByPL['thue_ngoai']);
            // Đã bỏ sheet đối chiếu theo yêu cầu
        } catch (Throwable $e) {
            // Bỏ qua lỗi để không làm hỏng cấu trúc XML
        }
    }

    } catch (Throwable $e) {
        // Nuốt lỗi để vẫn đóng Workbook đúng chuẩn
    } finally {
    echo '</Workbook>';
    exit;
    }
}

include 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card" id="historyFilterCard">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-database me-2"></i>Lịch Sử Các Chuyến Đã Lưu</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-2">
                    <div class="col-md-3">
                        <select class="form-select" name="ten_phuong_tien">
                            <option value="">Tất cả tàu</option>
                            <?php foreach ($shipOptions as $opt): ?>
                                <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo ($q['ten_phuong_tien'] === $opt ? 'selected' : ''); ?>><?php echo htmlspecialchars(formatTau($opt)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="so_chuyen">
                            <option value="">Tất cả chuyến</option>
                            <?php foreach ($tripOptions as $opt): ?>
                                <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo ($q['so_chuyen'] === $opt ? 'selected' : ''); ?>><?php echo htmlspecialchars($opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="diem_di">
                            <option value="">Tất cả điểm đi</option>
                            <?php foreach ($diemDiOptions as $opt): ?>
                                <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo ($q['diem_di'] === $opt ? 'selected' : ''); ?>><?php echo htmlspecialchars($opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="diem_den">
                            <option value="">Tất cả điểm đến</option>
                            <?php foreach ($diemDenOptions as $opt): ?>
                                <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo ($q['diem_den'] === $opt ? 'selected' : ''); ?>><?php echo htmlspecialchars($opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="loai_hang">
                            <option value="">Tất cả loại hàng</option>
                            <?php foreach ($loaiHangOptions as $opt): ?>
                                <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo ($q['loai_hang'] === $opt ? 'selected' : ''); ?>><?php echo htmlspecialchars($opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Chọn tháng</label>
                        <button class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-between" type="button" id="monthPickerBtn">
                            <span id="monthPickerLabel">--/----</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div id="monthPickerPanel" class="border rounded p-3 mt-2 bg-white shadow-sm" style="min-width:320px; display:none;">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <button type="button" class="btn btn-sm btn-light" id="monthPickerPrevYear"><i class="fas fa-chevron-left"></i></button>
                                <div class="fw-semibold" id="monthPickerYear"></div>
                                <button type="button" class="btn btn-sm btn-light" id="monthPickerNextYear"><i class="fas fa-chevron-right"></i></button>
                            </div>
                            <div class="row row-cols-4 g-2" id="monthPickerGrid">
                                <?php
                                $thangLabels = ['Th1','Th2','Th3','Th4','Th5','Th6','Th7','Th8','Th9','Th10','Th11','Th12'];
                                for ($i=1; $i<=12; $i++): ?>
                                    <div class="col">
                                        <button type="button" class="btn btn-outline-primary w-100 month-item" data-month="<?php echo $i; ?>"><?php echo $thangLabels[$i-1]; ?></button>
                                    </div>
                                <?php endfor; ?>
                            </div>
                            <div class="mt-3 d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-secondary" id="monthPickerClear">Xóa</button>
                                <button type="button" class="btn btn-sm btn-primary ms-auto" id="monthPickerApply">Áp dụng</button>
                            </div>
                        </div>
                        <input type="hidden" id="filter_month" name="thang" value="<?php 
                            // Ưu tiên tham số thang từ URL, nếu không có thì dùng từ tu_ngay
                            $thangValue = $q['thang'] ?: '';
                            if (!$thangValue && $q['tu_ngay']) {
                                $parsedDate = parse_date_vn($q['tu_ngay']);
                                if ($parsedDate) {
                                    $thangValue = date('Y-m', strtotime($parsedDate));
                                }
                            }
                            echo htmlspecialchars($thangValue);
                        ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-0">Từ ngày</label>
                        <input type="text" inputmode="numeric" pattern="\d{1,2}/\d{1,2}/\d{4}" class="form-control" name="tu_ngay" placeholder="dd/mm/yyyy" value="<?php $__tmp=parse_date_vn($q['tu_ngay']); echo htmlspecialchars($__tmp ? format_date_vn($__tmp) : $q['tu_ngay']); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-0">Đến ngày</label>
                        <input type="text" inputmode="numeric" pattern="\d{1,2}/\d{1,2}/\d{4}" class="form-control" name="den_ngay" placeholder="dd/mm/yyyy" value="<?php $__tmp2=parse_date_vn($q['den_ngay']); echo htmlspecialchars($__tmp2 ? format_date_vn($__tmp2) : $q['den_ngay']); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-0">Loại bản ghi</label>
                        <select class="form-select" name="loai">
                            <option value="" <?php echo $q['loai']===''?'selected':''; ?>>Tất cả</option>
                            <option value="chuyen" <?php echo $q['loai']==='chuyen'?'selected':''; ?>>Chuyến</option>
                            <option value="cap_them" <?php echo $q['loai']==='cap_them'?'selected':''; ?>>Cấp thêm</option>
                            <option value="chuyen_dau" <?php echo $q['loai']==='chuyen_dau'?'selected':''; ?>>Chuyển dầu</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-grid">
                        <label class="form-label invisible">.</label>
                        <button type="submit" class="btn btn-primary" name="filter" value="1"><i class="fas fa-filter me-2"></i>Lọc</button>
                    </div>
                    <div class="col-md-3 d-grid">
                        <label class="form-label invisible">.</label>
                        <button type="button" class="btn btn-success" id="exportExcelBtn"><i class="fas fa-file-excel me-2"></i>Xuất Excel</button>
                    </div>
                </form>

                <!-- Overlay chọn tàu xuất chi tiết (themed, Bootstrap-like) -->
                <style>
                #extraShipsOverlay{position:fixed;inset:0;background:rgba(0,0,0,.85);display:none;z-index:2000;}
                #extraShipsPanel{position:absolute;left:50%;top:10%;transform:translateX(-50%);width:min(680px, 94vw);}
                #extraShipsPanel .card{box-shadow:0 8px 28px rgba(0,0,0,.28);max-height:90vh;display:flex;flex-direction:column;}
                #extraShipsPanel .card-body{overflow:auto;max-height:calc(90vh - 56px);} /* 56px ~ header */
                #extraShipsPanel .bd{max-height:60vh;overflow:auto;}
                #extraShipsPanel .detail-actions{position:sticky;bottom:0;background:#fff;padding-top:8px;}
                body.no-scroll{overflow:hidden}
                </style>
                <div id="extraShipsOverlay" aria-hidden="true">
                    <div id="extraShipsPanel">
                        <div class="card">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <div class="fw-semibold"><i class="fas fa-file-excel me-2"></i>Xuất Excel</div>
                                <button type="button" class="btn btn-sm btn-light" id="extraShipsClose" title="Đóng"><i class="fas fa-times"></i></button>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="alert alert-info mb-0">
                                        <div class="d-flex align-items-start">
                                            <i class="fas fa-question-circle me-2 mt-1"></i>
                                            <div>
                                                <div class="fw-semibold">Bạn muốn xuất loại báo cáo nào?</div>
                                                <small class="text-muted">Chọn "Xuất mặc định" để chỉ xuất các sheet tổng hợp (BCTHANG-SLCTY, BCTHANG-SLN, BC TH). Chọn "Xuất chi tiết" để chỉ xuất báo cáo chi tiết cho từng tàu (IN TINH DAU).</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php
                                    $extraList = array_values(array_filter($shipOptions, function($s){ return $s !== ''; }));
                                ?>
                                <div class="mb-2 d-flex align-items-center gap-2">
                                    <button type="button" class="btn btn-outline-secondary" id="extraShipsDefault"><i class="fas fa-file-export me-2"></i>Xuất mặc định</button>
                                    <button type="button" class="btn btn-primary ms-auto" id="toggleDetailArea"><i class="fas fa-list-check me-2"></i>Xuất chi tiết...</button>
                                </div>
                                <div id="detailArea" class="border rounded p-3 mt-2" style="display:none;">
                                    <?php if (empty($extraList)): ?>
                                        <div class="text-muted">Không có tàu nào trong tháng đã chọn.</div>
                                    <?php else: ?>
                                        <div class="row g-2 align-items-center mb-2">
                                            <div class="col-sm-6">
                                                <input type="text" class="form-control" id="extraShipsSearch" placeholder="Tìm tàu...">
                                            </div>
                                            <div class="col-sm-6 text-sm-end">
                                                <button type="button" class="btn btn-sm btn-light me-2" id="extraShipsSelectAll"><i class="fas fa-check-double me-1"></i>Chọn tất cả</button>
                                                <button type="button" class="btn btn-sm btn-light" id="extraShipsClear"><i class="fas fa-eraser me-1"></i>Bỏ chọn</button>
                                            </div>
                                        </div>
                                        <div class="list-group" id="extraShipsList">
                                            <?php foreach ($extraList as $s): $id = 'exship_' . md5($s); ?>
                                                <label class="list-group-item d-flex align-items-center justify-content-between">
                                                    <div class="form-check">
                                                        <input class="form-check-input me-2 extra-ship-item" type="checkbox" value="<?php echo htmlspecialchars($s); ?>" id="<?php echo $id; ?>">
                                                        <label class="form-check-label" for="<?php echo $id; ?>"><?php echo htmlspecialchars($s); ?></label>
                                                    </div>
                                                    <span class="badge bg-light text-dark ms-2">SL</span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                        <!-- Wizard nhập tuần tự theo từng tàu -->
                                        <div id="detailWizard" class="border rounded p-3 mt-3" style="display:none;">
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fas fa-ship me-2 text-primary"></i>
                                                <div class="fw-semibold">Tàu: <span id="wizardShipName"></span></div>
                                                <div class="ms-auto small text-muted"><span id="wizardProgress"></span></div>
                                            </div>
                                            <div class="row g-2">
                                                <div class="col-md-6">
                                                    <label class="form-label small mb-1">Nợ tại – Bảng tính ngày (dd/mm/yyyy)</label>
                                                    <input type="text" class="form-control vn-date" id="wizardNotaiDate" placeholder="dd/mm/yyyy">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small mb-1">Số dư dầu tồn cuối kỳ trước (Lít)</label>
                                                    <input type="number" step="0.01" class="form-control" id="wizardNotaiAmount" placeholder="vd: 2000">
                                                </div>
                                            </div>
                                            <div class="d-flex justify-content-between mt-3">
                                                <div>
                                                    <button type="button" class="btn btn-outline-secondary" id="wizardBack"><i class="fas fa-arrow-left me-1"></i>Back</button>
                                                </div>
                                                <div class="ms-auto">
                                                    <button type="button" class="btn btn-light me-2" id="wizardSkip">Skip</button>
                                                    <button type="button" class="btn btn-outline-primary me-2" id="wizardApplyAll">Dùng cho tất cả</button>
                                                    <button type="button" class="btn btn-primary" id="wizardNext">Next</button>
                                                    <button type="button" class="btn btn-success d-none" id="wizardDone"><i class="fas fa-check me-1"></i>Done</button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="detail-actions text-end mt-3">
                                            <button type="button" class="btn btn-secondary me-2" id="extraShipsCancel">Hủy</button>
                                            <button type="button" class="btn btn-success" id="extraShipsConfirm"><i class="fas fa-file-export me-2"></i>Xuất</button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Ledger: Nhật ký dầu cho tàu đã chọn -->
<?php 
// Hiển thị nhật ký dầu khi có chọn Tên PT (tàu)
try {
    $shipForLedger = trim((string)($q['ten_phuong_tien'] ?? (isset($_GET['ten_phuong_tien']) ? $_GET['ten_phuong_tien'] : '')));
    if ($shipForLedger !== '') {
        $dauTonForPage = new DauTon();
        $ledgerEntries = $dauTonForPage->getNhatKyHienThi($shipForLedger);
        // Lọc theo khoảng ngày nếu có
        $tu = isset($q['tu_ngay']) ? parse_date_vn($q['tu_ngay']) : '';
        $den = isset($q['den_ngay']) ? parse_date_vn($q['den_ngay']) : '';
        if ($tu || $den) {
            $ledgerEntries = array_values(array_filter($ledgerEntries, function($e) use ($tu,$den){
                $d = (string)($e['ngay'] ?? '');
                if ($tu && strcmp($d, $tu) < 0) return false;
                if ($den && strcmp($d, $den) > 0) return false;
                return true;
            }));
        }
?>
<div class="card mt-4" id="ledgerCard">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-droplet me-2"></i>Nhật ký dầu (<?php echo htmlspecialchars($shipForLedger); ?>)</span>
        <small class="text-muted">Đồng bộ với Quản lý dầu tồn</small>
    </div>
    <div class="card-body">
        <?php if (empty($ledgerEntries)): ?>
            <div class="text-muted">Chưa có dữ liệu</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>Ngày</th>
                        <th>Loại</th>
                        <th class="text-end">Số lượng (Lít)</th>
                        <th>Diễn giải</th>
                        <th>Cây xăng</th>
                        <th class="text-end">Số dư (Lít)</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($ledgerEntries as $e): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($e['ngay_vn'] ?? format_date_vn($e['ngay'] ?? '')); ?></td>
                        <td>
                            <?php if (($e['loai_hien_thi'] ?? $e['loai']) === 'chuyen'): ?>
                                <?php $dir = ($e['transfer']['dir'] ?? '') === 'out' ? 'out' : 'in'; $other = $e['transfer']['other_ship'] ?? ''; ?>
                                <?php if ($dir === 'out'): ?>
                                    <span class="badge bg-dark"><i class="fas fa-right-left me-1"></i>Chuyển dầu → <?php echo htmlspecialchars($other); ?></span>
                                <?php else: ?>
                                    <span class="badge bg-dark"><i class="fas fa-right-left me-1"></i>Nhận dầu ← <?php echo htmlspecialchars($other); ?></span>
                                <?php endif; ?>
                            <?php elseif (($e['loai'] ?? '') === 'cap_them'): ?>
                                <span class="badge bg-success">Lấy dầu</span>
                            <?php elseif (($e['loai'] ?? '') === 'tieu_hao'): ?>
                                <span class="badge bg-warning text-dark">Tiêu hao</span>
                            <?php else: ?>
                                <span class="badge bg-primary">Tinh chỉnh</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end <?php echo (($e['so_luong'] ?? 0) < 0 ? 'text-danger' : 'text-success'); ?>">
                            <?php echo fmt_web_int((float)($e['so_luong'] ?? 0)); ?>
                        </td>
                        <td><?php echo htmlspecialchars($e['ly_do'] ?: ($e['mo_ta'] ?? '')); ?></td>
                        <td>
                            <?php if (($e['loai'] ?? '') === 'cap_them'): ?>
                                <?php echo !empty($e['cay_xang']) ? '<span class="badge bg-info text-dark">' . htmlspecialchars($e['cay_xang']) . '</span>' : '<span class="text-muted">—</span>'; ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end fw-semibold"><?php echo fmt_web_int((float)($e['so_du'] ?? 0)); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php } } catch (Exception $ex) { /* ignore ledger errors on history page */ } ?>

<div class="card" id="historyListCard">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle" id="historyTable" data-month="<?php echo htmlspecialchars($filterYm); ?>">
                <thead>
                    <tr>
                        <th>Tên PT</th>
                        <th>Số chuyến</th>
                        <th>Loại</th>
                        <th>Tuyến</th>
                        <th>Sch</th>
                        <th>Skh</th>
                        
                        <th>KL VC</th>
                        <th>KL LC</th>
                        <th>Dầu tính</th>
                        
                        <th>Ngày đi</th>
                        <th>Ngày đến</th>
                        <th>Ngày dỡ xong</th>
                        <th>Loại hàng</th>
                        <th>Ghi chú</th>
                        <th class="text-end">Thao tác</th>
                    </tr>
                </thead>
                <tbody id="historyBody">
                    <?php 
                        // Logic chuyển dầu đã được xử lý ở phần chính, không cần xử lý thêm ở đây
                        $__transferRows = [];
                    ?>
                    <?php 
                        // Trộn dữ liệu chuyển với dữ liệu chuyến theo ngày để hiển thị đúng vị trí thời gian
                        $__combined = [];
                        // Chuẩn hóa ngày của dòng chuyến
                        // Sắp xếp trip events theo tàu → chuyến → cap_them → idx (KHÔNG theo ngày)
                        $tripEvents = [];
                        foreach ($filtered as $idx => $row) {
                            $d = '';
                            if (!empty($row['ngay_di'])) { $d = parse_date_vn($row['ngay_di']) ?: ''; }
                            if ($d === '' && !empty($row['ngay_den'])) { $d = parse_date_vn($row['ngay_den']) ?: ''; }
                            if ($d === '' && !empty($row['ngay_do_xong'])) { $d = parse_date_vn($row['ngay_do_xong']) ?: ''; }
                            if ($d === '' && !empty($row['created_at'])) { $d = substr((string)$row['created_at'],0,10); }
                            $tripEvents[] = ['type'=>'trip','date'=>$d,'idx'=>$idx];
                        }
                        // Đọc order overrides cho tháng hiện tại (per ship)
                        $rankByShip = [];
                        try {
                            $orderFile = __DIR__ . '/data/order_overrides.json';
                            if (file_exists($orderFile)) {
                                $json = json_decode(@file_get_contents($orderFile), true);
                                if (is_array($json)) {
                                    $ym = $filterYm;
                                    if (!empty($ym) && isset($json[$ym]) && is_array($json[$ym])) {
                                        foreach ($json[$ym] as $shipName => $list) {
                                            if (!is_array($list)) continue;
                                            $rank = [];
                                            $pos = 0;
                                            foreach ($list as $idxVal) { $rank[(int)$idxVal] = $pos++; }
                                            $rankByShip[$shipName] = $rank;
                                        }
                                    }
                                }
                            }
                        } catch (Exception $e) { /* ignore */ }
                        
                        // Sắp xếp trip events theo tàu → chuyến → loại → idx
                        usort($tripEvents, function($a,$b) use ($filtered, $rankByShip) {
                            // 1. Tên tàu
                            $ta = mb_strtolower(trim($filtered[$a['idx']]['ten_phuong_tien'] ?? ''));
                            $tb = mb_strtolower(trim($filtered[$b['idx']]['ten_phuong_tien'] ?? ''));
                            if ($ta !== $tb) return $ta <=> $tb;

                            // 2. Số chuyến (chỉ cho chuyến thường/cấp thêm)
                            $rowA = $filtered[$a['idx']];
                            $rowB = $filtered[$b['idx']];
                            $capThemA = (int)($rowA['cap_them'] ?? 0);
                            $capThemB = (int)($rowB['cap_them'] ?? 0);
                            
                            if ($capThemA !== 2 && $capThemB !== 2) {
                                $tripA = (int)($rowA['so_chuyen'] ?? 0);
                                $tripB = (int)($rowB['so_chuyen'] ?? 0);
                                if ($tripA !== $tripB) return $tripA <=> $tripB;
                                
                                // 3. Cùng chuyến: ưu tiên chuyến thường (0) trước cấp thêm (1)
                                if ($tripA === $tripB && $capThemA !== $capThemB) {
                                    return $capThemA <=> $capThemB; // 0 (chuyến thường) trước 1 (cấp thêm)
                                }
                            }

                            // 4. ___idx (thứ tự trong CSV) - đây là thứ tự đã nhập vào hệ thống
                            $idxA = (float)($rowA['___idx'] ?? 0);
                            $idxB = (float)($rowB['___idx'] ?? 0);
                            if ($idxA !== $idxB) return $idxA <=> $idxB;

                            // 5. Áp dụng thứ tự override cho các dòng không ngày của cùng tàu
                            $aUndated = empty($rowA['ngay_di']) && empty($rowA['ngay_den']) && empty($rowA['ngay_do_xong']);
                            $bUndated = empty($rowB['ngay_di']) && empty($rowB['ngay_den']) && empty($rowB['ngay_do_xong']);
                            if ($aUndated && $bUndated) {
                                $shipKey = trim($rowA['ten_phuong_tien'] ?? '');
                                $ranks = $rankByShip[$shipKey] ?? [];
                                $rankA = $ranks[(int)($rowA['___idx'] ?? 0)] ?? null;
                                $rankB = $ranks[(int)($rowB['___idx'] ?? 0)] ?? null;
                                if ($rankA !== null && $rankB !== null && $rankA !== $rankB) {
                                    return $rankA <=> $rankB;
                                }
                            }
                            
                            return strcmp($a['created_at'] ?? '', $b['created_at'] ?? '');
                        });
                        
                        // Sắp xếp transfer events theo ngày - đã vô hiệu hóa vì logic chính đã xử lý
                        $transferEvents = [];
                        
                        // Sử dụng trực tiếp tripEvents vì logic chính đã merge lệnh chuyển dầu vào $rows
                        $__combined = $tripEvents;
                    ?>
                    <?php if (empty($__combined)): ?>
                    <tr><td colspan="15" class="text-center text-muted">Không có dữ liệu</td></tr>
                    <?php else: ?>
                    <?php foreach ($__combined as $__row): ?>
                    <?php if ($__row['type'] === 'transfer'): $__t = $__transferRows[$__row['t']]; ?>
                    <?php $dash = '<span class="text-muted">—</span>'; ?>
                    <tr class="table-light">
                        <td><?php echo htmlspecialchars($shipListParam); ?></td>
                        <td><?php echo $dash; ?></td>
                        <td>
                            <?php if ($__t['dir'] === 'out'): ?>
                                <span class="badge bg-dark">Chuyển dầu → <?php echo htmlspecialchars($__t['other']); ?></span>
                            <?php else: ?>
                                <span class="badge bg-dark">Nhận dầu ← <?php echo htmlspecialchars($__t['other']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars(td2_format_transfer_label($ship, $__t['other'], $__t['dir'])); ?></td>
                        <td class="text-end"><?php echo $dash; ?></td>
                        <td class="text-end"><?php echo $dash; ?></td>
                        <td class="text-end"><?php echo $dash; ?></td>
                        <td class="text-end"><?php echo $dash; ?></td>
                        <td class="text-end">
                            <div><span class="fw-semibold <?php echo ($__t['amount']<0?'text-danger':'text-success'); ?>"><?php echo fmt_web(abs($__t['amount']), 2); ?></span></div>
                        </td>
                        <td><?php echo htmlspecialchars($__t['date_vn']); ?></td>
                        <td><?php echo $dash; ?></td>
                        <td><?php echo $dash; ?></td>
                        <td><?php echo $dash; ?></td>
                        <td class="small text-muted"><?php echo htmlspecialchars($__t['note']); ?></td>
                        <td></td>
                    </tr>
                    <?php else: $r = $filtered[$__row['idx']]; ?>
                    <?php 
                        $undated = (empty($r['ngay_di']) && empty($r['ngay_den']) && empty($r['ngay_do_xong'])) ? '1' : '0';
                        $rowIdxVal = (float)($r['___idx'] ?? 0);
                    ?>
                    <tr data-ship="<?php echo htmlspecialchars($shipName); ?>" data-idx="<?php echo $rowIdxVal; ?>" data-undated="<?php echo $undated; ?>">
                        <?php 
                            $isCapThem = ((int)$r['cap_them'] === 1); 
                            $isChuyenDau = ((int)$r['cap_them'] === 2);
                            $dash = '<span class="text-muted">—</span>';
                            $shipName = (string)($r['ten_phuong_tien'] ?? '');
                            $shipNameClean = trim($shipName, '"');
                            $shipType = $plMap[$shipNameClean] ?? 'cong_ty';
                        ?>
                        <td>
                            <?php 
                                echo htmlspecialchars(formatTau($shipName));
                            ?>
                            <br>
                            <?php if ($shipType === 'cong_ty'): ?>
                                <span class="badge bg-info">Công ty</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Thuê ngoài</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars((string)($r['so_chuyen'] ?? '')); ?></td>
                        <td>
                            <?php if ($isCapThem): ?>
                                <span class="badge bg-warning text-dark"></span>
                            <?php elseif ($isChuyenDau): ?>
                                <?php if ($r['is_chuyen_out'] ?? false): ?>
                                    <span class="badge bg-dark">Chuyển dầu → <?php echo htmlspecialchars($r['tau_dich'] ?? ''); ?></span>
                                <?php else: ?>
                                    <span class="badge bg-dark">Nhận dầu ← <?php echo htmlspecialchars($r['tau_nguon'] ?? ''); ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge bg-primary">Chuyến</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($isCapThem): ?>
                                <span class="text-muted"></span>
                            <?php elseif ($isChuyenDau): ?>
                                <?php 
                                    $dir = ($r['is_chuyen_out'] ?? false) ? 'out' : 'in';
                                    $other = $dir === 'out' ? ($r['tau_dich'] ?? '') : ($r['tau_nguon'] ?? '');
                                    echo htmlspecialchars(td2_format_transfer_label((string)($r['ten_phuong_tien'] ?? ''), (string)$other, $dir));
                                ?>
                            <?php else: ?>
                                <?php
                                    $diemDi = trim((string)($r['diem_di'] ?? ''));
                                    $diemDen = trim((string)($r['diem_den'] ?? ''));
                                    $diemDuKien = trim((string)($r['diem_du_kien'] ?? ''));
                                    $isDoiLenh = ($r['doi_lenh'] ?? '0') == '1';
                                    
                                    $route = '';
                                    if ($diemDi !== '' || $diemDen !== '') {
                                        if ($isDoiLenh) {
                                            $route = $diemDi . ' → ' . $diemDuKien . ' (đổi lệnh) → ' . $diemDen;
                                        } else {
                                            $route = $diemDi . ' → ' . $diemDen;
                                        }
                                    }
                                    echo htmlspecialchars($route);
                                ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-end"><?php echo ($isCapThem || $isChuyenDau) ? $dash : fmt_web((float)($r['cu_ly_co_hang_km'] ?? 0), 1); ?></td>
                        <td class="text-end"><?php echo ($isCapThem || $isChuyenDau) ? $dash : fmt_web((float)($r['cu_ly_khong_hang_km'] ?? 0), 1); ?></td>
                        
                        <td class="text-end">
                            <?php echo ($isCapThem || $isChuyenDau) ? $dash : fmt_web((float)($r['khoi_luong_van_chuyen_t'] ?? 0), 2); ?>
                        </td>
                        <td class="text-end"><?php echo ($isCapThem || $isChuyenDau) ? $dash : fmt_web((float)($r['khoi_luong_luan_chuyen'] ?? 0), 2); ?></td>
                        <td class="text-end">
                            <?php if ($isCapThem): ?>
                                <div><span class="fw-semibold"><?php echo fmt_web((float)($r['so_luong_cap_them_lit'] ?? 0), 2); ?></span></div>
                                <div class="small text-muted text-end">Lý do: <?php echo htmlspecialchars((string)($r['ly_do_cap_them'] ?? '')); ?></div>
                            <?php elseif ($isChuyenDau): ?>
                                <div><span class="fw-semibold <?php echo (($r['so_luong_chuyen_dau'] ?? 0) < 0 ? 'text-danger' : 'text-success'); ?>"><?php echo fmt_web(abs((float)($r['so_luong_chuyen_dau'] ?? 0)), 2); ?></span></div>
                                <div class="small text-muted text-end"><?php echo htmlspecialchars((string)($r['ly_do_chuyen_dau'] ?? '')); ?></div>
                            <?php else: ?>
                                <?php echo fmt_web((float)($r['dau_tinh_toan_lit'] ?? 0), 2); ?>
                            <?php endif; ?>
                        </td>
                        
                        <td><?php echo ($isCapThem || $isChuyenDau) ? $dash : htmlspecialchars(format_date_vn($r['ngay_di'] ?? '')); ?></td>
                        <td><?php echo ($isCapThem || $isChuyenDau) ? $dash : htmlspecialchars(format_date_vn($r['ngay_den'] ?? '')); ?></td>
                        <td><?php 
                            if ($isChuyenDau) {
                                echo htmlspecialchars(format_date_vn($r['ngay_do_xong'] ?? ''));
                            } else {
                                echo ($isCapThem ? $dash : htmlspecialchars(format_date_vn($r['ngay_do_xong'] ?? '')));
                            }
                        ?></td>
                        <td><?php
                            if ($isCapThem || $isChuyenDau) {
                                echo $dash;
                            } else {
                                $loaiHangDisplay = trim((string)($r['loai_hang'] ?? ''));
                                // Nếu loại hàng là "Không hàng" (không phân biệt chữ hoa/thường) thì để trống
                                if (mb_strtolower($loaiHangDisplay) === 'không hàng') {
                                    echo '';
                                } else {
                                    echo htmlspecialchars($loaiHangDisplay);
                                }
                            }
                        ?></td>
                        <td class="small text-muted"><?php echo htmlspecialchars((string)($r['ghi_chu'] ?? '')); ?></td>
                        <td class="text-end">
                            <?php if ($isChuyenDau): ?>
                            <?php 
                                $transferDir = ($r['is_chuyen_out'] ?? false) ? 'out' : 'in';
                                $srcShip = $transferDir === 'out' ? ($r['ten_phuong_tien'] ?? '') : ($r['tau_nguon'] ?? '');
                                $dstShip = $transferDir === 'out' ? ($r['tau_dich'] ?? '') : ($r['ten_phuong_tien'] ?? '');
                                $transferLiters = abs((float)($r['so_luong_chuyen_dau'] ?? 0));
                                $transferDate = (string)($r['ngay_do_xong'] ?? '');
                            ?>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-primary" title="Sửa lệnh chuyển dầu" data-action="edit-transfer"
                                        data-src="<?php echo htmlspecialchars($srcShip); ?>"
                                        data-dst="<?php echo htmlspecialchars($dstShip); ?>"
                                        data-date="<?php echo htmlspecialchars(format_date_vn($transferDate)); ?>"
                                        data-liters="<?php echo htmlspecialchars(number_format($transferLiters, 3, '.', '')); ?>">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" title="Xóa lệnh chuyển dầu" data-action="delete-transfer"
                                        data-src="<?php echo htmlspecialchars($srcShip); ?>"
                                        data-dst="<?php echo htmlspecialchars($dstShip); ?>"
                                        data-date="<?php echo htmlspecialchars(format_date_vn($transferDate)); ?>"
                                        data-liters="<?php echo htmlspecialchars(number_format($transferLiters, 3, '.', '')); ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            <?php else: ?>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editModal" title="Chỉnh sửa"
                                        data-idx="<?php echo (int)$r['___idx']; ?>"
                                        data-klvc="<?php echo htmlspecialchars(number_format((float)($r['khoi_luong_van_chuyen_t'] ?? 0), 2, '.', '')); ?>"
                                        data-ngaydi="<?php echo htmlspecialchars(format_date_vn($r['ngay_di'] ?? '')); ?>"
                                        data-ngayden="<?php echo htmlspecialchars(format_date_vn($r['ngay_den'] ?? '')); ?>"
                                        data-ngaydx="<?php echo htmlspecialchars(format_date_vn($r['ngay_do_xong'] ?? '')); ?>"
                                        data-loaihang="<?php echo htmlspecialchars($r['loai_hang'] ?? ''); ?>"
                                        data-cap-them="<?php echo $isCapThem ? '1' : '0'; ?>"
                                        data-ly-do-cap-them="<?php echo htmlspecialchars($r['ly_do_cap_them'] ?? ''); ?>"
                                        data-so-luong-cap-them="<?php echo htmlspecialchars(number_format((float)$r['so_luong_cap_them_lit'], 2, '.', '')); ?>"
                                        data-cay-xang-cap-them="">
                                    <i class="fas fa-edit"></i>
                                </button>

                                <button class="btn btn-sm btn-warning" title="Sửa tháng báo cáo" data-action="edit-month"
                                        data-idx="<?php echo (int)$r['___idx']; ?>"
                                        data-ship="<?php echo htmlspecialchars((string)($r['ten_phuong_tien'] ?? '')); ?>"
                                        data-current-month="<?php echo htmlspecialchars((string)($r['thang_bao_cao'] ?? '')); ?>">
                                    <i class="fas fa-calendar-alt"></i>
                                </button>

                                <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteHistoryModal" title="Xóa"
                                        data-idx="<?php echo (int)$r['___idx']; ?>"
                                        data-ship="<?php echo htmlspecialchars((string)($r['ten_phuong_tien'] ?? '')); ?>"
                                        data-trip="<?php echo htmlspecialchars((string)($r['so_chuyen'] ?? '')); ?>">
                                    <i class="fas fa-trash"></i>
                                </button>

                                
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editModalLabel"><i class="fas fa-pen me-2"></i>Chỉnh sửa bản ghi</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST">
        <div class="modal-body">
            <input type="hidden" name="idx" id="edit_idx" value="">
            <input type="hidden" name="act" value="update_klvc">
            <input type="hidden" name="cap_them" id="edit_cap_them" value="0">
            
            <!-- Trường cho chuyến thường -->
            <div id="edit_chuyen_fields">
            <div class="mb-3">
                <label class="form-label">KL VC (tấn)</label>
                <input type="number" step="0.01" min="0" class="form-control" name="khoi_luong_van_chuyen_t" id="edit_klvc">
            </div>
            <div class="mb-3">
                <label class="form-label">Loại hàng</label>
                <select class="form-select" name="loai_hang" id="edit_loai_hang">
                    <option value="">-- Chọn loại hàng --</option>
                    <?php foreach ($loaiHangOptions as $opt): ?>
                        <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            </div>
            
            <!-- Trường cho cấp thêm -->
            <div id="edit_cap_them_fields" style="display: none;">
                <div class="mb-3">
                    <label class="form-label">Lý do cấp thêm</label>
                    <input type="text" class="form-control" name="ly_do_cap_them" id="edit_ly_do_cap_them">
                </div>
                <div class="mb-3">
                    <label class="form-label">Số lượng cấp thêm (Lít)</label>
                    <input type="number" step="0.01" min="0" class="form-control" name="so_luong_cap_them" id="edit_so_luong_cap_them">
                </div>
                <!-- Bỏ trường cây xăng vì dầu được múc từ trong tàu -->
            </div>

            <!-- Ngày cho chuyến thường (3 trường) -->
            <div id="edit_date_fields_chuyen" class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Ngày đi</label>
                    <input type="text" class="form-control vn-date" name="ngay_di" id="edit_ngay_di" placeholder="dd/mm/yyyy">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Ngày đến</label>
                    <input type="text" class="form-control vn-date" name="ngay_den" id="edit_ngay_den" placeholder="dd/mm/yyyy">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Ngày dỡ xong</label>
                    <input type="text" class="form-control vn-date" name="ngay_do_xong" id="edit_ngay_dx" placeholder="dd/mm/yyyy">
                </div>
            </div>

            <!-- Ngày cho cấp thêm (chỉ 1 trường: Ngày cấp) -->
            <div id="edit_date_fields_cap_them" class="mb-3" style="display: none;">
                <label class="form-label">Ngày cấp</label>
                <input type="text" class="form-control vn-date" name="ngay_di" id="edit_ngay_cap" placeholder="dd/mm/yyyy">
            </div>

            <div class="form-text">Để trống ngày nếu chưa xác định.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
          <button type="submit" class="btn btn-primary">Lưu</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteHistoryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-exclamation-triangle text-danger me-2"></i>Xác nhận xóa</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST">
        <div class="modal-body">
          <p>Bạn có chắc chắn muốn xóa bản ghi của <strong id="del_ship"></strong> (chuyến <strong id="del_trip"></strong>)?</p>
          <p class="text-muted mb-0">Hành động này không thể hoàn tác.</p>
          <input type="hidden" name="act" value="delete">
          <input type="hidden" name="idx" id="delete_idx" value="">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
          <button type="submit" class="btn btn-danger"><i class="fas fa-trash me-1"></i>Xóa</button>
        </div>
      </form>
    </div>
  </div>
  </div>

 

<!-- Edit Month Modal -->
<div class="modal fade" id="editMonthModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-calendar-alt me-2"></i>Sửa tháng báo cáo</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="editMonthForm">
        <div class="modal-body">
          <input type="hidden" id="editMonthIdx" value="">
          <div class="mb-2">
            <label class="form-label small mb-1">Chọn tháng</label>
            <input id="editMonthInput" type="month" class="form-control" />
          </div>
          <div class="form-text">Tàu: <strong id="editMonthShip"></strong></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
          <button type="submit" class="btn btn-primary" id="editMonthSave">Lưu</button>
        </div>
      </form>
    </div>
  </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
  // Auto-fill date range when selecting a month
  var monthInput = document.getElementById('filter_month');
  if (monthInput) {
    monthInput.addEventListener('change', function() {
      var val = this.value; // yyyy-mm
      if (!val || !/^\d{4}-\d{2}$/.test(val)) return;
      var parts = val.split('-');
      var y = parseInt(parts[0], 10);
      var m = parseInt(parts[1], 10);
      if (!y || !m) return;
      var first = new Date(y, m - 1, 1);
      var last = new Date(y, m, 0);
      var pad = n => (n < 10 ? '0' + n : '' + n);
      var tu = pad(first.getDate()) + '/' + pad(first.getMonth() + 1) + '/' + first.getFullYear();
      var den = pad(last.getDate()) + '/' + pad(last.getMonth() + 1) + '/' + last.getFullYear();
      var tuEl = document.querySelector('input[name="tu_ngay"]');
      var denEl = document.querySelector('input[name="den_ngay"]');
      if (tuEl) tuEl.value = tu;
      if (denEl) denEl.value = den;
    });
  }
  var modal = document.getElementById('editModal');
  if (!modal) return;
  modal.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    if (!button) return;
    var idx = button.getAttribute('data-idx') || '';
    var klvc = button.getAttribute('data-klvc') || '';
    var ngaydi = button.getAttribute('data-ngaydi') || '';
    var ngayden = button.getAttribute('data-ngayden') || '';
    var ngaydx = button.getAttribute('data-ngaydx') || '';
    var capThem = button.getAttribute('data-cap-them') || '0';
    var loaiHang = button.getAttribute('data-loaihang') || '';
    var lyDoCapThem = button.getAttribute('data-ly-do-cap-them') || '';
    var soLuongCapThem = button.getAttribute('data-so-luong-cap-them') || '';
    var cayXangCapThem = ''; // Không cần cây xăng vì dầu được múc từ trong tàu
    
    // Debug log (can be removed after testing)
    // console.log('DEBUG EDIT MODAL: idx=' + idx + ', klvc=' + klvc + ', ngaydi=' + ngaydi);
    
    // Set basic values
    document.getElementById('edit_idx').value = idx;
    document.getElementById('edit_cap_them').value = capThem;
    document.getElementById('edit_ngay_di').value = ngaydi;
    document.getElementById('edit_ngay_den').value = ngayden;
    document.getElementById('edit_ngay_dx').value = ngaydx;
    
    // Show/hide fields based on type
    var chuyenFields = document.getElementById('edit_chuyen_fields');
    var capThemFields = document.getElementById('edit_cap_them_fields');
    var dateFieldsChuyen = document.getElementById('edit_date_fields_chuyen');
    var dateFieldsCapThem = document.getElementById('edit_date_fields_cap_them');

    if (capThem === '1') {
      // Cấp thêm: ẩn khối lượng/loại hàng, hiện lý do + số lượng
      chuyenFields.style.display = 'none';
      capThemFields.style.display = 'block';
      document.getElementById('edit_ly_do_cap_them').value = lyDoCapThem;
      document.getElementById('edit_so_luong_cap_them').value = soLuongCapThem;

      // Ẩn 3 trường ngày của chuyến, hiện 1 trường "Ngày cấp"
      dateFieldsChuyen.style.display = 'none';
      dateFieldsCapThem.style.display = 'block';
      document.getElementById('edit_ngay_cap').value = ngaydi; // Ngày cấp lưu trong ngay_di
    } else {
      // Chuyến thường: hiện khối lượng/loại hàng, ẩn lý do cấp thêm
      chuyenFields.style.display = 'block';
      capThemFields.style.display = 'none';
      document.getElementById('edit_klvc').value = klvc;
      document.getElementById('edit_loai_hang').value = loaiHang;

      // Hiện 3 trường ngày của chuyến, ẩn trường "Ngày cấp"
      dateFieldsChuyen.style.display = 'flex';
      dateFieldsCapThem.style.display = 'none';
      document.getElementById('edit_ngay_di').value = ngaydi;
      document.getElementById('edit_ngay_den').value = ngayden;
      document.getElementById('edit_ngay_dx').value = ngaydx;
    }
  });
  // Removed prompt-based month edit; replaced with inline panel below
});
</script>

<script>
// Edit month via Bootstrap modal with input[type="month"]
(function(){
  var table = document.getElementById('historyTable');
  var modalEl = document.getElementById('editMonthModal');
  var input = document.getElementById('editMonthInput');
  var btnSave = document.getElementById('editMonthSave');
  var idxInput = document.getElementById('editMonthIdx');
  var shipEl = document.getElementById('editMonthShip');
  var form = document.getElementById('editMonthForm');
  var bsModal = null;

  function ymFromDate(d){ return d.toISOString().slice(0,7); }

  document.body.addEventListener('click', function(e){
    var btn = e.target.closest('[data-action="edit-month"]');
    if (!btn) return;
    e.preventDefault();
    var ymFilter = (table && table.getAttribute('data-month')) || '';
    var ymRow = btn.getAttribute('data-current-month') || '';
    input.value = ymFilter || ymRow || ymFromDate(new Date());
    idxInput.value = btn.getAttribute('data-idx') || '';
    if (shipEl) shipEl.textContent = btn.getAttribute('data-ship') || '';
    if (!bsModal) bsModal = new bootstrap.Modal(modalEl);
    bsModal.show();
  });

  async function handleSubmit(e){
    if (e) e.preventDefault();
    var idx = idxInput.value;
    var val = input.value;
    if (!/^\d{4}-\d{2}$/.test(val)) { alert('Tháng không hợp lệ'); return; }
    var prevHtml = btnSave.innerHTML;
    btnSave.disabled = true;
    btnSave.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang lưu...';
    try {
      var fd = new FormData();
      fd.append('idx', idx);
      fd.append('thang_bao_cao', val);
      var res = await fetch('api/update_thang_bao_cao.php', { method: 'POST', body: fd });
      var j = await res.json();
      if (!j.success) throw new Error(j.error || 'Lỗi không xác định');
      var btn = document.querySelector('[data-action="edit-month"][data-idx="' + CSS.escape(idx) + '"]');
      if (btn) btn.setAttribute('data-current-month', val);
      if (bsModal) bsModal.hide();
      // Auto-reload to apply filters without asking
      location.reload();
    } catch (err) {
      alert('Cập nhật thất bại: ' + err.message);
    } finally {
      btnSave.disabled = false;
      btnSave.innerHTML = prevHtml;
    }
  }

  form.addEventListener('submit', handleSubmit);
  btnSave.addEventListener('click', handleSubmit);
})();
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var delModal = document.getElementById('deleteHistoryModal');
  if (!delModal) return;
  delModal.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    if (!button) return;
    var idx = button.getAttribute('data-idx') || '';
    var ship = button.getAttribute('data-ship') || '';
    var trip = button.getAttribute('data-trip') || '';
    
    // Debug log (can be removed after testing)
    // console.log('DEBUG DELETE MODAL: idx=' + idx + ', ship=' + ship + ', trip=' + trip);
    
    var idxInput = document.getElementById('delete_idx');
    if (idxInput) idxInput.value = idx;
    var shipEl = delModal.querySelector('#del_ship');
    var tripEl = delModal.querySelector('#del_trip');
    if (shipEl) shipEl.textContent = ship;
    if (tripEl) tripEl.textContent = trip;
  });
});

 
</script>

<script>
document.addEventListener('DOMContentLoaded', function(){
  // Drag & drop for undated rows per ship in the selected month
  var table = document.getElementById('historyTable');
  var tbody = document.getElementById('historyBody');
  if (!table || !tbody) return;
  var month = table.getAttribute('data-month') || '';
  if (!/^\d{4}-\d{2}$/.test(month)) return;

  // Only allow drag for rows with data-undated="1"
  var draggingEl = null;
  tbody.querySelectorAll('tr[data-ship][data-undated="1"]').forEach(function(tr){
    tr.setAttribute('draggable', 'true');
    tr.addEventListener('dragstart', function(){ draggingEl = tr; tr.classList.add('table-warning'); });
    tr.addEventListener('dragend', function(){ draggingEl = null; tr.classList.remove('table-warning'); });
    tr.addEventListener('dragover', function(e){ e.preventDefault(); });
    tr.addEventListener('drop', function(e){
      e.preventDefault();
      if (!draggingEl) return;
      if (draggingEl === tr) return;
      if (draggingEl.getAttribute('data-ship') !== tr.getAttribute('data-ship')) return; // only within same ship
      var rect = tr.getBoundingClientRect();
      var before = (e.clientY - rect.top) < rect.height / 2;
      if (before) tr.parentNode.insertBefore(draggingEl, tr);
      else tr.parentNode.insertBefore(draggingEl, tr.nextSibling);
      persistOrder(tr.getAttribute('data-ship'));
    });
  });

  async function persistOrder(ship){
    // Collect order for this ship (only undated rows)
    var ids = [];
    tbody.querySelectorAll('tr[data-ship="' + CSS.escape(ship) + '"][data-undated="1"]').forEach(function(tr){
      var idx = tr.getAttribute('data-idx');
      if (idx) ids.push(idx);
    });
    try {
      var form = new FormData();
      form.append('month', month);
      form.append('ship', ship);
      ids.forEach(function(v){ form.append('order[]', v); });
      var res = await fetch('api/save_order_overrides.php', { method: 'POST', body: form });
      var j = await res.json();
      if (!j.success) throw new Error(j.error || 'Không lưu được thứ tự');
    } catch (err) {
      console.error(err);
      alert('Lưu thứ tự thất bại: ' + err.message);
    }
  }
});
</script>

<?php include 'includes/footer.php'; ?>


