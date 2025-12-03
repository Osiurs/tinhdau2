<?php
/**
 * Class LuuKetQua - Lưu kết quả tính toán vào file CSV
 */

require_once __DIR__ . '/../config/database.php';

class LuuKetQua {
    /**
     * Đảm bảo thư mục và file tồn tại, nếu chưa thì tạo cùng với header
     */
    private function ensureStorage(): void {
        if (!is_dir(KET_QUA_DIR)) {
            @mkdir(KET_QUA_DIR, 0777, true);
        }

        if (!file_exists(KET_QUA_FILE)) {
            $handle = fopen(KET_QUA_FILE, 'w');
            if ($handle) {
                // Header CSV
                fputcsv($handle, [
                    'ten_phuong_tien',
                    'so_chuyen',
                    'diem_di',
                    'diem_du_kien',
                    'diem_den',
                    'doi_lenh_tuyen',
                    'route_hien_thi',
                    'cu_ly_co_hang_km',
                    'cu_ly_khong_hang_km',
                    'he_so_co_hang',
                    'he_so_khong_hang',
                    'khoi_luong_van_chuyen_t',
                    'khoi_luong_luan_chuyen',
                    'dau_tinh_toan_lit',
                    'cap_them',
                    'doi_lenh',
                    'ly_do_cap_them',
                    'so_luong_cap_them_lit',
                    'ngay_di',
                    'ngay_den',
                    'ngay_do_xong',
                    'loai_hang',
                    'ghi_chu',
                    'thang_bao_cao',
                    'created_at'
                ]);
                fclose($handle);
            }
        }

        // Migration: remove 'dau_tong_lit' column if present in existing CSV
        $fh = fopen(KET_QUA_FILE, 'r');
        if ($fh) {
            $headers = fgetcsv($fh) ?: [];
            $dropIdx = array_search('dau_tong_lit', $headers, true);
            if ($dropIdx !== false) {
                $rows = [];
                while (($data = fgetcsv($fh)) !== false) {
                    if (array_key_exists($dropIdx, $data)) {
                        unset($data[$dropIdx]);
                    }
                    $rows[] = array_values($data);
                }
                fclose($fh);

                unset($headers[$dropIdx]);
                $headers = array_values($headers);

                $wh = fopen(KET_QUA_FILE, 'w');
                if ($wh) {
                    fputcsv($wh, $headers);
                    foreach ($rows as $row) {
                        fputcsv($wh, $row);
                    }
                    fclose($wh);
                }
            } else {
                fclose($fh);
            }
        }

        // Migration: remove 'ma_doan' column if present in existing CSV
        $fh2 = fopen(KET_QUA_FILE, 'r');
        if ($fh2) {
            $headers2 = fgetcsv($fh2) ?: [];
            $dropMaDoanIdx = array_search('ma_doan', $headers2, true);
            if ($dropMaDoanIdx !== false) {
                $rows2 = [];
                while (($data = fgetcsv($fh2)) !== false) {
                    if (array_key_exists($dropMaDoanIdx, $data)) {
                        unset($data[$dropMaDoanIdx]);
                    }
                    $rows2[] = array_values($data);
                }
                fclose($fh2);

                unset($headers2[$dropMaDoanIdx]);
                $headers2 = array_values($headers2);

                $wh2 = fopen(KET_QUA_FILE, 'w');
                if ($wh2) {
                    fputcsv($wh2, $headers2);
                    foreach ($rows2 as $row) {
                        fputcsv($wh2, $row);
                    }
                    fclose($wh2);
                }
            } else {
                fclose($fh2);
            }
        }

        // Migration: add 'ghi_chu' column if missing
        $fh2 = fopen(KET_QUA_FILE, 'r');
        if ($fh2) {
            $headers2 = fgetcsv($fh2) ?: [];
            if (!in_array('ghi_chu', $headers2, true)) {
                $rows2 = [];
                while (($data = fgetcsv($fh2)) !== false) {
                    $rows2[] = $data;
                }
                fclose($fh2);

                // Determine insert position: before 'created_at' if exists, else at end
                $createdIdx = array_search('created_at', $headers2, true);
                if ($createdIdx === false) {
                    $headers2[] = 'ghi_chu';
                    foreach ($rows2 as &$r) { $r[] = ''; }
                    unset($r);
                } else {
                    array_splice($headers2, $createdIdx, 0, 'ghi_chu');
                    foreach ($rows2 as &$r) { array_splice($r, $createdIdx, 0, ''); }
                    unset($r);
                }

                $wh2 = fopen(KET_QUA_FILE, 'w');
                if ($wh2) {
                    fputcsv($wh2, $headers2);
                    foreach ($rows2 as $row) { fputcsv($wh2, $row); }
                    fclose($wh2);
                }
            } else {
                fclose($fh2);
            }
        }

        // Migration: add 'doi_lenh' column if missing (after 'cap_them')
        $fh3 = fopen(KET_QUA_FILE, 'r');
        if ($fh3) {
            $headers3 = fgetcsv($fh3) ?: [];
            if (!in_array('doi_lenh', $headers3, true)) {
                $rows3 = [];
                while (($data = fgetcsv($fh3)) !== false) { $rows3[] = $data; }
                fclose($fh3);

                $posCapThem = array_search('cap_them', $headers3, true);
                $insertPos = ($posCapThem === false) ? count($headers3) : $posCapThem + 1;
                array_splice($headers3, $insertPos, 0, 'doi_lenh');
                foreach ($rows3 as &$r) { array_splice($r, $insertPos, 0, '0'); }
                unset($r);

                $wh3 = fopen(KET_QUA_FILE, 'w');
                if ($wh3) {
                    fputcsv($wh3, $headers3);
                    foreach ($rows3 as $row) { fputcsv($wh3, $row); }
                    fclose($wh3);
                }
            } else {
                fclose($fh3);
            }
        }

        // Migration: add 'nhom_cu_ly' column if missing (before 'loai_hang' if possible)
        $fh5 = fopen(KET_QUA_FILE, 'r');
        if ($fh5) {
            $headers5 = fgetcsv($fh5) ?: [];
            if (!in_array('nhom_cu_ly', $headers5, true)) {
                $rows5 = [];
                while (($data = fgetcsv($fh5)) !== false) { $rows5[] = $data; }
                fclose($fh5);

                $insertPos = array_search('loai_hang', $headers5, true);
                if ($insertPos === false) {
                    $insertPos = array_search('created_at', $headers5, true);
                }
                if ($insertPos === false) {
                    $insertPos = count($headers5);
                }
                array_splice($headers5, $insertPos, 0, 'nhom_cu_ly');
                foreach ($rows5 as &$r) { array_splice($r, $insertPos, 0, ''); }
                unset($r);

                $wh5 = fopen(KET_QUA_FILE, 'w');
                if ($wh5) {
                    fputcsv($wh5, $headers5);
                    foreach ($rows5 as $row) { fputcsv($wh5, $row); }
                    fclose($wh5);
                }
            } else {
                fclose($fh5);
            }
        }

        // Migration: add 'diem_du_kien' column if missing (after 'diem_di' or before 'diem_den')
        $fh4 = fopen(KET_QUA_FILE, 'r');
        if ($fh4) {
            $headers4 = fgetcsv($fh4) ?: [];
            if (!in_array('diem_du_kien', $headers4, true)) {
                $rows4 = [];
                while (($data = fgetcsv($fh4)) !== false) { $rows4[] = $data; }
                fclose($fh4);

                $posDiemDen = array_search('diem_den', $headers4, true);
                $insertPos = ($posDiemDen === false) ? 3 : $posDiemDen; // default after diem_di
                array_splice($headers4, $insertPos, 0, 'diem_du_kien');
                foreach ($rows4 as &$r) { array_splice($r, $insertPos, 0, ''); }
                unset($r);

                $wh4 = fopen(KET_QUA_FILE, 'w');
                if ($wh4) {
                    fputcsv($wh4, $headers4);
                    foreach ($rows4 as $row) { fputcsv($wh4, $row); }
                    fclose($wh4);
                }
            } else {
                fclose($fh4);
            }
        }

        // Migration: add 'doi_lenh_tuyen' column if missing (after 'diem_den')
        $fh7 = fopen(KET_QUA_FILE, 'r');
        if ($fh7) {
            $headers7 = fgetcsv($fh7) ?: [];
            if (!in_array('doi_lenh_tuyen', $headers7, true)) {
                $rows7 = [];
                while (($data = fgetcsv($fh7)) !== false) { $rows7[] = $data; }
                fclose($fh7);

                $insertPos = array_search('diem_den', $headers7, true);
                if ($insertPos === false) {
                    $insertPos = count($headers7);
                } else {
                    $insertPos = $insertPos + 1;
                }
                array_splice($headers7, $insertPos, 0, 'doi_lenh_tuyen');
                foreach ($rows7 as &$r) { array_splice($r, $insertPos, 0, ''); }
                unset($r);

                $wh7 = fopen(KET_QUA_FILE, 'w');
                if ($wh7) {
                    fputcsv($wh7, $headers7);
                    foreach ($rows7 as $row) { fputcsv($wh7, $row); }
                    fclose($wh7);
                }
            } else {
                fclose($fh7);
            }
        }

        // Migration: add 'route_hien_thi' column if missing (after 'doi_lenh_tuyen')
        $fh8 = fopen(KET_QUA_FILE, 'r');
        if ($fh8) {
            $headers8 = fgetcsv($fh8) ?: [];
            if (!in_array('route_hien_thi', $headers8, true)) {
                $rows8 = [];
                while (($data = fgetcsv($fh8)) !== false) { $rows8[] = $data; }
                fclose($fh8);

                $insertPos = array_search('doi_lenh_tuyen', $headers8, true);
                if ($insertPos === false) {
                    $insertPos = array_search('diem_den', $headers8, true);
                }
                if ($insertPos === false) {
                    $insertPos = count($headers8);
                } else {
                    $insertPos = $insertPos + 1;
                }
                array_splice($headers8, $insertPos, 0, 'route_hien_thi');
                foreach ($rows8 as &$r) { array_splice($r, $insertPos, 0, ''); }
                unset($r);

                $wh8 = fopen(KET_QUA_FILE, 'w');
                if ($wh8) {
                    fputcsv($wh8, $headers8);
                    foreach ($rows8 as $row) { fputcsv($wh8, $row); }
                    fclose($wh8);
                }
            } else {
                fclose($fh8);
            }
        }

        // Migration: add 'cay_xang_cap_them' column if missing (after 'so_luong_cap_them_lit')
        $fh6 = fopen(KET_QUA_FILE, 'r');
        if ($fh6) {
            $headers6 = fgetcsv($fh6) ?: [];
            if (!in_array('cay_xang_cap_them', $headers6, true)) {
                $rows6 = [];
                while (($data = fgetcsv($fh6)) !== false) { $rows6[] = $data; }
                fclose($fh6);

                $posSoLuongCapThem = array_search('so_luong_cap_them_lit', $headers6, true);
                $insertPos = ($posSoLuongCapThem === false) ? count($headers6) : $posSoLuongCapThem + 1;
                array_splice($headers6, $insertPos, 0, 'cay_xang_cap_them');
                foreach ($rows6 as &$r) { array_splice($r, $insertPos, 0, ''); }
                unset($r);

                $wh6 = fopen(KET_QUA_FILE, 'w');
                if ($wh6) {
                    fputcsv($wh6, $headers6);
                    foreach ($rows6 as $row) { fputcsv($wh6, $row); }
                    fclose($wh6);
                }
            } else {
                fclose($fh6);
            }
        }
    }

