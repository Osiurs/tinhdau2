<?php
require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/HeSoTau.php';
require_once __DIR__ . '/../models/DauTon.php';
require_once __DIR__ . '/../models/CayXang.php';

$heSoTau = new HeSoTau();
$dauTon = new DauTon();
$cayXangModel = new CayXang();
$danhSachCayXang = $cayXangModel->getAll();
$danhSachTau = $heSoTau->getDanhSachTau();

$alert = '';

// Sanitize inputs
if (!empty($_GET)) { $_GET = sanitize_input($_GET); }
if (!empty($_POST)) { $_POST = sanitize_input($_POST); }

// Handle POST actions
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
$action = $_POST['action'] ?? '';
$tenTau = trim($_POST['ten_tau'] ?? '');
$ngay = trim($_POST['ngay'] ?? '');
$soLuong = $_POST['so_luong'] ?? '';
$lyDo = trim($_POST['ly_do'] ?? '');
$cayXang = trim($_POST['cay_xang'] ?? '');
    try {
        // Server-side fallback: nếu checkbox tinh chỉnh được bật, luôn ưu tiên ghi nhận là tinh_chinh
        if (isset($_POST['is_tinh_chinh']) && $_POST['is_tinh_chinh'] === '1') {
            $action = 'tinh_chinh';
        }
        if ($action === 'cap_them') {
            $dauTon->themCapThem($tenTau, $ngay, $soLuong, $lyDo, $cayXang);
            $alert = '<div class="alert alert-success">Đã lưu lệnh cấp thêm dầu.</div>';
        } elseif ($action === 'tinh_chinh') {
            $dauTon->themTinhChinh($tenTau, $ngay, $soLuong, $lyDo);
            $alert = '<div class="alert alert-success">Đã lưu lệnh tinh chỉnh dầu.</div>';
        } elseif ($action === 'transfer') {
            // Chuyển dầu giữa 2 tàu
            $tauNguon = trim($_POST['tau_nguon'] ?? '');
            $tauDich = trim($_POST['tau_dich'] ?? '');
            $ngayChuyen = trim($_POST['ngay_chuyen'] ?? '');
            $so = $_POST['so_lit'] ?? '';
            $reason = trim($_POST['ly_do_chuyen'] ?? 'Chuyển dầu');
            try {
                $dauTon->chuyenDau($tauNguon, $tauDich, $ngayChuyen, $so, $reason);
                $alert = '<div class="alert alert-success">Đã chuyển dầu thành công từ ' . htmlspecialchars($tauNguon) . ' sang ' . htmlspecialchars($tauDich) . '.</div>';
            } catch (Exception $transferEx) {
                // Re-throw để catch chung xử lý
                throw $transferEx;
            }
        }
    } catch (Exception $e) {
        log_error('quan_ly_dau_ton', ['error' => $e->getMessage()]);
        $alert = '<div class="alert alert-danger">Lỗi: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// UI filter: selected ship
$tauChon = $_GET['tau'] ?? ($_POST['ten_tau'] ?? '');
if ($tauChon && !$heSoTau->isTauExists($tauChon)) { $tauChon = ''; }

include __DIR__ . '/../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h2 class="mb-1"><i class="fas fa-oil-can text-primary me-2"></i>Quản lý dầu tồn</h2>
                    <div class="text-muted">Theo dõi việc lấy dầu từ cây xăng, tinh chỉnh và tiêu hao theo ngày dỡ hàng</div>
                </div>
                <form method="get" class="d-flex align-items-center gap-2">
                    <label class="form-label mb-0 me-2">Chọn tàu</label>
                    <select name="tau" class="form-select" style="min-width: 260px;">
                        <option value="">-- Tất cả tàu --</option>
                        <?php foreach ($danhSachTau as $t): ?>
                        <option value="<?php echo htmlspecialchars($t); ?>" <?php echo ($tauChon === $t ? 'selected' : ''); ?>><?php echo htmlspecialchars($t); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary" type="submit"><i class="fas fa-filter me-1"></i>Lọc</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($alert)) echo $alert; ?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-oil-can me-2"></i>Tạo lệnh lấy dầu từ cây xăng</span>
                <div class="form-check form-switch m-0">
                    <input class="form-check-input" type="checkbox" id="toggleTinhChinh" name="is_tinh_chinh" value="1" form="formLenhDau">
                    <label class="form-check-label" for="toggleTinhChinh"><strong>Chuyển sang tinh chỉnh</strong></label>
                </div>
            </div>
            <div class="card-body">
                <form id="formLenhDau" method="post" onsubmit="return validateDauTonForm(this)">
                    <input type="hidden" name="action" id="actionHidden" value="cap_them" />
                    <div class="mb-3">
                        <label class="form-label" for="lenhTauSelect">Tàu</label>
                        <select id="lenhTauSelect" name="ten_tau" class="form-select" required>
                            <option value="">-- Chọn tàu --</option>
                            <?php foreach ($danhSachTau as $t): ?>
                            <option value="<?php echo htmlspecialchars($t); ?>" <?php echo ($tauChon === $t ? 'selected' : ''); ?>><?php echo htmlspecialchars($t); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" id="labelNgay" for="lenhNgayInput">Ngày lấy dầu</label>
                        <input type="text" id="lenhNgayInput" name="ngay" class="form-control vn-date" placeholder="dd/mm/yyyy" required />
                    </div>
                    <div class="mb-3">
                        <label class="form-label" id="labelSoLuong" for="inputSoLuong">Số lượng lấy (Lít)</label>
                        <input type="number" step="0.01" min="0" name="so_luong" id="inputSoLuong" class="form-control" placeholder="vd: 500" required />
                        <div class="form-text" id="hintSoLuong">Nhập số không âm</div>
                    </div>
                    <div class="mb-3" id="groupCayXang">
                        <label class="form-label" for="lenhCayXangSelect">Cây xăng lấy dầu</label>
                        <select id="lenhCayXangSelect" name="cay_xang" class="form-select">
                            <option value="">-- Chọn cây xăng --</option>
                            <?php foreach ($danhSachCayXang as $cx): ?>
                            <option value="<?php echo htmlspecialchars($cx); ?>"><?php echo htmlspecialchars($cx); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Chỉ áp dụng cho lệnh lấy dầu từ cây xăng</div>
                        <div class="form-text">Quản lý danh mục tại: <a href="quan_ly_cay_xang.php">Quản lý cây xăng</a></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="lenhLyDoInput">Lý do</label>
                        <input type="text" id="lenhLyDoInput" name="ly_do" class="form-control" placeholder="vd: Tiếp nhiên liệu" />
                    </div>
                    <button id="btnSubmitLenh" class="btn btn-success" type="submit"><i class="fas fa-save me-1"></i><span>Lưu lệnh lấy dầu</span></button>
                </form>
            </div>
        </div>
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-exchange-alt me-2"></i>Chuyển dầu giữa tàu</span>
            </div>
            <div class="card-body">
                <form method="post" onsubmit="return validateTransferForm(this)">
                    <input type="hidden" name="action" value="transfer" />
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="transferTauNguon">Tàu nguồn</label>
                            <select id="transferTauNguon" name="tau_nguon" class="form-select" required>
                                <option value="">-- Chọn tàu --</option>
                                <?php foreach ($danhSachTau as $t): ?>
                                <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="transferTauDich">Tàu đích</label>
                            <select id="transferTauDich" name="tau_dich" class="form-select" required>
                                <option value="">-- Chọn tàu --</option>
                                <?php foreach ($danhSachTau as $t): ?>
                                <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="transferNgayInput">Ngày chuyển</label>
                            <input type="text" id="transferNgayInput" name="ngay_chuyen" class="form-control vn-date" placeholder="dd/mm/yyyy" required />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="transferSoLitInput">Số lít</label>
                            <input type="number" id="transferSoLitInput" name="so_lit" class="form-control" step="0.01" min="0.01" placeholder="vd: 30" required />
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="transferLyDoInput">Lý do</label>
                        <input type="text" id="transferLyDoInput" name="ly_do_chuyen" class="form-control" placeholder="vd: Cấp cứu, điều động..." />
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="fas fa-save me-1"></i>Lưu chuyển dầu</button>
                </form>
            </div>
        </div>
        <!-- Đã loại bỏ khối 'Thiết lập tồn đầu kỳ'. Người dùng hãy bật 'Chuyển sang tinh chỉnh' ở form trên để tạo lệnh tinh chỉnh tương ứng. -->
    </div>

    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-gauge me-2"></i>Tổng quan dầu tồn</span>
                <small class="text-white-50">Đến ngày <?php echo format_date_vn(date('Y-m-d')); ?></small>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Tàu</th>
                                <th class="text-end">Số dư (Lít)</th>
                                <th class="text-center">Chi tiết</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($danhSachTau as $t): 
                                $soDu = $dauTon->tinhSoDu($t);
                                $badge = $soDu < 0 ? 'bg-danger' : ($soDu < 200 ? 'bg-warning text-dark' : 'bg-success');
                            ?>
                            <tr <?php echo ($tauChon === $t ? 'class="table-active"' : ''); ?>>
                                <td><i class="fas fa-ship me-2 text-secondary"></i><?php echo htmlspecialchars($t); ?></td>
                                <td class="text-end"><span class="badge <?php echo $badge; ?>"><?php echo number_format($soDu, 0); ?></span></td>
                                <td class="text-center">
                                    <a class="btn btn-sm btn-outline-secondary" href="?tau=<?php echo urlencode($t); ?>#nhatky"><i class="fas fa-eye me-1"></i>Xem</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="nhatky" class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-book me-2"></i>Nhật ký chi tiết <?php echo $tauChon ? (': ' . htmlspecialchars($tauChon)) : ''; ?></span>
                <?php if ($tauChon): ?>
                <span class="badge bg-light text-dark">Số dư hiện tại: <strong><?php echo number_format($dauTon->tinhSoDu($tauChon), 0); ?></strong> Lít</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!$tauChon): ?>
                    <div class="text-center text-muted py-4"><i class="fas fa-info-circle me-2"></i>Hãy chọn một tàu để xem nhật ký chi tiết.</div>
                <?php else: 
                    $entries = $dauTon->getNhatKyHienThi($tauChon);
                ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Ngày</th>
                                    <th>Loại</th>
                                    <th class="text-end">Số lượng (Lít)</th>
                                    <th>Lý do/Mô tả</th>
                                    <th>Cây xăng lấy dầu</th>
                                    <th class="text-end">Số dư (Lít)</th>
                                    <th>Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($entries)): ?>
                                <tr><td colspan="6" class="text-center text-muted">Chưa có dữ liệu</td></tr>
                                <?php else: ?>
                                <?php foreach ($entries as $e): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($e['ngay_vn']); ?></td>
                                    <td>
                                        <?php if (($e['loai_hien_thi'] ?? $e['loai']) === 'chuyen'): ?>
                                            <?php $dir = ($e['transfer']['dir'] ?? '') === 'out' ? 'out' : 'in'; $other = $e['transfer']['other_ship'] ?? ''; ?>
                                            <?php if ($dir === 'out'): ?>
                                                <span class="badge bg-dark"><i class="fas fa-arrow-right-arrow-left me-1"></i>Chuyển dầu → <?php echo htmlspecialchars($other); ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-dark"><i class="fas fa-arrow-right-arrow-left me-1"></i>Nhận dầu ← <?php echo htmlspecialchars($other); ?></span>
                                            <?php endif; ?>
                                        <?php elseif ($e['loai'] === 'cap_them'): ?>
                                            <span class="badge bg-success"><i class="fas fa-arrow-down me-1"></i>Lấy dầu từ cây xăng</span>
                                        <?php elseif ($e['loai'] === 'tinh_chinh'): ?>
                                            <span class="badge bg-primary"><i class="fas fa-sliders-h me-1"></i>Tinh chỉnh</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark"><i class="fas fa-fire me-1"></i>Tiêu hao</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end <?php echo ($e['so_luong'] < 0 ? 'text-danger' : 'text-success'); ?>"><?php echo number_format($e['so_luong'], 0); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($e['ly_do'] ?: $e['mo_ta']); ?>
                                        <?php if (($e['loai'] ?? '') === 'tieu_hao' && !empty($e['meta'])): $m = $e['meta']; ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary ms-2" 
                                                data-bs-toggle="modal" data-bs-target="#modalChiTiet"
                                                data-ten-tau="<?php echo htmlspecialchars($m['ten_tau'] ?? ''); ?>"
                                                data-route="<?php echo htmlspecialchars($m['route'] ?? ''); ?>"
                                                data-km="<?php echo htmlspecialchars(number_format($m['khoang_cach_km'] ?? 0, 1)); ?>"
                                                data-km-co="<?php echo htmlspecialchars(number_format($m['cu_ly_co_hang_km'] ?? 0, 1)); ?>"
                                                data-km-khong="<?php echo htmlspecialchars(number_format($m['cu_ly_khong_hang_km'] ?? 0, 1)); ?>"
                                                data-weight="<?php echo htmlspecialchars(number_format($m['khoi_luong_tan'] ?? 0, 2)); ?>"
                                                data-ngay-di="<?php echo htmlspecialchars(format_date_vn($m['ngay_di'] ?? '')); ?>"
                                                data-ngay-den="<?php echo htmlspecialchars(format_date_vn($m['ngay_den'] ?? '')); ?>"
                                                data-ngay-do="<?php echo htmlspecialchars(format_date_vn($m['ngay_do_xong'] ?? '')); ?>"
                                                data-dau="<?php echo htmlspecialchars(number_format($m['dau_lit'] ?? 0, 2)); ?>"
                                                title="Chi tiết">
                                            <i class="fas fa-circle-info"></i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                    <td data-field="cay-xang-cell">
                                        <?php if ($e['loai'] === 'cap_them'): ?>
                                            <span class="cay-xang-display badge bg-info text-dark"><?php echo htmlspecialchars($e['cay_xang'] ?: 'Chưa xác định'); ?></span>
                                            <div class="cay-xang-edit" style="display: none;">
                                                <select class="form-select form-select-sm">
                                                    <option value="">-- Bỏ chọn --</option>
                                                    <?php foreach ($danhSachCayXang as $cx): ?>
                                                    <option value="<?php echo htmlspecialchars($cx); ?>" <?php echo ($e['cay_xang'] === $cx ? 'selected' : ''); ?>><?php echo htmlspecialchars($cx); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button class="btn btn-sm btn-success btn-save-inline" data-id="<?php echo htmlspecialchars($e['id'] ?? ''); ?>" title="Lưu">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn btn-sm btn-secondary btn-cancel-inline" title="Hủy">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end fw-semibold"><?php echo number_format($e['so_du'], 0); ?></td>
                                    <td>
                                        <?php if ($e['loai'] === 'cap_them'): ?>
                                            <div class="action-buttons-view">
                                                <button class="btn btn-sm btn-outline-primary btn-edit-inline" data-id="<?php echo htmlspecialchars($e['id'] ?? ''); ?>" title="Sửa cây xăng">
                                                    <i class="fas fa-pencil-alt"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger btn-delete" data-id="<?php echo htmlspecialchars($e['id'] ?? ''); ?>" title="Xóa">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        <?php elseif ($e['loai'] === 'tinh_chinh' && empty($e['transfer_pair_id'])): ?>
                                            <div class="action-buttons-view">
                                                <button class="btn btn-sm btn-outline-primary btn-edit-tinh-chinh"
                                                        data-id="<?php echo htmlspecialchars($e['id'] ?? ''); ?>"
                                                        data-ngay="<?php echo htmlspecialchars($e['ngay_vn']); ?>"
                                                        data-so-luong="<?php echo htmlspecialchars($e['so_luong']); ?>"
                                                        data-ly-do="<?php echo htmlspecialchars($e['ly_do']); ?>"
                                                        title="Sửa tinh chỉnh">
                                                    <i class="fas fa-pencil-alt"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger btn-delete" data-id="<?php echo htmlspecialchars($e['id'] ?? ''); ?>" title="Xóa">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        <?php elseif (($e['loai_hien_thi'] ?? $e['loai']) === 'chuyen' && !empty($e['transfer_pair_id'])): ?>
                                            <?php 
                                            // Extract transfer info for edit/delete buttons
                                            $dir = ($e['transfer']['dir'] ?? '') === 'out' ? 'out' : 'in';
                                            $otherShip = $e['transfer']['other_ship'] ?? '';
                                            $currentShip = $tauChon;
                                            $srcShip = $dir === 'out' ? $currentShip : $otherShip;
                                            $dstShip = $dir === 'out' ? $otherShip : $currentShip;
                                            $absLiters = abs($e['so_luong']);
                                            $ngayChuyen = format_date_vn($e['ngay']);
                                            $rawReason = $e['ly_do'] ?? '';
                                            $baseReason = $rawReason;
                                            if (strpos($baseReason, '→') !== false) {
                                                $parts = explode('→', $baseReason, 2);
                                                $baseReason = trim($parts[0]);
                                            } elseif (strpos($baseReason, '←') !== false) {
                                                $parts = explode('←', $baseReason, 2);
                                                $baseReason = trim($parts[0]);
                                            }
                                            ?>
                                            <div class="d-flex gap-1">
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        data-action="edit-transfer"
                                                        data-src="<?php echo htmlspecialchars($srcShip); ?>"
                                                        data-dst="<?php echo htmlspecialchars($dstShip); ?>"
                                                        data-date="<?php echo htmlspecialchars($ngayChuyen); ?>"
                                                        data-liters="<?php echo htmlspecialchars(number_format($absLiters, 2, '.', '')); ?>"
                                                        data-dir="<?php echo htmlspecialchars($dir); ?>"
                                                        data-reason="<?php echo htmlspecialchars($baseReason); ?>"
                                                        data-pair-id="<?php echo htmlspecialchars($e['transfer_pair_id']); ?>"
                                                        title="Sửa chuyển dầu">
                                                    <i class="fas fa-pencil-alt"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" 
                                                        data-action="delete-transfer"
                                                        data-src="<?php echo htmlspecialchars($srcShip); ?>"
                                                        data-dst="<?php echo htmlspecialchars($dstShip); ?>"
                                                        data-date="<?php echo htmlspecialchars($ngayChuyen); ?>"
                                                        data-liters="<?php echo htmlspecialchars(number_format($absLiters, 2, '.', '')); ?>"
                                                        data-dir="<?php echo htmlspecialchars($dir); ?>"
                                                        data-reason="<?php echo htmlspecialchars($baseReason); ?>"
                                                        data-pair-id="<?php echo htmlspecialchars($e['transfer_pair_id']); ?>"
                                                        title="Xóa chuyển dầu">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>



