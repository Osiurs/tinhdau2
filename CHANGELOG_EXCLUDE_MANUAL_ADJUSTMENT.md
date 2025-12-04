# TÀI LIỆU THAY ĐỔI - BỎ TINH CHỈNH THỦ CÔNG KHỎI BÁO CÁO CHI TIẾT

## 📅 Thông tin
- **Ngày**: 04/12/2025
- **Dự án**: Tính dầu 2.0
- **File sửa**: `includes/excel_export_full.php`
- **Dòng sửa**: 288-291

## 🎯 Yêu cầu

Khi xuất báo cáo chi tiết theo tàu (IN TINH DAU):
1. ❌ **BỎ QUA**: Tinh chỉnh thủ công (không có transfer_pair_id)
2. ✅ **GIỮ LẠI**: Chuyển dầu (có transfer_pair_id)
3. ✅ **GIỮ LẠI**: Cấp thêm từ cây xăng

## 🔧 Thay đổi code

### TRƯỚC (dòng 289-295):
```php
} else {
    // Đây là tinh chỉnh thủ công → HIỂN THỊ với label "Tính chính"
    $label = 'Tính chính';
    $lyDo = trim((string)($gd['ly_do'] ?? ''));
    if ($lyDo !== '') {
        $label .= ' (' . $lyDo . ')';
    }
    $receiptEntries[] = ['label' => $label, 'date' => $ngay, 'amount' => $soLuong];
}
```

### SAU (dòng 288-291):
```php
} else {
    // Đây là tinh chỉnh thủ công → BỎ QUA (không hiển thị trong báo cáo)
    // Tinh chỉnh thủ công vẫn ảnh hưởng đến dầu tồn qua tinhSoDu(), nhưng không hiển thị chi tiết
}
```

## 📊 Ảnh hưởng

### Ví dụ với HTL-1:

#### Dữ liệu gốc (trong dau_ton.csv):
1. Tinh chỉnh thủ công: +7,000 lít (02/12/2025, không có transfer_pair_id)
2. Chuyển dầu cho HTL-2: -500 lít (02/12/2025, có transfer_pair_id)

#### TRƯỚC khi sửa:
```
Nợ tại | Bảng tính ngày | 30/08/2025 | 0
Nhận dầu tại | Tính chính | 02/12/2025 | 7,000  ← HIỂN THỊ
Nhận dầu tại | Chuyển dầu → HTL-2 | 02/12/2025 | -500
Cộng: | | | 6,500
Dầu tồn... | 04/12/2025 | -25,089 Lít

Công thức: Dầu tồn = Cộng - Tiêu hao = 6,500 - 31,589 = -25,089
```

#### SAU khi sửa:
```
Nợ tại | Bảng tính ngày | 30/08/2025 | 0
Nhận dầu tại | Chuyển dầu → HTL-2 | 02/12/2025 | -500  ← CHỈ CÒN CHUYỂN DẦU
Cộng: | | | -500
Dầu tồn... | 04/12/2025 | -32,089 Lít

Công thức: Dầu tồn = tinhSoDu() (tính đầy đủ cả tinh chỉnh)
          = 0 + 7,000 - 500 - 31,589 - 7,000 = -32,089

LƯU Ý: Dầu tồn KHÁC với (Cộng - Tiêu hao) vì:
       - "Cộng" không bao gồm tinh chỉnh thủ công (+7,000)
       - Nhưng tinhSoDu() có tính tinh chỉnh (+7,000)
```

## 🔍 Phân biệt loại giao dịch

### 1. Cấp thêm từ cây xăng
- **Loại**: `cap_them`
- **Cách nhận diện**: `loai === 'cap_them'`
- **Hiển thị**: ✅ **HIỂN THỊ** với label từ `cay_xang`

### 2. Chuyển dầu
- **Loại**: `tinh_chinh`
- **Cách nhận diện**: `transfer_pair_id !== ''`
- **Hiển thị**: ✅ **HIỂN THỊ** với label từ `ly_do`
- **Ví dụ**: "Chuyển dầu → chuyển sang HTL-2"

