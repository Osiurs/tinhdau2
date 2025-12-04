# FIX LỖI BỎ TINH CHỈNH NHƯNG MẤT LUÔN CHUYỂN DẦU

## Ngày: 2025-12-04

---

## 1. MÔ TẢ VẤN ĐỀ

### Yêu cầu
User muốn xuất file Excel **bỏ các dòng "Tinh chỉnh"** nhưng **giữ lại các dòng khác** như:
- ✅ Nhận dầu tại (Cấp thêm từ cây xăng)
- ✅ Chuyển dầu (chuyển từ tàu này sang tàu khác)

### File cũ (trước khi bỏ tinh chỉnh)
```
Nợ tại         Bảng tính ngày      30/08/2025
Nhận dầu tại   Tinh chỉnh          02/12/2025      7,000    ← Muốn BỎ
Nhận dầu tại   Chuyển dầu cho HTL-2 02/12/2025     -500     ← Muốn GIỮ
Nhận dầu tại   Tinh chỉnh          02/12/2025      3,000    ← Muốn BỎ
Cộng:                                              9,500
Dầu tồn trên sà lan đến ngày       03/12/2025      4,610 Lít
```

### File mới (sau khi bỏ tinh chỉnh - BỊ LỖI)
```
Nợ tại         Bảng tính ngày      30/10/2025
Cộng:
Dầu tồn trên sà lan đến ngày       04/12/2025      -26,731 Lít  ← SỐ ÂM!
```

**Vấn đề:**
- ❌ Không có dòng "Nhận dầu tại" → Mất hết các dòng nhận dầu
- ❌ Dầu tồn = **-26,731** lít (số âm!) → Sai logic

---

## 2. NGUYÊN NHÂN

### Cấu trúc dữ liệu trong database

Trong file `data/dau_ton_giao_dich.csv`:

| loai | transfer_pair_id | Ý nghĩa |
|------|------------------|---------|
| `cap_them` | (trống) | Cấp thêm dầu từ cây xăng |
| `tinh_chinh` | (có UUID) | **Chuyển dầu** giữa các tàu |
| `tinh_chinh` | (trống) | **Tinh chỉnh** thủ công |

**Vấn đề:** Code cũ **bỏ HẾT `loai = 'tinh_chinh'`**, nên:
- ✅ Bỏ được tinh chỉnh thủ công
- ❌ Nhưng cũng bỏ luôn **chuyển dầu** → Sai!

### Code cũ (SAI)
```php
if ($loai === 'cap_them') {
    // Thêm vào receiptEntries
} elseif ($loai === 'tinh_chinh') {
    // Bỏ HẾT tinh chỉnh → Vô tình bỏ luôn chuyển dầu!
}
```

---

## 3. GIẢI PHÁP

### Logic mới (ĐÚNG)
```php
if ($loai === 'cap_them') {
    // Cấp thêm → HIỂN THỊ
} elseif ($loai === 'tinh_chinh') {
    if (!empty($gd['transfer_pair_id'])) {
        // Có transfer_pair_id → Chuyển dầu → HIỂN THỊ
    } else {
        // Không có transfer_pair_id → Tinh chỉnh thủ công → BỎ QUA
    }
}
```

### Code đã fix

**File:** `includes/excel_export_full.php` (dòng 263-293)

```php
foreach ($dauTonModel->getLichSuGiaoDich($ship) as $gd) {
    $ngay = (string)($gd['ngay'] ?? '');
    if (!$ngay) continue;

    $loai = strtolower((string)($gd['loai'] ?? ''));

    if ($loai === 'cap_them') {
        // Cấp thêm từ cây xăng → HIỂN THỊ
        $soLuong = (float)($gd['so_luong_lit'] ?? 0);
        if ($soLuong !== 0.0) {
            $label = trim((string)($gd['cay_xang'] ?? 'Cấp thêm'));
            $receiptEntries[] = ['label' => $label, 'date' => $ngay, 'amount' => $soLuong];
        }
    } elseif ($loai === 'tinh_chinh') {
        // Phân biệt: Chuyển dầu vs Tinh chỉnh thủ công
        $transferPairId = trim((string)($gd['transfer_pair_id'] ?? ''));

        if ($transferPairId !== '') {
            // Có transfer_pair_id → Chuyển dầu → HIỂN THỊ
            $soLuong = (float)($gd['so_luong_lit'] ?? 0);
            if ($soLuong !== 0.0) {
                $label = trim((string)($gd['ly_do'] ?? 'Chuyển dầu'));
                $receiptEntries[] = ['label' => $label, 'date' => $ngay, 'amount' => $soLuong];
            }
        } else {
            // Không có transfer_pair_id → Tinh chỉnh thủ công → BỎ QUA
        }
    }
}
```

