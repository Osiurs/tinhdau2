<?php
/**
 * Trang quản lý tuyến đường - CRUD operations
 * Cho phép thêm, sửa, xóa tuyến đường và khoảng cách
 */

require_once __DIR__ . '/../auth/check_auth.php';
require_once '../includes/helpers.php';
require_once '../config/database.php';
require_once '../models/KhoangCach.php';

// Khởi tạo đối tượng khoảng cách
$khoangCach = new KhoangCach();

// Xử lý các action
$message = '';
$messageType = '';

// Hiển thị thông báo từ URL parameters (sau redirect)
if (isset($_GET['success']) && $_GET['success'] == '1' && isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
    $messageType = 'success';
} elseif (isset($_GET['error']) && $_GET['error'] == '1' && isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
    $messageType = 'danger';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add_tuyen_duong':
                $diemDau = trim($_POST['diem_dau']);
                $diemCuoi = trim($_POST['diem_cuoi']);
                $khoangCachKm = floatval($_POST['khoang_cach_km']);
                $lyDo = trim($_POST['ly_do'] ?? '');

                if (empty($diemDau) || empty($diemCuoi)) {
                    throw new Exception('Điểm đầu và điểm cuối không được để trống');
                }

                if ($diemDau === $diemCuoi) {
                    throw new Exception('Điểm đầu và điểm cuối không được giống nhau');
                }

                if ($khoangCachKm <= 0) {
                    throw new Exception('Khoảng cách phải lớn hơn 0');
                }

                // Kiểm tra tuyến đường đã tồn tại chưa
                if ($khoangCach->getKhoangCach($diemDau, $diemCuoi) !== null) {
                    throw new Exception('Tuyến đường này đã tồn tại');
                }

                // Thêm vào file CSV
                $lines = file(KHOA_CACH_FILE, FILE_IGNORE_NEW_LINES);
                $maxId = 0;
                foreach ($lines as $line) {
                    $data = explode(',', $line);
                    if (count($data) >= 1 && is_numeric($data[0])) {
                        $maxId = max($maxId, intval($data[0]));
                    }
                }
                $newId = $maxId + 1;

                $newData = "$newId,$diemDau,$diemCuoi,$khoangCachKm\n";
                file_put_contents(KHOA_CACH_FILE, $newData, FILE_APPEND | LOCK_EX);

                // Ghi log thêm tuyến đường (Fix #3: kèm lý do)
                $logFile = __DIR__ . '/../data/tuyen_duong_log.csv';
                $logEntry = date('Y-m-d H:i:s') . '|THÊM|' . $diemDau . ' → ' . $diemCuoi . '|' . $khoangCachKm . ' km|' . ($lyDo ?: 'Không có lý do') . "\n";
                file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

                // Redirect để tránh F5 resubmit
                header('Location: quan_ly_tuyen_duong.php?success=1&message=' . urlencode("Đã thêm tuyến đường '$diemDau' → '$diemCuoi' thành công!"));
                exit;
                
            case 'delete_tuyen_duong':
                $id = intval($_POST['id']);
                
                // Đọc file hiện tại
                $lines = file(KHOA_CACH_FILE, FILE_IGNORE_NEW_LINES);
                $newLines = [];
                $header = true;
                
                foreach ($lines as $line) {
                    if ($header) {
                        $newLines[] = $line; // Giữ header
                        $header = false;
                        continue;
                    }
                    
                    $data = explode(',', $line);
                    if (count($data) >= 1 && intval($data[0]) !== $id) {
                        $newLines[] = $line;
                    }
                }
                
                // Ghi lại file
                file_put_contents(KHOA_CACH_FILE, implode("\n", $newLines) . "\n");
                
                // Redirect để tránh F5 resubmit
                header('Location: quan_ly_tuyen_duong.php?success=1&message=' . urlencode("Đã xóa tuyến đường thành công!"));
                exit;
                
            case 'update_tuyen_duong':
                $id = intval($_POST['id']);
                $diemDau = trim($_POST['diem_dau']);
                $diemCuoi = trim($_POST['diem_cuoi']);
                $khoangCachKm = floatval($_POST['khoang_cach_km']);
                $lyDo = trim($_POST['ly_do'] ?? '');

                if (empty($diemDau) || empty($diemCuoi)) {
                    throw new Exception('Điểm đầu và điểm cuối không được để trống');
                }

                if ($diemDau === $diemCuoi) {
                    throw new Exception('Điểm đầu và điểm cuối không được giống nhau');
                }

                if ($khoangCachKm <= 0) {
                    throw new Exception('Khoảng cách phải lớn hơn 0');
                }

                // Đọc file hiện tại
                $lines = file(KHOA_CACH_FILE, FILE_IGNORE_NEW_LINES);
                $newLines = [];
                $oldData = '';

                foreach ($lines as $line) {
                    $data = explode(',', $line);
                    if (count($data) >= 1 && intval($data[0]) === $id) {
                        // Lưu dữ liệu cũ để log
                        $oldData = $line;
                        // Cập nhật dòng này
                        $newLines[] = "$id,$diemDau,$diemCuoi,$khoangCachKm";
                    } else {
                        $newLines[] = $line;
                    }
                }

                // Ghi lại file
                file_put_contents(KHOA_CACH_FILE, implode("\n", $newLines) . "\n");

                // Ghi log sửa tuyến đường (Fix #3: kèm lý do)
                $logFile = __DIR__ . '/../data/tuyen_duong_log.csv';
                $logEntry = date('Y-m-d H:i:s') . '|SỬA|' . $diemDau . ' → ' . $diemCuoi . '|' . $khoangCachKm . ' km|' . ($lyDo ?: 'Không có lý do') . '|Cũ: ' . $oldData . "\n";
                file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

                // Redirect để tránh F5 resubmit
                header('Location: quan_ly_tuyen_duong.php?success=1&message=' . urlencode("Đã cập nhật tuyến đường thành công!"));
                exit;
        }
    } catch (Exception $e) {
        // Redirect với thông báo lỗi để tránh F5 resubmit
        header('Location: quan_ly_tuyen_duong.php?error=1&message=' . urlencode('Lỗi: ' . $e->getMessage()));
        exit;
    }
}

