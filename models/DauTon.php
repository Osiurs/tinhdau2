<?php
/**
 * Class DauTon - Quản lý dầu tồn kho theo tàu
 * Lưu trữ giao dịch cấp thêm và tinh chỉnh trong CSV riêng, tính tiêu hao dựa trên kết quả tính toán (ket_qua_tinh_toan.csv)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/LuuKetQua.php';
require_once __DIR__ . '/HeSoTau.php';
require_once __DIR__ . '/CayXang.php';

class DauTon {
    private $filePath;
    private $ketQua;
    private $heSoTau;
    private $cayXang;
    private function logDebug(string $event, array $data = []): void {
        $payload = date('Y-m-d H:i:s') . ' [' . $event . '] ' . json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        $logFile = __DIR__ . '/../data/dau_ton_operations.log';
        @file_put_contents($logFile, $payload, FILE_APPEND);
    }

    private array $headers = [];

    public function __construct() {
        $this->filePath = __DIR__ . '/../data/dau_ton.csv';
        $this->ketQua = new LuuKetQua();
        $this->heSoTau = new HeSoTau();
        $this->cayXang = new CayXang();
        $this->ensureStorage();
    }

    /**
     * Đảm bảo file CSV tồn tại và có header
     */
    private function ensureStorage(): void {
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        if (!file_exists($this->filePath)) {
            $handle = fopen($this->filePath, 'w');
            if ($handle) {
                fputcsv($handle, [
                    'id',
                    'ten_phuong_tien',
                    'loai',              // cap_them | tinh_chinh
                    'ngay',              // yyyy-mm-dd
                    'so_luong_lit',      // số dương cho cấp thêm, âm/dương cho tinh chỉnh
                    'cay_xang',          // nơi lấy dầu (nếu là cấp thêm)
                    'ly_do',
                    'transfer_pair_id',  // UUID ghép cặp hai dòng chuyển dầu
                    'created_at'
                ]);
                fclose($handle);
            }
        }

        // Migration: add 'id' column if missing (at the beginning)
        $mh_id = fopen($this->filePath, 'r');
        if ($mh_id) {
            $headers_id = fgetcsv($mh_id) ?: [];
            if (!in_array('id', $headers_id, true)) {
                $rows_id = [];
                while (($data = fgetcsv($mh_id)) !== false) { $rows_id[] = $data; }
                fclose($mh_id);

                array_unshift($headers_id, 'id');
                foreach ($rows_id as &$r_id) {
                    $uuid = function_exists('td2_generate_uuid_v4') ? td2_generate_uuid_v4() : uniqid('row_', true);
                    array_unshift($r_id, $uuid);
                }
                unset($r_id);

                $wh_id = fopen($this->filePath, 'w');
                if ($wh_id) {
                    fputcsv($wh_id, $headers_id);
                    foreach ($rows_id as $row) { fputcsv($wh_id, $row); }
                    fclose($wh_id);
                }
            } else {
                fclose($mh_id);
            }
        }

        // Migration: add 'cay_xang' column if missing (before 'ly_do')
        $mh = fopen($this->filePath, 'r');
        if ($mh) {
            $headers = fgetcsv($mh) ?: [];
            if (!in_array('cay_xang', $headers, true)) {
                $rows = [];
                while (($data = fgetcsv($mh)) !== false) { $rows[] = $data; }
                fclose($mh);

                $posLyDo = array_search('ly_do', $headers, true);
                $insertPos = ($posLyDo === false) ? count($headers) : $posLyDo;
                array_splice($headers, $insertPos, 0, 'cay_xang');
                foreach ($rows as &$r) { array_splice($r, $insertPos, 0, ''); }
                unset($r);

                $wh = fopen($this->filePath, 'w');
                if ($wh) {
                    fputcsv($wh, $headers);
                    foreach ($rows as $row) { fputcsv($wh, $row); }
                    fclose($wh);
                }
            } else {
                fclose($mh);
            }
        }

        // Migration: add 'transfer_pair_id' column if missing (before 'created_at')
        $mh2 = fopen($this->filePath, 'r');
        if ($mh2) {
            $headers2 = fgetcsv($mh2) ?: [];
            if (!in_array('transfer_pair_id', $headers2, true)) {
                $rows2 = [];
                while (($data = fgetcsv($mh2)) !== false) { $rows2[] = $data; }
                fclose($mh2);

                $posCreated = array_search('created_at', $headers2, true);
                $insertPos2 = ($posCreated === false) ? count($headers2) : $posCreated;
                array_splice($headers2, $insertPos2, 0, 'transfer_pair_id');
                foreach ($rows2 as &$r2) { array_splice($r2, $insertPos2, 0, ''); }
                unset($r2);

                $wh2 = fopen($this->filePath, 'w');
                if ($wh2) {
                    fputcsv($wh2, $headers2);
                    foreach ($rows2 as $row) { fputcsv($wh2, $row); }
                    fclose($wh2);
                }
            } else {
                fclose($mh2);
            }
        }
    }

    private function readAllRows(): array {
        $rows = [];
        $this->headers = [];
        if (($handle = fopen($this->filePath, 'r')) !== false) {
            $this->headers = fgetcsv($handle) ?: [];
            $numH = count($this->headers);
            while (($data = fgetcsv($handle)) !== false) {
                if ($numH > 0) {
                    if (count($data) < $numH) {
                        $data = array_pad($data, $numH, '');
                    } elseif (count($data) > $numH) {
                        $data = array_slice($data, 0, $numH);
                    }
                }
                $rowAssoc = $numH ? @array_combine($this->headers, $data) : $data;
                if (!is_array($rowAssoc)) { continue; }
                $rowAssoc['id'] = trim((string)($rowAssoc['id'] ?? ($data[0] ?? '')));
                $rowAssoc['loai'] = isset($rowAssoc['loai']) ? trim(strtolower((string)$rowAssoc['loai'])) : '';
                if ($rowAssoc['id'] === '') { continue; }
                $rows[] = $rowAssoc;
            }
            fclose($handle);
        }
        $this->logDebug('readAllRows', ['count' => count($rows)]);
        return $rows;
    }

    private function writeAllRows(array $headers, array $rows): bool {
        $handle = fopen($this->filePath, 'w');
        if (!$handle) {
            $this->logDebug('writeAllRows_failed_open');
            return false;
        }
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            if (!is_array($row)) { continue; }
            $ordered = [];
            foreach ($headers as $h) {
                $ordered[] = $row[$h] ?? '';
            }
            fputcsv($handle, $ordered);
        }
        fclose($handle);
        $this->logDebug('writeAllRows_success', ['count' => count($rows)]);
        return true;
    }

    private function hasEntryById(string $id): bool {
        $id = trim($id);
        if ($id === '') { return false; }
        foreach ($this->readAllRows() as $row) {
            if (($row['id'] ?? '') === $id) { return true; }
        }
        return false;
    }

    public function entryExists(string $id): bool {
        return $this->hasEntryById($id);
    }

    /**
     * Thêm giao dịch cấp thêm dầu
     */
    public function themCapThem(string $tenTau, string $ngayCap, $soLuongLit, string $lyDo = '', string $cayXang = ''): bool {
        $this->logDebug('themCapThem_call', [
            'ten_tau' => $tenTau,
            'ngay' => $ngayCap,
            'so_luong' => $soLuongLit,
            'ly_do' => $lyDo,
            'cay_xang' => $cayXang,
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),
        ]);
        $tenTau = trim($tenTau);
        if (!$this->heSoTau->isTauExists($tenTau)) {
            throw new Exception(ERROR_MESSAGES['tau_not_found'] . ": $tenTau");
        }
        $so = is_numeric($soLuongLit) ? (float)$soLuongLit : null;
        if ($so === null || $so < 0) {
            throw new Exception('Số lượng cấp thêm phải là số không âm');
        }
        $ngay = parse_date_vn($ngayCap);
        if (!$ngay) { throw new Exception('Ngày cấp không hợp lệ'); }
        if ($cayXang !== '' && !$this->cayXang->exists($cayXang)) {
            throw new Exception('Cây xăng không có trong danh mục. Vui lòng thêm tại Quản lý cây xăng.');
        }

		// Rule: Trong một ngày với mỗi một chuyến chỉ được lấy dầu từ một cây xăng 1 lần
		// Thực thi: Không cho phép nhiều hơn 1 bản ghi "cấp thêm" trong cùng ngày cho cùng một tàu
		foreach ($this->getLichSuGiaoDich($tenTau) as $gd) {
			if (($gd['loai'] ?? '') === 'cap_them' && ($gd['ngay'] ?? '') === $ngay) {
				throw new Exception('Mỗi chuyến trong cùng một ngày chỉ được lấy dầu từ một cây xăng một lần');
			}
		}
        // Ghi vào nhật ký dầu tồn riêng
        $okLedger = $this->appendRow([
            'ten_phuong_tien' => $tenTau,
            'loai' => 'cap_them',
            'ngay' => $ngay,
            'so_luong_lit' => $so,
            'cay_xang' => $cayXang,
            'ly_do' => $lyDo,
        ]);
        return $okLedger;
    }

    /**
     * Thêm giao dịch tinh chỉnh (có thể âm hoặc dương)
     */
    public function themTinhChinh(string $tenTau, string $ngay, $soLuongLit, string $lyDo = '', array $extra = []): bool {
        $tenTau = trim($tenTau);
        if (!$this->heSoTau->isTauExists($tenTau)) {
            throw new Exception(ERROR_MESSAGES['tau_not_found'] . ": $tenTau");
        }
        if (!is_numeric($soLuongLit)) {
            throw new Exception('Số lượng tinh chỉnh phải là số (có thể âm)');
        }
        $ngayIso = parse_date_vn($ngay);
        if (!$ngayIso) { throw new Exception('Ngày tinh chỉnh không hợp lệ'); }
        return $this->appendRow([
            'ten_phuong_tien' => $tenTau,
            'loai' => 'tinh_chinh',
            'ngay' => $ngayIso,
            'so_luong_lit' => (float)$soLuongLit,
            'cay_xang' => '',
            'ly_do' => $lyDo,
            'transfer_pair_id' => $extra['transfer_pair_id'] ?? '',
        ]);
    }

    /**
     * Chuyển dầu giữa 2 tàu tại một ngày cụ thể.
     * Ghi 2 bút toán tinh_chinh: âm ở tàu nguồn, dương ở tàu đích, cùng ngày và lý do.
     * Có kiểm tra số dư tàu nguồn đến ngày đó đủ để trừ (>= amount).
     */
    public function chuyenDau(string $tauNguon, string $tauDich, string $ngay, $soLuongLit, string $lyDo = 'Chuyển dầu'): bool {
        $tauNguon = trim($tauNguon);
        $tauDich = trim($tauDich);
        if ($tauNguon === '' || $tauDich === '') { throw new Exception('Tàu nguồn/đích không được trống'); }
        if ($tauNguon === $tauDich) { throw new Exception('Tàu nguồn và tàu đích không được trùng nhau'); }
        if (!$this->heSoTau->isTauExists($tauNguon)) { throw new Exception(ERROR_MESSAGES['tau_not_found'] . ": $tauNguon"); }
        if (!$this->heSoTau->isTauExists($tauDich)) { throw new Exception(ERROR_MESSAGES['tau_not_found'] . ": $tauDich"); }
        if (!is_numeric($soLuongLit) || (float)$soLuongLit <= 0) { throw new Exception('Số lượng chuyển phải là số > 0'); }
        $ngayIso = parse_date_vn($ngay);
        if (!$ngayIso) { throw new Exception('Ngày chuyển không hợp lệ'); }

        $amount = (float)$soLuongLit;
        // Bỏ kiểm tra số dư - cho phép chuyển dầu tự do
        // $soDuNguon = $this->tinhSoDu($tauNguon, $ngayIso);
        // if ($soDuNguon < $amount) {
        //     throw new Exception("Số dư tàu $tauNguon đến ngày $ngayIso không đủ để chuyển ($soDuNguon < $amount)");
        // }

        // Generate transfer pair id for both rows
        $pairId = function_exists('td2_generate_uuid_v4') ? td2_generate_uuid_v4() : (uniqid('pair_', true));
        
        // Tạo lý do với format chuẩn
        $lyDoNguon = ($lyDo !== '' ? $lyDo : 'Chuyển dầu') . " → chuyển sang $tauDich";
        $lyDoDich = ($lyDo !== '' ? $lyDo : 'Chuyển dầu') . " ← nhận từ $tauNguon";
        
        // Ghi bút toán: tàu nguồn (-amount)
        $ok1 = $this->themTinhChinh($tauNguon, $ngayIso, -$amount, $lyDoNguon, ['transfer_pair_id' => $pairId]);
        if (!$ok1) {
            throw new Exception('Không thể ghi bút toán tàu nguồn');
        }
        
        // Ghi bút toán: tàu đích (+amount)
        $ok2 = $this->themTinhChinh($tauDich, $ngayIso, +$amount, $lyDoDich, ['transfer_pair_id' => $pairId]);
        if (!$ok2) {
            // Nếu ghi tàu đích thất bại, cần rollback tàu nguồn (xóa bản ghi vừa tạo)
            // Tìm và xóa bản ghi tàu nguồn vừa tạo
            try {
                $found = td2_find_transfer_rows_by_pair_id($pairId);
                if (isset($found['indexes']['src'])) {
                    td2_delete_csv_rows($this->filePath, [$found['indexes']['src']]);
                }
            } catch (Exception $e) {
                // Log lỗi rollback nhưng không throw
                log_error('chuyenDau_rollback', $e->getMessage());
            }
            throw new Exception('Không thể ghi bút toán tàu đích');
        }
        
        return true;
    }

    /**
     * Ghi thêm một dòng vào CSV (theo đúng thứ tự header)
     */
    private function appendRow(array $data): bool {
        $rh = fopen($this->filePath, 'r');
        if (!$rh) { throw new Exception('Không thể mở file dầu tồn'); }
        $headers = fgetcsv($rh) ?: [];
        fclose($rh);

        $rowOrdered = [];
        foreach ($headers as $h) {
            switch ($h) {
                case 'id': $rowOrdered[] = function_exists('td2_generate_uuid_v4') ? td2_generate_uuid_v4() : uniqid('row_', true); break;
                case 'ten_phuong_tien': $rowOrdered[] = $data['ten_phuong_tien'] ?? ''; break;
                case 'loai': $rowOrdered[] = $data['loai'] ?? ''; break;
                case 'ngay': $rowOrdered[] = $data['ngay'] ?? ''; break;
                case 'so_luong_lit': $rowOrdered[] = $data['so_luong_lit'] ?? 0; break;
                case 'cay_xang': $rowOrdered[] = $data['cay_xang'] ?? ''; break;
                case 'ly_do': $rowOrdered[] = $data['ly_do'] ?? ''; break;
                case 'transfer_pair_id': $rowOrdered[] = $data['transfer_pair_id'] ?? ''; break;
                case 'created_at': $rowOrdered[] = date('Y-m-d H:i:s'); break;
                default: $rowOrdered[] = '';
            }
        }
        $this->logDebug('appendRow', ['row' => $rowOrdered, 'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)]);

        $wh = fopen($this->filePath, 'a');
        if (!$wh) { throw new Exception('Không thể ghi file dầu tồn'); }
        $ok = fputcsv($wh, $rowOrdered) !== false;
        fclose($wh);
        return $ok;
    }

    /**
     * Lấy lịch sử giao dịch dầu tồn (cấp thêm, tinh chỉnh) theo tàu
     */
    public function getLichSuGiaoDich(string $tenTau): array {
        $this->ensureStorage();
        $rows = [];
        if (($h = fopen($this->filePath, 'r')) !== false) {
            $headers = fgetcsv($h) ?: [];
            $numH = count($headers);
            while (($data = fgetcsv($h)) !== false) {
                // Chuẩn hóa độ dài để tránh lỗi array_combine
                if (count($data) < $numH) {
                    $data = array_pad($data, $numH, '');
                } elseif (count($data) > $numH) {
                    $data = array_slice($data, 0, $numH);
                }
                $row = @array_combine($headers, $data);
                if (!is_array($row)) { continue; }
                
                
                if ((($row['ten_phuong_tien'] ?? '')) === $tenTau) {
                    $rows[] = $row;
                }
            }
            fclose($h);
        }
        // Sắp xếp theo ngày tăng dần
        usort($rows, function($a, $b) {
            return strcmp($a['ngay'] ?? '', $b['ngay'] ?? '');
        });
        return $rows;
    }

    /**
     * Tính số dư dầu tới một ngày (mặc định đến hôm nay)
     * Số dư = (cấp thêm + tinh chỉnh) - tiêu hao (theo ngày dỡ hàng)
     */
    public function tinhSoDu(string $tenTau, ?string $denNgay = null, bool $strictDoXong = false): float {
        // denNgay có thể đã là ISO (yyyy-mm-dd) hoặc dạng dd/mm/yyyy
        if ($denNgay) {
            $parsed = parse_date_vn($denNgay);
            $denIso = $parsed ?: $denNgay; // nếu parse thất bại, dùng nguyên giá trị (giả sử ISO)
        } else {
            $denIso = date('Y-m-d');
        }
        // 1) Tổng cấp thêm + tinh chỉnh
        $tongNhap = 0.0;
        foreach ($this->getLichSuGiaoDich($tenTau) as $gd) {
            $ngay = $gd['ngay'] ?? '';
            if ($ngay && strcmp($ngay, $denIso) <= 0) {
                $tongNhap += floor((float)($gd['so_luong_lit'] ?? 0));
            }
        }

        // 2) Tổng tiêu hao từ kết quả tính toán theo ngày dỡ hàng <= denIso
        $tongTieuHao = 0.0;
        foreach ($this->ketQua->docTatCa() as $row) {
            if (($row['ten_phuong_tien'] ?? '') !== $tenTau) continue;
            // Xác định ngày tính tiêu hao
            if ($strictDoXong) {
                $ngayDoXong = $row['ngay_do_xong'] ?? '';
                if (!$ngayDoXong) continue; // chế độ nghiêm ngặt: chỉ tính khi có ngày dỡ xong
                $ngayIso = parse_date_vn($ngayDoXong);
            } else {
                // Sử dụng helper method để xác định ngày
                $ngayIso = $this->getEntryDate($row);
                if ($ngayIso === '') continue;
            }
            if (strcmp($ngayIso, $denIso) <= 0) {
                $isCapThemCalc = intval($row['cap_them'] ?? 0) === 1;
                if ($isCapThemCalc) {
                    // Đối với cap_them, chỉ tính so_luong_cap_them_lit và làm tròn
                    $tongTieuHao += floor((float)($row['so_luong_cap_them_lit'] ?? 0));
                } else {
                    // Đối với tiêu hao thông thường, tính dau_tinh_toan_lit và làm tròn
                    $tongTieuHao += floor((float)($row['dau_tinh_toan_lit'] ?? 0));
                }
            }
        }

        return floor($tongNhap - $tongTieuHao);
    }

    /**
     * Helper method to determine the date for a fuel consumption entry
     * Priority: ngay_do_xong → ngay_den → ngay_di → created_at
     * @param array $row The data row from ket_qua_tinh_toan.csv
     * @return string ISO date string or empty string
     */
    private function getEntryDate(array $row): string {
        // Priority 1: ngay_do_xong
        $ngayDoXong = $row['ngay_do_xong'] ?? '';
        if ($ngayDoXong) {
            $ngayIso = parse_date_vn($ngayDoXong);
            if ($ngayIso) return $ngayIso;
        }
        
        // Priority 2: ngay_den
        $ngayDen = $row['ngay_den'] ?? '';
        if ($ngayDen) {
            $ngayIso = parse_date_vn($ngayDen);
            if ($ngayIso) return $ngayIso;
        }
        
        // Priority 3: ngay_di
        $ngayDi = $row['ngay_di'] ?? '';
        if ($ngayDi) {
            $ngayIso = parse_date_vn($ngayDi);
            if ($ngayIso) return $ngayIso;
        }
        
        // Priority 4: created_at
        $createdAt = $row['created_at'] ?? '';
        if ($createdAt) {
            return substr($createdAt, 0, 10); // Extract YYYY-MM-DD from datetime
        }
        
        return '';
    }

    /**
     * Lấy nhật ký hiển thị (cả giao dịch và tiêu hao) cho UI
     */
    public function getNhatKyHienThi(string $tenTau): array {
        $entries = [];
        // Giao dịch nhập: cap_them / tinh_chinh
        foreach ($this->getLichSuGiaoDich($tenTau) as $gd) {
            $rawLyDo = (string)($gd['ly_do'] ?? '');
            log_error('nhatky_cap_them_source', ['ten_tau' => $tenTau, 'row' => $gd]);
            $displayType = $gd['loai']; // giữ nguyên loại gốc để các báo cáo khác không bị ảnh hưởng
            $display = $gd['loai'] === 'cap_them' ? 'Lấy dầu từ cây xăng' : 'Tinh chỉnh';
            $transferMeta = null;
            // Nhận diện chuyển dầu qua template của chuyenDau(): "→ chuyển sang X" hoặc "← nhận từ Y"
            if (strpos($rawLyDo, 'chuyển sang') !== false || strpos($rawLyDo, 'nhận từ') !== false) {
                $displayType = 'chuyen';
                // Parse other ship
                if (preg_match('/chuyển sang\s+([^\s]+)/u', $rawLyDo, $m)) {
                    $transferMeta = ['dir' => 'out', 'other_ship' => $m[1]];
                    $display = 'Chuyển dầu → ' . $transferMeta['other_ship'];
                } elseif (preg_match('/nhận từ\s+([^\s]+)/u', $rawLyDo, $m)) {
                    $transferMeta = ['dir' => 'in', 'other_ship' => $m[1]];
                    $display = 'Nhận dầu ← ' . $transferMeta['other_ship'];
                } else {
                    $display = 'Chuyển dầu';
                }
            }
            $entries[] = [
                'id'   => $gd['id'] ?? '',
                'ngay' => $gd['ngay'],
                'loai' => $gd['loai'],
                'loai_hien_thi' => $displayType,
                'so_luong' => (float)$gd['so_luong_lit'],
                'ly_do' => $rawLyDo,
                'cay_xang' => $gd['cay_xang'] ?? '',
                'mo_ta' => $display,
                'transfer' => $transferMeta,
                'transfer_pair_id' => $gd['transfer_pair_id'] ?? ''
            ];
        }
        // Tiêu hao: từ ket_qua (tất cả các dòng tiêu hao, bao gồm cả chưa dỡ xong và cấp thêm)
        foreach ($this->ketQua->docTatCa() as $row) {
            if (($row['ten_phuong_tien'] ?? '') !== $tenTau) continue;
            
            // Sử dụng helper method để xác định ngày
            $ngayIso = $this->getEntryDate($row);
            if ($ngayIso === '') continue;
            
            $isCapThemCalc = intval($row['cap_them'] ?? 0) === 1;
            
            // Xử lý cấp thêm (múc dầu trong tàu ra) - ưu tiên cao nhất
            if ($isCapThemCalc) {
                $soLit = (float)($row['so_luong_cap_them_lit'] ?? 0);
                if ($soLit <= 0) continue;
                $lyDo = trim((string)($row['ly_do_cap_them'] ?? 'Cấp thêm'));
                $entries[] = [
                    'ngay' => $ngayIso,
                    'loai' => 'tieu_hao',
                    'so_luong' => - floor($soLit),
                    'ly_do' => $lyDo,
                    'mo_ta' => 'Tiêu hao (cấp thêm từ trang tính toán)',
                ];
                
            }
            // Xử lý tiêu hao thông thường (có quãng đường hoặc có tiêu hao dầu) - chỉ khi không phải cap_them
            elseif (!$isCapThemCalc) {
                $cuLyCo = (float)($row['cu_ly_co_hang_km'] ?? 0);
                $cuLyKhong = (float)($row['cu_ly_khong_hang_km'] ?? 0);
                $entryDauRaw = (float)($row['dau_tinh_toan_lit'] ?? 0);
                $hasDistance = $cuLyCo > 0 || $cuLyKhong > 0;
                $hasFuelConsumption = $entryDauRaw > 0;

                // Hiển thị cả chuyến có quãng đường hoặc có tiêu hao (kể cả khi làm tròn xuống = 0)
                if ($hasDistance || $hasFuelConsumption) {
                    $diemDi = $row['diem_di'] ?? '';
                    $diemDuKien = $row['diem_du_kien'] ?? '';
                    $diemDen = $row['diem_den'] ?? '';
                    $doiLenh = !empty($row['doi_lenh']);
                    $doiLenhTuyen = $row['doi_lenh_tuyen'] ?? '';
                    if ($doiLenh && $diemDuKien) {
                        $routeParts = [];
                        if ($diemDi !== '') { $routeParts[] = $diemDi; }
                        if ($diemDuKien !== '') { $routeParts[] = $diemDuKien . ' (đổi lệnh)'; }
                        $decoded = [];
                        if ($doiLenhTuyen) {
                            $tmp = json_decode($doiLenhTuyen, true);
                            if (is_array($tmp)) { $decoded = $tmp; }
                        }
                        if (!empty($decoded)) {
                            foreach ($decoded as $entry) {
                                $label = isset($entry['point']) ? trim((string)$entry['point']) : '';
                                if ($label === '') { continue; }
                                $suffix = [];
                                if (!empty($entry['reason'])) { $suffix[] = trim((string)$entry['reason']); }
                                if (!empty($entry['note'])) { $suffix[] = trim((string)$entry['note']); }
                                if (!empty($suffix)) {
                                    $label .= ' (' . implode(' – ', $suffix) . ')';
                                }
                                $routeParts[] = $label;
                            }
                        } else {
                            if ($diemDen !== '') { $routeParts[] = $diemDen; }
                        }
                        $route = implode(' → ', array_filter($routeParts, function($part){ return trim((string)$part) !== ''; }));
                    } else {
                        $route = $diemDi . ' → ' . $diemDen;
                    }
                    $tongKm = $cuLyCo + $cuLyKhong;
                    $khoiLuong = (float)($row['khoi_luong_van_chuyen_t'] ?? 0);
                    
                    // Xác định loại mô tả dựa trên ngày dỡ xong
                    $ngayDoXong = $row['ngay_do_xong'] ?? '';
                    $moTa = $ngayDoXong ? 'Tiêu hao (ngày dỡ hàng)' : 'Tiêu hao (chuyến chưa dỡ xong)';
                    
                    $meta = null;
                    if ($ngayDoXong) {
                        $meta = [
                            'ten_tau' => $row['ten_phuong_tien'] ?? '',
                            'route' => $route,
                            'diem_di' => $diemDi,
                            'diem_du_kien' => $diemDuKien,
                            'diem_den' => $route,
                            'doi_lenh' => $doiLenh ? 1 : 0,
                            'khoang_cach_km' => $tongKm,
                            'cu_ly_co_hang_km' => $cuLyCo,
                            'cu_ly_khong_hang_km' => $cuLyKhong,
                            'khoi_luong_tan' => $khoiLuong,
                            'ngay_di' => $row['ngay_di'] ?? '',
                            'ngay_den' => $row['ngay_den'] ?? '',
                            'ngay_do_xong' => $ngayIso,
                            'dau_lit' => $entryDauRaw,
                        ];
                    }

                    $entries[] = [
                        'ngay' => $ngayIso,
                        'loai' => 'tieu_hao',
                        'so_luong' => - floor($entryDauRaw),
                        'ly_do' => $route,
                        'mo_ta' => $moTa,
                        'meta' => $meta
                    ];
                }
            }
        }
        // Sắp xếp theo ngày, cùng ngày ưu tiên nhập trước rồi tiêu hao
        usort($entries, function($a, $b) {
            $cmp = strcmp($a['ngay'], $b['ngay']);
            if ($cmp !== 0) return $cmp;
            // cap_them/tinh_chinh trước, tieu_hao sau
            $order = ['cap_them' => 0, 'tinh_chinh' => 1, 'tieu_hao' => 2];
            return ($order[$a['loai']] ?? 9) <=> ($order[$b['loai']] ?? 9);
        });

        // Tính số dư lũy kế cho hiển thị
        // Đảm bảo số dư cuối cùng khớp với số dư hiện tại từ tinhSoDu
        $soDu = 0.0;
        
        if (!empty($entries)) {
            // Lấy ngày đầu tiên và ngày cuối cùng
            $ngayDauTien = $entries[0]['ngay'] ?? '';
            $ngayCuoiCung = end($entries)['ngay'] ?? '';
            
            if ($ngayDauTien !== '') {
                // Tính số dư trước ngày đầu tiên (ngày trước đó 1 ngày)
                $ngayTruoc = date('Y-m-d', strtotime($ngayDauTien . ' -1 day'));
                $soDu = $this->tinhSoDu($tenTau, $ngayTruoc);
            }
            
            // Tính số dư lũy kế từ số dư ban đầu
            foreach ($entries as &$e) {
                $soDu += (float)$e['so_luong'];
                $e['so_du'] = floor($soDu); // Làm tròn xuống số dư về số nguyên
            }
            unset($e);
            
            // Đảm bảo số dư cuối cùng khớp với số dư hiện tại
            $soDuHienTai = $this->tinhSoDu($tenTau);
            $soDuCuoiCung = end($entries)['so_du'];
            
            // Nếu có sai số do làm tròn, điều chỉnh lại số dư cuối cùng
            if (abs($soDuCuoiCung - $soDuHienTai) > 1) {
                // Tính lại số dư từ số dư hiện tại ngược lại
                $soDu = $soDuHienTai;
                for ($i = count($entries) - 1; $i >= 0; $i--) {
                    $entries[$i]['so_du'] = floor($soDu);
                    $soDu -= (float)$entries[$i]['so_luong'];
                }
            }
        } else {
            // Nếu không có entries, số dư = số dư hiện tại
            $soDu = $this->tinhSoDu($tenTau);
        }

        // Chuyển ngày về dd/mm/yyyy cho UI
        foreach ($entries as &$e) {
            $e['ngay_vn'] = format_date_vn($e['ngay']);
        }
        unset($e);

        return $entries;
    }
    /**
     * Cập nhật tên cây xăng cho một mục nhập
     */
    public function updateCayXang(string $id, string $cayXang): bool {
        $id = trim($id);
        if (empty($id)) {
            throw new Exception('ID không được để trống.');
        }

        $rows = $this->readAllRows();
        $headers = $this->headers;

        $newRows = [];
        $found = false;
        foreach ($rows as $row) {
            if (($row['id'] ?? '') === $id) {
                $found = true;
                if (($row['loai'] ?? '') !== 'cap_them') {
                    throw new Exception('Chỉ có thể sửa cây xăng cho các lệnh cấp dầu.');
                }
                $row['cay_xang'] = $cayXang;
            }
            $newRows[] = $row;
        }

        if (!$found) {
            throw new Exception('Không tìm thấy mục nhập với ID đã cho.');
        }

        return $this->writeAllRows($headers, $newRows);
    }

    /**
     * Cập nhật thông tin lệnh tinh chỉnh
     */
    public function updateTinhChinh(string $id, string $ngay, $soLuongLit, string $lyDo = ''): bool {
        $id = trim($id);
        if (empty($id)) {
            throw new Exception('ID không được để trống.');
        }

        if (!is_numeric($soLuongLit)) {
            throw new Exception('Số lượng tinh chỉnh phải là số (có thể âm)');
        }

        $ngayIso = parse_date_vn($ngay);
        if (!$ngayIso) {
            throw new Exception('Ngày tinh chỉnh không hợp lệ');
        }

        $rows = $this->readAllRows();
        $headers = $this->headers;

        $newRows = [];
        $found = false;
        foreach ($rows as $row) {
            if (($row['id'] ?? '') === $id) {
                $found = true;
                if (($row['loai'] ?? '') !== 'tinh_chinh') {
                    throw new Exception('Chỉ có thể sửa thông tin cho các lệnh tinh chỉnh.');
                }
                // Không cho phép sửa lệnh chuyển dầu
                if (!empty($row['transfer_pair_id'])) {
                    throw new Exception('Không thể sửa lệnh chuyển dầu. Vui lòng sử dụng nút sửa chuyển dầu.');
                }
                $row['ngay'] = $ngayIso;
                $row['so_luong_lit'] = (float)$soLuongLit;
                $row['ly_do'] = $lyDo;
            }
            $newRows[] = $row;
        }

        if (!$found) {
            throw new Exception('Không tìm thấy mục nhập với ID đã cho.');
        }

        return $this->writeAllRows($headers, $newRows);
    }

    /**
     * Xóa một mục nhập khỏi file CSV dựa trên ID
     */
    public function deleteEntry(string $id): bool {
        $id = trim($id);
        if (empty($id)) {
            throw new Exception('ID không được để trống.');
        }

        $rows = $this->readAllRows();
        $headers = $this->headers;
        log_error('delete_entry_attempt', ['id' => $id, 'id_length' => strlen($id), 'row_count' => count($rows)]);

        $newRows = [];
        $found = false;
        foreach ($rows as $row) {
            $rowId = trim($row['id'] ?? '');

            // Log để debug so sánh
            if (!$found) {
                log_error('delete_entry_compare', [
                    'searching_for' => $id,
                    'current_row_id' => $rowId,
                    'match' => ($rowId === $id)
                ]);
            }

            if ($rowId === $id) {
                $found = true;
                $loai = $row['loai'] ?? '';
                // Cho phép xóa cap_them và tinh_chinh (nhưng không phải chuyển dầu)
                if ($loai !== 'cap_them' && $loai !== 'tinh_chinh') {
                    log_error('delete_entry_invalid_type', ['id' => $id, 'loai' => $loai]);
                    throw new Exception('Chỉ có thể xóa các lệnh cấp dầu hoặc tinh chỉnh.');
                }
                // Không cho phép xóa lệnh chuyển dầu
                if ($loai === 'tinh_chinh' && !empty($row['transfer_pair_id'])) {
                    log_error('delete_entry_is_transfer', ['id' => $id]);
                    throw new Exception('Không thể xóa lệnh chuyển dầu. Vui lòng sử dụng nút xóa chuyển dầu.');
                }
                log_error('delete_entry_found_match', ['id' => $id, 'loai' => $loai]);
                continue;
            }
            $newRows[] = $row;
        }

        if (!$found) {
            log_error('delete_entry_not_found', [
                'id' => $id,
                'id_hex' => bin2hex($id),
                'total_rows' => count($rows)
            ]);
            throw new Exception('Không tìm thấy mục nhập với ID đã cho.');
        }

        $result = $this->writeAllRows($headers, $newRows);
        $logPath = __DIR__ . '/../data/delete_debug.log';
        $append = '[' . date('Y-m-d H:i:s') . "] deleteEntry id=" . $id . " result=" . ($result ? '1' : '0') . " remaining=" . count($newRows) . PHP_EOL;
        @file_put_contents($logPath, $append, FILE_APPEND);
        if (!$result) {
            log_error('delete_entry_write_failed', ['id' => $id]);
            throw new Exception('Không thể ghi file dầu tồn.');
        }

        log_error('delete_entry_success', ['id' => $id, 'remaining_rows' => count($newRows)]);
        return true;
    }


}
?>


