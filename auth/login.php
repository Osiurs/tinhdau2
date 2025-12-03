<?php
/**
 * Trang đăng nhập
 */

require_once __DIR__ . '/../auth/auth_helper.php';
require_once __DIR__ . '/../models/User.php';

// Nếu đã đăng nhập, chuyển về trang chủ
if (isLoggedIn()) {
    redirectToHome();
}

$error = '';
$message = $_GET['message'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Vui lòng nhập đầy đủ thông tin';
    } else {
        $userModel = new User();
        $user = $userModel->authenticate($username, $password);
        
        if ($user) {
            loginUser($user);

            // Chuyển hướng về trang được yêu cầu hoặc trang chủ
            $redirect = $_GET['redirect'] ?? '../index.php';
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = 'Tên đăng nhập hoặc mật khẩu không đúng';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            /* VICEM-like warm brown palette - giống với dự án */
            --primary-color: #8B5E34; /* dark brown */
            --secondary-color: #C49A6C; /* light gold-brown */
            --accent-color: #D4A574;
            --light-bg: #FAF7F2; /* warm paper */
            --text-color: #4B3A2F; /* deep coffee */
            --border-color: #E8DFD6;
        }

        body {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            position: relative;
        }

        /* Background pattern */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image:
                radial-gradient(circle at 20% 50%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        .login-container {
            max-width: 450px;
            width: 100%;
            padding: 20px;
            position: relative;
            z-index: 1;
        }

        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(139, 94, 52, 0.3);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .login-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
        }

        .login-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg,
                transparent,
                rgba(255,255,255,0.3) 50%,
                transparent
            );
        }

        .login-header .icon {
            width: 70px;
            height: 70px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .login-header .icon i {
            font-size: 32px;
        }

        .login-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .login-header p {
            margin: 0;
            opacity: 0.95;
            font-size: 14px;
        }

        .login-body {
            padding: 40px 30px;
            background: var(--light-bg);
        }

        .form-label {
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-control {
            border-radius: 10px;
            border: 2px solid var(--border-color);
            padding: 12px 15px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: white;
        }

        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(196, 154, 108, 0.25);
            background: white;
        }

        .input-group {
            margin-bottom: 20px;
        }

        .input-group-text {
            background: white;
            border: 2px solid var(--border-color);
            border-right: none;
            border-radius: 10px 0 0 10px;
            color: var(--primary-color);
        }

        .input-group .form-control {
            border-left: none;
            border-radius: 0 10px 10px 0;
        }

        .input-group:focus-within .input-group-text {
            border-color: var(--secondary-color);
        }

        .btn-login {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            margin-top: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(139, 94, 52, 0.2);
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(139, 94, 52, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .alert {
            border-radius: 10px;
            border: none;
            font-size: 14px;
        }

        .alert-danger {
            background-color: #FEE2E2;
            color: #991B1B;
        }

        .alert-info {
            background-color: #DBEAFE;
            color: #1E40AF;
        }

        .login-footer {
            text-align: center;
            padding: 20px;
            background: white;
            border-top: 1px solid var(--border-color);
            font-size: 13px;
            color: var(--text-color);
        }

        .login-footer i {
            color: var(--secondary-color);
            margin-right: 5px;
        }

        /* Animation */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-card {
            animation: fadeInUp 0.6s ease-out;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="icon">
                    <i class="fas fa-ship"></i>
                </div>
                <h1>Đăng Nhập</h1>
                <p>Hệ Thống Tính Toán Nhiên Liệu Tàu</p>
            </div>

            <div class="login-body">
                <?php if (!empty($message)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label">
                            <i class="fas fa-user me-1"></i>Tên đăng nhập
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-user"></i>
                            </span>
                            <input type="text" class="form-control" id="username" name="username"
                                   placeholder="Nhập tên đăng nhập"
                                   value="<?php echo htmlspecialchars($username ?? ''); ?>"
                                   required autofocus>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock me-1"></i>Mật khẩu
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" class="form-control" id="password" name="password"
                                   placeholder="Nhập mật khẩu"
                                   required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i>Đăng Nhập
                    </button>
                </form>
            </div>

            <div class="login-footer">
                <i class="fas fa-shield-alt"></i>
                Hệ thống bảo mật - Phiên bản 1.0
            </div>
        </div>

        <div class="text-center mt-3">
            <small style="color: rgba(255,255,255,0.9); text-shadow: 0 1px 2px rgba(0,0,0,0.2);">
                <i class="fas fa-copyright me-1"></i>
                <?php echo date('Y'); ?> - Hệ Thống Quản Lý Nhiên Liệu Tàu
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

