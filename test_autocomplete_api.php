<?php
// Test autocomplete API with both old and new formats
echo "=== Testing Autocomplete API ===\n\n";

// Test 1: New format with q parameter
echo "Test 1: New format (q=Cảng)\n";
$_GET['q'] = 'Cảng';
$_GET['keyword'] = null;
$_GET['diem_dau'] = '';
ob_start();
require 'api/search_diem.php';
$output1 = ob_get_clean();
echo $output1 . "\n\n";

// Reset
unset($_GET);
$_SERVER['REQUEST_METHOD'] = 'GET';

// Test 2: Old format with keyword parameter
echo "Test 2: Old format (keyword=Cảng)\n";
$_GET['keyword'] = 'Cảng';
$_GET['q'] = null;
$_GET['diem_dau'] = '';
ob_start();
require_once 'api/search_diem.php';
$output2 = ob_get_clean();
echo $output2 . "\n\n";

// Reset
unset($_GET);

// Test 3: Empty query (get all)
echo "Test 3: Empty query (keyword=)\n";
$_GET['keyword'] = '';
$_GET['q'] = '';
$_GET['diem_dau'] = '';
ob_start();
require 'api/search_diem.php';
$output3 = ob_get_clean();
$decoded = json_decode($output3, true);
if ($decoded && isset($decoded['data'])) {
    echo "Found " . count($decoded['data']) . " locations\n";
    echo "First 5 results:\n";
    foreach (array_slice($decoded['data'], 0, 5) as $item) {
        echo "  - {$item['diem']} (distance: {$item['khoang_cach']})\n";
    }
}
?>