    /**
     * Lấy mã chuyến cao nhất của một tàu
     * @param string $tenTau
     * @return int
     */
    public function layMaChuyenCaoNhat(string $tenTau): int {
        $this->ensureStorage();
        $maxChuyen = 0;
        
        // Chuẩn hóa tên tàu để tìm kiếm linh hoạt hơn
        $tenTauChuan = $this->chuanHoaTenTau($tenTau);
        
        if (($handle = fopen(KET_QUA_FILE, 'r')) !== false) {
            $headers = fgetcsv($handle) ?: [];
            while (($data = fgetcsv($handle)) !== false) {
                if (count($data) !== count($headers)) {
                    // Bỏ qua dòng lỗi định dạng
                    continue;
                }
                $row = array_combine($headers, $data);
                if (!is_array($row)) { continue; }
                
                $tenTauTrongDB = trim((string)($row['ten_phuong_tien'] ?? ''));
                $tenTauTrongDBChuan = $this->chuanHoaTenTau($tenTauTrongDB);
                
                if ($tenTauChuan === $tenTauTrongDBChuan) {
                    $soChuyen = (int)trim((string)($row['so_chuyen'] ?? '0'));
                    if ($soChuyen > $maxChuyen) {
                        $maxChuyen = $soChuyen;
                    }
                }
            }
            fclose($handle);
        }
        
        return $maxChuyen;
    }

