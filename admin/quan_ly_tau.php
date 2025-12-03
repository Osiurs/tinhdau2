<?php
/**
 * Trang quản lý tàu - CRUD operations
 * Cho phép thêm, sửa, xóa tàu và hệ số nhiên liệu
 */

require_once __DIR__ . '/../auth/check_auth.php';
require_once '../includes/helpers.php';
require_once '../config/database.php';
require_once '../models/HeSoTau.php';
require_once '../models/TauPhanLoai.php';

// Khởi tạo đối tượng hệ số tàu
$heSoTau = new HeSoTau();
$phanLoaiModel = new TauPhanLoai();

// Xử lý các action
$message = '';
$messageType = '';
// Giữ bộ lọc và vị trí scroll hiện tại (nếu có) khi reload
$currentFilter = isset($_GET['filter']) ? (string)$_GET['filter'] : '';
$currentScroll = isset($_GET['scroll']) ? intval($_GET['scroll']) : 0;

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
            case 'add_tau':
                $tenTau = trim($_POST['ten_tau']);
                $phanLoai = $_POST['phan_loai'] ?? 'cong_ty';
                $soDangKy = trim($_POST['so_dang_ky'] ?? '');
                
                if (empty($tenTau)) {
                    throw new Exception('Tên tàu không được để trống');
                }
                
                // Kiểm tra tàu đã tồn tại chưa
                if ($heSoTau->isTauExists($tenTau)) {
                    throw new Exception('Tàu này đã tồn tại trong hệ thống');
                }
                
                // Định nghĩa 6 đoạn khoảng cách cố định
                $segments = [
                    ['min' => 0, 'max' => 16],
                    ['min' => 16, 'max' => 30],
                    ['min' => 31, 'max' => 60],
                    ['min' => 61, 'max' => 100],
                    ['min' => 101, 'max' => 300],
                    ['min' => 301, 'max' => 999999]
                ];
                
                $newData = [];
                $hasAnyData = false;
                
                foreach ($segments as $segment) {
                    $kKoHangInput = $_POST["k_ko_hang_{$segment['min']}_{$segment['max']}"] ?? '';
                    $kCoHangInput = $_POST["k_co_hang_{$segment['min']}_{$segment['max']}"] ?? '';
                    
                    // Chỉ tạo đoạn nếu có ít nhất một hệ số được nhập
                    if ($kKoHangInput !== '' || $kCoHangInput !== '') {
                        $kKoHang = $kKoHangInput !== '' ? floatval($kKoHangInput) : 0;
                        $kCoHang = $kCoHangInput !== '' ? floatval($kCoHangInput) : 0;
                        
                        if ($kKoHang < 0 || $kCoHang < 0) {
                            throw new Exception("Hệ số nhiên liệu cho đoạn {$segment['min']}-{$segment['max']} km không được âm");
                        }
                        
                        $newData[] = "$tenTau,{$segment['min']},{$segment['max']},$kKoHang,$kCoHang";
                        $hasAnyData = true;
                    }
                }
                
                if (!$hasAnyData) {
                    throw new Exception('Vui lòng nhập ít nhất một hệ số nhiên liệu cho một đoạn khoảng cách');
                }
                
                // Thêm vào file CSV
                $newDataString = implode("\n", $newData) . "\n";
                file_put_contents(HE_SO_TAU_FILE, $newDataString, FILE_APPEND | LOCK_EX);
                
                // Lưu phân loại theo tên tàu + số đăng ký (có thể trống)
                $phanLoaiModel->setPhanLoai($tenTau, in_array($phanLoai, ['cong_ty','thue_ngoai'], true) ? $phanLoai : 'cong_ty', $soDangKy);
                
                // Reload data
                $heSoTau = new HeSoTau();
                
                // Redirect để tránh F5 resubmit, giữ lại filter & scroll
                $retFilter = isset($_POST['return_filter']) ? ('&filter=' . urlencode($_POST['return_filter'])) : '';
                $retScroll = isset($_POST['return_scroll']) ? ('&scroll=' . intval($_POST['return_scroll'])) : '';
                header('Location: quan_ly_tau.php?success=1&message=' . urlencode("Đã thêm tàu '$tenTau' với " . count($newData) . " đoạn khoảng cách thành công!") . $retFilter . $retScroll);
                exit;
                
            case 'delete_tau':
                $tenTau = $_POST['ten_tau'];
                
                // Xóa toàn bộ tàu (tất cả các đoạn)
                if ($heSoTau->deleteTau($tenTau)) {
                    // Xóa phân loại tàu
                    $phanLoaiModel->setPhanLoai($tenTau, ''); // Xóa bằng cách set empty
                }
                
                // Redirect để tránh F5 resubmit, giữ lại filter & scroll
                $retFilter = isset($_POST['return_filter']) ? ('&filter=' . urlencode($_POST['return_filter'])) : '';
                $retScroll = isset($_POST['return_scroll']) ? ('&scroll=' . intval($_POST['return_scroll'])) : '';
                header('Location: quan_ly_tau.php?success=1&message=' . urlencode("Đã xóa tàu '$tenTau' và tất cả các đoạn khoảng cách thành công!") . $retFilter . $retScroll);
                exit;
                
            case 'copy_tau':
                $tenTauGoc = $_POST['ten_tau_goc'];
                $tenTauMoi = trim($_POST['ten_tau_moi']);
                $phanLoaiMoi = $_POST['phan_loai_moi'] ?? 'cong_ty';
                $soDangKyMoi = trim($_POST['so_dang_ky_moi'] ?? '');
                
                if (empty($tenTauMoi)) {
                    throw new Exception('Tên tàu mới không được để trống');
                }
                
                if ($tenTauGoc === $tenTauMoi) {
                    throw new Exception('Tên tàu mới phải khác tên tàu gốc');
                }
                
                // Sao chép tàu
                if (!$heSoTau->copyTau($tenTauGoc, $tenTauMoi)) {
                    throw new Exception('Không thể sao chép tàu. Có thể tàu gốc không tồn tại hoặc tàu mới đã tồn tại');
                }
                
                // Lưu phân loại cho tàu mới (kèm số đăng ký nếu có)
                $phanLoaiModel->setPhanLoai($tenTauMoi, in_array($phanLoaiMoi, ['cong_ty','thue_ngoai'], true) ? $phanLoaiMoi : 'cong_ty', $soDangKyMoi);
                
                // Redirect để tránh F5 resubmit, giữ lại filter & scroll
                $retFilter = isset($_POST['return_filter']) ? ('&filter=' . urlencode($_POST['return_filter'])) : '';
                $retScroll = isset($_POST['return_scroll']) ? ('&scroll=' . intval($_POST['return_scroll'])) : '';
                header('Location: quan_ly_tau.php?success=1&message=' . urlencode("Đã sao chép tàu '$tenTauGoc' thành '$tenTauMoi' thành công!") . $retFilter . $retScroll);
                exit;
                
            case 'update_tau':
                $tenTau = $_POST['ten_tau'];
                $kmMin = floatval($_POST['km_min']);
                $kmMax = floatval($_POST['km_max']);
                $kKoHang = floatval($_POST['k_ko_hang']);
                $kCoHang = floatval($_POST['k_co_hang']);
                $phanLoai = $_POST['phan_loai'] ?? '';
                
                // Đọc file hiện tại
                $lines = file(HE_SO_TAU_FILE, FILE_IGNORE_NEW_LINES);
                $newLines = [];
                
                foreach ($lines as $line) {
                    $data = explode(',', $line);
                    if (count($data) >= 5) {
                        if ($data[0] === $tenTau && 
                            floatval($data[1]) === $kmMin && 
                            floatval($data[2]) === $kmMax) {
                            // Cập nhật dòng này
                            $newLines[] = "$tenTau,$kmMin,$kmMax,$kKoHang,$kCoHang";
                        } else {
                            $newLines[] = $line;
                        }
                    }
                }
                
                // Ghi lại file
                file_put_contents(HE_SO_TAU_FILE, implode("\n", $newLines) . "\n");
                // Cập nhật phân loại nếu có
                if ($phanLoai !== '') {
                    $phanLoaiModel->setPhanLoai($tenTau, in_array($phanLoai, ['cong_ty','thue_ngoai'], true) ? $phanLoai : 'cong_ty');
                }
                
                // Redirect để tránh F5 resubmit, giữ lại filter & scroll
                $retFilter = isset($_POST['return_filter']) ? ('&filter=' . urlencode($_POST['return_filter'])) : '';
                $retScroll = isset($_POST['return_scroll']) ? ('&scroll=' . intval($_POST['return_scroll'])) : '';
                header('Location: quan_ly_tau.php?success=1&message=' . urlencode("Đã cập nhật tàu '$tenTau' thành công!") . $retFilter . $retScroll);
                exit;
                
            case 'update_ship_type':
                $tenTau = $_POST['ten_tau'];
                $phanLoaiMoi = $_POST['phan_loai_moi'] ?? 'cong_ty';
                $soDangKyMoi = trim($_POST['so_dang_ky_moi'] ?? '');
                
                if (empty($tenTau)) {
                    throw new Exception('Tên tàu không được để trống');
                }
                
                // Cập nhật phân loại tàu + số đăng ký (nếu cung cấp)
                $success = $phanLoaiModel->setPhanLoai($tenTau, in_array($phanLoaiMoi, ['cong_ty','thue_ngoai'], true) ? $phanLoaiMoi : 'cong_ty', $soDangKyMoi);
                
                if ($success) {
                    // Redirect để tránh F5 resubmit, giữ lại filter & scroll
                    $retFilter = isset($_POST['return_filter']) ? ('&filter=' . urlencode($_POST['return_filter'])) : '';
                    $retScroll = isset($_POST['return_scroll']) ? ('&scroll=' . intval($_POST['return_scroll'])) : '';
                    header('Location: quan_ly_tau.php?success=1&message=' . urlencode("Đã cập nhật loại tàu '$tenTau' thành công!") . $retFilter . $retScroll);
                    exit;
                } else {
                    throw new Exception('Không thể cập nhật loại tàu');
                }
        }
    } catch (Exception $e) {
        // Redirect với thông báo lỗi để tránh F5 resubmit (giữ lại filter & scroll)
        $retFilter = isset($_POST['return_filter']) ? ('&filter=' . urlencode($_POST['return_filter'])) : '';
        $retScroll = isset($_POST['return_scroll']) ? ('&scroll=' . intval($_POST['return_scroll'])) : '';
        header('Location: quan_ly_tau.php?error=1&message=' . urlencode('Lỗi: ' . $e->getMessage()) . $retFilter . $retScroll);
        exit;
    }
}

