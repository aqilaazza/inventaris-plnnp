<?php
session_start();
include '../config/database.php';

$page_title = 'Dashboard';
$active_menu = 'dashboard';

// Hitung statistik (hanya barang aktif)
$total_unit    = $conn->query("SELECT COUNT(*) as total FROM unit")->fetch_assoc()['total'];
$total_ruang   = $conn->query("SELECT COUNT(*) as total FROM ruang")->fetch_assoc()['total'];
$total_barang  = $conn->query("SELECT SUM(jumlah) as total FROM barang WHERE status_aktif = 'aktif'")->fetch_assoc()['total'] ?? 0;
$total_baik    = $conn->query("SELECT SUM(jumlah) as total FROM barang WHERE kondisi = 'Baik' AND status_aktif = 'aktif'")->fetch_assoc()['total'] ?? 0;
$total_rusak   = $conn->query("SELECT SUM(jumlah) as total FROM barang WHERE kondisi = 'Rusak' AND status_aktif = 'aktif'")->fetch_assoc()['total'] ?? 0;
$total_hilang  = $conn->query("SELECT SUM(jumlah) as total FROM barang WHERE kondisi = 'Hilang' AND status_aktif = 'aktif'")->fetch_assoc()['total'] ?? 0;

// Ambil Periode Pengecekan Aktif
$res_periode_aktif = $conn->query("SELECT * FROM periode_pengecekan WHERE status = 'aktif' ORDER BY id DESC LIMIT 1");
$periode_aktif = null;
$pct_checked = 0;
$total_checked = 0;
$barang_aktif_count = 0;
if ($res_periode_aktif && $res_periode_aktif->num_rows > 0) {
    $periode_aktif = $res_periode_aktif->fetch_assoc();
    
    // Hitung progress
    $barang_aktif_count = $conn->query("SELECT COUNT(*) as total FROM barang WHERE status_aktif = 'aktif'")->fetch_assoc()['total'];
    $stmt_cek = $conn->prepare("SELECT COUNT(DISTINCT id_barang) as total FROM pengecekan_barang WHERE id_periode = ? AND status_review = 'disetujui'");
    $stmt_cek->bind_param("i", $periode_aktif['id']);
    $stmt_cek->execute();
    $total_checked = $stmt_cek->get_result()->fetch_assoc()['total'];
    $stmt_cek->close();
    
    if ($barang_aktif_count > 0) {
        $pct_checked = round(($total_checked / $barang_aktif_count) * 100);
    }
}

// Hitung Review Pending
$pending_review_count = 0;
$res_pending = $conn->query("SELECT COUNT(*) as total FROM pengecekan_barang WHERE status_review = 'menunggu'");
if ($res_pending) {
    $pending_review_count = intval($res_pending->fetch_assoc()['total']);
}

// ====== DATA UNTUK GRAFIK DASHBOARD ======