    /**
     * Lấy danh sách tất cả các chuyến của một tàu (để hiển thị trong dropdown)
     */
    public function getDanhSachChuyenCuaTau(string $tenTau): array {
        $this->ensureStorage();
        $trips = [];
        
        // Chuẩn hóa tên tàu để tìm kiếm linh hoạt hơn
        $tenTauChuan = $this->chuanHoaTenTau($tenTau);
        
        if (($handle = fopen(KET_QUA_FILE, 'r')) !== false) {
            $headers = fgetcsv($handle) ?: [];
            while (($data = fgetcsv($handle)) !== false) {
                if (count($data) !== count($headers)) {
                    continue;
                }
                $row = array_combine($headers, $data);
                if (!is_array($row)) { continue; }
                
                $tenTauTrongDB = trim((string)($row['ten_phuong_tien'] ?? ''));
                $tenTauTrongDBChuan = $this->chuanHoaTenTau($tenTauTrongDB);
                
                if ($tenTauChuan === $tenTauTrongDBChuan) {
                    $soChuyen = (int)trim((string)($row['so_chuyen'] ?? '0'));
                    if (!in_array($soChuyen, $trips)) {
                        $trips[] = $soChuyen;
                    }
                }
            }
            fclose($handle);
        }

        // Sắp xếp theo thứ tự giảm dần (chuyến mới nhất trước)
        rsort($trips);
        return $trips;
    }
    
