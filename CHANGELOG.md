# Changelog

Tất cả các thay đổi đáng chú ý của dự án này sẽ được ghi lại trong file này.

Format dựa trên [Keep a Changelog](https://keepachangelog.com/vi/1.0.0/),
và dự án này tuân thủ [Semantic Versioning](https://semver.org/lang/vi/).

## [1.3.8] - 2025-11-13

### Added
- `App\Report\HeaderTemplate`: lớp điều phối template Excel tái sử dụng cho các báo cáo `BCTHANG`, `BC TH`, `DAUTON`, `IN TINH DAU`.
- `config/report_header_registry.php`: registry trung tâm khai báo thư mục và ánh xạ template, hỗ trợ fallback `_default/header.xlsx`.
- Bộ export mới dựa trên PhpSpreadsheet trong `includes/excel_export_full.php` và `includes/excel_export_wrapper.php`, giữ nguyên merge cells, logo và style.
- Script bảo trì `admin/cleanup_he_so_tau.php` để lọc hệ số tàu theo phân loại và tự động tạo file backup.

### Changed
- Xuất Excel chuyển hoàn toàn sang template `.xlsx`, thay thế luồng XML cũ và đồng bộ header giữa các báo cáo.
- Logic chèn ngày tháng trong header: dò placeholder đa dạng, ghi đè có kiểm soát hoặc fallback sang ô tuỳ chỉnh (mặc định `F4`).
- Điều chỉnh tài liệu và cấu trúc README cho phù hợp guideline GitHub (mục What's New, Maintenance scripts).

### Fixed
- Cải thiện thông báo log khi thiếu template, tránh xuất file rỗng và đảm bảo sử dụng fallback.
- Đảm bảo import template theo từng sheet thay vì clone, tránh mất logo khi xuất nhiều sheet.

### Documentation
- Cập nhật README với khu vực What's New, mô tả cấu hình template và hướng dẫn chạy script bảo trì.
- Làm rõ quy trình backup trước khi chạy cleanup hệ số tàu.

## [1.3.7] - 2025-01-XX

### Added
- **Tính năng di chuyển đoạn giữa các chuyến**: Cho phép di chuyển đoạn từ chuyến này sang chuyến khác thông qua modal interface
- **Hỗ trợ chỉnh sửa đoạn đã lưu**: Thêm modal chỉnh sửa cho phép cập nhật thông tin đoạn sau khi đã lưu
- **Tính năng đổi lệnh đa điểm**: Hỗ trợ nhiều điểm trung gian (C, D, E, ...) với lý do riêng cho từng điểm
- **Ghi chú cho các điểm**: Thêm trường ghi chú cho điểm bắt đầu, điểm kết thúc và điểm mới
- **Preview lý do cấp thêm**: Hiển thị trước lý do cấp thêm sẽ được lưu dựa trên loại và địa điểm
- **Tự động đồng bộ ngày cấp thêm**: Tự động lấy ngày từ chuyến trước đó khi tạo lệnh cấp thêm
- **API endpoint `move_segment.php`**: Endpoint để xử lý di chuyển đoạn
- **API endpoint `update_segment.php`**: Endpoint để cập nhật đoạn
- **Hiển thị tổng cấp thêm**: Hiển thị tổng số lượng cấp thêm trong chuyến

### Changed
- **Logic xác định ngày cho báo cáo dầu tồn**: Cải thiện thứ tự ưu tiên xác định ngày (ngay_do_xong → ngay_den → ngay_di → created_at)
- **Hiển thị tuyến đường đổi lệnh**: Tối ưu hiển thị route đầy đủ trong báo cáo Excel với format: `A → B (đổi lệnh) → C (lý do) → D`
- **UX form nhập liệu**: 
  - Cải thiện auto-complete cho tìm kiếm điểm
  - Thêm nút "Chọn lại" cho các trường điểm
  - Cải thiện validation real-time
- **Sắp xếp đoạn trong chuyến**: Sắp xếp theo thứ tự nhập liệu thực tế (___idx) thay vì theo ngày
- **Cấu trúc lưu trữ đổi lệnh**: Lưu dưới dạng JSON với cấu trúc `{point, reason, note}`

### Fixed
- **Đồng bộ dữ liệu báo cáo DAUTON**: Sửa lỗi khác biệt dữ liệu giữa báo cáo và quản lý dầu tồn do logic xác định ngày khác nhau
- **Xác định ngày không nhất quán**: Áp dụng cùng một logic xác định ngày cho tất cả các module
- **Hiển thị route_hien_thi trong Excel**: Sửa lỗi không hiển thị đầy đủ tuyến đường đổi lệnh trong báo cáo Excel
- **Parse JSON doi_lenh_tuyen**: Sửa lỗi parse JSON khi có ký tự đặc biệt
- **Hiển thị điểm cuối đổi lệnh**: Sửa lỗi không hiển thị điểm cuối trong danh sách đoạn
- **Validation khoảng cách thực tế**: Sửa lỗi validation khi nhập khoảng cách cho đổi lệnh

### Security
- **Input validation**: Cải thiện validation cho tất cả các trường nhập liệu
- **Sanitize dữ liệu**: Sanitize dữ liệu trước khi lưu vào CSV để tránh injection
- **XSS protection**: Cải thiện escape output trong các template

### Performance
- **Tối ưu đọc CSV**: Cải thiện hiệu năng khi đọc file CSV lớn
- **Cache dữ liệu**: Cache dữ liệu tuyến đường và hệ số để giảm I/O

### Documentation
- Cập nhật README với hướng dẫn sử dụng tính năng mới
- Thêm ví dụ sử dụng API endpoints

## [1.3.6] - 2024-XX-XX

### Added
- **Quản lý loại hàng hóa**: Thêm module quản lý danh sách loại hàng với CRUD đầy đủ
- **Template header Excel tùy chỉnh**: Hỗ trợ nhiều template header cho các loại báo cáo khác nhau
  - BC TH (Báo cáo tháng)
  - BCTHANG
  - DAUTON (Dầu tồn)
  - IN TINH DAU (In tính dầu)
- **Cấp thêm nhiên liệu**: Tính năng cấp thêm với 3 loại:
  - Ma nơ: "Dầu ma nơ tại bến [địa điểm] 01 chuyến"
  - Qua cầu: "Dầu bơm nước qua cầu [địa điểm] 01 chuyến"
  - Khác: Tự nhập lý do
- **Tự động tạo lý do cấp thêm**: Tự động tạo lý do dựa trên loại và địa điểm được chọn
- **API quản lý loại hàng**: `add_loai_hang.php`, `get_loai_hang.php`
- **Model LoaiHang**: Class mới để quản lý loại hàng

### Changed
- **Hiệu năng đọc/ghi CSV**: Tối ưu code đọc/ghi file CSV lớn
- **Excel export**: Cải thiện hiệu năng xuất báo cáo Excel với template
- **Cấu trúc template**: Tổ chức lại cấu trúc thư mục template header

### Fixed
- **Tính toán thiếu tuyến**: Hiển thị thông báo rõ ràng khi không có tuyến trực tiếp
- **Hiển thị ngày**: Sửa lỗi format ngày trong form (dd/mm/yyyy)
- **Validation cấp thêm**: Sửa lỗi validation khi nhập số lượng cấp thêm

## [1.3.5] - 2024-XX-XX

### Added
- **Đổi lệnh trong chuyến**: Tính năng đổi lệnh cho phép thay đổi điểm đến trong quá trình chuyến
- **Khoảng cách thực tế**: Cho phép nhập khoảng cách thực tế khi đổi lệnh (không dùng khoảng cách tuyến có sẵn)
- **Tạo chuyến mới tự động**: Checkbox "Tạo chuyến mới" tự động tạo mã chuyến tiếp theo
- **Hiển thị danh sách đoạn**: Hiển thị tất cả các đoạn của chuyến hiện tại trong form
- **Phương thức `tinhNhienLieuDoiLenh()`**: Method mới trong `TinhToanNhienLieu` để tính toán đổi lệnh
- **Trường `doi_lenh`**: Thêm trường boolean để đánh dấu đoạn đổi lệnh
- **Trường `diem_du_kien`**: Lưu điểm dự kiến ban đầu (điểm B) khi đổi lệnh

### Changed
- **Giao diện quản lý chuyến**: 
  - Hiển thị danh sách đoạn trong card riêng
  - Thêm thông tin chi tiết cho mỗi đoạn
- **Logic tính toán đổi lệnh**: 
  - Sử dụng khoảng cách thực tế thay vì tính từ tuyến
  - Tính toán dựa trên điểm cuối cùng (điểm C)

### Fixed
- **Tính toán đổi lệnh**: Sửa lỗi tính toán sai khi sử dụng khoảng cách thực tế
- **Hiển thị mã chuyến**: Sửa lỗi không hiển thị đúng mã chuyến khi reload trang
- **Validation đổi lệnh**: Sửa lỗi validation khi không nhập điểm đến mới

## [1.3.4] - 2024-XX-XX

### Added
- Tính năng quản lý cây xăng
- Tính năng quản lý dầu tồn
- Báo cáo dầu tồn theo tháng
- Export Excel với template

### Changed
- Cải thiện cấu trúc báo cáo Excel
- Tối ưu hiệu năng xuất báo cáo

## [1.3.3] - 2024-XX-XX

### Added
- Tính năng phân loại tàu (Công ty/Thuê ngoài)
- Lọc tàu theo phân loại
- Tính năng tìm kiếm điểm
- Auto-complete cho input điểm

### Changed
- Cải thiện UX cho form nhập liệu
- Tối ưu hiệu năng tìm kiếm

## [1.3.2] - 2024-XX-XX

### Added
- Tính năng quản lý tuyến đường
- Validation tuyến đường khi tính toán
- Thông báo hướng dẫn khi thiếu tuyến

### Fixed
- Sửa lỗi tính toán khi thiếu tuyến
- Sửa lỗi validation input

## [1.3.1] - 2024-XX-XX

### Added
- Tính năng lưu kết quả tính toán
- Lịch sử tính toán
- Export lịch sử ra Excel
- Tìm kiếm và lọc lịch sử

### Changed
- Cải thiện cấu trúc lưu trữ dữ liệu
- Tối ưu hiệu năng truy vấn

## [1.3.0] - 2024-XX-XX

### Added
- Tính năng tính toán nhiên liệu cơ bản
- Quản lý danh sách tàu
- Quản lý danh sách điểm
- Công thức tính toán: Q = [(Sch+Skh)×Kkh] + (Sch×D×Kch)
- Hỗ trợ tính toán có hàng và không hàng
- Phân loại cự ly (Ngắn/Trung bình/Dài)
- Tra cứu hệ số nhiên liệu từ CSV

### Changed
- Cấu trúc dự án ban đầu
- Sử dụng CSV thay vì database

## [1.2.0] - 2024-XX-XX

### Added
- Cấu trúc dự án cơ bản
- Setup Composer
- Cấu hình môi trường

## [1.2.0] - 2024-XX-XX

### Added
- **Cấu trúc dự án cơ bản**: Khởi tạo cấu trúc thư mục và file cơ bản
- **Setup Composer**: Cấu hình Composer và autoload
- **Cấu hình môi trường**: File cấu hình database và debug
- **Dependencies**: Thêm PhpSpreadsheet cho xuất Excel

### Changed
- Khởi tạo dự án từ đầu

---

## Migration Notes

### Từ 1.3.7 lên 1.3.8

- **Breaking Changes**: Không có
- **New Files**:
  - `config/report_header_registry.php`
  - `admin/cleanup_he_so_tau.php`
- **Action Required**:
  - Thêm/cập nhật template trong `template_header/` tương ứng với map mới.
  - Chạy `composer dump-autoload` nếu bổ sung namespace mới trong `src/Report`.
  - Backup `bang_he_so_tau_cu_ly_full_v2.csv` trước khi chạy script cleanup.

### Từ 1.3.6 lên 1.3.7

- **Breaking Changes**: Không có
- **Database Changes**: Không có (sử dụng CSV)
- **Action Required**: 
  - Backup dữ liệu trước khi cập nhật
  - Kiểm tra file CSV có đầy đủ trường mới không

### Từ 1.3.5 lên 1.3.6

- **Breaking Changes**: Không có
- **New Files**: 
  - `models/LoaiHang.php`
  - `admin/quan_ly_loai_hang.php`
  - `api/add_loai_hang.php`
  - `api/get_loai_hang.php`
- **Action Required**: Tạo file `data/loai_hang.csv` nếu chưa có

### Từ 1.3.4 lên 1.3.5

- **Breaking Changes**: Không có
- **New Fields**: 
  - `doi_lenh` (boolean)
  - `diem_du_kien` (string)
  - `khoang_cach_thuc_te` (float)
- **Action Required**: Cập nhật file CSV nếu cần

---

## Contributors

Cảm ơn tất cả những người đã đóng góp cho dự án này!

- VICEM Development Team

---

## Loại thay đổi

- **Added**: Tính năng mới
- **Changed**: Thay đổi trong chức năng hiện có
- **Deprecated**: Tính năng sẽ bị loại bỏ trong tương lai
- **Removed**: Tính năng đã bị loại bỏ
- **Fixed**: Sửa lỗi
- **Security**: Sửa lỗi bảo mật
- **Performance**: Cải thiện hiệu năng
- **Documentation**: Cập nhật tài liệu

