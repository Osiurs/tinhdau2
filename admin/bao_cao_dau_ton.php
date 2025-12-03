<?php
/**
 * Trang Báo cáo nhiên liệu sử dụng và tồn kho theo tháng
 * Cho phép xem và xuất báo cáo nhiên liệu của từng tàu từ đầu năm đến ngày được chọn
 */

require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/HeSoTau.php';
require_once __DIR__ . '/../models/DauTon.php';
require_once __DIR__ . '/../includes/add_header_to_sheet.php'; // Helper thêm header template
require_once __DIR__ . '/../models/LuuKetQua.php';

// Xử lý xuất Excel
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    // Bảo đảm không có dữ liệu nào đã đẩy ra trước khi in XML
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) { @ob_end_clean(); }
    }
    // Tránh mọi warning/notice chen vào XML
    if (function_exists('ini_set')) {
        @ini_set('display_errors', '0');
        @ini_set('display_startup_errors', '0');
    }
    @error_reporting(0);
    
    // Lấy tham số
    $ngayBaoCao = isset($_GET['ngay']) ? $_GET['ngay'] : date('Y-m-d');
    $namHienTai = date('Y', strtotime($ngayBaoCao));
    
    // Khởi tạo các model
    $dauTon = new DauTon();
    $ketQua = new LuuKetQua();
    
    // Lấy danh sách tàu từ file CSV
    $danhSachTau = [];
    $dauTonFile = __DIR__ . '/../data/dau_ton.csv';
    if (file_exists($dauTonFile)) {
        $lines = file($dauTonFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $isFirstLine = true;
        foreach ($lines as $line) {
            if ($isFirstLine) {
                $isFirstLine = false;
                continue; // Bỏ qua header
            }
            $data = str_getcsv($line);
            if (count($data) >= 1 && !in_array($data[0], $danhSachTau)) {
                $danhSachTau[] = $data[0]; // Cột đầu tiên là ten_phuong_tien
            }
        }
    }
    sort($danhSachTau);
    
    
    // Tính dữ liệu báo cáo theo tháng
    function tinhDuLieuTheoThangTatCa($danhSachTau, $nam, $denNgay, $dauTon, $ketQua) {
        $baoCaos = [];
        
        // Tính số tháng từ đầu năm đến ngày được chọn
        $thangDen = date('m', strtotime($denNgay));
        
        for ($thang = 1; $thang <= $thangDen; $thang++) {
            $ngayDauThang = "$nam-" . sprintf('%02d', $thang) . "-01";
            $ngayCuoiThang = date('Y-m-t', strtotime($ngayDauThang));
            
            // Chỉ tính đến ngày được chọn
            if ($ngayCuoiThang > $denNgay) {
                $ngayCuoiThang = $denNgay;
            }
            
            $phuongTien = [];
            
            foreach ($danhSachTau as $tenTau) {
                // Tính số dư đầu kỳ (cuối tháng trước)
                $soDuDauKy = 0;
                if ($thang > 1) {
                    $ngayCuoiThangTruoc = date('Y-m-t', strtotime("$nam-" . sprintf('%02d', $thang - 1) . "-01"));
                    $soDuDauKy = $dauTon->tinhSoDu($tenTau, $ngayCuoiThangTruoc);
                } else {
                    // Tháng đầu tiên: tính số dư từ đầu năm đến ngày đầu tháng
                    $ngayDauNam = "$nam-01-01";
                    $ngayDauThang = "$nam-" . sprintf('%02d', $thang) . "-01";
                    $soDuDauKy = $dauTon->tinhSoDu($tenTau, $ngayDauThang);
                }
                
                // Tính dầu cấp trong tháng (chỉ cap_them, KHÔNG tính tinh_chinh)
                $dauCap = 0;
                $lichSuGiaoDich = $dauTon->getLichSuGiaoDich($tenTau);
                foreach ($lichSuGiaoDich as $giaoDich) {
                    $ngayGiaoDich = $giaoDich['ngay'] ?? '';
                    if ($ngayGiaoDich >= $ngayDauThang && $ngayGiaoDich <= $ngayCuoiThang) {
                        if ($giaoDich['loai'] === 'cap_them') {
                            $dauCap += (float)($giaoDich['so_luong_lit'] ?? 0);
                        }
                        // BỎ TINH CHỈNH: Không tính tinh_chinh vào báo cáo CT nữa
                    }
                }
                
                // Tính dầu sử dụng - sử dụng logic đồng bộ với hàm hiển thị web
                $dauSuDungKhongHang = 0;
                $dauSuDungCoHang = 0;
                
                $ketQuaTinhToan = $ketQua->docTatCa();
                foreach ($ketQuaTinhToan as $kq) {
                    if (($kq['ten_phuong_tien'] ?? '') !== $tenTau) continue;
                    
                    // Xác định ngày tính tiêu hao: ưu tiên ngày dỡ xong -> ngày đến -> ngày đi -> created_at
                    $ngayTieuHao = '';
                    $ngayDoXong = $kq['ngay_do_xong'] ?? '';
                    if ($ngayDoXong) {
                        $ngayTieuHao = parse_date_vn($ngayDoXong) ?: '';
                    }
                    if ($ngayTieuHao === '' && !empty($kq['ngay_den'])) {
                        $ngayTieuHao = parse_date_vn($kq['ngay_den']) ?: '';
                    }
                    if ($ngayTieuHao === '' && !empty($kq['ngay_di'])) {
                        $ngayTieuHao = parse_date_vn($kq['ngay_di']) ?: '';
                    }
                    if ($ngayTieuHao === '' && !empty($kq['created_at'])) {
                        $ngayTieuHao = substr((string)$kq['created_at'], 0, 10);
                    }
                    
                    if ($ngayTieuHao >= $ngayDauThang && $ngayTieuHao <= $ngayCuoiThang) {
                        // Xử lý cấp thêm dầu (đồng bộ hoàn toàn với Model DauTon)
                        $isCapThem = (int)($kq['cap_them'] ?? 0) === 1;
                        if ($isCapThem) {
                            $dauTieuHao = floor((float)($kq['so_luong_cap_them_lit'] ?? 0));
                        } else {
                            $dauTieuHao = floor((float)($kq['dau_tinh_toan_lit'] ?? 0));
                        }
                        
                        // Phân loại có hàng/không hàng dựa trên khối lượng vận chuyển
                        $khoiLuongVanChuyen = (float)($kq['khoi_luong_van_chuyen_t'] ?? 0);
                        if ($khoiLuongVanChuyen > 0) {
                            $dauSuDungCoHang += $dauTieuHao;
                        } else {
                            $dauSuDungKhongHang += $dauTieuHao;
                        }
                    }
                }

                // Xử lý TINH CHỈNH: tách riêng tinh chỉnh thông thường và chuyển dầu
                $chuyenDauDi = 0;  // Dầu chuyển đi (dương)
                $nhanDauVe = 0;    // Dầu nhận về (dương)
                
                $lichSuGiaoDich = $dauTon->getLichSuGiaoDich($tenTau);
                foreach ($lichSuGiaoDich as $gd) {
                    if (($gd['loai'] ?? '') !== 'tinh_chinh') { continue; }

                    $ngayGd = (string)($gd['ngay'] ?? '');
                    if ($ngayGd < $ngayDauThang || $ngayGd > $ngayCuoiThang) { continue; }

                    $amount = (float)($gd['so_luong_lit'] ?? 0);
                    $lyDo = (string)($gd['ly_do'] ?? '');
                    
                    // Phân loại tinh chỉnh
                    // Nhận diện chuyển dầu: tìm "chuyển sang" hoặc "nhận từ" (có thể có ký tự mũi tên → hoặc ←)
                    $isChuyenDau = (
                        strpos($lyDo, 'chuyển sang') !== false || 
                        strpos($lyDo, 'nhận từ') !== false ||
                        preg_match('/→\s*chuyển\s+sang|←\s*nhận\s+từ/u', $lyDo)
                    );
                    
                    if ($isChuyenDau) {
                        // Lệnh chuyển dầu: tách riêng
                        if ($amount < 0) {
                            $chuyenDauDi += abs($amount);  // Chuyển đi (dương)
                        } else {
                            $nhanDauVe += $amount;  // Nhận về (dương)
                        }
                    } else {
                        // Tinh chỉnh thông thường: tính vào dầu cấp
                        $dauCap += $amount;
                    }
                }

                $tongDauSuDung = $dauSuDungKhongHang + $dauSuDungCoHang;

                // Tính số dư cuối kỳ: Sử dụng Model DauTon
                // QUAN TRỌNG: Tính đến CUỐI THÁNG (ngayCuoiThang), không phải hôm nay
                $soDuCuoiKy = $dauTon->tinhSoDu($tenTau, $ngayCuoiThang);
                
                // Thêm tất cả phương tiện vào báo cáo (như trên web)
                if (true) {
                    $phuongTien[] = [
                        'ten_tau' => $tenTau,
                        'so_du_dau_ky' => $soDuDauKy,
                        'dau_cap' => $dauCap,
                        'chuyen_dau_di' => $chuyenDauDi,
                        'nhan_dau_ve' => $nhanDauVe,
                        'dau_su_dung_khong_hang' => $dauSuDungKhongHang,
                        'dau_su_dung_co_hang' => $dauSuDungCoHang,
                        'tong_dau_su_dung' => $tongDauSuDung,
                        'so_du_cuoi_ky' => $soDuCuoiKy,
                        'ghi_chu' => ''
                    ];
                }
            }
            
            if (!empty($phuongTien)) {
                $baoCaos[] = [
                    'thang' => $thang,
                    'ngay_cuoi_thang' => $ngayCuoiThang,
                    'phuong_tien' => $phuongTien
                ];
            }
        }
        
        return $baoCaos;
    }
    
    // Lấy dữ liệu thực từ hệ thống
    $baoCaos = tinhDuLieuTheoThangTatCa($danhSachTau, $namHienTai, $ngayBaoCao, $dauTon, $ketQua);
    
    
    
    // Nếu yêu cầu XLSX với template (logo/header chuẩn)
    if (isset($_GET['export']) && $_GET['export'] === 'excel' && isset($_GET['xlsx'])) {
        require_once __DIR__ . '/../includes/excel_export_wrapper.php';

        // Chuẩn bị dữ liệu theo cấu trúc wrapper
        $sheets = [];
        $headers = ['STT','PHƯƠNG TIỆN','DẦU TỒN ĐẦU KỲ','DẦU CẤP','CHUYỂN DẦU ĐI','NHẬN DẦU VỀ','DẦU SỬ DỤNG KHÔNG HÀNG','DẦU SỬ DỤNG CÓ HÀNG','TỔNG DẦU SỬ DỤNG','DẦU TỒN CUỐI KỲ','GHI CHÚ'];

        $tenThang = [1=>'THÁNG 1',2=>'THÁNG 2',3=>'THÁNG 3',4=>'THÁNG 4',5=>'THÁNG 5',6=>'THÁNG 6',7=>'THÁNG 7',8=>'THÁNG 8',9=>'THÁNG 9',10=>'THÁNG 10',11=>'THÁNG 11',12=>'THÁNG 12'];

        foreach ($baoCaos as $thang) {
            // Bỏ qua tháng không có dữ liệu thực (bao gồm cả chuyển dầu)
            $hasData = false;
            foreach ($thang['phuong_tien'] as $pt) {
                if ($pt['so_du_dau_ky'] != 0 || $pt['dau_cap'] != 0 || ($pt['chuyen_dau_di'] ?? 0) != 0 || ($pt['nhan_dau_ve'] ?? 0) != 0 || $pt['tong_dau_su_dung'] != 0 || $pt['so_du_cuoi_ky'] != 0) {
                    $hasData = true; break;
                }
            }
            if (!$hasData) continue;

            $rows = [];
            $stt = 0;
            foreach ($thang['phuong_tien'] as $pt) {
                // Chỉ hiển thị phương tiện có dữ liệu (bao gồm cả chuyển dầu)
                if ($pt['so_du_dau_ky'] == 0 && $pt['dau_cap'] == 0 && ($pt['chuyen_dau_di'] ?? 0) == 0 && ($pt['nhan_dau_ve'] ?? 0) == 0 && $pt['tong_dau_su_dung'] == 0 && $pt['so_du_cuoi_ky'] == 0) {
                    continue;
                }
                $stt++;
                $rows[] = [
                    $stt,
                    (string)($pt['ten_tau'] ?? ''),
                    (int)$pt['so_du_dau_ky'],
                    (int)$pt['dau_cap'],
                    (int)$pt['chuyen_dau_di'],
                    (int)$pt['nhan_dau_ve'],
                    (int)$pt['dau_su_dung_khong_hang'],
                    (int)$pt['dau_su_dung_co_hang'],
                    (int)$pt['tong_dau_su_dung'],
                    (int)$pt['so_du_cuoi_ky'],
                    (string)($pt['ghi_chu'] ?? ''),
                ];
            }

            // Thêm dòng tổng
            $tongSoDuDauKy = 0; $tongDauCap = 0; $tongChuyenDauDi = 0; $tongNhanDauVe = 0; $tongDauSuDungKhongHang = 0; $tongDauSuDungCoHang = 0; $tongDauSuDung = 0; $tongSoDuCuoiKy = 0;
            foreach ($thang['phuong_tien'] as $pt) {
                if ($pt['so_du_dau_ky'] != 0 || $pt['dau_cap'] != 0 || $pt['tong_dau_su_dung'] != 0 || $pt['so_du_cuoi_ky'] != 0) {
                    $tongSoDuDauKy += (int)$pt['so_du_dau_ky'];
                    $tongDauCap += (int)$pt['dau_cap'];
                    $tongChuyenDauDi += (int)($pt['chuyen_dau_di'] ?? 0);
                    $tongNhanDauVe += (int)($pt['nhan_dau_ve'] ?? 0);
                    $tongDauSuDungKhongHang += (int)$pt['dau_su_dung_khong_hang'];
                    $tongDauSuDungCoHang += (int)$pt['dau_su_dung_co_hang'];
                    $tongDauSuDung += (int)$pt['tong_dau_su_dung'];
                    $tongSoDuCuoiKy += (int)$pt['so_du_cuoi_ky'];
                }
            }
            $rows[] = ['', 'Tổng', $tongSoDuDauKy, $tongDauCap, $tongChuyenDauDi, $tongNhanDauVe, $tongDauSuDungKhongHang, $tongDauSuDungCoHang, $tongDauSuDung, $tongSoDuCuoiKy, ''];

            $sheetName = ($tenThang[$thang['thang']] ?? ('Thang ' . (int)$thang['thang'])) . '-' . $namHienTai;
            $sheets[] = [
                'name' => $sheetName,
                'headers' => $headers,
                'rows' => $rows,
            ];
        }

        $exportData = ['sheets' => $sheets];
        exportLichSuWithTemplate($exportData, [
            'filename' => 'Bao_cao_nhien_lieu_tat_ca_phuong_tien_' . $namHienTai . '.xlsx',
            'currentMonth' => (int)date('n'),
            'currentYear' => (int)date('Y'),
            'isDetailedExport' => false,
        ]);
        exit;
    }

    // Chỉ xuất báo cáo nếu có dữ liệu thực
    if (empty($baoCaos)) {
        // Không có dữ liệu, xuất file trống với thông báo
        $filename = 'Bao_cao_nhien_lieu_tat_ca_phuong_tien_' . $namHienTai . '.xls';
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo "<?mso-application progid=\"Excel.Sheet\"?>\n";
        echo "<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\"\n";
        echo " xmlns:o=\"urn:schemas-microsoft-com:office:office\"\n";
        echo " xmlns:x=\"urn:schemas-microsoft-com:office:excel\"\n";
        echo " xmlns:ss=\"urn:schemas-microsoft-com:office:spreadsheet\"\n";
        echo " xmlns:html=\"http://www.w3.org/TR/REC-html40\">\n";
        echo "<Worksheet ss:Name=\"Báo cáo nhiên liệu\">\n";
        echo "<Table>\n";
        echo "<Row>\n";
        echo "<Cell ss:MergeAcross=\"8\"><Data ss:Type=\"String\">KHÔNG CÓ DỮ LIỆU ĐỂ XUẤT BÁO CÁO</Data></Cell>\n";
        echo "</Row>\n";
        echo "</Table>\n";
        echo "</Worksheet>\n";
        echo "</Workbook>\n";
        exit;
    }
    
    // Header tải về
    $filename = 'Bao_cao_nhien_lieu_tat_ca_phuong_tien_' . $namHienTai . '.xls';
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
    
    echo '<Styles>';

    // Style cho header template
    echo '<Style ss:ID="TitleSub">'
        . '<Font ss:Bold="1" ss:Size="11" ss:Color="#34495E"/>'
        . '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
        . '</Style>';

    // Style cho ô logo
    echo '<Style ss:ID="LogoCell">'
        . '<Font ss:Bold="1" ss:Size="10" ss:Color="#7F7F7F"/>'
        . '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
        . '</Style>';

    // Style cho tiêu đề chính (đậm, màu đen, căn giữa)
    echo '<Style ss:ID="MainTitle">';
    echo '<Font ss:Bold="1" ss:Size="12" ss:Color="#000000"/>';
    echo '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>';
    echo '<Borders>';
    echo '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '</Borders>';
    echo '</Style>';
    
    // Style cho tiêu đề tháng (đậm, màu đỏ, căn giữa)
    echo '<Style ss:ID="MonthTitle">';
    echo '<Font ss:Bold="1" ss:Size="12" ss:Color="#FF0000"/>';
    echo '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>';
    echo '<Borders>';
    echo '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '</Borders>';
    echo '</Style>';
    
    // Style cho tiêu đề tháng năm (đậm, màu đen, căn giữa)
    echo '<Style ss:ID="MonthYearTitle">';
    echo '<Font ss:Bold="1" ss:Size="12" ss:Color="#000000"/>';
    echo '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>';
    echo '<Borders>';
    echo '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '</Borders>';
    echo '</Style>';
    
    // Style cho header cột (đậm, căn giữa)
    echo '<Style ss:ID="Header">';
    echo '<Font ss:Bold="1" ss:Size="10"/>';
    echo '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>';
    echo '<Borders>';
    echo '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '</Borders>';
    echo '</Style>';
    
    // Style cho cột STT (căn giữa)
    echo '<Style ss:ID="STT">';
    echo '<Font ss:Size="10"/>';
    echo '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>';
    echo '<Borders>';
    echo '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '</Borders>';
    echo '</Style>';
    
    // Style cho cột phương tiện (căn trái)
    echo '<Style ss:ID="Vehicle">';
    echo '<Font ss:Size="10"/>';
    echo '<Alignment ss:Horizontal="Left" ss:Vertical="Center"/>';
    echo '<Borders>';
    echo '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '</Borders>';
    echo '</Style>';
    
    // Style cho số liệu (căn phải, format số)
    echo '<Style ss:ID="Data">';
    echo '<Font ss:Size="10"/>';
    echo '<Alignment ss:Horizontal="Right" ss:Vertical="Center"/>';
    echo '<Borders>';
    echo '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '</Borders>';
    echo '</Style>';
    
    
    // Style cho số âm (in đậm)
    echo '<Style ss:ID="DataNegative">';
    echo '<Font ss:Bold="1" ss:Size="10"/>';
    echo '<Alignment ss:Horizontal="Right" ss:Vertical="Center"/>';
    echo '<Borders>';
    echo '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '</Borders>';
    echo '</Style>';
    
    // Style cho cột ghi chú (căn trái)
    echo '<Style ss:ID="Note">';
    echo '<Font ss:Size="10"/>';
    echo '<Alignment ss:Horizontal="Left" ss:Vertical="Center"/>';
    echo '<Borders>';
    echo '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '</Borders>';
    echo '</Style>';
    
    // Style cho dòng tổng (đậm)
    echo '<Style ss:ID="Total">';
    echo '<Font ss:Bold="1" ss:Size="10"/>';
    echo '<Alignment ss:Horizontal="Left" ss:Vertical="Center"/>';
    echo '<Borders>';
    echo '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '</Borders>';
    echo '</Style>';
    
    // Style cho số liệu tổng (đậm, căn phải, format số)
    echo '<Style ss:ID="TotalData">';
    echo '<Font ss:Bold="1" ss:Size="10"/>';
    echo '<Alignment ss:Horizontal="Right" ss:Vertical="Center"/>';
    echo '<NumberFormat ss:Format="#,##0"/>';
    echo '<Borders>';
    echo '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '</Borders>';
    echo '</Style>';
    echo '</Styles>';
    
    echo '<Worksheet ss:Name="Báo cáo nhiên liệu">';
    echo '<Table>';

    // HEADER TEMPLATE (dòng 1-5)
    printSheetHeaderTemplate(9);

    // Tiêu đề chính (màu đỏ)
    echo '<Row>';
    echo '<Cell ss:StyleID="MainTitle" ss:MergeAcross="8"><Data ss:Type="String">BÁO CÁO NHIÊN LIỆU SỬ DỤNG VÀ TỒN KHO</Data></Cell>';
    echo '</Row>';

    // Tiêu đề tháng năm (màu đen)
    $thangDen = date('n', strtotime($ngayBaoCao));
    $tenThangDen = [
        1 => 'THÁNG 1', 2 => 'THÁNG 2', 3 => 'THÁNG 3', 4 => 'THÁNG 4',
        5 => 'THÁNG 5', 6 => 'THÁNG 6', 7 => 'THÁNG 7', 8 => 'THÁNG 8',
        9 => 'THÁNG 9', 10 => 'THÁNG 10', 11 => 'THÁNG 11', 12 => 'THÁNG 12'
    ];
    echo '<Row>';
    echo '<Cell ss:StyleID="MonthYearTitle" ss:MergeAcross="8"><Data ss:Type="String">' . $tenThangDen[$thangDen] . ' NĂM ' . $namHienTai . '</Data></Cell>';
    echo '</Row>';
    
    // Dữ liệu từng tháng
    if (!empty($baoCaos)) {
        $tenThang = [
            1 => 'THÁNG 1', 2 => 'THÁNG 2', 3 => 'THÁNG 3', 4 => 'THÁNG 4',
            5 => 'THÁNG 5', 6 => 'THÁNG 6', 7 => 'THÁNG 7', 8 => 'THÁNG 8',
            9 => 'THÁNG 9', 10 => 'THÁNG 10', 11 => 'THÁNG 11', 12 => 'THÁNG 12'
        ];
        
        foreach ($baoCaos as $thang) {
            // Chỉ xuất tháng có dữ liệu thực (bao gồm cả chuyển dầu)
            $hasData = false;
            foreach ($thang['phuong_tien'] as $pt) {
                if ($pt['so_du_dau_ky'] != 0 || $pt['dau_cap'] != 0 || ($pt['chuyen_dau_di'] ?? 0) != 0 || ($pt['nhan_dau_ve'] ?? 0) != 0 || $pt['tong_dau_su_dung'] != 0 || $pt['so_du_cuoi_ky'] != 0) {
                    $hasData = true;
                    break;
                }
            }
            
            if (!$hasData) {
                continue; // Bỏ qua tháng không có dữ liệu
            }
            
            // Header cột (trước tiêu đề tháng)
            echo '<Row>';
            echo '<Cell ss:StyleID="Header"><Data ss:Type="String">STT</Data></Cell>';
            echo '<Cell ss:StyleID="Header"><Data ss:Type="String">PHƯƠNG TIỆN</Data></Cell>';
            echo '<Cell ss:StyleID="Header"><Data ss:Type="String">DẦU TỒN ĐẦU KỲ</Data></Cell>';
            echo '<Cell ss:StyleID="Header"><Data ss:Type="String">DẦU CẤP</Data></Cell>';
            echo '<Cell ss:StyleID="Header"><Data ss:Type="String">CHUYỂN DẦU ĐI</Data></Cell>';
            echo '<Cell ss:StyleID="Header"><Data ss:Type="String">NHẬN DẦU VỀ</Data></Cell>';
            echo '<Cell ss:StyleID="Header"><Data ss:Type="String">DẦU SỬ DỤNG KHÔNG HÀNG</Data></Cell>';
            echo '<Cell ss:StyleID="Header"><Data ss:Type="String">DẦU SỬ DỤNG CÓ HÀNG</Data></Cell>';
            echo '<Cell ss:StyleID="Header"><Data ss:Type="String">TỔNG DẦU SỬ DỤNG</Data></Cell>';
            echo '<Cell ss:StyleID="Header"><Data ss:Type="String">DẦU TỒN CUỐI KỲ</Data></Cell>';
            echo '<Cell ss:StyleID="Header"><Data ss:Type="String">GHI CHÚ</Data></Cell>';
            echo '</Row>';
            
            // Dòng phân chia tháng (sau header)
            $ngayCuoiThangVN = date('d-m-y', strtotime($thang['ngay_cuoi_thang']));
            echo '<Row>';
            echo '<Cell ss:StyleID="MonthTitle" ss:MergeAcross="9"><Data ss:Type="String">' . $tenThang[$thang['thang']] . '-' . $namHienTai . ' (' . $ngayCuoiThangVN . ')</Data></Cell>';
            echo '</Row>';
            
            // Dữ liệu phương tiện - chỉ hiển thị phương tiện có dữ liệu
            $stt = 0;
            foreach ($thang['phuong_tien'] as $pt) {
                // Chỉ hiển thị phương tiện có dữ liệu (bao gồm cả chuyển dầu)
                if ($pt['so_du_dau_ky'] == 0 && $pt['dau_cap'] == 0 && ($pt['chuyen_dau_di'] ?? 0) == 0 && ($pt['nhan_dau_ve'] ?? 0) == 0 && $pt['tong_dau_su_dung'] == 0 && $pt['so_du_cuoi_ky'] == 0) {
                    continue; // Bỏ qua phương tiện không có dữ liệu
                }
                
                $stt++;
                echo '<Row>';
                echo '<Cell ss:StyleID="STT"><Data ss:Type="Number">' . $stt . '</Data></Cell>';
                echo '<Cell ss:StyleID="Vehicle"><Data ss:Type="String">' . htmlspecialchars(formatTau($pt['ten_tau'])) . '</Data></Cell>';
                
                // Xử lý hiển thị số liệu theo format ảnh mẫu
                $soDuDauKy = $pt['so_du_dau_ky'];
                $dauCap = $pt['dau_cap'];
                $chuyenDauDi = $pt['chuyen_dau_di'];
                $nhanDauVe = $pt['nhan_dau_ve'];
                $dauSuDungKhongHang = $pt['dau_su_dung_khong_hang'];
                $dauSuDungCoHang = $pt['dau_su_dung_co_hang'];
                $tongDauSuDung = $pt['tong_dau_su_dung'];
                $soDuCuoiKy = $pt['so_du_cuoi_ky'];
                
                // Hiển thị số theo format ảnh mẫu: 0 hiển thị là "0", không phải "-"
                $styleSoDuDauKy = ($soDuCuoiKy < 0) ? 'DataNegative' : 'Data';
                $styleDauCap = 'Data';
                $styleChuyenDauDi = 'Data';
                $styleNhanDauVe = 'Data';
                $styleDauSuDungKhongHang = 'Data';
                $styleDauSuDungCoHang = 'Data';
                $styleTongDauSuDung = 'Data';
                $styleSoDuCuoiKy = ($soDuCuoiKy < 0) ? 'DataNegative' : 'Data';
                
                echo '<Cell ss:StyleID="' . $styleSoDuDauKy . '"><Data ss:Type="String">' . (fmt_export($soDuDauKy) ?: '-') . '</Data></Cell>';
                echo '<Cell ss:StyleID="' . $styleDauCap . '"><Data ss:Type="String">' . (fmt_export($dauCap) ?: '-') . '</Data></Cell>';
                echo '<Cell ss:StyleID="' . $styleChuyenDauDi . '"><Data ss:Type="String">' . (fmt_export($chuyenDauDi) ?: '-') . '</Data></Cell>';
                echo '<Cell ss:StyleID="' . $styleNhanDauVe . '"><Data ss:Type="String">' . (fmt_export($nhanDauVe) ?: '-') . '</Data></Cell>';
                echo '<Cell ss:StyleID="' . $styleDauSuDungKhongHang . '"><Data ss:Type="String">' . (fmt_export($dauSuDungKhongHang) ?: '-') . '</Data></Cell>';
                echo '<Cell ss:StyleID="' . $styleDauSuDungCoHang . '"><Data ss:Type="String">' . (fmt_export($dauSuDungCoHang) ?: '-') . '</Data></Cell>';
                echo '<Cell ss:StyleID="' . $styleTongDauSuDung . '"><Data ss:Type="String">' . (fmt_export($tongDauSuDung) ?: '-') . '</Data></Cell>';
                echo '<Cell ss:StyleID="' . $styleSoDuCuoiKy . '"><Data ss:Type="String">' . (fmt_export($soDuCuoiKy) ?: '-') . '</Data></Cell>';
                echo '<Cell ss:StyleID="Note"><Data ss:Type="String">' . htmlspecialchars($pt['ghi_chu'] ?? '') . '</Data></Cell>';
                echo '</Row>';
            }
            
            // Dòng tổng - chỉ tính những phương tiện có dữ liệu
            $tongSoDuDauKy = 0;
            $tongDauCap = 0;
            $tongChuyenDauDi = 0;
            $tongNhanDauVe = 0;
            $tongDauSuDungKhongHang = 0;
            $tongDauSuDungCoHang = 0;
            $tongDauSuDung = 0;
            $tongSoDuCuoiKy = 0;
            
            foreach ($thang['phuong_tien'] as $pt) {
                // Chỉ tính những phương tiện có dữ liệu (bao gồm cả chuyển dầu)
                if ($pt['so_du_dau_ky'] != 0 || $pt['dau_cap'] != 0 || ($pt['chuyen_dau_di'] ?? 0) != 0 || ($pt['nhan_dau_ve'] ?? 0) != 0 || $pt['tong_dau_su_dung'] != 0 || $pt['so_du_cuoi_ky'] != 0) {
                    $tongSoDuDauKy += $pt['so_du_dau_ky'];
                    $tongDauCap += $pt['dau_cap'];
                    $tongChuyenDauDi += $pt['chuyen_dau_di'] ?? 0;
                    $tongNhanDauVe += $pt['nhan_dau_ve'] ?? 0;
                    $tongDauSuDungKhongHang += $pt['dau_su_dung_khong_hang'];
                    $tongDauSuDungCoHang += $pt['dau_su_dung_co_hang'];
                    $tongDauSuDung += $pt['tong_dau_su_dung'];
                    $tongSoDuCuoiKy += $pt['so_du_cuoi_ky'];
                }
            }
            
            echo '<Row>';
            echo '<Cell ss:StyleID="Total"><Data ss:Type="String"></Data></Cell>';
            echo '<Cell ss:StyleID="Total"><Data ss:Type="String">Tổng</Data></Cell>';
            echo '<Cell ss:StyleID="TotalData"><Data ss:Type="String">' . (fmt_export($tongSoDuDauKy) ?: '-') . '</Data></Cell>';
            echo '<Cell ss:StyleID="TotalData"><Data ss:Type="String">' . (fmt_export($tongDauCap) ?: '-') . '</Data></Cell>';
            echo '<Cell ss:StyleID="TotalData"><Data ss:Type="String">' . (fmt_export($tongChuyenDauDi) ?: '-') . '</Data></Cell>';
            echo '<Cell ss:StyleID="TotalData"><Data ss:Type="String">' . (fmt_export($tongNhanDauVe) ?: '-') . '</Data></Cell>';
            echo '<Cell ss:StyleID="TotalData"><Data ss:Type="String">' . (fmt_export($tongDauSuDungKhongHang) ?: '-') . '</Data></Cell>';
            echo '<Cell ss:StyleID="TotalData"><Data ss:Type="String">' . (fmt_export($tongDauSuDungCoHang) ?: '-') . '</Data></Cell>';
            echo '<Cell ss:StyleID="TotalData"><Data ss:Type="String">' . (fmt_export($tongDauSuDung) ?: '-') . '</Data></Cell>';
            echo '<Cell ss:StyleID="TotalData"><Data ss:Type="String">' . (fmt_export($tongSoDuCuoiKy) ?: '-') . '</Data></Cell>';
            echo '<Cell ss:StyleID="Total"><Data ss:Type="String"></Data></Cell>';
            echo '</Row>';
            
            // Dòng trống
            echo '<Row>';
            echo '<Cell><Data ss:Type="String"></Data></Cell>';
            echo '</Row>';
        }
        
        // Dòng tổng cuối cùng cho toàn bộ báo cáo
        $tongTatCaSoDuDauKy = 0;
        $tongTatCaDauCap = 0;
        $tongTatCaChuyenDauDi = 0;
        $tongTatCaNhanDauVe = 0;
        $tongTatCaDauSuDungKhongHang = 0;
        $tongTatCaDauSuDungCoHang = 0;
        $tongTatCaDauSuDung = 0;
        $tongTatCaSoDuCuoiKy = 0;
        
        foreach ($baoCaos as $thang) {
            $tongTatCaSoDuDauKy += array_sum(array_column($thang['phuong_tien'], 'so_du_dau_ky'));
            $tongTatCaDauCap += array_sum(array_column($thang['phuong_tien'], 'dau_cap'));
            $tongTatCaChuyenDauDi += array_sum(array_column($thang['phuong_tien'], 'chuyen_dau_di'));
            $tongTatCaNhanDauVe += array_sum(array_column($thang['phuong_tien'], 'nhan_dau_ve'));
            $tongTatCaDauSuDungKhongHang += array_sum(array_column($thang['phuong_tien'], 'dau_su_dung_khong_hang'));
            $tongTatCaDauSuDungCoHang += array_sum(array_column($thang['phuong_tien'], 'dau_su_dung_co_hang'));
            $tongTatCaDauSuDung += array_sum(array_column($thang['phuong_tien'], 'tong_dau_su_dung'));
            $tongTatCaSoDuCuoiKy += array_sum(array_column($thang['phuong_tien'], 'so_du_cuoi_ky'));
        }
        
        echo '<Row>';
        echo '<Cell ss:StyleID="Total"><Data ss:Type="String">Tổng</Data></Cell>';
        echo '<Cell ss:StyleID="Total"><Data ss:Type="String"></Data></Cell>';
        echo '<Cell ss:StyleID="TotalData"><Data ss:Type="Number">' . $tongTatCaSoDuDauKy . '</Data></Cell>';
        echo '<Cell ss:StyleID="TotalData"><Data ss:Type="Number">' . $tongTatCaDauCap . '</Data></Cell>';
        echo '<Cell ss:StyleID="TotalData"><Data ss:Type="Number">' . $tongTatCaChuyenDauDi . '</Data></Cell>';
        echo '<Cell ss:StyleID="TotalData"><Data ss:Type="Number">' . $tongTatCaNhanDauVe . '</Data></Cell>';
        echo '<Cell ss:StyleID="TotalData"><Data ss:Type="Number">' . $tongTatCaDauSuDungKhongHang . '</Data></Cell>';
        echo '<Cell ss:StyleID="TotalData"><Data ss:Type="Number">' . $tongTatCaDauSuDungCoHang . '</Data></Cell>';
        echo '<Cell ss:StyleID="TotalData"><Data ss:Type="Number">' . $tongTatCaDauSuDung . '</Data></Cell>';
        echo '<Cell ss:StyleID="TotalData"><Data ss:Type="Number">' . $tongTatCaSoDuCuoiKy . '</Data></Cell>';
        echo '<Cell ss:StyleID="Total"><Data ss:Type="String"></Data></Cell>';
        echo '</Row>';
    }
    
    echo '</Table>';
    echo '</Worksheet>';
    echo '</Workbook>';
    exit;
}

