<?php
session_start();
include '../config/database.php';

$page_title = 'Periode Pengecekan';
$active_menu = 'periode_pengecekan';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $nama_periode = trim($_POST['nama_periode']);
        $tahun        = intval($_POST['tahun']);
        $tgl_mulai    = $_POST['tgl_mulai'];
        $tgl_selesai  = $_POST['tgl_selesai'];
        $id_user      = $_SESSION['user_id'];

        // Cek apakah ada periode yang masih aktif
        $check = $conn->query("SELECT id FROM periode_pengecekan WHERE status = 'aktif'");
        if ($check->num_rows > 0) {
            $_SESSION['error'] = 'Gagal membuat periode baru. Masih ada periode pengecekan yang AKTIF!';
        } else {
            $stmt = $conn->prepare("INSERT INTO periode_pengecekan (nama_periode, tahun, tgl_mulai, tgl_selesai, status, id_user_pembuat) VALUES (?, ?, ?, ?, 'aktif', ?)");
            $stmt->bind_param("sissi", $nama_periode, $tahun, $tgl_mulai, $tgl_selesai, $id_user);
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Periode pengecekan berhasil dibuat!';
            } else {
                $_SESSION['error'] = 'Gagal membuat periode pengecekan: ' . $conn->error;
            }
            $stmt->close();
        }
        header('Location: periode_pengecekan.php');
        exit;
    }

    if ($action === 'close') {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("UPDATE periode_pengecekan SET status = 'selesai' WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Periode pengecekan berhasil ditutup!';
        } else {
            $_SESSION['error'] = 'Gagal menutup periode pengecekan!';
        }
        $stmt->close();
        header('Location: periode_pengecekan.php');
        exit;
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM periode_pengecekan WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Periode pengecekan berhasil dihapus!';
    } else {
        $_SESSION['error'] = 'Gagal menghapus periode pengecekan!';
    }
    $stmt->close();
    header('Location: periode_pengecekan.php');
    exit;
}

// Get all periods
$query = "SELECT p.*, u.nama_lengkap as pembuat FROM periode_pengecekan p 
          LEFT JOIN users u ON p.id_user_pembuat = u.id 
          ORDER BY p.id DESC";
$result = $conn->query($query);

