<?php
// One-time maintenance: physically remove ships not in company list from coefficients CSV
// Usage: open this file in browser once: admin/cleanup_he_so_tau.php

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/TauPhanLoai.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $coeffFile = HE_SO_TAU_FILE; // bang_he_so_tau_cu_ly_full_v2.csv
    if (!file_exists($coeffFile)) {
        throw new Exception('Không tìm thấy file hệ số: ' . $coeffFile);
    }

    // Build maps from tau_phan_loai.csv
    $tp = new TauPhanLoai();
    $phanLoaiMap = $tp->getAll(); // ten_tau => cong_ty|thue_ngoai
    $allow = [];
    foreach ($phanLoaiMap as $ten => $pl) {
        if ($pl === 'cong_ty') { $allow[$ten] = true; }
    }
    if (empty($allow)) {
        throw new Exception('Danh sách tàu công ty rỗng. Kiểm tra data/tau_phan_loai.csv');
    }

    // Read original coefficients
    $rh = fopen($coeffFile, 'r');
    if (!$rh) { throw new Exception('Không thể mở file hệ số để đọc'); }
    $headers = fgetcsv($rh) ?: [];
    $rows = [];
    while (($row = fgetcsv($rh)) !== false) { $rows[] = $row; }
    fclose($rh);

    // Filter rows by policy: keep all rented ships; for company ships, only keep if allowed
    $kept = [];
    foreach ($rows as $r) {
        if (count($r) < 5) { continue; }
        $tenTau = trim((string)$r[0]);
        $pl = $phanLoaiMap[$tenTau] ?? null;
        if ($pl === 'cong_ty') {
            if (isset($allow[$tenTau])) { $kept[] = $r; }
        } else {
            // thuê ngoài hoặc chưa gắn tag => giữ lại
            $kept[] = $r;
        }
    }

    // Backup original
    $backupDir = __DIR__ . '/../data';
    if (!is_dir($backupDir)) { @mkdir($backupDir, 0777, true); }
    $backupFile = $backupDir . '/he_so_tau_backup_' . date('Ymd_His') . '.csv';
    if (!@copy($coeffFile, $backupFile)) {
        throw new Exception('Không thể tạo file backup: ' . $backupFile);
    }

    // Rewrite atomically
    $tmp = $coeffFile . '.tmp';
    $wh = fopen($tmp, 'w');
    if (!$wh) { throw new Exception('Không thể mở file tạm để ghi'); }
    fputcsv($wh, !empty($headers) ? $headers : ['ten_tau','km_min','km_max','k_ko_hang','k_co_hang']);
    foreach ($kept as $r) { fputcsv($wh, $r); }
    fclose($wh);
    if (!@rename($tmp, $coeffFile)) {
        throw new Exception('Không thể thay thế file hệ số bằng dữ liệu đã lọc');
    }

    echo "ĐÃ DỌN DẸP XONG\n";
    echo 'Backup: ' . basename($backupFile) . "\n";
    echo 'Tổng dòng trước: ' . count($rows) . "\n";
    echo 'Tổng dòng sau: ' . count($kept) . "\n";
    echo 'Số tàu công ty được giữ: ' . count($allow) . "\n";
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo 'LỖI: ' . $e->getMessage();
    exit;
}