// Lấy danh sách tàu
$danhSachTau = $heSoTau->getDanhSachTau();
$tauGrouped = $heSoTau->getTauGrouped();

// Include header
include '../includes/header.php';
?>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center">
                <h1 class="card-title">
                    <i class="fas fa-cogs text-primary me-3"></i>
                    Quản Lý Tàu
                </h1>
                <p class="card-text">
                    Thêm, sửa, xóa thông tin tàu và hệ số nhiên liệu
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
    <!-- Form thêm tàu -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-plus me-2"></i>
                    Thêm Tàu Mới
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" onsubmit="return validateTauForm()">
                    <input type="hidden" name="action" value="add_tau">
                    
                    <div class="mb-3">
                        <label for="ten_tau" class="form-label">Tên tàu <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="ten_tau" name="ten_tau" required>
                    </div>

                    <div class="mb-3">
                        <label for="phan_loai" class="form-label">Phân loại <span class="text-danger">*</span></label>
                        <select class="form-select" id="phan_loai" name="phan_loai" required>
                            <option value="cong_ty">Sà lan công ty</option>
                            <option value="thue_ngoai">Thuê ngoài</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="so_dang_ky" class="form-label">Số đăng ký (tùy chọn)</label>
                        <input type="text" class="form-control" id="so_dang_ky" name="so_dang_ky" placeholder="VD: SG-6239">
                        <div class="form-text">Có thể bỏ trống và cập nhật sau.</div>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-route me-2"></i>
                            Hệ số nhiên liệu cho các đoạn khoảng cách
                        </h6>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Lưu ý:</strong> Bạn có thể để trống các đoạn chưa có hệ số cụ thể. Chỉ cần nhập ít nhất một đoạn để tạo tàu.
                        </div>
                        
                        <?php
                        $segments = [
                            ['min' => 0, 'max' => 16],
                            ['min' => 16, 'max' => 30],
                            ['min' => 31, 'max' => 60],
                            ['min' => 61, 'max' => 100],
                            ['min' => 101, 'max' => 300],
                            ['min' => 301, 'max' => 999999]
                        ];
                        
                        foreach ($segments as $index => $segment): ?>
                        <div class="card mb-3">
                            <div class="card-header py-2">
                                <h6 class="mb-0">
                                    <i class="fas fa-road me-2"></i>
                                    Đoạn <?php echo $index + 1; ?>: <?php echo $segment['min']; ?> - <?php echo $segment['max']; ?> km
                                </h6>
                            </div>
                            <div class="card-body py-2">
                                <div class="row">
                                    <div class="col-6">
                                        <label for="k_ko_hang_<?php echo $segment['min']; ?>_<?php echo $segment['max']; ?>" class="form-label">Hệ số không hàng</label>
                                        <input type="number" class="form-control" id="k_ko_hang_<?php echo $segment['min']; ?>_<?php echo $segment['max']; ?>" 
                                               name="k_ko_hang_<?php echo $segment['min']; ?>_<?php echo $segment['max']; ?>" 
                                               min="0" step="0.000001" placeholder="Để trống nếu chưa có">
                                    </div>
                                    <div class="col-6">
                                        <label for="k_co_hang_<?php echo $segment['min']; ?>_<?php echo $segment['max']; ?>" class="form-label">Hệ số có hàng</label>
                                        <input type="number" class="form-control" id="k_co_hang_<?php echo $segment['min']; ?>_<?php echo $segment['max']; ?>" 
                                               name="k_co_hang_<?php echo $segment['min']; ?>_<?php echo $segment['max']; ?>" 
                                               min="0" step="0.0000001" placeholder="Để trống nếu chưa có">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-plus me-2"></i>
                            Thêm Tàu (6 đoạn)
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Danh sách tàu -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Danh Sách Tàu
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" id="search_tau_admin" placeholder="Tìm kiếm tàu..." value="<?php echo htmlspecialchars($currentFilter); ?>">
                    </div>
                    <small class="text-muted">Gõ tên tàu để lọc bảng.</small>
                </div>
                <div class="accordion" id="tauAccordion">
                    <?php foreach ($tauGrouped as $tenTau => $segments): 
                        $pl = $phanLoaiModel->getPhanLoai($tenTau) ?? 'cong_ty';
                        $segmentCount = count($segments);
                    ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading<?php echo md5($tenTau); ?>">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                                    data-bs-target="#collapse<?php echo md5($tenTau); ?>" aria-expanded="false" 
                                    aria-controls="collapse<?php echo md5($tenTau); ?>">
                                <div class="d-flex justify-content-between align-items-center w-100 me-3">
                                    <div>
                                        <strong><?php echo htmlspecialchars(formatTau($tenTau)); ?></strong>
                                        <span class="badge <?php echo ($pl === 'cong_ty') ? 'bg-primary' : 'bg-secondary'; ?> ms-2">
                                            <?php echo $pl === 'cong_ty' ? 'Công ty' : 'Thuê ngoài'; ?>
                                        </span>
                                    </div>
                                    <div class="text-muted">
                                        <small><?php echo $segmentCount; ?> đoạn khoảng cách</small>
                                    </div>
                                </div>
                            </button>
                        </h2>
                        <div id="collapse<?php echo md5($tenTau); ?>" class="accordion-collapse collapse" 
                             aria-labelledby="heading<?php echo md5($tenTau); ?>" data-bs-parent="#tauAccordion">
                            <div class="accordion-body">
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-sm btn-success" onclick="copyTau('<?php echo htmlspecialchars($tenTau); ?>', '<?php echo $pl; ?>')" title="Sao chép tàu">
                                                <i class="fas fa-copy me-1"></i>Sao chép
                                            </button>
                                            <button class="btn btn-sm btn-warning" onclick="editShipType('<?php echo htmlspecialchars($tenTau); ?>', '<?php echo $pl; ?>')" title="Sửa loại tàu">
                                                <i class="fas fa-ship me-1"></i>Sửa loại
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteTau('<?php echo htmlspecialchars($tenTau); ?>')" title="Xóa toàn bộ tàu">
                                                <i class="fas fa-trash me-1"></i>Xóa tàu
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                            <tr>
                                                <th>Khoảng cách (km)</th>
                                                <th>Hệ số không hàng</th>
                                                <th>Hệ số có hàng</th>
                                                <th>Thao tác</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($segments as $segment): ?>
                                            <tr>
                                                <td><?php echo number_format($segment['km_min']); ?> - <?php echo number_format($segment['km_max']); ?></td>
                                                <td><?php echo number_format($segment['k_ko_hang'], 6); ?></td>
                                                <td><?php echo number_format($segment['k_co_hang'], 7); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary" onclick="editTau('<?php echo htmlspecialchars($segment['ten_tau']); ?>', <?php echo $segment['km_min']; ?>, <?php echo $segment['km_max']; ?>, <?php echo $segment['k_ko_hang']; ?>, <?php echo $segment['k_co_hang']; ?>, '<?php echo $pl; ?>')" title="Chỉnh sửa đoạn này">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Edit Tau -->
