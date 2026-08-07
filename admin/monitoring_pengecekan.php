<?php
session_start();
include '../config/database.php';

$page_title = 'Monitoring Pengecekan';
$active_menu = 'monitoring_pengecekan';

// Get available periods
$periods_res = $conn->query("SELECT * FROM periode_pengecekan ORDER BY id DESC");
$periods = [];
while($p = $periods_res->fetch_assoc()) {
    $periods[] = $p;
}

// Select active or latest period by default
$selected_periode_id = isset($_GET['periode_id']) ? intval($_GET['periode_id']) : 0;
if ($selected_periode_id === 0 && count($periods) > 0) {
    // Cari yang aktif
    foreach ($periods as $p) {
        if ($p['status'] === 'aktif') {
            $selected_periode_id = $p['id'];
            break;
        }
    }
    // Jika tidak ada yang aktif, pilih yang terbaru
    if ($selected_periode_id === 0) {
        $selected_periode_id = $periods[0]['id'];
    }
}

// Filters for table
$unit_id = isset($_GET['unit_id']) ? intval($_GET['unit_id']) : 0;
$ruang_id = isset($_GET['ruang_id']) ? intval($_GET['ruang_id']) : 0;
$filter_status = isset($_GET['status_cek']) ? $_GET['status_cek'] : 'semua'; // semua, sudah, belum

// Fetch units for dropdown
$units = $conn->query("SELECT * FROM unit ORDER BY nama_unit ASC");
$rooms = [];
if ($unit_id > 0) {
    $rooms_res = $conn->query("SELECT * FROM ruang WHERE id_unit = $unit_id ORDER BY nama_ruang ASC");
    while($r = $rooms_res->fetch_assoc()) $rooms[] = $r;
}

// Main Stats if period selected
$total_barang_aktif = 0;
$sudah_dicek = 0;
$belum_dicek = 0;
$stat_baik = 0;
$stat_rusak = 0;
$stat_hilang = 0;
$pct = 0;

if ($selected_periode_id > 0) {
    // Total barang aktif saat ini
    $total_barang_aktif = $conn->query("SELECT COUNT(*) as total FROM barang WHERE status_aktif = 'aktif'")->fetch_assoc()['total'];
    
    // Sudah dicek (jumlah distinct barang yang ada di pengecekan_barang untuk periode ini)
    $stmt_sdh = $conn->prepare("SELECT COUNT(DISTINCT id_barang) as total FROM pengecekan_barang WHERE id_periode = ?");
    $stmt_sdh->bind_param("i", $selected_periode_id);
    $stmt_sdh->execute();
    $sudah_dicek = $stmt_sdh->get_result()->fetch_assoc()['total'];
    $stmt_sdh->close();
    
    $belum_dicek = max(0, $total_barang_aktif - $sudah_dicek);
    if ($total_barang_aktif > 0) {
        $pct = round(($sudah_dicek / $total_barang_aktif) * 100);
    }
    
    // Breakdown kondisi temuan
    $stmt_b = $conn->prepare("SELECT COUNT(*) as total FROM pengecekan_barang WHERE id_periode = ? AND kondisi_temuan = 'Baik'");
    $stmt_b->bind_param("i", $selected_periode_id);
    $stmt_b->execute();
    $stat_baik = $stmt_b->get_result()->fetch_assoc()['total'];
    $stmt_b->close();

    $stmt_r = $conn->prepare("SELECT COUNT(*) as total FROM pengecekan_barang WHERE id_periode = ? AND kondisi_temuan = 'Rusak'");
    $stmt_r->bind_param("i", $selected_periode_id);
    $stmt_r->execute();
    $stat_rusak = $stmt_r->get_result()->fetch_assoc()['total'];
    $stmt_r->close();

    $stmt_h = $conn->prepare("SELECT COUNT(*) as total FROM pengecekan_barang WHERE id_periode = ? AND kondisi_temuan = 'Hilang'");
    $stmt_h->bind_param("i", $selected_periode_id);
    $stmt_h->execute();
    $stat_hilang = $stmt_h->get_result()->fetch_assoc()['total'];
    $stmt_h->close();
}

