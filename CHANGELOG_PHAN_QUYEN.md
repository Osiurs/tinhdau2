# CHANGELOG - HỆ THỐNG PHÂN QUYỀN

## Ngày: 2025-11-14

### Tính năng mới

#### 1. Hệ thống Authentication & Authorization
- ✅ Đăng nhập/đăng xuất với session-based authentication
- ✅ Phân quyền 2 cấp: Admin và User
- ✅ Mã hóa mật khẩu với bcrypt (password_hash)
- ✅ Middleware bảo vệ các trang theo quyền

#### 2. Quản lý người dùng (Admin only)
- ✅ CRUD đầy đủ cho user
- ✅ Thêm/sửa/xóa user
- ✅ Đặt vai trò (admin/user)
- ✅ Vô hiệu hóa tài khoản (active/inactive)
- ✅ Giao diện quản lý với modal Bootstrap

### File mới được tạo

#### Thư mục auth/
1. **auth/auth_helper.php** - Helper functions cho authentication
   - `isLoggedIn()` - Kiểm tra đăng nhập
   - `isAdmin()` - Kiểm tra quyền admin
   - `getCurrentUser()` - Lấy thông tin user hiện tại
   - `loginUser($user)` - Đăng nhập user
   - `logoutUser()` - Đăng xuất
   - `requireLogin()` - Middleware yêu cầu đăng nhập
   - `requireAdmin()` - Middleware yêu cầu quyền admin

2. **auth/login.php** - Trang đăng nhập
   - Form đăng nhập với validation
   - Giao diện đẹp với gradient VICEM
   - Redirect về trang được yêu cầu sau khi login

3. **auth/logout.php** - Xử lý đăng xuất
   - Hủy session
   - Redirect về trang login

4. **auth/check_auth.php** - Middleware kiểm tra đăng nhập
   - Include vào các trang yêu cầu đăng nhập

5. **auth/check_admin.php** - Middleware kiểm tra quyền admin
   - Include vào các trang admin

#### Thư mục models/
6. **models/User.php** - Model quản lý user
   - `create()` - Tạo user mới
   - `authenticate()` - Xác thực đăng nhập
   - `update()` - Cập nhật thông tin user
   - `delete()` - Xóa user
   - `getAll()` - Lấy danh sách user
   - `getById()` - Lấy user theo ID
   - `getByUsername()` - Lấy user theo username
   - `isAdmin()` - Kiểm tra quyền admin
   - Lưu trữ trong `data/users.csv`

#### Thư mục admin/
7. **admin/quan_ly_user.php** - Trang quản lý user
   - Danh sách user với bảng
   - Modal thêm user
   - Modal sửa user
   - Xóa user với xác nhận
   - Hiển thị role badge và status

#### Root directory
8. **init_admin.php** - Script khởi tạo admin
   - Tạo tài khoản admin đầu tiên
   - Interactive CLI
   - Kiểm tra admin đã tồn tại

9. **HUONG_DAN_PHAN_QUYEN.md** - Hướng dẫn sử dụng
   - Cài đặt ban đầu
   - Quản lý user
   - Bảo mật
   - Khắc phục sự cố

### File được sửa đổi

#### 1. includes/header.php
**Thay đổi:**
- Thêm `require_once auth_helper.php` ở đầu file
- Thêm hiển thị thông tin user và nút đăng xuất trên navbar
- Thêm điều kiện `<?php if ($isUserAdmin): ?>` cho menu admin
- Thêm menu item "Quản lý người dùng" cho admin
- Cập nhật cả desktop sidebar và mobile offcanvas

**Dòng thay đổi:**
- Line 1-5: Load auth helper
- Line 337-352: User info và logout button
- Line 365-384: Mobile menu với điều kiện admin
- Line 433-470: Desktop menu với điều kiện admin

#### 2. Các trang admin (thêm middleware)
Thêm `require_once __DIR__ . '/../auth/check_admin.php';` vào đầu file:
- **admin/index.php** (line 6)
- **admin/quan_ly_tau.php** (line 7)
- **admin/quan_ly_tuyen_duong.php** (line 7)
- **admin/quan_ly_dau_ton.php** (line 2)
- **admin/bao_cao_dau_ton.php** (line 7)
- **admin/quan_ly_cay_xang.php** (line 2)
- **admin/quan_ly_loai_hang.php** (line 5)

#### 3. Các trang user (thêm middleware)
Thêm `require_once __DIR__ . '/auth/check_auth.php';` vào đầu file:
- **index.php** (line 16)
- **danh_sach_tau.php** (line 6)
- **lich_su.php** (line 22)
- **danh_sach_diem.php** (line 6)

### Cấu trúc dữ liệu

#### data/users.csv
```csv
id,username,password,full_name,role,status,created_at,updated_at
```

**Các trường:**
- `id`: Auto-increment integer
- `username`: Tên đăng nhập (unique)
- `password`: Mật khẩu đã hash (bcrypt)
- `full_name`: Họ tên đầy đủ
- `role`: 'admin' hoặc 'user'
- `status`: 'active' hoặc 'inactive'
- `created_at`: Thời gian tạo
- `updated_at`: Thời gian cập nhật

### Quyền hạn

#### Admin
- ✅ Tất cả chức năng của User
- ✅ Quản lý tàu
- ✅ Quản lý tuyến đường
- ✅ Quản lý dầu tồn
- ✅ Báo cáo dầu tồn
- ✅ Quản lý cây xăng
- ✅ Quản lý loại hàng
- ✅ **Quản lý người dùng** (mới)

#### User
- ✅ Tính toán nhiên liệu
- ✅ Xem danh sách tàu
- ✅ Xem lịch sử
- ✅ Xem danh sách điểm
- ❌ Không có quyền admin

### Bảo mật

1. **Mật khẩu**: Hash với bcrypt (cost=10)
2. **Session**: PHP session với cookie
3. **Middleware**: Kiểm tra quyền trước khi truy cập
4. **CSRF**: Chưa implement (có thể thêm sau)
5. **XSS**: Sử dụng `htmlspecialchars()` khi output

### Tương thích ngược

✅ **Tất cả chức năng cũ vẫn hoạt động bình thường**
- Không thay đổi logic tính toán
- Không thay đổi cấu trúc dữ liệu hiện có
- Không thay đổi giao diện (trừ thêm menu user)
- Chỉ thêm lớp bảo mật

### Hướng dẫn triển khai

1. **Khởi tạo admin:**
   ```bash
   php init_admin.php
   ```

2. **Đăng nhập:**
   - Truy cập: `/auth/login.php`
   - Nhập username và password

3. **Quản lý user:**
   - Đăng nhập với admin
   - Vào menu: Quản lý > Quản lý người dùng

### Lưu ý

⚠️ **Quan trọng:**
- Backup file `data/users.csv` thường xuyên
- Đặt mật khẩu mạnh cho admin
- Không chỉnh sửa trực tiếp file CSV
- Sử dụng giao diện để quản lý user

### Kiểm tra

- [x] Tạo file auth helper
- [x] Tạo trang login/logout
- [x] Tạo middleware
- [x] Tạo model User
- [x] Tạo trang quản lý user
- [x] Cập nhật header
- [x] Thêm middleware vào admin pages
- [x] Thêm middleware vào user pages
- [x] Tạo script init admin
- [x] Tạo hướng dẫn sử dụng
- [ ] Test đăng nhập
- [ ] Test phân quyền
- [ ] Test CRUD user

