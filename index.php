<?php
// Custom error handler to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        error_log('FATAL ERROR: ' . print_r($error, true));
    }
});

/*
 * Trang chính - Form tính toán nhiên liệu sử dụng cho tàu
 * Cho phép người dùng nhập thông tin tàu, điểm bắt đầu, điểm kết thúc và khối lượng
 */

// Kiểm tra đăng nhập
require_once __DIR__ . '/auth/check_auth.php';

require_once 'includes/helpers.php';
require_once 'config/database.php';
require_once 'models/TinhToanNhienLieu.php';
require_once 'models/LuuKetQua.php';
require_once 'models/TauPhanLoai.php';
require_once 'models/CayXang.php';
require_once 'models/DauTon.php';
require_once 'models/LoaiHang.php';

// Khởi tạo đối tượng tính toán
$tinhToan = new TinhToanNhienLieu();
$luuKetQua = new LuuKetQua();
$tauPhanLoai = new TauPhanLoai();
$cayXang = new CayXang();
$dauTon = new DauTon();

// Lấy danh sách tàu, điểm và cây xăng
$danhSachTau = $tinhToan->getDanhSachTau();
$danhSachDiem = $tinhToan->getDanhSachDiem();
$danhSachCayXang = $cayXang->getAll();
$loaiHangModel = new LoaiHang();
$danhSachLoaiHang = $loaiHangModel->getAll();

// Xử lý form submit
$ketQua = null;
$error = null;
$saved = isset($_GET['saved']) && $_GET['saved'] == '1';
$formData = [
    'ten_tau' => '',
    'so_chuyen' => '',
    'chuyen_moi' => 0,
    'thang_bao_cao' => date('Y-m'),
    'diem_bat_dau' => '',
    'diem_ket_thuc' => '',
    'doi_lenh' => 0,
    'diem_moi' => '',
    'diem_moi_list' => [],
    'khoang_cach_thuc_te' => '',
    'khoi_luong' => '',
    'ngay_di' => '',
    'ngay_den' => '',
    'ngay_do_xong' => '',
    'loai_hang' => '',
    'cap_them' => 0,
    'loai_cap_them' => 'bom_nuoc',
    'dia_diem_cap_them' => '',
    'ly_do_cap_them' => '',
    'ly_do_cap_them_khac' => '',
    'so_luong_cap_them' => '',
    'ghi_chu' => ''
];

