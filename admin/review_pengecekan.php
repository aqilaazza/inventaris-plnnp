<?php
session_start();
include '../config/database.php';

$page_title = 'Review Hasil Pengecekan';
$active_menu = 'review_pengecekan';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = intval($_POST['id'] ?? 0);
    $catatan_reviewer = trim($_POST['catatan_reviewer'] ?? '');
    $admin_id = $_SESSION['user_id'];

    if ($id > 0 && ($action === 'approve' || $action === 'reject')) {
        // Ambil detail pengecekan first
        $stmt_get = $conn->prepare("SELECT id_barang, kondisi_temuan FROM pengecekan_barang WHERE id = ?");
        $stmt_get->bind_param("i", $id);
        $stmt_get->execute();
        $detail = $stmt_get->get_result()->fetch_assoc();
        $stmt_get->close();

        if ($detail) {
            $id_barang = $detail['id_barang'];
            $kondisi_temuan = $detail['kondisi_temuan'];

            $conn->begin_transaction();

            try {
                if ($action === 'approve') {
                    // Update status_review ke 'disetujui'
                    $stmt_up = $conn->prepare("UPDATE pengecekan_barang SET status_review = 'disetujui', id_reviewer = ?, catatan_reviewer = ?, tgl_review = NOW() WHERE id = ?");
                    $stmt_up->bind_param("isi", $admin_id, $catatan_reviewer, $id);
                    $stmt_up->execute();
                    $stmt_up->close();

                    // Update kondisi di tabel barang
                    $stmt_brg = $conn->prepare("UPDATE barang SET kondisi = ? WHERE id = ?");
                    $stmt_brg->bind_param("si", $kondisi_temuan, $id_barang);
                    $stmt_brg->execute();
                    $stmt_brg->close();

                    $_SESSION['success'] = 'Hasil pengecekan berhasil disetujui dan kondisi barang telah diperbarui!';
                } else {
                    // Update status_review ke 'ditolak'
                    $stmt_up = $conn->prepare("UPDATE pengecekan_barang SET status_review = 'ditolak', id_reviewer = ?, catatan_reviewer = ?, tgl_review = NOW() WHERE id = ?");
                    $stmt_up->bind_param("isi", $admin_id, $catatan_reviewer, $id);
                    $stmt_up->execute();
                    $stmt_up->close();

                    $_SESSION['success'] = 'Hasil pengecekan telah ditolak. Kondisi barang tidak berubah.';
                }

                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['error'] = 'Terjadi kesalahan saat memproses data: ' . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = 'Data pengecekan tidak ditemukan!';
        }
        header('Location: review_pengecekan.php');
        exit;
    }
}

// Filters
$filter_periode = isset($_GET['periode_id']) ? intval($_GET['periode_id']) : 0;
$filter_status  = isset($_GET['status_review']) ? $_GET['status_review'] : 'menunggu';

