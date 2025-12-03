<?php
/**
 * Helper để xuất Excel bằng XML với header đẹp
 * Load header từ file template sample_header.xlsx
 * Áp dụng cho TẤT CẢ các báo cáo
 */

/**
 * Xuất Excel XML với header đẹp
 *
 * @param array $data Dữ liệu xuất [[row1], [row2], ...]
 * @param array $headers Header cột ['Cột 1', 'Cột 2', ...]
 * @param string $sheetName Tên sheet
 * @param string $filename Tên file xuất
 * @param array $config Cấu hình thêm
 */
function exportExcelXMLWithHeader($data, $headers, $sheetName = 'Báo cáo', $filename = 'export.xls', $config = []) {
    // Clear buffer
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    }
    @error_reporting(0);

    // Cấu hình mặc định
    $config = array_merge([
        'title' => 'BẢNG TỔNG HỢP NHIÊN LIỆU SỬ DỤNG',
        'month' => date('m'),
        'year' => date('Y'),
        'location' => 'Tp.Hồ Chí Minh'
    ], $config);

    // Set headers
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Bắt đầu XML
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<?mso-application progid=\"Excel.Sheet\"?>\n";
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
        . ' xmlns:o="urn:schemas-microsoft-com:office:office"'
        . ' xmlns:x="urn:schemas-microsoft-com:office:excel"'
        . ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"'
        . ' xmlns:html="http://www.w3.org/TR/REC-html40">';

    // Styles
    echo '<Styles>';

    // Style tiêu đề chính
    echo '<Style ss:ID="TitleMain">'
        . '<Font ss:Bold="1" ss:Size="16" ss:Color="#2C3E50"/>'
        . '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
        . '</Style>';

    // Style tiêu đề phụ
    echo '<Style ss:ID="TitleSub">'
        . '<Font ss:Bold="1" ss:Size="11" ss:Color="#34495E"/>'
        . '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
        . '</Style>';

    // Style cho ô logo
    echo '<Style ss:ID="LogoCell">'
        . '<Font ss:Bold="1" ss:Size="10" ss:Color="#7F7F7F"/>'
        . '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
        . '</Style>';

    // Style header bảng
    echo '<Style ss:ID="Header">'
        . '<Font ss:Bold="1" ss:Color="#FFFFFF" ss:Size="11"/>'
        . '<Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>'
        . '<Interior ss:Color="#2C3E50" ss:Pattern="Solid"/>'
        . '<Borders>'
            . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>'
            . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>'
            . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>'
            . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>'
        . '</Borders>'
        . '</Style>';

    // Style dữ liệu
    echo '<Style ss:ID="Data">'
        . '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
        . '<Borders>'
            . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>'
            . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>'
            . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>'
            . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>'
        . '</Borders>'
        . '</Style>';

    echo '</Styles>';

    // Worksheet
    echo '<Worksheet ss:Name="' . htmlspecialchars($sheetName) . '">';
    echo '<Table>';

    // Độ rộng cột tự động
    $numCols = count($headers);
    for ($i = 0; $i < $numCols; $i++) {
        echo '<Column ss:Width="100"/>';
    }

    // ============================================================================
    // HEADER ĐẸP - GIỐNG TEMPLATE sample_header.xlsx (DÒNG 1-4)
    // ============================================================================

    // Dòng 1: CÔNG TY và CỘNG HÒA
    echo '<Row ss:Height="25">';
    $half = floor($numCols / 2);
    echo '<Cell ss:MergeAcross="' . ($half - 1) . '" ss:StyleID="TitleSub">'
        . '<Data ss:Type="String">CÔNG TY CỔ PHẦN LOGISTICS VICEM</Data>'
        . '</Cell>';
    echo '<Cell ss:MergeAcross="' . ($numCols - $half - 1) . '" ss:StyleID="TitleSub">'
        . '<Data ss:Type="String">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</Data>'
        . '</Cell>';
    echo '</Row>';

    // Dòng 2: PHÒNG KỸ THUẬT và ĐỘC LẬP
    echo '<Row ss:Height="20">';
    echo '<Cell ss:MergeAcross="' . ($half - 1) . '" ss:StyleID="TitleSub">'
        . '<Data ss:Type="String">PHÒNG KỸ THUẬT VẬT TƯ</Data>'
        . '</Cell>';
    echo '<Cell ss:MergeAcross="' . ($numCols - $half - 1) . '" ss:StyleID="TitleSub">'
        . '<Data ss:Type="String">Độc Lập - Tự Do - Hạnh Phúc</Data>'
        . '</Cell>';
    echo '</Row>';

    // Dòng 3: Logo (trống)
    echo '<Row ss:Height="20">';
    echo '<Cell ss:MergeAcross="' . ($numCols - 1) . '"><Data ss:Type="String"></Data></Cell>';
    echo '</Row>';

    // Dòng 4: Ngày tháng theo ngày hiện tại
    $ngay = date('d'); $thang = date('m'); $nam = date('Y');
    echo '<Row ss:Height="20">';
    echo '<Cell ss:MergeAcross="' . ($numCols - 1) . '" ss:StyleID="TitleSub">'
        . '<Data ss:Type="String">' . $config['location'] . ', ngày ' . $ngay . ' tháng ' . $thang . ' năm ' . $nam . '</Data>'
        . '</Cell>';
    echo '</Row>';

    // ============================================================================
    // DÒNG 5: TRỐNG (theo template)
    // ============================================================================
    echo '<Row ss:Height="10"><Cell/></Row>';

    // ============================================================================
    // DÒNG 6: HEADER BẢNG DỮ LIỆU
    // ============================================================================
    echo '<Row ss:Height="35">';
    foreach ($headers as $header) {
        echo '<Cell ss:StyleID="Header"><Data ss:Type="String">' . htmlspecialchars($header) . '</Data></Cell>';
    }
    echo '</Row>';

    // ============================================================================
    // DÒNG 7+: DỮ LIỆU
    // ============================================================================
    foreach ($data as $row) {
        echo '<Row ss:Height="25">';
        foreach ($row as $cell) {
            if (is_numeric($cell) && strpos((string)$cell, '.') !== false) {
                // Số thập phân
                echo '<Cell ss:StyleID="Data"><Data ss:Type="Number">' . $cell . '</Data></Cell>';
            } elseif (is_numeric($cell)) {
                // Số nguyên
                echo '<Cell ss:StyleID="Data"><Data ss:Type="Number">' . $cell . '</Data></Cell>';
            } else {
                // Chuỗi
                echo '<Cell ss:StyleID="Data"><Data ss:Type="String">' . htmlspecialchars($cell) . '</Data></Cell>';
            }
        }
        echo '</Row>';
    }

    echo '</Table>';
    echo '</Worksheet>';
    echo '</Workbook>';

    exit();
}

