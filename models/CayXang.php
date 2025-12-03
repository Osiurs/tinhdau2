<?php
/**
 * Model quản lý danh sách cây xăng
 */
class CayXang {
    private $csvFile;
    
    public function __construct() {
        $this->csvFile = __DIR__ . '/../data/cay_xang.csv';
    }
    
    /**
     * Lấy danh sách tất cả cây xăng
     * @return array
     */
    public function getAll() {
        $cayXang = [];
        
        if (!file_exists($this->csvFile)) {
            return $cayXang;
        }
        
        $handle = fopen($this->csvFile, 'r');
        if (!$handle) {
            return $cayXang;
        }
        
        // Bỏ qua header nếu có ít nhất một cột
        $header = fgetcsv($handle);
        if (!is_array($header) || count($header) === 0) {
            // Không có header hợp lệ, reposition to start of file
            rewind($handle);
        }
        
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) >= 1 && !empty(trim($row[0]))) {
                $cayXang[] = trim($row[0]);
            }
        }
        
        fclose($handle);
        
        // Sắp xếp theo tên
        sort($cayXang, SORT_NATURAL | SORT_FLAG_CASE);
        
        return $cayXang;
    }
    
    /**
     * Thêm cây xăng mới
     */
    public function them($tenCayXang) {
        $tenCayXang = trim($tenCayXang);
        if (empty($tenCayXang)) {
            throw new Exception('Tên cây xăng không được để trống');
        }
        
        // Kiểm tra trùng lặp với chuẩn hóa tiếng Việt
        $duplicate = $this->checkDuplicate($tenCayXang);
        if ($duplicate) {
            throw new Exception("Cây xăng đã tồn tại: \"$duplicate\"");
        }
        
        // Lấy danh sách hiện tại và thêm vào
        $danhSach = $this->getAll();
        $danhSach[] = $tenCayXang;
        
        // Sắp xếp lại
        sort($danhSach, SORT_NATURAL | SORT_FLAG_CASE);
        
        // Tạo nội dung CSV với header chuẩn
        $csvContent = "ten_cay_xang,created_at\n";
        foreach ($danhSach as $ten) {
            $csvContent .= '"' . str_replace('"', '""', $ten) . '",' . date('Y-m-d H:i:s') . "\n";
        }
        
        // Ghi file
        if (file_put_contents($this->csvFile, $csvContent) === false) {
            throw new Exception('Không thể ghi file CSV');
        }
        
        return true;
    }
    
    /**
     * Kiểm tra xem cây xăng có tồn tại không
     */
    /** @return bool */
    public function exists($tenCayXang) {
        $tenCayXang = trim($tenCayXang);
        if (empty($tenCayXang)) {
            return false;
        }
        
        $danhSach = $this->getAll();
        return in_array($tenCayXang, $danhSach);
    }
    
    /**
     * Thêm cây xăng mới (alias cho them)
     */
    /** @return bool */
    public function add($tenCayXang) {
        return $this->them($tenCayXang);
    }
    
    /**
     * Xóa cây xăng
     */
    /** @return bool */
    public function remove($tenCayXang) {
        $tenCayXang = trim($tenCayXang);
        if (empty($tenCayXang)) {
            return false;
        }
        
        $danhSach = $this->getAll();
        $index = array_search($tenCayXang, $danhSach);
        
        if ($index === false) {
            return false; // Không tìm thấy
        }
        
        // Xóa khỏi mảng
        unset($danhSach[$index]);
        $danhSach = array_values($danhSach); // Re-index
        
        // Ghi lại file
        $handle = fopen($this->csvFile, 'w');
        if (!$handle) {
            return false;
        }
        
        // Ghi header nhất quán với getAll()/them()
        fputcsv($handle, ['ten_cay_xang', 'created_at']);
        
        // Ghi dữ liệu
        foreach ($danhSach as $ten) {
            fputcsv($handle, [$ten, date('Y-m-d H:i:s')]);
        }
        
        fclose($handle);
        return true;
    }
    
    /**
     * Chuẩn hóa chuỗi tiếng Việt để tìm kiếm
     */
    private function normalizeVietnamese($str) {
        // Chuyển về chữ thường
        $str = mb_strtolower($str, 'UTF-8');
        
        // Loại bỏ dấu tiếng Việt
        $str = str_replace(
            ['à', 'á', 'ạ', 'ả', 'ã', 'â', 'ầ', 'ấ', 'ậ', 'ẩ', 'ẫ', 'ă', 'ằ', 'ắ', 'ặ', 'ẳ', 'ẵ'],
            'a', $str
        );
        $str = str_replace(
            ['è', 'é', 'ẹ', 'ẻ', 'ẽ', 'ê', 'ề', 'ế', 'ệ', 'ể', 'ễ'],
            'e', $str
        );
        $str = str_replace(
            ['ì', 'í', 'ị', 'ỉ', 'ĩ'],
            'i', $str
        );
        $str = str_replace(
            ['ò', 'ó', 'ọ', 'ỏ', 'õ', 'ô', 'ồ', 'ố', 'ộ', 'ổ', 'ỗ', 'ơ', 'ờ', 'ớ', 'ợ', 'ở', 'ỡ'],
            'o', $str
        );
        $str = str_replace(
            ['ù', 'ú', 'ụ', 'ủ', 'ũ', 'ư', 'ừ', 'ứ', 'ự', 'ử', 'ữ'],
            'u', $str
        );
        $str = str_replace(
            ['ỳ', 'ý', 'ỵ', 'ỷ', 'ỹ'],
            'y', $str
        );
        $str = str_replace('đ', 'd', $str);
        
        // Loại bỏ khoảng trắng thừa
        $str = preg_replace('/\s+/', ' ', trim($str));
        
        return $str;
    }
    
    /**
     * Tìm kiếm cây xăng theo tên (không phân biệt dấu)
     */
    public function search($keyword) {
        $keyword = trim($keyword);
        if (empty($keyword)) {
            return $this->getAll();
        }
        
        $normalizedKeyword = $this->normalizeVietnamese($keyword);
        $allCayXang = $this->getAll();
        $results = [];
        
        foreach ($allCayXang as $ten) {
            $normalizedTen = $this->normalizeVietnamese($ten);
            if (strpos($normalizedTen, $normalizedKeyword) !== false) {
                $results[] = $ten;
            }
        }
        
        return $results;
    }
    
    /**
     * Kiểm tra trùng lặp với chuẩn hóa tiếng Việt
     */
    public function checkDuplicate($tenCayXang) {
        $tenCayXang = trim($tenCayXang);
        if (empty($tenCayXang)) {
            return false;
        }
        
        $normalizedNew = $this->normalizeVietnamese($tenCayXang);
        $allCayXang = $this->getAll();
        
        foreach ($allCayXang as $ten) {
            $normalizedExisting = $this->normalizeVietnamese($ten);
            if ($normalizedNew === $normalizedExisting) {
                return $ten; // Trả về tên gốc đã tồn tại
            }
        }
        
        return false;
    }
    
    /**
     * Đổi tên cây xăng
     */
    public function rename($tenCu, $tenMoi) {
        $tenCu = trim($tenCu);
        $tenMoi = trim($tenMoi);
        
        if (empty($tenCu) || empty($tenMoi)) {
            throw new Exception('Tên cây xăng không được để trống');
        }
        
        if ($tenCu === $tenMoi) {
            return true; // Không cần thay đổi
        }
        
        $danhSach = $this->getAll();
        $index = array_search($tenCu, $danhSach);
        
        if ($index === false) {
            throw new Exception('Không tìm thấy cây xăng để đổi tên');
        }
        
        // Kiểm tra tên mới đã tồn tại chưa (với chuẩn hóa tiếng Việt)
        $duplicate = $this->checkDuplicate($tenMoi);
        if ($duplicate && $duplicate !== $tenCu) {
            throw new Exception("Tên cây xăng mới đã tồn tại: \"$duplicate\"");
        }
        
        // Cập nhật tên
        $danhSach[$index] = $tenMoi;
        
        // Ghi lại file
        $handle = fopen($this->csvFile, 'w');
        if (!$handle) {
            throw new Exception('Không thể ghi file');
        }
        
        // Ghi header nhất quán với getAll()/them()
        fputcsv($handle, ['ten_cay_xang', 'created_at']);
        
        // Ghi dữ liệu
        foreach ($danhSach as $ten) {
            fputcsv($handle, [$ten, date('Y-m-d H:i:s')]);
        }
        
        fclose($handle);
        return true;
    }
}