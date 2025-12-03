<?php
// Bắt đầu output buffering để tránh output trước JSON
ob_start();

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../models/HeSoTau.php';

// Xóa mọi output trước đó
ob_clean();

header('Content-Type: application/json; charset=utf-8');

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $input = $method === 'POST' ? $_POST : $_GET;
    $src = trim((string)($input['source_ship'] ?? ''));
    $dst = trim((string)($input['dest_ship'] ?? ''));
    $dateRaw = trim((string)($input['date'] ?? ''));
    $pairId = trim((string)($input['transfer_pair_id'] ?? ''));
    
    // Parse liters more carefully
    $litersRaw = $input['liters'] ?? 0;
    if (is_string($litersRaw)) {
        $litersRaw = str_replace(',', '', $litersRaw); // Remove commas
    }
    $liters = (float)$litersRaw;

    // Debug logging
    // log_error('delete_transfer_debug', "Received data: src='{$src}', dst='{$dst}', date='{$dateRaw}', liters='{$liters}'");
    // log_error('delete_transfer_debug', "Raw liters: '{$litersRaw}', Parsed liters: '{$liters}'");
    // log_error('delete_transfer_debug', "Input array: " . json_encode($input));
    // log_error('delete_transfer_debug', "Request method: {$method}");

    if ($pairId === '' && ($src === '' || $dst === '' || $dateRaw === '' || $liters <= 0)) {
        $errorMsg = "Thiếu dữ liệu bắt buộc hoặc số lít không hợp lệ. Src: '{$src}', Dst: '{$dst}', Date: '{$dateRaw}', Liters: '{$liters}'";
        // log_error('delete_transfer_debug', $errorMsg);
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $errorMsg]);
        exit;
    }

    if ($pairId === '') {
        if ($src === $dst) { throw new Exception('Tàu nguồn và tàu đích không được trùng'); }
        $dateIso = parse_date_vn($dateRaw);
        if (!$dateIso) { throw new Exception('Ngày không hợp lệ'); }
        // Validate ships
        $hs = new HeSoTau();
        if (!$hs->isTauExists($src)) { throw new Exception('Tàu nguồn không tồn tại'); }
        if (!$hs->isTauExists($dst)) { throw new Exception('Tàu đích không tồn tại'); }
    } else {
        // When using pairId we don't require ship/date validation
        $dateIso = '';
    }

    // Locate rows
    $found = $pairId !== '' ? td2_find_transfer_rows_by_pair_id($pairId) : td2_find_transfer_rows($dateIso, $src, $dst, $liters);
    $idxSrc = $found['indexes']['src'] ?? null;
    $idxDst = $found['indexes']['dst'] ?? null;
    if ($idxSrc === null || $idxDst === null) {
        throw new Exception('Không tìm thấy lệnh chuyển dầu tương ứng. Vui lòng kiểm tra lại thông tin tàu, ngày và số lít.');
    }

    // Additional validation: check if the transfer data matches exactly
    $rows = $found['rows'] ?? [];
    if (isset($rows[$idxSrc]) && isset($rows[$idxDst])) {
        $srcRow = array_combine($found['headers'] ?? [], $rows[$idxSrc] ?? []);
        $dstRow = array_combine($found['headers'] ?? [], $rows[$idxDst] ?? []);
        
        // Verify source row has negative amount
        $srcAmount = (float)($srcRow['so_luong_lit'] ?? 0);
        if ($srcAmount >= 0) {
            throw new Exception('Dữ liệu nguồn không hợp lệ: số lít phải âm');
        }
        
        // Verify destination row has positive amount
        $dstAmount = (float)($dstRow['so_luong_lit'] ?? 0);
        if ($dstAmount <= 0) {
            throw new Exception('Dữ liệu đích không hợp lệ: số lít phải dương');
        }
        
        // Verify amounts match
        if (abs($srcAmount) !== $dstAmount) {
            throw new Exception('Số lít giữa tàu nguồn và đích không khớp');
        }
    }

    // Log the deletion for audit trail
    log_error('delete_transfer', "Deleting transfer: {$src} -> {$dst}, Date: {$dateIso}, Liters: {$liters}");

    $file = __DIR__ . '/../data/dau_ton.csv';
    $ok = td2_delete_csv_rows($file, [$idxSrc, $idxDst]);
    if (!$ok) { 
        throw new Exception('Không thể xóa bản ghi từ file CSV');
    }

    // Remove overrides key (only possible for legacy calls without pairId)
    if ($pairId === '') {
        $key = td2_make_transfer_key($dateIso, $src, $dst, $liters);
        $ov = td2_read_transfer_overrides();
        if (isset($ov[$key])) { 
            unset($ov[$key]); 
            td2_write_transfer_overrides($ov);
            log_error('delete_transfer', "Removed override key: {$key}");
        }
    }

    // Log successful deletion
    log_error('delete_transfer', "Successfully deleted transfer: {$src} -> {$dst}, Date: {$dateIso}, Liters: {$liters}");

    echo json_encode(['success' => true, 'message' => 'Đã xóa lệnh chuyển dầu thành công']);
    ob_end_flush();
} catch (Throwable $e) {
    ob_clean();
    log_error('delete_transfer', $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    ob_end_flush();
}
