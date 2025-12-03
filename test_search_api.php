<?php
// Test 1: Format mới (q parameter)
echo "=== Test 1: Format mới (q=Cảng) ===\n";
$_GET['q'] = 'Cảng';
$_GET['keyword'] = null;
$_GET['diem_dau'] = '';
ob_start();
require 'api/search_diem.php';
$output1 = ob_get_clean();
echo $output1 . "\n\n";

// Reset
unset($_GET);

// Test 2: Format cũ (keyword parameter)
echo "=== Test 2: Format cũ (keyword=Cảng) ===\n";
$_GET['keyword'] = 'Cảng';
$_GET['q'] = null;
$_GET['diem_dau'] = '';
ob_start();
require 'api/search_diem.php';
$output2 = ob_get_clean();
echo $output2 . "\n\n";

// Reset
unset($_GET);

// Test 3: Không có query (lấy tất cả)
echo "=== Test 3: Không có query ===\n";
$_GET['keyword'] = '';
$_GET['q'] = '';
$_GET['diem_dau'] = '';
ob_start();
require 'api/search_diem.php';
$output3 = ob_get_clean();
echo substr($output3, 0, 500) . "...\n";
