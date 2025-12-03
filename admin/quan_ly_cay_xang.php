<?php
require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/CayXang.php';

$cx = new CayXang();
$alert = '';

// Sanitize GET/POST early
if (!empty($_GET)) { $_GET = sanitize_input($_GET); }
if (!empty($_POST)) { $_POST = sanitize_input($_POST); }

$list = $cx->getAll();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $act = (string)($_POST['act'] ?? '');
    $ten = trim((string)($_POST['ten'] ?? ''));
    try {
        if ($act === 'add') {
            $cx->add($ten);
            $alert = '<div class="alert alert-success">Đã thêm cây xăng thành công.</div>';
        } elseif ($act === 'delete') {
            if ($cx->remove($ten)) {
                $alert = '<div class="alert alert-success">Đã xóa cây xăng thành công.</div>';
            } else {
                $alert = '<div class="alert alert-warning">Không tìm thấy cây xăng để xóa.</div>';
            }
        } elseif ($act === 'rename') {
            $newName = trim((string)($_POST['new_name'] ?? ''));
            $cx->rename($ten, $newName);
            $alert = '<div class="alert alert-success">Đã cập nhật tên cây xăng thành công.</div>';
        }
    } catch (Exception $e) {
        log_error('quan_ly_cay_xang', ['error' => $e->getMessage()]);
        $alert = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h2 class="mb-1"><i class="fas fa-gas-pump text-primary me-2"></i>Quản lý cây xăng</h2>
                    <div class="text-muted">Chuẩn hóa danh mục cây xăng để dùng khi cấp thêm dầu</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($alert)) echo $alert; ?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><i class="fas fa-plus me-2"></i>Thêm cây xăng</div>
            <div class="card-body">
                <form method="post" onsubmit="return (this.ten.value||'').trim().length>0;">
                    <input type="hidden" name="act" value="add" />
                    <div class="mb-3">
                        <label class="form-label">Tên cây xăng</label>
                        <input type="text" class="form-control" name="ten" placeholder="vd: Cây xăng ABC - Q9" required />
                        <div class="form-text">
                            <i class="fas fa-info-circle me-1"></i>
                            Tên sẽ được dùng cho dropdown ở Quản lý dầu tồn<br>
                            <small class="text-muted">
                                <i class="fas fa-shield-alt me-1"></i>
                                Hệ thống sẽ tự động kiểm tra trùng lặp (không phân biệt dấu tiếng Việt)
                            </small>
                        </div>
                    </div>
                    <button class="btn btn-success" type="submit"><i class="fas fa-save me-1"></i>Lưu</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2"></i>
                        Danh sách cây xăng
                    </h5>
                    <div class="d-flex gap-2">
                        <div class="input-group" style="width: 300px;">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="searchCayXang" placeholder="Tìm kiếm cây xăng...">
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="cayXangTable">
                        <thead>
                            <tr>
                                <th>Tên cây xăng</th>
                                <th class="text-end">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($list)): ?>
                                <tr><td colspan="2" class="text-center text-muted">Chưa có dữ liệu</td></tr>
                            <?php else: foreach ($list as $name): ?>
                                <tr data-search="<?php echo strtolower(htmlspecialchars($name)); ?>">
                                    <td><?php echo htmlspecialchars($name); ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-primary me-2" data-bs-toggle="modal" data-bs-target="#modalEdit" data-name="<?php echo htmlspecialchars($name); ?>" title="Chỉnh sửa">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Xóa cây xăng này?');">
                                            <input type="hidden" name="act" value="delete" />
                                            <input type="hidden" name="ten" value="<?php echo htmlspecialchars($name); ?>" />
                                            <button class="btn btn-sm btn-danger" title="Xóa"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal edit tên cây xăng -->
<div class="modal fade" id="modalEdit" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-pen me-2"></i>Sửa tên cây xăng</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post">
        <div class="modal-body">
            <input type="hidden" name="act" value="rename" />
            <input type="hidden" name="ten" id="oldName" />
            <div class="mb-3">
                <label class="form-label">Tên mới</label>
                <input type="text" class="form-control" name="new_name" id="newName" required />
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Lưu</button>
        </div>
      </form>
    </div>
  </div>
  <script>
  document.addEventListener('DOMContentLoaded', function() {
    // Xử lý modal edit
    var modal = document.getElementById('modalEdit');
    if (modal) {
      modal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        if (!button) return;
        var name = button.getAttribute('data-name') || '';
        document.getElementById('oldName').value = name;
        document.getElementById('newName').value = name;
      });
    }
    
    // Tìm kiếm cây xăng real-time
    const searchInput = document.getElementById('searchCayXang');
    const table = document.getElementById('cayXangTable');
    
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
  });
  </script>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>