// Lấy tất cả dữ liệu khoảng cách
$allData = $khoangCach->getAllData();

// Include header
include '../includes/header.php';
?>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center">
                <h1 class="card-title">
                    <i class="fas fa-route text-primary me-3"></i>
                    Quản Lý Tuyến Đường
                </h1>
                <p class="card-text">
                    Thêm, sửa, xóa thông tin tuyến đường và khoảng cách
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Message Alert -->
<?php if ($message): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row">
    <!-- Form thêm tuyến đường -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-plus me-2"></i>
                    Thêm Tuyến Đường Mới
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" onsubmit="return validateTuyenDuongForm()">
                    <input type="hidden" name="action" value="add_tuyen_duong">
                    
                    <div class="mb-3 position-relative">
                        <label for="diem_dau" class="form-label">Điểm đầu <span class="text-danger">*</span></label>
                        <input type="text" class="form-control admin-diem-input" id="diem_dau" name="diem_dau" autocomplete="off" required>
                        <div class="dropdown-menu w-100" id="diem_dau_results"></div>
                    </div>
                    
                    <div class="mb-3 position-relative">
                        <label for="diem_cuoi" class="form-label">Điểm cuối <span class="text-danger">*</span></label>
                        <input type="text" class="form-control admin-diem-input" id="diem_cuoi" name="diem_cuoi" autocomplete="off" required>
                        <div class="dropdown-menu w-100" id="diem_cuoi_results"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="khoang_cach_km" class="form-label">Khoảng cách (km) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="khoang_cach_km" name="khoang_cach_km" min="0.1" step="0.1" required>
                    </div>

                    <div class="mb-3">
                        <label for="ly_do" class="form-label">Lý do thêm <span class="text-muted">(tùy chọn)</span></label>
                        <input type="text" class="form-control" id="ly_do" name="ly_do" placeholder="Nhập lý do thêm tuyến đường...">
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-plus me-2"></i>
                            Thêm Tuyến Đường
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Danh sách tuyến đường -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2"></i>
                        Danh Sách Tuyến Đường
                    </h5>
                    <div class="d-flex gap-2">
                        <div class="input-group" style="width: 300px;">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="searchTuyenDuong" placeholder="Tìm kiếm tuyến đường...">
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="tuyenDuongTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Điểm đầu</th>
                                <th>Điểm cuối</th>
                                <th>Khoảng cách (km)</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allData as $tuyenDuong): ?>
                            <tr data-search="<?php echo strtolower(htmlspecialchars($tuyenDuong['diem_dau'] . ' ' . $tuyenDuong['diem_cuoi'])); ?>">
                                <td><strong><?php echo $tuyenDuong['id']; ?></strong></td>
                                <td><i class="fas fa-map-marker-alt text-primary me-1"></i><?php echo htmlspecialchars($tuyenDuong['diem_dau']); ?></td>
                                <td><i class="fas fa-flag-checkered text-success me-1"></i><?php echo htmlspecialchars($tuyenDuong['diem_cuoi']); ?></td>
                                <td><span class="badge bg-info"><?php echo number_format($tuyenDuong['khoang_cach_km'], 1); ?> km</span></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-primary" onclick="editTuyenDuong(<?php echo $tuyenDuong['id']; ?>, '<?php echo htmlspecialchars($tuyenDuong['diem_dau']); ?>', '<?php echo htmlspecialchars($tuyenDuong['diem_cuoi']); ?>', <?php echo $tuyenDuong['khoang_cach_km']; ?>)" title="Chỉnh sửa">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteTuyenDuong(<?php echo $tuyenDuong['id']; ?>, '<?php echo htmlspecialchars($tuyenDuong['diem_dau']); ?>', '<?php echo htmlspecialchars($tuyenDuong['diem_cuoi']); ?>')" title="Xóa">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Edit Tuyen Duong -->
