<?php
/**
 * Helper functions cho việc xuất Excel với template
 */

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Xuất dữ liệu ra Excel sử dụng file template có sẵn
 *
 * @param string $templatePath Đường dẫn đến file template Excel (.xlsx)
 * @param array $data Mảng 2 chiều chứa dữ liệu cần xuất [row][column]
 * @param int $startRow Dòng bắt đầu chèn dữ liệu (mặc định là 6)
 * @param int $startCol Cột bắt đầu chèn dữ liệu (mặc định là 1 = cột A)
 * @param string $outputFilename Tên file output (không bao gồm đường dẫn)
 * @param int $sheetIndex Sheet index để ghi dữ liệu (mặc định là 0 = sheet đầu tiên)
 * @return void
 */
function exportExcelWithTemplate($templatePath, $data, $startRow = 6, $startCol = 1, $outputFilename = 'export.xlsx', $sheetIndex = 0) {
    try {
        // Kiểm tra file template có tồn tại không
        if (!file_exists($templatePath)) {
            throw new Exception("File template không tồn tại: $templatePath");
        }

        // Load file template
        $spreadsheet = IOFactory::load($templatePath);

        // Chọn sheet cần ghi dữ liệu
        $sheet = $spreadsheet->setActiveSheetIndex($sheetIndex);

        // Ghi dữ liệu vào sheet từ dòng startRow
        $currentRow = $startRow;
        foreach ($data as $rowData) {
            $currentCol = $startCol;
            foreach ($rowData as $cellValue) {
                $sheet->setCellValueByColumnAndRow($currentCol, $currentRow, $cellValue);
                $currentCol++;
            }
            $currentRow++;
        }

        // Xóa hết output buffer để tránh lỗi
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
        }

        // Tắt hiển thị lỗi
        if (function_exists('ini_set')) {
            @ini_set('display_errors', '0');
            @ini_set('display_startup_errors', '0');
        }
        @error_reporting(0);

        // Set headers để download file
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $outputFilename . '"');
        header('Cache-Control: max-age=0');
        header('Cache-Control: max-age=1');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: cache, must-revalidate');
        header('Pragma: public');

        // Xuất file
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');

        // Giải phóng bộ nhớ
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        exit();

    } catch (Exception $e) {
        // Xử lý lỗi
        die('Lỗi khi xuất Excel: ' . $e->getMessage());
    }
}

/**
 * Xuất dữ liệu ra Excel với template và tự động format các cột
 *
 * @param string $templatePath Đường dẫn đến file template
 * @param array $data Dữ liệu cần xuất
 * @param array $config Cấu hình chi tiết
 *   - startRow: dòng bắt đầu (mặc định 6)
 *   - startCol: cột bắt đầu (mặc định 1)
 *   - sheetIndex: sheet index (mặc định 0)
 *   - columnFormats: mảng format cho từng cột ['A' => 'number', 'B' => 'date', 'C' => 'text']
 *   - columnWidths: mảng độ rộng cột ['A' => 15, 'B' => 20]
 * @param string $outputFilename Tên file output
 * @return void
 */
function exportExcelWithTemplateAdvanced($templatePath, $data, $config = [], $outputFilename = 'export.xlsx') {
    try {
        // Cấu hình mặc định
        $startRow = $config['startRow'] ?? 6;
        $startCol = $config['startCol'] ?? 1;
        $sheetIndex = $config['sheetIndex'] ?? 0;
        $columnFormats = $config['columnFormats'] ?? [];
        $columnWidths = $config['columnWidths'] ?? [];

        // Kiểm tra file template
        if (!file_exists($templatePath)) {
            throw new Exception("File template không tồn tại: $templatePath");
        }

        // Load template
        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->setActiveSheetIndex($sheetIndex);

        // Set độ rộng cột nếu có
        foreach ($columnWidths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // Ghi dữ liệu
        $currentRow = $startRow;
        foreach ($data as $rowData) {
            $currentCol = $startCol;
            foreach ($rowData as $cellValue) {
                $cellCoordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($currentCol) . $currentRow;
                $sheet->setCellValue($cellCoordinate, $cellValue);

                // Áp dụng format nếu có
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($currentCol);
                if (isset($columnFormats[$colLetter])) {
                    $format = $columnFormats[$colLetter];
                    switch ($format) {
                        case 'number':
                            $sheet->getStyle($cellCoordinate)->getNumberFormat()
                                ->setFormatCode('#,##0.00');
                            break;
                        case 'integer':
                            $sheet->getStyle($cellCoordinate)->getNumberFormat()
                                ->setFormatCode('#,##0');
                            break;
                        case 'date':
                            $sheet->getStyle($cellCoordinate)->getNumberFormat()
                                ->setFormatCode('dd/mm/yyyy');
                            break;
                        case 'datetime':
                            $sheet->getStyle($cellCoordinate)->getNumberFormat()
                                ->setFormatCode('dd/mm/yyyy hh:mm:ss');
                            break;
                    }
                }

                $currentCol++;
            }
            $currentRow++;
        }

        // Xóa output buffer
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
        }

        // Tắt error display
        if (function_exists('ini_set')) {
            @ini_set('display_errors', '0');
            @ini_set('display_startup_errors', '0');
        }
        @error_reporting(0);

        // Set headers
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $outputFilename . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        // Xuất file
        $writer = new Xlsx($spreadsheet);
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
 * Xuất nhiều sheet với template
 *
 * @param string $templatePath Đường dẫn template
 * @param array $sheets Mảng các sheet cần xuất
 *   [
 *     [
 *       'sheetIndex' => 0,
 *       'data' => [...],
 *       'startRow' => 6,
 *       'startCol' => 1
 *     ],
 *     ...
 *   ]
 * @param string $outputFilename Tên file output
 * @return void
 */
function exportExcelMultiSheetWithTemplate($templatePath, $sheets, $outputFilename = 'export.xlsx') {
    try {
        if (!file_exists($templatePath)) {
            throw new Exception("File template không tồn tại: $templatePath");
        }

        // Load template
        $spreadsheet = IOFactory::load($templatePath);

        // Xử lý từng sheet
        foreach ($sheets as $sheetConfig) {
            $sheetIndex = $sheetConfig['sheetIndex'] ?? 0;
            $data = $sheetConfig['data'] ?? [];
            $startRow = $sheetConfig['startRow'] ?? 6;
            $startCol = $sheetConfig['startCol'] ?? 1;

            $sheet = $spreadsheet->setActiveSheetIndex($sheetIndex);

            // Ghi dữ liệu
            $currentRow = $startRow;
            foreach ($data as $rowData) {
                $currentCol = $startCol;
                foreach ($rowData as $cellValue) {
                    $sheet->setCellValueByColumnAndRow($currentCol, $currentRow, $cellValue);
                    $currentCol++;
                }
                $currentRow++;
            }
        }

        // Xóa buffer
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
        }

        @error_reporting(0);

        // Headers
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $outputFilename . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        // Export
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        exit();

    } catch (Exception $e) {
        die('Lỗi khi xuất Excel: ' . $e->getMessage());
    }
}
