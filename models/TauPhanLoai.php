<?php
require_once __DIR__ . '/../config/database.php';

class TauPhanLoai {
    private $filePath;

    public function __construct() {
        $this->filePath = TAU_PHAN_LOAI_FILE;
        $this->ensureStorage();
    }

    private function ensureStorage() {
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        if (!file_exists($this->filePath)) {
            $fh = fopen($this->filePath, 'w');
            if ($fh) {
                // Add default headers including so_dang_ky for new installs
                fputcsv($fh, ['ten_tau', 'phan_loai', 'so_dang_ky']);
                fclose($fh);
            }
        }
        // Migration: ensure header has so_dang_ky column
        $mh = fopen($this->filePath, 'r');
        if ($mh) {
            $headers = fgetcsv($mh) ?: [];
            $rows = [];
            while (($r = fgetcsv($mh)) !== false) { $rows[] = $r; }
            fclose($mh);
            if (!in_array('so_dang_ky', $headers, true)) {
                $headers[] = 'so_dang_ky';
                foreach ($rows as &$r) { $r[] = ''; }
                unset($r);
                $wh = fopen($this->filePath, 'w');
                if ($wh) {
                    fputcsv($wh, $headers);
                    foreach ($rows as $row) { fputcsv($wh, $row); }
                    fclose($wh);
                }
            }
        }
    }

    /**
     * Lấy phân loại của một tàu
     * @return string|null cong_ty|thue_ngoai|null
     */
    public function getPhanLoai(string $tenTau): ?string {
        if (!file_exists($this->filePath)) return null;
        $fh = fopen($this->filePath, 'r');
        if (!$fh) return null;
        $headers = fgetcsv($fh) ?: [];
        $map = array_flip($headers);
        while (($row = fgetcsv($fh)) !== false) {
            if (count($row) !== count($headers)) continue;
            $rec = array_combine($headers, $row);
            if (!$rec) continue;
            if (isset($rec['ten_tau']) && $rec['ten_tau'] === $tenTau) {
                fclose($fh);
                $val = $rec['phan_loai'] ?? '';
                return $val !== '' ? $val : null;
            }
        }
        fclose($fh);
        return null;
    }

    /**
     * Lấy số đăng ký của một tàu
     */
    public function getSoDangKy(string $tenTau): ?string {
        if (!file_exists($this->filePath)) return null;
        $fh = fopen($this->filePath, 'r');
        if (!$fh) return null;
        $headers = fgetcsv($fh) ?: [];
        while (($row = fgetcsv($fh)) !== false) {
            if (count($row) !== count($headers)) continue;
            $rec = array_combine($headers, $row);
            if (!$rec) continue;
            if (($rec['ten_tau'] ?? '') === $tenTau) {
                fclose($fh);
                $val = trim((string)($rec['so_dang_ky'] ?? ''));
                return $val !== '' ? $val : null;
            }
        }
        fclose($fh);
        return null;
    }

    /**
     * Lưu/cập nhật phân loại cho tàu
     */
    public function setPhanLoai(string $tenTau, string $phanLoai, string $soDangKy = ''): bool {
        $phanLoai = in_array($phanLoai, ['cong_ty', 'thue_ngoai'], true) ? $phanLoai : 'cong_ty';
        $this->ensureStorage();
        $fh = fopen($this->filePath, 'r');
        $rows = [];
        $headers = [];
        if ($fh) {
            $headers = fgetcsv($fh) ?: [];
            while (($row = fgetcsv($fh)) !== false) { $rows[] = $row; }
            fclose($fh);
        }
        if (empty($headers)) { $headers = ['ten_tau','phan_loai','so_dang_ky']; }

        $updated = false;
        foreach ($rows as &$row) {
            // Ensure row size
            if (count($row) < count($headers)) { $row = array_pad($row, count($headers), ''); }
            if ($row[0] === $tenTau) {
                $row[1] = $phanLoai;
                // Update optional registration if provided (can be empty to keep existing)
                if ($soDangKy !== '') { $row[2] = $soDangKy; }
                $updated = true;
                break;
            }
        }
        if (!$updated) {
            $rows[] = [$tenTau, $phanLoai, $soDangKy];
        }

        $wh = fopen($this->filePath, 'w');
        if (!$wh) return false;
        fputcsv($wh, $headers);
        foreach ($rows as $r) { fputcsv($wh, $r); }
        fclose($wh);
        return true;
    }

    /**
     * Lấy toàn bộ mapping tên tàu => phân loại
     */
    public function getAll(): array {
        $result = [];
        if (!file_exists($this->filePath)) return $result;
        $fh = fopen($this->filePath, 'r');
        if (!$fh) return $result;
        $headers = fgetcsv($fh) ?: [];
        while (($row = fgetcsv($fh)) !== false) {
            if (count($row) !== count($headers)) continue;
            $rec = array_combine($headers, $row);
            if (!$rec) continue;
            $ten = $rec['ten_tau'] ?? '';
            $pl = $rec['phan_loai'] ?? '';
            if ($ten !== '') { $result[$ten] = $pl; }
        }
        fclose($fh);
        return $result;
    }

    /**
     * Lấy map tên tàu => số đăng ký
     */
    public function getAllSoDangKy(): array {
        $result = [];
        if (!file_exists($this->filePath)) return $result;
        $fh = fopen($this->filePath, 'r');
        if (!$fh) return $result;
        $headers = fgetcsv($fh) ?: [];
        while (($row = fgetcsv($fh)) !== false) {
            if (count($row) !== count($headers)) continue;
            $rec = array_combine($headers, $row);
            if (!$rec) continue;
            $ten = $rec['ten_tau'] ?? '';
            $sdk = trim((string)($rec['so_dang_ky'] ?? ''));
            if ($ten !== '' && $sdk !== '') { $result[$ten] = $sdk; }
        }
        fclose($fh);
        return $result;
    }
}
?>