// Cek apakah ada periode aktif
$has_active = false;
$active_res = $conn->query("SELECT id FROM periode_pengecekan WHERE status = 'aktif'");
if ($active_res && $active_res->num_rows > 0) {
    $has_active = true;
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>Periode Pengecekan</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Periode</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle mr-1"></i> <?= $_SESSION['success'] ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle mr-1"></i> <?= $_SESSION['error'] ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h3 class="card-title">
                    <i class="fas fa-calendar-alt mr-2 text-indigo"></i>
                    Daftar Sesi Pengecekan Barang Berkala
                </h3>
                <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalTambahPeriode" <?= $has_active ? 'disabled title="Tutup periode aktif terlebih dahulu untuk membuat periode baru"' : '' ?>>
                    <i class="fas fa-plus mr-1"></i> Buat Sesi Baru
                </button>
            </div>
            <div class="card-body">
                <?php if ($has_active): ?>
                    <div class="alert alert-warning py-2 mb-3" style="font-size: 13px; border-radius: 10px;">
                        <i class="fas fa-exclamation-circle mr-1"></i> Saat ini terdapat periode pengecekan yang sedang berjalan (aktif). Petugas hanya bisa menginput pengecekan pada periode yang aktif.
                    </div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table id="dataTablePeriode" class="table table-hover table-striped">
                        <thead>
                            <tr>
                                <th width="50">No</th>
                                <th>Nama Periode</th>
                                <th width="80" class="text-center">Tahun</th>
                                <th width="200" class="text-center">Mulai - Selesai</th>
                                <th>Progress Pengecekan</th>
                                <th width="100" class="text-center">Status</th>
                                <th width="150" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1; 
                            $barang_aktif_count = $conn->query("SELECT COUNT(*) as total FROM barang WHERE status_aktif = 'aktif'")->fetch_assoc()['total'];
                            while ($row = $result->fetch_assoc()): 
                                // Hitung barang yang sudah dicek di periode ini
                                $stmt_cek = $conn->prepare("SELECT COUNT(DISTINCT id_barang) as total FROM pengecekan_barang WHERE id_periode = ?");
                                $stmt_cek->bind_param("i", $row['id']);
                                $stmt_cek->execute();
                                $sudah_dicek = $stmt_cek->get_result()->fetch_assoc()['total'];
                                $stmt_cek->close();

                                $pct = 0;
                                if ($barang_aktif_count > 0) {
                                    $pct = round(($sudah_dicek / $barang_aktif_count) * 100);
                                }
                            ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($row['nama_periode']) ?></strong><br>
                                    <small class="text-muted"><i class="fas fa-user mr-1"></i> Dibuat oleh: <?= htmlspecialchars($row['pembuat']) ?></small>
                                </td>
                                <td class="text-center"><code><?= $row['tahun'] ?></code></td>
                                <td class="text-center">
                                    <small>
                                        <?= date('d/m/Y', strtotime($row['tgl_mulai'])) ?> s/d <?= date('d/m/Y', strtotime($row['tgl_selesai'])) ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="progress progress-xs w-100 mr-2" style="height:6px; border-radius:3px;">
                                            <div class="progress-bar bg-indigo" style="width: <?= $pct ?>%; border-radius:3px;"></div>
                                        </div>
                                        <small class="font-weight-bold" style="white-space:nowrap;"><?= $sudah_dicek ?>/<?= $barang_aktif_count ?> (<?= $pct ?>%)</small>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <?php if ($row['status'] === 'aktif'): ?>
                                        <span class="badge badge-success px-3 py-1"><i class="fas fa-play mr-1"></i> Aktif</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary px-3 py-1"><i class="fas fa-check mr-1"></i> Selesai</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <a href="monitoring_pengecekan.php?periode_id=<?= $row['id'] ?>" class="btn btn-info btn-xs" title="Monitoring Detail">
                                        <i class="fas fa-chart-pie"></i>
                                    </a>
                                    <?php if ($row['status'] === 'aktif'): ?>
                                        <button class="btn btn-warning btn-xs text-white" onclick="closePeriode(<?= $row['id'] ?>, '<?= htmlspecialchars($row['nama_periode']) ?>')" title="Tutup Periode Pengecekan">
                                            <i class="fas fa-stop"></i> Tutup
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn btn-danger btn-xs" onclick="confirmDeletePeriode(<?= $row['id'] ?>)" title="Hapus Periode">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</section>

<!-- Modal Tambah Periode -->
<div class="modal fade" id="modalTambahPeriode">
    <div class="modal-dialog">
        <div class="modal-content text-left">
            <form method="POST" action="periode_pengecekan.php">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-calendar-plus text-primary mr-2"></i>Buat Periode Pengecekan Baru</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Periode <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_periode" placeholder="Contoh: Sensus Inventaris 2026 Tahap 1" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Tahun Sesi <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="tahun" value="<?= date('Y') ?>" min="2020" max="2100" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Tanggal Mulai <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="tgl_mulai" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Tanggal Batas Akhir (Selesai) <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="tgl_selesai" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan Sesi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Form tersembunyi untuk submit penutupan periode -->
<form id="formClosePeriode" method="POST" action="periode_pengecekan.php" style="display:none;">
    <input type="hidden" name="action" value="close">
    <input type="hidden" name="id" id="close_id">
</form>

<script>
$(function () {
    $('#dataTablePeriode').DataTable({
        "paging": true,
        "lengthChange": true,
        "searching": true,
        "ordering": true,
        "info": true,
        "autoWidth": false,
        "responsive": true
    });
});

function closePeriode(id, nama) {
    Swal.fire({
        title: 'Tutup Periode Pengecekan?',
        text: `Apakah Anda yakin ingin menutup periode "${nama}"? Petugas tidak akan bisa menginputkan pengecekan lagi ke periode ini.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#f59e0b',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Tutup!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('close_id').value = id;
            document.getElementById('formClosePeriode').submit();
        }
    });
}

function confirmDeletePeriode(id) {
    Swal.fire({
        title: 'Hapus Periode Pengecekan?',
        text: 'Menghapus periode ini akan menghapus seluruh data riwayat pengecekan barang yang terkait di dalamnya! Tindakan ini tidak dapat dibatalkan.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'periode_pengecekan.php?delete=' + id;
        }
    });
}
</script>

<?php include '../includes/footer.php'; ?>
