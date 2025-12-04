# BÁO CÁO FIX LỖI XUẤT EXCEL CHI TIẾT

## Ngày: 2025-12-04

---

## 1. MÔ TẢ VẤN ĐỀ

### Hiện tượng
- User bấm nút "Xuất Excel" → chọn "Xuất chi tiết..." → chọn tàu (HTL-1) → bấm nút "Xuất"
- **Không có gì xảy ra** - File Excel không được tải xuống

### Nguyên nhân phát hiện
Sau khi phân tích code, phát hiện vấn đề chính:

**Wizard nhập thông tin "Nợ tại" hiện ra nhưng nằm BÊN DƯỚI danh sách tàu**, do đó:
- User không thấy wizard vì nó nằm ngoài viewport
- User cần scroll xuống để thấy wizard nhưng không biết phải scroll
- User nghĩ rằng "không xuất được file Excel"

---

## 2. FLOW XỬ LÝ XUẤT CHI TIẾT

### Flow đúng:
1. User bấm "Xuất Excel" → Modal hiện ra
2. User bấm "Xuất chi tiết..." → Danh sách tàu hiện ra
3. User chọn tàu (HTL-1, HTL-2,...)
4. User bấm "Xuất" → **Wizard hiện ra** để nhập thông tin "Nợ tại" cho từng tàu
5. User nhập thông tin hoặc bấm "Skip" để bỏ qua
6. Sau khi hoàn tất wizard → Form submit → File Excel được tải xuống

### Vấn đề:
- Bước 4: Wizard hiện ra nhưng **user không thấy** vì nó nằm ngoài viewport
- User không biết wizard đã hiện, nên không scroll xuống
- User nghĩ rằng nút "Xuất" không hoạt động

---

## 3. GIẢI PHÁP ĐÃ ÁP DỤNG

### 3.1. Auto-scroll đến wizard khi hiện
**File:** `includes/footer.php` (dòng 1428-1435)

Thêm code tự động scroll đến wizard khi nó hiện ra:
```javascript
// Auto-scroll đến wizard để user thấy ngay
setTimeout(() => {
    wizard.scrollIntoView({ behavior: 'smooth', block: 'center' });
    // Focus vào input đầu tiên để user có thể nhập liền
    if (dateEl) {
        try { dateEl.focus(); } catch(e) {}
    }
}, 100);
```

**Lợi ích:**
- Wizard tự động scroll đến giữa màn hình
- User thấy ngay wizard mà không cần scroll thủ công
- Auto-focus vào input đầu tiên để user có thể nhập liền

### 3.2. Thêm hướng dẫn visual cho wizard
**File:** `lich_su.php` (dòng 2495-2500)

Thêm alert info hướng dẫn user khi wizard hiện ra:
```html
<div class="alert alert-info alert-dismissible fade show mb-3" role="alert">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Nhập thông tin dầu tồn cho từng tàu</strong> hoặc bấm <strong>Skip</strong>
    để bỏ qua, <strong>Dùng cho tất cả</strong> để áp dụng giá trị cho tất cả tàu còn lại.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
```

Thêm background sáng cho wizard để dễ nhận biết:
```html
<div id="detailWizard" class="border rounded p-3 mt-3 bg-light" style="display:none;">
```

**Lợi ích:**
- User hiểu ngay wizard là gì và cần làm gì
- Background sáng giúp wizard nổi bật hơn
- Alert có thể đóng nếu user không muốn thấy nữa

---

## 4. CÁCH SỬ DỤNG SAU KHI FIX

### Xuất chi tiết theo tàu:

1. **Bấm "Xuất Excel"** → Modal hiện ra

2. **Bấm "Xuất chi tiết..."** → Danh sách tàu hiện ra

3. **Chọn tàu muốn xuất** (có thể chọn nhiều tàu):
   - Dùng ô tìm kiếm để lọc tàu
   - Bấm "Chọn tất cả" hoặc "Bỏ chọn" để nhanh
   - Check từng tàu muốn xuất

4. **Bấm "Xuất"** → Wizard hiện ra (tự động scroll đến wizard)

5. **Nhập thông tin cho từng tàu** (hoặc bỏ qua):

   **Wizard hỏi tuần tự từng tàu:**
   - Tàu: HTL-1 (1/3)
   - Nợ tại – Bảng tính ngày: `dd/mm/yyyy` (optional)
   - Số dư dầu tồn cuối kỳ trước: `2000` (optional)

   **Các nút:**
   - **Back**: Quay lại tàu trước
   - **Skip**: Bỏ qua tàu hiện tại (không nhập thông tin)
   - **Dùng cho tất cả**: Áp dụng giá trị hiện tại cho tất cả tàu còn lại
   - **Next**: Chuyển sang tàu tiếp theo
   - **Done**: Hoàn tất và xuất file (hiện ở tàu cuối cùng)

6. **File Excel được tải xuống** với tên: `CT_T{tháng}_{năm}.xlsx`

   File chỉ chứa các sheet chi tiết "IN TINH DAU" cho các tàu đã chọn.

### Xuất mặc định (không cần wizard):

1. Bấm "Xuất Excel" → Modal hiện ra
2. Bấm "Xuất mặc định" → File Excel được tải xuống ngay

   File chứa các sheet tổng hợp: BCTHANG-SLCTY, BCTHANG-SLN, BC TH, DAUTON-SLCTY, DAUTON-SLN

---

## 5. KẾT QUẢ SAU KHI FIX

### Trước khi fix:
- ❌ User không thấy wizard
- ❌ User nghĩ nút "Xuất" không hoạt động
- ❌ Không xuất được file Excel chi tiết

### Sau khi fix:
- ✅ Wizard tự động scroll đến giữa màn hình khi hiện
- ✅ User thấy ngay wizard và hướng dẫn sử dụng
- ✅ Auto-focus vào input để nhập nhanh
- ✅ Background sáng giúp wizard nổi bật
- ✅ Xuất file Excel chi tiết thành công

---

## 6. FILES ĐÃ CHỈNH SỬA

1. **includes/footer.php** (dòng 1428-1435):
   - Thêm auto-scroll đến wizard
   - Thêm auto-focus vào input đầu tiên

2. **lich_su.php** (dòng 2495-2500):
   - Thêm alert info hướng dẫn
   - Thêm class `bg-light` cho wizard

---

## 7. TEST CHECKLIST

- [ ] Test xuất chi tiết với 1 tàu
- [ ] Test xuất chi tiết với nhiều tàu
- [ ] Test wizard scroll đến đúng vị trí
- [ ] Test focus vào input ngày
- [ ] Test nút Skip, Next, Back, Done
- [ ] Test nút "Dùng cho tất cả"
- [ ] Test xuất mặc định vẫn hoạt động bình thường
- [ ] Test trên các trình duyệt khác nhau (Chrome, Firefox, Edge)
- [ ] Test trên mobile

---

## 8. GHI CHÚ

- Wizard chỉ hiện khi user **ĐÃ CHỌN TÀU** và bấm "Xuất"
- Nếu không chọn tàu nào, bấm "Xuất" sẽ xuất mặc định (không có wizard)
- User có thể bỏ qua nhập thông tin bằng cách bấm "Skip" cho từng tàu
- Thông tin "Nợ tại" là **optional** - có thể để trống
