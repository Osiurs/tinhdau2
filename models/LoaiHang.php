<?php
/**
 * Quản lý Loại hàng dựa trên file CSV data/loai_hang.csv
 * Cấu trúc CSV: id,ten_loai_hang,mo_ta,trang_thai,created_at,updated_at
 */

require_once __DIR__ . '/../config/database.php';

if (!defined('LOAI_HANG_FILE')) {
    define('LOAI_HANG_FILE', __DIR__ . '/../data/loai_hang.csv');
}

class LoaiHang {
    /**
     * Lấy toàn bộ loại hàng (không phân biệt trạng thái)
     * @param bool $includeInactive deprecated
     * @return array<int, array<string,string>>
     */
    public function getAll(bool $includeInactive = false): array {
        $rows = $this->readCsv();
        return $rows;
    }

    /**
     * Lấy danh sách tên loại hàng
     * @param bool $includeInactive deprecated
     * @return array<int, string>
     */
    public function getTenLoaiHangList(bool $includeInactive = false): array {
        return array_values(array_map(function($r){ return (string)($r['ten_loai_hang'] ?? ''); }, $this->getAll(true)));
    }

    /**
     * Kiểm tra tồn tại theo tên (case-insensitive, trim)
     */
    public function existsByName(string $name): bool {
        $nameN = $this->normalize($name);
        if ($nameN === '') return false;
        foreach ($this->readCsv() as $r) {
            if ($this->normalize((string)($r['ten_loai_hang'] ?? '')) === $nameN) {
                return true;
            }
        }
        return false;
    }

    /**
     * Thêm loại hàng mới; trả về bản ghi đã thêm
     * @param string $name
     * @return array<string,string>
     */
    public function add(string $name, string $moTa = ''): array {
        $name = trim($name);
        if ($name === '') {
            throw new Exception('Tên loại hàng không được để trống');
        }
        if ($this->existsByName($name)) {
            throw new Exception('Loại hàng đã tồn tại');
        }
        $rows = $this->readCsvRawLines();
        // Tìm max id
        $maxId = 0;
        $header = true;
        foreach ($rows as $line) {
            if ($header) { $header = false; continue; }
            $parts = str_getcsv($line);
            if (count($parts) > 0 && is_numeric($parts[0])) {
                $maxId = max($maxId, (int)$parts[0]);
            }
        }
        $newId = $maxId + 1;
        $now = date('Y-m-d H:i:s');
        $record = [
            'id' => (string)$newId,
            'ten_loai_hang' => $name,
            'mo_ta' => '',
            'trang_thai' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $this->appendCsv($record);
        return $record;
    }

    /**
     * Cập nhật mô tả/trạng thái theo id
     */
    public function update(int $id, array $data): bool {
        $lines = $this->readCsvRawLines();
        $out = [];
        $header = true;
        $updated = false;
        foreach ($lines as $line) {
            if ($header) { $out[] = $line; $header = false; continue; }
            $parts = str_getcsv($line);
            if (count($parts) >= 6 && (int)$parts[0] === $id) {
                $ten = $parts[1];
                // Đổi tên nếu có yêu cầu
                if (array_key_exists('ten_loai_hang', $data)) {
                    $newName = trim((string)$data['ten_loai_hang']);
                    if ($newName === '') { throw new Exception('Tên loại hàng không được để trống'); }
                    // Kiểm tra trùng tên cho bản ghi khác
                    foreach ($this->readCsv() as $row) {
                        if ((int)($row['id'] ?? 0) !== $id && $this->normalize((string)($row['ten_loai_hang'] ?? '')) === $this->normalize($newName)) {
                            throw new Exception('Tên loại hàng đã tồn tại');
                        }
                    }
                    $ten = $newName;
                }
                $moTa = '';
                // Giữ nguyên giá trị trạng thái hiện có trong file để tương thích cột cũ
                $trangThai = $parts[3] ?? 'active';
                $createdAt = $parts[4];
                $updatedAt = date('Y-m-d H:i:s');
                $out[] = $this->toCsvLine([$id, $ten, $moTa, $trangThai, $createdAt, $updatedAt]);
                $updated = true;
            } else {
                $out[] = $line;
            }
        }
        if ($updated) {
            file_put_contents(LOAI_HANG_FILE, implode("\n", $out) . "\n");
        }
        return $updated;
    }

    /**
     * Xóa theo id
     */
    public function delete(int $id): bool {
        $lines = $this->readCsvRawLines();
        $out = [];
        $header = true;
        $deleted = false;
        foreach ($lines as $line) {
            if ($header) { $out[] = $line; $header = false; continue; }
            $parts = str_getcsv($line);
            if (count($parts) >= 1 && (int)$parts[0] === $id) {
                $deleted = true;
                continue;
            }
            $out[] = $line;
        }
        if ($deleted) {
            file_put_contents(LOAI_HANG_FILE, implode("\n", $out) . "\n");
        }
        return $deleted;
    }

    // ===== Helpers =====
    private function normalize(string $s): string { return mb_strtolower(trim($s), 'UTF-8'); }

    private function readCsv(): array {
        if (!file_exists(LOAI_HANG_FILE)) {
            $this->initCsv();
        }
        $rows = [];
        if (($fh = fopen(LOAI_HANG_FILE, 'r')) !== false) {
            $headers = fgetcsv($fh);
            if ($headers === false) { fclose($fh); return []; }
            while (($data = fgetcsv($fh)) !== false) {
                $row = [];
                foreach ($headers as $i => $h) {
                    $row[$h] = $data[$i] ?? '';
                }
                $rows[] = $row;
            }
            fclose($fh);
        }
        return $rows;
    }

    private function readCsvRawLines(): array {
        if (!file_exists(LOAI_HANG_FILE)) {
            $this->initCsv();
        }
        return file(LOAI_HANG_FILE, FILE_IGNORE_NEW_LINES);
    }

    private function appendCsv(array $record): void {
        if (!file_exists(LOAI_HANG_FILE)) {
            $this->initCsv();
        }
        $line = $this->toCsvLine([
            $record['id'],
            $record['ten_loai_hang'],
            $record['mo_ta'],
            $record['trang_thai'],
            $record['created_at'],
            $record['updated_at'],
        ]);
        file_put_contents(LOAI_HANG_FILE, $line . "\n", FILE_APPEND | LOCK_EX);
    }

    private function toCsvLine(array $fields): string {
        $f = fopen('php://temp', 'r+');
        fputcsv($f, $fields);
        rewind($f);
        $csv = rtrim(stream_get_contents($f), "\r\n");
        fclose($f);
        return $csv;
    }

    private function initCsv(): void {
        $header = 'id,ten_loai_hang,mo_ta,trang_thai,created_at,updated_at' . "\n";
        file_put_contents(LOAI_HANG_FILE, $header, LOCK_EX);
    }
}

?>


