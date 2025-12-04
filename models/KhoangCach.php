<?php
/**
 * Class KhoangCach - Quản lý thông tin khoảng cách giữa các điểm
 * Đọc dữ liệu từ file CSV và cung cấp các phương thức truy xuất
 */

require_once __DIR__ . '/../config/database.php';

class KhoangCach {
    private $khoangCachData = [];
    private $filePath;
    
    /**
     * Chuẩn hóa chuỗi để so sánh (loại bỏ dấu và chuyển về lowercase)
     * Sử dụng Unicode NFC normalization để đảm bảo tính nhất quán
     * @param string $str Chuỗi cần chuẩn hóa
     * @return string Chuỗi đã chuẩn hóa
     */
    private function normalizeString($str) {
        if (empty($str)) return '';
        
        // Normalize Unicode to NFC first if available
        if (function_exists('normalizer_normalize')) {
            $str = normalizer_normalize($str, Normalizer::FORM_C);
        }
        
        // Loại bỏ dấu tiếng Việt thủ công để đảm bảo kết quả nhất quán
        $str = str_replace(
            ['à', 'á', 'ạ', 'ả', 'ã', 'â', 'ầ', 'ấ', 'ậ', 'ẩ', 'ẫ', 'ă', 'ằ', 'ắ', 'ặ', 'ẳ', 'ẵ', 'À', 'Á', 'Ạ', 'Ả', 'Ã', 'Â', 'Ầ', 'Ấ', 'Ậ', 'Ẩ', 'Ẫ', 'Ă', 'Ằ', 'Ắ', 'Ặ', 'Ẳ', 'Ẵ'],
            ['a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a'],
            $str
        );
        $str = str_replace(
            ['è', 'é', 'ẹ', 'ẻ', 'ẽ', 'ê', 'ề', 'ế', 'ệ', 'ể', 'ễ', 'È', 'É', 'Ẹ', 'Ẻ', 'Ẽ', 'Ê', 'Ề', 'Ế', 'Ệ', 'Ể', 'Ễ'],
            ['e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e'],
            $str
        );
        $str = str_replace(
            ['ì', 'í', 'ị', 'ỉ', 'ĩ', 'Ì', 'Í', 'Ị', 'Ỉ', 'Ĩ'],
            ['i', 'i', 'i', 'i', 'i', 'i', 'i', 'i', 'i', 'i'],
            $str
        );
        $str = str_replace(
            ['ò', 'ó', 'ọ', 'ỏ', 'õ', 'ô', 'ồ', 'ố', 'ộ', 'ổ', 'ỗ', 'ơ', 'ờ', 'ớ', 'ợ', 'ở', 'ỡ', 'Ò', 'Ó', 'Ọ', 'Ỏ', 'Õ', 'Ô', 'Ồ', 'Ố', 'Ộ', 'Ổ', 'Ỗ', 'Ơ', 'Ờ', 'Ớ', 'Ợ', 'Ở', 'Ỡ'],
            ['o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o'],
            $str
        );
        $str = str_replace(
            ['ù', 'ú', 'ụ', 'ủ', 'ũ', 'ư', 'ừ', 'ứ', 'ự', 'ử', 'ữ', 'Ù', 'Ú', 'Ụ', 'Ủ', 'Ũ', 'Ư', 'Ừ', 'Ứ', 'Ự', 'Ử', 'Ữ'],
            ['u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u'],
            $str
        );
        $str = str_replace(
            ['ỳ', 'ý', 'ỵ', 'ỷ', 'ỹ', 'Ỳ', 'Ý', 'Ỵ', 'Ỷ', 'Ỹ'],
            ['y', 'y', 'y', 'y', 'y', 'y', 'y', 'y', 'y', 'y'],
            $str
        );
        $str = str_replace(
            ['đ', 'Đ'],
            ['d', 'd'],
            $str
        );
        
        // Loại bỏ các ký tự đặc biệt và khoảng trắng thừa
        $str = preg_replace('/[^a-zA-Z0-9\s]/', '', $str);
        $str = preg_replace('/\s+/', ' ', $str);
        $str = trim($str);
        
        // Chuyển về lowercase
        return strtolower($str);
    }
    
    /**
     * Constructor - Khởi tạo và đọc dữ liệu từ file CSV
     */
    public function __construct() {
        $this->filePath = KHOA_CACH_FILE;
        $this->loadData();
    }
    