    /**
     * Chuẩn hóa tên tàu để tìm kiếm linh hoạt
     * Ví dụ: HTL-01 -> HTL-1, HTL-02 -> HTL-2, ABC-05 -> ABC-5
     */
    private function chuanHoaTenTau(string $tenTau): string {
        // Loại bỏ khoảng trắng
        $tenTau = trim($tenTau);
        
        // Chuẩn hóa format số: XXX-0Y -> XXX-Y (cho tất cả prefix)
        if (preg_match('/^([A-Za-z]+)-0(\d+)$/', $tenTau, $matches)) {
            return $matches[1] . '-' . $matches[2];
        }
        
        // Chuẩn hóa format số: XXX-00Y -> XXX-Y (cho số có 2 chữ số với leading zero)
        if (preg_match('/^([A-Za-z]+)-0(\d{2,})$/', $tenTau, $matches)) {
            return $matches[1] . '-' . ltrim($matches[2], '0') ?: '0';
        }
        
        return $tenTau;
    }

    /**
     * Lấy danh sách các đoạn của một chuyến cụ thể, sắp xếp theo ngày đi
     * @param string $tenTau
     * @param int $soChuyen
     * @return array
     */
    public function layCacDoanCuaChuyen(string $tenTau, int $soChuyen): array {
        $this->ensureStorage();
        $doanList = [];
        
        // Chuẩn hóa tên tàu để tìm kiếm linh hoạt hơn
        $tenTauChuan = $this->chuanHoaTenTau($tenTau);
        
        if (($handle = fopen(KET_QUA_FILE, 'r')) !== false) {
            $headers = fgetcsv($handle) ?: [];
            $i = 0;
            
            while (($data = fgetcsv($handle)) !== false) {
                $i++;
                if (count($data) !== count($headers)) { continue; }
                $row = array_combine($headers, $data);
                if (!is_array($row)) { continue; }
                
                $tenTauTrongDB = trim((string)($row['ten_phuong_tien'] ?? ''));
                $tenTauTrongDBChuan = $this->chuanHoaTenTau($tenTauTrongDB);
                $soCh = intval($row['so_chuyen'] ?? 0);
                
                if (($tenTauChuan === $tenTauTrongDBChuan || $tenTau === $tenTauTrongDB) && $soCh === $soChuyen) {
                    // Bỏ qua các bản ghi chỉ dùng để ghi nhận Cấp thêm
                    if (intval($row['cap_them'] ?? 0) === 1) { continue; }
                    $row['___idx'] = $i;
                    $doanList[] = $row;
                }
            }
            fclose($handle);
        }
        
        // Sắp xếp theo ngày đi (từ cũ đến mới)
        usort($doanList, function($a, $b) {
            $ngayA = parse_date_vn($a['ngay_di'] ?? '') ?: '';
            $ngayB = parse_date_vn($b['ngay_di'] ?? '') ?: '';
            
            if ($ngayA && $ngayB) {
                return strtotime($ngayA) - strtotime($ngayB);
            } elseif ($ngayA) {
                return -1;
            } elseif ($ngayB) {
                return 1;
            } else {
                // Nếu không có ngày đi, sắp xếp theo thời gian tạo
                return strtotime($a['created_at'] ?? '') - strtotime($b['created_at'] ?? '');
            }
        });
        
        return $doanList;
    }