$heSoTau = new HeSoTau();
$dauTon = new DauTon();
$ketQua = new LuuKetQua();
$danhSachTau = $heSoTau->getDanhSachTau();

// Lấy tham số từ URL
$ngayBaoCao = $_GET['ngay'] ?? date('Y-m-d');

// Validate ngày
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ngayBaoCao)) {
    $ngayBaoCao = date('Y-m-d');
}

$ngayBaoCaoVN = format_date_vn($ngayBaoCao);
$namHienTai = date('Y', strtotime($ngayBaoCao));

/**
 * Tính toán dữ liệu nhiên liệu theo tháng cho tất cả phương tiện có số liệu
 */
function tinhDuLieuTheoThangTatCaWeb($danhSachTau, $nam, $denNgay, $dauTon, $ketQua) {
    $duLieuThang = [];
    
    // Tính số tháng từ đầu năm đến ngày được chọn
    $thangDen = date('m', strtotime($denNgay));
    
    for ($thang = 1; $thang <= $thangDen; $thang++) {
        $ngayDauThang = "$nam-" . sprintf('%02d', $thang) . "-01";
        $ngayCuoiThang = date('Y-m-t', strtotime($ngayDauThang));
        
        // Nếu là tháng hiện tại, chỉ tính đến ngày được chọn
        if ($thang == $thangDen) {
            $ngayCuoiThang = $denNgay;
        }
        
        $duLieuPhuongTien = [];
        
        foreach ($danhSachTau as $tenTau) {
            // Ưu tiên giá trị nhập tay từ đầu tồn đầu kỳ
            // Tính số dư đầu kỳ (cuối tháng trước)
            $soDuDauKy = 0;
            if ($thang > 1) {
                $ngayCuoiThangTruoc = date('Y-m-t', strtotime("$nam-" . sprintf('%02d', $thang - 1) . "-01"));
                $soDuDauKy = $dauTon->tinhSoDu($tenTau, $ngayCuoiThangTruoc);
            } else {
                // Tháng đầu tiên: tính số dư từ đầu năm đến ngày đầu tháng
                $ngayDauNam = "$nam-01-01";
                $ngayDauThang = "$nam-" . sprintf('%02d', $thang) . "-01";
                $soDuDauKy = $dauTon->tinhSoDu($tenTau, $ngayDauThang);
            }
            
                // Tính dầu cấp trong tháng (chỉ cap_them, KHÔNG tính tinh_chinh)
                $dauCap = 0;
                $giaoDichThang = $dauTon->getLichSuGiaoDich($tenTau);
                foreach ($giaoDichThang as $gd) {
                    $ngayGiaoDich = $gd['ngay'] ?? '';
                    if ($ngayGiaoDich >= $ngayDauThang && $ngayGiaoDich <= $ngayCuoiThang) {
                        if ($gd['loai'] === 'cap_them' && (float)($gd['so_luong_lit'] ?? 0) > 0) {
                            $dauCap += (float)$gd['so_luong_lit'];
                        }
                        // BỎ TINH CHỈNH: Không tính tinh_chinh vào báo cáo CT nữa
                    }
                }
            
            // Tính dầu sử dụng trong tháng
            $dauSuDungKhongHang = 0;
            $dauSuDungCoHang = 0;
            
            $ketQuaThang = $ketQua->docTatCa();
            foreach ($ketQuaThang as $row) {
                if (($row['ten_phuong_tien'] ?? '') !== $tenTau) continue;
                
                // Xác định ngày tính tiêu hao: ưu tiên ngày dỡ xong -> ngày đến -> ngày đi -> created_at
                $ngayTieuHao = '';
                $ngayDoXong = $row['ngay_do_xong'] ?? '';
                if ($ngayDoXong) {
                    $ngayTieuHao = parse_date_vn($ngayDoXong) ?: '';
                }
                if ($ngayTieuHao === '' && !empty($row['ngay_den'])) {
                    $ngayTieuHao = parse_date_vn($row['ngay_den']) ?: '';
                }
                if ($ngayTieuHao === '' && !empty($row['ngay_di'])) {
                    $ngayTieuHao = parse_date_vn($row['ngay_di']) ?: '';
                }
                if ($ngayTieuHao === '' && !empty($row['created_at'])) {
                    $ngayTieuHao = substr((string)$row['created_at'], 0, 10);
                }
                
                if ($ngayTieuHao >= $ngayDauThang && $ngayTieuHao <= $ngayCuoiThang) {
                    // Xử lý cấp thêm dầu (đồng bộ hoàn toàn với Model DauTon)
                    $isCapThem = (int)($row['cap_them'] ?? 0) === 1;
                    if ($isCapThem) {
                        $dauTieuHao = floor((float)($row['so_luong_cap_them_lit'] ?? 0));
                    } else {
                        $dauTieuHao = floor((float)($row['dau_tinh_toan_lit'] ?? 0));
                    }
                    
                    // Phân loại có hàng/không hàng dựa trên khối lượng vận chuyển
                    $khoiLuongVanChuyen = (float)($row['khoi_luong_van_chuyen_t'] ?? 0);
                    if ($khoiLuongVanChuyen > 0) {
                        $dauSuDungCoHang += $dauTieuHao;
                    } else {
                        $dauSuDungKhongHang += $dauTieuHao;
                    }
                }
            }

            // Xử lý TINH CHỈNH: tách riêng tinh chỉnh thông thường và chuyển dầu
            $chuyenDauDi = 0;  // Dầu chuyển đi (dương)
            $nhanDauVe = 0;    // Dầu nhận về (dương)
            
            $giaoDichTau = $dauTon->getLichSuGiaoDich($tenTau);
            foreach ($giaoDichTau as $gd) {
                if (($gd['loai'] ?? '') !== 'tinh_chinh') { continue; }

                $ngayGd = (string)($gd['ngay'] ?? '');
                if ($ngayGd < $ngayDauThang || $ngayGd > $ngayCuoiThang) { continue; }

                $amount = (float)($gd['so_luong_lit'] ?? 0);
                $lyDo = (string)($gd['ly_do'] ?? '');
                
                // Phân loại tinh chỉnh
                // Nhận diện chuyển dầu: tìm "chuyển sang" hoặc "nhận từ" (có thể có ký tự mũi tên → hoặc ←)
                $isChuyenDau = (
                    strpos($lyDo, 'chuyển sang') !== false || 
                    strpos($lyDo, 'nhận từ') !== false ||
                    preg_match('/→\s*chuyển\s+sang|←\s*nhận\s+từ/u', $lyDo)
                );
                
                if ($isChuyenDau) {
                    // Lệnh chuyển dầu: tách riêng
                    if ($amount < 0) {
                        $chuyenDauDi += abs($amount);  // Chuyển đi (dương)
                    } else {
                        $nhanDauVe += $amount;  // Nhận về (dương)
                    }
                } else {
                    // Tinh chỉnh thông thường: tính vào dầu cấp
                    $dauCap += $amount;
                }
            }

            $tongDauSuDung = $dauSuDungKhongHang + $dauSuDungCoHang;
            // Tính số dư cuối kỳ: Sử dụng Model DauTon
            // QUAN TRỌNG: Tính đến CUỐI THÁNG (ngayCuoiThang)
            $soDuCuoiKy = $dauTon->tinhSoDu($tenTau, $ngayCuoiThang);
            
            // Chỉ thêm phương tiện có số liệu
            if ($soDuDauKy > 0 || $dauCap > 0 || $chuyenDauDi > 0 || $nhanDauVe > 0 || $tongDauSuDung > 0 || $soDuCuoiKy > 0) {
                $duLieuPhuongTien[] = [
                    'ten_tau' => $tenTau,
                    'so_du_dau_ky' => $soDuDauKy,
                    'dau_cap' => $dauCap,
                    'chuyen_dau_di' => $chuyenDauDi,
                    'nhan_dau_ve' => $nhanDauVe,
                    'dau_su_dung_khong_hang' => $dauSuDungKhongHang,
                    'dau_su_dung_co_hang' => $dauSuDungCoHang,
                    'tong_dau_su_dung' => $tongDauSuDung,
                    'so_du_cuoi_ky' => $soDuCuoiKy
                ];
            }
        }
        
        if (!empty($duLieuPhuongTien)) {
            $duLieuThang[] = [
                'thang' => $thang,
                'ngay_dau_thang' => $ngayDauThang,
                'ngay_cuoi_thang' => $ngayCuoiThang,
                'phuong_tien' => $duLieuPhuongTien
            ];
        }
    }
    
    return $duLieuThang;
}

