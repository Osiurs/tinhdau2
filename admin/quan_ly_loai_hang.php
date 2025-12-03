<?php
/**
 * Quản lý Loại hàng (CRUD) dựa trên data/loai_hang.csv
 */
require_once __DIR__ . '/../auth/check_auth.php';
require_once '../includes/helpers.php';
require_once '../config/database.php';
require_once '../models/LoaiHang.php';

$lh = new LoaiHang();
$message = '';
$messageType = '';

// Sanitize input
if (!empty($_GET)) { $_GET = sanitize_input($_GET); }
if (!empty($_POST)) { $_POST = sanitize_input($_POST); }

if (isset($_GET['success']) && $_GET['success'] == '1' && isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
    $messageType = 'success';
} elseif (isset($_GET['error']) && $_GET['error'] == '1' && isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
    $messageType = 'danger';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    try {
        switch ($action) {
            case 'add':
                $ten = trim((string)($_POST['ten_loai_hang'] ?? ''));
                $moTa = trim((string)($_POST['mo_ta'] ?? ''));
                $rec = $lh->add($ten, $moTa);
                header('Location: quan_ly_loai_hang.php?success=1&message=' . urlencode("Đã thêm loại hàng '" . $rec['ten_loai_hang'] . "'"));
                exit;
            case 'update':
                $id = (int)($_POST['id'] ?? 0);
                $ten = trim((string)($_POST['ten_loai_hang'] ?? ''));
                $ok = $lh->update($id, ['ten_loai_hang' => $ten]);
                if (!$ok) throw new Exception('Không cập nhật được bản ghi');
                header('Location: quan_ly_loai_hang.php?success=1&message=' . urlencode('Cập nhật thành công'));
                exit;
            case 'delete':
                $id = (int)($_POST['id'] ?? 0);
                $ok = $lh->delete($id);
                if (!$ok) throw new Exception('Không xóa được bản ghi');
                header('Location: quan_ly_loai_hang.php?success=1&message=' . urlencode('Đã xóa loại hàng'));
                exit;
        }
    } catch (Exception $e) {
        log_error('quan_ly_loai_hang', ['error' => $e->getMessage()]);
        header('Location: quan_ly_loai_hang.php?error=1&message=' . urlencode('Lỗi: ' . $e->getMessage()));
        exit;
    }
}

$all = $lh->getAll(true);
include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center">
                <h1 class="card-title"><i class="fas fa-tags text-primary me-3"></i>Quản Lý Loại Hàng</h1>
                <p class="card-text">Thêm, sửa, xóa loại hàng. Dữ liệu dùng để hiển thị dropdown ở trang tính toán và lọc lịch sử.</p>
            </div>
        </div>
    </div>
</div>

<?php if ($message): ?>
<div class="row mb-3"><div class="col-12">
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <i class="fas fa-<?php echo $messageType==='success'?'check-circle':'exclamation-triangle'; ?> me-2"></i>
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
</div></div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-plus me-2"></i>Thêm Loại Hàng</h5></div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label">Tên loại hàng <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="ten_loai_hang" required>
                    </div>
                    <div class="d-grid"><button class="btn btn-success" type="submit"><i class="fas fa-plus me-1"></i>Thêm</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Danh Sách Loại Hàng</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead><tr>
                            <th>ID</th><th>Tên</th><th>Thao tác</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($all as $r): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($r['id']); ?></strong></td>
                            <td><?php echo htmlspecialchars($r['ten_loai_hang']); ?></td>
                            <td>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-primary" onclick="editLH(<?php echo (int)$r['id']; ?>, '<?php echo htmlspecialchars($r['ten_loai_hang'], ENT_QUOTES); ?>')"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-sm btn-danger" onclick="delLH(<?php echo (int)$r['id']; ?>, '<?php echo htmlspecialchars($r['ten_loai_hang'], ENT_QUOTES); ?>')"><i class="fas fa-trash"></i></button>
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
<!-- Modal Edit -->
<div class="modal fade" id="editLHModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="fas fa-edit me-2"></i>Đổi tên Loại hàng</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST" action="">
        <div class="modal-body">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id" id="edit_id">
          <div class="mb-3"><label class="form-label">Tên loại hàng mới</label><input type="text" class="form-control" name="ten_loai_hang" id="edit_ten" required></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button><button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Lưu</button></div>
      </form>
    </div>
  </div>
</div>


<!-- Modal Delete -->
<div class="modal fade" id="delLHModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="fas fa-exclamation-triangle text-danger me-2"></i>Xóa loại hàng</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST" action="">
        <div class="modal-body">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" id="del_id">
          <p>Bạn có chắc chắn muốn xóa loại hàng <strong id="del_name"></strong>?</p>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-danger"><i class="fas fa-trash me-1"></i>Xóa</button></div>
      </form>
    </div>
  </div>
</div>

<script>
function editLH(id, ten){
  document.getElementById('edit_id').value = id;
  document.getElementById('edit_ten').value = ten;
  new bootstrap.Modal(document.getElementById('editLHModal')).show();
}
function delLH(id, ten){
  document.getElementById('del_id').value = id;
  document.getElementById('del_name').textContent = ten;
  new bootstrap.Modal(document.getElementById('delLHModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>


