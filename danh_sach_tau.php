<?php
/**
 * Trang hiển thị danh sách tàu và thông tin hệ số nhiên liệu
 */

require_once __DIR__ . '/auth/check_auth.php';
require_once 'includes/helpers.php';
require_once 'config/database.php';
require_once 'models/HeSoTau.php';
require_once 'models/TauPhanLoai.php';

// Khởi tạo đối tượng hệ số tàu
$heSoTau = new HeSoTau();
$phanLoaiModel = new TauPhanLoai();

// Lấy danh sách tàu
$danhSachTau = $heSoTau->getDanhSachTau();

// Lấy thông tin chi tiết của tàu được chọn
$thongTinTau = null;
$tenTauChon = $_GET['tau'] ?? '';

if (!empty($tenTauChon)) {
    $thongTinTau = $heSoTau->getThongTinTau($tenTauChon);
}

// Include header
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center">
                <h1 class="card-title">
                    <i class="fas fa-list text-primary me-3"></i>
                    Danh Sách Tàu
                </h1>
                <p class="card-text">
                    Xem thông tin chi tiết về hệ số nhiên liệu của các loại tàu trong hệ thống
                </p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Danh sách tàu -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-ship me-2"></i>
                    Danh Sách Tàu (<?php echo count($danhSachTau); ?>)
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" id="search_tau" placeholder="Tìm kiếm tàu...">
                    </div>
                    <small class="text-muted">Gõ tên tàu để lọc danh sách.</small>
                </div>
                <div class="list-group" id="tauList">
                    <?php foreach ($danhSachTau as $tau): $pl = $phanLoaiModel->getPhanLoai($tau) ?? 'cong_ty'; ?>
                    <a href="?tau=<?php echo urlencode($tau); ?>" 
                       class="list-group-item list-group-item-action <?php echo ($tenTauChon === $tau) ? 'active' : ''; ?>">
                        <i class="fas fa-ship me-2"></i>
                        <?php echo htmlspecialchars(formatTau($tau)); ?>
                        <span class="badge float-end <?php echo $pl === 'cong_ty' ? 'bg-primary' : 'bg-secondary'; ?>">
                            <?php echo $pl === 'cong_ty' ? 'Công ty' : 'Thuê ngoài'; ?>
                        </span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Thông tin chi tiết tàu -->
    <div class="col-lg-8">
        <?php if ($thongTinTau): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Thông Tin Chi Tiết: <?php echo htmlspecialchars(formatTau($tenTauChon)); ?>
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Khoảng cách (km)</th>
                                <th>Hệ số không hàng (Lít/Km)</th>
                                <th>Hệ số có hàng (Lít/T.Km)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($thongTinTau as $thongTin): ?>
                            <tr>
                                <td>
                                    <strong><?php echo number_format($thongTin['km_min']); ?> - <?php echo number_format($thongTin['km_max']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-primary">
                                        <?php echo number_format($thongTin['k_ko_hang'], 6); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-success">
                                        <?php echo number_format($thongTin['k_co_hang'], 7); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Thống kê -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6>Hệ số không hàng trung bình</h6>
                                <div class="h4 text-primary">
                                    <?php 
                                    $avgKkh = array_sum(array_column($thongTinTau, 'k_ko_hang')) / count($thongTinTau);
                                    echo number_format($avgKkh, 6);
                                    ?>
                                </div>
                                <small class="text-muted">Lít/Km</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6>Hệ số có hàng trung bình</h6>
                                <div class="h4 text-success">
                                    <?php 
                                    $avgKch = array_sum(array_column($thongTinTau, 'k_co_hang')) / count($thongTinTau);
                                    echo number_format($avgKch, 7);
                                    ?>
                                </div>
                                <small class="text-muted">Lít/T.Km</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-body text-center">
                <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                <h5>Chọn tàu để xem thông tin chi tiết</h5>
                <p class="text-muted">
                    Click vào tên tàu bên trái để xem hệ số nhiên liệu chi tiết
                </p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Thông tin bổ sung -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-lightbulb me-2"></i>
                    Giải Thích Hệ Số
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="fas fa-gas-pump me-2"></i>Hệ số không hàng (Kkh):</h6>
                        <ul>
                            <li>Đơn vị: Lít/Km</li>
                            <li>Lượng nhiên liệu tiêu thụ trên 1 km khi tàu chạy không hàng</li>
                            <li>Phụ thuộc vào loại tàu và khoảng cách</li>
                            <li>Thường cao hơn hệ số có hàng do tàu chạy không tải</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-weight-hanging me-2"></i>Hệ số có hàng (Kch):</h6>
                        <ul>
                            <li>Đơn vị: Lít/T.Km</li>
                            <li>Lượng nhiên liệu tiêu thụ trên 1 tấn-km khi tàu chở hàng</li>
                            <li>Phụ thuộc vào loại tàu và khoảng cách</li>
                            <li>Được nhân với khối lượng hàng hóa để tính nhiên liệu</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('search_tau');
    const list = document.getElementById('tauList');
    if (!input || !list) return;
    input.addEventListener('input', function() {
        const term = (input.value || '').toLowerCase();
        list.querySelectorAll('.list-group-item').forEach(function(item) {
            const text = (item.textContent || '').toLowerCase();
            item.style.display = text.includes(term) ? '' : 'none';
        });
    });
});
</script>

</div>

<?php include 'includes/footer.php'; ?>