// Lấy dữ liệu báo cáo cho tất cả phương tiện
$baoCaos = tinhDuLieuTheoThangTatCaWeb($danhSachTau, $namHienTai, $ngayBaoCao, $dauTon, $ketQua);

include __DIR__ . '/../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div>
                        <h2 class="mb-1">
                            <i class="fas fa-chart-line text-primary me-2"></i>
                            Báo cáo nhiên liệu sử dụng và tồn kho
                        </h2>
                        <div class="text-muted">Báo cáo nhiên liệu theo tháng từ đầu năm đến ngày được chọn</div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <form method="get" class="d-flex align-items-center gap-2">
                            <label class="form-label mb-0">Đến ngày:</label>
                            <input type="date" name="ngay" value="<?php echo $ngayBaoCao; ?>" class="form-control" style="width: 180px;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i>Xem
                            </button>
                        </form>
                        <?php if (!empty($baoCaos)): ?>
                        <button onclick="exportToExcel()" class="btn btn-success">
                            <i class="fas fa-file-excel me-1"></i>Xuất Excel
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (empty($baoCaos)): ?>
<!-- Không có dữ liệu -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-info-circle text-primary mb-3" style="font-size: 3rem;"></i>
                <h4 class="text-muted">Không có dữ liệu</h4>
                <p class="text-muted">Không tìm thấy dữ liệu nhiên liệu trong năm <?php echo $namHienTai; ?> đến ngày <?php echo $ngayBaoCaoVN; ?></p>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<!-- Thông tin báo cáo -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card bg-light">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="mb-1">
                            <i class="fas fa-chart-bar text-primary me-2"></i>
                            Báo cáo tất cả phương tiện có số liệu
                        </h5>
                        <p class="text-muted mb-0">Từ đầu năm <?php echo $namHienTai; ?> đến ngày <?php echo $ngayBaoCaoVN; ?></p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="h6 text-primary mb-0">
                            Số tháng có dữ liệu: <strong><?php echo count($baoCaos); ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($baoCaos)): ?>