    /**
     * Đọc dữ liệu từ file CSV
     */
    private function loadData() {
        if (!file_exists($this->filePath)) {
            throw new Exception(ERROR_MESSAGES['file_not_found'] . ': ' . $this->filePath);
        }
        
        $handle = fopen($this->filePath, 'r');
        if (!$handle) {
            throw new Exception('Không thể mở file: ' . $this->filePath);
        }
        
        // Bỏ qua header
        fgetcsv($handle);
        
        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) >= 4) {
                $this->khoangCachData[] = [
                    'id' => (int)$data[0],
                    'diem_dau' => trim($data[1]),
                    'diem_cuoi' => trim($data[2]),
                    'khoang_cach_km' => (float)$data[3]
                ];
            }
        }
        
        fclose($handle);
    }
    
    /**
     * Lấy danh sách tất cả các điểm
     * @return array Danh sách điểm
     */
    public function getDanhSachDiem() {
        $diemList = [];
        foreach ($this->khoangCachData as $row) {
            if (!in_array($row['diem_dau'], $diemList)) {
                $diemList[] = $row['diem_dau'];
            }
            if (!in_array($row['diem_cuoi'], $diemList)) {
                $diemList[] = $row['diem_cuoi'];
            }
        }
        sort($diemList);
        return $diemList;
    }
    
    /**
     * Tìm khoảng cách giữa hai điểm
     * @param string $diemDau Điểm đầu
     * @param string $diemCuoi Điểm cuối
     * @return float|null Khoảng cách (km) hoặc null nếu không tìm thấy
     */
    public function getKhoangCach($diemDau, $diemCuoi) {
        // Tạo danh sách các biến thể tên điểm để tìm kiếm linh hoạt hơn
        $diemDauVariants = $this->getPointNameVariants($diemDau);
        $diemCuoiVariants = $this->getPointNameVariants($diemCuoi);
        
        foreach ($this->khoangCachData as $row) {
            $rowDiemDauNormalized = $this->normalizeString($row['diem_dau']);
            $rowDiemCuoiNormalized = $this->normalizeString($row['diem_cuoi']);
            
            // Kiểm tra tất cả các biến thể
            foreach ($diemDauVariants as $diemDauVar) {
                foreach ($diemCuoiVariants as $diemCuoiVar) {
                    if (($rowDiemDauNormalized === $diemDauVar && $rowDiemCuoiNormalized === $diemCuoiVar) ||
                        ($rowDiemDauNormalized === $diemCuoiVar && $rowDiemCuoiNormalized === $diemDauVar)) {
                        return $row['khoang_cach_km'];
                    }
                }
            }
        }
        return null;
    }
    
    /**
     * Tạo danh sách các biến thể tên điểm để tìm kiếm linh hoạt
     * @param string $diem Tên điểm (có thể có phần trong ngoặc)
     * @return array Danh sách các biến thể đã normalize
     */
    private function getPointNameVariants($diem) {
        $variants = [];

        // 1. Tên đầy đủ (có phần trong ngoặc)
        $variants[] = $this->normalizeString($diem);

        // 2. Tạo các biến thể bằng cách xóa dần ngoặc từ cuối
        // Ví dụ: "Cảng Long Bình (ĐN) (test)"
        // → Variant 1: "Cảng Long Bình (ĐN) (test)" (đầy đủ)
        // → Variant 2: "Cảng Long Bình (ĐN)" (xóa ngoặc cuối)
        // → Variant 3: "Cảng Long Bình" (xóa tất cả ngoặc)

        $current = $diem;

        // Xóa từng cặp ngoặc từ cuối, tạo variant mỗi lần
        // Xử lý cả ngoặc thường () và fullwidth （）
        while (preg_match('/\s*[（(][^）)]*[）)]\s*$/', $current)) {
            $current = preg_replace('/\s*[（(][^）)]*[）)]\s*$/', '', $current);
            $current = trim($current);
            if ($current !== '' && $current !== $diem) {
                $variants[] = $this->normalizeString($current);
            }
        }

        // Loại bỏ trùng lặp
        return array_unique($variants);
    }
    
    /**
     * Tìm tất cả các tuyến đường từ một điểm
     * @param string $diem Điểm cần tìm
     * @return array Danh sách các tuyến đường
     */
    public function getTuyenDuongTuDiem($diem) {
        $tuyenDuong = [];
        $diemVariants = $this->getPointNameVariants($diem);
        
        foreach ($this->khoangCachData as $row) {
            $rowDiemDauNormalized = $this->normalizeString($row['diem_dau']);
            $rowDiemCuoiNormalized = $this->normalizeString($row['diem_cuoi']);
            
            // Kiểm tra tất cả các biến thể
            foreach ($diemVariants as $diemVar) {
                if ($rowDiemDauNormalized === $diemVar) {
                    $tuyenDuong[] = [
                        'diem_dau' => $row['diem_dau'],
                        'diem_cuoi' => $row['diem_cuoi'],
                        'khoang_cach_km' => $row['khoang_cach_km']
                    ];
                    break; // Đã tìm thấy, không cần kiểm tra các biến thể khác
                } elseif ($rowDiemCuoiNormalized === $diemVar) {
                    $tuyenDuong[] = [
                        'diem_dau' => $row['diem_cuoi'],
                        'diem_cuoi' => $row['diem_dau'],
                        'khoang_cach_km' => $row['khoang_cach_km']
                    ];
                    break; // Đã tìm thấy, không cần kiểm tra các biến thể khác
                }
            }
        }
        return $tuyenDuong;
    }
    
    /**
     * Kiểm tra điểm có tồn tại trong hệ thống không
     * @param string $diem Tên điểm
     * @return bool True nếu điểm tồn tại
     */
    public function isDiemExists($diem) {
        $danhSachDiem = $this->getDanhSachDiem();
        return in_array($diem, $danhSachDiem);
    }
    
    /**
     * Tìm kiếm điểm theo từ khóa
     * @param string $keyword Từ khóa tìm kiếm
     * @return array Danh sách điểm phù hợp
     */
    public function searchDiem($keyword) {
        $danhSachDiem = $this->getDanhSachDiem();
        $ketQua = [];

        // Chuẩn hóa Unicode về NFC nếu có thể
        if (function_exists('normalizer_normalize')) {
            $keyword = normalizer_normalize($keyword, Normalizer::FORM_C);
        }

        foreach ($danhSachDiem as $diem) {
            $target = $diem;
            if (function_exists('normalizer_normalize')) {
                $target = normalizer_normalize($target, Normalizer::FORM_C);
            }
            if (stripos($target, $keyword) !== false) {
                $ketQua[] = $diem;
            }
        }

        return $ketQua;
    }
    
    /**
     * Tìm kiếm điểm với thông tin khoảng cách và điểm kết nối
     * @param string $keyword Từ khóa tìm kiếm
     * @param string $diemDau Điểm đầu đã chọn (để lọc điểm cuối)
     * @return array Danh sách điểm với thông tin khoảng cách
     */
    public function searchDiemWithDistance($keyword, $diemDau = '') {
        $ketQua = [];
        // Chuẩn hóa Unicode về NFC
        if (function_exists('normalizer_normalize')) {
            $keyword = normalizer_normalize($keyword, Normalizer::FORM_C);
            $diemDau = normalizer_normalize($diemDau, Normalizer::FORM_C);
        }
        $diemDaThem = []; // Mảng để theo dõi điểm đã thêm
        $normalizedDiem = []; // Mảng để theo dõi điểm đã chuẩn hóa
        
        // Nếu có điểm đầu (đang tìm điểm kết thúc), chỉ tìm các điểm có kết nối
        if (!empty($diemDau)) {
            $normalizedDiemDau = $this->normalizeString($diemDau);
            foreach ($this->khoangCachData as $row) {
                $diemDauRow = $row['diem_dau'];
                $diemCuoiRow = $row['diem_cuoi'];
                $normDau = $this->normalizeString($diemDauRow);
                $normCuoi = $this->normalizeString($diemCuoiRow);
                $khoangCach = $row['khoang_cach_km'];
                
                // Nếu điểm đầu của tuyến khớp với điểm đã chọn, thêm điểm cuối
                $normalizedKeyword = $this->normalizeString($keyword);
                if ($normDau === $normalizedDiemDau && stripos($normCuoi, $normalizedKeyword) !== false && !in_array($normCuoi, $normalizedDiem)) {
                    $ketQua[] = [
                        'diem' => $diemCuoiRow,
                        'loai' => 'diem_cuoi',
                        'khoang_cach' => $khoangCach,
                        'diem_ket_noi' => $diemDauRow
                    ];
                    $diemDaThem[] = $diemCuoiRow;
                    $normalizedDiem[] = $normCuoi;
                }
                
                // Nếu điểm cuối của tuyến khớp với điểm đã chọn, thêm điểm đầu
                if ($normCuoi === $normalizedDiemDau && stripos($normDau, $normalizedKeyword) !== false && !in_array($normDau, $normalizedDiem)) {
                    $ketQua[] = [
                        'diem' => $diemDauRow,
                        'loai' => 'diem_dau',
                        'khoang_cach' => $khoangCach,
                        'diem_ket_noi' => $diemCuoiRow
                    ];
                    $diemDaThem[] = $diemDauRow;
                    $normalizedDiem[] = $normDau;
                }
            }
        } else {
            // Nếu không có điểm đầu (đang tìm điểm bắt đầu), tìm tất cả điểm duy nhất
            // Thu thập tất cả điểm duy nhất từ cả diem_dau và diem_cuoi
            $allDiem = [];
            $seenNormalized = []; // Mảng để theo dõi điểm đã chuẩn hóa
            
            foreach ($this->khoangCachData as $row) {
                $diemDauRow = $row['diem_dau'];
                $diemCuoiRow = $row['diem_cuoi'];
                
                // Chuẩn hóa chuỗi cho việc so sánh
                $normDau = $this->normalizeString($diemDauRow);
                $normCuoi = $this->normalizeString($diemCuoiRow);
                
                // Chỉ thêm nếu chưa có trong danh sách đã chuẩn hóa
                if (!in_array($normDau, $seenNormalized)) {
                    $allDiem[] = $diemDauRow;
                    $seenNormalized[] = $normDau;
                }
                if (!in_array($normCuoi, $seenNormalized)) {
                    $allDiem[] = $diemCuoiRow;
                    $seenNormalized[] = $normCuoi;
                }
            }
            
            // Tìm kiếm trong danh sách điểm duy nhất
            $seenNormalized = []; // Mảng để theo dõi điểm đã chuẩn hóa trong kết quả
            foreach ($allDiem as $diem) {
                $cmpDiem = $this->normalizeString($diem);
                $normalizedKeyword = $this->normalizeString($keyword);
                
                if (stripos($cmpDiem, $normalizedKeyword) !== false) {
                    // Chỉ thêm nếu chưa có trong danh sách đã chuẩn hóa
                    if (!in_array($cmpDiem, $seenNormalized)) {
                        $ketQua[] = [
                            'diem' => $diem,
                            'loai' => 'diem_unique',
                            'khoang_cach' => null,
                            'diem_ket_noi' => null
                        ];
                        $seenNormalized[] = $cmpDiem;
                    }
                }
            }
        }
        
        // Loại bỏ trùng lặp dựa trên chuẩn hóa
        $finalResult = [];
        $seenNormalized = [];
        
        foreach ($ketQua as $item) {
            $normalized = $this->normalizeString($item['diem']);
            if (!in_array($normalized, $seenNormalized)) {
                $finalResult[] = $item;
                $seenNormalized[] = $normalized;
            }
        }
        
        // Sắp xếp theo tên điểm
        usort($finalResult, function($a, $b) {
            return strcasecmp($a['diem'], $b['diem']);
        });
        
        return $finalResult;
    }
    
    /**
     * Lấy tất cả điểm cho việc tìm kiếm (khi không có từ khóa)
     * @param string $diemDau Điểm đầu đã chọn (để lọc điểm cuối)
     * @return array Danh sách điểm có kết nối
     */
    public function getAllDiemForSearch($diemDau = '') {
        $ketQua = [];
        $diemDaThem = [];
        $normalizedDiem = []; // Mảng để theo dõi điểm đã chuẩn hóa

        // Nếu có điểm đầu, chỉ lấy những điểm có kết nối thực sự
        if (!empty($diemDau)) {
            // Tạo variants cho điểm đầu để hỗ trợ tìm kiếm linh hoạt (loại bỏ ghi chú trong ngoặc)
            $diemDauVariants = $this->getPointNameVariants($diemDau);

            foreach ($this->khoangCachData as $row) {
                $diemDauRow = trim($row['diem_dau']);
                $diemCuoiRow = trim($row['diem_cuoi']);
                $khoangCach = $row['khoang_cach_km'];

                // Chuẩn hóa điểm trong database
                $cmpDau = $this->normalizeString($diemDauRow);
                $cmpCuoi = $this->normalizeString($diemCuoiRow);

                // Kiểm tra xem có variant nào của điểm đầu khớp với điểm trong DB không
                $dauMatches = false;
                foreach ($diemDauVariants as $variant) {
                    if ($cmpDau === $variant) {
                        $dauMatches = true;
                        break;
                    }
                }

                $cuoiMatches = false;
                foreach ($diemDauVariants as $variant) {
                    if ($cmpCuoi === $variant) {
                        $cuoiMatches = true;
                        break;
                    }
                }

                // Chỉ thêm điểm cuối nếu điểm đầu khớp với điểm đã chọn và chưa có trong danh sách
                if ($dauMatches && !in_array($cmpCuoi, $normalizedDiem)) {
                    $ketQua[] = [
                        'diem' => $diemCuoiRow,
                        'loai' => 'diem_cuoi',
                        'khoang_cach' => $khoangCach,
                        'diem_ket_noi' => $diemDauRow
                    ];
                    $diemDaThem[] = $diemCuoiRow;
                    $normalizedDiem[] = $cmpCuoi;
                }

                // Chỉ thêm điểm đầu nếu điểm cuối khớp với điểm đã chọn và chưa có trong danh sách
                if ($cuoiMatches && !in_array($cmpDau, $normalizedDiem)) {
                    $ketQua[] = [
                        'diem' => $diemDauRow,
                        'loai' => 'diem_dau',
                        'khoang_cach' => $khoangCach,
                        'diem_ket_noi' => $diemCuoiRow
                    ];
                    $diemDaThem[] = $diemDauRow;
                    $normalizedDiem[] = $cmpDau;
                }
            }
        } else {
            // Nếu không có điểm đầu, lấy tất cả điểm duy nhất (cho điểm bắt đầu)
            $seenNormalized = []; // Mảng để theo dõi điểm đã chuẩn hóa
            
            foreach ($this->khoangCachData as $row) {
                $diemDauRow = trim($row['diem_dau']);
                $diemCuoiRow = trim($row['diem_cuoi']);
                
                // Chuẩn hóa chuỗi cho việc so sánh
                $normDau = $this->normalizeString($diemDauRow);
                $normCuoi = $this->normalizeString($diemCuoiRow);
                
                // Thêm điểm đầu nếu chưa có (chỉ kiểm tra chuẩn hóa)
                if (!in_array($normDau, $seenNormalized)) {
                    $ketQua[] = [
                        'diem' => $diemDauRow,
                        'loai' => 'diem_dau',
                        'khoang_cach' => null,
                        'diem_ket_noi' => $diemCuoiRow
                    ];
                    $seenNormalized[] = $normDau;
                }
                
                // Thêm điểm cuối nếu chưa có (chỉ kiểm tra chuẩn hóa)
                if (!in_array($normCuoi, $seenNormalized)) {
                    $ketQua[] = [
                        'diem' => $diemCuoiRow,
                        'loai' => 'diem_cuoi',
                        'khoang_cach' => null,
                        'diem_ket_noi' => $diemDauRow
                    ];
                    $seenNormalized[] = $normCuoi;
                }
            }
        }
        
        // Loại bỏ trùng lặp dựa trên chuẩn hóa
        $finalResult = [];
        $seenNormalized = [];
        
        foreach ($ketQua as $item) {
            $normalized = $this->normalizeString($item['diem']);
            if (!in_array($normalized, $seenNormalized)) {
                $finalResult[] = $item;
                $seenNormalized[] = $normalized;
            }
        }
        
        // Sắp xếp theo tên điểm
        usort($finalResult, function($a, $b) {
            return strcasecmp($a['diem'], $b['diem']);
        });
        
        return $finalResult;
    }
    
    /**
     * Reload dữ liệu từ file CSV (để cập nhật dữ liệu mới)
     */
    public function reloadData() {
        $this->khoangCachData = [];
        $this->loadData();
    }
    
    /**
     * Lấy tất cả dữ liệu khoảng cách
     * @return array Dữ liệu khoảng cách
     */
    public function getAllData() {
        return $this->khoangCachData;
    }
}
?>
