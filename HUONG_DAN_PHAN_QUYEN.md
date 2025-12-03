# HƯỚNG DẪN SỬ DỤNG HỆ THỐNG PHÂN QUYỀN

## Tổng quan

Hệ thống đã được bổ sung chức năng phân quyền với 2 vai trò:
- **Admin**: Có toàn quyền quản lý hệ thống, bao gồm CRUD user và tất cả chức năng hiện có
- **User**: Chỉ có quyền sử dụng các chức năng tính toán và xem dữ liệu (không có quyền quản lý)

## Cài đặt ban đầu

### Bước 1: Tạo tài khoản admin đầu tiên

Chạy lệnh sau từ thư mục gốc của dự án:

```bash
php init_admin.php
```

Script sẽ hỏi bạn:
- Tên đăng nhập
- Mật khẩu
- Họ tên (có thể để trống)

Sau khi tạo thành công, bạn có thể đăng nhập vào hệ thống.

### Bước 2: Đăng nhập

Truy cập: `http://your-domain/auth/login.php`

Nhập tên đăng nhập và mật khẩu của tài khoản admin vừa tạo.

## Cấu trúc file mới

```
tinh-dau-2/
├── auth/                          # Thư mục xác thực
│   ├── auth_helper.php           # Các hàm helper cho authentication
│   ├── check_auth.php            # Middleware kiểm tra đăng nhập
│   ├── check_admin.php           # Middleware kiểm tra quyền admin
│   ├── login.php                 # Trang đăng nhập
│   └── logout.php                # Xử lý đăng xuất
├── models/
│   └── User.php                  # Model quản lý user
├── admin/
│   └── quan_ly_user.php          # Trang quản lý user (chỉ admin)
├── data/
│   └── users.csv                 # File lưu trữ thông tin user
├── init_admin.php                # Script khởi tạo admin
└── HUONG_DAN_PHAN_QUYEN.md      # File này
```

## Chức năng theo vai trò

### Admin
- ✅ Tất cả chức năng của User
- ✅ Quản lý tàu (CRUD)
- ✅ Quản lý tuyến đường (CRUD)
- ✅ Quản lý dầu tồn (CRUD)
- ✅ Báo cáo dầu tồn
- ✅ Quản lý cây xăng (CRUD)
- ✅ Quản lý loại hàng (CRUD)
- ✅ **Quản lý người dùng (CRUD)** - Chức năng mới

### User
- ✅ Tính toán nhiên liệu
- ✅ Xem danh sách tàu
- ✅ Xem lịch sử tính toán
- ✅ Xem danh sách điểm
- ❌ Không có quyền truy cập các trang quản lý

## Quản lý người dùng (Admin)

### Thêm user mới
1. Đăng nhập với tài khoản admin
2. Vào menu **Quản lý > Quản lý người dùng**
3. Click nút **Thêm người dùng**
4. Nhập thông tin:
   - Tên đăng nhập (bắt buộc, duy nhất)
   - Mật khẩu (bắt buộc)
   - Họ tên (tùy chọn)
   - Vai trò: Admin hoặc User
5. Click **Lưu**

### Sửa thông tin user
1. Trong danh sách user, click nút **Sửa** (biểu tượng bút)
2. Cập nhật thông tin cần thiết
3. Để trống mật khẩu nếu không muốn đổi
4. Click **Cập nhật**

### Xóa user
1. Trong danh sách user, click nút **Xóa** (biểu tượng thùng rác)
2. Xác nhận xóa
3. **Lưu ý**: Không thể xóa chính tài khoản đang đăng nhập

### Vô hiệu hóa user
1. Sửa thông tin user
2. Chọn trạng thái: **Inactive**
3. User sẽ không thể đăng nhập

## Bảo mật

### Mật khẩu
- Mật khẩu được mã hóa bằng `password_hash()` của PHP (bcrypt)
- Không thể xem mật khẩu gốc
- Chỉ có thể đặt lại mật khẩu mới

### Session
- Sử dụng PHP session để quản lý đăng nhập
- Tự động đăng xuất khi đóng trình duyệt (session cookie)
- Có thể đăng xuất thủ công bằng nút **Đăng xuất**

### Middleware
- Tất cả trang đều yêu cầu đăng nhập
- Trang admin yêu cầu vai trò admin
- Tự động chuyển hướng về trang login nếu chưa đăng nhập
- Hiển thị lỗi 403 nếu không đủ quyền

## File dữ liệu

### data/users.csv
Cấu trúc:
```csv
id,username,password,full_name,role,status,created_at,updated_at
1,admin,<hashed_password>,Administrator,admin,active,2024-01-01 00:00:00,2024-01-01 00:00:00
```

**Lưu ý**: 
- Không chỉnh sửa file này trực tiếp
- Sử dụng giao diện quản lý user để thao tác
- File được tự động tạo khi chạy `init_admin.php`

## Khắc phục sự cố

### Quên mật khẩu admin
Chạy lại `init_admin.php` để tạo tài khoản admin mới.

### Không thể đăng nhập
1. Kiểm tra tên đăng nhập và mật khẩu
2. Kiểm tra trạng thái tài khoản (phải là active)
3. Kiểm tra file `data/users.csv` có tồn tại không

### Lỗi 403 Forbidden
- Tài khoản user đang cố truy cập trang admin
- Đăng nhập bằng tài khoản admin để truy cập

## Lưu ý quan trọng

⚠️ **Chức năng hiện có không bị thay đổi**
- Tất cả chức năng tính toán, quản lý dữ liệu vẫn hoạt động như cũ
- Chỉ thêm lớp bảo mật và phân quyền
- Giao diện không thay đổi (trừ thêm menu Quản lý người dùng cho admin)

⚠️ **Backup dữ liệu**
- Nên backup thư mục `data/` định kỳ
- File `users.csv` chứa thông tin đăng nhập quan trọng

⚠️ **Bảo mật**
- Đặt mật khẩu mạnh cho tài khoản admin
- Không chia sẻ thông tin đăng nhập
- Thường xuyên kiểm tra danh sách user