// Fetch all active goods and join with checking info for the selected period
$query_goods = "SELECT b.id, b.nama_barang, b.kondisi as kondisi_asal, m.nama_merk, k.nama_kategori, 
                       u.nama_unit, r.nama_ruang,
                       pb.id as id_pengecekan, pb.kondisi_temuan, pb.status_review, pb.tgl_pengecekan, 
                       pt.nama_lengkap as nama_petugas
                FROM barang b
                LEFT JOIN merk m ON b.id_merk = m.id
                LEFT JOIN kategori k ON b.id_kategori = k.id
                LEFT JOIN unit u ON b.id_unit = u.id
                LEFT JOIN ruang r ON b.id_ruang = r.id
                LEFT JOIN pengecekan_barang pb ON b.id = pb.id_barang AND pb.id_periode = $selected_periode_id
                LEFT JOIN users pt ON pb.id_petugas = pt.id
                WHERE b.status_aktif = 'aktif'";

if ($unit_id > 0) {
    $query_goods .= " AND b.id_unit = $unit_id";
}
if ($ruang_id > 0) {
    $query_goods .= " AND b.id_ruang = $ruang_id";
}

if ($filter_status === 'sudah') {
    $query_goods .= " AND pb.id IS NOT NULL";
} elseif ($filter_status === 'belum') {
    $query_goods .= " AND pb.id IS NULL";
}

