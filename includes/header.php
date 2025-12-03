<?php
// Load auth helper để kiểm tra quyền
require_once __DIR__ . '/../auth/auth_helper.php';
$currentUser = getCurrentUser();
$isUserAdmin = isAdmin();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - <?php echo VERSION; ?></title>
    
    <!-- Modern Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Flatpickr CSS for calendar datepicker -->
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">

    <!-- UX Enhancements (Usability Only) -->
    <?php
    // Xác định prefix dựa trên vị trí file hiện tại
    $scriptPath = $_SERVER['SCRIPT_NAME'];
    if (strpos($scriptPath, '/admin/') !== false || strpos($scriptPath, '/auth/') !== false) {
        $prefix = '../';
    } else {
        $prefix = '';
    }
    ?>
    <link href="<?php echo $prefix; ?>assets/ux-enhancements.css" rel="stylesheet">

    <style>
        :root {
            /* VICEM-like warm brown palette */
            --primary-color: #8B5E34; /* dark brown */
            --secondary-color: #C49A6C; /* light gold-brown */
            --success-color: #16A34A;
            --warning-color: #F59E0B;
            --danger-color: #EF4444;
            --light-bg: #FAF7F2; /* warm paper */
            --text-color: #4B3A2F; /* deep coffee */
            --muted-text: #7A6E63;
            --card-bg: #ffffff;
            --border-color: #E8DFD6;
        }
        
        body {
            background-color: var(--light-bg);
            color: var(--text-color);
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.55;
        }
        html { scroll-behavior: smooth; }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
            backdrop-filter: saturate(1.2) blur(8px);
            position: relative;
            z-index: 1030;
        }
        .navbar .container { display: flex; align-items: center; flex-wrap: nowrap; gap: 8px; }

        /* Fix dropdown menu z-index and styling */
        .navbar .dropdown-menu {
            z-index: 1050 !important;
            position: absolute !important;
            margin-top: 0.5rem;
            border: 1px solid rgba(0,0,0,0.1);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            border-radius: 10px;
            min-width: 200px;
        }

        .navbar .dropdown-menu .dropdown-item {
            padding: 0.6rem 1rem;
            transition: background-color 0.2s ease;
        }

        .navbar .dropdown-menu .dropdown-item:hover {
            background-color: var(--light-bg);
        }

        .navbar .dropdown-menu .dropdown-header {
            color: var(--text-color);
            font-weight: 600;
            padding: 0.6rem 1rem;
        }
        
        .navbar-brand {
            font-weight: bold;
            color: white !important;
            letter-spacing: .2px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 60vw;
        }
        .navbar .btn { border-color: rgba(255,255,255,.6) !important; }
        
        .card {
            border: 1px solid var(--border-color);
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(2, 8, 20, 0.06);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }
        
        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 40px rgba(2, 8, 20, 0.08);
        }
        
        .card-header {
            background: linear-gradient(135deg, rgba(139,94,52,0.96), rgba(196,154,108,0.96));
            color: white;
            border-radius: 16px 16px 0 0 !important;
            font-weight: 600;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 26px;
            padding: 10px 22px;
            font-weight: 600;
            letter-spacing: .2px;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .btn-success {
            background: linear-gradient(135deg, #22C55E, #16A34A);
            border: none;
            border-radius: 26px;
            font-weight: 600;
        }
        .btn-outline-secondary {
            border-radius: 26px;
            color: var(--text-color);
            border-color: #cbd5e1;
        }
        .btn:focus { box-shadow: 0 0 0 0.2rem rgba(196,154,108,.25) !important; }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        .form-label { font-weight: 600; color: var(--text-color); }
        .form-text { color: var(--muted-text) !important; }
        .form-check-input:checked { background-color: var(--secondary-color); border-color: var(--secondary-color); }
        
        .result-card {
            background: linear-gradient(135deg, #fff, #f8f9fa);
            border-left: 5px solid var(--success-color);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            box-shadow: 0 6px 24px rgba(2,8,20,.06);
        }
        
        .table {
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
            border: 1px solid var(--border-color);
        }
        
        .table thead th {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            font-weight: 600;
            letter-spacing: .3px;
        }
        .table tbody tr:hover { background-color: #F6EFE7; }
        .table td, .table th { vertical-align: middle; }
        
        .footer {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            text-align: center;
            padding: 24px 0;
            margin-top: 56px;
        }
        
        .loading {
            display: none;
        }
        
        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
        }

        /* Sidebar layout */
        .app-sidebar {
            background-color: #ffffff;
            border-right: 1px solid #e9ecef;
            width: 260px;
            transition: width 0.25s ease;
        }
        .app-sidebar .sidebar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            border-bottom: 1px solid #f1f3f5;
            background: linear-gradient(135deg, rgba(139,94,52,0.06), rgba(196,154,108,0.1));
        }
        .app-sidebar .sidebar-header .sidebar-title { flex: 1 1 auto; }
        .app-sidebar .sidebar-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }
        .app-sidebar .list-group-item {
            border: 0;
            padding: 10px 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-color);
            border-radius: 10px;
            margin: 4px 8px;
            transition: background-color .2s ease, color .2s ease;
        }
        .app-sidebar .list-group-item:hover {
            background-color: #F3E9DD;
        }
        .app-sidebar .list-group-item.active {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: #fff;
        }
        .app-sidebar .list-group-item.active i {
            color: #fff;
        }
        .app-sidebar .menu-section {
            font-size: 12px;
            font-weight: 700;
            color: #9F8F82;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .app-sidebar .item-text {
            white-space: nowrap;
        }
        /* Collapsed state */
        body.sidebar-collapsed #desktopSidebar {
            width: 72px;
        }
        body.sidebar-collapsed #desktopSidebar .sidebar-header .sidebar-title .title-text,
        body.sidebar-collapsed #desktopSidebar .item-text,
        body.sidebar-collapsed #desktopSidebar .menu-section {
            display: none;
        }
        body.sidebar-collapsed #desktopSidebar .sidebar-header {
            justify-content: center;
        }
        body.sidebar-collapsed #desktopSidebar .sidebar-header .sidebar-title i { display: none; }
        body.sidebar-collapsed #desktopSidebar .list-group-item {
            justify-content: center;
            padding-left: 0.75rem;
            padding-right: 0.75rem;
        }
        /* Make row act like fluid layout between sidebar and main */
        #layoutRow {
            display: flex;
            flex-wrap: nowrap;
        }
        #mainContentCol {
            flex: 1 1 auto;
        }
        @media (min-width: 992px) {
            /* When collapsed, give main more space by reducing sidebar flex-basis */
            body.sidebar-collapsed #desktopSidebar { flex: 0 0 72px; max-width: 72px; }
            body.sidebar-collapsed #mainContentCol { flex: 1 1 auto; max-width: none; }
        }
        
        /* Desktop sticky sidebar */
        @media (min-width: 992px) {
            #desktopSidebar {
                position: sticky;
                top: 0; /* stick to top of viewport */
                align-self: flex-start;
                max-height: 100vh; /* fill viewport height */
                overflow: auto; /* scroll inside when long */
                min-height: auto; /* override inline min-height */
            }
        }
        
        /* Dropdown tìm kiếm điểm */
        .diem-results {
            border: 2px solid var(--secondary-color);
            border-top: none;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 12px 30px rgba(2,8,20,.1);
            z-index: 1000;
            animation: fadeIn .15s ease;
        }
        
        .diem-results .dropdown-item {
            padding: 10px 15px;
            border-bottom: 1px solid #f8f9fa;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        
        .diem-results .dropdown-item:hover {
            background-color: var(--light-bg);
        }
        
        .diem-results .dropdown-item:last-child {
            border-bottom: none;
        }
        
        /* Input readonly */
        .form-control[readonly] {
            background-color: #f8f9fa;
            cursor: pointer;
        }
        
        .form-control[readonly]:hover {
            background-color: #e9ecef;
        }

        /* Small utilities */
        .display-4 { font-weight: 800; letter-spacing: -.5px; }
        .badge { border-radius: 999px; padding: .5em .75em; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: translateY(0); } }

        /* Brand logo helper */
        .brand-logo { height: 28px; width: auto; display: inline-block; vertical-align: middle; }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <button class="btn btn-outline-light d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Mở menu">
                <i class="fas fa-bars"></i>
            </button>
            <a class="navbar-brand" href="<?php echo $prefix; ?>index.php">
                <?php
                    $logoPath = $prefix . 'assets/logo-vicem.png';
                    if (file_exists($logoPath)) {
                        echo '<img src="' . htmlspecialchars($logoPath) . '" alt="Logo" class="brand-logo me-2">';
                    } else {
                        echo '<i class="fas fa-ship me-2"></i>';
                    }
                    echo SITE_NAME;
                ?>
            </a>

            <!-- User info and logout -->
            <div class="ms-auto d-flex align-items-center">
                <?php if ($currentUser): ?>
                    <!-- User Info with Dropdown -->
                    <div class="dropdown me-2">
                        <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i>
                            <span class="d-none d-md-inline"><?php echo htmlspecialchars($currentUser['full_name'] ?: $currentUser['username']); ?></span>
                            <?php if ($isUserAdmin): ?>
                                <span class="badge bg-danger ms-1">Admin</span>
                            <?php endif; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li>
                                <h6 class="dropdown-header">
                                    <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($currentUser['full_name'] ?: $currentUser['username']); ?>
                                </h6>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="<?php echo $prefix; ?>auth/change_password.php">
                                    <i class="fas fa-key me-2"></i>Đổi mật khẩu
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="<?php echo $prefix; ?>auth/logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>Đăng xuất
                                </a>
                            </li>
                        </ul>
                    </div>
                <?php endif; ?>

                <button id="sidebarToggleDesktop" class="btn btn-outline-light d-none d-xl-inline-flex" type="button" aria-label="Thu gọn/hiện sidebar">
                    <i class="fas fa-angles-left me-1"></i><span class="d-none d-xl-inline"> Thu gọn</span>
                </button>
            </div>
        </div>
    </nav>

    <!-- Sidebar Offcanvas (mobile) -->
    <div class="offcanvas offcanvas-start" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="sidebarOffcanvasLabel"><i class="fas fa-bars me-2"></i>Menu</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body p-0">
                <div class="list-group list-group-flush">
                <a class="list-group-item list-group-item-action" href="<?php echo $prefix; ?>index.php"><i class="fas fa-calculator me-2"></i>Tính toán</a>
                <a class="list-group-item list-group-item-action" href="<?php echo $prefix; ?>danh_sach_tau.php"><i class="fas fa-list me-2"></i>Danh sách tàu</a>
                <a class="list-group-item list-group-item-action" href="<?php echo $prefix; ?>lich_su.php"><i class="fas fa-database me-2"></i>Lịch sử đã lưu</a>
                <a class="list-group-item list-group-item-action" href="<?php echo $prefix; ?>danh_sach_diem.php"><i class="fas fa-map-marker-alt me-2"></i>Danh sách điểm</a>

                <!-- Menu Quản lý - Hiển thị cho tất cả user -->
                <div class="list-group-item fw-bold text-muted"><i class="fas fa-cogs me-2"></i>Quản lý</div>
                <a class="list-group-item list-group-item-action ps-4" href="<?php echo $prefix; ?>admin/quan_ly_tau.php"><i class="fas fa-ship me-2"></i>Quản lý tàu</a>
                <a class="list-group-item list-group-item-action ps-4" href="<?php echo $prefix; ?>admin/quan_ly_tuyen_duong.php"><i class="fas fa-route me-2"></i>Quản lý tuyến đường</a>
                <a class="list-group-item list-group-item-action ps-4" href="<?php echo $prefix; ?>admin/quan_ly_dau_ton.php"><i class="fas fa-oil-can me-2"></i>Quản lý dầu tồn</a>
                <a class="list-group-item list-group-item-action ps-4" href="<?php echo $prefix; ?>admin/bao_cao_dau_ton.php"><i class="fas fa-chart-line me-2"></i>Báo cáo dầu tồn</a>
                <a class="list-group-item list-group-item-action ps-4" href="<?php echo $prefix; ?>admin/quan_ly_cay_xang.php"><i class="fas fa-gas-pump me-2"></i>Quản lý cây xăng</a>
                <a class="list-group-item list-group-item-action ps-4" href="<?php echo $prefix; ?>admin/quan_ly_loai_hang.php"><i class="fas fa-tags me-2"></i>Quản lý loại hàng</a>

                <!-- Quản lý người dùng - CHỈ admin -->
                <?php if ($isUserAdmin): ?>
                <a class="list-group-item list-group-item-action ps-4" href="<?php echo $prefix; ?>admin/quan_ly_user.php"><i class="fas fa-users me-2"></i>Quản lý người dùng</a>
                <?php endif; ?>

                <!-- Tài khoản -->
                <div class="list-group-item fw-bold text-muted"><i class="fas fa-user-cog me-2"></i>Tài khoản</div>
                <a class="list-group-item list-group-item-action ps-4" href="<?php echo $prefix; ?>auth/change_password.php"><i class="fas fa-key me-2"></i>Đổi mật khẩu</a>
                <a class="list-group-item list-group-item-action ps-4 text-danger" href="<?php echo $prefix; ?>auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Đăng xuất</a>
            </div>
        </div>
    </div>

    <!-- Layout with Sidebar (desktop) -->
    <div class="container-fluid">
        <?php 
            $script = $_SERVER['SCRIPT_NAME'] ?? '';
            $page = basename($script);
            $activeIndex = ($page === 'index.php');
            $activeTau = ($page === 'danh_sach_tau.php');
            $activeLichSu = ($page === 'lich_su.php');
            $activeDiem = ($page === 'danh_sach_diem.php');
            $activeQLTau = ($page === 'quan_ly_tau.php');
            $activeQLTuyen = ($page === 'quan_ly_tuyen_duong.php');
            $activeQLDauTon = ($page === 'quan_ly_dau_ton.php');
            $activeBaoCaoDauTon = ($page === 'bao_cao_dau_ton.php');
            $activeQLCayXang = ($page === 'quan_ly_cay_xang.php');
            $activeQLLoaiHang = ($page === 'quan_ly_loai_hang.php');
        ?>
        <div class="row" id="layoutRow">
            <!-- Sidebar (desktop) -->
            <aside id="desktopSidebar" class="app-sidebar d-none d-lg-block" style="min-height: calc(100vh - 70px);">
                <div class="sidebar-header">
                    <div class="sidebar-title">
                        <i class="fas fa-bars"></i>
                        <span class="title-text">Menu</span>
                    </div>
                    <button id="sidebarToggleInside" class="btn btn-sm btn-outline-secondary" type="button" aria-label="Thu gọn/hiện sidebar">
                        <i class="fas fa-angles-left"></i>
                    </button>
                </div>
                <div class="p-2">
                    <div class="list-group list-group-flush">
                        <a class="list-group-item list-group-item-action <?php echo $activeIndex ? 'active' : ''; ?>" href="<?php echo $prefix; ?>index.php">
                            <i class="fas fa-calculator"></i>
                            <span class="item-text">Tính toán</span>
                        </a>
                        <a class="list-group-item list-group-item-action <?php echo $activeTau ? 'active' : ''; ?>" href="<?php echo $prefix; ?>danh_sach_tau.php">
                            <i class="fas fa-list"></i>
                            <span class="item-text">Danh sách tàu</span>
                        </a>
                        <a class="list-group-item list-group-item-action <?php echo $activeLichSu ? 'active' : ''; ?>" href="<?php echo $prefix; ?>lich_su.php">
                            <i class="fas fa-database"></i>
                            <span class="item-text">Lịch sử đã lưu</span>
                        </a>
                        <a class="list-group-item list-group-item-action <?php echo $activeDiem ? 'active' : ''; ?>" href="<?php echo $prefix; ?>danh_sach_diem.php">
                            <i class="fas fa-map-marker-alt"></i>
                            <span class="item-text">Danh sách điểm</span>
                        </a>

                        <!-- Menu Quản lý - Hiển thị cho tất cả user đã đăng nhập -->
                        <div class="list-group-item menu-section">
                            <i class="fas fa-cogs me-2"></i><span class="item-text">Quản lý</span>
                        </div>
                        <a class="list-group-item list-group-item-action ps-4 <?php echo $activeQLTau ? 'active' : ''; ?>" href="<?php echo $prefix; ?>admin/quan_ly_tau.php">
                            <i class="fas fa-ship"></i>
                            <span class="item-text">Quản lý tàu</span>
                        </a>
                        <a class="list-group-item list-group-item-action ps-4 <?php echo $activeQLTuyen ? 'active' : ''; ?>" href="<?php echo $prefix; ?>admin/quan_ly_tuyen_duong.php">
                            <i class="fas fa-route"></i>
                            <span class="item-text">Quản lý tuyến đường</span>
                        </a>
                        <a class="list-group-item list-group-item-action ps-4 <?php echo $activeQLDauTon ? 'active' : ''; ?>" href="<?php echo $prefix; ?>admin/quan_ly_dau_ton.php">
                            <i class="fas fa-oil-can"></i>
                            <span class="item-text">Quản lý dầu tồn</span>
                        </a>
                        <a class="list-group-item list-group-item-action ps-4 <?php echo $activeBaoCaoDauTon ? 'active' : ''; ?>" href="<?php echo $prefix; ?>admin/bao_cao_dau_ton.php">
                            <i class="fas fa-chart-line"></i>
                            <span class="item-text">Báo cáo dầu tồn</span>
                        </a>
                        <a class="list-group-item list-group-item-action ps-4 <?php echo $activeQLCayXang ? 'active' : ''; ?>" href="<?php echo $prefix; ?>admin/quan_ly_cay_xang.php">
                            <i class="fas fa-gas-pump"></i>
                            <span class="item-text">Quản lý cây xăng</span>
                        </a>
                        <a class="list-group-item list-group-item-action ps-4 <?php echo $activeQLLoaiHang ? 'active' : ''; ?>" href="<?php echo $prefix; ?>admin/quan_ly_loai_hang.php">
                            <i class="fas fa-tags"></i>
                            <span class="item-text">Quản lý loại hàng</span>
                        </a>

                        <!-- Quản lý người dùng - CHỈ dành cho Admin -->
                        <?php if ($isUserAdmin): ?>
                        <?php
                            $activeQLUser = ($page === 'quan_ly_user.php');
                        ?>
                        <a class="list-group-item list-group-item-action ps-4 <?php echo $activeQLUser ? 'active' : ''; ?>" href="<?php echo $prefix; ?>admin/quan_ly_user.php">
                            <i class="fas fa-users"></i>
                            <span class="item-text">Quản lý người dùng</span>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </aside>

            <!-- Main Content -->
            <div id="mainContentCol" class="col-12 col-lg-9">
                <div class="container mt-4">