<!-- Modal chỉnh sửa chuyển dầu -->
<div class="modal fade" id="modalEditTransfer" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-pencil-alt me-2"></i>Sửa lệnh chuyển dầu</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="formEditTransfer">
        <div class="modal-body">
            <input type="hidden" name="transfer_pair_id" id="editTransferPairId" />
            <div class="mb-3">
                <label class="form-label">Tàu nguồn</label>
                <select class="form-select" name="new_source_ship" id="editTransferSource" required>
                    <option value="">-- Chọn tàu --</option>
                    <?php foreach ($danhSachTau as $t): ?>
                    <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Tàu đích</label>
                <select class="form-select" name="new_dest_ship" id="editTransferDest" required>
                    <option value="">-- Chọn tàu --</option>
                    <?php foreach ($danhSachTau as $t): ?>
                    <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Ngày chuyển</label>
                    <input type="text" class="form-control vn-date" name="new_date" id="editTransferDate" placeholder="dd/mm/yyyy" required />
                </div>
                <div class="col-md-6">
                    <label class="form-label">Số lít</label>
                    <input type="number" class="form-control" step="0.01" min="0.01" name="new_liters" id="editTransferLiters" placeholder="vd: 100" required />
                </div>
            </div>
            <div class="mt-3">
                <label class="form-label">Lý do</label>
                <input type="text" class="form-control" name="reason" id="editTransferReason" placeholder="vd: Chuyển dầu" />
                <div class="form-text">Nếu bỏ trống sẽ dùng mặc định &quot;Chuyển dầu&quot;.</div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Lưu thay đổi</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal chỉnh sửa tinh chỉnh -->
