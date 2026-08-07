<?php
session_start();
include '../config/database.php';

$page_title = 'Dashboard';
$active_menu = 'dashboard';

// Ambil Periode Pengecekan Aktif
$res_periode_aktif = $conn->query("SELECT * FROM periode_pengecekan WHERE status = 'aktif' ORDER BY id DESC LIMIT 1");
$periode_aktif = null;
$pct_checked = 0;
$total_checked_petugas = 0;
$total_barang_aktif = 0;

if ($res_periode_aktif && $res_periode_aktif->num_rows > 0) {
    $periode_aktif = $res_periode_aktif->fetch_assoc();
    $id_periode = $periode_aktif['id'];
    $id_petugas = $_SESSION['user_id'];

    // Total barang aktif saat ini
    $total_barang_aktif = $conn->query("SELECT COUNT(*) as total FROM barang WHERE status_aktif = 'aktif'")->fetch_assoc()['total'];

    $stmt_cek = $conn->prepare("SELECT COUNT(*) as total FROM pengecekan_barang WHERE id_periode = ? AND id_petugas = ?");
    $stmt_cek->bind_param("ii", $id_periode, $id_petugas);
    $stmt_cek->execute();
    $total_checked_petugas = $stmt_cek->get_result()->fetch_assoc()['total'];
    $stmt_cek->close();

    // Progress total (semua petugas)
    $stmt_all_cek = $conn->prepare("SELECT COUNT(DISTINCT id_barang) as total FROM pengecekan_barang WHERE id_periode = ?");
    $stmt_all_cek->bind_param("i", $id_periode);
    $stmt_all_cek->execute();
    $total_checked_all = $stmt_all_cek->get_result()->fetch_assoc()['total'];
    $stmt_all_cek->close();

    if ($total_barang_aktif > 0) {
        $pct_checked = round(($total_checked_all / $total_barang_aktif) * 100);
    }
}

// Ambil 5 riwayat pengecekan terbaru oleh petugas ini
$riwayat_res = null;
if (isset($_SESSION['user_id'])) {
    $id_petugas = $_SESSION['user_id'];
    $riwayat_query = "SELECT pb.*, b.nama_barang, pe.nama_periode 
                      FROM pengecekan_barang pb
                      JOIN barang b ON pb.id_barang = b.id
                      JOIN periode_pengecekan pe ON pb.id_periode = pe.id
                      WHERE pb.id_petugas = $id_petugas
                      ORDER BY pb.tgl_pengecekan DESC LIMIT 5";
    $riwayat_res = $conn->query($riwayat_query);
}

