<?php
/**
 * Trang Dashboard Admin - Tổng quan hệ thống
 */

require_once __DIR__ . '/../auth/check_auth.php';
require_once '../includes/helpers.php';
require_once '../config/database.php';
require_once '../models/HeSoTau.php';
require_once '../models/KhoangCach.php';

// Khởi tạo các đối tượng
$heSoTau = new HeSoTau();
$khoangCach = new KhoangCach();

// Lấy thống kê
$danhSachTau = $heSoTau->getDanhSachTau();
$danhSachDiem = $khoangCach->getDanhSachDiem();
$allTauData = $heSoTau->getAllData();
$allTuyenDuongData = $khoangCach->getAllData();

// Tính toán thống kê
$tongTau = count($danhSachTau);
$tongDiem = count($danhSachDiem);
$tongTuyenDuong = count($allTuyenDuongData);
$tongHeSo = count($allTauData);

// Khoảng cách trung bình
$avgDistance = array_sum(array_column($allTuyenDuongData, 'khoang_cach_km')) / $tongTuyenDuong;

// Hệ số trung bình
$avgKkh = array_sum(array_column($allTauData, 'k_ko_hang')) / $tongHeSo;
$avgKch = array_sum(array_column($allTauData, 'k_co_hang')) / $tongHeSo;

// Include header
include '../includes/header.php';
?>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center">
                <h1 class="card-title">
                    <i class="fas fa-tachometer-alt text-primary me-3"></i>
                    Dashboard Quản Lý
                </h1>
                <p class="card-text">
                    Tổng quan hệ thống tính toán nhiên liệu tàu
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <h5><i class="fas fa-ship me-2"></i>Tổng số tàu</h5>
                <div class="display-6"><?php echo $tongTau; ?></div>
                <small>Loại tàu</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <h5><i class="fas fa-map-marker-alt me-2"></i>Tổng số điểm</h5>
                <div class="display-6"><?php echo $tongDiem; ?></div>
                <small>Điểm vận chuyển</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <h5><i class="fas fa-route me-2"></i>Tổng tuyến đường</h5>
                <div class="display-6"><?php echo $tongTuyenDuong; ?></div>
                <small>Tuyến đường</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body text-center">
                <h5><i class="fas fa-cogs me-2"></i>Tổng hệ số</h5>
                <div class="display-6"><?php echo $tongHeSo; ?></div>
                <small>Hệ số nhiên liệu</small>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Statistics -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-ruler me-2"></i>
                    Thống Kê Khoảng Cách
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6">
                        <div class="text-center">
                            <div class="h4 text-primary"><?php echo number_format($avgDistance, 1); ?></div>
                            <small class="text-muted">Khoảng cách TB (km)</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center">
                            <div class="h4 text-success"><?php echo number_format(max(array_column($allTuyenDuongData, 'khoang_cach_km')), 1); ?></div>
                            <small class="text-muted">Khoảng cách max (km)</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-gas-pump me-2"></i>
                    Thống Kê Hệ Số
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6">
                        <div class="text-center">
                            <div class="h4 text-primary"><?php echo number_format($avgKkh, 6); ?></div>
                            <small class="text-muted">Hệ số không hàng TB</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center">
                            <div class="h4 text-success"><?php echo number_format($avgKch, 7); ?></div>
                            <small class="text-muted">Hệ số có hàng TB</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Thao Tác Nhanh
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <a href="quan_ly_tau.php" class="btn btn-primary btn-lg w-100 mb-3">
                            <i class="fas fa-ship me-2"></i>
                            Quản Lý Tàu
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="quan_ly_tuyen_duong.php" class="btn btn-success btn-lg w-100 mb-3">
                            <i class="fas fa-route me-2"></i>
                            Quản Lý Tuyến Đường
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="../danh_sach_tau.php" class="btn btn-info btn-lg w-100 mb-3">
                            <i class="fas fa-list me-2"></i>
                            Xem Danh Sách Tàu
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="../danh_sach_diem.php" class="btn btn-warning btn-lg w-100 mb-3">
                            <i class="fas fa-map-marker-alt me-2"></i>
                            Xem Danh Sách Điểm
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Data -->
<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-ship me-2"></i>
                    Tàu Gần Đây
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Tên tàu</th>
                                <th>Khoảng cách</th>
                                <th>Hệ số</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $recentTau = array_slice($allTauData, 0, 5);
                            foreach ($recentTau as $tau): 
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($tau['ten_tau']); ?></strong></td>
                                <td><?php echo number_format($tau['km_min']); ?>-<?php echo number_format($tau['km_max']); ?> km</td>
                                <td>
                                    <span class="badge bg-primary"><?php echo number_format($tau['k_ko_hang'], 4); ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-route me-2"></i>
                    Tuyến Đường Gần Đây
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Tuyến đường</th>
                                <th>Khoảng cách</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $recentTuyenDuong = array_slice($allTuyenDuongData, 0, 5);
                            foreach ($recentTuyenDuong as $tuyenDuong): 
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($tuyenDuong['diem_dau']); ?></strong> → 
                                    <strong><?php echo htmlspecialchars($tuyenDuong['diem_cuoi']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo number_format($tuyenDuong['khoang_cach_km'], 1); ?> km</span>
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

<?php include '../includes/footer.php'; ?>















