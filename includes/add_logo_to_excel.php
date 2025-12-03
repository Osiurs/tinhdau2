<?php
/**
 * Helper function to add company logo to Excel worksheet
 * Sử dụng cho các file export với PhpSpreadsheet
 */

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

/**
 * Thêm logo vào worksheet
 *
 * @param Worksheet $sheet Worksheet cần thêm logo
 * @param string $logoPath Đường dẫn đến file logo
 * @param string $cell Ô cần đặt logo (mặc định: B3)
 * @param int $height Chiều cao logo (pixels, mặc định: 60)
 * @return void
 */
function addLogoToWorksheet(Worksheet $sheet, $logoPath, $cell = 'B3', $height = 60) {
    if (!file_exists($logoPath)) {
        // Nếu không tìm thấy logo, bỏ qua
        return;
    }

    $drawing = new Drawing();
    $drawing->setName('Company Logo');
    $drawing->setDescription('VICEM Logistics Logo');
    $drawing->setPath($logoPath);
    $drawing->setCoordinates($cell);
    $drawing->setHeight($height);
    $drawing->setOffsetX(5);
    $drawing->setOffsetY(5);
    $drawing->setWorksheet($sheet);
}

/**
 * Thêm header đầy đủ với logo vào worksheet
 *
 * @param Worksheet $sheet Worksheet cần thêm header
 * @param string $logoPath Đường dẫn đến file logo
 * @param array $config Cấu hình tùy chỉnh
 * @return void
 */
function addHeaderWithLogoToWorksheet(Worksheet $sheet, $logoPath, $config = []) {
    // Cấu hình mặc định
    $defaults = [
        'company_name' => 'CÔNG TY CỔ PHẦN LOGISTICS VICEM',
        'department' => 'PHÒNG KỸ THUẬT VẬT TƯ',
        'nation' => 'CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM',
        'motto' => 'Độc Lập - Tự Do - Hạnh Phúc',
        'location' => 'Tp.Hồ Chí Minh',
        'num_cols' => 10,
        'logo_cell' => 'B3',
        'logo_height' => 60,
    ];
    $config = array_merge($defaults, $config);

    $half = floor($config['num_cols'] / 2);

    // Dòng 1: CÔNG TY và CỘNG HÒA
    $sheet->setCellValue('A1', $config['company_name']);
    $sheet->mergeCells('A1:' . getColumnLetter($half) . '1');
    $sheet->getStyle('A1')->applyFromArray([
        'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '34495E']],
        'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
    ]);

    $nationCol = getColumnLetter($half + 1);
    $lastCol = getColumnLetter($config['num_cols']);
    $sheet->setCellValue($nationCol . '1', $config['nation']);
    $sheet->mergeCells($nationCol . '1:' . $lastCol . '1');
    $sheet->getStyle($nationCol . '1')->applyFromArray([
        'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '34495E']],
        'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
    ]);

    // Dòng 2: PHÒNG KỸ THUẬT và ĐỘC LẬP
    $sheet->setCellValue('A2', $config['department']);
    $sheet->mergeCells('A2:' . getColumnLetter($half) . '2');
    $sheet->getStyle('A2')->applyFromArray([
        'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '34495E']],
        'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
    ]);

    $sheet->setCellValue($nationCol . '2', $config['motto']);
    $sheet->mergeCells($nationCol . '2:' . $lastCol . '2');
    $sheet->getStyle($nationCol . '2')->applyFromArray([
        'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '34495E']],
        'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
    ]);

    // Dòng 3: Logo
    $sheet->getRowDimension(3)->setRowHeight(40);
    addLogoToWorksheet($sheet, $logoPath, $config['logo_cell'], $config['logo_height']);

    // Dòng 4: Ngày tháng năm
    $ngay = date('d');
    $thang = date('m');
    $nam = date('Y');
    $dateText = $config['location'] . ', ngày ' . $ngay . ' tháng ' . $thang . ' năm ' . $nam;

    $sheet->setCellValue('A4', $dateText);
    $sheet->mergeCells('A4:' . $lastCol . '4');
    $sheet->getStyle('A4')->applyFromArray([
        'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '34495E']],
        'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
    ]);

    // Dòng 5 & 6: Trống (khoảng cách)
    $sheet->getRowDimension(5)->setRowHeight(10);
    $sheet->getRowDimension(6)->setRowHeight(10);
}

/**
 * Chuyển số cột thành chữ cái (1 -> A, 2 -> B, 27 -> AA, ...)
 *
 * @param int $columnNumber Số cột (bắt đầu từ 1)
 * @return string Chữ cái tương ứng
 */
function getColumnLetter($columnNumber) {
    $letter = '';
    while ($columnNumber > 0) {
        $temp = ($columnNumber - 1) % 26;
        $letter = chr($temp + 65) . $letter;
        $columnNumber = ($columnNumber - $temp - 1) / 26;
    }
    return $letter;
}