<div class="modal fade" id="editTuyenDuongModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>
                    Sửa Thông Tin Tuyến Đường
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_tuyen_duong">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3 position-relative">
                        <label for="edit_diem_dau" class="form-label">Điểm đầu</label>
                        <input type="text" class="form-control admin-diem-input" id="edit_diem_dau" name="diem_dau" autocomplete="off" required>
                        <div class="dropdown-menu w-100" id="edit_diem_dau_results"></div>
                    </div>
                    
                    <div class="mb-3 position-relative">
                        <label for="edit_diem_cuoi" class="form-label">Điểm cuối</label>
                        <input type="text" class="form-control admin-diem-input" id="edit_diem_cuoi" name="diem_cuoi" autocomplete="off" required>
                        <div class="dropdown-menu w-100" id="edit_diem_cuoi_results"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_khoang_cach_km" class="form-label">Khoảng cách (km)</label>
                        <input type="number" class="form-control" id="edit_khoang_cach_km" name="khoang_cach_km" min="0.1" step="0.1" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_ly_do" class="form-label">Lý do sửa <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_ly_do" name="ly_do" placeholder="Nhập lý do sửa tuyến đường..." required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>
                        Cập nhật
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Delete Confirmation -->
<div class="modal fade" id="deleteTuyenDuongModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                    Xác Nhận Xóa
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Bạn có chắc chắn muốn xóa tuyến đường <strong id="deleteTuyenDuongInfo"></strong>?</p>
                <p class="text-muted">Hành động này không thể hoàn tác!</p>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete_tuyen_duong">
                <input type="hidden" name="id" id="deleteId">
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-1"></i>
                        Xóa
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Hàm validate form thêm tuyến đường
function validateTuyenDuongForm() {
    const diemDau = document.getElementById('diem_dau').value.trim();
    const diemCuoi = document.getElementById('diem_cuoi').value.trim();
    const khoangCach = parseFloat(document.getElementById('khoang_cach_km').value);
    
    if (!diemDau || !diemCuoi) {
        alert('Vui lòng nhập đầy đủ điểm đầu và điểm cuối');
        return false;
    }
    
    if (diemDau === diemCuoi) {
        alert('Điểm đầu và điểm cuối không được giống nhau');
        return false;
    }
    
    if (khoangCach <= 0) {
        alert('Khoảng cách phải lớn hơn 0');
        return false;
    }
    
    return true;
}

// Hàm sửa tuyến đường
function editTuyenDuong(id, diemDau, diemCuoi, khoangCach) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_diem_dau').value = diemDau;
    document.getElementById('edit_diem_cuoi').value = diemCuoi;
    document.getElementById('edit_khoang_cach_km').value = khoangCach;
    
    const modal = new bootstrap.Modal(document.getElementById('editTuyenDuongModal'));
    modal.show();
}

// Hàm xóa tuyến đường
function deleteTuyenDuong(id, diemDau, diemCuoi) {
    document.getElementById('deleteTuyenDuongInfo').textContent = `"${diemDau}" → "${diemCuoi}"`;
    document.getElementById('deleteId').value = id;
    
    const modal = new bootstrap.Modal(document.getElementById('deleteTuyenDuongModal'));
    modal.show();
}
</script>

<script>
// Nguồn dữ liệu điểm từ PHP sang JS
const ADMIN_ALL_DIEM = <?php echo json_encode($khoangCach->getDanhSachDiem(), JSON_UNESCAPED_UNICODE); ?>;

// Chuẩn hoá Unicode NFC để tìm kiếm tiếng Việt ổn định
function adminNormalize(str) {
    try { return (str || '').normalize('NFC'); } catch (e) { return str || ''; }
}

