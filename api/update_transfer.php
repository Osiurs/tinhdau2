<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../models/HeSoTau.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $in = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;

    $pairId = trim((string)($in['transfer_pair_id'] ?? ''));
    
    // New values
    $newSrc = trim((string)($in['new_source_ship'] ?? ''));
    $newDst = trim((string)($in['new_dest_ship'] ?? ''));
    $newDateRaw = trim((string)($in['new_date'] ?? ''));
    $newLiters = isset($in['new_liters']) ? (float)$in['new_liters'] : 0;
    $newReason = trim((string)($in['reason'] ?? 'Chuyển dầu'));

    // If using pair_id, we can get old values from the found rows
    $srcRow = null;
    $dstRow = null;
    
    if ($pairId !== '') {
        $found = td2_find_transfer_rows_by_pair_id($pairId);
        $idxSrc = $found['indexes']['src'] ?? null;
        $idxDst = $found['indexes']['dst'] ?? null;
        if ($idxSrc === null || $idxDst === null) {
            throw new Exception('Không tìm thấy lệnh chuyển dầu để sửa');
        }
        
        // Get old values from existing rows
        $rows = $found['rows'] ?? [];
        $headers = $found['headers'];
        $srcRow = array_combine($headers, $rows[$idxSrc] ?? []);
        $dstRow = array_combine($headers, $rows[$idxDst] ?? []);
        
        $oldSrc = trim((string)($srcRow['ten_phuong_tien'] ?? ''));
        $oldDst = trim((string)($dstRow['ten_phuong_tien'] ?? ''));
        $oldDateIso = trim((string)($srcRow['ngay'] ?? ''));
        $oldLiters = abs((float)($srcRow['so_luong_lit'] ?? 0));
        
        // Fallback to old values if new values not provided
        if ($newSrc === '') $newSrc = $oldSrc;
        if ($newDst === '') $newDst = $oldDst;
        if ($newDateRaw === '') $newDateRaw = format_date_vn($oldDateIso);
        if ($newLiters <= 0) $newLiters = $oldLiters;
    } else {
        // Legacy mode: use old_* parameters
        $oldSrc = trim((string)($in['old_source_ship'] ?? ''));
        $oldDst = trim((string)($in['old_dest_ship'] ?? ''));
        $oldDateRaw = trim((string)($in['old_date'] ?? ''));
        $oldLiters = (float)($in['old_liters'] ?? 0);

        if ($oldSrc === '' || $oldDst === '' || $oldDateRaw === '' || $oldLiters <= 0) {
            throw new Exception('Thiếu tham số old_* hoặc transfer_pair_id');
        }
        $oldDateIso = parse_date_vn($oldDateRaw);
        if (!$oldDateIso) { throw new Exception('Ngày cũ không hợp lệ'); }

        // Fallback to old values if new values not provided
        if ($newSrc === '') $newSrc = $oldSrc;
        if ($newDst === '') $newDst = $oldDst;
        if ($newDateRaw === '') $newDateRaw = $oldDateRaw;
        if ($newLiters <= 0) $newLiters = $oldLiters;
        
        $found = td2_find_transfer_rows($oldDateIso, $oldSrc, $oldDst, $oldLiters);
        $idxSrc = $found['indexes']['src'] ?? null;
        $idxDst = $found['indexes']['dst'] ?? null;
        if ($idxSrc === null || $idxDst === null) {
            throw new Exception('Không tìm thấy lệnh chuyển dầu để sửa');
        }
        $headers = $found['headers'];
        
        // Preserve existing pair_id if any
        $rows = $found['rows'] ?? [];
        $srcRow = array_combine($headers, $rows[$idxSrc] ?? []);
        $dstRow = array_combine($headers, $rows[$idxDst] ?? []);
        $pairId = trim((string)($srcRow['transfer_pair_id'] ?? ''));
    }

    // Validate new values
    if ($newSrc === '' || $newDst === '') {
        throw new Exception('Tàu nguồn và tàu đích không được để trống');
    }
    $newDateIso = parse_date_vn($newDateRaw);
    if (!$newDateIso) { throw new Exception('Ngày mới không hợp lệ'); }
    if ($newSrc === $newDst) { throw new Exception('Tàu nguồn và tàu đích không được trùng'); }
    if ($newLiters <= 0) { throw new Exception('Số lít mới phải > 0'); }

    $hs = new HeSoTau();
    if (!$hs->isTauExists($newSrc)) { throw new Exception('Tàu nguồn mới không tồn tại'); }
    if (!$hs->isTauExists($newDst)) { throw new Exception('Tàu đích mới không tồn tại'); }

    // Generate new pair_id if not exists
    if ($pairId === '') {
        $pairId = function_exists('td2_generate_uuid_v4') ? td2_generate_uuid_v4() : uniqid('pair_', true);
    }

    $file = __DIR__ . '/../data/dau_ton.csv';

    // Preserve created_at from existing rows
    $srcCreatedAt = ($srcRow && isset($srcRow['created_at'])) ? $srcRow['created_at'] : date('Y-m-d H:i:s');
    $dstCreatedAt = ($dstRow && isset($dstRow['created_at'])) ? $dstRow['created_at'] : date('Y-m-d H:i:s');
    
    // Build new rows (preserve transfer_pair_id and created_at)
    $srcAssoc = [
        'ten_phuong_tien' => $newSrc,
        'loai' => 'tinh_chinh',
        'ngay' => $newDateIso,
        'so_luong_lit' => -$newLiters,
        'cay_xang' => '',
        'ly_do' => ($newReason !== '' ? $newReason : 'Chuyển dầu') . ' → chuyển sang ' . $newDst,
        'transfer_pair_id' => $pairId,
        'created_at' => $srcCreatedAt
    ];
    $dstAssoc = [
        'ten_phuong_tien' => $newDst,
        'loai' => 'tinh_chinh',
        'ngay' => $newDateIso,
        'so_luong_lit' => $newLiters,
        'cay_xang' => '',
        'ly_do' => ($newReason !== '' ? $newReason : 'Chuyển dầu') . ' ← nhận từ ' . $newSrc,
        'transfer_pair_id' => $pairId,
        'created_at' => $dstCreatedAt
    ];

    $ok = td2_update_csv_rows($file, [ $idxSrc => $srcAssoc, $idxDst => $dstAssoc ], $headers);
    if (!$ok) { throw new Exception('Không thể cập nhật bản ghi'); }

    // Migrate override key if composite changed (only for legacy mode without pair_id)
    if ($pairId === '' || !isset($oldDateIso)) {
        $oldKey = td2_make_transfer_key($oldDateIso ?? '', $oldSrc ?? '', $oldDst ?? '', $oldLiters ?? 0);
        $newKey = td2_make_transfer_key($newDateIso, $newSrc, $newDst, $newLiters);
        if ($oldKey !== $newKey) {
            $ov = td2_read_transfer_overrides();
            if (isset($ov[$oldKey])) {
                $ov[$newKey] = $ov[$oldKey];
                unset($ov[$oldKey]);
                td2_write_transfer_overrides($ov);
            }
        }
    }

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    log_error('update_transfer', $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>


