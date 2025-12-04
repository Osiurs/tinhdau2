# 🧪 HƯỚNG DẪN TEST FIX XUẤT CHI TIẾT

## ✅ NHỮNG GÌ ĐÃ FIX

1. ✅ **Sửa tên cột:** `ten_phuong_tien` → `ten_tau`
2. ✅ **Thêm validation:** Kiểm tra dữ liệu trước xử lý
3. ✅ **Thêm error handling:** Chi tiết hơn khi có lỗi
4. ✅ **Thêm debug info:** Trả về debug data trong response

---

## 🧪 TEST CASES

### Test 1: Xuất Chi Tiết Bình Thường ✅

**Bước 1:** Mở trang Quản Lý Dầu Tồn
```
URL: http://localhost/tinh-dau-2/admin/quan_ly_dau_ton.php
```

**Bước 2:** Chọn một tàu từ danh sách
```
Ví dụ: HTL-1 (hoặc tàu nào có dữ liệu)
```

**Bước 3:** Chọn một chuyến
```
Ví dụ: Chuyến 1, 2, 3, ...
```

**Bước 4:** Click "Xem Chi Tiết"
```
Nút này sẽ gọi: ajax/get_trip_details.php?ten_tau=HTL-1&so_chuyen=1
```

**Kết Quả Mong Đợi:**
```
✅ Hiển thị danh sách các đoạn (segments)
✅ Hiển thị danh sách cấp thêm (cap_them)
✅ Không có lỗi trong console
✅ Response JSON có success=true
```

**Kiểm Tra Console (F12):**
```javascript
// Mở DevTools (F12) → Console
// Kiểm tra không có error
// Nên thấy response JSON:
{
    "success": true,
    "segments": [...],
    "cap_them": [...],
    "has_data": true,
    "debug": {
        "tenTau": "HTL-1",
        "soChuyen": 1,
        "segments_count": 3,
        "cap_them_count": 1
    }
}
```

---

### Test 2: Tàu Không Tồn Tại ❌

**Bước 1:** Mở DevTools Console (F12)
```
Ctrl+Shift+J (Windows/Linux)
Cmd+Option+J (Mac)
```

**Bước 2:** Chạy lệnh test
```javascript
fetch('ajax/get_trip_details.php?ten_tau=INVALID_SHIP&so_chuyen=1')
    .then(r => r.json())
    .then(d => console.log(d))
```

**Kết Quả Mong Đợi:**
```json
{
    "success": false,
    "error": "Không có dữ liệu chuyến",
    "debug": {
        "tenTau": "INVALID_SHIP",
        "soChuyen": 1
    }
}
```

---

### Test 3: Chuyến Không Tồn Tại ⚠️

**Bước 1:** Mở DevTools Console
```
F12 → Console
```

**Bước 2:** Chạy lệnh test
```javascript
fetch('ajax/get_trip_details.php?ten_tau=HTL-1&so_chuyen=999')
    .then(r => r.json())
    .then(d => console.log(d))
```

**Kết Quả Mong Đợi:**
```json
{
    "success": true,
    "segments": [],
    "cap_them": [],
    "has_data": false,
    "debug": {
        "tenTau": "HTL-1",
        "soChuyen": 999,
        "segments_count": 0,
        "cap_them_count": 0
    }
}
```

---

### Test 4: Parameter Trống ❌

**Bước 1:** Chạy lệnh test
```javascript
fetch('ajax/get_trip_details.php?ten_tau=&so_chuyen=')
    .then(r => r.json())
    .then(d => console.log(d))
```

**Kết Quả Mong Đợi:**
```json
{
    "success": false,
    "error": "Tên tàu và số chuyến không được để trống"
}
```

---

## 🔍 KIỂM TRA TRONG BROWSER

### Cách 1: Dùng DevTools Network Tab

**Bước 1:** Mở DevTools (F12)
```
F12 → Network tab
```

**Bước 2:** Thực hiện hành động xuất chi tiết
```
Click "Xem Chi Tiết"
```

**Bước 3:** Kiểm tra request
```
- Tìm request: get_trip_details.php
- Kiểm tra Status: 200 (OK)
- Kiểm tra Response: JSON hợp lệ
```

