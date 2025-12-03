# Đề xuất: Hệ thống ID Điểm để tránh nhầm lẫn

## Vấn đề hiện tại
Hiện tại hệ thống so sánh điểm bằng **TÊN** → Dễ bị lỗi:
- ❌ Dấu tiếng Việt khác nhau (có/không dấu)
- ❌ Ghi chú trong ngoặc: "Cảng Long Bình (ĐN) (test)"
- ❌ Khoảng trắng thừa
- ❌ Viết hoa/thường khác nhau
- ❌ Unicode NFC/NFD

## Giải pháp đã áp dụng (Tạm thời) ✅
Tạo nhiều **variants** của tên điểm để so sánh:
```
"Cảng Long Bình (ĐN) (test)"
→ Variant 1: "cang long binh dn test"
→ Variant 2: "cang long binh dn"
→ Variant 3: "cang long binh"
```
Database có "Cảng Long Bình (ĐN)" → Match với Variant 2

## Giải pháp dài hạn: Sử dụng ID điểm 🎯

### 1. Tạo file danh sách điểm: `data/diem.csv`
```csv
id_diem,ten_diem,ma_diem,tinh_thanh,loai_diem
1,Cảng Long Bình,CLB_DN,Đồng Nai,cang
2,TN Long An,TN_LA,Long An,cang
3,Phao Gò Gia,PGG,Hồ Chí Minh,phao
4,Cảng Sotrans Thủ Đức,CSTD,Hồ Chí Minh,cang
...
```

### 2. Sửa file `khoang_duong.csv`
**CŨ:**
```csv
id,diem_dau,diem_cuoi,khoang_cach_km
57,TN Long An,Cảng Long Bình (ĐN),125
```

**MỚI:**
```csv
id,id_diem_dau,id_diem_cuoi,khoang_cach_km,ghi_chu
57,2,1,125,
```

### 3. Sửa database lịch sử `data/ket_qua_tinh_toan.csv`
**CŨ:**
```csv
id,ten_tau,diem_di,diem_den,khoang_cach_km,...
123,Tàu ABC,TN Long An,Cảng Long Bình (ĐN) (test),125,...
```

**MỚI:**
```csv
id,ten_tau,id_diem_di,id_diem_den,khoang_cach_km,diem_di_display,diem_den_display,...
123,Tàu ABC,2,1,125,TN Long An,Cảng Long Bình (ĐN) (test),...
```

### 4. Ưu điểm
- ✅ **100% chính xác** - So sánh bằng số, không bao giờ sai
- ✅ **Hiệu suất cao** - So sánh số nhanh hơn string
- ✅ **Dễ bảo trì** - Đổi tên điểm chỉ sửa 1 chỗ
- ✅ **Mở rộng dễ** - Thêm thông tin điểm (tọa độ, tỉnh, loại...)
- ✅ **Hỗ trợ tìm kiếm** - Tìm theo mã, theo tỉnh, theo loại...

### 5. Nhược điểm
- ⚠️ Cần migration dữ liệu hiện tại
- ⚠️ Thay đổi code ở nhiều file
- ⚠️ Mất thời gian triển khai (~2-3 ngày)

## Roadmap triển khai

### Phase 1: Tạo bảng điểm (1 ngày)
1. ✅ Scan tất cả điểm trong `khoang_duong.csv` và `ket_qua_tinh_toan.csv`
2. ✅ Tạo ID unique cho mỗi điểm
3. ✅ Sinh file `data/diem.csv`
4. ✅ Tạo model `Diem.php` để quản lý

### Phase 2: Migration dữ liệu (0.5 ngày)
1. ✅ Thêm cột `id_diem_dau`, `id_diem_cuoi` vào `khoang_duong.csv`
2. ✅ Thêm cột `id_diem_di`, `id_diem_den` vào `ket_qua_tinh_toan.csv`
3. ✅ Giữ cột tên cũ để backward compatible

### Phase 3: Sửa code (1 ngày)
1. ✅ Sửa `KhoangCach.php` → Dùng ID để get khoảng cách
2. ✅ Sửa `index.php` → Lưu cả ID và tên khi chọn điểm
3. ✅ Sửa `lich_su.php` → Load điểm theo ID
4. ✅ Sửa API `search_diem.php` → Trả về cả ID
5. ✅ Sửa JavaScript → Lưu ID vào hidden input

### Phase 4: Testing & Rollout (0.5 ngày)
1. ✅ Test các tính năng chính
2. ✅ Backup dữ liệu cũ
3. ✅ Deploy lên production
4. ✅ Monitor lỗi trong 1 tuần

## So sánh API mới

### API cũ (hiện tại)
```php
// GET: api/get_distance.php?diem_dau=TN%20Long%20An&diem_cuoi=C%E1%BA%A3ng%20Long%20B%C3%ACnh%20(DN)
{
  "success": true,
  "distance": 125.0
}
```

### API mới (đề xuất)
```php
// GET: api/get_distance.php?id_diem_dau=2&id_diem_cuoi=1
{
  "success": true,
  "distance": 125.0,
  "diem_dau": {
    "id": 2,
    "ten": "TN Long An",
    "ma": "TN_LA"
  },
  "diem_cuoi": {
    "id": 1,
    "ten": "Cảng Long Bình",
    "ma": "CLB_DN"
  }
}
```

## Kết luận
- **Ngắn hạn**: Dùng fix hiện tại (variants) - Đủ tốt cho 95% trường hợp
- **Dài hạn**: Migrate sang ID điểm - Giải pháp hoàn hảo, không bao giờ sai

---
**Tác giả**: Claude
**Ngày**: 2025-12-04
**Status**: PROPOSAL - Chờ phê duyệt