// Biến để lưu thông tin chuyến hiện tại và các đoạn
$chuyenHienTai = null;
$cacDoanCuaChuyen = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? 'calculate';
        $tenTau = $_POST['ten_tau'] ?? '';
        $soChuyen = trim($_POST['so_chuyen'] ?? '');
        $chuyenMoi = isset($_POST['chuyen_moi']) ? 1 : 0;
        $thangBaoCao = $_POST['thang_bao_cao'] ?? date('Y-m');
        

        $diemBatDau = trim($_POST['diem_bat_dau'] ?? '');
        $diemKetThuc = trim($_POST['diem_ket_thuc'] ?? '');
        $khoiLuong = floatval($_POST['khoi_luong'] ?? 0);

        // Lấy ghi chú cho các điểm
        $ghiChuDiemBatDau = trim($_POST['ghi_chu_diem_bat_dau'] ?? '');
        $ghiChuDiemKetThuc = trim($_POST['ghi_chu_diem_ket_thuc'] ?? '');
        $ghiChuDiemMoi = trim($_POST['ghi_chu_diem_moi'] ?? '');

        // Ghép ghi chú vào tên điểm nếu có (dùng ngoặc fullwidth để phân biệt)
        if (!empty($ghiChuDiemBatDau)) {
            $diemBatDau .= ' （' . $ghiChuDiemBatDau . '）';
        }
        if (!empty($ghiChuDiemKetThuc)) {
            $diemKetThuc .= ' （' . $ghiChuDiemKetThuc . '）';
        }
        $ngayDiRaw = trim($_POST['ngay_di'] ?? '');
        $ngayDi = parse_date_vn($ngayDiRaw);
        $ngayDen = parse_date_vn($_POST['ngay_den'] ?? '');
        $ngayDoXong = parse_date_vn($_POST['ngay_do_xong'] ?? '');
        $loaiHang = trim($_POST['loai_hang'] ?? '');
        // Fix: Kiểm tra giá trị thay vì chỉ kiểm tra isset (vì hidden input luôn tồn tại)
        $capThem = (isset($_POST['cap_them']) && $_POST['cap_them'] == '1') ? 1 : 0;
        $doiLenh = isset($_POST['doi_lenh']) ? 1 : 0;
        // Hỗ trợ Đổi lệnh đa điểm: nhận mảng điểm mới
        $rawDiemMoi = $_POST['diem_moi'] ?? [];
        if (!is_array($rawDiemMoi)) {
            $rawDiemMoi = [$rawDiemMoi];
        }
        $rawLyDoDiemMoi = $_POST['diem_moi_reason'] ?? [];
        if (!is_array($rawLyDoDiemMoi)) {
            $rawLyDoDiemMoi = [$rawLyDoDiemMoi];
        }
        $structuredDiemMoi = [];
        $dsDiemMoi = [];
        $dsDiemMoiGoc = [];
        $lastPoint = '';
        foreach ($rawDiemMoi as $idx => $value) {
            $point = trim((string)$value);
            if ($point === '') {
                continue;
            }
            $reason = isset($rawLyDoDiemMoi[$idx]) ? trim((string)$rawLyDoDiemMoi[$idx]) : '';
            // Tách lý do nằm trong ngoặc đối với dữ liệu cũ (ví dụ: "Cảng X (Đổi lệnh)")
            if ($reason === '') {
                // Xử lý ngoặc chuẩn ()
                if (preg_match('/^(.*)\(([^()]*)\)\s*$/u', $point, $matches)) {
                    $candidatePoint = trim($matches[1]);
                    $candidateReason = trim($matches[2]);
                    if (mb_stripos($candidateReason, 'đổi lệnh') !== false || mb_stripos($candidateReason, 'lãnh vật tư') !== false) {
                        $point = $candidatePoint;
                        $reason = $candidateReason;
                    }
                }
                // Xử lý ngoặc full-width （）
                if (preg_match('/^(.*)（([^（）]*)）\s*$/u', $point, $matchesFull)) {
                    $candidatePoint = trim($matchesFull[1]);
                    $candidateReason = trim($matchesFull[2]);
                    if (mb_stripos($candidateReason, 'đổi lệnh') !== false || mb_stripos($candidateReason, 'lãnh vật tư') !== false) {
                        $point = $candidatePoint;
                        $reason = $candidateReason;
                    }
                }
            }
            $structuredEntry = [
                'point' => $point,
                'reason' => $reason,
                'note' => ''
            ];
            $displayPoint = $point;
            if ($reason !== '') {
                $displayPoint .= ' (' . $reason . ')';
            }
            $structuredDiemMoi[] = $structuredEntry;
            $dsDiemMoi[] = $displayPoint;
            $lastPoint = $point;
            $dsDiemMoiGoc[] = $point;
        }
        // Ghép ghi chú vào điểm cuối nếu có (dùng ngoặc fullwidth để phân biệt)
        if (!empty($ghiChuDiemMoi) && !empty($dsDiemMoi)) {
            $lastIdx = count($dsDiemMoi) - 1;
            $dsDiemMoi[$lastIdx] .= ' （' . $ghiChuDiemMoi . '）';
            $structuredDiemMoi[$lastIdx]['note'] = $ghiChuDiemMoi;
        }
        // Chuỗi điểm mới để hiển thị/lưu
        $diemMoi = implode(' → ', $dsDiemMoi);
        if (!empty($structuredDiemMoi)) {
            $lastEntry = end($structuredDiemMoi);
            if ($lastEntry && isset($lastEntry['point'])) {
                $lastPoint = $lastEntry['point'];
            }
        }
        if ($lastPoint === '') {
            $lastPoint = $diemKetThuc;
        }
        $khoangCachThucTe = isset($_POST['khoang_cach_thuc_te']) && $_POST['khoang_cach_thuc_te'] !== '' ? floatval($_POST['khoang_cach_thuc_te']) : null;
        // Lấy khoảng cách thủ công (chỉ dùng khi không có tuyến trực tiếp và không đổi lệnh)
        $khoangCachThuCong = isset($_POST['khoang_cach_thu_cong']) && $_POST['khoang_cach_thu_cong'] !== '' ? floatval($_POST['khoang_cach_thu_cong']) : null;

        // Xử lý lý do cấp thêm
        $loaiCapThem = trim($_POST['loai_cap_them'] ?? 'bom_nuoc');
        $diaDiemCapThem = trim($_POST['dia_diem_cap_them'] ?? '');
        $lyDoCapThemKhac = trim($_POST['ly_do_cap_them_khac'] ?? '');
        $soLuongCapThem = floatval($_POST['so_luong_cap_them'] ?? 0);

        // Tạo chuỗi lý do cấp thêm tự động
        $lyDoCapThem = '';
        if ($capThem) {
            if ($loaiCapThem === 'bom_nuoc') {
                $lyDoCapThem = "Dầu ma nơ tại bến " . $diaDiemCapThem . " 01 chuyến";
            } elseif ($loaiCapThem === 'qua_cau') {
                $lyDoCapThem = "Dầu bơm nước qua cầu " . $diaDiemCapThem . " 01 chuyến";
            } else {
                // Loại khác - dùng lý do tự nhập
                $lyDoCapThem = $lyDoCapThemKhac;
            }
            // Thêm số lượng vào cuối lý do nếu có
            if (!empty($lyDoCapThem) && $soLuongCapThem > 0) {
                $lyDoCapThem .= " x " . number_format($soLuongCapThem, 0) . " lít";
            }
        }
        $ghiChu = trim($_POST['ghi_chu'] ?? '');

        // Logic xác định mã chuyến được làm rõ
        if ($action === 'save' || $action === 'calculate') {
            if ($chuyenMoi && !empty($tenTau)) {
                $maChuyenCaoNhat = $luuKetQua->layMaChuyenCaoNhat($tenTau);
                $soChuyen = $maChuyenCaoNhat + 1;
            } elseif (empty($soChuyen) || !is_numeric($soChuyen)) {
                // Nếu không ở chế độ tạo mới và không có mã chuyến hợp lệ, đây là lỗi
                if ($action === 'save') {
                    throw new Exception('Không có mã chuyến hợp lệ để lưu.');
                }
                // Đối với 'calculate', có thể cho qua để chỉ hiển thị tính toán
            }
        }
        


        // Xử lý logic ngày cho cấp thêm: tự động link theo ngày chuyến trước đó
        if ($capThem && !empty($tenTau) && !empty($soChuyen)) {
            $ngayChuyenTruoc = $luuKetQua->layNgayChuyenTruoc($tenTau, (int)$soChuyen);
            if ($ngayChuyenTruoc !== '') {
                // Chuyển đổi từ format VN sang ISO để lưu
                $ngayChuyenTruocIso = parse_date_vn($ngayChuyenTruoc);
                if ($ngayChuyenTruocIso) {
                    $ngayDi = $ngayChuyenTruocIso;
                }
            }
        }

        // Lưu lại dữ liệu form để hiển thị lại sau redirect
        // Reset chuyen_moi về false sau khi lưu để tránh tự động tạo chuyến mới
        $formData = [
            'ten_tau' => $tenTau,
            'so_chuyen' => $soChuyen,
            'chuyen_moi' => 0, // Luôn reset về false sau khi lưu
            'thang_bao_cao' => $thangBaoCao,
            'diem_bat_dau' => $diemBatDau,
            'diem_ket_thuc' => $diemKetThuc,
            'doi_lenh' => $doiLenh,
            'diem_moi' => $diemMoi,
            'diem_moi_list' => $doiLenh ? $structuredDiemMoi : [],
            'khoang_cach_thuc_te' => ($khoangCachThucTe === null ? '' : (string)$khoangCachThucTe),
            'khoi_luong' => ($khoiLuong === 0.0 ? '0' : (string)$khoiLuong),
            'ngay_di' => $ngayDi,
            'ngay_den' => $ngayDen,
            'ngay_do_xong' => $ngayDoXong,
            'loai_hang' => $loaiHang,
            'cap_them' => $capThem,
            'loai_cap_them' => $loaiCapThem,
            'dia_diem_cap_them' => $diaDiemCapThem,
            'ly_do_cap_them' => $lyDoCapThem,
            'ly_do_cap_them_khac' => $lyDoCapThemKhac,
            'so_luong_cap_them' => ($soLuongCapThem === 0.0 ? '' : (string)$soLuongCapThem),  // FIX: Dùng '' thay vì '0' để tránh vi phạm min="0.01"
            'ghi_chu' => $ghiChu
        ];

        // Thực hiện tính toán
        // Biến lưu kết quả cấp thêm (nếu có)
        $ketQuaCapThem = null;

        // Xử lý tính toán dầu cho quảng đường (nếu có đủ thông tin)
        $coThongTinTuyenDuong = !empty($diemBatDau) && !empty($diemKetThuc);

        if ($coThongTinTuyenDuong) {
            // Có thông tin tuyến đường -> tính toán dầu cho quảng đường
            // Tách tên điểm gốc (loại bỏ ghi chú trong ngoặc fullwidth) để tính toán
            $diemBatDauGoc = preg_replace('/\s*（[^）]*）\s*$/', '', $diemBatDau);
            $diemKetThucGoc = preg_replace('/\s*（[^）]*）\s*$/', '', $diemKetThuc);
            // Với đổi lệnh đa điểm, lấy điểm cuối cùng để tính, nhưng hiển thị toàn chuỗi
            if (!empty($dsDiemMoiGoc)) {
                $dsDiemMoiGoc = array_values($dsDiemMoiGoc);
            } else {
                $dsDiemMoiGoc = array_map(function($p){
                    $p = preg_replace('/\s*（[^）]*）\s*$/u', '', (string)$p);
                    $p = preg_replace('/\s*\([^()]*\)\s*$/u', '', (string)$p);
                    return trim($p);
                }, ($dsDiemMoi ?? []));
            }
            $diemMoiGoc = !empty($dsDiemMoiGoc) ? end($dsDiemMoiGoc) : '';

            // Tính toán bình thường sử dụng tên điểm gốc
            if ($doiLenh) {
                if (empty($diemMoiGoc)) {
                    throw new Exception('Vui lòng nhập ít nhất một Điểm đến mới (C, D, ...).');
                }
                $ketQua = $tinhToan->tinhNhienLieuDoiLenh($tenTau, $diemBatDauGoc, $diemKetThucGoc, $diemMoiGoc, $khoiLuong, $khoangCachThucTe ?? 0);
                // Ghi đè hiển thị tuyến để thể hiện đầy đủ các điểm đổi lệnh
                if (is_array($ketQua)) {
                    $routeSegments = [];
                    $routeSegments[] = $diemBatDau;
                    $routeSegments[] = $diemKetThuc;
                    if (!empty($structuredDiemMoi)) {
                        foreach ($structuredDiemMoi as $entry) {
                            $label = $entry['point'];
                            $suffixParts = [];
                            if (!empty($entry['reason'])) {
                                $suffixParts[] = $entry['reason'];
                            }
                            if (!empty($entry['note'])) {
                                $suffixParts[] = $entry['note'];
                            }
                            if (!empty($suffixParts)) {
                                $label .= ' (' . implode(' – ', $suffixParts) . ')';
                            }
                            $routeSegments[] = $label;
                        }
                    }
                    $routeHienThi = implode(' → ', array_filter($routeSegments, function($part){
                        return trim((string)$part) !== '';
                    }));
                    $ketQua['thong_tin']['route_hien_thi'] = $routeHienThi;
                }
            } else {
                // Chỉ tính theo tuyến có sẵn; nếu thiếu tuyến sẽ báo hướng dẫn thêm tuyến
                // HOẶC sử dụng khoảng cách thủ công nếu người dùng đã nhập
                try {
                    $ketQua = $tinhToan->tinhNhienLieu($tenTau, $diemBatDauGoc, $diemKetThucGoc, $khoiLuong, $khoangCachThuCong);
                } catch (Exception $e) {
                    $link = '<a href="admin/quan_ly_tuyen_duong.php">Quản lý tuyến đường</a>';
                    throw new Exception('Chưa có tuyến trực tiếp giữa "' . $diemBatDauGoc . '" và "' . $diemKetThucGoc . '". Vui lòng vào ' . $link . ' để thêm tuyến hoặc nhập khoảng cách thủ công.');
                }
            }
        } else {
            // Không có thông tin tuyến đường -> chỉ có thể là cấp thêm
            $ketQua = null;
        }

        // Xử lý cấp thêm (nếu có)
        if ($capThem && $soLuongCapThem > 0) {
            $ketQuaCapThem = [
                'nhien_lieu_lit' => $soLuongCapThem,
                'loai_tinh' => 'cap_them',
                'thong_tin' => [
                    'ten_tau' => $tenTau,
                    'diem_bat_dau' => '',
                    'diem_ket_thuc' => '',
                    'khoang_cach_km' => 0,
                    'khoi_luong_tan' => 0,
                    'he_so_ko_hang' => 0,
                    'he_so_co_hang' => 0,
                    'nhom_cu_ly' => '',
                    'nhom_cu_ly_label' => 'Cấp thêm'
                ],
                'chi_tiet' => [
                    'sch' => 0,
                    'skh' => 0,
                    'cong_thuc' => $lyDoCapThem
                ]
            ];

            // Nếu không có kết quả tính toán dầu cho quảng đường, hiển thị cấp thêm
            if (!$ketQua) {
                $ketQua = $ketQuaCapThem;
            }
        }

        // Kiểm tra phải có ít nhất một kết quả
        if (!$ketQua && !$ketQuaCapThem) {
            throw new Exception('Vui lòng nhập thông tin tuyến đường hoặc cấp thêm dầu.');
        }

        // Xác định created_at theo quy tắc tháng báo cáo và/hoặc ngày đã nhập
        // - Nếu có bất kỳ ngày nào: lấy ngày MUỘN NHẤT trong số (ngày đi/đến/dỡ xong)
        // - Nếu không có ngày: dùng giữa tháng của 'thang_bao_cao' (ngày 15)
        $createdAt = date('Y-m-d H:i:s');
        $dsNgay = array_values(array_filter([$ngayDi, $ngayDen, $ngayDoXong], function($d){ return !empty($d); }));
        if (!empty($dsNgay)) {
            // Convert dates to timestamps for proper comparison
            $timestamps = array_map(function($date) {
                return strtotime($date);
            }, $dsNgay);
            
            // Filter out invalid timestamps
            $validTimestamps = array_filter($timestamps, function($ts) {
                return $ts !== false;
            });
            
            if (!empty($validTimestamps)) {
                // Lấy ngày muộn nhất để đảm bảo ghi nhận đúng tháng phát sinh gần nhất
                $maxTimestamp = max($validTimestamps);
                $createdAt = date('Y-m-d H:i:s', $maxTimestamp);
            }
        } elseif (!empty($thangBaoCao) && preg_match('/^\d{4}-\d{2}$/', $thangBaoCao)) {
            // Không có ngày nào: rơi về tháng báo cáo do người dùng chọn
            $createdAt = $thangBaoCao . '-15 ' . date('H:i:s');
        }

        // Chuẩn bị dữ liệu lưu
        // Xác định có lưu cả hai kết quả không
        $luuCaHaiKetQua = ($ketQua && $ketQuaCapThem && $ketQua['loai_tinh'] !== 'cap_them');

        // Dữ liệu chung cho cả hai loại
        $dataChung = [
            'ten_phuong_tien' => $tenTau,
            'so_chuyen' => $soChuyen,
            'ghi_chu' => $ghiChu,
            'ngay_di' => $ngayDi,
            'ngay_den' => $ngayDen,
            'ngay_do_xong' => $ngayDoXong,
            'loai_hang' => $loaiHang,
            'thang_bao_cao' => $thangBaoCao,
            'created_at' => $createdAt,
        ];

        // Chuẩn bị dữ liệu tính toán dầu (nếu có)
        $dataLuuTinhToan = null;
        if ($ketQua && $ketQua['loai_tinh'] !== 'cap_them') {
            $sch = $ketQua['chi_tiet']['sch'] ?? 0;
            $skh = $ketQua['chi_tiet']['skh'] ?? 0;
            $heSoKhongHang = $ketQua['thong_tin']['he_so_ko_hang'] ?? 0;
            $heSoCoHang = $ketQua['thong_tin']['he_so_co_hang'] ?? 0;
            $nhienLieuLit = $ketQua['nhien_lieu_lit'] ?? 0;
            $khoiLuongLuanChuyen = ($sch > 0 && $khoiLuong > 0) ? ($sch * $khoiLuong) : 0;

            $dataLuuTinhToan = array_merge($dataChung, [
                'diem_di' => $diemBatDau,
                'diem_den' => (!empty($doiLenh) ? $lastPoint : $diemKetThuc),
                'cu_ly_co_hang_km' => $sch,
                'cu_ly_khong_hang_km' => $skh,
                'he_so_co_hang' => $heSoCoHang,
                'he_so_khong_hang' => $heSoKhongHang,
                'khoi_luong_van_chuyen_t' => $khoiLuong,
                'khoi_luong_luan_chuyen' => $khoiLuongLuanChuyen,
                'dau_tinh_toan_lit' => $nhienLieuLit,
                'cap_them' => 0, // Không phải cấp thêm
                'doi_lenh' => $doiLenh,
                'diem_du_kien' => $diemKetThuc,
                'ly_do_cap_them' => '',
                'so_luong_cap_them_lit' => 0,
                'cay_xang_cap_them' => '',
                'nhom_cu_ly' => $ketQua['thong_tin']['nhom_cu_ly'] ?? '',
                'doi_lenh_tuyen' => (!empty($doiLenh) ? json_encode($structuredDiemMoi, JSON_UNESCAPED_UNICODE) : ''),
                'route_hien_thi' => ($ketQua['thong_tin']['route_hien_thi'] ?? '') ?: ($diemBatDau . ' → ' . $diemKetThuc),
            ]);
        }

        // Chuẩn bị dữ liệu cấp thêm (nếu có)
        $dataLuuCapThem = null;
        if ($ketQuaCapThem) {
            $dataLuuCapThem = array_merge($dataChung, [
                'diem_di' => '',
                'diem_den' => '',
                'cu_ly_co_hang_km' => 0,
                'cu_ly_khong_hang_km' => 0,
                'he_so_co_hang' => 0,
                'he_so_khong_hang' => 0,
                'khoi_luong_van_chuyen_t' => 0,
                'khoi_luong_luan_chuyen' => 0,
                'dau_tinh_toan_lit' => $soLuongCapThem, // FIX: Ghi số lượng cấp thêm thay vì 0
                'cap_them' => 1, // Đây là cấp thêm
                'doi_lenh' => 0,
                'diem_du_kien' => '',
                'ly_do_cap_them' => $lyDoCapThem,
                'so_luong_cap_them_lit' => $soLuongCapThem,
                'cay_xang_cap_them' => '',
                'nhom_cu_ly' => '',
                'doi_lenh_tuyen' => '',
                'route_hien_thi' => '',
            ]);
        }

        // Trường hợp chỉ có cấp thêm (không có tính toán dầu)
        if (!$dataLuuTinhToan && $dataLuuCapThem) {
            $dataLuuTinhToan = $dataLuuCapThem;
            $dataLuuCapThem = null; // Để tránh lưu 2 lần
        }

        file_put_contents('debug_log.txt', "[POST] Processing action: {$action}.\n", FILE_APPEND);
        if ($action === 'save') {
            // Debug dữ liệu ngay trước khi lưu
            error_log('DEBUG SAVE ACTION: $ketQua exists: ' . ($ketQua ? 'YES' : 'NO'));
            error_log('DEBUG SAVE ACTION: $ketQuaCapThem exists: ' . ($ketQuaCapThem ? 'YES' : 'NO'));
            error_log('DEBUG SAVE ACTION: $dataLuuTinhToan exists: ' . ($dataLuuTinhToan ? 'YES' : 'NO'));
            error_log('DEBUG SAVE ACTION: $dataLuuCapThem exists: ' . ($dataLuuCapThem ? 'YES' : 'NO'));
            if ($dataLuuCapThem) {
                error_log('DEBUG SAVE ACTION: Data to be saved (Cap them): ' . print_r($dataLuuCapThem, true));
            } else {
                error_log('DEBUG SAVE ACTION: $dataLuuCapThem is NULL! $capThem=' . $capThem . ', $soLuongCapThem=' . $soLuongCapThem);
            }

            file_put_contents('debug_log.txt', "[POST] Attempting to save data...\n", FILE_APPEND);

            // Kiểm tra dữ liệu trước khi lưu
            if (!$dataLuuTinhToan) {
                $errorMsg = 'Không có dữ liệu để lưu. $ketQua: ' . ($ketQua ? 'exists' : 'null') . ', $ketQuaCapThem: ' . ($ketQuaCapThem ? 'exists' : 'null');
                error_log('DEBUG SAVE ACTION: ' . $errorMsg);
                throw new Exception($errorMsg);
            }

            $saved = false;

            // Lưu kết quả tính toán dầu (nếu có)
            if ($dataLuuTinhToan) {
                $saved = $luuKetQua->luu($dataLuuTinhToan);
                if (!$saved) {
                    error_log('DEBUG SAVE ACTION: luu() returned false for tinh toan.');
                    throw new Exception('Không thể lưu dữ liệu tính toán. Vui lòng kiểm tra lại.');
                } else {
                    error_log('DEBUG SAVE ACTION: luu() returned true for tinh toan.');
                }
            }

            // Lưu kết quả cấp thêm (nếu có và khác với tính toán dầu)
            if ($saved && $dataLuuCapThem) {
                $savedCapThem = $luuKetQua->luu($dataLuuCapThem);
                if (!$savedCapThem) {
                    error_log('DEBUG SAVE ACTION: luu() returned false for cap them.');
                } else {
                    error_log('DEBUG SAVE ACTION: luu() returned true for cap them.');
                }
            }

            if ($saved) {
                // Điều hướng về trang chính với tàu và số chuyến để hiển thị ngay các đoạn của chuyến
                $redirectSoChuyen = $soChuyen;
                // Giữ nguyên tháng báo cáo người dùng chọn để không bị đổi sau khi lưu
                $qs = 'ten_tau=' . urlencode($tenTau) . '&so_chuyen=' . urlencode((string)$redirectSoChuyen) . '&saved=1';
                if (!empty($thangBaoCao)) { $qs .= '&thang=' . urlencode($thangBaoCao); }

                // Debug redirect URL
                error_log('DEBUG REDIRECT: URL=' . 'index.php?' . $qs);

                header('Location: index.php?' . $qs);
                exit;
            } else {
                throw new Exception('Không thể lưu dữ liệu. Vui lòng thử lại.');
            }
            // Sau khi lưu xong, có thể xóa bản tính tạm
            unset($_SESSION['calc']);
        } else {
            // Lưu vào session và chuyển hướng (PRG) để tránh F5 mất dữ liệu/confirm resubmit
            $_SESSION['calc'] = [
                'form' => $formData,
                'ketQua' => $ketQua,
                'ketQuaCapThem' => $ketQuaCapThem ?? null
            ];
            header('Location: index.php?show=1');
            exit;
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Nếu là GET và có yêu cầu hiển thị kết quả từ session
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['show']) && isset($_SESSION['calc'])) {
    $ketQua = $_SESSION['calc']['ketQua'] ?? null;
    $ketQuaCapThem = $_SESSION['calc']['ketQuaCapThem'] ?? null;
    $formData = $_SESSION['calc']['form'] ?? $formData;

}