// Distribusi barang per unit
$data_unit_labels = [];
$data_unit_values = [];
$res_chart_unit = $conn->query("
    SELECT COALESCE(u.nama_unit, 'Belum Ditentukan') as nama, SUM(b.jumlah) as total 
    FROM barang b LEFT JOIN unit u ON b.id_unit = u.id 
    WHERE b.status_aktif = 'aktif' GROUP BY b.id_unit ORDER BY total DESC
");
if ($res_chart_unit) {
    while ($r = $res_chart_unit->fetch_assoc()) {
        $data_unit_labels[] = ucwords(strtolower($r['nama']));
        $data_unit_values[] = intval($r['total']);
    }
}

// Top 10 Kategori
$data_kat_labels = [];
$data_kat_values = [];
$res_chart_kat = $conn->query("
    SELECT COALESCE(k.nama_kategori, 'Tanpa Kategori') as nama, SUM(b.jumlah) as total 
    FROM barang b LEFT JOIN kategori k ON b.id_kategori = k.id 
    WHERE b.status_aktif = 'aktif' GROUP BY b.id_kategori ORDER BY total DESC LIMIT 10
");
if ($res_chart_kat) {
    while ($r = $res_chart_kat->fetch_assoc()) {
        $data_kat_labels[] = ucwords(strtolower($r['nama']));
        $data_kat_values[] = intval($r['total']);
    }
}

// Top 10 Ruang
$data_ruang_labels = [];
$data_ruang_values = [];
$res_chart_ruang = $conn->query("
    SELECT CONCAT(COALESCE(r.nama_ruang, '-'), ' (', COALESCE(u.nama_unit, '-'), ')') as label, SUM(b.jumlah) as total 
    FROM barang b LEFT JOIN ruang r ON b.id_ruang = r.id LEFT JOIN unit u ON b.id_unit = u.id 
    WHERE b.status_aktif = 'aktif' GROUP BY b.id_ruang ORDER BY total DESC LIMIT 10
");
if ($res_chart_ruang) {
    while ($r = $res_chart_ruang->fetch_assoc()) {
        $data_ruang_labels[] = ucwords(strtolower($r['label']));
        $data_ruang_values[] = intval($r['total']);
    }
}

// 5 Barang terbaru ditambahkan
$data_terbaru = [];
$res_terbaru = $conn->query("
    SELECT b.id, b.nama_barang, b.kondisi, b.created_at, 
           COALESCE(k.nama_kategori, '-') as nama_kategori, 
           COALESCE(u.nama_unit, '-') as nama_unit, 
           COALESCE(r.nama_ruang, '-') as nama_ruang 
    FROM barang b LEFT JOIN kategori k ON b.id_kategori = k.id 
    LEFT JOIN unit u ON b.id_unit = u.id LEFT JOIN ruang r ON b.id_ruang = r.id 
    WHERE b.status_aktif = 'aktif' ORDER BY b.created_at DESC LIMIT 5
");
if ($res_terbaru) {
    while ($r = $res_terbaru->fetch_assoc()) {
        $data_terbaru[] = $r;
    }
}

// Ringkasan Penghapusan
$data_hapus_labels = [];
$data_hapus_values = [];
$total_penghapusan = 0;
$res_hapus = $conn->query("SELECT alasan, COUNT(*) as total FROM penghapusan_barang GROUP BY alasan ORDER BY total DESC");
if ($res_hapus) {
    while ($r = $res_hapus->fetch_assoc()) {
        $data_hapus_labels[] = $r['alasan'];
        $data_hapus_values[] = intval($r['total']);
        $total_penghapusan += intval($r['total']);
    }
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>Dashboard</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item active">Dashboard</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">

        <!-- Welcome Card -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="card" style="background: linear-gradient(135deg, #1e1b4b, #312e81); border-radius: 12px;">
                    <div class="card-body" style="padding: 15px 20px;">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h6 style="color: #fff; font-weight: 700; margin-bottom: 4px; font-size: 16px;">
                                    Selamat Datang, <?= htmlspecialchars($_SESSION['nama_lengkap']) ?>!
                                </h6>
                                <p style="color: rgba(255,255,255,0.6); margin: 0; font-size: 12px;">
                                    Kelola inventaris barang dengan mudah melalui panel admin ini.
                                </p>
                            </div>
                            <div class="col-md-4 text-right d-none d-md-block">
                                <i class="fas fa-chart-line" style="font-size: 32px; color: rgba(255,255,255,0.1);"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stat Cards -->
        <div class="row">
            <div class="col-lg-2 col-6">
                <div class="small-box"
                    style="background: linear-gradient(135deg, #6366f1, #818cf8); border-radius: 12px; margin-bottom: 15px;">
                    <div class="inner" style="padding: 15px;">
                        <h3 style="color:#fff; font-size: 20px; font-weight: 700;"><?= $total_unit ?></h3>
                        <p style="color:rgba(255,255,255,0.8); font-size: 12px; margin-bottom: 0;">Unit</p>
                    </div>
                    <div class="icon" style="font-size: 35px; top: 5px; right: 10px;">
                        <i class="fas fa-building" style="opacity: 0.3;"></i>
                    </div>
                    <a href="unit.php" class="small-box-footer"
                        style="background: rgba(0,0,0,0.05); color:#fff; font-size:11px; padding: 3px 0;">
                        Detail <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>

            <div class="col-lg-2 col-6">
                <div class="small-box"
                    style="background: linear-gradient(135deg, #0ea5e9, #38bdf8); border-radius: 12px; margin-bottom: 15px;">
                    <div class="inner" style="padding: 15px;">
                        <h3 style="color:#fff; font-size: 20px; font-weight: 700;"><?= $total_ruang ?></h3>
                        <p style="color:rgba(255,255,255,0.8); font-size: 12px; margin-bottom: 0;">Ruang</p>
                    </div>
                    <div class="icon" style="font-size: 35px; top: 5px; right: 10px;">
                        <i class="fas fa-door-open" style="opacity: 0.3;"></i>
                    </div>
                    <a href="ruang.php" class="small-box-footer"
                        style="background: rgba(0,0,0,0.05); color:#fff; font-size:11px; padding: 3px 0;">
                        Detail <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>

            <div class="col-lg-2 col-6">
                <div class="small-box"
                    style="background: linear-gradient(135deg, #1e1b4b, #312e81); border-radius: 12px; margin-bottom: 15px;">
                    <div class="inner" style="padding: 15px;">
                        <h3 style="color:#fff; font-size: 20px; font-weight: 700;"><?= $total_barang ?></h3>
                        <p style="color:rgba(255,255,255,0.8); font-size: 12px; margin-bottom: 0;">Barang Aktif</p>
                    </div>
                    <div class="icon" style="font-size: 35px; top: 5px; right: 10px;">
                        <i class="fas fa-box" style="opacity: 0.3;"></i>
                    </div>
                    <a href="barang.php" class="small-box-footer"
                        style="background: rgba(0,0,0,0.05); color:#fff; font-size:11px; padding: 3px 0;">
                        Detail <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>

            <div class="col-lg-2 col-6">
                <div class="small-box"
                    style="background: linear-gradient(135deg, #10b981, #34d399); border-radius: 12px; margin-bottom: 15px;">
                    <div class="inner" style="padding: 15px;">
                        <h3 style="color:#fff; font-size: 20px; font-weight: 700;"><?= $total_baik ?></h3>
                        <p style="color:rgba(255,255,255,0.8); font-size: 12px; margin-bottom: 0;">Kondisi Baik</p>
                    </div>
                    <div class="icon" style="font-size: 35px; top: 5px; right: 10px;">
                        <i class="fas fa-check-circle" style="opacity: 0.3;"></i>
                    </div>
                    <a href="barang.php" class="small-box-footer"
                        style="background: rgba(0,0,0,0.05); color:#fff; font-size:11px; padding: 3px 0;">
                        Detail <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>

            <div class="col-lg-2 col-6">
                <div class="small-box"
                    style="background: linear-gradient(135deg, #f59e0b, #fbbf24); border-radius: 12px; margin-bottom: 15px;">
                    <div class="inner" style="padding: 15px;">
                        <h3 style="color:#fff; font-size: 20px; font-weight: 700;"><?= $total_rusak ?></h3>
                        <p style="color:rgba(255,255,255,0.8); font-size: 12px; margin-bottom: 0;">Kondisi Rusak</p>
                    </div>
                    <div class="icon" style="font-size: 35px; top: 5px; right: 10px;">
                        <i class="fas fa-exclamation-triangle" style="opacity: 0.3;"></i>
                    </div>
                    <a href="barang.php" class="small-box-footer"
                        style="background: rgba(0,0,0,0.05); color:#fff; font-size:11px; padding: 3px 0;">
                        Detail <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>

            <div class="col-lg-2 col-6">
                <div class="small-box"
                    style="background: linear-gradient(135deg, #ef4444, #f87171); border-radius: 12px; margin-bottom: 15px;">
                    <div class="inner" style="padding: 15px;">
                        <h3 style="color:#fff; font-size: 20px; font-weight: 700;"><?= $total_hilang ?></h3>
                        <p style="color:rgba(255,255,255,0.8); font-size: 12px; margin-bottom: 0;">Kondisi Hilang</p>
                    </div>
                    <div class="icon" style="font-size: 35px; top: 5px; right: 10px;">
                        <i class="fas fa-times-circle" style="opacity: 0.3;"></i>
                    </div>
                    <a href="barang.php" class="small-box-footer"
                        style="background: rgba(0,0,0,0.05); color:#fff; font-size:11px; padding: 3px 0;">
                        Detail <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Monitoring & Progress -->
        <div class="row">
            <!-- Periode Pengecekan Progress -->
            <div class="col-md-6 col-12">
                <div class="card card-indigo card-outline">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-calendar-check mr-2 text-indigo"></i>
                            Pengecekan Berkala (Aktif)
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if ($periode_aktif): ?>
                            <h5><b><?= htmlspecialchars($periode_aktif['nama_periode']) ?></b> (Tahun <?= $periode_aktif['tahun'] ?>)</h5>
                            <p class="text-muted mb-2">Batas Akhir: <?= date('d F Y', strtotime($periode_aktif['tgl_selesai'])) ?></p>
                            
                            <div class="progress-group mb-3">
                                Progress Pengecekan Barang
                                <span class="float-right"><b><?= $total_checked ?></b>/<?= $barang_aktif_count ?> Barang (<?= $pct_checked ?>%)</span>
                                <div class="progress progress-sm" style="border-radius: 5px; height: 10px;">
                                    <div class="progress-bar bg-primary" style="width: <?= $pct_checked ?>%; border-radius: 5px;"></div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="monitoring_pengecekan.php?periode_id=<?= $periode_aktif['id'] ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-chart-bar mr-1"></i> Monitoring Detail
                                </a>
                                <span class="badge badge-success d-flex align-items-center"><i class="fas fa-circle mr-1" style="font-size:7px;"></i> Periode Berjalan</span>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-times text-muted mb-3" style="font-size: 40px;"></i>
                                <p class="text-muted mb-2">Tidak ada periode pengecekan aktif saat ini.</p>
                                <a href="periode_pengecekan.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus mr-1"></i> Buat Periode Baru
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Review Pengecekan Pending -->
            <div class="col-md-6 col-12">
                <div class="card card-warning card-outline">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-shield-alt mr-2 text-warning"></i>
                            Persetujuan Pengecekan
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if ($pending_review_count > 0): ?>
                            <div class="text-center py-3">
                                <i class="fas fa-exclamation-circle text-warning mb-3" style="font-size: 40px;"></i>
                                <h5>Ada <b><?= $pending_review_count ?></b> temuan pengecekan yang butuh persetujuan!</h5>
                                <p class="text-muted text-sm">Petugas melaporkan barang rusak atau hilang yang membutuhkan persetujuan/peninjauan Anda.</p>
                                <a href="review_pengecekan.php" class="btn btn-warning text-white btn-sm px-4" style="border-radius: 8px; font-weight:600; background: linear-gradient(135deg, #f59e0b, #fbbf24); border:none;">
                                    <i class="fas fa-check-double mr-1"></i> Proses Sekarang
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-check-circle text-success mb-3" style="font-size: 40px;"></i>
                                <p class="text-muted mb-0">Semua laporan pengecekan telah ditinjau (0 antrean).</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ====== GRAFIK ROW 1: Kondisi, Per Unit, Top Kategori ====== -->
        <div class="row">
            <!-- Doughnut: Kondisi Barang -->
            <div class="col-md-4 col-12 mb-3">
                <div class="card h-100">
                    <div class="card-header bg-white py-3">
                        <h3 class="card-title font-weight-bold"><i class="fas fa-chart-pie mr-2 text-indigo"></i>Kondisi Barang</h3>
                    </div>
                    <div class="card-body d-flex align-items-center justify-content-center" style="padding: 20px;">
                        <div style="position: relative; width: 100%;">
                            <canvas id="chartKondisi"></canvas>
                            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; pointer-events: none;">
                                <span style="font-size: 32px; font-weight: 800; color: #1f2937;"><?= number_format($total_barang, 0, ',', '.') ?></span>
                                <br>
                                <span style="font-size: 12px; color: #9ca3af; font-weight: 600;">Total Barang</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Horizontal Bar: Distribusi per Unit -->
            <div class="col-md-4 col-12 mb-3">
                <div class="card h-100">
                    <div class="card-header bg-white py-3">
                        <h3 class="card-title font-weight-bold"><i class="fas fa-building mr-2 text-indigo"></i>Distribusi per Unit</h3>
                    </div>
                    <div class="card-body">
                        <div style="height: 240px;">
                            <canvas id="chartUnit"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Horizontal Bar: Top 10 Kategori -->
            <div class="col-md-4 col-12 mb-3">
                <div class="card h-100">
                    <div class="card-header bg-white py-3">
                        <h3 class="card-title font-weight-bold"><i class="fas fa-th-large mr-2 text-indigo"></i>Top 10 Kategori</h3>
                    </div>
                    <div class="card-body">
                        <div style="height: 240px;">
                            <canvas id="chartKategori"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ====== GRAFIK ROW 2: Top Ruang & Barang Terbaru ====== -->
        <div class="row">
            <!-- Horizontal Bar: Top 10 Ruang -->
            <div class="col-md-6 col-12 mb-3">
                <div class="card h-100">
                    <div class="card-header bg-white py-3">
                        <h3 class="card-title font-weight-bold"><i class="fas fa-door-open mr-2 text-indigo"></i>Top 10 Ruang</h3>
                    </div>
                    <div class="card-body">
                        <div style="height: 300px;">
                            <canvas id="chartRuang"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabel: Barang Terbaru Ditambahkan -->
            <div class="col-md-6 col-12 mb-3">
                <div class="card h-100">
                    <div class="card-header bg-white py-3">
                        <h3 class="card-title font-weight-bold"><i class="fas fa-clock mr-2 text-indigo"></i>Barang Terbaru Ditambahkan</h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Kode</th>
                                        <th>Nama Barang</th>
                                        <th>Lokasi</th>
                                        <th>Kondisi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($data_terbaru)): ?>
                                        <?php foreach ($data_terbaru as $item): ?>
                                            <?php
                                                $badge_cls = 'success';
                                                if ($item['kondisi'] === 'Rusak') $badge_cls = 'warning';
                                                if ($item['kondisi'] === 'Hilang') $badge_cls = 'danger';
                                            ?>
                                            <tr>
                                                <td><code><?= str_pad($item['id'], 5, '0', STR_PAD_LEFT) ?></code></td>
                                                <td>
                                                    <strong style="font-size: 12px;"><?= htmlspecialchars($item['nama_barang']) ?></strong>
                                                    <br><small class="text-muted"><?= htmlspecialchars(ucwords(strtolower($item['nama_kategori']))) ?></small>
                                                </td>
                                                <td style="font-size: 12px;">
                                                    <i class="fas fa-map-marker-alt text-danger mr-1" style="font-size:10px;"></i>
                                                    <?= htmlspecialchars($item['nama_unit']) ?>
                                                    <br><small class="text-muted"><?= htmlspecialchars($item['nama_ruang']) ?></small>
                                                </td>
                                                <td><span class="badge badge-<?= $badge_cls ?>"><?= $item['kondisi'] ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" class="text-center text-muted py-4">Belum ada data barang.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (!empty($data_terbaru)): ?>
                        <div class="card-footer bg-white text-center py-2" style="border-top: 1px solid #f3f4f6;">
                            <a href="barang.php" class="text-sm font-weight-bold" style="color: #6366f1; text-decoration: none;">Lihat Semua Barang <i class="fas fa-arrow-right ml-1"></i></a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ====== ROW 3: Ringkasan Penghapusan (opsional) ====== -->
        <?php if ($total_penghapusan > 0): ?>
        <div class="row">
            <div class="col-12 mb-3">
                <div class="card">
                    <div class="card-header bg-white py-3">
                        <h3 class="card-title font-weight-bold"><i class="fas fa-minus-circle mr-2 text-danger"></i>Ringkasan Penghapusan Barang</h3>
                        <div class="card-tools">
                            <span class="badge badge-danger px-3 py-2" style="font-size: 13px;"><?= $total_penghapusan ?> Barang Dihapus</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($data_penghapusan as $hp): ?>
                            <div class="col-md-3 col-6 mb-2">
                                <div class="d-flex align-items-center p-3" style="background: #fef2f2; border-radius: 10px;">
                                    <div class="mr-3">
                                        <i class="fas fa-exclamation-circle text-danger" style="font-size: 20px;"></i>
                                    </div>
                                    <div>
                                        <div style="font-size: 18px; font-weight: 800; color: #991b1b;"><?= $hp['total'] ?></div>
                                        <div style="font-size: 11px; color: #6b7280; font-weight: 600;"><?= htmlspecialchars($hp['alasan']) ?></div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</section>

<!-- Chart.js -->
<script src="../plugins/chart.js/Chart.min.js"></script>
<script>
$(function() {
    // Chart.js Global Defaults
    Chart.defaults.global.defaultFontFamily = "'Inter', sans-serif";
    Chart.defaults.global.defaultFontColor = '#6b7280';
    Chart.defaults.global.defaultFontSize = 12;

    // Helper: format angka dengan titik ribuan
    function fmtNum(n) {
        return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    // ============ 1. DOUGHNUT: Kondisi Barang ============
    var ctxKondisi = document.getElementById('chartKondisi').getContext('2d');
    new Chart(ctxKondisi, {
        type: 'doughnut',
        data: {
            labels: ['Baik', 'Rusak', 'Hilang'],
            datasets: [{
                data: [<?= intval($total_baik) ?>, <?= intval($total_rusak) ?>, <?= intval($total_hilang) ?>],
                backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                hoverBackgroundColor: ['#059669', '#d97706', '#dc2626'],
                borderWidth: 3,
                borderColor: '#ffffff',
                hoverBorderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            cutoutPercentage: 72,
            legend: {
                position: 'bottom',
                labels: {
                    padding: 16,
                    usePointStyle: true,
                    pointStyle: 'circle',
                    fontSize: 12,
                    fontStyle: '600'
                }
            },
            tooltips: {
                backgroundColor: 'rgba(30,27,75,0.9)',
                titleFontSize: 13,
                bodyFontSize: 12,
                xPadding: 12,
                yPadding: 10,
                cornerRadius: 8,
                callbacks: {
                    label: function(t, d) {
                        var ds = d.datasets[t.datasetIndex];
                        var total = ds.data.reduce(function(a, b) { return a + b; }, 0);
                        var val = ds.data[t.index];
                        var pct = total > 0 ? Math.round((val / total) * 100) : 0;
                        return ' ' + d.labels[t.index] + ': ' + fmtNum(val) + ' (' + pct + '%)';
                    }
                }
            },
            animation: {
                animateRotate: true,
                animateScale: true
            }
        }
    });

    // ============ 2. HORIZONTAL BAR: Per Unit ============
    var unitColors = ['#6366f1', '#8b5cf6', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#ec4899', '#14b8a6'];
    var ctxUnit = document.getElementById('chartUnit').getContext('2d');
    new Chart(ctxUnit, {
        type: 'horizontalBar',
        data: {
            labels: <?= json_encode($data_unit_labels) ?>,
            datasets: [{
                label: 'Jumlah Barang',
                data: <?= json_encode($data_unit_values) ?>,
                backgroundColor: unitColors.slice(0, <?= count($data_unit_labels) ?>),
                hoverBackgroundColor: unitColors.slice(0, <?= count($data_unit_labels) ?>).map(function(c) { return c + 'dd'; }),
                borderWidth: 0,
                barThickness: 20,
                maxBarThickness: 24
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: { display: false },
            scales: {
                xAxes: [{
                    ticks: { beginAtZero: true, fontSize: 11, fontColor: '#9ca3af' },
                    gridLines: { color: 'rgba(0,0,0,0.04)', drawBorder: false }
                }],
                yAxes: [{
                    ticks: { fontSize: 12, fontStyle: '600', fontColor: '#374151' },
                    gridLines: { display: false }
                }]
            },
            tooltips: {
                backgroundColor: 'rgba(30,27,75,0.9)',
                cornerRadius: 8,
                xPadding: 12,
                yPadding: 10,
                callbacks: {
                    label: function(t) { return ' ' + fmtNum(t.value) + ' barang'; }
                }
            }
        }
    });

    // ============ 3. HORIZONTAL BAR: Top 10 Kategori ============
    var katColors = ['#312e81','#3730a3','#4338ca','#4f46e5','#6366f1','#818cf8','#a5b4fc','#c7d2fe','#ddd6fe','#e0e7ff'];
    var ctxKat = document.getElementById('chartKategori').getContext('2d');
    new Chart(ctxKat, {
        type: 'horizontalBar',
        data: {
            labels: <?= json_encode($data_kat_labels) ?>,
            datasets: [{
                label: 'Jumlah Barang',
                data: <?= json_encode($data_kat_values) ?>,
                backgroundColor: katColors.slice(0, <?= count($data_kat_labels) ?>),
                borderWidth: 0,
                barThickness: 16,
                maxBarThickness: 20
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: { display: false },
            scales: {
                xAxes: [{
                    ticks: { beginAtZero: true, fontSize: 11, fontColor: '#9ca3af' },
                    gridLines: { color: 'rgba(0,0,0,0.04)', drawBorder: false }
                }],
                yAxes: [{
                    ticks: { fontSize: 11, fontStyle: '600', fontColor: '#374151' },
                    gridLines: { display: false }
                }]
            },
            tooltips: {
                backgroundColor: 'rgba(30,27,75,0.9)',
                cornerRadius: 8,
                xPadding: 12,
                yPadding: 10,
                callbacks: {
                    label: function(t) { return ' ' + fmtNum(t.value) + ' barang'; }
                }
            }
        }
    });

    // ============ 4. HORIZONTAL BAR: Top 10 Ruang ============
    var ruangColors = ['#134e4a','#115e59','#0f766e','#0d9488','#14b8a6','#2dd4bf','#5eead4','#99f6e4','#a7f3d0','#d1fae5'];
    var ctxRuang = document.getElementById('chartRuang').getContext('2d');
    new Chart(ctxRuang, {
        type: 'horizontalBar',
        data: {
            labels: <?= json_encode($data_ruang_labels) ?>,
            datasets: [{
                label: 'Jumlah Barang',
                data: <?= json_encode($data_ruang_values) ?>,
                backgroundColor: ruangColors.slice(0, <?= count($data_ruang_labels) ?>),
                borderWidth: 0,
                barThickness: 18,
                maxBarThickness: 22
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: { display: false },
            scales: {
                xAxes: [{
                    ticks: { beginAtZero: true, fontSize: 11, fontColor: '#9ca3af' },
                    gridLines: { color: 'rgba(0,0,0,0.04)', drawBorder: false }
                }],
                yAxes: [{
                    ticks: { fontSize: 11, fontStyle: '600', fontColor: '#374151' },
                    gridLines: { display: false }
                }]
            },
            tooltips: {
                backgroundColor: 'rgba(30,27,75,0.9)',
                cornerRadius: 8,
                xPadding: 12,
                yPadding: 10,
                callbacks: {
                    label: function(t) { return ' ' + fmtNum(t.value) + ' barang'; }
                }
            }
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>