---

## 4. KẾT QUẢ MONG ĐỢI

### File mới (sau khi fix)
```
Nợ tại         Bảng tính ngày      30/08/2025
Nhận dầu tại   Chuyển dầu cho HTL-2 02/12/2025     -500     ← GIỮ LẠI
Cộng:                                              -500
Dầu tồn trên sà lan đến ngày       03/12/2025      [số dương] Lít
```

**Kết quả:**
- ✅ Bỏ các dòng "Tinh chỉnh" thủ công
- ✅ Giữ lại các dòng "Chuyển dầu"
- ✅ Giữ lại các dòng "Cấp thêm"
- ✅ Dầu tồn tính đúng (không âm)

---

## 5. CÁCH TEST

### Bước 1: Xóa file Excel cũ
Xóa tất cả file Excel cũ trong thư mục Downloads để tránh nhầm lẫn.

### Bước 2: Refresh trình duyệt
Bấm `Ctrl + F5` (Windows) hoặc `Cmd + Shift + R` (Mac) để clear cache.

### Bước 3: Xuất Excel chi tiết
1. Bấm "Xuất Excel"
2. Bấm "Xuất chi tiết..."
3. Chọn tàu cần xuất (ví dụ: HTL-1)
4. Bấm "Xuất"
5. Điền thông tin wizard hoặc Skip
6. Chờ file tải về

### Bước 4: Kiểm tra file Excel

Mở file `CT_T[tháng]_[năm].xlsx` và kiểm tra:

#### ✅ Checklist
- [ ] Sheet "IN TINH DAU-SLCTY - [Tên tàu]" hiển thị đúng
- [ ] Có dòng "Nợ tại - Bảng tính ngày"
- [ ] **Có các dòng "Nhận dầu tại"** (không bị mất)
- [ ] Các dòng "Nhận dầu tại" bao gồm:
  - [ ] Cấp thêm từ cây xăng (số dương)
  - [ ] Chuyển dầu cho/từ tàu khác (số âm/dương)
- [ ] **KHÔNG có** dòng "Tinh chỉnh" (đã bỏ thành công)
- [ ] Có dòng "Cộng:" với tổng đúng
- [ ] Dầu tồn **không âm** (hoặc âm hợp lý nếu thực tế thiếu dầu)

#### ❌ Nếu vẫn sai
Nếu vẫn không thấy dòng "Nhận dầu tại", kiểm tra:
1. Database có dữ liệu nhận dầu không? (Xem file `data/dau_ton_giao_dich.csv`)
2. Có lỗi PHP không? (Xem browser console hoặc `data/debug.log`)
3. Đang xem đúng sheet không? (Phải là sheet "IN TINH DAU", không phải "DAUTON")

---

## 6. FILES ĐÃ SỬA

### includes/excel_export_full.php
**Dòng 263-293:** Thêm logic phân biệt chuyển dầu vs tinh chỉnh

**Thay đổi:**
- **Trước:** Bỏ hết `loai = 'tinh_chinh'`
- **Sau:** Kiểm tra `transfer_pair_id`:
  - Có `transfer_pair_id` → Chuyển dầu → HIỂN THỊ
  - Không có → Tinh chỉnh → BỎ QUA

---

## 7. LƯU Ý

### Về chuyển dầu
- Chuyển dầu được lưu thành **2 dòng** trong database:
  1. Tàu nguồn: `so_luong_lit` = **âm** (ví dụ: -500)
  2. Tàu đích: `so_luong_lit` = **dương** (ví dụ: +500)
- Cả 2 dòng đều có cùng `transfer_pair_id` để ghép cặp

### Về tinh chỉnh
- Tinh chỉnh thủ công: `loai = 'tinh_chinh'` + `transfer_pair_id` = rỗng
- Chỉ ảnh hưởng đến số dư dầu tồn, **không hiển thị** trong báo cáo Excel

### Về dầu tồn âm
- Nếu dầu tồn = âm: Có thể do thực tế tàu đã dùng dầu nhiều hơn nhận vào
- Không phải lỗi kỹ thuật, trừ khi số âm quá lớn (ví dụ: -26,731)

---

## 8. TÓM TẮT

| Loại giao dịch | transfer_pair_id | Hiển thị trong Excel? |
|----------------|------------------|----------------------|
| Cấp thêm | (trống) | ✅ HIỂN THỊ |
| Chuyển dầu | (có UUID) | ✅ HIỂN THỊ |
| Tinh chỉnh | (trống) | ❌ BỎ QUA |

**Nguyên tắc:**
- Chỉ bỏ **tinh chỉnh thủ công** (không có `transfer_pair_id`)
- Giữ lại **tất cả giao dịch thực tế** (cấp thêm, chuyển dầu)
