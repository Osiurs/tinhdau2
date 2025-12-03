<?php
/**
 * Wrapper để chuyển đổi từ xuất XML sang xuất với PhpSpreadsheet
 * Cho phép giữ nguyên logic cũ và dễ dàng chuyển đổi sang template
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/excel_helper.php';
require_once __DIR__ . '/../src/Report/HeaderTemplate.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use App\Report\HeaderTemplate;

/**
 * Export báo cáo lịch sử với template
 * Thay thế cho phần export XML trong lich_su.php
 */
function exportLichSuWithTemplate($exportData, $config = []) {
    try {
        // Lấy cấu hình
        $filename = $config['filename'] ?? 'BCTHANG.xlsx';
        $currentMonth = $config['currentMonth'] ?? date('n');
        $currentYear = $config['currentYear'] ?? date('Y');
        $isDetailedExport = $config['isDetailedExport'] ?? false;

        // Chọn template mặc định cho workbook (sheet đầu)
        $templatePath = HeaderTemplate::pathFor('BCTHANG');
        if (!$templatePath || !file_exists($templatePath)) {
            throw new Exception("File template không tồn tại: $templatePath");
        }

        // Load template đầu tiên
        $spreadsheet = IOFactory::load($templatePath);

        // Xóa các sheet mặc định nếu có (giữ sheet đầu tiên làm template)
        while ($spreadsheet->getSheetCount() > 1) {
            $spreadsheet->removeSheetByIndex(1);
        }

        // Lấy sheet template (sheet đầu chứa header+logo)
        $templateSheet = $spreadsheet->getSheet(0);

        // Tuyệt đối không ghi đè header: dữ liệu thực tế chỉ bắt đầu từ dòng 7 trở đi
        $headerRowExcel = 7;

        // sheetIndex = 0 xử lý riêng, các sheet tiếp theo load lại template!
        $sheetIndex = 0;
        foreach ($exportData['sheets'] as $sheetData) {
            if ($sheetIndex === 0) {
                $sheet = $spreadsheet->getSheet(0);
                $sheet->setTitle($sheetData['name']);
                \App\Report\HeaderTemplate::applyCommonHeader($sheet, 'F4');
            } else {
                // Với từng sheet, chọn template theo tên sheet
                $name = (string)($sheetData['name'] ?? '');
                if (stripos($name, 'IN TINH DAU') === 0) {
                    $sheetTemplatePath = HeaderTemplate::pathFor('IN_TINH_DAU');
                } elseif (stripos($name, 'DAUTON') === 0) {
                    $sheetTemplatePath = HeaderTemplate::pathFor('DAUTON');
                } elseif (stripos($name, 'BC TH') === 0) {
                    $sheetTemplatePath = HeaderTemplate::pathFor('BC_TH');
                } else {
                    $sheetTemplatePath = HeaderTemplate::pathFor('BCTHANG');
                }
                if (!$sheetTemplatePath || !file_exists($sheetTemplatePath)) {
                    throw new Exception("File template không tồn tại: $sheetTemplatePath");
                }
                // LUÔN LUÔN load lại template cho mỗi sheet để giữ logo (KHÔNG dùng clone)
                $tmp = IOFactory::load($sheetTemplatePath);
                $sheet = $tmp->getSheet(0);
                $sheet->setTitle($sheetData['name']);
                $spreadsheet->addSheet($sheet);
                unset($tmp);
                \App\Report\HeaderTemplate::applyCommonHeader($sheet, 'F4');
            }
            // Ghi header bảng từ row 7
            $col = 1;
            foreach ($sheetData['headers'] as $header) {
                $cell = $sheet->getCellByColumnAndRow($col, $headerRowExcel);
                $cell->setValue($header);
                $cell->getStyle()->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '2C3E50']
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000']
                        ]
                    ]
                ]);
                $col++;
            }
            // Ghi dữ liệu thực tế bắt đầu từ dòng 8 trở đi
            $currentRow = $headerRowExcel + 1;
            foreach ($sheetData['rows'] as $rowData) {
                $col = 1;
                foreach ($rowData as $cellValue) {
                    $sheet->setCellValueByColumnAndRow($col, $currentRow, $cellValue);
                    $sheet->getStyleByColumnAndRow($col, $currentRow)->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => '000000']
                            ]
                        ],
                        'alignment' => [
                            'vertical' => Alignment::VERTICAL_CENTER
                        ]
                    ]);
                    $col++;
                }
                $currentRow++;
            }
            // Auto-size columns
            foreach (range(1, count($sheetData['headers'])) as $colNum) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNum);
                $sheet->getColumnDimension($colLetter)->setAutoSize(true);
            }
            $sheetIndex++;
        }

        // Clear output buffer
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
        }

        @error_reporting(0);

        // Set headers
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        // Export
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');

        // Cleanup
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        exit();

    } catch (Exception $e) {
        die('Lỗi khi xuất Excel: ' . $e->getMessage());
    }
}

/**
 * Export báo cáo dầu tồn với template đơn giản
 */
function exportBaoCaoDauTonWithTemplate($data, $thang) {
    try {
        $templatePath = HeaderTemplate::pathFor('DAUTON');

        if (!$templatePath || !file_exists($templatePath)) {
            throw new Exception("File template không tồn tại");
        }

        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        // Cập nhật tiêu đề
        $sheet->setCellValue('A1', 'BÁO CÁO NHIÊN LIỆU');
        $sheet->setCellValue('A2', 'THÁNG ' . $thang);

        // Merge cells cho tiêu đề
        $sheet->mergeCells('A1:K1');
        $sheet->mergeCells('A2:K2');

        // Style tiêu đề
        $sheet->getStyle('A1:A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);

        // Header bảng dữ liệu tại dòng 5
        $headers = ['STT', 'Tháng', 'Tên tàu', 'Loại tàu', 'Số chuyến',
                   'Kế hoạch', 'Tháng trước', 'Nhiên liệu', 'SL đo', 'Ghi chú'];

        $col = 1;
        foreach ($headers as $header) {
            $cell = $sheet->getCellByColumnAndRow($col, 5);
            $cell->setValue($header);
            $cell->getStyle()->applyFromArray([
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'D3D3D3']
                ],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN]
                ]
            ]);
            $col++;
        }

        // Ghi dữ liệu từ dòng 6
        $row = 6;
        foreach ($data as $rowData) {
            $col = 1;
            foreach ($rowData as $value) {
                $sheet->setCellValueByColumnAndRow($col, $row, $value);
                $col++;
            }
            $row++;
        }

        // Clear buffer
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
        }

        @error_reporting(0);

        // Headers
        $filename = 'Bao_cao_dau_ton_' . str_replace('/', '_', $thang) . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        // Export
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        exit();

    } catch (Exception $e) {
        die('Lỗi: ' . $e->getMessage());
    }
}