// Xử lý tham số GET khi reload trang (từ onTauChange)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ten_tau'])) {
    // Debug GET parameters on reload
    error_log('DEBUG GET: Parameters: ' . print_r($_GET, true));
    $formData['ten_tau'] = $_GET['ten_tau'];
    if (isset($_GET['so_chuyen']) && !empty($_GET['so_chuyen'])) {
        $formData['so_chuyen'] = $_GET['so_chuyen'];
    } else {
        // Nếu có ten_tau nhưng không có so_chuyen, tự động set mã chuyến cao nhất
        $maChuyenCaoNhat = $luuKetQua->layMaChuyenCaoNhat($_GET['ten_tau']);
        $formData['so_chuyen'] = $maChuyenCaoNhat > 0 ? $maChuyenCaoNhat : 1;
    }
    // Giữ lại tháng báo cáo nếu được truyền trong URL
    if (isset($_GET['thang']) && preg_match('/^\d{4}-\d{2}$/', $_GET['thang'])) {
        $formData['thang_bao_cao'] = $_GET['thang'];
    }
}

// Tính mã chuyến cao nhất (base) để gán vào data attribute cho client
$maChuyenCaoNhat = 0;
if (!empty($formData['ten_tau'])) {
    $maChuyenCaoNhat = $luuKetQua->layMaChuyenCaoNhat($formData['ten_tau']);
}

// Tự động set mã chuyến cao nhất CHỈ KHI chưa có số chuyến được chọn
if (!empty($formData['ten_tau']) && (empty($formData['so_chuyen']) || !is_numeric($formData['so_chuyen']))) {
    // Chỉ tự động chọn mã chuyến cao nhất nếu không phải là đang hiển thị kết quả tính toán
    // Vì khi hiển thị kết quả, mã chuyến đã được xác định trong session
    if (!isset($_GET['show'])) {
        $maChuyenCaoNhat = $luuKetQua->layMaChuyenCaoNhat($formData['ten_tau']);
        $formData['so_chuyen'] = $maChuyenCaoNhat > 0 ? $maChuyenCaoNhat : 1;
    }
}

// Lấy thông tin chuyến hiện tại và các đoạn nếu đã chọn tàu
if (!empty($formData['ten_tau'])) {
    $chuyenHienTai = intval($formData['so_chuyen']);
    $cacDoanCuaChuyen = $luuKetQua->layCacDoanCuaChuyen($formData['ten_tau'], $chuyenHienTai);
}