    /**
     * Lấy ngày của chuyến trước đó cùng tàu (để link cho cấp thêm)
     */
    public function layNgayChuyenTruoc(string $tenTau, int $soChuyenHienTai): string {
        $this->ensureStorage();
        $ngayChuyenTruoc = '';
        
        // Chuẩn hóa tên tàu để tìm kiếm linh hoạt hơn
        $tenTauChuan = $this->chuanHoaTenTau($tenTau);
        
        if (($handle = fopen(KET_QUA_FILE, 'r')) !== false) {
            $headers = fgetcsv($handle) ?: [];
            $chuyenTruocGanNhat = 0;
            $ngayChuyenTruocGanNhat = '';
            
            while (($data = fgetcsv($handle)) !== false) {
                if (count($data) !== count($headers)) { continue; }
                $row = array_combine($headers, $data);
                if (!is_array($row)) { continue; }
                
                $tenTauTrongDB = trim((string)($row['ten_phuong_tien'] ?? ''));
                $tenTauTrongDBChuan = $this->chuanHoaTenTau($tenTauTrongDB);
                
                if ($tenTauChuan === $tenTauTrongDBChuan) {
                    $soChuyen = (int)trim((string)($row['so_chuyen'] ?? '0'));
                    
                    // Chỉ lấy chuyến trước chuyến hiện tại
                    if ($soChuyen < $soChuyenHienTai && $soChuyen > $chuyenTruocGanNhat) {
                        $chuyenTruocGanNhat = $soChuyen;
                        // Ưu tiên ngày dỡ xong, nếu không có thì dùng ngày đi
                        $ngayDoXong = trim((string)($row['ngay_do_xong'] ?? ''));
                        $ngayDi = trim((string)($row['ngay_di'] ?? ''));
                        
                        if ($ngayDoXong !== '') {
                            $ngayChuyenTruocGanNhat = $ngayDoXong;
                        } elseif ($ngayDi !== '') {
                            $ngayChuyenTruocGanNhat = $ngayDi;
                        }
                    }
                }
            }
            fclose($handle);
            
            $ngayChuyenTruoc = $ngayChuyenTruocGanNhat;
        }
        
        return $ngayChuyenTruoc;
    }

