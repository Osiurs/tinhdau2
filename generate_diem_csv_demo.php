<?php
/**
 * Script tự động tạo file diem.csv từ dữ liệu hiện có
 * Scan tất cả điểm trong khoang_duong.csv và tạo ID unique
 *
 * DEMO - Chưa áp dụng vào hệ thống chính
 */

require_once __DIR__ . '/config/database.php';

// Đường dẫn file
$khoangDuongFile = __DIR__ . '/khoang_duong.csv';
$outputFile = __DIR__ . '/data/diem_generated.csv';

// Đảm bảo thư mục data tồn tại
if (!is_dir(__DIR__ . '/data')) {
    mkdir(__DIR__ . '/data', 0755, true);
}

echo "=== DEMO: Tạo file danh sách điểm với ID ===\n\n";

// 1. Đọc file khoang_duong.csv
if (!file_exists($khoangDuongFile)) {
    die("ERROR: File không tồn tại: $khoangDuongFile\n");
}

$handle = fopen($khoangDuongFile, 'r');
if (!$handle) {
    die("ERROR: Không thể mở file: $khoangDuongFile\n");
}

// Bỏ qua header
fgetcsv($handle);

// Mảng lưu tất cả điểm unique (normalize để tránh trùng)
$diemMap = []; // [normalized_name => original_name]
$diemList = []; // Danh sách điểm unique để xuất

/**
 * Chuẩn hóa tên điểm (giống KhoangCach.php)
 */
function normalizeDiem($str) {
    if (empty($str)) return '';

    // Normalize Unicode to NFC
    if (function_exists('normalizer_normalize')) {
        $str = normalizer_normalize($str, Normalizer::FORM_C);
    }

    // Loại bỏ dấu tiếng Việt
    $str = str_replace(
        ['à', 'á', 'ạ', 'ả', 'ã', 'â', 'ầ', 'ấ', 'ậ', 'ẩ', 'ẫ', 'ă', 'ằ', 'ắ', 'ặ', 'ẳ', 'ẵ'],
        ['a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a'],
        $str
    );
    $str = str_replace(['è', 'é', 'ẹ', 'ẻ', 'ẽ', 'ê', 'ề', 'ế', 'ệ', 'ể', 'ễ'], ['e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e'], $str);
    $str = str_replace(['ì', 'í', 'ị', 'ỉ', 'ĩ'], ['i', 'i', 'i', 'i', 'i'], $str);
    $str = str_replace(
        ['ò', 'ó', 'ọ', 'ỏ', 'õ', 'ô', 'ồ', 'ố', 'ộ', 'ổ', 'ỗ', 'ơ', 'ờ', 'ớ', 'ợ', 'ở', 'ỡ'],
        ['o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o'],
        $str
    );
    $str = str_replace(['ù', 'ú', 'ụ', 'ủ', 'ũ', 'ư', 'ừ', 'ứ', 'ự', 'ử', 'ữ'], ['u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u'], $str);
    $str = str_replace(['ỳ', 'ý', 'ỵ', 'ỷ', 'ỹ'], ['y', 'y', 'y', 'y', 'y'], $str);
    $str = str_replace(['đ', 'Đ'], ['d', 'd'], $str);

    // Loại bỏ ký tự đặc biệt
    $str = preg_replace('/[^a-zA-Z0-9\s()]/', '', $str);
    $str = preg_replace('/\s+/', ' ', $str);

    return strtolower(trim($str));
}

/**
 * Tạo mã điểm từ tên (viết tắt)
 */
function generateMaDiem($tenDiem, $existingCodes) {
    // Lấy chữ cái đầu mỗi từ
    $words = explode(' ', $tenDiem);
    $ma = '';

    foreach ($words as $word) {
        $word = trim($word);
        // Bỏ qua từ trong ngoặc
        if (preg_match('/^\(/', $word)) continue;
        if (!empty($word)) {
            $ma .= strtoupper(substr($word, 0, 1));
        }
    }

    // Nếu mã đã tồn tại, thêm số
    $originalMa = $ma;
    $counter = 1;
    while (in_array($ma, $existingCodes)) {
        $ma = $originalMa . $counter;
        $counter++;
    }

    return $ma;
}

/**
 * Extract tỉnh thành từ tên điểm (trong ngoặc)
 */
function extractTinhThanh($tenDiem) {
    if (preg_match('/\(([^)]+)\)/', $tenDiem, $matches)) {
        return $matches[1];
    }
    return '';
}

/**
 * Xác định loại điểm
 */
