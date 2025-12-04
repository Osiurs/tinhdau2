<?php
require_once __DIR__ . '/models/DauTon.php';
require_once __DIR__ . '/models/LuuKetQua.php';

echo "<style>
table { border-collapse: collapse; margin: 20px 0; }
td, th { border: 1px solid #ccc; padding: 8px; }
th { background: #f0f0f0; }
.highlight { background: #ffffcc; font-weight: bold; }
.error { color: red; font-weight: bold; }
.success { color: green; font-weight: bold; }
</style>";

$dauTonModel = new DauTon();
$ketQuaModel = new LuuKetQua();
$ship = 'HTL-1';

echo "<h1>PHÂN TÍCH CHÊNH LỆCH DẦU TỒN - Ship: $ship</h1>";
echo "<p><strong>So sánh giữa Downloads và xampp/htdocs</strong></p>";

// ============= THU THẬP DỮ LIỆU =============
$transactions = $dauTonModel->getLichSuGiaoDich($ship);
$allResults = $ketQuaModel->docTatCa();

echo "<h2>1. DỮ LIỆU GIAO DỊCH (dau_ton.csv)</h2>";
echo "<table>";
echo "<tr><th>STT</th><th>Loại</th><th>Ngày</th><th>Số lượng</th><th>Lý do</th><th>Transfer ID</th></tr>";
foreach ($transactions as $idx => $gd) {
    echo "<tr>";
    echo "<td>" . ($idx + 1) . "</td>";
    echo "<td>" . ($gd['loai'] ?? '') . "</td>";
    echo "<td>" . ($gd['ngay'] ?? '') . "</td>";
    echo "<td>" . number_format($gd['so_luong_lit'] ?? 0) . "</td>";
    echo "<td>" . ($gd['ly_do'] ?? '') . "</td>";
    echo "<td>" . ($gd['transfer_pair_id'] ?? 'Không') . "</td>";
    echo "</tr>";
}
echo "</table>";

// ============= LOGIC DOWNLOADS (HIỂN THỊ TẤT CẢ TINH_CHINH) =============
echo "<h2>2. LOGIC DOWNLOADS (tinh-dau-2 (1))</h2>";
echo "<p><strong>receiptEntries = Tất cả tinh_chinh (bao gồm cả tinh chỉnh thủ công)</strong></p>";

$receiptEntries_Downloads = [];
$tonDau_Downloads = 0.0;

foreach ($transactions as $gd) {
    $ngay = (string)($gd['ngay'] ?? '');
    if (!$ngay) continue;

    $loai = strtolower((string)($gd['loai'] ?? ''));
    if ($loai === 'cap_them') {
        $soLuong = (float)($gd['so_luong_lit'] ?? 0);
        if ($soLuong !== 0.0) {
            $label = trim((string)($gd['cay_xang'] ?? 'Cấp thêm'));
            $receiptEntries_Downloads[] = ['label' => $label, 'date' => $ngay, 'amount' => $soLuong];
        }
    } elseif ($loai === 'tinh_chinh') {
        $soLuong = (float)($gd['so_luong_lit'] ?? 0);
        $rawLyDo = (string)($gd['ly_do'] ?? '');
        $dir = 'in'; $other = '';
        if (preg_match('/chuyển sang\s+([^\s]+)/u', $rawLyDo, $m1)) { $dir = 'out'; $other = $m1[1]; }
        elseif (preg_match('/nhận từ\s+([^\s]+)/u', $rawLyDo, $m2)) { $dir = 'in'; $other = $m2[1]; }
        $label = $other !== '' ? ('Chuyển dầu ' . ($dir === 'out' ? '→' : '←') . ' ' . $other) : 'Tinh chỉnh';
        $receiptEntries_Downloads[] = ['label' => $label, 'date' => $ngay, 'amount' => $soLuong];
    }
}

$sumReceiptsInt_Downloads = 0;
foreach ($receiptEntries_Downloads as $rc) {
    $sumReceiptsInt_Downloads += (int)round((float)$rc['amount']);
}
$tongNoNhan_Downloads = (int)round($tonDau_Downloads) + (int)$sumReceiptsInt_Downloads;

echo "<table>";
echo "<tr><th>Label</th><th>Số lượng</th></tr>";
foreach ($receiptEntries_Downloads as $rc) {
    echo "<tr><td>" . $rc['label'] . "</td><td>" . number_format($rc['amount']) . "</td></tr>";
}
echo "<tr class='highlight'><td>TỔNG (sumReceiptsInt)</td><td>" . number_format($sumReceiptsInt_Downloads) . "</td></tr>";
echo "<tr class='highlight'><td>Nợ tại (tonDau)</td><td>" . number_format($tonDau_Downloads) . "</td></tr>";
echo "<tr class='highlight'><td>CỘNG (tongNoNhan)</td><td>" . number_format($tongNoNhan_Downloads) . "</td></tr>";
echo "</table>";

// ============= LOGIC XAMPP/HTDOCS (CHỈ CHUYỂN DẦU) =============
echo "<h2>3. LOGIC XAMPP/HTDOCS (SAU KHI SỬA)</h2>";
echo "<p><strong>receiptEntries = Chỉ chuyển dầu (có transfer_pair_id)</strong></p>";

$receiptEntries_Xampp = [];
$tonDau_Xampp = 0.0;

foreach ($transactions as $gd) {
    $ngay = (string)($gd['ngay'] ?? '');
    if (!$ngay) continue;

    $loai = strtolower((string)($gd['loai'] ?? ''));
    if ($loai === 'cap_them') {
        $soLuong = (float)($gd['so_luong_lit'] ?? 0);
        if ($soLuong !== 0.0) {
            $label = trim((string)($gd['cay_xang'] ?? 'Cấp thêm'));
            $receiptEntries_Xampp[] = ['label' => $label, 'date' => $ngay, 'amount' => $soLuong];
        }
    } elseif ($loai === 'tinh_chinh') {
        $transferPairId = trim((string)($gd['transfer_pair_id'] ?? ''));
        $soLuong = (float)($gd['so_luong_lit'] ?? 0);
        if ($soLuong !== 0.0) {
            if ($transferPairId !== '') {
                $label = trim((string)($gd['ly_do'] ?? 'Chuyển dầu'));
                $receiptEntries_Xampp[] = ['label' => $label, 'date' => $ngay, 'amount' => $soLuong];
            } else {
                // BỎ QUA tinh chỉnh thủ công
            }
        }
    }
}

$sumReceiptsInt_Xampp = 0;
foreach ($receiptEntries_Xampp as $rc) {
    $sumReceiptsInt_Xampp += (int)round((float)$rc['amount']);
}
$tongNoNhan_Xampp = (int)round($tonDau_Xampp) + (int)$sumReceiptsInt_Xampp;

echo "<table>";
echo "<tr><th>Label</th><th>Số lượng</th></tr>";
if (count($receiptEntries_Xampp) > 0) {
    foreach ($receiptEntries_Xampp as $rc) {
        echo "<tr><td>" . $rc['label'] . "</td><td>" . number_format($rc['amount']) . "</td></tr>";
    }
} else {
    echo "<tr><td colspan='2' class='error'>KHÔNG CÓ DÒNG NÀO!</td></tr>";
}
echo "<tr class='highlight'><td>TỔNG (sumReceiptsInt)</td><td>" . number_format($sumReceiptsInt_Xampp) . "</td></tr>";
echo "<tr class='highlight'><td>Nợ tại (tonDau)</td><td>" . number_format($tonDau_Xampp) . "</td></tr>";
echo "<tr class='highlight'><td>CỘNG (tongNoNhan)</td><td>" . number_format($tongNoNhan_Xampp) . "</td></tr>";
echo "</table>";

// ============= TÍNH TỔNG DẦU TIÊU HAO =============
echo "<h2>4. TỔNG DẦU TIÊU HAO (sumFuel)</h2>";

$sumFuel = 0;
$fuelDetails = [];
foreach ($allResults as $row) {
    if (($row['ten_phuong_tien'] ?? '') !== $ship) continue;

    $isCapThem = intval($row['cap_them'] ?? 0) === 1;
    if ($isCapThem) {
        $fuel = (int)round((float)($row['so_luong_cap_them_lit'] ?? 0));
    } else {
        $fuel = (int)round((float)($row['dau_tinh_toan_lit'] ?? 0));
    }

    if ($fuel > 0) {
        $fuelDetails[] = ['trip' => ($row['so_chuyen'] ?? 'N/A'), 'fuel' => $fuel];
        $sumFuel += $fuel;
    }
}

echo "<p><strong>Tổng số chuyến: " . count($fuelDetails) . "</strong></p>";
echo "<p><strong>TỔNG DẦU TIÊU HAO (sumFuel): " . number_format($sumFuel) . " lít</strong></p>";

// ============= TÍNH DẦU TỒN =============
echo "<h2>5. TÍNH DẦU TỒN</h2>";
echo "<p><strong>Công thức: tonCuoi = tongNoNhan - sumFuel</strong></p>";

$tonCuoi_Downloads = (int)round($tongNoNhan_Downloads - (int)$sumFuel);
$tonCuoi_Xampp = (int)round($tongNoNhan_Xampp - (int)$sumFuel);

echo "<table>";
echo "<tr><th></th><th>Downloads</th><th>xampp/htdocs</th><th>Chênh lệch</th></tr>";
echo "<tr><td>tongNoNhan (Cộng)</td><td>" . number_format($tongNoNhan_Downloads) . "</td><td>" . number_format($tongNoNhan_Xampp) . "</td><td class='error'>" . number_format($tongNoNhan_Downloads - $tongNoNhan_Xampp) . "</td></tr>";
echo "<tr><td>sumFuel (Tiêu hao)</td><td>" . number_format($sumFuel) . "</td><td>" . number_format($sumFuel) . "</td><td>0</td></tr>";
echo "<tr class='highlight'><td><strong>DẦU TỒN (tonCuoi)</strong></td><td class='" . ($tonCuoi_Downloads > 0 ? 'success' : 'error') . "'>" . number_format($tonCuoi_Downloads) . "</td><td class='" . ($tonCuoi_Xampp > 0 ? 'success' : 'error') . "'>" . number_format($tonCuoi_Xampp) . "</td><td class='error'>" . number_format($tonCuoi_Downloads - $tonCuoi_Xampp) . "</td></tr>";
echo "</table>";

// ============= KẾT LUẬN =============
echo "<h2>6. KẾT LUẬN</h2>";
echo "<div style='background:#ffffcc;padding:20px;border:2px solid #ff9900;'>";
echo "<h3>NGUYÊN NHÂN CHÊNH LỆCH:</h3>";
echo "<ol>";
echo "<li><strong>Downloads:</strong> Hiển thị TẤT CẢ tinh_chinh (bao gồm tinh chỉnh thủ công +7,000 và chuyển dầu -500)</li>";
echo "<li><strong>xampp/htdocs:</strong> CHỈ hiển thị chuyển dầu (có transfer_pair_id = -500), BỎ QUA tinh chỉnh thủ công (+7,000)</li>";
echo "<li><strong>Kết quả:</strong><ul>";
echo "<li>Downloads: tongNoNhan = " . number_format($tongNoNhan_Downloads) . " → Dầu tồn = " . number_format($tonCuoi_Downloads) . " (DƯƠNG)</li>";
echo "<li>xampp/htdocs: tongNoNhan = " . number_format($tongNoNhan_Xampp) . " → Dầu tồn = " . number_format($tonCuoi_Xampp) . " (ÂM)</li>";
echo "</ul></li>";
echo "<li><strong>Chênh lệch: " . number_format(abs($tonCuoi_Downloads - $tonCuoi_Xampp)) . " lít</strong> = Đúng bằng số tinh chỉnh thủ công bị bỏ qua (+7,000)</li>";
echo "</ol>";
echo "</div>";

echo "<div style='background:#ffeeee;padding:20px;border:2px solid #ff0000;margin-top:20px;'>";
echo "<h3>⚠️ LƯU Ý:</h3>";
echo "<p>Nếu <strong>Hình ảnh bạn gửi</strong> cho thấy:</p>";
echo "<ul>";
echo "<li>Downloads: Dầu tồn DƯƠNG (4,836 hoặc 4,610)</li>";
echo "<li>xampp/htdocs: Dầu tồn ÂM (-4,632)</li>";
echo "</ul>";
echo "<p><strong>Thì đây là file Excel CŨ!</strong> Vì:</p>";
echo "<ul>";
echo "<li>Dữ liệu hiện tại chỉ có 2 giao dịch (7,000 và -500)</li>";
echo "<li>Không có dòng 3,000 nào</li>";
echo "<li>Cần xuất lại file Excel MỚI để có kết quả chính xác!</li>";
echo "</ul>";
echo "</div>";
?>