include '../includes/header_petugas.php';
include '../includes/sidebar_petugas.php';
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>Dashboard Petugas</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item active">Dashboard</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">

        <!-- Welcome Card -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card" style="background: linear-gradient(135deg, #312e81, #4c1d95); border-radius: 14px;">
                    <div class="card-body" style="padding: 20px 24px;">
                        <div class="row align-items-center">
                            <div class="col-md-9 col-12">
                                <h5 style="color: #fff; font-weight: 700; margin-bottom: 6px;">
                                    Halo, <?= htmlspecialchars($_SESSION['nama_lengkap']) ?>!
                                </h5>
                                <p style="color: rgba(255,255,255,0.7); margin: 0; font-size: 13px;">
                                    Siap melakukan Inventarisasi? Pilih menu <b>Scan & Cek Barang</b> untuk mulai memeriksa kondisi fisik aset inventaris.
                                </p>
                            </div>
                            <div class="col-md-3 col-12 text-md-right mt-3 mt-md-0">
                                <a href="scan_barang.php" class="btn btn-light px-4" style="border-radius: 10px; font-weight: 700; color: #4c1d95;">
                                    <i class="fas fa-qrcode mr-2"></i> Cek Barang Now
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Active Tasks -->
            <div class="col-md-6 col-12 mb-4">
                <div class="card card-primary card-outline h-100">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-calendar-check mr-2 text-indigo"></i>Tugas Pengecekan Aktif</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($periode_aktif): ?>
                            <div class="mb-3">
                                <h5><b><?= htmlspecialchars($periode_aktif['nama_periode']) ?></b></h5>
                                <p class="text-muted text-sm mb-2"><i class="fas fa-clock mr-1"></i> Batas Akhir: <?= date('d F Y', strtotime($periode_aktif['tgl_selesai'])) ?></p>
                            </div>
                            
                            <!-- Stats Boxes -->
                            <div class="row mb-4">
                                <div class="col-6">
                                    <div class="p-3 bg-light text-center" style="border-radius:10px;">
                                        <h3 class="font-weight-bold text-primary mb-1"><?= $total_checked_petugas ?></h3>
                                        <small class="text-muted font-weight-bold">Telah Anda Inventarisasi</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-3 bg-light text-center" style="border-radius:10px;">
                                        <h3 class="font-weight-bold text-dark mb-1"><?= $total_barang_aktif ?></h3>
                                        <small class="text-muted font-weight-bold">Total Target Barang</small>
                                    </div>
                                </div>
                            </div>

                            <div class="progress-group mb-2">
                                Progress Pengecekan Global (Semua Petugas)
                                <span class="float-right"><b><?= $pct_checked ?>%</b></span>
                                <div class="progress progress-sm" style="border-radius: 6px; height: 10px; background:#e5e7eb;">
                                    <div class="progress-bar bg-indigo" style="width: <?= $pct_checked ?>%; border-radius:6px;"></div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-calendar-times text-muted mb-3" style="font-size: 50px;"></i>
                                <h6 class="font-weight-bold">Tidak ada tugas pengecekan berjalan saat ini.</h6>
                                <p class="text-muted text-sm">Menunggu administrator untuk memulai sesi periode pengecekan berkala baru.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Inspections -->
            <div class="col-md-6 col-12 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-history mr-2 text-indigo"></i>Pengecekan Terakhir Saya</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($riwayat_res && $riwayat_res->num_rows > 0): ?>
                            <div class="timeline timeline-inverse" style="font-size:13px;">
                                <?php while ($rw = $riwayat_res->fetch_assoc()): 
                                    $c_badge = 'success';
                                    if ($rw['kondisi_temuan'] === 'Rusak') $c_badge = 'warning';
                                    if ($rw['kondisi_temuan'] === 'Hilang') $c_badge = 'danger';

                                    $r_badge = 'warning';
                                    if ($rw['status_review'] === 'disetujui') $r_badge = 'success';
                                    if ($rw['status_review'] === 'ditolak') $r_badge = 'danger';
                                ?>
                                    <div>
                                        <i class="fas fa-check-circle bg-indigo"></i>
                                        <div class="timeline-item" style="border-radius:10px; margin-left:45px; background:#f9fafb; border:none; box-shadow:none; padding:10px 15px;">
                                            <span class="time text-xs"><i class="far fa-clock mr-1"></i><?= date('d/m H:i', strtotime($rw['tgl_pengecekan'])) ?></span>
                                            <h6 class="timeline-header border-0 p-0 font-weight-bold">
                                                <?= htmlspecialchars($rw['nama_barang']) ?>
                                            </h6>
                                            <div class="timeline-body p-0 mt-1">
                                                Kondisi Temuan: <span class="badge badge-<?= $c_badge ?> py-0 px-2"><?= $rw['kondisi_temuan'] ?></span> | 
                                                Review: <span class="badge badge-<?= $r_badge ?> py-0 px-2"><?= ucfirst($rw['status_review']) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-clipboard-list text-muted mb-3" style="font-size: 50px;"></i>
                                <h6 class="font-weight-bold">Belum ada riwayat pengecekan.</h6>
                                <p class="text-muted text-sm">Hasil pengecekan barang yang Anda lakukan akan tampil di sini.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>

<?php include '../includes/footer.php'; ?>
