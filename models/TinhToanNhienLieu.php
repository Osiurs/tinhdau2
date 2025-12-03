<?php
/**
 * Class TinhToanNhienLieu - Thực hiện tính toán nhiên liệu sử dụng cho tàu
 * Sử dụng công thức: Q = [(Sch+Skh)*Kkh] + (Sch*D*Kch)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/HeSoTau.php';
require_once __DIR__ . '/KhoangCach.php';

class TinhToanNhienLieu {
    private $heSoTau;
    private $khoangCach;
    
    /**
     * Constructor - Khởi tạo các đối tượng cần thiết
     */
    public function __construct() {
        $this->heSoTau = new HeSoTau();
        $this->khoangCach = new KhoangCach();
    }
    
    /**
     * Tính toán nhiên liệu sử dụng
     * @param string $tenTau Tên tàu
     * @param string $diemBatDau Điểm bắt đầu
     * @param string $diemKetThuc Điểm kết thúc
     * @param float $khoiLuong Khối lượng hàng hóa (tấn)
     * @param float|null $khoangCachThuCong Khoảng cách thủ công (km) - chỉ dùng khi không có tuyến trực tiếp
     * @return array Kết quả tính toán
     */
    public function tinhNhienLieu($tenTau, $diemBatDau, $diemKetThuc, $khoiLuong, $khoangCachThuCong = null) {
        // Reload dữ liệu để đảm bảo có dữ liệu mới nhất
        $this->khoangCach->reloadData();

        // Kiểm tra dữ liệu đầu vào
        $this->validateInput($tenTau, $diemBatDau, $diemKetThuc, $khoiLuong);

        // Lấy khoảng cách giữa hai điểm từ dữ liệu
        $khoangCach = $this->khoangCach->getKhoangCach($diemBatDau, $diemKetThuc);

        // Nếu không có tuyến trực tiếp, sử dụng khoảng cách thủ công (nếu có)
        if ($khoangCach === null) {
            if ($khoangCachThuCong !== null && $khoangCachThuCong > 0) {
                // Sử dụng khoảng cách thủ công
                $khoangCach = floatval($khoangCachThuCong);
            } else {
                // Tìm các tuyến đường có sẵn từ điểm bắt đầu
                $tuyenTuDiemBatDau = $this->khoangCach->getTuyenDuongTuDiem($diemBatDau);
                $tuyenDenDiemKetThuc = $this->khoangCach->getTuyenDuongTuDiem($diemKetThuc);

                $message = "Không tìm thấy tuyến đường trực tiếp giữa '$diemBatDau' và '$diemKetThuc'.\n\n";

                if (count($tuyenTuDiemBatDau) > 0) {
                    $message .= "Từ '$diemBatDau' có thể đi đến:\n";
                    foreach ($tuyenTuDiemBatDau as $tuyen) {
                        $message .= "- " . $tuyen['diem_cuoi'] . " (" . $tuyen['khoang_cach_km'] . " km)\n";
                    }
                }

                if (count($tuyenDenDiemKetThuc) > 0) {
                    $message .= "\nĐến '$diemKetThuc' có thể đi từ:\n";
                    foreach ($tuyenDenDiemKetThuc as $tuyen) {
                        $message .= "- " . $tuyen['diem_dau'] . " (" . $tuyen['khoang_cach_km'] . " km)\n";
                    }
                }

                $message .= "\nVui lòng chọn tuyến đường khác, liên hệ quản trị viên để thêm tuyến đường mới, hoặc nhập khoảng cách thủ công.";

                throw new Exception($message);
            }
        }

        // Lấy hệ số nhiên liệu cho tàu và khoảng cách
        $heSo = $this->heSoTau->getHeSo($tenTau, $khoangCach);
        if ($heSo === null) {
            throw new Exception("Không tìm thấy hệ số nhiên liệu cho tàu '$tenTau' với khoảng cách $khoangCach km");
        }

        // Tính toán nhiên liệu theo công thức
        $ketQua = $this->tinhTheoCongThuc($khoangCach, $khoiLuong, $heSo);

        // Thêm thông tin bổ sung
        $nhomCuLy = phan_loai_cu_ly($khoangCach);
        $ketQua['thong_tin'] = [
            'ten_tau' => $tenTau,
            'diem_bat_dau' => $diemBatDau,
            'diem_ket_thuc' => $diemKetThuc,
            'khoang_cach_km' => $khoangCach,
            'khoi_luong_tan' => $khoiLuong,
            'he_so_ko_hang' => $heSo['k_ko_hang'],
            'he_so_co_hang' => $heSo['k_co_hang'],
            'nhom_cu_ly' => $nhomCuLy,
            'nhom_cu_ly_label' => label_cu_ly($nhomCuLy)
        ];

        // Đánh dấu nếu sử dụng khoảng cách thủ công
        if ($khoangCachThuCong !== null && $khoangCachThuCong > 0) {
            $ketQua['thong_tin']['khoang_cach_thu_cong'] = true;
        }

        return $ketQua;
    }

    /**
     * Tính nhiên liệu trong trường hợp đổi lệnh với khoảng cách nhập tay
     * Route hiển thị: A -> B (đổi lệnh) -> C
     * @param string $tenTau Tên tàu
     * @param string $diemBatDau A - điểm xuất phát
     * @param string $diemDuKien B - điểm dự kiến ban đầu (đổi lệnh tại đây)
     * @param string $diemMoi C - điểm đến mới
     * @param float $khoiLuong Khối lượng (tấn)
     * @param float $khoangCachThucTe Tổng khoảng cách thực tế của chuyến đi (km) – do người dùng nhập
     * @return array Kết quả tính toán
     */
    public function tinhNhienLieuDoiLenh($tenTau, $diemBatDau, $diemDuKien, $diemMoi, $khoiLuong, $khoangCachThucTe) {
        // Reload dữ liệu để đảm bảo có dữ liệu mới nhất
        $this->khoangCach->reloadData();
        
        // Kiểm tra cơ bản
        if (empty($tenTau)) {
            throw new Exception('Tên tàu không được để trống');
        }
        if (!$this->heSoTau->isTauExists($tenTau)) {
            throw new Exception(ERROR_MESSAGES['tau_not_found'] . ": $tenTau");
        }
        if (empty($diemBatDau)) {
            throw new Exception('Điểm bắt đầu không được để trống');
        }
        if (empty($diemDuKien)) {
            throw new Exception('Điểm dự kiến (đổi lệnh) không được để trống');
        }
        if (empty($diemMoi)) {
            throw new Exception('Điểm đến mới không được để trống');
        }
        if (!$this->khoangCach->isDiemExists($diemBatDau)) {
            throw new Exception(ERROR_MESSAGES['diem_not_found'] . ": $diemBatDau");
        }
        if (!$this->khoangCach->isDiemExists($diemDuKien)) {
            throw new Exception(ERROR_MESSAGES['diem_not_found'] . ": $diemDuKien");
        }
        if (!$this->khoangCach->isDiemExists($diemMoi)) {
            throw new Exception(ERROR_MESSAGES['diem_not_found'] . ": $diemMoi");
        }
        if (!is_numeric($khoiLuong) || $khoiLuong < 0) {
            throw new Exception(ERROR_MESSAGES['invalid_weight']);
        }
        if (!is_numeric($khoangCachThucTe) || $khoangCachThucTe <= 0) {
            throw new Exception('Khoảng cách thực tế phải là số > 0');
        }

        // Lấy hệ số theo tổng khoảng cách thực tế
        $heSo = $this->heSoTau->getHeSo($tenTau, $khoangCachThucTe);
        if ($heSo === null) {
            throw new Exception("Không tìm thấy hệ số nhiên liệu cho tàu '$tenTau' với khoảng cách $khoangCachThucTe km");
        }

        $ketQua = $this->tinhTheoCongThuc($khoangCachThucTe, $khoiLuong, $heSo);
        $nhomCuLy = phan_loai_cu_ly($khoangCachThucTe);
        $ketQua['thong_tin'] = [
            'ten_tau' => $tenTau,
            'diem_bat_dau' => $diemBatDau,
            // Lưu điểm kết thúc là điểm mới (C)
            'diem_ket_thuc' => $diemMoi,
            'khoang_cach_km' => $khoangCachThucTe,
            'khoi_luong_tan' => $khoiLuong,
            'he_so_ko_hang' => $heSo['k_ko_hang'],
            'he_so_co_hang' => $heSo['k_co_hang'],
            // Dùng riêng cho UI hiển thị route đổi lệnh
            'route_hien_thi' => ($diemBatDau . ' → ' . $diemDuKien . ' (đổi lệnh) → ' . $diemMoi),
            'doi_lenh' => true,
            'diem_du_kien' => $diemDuKien,
            'nhom_cu_ly' => $nhomCuLy,
            'nhom_cu_ly_label' => label_cu_ly($nhomCuLy)
        ];

        return $ketQua;
    }

    /**
     * Tính toán nhiên liệu với khoảng cách được cung cấp trực tiếp
     * @param string $tenTau Tên tàu
     * @param string $diemBatDau Điểm bắt đầu
     * @param string $diemKetThuc Điểm kết thúc
     * @param float $khoiLuong Khối lượng hàng hóa (tấn)
     * @param float $khoangCach Khoảng cách được cung cấp (km)
     * @return array Kết quả tính toán
     */
    public function tinhNhienLieuVoiKhoangCach($tenTau, $diemBatDau, $diemKetThuc, $khoiLuong, $khoangCach) {
        // Reload dữ liệu để đảm bảo có dữ liệu mới nhất
        $this->khoangCach->reloadData();
        
        // Kiểm tra dữ liệu đầu vào (nới lỏng: không bắt buộc điểm tồn tại trong CSV)
        $this->validateInputForManualDistance($tenTau, $diemBatDau, $diemKetThuc, $khoiLuong);
        
        // Kiểm tra khoảng cách
        if (!is_numeric($khoangCach) || $khoangCach <= 0) {
            throw new Exception('Khoảng cách phải là số dương');
        }
        
        // Lấy hệ số nhiên liệu cho tàu và khoảng cách
        $heSo = $this->heSoTau->getHeSo($tenTau, $khoangCach);
        if ($heSo === null) {
            throw new Exception("Không tìm thấy hệ số nhiên liệu cho tàu '$tenTau' với khoảng cách $khoangCach km");
        }
        
        // Tính toán nhiên liệu theo công thức
        $ketQua = $this->tinhTheoCongThuc($khoangCach, $khoiLuong, $heSo);
        
        // Thêm thông tin bổ sung
        $nhomCuLy = phan_loai_cu_ly($khoangCach);
        $ketQua['thong_tin'] = [
            'ten_tau' => $tenTau,
            'diem_bat_dau' => $diemBatDau,
            'diem_ket_thuc' => $diemKetThuc,
            'khoang_cach_km' => $khoangCach,
            'khoi_luong_tan' => $khoiLuong,
            'he_so_ko_hang' => $heSo['k_ko_hang'],
            'he_so_co_hang' => $heSo['k_co_hang'],
            'nhom_cu_ly' => $nhomCuLy,
            'nhom_cu_ly_label' => label_cu_ly($nhomCuLy),
            'khoang_cach_thu_cong' => true // Đánh dấu là khoảng cách thủ công
        ];
        
        return $ketQua;
    }

    /**
     * Kiểm tra tính hợp lệ cho trường hợp nhập khoảng cách thủ công
     * - Không yêu cầu điểm tồn tại trong dữ liệu CSV
     */
    private function validateInputForManualDistance($tenTau, $diemBatDau, $diemKetThuc, $khoiLuong) {
        // Kiểm tra tên tàu
        if (empty($tenTau)) {
            throw new Exception('Tên tàu không được để trống');
        }
        if (!$this->heSoTau->isTauExists($tenTau)) {
            throw new Exception(ERROR_MESSAGES['tau_not_found'] . ": $tenTau");
        }

        // Kiểm tra điểm bắt đầu/kết thúc (không kiểm tra tồn tại trong CSV)
        if (empty($diemBatDau)) {
            throw new Exception('Điểm bắt đầu không được để trống');
        }
        if (empty($diemKetThuc)) {
            throw new Exception('Điểm kết thúc không được để trống');
        }
        if ($diemBatDau === $diemKetThuc) {
            throw new Exception('Điểm bắt đầu và điểm kết thúc không được giống nhau');
        }

        // Kiểm tra khối lượng
        if (!is_numeric($khoiLuong)) {
            throw new Exception(ERROR_MESSAGES['invalid_weight']);
        }
        if ($khoiLuong < 0) {
            throw new Exception('Khối lượng không được âm');
        }
    }
    
    /**
     * Tính toán theo công thức: Q = [(Sch+Skh)*Kkh] + (Sch*D*Kch)
     * @param float $khoangCach Khoảng cách (km)
     * @param float $khoiLuong Khối lượng (tấn)
     * @param array $heSo Hệ số nhiên liệu
     * @return array Kết quả tính toán
     */
    private function tinhTheoCongThuc($khoangCach, $khoiLuong, $heSo) {
        $Kkh = $heSo['k_ko_hang']; // Hệ số không hàng (Lít/Km)
        $Kch = $heSo['k_co_hang']; // Hệ số có hàng (Lít/T.Km)
        
        // Validate hệ số phải > 0 để tránh division by zero và kết quả sai
        if ($Kkh <= 0) {
            throw new Exception("Hệ số không hàng phải lớn hơn 0, hiện tại: $Kkh");
        }
        if ($Kch <= 0) {
            throw new Exception("Hệ số có hàng phải lớn hơn 0, hiện tại: $Kch");
        }
        
        // Validate khoảng cách và khối lượng
        if ($khoangCach <= 0) {
            throw new Exception("Khoảng cách phải lớn hơn 0");
        }
        if ($khoiLuong < 0) {
            throw new Exception("Khối lượng không được âm");
        }
        
        if ($khoiLuong == 0) {
            // Trường hợp không có hàng: Sch = 0, Skh = khoảng cách
            $Sch = 0; // Quãng đường có hàng
            $Skh = $khoangCach; // Quãng đường không hàng
            $D = 0; // Khối lượng hàng hóa
            
            // Q = [(0 + khoảng cách) * Kkh] + (0 * 0 * Kch)
            $Q = ($Sch + $Skh) * $Kkh;
            
            return [
                'nhien_lieu_lit' => round($Q, 2),
                'loai_tinh' => 'khong_hang',
                'chi_tiet' => [
                    'sch' => $Sch,
                    'skh' => $Skh,
                    'd' => $D,
                    'kkh' => $Kkh,
                    'kch' => $Kch,
                    'cong_thuc' => "Q = [($Sch + $Skh) × $Kkh] + ($Sch × $D × $Kch) = $Q Lít"
                ]
            ];
        } else {
            // Trường hợp có hàng: Sch = khoảng cách, Skh = 0
            $Sch = $khoangCach; // Quãng đường có hàng
            $Skh = 0; // Quãng đường không hàng
            $D = $khoiLuong; // Khối lượng hàng hóa
            
            // Q = [(khoảng cách + 0) * Kkh] + (khoảng cách * khối lượng * Kch)
            $Q = ($Sch + $Skh) * $Kkh + ($Sch * $D * $Kch);
            
            return [
                'nhien_lieu_lit' => round($Q, 2),
                'loai_tinh' => 'co_hang',
                'chi_tiet' => [
                    'sch' => $Sch,
                    'skh' => $Skh,
                    'd' => $D,
                    'kkh' => $Kkh,
                    'kch' => $Kch,
                    'cong_thuc' => "Q = [($Sch + $Skh) × $Kkh] + ($Sch × $D × $Kch) = $Q Lít"
                ]
            ];
        }
    }
    
    /**
     * Kiểm tra tính hợp lệ của dữ liệu đầu vào
     * @param string $tenTau Tên tàu
     * @param string $diemBatDau Điểm bắt đầu
     * @param string $diemKetThuc Điểm kết thúc
     * @param float $khoiLuong Khối lượng hàng hóa
     */
    private function validateInput($tenTau, $diemBatDau, $diemKetThuc, $khoiLuong) {
        // Kiểm tra tên tàu
        if (empty($tenTau)) {
            throw new Exception('Tên tàu không được để trống');
        }
        
        if (!$this->heSoTau->isTauExists($tenTau)) {
            throw new Exception(ERROR_MESSAGES['tau_not_found'] . ": $tenTau");
        }
        
        // Kiểm tra điểm bắt đầu
        if (empty($diemBatDau)) {
            throw new Exception('Điểm bắt đầu không được để trống');
        }
        
        if (!$this->khoangCach->isDiemExists($diemBatDau)) {
            throw new Exception(ERROR_MESSAGES['diem_not_found'] . ": $diemBatDau");
        }
        
        // Kiểm tra điểm kết thúc
        if (empty($diemKetThuc)) {
            throw new Exception('Điểm kết thúc không được để trống');
        }
        
        if (!$this->khoangCach->isDiemExists($diemKetThuc)) {
            throw new Exception(ERROR_MESSAGES['diem_not_found'] . ": $diemKetThuc");
        }
        
        // Kiểm tra điểm bắt đầu và kết thúc không được giống nhau
        if ($diemBatDau === $diemKetThuc) {
            throw new Exception('Điểm bắt đầu và điểm kết thúc không được giống nhau');
        }
        
        // Kiểm tra khối lượng
        if (!is_numeric($khoiLuong)) {
            throw new Exception(ERROR_MESSAGES['invalid_weight']);
        }
        
        if ($khoiLuong < 0) {
            throw new Exception('Khối lượng không được âm');
        }
    }
    
    /**
     * Lấy danh sách tàu
     * @return array Danh sách tàu
     */
    public function getDanhSachTau() {
        return $this->heSoTau->getDanhSachTau();
    }
    
    /**
     * Lấy danh sách điểm
     * @return array Danh sách điểm
     */
    public function getDanhSachDiem() {
        // Reload dữ liệu để đảm bảo có dữ liệu mới nhất
        $this->khoangCach->reloadData();
        return $this->khoangCach->getDanhSachDiem();
    }
    
    /**
     * Tìm kiếm điểm
     * @param string $keyword Từ khóa
     * @return array Kết quả tìm kiếm
     */
    public function searchDiem($keyword) {
        // Reload dữ liệu để đảm bảo có dữ liệu mới nhất
        $this->khoangCach->reloadData();
        return $this->khoangCach->searchDiem($keyword);
    }
}
?>