$query_goods .= " ORDER BY b.id DESC";
$goods_res = $conn->query($query_goods);

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>Monitoring Hasil Pengecekan</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Monitoring</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">

        <!-- Sesi Selector & Global Stats Card -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="monitoring_pengecekan.php" class="row align-items-center mb-3">
                    <div class="col-md-5">
                        <div class="form-group mb-0">
                            <label style="font-weight:700; font-size:13px; color:#4b5563;">Pilih Periode Pengecekan</label>
                            <select name="periode_id" class="form-control select2" onchange="this.form.submit()">
                                <option value="0">-- Pilih Periode --</option>
                                <?php foreach($periods as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= $selected_periode_id == $p['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($p['nama_periode']) ?> (<?= $p['tahun'] ?>) - [<?= strtoupper($p['status']) ?>]
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-7 text-md-right mt-3 mt-md-0">
                        <?php 
                        $selected_p_meta = null;
                        foreach($periods as $p) { if ($p['id'] == $selected_periode_id) { $selected_p_meta = $p; break; } }
                        if ($selected_p_meta):
                        ?>
                            <span class="badge badge-<?= $selected_p_meta['status'] === 'aktif' ? 'success' : 'secondary' ?> px-3 py-1 font-weight-bold" style="font-size:13px;">
                                <i class="fas <?= $selected_p_meta['status'] === 'aktif' ? 'fa-play-circle' : 'fa-check-circle' ?> mr-1"></i>
                                Status Sesi: <?= strtoupper($selected_p_meta['status']) ?>
                            </span>
                            <small class="text-muted d-block mt-1">Pengecekan berjalan dari <?= date('d/m/Y', strtotime($selected_p_meta['tgl_mulai'])) ?> hingga <?= date('d/m/Y', strtotime($selected_p_meta['tgl_selesai'])) ?></small>
                        <?php endif; ?>
                    </div>
                </form>

                <?php if ($selected_periode_id > 0): ?>
                    <hr>
                    <!-- Progress Sensus & Quick stats -->
                    <div class="row align-items-center mt-3">
                        <div class="col-lg-4 col-12 mb-3 mb-lg-0">
                            <h6 class="font-weight-bold text-indigo"><i class="fas fa-spinner fa-spin mr-1"></i> PROGRESS PENGECEKAN</h6>
                            <div class="d-flex align-items-center mt-2">
                                <div class="progress progress-sm w-100 mr-3" style="height:12px; border-radius:6px; background:#e5e7eb;">
                                    <div class="progress-bar bg-indigo" style="width: <?= $pct ?>%; border-radius:6px;"></div>
                                </div>
                                <span class="h5 mb-0 font-weight-bold" style="white-space:nowrap;"><?= $pct ?>%</span>
                            </div>
                            <small class="text-muted"><?= $sudah_dicek ?> dari <?= $total_barang_aktif ?> barang telah diperiksa petugas.</small>
                        </div>
                        
                        <div class="col-lg-8 col-12">
                            <div class="row">
                                <div class="col-md-3 col-6 text-center">
                                    <h4 class="font-weight-bold text-dark mb-1"><?= $total_barang_aktif ?></h4>
                                    <small class="text-muted text-uppercase" style="font-size:10px; font-weight:700;">Barang Aktif</small>
                                </div>
                                <div class="col-md-3 col-6 text-center" style="border-left:1px solid #e5e7eb;">
                                    <h4 class="font-weight-bold text-success mb-1"><?= $sudah_dicek ?></h4>
                                    <small class="text-muted text-uppercase" style="font-size:10px; font-weight:700;">Sudah Dicek</small>
                                </div>
                                <div class="col-md-3 col-6 text-center" style="border-left:1px solid #e5e7eb;">
                                    <h4 class="font-weight-bold text-danger mb-1"><?= $belum_dicek ?></h4>
                                    <small class="text-muted text-uppercase" style="font-size:10px; font-weight:700;">Belum Dicek</small>
                                </div>
                                <div class="col-md-3 col-6 text-center" style="border-left:1px solid #e5e7eb;">
                                    <h6 class="mb-0 text-success" style="font-size:11px; font-weight:600;"><i class="fas fa-check-circle mr-1"></i>Baik: <?= $stat_baik ?></h6>
                                    <h6 class="mb-0 text-warning" style="font-size:11px; font-weight:600;"><i class="fas fa-exclamation-triangle mr-1"></i>Rusak: <?= $stat_rusak ?></h6>
                                    <h6 class="mb-0 text-danger" style="font-size:11px; font-weight:600;"><i class="fas fa-times-circle mr-1"></i>Hilang: <?= $stat_hilang ?></h6>
                                    <small class="text-muted text-uppercase" style="font-size:9px; font-weight:700;">Kondisi Temuan</small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($selected_periode_id > 0): ?>
            <!-- Filter detail tabel -->
            <div class="card mb-3">
                <div class="card-body" style="padding:12px 20px;">
                    <form method="GET" action="monitoring_pengecekan.php" class="row align-items-end">
                        <input type="hidden" name="periode_id" value="<?= $selected_periode_id ?>">
                        <div class="col-md-3">
                            <div class="form-group mb-0">
                                <label style="font-size:11px; font-weight:700;">Status Pengecekan</label>
                                <select name="status_cek" class="form-control form-control-sm">
                                    <option value="semua" <?= $filter_status === 'semua' ? 'selected' : '' ?>>-- Semua --</option>
                                    <option value="sudah" <?= $filter_status === 'sudah' ? 'selected' : '' ?>>Sudah Dicek (Selesai)</option>
                                    <option value="belum" <?= $filter_status === 'belum' ? 'selected' : '' ?>>Belum Dicek</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group mb-0">
                                <label style="font-size:11px; font-weight:700;">Filter Unit</label>
                                <select name="unit_id" id="filter_unit" class="form-control form-control-sm select2">
                                    <option value="0">-- Semua Unit --</option>
                                    <?php 
                                    $units->data_seek(0);
                                    while($u = $units->fetch_assoc()): 
                                    ?>
                                        <option value="<?= $u['id'] ?>" <?= $unit_id == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['nama_unit']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group mb-0">
                                <label style="font-size:11px; font-weight:700;">Filter Ruang</label>
                                <select name="ruang_id" id="filter_ruang" class="form-control form-control-sm select2">
                                    <option value="0">-- Semua Ruang --</option>
                                    <?php foreach($rooms as $r): ?>
                                        <option value="<?= $r['id'] ?>" <?= $ruang_id == $r['id'] ? 'selected' : '' ?>><?= htmlspecialchars($r['nama_ruang']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-filter mr-1"></i> Terapkan
                            </button>
                            <a href="monitoring_pengecekan.php?periode_id=<?= $selected_periode_id ?>" class="btn btn-secondary btn-sm">
                                <i class="fas fa-sync mr-1"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Goods list card -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-list mr-2 text-indigo"></i>Daftar Status Pengecekan Barang</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="dataTableMonitoring" class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th width="50">No</th>
                                    <th width="80">Kode</th>
                                    <th>Nama Barang</th>
                                    <th>Lokasi Barang</th>
                                    <th width="120" class="text-center">Status Cek</th>
                                    <th width="120" class="text-center">Temuan Petugas</th>
                                    <th width="100" class="text-center">Review</th>
                                    <th>Petugas / Waktu</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $no = 1; 
                                while ($row = $goods_res->fetch_assoc()): 
                                    $code = str_pad($row['id'], 5, "0", STR_PAD_LEFT);
                                    $is_checked = !empty($row['id_pengecekan']);
                                ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><code><?= $code ?></code></td>
                                    <td>
                                        <strong><?= htmlspecialchars($row['nama_barang']) ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars($row['nama_kategori'] ?? '-') ?> | <?= htmlspecialchars($row['nama_merk'] ?? '-') ?></small>
                                    </td>
                                    <td>
                                        <small>
                                            <i class="fas fa-building mr-1"></i> <?= htmlspecialchars($row['nama_unit'] ?? '-') ?><br>
                                            <i class="fas fa-door-open mr-1"></i> <?= htmlspecialchars($row['nama_ruang'] ?? '-') ?>
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($is_checked): ?>
                                            <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i> Sudah Dicek</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger px-2 py-1"><i class="fas fa-times-circle mr-1"></i> Belum Dicek</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($is_checked): 
                                            $t_badge = 'success';
                                            if ($row['kondisi_temuan'] === 'Rusak') $t_badge = 'warning';
                                            if ($row['kondisi_temuan'] === 'Hilang') $t_badge = 'danger';
                                        ?>
                                            <span class="badge badge-<?= $t_badge ?> px-2 py-1"><?= $row['kondisi_temuan'] ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($is_checked): 
                                            $r_badge = 'warning';
                                            if ($row['status_review'] === 'disetujui') $r_badge = 'success';
                                            if ($row['status_review'] === 'ditolak') $r_badge = 'danger';
                                        ?>
                                            <span class="badge badge-<?= $r_badge ?>"><?= ucfirst($row['status_review']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($is_checked): ?>
                                            <small>
                                                <i class="fas fa-user text-muted mr-1"></i> <?= htmlspecialchars($row['nama_petugas'] ?? '-') ?><br>
                                                <i class="fas fa-clock text-muted mr-1"></i> <?= date('d/m/Y H:i', strtotime($row['tgl_pengecekan'])) ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="text-muted text-xs">Belum ada data</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info py-4 text-center">
                <i class="fas fa-info-circle mb-3" style="font-size:32px;"></i>
                <h5>Silakan pilih atau buat periode pengecekan terlebih dahulu.</h5>
            </div>
        <?php endif; ?>

    </div>
</section>

<script>
$(function () {
    $('#dataTableMonitoring').DataTable({
        "paging": true,
        "lengthChange": true,
        "searching": true,
        "ordering": true,
        "info": true,
        "autoWidth": false,
        "responsive": true
    });

    // Dependent Dropdown for Filter
    $('#filter_unit').change(function() {
        let unitId = $(this).val();
        let target = $('#filter_ruang');
        
        target.html('<option value="0">-- Memuat... --</option>').trigger('change');
        
        if (unitId && unitId !== "0") {
            $.getJSON('get_ruang.php', { unit_id: unitId }, function(data) {
                let options = '<option value="0">-- Semua Ruang --</option>';
                data.forEach(function(r) {
                    options += `<option value="${r.id}">${r.nama_ruang}</option>`;
                });
                target.html(options).trigger('change');
            });
        } else {
            target.html('<option value="0">-- Semua Ruang --</option>').trigger('change');
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
