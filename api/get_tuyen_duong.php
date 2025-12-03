<?php
// API endpoint to get all routes
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../models/KhoangCach.php';

try {
    $model = new KhoangCach();
    $routes = $model->getAllData();

    // Convert to array format with proper keys
    $routesList = [];
    foreach ($routes as $route) {
        $routesList[] = [
            'id' => $route['id'],
            'diem_dau' => $route['diem_dau'],
            'diem_cuoi' => $route['diem_cuoi'],
            'khoang_cach_km' => $route['khoang_cach_km']
        ];
    }

    echo json_encode([
        'success' => true,
        'routes' => $routesList
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