<div class="modal fade" id="editTauModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>
                    Sửa Thông Tin Tàu
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_tau">
                    <input type="hidden" name="return_filter" id="edit_return_filter">
                    <input type="hidden" name="return_scroll" id="edit_return_scroll">
                    
                    <div class="mb-3">
                        <label for="edit_ten_tau" class="form-label">Tên tàu</label>
                        <input type="text" class="form-control" id="edit_ten_tau" name="ten_tau" readonly>
                    </div>
                    
                    <div class="row">
                        <div class="col-6">
                            <div class="mb-3">
                                <label for="edit_km_min" class="form-label">Km min</label>
                                <input type="number" class="form-control" id="edit_km_min" name="km_min" min="0" step="0.1" required>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mb-3">
                                <label for="edit_km_max" class="form-label">Km max</label>
                                <input type="number" class="form-control" id="edit_km_max" name="km_max" min="0" step="0.1" required>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_phan_loai" class="form-label">Phân loại</label>
                        <select class="form-select" id="edit_phan_loai" name="phan_loai">
                            <option value="cong_ty">Sà lan công ty</option>
                            <option value="thue_ngoai">Thuê ngoài</option>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-6">
                            <div class="mb-3">
                                <label for="edit_k_ko_hang" class="form-label">Hệ số không hàng</label>
                                <input type="number" class="form-control" id="edit_k_ko_hang" name="k_ko_hang" min="0" step="0.000001" required>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mb-3">
                                <label for="edit_k_co_hang" class="form-label">Hệ số có hàng</label>
                                <input type="number" class="form-control" id="edit_k_co_hang" name="k_co_hang" min="0" step="0.0000001" required>
                            </div>
                        </div>
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