**Bước 4:** Xem Response
```
Preview tab → Xem JSON response
```

---

### Cách 2: Dùng Console

**Bước 1:** Mở Console (F12)
```
F12 → Console
```

**Bước 2:** Chạy test command
```javascript
// Test 1: Xuất chi tiết bình thường
fetch('ajax/get_trip_details.php?ten_tau=HTL-1&so_chuyen=1')
    .then(r => r.json())
    .then(d => {
        console.log('Response:', d);
        console.log('Success:', d.success);
        console.log('Segments:', d.segments?.length || 0);
        console.log('Cap Them:', d.cap_them?.length || 0);
    })
    .catch(e => console.error('Error:', e));
```

**Bước 3:** Kiểm tra output
```
✅ Response: {...}
✅ Success: true
✅ Segments: 3
✅ Cap Them: 1
```

---

## 📊 BẢNG KIỂM TRA

| Test Case | Input | Expected | Status |
|-----------|-------|----------|--------|
| **Bình thường** | ten_tau=HTL-1, so_chuyen=1 | success=true, segments[] | ✅ |
| **Tàu sai** | ten_tau=INVALID, so_chuyen=1 | success=false, error | ✅ |
| **Chuyến sai** | ten_tau=HTL-1, so_chuyen=999 | success=true, segments=[] | ✅ |
| **Trống** | ten_tau=, so_chuyen= | success=false, error | ✅ |
| **Không có param** | (không có param) | success=false, error | ✅ |

---

## [object Object]ESHOOTING

### Vấn Đề 1: Vẫn Không Hiển Thị Chi Tiết

**Nguyên Nhân Có Thể:**
1. Cache browser cũ
2. File chưa được save
3. Dữ liệu CSV không hợp lệ

**Giải Pháp:**
```
1. Xóa cache: Ctrl+Shift+Delete
2. Reload trang: Ctrl+F5
3. Kiểm tra file: ajax/get_trip_details.php
4. Kiểm tra dữ liệu: data/ket_qua_tinh_toan.csv
```

### Vấn Đề 2: Lỗi JSON Parse

**Nguyên Nhân:**
- Response không phải JSON hợp lệ
- Có HTML/warning trước JSON

**Giải Pháp:**
```
1. Kiểm tra console error
2. Xem Network tab → Response
3. Kiểm tra file không có warning/error
```

### Vấn Đề 3: 404 Not Found

**Nguyên Nhân:**
- File ajax/get_trip_details.php không tồn tại
- Path sai

**Giải Pháp:**
```
1. Kiểm tra file tồn tại
2. Kiểm tra path đúng
3. Kiểm tra permissions
```

---

## 📝 CHECKLIST VERIFY

- [ ] File `ajax/get_trip_details.php` đã được fix
- [ ] Dòng 38: `ten_tau` (không phải `ten_phuong_tien`)
- [ ] Có validation dữ liệu
- [ ] Có error handling chi tiết
- [ ] Có debug info trong response
- [ ] Test xuất chi tiết bình thường ✅
- [ ] Test tàu không tồn tại ✅
- [ ] Test chuyến không tồn tại ✅
- [ ] Test parameter trống ✅
- [ ] Console không có error ✅
- [ ] Network response status 200 ✅
- [ ] Response JSON hợp lệ ✅

---

## 🎯 EXPECTED RESULTS

### Trước Fix ❌
```
- Lỗi: Không hiển thị chi tiết
- Console: undefined is not an object
- Response: Không có dữ liệu
- Debug: Không biết nguyên nhân
```

### Sau Fix ✅
```
- Hiển thị chi tiết bình thường
- Console: Không có error
- Response: JSON hợp lệ với success=true
- Debug: Có debug info để troubleshoot
```

---

## 🚀 NEXT STEPS

1. **Verify Fix** - Chạy tất cả test cases
2. **Monitor** - Theo dõi error logs
3. **Optimize** - Thêm caching nếu cần
4. **Document** - Cập nhật documentation

---

**Status:** ✅ FIX HOÀN THÀNH & READY FOR TEST  
**Ngày:** 2024-12-04  
**Severity:** 🔴 HIGH (Ảnh hưởng tính năng xuất chi tiết)


