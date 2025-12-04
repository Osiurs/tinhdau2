KIẾN TRÚC KỸTHẬT DỰ ÁN TINH-DAU-2

## 🏗️ KIẾN TRÚC TỔNG THỂ

```
┌─────────────────────────────────────────────────────────────────┐
│                      NGƯỜI DÙNG (BROWSER)                       │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         │ HTTP Request
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│                    WEB SERVER (Apache/Nginx)                    │
│                     + PHP 7.4+ Runtime                          │
└────────────────────────┬────────────────────────────────────────┘
                         │
        ┌────────────────┼────────────────┐
        ▼                ▼                ▼
┌──────────────┐  ┌──────────────┐  ┌──────────────┐
│   Pages      │  │   API        │  │   Auth       │
│   (PHP)      │  │   (AJAX)     │  │   (Session)  │
│              │  │              │  │              │
│ - index.php  │  │ - search_*   │  │ - login.php  │
│ - admin/*    │  │ - update_*   │  │ - logout.php │
│ - danh_sach* │  │ - delete_*   │  │ - check_*    │
└──────┬───────┘  └──────┬───────┘  └──────┬───────┘
       │                 │                 │
       └─────────────────┼─────────────────┘
                         │
                         ▼
        ┌────────────────────────────────┐
        │      BUSINESS LOGIC LAYER      │
        │      (Models - PHP Classes)    │
        │                                │
        │ - TinhToanNhienLieu.php        │
        │ - DauTon.php                   │
        │ - HeSoTau.php                  │
        │ - KhoangCach.php               │
        │ - LuuKetQua.php                │
        │ - User.php                     │
        │ - LoaiHang.php                 │
        │ - CayXang.php                  │
        │ - TauPhanLoai.php              │
        │ - Logger.php                   │
        └────────────────┬───────────────┘
                         │
                         ▼
        ┌────────────────────────────────┐
        │      DATA ACCESS LAYER         │
        │      (CSV File I/O)            │
        │                                │
        │ - readCsv()                    │
        │ - writeCsv()                   │
        │ - appendCsv()                  │
        │ - updateCsv()                  │
        │ - deleteCsv()                  │
        └────────────────┬───────────────┘
                         │
        ┌────────────────┼────────────────┐
        ▼                ▼                ▼
    ┌────────┐      ┌────────┐      ┌────────┐
    │ CSV    │      │ JSON   │      │ LOG    │
    │ Files  │      │ Files  │      │ Files  │
    └────────┘      └────────┘      └────────┘
```

---

## 📦 LAYER ARCHITECTURE

### 1. **Presentation Layer (Tầng Giao Diện)**

#### Pages (PHP Templates)
```
admin/
├── index.php                    # Dashboard
├── quan_ly_tau.php             # Quản lý tàu
├── quan_ly_dau_ton.php         # Quản lý dầu tồn
├── quan_ly_tuyen_duong.php     # Quản lý tuyến đường
├── quan_ly_loai_hang.php       # Quản lý loại hàng
├── quan_ly_cay_xang.php        # Quản lý cây xăng
├── quan_ly_user.php            # Quản lý người dùng
└── bao_cao_dau_ton.php         # Báo cáo dầu tồn

Public/
├── index.php                    # Trang chủ
├── danh_sach_tau.php           # Danh sách tàu
├── danh_sach_diem.php          # Danh sách điểm
└── lich_su.php                 # Lịch sử giao dịch
```

---

### 2. **Application Layer (Tầng Ứng Dụng)**

#### API Endpoints (AJAX)
```
api/
├── search_diem.php              # Tìm kiếm điểm
├── get_loai_hang.php            # Lấy loại hàng
├── get_ma_chuyen.php            # Lấy mã chuyến
├── update_segment.php           # Cập nhật đoạn
├── update_transfer.php          # Cập nhật chuyển dầu
├── update_tinh_chinh.php        # Cập nhật tính chính
├── update_cay_xang.php          # Cập nhật cây xăng
├── delete_transfer.php          # Xóa chuyển dầu
├── delete_dau_ton.php           # Xóa dầu tồn
├── move_segment.php             # Di chuyển đoạn
├── save_order_overrides.php     # Lưu ghi đè thứ tự
└── add_loai_hang.php            # Thêm loại hàng
```

---

### 3. **Business Logic Layer (Tầng Logic Kinh Doanh)**

