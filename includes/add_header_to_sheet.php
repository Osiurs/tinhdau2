<?php
/**
 * Hàm helper để thêm header template vào đầu mỗi sheet
 * Sử dụng cho file lich_su.php và bao_cao_dau_ton.php
 */

/**
 * In header template cho 1 sheet (dòng 1-5)
 *
 * @param int $numCols Số cột của bảng
 * @param string $location Địa điểm (mặc định: Tp.Hồ Chí Minh)
 */
function printSheetHeaderTemplate($numCols = 10, $location = 'Tp.Hồ Chí Minh') {
    $half = floor($numCols / 2);

    // Lấy ngày tháng năm hiện tại
    $ngay = date('d');
    $thang = date('m');
    $nam = date('Y');

    // Dòng 1: CÔNG TY và CỘNG HÒA
    echo '<Row ss:Height="25">';
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

    // Dòng 3: Logo (không thể nhúng ảnh vào XML SpreadsheetML, chỉ có thể thêm text thay thế)
    // Chú ý: Nếu sử dụng template .xlsx thì logo đã được nhúng sẵn ở cell B3
    echo '<Row ss:Height="40">';
    echo '<Cell ss:MergeAcross="' . ($half - 1) . '" ss:StyleID="LogoCell">'
        . '<Data ss:Type="String">[LOGO VICEM]</Data>'
        . '</Cell>';
    if ($numCols > $half) {
        echo '<Cell ss:MergeAcross="' . ($numCols - $half - 1) . '"><Data ss:Type="String"></Data></Cell>';
    }
    echo '</Row>';

    // Dòng 4: Ngày tháng năm TỰ ĐỘNG
    echo '<Row ss:Height="20">';
    echo '<Cell ss:MergeAcross="' . ($numCols - 1) . '" ss:StyleID="TitleSub">'
        . '<Data ss:Type="String">' . $location . ', ngày ' . $ngay . ' tháng ' . $thang . ' năm ' . $nam . '</Data>'
        . '</Cell>';
    echo '</Row>';

    // Dòng 5 & 6: Trống (tạo khoảng cách giữa header và bảng báo cáo)
    echo '<Row ss:Height="10"><Cell/></Row>';
    echo '<Row ss:Height="10"><Cell/></Row>';
}

/**
 * In style cho header template
 * Thêm vào phần Styles của Workbook
 */
function printHeaderStyles() {
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
}
