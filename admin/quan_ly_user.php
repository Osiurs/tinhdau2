<?php
/**
 * Trang quản lý người dùng (chỉ dành cho admin)
 */

require_once __DIR__ . '/../auth/check_admin.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/database.php';

$userModel = new User();
$alert = '';

// Xử lý các action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add':
                $username = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';
                $fullName = trim($_POST['full_name'] ?? '');
                $role = $_POST['role'] ?? 'user';
                
                $userModel->create($username, $password, $fullName, $role);
                $alert = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Đã thêm người dùng thành công</div>';
                break;
                
            case 'update':
                $id = (int)($_POST['id'] ?? 0);
                $data = [
                    'full_name' => trim($_POST['full_name'] ?? ''),
                    'role' => $_POST['role'] ?? 'user',
                    'status' => $_POST['status'] ?? 'active'
                ];
                
                // Chỉ cập nhật password nếu có nhập
                if (!empty($_POST['password'])) {
                    $data['password'] = $_POST['password'];
                }
                
                if ($userModel->update($id, $data)) {
                    $alert = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Đã cập nhật người dùng thành công</div>';
                } else {
                    $alert = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Không thể cập nhật người dùng</div>';
                }
                break;
                
            case 'delete':
                $id = (int)($_POST['id'] ?? 0);
                
                // Không cho phép xóa chính mình
                if ($id == $_SESSION['user_id']) {
                    throw new Exception('Không thể xóa tài khoản đang đăng nhập');
                }
                
                if ($userModel->delete($id)) {
                    $alert = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Đã xóa người dùng thành công</div>';
                } else {
                    $alert = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Không thể xóa người dùng</div>';
                }
                break;
        }
    } catch (Exception $e) {
        $alert = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Lấy danh sách users
$users = $userModel->getAll();

// Include header
include __DIR__ . '/../includes/header.php';
?>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h1 class="card-title">
                    <i class="fas fa-users text-primary me-3"></i>
                    Quản Lý Người Dùng
                </h1>
                <p class="card-text">Quản lý tài khoản người dùng và phân quyền hệ thống</p>
            </div>
        </div>
    </div>
</div>

<?php echo $alert; ?>

<!-- Nút thêm user -->
<div class="row mb-3">
    <div class="col-12">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fas fa-plus me-2"></i>Thêm Người Dùng
        </button>
    </div>
</div>

<!-- Danh sách users -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Danh Sách Người Dùng (<?php echo count($users); ?>)
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tên đăng nhập</th>
                                <th>Họ tên</th>
                                <th>Vai trò</th>
                                <th>Trạng thái</th>
                                <th>Ngày tạo</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted">
                                        <i class="fas fa-inbox me-2"></i>Chưa có người dùng nào
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['id']); ?></td>
                                        <td>
                                            <i class="fas fa-user me-2"></i>
                                            <?php echo htmlspecialchars($user['username']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                        <td>
                                            <?php if ($user['role'] === 'admin'): ?>
                                                <span class="badge bg-danger">
                                                    <i class="fas fa-crown me-1"></i>Admin
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-primary">
                                                    <i class="fas fa-user me-1"></i>User
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['status'] === 'active'): ?>
                                                <span class="badge bg-success">Hoạt động</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Vô hiệu</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-warning"
                                                    onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <button type="button" class="btn btn-sm btn-danger"
                                                        onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Thêm User -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user-plus me-2"></i>Thêm Người Dùng
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="add_username" class="form-label">Tên đăng nhập *</label>
                        <input type="text" class="form-control" id="add_username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="add_password" class="form-label">Mật khẩu *</label>
                        <input type="password" class="form-control" id="add_password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="add_full_name" class="form-label">Họ tên</label>
                        <input type="text" class="form-control" id="add_full_name" name="full_name">
                    </div>
                    <div class="mb-3">
                        <label for="add_role" class="form-label">Vai trò *</label>
                        <select class="form-select" id="add_role" name="role" required>
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Lưu
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Sửa User -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user-edit me-2"></i>Sửa Người Dùng
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Tên đăng nhập</label>
                        <input type="text" class="form-control" id="edit_username" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="edit_password" class="form-label">Mật khẩu mới (để trống nếu không đổi)</label>
                        <input type="password" class="form-control" id="edit_password" name="password">
                    </div>
                    <div class="mb-3">
                        <label for="edit_full_name" class="form-label">Họ tên</label>
                        <input type="text" class="form-control" id="edit_full_name" name="full_name">
                    </div>
                    <div class="mb-3">
                        <label for="edit_role" class="form-label">Vai trò *</label>
                        <select class="form-select" id="edit_role" name="role" required>
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_status" class="form-label">Trạng thái *</label>
                        <select class="form-select" id="edit_status" name="status" required>
                            <option value="active">Hoạt động</option>
                            <option value="inactive">Vô hiệu</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Cập nhật
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Form xóa user (ẩn) -->
<form id="deleteUserForm" method="POST" action="" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_id">
</form>

<script>
function editUser(user) {
    document.getElementById('edit_id').value = user.id;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_full_name').value = user.full_name || '';
    document.getElementById('edit_role').value = user.role;
    document.getElementById('edit_status').value = user.status;
    document.getElementById('edit_password').value = '';

    var modal = new bootstrap.Modal(document.getElementById('editUserModal'));
    modal.show();
}

function deleteUser(id, username) {
    if (confirm('Bạn có chắc chắn muốn xóa người dùng "' + username + '"?')) {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteUserForm').submit();
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

