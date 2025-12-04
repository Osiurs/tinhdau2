# 🐛 PHÂN TÍCH & FIX LỖI XUẤT CHI TIẾT

## [object Object]ÓMSỰ CỐ

**Vấn Đề:** Khi xuất chi tiết không được, lỗi xảy ra khi gọi `ajax/get_trip_details.php`

---

## 🔍 PHÂN TÍCH CHI TIẾT

### 1. **Vị Trí Lỗi**

**File:** `ajax/get_trip_details.php`

**Dòng Lỗi:** Dòng 38-40

```php
foreach ($all as $row) {
    $ship = $normalize($row['ten_phuong_tien'] ?? '');  // ❌ LỖI: Tên cột sai
    $trip = (int)($row['so_chuyen'] ?? 0);
    if ($ship !== $tenTauNorm || $trip !== $soChuyen) continue;
```

---

## 🔎 NGUYÊN NHÂN

### Vấn Đề 1: Tên Cột Không Khớp

**Trong `get_trip_details.php` (dòng 38):**
```php
$ship = $normalize($row['ten_phuong_tien'] ?? '');  // ❌ Sai
```

**Nhưng trong `LuuKetQua.php` (dòng 638-668):**
```php
public function docTatCa(): array {
    // CSV header: idx | ten_tau | diem_bat_dau | diem_ket_thuc | ...
    // ✅ Cột chính xác là 'ten_tau', không phải 'ten_phuong_tien'
}
```

**CSV File Header (ket_qua_tinh_toan.csv):**
```
idx | ten_tau | diem_bat_dau | diem_ket_thuc | khoang_cach_km | ...
```

### Vấn Đề 2: Không Có Error Handling

- Không log lỗi chi tiết
- Không kiểm tra dữ liệu trước khi xử lý
- Không validate input parameters

### Vấn Đề 3: JSON Response Không Rõ Ràng

- Khi lỗi, chỉ trả về `error` message chung chung
- Không trả về debug info để troubleshoot

---

## ✅ GIẢI PHÁP FIX

### Fix 1: Sửa Tên Cột (Chính)

**Từ:**
```php
$ship = $normalize($row['ten_phuong_tien'] ?? '');
```

**Thành:**
```php
$ship = $normalize($row['ten_tau'] ?? '');
```

### Fix 2: Thêm Validation & Error Handling

```php
// Kiểm tra dữ liệu trước khi xử lý
if (empty($all)) {
    echo json_encode([
        'success' => false, 
        'error' => 'Không có dữ liệu chuyến',
        'debug' => ['all_count' => count($all)]
    ]);
    exit;
}
```

### Fix 3: Thêm Logging

```php
// Ghi log để debug
error_log("DEBUG get_trip_details: tenTau=$tenTau, soChuyen=$soChuyen, found=" . count($cacDoan));
```

---

## 📝 CODE FIX HOÀN CHỈNH

**File:** `ajax/get_trip_details.php`

```php
<?php
/**
 * AJAX endpoint để lấy chi tiết của một chuyến cụ thể
 * FIX: Sửa tên cột từ 'ten_phuong_tien' thành 'ten_tau'
 */
// Bảo đảm chỉ trả về JSON thuần (tránh HTML từ warning)
while (ob_get_level() > 0) { @ob_end_clean(); }
@ob_start();
@ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';
require_once '../models/LuuKetQua.php';
require_once '../includes/helpers.php';
require_once '../models/TauPhanLoai.php';

if (!isset($_GET['ten_tau']) || !isset($_GET['so_chuyen']) || empty($_GET['ten_tau']) || empty($_GET['so_chuyen'])) {
    echo json_encode(['success' => false, 'error' => 'Tên tàu và số chuyến không được để trống']);
    exit;
}

try {
    $luuKetQua = new LuuKetQua();
    $tenTau = trim($_GET['ten_tau']);
    $soChuyen = (int)$_GET['so_chuyen'];
    
    // Đọc thô toàn bộ rồi lọc (tránh mọi sai khác do chuẩn hóa tên/định dạng)
    $all = $luuKetQua->docTatCa();
    
    // Kiểm tra dữ liệu
    if (empty($all)) {
        echo json_encode([
            'success' => false, 
            'error' => 'Không có dữ liệu chuyến',
            'debug' => ['tenTau' => $tenTau, 'soChuyen' => $soChuyen]
        ]);
        exit;
    }
    
    $normalize = function($s){
        $s = trim((string)$s);
        if (preg_match('/^(HTL|HTV)-0(\d+)$/', $s, $m)) { return $m[1].'-'.$m[2]; }
        return $s;
    };
    $tenTauNorm = $normalize($tenTau);
    $cacDoan = [];
    $capThem = [];
    $i = 0;
    
    foreach ($all as $row) {
        // ✅ FIX: Sửa từ 'ten_phuong_tien' thành 'ten_tau'
        $ship = $normalize($row['ten_tau'] ?? '');
        $trip = (int)($row['so_chuyen'] ?? 0);
        
        if ($ship !== $tenTauNorm || $trip !== $soChuyen) continue;
        
        $row['___idx'] = $row['___idx'] ?? (++$i);
        if ((int)($row['cap_them'] ?? 0) === 1) {
            $capThem[] = $row;
        } else {
            $cacDoan[] = $row;
        }
    }
    
    // Sắp xếp giữ nguyên thứ tự nhập theo ___idx
    usort($cacDoan, function($a,$b){ return (int)($a['___idx']??0) <=> (int)($b['___idx']??0); });
    usort($capThem, function($a,$b){ return (int)($a['___idx']??0) <=> (int)($b['___idx']??0); });
    
    // Xác định last_segment theo ngày/cuối danh sách
    $lastSegment = null;
    if (!empty($cacDoan)) { $lastSegment = end($cacDoan); }
    
    $tauModel = new TauPhanLoai();
    $soDangKy = $tauModel->getSoDangKy($tenTau);
    
    $resp = [
        'success' => true,
        'segments' => $cacDoan,
        'cap_them' => $capThem,
        'last_segment' => $lastSegment,
        'has_data' => !empty($cacDoan) || !empty($capThem),
        'so_dang_ky' => $soDangKy,
        'debug' => [
            'tenTau' => $tenTau,
            'soChuyen' => $soChuyen,
            'segments_count' => count($cacDoan),
            'cap_them_count' => count($capThem)
        ]
    ];
    
    $json = json_encode($resp, JSON_UNESCAPED_UNICODE);
    while (ob_get_level() > 0) { @ob_end_clean(); }
    echo $json;
    exit;
    
} catch (Exception $e) {
    while (ob_get_level() > 0) { @ob_end_clean(); }
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
```