    /**
     * Lấy các bản ghi Cấp thêm thuộc một chuyến cụ thể (để hiển thị thông báo/nhắc trên UI)
     */
    public function layCapThemCuaChuyen(string $tenTau, int $soChuyen): array {
        $this->ensureStorage();
        $list = [];

        $tenTauChuan = $this->chuanHoaTenTau($tenTau);
        if (($handle = fopen(KET_QUA_FILE, 'r')) !== false) {
            $headers = fgetcsv($handle) ?: [];
            $i = 0;
            
            while (($data = fgetcsv($handle)) !== false) {
                $i++;
                if (count($data) !== count($headers)) { continue; }
                $row = array_combine($headers, $data);
                if (!is_array($row)) { continue; }
                $tenTauTrongDB = trim((string)($row['ten_phuong_tien'] ?? ''));
                $tenTauTrongDBChuan = $this->chuanHoaTenTau($tenTauTrongDB);
                $soCh = intval($row['so_chuyen'] ?? 0);
                if (($tenTauChuan === $tenTauTrongDBChuan || $tenTau === $tenTauTrongDB)
                    && $soCh === $soChuyen
                    && intval($row['cap_them'] ?? 0) === 1) {
                    $row['___idx'] = $i; // Thêm ID để sắp xếp theo thứ tự nhập
                    $list[] = $row;
                }
            }
            fclose($handle);
        }

        // Sắp xếp theo ID (thứ tự nhập) để giữ nguyên thứ tự thực tế
        usort($list, function($a, $b){
            $idA = (int)($a['___idx'] ?? 0);
            $idB = (int)($b['___idx'] ?? 0);
            return $idA <=> $idB;
        });

        return $list;
    }

    /**
     * Lưu một dòng dữ liệu vào CSV với file locking để tránh race condition
     * @param array $data
     * @return bool
     */
    public function luu(array $data): bool {
        $this->ensureStorage();

        // Read headers to map row dynamically
        $rh = fopen(KET_QUA_FILE, 'r');
        if (!$rh) {
            throw new Exception('Không thể mở file lưu kết quả: ' . KET_QUA_FILE);
        }
        $headers = fgetcsv($rh) ?: [];
        fclose($rh);

        $rowOrdered = [];
        foreach ($headers as $h) {
            switch ($h) {
                case 'ten_phuong_tien': $rowOrdered[] = $data['ten_phuong_tien'] ?? ''; break;
                case 'so_chuyen': $rowOrdered[] = $data['so_chuyen'] ?? ''; break;
                case 'diem_di': $rowOrdered[] = $data['diem_di'] ?? ''; break;
                case 'diem_du_kien': $rowOrdered[] = $data['diem_du_kien'] ?? ''; break;
                case 'diem_den': $rowOrdered[] = $data['diem_den'] ?? ''; break;
                case 'doi_lenh_tuyen': $rowOrdered[] = $data['doi_lenh_tuyen'] ?? ''; break;
                case 'route_hien_thi': $rowOrdered[] = $data['route_hien_thi'] ?? ''; break;
                case 'cu_ly_co_hang_km': $rowOrdered[] = $data['cu_ly_co_hang_km'] ?? 0; break;
                case 'cu_ly_khong_hang_km': $rowOrdered[] = $data['cu_ly_khong_hang_km'] ?? 0; break;
                case 'he_so_co_hang': $rowOrdered[] = $data['he_so_co_hang'] ?? 0; break;
                case 'he_so_khong_hang': $rowOrdered[] = $data['he_so_khong_hang'] ?? 0; break;
                case 'khoi_luong_van_chuyen_t': $rowOrdered[] = $data['khoi_luong_van_chuyen_t'] ?? 0; break;
                case 'khoi_luong_luan_chuyen': $rowOrdered[] = $data['khoi_luong_luan_chuyen'] ?? 0; break;
                case 'dau_tinh_toan_lit': $rowOrdered[] = $data['dau_tinh_toan_lit'] ?? 0; break;
                case 'cap_them': $rowOrdered[] = !empty($data['cap_them']) ? 1 : 0; break;
                case 'doi_lenh': $rowOrdered[] = !empty($data['doi_lenh']) ? 1 : 0; break;
                case 'ly_do_cap_them': $rowOrdered[] = $data['ly_do_cap_them'] ?? ''; break;
                case 'so_luong_cap_them_lit': $rowOrdered[] = $data['so_luong_cap_them_lit'] ?? 0; break;
                case 'cay_xang_cap_them': $rowOrdered[] = $data['cay_xang_cap_them'] ?? ''; break;
                case 'ngay_di': $rowOrdered[] = $data['ngay_di'] ?? ''; break;
                case 'ngay_den': $rowOrdered[] = $data['ngay_den'] ?? ''; break;
                case 'ngay_do_xong': $rowOrdered[] = $data['ngay_do_xong'] ?? ''; break;
                case 'nhom_cu_ly': $rowOrdered[] = $data['nhom_cu_ly'] ?? ''; break;
                case 'loai_hang': $rowOrdered[] = $data['loai_hang'] ?? ''; break;
                case 'ghi_chu': $rowOrdered[] = $data['ghi_chu'] ?? ''; break;
                case 'thang_bao_cao': $rowOrdered[] = $data['thang_bao_cao'] ?? date('Y-m'); break;
                case 'created_at': $rowOrdered[] = $data['created_at'] ?? date('Y-m-d H:i:s'); break;
                default: $rowOrdered[] = ''; break;
            }
        }

        // Use file locking to prevent race conditions
        $handle = fopen(KET_QUA_FILE, 'a');
        if (!$handle) {
            throw new Exception('Không thể mở file lưu kết quả: ' . KET_QUA_FILE);
        }
        
        // Acquire exclusive lock
        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            throw new Exception('Không thể khóa file để ghi dữ liệu');
        }
        