<div class="modal fade" id="modalEditTinhChinh" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-pencil-alt me-2"></i>Sửa lệnh tinh chỉnh</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="formEditTinhChinh">
        <div class="modal-body">
            <input type="hidden" name="id" id="editTinhChinhId" />
            <div class="mb-3">
                <label class="form-label">Ngày tinh chỉnh</label>
                <input type="text" class="form-control vn-date" name="ngay" id="editTinhChinhNgay" placeholder="dd/mm/yyyy" required />
            </div>
            <div class="mb-3">
                <label class="form-label">Số lượng (Lít)</label>
                <input type="number" class="form-control" step="0.01" name="so_luong" id="editTinhChinhSoLuong" placeholder="vd: 100 hoặc -100" required />
                <div class="form-text">Số dương để tăng, số âm để giảm</div>
            </div>
            <div class="mb-3">
                <label class="form-label">Lý do</label>
                <input type="text" class="form-control" name="ly_do" id="editTinhChinhLyDo" placeholder="vd: Điều chỉnh số dư" />
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
            <button type="button" class="btn btn-primary" id="btnSaveTinhChinh"><i class="fas fa-save me-1"></i>Lưu thay đổi</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal chi tiết tiêu hao -->
<div class="modal fade" id="modalChiTiet" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-circle-info me-2"></i>Chi tiết tiêu hao</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="p-2 border rounded">
                    <div class="small text-muted">Tàu</div>
                    <div class="fw-semibold" data-field="ten_tau"></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="p-2 border rounded">
                    <div class="small text-muted">Tuyến đường</div>
                    <div class="fw-semibold" data-field="route"></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-2 border rounded">
                    <div class="small text-muted">Tổng quãng đường</div>
                    <div class="fw-semibold" data-field="km"></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-2 border rounded">
                    <div class="small text-muted">Có hàng</div>
                    <div class="fw-semibold" data-field="km_co"></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-2 border rounded">
                    <div class="small text-muted">Không hàng</div>
                    <div class="fw-semibold" data-field="km_khong"></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-2 border rounded">
                    <div class="small text-muted">Khối lượng (tấn)</div>
                    <div class="fw-semibold" data-field="weight"></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-2 border rounded">
                    <div class="small text-muted">Ngày đi</div>
                    <div class="fw-semibold" data-field="ngay_di"></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-2 border rounded">
                    <div class="small text-muted">Ngày đến</div>
                    <div class="fw-semibold" data-field="ngay_den"></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-2 border rounded">
                    <div class="small text-muted">Ngày dỡ xong</div>
                    <div class="fw-semibold" data-field="ngay_do"></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-2 border rounded">
                    <div class="small text-muted">Dầu tiêu hao (Lít)</div>
                    <div class="fw-semibold text-danger" data-field="dau"></div>
                </div>
            </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
      </div>
    </div>
  </div>
