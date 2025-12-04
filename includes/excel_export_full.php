<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../src/Report/HeaderTemplate.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Report\HeaderTemplate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

// Helper functions for Excel export
function toIntHelper($v){ return (int)floor((float)$v); }

/**
 * Set integer value in Excel cell with proper formatting
 * - Làm tròn xuống (floor) thành số nguyên
 * - Nếu = 0 thì để ô trống
 * - Áp dụng format #,##0 (dấu chấm phân cách nghìn)
 */
function setIntHelper($sheet,$col,$row,$val,$showDashForZero=false){
    $n=(int)floor((float)$val);
    if($n===0){
        if($showDashForZero){
            $sheet->setCellValueExplicitByColumnAndRow($col, $row, '-', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->getStyleByColumnAndRow($col, $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        } else {
            $sheet->setCellValueByColumnAndRow($col,$row,'');
        }
        return;
    }
    $sheet->setCellValueByColumnAndRow($col,$row,$n);
    $sheet->getStyleByColumnAndRow($col,$row)->getNumberFormat()->setFormatCode('#,##0');
}

/**
 * Set decimal value in Excel cell with proper formatting
 * - Giữ phần thập phân
 * - Nếu = 0 thì để ô trống
 * - Áp dụng format #,##0.00 (dấu chấm phân cách nghìn, dấu phẩy thập phân sẽ do Excel locale quyết định)
 */
function setDecimalHelper($sheet,$col,$row,$val,$decimals=2){ 
    $v=(float)$val; 
    if($v==0){ 
        $sheet->setCellValueExplicitByColumnAndRow($col, $row, '-', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->getStyleByColumnAndRow($col, $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        return; 
    }
    $sheet->setCellValueByColumnAndRow($col,$row,$v);
    $formatCode = '#,##0' . ($decimals > 0 ? ('.' . str_repeat('0', $decimals)) : '');
    $sheet->getStyleByColumnAndRow($col,$row)->getNumberFormat()->setFormatCode($formatCode); 
}

function exportLichSuFull($groups, $currentMonth, $currentYear, $isDetailedExport = false) {
    if (!headers_sent()) { @header('X-Export-Enter: 1'); }
    if (empty($groups) || !is_array($groups)) {
        die('<pre style="color:red;font-size:16px;">LỖI: Dữ liệu xuất Excel rỗng hoặc không hợp lệ. $groups=' . htmlspecialchars(var_export($groups, true)) . '</pre>');
    }
    // Model lấy số đăng ký và dầu tồn
    require_once __DIR__ . '/../models/TauPhanLoai.php';
    require_once __DIR__ . '/../models/DauTon.php';
    require_once __DIR__ . '/../models/LuuKetQua.php';
    $tauModel = class_exists('TauPhanLoai') ? new \TauPhanLoai() : null;
    $dauTonModel = new \DauTon();
    $ketQuaModel = new \LuuKetQua();

    $spreadsheet = new Spreadsheet();
    while ($spreadsheet->getSheetCount() > 0) { $spreadsheet->removeSheetByIndex(0); }
    $sheetAdded = false;

    // Nếu yêu cầu xuất chi tiết theo tàu (IN TINH DAU) → chỉ tạo các sheet chi tiết và bỏ qua các sheet tổng hợp
    if ($isDetailedExport) {
        $templatePath = HeaderTemplate::pathFor('IN_TINH_DAU');
        if (!$templatePath || !file_exists($templatePath)) {
            die('<pre style="color:red;font-size:16px;">LỖI: File template không tồn tại: ' . htmlspecialchars((string)$templatePath) . '</pre>');
        }
        $defaultCellStyle = ['borders' => [ 'allBorders' => ['borderStyle' => Border::BORDER_THIN] ], 'alignment' => [ 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true ] ];
        // Danh sách tàu được chọn từ request
        $selectedShips = [];
        if (isset($_GET['extra_ships']) && is_array($_GET['extra_ships'])) {
            foreach ($_GET['extra_ships'] as $s) {
                $s = trim((string)$s);
                if ($s !== '') { $selectedShips[strtolower(trim($s, '"'))] = $s; }
            }
        }
        // Nếu người dùng có lọc theo tên tàu ở bộ lọc chính, đảm bảo cũng nằm trong tập chọn
        $shipFilter = isset($_GET['ten_phuong_tien']) ? trim((string)$_GET['ten_phuong_tien']) : '';
        if ($shipFilter !== '') { $selectedShips[strtolower(trim($shipFilter, '"'))] = $shipFilter; }

        // Gom dữ liệu theo tàu và phân loại để render
        $rowsByShip = [];
        $plByShip = [];
        foreach ($groups as $phanLoai => $rowsInGroup) {
            foreach ($rowsInGroup as $r) {
                $ship = trim((string)($r['ten_phuong_tien'] ?? ''));
                if ($ship === '') continue;
                $shipKey = strtolower(trim($ship, '"'));
                // Nếu có selectedShips thì chỉ lấy những tàu được chọn
                if (!empty($selectedShips) && !isset($selectedShips[$shipKey])) continue;
                if (!isset($rowsByShip[$ship])) $rowsByShip[$ship] = [];
                $rowsByShip[$ship][] = $r;
                $plByShip[$ship] = ($phanLoai === 'thue_ngoai') ? 'SLN' : 'SLCTY';
            }
        }

        // Tạo từng sheet chi tiết theo tàu
        foreach ($rowsByShip as $ship => $rows) {
            $tmpSpreadsheet = IOFactory::load($templatePath);
            $sheet = $tmpSpreadsheet->getSheet(0);
            // Điền ngày hệ thống vào header (dòng 4: "Tp. Hồ Chí Minh, ngày XX tháng XX năm XXXX")
            HeaderTemplate::applyCommonHeader($sheet, 'G4');

            $suffix = $plByShip[$ship] ?? 'SLCTY';
            $sheetName = 'IN TINH DAU-' . $suffix . ' - ' . $ship;
            $sheet->setTitle(mb_substr($sheetName, 0, 31)); // Excel sheet name <= 31 ký tự

            // Ghi tiêu đề vào dòng 6 (A6:I6 merged trong template)
            $sheet->setCellValue('A6', 'BÁO CÁO TÍNH DẦU SÀ LAN TỰ HÀNH ' . $ship);
            $sheet->getStyle('A6')->getFont()->setBold(true)->setSize(13);
            $sheet->getStyle('A6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Sắp xếp theo tên tàu và ___idx (giữ nguyên thứ tự đã lưu trong lịch sử)
            usort($rows, function($a,$b){
                $ta=mb_strtolower(trim($a['ten_phuong_tien']??'')); $tb=mb_strtolower(trim($b['ten_phuong_tien']??'')); if($ta!==$tb) return $ta<=>$tb;
                // Giữ nguyên thứ tự theo ___idx (thứ tự đã lưu trong lịch sử)
                $idxA=(float)($a['___idx']??0); $idxB=(float)($b['___idx']??0); return $idxA<=>$idxB;
            });

            // Dữ liệu bắt đầu từ dòng 9 vì template đã có header cột ở dòng 8
            $currentRow = 9; $stt = 1; $displayedTrips = [];
            $sumKm = 0; $sumFuel = 0;
            foreach ($rows as $r) {
                $isCapThem = (int)($r['cap_them'] ?? 0) === 1;
                $tripCode = (string)($r['so_chuyen'] ?? '');
                $soChuyenDisplay = '';
                if (!$isCapThem && $tripCode !== '') {
                    if (!isset($displayedTrips[$tripCode])) {
                        $displayedTrips[$tripCode] = count($displayedTrips) + 1;
                        $soChuyenDisplay = (string)$displayedTrips[$tripCode];
                    }
                }

                if ($isCapThem) {
                    $fuel = (float)($r['so_luong_cap_them_lit'] ?? 0);
                    $kl = 0; $totalKm = 0;
                    $loaiHang = '';
                    $route = trim((string)($r['ly_do_cap_them'] ?? ''));
                    $dateVN = format_date_vn((string)($r['ngay_di'] ?? ''));
                } else {
                    $sch = (float)($r['cu_ly_co_hang_km'] ?? 0);
                    $skh = (float)($r['cu_ly_khong_hang_km'] ?? 0);
                    $kkh = (float)($r['he_so_khong_hang'] ?? 0);
                    $kch = (float)($r['he_so_co_hang'] ?? 0);
                    $kl  = (float)($r['khoi_luong_van_chuyen_t'] ?? 0);
                    $fuelStored = (float)($r['dau_tinh_toan_lit'] ?? 0);
                    $fuel = $fuelStored > 0 ? $fuelStored : (($skh * $kkh) + ($sch * $kl * $kch));
                    // Xây tuyến đường, ưu tiên dùng route_hien_thi nếu có (đã lưu đầy đủ tuyến đường)
                    $route = trim((string)($r['route_hien_thi'] ?? ''));
                    if ($route === '') {
                        // Fallback: xây dựng tuyến từ các điểm riêng lẻ hoặc tuyen_duong
                        $route = trim((string)($r['tuyen_duong'] ?? ''));
                        if ($route === '') {
                            $isDoiLenh = (($r['doi_lenh'] ?? '0') == '1');
                            $di = trim((string)($r['diem_di'] ?? ''));
                            $den = trim((string)($r['diem_den'] ?? ''));
                            $b   = trim((string)($r['diem_du_kien'] ?? ''));
                            if ($isDoiLenh && ($di !== '' || $b !== '' || $den !== '')) {
                                $route = $di . ' → ' . $b . ' (đổi lệnh) → ' . $den;
                            } else if ($di !== '' || $den !== '') {
                                $route = $di . ' → ' . $den;
                            } else {
                                // Chế độ nhập thủ công: không có điểm đi/đến → lấy từ ghi chú (lưu nguyên văn)
                                $route = trim((string)($r['ghi_chu'] ?? ''));
                            }
                        }
                    }
                    $dateVN = format_date_vn((string)($r['ngay_di'] ?? ''));
                    $totalKm = (int)round($sch + $skh);
                    // Chỉ hiển thị loại hàng khi có hàng (kl > 0), không hàng thì để trống
                    $loaiHang = ($kl > 0) ? (string)($r['loai_hang'] ?? '') : '';
                }

                $fuelDisplay = (int)floor($fuel);
                $sheet->setCellValueByColumnAndRow(1,$currentRow,$stt);
                $sheet->setCellValueByColumnAndRow(2,$currentRow,$soChuyenDisplay);
                // KLVC: Giữ phần thập phân, format #,##0.00
                setDecimalHelper($sheet,3,$currentRow,$kl,2);
                $sheet->setCellValueByColumnAndRow(4,$currentRow,$loaiHang);
                $sheet->setCellValueByColumnAndRow(5,$currentRow,$route);
                $sheet->setCellValueByColumnAndRow(6,$currentRow,$dateVN);
                // Cự ly: Làm tròn xuống, để trống nếu = 0
                setIntHelper($sheet,7,$currentRow,$totalKm);
                // Dầu: Làm tròn xuống, để trống nếu = 0
                setIntHelper($sheet,8,$currentRow,$fuelDisplay);
                $sheet->setCellValueByColumnAndRow(9,$currentRow,(string)($r['ghi_chu'] ?? ''));
                $sheet->getStyle("A{$currentRow}:I{$currentRow}")->applyFromArray($defaultCellStyle);
                $stt++; $currentRow++;

                $sumKm += (int)$totalKm; $sumFuel += $fuelDisplay;
            }

            // Dòng tổng cộng (text ở cột E thay vì D)
            $sheet->setCellValueByColumnAndRow(2,$currentRow,count($displayedTrips));
            $sheet->setCellValueByColumnAndRow(5,$currentRow,'Tổng cộng:');
            setIntHelper($sheet,7,$currentRow,$sumKm);
            setIntHelper($sheet,8,$currentRow,$sumFuel);
            $sheet->getStyle("A{$currentRow}:I{$currentRow}")->applyFromArray(array_merge($defaultCellStyle,[
                'font'=>['bold'=>true],
                'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFE08A']]
            ]));
            $currentRow++;
            // Thêm một dòng trống ngăn cách bảng và phần phụ (Nợ tại...)
            $currentRow++;

            // ====================== Các dòng phụ: Nợ tại / Nhận dầu tại / Cộng ======================
            // Sheet IN TINH DAU: Lấy tất cả data từ quá khứ đến ngày hiện tại
            // Tìm ngày đầu tiên có data trong rows để làm "Bảng tính ngày"
            $firstDateInData = null;
            foreach ($rows as $r) {
                $dateStr = trim((string)($r['ngay_di'] ?? ''));
                if ($dateStr !== '') {
                    $iso = parse_date_vn($dateStr);
                    if ($iso && ($firstDateInData === null || $iso < $firstDateInData)) {
                        $firstDateInData = $iso;
                    }
                }
            }
            // Nếu không tìm thấy ngày nào, fallback về đầu năm hiện tại
            $ngayBangTinhIso = $firstDateInData ?: date('Y-01-01');
            $ngayBangTinhVN = format_date_vn($ngayBangTinhIso);

            // Nợ tại: cho phép override qua notai_* giống luồng XML và suy ra tồn đầu kỳ từ nhật ký
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
                $parsed = parse_date_vn($notaiDateOverrideVN);
                if ($parsed) { $ngayBangTinhVN = $notaiDateOverrideVN; }
            }

            // Thu thập TẤT CẢ lần nhận dầu từ đầu đến giờ (không filter theo tháng)
            $receiptEntries = [];
            // Tồn đầu kỳ (mặc định 0; có thể override bằng tham số notai_amount)
            $tonDau = 0.0;
            foreach ($dauTonModel->getLichSuGiaoDich($ship) as $gd) {
                $ngay = (string)($gd['ngay'] ?? '');
                if (!$ngay) continue;
                // KHÔNG FILTER THEO THÁNG - Lấy tất cả từ quá khứ đến hiện tại
                // $y = (int)date('Y', strtotime($ngay));
                // $m = (int)date('n', strtotime($ngay));
                // if ($y !== (int)$currentYear || $m !== (int)$currentMonth) continue;
                $loai = strtolower((string)($gd['loai'] ?? ''));
                if ($loai === 'cap_them') {
                    $soLuong = (float)($gd['so_luong_lit'] ?? 0);
                    if ($soLuong !== 0.0) {
                        $label = trim((string)($gd['cay_xang'] ?? 'Cấp thêm'));
                        $receiptEntries[] = ['label' => $label, 'date' => $ngay, 'amount' => $soLuong];
                    }
                } elseif ($loai === 'tinh_chinh') {
                    // Phân biệt: Chuyển dầu (có transfer_pair_id) vs Tinh chỉnh thủ công (không có)
                    $transferPairId = trim((string)($gd['transfer_pair_id'] ?? ''));
                    $soLuong = (float)($gd['so_luong_lit'] ?? 0);
                    if ($soLuong !== 0.0) {
                        if ($transferPairId !== '') {
                            // Đây là chuyển dầu → HIỂN THỊ trong báo cáo
                            $label = trim((string)($gd['ly_do'] ?? 'Chuyển dầu'));
                            $receiptEntries[] = ['label' => $label, 'date' => $ngay, 'amount' => $soLuong];
                        } else {
                            // Đây là tinh chỉnh thủ công → BỎ QUA (không hiển thị)
                            // Tinh chỉnh chỉ ảnh hưởng đến dầu tồn qua tinhSoDu(), không hiển thị chi tiết trong báo cáo
                        }
                    }
                }
            }
            usort($receiptEntries, function($a,$b){ return strcmp($a['date'],$b['date']); });

            // Row: Nợ tại
            // Theo mẫu: "Nợ tại" (cột D), "Bảng tính ngày" (cột E), ngày (cột F), số (cột G - căn phải)
            $sheet->setCellValueByColumnAndRow(4,$currentRow,'Nợ tại');
            $sheet->setCellValueByColumnAndRow(5,$currentRow,'Bảng tính ngày');
            $sheet->setCellValueByColumnAndRow(6,$currentRow,$ngayBangTinhVN);
            if ($notaiAmountOverride !== '') {
                $tonDau = (float)floor((float)str_replace(',','.', $notaiAmountOverride));
                setIntHelper($sheet,7,$currentRow,$tonDau);
            } else {
                setIntHelper($sheet,7,$currentRow,$tonDau);
            }
            // Không viền cho các dòng dưới "Tổng cộng", căn phải cột G (số liệu)
            $sheet->getStyle("A{$currentRow}:I{$currentRow}")->applyFromArray([
                'alignment'=>['vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true]
            ]);
            $sheet->getStyle("G{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $currentRow++;

            // Rows: Nhận dầu tại (mỗi entry một dòng)
            // Theo mẫu: "Nhận dầu tại" (cột D), tên cây xăng (cột E), ngày (cột F), số (cột G - căn phải)
            $sumReceiptsInt = 0;
            foreach ($receiptEntries as $rc) {
                $sheet->setCellValueByColumnAndRow(4,$currentRow,'Nhận dầu tại');
                $sheet->setCellValueByColumnAndRow(5,$currentRow,(string)$rc['label']);
                $sheet->setCellValueByColumnAndRow(6,$currentRow,format_date_vn((string)$rc['date']));
                $valInt = (int)floor((float)$rc['amount']);
                setIntHelper($sheet,7,$currentRow,$valInt);
                $sheet->getStyle("A{$currentRow}:I{$currentRow}")->applyFromArray([
                    'alignment'=>['vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true]
                ]);
                // Căn phải cột G (số liệu)
                $sheet->getStyle("G{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sumReceiptsInt += $valInt;
                $currentRow++;
            }

            // Row: Cộng:
            // Theo mẫu: "Cộng:" (cột E), số (cột G - căn phải)
            $sheet->setCellValueByColumnAndRow(5,$currentRow,'Cộng:');
            $tongNoNhan = (int)floor($tonDau) + (int)$sumReceiptsInt;
            setIntHelper($sheet,7,$currentRow,$tongNoNhan);
            $sheet->getStyle("A{$currentRow}:I{$currentRow}")->applyFromArray([
                'font'=>['bold'=>true],
                'alignment'=>['vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true]
            ]);
            // Căn phải cột G (số liệu)
            $sheet->getStyle("G{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $currentRow++;

            // ========== Dòng: Dầu tồn trên sà lan đến ngày ==========
            // Thêm một dòng trống ngăn cách trước khi hiển thị dầu tồn (giống XML)
            $currentRow++;
            // Sheet IN TINH DAU: Lấy tất cả data từ quá khứ đến NGÀY HIỆN TẠI
            // → Ngày phải là ngày hiện tại, KHÔNG PHẢI cuối tháng filter
            $dateForReport = date('Y-m-d'); // Ngày hiện tại
            $monthEndVN  = format_date_vn($dateForReport);
            // Tồn cuối = (Nợ tại + Nhận dầu) - Tổng dầu sử dụng hiển thị
            $tonCuoi = (int)floor($tongNoNhan - (int)$sumFuel);
            // Theo mẫu: "Dầu tồn trên sà lan đến ngày" (cột D-E merged), ngày (cột F), số (cột G - căn phải), "Lít" (cột H)
            // Merge D:E để text dài không bị cắt, giữ F riêng cho ngày
            $sheet->mergeCells("D{$currentRow}:E{$currentRow}");
            $sheet->setCellValueByColumnAndRow(4,$currentRow,'Dầu tồn trên sà lan đến ngày');
            $sheet->setCellValueByColumnAndRow(6,$currentRow,$monthEndVN);
            setIntHelper($sheet,7,$currentRow,$tonCuoi);
            $sheet->setCellValueByColumnAndRow(8,$currentRow,'Lít');
            $sheet->getStyle("A{$currentRow}:I{$currentRow}")->applyFromArray([
                'font'=>['bold'=>true],
                'alignment'=>['vertical'=>Alignment::VERTICAL_CENTER,'horizontal'=>Alignment::HORIZONTAL_LEFT],
                'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_NONE]]
            ]);
            // Căn phải cột G (số liệu)
            $sheet->getStyle("G{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $currentRow++;

            $spreadsheet->addExternalSheet($sheet); unset($tmpSpreadsheet); $sheetAdded = true;
        }

        if(!$sheetAdded){ if (!headers_sent()) { @header('X-Export-Stop: no_detail_sheets'); } die('<pre style="color:red;font-size:16px;">LỖI: Không có tàu nào được chọn để xuất chi tiết.</pre>'); }

        // Xuất file (chỉ sheet chi tiết)
        $spreadsheet->setActiveSheetIndex(0);
        if(function_exists('ob_get_level')){ while(ob_get_level()>0){ @ob_end_clean(); } }
        @error_reporting(0);
        $filename = 'CT_T' . $currentMonth . '_' . $currentYear . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0'); header('Pragma: public');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet); $writer->save('php://output');
        $spreadsheet->disconnectWorksheets(); unset($spreadsheet); exit();
    }

// ========== HÀM TẠO SHEET DAUTON (DẦU TỒN) ==========
function createDAUTONSheet($spreadsheet, $templatePath, $rowsInGroup, $currentMonth, $currentYear, $suffix, $tauModel, $dauTonModel, $ketQuaModel, $defaultCellStyle) {
    $tmpSpreadsheet = IOFactory::load($templatePath);
    $sheet = $tmpSpreadsheet->getSheet(0);
    $sheetName = 'DAUTON-' . $suffix;
    $sheet->setTitle($sheetName);

    // Header ngày theo template. Tiêu đề chia làm 2 dòng.
    HeaderTemplate::applyCommonHeader($sheet, 'D4');
    // Dòng 6: BẢNG TỔNG HỢP NHIÊN LIỆU SỬ DỤNG VÀ TỒN KHO
    $sheet->setCellValue('A6', 'BÁO CÁO NHIÊN LIỆU SỬ DỤNG VÀ TỒN KHO');
    $sheet->getStyle('A6')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->mergeCells('A6:I6');
    // Dòng 7: THÁNG X NĂM XXXX
    $sheet->setCellValue('A7', 'THÁNG ' . $currentMonth . ' NĂM ' . $currentYear);
    $sheet->getStyle('A7')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->mergeCells('A7:I7');

    // Gom theo tàu
    $ships = [];
    foreach($rowsInGroup as $row){ $ship = trim($row['ten_phuong_tien'] ?? ''); if($ship!==''){ $ships[$ship] = true; } }
    $ships = array_keys($ships); sort($ships);

    // Tính toán theo tháng
    $ngayDauThang = "$currentYear-" . sprintf('%02d', $currentMonth) . "-01";
    $ngayCuoiThang = date('Y-m-t', strtotime($ngayDauThang));
    $ngayCuoiThangVN = date('d-m-y', strtotime($ngayCuoiThang)); // dd-mm-yy format

    // Thêm dòng "THÁNG X-YYYY (dd-mm-yy)" ở dòng 10 (trong bảng, căn trái)
    $sheet->setCellValue('A10', 'THÁNG ' . sprintf('%d', $currentMonth) . '-' . $currentYear . ' (' . $ngayCuoiThangVN . ')');
    $sheet->getStyle('A10')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('FF0000');
    $sheet->getStyle('A10')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet->mergeCells('A10:I10');

    // Dữ liệu bắt đầu từ dòng 11 (sau dòng tháng)
    $currentRow=11; $stt=1; $sumCols=[3,4,5,6,7,8]; $grandTotals=array_fill_keys($sumCols,0);
    foreach($ships as $ship){
        // Tồn đầu kỳ = số dư đến cuối tháng trước
        $ngayCuoiTruoc = date('Y-m-t', strtotime("$currentYear-" . sprintf('%02d', max(1,$currentMonth-1)) . "-01"));
        if ($currentMonth === 1) { $ngayCuoiTruoc = ($currentYear-1) . "-12-31"; }
        $tonDauKy = toIntHelper($dauTonModel->tinhSoDu($ship, $ngayCuoiTruoc));

        // Dầu cấp trong tháng (cap_them + tinh_chinh ngoại trừ chuyển/nhận)
        $dauCap = 0;
        foreach ($dauTonModel->getLichSuGiaoDich($ship) as $gd) {
            $ngay = (string)($gd['ngay'] ?? '');
            if ($ngay < $ngayDauThang || $ngay > $ngayCuoiThang) continue;
            if (($gd['loai'] ?? '') === 'cap_them') { $dauCap += (float)($gd['so_luong_lit'] ?? 0); }
        }

        // Dầu sử dụng KH/CH từ dữ liệu kết quả (rowsInGroup)
        $dauSuDungKH = 0; $dauSuDungCH = 0;
        foreach ($rowsInGroup as $kq) {
            if (trim(($kq['ten_phuong_tien'] ?? '')) !== $ship) continue;
            $isCapThem = (int)($kq['cap_them'] ?? 0) === 1;
            $dau = $isCapThem ? toIntHelper($kq['so_luong_cap_them_lit'] ?? 0) : toIntHelper($kq['dau_tinh_toan_lit'] ?? 0);
            $kl = (float)($kq['khoi_luong_van_chuyen_t'] ?? 0);
            if ($kl <= 1e-6) { $dauSuDungKH += $dau; } else { $dauSuDungCH += $dau; }
        }
        $tongSD = $dauSuDungKH + $dauSuDungCH;
        $tonCuoiKy = toIntHelper($dauTonModel->tinhSoDu($ship, $ngayCuoiThang));

        // Ghi dòng
        $sheet->setCellValueByColumnAndRow(1,$currentRow,$stt++);
        $sheet->setCellValueByColumnAndRow(2,$currentRow,$ship);
        setIntHelper($sheet,3,$currentRow,$tonDauKy);
        setIntHelper($sheet,4,$currentRow,toIntHelper($dauCap));
        setIntHelper($sheet,5,$currentRow,$dauSuDungKH);
        setIntHelper($sheet,6,$currentRow,$dauSuDungCH);
        setIntHelper($sheet,7,$currentRow,$tongSD);
        setIntHelper($sheet,8,$currentRow,$tonCuoiKy);
        // Cột 9 (I): GHI CHÚ - để trống, sẽ được điền thủ công nếu cần
        $sheet->setCellValueByColumnAndRow(9,$currentRow,'');
        $sheet->getStyle("A{$currentRow}:I{$currentRow}")->applyFromArray($defaultCellStyle);
        foreach($sumCols as $c){ $grandTotals[$c]+= (int)$sheet->getCellByColumnAndRow($c,$currentRow)->getValue(); }
        $currentRow++;
    }

    // Dòng tổng
    $sheet->setCellValueByColumnAndRow(2,$currentRow,'Tổng');
    foreach($sumCols as $c){ setIntHelper($sheet,$c,$currentRow,$grandTotals[$c]); }
    $sheet->getStyle("A{$currentRow}:I{$currentRow}")->applyFromArray(array_merge($defaultCellStyle,['font'=>['bold'=>true],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFE08A']]]));

    $spreadsheet->addExternalSheet($sheet); unset($tmpSpreadsheet);
}

    // Tạo sheet BCTHANG cho mỗi phân loại (xuất mặc định)
    $templatePathBCTHANG = HeaderTemplate::pathFor('BCTHANG');
    if (!$templatePathBCTHANG || !file_exists($templatePathBCTHANG)) {
        die('<pre style="color:red;font-size:16px;">LỖI: File template không tồn tại: ' . htmlspecialchars((string)$templatePathBCTHANG) . '</pre>');
    }
    foreach ($groups as $phanLoai => $rowsInGroup) {
        if (empty($rowsInGroup)) continue;
        $tmpSpreadsheet = IOFactory::load($templatePathBCTHANG);
        $sheet = $tmpSpreadsheet->getSheet(0);
        $suffix = ($phanLoai === 'cong_ty') ? 'SLCTY' : 'SLN';
        $sheetName = 'BCTHANG-' . $suffix;
        $sheet->setTitle($sheetName);
        HeaderTemplate::applyCommonHeader($sheet, 'F4');
        $titleText = 'BẢNG TỔNG HỢP NHIÊN LIỆU VÀ KHỐI LƯỢNG VẬN CHUYỂN HÀNG HÓA THÁNG ' . $currentMonth . ' NĂM ' . $currentYear;
        $sheet->setCellValue('A6', $titleText); // Tiêu đề động được ghi vào dòng 6

        $defaultCellStyle = ['borders' => [ 'allBorders' => ['borderStyle' => Border::BORDER_THIN] ], 'alignment' => [ 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true ] ];

        // Sắp xếp dữ liệu: theo tên tàu, rồi theo ___idx (thứ tự lưu trong lịch sử)
        // Giữ nguyên thứ tự đã lưu để khớp với trang lịch sử
        usort($rowsInGroup, function($a, $b) {
            // 1. Tên tàu
            $ta = mb_strtolower(trim($a['ten_phuong_tien'] ?? ''));
            $tb = mb_strtolower(trim($b['ten_phuong_tien'] ?? ''));
            if ($ta !== $tb) return $ta <=> $tb;

            // 2. Giữ nguyên thứ tự theo ___idx (thứ tự đã lưu trong lịch sử)
            $idxA = (float)($a['___idx'] ?? 0);
            $idxB = (float)($b['___idx'] ?? 0);
            return $idxA <=> $idxB;
        });

        // Dữ liệu bắt đầu từ dòng 9 vì template đã có header cột ở dòng 7-8
        // sumCols: Các cột cần tính tổng - CỰ LY (G,H,I=7,8,9), KLVC (M=13), SL LUÂN CHUYỂN (N=14), DẦU SD (O=15), phân loại cự ly (V,W,X=22,23,24)
        $currentRow=9; $stt=1; $sumCols=[7,8,9,13,14,15,22,23,24]; $grandTotals=array_fill_keys($sumCols,0); $currentShip=null; $subtotal=array_fill_keys($sumCols,0); $prevTripByShip=[]; $tripSeenByShip=[]; $grandTotalTrips=0; $isFirstRowOfShip=false; $tripCounterForMonth = 0;

        foreach($rowsInGroup as $row){
            $ship=trim($row['ten_phuong_tien']??''); $soChuyen=trim((string)($row['so_chuyen']??'')); $isCapThem=((int)($row['cap_them']??0)===1);
            $isChuyenDau=((int)($row['cap_them']??0)===2);
            
            if($currentShip!==null && $ship!==$currentShip){
                $tripCount=count($tripSeenByShip);
                $sheet->setCellValueByColumnAndRow(3,$currentRow,$currentShip.' Cộng');
                $sheet->setCellValueByColumnAndRow(4,$currentRow,'');
                $sheet->setCellValueByColumnAndRow(5,$currentRow,$tripCount);
                $sheet->setCellValueByColumnAndRow(6,$currentRow,'');
                foreach($sumCols as $c){ setIntHelper($sheet,$c,$currentRow,$subtotal[$c]); }
                $sheet->getStyle("A{$currentRow}:X{$currentRow}")->applyFromArray(array_merge($defaultCellStyle,['font'=>['bold'=>true],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFF59D']]]));
                // Căn giữa cho cột số chuyến
                $sheet->getStyleByColumnAndRow(5,$currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $currentRow++;
                foreach($sumCols as $c){ $grandTotals[$c]+=$subtotal[$c]; }
                $grandTotalTrips+=$tripCount;
                $subtotal=array_fill_keys($sumCols,0);
                $prevTripByShip=[];
                $tripSeenByShip=[];
                $tripCounterForMonth = 0; // Reset số chuyến khi chuyển sang tàu mới
                $stt++; // Tăng STT khi chuyển sang tàu mới
                $isFirstRowOfShip=true;
            }
            // Kiểm tra xem có phải dòng đầu tiên của tàu không
            if($currentShip === null || $currentShip !== $ship) { $isFirstRowOfShip = true; }
            $currentShip=$ship;
            // Ghi STT vào cột A - chỉ hiển thị ở dòng đầu tiên của mỗi tàu
            $sheet->setCellValueByColumnAndRow(1,$currentRow,$isFirstRowOfShip ? $stt : '');
            $isFirstRowOfShip = false; // Các dòng tiếp theo của cùng tàu sẽ không hiển thị STT
            // Cột B (2) để trống - theo template
            $sheet->setCellValueByColumnAndRow(3,$currentRow,$ship); // TÊN PT vào cột C
            // SỐ ĐK vào cột D
            $soDK = $tauModel ? ($tauModel->getSoDangKy($ship) ?: '') : '';
            $sheet->setCellValueByColumnAndRow(4,$currentRow,$soDK);
            // Số chuyến vào cột E - đánh số tuần tự cho mỗi tàu
            // Reset counter khi chuyển sang tàu mới
            if($currentShip === null || $currentShip !== $ship) {
                $tripCounterForMonth = 0;
            }
            // Kiểm tra khối lượng vận chuyển để xác định có hàng hay không
            $klvcInt=toIntHelper($row['khoi_luong_van_chuyen_t']??0);
            $showTrip=!isset($prevTripByShip[$soChuyen]) && $soChuyen!=='' && $klvcInt > 0;
            if($showTrip) {
                $tripCounterForMonth++;
                $prevTripByShip[$soChuyen]=true;
            }
            $sheet->setCellValueByColumnAndRow(5,$currentRow,$showTrip?$tripCounterForMonth:'');
            
            // Xử lý cấp thêm dầu
            if($isCapThem){
                $lyDo=trim((string)($row['ly_do_cap_them']??''));
                $litVal=(float)($row['dau_tinh_toan_lit'] ?? ($row['so_luong_cap_them_lit']??0));
                $litInt=toIntHelper($litVal);
                // Hiển thị lý do cấp thêm vào cột TUYẾN ĐƯỜNG (cột F = 6) - chỉ hiển thị lý do, không có prefix
                // Xóa prefix "CẤP THÊM:" nếu có trong $lyDo
                $lyDoClean = preg_replace('/^CẤP THÊM:\s*/i', '', $lyDo);
                $sheet->setCellValueByColumnAndRow(6,$currentRow, $lyDoClean); // TUYẾN ĐƯỜNG cột F (6)
                // Hiển thị ngày cấp thêm vào cột NGÀY ĐI (cột P = 16)
                $ngayCapThem = !empty($row['ngay_di']) ? format_date_vn($row['ngay_di']) : '';
                $sheet->setCellValueByColumnAndRow(16,$currentRow,$ngayCapThem); // NGÀY ĐI cột P (16)
                setIntHelper($sheet,15,$currentRow,$litInt); // DẦU SD cột O (15)
                // Cấp thêm không có cự ly → Mặc định gán vào cột <80km (cột V = 22)
                setIntHelper($sheet,22,$currentRow,$litInt,true); // <80km cột V (22) - hiển thị '-' nếu = 0
                $subtotal[15]+=$litInt; // DẦU SD index (cột O)
                $subtotal[22]+=$litInt; // <80km (cột V)
                $sheet->getStyle("A{$currentRow}:X{$currentRow}")->applyFromArray($defaultCellStyle);
                $currentRow++;
                continue;
            }

            if($isChuyenDau){
                // Xử lý lệnh chuyển dầu
                $tauNguon = trim((string)($row['tau_nguon'] ?? ''));
                $tauDich = trim((string)($row['tau_dich'] ?? ''));
                $isChuyenOut = (bool)($row['is_chuyen_out'] ?? false);
                $isChuyenIn = (bool)($row['is_chuyen_in'] ?? false);
                
                // Xác định hướng và tàu đối tác
                $dir = $isChuyenOut ? 'out' : 'in';
                $other = $isChuyenOut ? $tauDich : $tauNguon;
                
                // Nếu $other rỗng, thử extract lại từ ly_do_chuyen_dau
                $lyDoChuyenDau = trim((string)($row['ly_do_chuyen_dau'] ?? ''));
                if ($other === '' && $lyDoChuyenDau !== '') {
                    // Normalize encoding lỗi
                    $lyDoNormalized = $lyDoChuyenDau;
                    $lyDoNormalized = str_replace(['Chuy?n', 'chuyn', 'Chuyn'], ['Chuyển', 'chuyển', 'Chuyển'], $lyDoNormalized);
                    $lyDoNormalized = str_replace(['d?u', 'du'], ['dầu', 'dầu'], $lyDoNormalized);
                    $lyDoNormalized = str_replace(['nh?n', 'nhan'], ['nhận', 'nhận'], $lyDoNormalized);
                    
                    // Pattern đơn giản: tìm tên tàu trong chuỗi
                    if (preg_match('/([A-Z]{2,}[0-9-]+)/', $lyDoChuyenDau, $matches)) {
                        $candidate = trim($matches[1]);
                        if ($candidate !== $ship && strlen($candidate) >= 3) {
                            $other = $candidate;
                            // Xác định lại dir dựa trên ly_do
                            if (strpos($lyDoNormalized, 'chuyển sang') !== false || strpos($lyDoNormalized, 'chuyn sang') !== false) {
                                $dir = 'out';
                            } elseif (strpos($lyDoNormalized, 'nhận từ') !== false || strpos($lyDoNormalized, 'nhan tu') !== false) {
                                $dir = 'in';
                            }
                        }
                    }
                }
                
                // Format route sử dụng td2_format_transfer_label
                $route = '';
                if ($other !== '') {
                    $route = td2_format_transfer_label($ship, $other, $dir);
                } else {
                    // Fallback: hiển thị lý do chuyển dầu hoặc mặc định
                    $route = $lyDoChuyenDau !== '' ? $lyDoChuyenDau : 'Chuyển dầu';
                }
                
                // Hiển thị tuyến đường vào cột F (TUYẾN ĐƯỜNG)
                $sheet->setCellValueByColumnAndRow(6,$currentRow,$route);
                
                // Hiển thị ngày chuyển dầu vào cột NGÀY ĐI (cột P = 16)
                $ngayChuyenDau = !empty($row['ngay_do_xong']) ? format_date_vn($row['ngay_do_xong']) : (!empty($row['ngay_di']) ? format_date_vn($row['ngay_di']) : '');
                $sheet->setCellValueByColumnAndRow(16,$currentRow,$ngayChuyenDau);
                
                // Đối với tàu chuyển đi (is_chuyen_out = true): hiển thị dầu tiêu hao dương ở cột DẦU SD
                // Đối với tàu nhận vào (is_chuyen_in = true): không hiển thị dầu tiêu hao (để trống hoặc 0)
                if ($isChuyenOut) {
                    // Tàu chuyển đi: hiển thị dầu tiêu hao dương
                    $soLuongChuyenDau = abs((float)($row['so_luong_chuyen_dau'] ?? 0));
                    if ($soLuongChuyenDau == 0) {
                        // Fallback: thử lấy từ so_luong hoặc so_luong_lit
                        $soLuongChuyenDau = abs((float)($row['so_luong'] ?? 0));
                        if ($soLuongChuyenDau == 0) {
                            $soLuongChuyenDau = abs((float)($row['so_luong_lit'] ?? 0));
                        }
                    }
                    $litInt = toIntHelper($soLuongChuyenDau);
                    setIntHelper($sheet,15,$currentRow,$litInt); // DẦU SD cột O (15)
                    $subtotal[15]+=$litInt; // Cộng vào tổng dầu sử dụng
                    // Chuyển dầu không có cự ly → Không gán vào các cột V,W,X (<80, 80-200, >200)
                    // Chỉ cộng vào bucket <80 để phản ánh lượng dầu
                    $subtotal[22]+=$litInt; // Cộng vào bucket <80
                } else {
                    // Tàu nhận vào: không hiển thị dầu tiêu hao
                    setIntHelper($sheet,15,$currentRow,0);
                }
                
                $sheet->getStyle("A{$currentRow}:X{$currentRow}")->applyFromArray($defaultCellStyle);
                $currentRow++;
                continue;
            }

            // TUYẾN ĐƯỜNG cột F: ưu tiên dùng route_hien_thi nếu có (đã lưu đầy đủ tuyến đường)
            $route = trim((string)($row['route_hien_thi'] ?? ''));
            if ($route === '') {
                // Fallback: xây dựng tuyến từ các điểm riêng lẻ (chỉ hiển thị 3 điểm)
                $isDoiLenh = (($row['doi_lenh'] ?? '0') == '1');
                $di = trim((string)($row['diem_di'] ?? ''));
                $den = trim((string)($row['diem_den'] ?? ''));
                $b = trim((string)($row['diem_du_kien'] ?? ''));
                if ($isDoiLenh) {
                    $route = $di . ' → ' . $b . ' (đổi lệnh) → ' . $den;
                } else if ($di !== '' || $den !== '') {
                    $route = $di . ' → ' . $den;
                } else {
                    // Chế độ nhập thủ công: không có điểm đi/đến → lấy từ ghi chú (lưu nguyên văn)
                    $route = trim((string)($row['ghi_chu'] ?? ''));
                }
            }
            $sheet->setCellValueByColumnAndRow(6,$currentRow,$route); // TUYẾN ĐƯỜNG cột F
            $val_kh=toIntHelper($row['cu_ly_khong_hang_km']??0); $val_ch=toIntHelper($row['cu_ly_co_hang_km']??0); $tot=$val_kh+$val_ch;
            setIntHelper($sheet,7,$currentRow,$val_kh); setIntHelper($sheet,8,$currentRow,$val_ch); setIntHelper($sheet,9,$currentRow,$tot); // CỰ LY cột G,H,I
            $sheet->setCellValueByColumnAndRow(10,$currentRow,$row['he_so_khong_hang']??''); $sheet->setCellValueByColumnAndRow(11,$currentRow,$row['he_so_co_hang']??''); // HS cột J,K
            $klvcInt=toIntHelper($row['khoi_luong_van_chuyen_t']??0); $kllcInt=toIntHelper($row['khoi_luong_luan_chuyen']??0); $fuelInt=toIntHelper($row['dau_tinh_toan_lit']??0);
            // Template có cột L: KL PKTTC (để trống), M: KLVC, N: SL LUÂN CHUYỂN, O: DẦU SD
            $sheet->setCellValueByColumnAndRow(12,$currentRow,''); // KL PKTTC - cột L - để trống
            setIntHelper($sheet,13,$currentRow,$klvcInt); // KLVC vào cột M
            setIntHelper($sheet,14,$currentRow,$kllcInt); // SL LUÂN CHUYỂN vào cột N
            setIntHelper($sheet,15,$currentRow,$fuelInt); // DẦU SD vào cột O
            // Format ngày sang dd/mm/yyyy
            $ngayDi = !empty($row['ngay_di']) ? format_date_vn($row['ngay_di']) : '';
            $ngayDen = !empty($row['ngay_den']) ? format_date_vn($row['ngay_den']) : '';
            $ngayDoXong = !empty($row['ngay_do_xong']) ? format_date_vn($row['ngay_do_xong']) : '';
            $sheet->setCellValueByColumnAndRow(16,$currentRow,$ngayDi); // NGÀY ĐI cột P
            $sheet->setCellValueByColumnAndRow(17,$currentRow,$ngayDen); // NGÀY ĐẾN cột Q
            $sheet->setCellValueByColumnAndRow(18,$currentRow,$ngayDoXong); // NGÀY DỠ XONG cột R
            // Chỉ hiển thị loại hàng khi có hàng (klvcInt > 0), không hàng thì để trống
            $loaiHangValue = ($klvcInt > 0) ? ($row['loai_hang'] ?? '') : '';
            $sheet->setCellValueByColumnAndRow(19,$currentRow,$loaiHangValue); // LOẠI HÀNG cột S
            // Cột T: TÊN TÀU (chỉ dùng trong BC TH, để trống trong BCTHANG)
            $sheet->setCellValueByColumnAndRow(21,$currentRow,$row['ghi_chu']??''); // GHI CHÚ cột U
            $v1=($tot>0 && $tot<80)?$fuelInt:0; $v2=($tot>=80 && $tot<=200)?$fuelInt:0; $v3=($tot>200)?$fuelInt:0;
            setIntHelper($sheet,22,$currentRow,$v1,true); setIntHelper($sheet,23,$currentRow,$v2,true); setIntHelper($sheet,24,$currentRow,$v3,true); // <80 V, 80-200 W, >200 X - hiển thị '-' nếu = 0
            $tripKey=$ship.'|'.$soChuyen; $isFirstTrip=($soChuyen!=='' && !isset($tripSeenByShip[$tripKey])); if($isFirstTrip){ $subtotal[7]+=$val_kh; $subtotal[8]+=$val_ch; $subtotal[9]+=$tot; $subtotal[13]+=$klvcInt; $subtotal[14]+=$kllcInt; $tripSeenByShip[$tripKey]=true; }
            $subtotal[15]+=$fuelInt; $subtotal[22]+=$v1; $subtotal[23]+=$v2; $subtotal[24]+=$v3;
            $sheet->getStyle("A{$currentRow}:X{$currentRow}")->applyFromArray($defaultCellStyle); $currentRow++;
        }
        if($currentShip){
            $tripCount=count($tripSeenByShip);
            $sheet->setCellValueByColumnAndRow(3,$currentRow,$currentShip.' Cộng');
            $sheet->setCellValueByColumnAndRow(4,$currentRow,'');
            $sheet->setCellValueByColumnAndRow(5,$currentRow,$tripCount);
            $sheet->setCellValueByColumnAndRow(6,$currentRow,'');
            foreach($sumCols as $c){
                $showDash = in_array($c, [22,23,24]); // Chỉ hiển thị '-' cho 3 cột cự ly
                setIntHelper($sheet,$c,$currentRow,$subtotal[$c],$showDash);
                $grandTotals[$c]+=$subtotal[$c];
            }
            $grandTotalTrips+=$tripCount;
            $sheet->getStyle("A{$currentRow}:X{$currentRow}")->applyFromArray(array_merge($defaultCellStyle,['font'=>['bold'=>true],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFF59D']]]));
            // Căn giữa cho cột số chuyến
            $sheet->getStyleByColumnAndRow(5,$currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $currentRow++;
        }
        $sheet->setCellValueByColumnAndRow(3,$currentRow,'TỔNG');
        $sheet->setCellValueByColumnAndRow(4,$currentRow,'');
        $sheet->setCellValueByColumnAndRow(5,$currentRow,$grandTotalTrips);
        $sheet->setCellValueByColumnAndRow(6,$currentRow,'');
        foreach($sumCols as $c){
            $showDash = in_array($c, [22,23,24]); // Chỉ hiển thị '-' cho 3 cột cự ly
            setIntHelper($sheet,$c,$currentRow,$grandTotals[$c],$showDash);
        }
        $sheet->getStyle("A{$currentRow}:X{$currentRow}")->applyFromArray(array_merge($defaultCellStyle,[
            'font'=>['bold'=>true],
            'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFE08A']]
        ]));
        // Căn giữa cho cột số chuyến
        $sheet->getStyleByColumnAndRow(5,$currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $spreadsheet->addExternalSheet($sheet); unset($tmpSpreadsheet); $sheetAdded=true;
    }
    if(!$sheetAdded){ if (!headers_sent()) { @header('X-Export-Stop: no_sheets'); } die('<pre style="color:red;font-size:16px;">LỖI: Không xuất được sheet nào! Do dữ liệu rỗng hoặc mapping sai.</pre>'); }
    
    // ========== TẠO SHEET BC TH (BÁO CÁO TỔNG HỢP THEO TÀU) ==========
    $templatePathBCTH = HeaderTemplate::pathFor('BC_TH');
    if (!$templatePathBCTH || !file_exists($templatePathBCTH)) {
        die('<pre style="color:red;font-size:16px;">LỖI: File template không tồn tại: ' . htmlspecialchars((string)$templatePathBCTH) . '</pre>');
    }
    $tmpSpreadsheet = IOFactory::load($templatePathBCTH);
    $sheet = $tmpSpreadsheet->getSheet(0);
    $sheet->setTitle('BC TH');

    // Tiêu đề chia làm 2 dòng (không có dòng ngày tháng năm)
    // Dòng 5: BẢNG TỔNG HỢP NHIÊN LIỆU SỬ DỤNG
    $sheet->setCellValue('A5', 'BẢNG TỔNG HỢP NHIÊN LIỆU SỬ DỤNG');
    $sheet->getStyle('A5')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->mergeCells('A5:L5');
    // Dòng 6: THÁNG X NĂM XXXX
    $sheet->setCellValue('A6', 'THÁNG ' . $currentMonth . ' NĂM ' . $currentYear);
    $sheet->getStyle('A6')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->mergeCells('A6:L6');

    // Gom toàn bộ dữ liệu cả công ty và thuê ngoài vào một mảng chung
    $allRows = [];
    foreach ($groups as $rowsInGroup) {
        if (empty($rowsInGroup)) { continue; }
        foreach ($rowsInGroup as $r) { $allRows[] = $r; }
    }
    if (empty($allRows)) {
        // Không có dữ liệu, vẫn thêm sheet trống để nhất quán
        $spreadsheet->addExternalSheet($sheet); unset($tmpSpreadsheet);
        return;
    }

    // Nhóm dữ liệu theo tàu
    $shipData = [];
    foreach($allRows as $row){
        $ship=trim($row['ten_phuong_tien']??'');
        if(!isset($shipData[$ship])) $shipData[$ship]=[];
        $shipData[$ship][]=$row;
    }
    ksort($shipData);

    // Thêm dòng "THÁNG XX-YYYY" ở dòng 9 (căn trái)
    $sheet->setCellValue('A9', 'THÁNG ' . sprintf('%02d', $currentMonth) . '-' . $currentYear);
    $sheet->getStyle('A9')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('FF0000');
    $sheet->getStyle('A9')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet->mergeCells('A9:L9');

    // Dữ liệu bắt đầu từ dòng 10 (sau dòng tháng)
    $currentRow=10; $stt=1; $sumCols=[4,5,6,7,8,9,10,11,12]; $grandTotals=array_fill_keys($sumCols,0);

    foreach($shipData as $ship=>$shipRows){
            $subtotal=array_fill_keys($sumCols,0); $tripSeen=[];
            
            foreach($shipRows as $row){
                $soChuyen=trim((string)($row['so_chuyen']??'')); $isCapThem=((int)($row['cap_them']??0)===1);

                if($isCapThem){
                    $litVal=(float)($row['dau_tinh_toan_lit'] ?? ($row['so_luong_cap_them_lit']??0)); $litInt=toIntHelper($litVal);
                    $subtotal[7]+=$litInt; // DẦU SỬ DỤNG KHÔNG HÀNG (cấp thêm không có hàng)
                    $subtotal[9]+=$litInt; // Tổng dầu SD
                    // Cấp thêm không có cự ly → Mặc định gán vào cột <80km
                    $subtotal[10]+=$litInt; // <80km
                    continue;
                }

                $tripKey=$ship.'|'.$soChuyen; $isFirstTrip=($soChuyen!=='' && !isset($tripSeen[$tripKey]));
                if($isFirstTrip){
                    $tot=toIntHelper($row['cu_ly_khong_hang_km']??0)+toIntHelper($row['cu_ly_co_hang_km']??0);
                    $klvcInt=toIntHelper($row['khoi_luong_van_chuyen_t']??0); $kllcInt=toIntHelper($row['khoi_luong_luan_chuyen']??0);
                    $subtotal[4]+=$tot; $subtotal[5]+=$klvcInt; $subtotal[6]+=$kllcInt;
                    $tripSeen[$tripKey]=true;
                }

                $fuelInt=toIntHelper($row['dau_tinh_toan_lit']??0);
                $tot=toIntHelper($row['cu_ly_khong_hang_km']??0)+toIntHelper($row['cu_ly_co_hang_km']??0);
                $kl=(float)($row['khoi_luong_van_chuyen_t']??0);
                // Phân loại dầu: KH (không hàng) vs CH (có hàng)
                if($kl <= 1e-6){ $subtotal[7]+=$fuelInt; } else { $subtotal[8]+=$fuelInt; }
                $subtotal[9]+=$fuelInt; // Tổng dầu
                $v1=($tot>0 && $tot<80)?$fuelInt:0; $v2=($tot>=80 && $tot<=200)?$fuelInt:0; $v3=($tot>200)?$fuelInt:0;
                $subtotal[10]+=$v1; $subtotal[11]+=$v2; $subtotal[12]+=$v3;
            }

            // Ghi dòng tổng cho tàu
            // A=STT, B=PHƯƠNG TIỆN, C=SỐ CHUYẾN, D=TỔNG CỰ LY, E=KLVC, F=SL LUÂN CHUYỂN, G-H=DẦU SD (KH/CH), I=TỔNG DẦU SD, J-L=<80/80-200/>200
            $sheet->setCellValueByColumnAndRow(1,$currentRow,$stt++);
            $sheet->setCellValueByColumnAndRow(2,$currentRow,$ship);
            $sheet->setCellValueByColumnAndRow(3,$currentRow,count($tripSeen));
            setIntHelper($sheet,4,$currentRow,$subtotal[4]); // TỔNG CỰ LY
            setIntHelper($sheet,5,$currentRow,$subtotal[5]); // KLVC
            setIntHelper($sheet,6,$currentRow,$subtotal[6]); // SL LUÂN CHUYỂN
            setIntHelper($sheet,7,$currentRow,$subtotal[7]); // DẦU SD KH
            setIntHelper($sheet,8,$currentRow,$subtotal[8]); // DẦU SD CH
            setIntHelper($sheet,9,$currentRow,$subtotal[9]); // TỔNG DẦU SD
            setIntHelper($sheet,10,$currentRow,$subtotal[10]); // <80
            setIntHelper($sheet,11,$currentRow,$subtotal[11]); // 80-200
            setIntHelper($sheet,12,$currentRow,$subtotal[12]); // >200
            $sheet->getStyle("A{$currentRow}:L{$currentRow}")->applyFromArray($defaultCellStyle);

            foreach($sumCols as $c){ $grandTotals[$c]+=$subtotal[$c]; }
            $currentRow++;
        }
        
    // Dòng tổng
    // Tính tổng số chuyến
    $totalTrips = 0;
    foreach($shipData as $ship=>$shipRows){
        $tripSeen=[];
        foreach($shipRows as $row){
            $soChuyen=trim((string)($row['so_chuyen']??''));
            $tripKey=$ship.'|'.$soChuyen;
            if($soChuyen!=='' && !isset($tripSeen[$tripKey])){ $tripSeen[$tripKey]=true; }
        }
        $totalTrips += count($tripSeen);
    }

    $sheet->setCellValueByColumnAndRow(2,$currentRow,'Tổng cộng:');
    $sheet->setCellValueByColumnAndRow(3,$currentRow,$totalTrips);
    foreach($sumCols as $c){ setIntHelper($sheet,$c,$currentRow,$grandTotals[$c]); }
    $sheet->getStyle("A{$currentRow}:L{$currentRow}")->applyFromArray(array_merge($defaultCellStyle,['font'=>['bold'=>true],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFE08A']]]));
    // Căn giữa cho cột số chuyến
    $sheet->getStyleByColumnAndRow(3,$currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $spreadsheet->addExternalSheet($sheet); unset($tmpSpreadsheet);

    // ========== TẠO SHEET DAUTON-SLCTY VÀ DAUTON-SLN ==========
    $templatePathDAUTON = HeaderTemplate::pathFor('DAUTON');
    if (!$templatePathDAUTON || !file_exists($templatePathDAUTON)) {
        die('<pre style="color:red;font-size:16px;">LỖI: File template không tồn tại: ' . htmlspecialchars((string)$templatePathDAUTON) . '</pre>');
    }
    foreach ($groups as $phanLoai => $rowsInGroup) {
        if (empty($rowsInGroup)) continue;
        $suffix = ($phanLoai === 'cong_ty') ? 'SLCTY' : 'SLN';
        createDAUTONSheet($spreadsheet, $templatePathDAUTON, $rowsInGroup, $currentMonth, $currentYear, $suffix, $tauModel, $dauTonModel, $ketQuaModel, ['borders' => [ 'allBorders' => ['borderStyle' => Border::BORDER_THIN] ], 'alignment' => [ 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true ] ]);
    }

    // Stream workbook once after adding all sheets
    $spreadsheet->setActiveSheetIndex(0);
    if(function_exists('ob_get_level')){ while(ob_get_level()>0){ @ob_end_clean(); } }
    @error_reporting(0);
    $filename = ($isDetailedExport ? 'CT_T' : 'BCTHANG_T') . $currentMonth . '_' . $currentYear . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0'); header('Pragma: public');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet); $writer->save('php://output');
    $spreadsheet->disconnectWorksheets(); unset($spreadsheet); exit();
}