<!-- Modal Copy Ship -->
<div class="modal fade" id="copyTauModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-copy text-success me-2"></i>
                    Sao Chép Tàu
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="copy_tau">
                    <input type="hidden" name="ten_tau_goc" id="copyTauGoc">
                    <input type="hidden" name="return_filter" id="copy_return_filter">
                    <input type="hidden" name="return_scroll" id="copy_return_scroll">
                    
                    <div class="mb-3">
                        <label class="form-label">Sao chép từ tàu:</label>
                        <div class="form-control-plaintext fw-bold" id="copyTauGocDisplay"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="copy_ten_tau_moi" class="form-label">Tên tàu mới <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="copy_ten_tau_moi" name="ten_tau_moi" required>
                        <div class="form-text">Tên tàu mới sẽ được tạo với tất cả 6 đoạn khoảng cách giống tàu gốc</div>
                    </div>

                    <div class="mb-3">
                        <label for="copy_so_dang_ky_moi" class="form-label">Số đăng ký (tùy chọn)</label>
                        <input type="text" class="form-control" id="copy_so_dang_ky_moi" name="so_dang_ky_moi" placeholder="VD: SG-6239">
                        <div class="form-text">Có thể để trống và cập nhật sau.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="copy_phan_loai_moi" class="form-label">Phân loại</label>
                        <select class="form-select" id="copy_phan_loai_moi" name="phan_loai_moi">
                            <option value="cong_ty">Sà lan công ty</option>
                            <option value="thue_ngoai">Thuê ngoài</option>
                        </select>
                    </div>
                    
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle me-2"></i>Các đoạn khoảng cách sẽ được sao chép:</h6>
                        <ul class="mb-0">
                            <li>0 - 16 km</li>
                            <li>16 - 30 km</li>
                            <li>31 - 60 km</li>
                            <li>61 - 100 km</li>
                            <li>101 - 300 km</li>
                            <li>301 - 999,999 km</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-copy me-1"></i>
                        Sao chép tàu
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Delete Confirmation -->
<div class="modal fade" id="deleteTauModal" tabindex="-1">
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
                <p>Bạn có chắc chắn muốn xóa tàu <strong id="deleteTauName"></strong>?</p>
                <p class="text-danger fw-bold">Tất cả các đoạn khoảng cách của tàu này sẽ bị xóa!</p>
                <p class="text-muted">Hành động này không thể hoàn tác!</p>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete_tau">
                <input type="hidden" name="ten_tau" id="deleteTauNameInput">
                <input type="hidden" name="return_filter" id="delete_return_filter">
                <input type="hidden" name="return_scroll" id="delete_return_scroll">
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-1"></i>
                        Xóa tàu
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal chỉnh sửa loại tàu -->
<div class="modal fade" id="editShipTypeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-ship me-2"></i>
                    Chỉnh sửa loại tàu
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_ship_type">
                    <input type="hidden" name="ten_tau" id="editShipTypeNameInput">
                    <input type="hidden" name="return_filter" id="editShipType_return_filter">
                    <input type="hidden" name="return_scroll" id="editShipType_return_scroll">
                    
                    <div class="mb-3">
                        <label class="form-label">Tên tàu</label>
                        <input type="text" class="form-control" id="editShipTypeNameDisplay" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editShipTypePhanLoai" class="form-label">Loại tàu</label>
                        <select class="form-select" id="editShipTypePhanLoai" name="phan_loai_moi" required>
                            <option value="cong_ty">Sà lan công ty</option>
                            <option value="thue_ngoai">Thuê ngoài</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="editShipTypeSoDangKy" class="form-label">Số đăng ký (tùy chọn)</label>
                        <input type="text" class="form-control" id="editShipTypeSoDangKy" name="so_dang_ky_moi" placeholder="VD: SG-6239">
                        <div class="form-text">Để trống nếu không muốn thay đổi.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-ship me-1"></i>
                        Lưu thay đổi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Hàm validate form thêm tàu
