# 🚢 Hệ Thống Tính Toán Nhiên Liệu Tàu

[![Version](https://img.shields.io/badge/version-1.3.8-blue.svg)](https://github.com/vicem/tinh-dau-2)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D7.4-777BB4.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-Proprietary-red.svg)](LICENSE)

Hệ thống quản lý và tính toán nhiên liệu sử dụng cho tàu, hỗ trợ tính toán tiêu hao nhiên liệu dựa trên tuyến đường, khối lượng hàng hóa và các hệ số kỹ thuật của tàu.

## 📋 Mục lục

- [What's New](#-whats-new)
- [Tính năng](#-tính-năng)
- [Yêu cầu hệ thống](#-yêu-cầu-hệ-thống)
- [Cài đặt](#-cài-đặt)
- [Cấu hình](#-cấu-hình)
- [Sử dụng](#-sử-dụng)
- [Cấu trúc dự án](#-cấu-trúc-dự-án)
- [Công thức tính toán](#-công-thức-tính-toán)
- [Quản lý dữ liệu](#-quản-lý-dữ-liệu)
- [Báo cáo](#-báo-cáo)
- [Bảo trì & Scripts](#-bảo-trì--scripts)
- [Đóng góp](#-đóng-góp)
- [License](#-license)

## 🆕 What's New

### 1.3.8 · 2025-11-13
- Xuất Excel dựa trên template thống nhất sử dụng `App\Report\HeaderTemplate` và PhpSpreadsheet cho các báo cáo `BCTHANG`, `BC TH`, `DAUTON`, `IN TINH DAU`.
- Thêm cấu hình `config/report_header_registry.php` để ánh xạ động mã báo cáo ↔ tệp template, hỗ trợ fallback an toàn khi thiếu file.
- Cải tiến logic chèn ngày tháng vào header (tự động tìm placeholder, fallback cell tuỳ chỉnh) nhằm đồng nhất định dạng báo cáo.
- Bổ sung script bảo trì `admin/cleanup_he_so_tau.php` giúp lọc hệ số tàu theo phân loại và tự động tạo bản sao lưu trước khi ghi đè.
- Cập nhật tài liệu hướng dẫn cài đặt, cấu hình và quy trình xuất báo cáo theo chuẩn GitHub.

## ✨ Tính năng

### Tính toán nhiên liệu
- ✅ Tính toán tiêu hao nhiên liệu dựa trên công thức: `Q = [(Sch+Skh)×Kkh] + (Sch×D×Kch)`
- ✅ Hỗ trợ tính toán cho tàu có hàng và không hàng
- ✅ Tự động xác định hệ số nhiên liệu theo nhóm cự ly (Ngắn/Trung bình/Dài)
- ✅ Tính toán đổi lệnh với nhiều điểm trung gian
- ✅ Tính toán cấp thêm nhiên liệu (ma nơ, qua cầu, v.v.)

### Quản lý dữ liệu
- ✅ Quản lý danh sách tàu và phân loại (Công ty/Thuê ngoài)
- ✅ Quản lý danh sách điểm và tuyến đường
- ✅ Quản lý hệ số nhiên liệu theo tàu và cự ly
- ✅ Quản lý loại hàng hóa
- ✅ Quản lý cây xăng và dầu tồn

### Báo cáo
- ✅ Xuất báo cáo Excel với template tùy chỉnh
- ✅ Báo cáo dầu tồn theo tháng
- ✅ Lịch sử tính toán và tra cứu
- ✅ Hỗ trợ header template cho các loại báo cáo khác nhau

### Tính năng nâng cao
- ✅ Di chuyển đoạn giữa các chuyến
- ✅ Chỉnh sửa đoạn đã lưu
- ✅ Tự động đồng bộ dữ liệu giữa các báo cáo
- ✅ Ghi chú và metadata cho từng đoạn
- ✅ Tìm kiếm và lọc dữ liệu

## 🔧 Yêu cầu hệ thống

- **PHP**: >= 7.4
- **Web Server**: Apache/Nginx
- **Database**: Không (sử dụng CSV files)
- **Extensions PHP**:
  - `php-xml`
  - `php-zip`
  - `php-gd` (tùy chọn, cho xử lý hình ảnh)

### Dependencies

- `phpoffice/phpspreadsheet`: ^1.29 (Xuất báo cáo Excel)

## 📦 Cài đặt

### 1. Clone repository

```bash
git clone https://github.com/vicem/tinh-dau-2.git
cd tinh-dau-2
```

### 2. Cài đặt dependencies

```bash
composer install
```

### 3. Cấu hình

Chỉnh sửa file `config/database.php` để cấu hình đường dẫn file CSV và các thông số khác.

### 4. Phân quyền thư mục

Đảm bảo thư mục `data/` có quyền ghi:

```bash
chmod -R 755 data/
```

### 5. Cấu hình Web Server

#### Apache (.htaccess)

Đảm bảo mod_rewrite được bật và cấu hình DocumentRoot trỏ đến thư mục dự án.

#### Nginx

```nginx
server {
    listen 80;
    server_name tinh-dau.local;
    root /path/to/tinh-dau-2;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
    }
}
```

## ⚙️ Cấu hình

### File cấu hình chính

- `config/database.php`: Cấu hình đường dẫn file CSV và hằng số hệ thống
- `config/debug.php`: Cấu hình debug mode
- `config/report_header_registry.php`: Khai báo thư mục gốc và map template header cho từng loại báo cáo (hỗ trợ fallback `_default/header.xlsx`)

### Các file dữ liệu CSV

- `bang_he_so_tau_cu_ly_full_v2.csv`: Bảng hệ số nhiên liệu theo tàu và cự ly
- `khoang_duong.csv`: Khoảng cách giữa các điểm
- `data/ket_qua_tinh_toan.csv`: Lưu kết quả tính toán
- `data/tau_phan_loai.csv`: Phân loại tàu (Công ty/Thuê ngoài)
- `data/cay_xang.csv`: Danh sách cây xăng
- `data/dau_ton.csv`: Dữ liệu dầu tồn
- `data/loai_hang.csv`: Danh sách loại hàng

### Template Header

Các template header Excel được lưu trong `template_header/`:
- `_default/`: Template mặc định
- `sample_header_*.xlsx`: Các template mẫu cho từng loại báo cáo

## 🚀 Sử dụng

### Tính toán nhiên liệu

1. Truy cập trang chủ (`index.php`)
2. Chọn tàu từ danh sách
3. Chọn mã chuyến (hoặc tạo chuyến mới)
4. Nhập thông tin:
   - Điểm bắt đầu
   - Điểm kết thúc (hoặc điểm đổi lệnh)
   - Khối lượng hàng hóa
   - Ngày đi/đến/dỡ xong
5. Nhấn "Tính Toán Nhiên Liệu" để xem kết quả
6. Nhấn "Lưu Kết Quả" để lưu vào hệ thống

### Đổi lệnh

1. Bật checkbox "Đổi lệnh trong chuyến"
2. Nhập điểm kết thúc dự kiến (điểm B - nơi đổi lệnh)
3. Thêm các điểm đến mới (C, D, E, ...)
4. Nhập tổng khoảng cách thực tế
5. Tính toán và lưu

### Cấp thêm nhiên liệu

1. Bật checkbox "Cấp thêm"
2. Chọn loại: Ma nơ / Qua cầu / Khác
3. Nhập địa điểm và số lượng
4. Lưu kết quả

### Quản lý dữ liệu

Truy cập các trang quản lý trong thư mục `admin/`:
- `quan_ly_tau.php`: Quản lý danh sách tàu
- `quan_ly_tuyen_duong.php`: Quản lý tuyến đường
- `quan_ly_loai_hang.php`: Quản lý loại hàng
- `quan_ly_cay_xang.php`: Quản lý cây xăng
- `quan_ly_dau_ton.php`: Quản lý dầu tồn

### Xuất báo cáo

1. Truy cập `admin/bao_cao_dau_ton.php`
2. Chọn tháng báo cáo
3. Chọn loại báo cáo và template
4. Nhấn "Xuất Excel"

## 📁 Cấu trúc dự án

```
tinh-dau-2/
├── admin/                  # Trang quản trị
│   ├── bao_cao_dau_ton.php
│   ├── quan_ly_tau.php
│   ├── quan_ly_tuyen_duong.php
│   └── ...
├── ajax/                   # API endpoints
│   ├── get_trips.php
│   ├── get_trip_details.php
│   └── ...
├── api/                    # API xử lý dữ liệu
│   ├── update_segment.php
│   ├── move_segment.php
│   └── ...
├── assets/                 # Tài nguyên tĩnh
│   ├── logo.png
│   ├── ux-enhancements.css
│   └── ux-enhancements.js
├── config/                 # Cấu hình
│   ├── database.php
│   └── debug.php
├── data/                   # Dữ liệu CSV
│   ├── ket_qua_tinh_toan.csv
│   ├── tau_phan_loai.csv
│   └── ...
├── includes/               # Thư viện và helpers
│   ├── header.php
│   ├── footer.php
│   ├── helpers.php
│   ├── excel_export_full.php
│   └── ...
├── models/                 # Models
│   ├── TinhToanNhienLieu.php
│   ├── LuuKetQua.php
│   ├── TauPhanLoai.php
│   └── ...
├── src/                    # Source code
│   └── Report/
│       └── HeaderTemplate.php
├── template_header/        # Excel templates
│   ├── _default/
│   └── sample_header_*.xlsx
├── index.php              # Trang chủ
├── lich_su.php           # Lịch sử tính toán
├── composer.json          # Dependencies
└── README.md             # Tài liệu này
```

## 📐 Công thức tính toán

### Công thức chính

```
Q = [(Sch + Skh) × Kkh] + (Sch × D × Kch)
```

Trong đó:
- **Q**: Nhiên liệu tiêu thụ (Lít)
- **Sch**: Quãng đường có hàng (Km)
- **Skh**: Quãng đường không hàng (Km)
- **Kkh**: Hệ số không hàng (Lít/Km)
- **Kch**: Hệ số có hàng (Lít/T.Km)
- **D**: Khối lượng hàng hóa (Tấn)

### Phân loại cự ly

- **Ngắn**: ≤ 80 km
- **Trung bình**: 80 < x ≤ 200 km
- **Dài**: > 200 km

Hệ số nhiên liệu được tra cứu từ bảng `bang_he_so_tau_cu_ly_full_v2.csv` dựa trên:
- Tên tàu
- Nhóm cự ly (Ngắn/Trung bình/Dài)

## 💾 Quản lý dữ liệu

### Format CSV

Tất cả dữ liệu được lưu dưới dạng CSV với encoding UTF-8.

### Cấu trúc dữ liệu chính

#### ket_qua_tinh_toan.csv
- `ten_phuong_tien`: Tên tàu
- `so_chuyen`: Mã chuyến
- `diem_di`: Điểm đi
- `diem_den`: Điểm đến
- `cu_ly_co_hang_km`: Quãng đường có hàng
- `cu_ly_khong_hang_km`: Quãng đường không hàng
- `dau_tinh_toan_lit`: Nhiên liệu tính toán
- `khoi_luong_van_chuyen_t`: Khối lượng vận chuyển
- `ngay_di`, `ngay_den`, `ngay_do_xong`: Các ngày
- `thang_bao_cao`: Tháng báo cáo
- `created_at`: Thời gian tạo

## 📊 Báo cáo

### Hệ thống template header Excel
- Tất cả báo cáo Excel nay sử dụng lớp `App\Report\HeaderTemplate` để tải template chuẩn từ thư mục `template_header/`.
- Map template được định nghĩa trong `config/report_header_registry.php`, có thể bổ sung/ghi đè bằng cách thêm file `.xlsx` và cập nhật key tương ứng (`BCTHANG`, `BC_TH`, `DAUTON`, `IN_TINH_DAU`, ...).
- Hàm `HeaderTemplate::applyCommonHeader()` tự động thay thế placeholder ngày tháng trong header hoặc ghi vào ô fallback (mặc định `F4`).
- Khi thiếu file template, hệ thống ghi log và sử dụng file dự phòng `_default/header.xlsx` nếu có.
- Các hàm export mới trong `includes/excel_export_full.php` và `includes/excel_export_wrapper.php` đảm bảo giữ nguyên logo, merge cells, style và auto-size cột.

### Báo cáo dầu tồn

Xuất báo cáo Excel với các tính năng:
- Template header tùy chỉnh
- Tự động tính toán tổng hợp
- Hỗ trợ nhiều loại báo cáo:
  - BC TH (Báo cáo tháng)
  - BCTHANG
  - DAUTON (Dầu tồn)
  - IN TINH DAU (In tính dầu)

### Lịch sử tính toán

Truy cập `lich_su.php` để:
- Xem lịch sử tất cả các tính toán
- Tìm kiếm và lọc theo tàu, chuyến, tháng
- Xuất dữ liệu ra Excel

## 🛠️ Bảo trì & Scripts

- **`admin/cleanup_he_so_tau.php`**: Script chạy một lần để dọn bảng hệ số (`bang_he_so_tau_cu_ly_full_v2.csv`). Khi truy cập bằng trình duyệt:
  - Tự động tạo bản sao lưu trong `data/he_so_tau_backup_YYYYmmdd_HHMMSS.csv`.
  - Chỉ giữ các tàu thuộc phân loại `cong_ty` có trong `data/tau_phan_loai.csv`, đồng thời giữ nguyên tàu thuê ngoài hoặc chưa phân loại.
  - Trả về HTTP 500 cùng thông điệp lỗi nếu thiếu dữ liệu bắt buộc hoặc không ghi được file tạm.
- **Thư mục `backup/`**: Lưu trữ bản sao dự phòng. Khuyến nghị đồng bộ hóa với quy trình bảo trì định kỳ.
- **Quy trình đề xuất**:
  1. Sao lưu toàn bộ thư mục `data/`.
  2. Xác minh quyền ghi cho các file CSV.
  3. Chạy script trên môi trường staging trước khi áp dụng production.

## 🤝 Đóng góp

1. Fork dự án
2. Tạo feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit các thay đổi (`git commit -m 'Add some AmazingFeature'`)
4. Push lên branch (`git push origin feature/AmazingFeature`)
5. Mở Pull Request

### Quy tắc commit

Sử dụng [Conventional Commits](https://www.conventionalcommits.org/):
- `feat`: Tính năng mới
- `fix`: Sửa lỗi
- `docs`: Cập nhật tài liệu
- `style`: Formatting, thiếu semicolon, v.v.
- `refactor`: Refactor code
- `test`: Thêm/sửa test
- `chore`: Cập nhật build tasks, v.v.

## 📝 License

Proprietary - Tất cả quyền được bảo lưu.

## 👥 Tác giả

**VICEM** - Hệ thống tính toán nhiên liệu tàu

## 🔌 API Endpoints

Hệ thống cung cấp các API endpoints để tương tác với dữ liệu:

### AJAX Endpoints (`ajax/`)
- `get_trips.php`: Lấy danh sách chuyến của tàu
- `get_trip_details.php`: Lấy chi tiết chuyến cụ thể

### API Endpoints (`api/`)
- `search_diem.php`: Tìm kiếm điểm
- `get_ma_chuyen.php`: Lấy mã chuyến cao nhất
- `update_segment.php`: Cập nhật đoạn
- `move_segment.php`: Di chuyển đoạn giữa các chuyến
- `update_transfer.php`: Cập nhật thông tin chuyển đoạn
- `delete_transfer.php`: Xóa đoạn
- `add_loai_hang.php`: Thêm loại hàng
- `get_loai_hang.php`: Lấy danh sách loại hàng
- `update_cay_xang.php`: Cập nhật cây xăng
- `update_thang_bao_cao.php`: Cập nhật tháng báo cáo
- `save_order_overrides.php`: Lưu thứ tự sắp xếp
- `delete_dau_ton.php`: Xóa dầu tồn

### Format Response

Tất cả API trả về JSON với format:
```json
{
  "success": true,
  "data": {...},
  "message": "Thông báo"
}
```

## 📝 Ví dụ sử dụng

### Ví dụ 1: Tính toán nhiên liệu cơ bản

```php
require_once 'models/TinhToanNhienLieu.php';

$tinhToan = new TinhToanNhienLieu();
$ketQua = $tinhToan->tinhNhienLieu(
    'Tàu A',           // Tên tàu
    'Cảng Sài Gòn',    // Điểm bắt đầu
    'Cảng Đà Nẵng',    // Điểm kết thúc
    500                // Khối lượng (tấn)
);

echo "Nhiên liệu cần: " . $ketQua['nhien_lieu_lit'] . " lít";
```

### Ví dụ 2: Tính toán đổi lệnh

```php
$ketQua = $tinhToan->tinhNhienLieuDoiLenh(
    'Tàu A',
    'Cảng Sài Gòn',      // Điểm A
    'Cảng Vũng Tàu',     // Điểm B (đổi lệnh)
    'Cảng Nha Trang',    // Điểm C (điểm đến mới)
    500,                 // Khối lượng
    350                  // Khoảng cách thực tế (km)
);
```

### Ví dụ 3: Lưu kết quả tính toán

```php
require_once 'models/LuuKetQua.php';

$luuKetQua = new LuuKetQua();
$data = [
    'ten_phuong_tien' => 'Tàu A',
    'so_chuyen' => 1,
    'diem_di' => 'Cảng Sài Gòn',
    'diem_den' => 'Cảng Đà Nẵng',
    'dau_tinh_toan_lit' => 1500,
    // ... các trường khác
];

$saved = $luuKetQua->luu($data);
```

## 🐛 Troubleshooting

### Lỗi thường gặp

#### 1. Lỗi "Không tìm thấy tuyến đường"

**Nguyên nhân**: Chưa có tuyến đường giữa hai điểm trong hệ thống.

**Giải pháp**:
- Truy cập `admin/quan_ly_tuyen_duong.php`
- Thêm tuyến đường mới với điểm đầu, điểm cuối và khoảng cách

#### 2. Lỗi "Không tìm thấy hệ số nhiên liệu"

**Nguyên nhân**: Chưa có hệ số nhiên liệu cho tàu với khoảng cách tương ứng.

**Giải pháp**:
- Kiểm tra file `bang_he_so_tau_cu_ly_full_v2.csv`
- Thêm hệ số cho tàu và nhóm cự ly tương ứng

#### 3. Lỗi quyền ghi file CSV

**Nguyên nhân**: Thư mục `data/` không có quyền ghi.

**Giải pháp**:
```bash
chmod -R 755 data/
chown -R www-data:www-data data/  # Linux
```

#### 4. Lỗi export Excel không hoạt động

**Nguyên nhân**: Thiếu extension PHP hoặc lỗi template.

**Giải pháp**:
- Kiểm tra `php-xml` và `php-zip` đã được cài đặt
- Kiểm tra template header trong `template_header/`
- Xem log lỗi PHP để biết chi tiết

#### 5. Lỗi encoding UTF-8

**Nguyên nhân**: File CSV không được lưu với encoding UTF-8.

**Giải pháp**:
- Đảm bảo tất cả file CSV được lưu với encoding UTF-8
- Sử dụng BOM UTF-8 nếu cần thiết

## ⚡ Performance

### Tối ưu hiệu năng

1. **Cache dữ liệu**: Hệ thống tự động cache dữ liệu CSV trong memory
2. **Lazy loading**: Chỉ load dữ liệu khi cần thiết
3. **Index CSV**: Sử dụng index để tìm kiếm nhanh hơn

### Giới hạn

- **File CSV lớn**: Với file > 10MB, có thể chậm khi đọc/ghi
- **Số lượng tàu**: Hệ thống hỗ trợ tốt với < 1000 tàu
- **Số lượng tuyến**: Hỗ trợ tốt với < 5000 tuyến

### Khuyến nghị

- Backup định kỳ file CSV trong thư mục `data/`
- Sử dụng cron job để backup tự động
- Xem xét nâng cấp lên database nếu dữ liệu lớn

## 🧪 Testing

### Test thủ công

1. **Test tính toán cơ bản**:
   - Chọn tàu có hệ số đầy đủ
   - Nhập tuyến đường có sẵn
   - Kiểm tra kết quả tính toán

2. **Test đổi lệnh**:
   - Tạo chuyến với đổi lệnh
   - Kiểm tra hiển thị route đầy đủ
   - Kiểm tra tính toán đúng

3. **Test cấp thêm**:
   - Tạo lệnh cấp thêm
   - Kiểm tra lý do tự động
   - Kiểm tra lưu vào chuyến

### Test tự động (nếu có)

```bash
# Chạy test suite (nếu có)
php vendor/bin/phpunit
```

## 🔒 Security

### Bảo mật

- **Input validation**: Tất cả input được validate trước khi xử lý
- **XSS protection**: Sử dụng `htmlspecialchars()` cho output
- **CSRF protection**: Sử dụng token cho các form quan trọng
- **File upload**: Chỉ cho phép upload file Excel với validation

### Khuyến nghị

- Không expose thư mục `data/` ra ngoài
- Sử dụng HTTPS trong production
- Giới hạn quyền truy cập admin
- Backup dữ liệu định kỳ

## 🛠️ Development

### Setup môi trường phát triển

```bash
# Clone repository
git clone https://github.com/vicem/tinh-dau-2.git
cd tinh-dau-2

# Cài đặt dependencies
composer install

# Cấu hình local
cp config/database.php.example config/database.php
# Chỉnh sửa config/database.php

# Tạo thư mục data nếu chưa có
mkdir -p data
chmod 755 data
```

### Coding Standards

- Tuân thủ PSR-12 coding standard
- Sử dụng type hints cho PHP 7.4+
- Comment đầy đủ cho các hàm public
- Sử dụng meaningful variable names

### Debug Mode

Bật debug mode trong `config/debug.php`:

```php
define('DEBUG_MODE', true);
define('ERROR_REPORTING', E_ALL);
```

## 📈 Roadmap

### Phiên bản tiếp theo

- [ ] Nâng cấp lên database (MySQL/PostgreSQL)
- [ ] API RESTful đầy đủ
- [ ] Authentication và Authorization
- [ ] Multi-language support
- [ ] Mobile responsive design
- [ ] Real-time notifications
- [ ] Advanced reporting với charts
- [ ] Import/Export dữ liệu nâng cao
- [ ] Audit log đầy đủ
- [ ] Unit tests và Integration tests

### Đang phát triển

- [ ] Cải thiện performance cho file CSV lớn
- [ ] Thêm validation nâng cao
- [ ] Cải thiện UX/UI

## ❓ FAQ

### Q: Có thể sử dụng database thay vì CSV không?

A: Hiện tại hệ thống chỉ hỗ trợ CSV. Có thể nâng cấp lên database trong tương lai.

### Q: Làm thế nào để backup dữ liệu?

A: Backup toàn bộ thư mục `data/` và các file CSV trong root.

### Q: Có thể thêm nhiều loại báo cáo không?

A: Có, thêm template mới vào `template_header/` và cập nhật code export.

### Q: Hệ thống hỗ trợ bao nhiêu tàu?

A: Không có giới hạn cứng, nhưng khuyến nghị < 1000 tàu để đảm bảo performance.

### Q: Làm thế nào để migrate dữ liệu từ hệ thống cũ?

A: Chuyển đổi dữ liệu sang format CSV và import vào thư mục `data/`.

## 📞 Liên hệ

Để biết thêm thông tin, vui lòng liên hệ qua:
- Email: [your-email@example.com]
- Website: [your-website.com]
- Issues: [GitHub Issues](https://github.com/vicem/tinh-dau-2/issues)

## 🙏 Acknowledgments

- [PhpSpreadsheet](https://github.com/PHPOffice/PhpSpreadsheet) - Thư viện xuất Excel
- Bootstrap - Framework CSS
- Font Awesome - Icons

---

**Lưu ý**: Dự án này sử dụng CSV files để lưu trữ dữ liệu. Để nâng cấp lên database (MySQL/PostgreSQL), vui lòng tham khảo phần [Migration Guide](docs/MIGRATION.md) (nếu có).

**Version**: 1.3.8 | **Last Updated**: 2025-11