function adminFilterDiem(keyword) {
    const kw = adminNormalize(keyword).toLowerCase();
    const list = ADMIN_ALL_DIEM.filter(d => adminNormalize(d).toLowerCase().includes(kw));
    // Giới hạn 20 kết quả để gọn
    return list.slice(0, 20);
}

function adminShowSuggestions(inputEl, resultsEl) {
    const keyword = inputEl.value.trim();
    const suggestions = adminFilterDiem(keyword);
    let html = '';
    if (suggestions.length > 0) {
        suggestions.forEach(d => {
            const val = d;
            html += `<button type=\"button\" class=\"dropdown-item\" data-value=\"${val.replace(/\"/g,'&quot;')}\">${d}</button>`;
        });
    }
    // Thêm option "Thêm ..." nếu không khớp 100% (so khớp trên NFC)
    const exists = ADMIN_ALL_DIEM.some(d => adminNormalize(d) === adminNormalize(keyword));
    if (keyword.length > 0 && !exists) {
        const val = keyword;
        html += `<button type=\"button\" class=\"dropdown-item text-primary\" data-value=\"${val.replace(/\"/g,'&quot;')}\"><i class=\"fas fa-plus me-1\"></i>Thêm \"${keyword}\"</button>`;
    }

    if (html === '') {
        resultsEl.classList.remove('show');
        resultsEl.innerHTML = '';
        return;
    }

    resultsEl.innerHTML = html;
    resultsEl.classList.add('show');

    // Bind click
    Array.from(resultsEl.querySelectorAll('.dropdown-item')).forEach(btn => {
        btn.addEventListener('click', () => {
            const val = btn.getAttribute('data-value') || btn.textContent;
            inputEl.value = adminNormalize(val).trim();
            resultsEl.classList.remove('show');
        });
    });
}

function adminAttachSearch(inputId, resultsId) {
    const inputEl = document.getElementById(inputId);
    const resultsEl = document.getElementById(resultsId);
    if (!inputEl || !resultsEl) return;
    inputEl.addEventListener('input', () => adminShowSuggestions(inputEl, resultsEl));
    inputEl.addEventListener('focus', () => adminShowSuggestions(inputEl, resultsEl));
    // Ẩn khi bấm ra ngoài
    document.addEventListener('click', (e) => {
        if (!resultsEl.contains(e.target) && e.target !== inputEl) {
            resultsEl.classList.remove('show');
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    adminAttachSearch('diem_dau', 'diem_dau_results');
    adminAttachSearch('diem_cuoi', 'diem_cuoi_results');
    adminAttachSearch('edit_diem_dau', 'edit_diem_dau_results');
    adminAttachSearch('edit_diem_cuoi', 'edit_diem_cuoi_results');
    
    // Tìm kiếm tuyến đường
    const searchInput = document.getElementById('searchTuyenDuong');
    const table = document.getElementById('tuyenDuongTable');
    
    if (searchInput && table) {
        searchInput.addEventListener('input', function() {
            const keyword = this.value.toLowerCase().trim();
            const rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const searchText = row.getAttribute('data-search') || '';
                if (keyword === '' || searchText.includes(keyword)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
    
    // Xử lý Enter để submit form thêm tuyến đường
    const khoangCachInput = document.getElementById('khoang_cach_km');
    if (khoangCachInput) {
        khoangCachInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                
                // Kiểm tra các trường bắt buộc
                const diemDau = document.getElementById('diem_dau').value.trim();
                const diemCuoi = document.getElementById('diem_cuoi').value.trim();
                const khoangCach = parseFloat(this.value);
                
                if (!diemDau || !diemCuoi) {
                    showAlert('Vui lòng nhập đầy đủ điểm đầu và điểm cuối', 'warning');
                    return false;
                }
                
                if (diemDau === diemCuoi) {
                    showAlert('Điểm đầu và điểm cuối không được giống nhau', 'warning');
                    return false;
                }
                
                if (isNaN(khoangCach) || khoangCach <= 0) {
                    showAlert('Khoảng cách phải là số lớn hơn 0', 'warning');
                    return false;
                }
                
                // Submit form
                const form = this.closest('form');
                if (form) {
                    form.submit();
                }
            }
        });
    }
    
    // Hàm hiển thị thông báo
    function showAlert(message, type = 'info') {
        // Tạo alert element
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            <i class="fas fa-${type === 'warning' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        // Thêm vào đầu trang
        const container = document.querySelector('.container');
        if (container) {
            container.insertBefore(alertDiv, container.firstChild);
            
            // Tự động ẩn sau 5 giây
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }
    }
    
    // Làm cho hàm showAlert có thể sử dụng toàn cục
    window.showAlert = showAlert;
});
</script>

<?php include '../includes/footer.php'; ?>