function validateTauForm() {
    const tenTau = document.getElementById('ten_tau').value.trim();
    
    // Gắn filter & scroll hiện tại vào hidden inputs để redirect giữ lại
    const createForm = document.querySelector('form[action=""][method="POST"] input[name="action"][value="add_tau"]')?.form;
    if (createForm) {
        let hf = createForm.querySelector('input[name="return_filter"]');
        if (!hf) { hf = document.createElement('input'); hf.type='hidden'; hf.name='return_filter'; createForm.appendChild(hf); }
        hf.value = (document.getElementById('search_tau_admin')?.value || '');
        let hs = createForm.querySelector('input[name="return_scroll"]');
        if (!hs) { hs = document.createElement('input'); hs.type='hidden'; hs.name='return_scroll'; createForm.appendChild(hs); }
        hs.value = String(window.scrollY || 0);
    }
    
    if (!tenTau) {
        alert('Vui lòng nhập tên tàu');
        return false;
    }
    
    // Validate các đoạn khoảng cách (chỉ kiểm tra những đoạn có dữ liệu)
    const segments = [
        {min: 0, max: 16},
        {min: 16, max: 30},
        {min: 31, max: 60},
        {min: 61, max: 100},
        {min: 101, max: 300},
        {min: 301, max: 999999}
    ];
    
    let hasAnyData = false;
    
    for (let segment of segments) {
        const kKoHangInput = document.getElementById(`k_ko_hang_${segment.min}_${segment.max}`).value;
        const kCoHangInput = document.getElementById(`k_co_hang_${segment.min}_${segment.max}`).value;
        
        // Chỉ validate nếu có ít nhất một hệ số được nhập
        if (kKoHangInput !== '' || kCoHangInput !== '') {
            const kKoHang = kKoHangInput !== '' ? parseFloat(kKoHangInput) : 0;
            const kCoHang = kCoHangInput !== '' ? parseFloat(kCoHangInput) : 0;
            
            if (kKoHang < 0 || kCoHang < 0) {
                alert(`Hệ số nhiên liệu cho đoạn ${segment.min}-${segment.max} km không được âm`);
                return false;
            }
            
            hasAnyData = true;
        }
    }
    
    if (!hasAnyData) {
        alert('Vui lòng nhập ít nhất một hệ số nhiên liệu cho một đoạn khoảng cách');
        return false;
    }
    
    return true;
}

