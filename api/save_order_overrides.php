<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../models/HeSoTau.php';

// Only allow POST
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$month = isset($_POST['month']) ? trim((string)$_POST['month']) : '';
$ship  = isset($_POST['ship']) ? trim((string)$_POST['ship']) : '';
$order = isset($_POST['order']) ? $_POST['order'] : [];

try {
    if ($month === '' || !preg_match('/^\d{4}-\d{2}$/', $month)) { throw new Exception('Tháng không hợp lệ'); }
    if ($ship === '') { throw new Exception('Thiếu tàu'); }
    // Validate ship exists
    $hs = new HeSoTau();
    if (!$hs->isTauExists($ship)) { throw new Exception('Tàu không tồn tại'); }
    if (!is_array($order)) { throw new Exception('Order không hợp lệ'); }
    $order = array_values(array_filter(array_map(function($v){ return (int)$v; }, $order), function($v){ return $v > 0; }));

    $file = __DIR__ . '/../data/order_overrides.json';
    if (!file_exists($file)) { file_put_contents($file, '{}'); }

    $fh = fopen($file, 'c+');
    if (!$fh) { throw new Exception('Không thể mở file order'); }
    if (!flock($fh, LOCK_EX)) { fclose($fh); throw new Exception('Không thể khóa file order'); }

    $stat = fstat($fh);
    $len = $stat['size'] ?? 0;
    rewind($fh);
    $raw = $len > 0 ? fread($fh, $len) : '';
    $json = $raw !== '' ? json_decode($raw, true) : [];
    if (!is_array($json)) { $json = []; }

    if (!isset($json[$month])) { $json[$month] = []; }
    $json[$month][$ship] = $order;

    // Ghi lại
    rewind($fh);
    ftruncate($fh, 0);
    fwrite($fh, json_encode($json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>