$where_clauses = [];
if ($filter_periode > 0) {
    $where_clauses[] = "pb.id_periode = $filter_periode";
}
if ($filter_status !== 'semua') {
    $where_clauses[] = "pb.status_review = '$filter_status'";
} else {
    // Tampilkan yang Baik langsung disetujui OR yang rusak/hilang sudah disetujui/ditolak/menunggu
    // Sebenarnya kalau 'semua', biarkan saja tanpa clause status
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

// Fetch checking list
$query = "SELECT pb.*, b.nama_barang, b.foto as foto_barang, m.nama_merk, k.nama_kategori, 
                 u.nama_unit, r.nama_ruang, p.nama_lengkap as nama_petugas, 
                 pe.nama_periode, pe.tahun,
                 rv.nama_lengkap as nama_reviewer
          FROM pengecekan_barang pb
          JOIN barang b ON pb.id_barang = b.id
          LEFT JOIN merk m ON b.id_merk = m.id
          LEFT JOIN kategori k ON b.id_kategori = k.id
          LEFT JOIN unit u ON b.id_unit = u.id
          LEFT JOIN ruang r ON b.id_ruang = r.id
          JOIN users p ON pb.id_petugas = p.id
          JOIN periode_pengecekan pe ON pb.id_periode = pe.id
          LEFT JOIN users rv ON pb.id_reviewer = rv.id
          $where_sql
          ORDER BY pb.tgl_pengecekan DESC";
$result = $conn->query($query);

// Fetch periods for dropdown
$periods = $conn->query("SELECT * FROM periode_pengecekan ORDER BY id DESC");

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>Persetujuan Hasil Pengecekan</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Review Pengecekan</li>
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

        <!-- Filter Card -->
        <div class="card mb-3">
            <div class="card-body" style="padding: 15px 20px;">
                <form method="GET" action="review_pengecekan.php" class="row align-items-end">
                    <div class="col-md-4">
                        <div class="form-group mb-0">
                            <label style="font-size:12px; font-weight:700;">Filter Periode</label>
                            <select name="periode_id" class="form-control form-control-sm select2">
                                <option value="0">-- Semua Sesi Periode --</option>
                                <?php while($pe = $periods->fetch_assoc()): ?>
                                    <option value="<?= $pe['id'] ?>" <?= $filter_periode == $pe['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($pe['nama_periode']) ?> (<?= $pe['tahun'] ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-0">
                            <label style="font-size:12px; font-weight:700;">Status Review</label>
                            <select name="status_review" class="form-control form-control-sm">
                                <option value="menunggu" <?= $filter_status === 'menunggu' ? 'selected' : '' ?>>Menunggu Review ⏳</option>
                                <option value="disetujui" <?= $filter_status === 'disetujui' ? 'selected' : '' ?>>Disetujui ✅</option>
                                <option value="ditolak" <?= $filter_status === 'ditolak' ? 'selected' : '' ?>>Ditolak ❌</option>
                                <option value="semua" <?= $filter_status === 'semua' ? 'selected' : '' ?>>Semua</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-filter mr-1"></i> Saring Data
                        </button>
                        <a href="review_pengecekan.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-sync mr-1"></i> Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Table Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-shield-alt mr-2 text-warning"></i>Daftar Laporan Temuan Barang Pengecekan</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="dataTableReview" class="table table-hover table-striped">
                        <thead>
                            <tr>
                                <th width="50">No</th>
                                <th>Barang</th>
                                <th width="150">Periode Sensus</th>
                                <th width="120">Petugas</th>
                                <th width="120">Kondisi Dilaporkan</th>
                                <th>Bukti Foto / Catatan</th>
                                <th width="100" class="text-center">Status</th>
                                <th width="150" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; while ($row = $result->fetch_assoc()): 
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
                                    <small><strong><?= htmlspecialchars($row['nama_petugas']) ?></strong><br>
                                    <span class="text-muted text-xs"><?= date('d/m/Y H:i', strtotime($row['tgl_pengecekan'])) ?></span></small>
                                </td>
                                <td>
                                    <?php 
                                    $badge = 'success';
                                    if ($row['kondisi_temuan'] === 'Rusak') $badge = 'warning';
                                    if ($row['kondisi_temuan'] === 'Hilang') $badge = 'danger';
                                    ?>
                                    <span class="badge badge-<?= $badge ?> px-3 py-1 font-weight-bold" style="font-size:12px;"><?= $row['kondisi_temuan'] ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($row['foto_bukti']) && file_exists('../uploads/bukti/' . $row['foto_bukti'])): ?>
                                        <a href="#" class="view-bukti" data-src="../uploads/bukti/<?= $row['foto_bukti'] ?>">
                                            <img src="../uploads/bukti/<?= $row['foto_bukti'] ?>" class="img-thumbnail" style="width:70px; height:70px; object-fit:cover; margin-bottom:5px;">
                                        </a>
                                    <?php elseif (!empty($row['foto_bukti'])): ?>
                                        <small class="text-danger"><i class="fas fa-exclamation-triangle"></i> Foto Hilang</small><br>
                                    <?php else: ?>
                                        <small class="text-muted"><i class="fas fa-image"></i> Tanpa Foto Bukti</small><br>
                                    <?php endif; ?>
                                    <small class="d-block text-dark font-italic">"<?= htmlspecialchars($row['catatan'] ?? '-') ?>"</small>
                                </td>
                                <td class="text-center">
                                    <?php if ($row['status_review'] === 'menunggu'): ?>
                                        <span class="badge badge-warning px-2 py-1"><i class="fas fa-hourglass-half mr-1"></i> Menunggu</span>
                                    <?php elseif ($row['status_review'] === 'disetujui'): ?>
                                        <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i> Disetujui</span>
                                        <?php if (!empty($row['nama_reviewer'])): ?>
                                            <br><small class="text-muted text-xs" style="white-space:nowrap;">Oleh: <?= htmlspecialchars($row['nama_reviewer']) ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge badge-danger px-2 py-1"><i class="fas fa-times-circle mr-1"></i> Ditolak</span>
                                        <?php if (!empty($row['nama_reviewer'])): ?>
                                            <br><small class="text-muted text-xs" style="white-space:nowrap;">Oleh: <?= htmlspecialchars($row['nama_reviewer']) ?></small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($row['status_review'] === 'menunggu'): ?>
                                        <div class="btn-group-vertical">
                                            <button class="btn btn-success btn-xs mb-1" onclick="processReview(<?= $row['id'] ?>, 'approve', '<?= htmlspecialchars(addslashes($row['nama_barang'])) ?>', '<?= $row['kondisi_temuan'] ?>')">
                                                <i class="fas fa-check mr-1"></i> Setujui
                                            </button>
                                            <button class="btn btn-danger btn-xs mb-1" onclick="processReview(<?= $row['id'] ?>, 'reject', '<?= htmlspecialchars(addslashes($row['nama_barang'])) ?>', '<?= $row['kondisi_temuan'] ?>')">
                                                <i class="fas fa-times mr-1"></i> Tolak
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <small class="text-muted">Reviewed</small>
                                    <?php endif; ?>
                                    
                                    <?php if (in_array($row['kondisi_temuan'], ['Rusak', 'Hilang']) && $row['status_review'] === 'disetujui'): ?>
                                        <div class="mt-1">
                                            <a href="penghapusan_barang.php?barang_id=<?= $row['id_barang'] ?>&alasan=<?= $row['kondisi_temuan'] ?>&ket=Dari+review+pengecekan+berkala" class="btn btn-danger btn-xs btn-block">
                                                <i class="fas fa-trash-alt mr-1"></i> Proses Penghapusan
                                            </a>
                                        </div>
                                    <?php endif; ?>
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

<!-- Modal Preview Bukti Foto -->
<div class="modal fade" id="buktiModal" tabindex="-1" role="dialog" aria-labelledby="buktiModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="buktiModalLabel">Preview Bukti Foto</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center">
                <img id="buktiModalImg" src="" alt="Bukti Foto" style="max-width:100%; height:auto; border-radius:6px;" />
            </div>
        </div>
    </div>
</div>

<!-- Form tersembunyi untuk review -->
<form id="formReview" method="POST" action="review_pengecekan.php" style="display:none;">
    <input type="hidden" name="action" id="review_action">
    <input type="hidden" name="id" id="review_id">
    <input type="hidden" name="catatan_reviewer" id="review_catatan">
</form>

<script>
$(function () {
    $('#dataTableReview').DataTable({
        "paging": true,
        "lengthChange": true,
        "searching": true,
        "ordering": true,
        "info": true,
        "autoWidth": false,
        "responsive": true,
        "order": [[ 0, "asc" ]]
    });
});

function processReview(id, action, nama, kondisi) {
    const isApprove = action === 'approve';
    const actionText = isApprove ? 'MENYETUJUI' : 'MENOLAK';
    const actionColor = isApprove ? '#10b981' : '#ef4444';
    
    let promptText = `Anda akan ${actionText} laporan bahwa barang "${nama}" dalam kondisi ${kondisi}.`;
    if (isApprove) {
        promptText += `\nKondisi barang ini di master data otomatis berubah menjadi "${kondisi}".`;
    }

    Swal.fire({
        title: `${isApprove ? 'Setujui' : 'Tolak'} Laporan?`,
        text: promptText,
        icon: isApprove ? 'question' : 'warning',
        input: 'textarea',
        inputPlaceholder: 'Tuliskan catatan peninjauan Anda di sini (opsional)...',
        showCancelButton: true,
        confirmButtonColor: actionColor,
        cancelButtonColor: '#6c757d',
        confirmButtonText: isApprove ? 'Ya, Setujui!' : 'Ya, Tolak!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('review_action').value = action;
            document.getElementById('review_id').value = id;
            document.getElementById('review_catatan').value = result.value || '';
            document.getElementById('formReview').submit();
        }
    });
}

// Preview bukti foto di modal
$(document).on('click', '.view-bukti', function(e) {
    e.preventDefault();
    var src = $(this).data('src');
    if (src) {
        $('#buktiModalImg').attr('src', src);
        $('#buktiModal').modal('show');
    }
});
</script>

<?php include '../includes/footer.php'; ?>