// Hàm sửa tàu
function editTau(tenTau, kmMin, kmMax, kKoHang, kCoHang, phanLoai) {
    document.getElementById('edit_ten_tau').value = tenTau;
    document.getElementById('edit_km_min').value = kmMin;
    document.getElementById('edit_km_max').value = kmMax;
    document.getElementById('edit_k_ko_hang').value = kKoHang;
    document.getElementById('edit_k_co_hang').value = kCoHang;
    const sel = document.getElementById('edit_phan_loai');
    if (sel) sel.value = phanLoai === 'thue_ngoai' ? 'thue_ngoai' : 'cong_ty';
    // Lưu bộ lọc và scroll hiện tại
    const filterVal = (document.getElementById('search_tau_admin')?.value || '');
    const editFilter = document.getElementById('edit_return_filter');
    const editScroll = document.getElementById('edit_return_scroll');
    if (editFilter) editFilter.value = filterVal;
    if (editScroll) editScroll.value = String(window.scrollY || 0);
    
    const modal = new bootstrap.Modal(document.getElementById('editTauModal'));
    modal.show();
}

// Hàm sao chép tàu
function copyTau(tenTau, phanLoai) {
    document.getElementById('copyTauGoc').value = tenTau;
    document.getElementById('copyTauGocDisplay').textContent = tenTau;
    document.getElementById('copy_ten_tau_moi').value = '';
    document.getElementById('copy_phan_loai_moi').value = phanLoai;
    
    // Lưu bộ lọc và scroll hiện tại
    const filterVal = (document.getElementById('search_tau_admin')?.value || '');
    const copyFilter = document.getElementById('copy_return_filter');
    const copyScroll = document.getElementById('copy_return_scroll');
    if (copyFilter) copyFilter.value = filterVal;
    if (copyScroll) copyScroll.value = String(window.scrollY || 0);
    
    const modal = new bootstrap.Modal(document.getElementById('copyTauModal'));
    modal.show();
}

