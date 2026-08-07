<?php
session_start();
include '../config/database.php';

$page_title = 'Riwayat Pengecekan Saya';
$active_menu = 'riwayat_pengecekan';

$id_petugas = $_SESSION['user_id'];

// Fetch checking list for this petugas
$query = "SELECT pb.*, b.nama_barang, b.foto as foto_barang, m.nama_merk, k.nama_kategori, 
                 u.nama_unit, r.nama_ruang, 
                 pe.nama_periode, pe.tahun,
                 rv.nama_lengkap as nama_reviewer
          FROM pengecekan_barang pb
          JOIN barang b ON pb.id_barang = b.id
          LEFT JOIN merk m ON b.id_merk = m.id
          LEFT JOIN kategori k ON b.id_kategori = k.id
          LEFT JOIN unit u ON b.id_unit = u.id
          LEFT JOIN ruang r ON b.id_ruang = r.id
          JOIN periode_pengecekan pe ON pb.id_periode = pe.id
          LEFT JOIN users rv ON pb.id_reviewer = rv.id
          WHERE pb.id_petugas = $id_petugas
          ORDER BY pb.tgl_pengecekan DESC";
$result = $conn->query($query);

include '../includes/header_petugas.php';
include '../includes/sidebar_petugas.php';
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>Riwayat Pengecekan Saya</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Riwayat Pengecekan</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">

        <!-- Table Card -->
        <div class="card">
            <div class="card-header bg-white py-3">
                <h3 class="card-title font-weight-bold"><i class="fas fa-history mr-2 text-indigo"></i>Daftar Pengecekan Barang</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="dataTableRiwayat" class="table table-hover table-striped">
                        <thead>
                            <tr>
                                <th width="50">No</th>
                                <th>Barang / Aset</th>
                                <th width="150">Periode</th>
                                <th width="130">Tanggal Cek</th>
                                <th width="120">Temuan Fisik</th>
                                <th>Keterangan / Foto Bukti</th>
                                <th width="120" class="text-center">Status Review</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1; 
                            if ($result && $result->num_rows > 0):
                                while ($row = $result->fetch_assoc()): 
                                    $code = str_pad($row['id_barang'], 5, "0", STR_PAD_LEFT);
                            ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td>
                                    <code><?= $code ?></code> — <strong><?= htmlspecialchars($row['nama_barang']) ?></strong><br>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($row['nama_kategori'] ?? '-') ?> | <?= htmlspecialchars($row['nama_merk'] ?? '-') ?><br>
                                        <i class="fas fa-map-marker-alt text-danger"></i> <?= htmlspecialchars($row['nama_unit'] ?? '-') ?> &rarr; <?= htmlspecialchars($row['nama_ruang'] ?? '-') ?>
                                    </small>
                                </td>
                                <td>
                                    <small><strong><?= htmlspecialchars($row['nama_periode']) ?></strong> (<?= $row['tahun'] ?>)</small>
                                </td>
                                <td>
                                    <small class="font-weight-bold text-dark"><?= date('d F Y', strtotime($row['tgl_pengecekan'])) ?></small><br>
                                    <small class="text-muted"><?= date('H:i', strtotime($row['tgl_pengecekan'])) ?> WIB</small>
                                </td>
                                <td>
                                    <?php 
                                    $badge = 'success';
                                    if ($row['kondisi_temuan'] === 'Rusak') $badge = 'warning';
                                    if ($row['kondisi_temuan'] === 'Hilang') $badge = 'danger';
                                    ?>
                                    <span class="badge badge-<?= $badge ?> px-3 py-1 font-weight-bold" style="font-size:11px;"><?= $row['kondisi_temuan'] ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($row['foto_bukti']) && file_exists('../uploads/bukti/' . $row['foto_bukti'])): ?>
                                        <div class="mb-1">
                                            <a href="../uploads/bukti/<?= $row['foto_bukti'] ?>" target="_blank">
                                                <img src="../uploads/bukti/<?= $row['foto_bukti'] ?>" class="img-thumbnail" style="width:60px; height:60px; object-fit:cover;">
                                            </a>
                                        </div>
                                    <?php elseif (!empty($row['foto_bukti'])): ?>
                                        <small class="text-danger d-block mb-1"><i class="fas fa-exclamation-triangle"></i> Foto Hilang</small>
                                    <?php endif; ?>
                                    <small class="d-block text-dark font-italic">"<?= htmlspecialchars($row['catatan'] ?? '-') ?>"</small>
                                </td>
                                <td class="text-center">
                                    <?php if ($row['status_review'] === 'menunggu'): ?>
                                        <span class="badge badge-warning px-2 py-1"><i class="fas fa-hourglass-half mr-1"></i> Menunggu</span>
                                    <?php elseif ($row['status_review'] === 'disetujui'): ?>
                                        <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i> Disetujui</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger px-2 py-1"><i class="fas fa-times-circle mr-1"></i> Ditolak</span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($row['nama_reviewer'])): ?>
                                        <div class="mt-1" style="font-size: 10px; color:#4b5563;">
                                            <strong>Reviewer:</strong> <?= htmlspecialchars($row['nama_reviewer']) ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($row['catatan_reviewer'])): ?>
                                        <div class="mt-1 bg-light p-1 rounded font-italic text-xs text-left" style="border-left: 2px solid #6b7280; font-size:10.5px;">
                                            "<?= htmlspecialchars($row['catatan_reviewer']) ?>"
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php 
                                endwhile; 
                            endif;
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</section>

<script>
$(function () {
    $('#dataTableRiwayat').DataTable({
        "paging": true,
        "lengthChange": true,
        "searching": true,
        "ordering": true,
        "info": true,
        "autoWidth": false,
        "responsive": true,
        "order": [[ 0, "asc" ]],
        "language": {
            "emptyTable": "Belum ada riwayat pengecekan barang yang Anda lakukan.",
            "search": "Cari data:",
            "lengthMenu": "Tampilkan _MENU_ baris",
            "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ baris",
            "infoEmpty": "Menampilkan 0 sampai 0 dari 0 baris",
            "paginate": {
                "first": "Pertama",
                "last": "Terakhir",
                "next": "Berikutnya",
                "previous": "Sebelumnya"
            }
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