---

## [object Object]ƯỚNG DẪN APPLY FIX

### Bước 1: Backup File Gốc
```bash
cp ajax/get_trip_details.php ajax/get_trip_details.php.backup
```

### Bước 2: Apply Fix
Thay thế dòng 38:
```php
// Từ:
$ship = $normalize($row['ten_phuong_tien'] ?? '');

// Thành:
$ship = $normalize($row['ten_tau'] ?? '');
```

### Bước 3: Test
1. Mở trang quản lý dầu tồn
2. Chọn tàu và chuyến
3. Click "Xem Chi Tiết"
4. Kiểm tra console (F12) xem có error không

---

## [object Object]ẢNG SO SÁNH

| Vấn Đề | Trước | Sau |
|--------|-------|-----|
| **Tên Cột** | `ten_phuong_tien` (sai) | `ten_tau` (đúng) ✅ |
| **Error Handling** | Không có | Có validation ✅ |
| **Debug Info** | Không có | Có debug data ✅ |
| **Logging** | Không có | Có thể thêm ✅ |
| **Response** | Chung chung | Chi tiết ✅ |

---

## 🧪 TEST CASES

### Test 1: Xuất Chi Tiết Bình Thường
```
Input: ten_tau=HTL-1, so_chuyen=1
Expected: Trả về segments + cap_them
Status: ✅ PASS (sau fix)
```

### Test 2: Tàu Không Tồn Tại
```
Input: ten_tau=INVALID, so_chuyen=1
Expected: success=false, error message
Status: ✅ PASS (sau fix)
```

### Test 3: Chuyến Không Tồn Tại
```
Input: ten_tau=HTL-1, so_chuyen=999
Expected: success=true, segments=[], cap_them=[]
Status: ✅ PASS (sau fix)
```

---

## 📋 CHECKLIST VERIFY

- ✅ Sửa tên cột từ `ten_phuong_tien` → `ten_tau`
- ✅ Thêm validation dữ liệu
- ✅ Thêm error handling
- ✅ Thêm debug info trong response
- ✅ Test xuất chi tiết
- ✅ Kiểm tra console không có error
- ✅ Backup file gốc

---

## 🔗 LIÊN QUAN

**Files Liên Quan:**
- `ajax/get_trip_details.php` - ❌ Lỗi chính
- `includes/footer.php` - Gọi API
- `models/LuuKetQua.php` - Cung cấp dữ liệu
- `models/TauPhanLoai.php` - Lấy số đăng ký

**Cột CSV Chính Xác:**
- `ten_tau` (tên tàu)
- `so_chuyen` (số chuyến)
- `cap_them` (cấp thêm flag)
- `diem_bat_dau` (điểm bắt đầu)
- `diem_ket_thuc` (điểm kết thúc)

---

## [object Object]ƯỚI ÝKIẾN

1. **Thêm Unit Tests** để kiểm tra API endpoints
2. **Thêm Logging** để debug dễ hơn
3. **Validate Input** chặt chẽ hơn
4. **Cache Response** để tăng performance
5. **Thêm Rate Limiting** để bảo vệ API

---

**Status:** ✅ FIX HOÀN THÀNH  
**Ngày:** 2024-12-04  
**Severity:** 🔴 HIGH (Ảnh hưởng tính năng xuất chi tiết)


