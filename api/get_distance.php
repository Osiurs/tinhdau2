<?php
/**
 * API endpoint to get distance between two points
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/KhoangCach.php';

// Get parameters
$diemDau = trim($_GET['diem_dau'] ?? '');
$diemCuoi = trim($_GET['diem_cuoi'] ?? '');

// Validate input
if (empty($diemDau) || empty($diemCuoi)) {
    echo json_encode([
        'success' => false,
        'error' => 'Missing parameters',
        'distance' => null
    ]);
    exit;
}

try {
    $khoangCach = new KhoangCach();

    // Get distance between two points
    $distance = $khoangCach->getKhoangCach($diemDau, $diemCuoi);

    if ($distance === null) {
        // No route found between these points
        echo json_encode([
            'success' => false,
            'error' => 'No route found',
            'distance' => null
        ]);
    } else {
        // Route found
        echo json_encode([
            'success' => true,
            'distance' => $distance,
            'error' => null
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'distance' => null
    ]);
}
