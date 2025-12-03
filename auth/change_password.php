<?php
/**
 * Trang đổi mật khẩu - Cho phép user đổi mật khẩu của chính mình
 */

require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../auth/auth_helper.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/database.php';

$currentUser = getCurrentUser();
$userModel = new User();
$alert = '';
$alertType = '';

// Xử lý form đổi mật khẩu
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldPassword = $_POST['old_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    try {
        // Validate
        if (empty($oldPassword)) {
            throw new Exception('Vui lòng nhập mật khẩu cũ');
        }
        
        if (empty($newPassword)) {
            throw new Exception('Vui lòng nhập mật khẩu mới');
        }
        
        if (strlen($newPassword) < 6) {
            throw new Exception('Mật khẩu mới phải có ít nhất 6 ký tự');
        }
        
        if ($newPassword !== $confirmPassword) {
            throw new Exception('Mật khẩu mới và xác nhận mật khẩu không khớp');
        }
        
        if ($oldPassword === $newPassword) {
            throw new Exception('Mật khẩu mới phải khác mật khẩu cũ');
        }
        
        // Đổi mật khẩu
        $userModel->changePassword($currentUser['id'], $oldPassword, $newPassword);
        
        $alert = 'Đổi mật khẩu thành công!';
        $alertType = 'success';
        
        // Clear form
        $_POST = [];
        
    } catch (Exception $e) {
        $alert = $e->getMessage();
        $alertType = 'danger';
    }
}

$pageTitle = 'Đổi mật khẩu';
require_once '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-key me-2"></i>Đổi mật khẩu
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($alert)): ?>
                        <div class="alert alert-<?php echo $alertType; ?> alert-dismissible fade show" role="alert">
                            <i class="fas fa-<?php echo $alertType === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                            <?php echo htmlspecialchars($alert); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3 p-3 bg-light rounded">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-user-circle fa-2x text-primary me-3"></i>
                            <div>
                                <strong><?php echo htmlspecialchars($currentUser['full_name'] ?: $currentUser['username']); ?></strong>
                                <br>
                                <small class="text-muted">
                                    <i class="fas fa-at me-1"></i><?php echo htmlspecialchars($currentUser['username']); ?>
                                </small>
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="" id="changePasswordForm">
                        <div class="mb-3">
                            <label for="old_password" class="form-label">
                                <i class="fas fa-lock me-1"></i>Mật khẩu cũ <span class="text-danger">*</span>
                            </label>
                            <input type="password" class="form-control" id="old_password" name="old_password" 
                                   required autocomplete="current-password">
                        </div>

                        <div class="mb-3">
                            <label for="new_password" class="form-label">
                                <i class="fas fa-key me-1"></i>Mật khẩu mới <span class="text-danger">*</span>
                            </label>
                            <input type="password" class="form-control" id="new_password" name="new_password" 
                                   required minlength="6" autocomplete="new-password">
                            <small class="text-muted">Tối thiểu 6 ký tự</small>
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">
                                <i class="fas fa-check-double me-1"></i>Xác nhận mật khẩu mới <span class="text-danger">*</span>
                            </label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                   required minlength="6" autocomplete="new-password">
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Đổi mật khẩu
                            </button>
                            <a href="<?php echo $prefix; ?>index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Hủy
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mt-3 border-warning">
                <div class="card-body">
                    <h6 class="text-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>Lưu ý
                    </h6>
                    <ul class="mb-0 small">
                        <li>Mật khẩu mới phải có ít nhất 6 ký tự</li>
                        <li>Mật khẩu mới phải khác mật khẩu cũ</li>
                        <li>Sau khi đổi mật khẩu, bạn vẫn đăng nhập bình thường</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

