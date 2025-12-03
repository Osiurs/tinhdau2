<?php
/**
 * Trang hiển thị danh sách điểm và các tuyến đường kết nối
 */

require_once __DIR__ . '/auth/check_auth.php';
require_once 'includes/helpers.php';
require_once 'config/database.php';
require_once 'models/KhoangCach.php';

$khoangCach = new KhoangCach();
$allRoutes = $khoangCach->getAllData();
$allPoints = $khoangCach->getDanhSachDiem();

// Tìm kiếm điểm/tuyến
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

// Lọc tuyến theo từ khoá (nếu có)
$filteredRoutes = array_filter($allRoutes, function($r) use ($q) {
    if ($q === '') return true;
    return stripos($r['diem_dau'], $q) !== false || stripos($r['diem_cuoi'], $q) !== false;
});

// Tính số kết nối của từng điểm
$pointToDegree = [];
foreach ($allRoutes as $r) {
    $pointToDegree[$r['diem_dau']] = ($pointToDegree[$r['diem_dau']] ?? 0) + 1;
    $pointToDegree[$r['diem_cuoi']] = ($pointToDegree[$r['diem_cuoi']] ?? 0) + 1;
}

include 'includes/header.php';
?>

<!-- Page Header -->
<div class="row mb-4">
	<div class="col-12">
		<div class="card">
			<div class="card-body text-center">
				<h1 class="card-title">
					<i class="fas fa-map-marker-alt text-primary me-3"></i>
					Danh Sách Điểm & Tuyến Đường
				</h1>
				<p class="card-text">Tra cứu các điểm và tuyến đường hiện có trong hệ thống</p>
			</div>
		</div>
	</div>
</div>

<div class="row mb-3">
	<div class="col-12">
		<form class="row g-2" method="GET" action="">
			<div class="col-md-6">
				<input type="text" class="form-control" name="q" placeholder="Tìm điểm hoặc tuyến (vd: A, B)" value="<?php echo htmlspecialchars($q); ?>" autocomplete="off">
			</div>
			<div class="col-md-3 d-grid">
				<button class="btn btn-primary"><i class="fas fa-search me-2"></i>Tìm kiếm</button>
			</div>
			<div class="col-md-3 d-grid">
				<a class="btn btn-outline-secondary" href="danh_sach_diem.php"><i class="fas fa-undo me-2"></i>Xoá lọc</a>
			</div>
		</form>
	</div>
</div>

<div class="row">
	<!-- Danh sách điểm -->
	<div class="col-lg-4">
		<div class="card">
			<div class="card-header">
				<h5 class="mb-0"><i class="fas fa-map-pin me-2"></i>Điểm (<?php echo count($allPoints); ?>)</h5>
			</div>
			<div class="card-body" style="max-height: 520px; overflow:auto;">
				<ul class="list-group">
					<?php foreach ($allPoints as $p): ?>
					<li class="list-group-item d-flex justify-content-between align-items-center">
						<span><i class="fas fa-location-dot text-primary me-2"></i><?php echo htmlspecialchars($p); ?></span>
						<span class="badge bg-info rounded-pill"><?php echo (int)($pointToDegree[$p] ?? 0); ?></span>
					</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
	</div>

	<!-- Danh sách tuyến đường -->
	<div class="col-lg-8">
		<div class="card">
			<div class="card-header">
				<h5 class="mb-0"><i class="fas fa-route me-2"></i>Tuyến Đường (<?php echo count($filteredRoutes); ?>)</h5>
			</div>
			<div class="card-body">
				<div class="table-responsive">
					<table class="table table-striped table-hover">
						<thead>
							<tr>
								<th>#</th>
								<th>Điểm đầu</th>
								<th>Điểm cuối</th>
								<th class="text-end">Khoảng cách (km)</th>
							</tr>
						</thead>
						<tbody>
							<?php if (empty($filteredRoutes)): ?>
							<tr><td colspan="4" class="text-center text-muted">Không có tuyến nào</td></tr>
							<?php else: $i=1; foreach ($filteredRoutes as $r): ?>
							<tr>
								<td><?php echo $i++; ?></td>
								<td><?php echo htmlspecialchars($r['diem_dau']); ?></td>
								<td><?php echo htmlspecialchars($r['diem_cuoi']); ?></td>
								<td class="text-end"><span class="badge bg-info"><?php echo number_format($r['khoang_cach_km'], 1); ?> km</span></td>
							</tr>
							<?php endforeach; endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>

<?php include 'includes/footer.php'; ?>


