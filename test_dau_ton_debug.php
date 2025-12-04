<?php
require_once __DIR__ . '/models/DauTon.php';

$dauTonModel = new DauTon();

// Test với HTL-1
$ship = 'HTL-1';
echo "<h2>Testing getLichSuGiaoDich for ship: $ship</h2>";

$transactions = $dauTonModel->getLichSuGiaoDich($ship);
echo "<p>Total transactions found: " . count($transactions) . "</p>";

if (count($transactions) > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Tàu</th><th>Loại</th><th>Ngày</th><th>Số lượng</th><th>Lý do</th><th>Transfer ID</th></tr>";
    foreach ($transactions as $gd) {
        echo "<tr>";
        echo "<td>" . ($gd['id'] ?? '') . "</td>";
        echo "<td>" . ($gd['ten_phuong_tien'] ?? '') . "</td>";
        echo "<td>" . ($gd['loai'] ?? '') . "</td>";
        echo "<td>" . ($gd['ngay'] ?? '') . "</td>";
        echo "<td>" . ($gd['so_luong_lit'] ?? '') . "</td>";
        echo "<td>" . ($gd['ly_do'] ?? '') . "</td>";
        echo "<td>" . ($gd['transfer_pair_id'] ?? '') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red;'>No transactions found!</p>";
}

// Giả lập logic thu thập receiptEntries từ excel_export_full.php
echo "<h2>Simulating receiptEntries collection logic</h2>";
$receiptEntries = [];
foreach ($transactions as $gd) {
    $ngay = (string)($gd['ngay'] ?? '');
    if (!$ngay) continue;

    $loai = strtolower((string)($gd['loai'] ?? ''));
    if ($loai === 'cap_them') {
        $soLuong = (float)($gd['so_luong_lit'] ?? 0);
        if ($soLuong !== 0.0) {
            $label = trim((string)($gd['cay_xang'] ?? 'Cấp thêm'));
            $receiptEntries[] = ['label' => $label, 'date' => $ngay, 'amount' => $soLuong];
        }
    } elseif ($loai === 'tinh_chinh') {
        $transferPairId = trim((string)($gd['transfer_pair_id'] ?? ''));
        $soLuong = (float)($gd['so_luong_lit'] ?? 0);
        if ($soLuong !== 0.0) {
            if ($transferPairId !== '') {
                $label = trim((string)($gd['ly_do'] ?? 'Chuyển dầu'));
                $receiptEntries[] = ['label' => $label, 'date' => $ngay, 'amount' => $soLuong];
            } else {
                $label = 'Tính chính';
                $lyDo = trim((string)($gd['ly_do'] ?? ''));
                if ($lyDo !== '') {
                    $label .= ' (' . $lyDo . ')';
                }
                $receiptEntries[] = ['label' => $label, 'date' => $ngay, 'amount' => $soLuong];
            }
        }
    }
}

echo "<p>Total receiptEntries: " . count($receiptEntries) . "</p>";
if (count($receiptEntries) > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Label</th><th>Date</th><th>Amount</th></tr>";
    foreach ($receiptEntries as $rc) {
        echo "<tr>";
        echo "<td>" . ($rc['label'] ?? '') . "</td>";
        echo "<td>" . ($rc['date'] ?? '') . "</td>";
        echo "<td>" . ($rc['amount'] ?? '') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red;'>No receiptEntries collected!</p>";
}
?>
