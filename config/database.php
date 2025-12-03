<?php
/**
 * File cấu hình cơ sở dữ liệu và các hằng số
 * Chứa đường dẫn file CSV và các thông tin cấu hình khác
 */

// Đường dẫn đến các file CSV
define('HE_SO_TAU_FILE', __DIR__ . '/../bang_he_so_tau_cu_ly_full_v2.csv');
define('KHOA_CACH_FILE', __DIR__ . '/../khoang_duong.csv');
// File lưu kết quả tính toán
define('KET_QUA_DIR', __DIR__ . '/../data');
define('KET_QUA_FILE', KET_QUA_DIR . '/ket_qua_tinh_toan.csv');
// File phân loại tàu: cong_ty | thue_ngoai
if (!defined('TAU_PHAN_LOAI_FILE')) {
    define('TAU_PHAN_LOAI_FILE', __DIR__ . '/../data/tau_phan_loai.csv');
}

// Thông tin hệ thống
define('SITE_NAME', 'Hệ Thống Tính Toán Nhiên Liệu Tàu');
define('VERSION', '1.3.8');

// Cấu hình phân trang
define('ITEMS_PER_PAGE', 10);

// Thông báo lỗi
define('ERROR_MESSAGES', [
    'tau_not_found' => 'Không tìm thấy thông tin tàu',
    'diem_not_found' => 'Không tìm thấy thông tin điểm',
    'invalid_distance' => 'Khoảng cách không hợp lệ',
    'invalid_weight' => 'Khối lượng không hợp lệ',
    'file_not_found' => 'Không tìm thấy file dữ liệu'
]);

// Thông báo thành công
define('SUCCESS_MESSAGES', [
    'calculation_success' => 'Tính toán thành công',
    'data_saved' => 'Dữ liệu đã được lưu',
    'data_updated' => 'Dữ liệu đã được cập nhật',
    'data_deleted' => 'Dữ liệu đã được xóa'
]);

// Cấu hình phân loại cự ly (km)
// Ngắn: <= CU_LY_NGAN_MAX_KM, Trung bình: (CU_LY_NGAN_MAX_KM, CU_LY_TRUNG_BINH_MAX_KM], Dài: > CU_LY_TRUNG_BINH_MAX_KM
if (!defined('CU_LY_NGAN_MAX_KM')) {
    define('CU_LY_NGAN_MAX_KM', 80);
}
if (!defined('CU_LY_TRUNG_BINH_MAX_KM')) {
    define('CU_LY_TRUNG_BINH_MAX_KM', 200);
}

// Các hàm phan_loai_cu_ly và label_cu_ly đã được chuyển vào includes/helpers.php

// Các hàm format_date_vn và parse_date_vn đã được chuyển vào includes/helpers.php
?>


