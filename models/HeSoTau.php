<?php
/**
 * Class HeSoTau - Quản lý hệ số nhiên liệu của các loại tàu
 * Đọc dữ liệu từ file CSV và cung cấp các phương thức truy xuất
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/TauPhanLoai.php';

class HeSoTau {
    private $heSoData = [];
    private $filePath;
    
    /**
     * Constructor - Khởi tạo và đọc dữ liệu từ file CSV
     */
    public function __construct() {
        $this->filePath = HE_SO_TAU_FILE;
        $this->loadData();
    }
    
    /**
     * Đọc dữ liệu từ file CSV
     */
    private function loadData() {
        // Reset cache before (re)loading to avoid duplicating rows on reloads
        $this->heSoData = [];
        if (!file_exists($this->filePath)) {
            throw new Exception(ERROR_MESSAGES['file_not_found'] . ': ' . $this->filePath);
        }
        
        $handle = fopen($this->filePath, 'r');
        if (!$handle) {
            throw new Exception('Không thể mở file: ' . $this->filePath);
        }
        
        // Bỏ qua header
        fgetcsv($handle);

        // Chính sách nạp dữ liệu:
        // - Luôn giữ tất cả tàu thuê ngoài
        // - Với tàu công ty: chỉ giữ các tàu có trong danh sách duyệt (data/tau_phan_loai.csv, phan_loai=cong_ty)
        $phanLoai = new TauPhanLoai();
        $phanLoaiMap = $phanLoai->getAll(); // ten_tau => cong_ty|thue_ngoai
        $allowedCompanyShips = [];
        foreach ($phanLoaiMap as $ten => $pl) {
            if ($pl === 'cong_ty') { $allowedCompanyShips[$ten] = true; }
        }
        
        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) >= 5) {
                $tenTau = trim($data[0]);
                $pl = $phanLoaiMap[$tenTau] ?? null;
                // Bỏ các tàu công ty không có trong danh sách duyệt; giữ thuê ngoài và các tàu khác
                if ($pl === 'cong_ty' && !isset($allowedCompanyShips[$tenTau])) { continue; }
                $this->heSoData[] = [
                    'ten_tau' => $tenTau,
                    'km_min' => (float)$data[1],
                    'km_max' => (float)$data[2],
                    'k_ko_hang' => (float)$data[3],
                    'k_co_hang' => (float)$data[4]
                ];
            }
        }
        
        fclose($handle);
    }
    
    /**
     * Lấy danh sách tất cả các tàu
     * @return array Danh sách tên tàu
     */
    public function getDanhSachTau() {
        $tauList = [];
        foreach ($this->heSoData as $row) {
            if (!in_array($row['ten_tau'], $tauList)) {
                $tauList[] = $row['ten_tau'];
            }
        }
        sort($tauList);
        return $tauList;
    }
    
    /**
     * Lấy hệ số nhiên liệu cho tàu và khoảng cách cụ thể
     * @param string $tenTau Tên tàu
     * @param float $khoangCach Khoảng cách (km)
     * @return array|null Mảng chứa hệ số không hàng và có hàng
     */
    public function getHeSo($tenTau, $khoangCach) {
        foreach ($this->heSoData as $row) {
            if ($row['ten_tau'] === $tenTau && 
                $khoangCach >= $row['km_min'] && 
                $khoangCach <= $row['km_max']) {
                return [
                    'k_ko_hang' => $row['k_ko_hang'],
                    'k_co_hang' => $row['k_co_hang']
                ];
            }
        }
        return null;
    }
    
    /**
     * Kiểm tra tàu có tồn tại trong hệ thống không
     * @param string $tenTau Tên tàu
     * @return bool True nếu tàu tồn tại
     */
    public function isTauExists($tenTau) {
        $danhSachTau = $this->getDanhSachTau();
        return in_array($tenTau, $danhSachTau);
    }
    
    /**
     * Lấy thông tin chi tiết của một tàu
     * @param string $tenTau Tên tàu
     * @return array|null Thông tin chi tiết tàu
     */
    public function getThongTinTau($tenTau) {
        $thongTin = [];
        foreach ($this->heSoData as $row) {
            if ($row['ten_tau'] === $tenTau) {
                $thongTin[] = $row;
            }
        }
        return empty($thongTin) ? null : $thongTin;
    }
    
    /**
     * Lấy tất cả dữ liệu tàu
     * @return array Dữ liệu tàu
     */
    public function getAllData() {
        return $this->heSoData;
    }
    
    /**
     * Lấy dữ liệu tàu được nhóm theo tên tàu
     * @return array Dữ liệu tàu được nhóm
     */
    public function getTauGrouped() {
        $grouped = [];
        foreach ($this->heSoData as $row) {
            $tenTau = $row['ten_tau'];
            if (!isset($grouped[$tenTau])) {
                $grouped[$tenTau] = [];
            }
            $grouped[$tenTau][] = $row;
        }
        
        // Sắp xếp các đoạn theo km_min
        foreach ($grouped as $tenTau => $segments) {
            usort($grouped[$tenTau], function($a, $b) {
                return $a['km_min'] <=> $b['km_min'];
            });
        }
        
        return $grouped;
    }
    
    /**
     * Sao chép tàu với tên mới
     * @param string $tenTauGoc Tên tàu gốc
     * @param string $tenTauMoi Tên tàu mới
     * @return bool True nếu thành công
     */
    public function copyTau($tenTauGoc, $tenTauMoi) {
        // Kiểm tra tàu gốc có tồn tại không
        if (!$this->isTauExists($tenTauGoc)) {
            return false;
        }
        
        // Kiểm tra tàu mới chưa tồn tại
        if ($this->isTauExists($tenTauMoi)) {
            return false;
        }
        
        // Lấy thông tin tàu gốc
        $thongTinTauGoc = $this->getThongTinTau($tenTauGoc);
        if (!$thongTinTauGoc) {
            return false;
        }
        
        // Tạo dữ liệu mới cho tàu mới
        $newData = [];
        foreach ($thongTinTauGoc as $segment) {
            $newData[] = "$tenTauMoi,{$segment['km_min']},{$segment['km_max']},{$segment['k_ko_hang']},{$segment['k_co_hang']}";
        }
        
        // Ghi vào file CSV
        $newDataString = implode("\n", $newData) . "\n";
        file_put_contents($this->filePath, $newDataString, FILE_APPEND | LOCK_EX);
        
        // Reload data
        $this->loadData();
        
        return true;
    }
    
    /**
     * Xóa toàn bộ tàu (tất cả các đoạn)
     * @param string $tenTau Tên tàu cần xóa
     * @return bool True nếu thành công
     */
    public function deleteTau($tenTau) {
        if (!file_exists($this->filePath)) {
            return false;
        }
        
        $lines = file($this->filePath, FILE_IGNORE_NEW_LINES);
        $newLines = [];
        
        foreach ($lines as $line) {
            $data = explode(',', $line);
            if (count($data) >= 5) {
                if ($data[0] !== $tenTau) {
                    $newLines[] = $line;
                }
            }
        }
        
        // Ghi lại file
        file_put_contents($this->filePath, implode("\n", $newLines) . "\n");
        
        // Reload data
        $this->loadData();
        
        return true;
    }
}
?>
