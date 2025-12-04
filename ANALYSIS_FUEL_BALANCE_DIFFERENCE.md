# PHÂN TÍCH TẠI SAO DẦU TỒN MỘT BÊN DƯƠNG, MỘT BÊN ÂM

## 📊 Tổng quan

**Hiện tượng:**
- **Downloads (tinh-dau-2 (1))**: Dầu tồn = DƯƠNG (4,836 lít trong hình)
- **xampp/htdocs**: Dầu tồn = ÂM (-4,632 lít trong hình)

**Câu hỏi:** Tại sao cùng dữ liệu nhưng kết quả khác nhau?

---

## 🔍 1. CÔNG THỨC TÍNH DẦU TỒN

### Cả 2 dự án đều dùng CÙNG công thức:

```php
$tonCuoi = (int)round($tongNoNhan - (int)$sumFuel);
```

**Trong đó:**
- `$tongNoNhan` = Tổng (Nợ tại + Nhận dầu)
- `$sumFuel` = Tổng dầu tiêu hao

**Công thức:** `Dầu tồn = (Nợ tại + Nhận dầu) - Tiêu hao`

---

## 🔧 2. SỰ KHÁC BIỆT LOGIC

### 2.1. Downloads (tinh-dau-2 (1)) - **HIỂN THỊ TẤT CẢ**

**Code (dòng 275-283):**
```php
} elseif ($loai === 'tinh_chinh') {
    $soLuong = (float)($gd['so_luong_lit'] ?? 0);
    $rawLyDo = (string)($gd['ly_do'] ?? '');
    $dir = 'in'; $other = '';
    if (preg_match('/chuyển sang\s+([^\s]+)/u', $rawLyDo, $m1)) { $dir = 'out'; $other = $m1[1]; }
    elseif (preg_match('/nhận từ\s+([^\s]+)/u', $rawLyDo, $m2)) { $dir = 'in'; $other = $m2[1]; }
    $label = $other !== '' ? td2_format_transfer_label($ship, $other, $dir) : 'Tinh chỉnh';
    $receiptEntries[] = ['label' => $label, 'date' => $ngay, 'amount' => $soLuong]; // ← THÊM TẤT CẢ
}
```

**Logic:**
- ✅ Hiển thị **TẤT CẢ** tinh_chinh
- ✅ Bao gồm: Tinh chỉnh thủ công + Chuyển dầu

### 2.2. xampp/htdocs - **CHỈ CHUYỂN DẦU**

**Code (dòng 275-289):**
```php
} elseif ($loai === 'tinh_chinh') {
    $transferPairId = trim((string)($gd['transfer_pair_id'] ?? ''));
    $soLuong = (float)($gd['so_luong_lit'] ?? 0);
    if ($soLuong !== 0.0) {
        if ($transferPairId !== '') {
            // Đây là chuyển dầu → HIỂN THỊ
            $label = trim((string)($gd['ly_do'] ?? 'Chuyển dầu'));
            $receiptEntries[] = ['label' => $label, 'date' => $ngay, 'amount' => $soLuong];
        } else {
            // Đây là tinh chỉnh thủ công → BỎ QUA
        }
    }
}
```

**Logic:**
- ✅ CHỈ hiển thị chuyển dầu (có `transfer_pair_id`)
- ❌ BỎ QUA tinh chỉnh thủ công (không có `transfer_pair_id`)

---

## 📈 3. TÍNH TOÁN VỚI DỮ LIỆU HIỆN TẠI

### Dữ liệu HTL-1 trong dau_ton.csv:

| STT | Loại | Ngày | Số lượng | Transfer ID | Phân loại |
|-----|------|------|----------|-------------|-----------|
| 1 | tinh_chinh | 02/12/2025 | +7,000 | (Không) | **Tinh chỉnh thủ công** |
| 2 | tinh_chinh | 02/12/2025 | -500 | 7742d... | **Chuyển dầu** |

### Kết quả thu thập receiptEntries:

#### Downloads:
```
receiptEntries:
1. Tinh chỉnh: +7,000 ✅
2. Chuyển dầu → HTL-2: -500 ✅

sumReceiptsInt = 7,000 + (-500) = 6,500
tongNoNhan = 0 + 6,500 = 6,500
```

#### xampp/htdocs:
```
receiptEntries:
1. Chuyển dầu → HTL-2: -500 ✅
   (Tinh chỉnh +7,000 bị bỏ qua ❌)

sumReceiptsInt = -500
tongNoNhan = 0 + (-500) = -500
```

### Tính Dầu tồn:

**Tổng tiêu hao (sumFuel) = 28,735 lít** (giống nhau cả 2 bên)

#### Downloads:
```
Dầu tồn = 6,500 - 28,735 = -22,235 lít
```