<!-- Bảng báo cáo chi tiết theo tháng -->
<div class="row">
    <div class="col-12">
        <div class="card" id="reportCard">
            <div class="card-body">
                <!-- Tiêu đề chính -->
                <div class="text-center mb-4">
                    <h2 class="fw-bold mb-2" style="font-size: 24px; font-weight: bold;">
                        BÁO CÁO NHIÊN LIỆU SỬ DỤNG VÀ TỒN KHO
                    </h2>
                    <h3 class="fw-bold mb-0" style="font-size: 20px; font-weight: bold;">
                        THÁNG <?php echo date('m', strtotime($ngayBaoCao)); ?> NĂM <?php echo $namHienTai; ?>
                    </h3>
                </div>
                <?php foreach ($baoCaos as $index => $thang): 
                    $tenThang = [
                        1 => 'THÁNG 1', 2 => 'THÁNG 2', 3 => 'THÁNG 3', 4 => 'THÁNG 4',
                        5 => 'THÁNG 5', 6 => 'THÁNG 6', 7 => 'THÁNG 7', 8 => 'THÁNG 8',
                        9 => 'THÁNG 9', 10 => 'THÁNG 10', 11 => 'THÁNG 11', 12 => 'THÁNG 12'
                    ];
                    $ngayCuoiThangVN = format_date_vn($thang['ngay_cuoi_thang']);
                    
                    // Tính tổng cho tháng
                    $tongSoDuDauKy = array_sum(array_column($thang['phuong_tien'], 'so_du_dau_ky'));
                    $tongDauCap = array_sum(array_column($thang['phuong_tien'], 'dau_cap'));
                    $tongChuyenDauDi = array_sum(array_column($thang['phuong_tien'], 'chuyen_dau_di'));
                    $tongNhanDauVe = array_sum(array_column($thang['phuong_tien'], 'nhan_dau_ve'));
                    $tongDauSuDungKhongHang = array_sum(array_column($thang['phuong_tien'], 'dau_su_dung_khong_hang'));
                    $tongDauSuDungCoHang = array_sum(array_column($thang['phuong_tien'], 'dau_su_dung_co_hang'));
                    $tongDauSuDung = array_sum(array_column($thang['phuong_tien'], 'tong_dau_su_dung'));
                    $tongSoDuCuoiKy = array_sum(array_column($thang['phuong_tien'], 'so_du_cuoi_ky'));
                ?>
                <div class="mb-4">
                    <div class="table-responsive">
                        <table class="table table-bordered" style="font-size: 13px; border-collapse: collapse;">
                            <thead>
                                <tr style="background-color: #dc3545; color: white;">
                                    <th colspan="2" class="text-center py-2 fw-bold" style="font-size: 16px; font-weight: bold; border: 1px solid #000;">
                                        <?php echo $tenThang[$thang['thang']]; ?>-<?php echo $namHienTai; ?> (<?php echo $ngayCuoiThangVN; ?>)
                                    </th>
                                    <th colspan="11" class="text-center py-2" style="border: 1px solid #000;"></th>
                                </tr>
                                <tr style="background-color: #f8f9fa;">
                                    <th width="5%" class="text-center py-2 fw-bold" style="border: 1px solid #000;">STT</th>
                                    <th width="15%" class="text-center py-2 fw-bold" style="border: 1px solid #000;">PHƯƠNG TIỆN</th>
                                    <th width="10%" class="text-center py-2 fw-bold" style="border: 1px solid #000;">DẦU TỒN ĐẦU KỲ</th>
                                    <th width="8%" class="text-center py-2 fw-bold" style="border: 1px solid #000;">DẦU CẤP</th>
                                    <th width="8%" class="text-center py-2 fw-bold" style="border: 1px solid #000;">CHUYỂN ĐI</th>
                                    <th width="8%" class="text-center py-2 fw-bold" style="border: 1px solid #000;">NHẬN VỀ</th>
                                    <th width="10%" class="text-center py-2 fw-bold" style="border: 1px solid #000;">DẦU SỬ DỤNG KHÔNG HÀNG</th>
                                    <th width="10%" class="text-center py-2 fw-bold" style="border: 1px solid #000;">DẦU SỬ DỤNG CÓ HÀNG</th>
                                    <th width="10%" class="text-center py-2 fw-bold" style="border: 1px solid #000;">TỔNG DẦU SỬ DỤNG</th>
                                    <th width="10%" class="text-center py-2 fw-bold" style="border: 1px solid #000;">DẦU TỒN CUỐI KỲ</th>
                                    <th width="6%" class="text-center py-2 fw-bold" style="border: 1px solid #000;">GHI CHÚ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($thang['phuong_tien'] as $stt => $pt): ?>
                                <tr>
                                    <td class="text-center py-2" style="border: 1px solid #000;"><?php echo $stt + 1; ?></td>
                                    <td class="py-2 fw-bold" style="border: 1px solid #000; text-align: left;"><strong><?php echo htmlspecialchars(formatTau($pt['ten_tau'])); ?></strong></td>
                                    <td class="text-end py-2" style="border: 1px solid #000;"><?php echo fmt_web_int($pt['so_du_dau_ky']) ?: '-'; ?></td>
                                    <td class="text-end py-2" style="border: 1px solid #000;"><?php echo fmt_web_int($pt['dau_cap']) ?: '-'; ?></td>
                                    <td class="text-end py-2" style="border: 1px solid #000;"><?php echo fmt_web_int($pt['chuyen_dau_di']) ?: '-'; ?></td>
                                    <td class="text-end py-2" style="border: 1px solid #000;"><?php echo fmt_web_int($pt['nhan_dau_ve']) ?: '-'; ?></td>
                                    <td class="text-end py-2" style="border: 1px solid #000;"><?php echo fmt_web_int($pt['dau_su_dung_khong_hang']) ?: '-'; ?></td>
                                    <td class="text-end py-2" style="border: 1px solid #000;"><?php echo fmt_web_int($pt['dau_su_dung_co_hang']) ?: '-'; ?></td>
                                    <td class="text-end py-2" style="border: 1px solid #000;"><?php echo fmt_web_int($pt['tong_dau_su_dung']) ?: '-'; ?></td>
                                    <td class="text-end py-2 fw-bold" style="border: 1px solid #000;"><strong><?php echo fmt_web_int($pt['so_du_cuoi_ky']) ?: '-'; ?></strong></td>
                                    <td class="text-center py-2" style="border: 1px solid #000;"></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background-color: #f8f9fa;">
                                    <td class="text-center py-2 fw-bold" style="border: 1px solid #000;"></td>
                                    <td class="text-start py-2 fw-bold" style="border: 1px solid #000;">Tổng</td>
                                    <td class="text-end py-2 fw-bold" style="border: 1px solid #000;"><?php echo fmt_web_int($tongSoDuDauKy) ?: '-'; ?></td>
                                    <td class="text-end py-2 fw-bold" style="border: 1px solid #000;"><?php echo fmt_web_int($tongDauCap) ?: '-'; ?></td>
                                    <td class="text-end py-2 fw-bold" style="border: 1px solid #000;"><?php echo fmt_web_int($tongChuyenDauDi) ?: '-'; ?></td>
                                    <td class="text-end py-2 fw-bold" style="border: 1px solid #000;"><?php echo fmt_web_int($tongNhanDauVe) ?: '-'; ?></td>
                                    <td class="text-end py-2 fw-bold" style="border: 1px solid #000;"><?php echo fmt_web_int($tongDauSuDungKhongHang) ?: '-'; ?></td>
                                    <td class="text-end py-2 fw-bold" style="border: 1px solid #000;"><?php echo fmt_web_int($tongDauSuDungCoHang) ?: '-'; ?></td>
                                    <td class="text-end py-2 fw-bold" style="border: 1px solid #000;"><?php echo fmt_web_int($tongDauSuDung) ?: '-'; ?></td>
                                    <td class="text-end py-2 fw-bold" style="border: 1px solid #000;"><?php echo fmt_web_int($tongSoDuCuoiKy) ?: '-'; ?></td>
                                    <td class="py-2" style="border: 1px solid #000;"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>


<script>
function exportToExcel() {
    // Lấy ngày hiện tại từ form
    const form = document.querySelector('form');
    const ngayInput = form.querySelector('input[name="ngay"]');
    const ngayValue = ngayInput ? ngayInput.value : '';
    
    // Chuyển hướng trực tiếp đến URL export
    const exportUrl = window.location.pathname + '?export=excel&ngay=' + encodeURIComponent(ngayValue);
    window.location.href = exportUrl;
}

// Tự động cập nhật ngày hôm nay khi trang load
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    const ngayInput = document.querySelector('input[name="ngay"]');
    if (ngayInput && !ngayInput.value) {
        ngayInput.value = today;
    }
});
</script>


<?php include __DIR__ . '/../includes/footer.php'; ?>