        $ok = fputcsv($handle, $rowOrdered) !== false;
        
        // Release lock and close file
        flock($handle, LOCK_UN);
        fclose($handle);
        
        return $ok;
    }

    /**
     * Đọc tất cả bản ghi kèm chỉ số dòng (idx) sau header
     * @return array
     */
    public function docTatCa(): array {
        $this->ensureStorage();
        
        // Check file size before loading
        $fileInfo = $this->checkFileSize();
        if ($fileInfo['warning']) {
            error_log("WARNING: Large CSV file detected: " . $fileInfo['message']);
        }
        
        $rows = [];
        if (($handle = fopen(KET_QUA_FILE, 'r')) !== false) {
            $headers = fgetcsv($handle) ?: [];
            $i = 0;
            while (($data = fgetcsv($handle)) !== false) {
                $i++;
                // Skip malformed rows that don't match header length
                if (count($data) !== count($headers)) { continue; }
                $row = array_combine($headers, $data);
                if (!is_array($row)) { continue; }
                $row['___idx'] = $i; // chỉ số dòng sau header
                $rows[] = $row;
            }
            fclose($handle);
        }
        return $rows;
    }

    /**
     * Cập nhật một bản ghi theo chỉ số dòng (sau header) với file locking
     */
    public function capNhat(int $idx, array $data): bool {
        $this->ensureStorage();

        // Use file locking to prevent race conditions
        $handle = fopen(KET_QUA_FILE, 'r+');
        if (!$handle) {
            return false;
        }
        
        // Acquire exclusive lock
        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            return false;
        }
        
        $lines = [];
        while (($line = fgets($handle)) !== false) {
            $lines[] = rtrim($line, "\r\n");
        }
        
        if (count($lines) < 2 || !isset($lines[$idx])) {
            flock($handle, LOCK_UN);
            fclose($handle);
            return false;
        }

        $headers = str_getcsv($lines[0]);
        
        // Đảm bảo theo thứ tự header
        $row = [];
        foreach ($headers as $h) {
            $row[] = $data[$h] ?? '';
        }

        $lines[$idx] = $this->toCsvLine($row);
        
        // Rewind and truncate file
        rewind($handle);
        ftruncate($handle, 0);
        
        // Write all lines back
        foreach ($lines as $line) {
            fwrite($handle, $line . PHP_EOL);
        }
        
        // Release lock and close file
        flock($handle, LOCK_UN);
        fclose($handle);
        
        return true;
    }

    /**
     * Xóa một bản ghi theo chỉ số dòng (sau header) với file locking
     */
    public function xoa(int $idx): bool {
        $this->ensureStorage();
        
        // Use file locking to prevent race conditions
        $handle = fopen(KET_QUA_FILE, 'r+');
        if (!$handle) {
            return false;
        }
        
        // Acquire exclusive lock
        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            return false;
        }
        
        $lines = [];
        while (($line = fgets($handle)) !== false) {
            $lines[] = rtrim($line, "\r\n");
        }
        
        if (count($lines) < 2 || !isset($lines[$idx])) {
            flock($handle, LOCK_UN);
            fclose($handle);
            return false;
        }
        
        // Remove the line (idx because ___idx starts from 1, but lines array starts from 0)
        // ___idx=1 corresponds to $lines[1] (first data row after header)
        array_splice($lines, $idx, 1);
        
        // Rewind and truncate file
        rewind($handle);
        ftruncate($handle, 0);
        
        // Write all lines back
        foreach ($lines as $line) {
            fwrite($handle, $line . PHP_EOL);
        }
        
        // Release lock and close file
        flock($handle, LOCK_UN);
        fclose($handle);
        
        return true;
    }

    private function toCsvLine(array $fields): string {
        // Build a CSV line safely
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, $fields);
        rewind($fh);
        $line = stream_get_contents($fh);
        fclose($fh);
        return rtrim($line, "\r\n");
    }

    /**
     * Kiểm tra kích thước file và cảnh báo nếu quá lớn
     * @return array Thông tin về file size và cảnh báo
     */
    public function checkFileSize(): array {
        if (!file_exists(KET_QUA_FILE)) {
            return ['size' => 0, 'warning' => false, 'message' => ''];
        }
        
        $fileSize = filesize(KET_QUA_FILE);
        $sizeInMB = $fileSize / (1024 * 1024);
        
        $warning = false;
        $message = '';
        
        if ($sizeInMB > 50) { // Cảnh báo nếu file > 50MB
            $warning = true;
            $message = "File dữ liệu đã lớn hơn 50MB ({$sizeInMB}MB). Cân nhắc chuyển sang database để tối ưu hiệu suất.";
        } elseif ($sizeInMB > 10) { // Thông báo nếu file > 10MB
            $message = "File dữ liệu hiện tại: {$sizeInMB}MB. Hệ thống vẫn hoạt động bình thường.";
        }
        
        return [
            'size' => $fileSize,
            'size_mb' => round($sizeInMB, 2),
            'warning' => $warning,
            'message' => $message
        ];
    }

    /**
     * Lấy tất cả kết quả tính toán từ CSV
     */
    public function layTatCaKetQua(): array {
        $this->ensureStorage();
        $results = [];
        
        if (($handle = fopen(KET_QUA_FILE, 'r')) !== false) {
            $headers = fgetcsv($handle) ?: [];
            $rowIndex = 1; // 1-based index for the first data row after header
            while (($data = fgetcsv($handle)) !== false) {
                if (count($data) !== count($headers)) continue; // Skip malformed rows
                $row = array_combine($headers, $data);
                if (is_array($row)) {
                    // Gắn kèm chỉ số dòng trong file để phục vụ việc giữ nguyên thứ tự lịch sử
                    $row['__row_index'] = $rowIndex;
                    $results[] = $row;
                }
                $rowIndex++;
            }
            fclose($handle);
        }
        
        return $results;
    }
}
?>