#### Core Models
```
models/
├── TinhToanNhienLieu.php        # Tính toán nhiên liệu
├── DauTon.php                   # Quản lý dầu tồn
├── HeSoTau.php                  # Quản lý hệ số tàu
├── KhoangCach.php               # Quản lý khoảng cách
├── LuuKetQua.php                # Lưu kết quả tính toán
├── User.php                     # Quản lý người dùng
├── LoaiHang.php                 # Quản lý loại hàng
├── CayXang.php                  # Quản lý cây xăng
├── TauPhanLoai.php              # Phân loại tàu
└── Logger.php                   # Ghi log
```

---

### 4. **Data Access Layer (Tầng Truy Cập Dữ Liệu)**

#### CSV File Operations
```
Data Access Methods:
├── readCsv()                    # Đọc file CSV
├── writeCsv()                   # Ghi file CSV
├── appendCsv()                  # Thêm dòng vào CSV
├── updateCsv()                  # Cập nhật dòng CSV
└── deleteCsv()                  # Xóa dòng CSV
```

---

### 5. **Data Layer (Tầng Dữ Liệu)**

#### CSV Files
```
data/
├── users.csv                    # Người dùng
├── dau_ton.csv                  # Dầu tồn
├── cay_xang.csv                 # Cây xăng
├── loai_hang.csv                # Loại hàng
├── tau_phan_loai.csv            # Phân loại tàu
├── ket_qua_tinh_toan.csv        # Kết quả tính toán
└── *.log                        # Log files

Root CSV Files:
├── bang_he_so_tau_cu_ly_full_v2.csv  # Hệ số tàu
└── khoang_duong.csv             # Khoảng cách
```

---

## 🔐 SECURITY ARCHITECTURE

### Authentication Flow
```
Login Page
    │
    ├─ Username + Password
    │
    ▼
User::authenticate()
    │
    ├─ Kiểm tra username tồn tại
    ├─ Verify password (password_verify)
    └─ Trả về User object
    │
    ▼
loginUser()
    │
    ├─ Tạo session
    ├─ Lưu user ID
    └─ Lưu role (admin/user)
    │
    ▼
Redirect to Home
    │
    └─ Chuyển hướng về index.php
```

---

## 🔌 API ARCHITECTURE

### RESTful API Endpoints

#### Search API
```
GET /api/search_diem.php
    ├─ Parameters:
    │  ├─ q: keyword (string)
    │  └─ diem_dau: optional (string)
    │
    └─ Response:
       └─ JSON array of points
```

#### CRUD API
```
POST /api/update_segment.php
    ├─ Body:
    │  ├─ idx: index (int)
    │  ├─ ten_tau: ship name (string)
    │  ├─ diem_bat_dau: start point (string)
    │  ├─ diem_ket_thuc: end point (string)
    │  └─ khoi_luong: weight (float)
    │
    └─ Response:
       └─ JSON {status, message}
```

---

## 📊 DATABASE SCHEMA (CSV Format)

### users.csv
```
id | username | password | full_name | role | created_at | updated_at
1  | admin    | hash...  | Admin     | admin| 2024-01-01 | 2024-01-01
```

### dau_ton.csv
```
id   | ten_tau | ngay       | loai_giao_dich | so_luong_lit | ly_do | cay_xang
uuid | Tàu 1   | 2024-01-01 | cap_them       | 1000         | ...   | Cây 1
```

### bang_he_so_tau_cu_ly_full_v2.csv
```
ten_tau | cu_ly_km | k_ko_hang | k_co_hang
Tàu 1   | 50       | 0.8       | 0.9
```

### khoang_duong.csv
```
diem_dau | diem_cuoi | khoang_cach_km
HCM      | Hà Nội    | 1200
```

---

## 🎓 TECHNOLOGY STACK SUMMARY

| Layer | Technology | Version |
|-------|-----------|---------|
| **Frontend** | HTML5, CSS3, Bootstrap | 5.3.0 |
| **Frontend** | JavaScript | Latest |
| **Frontend** | Font Awesome | 6.4.0 |
| **Backend** | PHP | 7.4+ |
| **Data Storage** | CSV Files | - |
| **Excel Export** | PHPOffice/PHPSpreadsheet | 1.29 |
| **Server** | Apache/Nginx | Latest |
| **Dependency Manager** | Composer | Latest |

---

**Tài Liệu Này Được Tạo Bởi:** Code-Based Analysis  
**Ngày Tạo:** 2024-12-03  
**Phiên Bản:** 1.0