/**
 * Xuất Excel với NHIỀU SHEET
 *
 * @param array $sheets Mảng các sheet
 *   [
 *     [
 *       'name' => 'Sheet 1',
 *       'headers' => ['Cột 1', 'Cột 2'],
 *       'data' => [[row1], [row2]]
 *     ],
 *     ...
 *   ]
 * @param string $filename Tên file
 * @param array $config Cấu hình
 */
function exportExcelXMLMultiSheet($sheets, $filename = 'export.xls', $config = []) {
    // Clear buffer
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    }
    @error_reporting(0);

    // Cấu hình mặc định
    $config = array_merge([
        'location' => 'Tp.Hồ Chí Minh'
    ], $config);

    // Set headers
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Bắt đầu XML
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<?mso-application progid=\"Excel.Sheet\"?>\n";
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
        . ' xmlns:o="urn:schemas-microsoft-com:office:office"'
        . ' xmlns:x="urn:schemas-microsoft-com:office:excel"'
        . ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"'
        . ' xmlns:html="http://www.w3.org/TR/REC-html40">';

    // Styles (giống trên)
    echo '<Styles>';
    echo '<Style ss:ID="TitleMain"><Font ss:Bold="1" ss:Size="16" ss:Color="#2C3E50"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/></Style>';
    echo '<Style ss:ID="TitleSub"><Font ss:Bold="1" ss:Size="11" ss:Color="#34495E"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/></Style>';
    echo '<Style ss:ID="Header"><Font ss:Bold="1" ss:Color="#FFFFFF" ss:Size="11"/><Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/><Interior ss:Color="#2C3E50" ss:Pattern="Solid"/><Borders><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';
    echo '<Style ss:ID="Data"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Borders><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';
    echo '</Styles>';

    // Xuất từng sheet
    foreach ($sheets as $sheet) {
        $sheetName = $sheet['name'] ?? 'Sheet';
        $headers = $sheet['headers'] ?? [];
        $data = $sheet['data'] ?? [];
        $numCols = count($headers);

        echo '<Worksheet ss:Name="' . htmlspecialchars($sheetName) . '">';
        echo '<Table>';

        // Độ rộng cột
        for ($i = 0; $i < $numCols; $i++) {
            echo '<Column ss:Width="100"/>';
        }

        // HEADER TEMPLATE (dòng 1-4)
        $half = floor($numCols / 2);

        echo '<Row ss:Height="25">';
        echo '<Cell ss:MergeAcross="' . ($half - 1) . '" ss:StyleID="TitleSub"><Data ss:Type="String">CÔNG TY CỔ PHẦN LOGISTICS VICEM</Data></Cell>';
        echo '<Cell ss:MergeAcross="' . ($numCols - $half - 1) . '" ss:StyleID="TitleSub"><Data ss:Type="String">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</Data></Cell>';
        echo '</Row>';

        echo '<Row ss:Height="20">';
        echo '<Cell ss:MergeAcross="' . ($half - 1) . '" ss:StyleID="TitleSub"><Data ss:Type="String">PHÒNG KỸ THUẬT VẬT TƯ</Data></Cell>';
        echo '<Cell ss:MergeAcross="' . ($numCols - $half - 1) . '" ss:StyleID="TitleSub"><Data ss:Type="String">Độc Lập - Tự Do - Hạnh Phúc</Data></Cell>';
        echo '</Row>';

        echo '<Row ss:Height="20"><Cell ss:MergeAcross="' . ($numCols - 1) . '"><Data ss:Type="String"></Data></Cell></Row>';

        $ngay = date('d'); $thang = date('m'); $nam = date('Y');
        echo '<Row ss:Height="20">';
        echo '<Cell ss:MergeAcross="' . ($numCols - 1) . '" ss:StyleID="TitleSub"><Data ss:Type="String">' . $config['location'] . ', ngày ' . $ngay . ' tháng ' . $thang . ' năm ' . $nam . '</Data></Cell>';
        echo '</Row>';

        // Dòng 5: Trống
        echo '<Row ss:Height="10"><Cell/></Row>';

        // Dòng 6: Header bảng
        echo '<Row ss:Height="35">';
        foreach ($headers as $header) {
            echo '<Cell ss:StyleID="Header"><Data ss:Type="String">' . htmlspecialchars($header) . '</Data></Cell>';
        }
        echo '</Row>';

        // Dòng 7+: Dữ liệu
        foreach ($data as $row) {
            echo '<Row ss:Height="25">';
            foreach ($row as $cell) {
                if (is_numeric($cell) && strpos((string)$cell, '.') !== false) {
                    echo '<Cell ss:StyleID="Data"><Data ss:Type="Number">' . $cell . '</Data></Cell>';
                } elseif (is_numeric($cell)) {
                    echo '<Cell ss:StyleID="Data"><Data ss:Type="Number">' . $cell . '</Data></Cell>';
                } else {
                    echo '<Cell ss:StyleID="Data"><Data ss:Type="String">' . htmlspecialchars($cell) . '</Data></Cell>';
                }
            }
            echo '</Row>';
        }

        echo '</Table>';
        echo '</Worksheet>';
    }

    echo '</Workbook>';
    exit();
}
