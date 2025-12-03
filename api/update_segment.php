<?php
/**
 * API để cập nhật thông tin một đoạn trong mã chuyến
 */

header('Content-Type: application/json; charset=utf-8');

// Bắt đầu output buffering để tránh lỗi khi có warning/notice
while (ob_get_level() > 0) { @ob_end_clean(); }
@ob_start();
@ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/LuuKetQua.php';
require_once __DIR__ . '/../models/TinhToanNhienLieu.php';

try {
    // Log để debug
    error_log('update_segment.php - POST data: ' . print_r($_POST, true));
    
    $idx = isset($_POST['idx']) ? (int)$_POST['idx'] : 0; // ___idx 1-based
    
    if ($idx <= 0) {
        throw new Exception('Thiếu hoặc sai idx. Giá trị nhận được: ' . var_export($_POST['idx'] ?? 'không có', true));
    }
    
    // Lấy dữ liệu hiện tại
    $luuKetQua = new LuuKetQua();
    $allData = $luuKetQua->docTatCa();
    
    $target = null;
    foreach ($allData as $row) {
        $currentIdx = (int)($row['___idx'] ?? 0);
        if ($currentIdx === $idx) {
            $target = $row;
            break;
        }
    }
    
    if (!$target) {
        throw new Exception('Không tìm thấy đoạn cần sửa với idx=' . $idx);
    }
    
    // Lưu bản ghi gốc để giữ nguyên các trường không được cập nhật
    $originalTarget = $target;
    
    $isCapThem = (int)($target['cap_them'] ?? 0) === 1;
    
    if ($isCapThem) {
        // Cập nhật cấp thêm
        $lyDoCapThem = trim($_POST['ly_do_cap_them'] ?? '');
        $soLuongCapThem = floatval($_POST['so_luong_cap_them_lit'] ?? 0);
        
        $target['ly_do_cap_them'] = $lyDoCapThem;
        $target['so_luong_cap_them_lit'] = $soLuongCapThem;
        
        // Cập nhật ngày
        $ngayDiStr = trim($_POST['ngay_di'] ?? '');
        if ($ngayDiStr !== '') {
            $ngayDi = parse_date_vn($ngayDiStr);
            if ($ngayDi !== false) {
                $target['ngay_di'] = $ngayDi;
            }
        }
    } else {
        // Cập nhật đoạn thường
        // Validate dữ liệu đầu vào
        if (!isset($_POST['diem_di']) || trim($_POST['diem_di']) === '') {
            throw new Exception('Thiếu điểm đi');
        }
        if (!isset($_POST['diem_den']) || trim($_POST['diem_den']) === '') {
            throw new Exception('Thiếu điểm đến');
        }
        
        $tenTau = isset($_POST['ten_tau']) && trim($_POST['ten_tau']) !== '' 
            ? trim($_POST['ten_tau']) 
            : ($target['ten_phuong_tien'] ?? '');
        
        if (empty($tenTau)) {
            throw new Exception('Thiếu tên tàu');
        }
        
        $diemDi = trim($_POST['diem_di']);
        $diemDen = trim($_POST['diem_den']);
        $khoiLuong = isset($_POST['khoi_luong_van_chuyen_t']) ? floatval($_POST['khoi_luong_van_chuyen_t']) : (float)($target['khoi_luong_van_chuyen_t'] ?? 0);
        $loaiHang = isset($_POST['loai_hang']) ? trim($_POST['loai_hang']) : ($target['loai_hang'] ?? '');
        $ghiChu = isset($_POST['ghi_chu']) ? trim($_POST['ghi_chu']) : ($target['ghi_chu'] ?? '');
        
        // Cập nhật ngày (chỉ cập nhật nếu có giá trị mới)
        $ngayDiStr = isset($_POST['ngay_di']) ? trim($_POST['ngay_di']) : '';
        $ngayDenStr = isset($_POST['ngay_den']) ? trim($_POST['ngay_den']) : '';
        $ngayDxStr = isset($_POST['ngay_do_xong']) ? trim($_POST['ngay_do_xong']) : '';
        
        if ($ngayDiStr !== '') {
            $ngayDi = parse_date_vn($ngayDiStr);
            $target['ngay_di'] = ($ngayDi !== false) ? $ngayDi : ($target['ngay_di'] ?? '');
        }
        if ($ngayDenStr !== '') {
            $ngayDen = parse_date_vn($ngayDenStr);
            $target['ngay_den'] = ($ngayDen !== false) ? $ngayDen : ($target['ngay_den'] ?? '');
        }
        if ($ngayDxStr !== '') {
            $ngayDx = parse_date_vn($ngayDxStr);
            $target['ngay_do_xong'] = ($ngayDx !== false) ? $ngayDx : ($target['ngay_do_xong'] ?? '');
        }
        
        // Kiểm tra xem có thay đổi tuyến đường (điểm đi/điểm đến) không
        $diemDiCu = trim((string)($target['diem_di'] ?? ''));
        $diemDenCu = trim((string)($target['diem_den'] ?? ''));
        $diemDiMoi = trim($diemDi);
        $diemDenMoi = trim($diemDen);
        
        // Tách tên điểm gốc (loại bỏ ghi chú trong ngoặc fullwidth và ngoặc thường)
        $diemDiCuGoc = preg_replace('/\s*（[^）]*）\s*$/', '', $diemDiCu);
        $diemDiCuGoc = preg_replace('/\s*\([^)]*\)\s*$/', '', $diemDiCuGoc);
        $diemDenCuGoc = preg_replace('/\s*（[^）]*）\s*$/', '', $diemDenCu);
        $diemDenCuGoc = preg_replace('/\s*\([^)]*\)\s*$/', '', $diemDenCuGoc);
        
        $diemDiMoiGoc = preg_replace('/\s*（[^）]*）\s*$/', '', $diemDiMoi);
        $diemDiMoiGoc = preg_replace('/\s*\([^)]*\)\s*$/', '', $diemDiMoiGoc);
        $diemDenMoiGoc = preg_replace('/\s*（[^）]*）\s*$/', '', $diemDenMoi);
        $diemDenMoiGoc = preg_replace('/\s*\([^)]*\)\s*$/', '', $diemDenMoiGoc);
        
        $tuyenDuongThayDoi = ($diemDiCuGoc !== $diemDiMoiGoc || $diemDenCuGoc !== $diemDenMoiGoc);
        $khoiLuongThayDoi = ($khoiLuong != (float)($target['khoi_luong_van_chuyen_t'] ?? 0));
        
        // Nếu tuyến đường thay đổi, PHẢI tính toán lại từ đầu với hệ số mới
        if ($tuyenDuongThayDoi) {
            $tinhToan = new TinhToanNhienLieu();
            $ketQua = null;
            $lastError = null;
            
            // Thử tính toán với các biến thể tên điểm
            // 1. Thử với tên đầy đủ (có phần trong ngoặc) trước
            try {
                $ketQua = $tinhToan->tinhNhienLieu($tenTau, $diemDiMoi, $diemDenMoi, $khoiLuong);
            } catch (Exception $e1) {
                $lastError = $e1;
                // 2. Nếu không được, thử với tên đã loại bỏ ngoặc
                try {
                    $ketQua = $tinhToan->tinhNhienLieu($tenTau, $diemDiMoiGoc, $diemDenMoiGoc, $khoiLuong);
                } catch (Exception $e2) {
                    $lastError = $e2;
                    // 3. Thử với điểm đi đầy đủ và điểm đến đã loại bỏ ngoặc
                    try {
                        $ketQua = $tinhToan->tinhNhienLieu($tenTau, $diemDiMoi, $diemDenMoiGoc, $khoiLuong);
                    } catch (Exception $e3) {
                        // 4. Thử với điểm đi đã loại bỏ ngoặc và điểm đến đầy đủ
                        try {
                            $ketQua = $tinhToan->tinhNhienLieu($tenTau, $diemDiMoiGoc, $diemDenMoi, $khoiLuong);
                        } catch (Exception $e4) {
                            $lastError = $e4;
                        }
                    }
                }
            }
            
            if ($ketQua === null) {
                // Nếu không tính được với bất kỳ biến thể nào, báo lỗi
                throw new Exception('Không tìm thấy tuyến đường giữa "' . $diemDiMoi . '" và "' . $diemDenMoi . '". ' . ($lastError ? $lastError->getMessage() : ''));
            }
            
            // Cập nhật TẤT CẢ các trường liên quan với dữ liệu mới
            $target['diem_di'] = $diemDiMoi;
            $target['diem_den'] = $diemDenMoi;
            $target['diem_du_kien'] = $diemDenMoi; // Cập nhật điểm dự kiến
            $target['khoi_luong_van_chuyen_t'] = $khoiLuong;
            
            // Cập nhật cự ly (tính lại từ đầu)
            $target['cu_ly_co_hang_km'] = $ketQua['chi_tiet']['sch'] ?? 0;
            $target['cu_ly_khong_hang_km'] = $ketQua['chi_tiet']['skh'] ?? 0;
            
            // Cập nhật hệ số (lấy từ hệ số mới của tuyến đường mới)
            $target['he_so_co_hang'] = $ketQua['thong_tin']['he_so_co_hang'] ?? 0;
            $target['he_so_khong_hang'] = $ketQua['thong_tin']['he_so_ko_hang'] ?? 0;
            
            // Cập nhật nhiên liệu (tính lại với hệ số mới)
            $target['dau_tinh_toan_lit'] = $ketQua['nhien_lieu_lit'] ?? 0;
            
            // Cập nhật khối lượng luân chuyển
            $target['khoi_luong_luan_chuyen'] = ($ketQua['chi_tiet']['sch'] ?? 0) * $khoiLuong;
            
            // Cập nhật nhóm cự ly (có thể thay đổi nếu khoảng cách thay đổi)
            $target['nhom_cu_ly'] = $ketQua['thong_tin']['nhom_cu_ly'] ?? '';
            
            // Cập nhật route_hien_thi (tuyến đường hiển thị)
            $target['route_hien_thi'] = $diemDiMoi . ' → ' . $diemDenMoi;
            
            // Reset đổi lệnh nếu tuyến đường thay đổi (vì đây là tuyến mới)
            $target['doi_lenh'] = 0;
            $target['doi_lenh_tuyen'] = '';
        } elseif ($khoiLuongThayDoi) {
            // Chỉ khối lượng thay đổi, tuyến đường giữ nguyên
            // Tính lại với hệ số hiện tại (không cần tính lại hệ số)
            $schOld = (float)($target['cu_ly_co_hang_km'] ?? 0);
            $skhOld = (float)($target['cu_ly_khong_hang_km'] ?? 0);
            $totalKm = $schOld + $skhOld;
            $kkh = (float)($target['he_so_khong_hang'] ?? 0);
            $kch = (float)($target['he_so_co_hang'] ?? 0);
            
            // Phân bổ lại cự ly dựa trên khối lượng mới
            $sch = ($khoiLuong > 0) ? $totalKm : 0.0;
            $skh = ($khoiLuong > 0) ? 0.0 : $totalKm;
            
            // Tính lại nhiên liệu với hệ số hiện tại
            $kllc = $sch * $khoiLuong;
            $Q = (($sch + $skh) * $kkh) + ($sch * $khoiLuong * $kch);
            
            // Cập nhật các trường
            $target['khoi_luong_van_chuyen_t'] = $khoiLuong;
            $target['khoi_luong_luan_chuyen'] = $kllc;
            $target['dau_tinh_toan_lit'] = round($Q, 2);
            $target['cu_ly_co_hang_km'] = $sch;
            $target['cu_ly_khong_hang_km'] = $skh;
        } else {
            // Không có thay đổi về tuyến đường hay khối lượng, chỉ cập nhật thông tin khác
            $target['diem_di'] = $diemDiMoi;
            $target['diem_den'] = $diemDenMoi;
        }
        
        // Cập nhật loại hàng và ghi chú (luôn cập nhật, kể cả khi rỗng)
        $target['loai_hang'] = $loaiHang;
        $target['ghi_chu'] = $ghiChu;
    }
    
    // Đảm bảo tất cả các trường cần thiết có giá trị
    // Lấy danh sách headers từ file để đảm bảo có đủ trường
    $headers = [];
    if (file_exists(KET_QUA_FILE)) {
        $fh = fopen(KET_QUA_FILE, 'r');
        if ($fh) {
            $headerLine = fgetcsv($fh);
            if ($headerLine) {
                $headers = $headerLine;
            }
            fclose($fh);
        }
    }
    
    // Đảm bảo tất cả các trường trong headers đều có trong $target
    // Giữ nguyên giá trị cũ nếu chưa được cập nhật
    foreach ($headers as $header) {
        if (!isset($target[$header])) {
            // Giữ nguyên giá trị từ bản ghi gốc nếu có
            $target[$header] = $originalTarget[$header] ?? '';
        }
    }
    
    // Loại bỏ các trường không có trong headers (như ___idx, __row_index)
    $target = array_intersect_key($target, array_flip($headers));
    
    // Đảm bảo thứ tự theo headers
    $targetOrdered = [];
    foreach ($headers as $header) {
        $targetOrdered[$header] = $target[$header] ?? '';
    }
    $target = $targetOrdered;
    
    // Log để debug (chỉ log một số trường quan trọng để tránh log quá dài)
    error_log('update_segment.php - Updating idx: ' . $idx . ', ten_phuong_tien: ' . ($target['ten_phuong_tien'] ?? 'N/A'));
    
    // Lưu lại
    $result = $luuKetQua->capNhat($idx, $target);
    
    if (!$result) {
        error_log('update_segment.php - capNhat returned false for idx: ' . $idx);
        // Kiểm tra xem file có tồn tại và có đủ dòng không
        $lineCount = 0;
        if (file_exists(KET_QUA_FILE)) {
            $fh = fopen(KET_QUA_FILE, 'r');
            if ($fh) {
                while (fgets($fh) !== false) {
                    $lineCount++;
                }
                fclose($fh);
            }
        }
        throw new Exception('Không thể cập nhật dữ liệu. Index: ' . $idx . ', Tổng số dòng trong file: ' . $lineCount);
    }
    
    // Xóa output buffer trước khi trả về JSON
    while (ob_get_level() > 0) { @ob_end_clean(); }
    
    echo json_encode([
        'success' => true,
        'message' => 'Đã cập nhật đoạn thành công'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Xóa output buffer trước khi trả về JSON
    while (ob_get_level() > 0) { @ob_end_clean(); }
    
    // Log lỗi để debug
    error_log('update_segment.php error: ' . $e->getMessage());
    error_log('POST data: ' . print_r($_POST, true));
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    // Xóa output buffer trước khi trả về JSON
    while (ob_get_level() > 0) { @ob_end_clean(); }
    
    // Log lỗi để debug
    error_log('update_segment.php fatal error: ' . $e->getMessage());
    error_log('POST data: ' . print_r($_POST, true));
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Lỗi hệ thống: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