#### xampp/htdocs:
```
Dầu tồn = -500 - 28,735 = -29,235 lít
```

### **Chênh lệch:**
```
-22,235 - (-29,235) = 7,000 lít
```

✅ **Chênh lệch = Đúng bằng số tinh chỉnh thủ công bị bỏ qua!**

---

## ⚠️ 4. VÌ SAO HÌNH ẢNH CÓ GIÁ TRỊ KHÁC?

### Từ hình ảnh bạn gửi:

- **Downloads**: Dầu tồn = +4,836 lít (DƯƠNG)
- **xampp/htdocs**: Dầu tồn = -4,632 lít (ÂM)
- **Chênh lệch**: 4,836 - (-4,632) = 9,468 lít

### **KẾT LUẬN:**

🚨 **Hình ảnh bạn gửi là FILE EXCEL CŨ!**

**Chứng cứ:**
1. Dữ liệu hiện tại chỉ có **2 giao dịch** (7,000 và -500)
2. Chênh lệch thực tế = **7,000 lít**
3. Nhưng hình ảnh chênh lệch = **9,468 lít** → Không khớp!
4. Hình 2 (Downloads) có **3 dòng** "Nhận dầu tại" (7,000 + (-500) + 3,000 = 9,500)
   → Dòng 3,000 KHÔNG TỒN TẠI trong dữ liệu hiện tại!

---

## 🎯 5. NGUYÊN NHÂN GỐC RỄ

### Tại sao một bên dương, một bên âm?

**Downloads:**
- Hiển thị tinh chỉnh thủ công (+7,000)
- → `tongNoNhan` lớn hơn
- → Dầu tồn ít âm hơn (hoặc dương)

**xampp/htdocs:**
- BỎ QUA tinh chỉnh thủ công (+7,000)
- → `tongNoNhan` nhỏ hơn
- → Dầu tồn âm nhiều hơn

**Công thức:**
```
Downloads: Dầu tồn = (+7,000 - 500) - 28,735 = -22,235
xampp:     Dầu tồn = (-500) - 28,735 = -29,235
                     ↑
                Chênh 7,000 lít
```

---

## ✅ 6. GIẢI PHÁP

### Để có kết quả chính xác với dữ liệu hiện tại:

1. **Xóa tất cả file Excel cũ** trong Downloads
2. **Vào trang Lịch sử** trong dự án xampp/htdocs
3. **Chọn tàu HTL-1**
4. **Nhấn Xuất → Chọn "Xuất chi tiết theo tàu"**
5. **Mở file MỚI** vừa tải

### Kết quả mong đợi với dữ liệu hiện tại:

**Downloads (tinh-dau-2 (1)):**
```
Nhận dầu tại | Tinh chỉnh | 02/12/2025 | 7,000
Nhận dầu tại | Chuyển dầu → HTL-2 | 02/12/2025 | -500
Cộng: 6,500
Dầu tồn: -22,235 lít
```

**xampp/htdocs:**
```
Nhận dầu tại | Chuyển dầu → HTL-2 | 02/12/2025 | -500
Cộng: -500
Dầu tồn: -29,235 lít
```

**Chênh lệch: 7,000 lít** (tinh chỉnh thủ công)

---

## 📝 7. TÓM TẮT

### Nguyên nhân chính:

| Khía cạnh | Downloads | xampp/htdocs |
|-----------|-----------|--------------|
| **Logic** | Hiển thị TẤT CẢ tinh_chinh | CHỈ hiển thị chuyển dầu |
| **Tinh chỉnh +7,000** | ✅ Hiển thị | ❌ Bỏ qua |
| **Chuyển dầu -500** | ✅ Hiển thị | ✅ Hiển thị |
| **tongNoNhan** | 6,500 | -500 |
| **sumFuel** | 28,735 | 28,735 |
| **Dầu tồn** | -22,235 (ít âm) | -29,235 (âm nhiều) |
| **Chênh lệch** | **7,000 lít** | |

### Kết luận cuối cùng:

1. ✅ **Code logic khác nhau** → receiptEntries khác nhau
2. ✅ **Chênh lệch = Tinh chỉnh thủ công** bị bỏ qua
3. ⚠️ **Hình ảnh bạn gửi = File Excel CŨ** (có dòng 3,000 không tồn tại)
4. 🎯 **Xuất lại Excel MỚI** để có kết quả chính xác

---

## 🔧 File đã sửa:

```
C:\xampp\htdocs\tinh-dau-2\tinh-dau-2\includes\excel_export_full.php
```

**Thay đổi (dòng 275-289):** BỎ QUA tinh chỉnh thủ công, CHỈ giữ chuyển dầu.