// Include header
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center">
                <h1 class="card-title">
                    <i class="fas fa-calculator text-primary me-3"></i>
                    Tính Toán Nhiên Liệu Sử Dụng
                </h1>
                <p class="card-text">
                    Nhập thông tin tàu, tuyến đường và khối lượng hàng hóa để tính toán lượng nhiên liệu cần thiết
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Error Alert -->
<?php if ($error): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Lỗi:</strong> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($saved): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-save me-2"></i>
            Đã lưu kết quả tính toán.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Calculation Form -->
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-edit me-2"></i>
                    Thông Tin Tính Toán
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" onsubmit="return validateForm()" autocomplete="off">
                    <?php if ($ketQua || (isset($_GET['show']) && isset($_SESSION['calc']))): ?>
                    <input type="hidden" id="has_calc_session" value="1">
                    <?php endif; ?>
                    <!-- Tên tàu -->
                    <div class="mb-3">
                        <label for="ten_tau" class="form-label">
                            <i class="fas fa-ship me-1"></i>
                            Tên tàu <span class="text-danger">*</span>
                        </label>
                        <div class="row g-2">
                            <div class="col-md-8">
                                <select class="form-select" id="ten_tau" name="ten_tau" onchange="onTauChange()">
                                    <option value="">-- Chọn tàu --</option>
                                    <?php $mapPL = $tauPhanLoai->getAll(); foreach ($danhSachTau as $tau): $pl = $mapPL[$tau] ?? 'cong_ty'; ?>
                                    <option value="<?php echo htmlspecialchars($tau); ?>" data-pl="<?php echo htmlspecialchars($pl); ?>" <?php echo ($formData['ten_tau'] === $tau) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tau); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <select class="form-select" id="loc_phan_loai" onchange="filterTauByPhanLoai()">
                                    <option value="">-- Tất cả --</option>
                                    <option value="cong_ty">Sà lan công ty</option>
                                    <option value="thue_ngoai">Thuê ngoài</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Thông tin chuyến -->
                    <div class="mb-3">
                        <div class="row align-items-end">
                            <div class="col-md-3">
                                <label for="so_chuyen" class="form-label">
                                    <i class="fas fa-hashtag me-1"></i>
                                    Mã chuyến
                                </label>
                                <select class="form-select" id="so_chuyen" name="so_chuyen" onchange="onChuyenChange()" data-preselected="<?php echo htmlspecialchars($formData['so_chuyen']); ?>">
                                    <option value="">Vui lòng chọn tàu</option>
                                </select>
                                <script>
                                    // Đồng bộ hóa giá trị preselected ngay lập tức để tránh race condition
                                    (function() {
                                        const select = document.getElementById('so_chuyen');
                                        const preselectedValue = select.getAttribute('data-preselected');
                                        if (preselectedValue) {
                                            // Kiểm tra xem option đã tồn tại chưa
                                            if (!select.querySelector(`option[value="${preselectedValue}"]`)) {
                                                const option = document.createElement('option');
                                                option.value = preselectedValue;
                                                option.textContent = preselectedValue;
                                                select.appendChild(option);
                                            }
                                            select.value = preselectedValue;
                                        }
                                    })();
                                </script>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="openTripChangeModal()" id="btn_change_trip">
                                        <i class="fas fa-exchange-alt me-1"></i>
                                        Di chuyển đoạn
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label for="thang_bao_cao" class="form-label">
                                    <i class="fas fa-calendar-alt me-1"></i>
                                    Tháng báo cáo
                                </label>
                                <select class="form-select" id="thang_bao_cao" name="thang_bao_cao">
                                    <?php
                                    // Hiển thị 12 tháng trước đến 2 tháng tới (tổng 15 tháng)
                                    for ($i = -11; $i <= 2; $i++) {
                                        $time = strtotime("$i month");
                                        $value = date('Y-m', $time);
                                        $text = 'Tháng ' . date('m/Y', $time);
                                        // Chọn theo formData nếu có, mặc định tháng hiện tại
                                        $selected = (!empty($formData['thang_bao_cao']) ? ($formData['thang_bao_cao'] === $value) : (date('Y-m') === $value)) ? 'selected' : '';
                                        echo "<option value='{$value}' {$selected}>{$text}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="chuyen_moi" name="chuyen_moi" 
                                           onchange="onToggleChuyenMoi()"
                                           <?php echo ($formData['chuyen_moi'] ? 'checked' : ''); ?>>
                                    <label class="form-check-label" for="chuyen_moi">
                                        <strong>Tạo chuyến mới</strong>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="form-text">
                            <i class="fas fa-info-circle me-1"></i>
                            Chọn tàu để tải danh sách chuyến. Tick "Tạo chuyến mới" để tạo chuyến tiếp theo. Nhấn "Di chuyển đoạn" để chuyển đoạn giữa các chuyến.
                        </div>
                    </div>


                    <!-- Hiển thị các đoạn của chuyến hiện tại (luôn hiển thị khung) -->
                    <div class="mb-3" id="tripLogDynamic">
                        <div class="card border-info">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-list me-2"></i>
                                    Các đoạn của chuyến <?php echo ($chuyenHienTai ?? ''); ?> (sắp xếp theo thứ tự nhập)
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php 
                                    // Chỉ truy vấn khi đã có tàu và mã chuyến hợp lệ
                                    $capThemTrongChuyen = [];
                                    if (!empty($formData['ten_tau']) && is_numeric($chuyenHienTai)) {
                                        $capThemTrongChuyen = $luuKetQua->layCapThemCuaChuyen($formData['ten_tau'], (int)$chuyenHienTai);
                                    }
                                    // Gộp các đoạn và cấp thêm để sắp xếp theo thứ tự nhập thực tế (ID)
                                    $combinedRows = [];
                                    foreach ($cacDoanCuaChuyen as $idx => $doan) {
                                        $combinedRows[] = [
                                            'type' => 'doan',
                                            'data' => $doan,
                                            'id' => (int)($doan['___idx'] ?? 0), // Sử dụng ID thực tế từ database
                                            'date' => parse_date_vn($doan['ngay_di'] ?? '') ?: substr((string)($doan['created_at'] ?? ''), 0, 10),
                                            'seq' => $idx
                                        ];
                                    }
                                    foreach ($capThemTrongChuyen as $i => $ct) {
                                        $combinedRows[] = [
                                            'type' => 'cap_them',
                                            'data' => $ct,
                                            'id' => (int)($ct['___idx'] ?? 0), // Sử dụng ID thực tế từ database
                                            'date' => substr((string)($ct['created_at'] ?? ''), 0, 10),
                                            'seq' => 1000 + $i
                                        ];
                                    }
                                    // Sắp xếp theo thứ tự logic: mã chuyến -> thứ tự nhập liệu
                                    usort($combinedRows, function($a, $b){
                                        // Sắp xếp theo mã chuyến (số tăng dần)
                                        $tripA = (int)($a['so_chuyen'] ?? 0);
                                        $tripB = (int)($b['so_chuyen'] ?? 0);
                                        if ($tripA !== $tripB) {
                                            return $tripA <=> $tripB;
                                        }
                                        
                                        // Sắp xếp theo ___idx (thứ tự trong CSV)
                                        $idxA = (int)($a['___idx'] ?? 0);
                                        $idxB = (int)($b['___idx'] ?? 0);
                                        if ($idxA !== $idxB) {
                                            return $idxA <=> $idxB;
                                        }
                                        
                                        // Nếu cùng chuyến và cùng ___idx, sắp xếp theo thứ tự trong mảng
                                        return $a['seq'] <=> $b['seq'];
                                    });
                                ?>
                                <?php if (!empty($capThemTrongChuyen)): ?>
                                <div class="alert alert-warning d-flex align-items-center" role="alert">
                                    <i class="fas fa-gas-pump me-2"></i>
                                    <div>
                                        Đã có <strong><?php echo count($capThemTrongChuyen); ?></strong> lệnh cấp thêm trong chuyến này.
                                        <?php $sumCap = array_sum(array_map(function($r){ return (float)($r['so_luong_cap_them_lit'] ?? 0); }, $capThemTrongChuyen)); ?>
                                        Tổng: <strong><?php echo number_format($sumCap, 0); ?></strong> lít.
                                    </div>
                                </div>
                                <?php endif; ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                            <tr>
                                                <th>STT</th>
                                                <th>Điểm đi</th>
                                                <th>Điểm đến</th>
                                                <th>Khối lượng</th>
                                                <th>Nhiên liệu</th>
                                                <th>Ngày đi</th>
                                                <th>Thao tác</th>
                                            </tr>
                                        </thead>
                                        <tbody id="trip_table_body">
                                            <?php if (empty($combinedRows)): ?>
                                                <tr>
                                                    <td colspan="7" class="text-muted">Chưa có dữ liệu cho chuyến này.</td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php $stt = 1; foreach ($combinedRows as $row): ?>
                                                <?php if ($row['type'] === 'doan'): $doan = $row['data']; ?>
                                                    <?php
                                                        // Ưu tiên dùng route_hien_thi nếu có (đã lưu đầy đủ tuyến đường)
                                                        // Tìm key route_hien_thi trong mảng (có thể có vấn đề với CSV parsing)
                                                        $routeHienThi = '';
                                                        foreach ($doan as $key => $value) {
                                                            if (trim($key) === 'route_hien_thi') {
                                                                $routeHienThi = trim((string)$value);
                                                                if ($routeHienThi !== '') {
                                                                    break;
                                                                }
                                                            }
                                                        }
                                                        
                                                        // Tìm key doi_lenh_tuyen trong mảng
                                                        $doiLenhTuyenJson = '';
                                                        foreach ($doan as $key => $value) {
                                                            if (trim($key) === 'doi_lenh_tuyen') {
                                                                $doiLenhTuyenJson = trim((string)$value);
                                                                if ($doiLenhTuyenJson !== '') {
                                                                    break;
                                                                }
                                                            }
                                                        }
                                                        
                                                        $isDoiLenh = (intval($doan['doi_lenh'] ?? 0) === 1);
                                                        
                                                        $routeDisplay = '';
                                                        
                                                        // Bước 1: Ưu tiên dùng route_hien_thi nếu có
                                                        if ($routeHienThi !== '' && strlen($routeHienThi) > 0) {
                                                            $routeDisplay = $routeHienThi;
                                                        }
                                                        // Bước 2: Nếu không có route_hien_thi, thử xây dựng từ doi_lenh_tuyen
                                                        elseif ($doiLenhTuyenJson !== '' && $isDoiLenh) {
                                                            $doiLenhTuyenData = json_decode($doiLenhTuyenJson, true);
                                                            if (is_array($doiLenhTuyenData) && !empty($doiLenhTuyenData)) {
                                                                $routeSegments = [];
                                                                $diemDi = trim((string)($doan['diem_di'] ?? ''));
                                                                if ($diemDi !== '') {
                                                                    $routeSegments[] = $diemDi;
                                                                }
                                                                $diemDuKien = trim((string)($doan['diem_du_kien'] ?? ''));
                                                                if ($diemDuKien !== '') {
                                                                    $routeSegments[] = $diemDuKien;
                                                                }
                                                                foreach ($doiLenhTuyenData as $entry) {
                                                                    if (is_array($entry)) {
                                                                        $label = trim((string)($entry['point'] ?? ''));
                                                                        $suffixParts = [];
                                                                        if (!empty($entry['reason'])) {
                                                                            $suffixParts[] = trim((string)$entry['reason']);
                                                                        }
                                                                        if (!empty($entry['note'])) {
                                                                            $suffixParts[] = trim((string)$entry['note']);
                                                                        }
                                                                        if (!empty($suffixParts)) {
                                                                            $label .= ' (' . implode(' – ', $suffixParts) . ')';
                                                                        }
                                                                        if ($label !== '') {
                                                                            $routeSegments[] = $label;
                                                                        }
                                                                    }
                                                                }
                                                                // Điểm cuối thực tế (diem_den) thường đã có trong doi_lenh_tuyen
                                                                // nhưng để đảm bảo, kiểm tra xem có cần thêm không
                                                                $diemDen = trim((string)($doan['diem_den'] ?? ''));
                                                                if ($diemDen !== '') {
                                                                    $lastSegment = !empty($routeSegments) ? end($routeSegments) : '';
                                                                    $lastPointOnly = preg_replace('/\s*\([^)]*\)\s*$/', '', $lastSegment);
                                                                    // Kiểm tra xem điểm cuối đã có trong routeSegments chưa
                                                                    $found = false;
                                                                    foreach ($routeSegments as $seg) {
                                                                        $segClean = preg_replace('/\s*\([^)]*\)\s*$/', '', $seg);
                                                                        if (stripos($seg, $diemDen) !== false || stripos($segClean, $diemDen) !== false) {
                                                                            $found = true;
                                                                            break;
                                                                        }
                                                                    }
                                                                    if (!$found) {
                                                                        // Nếu điểm cuối chưa có trong route, thêm vào
                                                                        $routeSegments[] = $diemDen;
                                                                    }
                                                                }
                                                                $routeDisplay = implode(' → ', array_filter($routeSegments, function($part){
                                                                    return trim((string)$part) !== '';
                                                                }));
                                                            }
                                                        }
                                                        // Bước 3: Fallback - dùng diem_den nếu không có route_hien_thi và không có doi_lenh_tuyen
                                                        if ($routeDisplay === '') {
                                                            $routeDisplay = trim((string)($doan['diem_den'] ?? ''));
                                                        }
                                                    ?>
                                                    <tr>
                                                        <td><strong><?php echo $stt++; ?></strong></td>
                                                        <td><?php echo htmlspecialchars($doan['diem_di']); ?></td>
                                                        <td><?php echo htmlspecialchars($routeDisplay); ?></td>
                                                        <td><?php echo htmlspecialchars($doan['khoi_luong_van_chuyen_t']); ?> tấn</td>
                                                        <td><?php echo htmlspecialchars($doan['dau_tinh_toan_lit']); ?> lít</td>
                                                        <td><?php echo htmlspecialchars(format_date_vn($doan['ngay_di'])); ?></td>
                                                        <td>
                                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                    onclick="openEditSegmentModal(<?php echo (int)($doan['___idx'] ?? 0); ?>)"
                                                                    title="Sửa đoạn này">
                                                                <i class="fas fa-edit"></i> Sửa
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php else: $ct = $row['data']; ?>
                                                    <tr class="table-warning">
                                                        <td><span class="badge bg-warning text-dark">Cấp thêm</span></td>
                                                        <td colspan="2">
                                                            <span class="text-muted">—</span>
                                                        </td>
                                                        <td><span class="text-muted">—</span></td>
                                                        <td>
                                                            <strong><?php echo number_format((float)($ct['so_luong_cap_them_lit'] ?? 0), 0); ?></strong> lít
                                                            <?php if (!empty($ct['ly_do_cap_them'])): ?>
                                                            <br><small class="text-muted">Lý do: <?php echo htmlspecialchars($ct['ly_do_cap_them']); ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="text-muted">—</span>
                                                        </td>
                                                        <td>
                                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                    onclick="openEditSegmentModal(<?php echo (int)($ct['___idx'] ?? 0); ?>)"
                                                                    title="Sửa cấp thêm này">
                                                                <i class="fas fa-edit"></i> Sửa
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <i class="fas fa-plus-circle me-1"></i>
                                        Đoạn mới sẽ được thêm vào danh sách trên
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Đổi lệnh -->
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="doi_lenh" name="doi_lenh" <?php echo (!empty($formData['doi_lenh']) ? 'checked' : ''); ?>>
                        <label class="form-check-label" for="doi_lenh"><strong>Đổi lệnh trong chuyến</strong></label>
                    </div>

                    <!-- Điểm bắt đầu -->
                    <div class="mb-3">
                        <label for="diem_bat_dau" class="form-label">
                            <i class="fas fa-map-marker-alt me-1"></i>
                            Điểm bắt đầu <span class="text-danger">*</span>
                        </label>
                        <div class="row g-2">
                            <div class="col-md-8">
                                <input type="text" class="form-control diem-input" id="diem_bat_dau" name="diem_bat_dau"
                                    value="<?php echo htmlspecialchars($formData['diem_bat_dau']); ?>"
                                    placeholder="Bắt đầu nhập để tìm kiếm..." autocomplete="off"
                                    onfocus="showAllDiem(document.getElementById('diem_bat_dau_results'), '');"
                                    oninput="searchDiem(this, document.getElementById('diem_bat_dau_results'))">
                                <div class="dropdown-menu diem-results" id="diem_bat_dau_results" style="width: 100%; max-height: 200px; overflow-y: auto;"></div>
                            </div>
                            <div class="col-md-4">
                                <input type="text" class="form-control" id="ghi_chu_diem_bat_dau" name="ghi_chu_diem_bat_dau"
                                    placeholder="Ghi chú..." autocomplete="off">
                            </div>
                        </div>
                        <div class="form-text">
                            <i class="fas fa-info-circle me-1"></i>
                            Click vào ô để hiện tất cả điểm có sẵn. Ghi chú sẽ hiển thị: Tên điểm （ghi chú）
                        </div>
                        <div class="mt-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetDiem('diem_bat_dau')">
                                <i class="fas fa-undo me-1"></i>Chọn lại
                            </button>
                        </div>
                    </div>

                    <!-- Điểm kết thúc -->
                    <div class="mb-3">
                        <label for="diem_ket_thuc" class="form-label">
                            <i class="fas fa-flag-checkered me-1"></i>
                            Điểm kết thúc dự kiến (B) <span class="text-danger">*</span>
                        </label>
                        <div class="row g-2">
                            <div class="col-md-8">
                                <input type="text" class="form-control diem-input" id="diem_ket_thuc" name="diem_ket_thuc"
                                    value="<?php echo htmlspecialchars($formData['diem_ket_thuc']); ?>"
                                    placeholder="Bắt đầu nhập để tìm kiếm..." autocomplete="off"
                                    onfocus="showAllDiem(document.getElementById('diem_ket_thuc_results'), document.getElementById('diem_bat_dau').value);"
                                    oninput="searchDiem(this, document.getElementById('diem_ket_thuc_results'))">
                                <div class="dropdown-menu diem-results" id="diem_ket_thuc_results" style="width: 100%; max-height: 200px; overflow-y: auto;"></div>
                            </div>
                            <div class="col-md-4">
                                <input type="text" class="form-control" id="ghi_chu_diem_ket_thuc" name="ghi_chu_diem_ket_thuc"
                                    placeholder="Ghi chú..." autocomplete="off">
                            </div>
                        </div>
                        <div class="form-text">
                            <i class="fas fa-info-circle me-1"></i>
                            Nếu bật Đổi lệnh, đây là điểm B (đổi lệnh tại đây)
                        </div>
                        <div class="mt-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetDiem('diem_ket_thuc')">
                                <i class="fas fa-undo me-1"></i>Chọn lại
                            </button>
                        </div>
                    </div>

                    <!-- Khoảng cách cho tuyến A → B (hiển thị khi chọn đủ 2 điểm và không đổi lệnh) -->
                    <div id="khoang_cach_thu_cong_fields" class="mb-3" style="display: none;">
                        <label for="khoang_cach_thu_cong" class="form-label">
                            <i class="fas fa-ruler-combined me-1"></i>
                            Khoảng cách (A → B) - Km <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input type="number" step="0.1" min="0.1" class="form-control" id="khoang_cach_thu_cong" name="khoang_cach_thu_cong"
                                   value="" autocomplete="off" placeholder="Nhập khoảng cách...">
                            <button class="btn btn-outline-secondary" type="button" id="btn_unlock_khoang_cach" style="display: none;"
                                    onclick="unlockKhoangCach()" title="Cho phép chỉnh sửa">
                                <i class="fas fa-lock-open"></i> Sửa
                            </button>
                        </div>
                        <div class="form-text" id="khoang_cach_help_text">
                            <i class="fas fa-info-circle me-1"></i>
                            <span id="khoang_cach_status">Đang kiểm tra...</span>
                        </div>
                    </div>

                    <!-- Khu vực đổi lệnh: điểm đến mới C + khoảng cách thực tế -->
                    <div id="doi_lenh_fields" class="border rounded p-3 mb-3" style="display:none;">
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-location-arrow me-1"></i>
                                Điểm đến mới (C, D, ...) <span class="text-danger">*</span>
                            </label>
                            <input type="hidden" id="prefilled_diem_moi_json" value="<?php echo htmlspecialchars(json_encode($formData['diem_moi_list'] ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" id="prefilled_diem_moi_json" value="<?php echo htmlspecialchars(json_encode($formData['diem_moi_list'] ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>">
                            <div id="ds_diem_moi_wrapper">
                                <div class="row g-2 mb-2 diem-moi-item">
                                    <div class="col-lg-5 col-md-6">
                                        <div class="position-relative">
                                            <input type="text" class="form-control diem-input" name="diem_moi[]"
                                                placeholder="Điểm C - Bắt đầu nhập để tìm kiếm..." autocomplete="off"
                                                onfocus="showAllDiem(this.nextElementSibling, '');"
                                                oninput="searchDiem(this, this.nextElementSibling)">
                                            <div class="dropdown-menu diem-results" style="width: 100%; max-height: 200px; overflow-y: auto;"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 col-md-5">
                                        <div class="d-flex flex-wrap gap-1 align-items-center reason-group">
                                            <input type="text" class="form-control form-control-sm diem-moi-reason" name="diem_moi_reason[]"
                                                placeholder="Ghi chú (tùy chọn)" autocomplete="off">
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-12 d-flex gap-2 justify-content-lg-end">
                                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="xoaDiemMoi(this)"><i class="fas fa-trash-alt me-1"></i>Xóa</button>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="themDiemMoi()"><i class="fas fa-plus me-1"></i>Thêm điểm</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetDiemDangChon()" title="Chọn lại điểm đang chỉnh"><i class="fas fa-undo me-1"></i>Chọn lại</button>
                            </div>
                            <div class="form-text mt-2">
                                <i class="fas fa-info-circle me-1"></i>
                                Có thể thêm nhiều điểm (C, D, E, ...). Hệ thống sẽ dùng <strong>Điểm cuối</strong> cho tính toán và <strong>Km thực tế</strong> bạn nhập là tổng cho toàn hành trình.
                            </div>
                            <div class="mt-3">
                                <label for="ghi_chu_diem_moi" class="form-label">Ghi chú cho điểm cuối (tùy chọn)</label>
                                <input type="text" class="form-control" id="ghi_chu_diem_moi" name="ghi_chu_diem_moi"
                                    placeholder="Ghi chú..." autocomplete="off">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="khoang_cach_thuc_te" class="form-label">
                                <i class="fas fa-ruler-combined me-1"></i>
                                Tổng khoảng cách thực tế (A → B (đổi lệnh) → C) - Km <span class="text-danger">*</span>
                            </label>
                            <input type="number" step="0.1" min="0.1" class="form-control" id="khoang_cach_thuc_te" name="khoang_cach_thuc_te"
                                   value="<?php echo htmlspecialchars($formData['khoang_cach_thuc_te'] ?? ''); ?>" autocomplete="off">
                            <div class="form-text">Nhập tổng Km thực tế của cả hành trình.</div>
                        </div>
                    </div>

                    <!-- Khối lượng -->
                    <div class="mb-3">
                        <label for="khoi_luong" class="form-label">
                            <i class="fas fa-weight-hanging me-1"></i>
                            Khối lượng hàng hóa (tấn) <span class="text-danger">*</span>
                        </label>
                        <input type="number" class="form-control" id="khoi_luong" name="khoi_luong" 
                               value="<?php echo htmlspecialchars($formData['khoi_luong']); ?>" 
                               min="0" step="0.01" autocomplete="off"
                               data-bs-toggle="tooltip" data-bs-placement="top" 
                               title="Nhập 0 nếu tàu chạy không hàng">
                        <div class="form-text">
                            <i class="fas fa-info-circle me-1"></i>
                            Nhập 0 nếu tàu chạy không hàng, nhập khối lượng thực tế nếu có hàng
                        </div>
                    </div>

                    <!-- Ngày đi - đến - dỡ xong -->
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label id="label_ngay_di" for="ngay_di" class="form-label"><i class="fas fa-calendar-day me-1"></i><span class="label-text">Ngày đi</span></label>
                            <input type="text" class="form-control vn-date" id="ngay_di" name="ngay_di" placeholder="dd/mm/yyyy" value="<?php echo htmlspecialchars(format_date_vn($formData['ngay_di'])); ?>" readonly>
                            <div class="form-text" id="ngay_di_help" style="display: none;">
                                <i class="fas fa-info-circle text-info me-1"></i>
                                Ngày sẽ tự động lấy từ chuyến trước đó
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="ngay_den" class="form-label"><i class="fas fa-calendar-check me-1"></i>Ngày đến</label>
                            <input type="text" class="form-control vn-date" id="ngay_den" name="ngay_den" placeholder="dd/mm/yyyy" value="<?php echo htmlspecialchars(format_date_vn($formData['ngay_den'])); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="ngay_do_xong" class="form-label"><i class="fas fa-box-open me-1"></i>Ngày dỡ xong</label>
                            <input type="text" class="form-control vn-date" id="ngay_do_xong" name="ngay_do_xong" placeholder="dd/mm/yyyy" value="<?php echo htmlspecialchars(format_date_vn($formData['ngay_do_xong'])); ?>">
                        </div>
                    </div>

                    <!-- Loại hàng -->
                    <div class="mb-3">
                        <label for="loai_hang" class="form-label"><i class="fas fa-tags me-1"></i>Loại hàng</label>
                        <select class="form-select" id="loai_hang" name="loai_hang">
                            <option value="">-- Chọn loại hàng --</option>
                            <?php foreach ($danhSachLoaiHang as $lh): $val = (string)($lh['ten_loai_hang'] ?? ''); ?>
                                <option value="<?php echo htmlspecialchars($val); ?>" <?php echo ($formData['loai_hang'] === $val ? 'selected' : ''); ?>><?php echo htmlspecialchars($val); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Quản lý danh mục tại: <a href="admin/quan_ly_loai_hang.php">Quản lý loại hàng</a></div>
                    </div>

                    <!-- Ghi chú -->
                    <div class="mb-3">
                        <label for="ghi_chu" class="form-label"><i class="fas fa-sticky-note me-1"></i>Ghi chú</label>
                        <input type="text" class="form-control" id="ghi_chu" name="ghi_chu" value="<?php echo htmlspecialchars($formData['ghi_chu']); ?>" autocomplete="off" placeholder="Nhập ghi chú (không phải ngày tạo)">
                    </div>

                    <!-- Nút gạt hiện/ẩn form cấp dầu -->
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="toggle_cap_them" onchange="toggleCapThemForm(this.checked)">
                        <label class="form-check-label" for="toggle_cap_them">
                            <i class="fas fa-gas-pump me-1"></i>
                            <strong>Cấp thêm</strong>
                        </label>
                    </div>

                    <!-- Form cấp thêm - ẩn mặc định -->
                    <div class="card border-primary mb-3" id="cap_them_card" style="display: none;">
                        <div class="card-header bg-primary text-white py-2">
                            <i class="fas fa-gas-pump me-2"></i>
                            <strong>Cấp thêm (tùy chọn)</strong>
                        </div>
                        <div class="card-body">
                            <input type="hidden" id="cap_them" name="cap_them" value="<?php echo $formData['cap_them'] ?? 0; ?>">
                        <div class="mb-3">
                            <label class="form-label">Loại <span class="text-danger">*</span></label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="loai_cap_them" id="loai_bom_nuoc" value="bom_nuoc" <?php echo ($formData['loai_cap_them'] === 'bom_nuoc') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="loai_bom_nuoc">Ma nơ</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="loai_cap_them" id="loai_qua_cau" value="qua_cau" <?php echo ($formData['loai_cap_them'] === 'qua_cau') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="loai_qua_cau">Qua cầu</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="loai_cap_them" id="loai_khac" value="khac" <?php echo ($formData['loai_cap_them'] === 'khac') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="loai_khac">Khác</label>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3" id="dia_diem_cap_them_wrapper">
                            <label for="dia_diem_cap_them" class="form-label">Địa điểm <span class="text-danger">*</span></label>
                            <input type="text" class="form-control diem-input" id="dia_diem_cap_them" name="dia_diem_cap_them"
                                value="<?php echo htmlspecialchars($formData['dia_diem_cap_them']); ?>"
                                placeholder="Nhập địa điểm (hoặc chọn từ gợi ý)..." autocomplete="off"
                                onfocus="showAllDiem(document.getElementById('dia_diem_cap_them_results'), '');"
                                oninput="searchDiem(this, document.getElementById('dia_diem_cap_them_results'))">
                            <div class="dropdown-menu diem-results" id="dia_diem_cap_them_results" style="width: 100%; max-height: 200px; overflow-y: auto;"></div>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Có thể nhập tự do hoặc chọn từ danh sách gợi ý
                            </div>
                        </div>
                        <!-- Ô nhập lý do (chỉ hiện khi chọn "Khác") -->
                        <div class="mb-3" id="ly_do_cap_them_wrapper" style="display:none;">
                            <label for="ly_do_cap_them_khac" class="form-label">Lý do tiêu hao <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="ly_do_cap_them_khac" name="ly_do_cap_them_khac" autocomplete="off" placeholder="Nhập lý do tiêu hao..." value="<?php echo htmlspecialchars($formData['ly_do_cap_them_khac']); ?>">
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Nhập lý do tiêu hao dầu (ví dụ: dầu cho thiết bị, dầu khác...)
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="so_luong_cap_them" class="form-label">Số lượng (Lít) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="so_luong_cap_them" name="so_luong_cap_them" value="<?php echo htmlspecialchars($formData['so_luong_cap_them']); ?>" min="0.01" step="0.01" autocomplete="off">
                        </div>
                        <!-- Ô preview (luôn hiển thị, readonly) -->
                        <div class="mb-3">
                            <label for="ly_do_cap_them_display" class="form-label" id="ly_do_cap_them_label">Lý do tiêu hao (tự động tạo)</label>
                            <input type="text" class="form-control" id="ly_do_cap_them_display" readonly
                                   value="" style="background-color: #f8f9fa; cursor: not-allowed;">
                            <div class="form-text" id="ly_do_cap_them_help">
                                <i class="fas fa-info-circle me-1"></i>
                                <span id="ly_do_cap_them_help_text">Lý do sẽ được tự động tạo dựa trên loại và địa điểm bạn chọn</span>
                            </div>
                        </div>
                        <div class="mb-3" id="ngay_cap_them_group" style="display: none;">
                            <label for="ngay_cap_them" class="form-label">Ngày cấp thêm</label>
                            <input type="text" class="form-control vn-date" id="ngay_cap_them" placeholder="dd/mm/yyyy"
                                   value="<?php echo htmlspecialchars(format_date_vn($formData['ngay_di'])); ?>" autocomplete="off">
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                <span class="ngay-cap-hint">Chọn ngày cấp thêm thực tế (tùy chọn cho Dầu ma nơ)</span>
                            </div>
                        </div>
                        <div class="alert alert-success" id="cap_them_preview">
                            <i class="fas fa-eye me-2"></i>
                            <strong>Kết quả sẽ lưu:</strong><br>
                            <div class="mt-2">
                                <strong>Lý do (sẽ lưu vào hệ thống):</strong><br>
                                <span id="cap_them_result_text" class="fw-bold">Dầu ma nơ tại bến [Địa điểm] 01 chuyến x [Số lượng] lít</span>
                            </div>
                            <div class="mt-2 small text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Trong báo cáo Excel sẽ hiển thị: <strong>CẤP THÊM: [Lý do trên]</strong>
                            </div>
                        </div>
                        </div><!-- end card-body -->
                    </div><!-- end card (Cấp thêm) -->
                    <div id="cap_them_fields" style="display:none;"></div><!-- placeholder để giữ tương thích -->

                    <script>
                    // Hàm toggle hiện/ẩn form cấp dầu
                    function toggleCapThemForm(show) {
                        const card = document.getElementById('cap_them_card');
                        const capThemHidden = document.getElementById('cap_them');
                        const soLuongInput = document.getElementById('so_luong_cap_them');
                        const diaDiemInput = document.getElementById('dia_diem_cap_them');
                        const lyDoDisplayInput = document.getElementById('ly_do_cap_them_display');
                        const lyDoKhacInput = document.getElementById('ly_do_cap_them_khac');

                        if (show) {
                            card.style.display = 'block';
                            // Set cap_them = 1 khi hiện form (user đã bật toggle)
                            if (capThemHidden) capThemHidden.value = '1';

                            // Đảm bảo các inputs KHÔNG bị disabled và có thể validate
                            if (diaDiemInput) {
                                diaDiemInput.disabled = false;
                                diaDiemInput.removeAttribute('disabled');
                            }
                            if (soLuongInput) {
                                soLuongInput.disabled = false;
                                soLuongInput.removeAttribute('disabled');
                            }
                            if (lyDoDisplayInput) {
                                lyDoDisplayInput.disabled = false;
                                lyDoDisplayInput.removeAttribute('disabled');
                            }
                            if (lyDoKhacInput) {
                                lyDoKhacInput.disabled = false;
                                lyDoKhacInput.removeAttribute('disabled');
                            }

                            // Trigger change event để hiển thị đúng form fields dựa trên loại được chọn
                            setTimeout(function() {
                                const selectedRadio = document.querySelector('input[name="loai_cap_them"]:checked');
                                if (selectedRadio) {
                                    const changeEvent = new Event('change', { bubbles: true });
                                    selectedRadio.dispatchEvent(changeEvent);
                                }
                            }, 150);
                        } else {
                            card.style.display = 'none';
                            // Reset giá trị khi ẩn (cap_them = 0) và DISABLE inputs để tránh validation
                            if (capThemHidden) capThemHidden.value = '0';

                            // Disable và reset value để tránh lỗi "invalid form control is not focusable"
                            if (soLuongInput) {
                                soLuongInput.value = '';  // Xóa value để không vi phạm min="0.01"
                                soLuongInput.disabled = true;  // Disable để skip validation
                            }
                            if (diaDiemInput) {
                                diaDiemInput.value = '';
                                diaDiemInput.disabled = true;
                            }
                            if (lyDoDisplayInput) {
                                lyDoDisplayInput.value = '';
                                lyDoDisplayInput.disabled = true;
                            }
                            if (lyDoKhacInput) {
                                lyDoKhacInput.value = '';
                                lyDoKhacInput.disabled = true;
                            }
                        }
                    }

                    // Toggle hiển thị địa điểm hoặc lý do tùy theo loại
                    document.querySelectorAll('input[name="loai_cap_them"]').forEach(function(radio) {
                        radio.addEventListener('change', function() {
                            console.log('Radio changed to:', this.value); // DEBUG

                            const diaDiemWrapper = document.getElementById('dia_diem_cap_them_wrapper');
                            const lyDoWrapper = document.getElementById('ly_do_cap_them_wrapper');
                            const diaDiemInput = document.getElementById('dia_diem_cap_them');
                            const lyDoKhacInput = document.getElementById('ly_do_cap_them_khac');
                            const lyDoHelpText = document.getElementById('ly_do_cap_them_help_text');
                            const ngayCapInput = document.getElementById('ngay_cap_them');
                            const ngayCapRequired = document.querySelector('.ngay-cap-required');
                            const ngayCapHint = document.querySelector('.ngay-cap-hint');

                            if (this.value === 'khac') {
                                console.log('Showing ly do input, hiding dia diem'); // DEBUG

                                // Ẩn địa điểm, hiện lý do nhập
                                if (diaDiemWrapper) diaDiemWrapper.style.display = 'none';
                                if (lyDoWrapper) {
                                    lyDoWrapper.style.display = 'block';
                                    console.log('lyDoWrapper display set to block'); // DEBUG
                                }

                                // Bỏ required cho địa điểm, thêm required cho lý do
                                if (diaDiemInput) diaDiemInput.removeAttribute('required');
                                if (lyDoKhacInput) {
                                    lyDoKhacInput.setAttribute('required', 'required');
                                    lyDoKhacInput.disabled = false;
                                    lyDoKhacInput.removeAttribute('disabled');
                                }

                                // Đổi help text
                                if (lyDoHelpText) {
                                    lyDoHelpText.textContent = 'Lý do sẽ được tự động tính dựa trên lý do bạn nhập và số lượng';
                                }

                                // Ngày cấp không bắt buộc
                                if (ngayCapInput) ngayCapInput.required = false;
                                if (ngayCapRequired) ngayCapRequired.style.display = 'none';
                                if (ngayCapHint) ngayCapHint.textContent = 'Chọn ngày cấp thêm thực tế (tùy chọn)';
                            } else if (this.value === 'bom_nuoc') {
                                // Hiện địa điểm, ẩn lý do nhập
                                diaDiemWrapper.style.display = 'block';
                                lyDoWrapper.style.display = 'none';

                                // Thêm required cho địa điểm, bỏ required cho lý do
                                if (diaDiemInput) {
                                    diaDiemInput.setAttribute('required', 'required');
                                    diaDiemInput.disabled = false;
                                    diaDiemInput.removeAttribute('disabled');
                                }
                                if (lyDoKhacInput) lyDoKhacInput.removeAttribute('required');

                                // Đổi help text về mặc định
                                if (lyDoHelpText) {
                                    lyDoHelpText.textContent = 'Lý do sẽ được tự động tạo dựa trên loại và địa điểm bạn chọn';
                                }

                                // Ngày cấp không bắt buộc cho Dầu ma nơ
                                if (ngayCapInput) ngayCapInput.required = false;
                                if (ngayCapRequired) ngayCapRequired.style.display = 'none';
                                if (ngayCapHint) ngayCapHint.textContent = 'Chọn ngày cấp thêm thực tế (tùy chọn cho Dầu ma nơ)';
                            } else {
                                // Qua cầu: Hiện địa điểm, ẩn lý do nhập
                                diaDiemWrapper.style.display = 'block';
                                lyDoWrapper.style.display = 'none';

                                // Thêm required cho địa điểm, bỏ required cho lý do
                                if (diaDiemInput) {
                                    diaDiemInput.setAttribute('required', 'required');
                                    diaDiemInput.disabled = false;
                                    diaDiemInput.removeAttribute('disabled');
                                }
                                if (lyDoKhacInput) lyDoKhacInput.removeAttribute('required');

                                // Đổi help text về mặc định
                                if (lyDoHelpText) {
                                    lyDoHelpText.textContent = 'Lý do sẽ được tự động tạo dựa trên loại và địa điểm bạn chọn';
                                }

                                // Ngày cấp không bắt buộc
                                if (ngayCapInput) ngayCapInput.required = false;
                                if (ngayCapRequired) ngayCapRequired.style.display = 'none';
                                if (ngayCapHint) ngayCapHint.textContent = 'Chọn ngày cấp thêm thực tế (tùy chọn)';
                            }

                            // Cập nhật preview
                            updateCapThemPreview();
                        });
                    });
                    
                    // Khởi tạo trạng thái ban đầu cho ngày cấp (Dầu ma nơ được chọn mặc định)
                    document.addEventListener('DOMContentLoaded', function() {
                        const bomNuocRadio = document.getElementById('loai_bom_nuoc');
                        if (bomNuocRadio && bomNuocRadio.checked) {
                            const ngayCapInput = document.getElementById('ngay_cap_them');
                            const ngayCapRequired = document.querySelector('.ngay-cap-required');
                            const ngayCapHint = document.querySelector('.ngay-cap-hint');
                            if (ngayCapInput) ngayCapInput.required = false;
                            if (ngayCapRequired) ngayCapRequired.style.display = 'none';
                            if (ngayCapHint) ngayCapHint.textContent = 'Chọn ngày cấp thêm thực tế (tùy chọn cho Dầu ma nơ)';
                        }

                        // Đảm bảo tất cả inputs trong form cấp thêm KHÔNG bị disabled khi trang load
                        const diaDiemInput = document.getElementById('dia_diem_cap_them');
                        const soLuongInput = document.getElementById('so_luong_cap_them');
                        const lyDoKhacInput = document.getElementById('ly_do_cap_them_khac');

                        if (diaDiemInput) {
                            diaDiemInput.disabled = false;
                            diaDiemInput.removeAttribute('disabled');
                        }
                        if (soLuongInput) {
                            soLuongInput.disabled = false;
                            soLuongInput.removeAttribute('disabled');
                        }
                        if (lyDoKhacInput) {
                            lyDoKhacInput.disabled = false;
                            lyDoKhacInput.removeAttribute('disabled');
                        }
                    });

                    // Hàm cập nhật preview cấp thêm
                    function updateCapThemPreview() {
                        // Đảm bảo tất cả inputs LUÔN enabled (không bị disable bởi bất kỳ logic nào)
                        const diaDiemInputEl = document.getElementById('dia_diem_cap_them');
                        const lyDoKhacInputEl = document.getElementById('ly_do_cap_them_khac');
                        const lyDoDisplayInputEl = document.getElementById('ly_do_cap_them_display');
                        const soLuongInputEl = document.getElementById('so_luong_cap_them');

                        if (diaDiemInputEl) {
                            diaDiemInputEl.disabled = false;
                            diaDiemInputEl.removeAttribute('disabled');
                        }
                        if (lyDoKhacInputEl) {
                            lyDoKhacInputEl.disabled = false;
                            lyDoKhacInputEl.removeAttribute('disabled');
                        }
                        if (soLuongInputEl) {
                            soLuongInputEl.disabled = false;
                            soLuongInputEl.removeAttribute('disabled');
                        }

                        const loai = document.querySelector('input[name="loai_cap_them"]:checked')?.value || 'bom_nuoc';
                        const diaDiem = diaDiemInputEl?.value.trim() || '';
                        const lyDoKhac = lyDoKhacInputEl?.value.trim() || '';
                        const soLuong = soLuongInputEl?.value || '';
                        const resultTextEl = document.getElementById('cap_them_result_text');

                        let resultText = '';
                        let displayText = '';

                        if (loai === 'khac') {
                            // Tạo text từ lý do người nhập + số lượng
                            if (lyDoKhac) {
                                displayText = soLuong ? `${lyDoKhac} x ${soLuong} lít` : lyDoKhac;
                                resultText = `CẤP THÊM: ${displayText}`;
                            } else {
                                displayText = '';
                                resultText = `CẤP THÊM: [Lý do]`;
                            }

                            // Cập nhật vào ô readonly
                            if (lyDoDisplayInputEl) {
                                lyDoDisplayInputEl.value = displayText;
                            }
                        } else if (loai === 'bom_nuoc') {
                            if (diaDiem) {
                                const lyDoBase = `Dầu ma nơ tại bến ${diaDiem} 01 chuyến`;
                                displayText = soLuong ? `${lyDoBase} x ${soLuong} lít` : lyDoBase;
                                resultText = `CẤP THÊM: ${displayText}`;
                            } else {
                                displayText = '';
                                resultText = `CẤP THÊM: Dầu ma nơ tại bến [Địa điểm] 01 chuyến`;
                            }

                            // Cập nhật vào ô readonly
                            if (lyDoDisplayInputEl) {
                                lyDoDisplayInputEl.value = displayText;
                            }
                        } else {
                            // Qua cầu
                            if (diaDiem) {
                                const lyDoBase = `Dầu bơm nước qua cầu ${diaDiem} 01 chuyến`;
                                displayText = soLuong ? `${lyDoBase} x ${soLuong} lít` : lyDoBase;
                                resultText = `CẤP THÊM: ${displayText}`;
                            } else {
                                displayText = '';
                                resultText = `CẤP THÊM: Dầu bơm nước qua cầu [Địa điểm] 01 chuyến`;
                            }

                            // Cập nhật vào ô readonly
                            if (lyDoDisplayInputEl) {
                                lyDoDisplayInputEl.value = displayText;
                            }
                        }

                        if (resultTextEl) {
                            resultTextEl.textContent = resultText.replace('CẤP THÊM: ', '');
                        }
                    }

                    // Lắng nghe thay đổi trên các input
                    const diaDiemInput = document.getElementById('dia_diem_cap_them');
                    const lyDoKhacInput = document.getElementById('ly_do_cap_them_khac');
                    const soLuongInput = document.getElementById('so_luong_cap_them');
                    const ngayCapInput = document.getElementById('ngay_cap_them');
                    const hiddenNgayDiInput = document.getElementById('ngay_di');
                    const syncNgayCapValue = () => {
                        if (hiddenNgayDiInput) {
                            hiddenNgayDiInput.value = ngayCapInput ? ngayCapInput.value : '';
                        }
                    };

                    if (diaDiemInput) {
                        diaDiemInput.addEventListener('input', updateCapThemPreview);
                        diaDiemInput.addEventListener('change', updateCapThemPreview);
                        diaDiemInput.addEventListener('blur', updateCapThemPreview);
                        // Kiểm tra thay đổi mỗi 500ms để bắt được việc chọn từ dropdown
                        setInterval(function() {
                            if (diaDiemInput.value !== diaDiemInput.dataset.lastValue) {
                                diaDiemInput.dataset.lastValue = diaDiemInput.value;
                                updateCapThemPreview();
                            }
                        }, 500);
                    }

                    if (lyDoKhacInput) {
                        lyDoKhacInput.addEventListener('input', updateCapThemPreview);
                        lyDoKhacInput.addEventListener('change', updateCapThemPreview);
                    }

                    // Auto-enable cấp thêm khi nhập số lượng
                    if (soLuongInput) {
                        soLuongInput.addEventListener('input', function() {
                            updateCapThemPreview();
                            // Auto-enable toggle và cap_them khi có nhập số lượng > 0
                            const hasQuantity = parseFloat(soLuongInput.value) > 0;
                            const toggleCheckbox = document.getElementById('toggle_cap_them');
                            const capThemHidden = document.getElementById('cap_them');

                            if (hasQuantity) {
                                // Auto-check toggle và set cap_them = 1
                                if (toggleCheckbox && !toggleCheckbox.checked) {
                                    toggleCheckbox.checked = true;
                                    toggleCapThemForm(true);
                                }
                                if (capThemHidden) {
                                    capThemHidden.value = '1';
                                }
                            }
                        });
                    }

                    if (ngayCapInput) {
                        if (hiddenNgayDiInput && !ngayCapInput.value && hiddenNgayDiInput.value) {
                            ngayCapInput.value = hiddenNgayDiInput.value;
                        }
                        ngayCapInput.addEventListener('input', () => {
                            syncNgayCapValue();
                        });
                        ngayCapInput.addEventListener('change', () => {
                            syncNgayCapValue();
                        });
                    }

                    // Cập nhật preview khi load trang
                    document.addEventListener('DOMContentLoaded', function() {
                        // Fix #2,#6,#8,#10: Bỏ required vì cấp thêm là tùy chọn
                        const diaDiemInput = document.getElementById('dia_diem_cap_them');
                        const lyDoKhacInput = document.getElementById('ly_do_cap_them_khac');

                        if (diaDiemInput) diaDiemInput.removeAttribute('required');
                        if (lyDoKhacInput) lyDoKhacInput.removeAttribute('required');

                        // FIX: Disable inputs ngay khi page load nếu form đang ẩn (để tránh validation error)
                        // NHƯNG chỉ xóa value nếu KHÔNG có dữ liệu cấp thêm (để tránh mất dữ liệu khi tính toán)
                        const card = document.getElementById('cap_them_card');
                        const toggleCheckbox = document.getElementById('toggle_cap_them');
                        const soLuongInput = document.getElementById('so_luong_cap_them');
                        const capThemHidden = document.getElementById('cap_them');

                        // Kiểm tra xem form có đang ẩn không
                        const isFormHidden = !toggleCheckbox || !toggleCheckbox.checked;

                        // Kiểm tra xem có dữ liệu cấp thêm từ PHP không (từ session sau tính toán)
                        const capThemValue = capThemHidden ? capThemHidden.value : '0';
                        const soLuongValue = soLuongInput ? soLuongInput.value : '';
                        const hasCapThemData = capThemValue == '1' || (soLuongValue && parseFloat(soLuongValue) > 0);

                        // CHỈ disable và xóa value nếu form ẩn VÀ KHÔNG có dữ liệu
                        if (card && isFormHidden && !hasCapThemData) {
                            const lyDoDisplayInput = document.getElementById('ly_do_cap_them_display');

                            // Disable tất cả inputs trong form cấp thêm
                            if (diaDiemInput) {
                                diaDiemInput.disabled = true;
                                diaDiemInput.value = '';
                            }
                            if (soLuongInput) {
                                soLuongInput.disabled = true;
                                soLuongInput.value = '';  // Xóa value để tránh vi phạm min="0.01"
                            }
                            if (lyDoKhacInput) {
                                lyDoKhacInput.disabled = true;
                                lyDoKhacInput.value = '';
                            }
                            if (lyDoDisplayInput) {
                                lyDoDisplayInput.disabled = true;
                                lyDoDisplayInput.value = '';
                            }
                        }

                        // QUAN TRỌNG: Trigger change event để hiển thị đúng form dựa trên radio được chọn
                        const selectedRadio = document.querySelector('input[name="loai_cap_them"]:checked');
                        if (selectedRadio) {
                            // Tạo và dispatch change event
                            const changeEvent = new Event('change', { bubbles: true });
                            selectedRadio.dispatchEvent(changeEvent);
                        }

                        updateCapThemPreview();

                        // Auto-show form cấp thêm nếu có dữ liệu (reuse variables đã khai báo ở trên)
                        if (capThemValue == '1' || (soLuongValue && parseFloat(soLuongValue) > 0)) {
                            if (toggleCheckbox) {
                                toggleCheckbox.checked = true;
                                toggleCapThemForm(true);

                                // Trigger change event cho radio loai_cap_them để hiển thị đúng UI
                                setTimeout(function() {
                                    const selectedLoaiCapThem = document.querySelector('input[name="loai_cap_them"]:checked');
                                    if (selectedLoaiCapThem) {
                                        const changeEvent = new Event('change', { bubbles: true });
                                        selectedLoaiCapThem.dispatchEvent(changeEvent);
                                    }
                                    updateCapThemPreview();
                                }, 200);
                            }
                        }

                        // Watchdog: Đảm bảo inputs trong form cấp thêm LUÔN enabled (check mỗi 300ms)
                        setInterval(function() {
                            const card = document.getElementById('cap_them_card');
                            // Chỉ chạy watchdog khi form đang hiển thị
                            if (card && card.style.display !== 'none') {
                                const diaDiem = document.getElementById('dia_diem_cap_them');
                                const soLuong = document.getElementById('so_luong_cap_them');
                                const lyDoKhac = document.getElementById('ly_do_cap_them_khac');

                                if (diaDiem && diaDiem.disabled) {
                                    diaDiem.disabled = false;
                                    diaDiem.removeAttribute('disabled');
                                }
                                if (soLuong && soLuong.disabled) {
                                    soLuong.disabled = false;
                                    soLuong.removeAttribute('disabled');
                                }
                                if (lyDoKhac && lyDoKhac.disabled) {
                                    lyDoKhac.disabled = false;
                                    lyDoKhac.removeAttribute('disabled');
                                }
                            }
                        }, 300);
                    });
                    </script>

                    <!-- Actions -->
                    <div class="d-grid gap-2 d-md-flex justify-content-md-start">
                        <button type="submit" name="action" value="calculate" class="btn btn-primary btn-lg">
                            <i class="fas fa-calculator me-2"></i>
                            Tính Toán Nhiên Liệu
                        </button>
                        <button type="submit" name="action" value="save" class="btn btn-success btn-lg">
                            <i class="fas fa-save me-2"></i>
                            Lưu Kết Quả
                        </button>
                        <a href="lich_su.php" class="btn btn-outline-secondary btn-lg">
                            <i class="fas fa-database me-2"></i>Xem lịch sử
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Information Panel -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Thông Tin Hướng Dẫn
                </h5>
            </div>
            <div class="card-body">
                <h6><i class="fas fa-formula me-2"></i>Công thức tính:</h6>
                <p class="small">
                    <strong>Q = [(Sch+Skh)×Kkh] + (Sch×D×Kch)</strong>
                </p>
                
                <h6><i class="fas fa-list me-2"></i>Trong đó:</h6>
                <ul class="small">
                    <li><strong>Q:</strong> Nhiên liệu tiêu thụ (Lít)</li>
                    <li><strong>Sch:</strong> Quãng đường có hàng (Km)</li>
                    <li><strong>Skh:</strong> Quãng đường không hàng (Km)</li>
                    <li><strong>Kkh:</strong> Hệ số không hàng (Lít/Km)</li>
                    <li><strong>Kch:</strong> Hệ số có hàng (Lít/T.Km)</li>
                    <li><strong>D:</strong> Khối lượng hàng hóa (Tấn)</li>
                </ul>

                <h6><i class="fas fa-lightbulb me-2"></i>Lưu ý:</h6>
                <ul class="small">
                    <li>Nếu khối lượng = 0: Tính quãng đường không hàng</li>
                    <li>Nếu khối lượng > 0: Tính quãng đường có hàng</li>
                    <li>Hệ số nhiên liệu phụ thuộc vào loại tàu và khoảng cách</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Results Section -->
<?php if ($ketQua): ?>
<div class="row mt-4" id="ket-qua-tinh-toan">
    <div class="col-12">
        <div class="card result-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>
                    Kết Quả Tính Toán
                </h5>
            </div>
            <div class="card-body">
                <?php if (isset($ketQuaCapThem) && $ketQuaCapThem && $ketQua['loai_tinh'] !== 'cap_them'): ?>
                <!-- Thông báo khi có cả hai kết quả -->
                <div class="alert alert-info mb-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Lưu ý:</strong> Khi lưu, hệ thống sẽ lưu <strong>2 bản ghi riêng biệt</strong>:
                    <ul class="mb-0 mt-2">
                        <li><strong>Bản ghi 1:</strong> Tính toán dầu cho quảng đường (<strong><?php echo number_format($ketQua['nhien_lieu_lit'], 0); ?> lít</strong>)</li>
                        <li><strong>Bản ghi 2:</strong> Cấp thêm dầu (<strong><?php echo number_format($ketQuaCapThem['nhien_lieu_lit'], 0); ?> lít</strong> - <?php echo htmlspecialchars($ketQuaCapThem['chi_tiet']['cong_thuc'] ?? ''); ?>)</li>
                    </ul>
                </div>
                <?php endif; ?>
                <div class="row">
                    <!-- Thông tin cơ bản -->
                    <div class="col-md-6">
                        <h6><i class="fas fa-ship me-2"></i>Thông tin chuyến đi:</h6>
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Tàu:</strong></td>
                                <td><?php echo htmlspecialchars($ketQua['thong_tin']['ten_tau']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Tuyến đường:</strong></td>
                                <td>
                                    <?php if ($ketQua['loai_tinh'] === 'cap_them'): ?>
                                        <span class="text-muted">—</span>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($ketQua['thong_tin']['route_hien_thi'] ?? ($ketQua['thong_tin']['diem_bat_dau'] . ' → ' . $ketQua['thong_tin']['diem_ket_thuc'])); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Khoảng cách:</strong></td>
                                <td>
                                    <?php if ($ketQua['loai_tinh'] === 'cap_them'): ?>
                                        <span class="text-muted">—</span>
                                    <?php else: ?>
                                        <?php echo number_format($ketQua['thong_tin']['khoang_cach_km'], 1); ?> km
                                        <?php if (isset($ketQua['thong_tin']['khoang_cach_thu_cong']) && $ketQua['thong_tin']['khoang_cach_thu_cong']): ?>
                                            <span class="badge bg-warning text-dark ms-2">Thủ công</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Nhóm cự ly:</strong></td>
                                <td>
                                    <?php $nhomLabel = $ketQua['thong_tin']['nhom_cu_ly_label'] ?? ''; ?>
                                    <?php if ($nhomLabel): ?>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($nhomLabel); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Khối lượng:</strong></td>
                                <td><?php echo number_format($ketQua['thong_tin']['khoi_luong_tan'], 2); ?> tấn</td>
                            </tr>
                            <tr>
                                <td><strong>Loại tính:</strong></td>
                                <td>
                                    <?php if ($ketQua['loai_tinh'] === 'cap_them'): ?>
                                        <span class="badge bg-info">Cấp thêm</span>
                                    <?php elseif ($ketQua['loai_tinh'] === 'khong_hang'): ?>
                                        <span class="badge bg-warning">Không hàng</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Có hàng</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Kết quả tính toán -->
                    <div class="col-md-6">
                        <h6><i class="fas fa-gas-pump me-2"></i>Kết quả nhiên liệu:</h6>
                        <div class="text-center">
                            <div class="display-4 text-success fw-bold">
                                <?php echo number_format($ketQua['nhien_lieu_lit'], 0); ?>
                            </div>
                            <div class="h5 text-muted">Lít</div>
                        </div>
                        
                        <hr>
                        
                        <?php if ($ketQua['loai_tinh'] !== 'cap_them'): ?>
                        <h6><i class="fas fa-cogs me-2"></i>Hệ số sử dụng:</h6>
                        <table class="table table-sm">
                            <tr>
                                <td>Hệ số không hàng (Kkh):</td>
                                <td class="text-end"><?php echo number_format($ketQua['thong_tin']['he_so_ko_hang'], 6); ?> Lít/Km</td>
                            </tr>
                            <tr>
                                <td>Hệ số có hàng (Kch):</td>
                                <td class="text-end"><?php echo number_format($ketQua['thong_tin']['he_so_co_hang'], 7); ?> Lít/T.Km</td>
                            </tr>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Chi tiết công thức -->
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="alert <?php echo ($ketQua['loai_tinh'] === 'cap_them') ? 'alert-warning' : 'alert-info'; ?>">
                            <h6><i class="fas fa-calculator me-2"></i>Chi tiết tính toán:</h6>
                            <p class="mb-0"><strong><?php echo $ketQua['chi_tiet']['cong_thuc']; ?></strong></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal chuyển đổi mã chuyến -->
<div class="modal fade" id="tripChangeModal" tabindex="-1" aria-labelledby="tripChangeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tripChangeModalLabel">
                    <i class="fas fa-exchange-alt me-2"></i>
                    Di chuyển đoạn giữa các chuyến
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-ship me-2"></i>
                                    Chuyến hiện tại
                                </h6>
                            </div>
                            <div class="card-body">
                                <p><strong>Tàu:</strong> <span id="currentShipName">-</span></p>
                                <p><strong>Mã chuyến:</strong> <span id="currentTripNumber">-</span></p>
                                <p><strong>Số đoạn:</strong> <span id="currentTripSegments">-</span></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-list me-2"></i>
                                    Chọn chuyến khác
                                </h6>
                            </div>
                            <div class="card-body">
                                <label for="newTripSelect" class="form-label">Chọn mã chuyến:</label>
                                <select class="form-select" id="newTripSelect">
                                    <option value="">-- Đang tải danh sách --</option>
                                </select>
                                <div class="mt-3">
                                    <button type="button" class="btn btn-success" onclick="changeTrip()" id="btnConfirmChange" disabled>
                                        <i class="fas fa-arrow-right me-1"></i>
                                        Di chuyển đoạn
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Danh sách các đoạn của chuyến được chọn -->
                <div class="mt-3" id="selectedTripInfo" style="display: none;">
                    <div class="card border-info">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0">
                                <i class="fas fa-list me-2"></i>
                                Chọn đoạn để chuyển sang
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning mb-3">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Lưu ý:</strong> Chọn đoạn để di chuyển sang chuyến khác. 
                                Đoạn được chọn sẽ được chuyển từ chuyến hiện tại sang chuyến đích.
                            </div>
                            <div id="selectedTripDetails">
                                <!-- Danh sách đoạn sẽ được load động -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>
                    Hủy
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal chỉnh sửa đoạn -->
<div class="modal fade" id="editSegmentModal" tabindex="-1" aria-labelledby="editSegmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editSegmentModalLabel">
                    <i class="fas fa-edit me-2"></i>
                    Chỉnh sửa đoạn
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editSegmentForm">
                    <input type="hidden" id="edit_segment_idx" name="idx">
                    <div id="edit_segment_content">
                        <!-- Nội dung sẽ được load động -->
                        <div class="text-center">
                            <i class="fas fa-spinner fa-spin"></i> Đang tải dữ liệu...
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Hủy
                </button>
                <button type="button" class="btn btn-primary" onclick="saveSegmentEdit()">
                    <i class="fas fa-save me-1"></i>Lưu thay đổi
                </button>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
// Quản lý danh sách Điểm mới (đa điểm)
window.__lastFocusedDiemMoiInput = null;
function themDiemMoi(prefill) {
    const wrapper = document.getElementById('ds_diem_moi_wrapper');
    if (!wrapper) return;
    let pointValue = '';
    let reasonValue = '';
    if (prefill) {
        if (typeof prefill === 'object' && prefill !== null) {
            pointValue = prefill.point || '';
            reasonValue = prefill.reason || '';
        } else {
            pointValue = String(prefill || '');
        }
    }
    const row = document.createElement('div');
    row.className = 'row g-2 mb-2 diem-moi-item';
    row.innerHTML = `
        <div class="col-lg-5 col-md-6">
            <div class="position-relative">
                <input type="text" class="form-control diem-input" name="diem_moi[]"
                    placeholder="Điểm tiếp theo..." autocomplete="off"
                    onfocus="showAllDiem(this.nextElementSibling, '');"
                    oninput="searchDiem(this, this.nextElementSibling)">
                <div class="dropdown-menu diem-results" style="width: 100%; max-height: 200px; overflow-y: auto;"></div>
            </div>
        </div>
        <div class="col-lg-4 col-md-5">
            <div class="d-flex flex-wrap gap-1 align-items-center reason-group">
                <input type="text" class="form-control form-control-sm diem-moi-reason" name="diem_moi_reason[]"
                    placeholder="Ghi chú (tùy chọn)" autocomplete="off">
            </div>
        </div>
        <div class="col-lg-3 col-md-12 d-flex gap-2 justify-content-lg-end">
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="xoaDiemMoi(this)"><i class="fas fa-trash-alt me-1"></i>Xóa</button>
        </div>
    `;
    wrapper.appendChild(row);
    const input = row.querySelector('input[name="diem_moi[]"]');
    const reasonInput = row.querySelector('input[name="diem_moi_reason[]"]');
    if (input) {
        input.addEventListener('focus', function(){ window.__lastFocusedDiemMoiInput = input; });
        input.addEventListener('click', function(){ window.__lastFocusedDiemMoiInput = input; });
        if (pointValue) {
            input.value = pointValue;
            input.readOnly = true;
            input.placeholder = 'Đã chọn: ' + pointValue;
            input.dataset.prefilled = '1';
        }
    }
    if (reasonInput && reasonValue) {
        reasonInput.value = reasonValue;
    }
    updateDiemMoiPlaceholders();
}
function xoaDiemMoi(btn) {
    const item = btn.closest('.diem-moi-item');
    if (item) {
        const wrapper = document.getElementById('ds_diem_moi_wrapper');
        // Luôn giữ ít nhất 1 hàng để người dùng nhập
        if (wrapper && wrapper.querySelectorAll('.diem-moi-item').length <= 1) {
            const input = item.querySelector('input[name="diem_moi[]"]');
            const reasonInput = item.querySelector('input[name="diem_moi_reason[]"]');
            if (input) {
                input.value = '';
                input.readOnly = false;
            }
            if (reasonInput) {
                reasonInput.value = '';
            }
            updateDiemMoiPlaceholders();
            return;
        }
        item.remove();
    }
    if (window.__lastFocusedDiemMoiInput && !document.body.contains(window.__lastFocusedDiemMoiInput)) {
        window.__lastFocusedDiemMoiInput = null;
    }
    updateDiemMoiPlaceholders();
}
function setDiemMoiReason(btn, reason) {
    const item = btn.closest('.diem-moi-item');
    if (!item) return;
    const reasonInput = item.querySelector('input[name="diem_moi_reason[]"]');
    if (reasonInput) {
        reasonInput.value = reason;
        reasonInput.focus();
    }
}
function resetDiemDangChon() {
    let input = window.__lastFocusedDiemMoiInput;
    if (!input || !input.closest('#ds_diem_moi_wrapper')) {
        input = document.querySelector('#ds_diem_moi_wrapper input[name="diem_moi[]"]');
    }
    if (!input) return;
    const item = input.closest('.diem-moi-item');
    input.value = '';
    input.readOnly = false;
    input.dataset.prefilled = '';
    const dropdown = input.nextElementSibling;
    if (dropdown) {
        dropdown.style.display = 'none';
        dropdown.innerHTML = '';
    }
    if (item) {
        const reasonInput = item.querySelector('input[name="diem_moi_reason[]"]');
        if (reasonInput) reasonInput.value = '';
    }
    updateDiemMoiPlaceholders();
    input.focus();
}
function updateDiemMoiPlaceholders() {
    const rows = document.querySelectorAll('#ds_diem_moi_wrapper .diem-moi-item');
    rows.forEach((row, idx) => {
        const input = row.querySelector('input[name="diem_moi[]"]');
        if (!input) return;
        const placeholder = idx === 0 ? 'Điểm C - Bắt đầu nhập để tìm kiếm...' : 'Điểm tiếp theo...';
        if (!input.readOnly || input.value === '') {
            input.placeholder = placeholder;
        }
        input.dataset.index = String(idx);
    });
}
// Khởi tạo ít nhất một ô Điểm mới khi mở modal/hiển thị vùng đổi lệnh
document.addEventListener('DOMContentLoaded', function() {
    const wrapper = document.getElementById('ds_diem_moi_wrapper');
    if (wrapper) {
        const prefillNode = document.getElementById('prefilled_diem_moi_json');
        let prefilledList = [];
        if (prefillNode && prefillNode.value) {
            try {
                const parsed = JSON.parse(prefillNode.value);
                if (Array.isArray(parsed)) {
                    prefilledList = parsed.filter(item => {
                        if (typeof item === 'string') {
                            return item.trim().length > 0;
                        }
                        if (item && typeof item === 'object') {
                            return String(item.point || '').trim().length > 0;
                        }
                        return false;
                    });
                }
            } catch(_){}
        }
        if (prefilledList.length > 0) {
            wrapper.innerHTML = '';
            prefilledList.forEach(item => themDiemMoi(item));
        }
        if (wrapper.querySelectorAll('.diem-moi-item').length === 0) {
            themDiemMoi();
        } else {
            updateDiemMoiPlaceholders();
        }
    }
});
</script>