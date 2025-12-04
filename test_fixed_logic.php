<?php
require_once __DIR__ . '/models/DauTon.php';

$dauTonModel = new DauTon();
$ship = 'HTL-1';

echo "<h2>Testing xampp/htdocs (FIXED) - Ship: $ship</h2>";

$transactions = $dauTonModel->getLichSuGiaoDich($ship);
echo "<p>Total transactions: " . count($transactions) . "</p>";

echo "<h3>All transactions:</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Loại</th><th>Ngày</th><th>Số lượng</th><th>Lý do</th><th>Transfer ID</th><th>Kết quả</th></tr>";

$receiptEntries = [];

foreach ($transactions as $gd) {
    $ngay = (string)($gd['ngay'] ?? '');
    $loai = strtolower((string)($gd['loai'] ?? ''));
    $soLuong = (float)($gd['so_luong_lit'] ?? 0);
    $lyDo = (string)($gd['ly_do'] ?? '');
    $transferPairId = trim((string)($gd['transfer_pair_id'] ?? ''));

    $result = '';
    $addToReceipt = false;

    if ($loai === 'cap_them') {
        if ($soLuong !== 0.0) {
            $label = trim((string)($gd['cay_xang'] ?? 'Cấp thêm'));
            $receiptEntries[] = ['label' => $label, 'date' => $ngay, 'amount' => $soLuong];
            $result = '✅ HIỂN THỊ (Cấp thêm)';
            $addToReceipt = true;
        }
    } elseif ($loai === 'tinh_chinh') {
        if ($soLuong !== 0.0) {
            if ($transferPairId !== '') {
                $label = trim($lyDo !== '' ? $lyDo : 'Chuyển dầu');
                $receiptEntries[] = ['label' => $label, 'date' => $ngay, 'amount' => $soLuong];
                $result = '✅ HIỂN THỊ (Chuyển dầu)';
                $addToReceipt = true;
            } else {
                $result = '❌ BỎ QUA (Tinh chỉnh thủ công)';
            }
        }
    }

    echo "<tr>";
    echo "<td>" . $loai . "</td>";
    echo "<td>" . $ngay . "</td>";
    echo "<td>" . number_format($soLuong) . "</td>";
    echo "<td>" . $lyDo . "</td>";
    echo "<td>" . ($transferPairId ? 'Có' : 'Không') . "</td>";
    echo "<td style='font-weight:bold;color:" . ($addToReceipt ? 'green' : 'red') . ";'>" . $result . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>receiptEntries (sẽ hiển thị trong Excel):</h3>";
echo "<p style='font-weight:bold;'>Tổng: " . count($receiptEntries) . " dòng</p>";

if (count($receiptEntries) > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>STT</th><th>Label</th><th>Ngày</th><th>Số lượng</th></tr>";
    $sum = 0;
    foreach ($receiptEntries as $idx => $rc) {
        echo "<tr>";
        echo "<td>" . ($idx + 1) . "</td>";
        echo "<td>" . ($rc['label'] ?? '') . "</td>";
        echo "<td>" . ($rc['date'] ?? '') . "</td>";
        echo "<td>" . number_format($rc['amount'] ?? 0) . "</td>";
        echo "</tr>";
        $sum += (int)round($rc['amount']);
    }
    echo "</table>";
    echo "<p style='font-size:18px;font-weight:bold;color:green;'>Tổng số lượng nhận dầu: " . number_format($sum) . " lít</p>";
} else {
    echo "<p style='color:red;'>Không có dữ liệu nào được hiển thị!</p>";
}

echo "<h3 style='color:green;'>✅ KẾT QUẢ MONG ĐỢI:</h3>";
echo "<ul>";
echo "<li>✅ Chỉ hiển thị 1 dòng: Chuyển dầu (-500)</li>";
echo "<li>❌ Không hiển thị: Tinh chỉnh thủ công (+7,000)</li>";
echo "<li>Tổng: -500 lít</li>";
echo "</ul>";
?>
