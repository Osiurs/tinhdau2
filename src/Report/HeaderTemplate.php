<?php
namespace App\Report;

final class HeaderTemplate {
	public static function pathFor(string $reportType): string {
		$cfgPath = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'report_header_registry.php';
		$cfg = require $cfgPath;
		$root = $cfg['root'] ?? '';
		$map = $cfg['map'] ?? [];
		if (isset($map[$reportType])) {
			$candidate = $root . DIRECTORY_SEPARATOR . $map[$reportType];
			if (is_file($candidate)) {
				return $candidate;
			}
		}
		$fallback = '';
		if (!empty($cfg['default'])) {
			$fallback = $root . DIRECTORY_SEPARATOR . $cfg['default'];
		}
		error_log('[header-template] Missing template for ' . $reportType . ', using default: ' . $fallback);
		return $fallback;
	}

	/**
	 * Apply common header data without changing layout/styles.
	 * Searches for placeholder text and replaces it with actual date.
	 * Falls back to writing to specified cell if placeholder not found.
	 *
	 * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet The worksheet to update
	 * @param string $dateCell The cell address to write date if placeholder not found (default 'F4')
	 */
	public static function applyCommonHeader(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $dateCell = 'F4'): void {
		$today = date('d');
		$month = date('m');
		$year = date('Y');
		// Chỉ tạo phần ngày tháng năm, KHÔNG có "Tp.Hồ Chí Minh" vì template đã có sẵn
		$dateStr = 'ngày ' . $today . ' tháng ' . $month . ' năm ' . $year;

		// Method 1: Search and replace placeholder text in header rows (1-6)
		// This is the PRIMARY method - we search for placeholders first
		// CHÚ Ý: Chỉ replace placeholder "ngày  tháng  năm", KHÔNG thêm "Tp.Hồ Chí Minh" để tránh duplicate
		$placeholders = [
			'Thành phố Hồ Chí Minh, ngày  tháng  năm .',  // DAUTON template (full name with period)
			'Thành phố Hồ Chí Minh, ngày  tháng  năm',    // DAUTON template (full name without period)
			'Tp.Hồ Chí Minh, ngày  tháng  năm .',         // BCTHANG template (with period at end)
			'Tp.Hồ Chí Minh, ngày  tháng  năm',           // with double spaces
			'Tp. Hồ Chí Minh, ngày tháng năm',            // with period after Tp (IN TINH DAU)
			'Tp Hồ Chí Minh, ngày tháng năm',             // without period
			'ngày  tháng  năm',                            // minimal with double spaces
			'ngày tháng năm'                               // minimal
		];

		$found = false;
		try {
			$highestColumn = $sheet->getHighestColumn();
			$highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

			// Search in first 6 rows (header area)
			for ($row = 1; $row <= 6; $row++) {
				for ($col = 1; $col <= min($highestColumnIndex, 20); $col++) { // Check up to column T
					$cell = $sheet->getCellByColumnAndRow($col, $row);
					$value = $cell->getValue();

					if (is_string($value)) {
						foreach ($placeholders as $placeholder) {
							if (stripos($value, $placeholder) !== false) {
								// Phương pháp mới: Chỉ thay thế phần "ngày  tháng  năm" trong placeholder
								// Giữ nguyên phần prefix (VD: "Tp. Hồ Chí Minh, ") để tránh duplicate
								$hasPeriod = substr(trim($value), -1) === '.';

								// Tìm vị trí của "ngày" trong placeholder
								$ngayPos = stripos($placeholder, 'ngày');
								if ($ngayPos !== false) {
									// Lấy prefix (phần trước "ngày")
									$prefix = substr($placeholder, 0, $ngayPos);
									// Replace toàn bộ placeholder = prefix + dateStr
									$newValue = str_ireplace($placeholder, $prefix . $dateStr, $value);
								} else {
									// Nếu không tìm thấy "ngày" (trường hợp minimal), replace trực tiếp
									$newValue = str_ireplace($placeholder, $dateStr, $value);
								}

								if ($hasPeriod && substr(trim($newValue), -1) !== '.') {
									$newValue = rtrim($newValue) . '.';
								}
								$cell->setValue($newValue);
								error_log('[HeaderTemplate] Replaced date placeholder in cell ' . $cell->getCoordinate() . ': "' . $value . '" -> "' . $newValue . '"');
								$found = true;
								break 3; // Found and replaced, exit all loops
							}
						}
					}
				}
			}
		} catch (\Throwable $e) {
			error_log('[HeaderTemplate] Error during placeholder search: ' . $e->getMessage());
		}

		// Method 2: Fallback - Write to specified cell if placeholder not found
		if (!$found) {
			try {
				// Nếu fallback, cần thêm "Tp.Hồ Chí Minh, " vào đầu
				$sheet->setCellValue($dateCell, 'Tp.Hồ Chí Minh, ' . $dateStr . '.');
				error_log('[HeaderTemplate] No placeholder found, wrote date to fallback cell: ' . $dateCell);
			} catch (\Throwable $e) {
				error_log('[HeaderTemplate] Failed to set date in cell ' . $dateCell . ': ' . $e->getMessage());
			}
		}
	}
}