</div>





<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Xử lý nút sửa tinh chỉnh
    document.body.addEventListener('click', function(e) {
        const btnEdit = e.target.closest('.btn-edit-tinh-chinh');
        if (btnEdit) {
            e.preventDefault();
            const id = btnEdit.getAttribute('data-id');
            const ngay = btnEdit.getAttribute('data-ngay');
            const soLuong = btnEdit.getAttribute('data-so-luong');
            const lyDo = btnEdit.getAttribute('data-ly-do') || '';

            // Điền dữ liệu vào modal
            document.getElementById('editTinhChinhId').value = id;
            document.getElementById('editTinhChinhNgay').value = ngay;
            document.getElementById('editTinhChinhSoLuong').value = soLuong;
            document.getElementById('editTinhChinhLyDo').value = lyDo;

            // Hiển thị modal
            const modal = new bootstrap.Modal(document.getElementById('modalEditTinhChinh'));
            modal.show();
        }
    });

    // Xử lý click nút "Lưu thay đổi" trong modal sửa tinh chỉnh
    const btnSaveTinhChinh = document.getElementById('btnSaveTinhChinh');
    if (btnSaveTinhChinh) {
        btnSaveTinhChinh.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Button save tinh chinh clicked');

            const formEditTinhChinh = document.getElementById('formEditTinhChinh');
            if (!formEditTinhChinh) {
                console.error('Form not found');
                return;
            }

            const formData = new FormData(formEditTinhChinh);
            const id = formData.get('id');
            const ngay = formData.get('ngay');
            const soLuong = formData.get('so_luong');

            console.log('Form data:', {
                id: id,
                ngay: ngay,
                so_luong: soLuong,
                ly_do: formData.get('ly_do')
            });

            if (!id || id.trim() === '') {
                alert('Lỗi: Không tìm thấy ID của lệnh cần sửa.');
                return false;
            }

            // Disable button để tránh click nhiều lần
            btnSaveTinhChinh.disabled = true;
            btnSaveTinhChinh.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Đang lưu...';

            fetch('../api/update_tinh_chinh.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Update response:', data);
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('modalEditTinhChinh'));
                    if (modal) modal.hide();
                    location.reload();
                } else {
                    alert('Lỗi: ' + (data.message || 'Không xác định'));
                    btnSaveTinhChinh.disabled = false;
                    btnSaveTinhChinh.innerHTML = '<i class="fas fa-save me-1"></i>Lưu thay đổi';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Lỗi khi cập nhật: ' + error.message);
                btnSaveTinhChinh.disabled = false;
                btnSaveTinhChinh.innerHTML = '<i class="fas fa-save me-1"></i>Lưu thay đổi';
            });

            return false;
        });
    } else {
        console.error('btnSaveTinhChinh not found!');
    }

    // Xử lý nút xóa (cho cả cap_them và tinh_chinh)
    document.body.addEventListener('click', function(e) {
        const btnDelete = e.target.closest('.btn-delete');
        if (btnDelete && !btnDelete.closest('[data-action="delete-transfer"]')) {
            e.preventDefault();
            const id = btnDelete.getAttribute('data-id');
            console.log('Delete button clicked, ID:', id, 'Length:', id ? id.length : 0);

            if (!id || id.trim() === '') {
                alert('Lỗi: Không tìm thấy ID của mục cần xóa.');
                return;
            }

            if (confirm('Bạn có chắc chắn muốn xóa mục này?')) {
                const formData = new FormData();
                formData.append('id', id);
                console.log('Sending delete request for ID:', id);

                fetch('../api/delete_dau_ton.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    console.log('Delete response:', data);
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Lỗi: ' + (data.message || 'Không xác định'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Lỗi khi xóa: ' + error.message);
                });
            }
        }
    });
});
</script>