// Hàm xóa tàu
function deleteTau(tenTau) {
    document.getElementById('deleteTauName').textContent = tenTau;
    document.getElementById('deleteTauNameInput').value = tenTau;
    
    // Lưu bộ lọc và scroll hiện tại
    const filterVal = (document.getElementById('search_tau_admin')?.value || '');
    const delFilter = document.getElementById('delete_return_filter');
    const delScroll = document.getElementById('delete_return_scroll');
    if (delFilter) delFilter.value = filterVal;
    if (delScroll) delScroll.value = String(window.scrollY || 0);
    
    const modal = new bootstrap.Modal(document.getElementById('deleteTauModal'));
    modal.show();
}

// Hàm chỉnh sửa loại tàu
function editShipType(tenTau, phanLoai) {
    document.getElementById('editShipTypeNameInput').value = tenTau;
    document.getElementById('editShipTypeNameDisplay').value = tenTau;
    document.getElementById('editShipTypePhanLoai').value = phanLoai;
    
    // Lưu bộ lọc và scroll hiện tại
    const filterVal = (document.getElementById('search_tau_admin')?.value || '');
    const editFilter = document.getElementById('editShipType_return_filter');
    const editScroll = document.getElementById('editShipType_return_scroll');
    if (editFilter) editFilter.value = filterVal;
    if (editScroll) editScroll.value = String(window.scrollY || 0);
    
    const modal = new bootstrap.Modal(document.getElementById('editShipTypeModal'));
    modal.show();
}
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('search_tau_admin');
    const accordion = document.getElementById('tauAccordion');
    if (!input || !accordion) return;
    
    input.addEventListener('input', function() {
        const term = (input.value || '').toLowerCase();
        accordion.querySelectorAll('.accordion-item').forEach(function(item) {
            const button = item.querySelector('.accordion-button');
            const text = button ? button.textContent : '';
            item.style.display = text.toLowerCase().includes(term) ? '' : 'none';
        });
    });
    
    // Áp dụng filter có trong URL khi load lại trang
    if (input.value) {
        input.dispatchEvent(new Event('input'));
    }
    
    // Khôi phục vị trí cuộn (nếu có)
    const scrollPos = <?php echo (int)$currentScroll; ?>;
    if (scrollPos > 0) {
        window.scrollTo(0, scrollPos);
    }
    
    // Khi người dùng gõ tìm kiếm, cập nhật query string để có thể share/refresh
    let typingTimer;
    input.addEventListener('input', function() {
        clearTimeout(typingTimer);
        typingTimer = setTimeout(function() {
            const params = new URLSearchParams(window.location.search);
            const term = input.value || '';
            if (term) params.set('filter', term); else params.delete('filter');
            params.set('scroll', String(window.scrollY || 0));
            const newUrl = window.location.pathname + '?' + params.toString();
            window.history.replaceState({}, '', newUrl);
        }, 200);
    });
});
</script>

<?php include '../includes/footer.php'; ?>