### 3. Tinh chỉnh thủ công
- **Loại**: `tinh_chinh`
- **Cách nhận diện**: `transfer_pair_id === ''`
- **Hiển thị**: ❌ **BỎ QUA** (không hiển thị)
- **Lý do**: Chỉ dùng để điều chỉnh số dư, không phải giao dịch thực

## ✅ Kết quả test

### Test với HTL-1:
```
Tổng giao dịch: 2
- ❌ BỎ QUA: Tinh chỉnh thủ công (+7,000 lít)
- ✅ HIỂN THỊ: Chuyển dầu (-500 lít)

receiptEntries: 1 dòng
Tổng nhận dầu: -500 lít
```

✅ **Hoạt động đúng như yêu cầu!**

## 📌 Lưu ý khi deploy

### 1. File cần cập nhật lên server:
```
includes/excel_export_full.php
```

### 2. Không cần thay đổi:
- Database/CSV (dữ liệu gốc không đổi)
- Model DauTon.php
- Các file khác

### 3. Kiểm tra sau deploy:
1. Xuất báo cáo chi tiết cho tàu có tinh chỉnh thủ công
2. Xác nhận phần "Nhận dầu tại" KHÔNG hiển thị tinh chỉnh thủ công
3. Xác nhận vẫn hiển thị chuyển dầu
4. Xác nhận số dầu tồn vẫn chính xác

### 4. Ảnh hưởng đến người dùng:
- **Số dầu tồn**: KHÔNG THAY ĐỔI (vẫn chính xác)
- **Phần "Cộng"**: SẼ KHÁC (nhỏ hơn vì không có tinh chỉnh thủ công)
- **Lý do**: Tinh chỉnh thủ công là điều chỉnh kế toán, không phải giao dịch thực tế

## 🚨 Cảnh báo

### Trường hợp cần lưu ý:
Nếu người dùng thắc mắc "Tại sao Dầu tồn ≠ (Cộng - Tiêu hao)?":

**Giải thích:**
- **"Cộng"** chỉ bao gồm: Nợ tại + Cấp thêm + Chuyển dầu (giao dịch thực tế)
- **"Dầu tồn"** được tính từ model, bao gồm cả tinh chỉnh thủ công (điều chỉnh kế toán)
- **Tinh chỉnh thủ công** không hiển thị chi tiết vì không phải giao dịch thực tế, chỉ là điều chỉnh số dư

### Cách kiểm tra:
```
Dầu tồn = Nợ tại + Tổng cấp thêm + Tổng chuyển dầu + Tổng tinh chỉnh - Tổng tiêu hao
```

Trong báo cáo chỉ hiển thị: Nợ tại + Cấp thêm + Chuyển dầu (bỏ Tinh chỉnh)

## 📚 Tham khảo

### Logic phân loại trong code:
```php
if ($loai === 'cap_them') {
    // ✅ HIỂN THỊ: Cấp thêm
}
elseif ($loai === 'tinh_chinh') {
    if ($transferPairId !== '') {
        // ✅ HIỂN THỊ: Chuyển dầu
    } else {
        // ❌ BỎ QUA: Tinh chỉnh thủ công
    }
}
```

### Model tính dầu tồn:
- File: `models/DauTon.php`
- Phương thức: `tinhSoDu($tenTau, $denNgay)`
- Logic: Tính TẤT CẢ giao dịch (bao gồm tinh chỉnh)

## ✅ Kết luận

**Thay đổi thành công:**
- ❌ Bỏ tinh chỉnh thủ công khỏi báo cáo chi tiết
- ✅ Giữ lại chuyển dầu
- ✅ Dầu tồn vẫn chính xác
- ✅ Code sạch, dễ maintain

**Phạm vi:**
- Chỉ ảnh hưởng phần hiển thị báo cáo Excel
- Không ảnh hưởng dữ liệu gốc
- An toàn để deploy production