function detectLoaiDiem($tenDiem) {
    $tenLower = mb_strtolower($tenDiem, 'UTF-8');

    if (strpos($tenLower, 'cảng') !== false) return 'cang';
    if (strpos($tenLower, 'phao') !== false) return 'phao';
    if (strpos($tenLower, 'tn ') === 0 || strpos($tenLower, 'tn') === 0) return 'cang'; // TN = Tàu Nhỏ
    if (strpos($tenLower, 'xm ') === 0) return 'nha_may'; // XM = Xí Nghiệp
    if (strpos($tenLower, 'nhà máy') !== false) return 'nha_may';
    if (strpos($tenLower, 'fico') !== false) return 'kho';

    return 'khac';
}

// 2. Scan tất cả điểm
echo "Bước 1: Scan tất cả điểm từ file khoang_duong.csv...\n";
while (($data = fgetcsv($handle)) !== false) {
    if (count($data) >= 4) {
        $diemDau = trim($data[1]);
        $diemCuoi = trim($data[2]);

        // Thêm điểm đầu
        $normDau = normalizeDiem($diemDau);
        if (!isset($diemMap[$normDau]) && !empty($diemDau)) {
            $diemMap[$normDau] = $diemDau;
        }

        // Thêm điểm cuối
        $normCuoi = normalizeDiem($diemCuoi);
        if (!isset($diemMap[$normCuoi]) && !empty($diemCuoi)) {
            $diemMap[$normCuoi] = $diemCuoi;
        }
    }
}
fclose($handle);

echo "   → Tìm thấy " . count($diemMap) . " điểm unique\n\n";

// 3. Tạo ID và thông tin cho mỗi điểm
echo "Bước 2: Tạo ID và mã cho mỗi điểm...\n";
$idCounter = 1;
$existingCodes = [];

foreach ($diemMap as $normalized => $originalName) {
    $maDiem = generateMaDiem($originalName, $existingCodes);
    $existingCodes[] = $maDiem;

    $diemList[] = [
        'id_diem' => $idCounter,
        'ten_diem' => $originalName,
        'ma_diem' => $maDiem,
        'tinh_thanh' => extractTinhThanh($originalName),
        'loai_diem' => detectLoaiDiem($originalName)
    ];

    $idCounter++;
}

// Sắp xếp theo tên
usort($diemList, function($a, $b) {
    return strcasecmp($a['ten_diem'], $b['ten_diem']);
});

// Tạo lại ID sau khi sort
for ($i = 0; $i < count($diemList); $i++) {
    $diemList[$i]['id_diem'] = $i + 1;
}

echo "   → Đã tạo " . count($diemList) . " bản ghi\n\n";

// 4. Ghi ra file CSV
echo "Bước 3: Ghi vào file $outputFile...\n";
$outHandle = fopen($outputFile, 'w');
if (!$outHandle) {
    die("ERROR: Không thể tạo file: $outputFile\n");
}

// Ghi BOM UTF-8
fwrite($outHandle, "\xEF\xBB\xBF");

// Header
fputcsv($outHandle, ['id_diem', 'ten_diem', 'ma_diem', 'tinh_thanh', 'loai_diem']);

// Data
foreach ($diemList as $diem) {
    fputcsv($outHandle, $diem);
}

fclose($outHandle);

echo "   → Hoàn thành!\n\n";

// 5. Hiển thị preview
echo "=== Preview 10 điểm đầu tiên ===\n";
printf("%-5s %-40s %-10s %-15s %-10s\n", 'ID', 'Tên điểm', 'Mã', 'Tỉnh', 'Loại');
echo str_repeat('-', 85) . "\n";

for ($i = 0; $i < min(10, count($diemList)); $i++) {
    $d = $diemList[$i];
    printf("%-5d %-40s %-10s %-15s %-10s\n",
        $d['id_diem'],
        mb_substr($d['ten_diem'], 0, 40),
        $d['ma_diem'],
        $d['tinh_thanh'],
        $d['loai_diem']
    );
}

echo "\n";
echo "=== Thống kê ===\n";
echo "Tổng số điểm: " . count($diemList) . "\n";

// Thống kê theo loại
$stats = [];
foreach ($diemList as $d) {
    $loai = $d['loai_diem'];
    if (!isset($stats[$loai])) {
        $stats[$loai] = 0;
    }
    $stats[$loai]++;
}

echo "\nPhân loại:\n";
foreach ($stats as $loai => $count) {
    echo "  - $loai: $count\n";
}

echo "\n";
echo "✅ File đã được tạo: $outputFile\n";
echo "📖 Xem thêm chi tiết trong: PROPOSAL_DIEM_ID_SYSTEM.md\n";
?>
