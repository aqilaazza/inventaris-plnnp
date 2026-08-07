<?php
session_start();
include '../config/database.php';

$page_title = 'Hasil Pengecekan';
$active_menu = 'hasil_pengecekan';

$keyword = $_GET['keyword'] ?? '';
$selected_unit = isset($_GET['unit_id']) ? intval($_GET['unit_id']) : 0;
$selected_ruang = isset($_GET['ruang_id']) ? intval($_GET['ruang_id']) : 0;
$status_filter = $_GET['status_filter'] ?? 'all';
$valid_status = ['all', 'sudah', 'belum'];
if (!in_array($status_filter, $valid_status)) {
    $status_filter = 'all';
}

$is_search = isset($_GET['keyword']) || isset($_GET['unit_id']) || isset($_GET['status_filter']);

$res_periode_aktif = $conn->query("SELECT * FROM periode_pengecekan WHERE status = 'aktif' ORDER BY id DESC LIMIT 1");
$periode_aktif = null;
if ($res_periode_aktif && $res_periode_aktif->num_rows > 0) {
    $periode_aktif = $res_periode_aktif->fetch_assoc();
    $id_periode = $periode_aktif['id'];
}

// Handle AJAX Request untuk Membatalkan Pengecekan (Reset ke Belum Dicek)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'batal_check') {
    header('Content-Type: application/json');
    
    if (!$periode_aktif) {
        echo json_encode(['success' => false, 'message' => 'Tidak ada periode pengecekan aktif saat ini!']);
        exit;
    }

    $id_periode = $periode_aktif['id'];
    $id_barang = intval($_POST['id_barang']);
    
    // Ambil detail barang
    $res_barang = $conn->query("SELECT nama_barang FROM barang WHERE id = $id_barang");
    $barang_data = $res_barang->fetch_assoc();
    if (!$barang_data) {
        echo json_encode(['success' => false, 'message' => 'Barang tidak ditemukan!']);
        exit;
    }

    $conn->begin_transaction();
    try {
        // Ambil data pengecekan untuk memvalidasi petugas dan menghapus fotonya jika ada
        $stmt_get_check = $conn->prepare("SELECT id_petugas, foto_bukti FROM pengecekan_barang WHERE id_periode = ? AND id_barang = ?");
        $stmt_get_check->bind_param("ii", $id_periode, $id_barang);
        $stmt_get_check->execute();
        $check_res = $stmt_get_check->get_result();

        if ($check_res && $check_res->num_rows > 0) {
            $check_data = $check_res->fetch_assoc();
            
            // Validasi: hanya petugas yang mengecek yang bisa membatalkan
            if (intval($check_data['id_petugas']) !== intval($_SESSION['user_id'])) {
                $stmt_get_check->close();
                echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki akses untuk membatalkan pengecekan ini!']);
                $conn->rollback();
                exit;
            }
            $stmt_get_check->close();

            if (!empty($check_data['foto_bukti'])) {
                @unlink("../uploads/bukti/" . $check_data['foto_bukti']);
            }

            // Hapus pengecekan_barang
            $stmt_del = $conn->prepare("DELETE FROM pengecekan_barang WHERE id_periode = ? AND id_barang = ?");
            $stmt_del->bind_param("ii", $id_periode, $id_barang);
            $stmt_del->execute();
            $stmt_del->close();
        } else {
            $stmt_get_check->close();
            echo json_encode(['success' => false, 'message' => 'Pengecekan barang tidak ditemukan atau sudah dibatalkan.']);
            $conn->rollback();
            exit;
        }

        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Pengecekan barang "' . htmlspecialchars($barang_data['nama_barang']) . '" berhasil dibatalkan.'
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()]);
    }
    exit;
}

$units = $conn->query("SELECT * FROM unit ORDER BY nama_unit ASC");
$rooms = [];
if ($selected_unit) {
    $stmt_ruang = $conn->prepare("SELECT id, nama_ruang FROM ruang WHERE id_unit = ? ORDER BY nama_ruang ASC");
    $stmt_ruang->bind_param("i", $selected_unit);
    $stmt_ruang->execute();
    $room_result = $stmt_ruang->get_result();
    while ($row = $room_result->fetch_assoc()) {
        $rooms[] = $row;
    }
    $stmt_ruang->close();
}

$items = [];
$checked_count = 0;
$unchecked_count = 0;
$total_items = 0;

if ($periode_aktif && $is_search) {
    $sql = "SELECT b.*, u.nama_unit, r.nama_ruang, pb.id AS id_pengecekan, pb.kondisi_temuan, pb.tgl_pengecekan, pb.id_petugas, ptg.nama_lengkap AS nama_petugas
            FROM barang b
            LEFT JOIN unit u ON b.id_unit = u.id
            LEFT JOIN ruang r ON b.id_ruang = r.id
            LEFT JOIN pengecekan_barang pb ON pb.id_barang = b.id AND pb.id_periode = ?
            LEFT JOIN users ptg ON pb.id_petugas = ptg.id
            WHERE b.status_aktif = 'aktif'";

    $params = [$id_periode];
    $types = 'i';

    if (!empty($keyword)) {
        $sql .= " AND (b.nama_barang LIKE ? OR CAST(b.id AS CHAR) LIKE ?)";
        $types .= 'ss';
        $like_keyword = '%' . $keyword . '%';
        $params[] = $like_keyword;
        $params[] = $like_keyword;
    }

    if ($selected_unit) {
        $sql .= ' AND b.id_unit = ?';
        $types .= 'i';
        $params[] = $selected_unit;
    }
    if ($selected_ruang) {
        $sql .= ' AND b.id_ruang = ?';
        $types .= 'i';
        $params[] = $selected_ruang;
    }
    if ($status_filter === 'sudah') {
        $sql .= ' AND pb.id IS NOT NULL';
    }
    if ($status_filter === 'belum') {
        $sql .= ' AND pb.id IS NULL';
    }

    $sql .= ' ORDER BY u.nama_unit, r.nama_ruang, b.nama_barang ASC';

    $stmt_items = $conn->prepare($sql);
    if ($stmt_items) {
        if (count($params) > 0) {
            $bind_params = array_merge([$types], $params);
            $tmp = [];
            foreach ($bind_params as $key => $value) {
                $tmp[$key] = &$bind_params[$key];
            }
            call_user_func_array([$stmt_items, 'bind_param'], $tmp);
        }
        $stmt_items->execute();
        $result_items = $stmt_items->get_result();
        while ($row = $result_items->fetch_assoc()) {
            $items[] = $row;
            if (!empty($row['id_pengecekan'])) {
                $checked_count++;
            } else {
                $unchecked_count++;
            }
        }
        $total_items = count($items);
        $stmt_items->close();
    }
}

include '../includes/header_petugas.php';
include '../includes/sidebar_petugas.php';
?>

<style>
    .btn-flat-primary {
        background-image: none !important;
        background-color: #007bff !important;
        border-color: #007bff !important;
        box-shadow: none !important;
        color: #fff !important;
    }
    .btn-flat-primary:hover,
    .btn-flat-primary:focus {
        background-color: #0069d9 !important;
        border-color: #0062cc !important;
        box-shadow: none !important;
    }
</style>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>Hasil Pengecekan</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Hasil Pengecekan</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-white py-3">
                        <h3 class="card-title font-weight-bold"><i class="fas fa-list-alt mr-2 text-indigo"></i>Filter Hasil Pengecekan</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!$periode_aktif): ?>
                            <div class="alert alert-warning" role="alert">
                                Tidak ada periode pengecekan aktif saat ini. Hasil pengecekan hanya dapat ditampilkan ketika ada periode yang aktif.
                            </div>
                        <?php endif; ?>

                        <form method="get" class="row align-items-end">
                            <div class="form-group col-md-3">
                                <label for="keyword">Pencarian</label>
                                <input type="text" id="keyword" name="keyword" class="form-control" value="<?= htmlspecialchars($keyword) ?>" placeholder="Nama/ID...">
                            </div>
                            <div class="form-group col-md-3">
                                <label for="unit_id">Unit</label>
                                <select id="unit_id" name="unit_id" class="form-control">
                                    <option value="">Semua Unit</option>
                                    <?php while ($unit = $units->fetch_assoc()): ?>
                                        <option value="<?= $unit['id'] ?>" <?= $selected_unit === intval($unit['id']) ? 'selected' : '' ?>><?= htmlspecialchars($unit['nama_unit']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="ruang_id">Ruangan</label>
                                <select id="ruang_id" name="ruang_id" class="form-control" <?= $selected_unit ? '' : 'disabled' ?>>
                                    <option value="">Semua Ruangan</option>
                                    <?php foreach ($rooms as $room): ?>
                                        <option value="<?= $room['id'] ?>" <?= $selected_ruang === intval($room['id']) ? 'selected' : '' ?>><?= htmlspecialchars($room['nama_ruang']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group col-md-2">
                                <label for="status_filter">Status</label>
                                <select id="status_filter" name="status_filter" class="form-control">
                                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>Semua</option>
                                    <option value="sudah" <?= $status_filter === 'sudah' ? 'selected' : '' ?>>Sudah</option>
                                    <option value="belum" <?= $status_filter === 'belum' ? 'selected' : '' ?>>Belum</option>
                                </select>
                            </div>
                            <div class="form-group col-md-1 text-right">
                                <button type="submit" class="btn btn-flat-primary btn-block" style="padding-left:0; padding-right:0;"><i class="fas fa-search"></i></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($periode_aktif): ?>
        <div class="row">
            <div class="col-md-4 col-sm-6 mb-3">
                <div class="info-box">
                    <span class="info-box-icon bg-indigo"><i class="fas fa-calendar-day"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Periode Aktif</span>
                        <span class="info-box-number"><?= htmlspecialchars($periode_aktif['nama_periode']) ?> (<?= $periode_aktif['tahun'] ?>)</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-sm-6 mb-3">
                <div class="info-box">
                    <span class="info-box-icon bg-success"><i class="fas fa-check"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Sudah Dicek</span>
                        <span class="info-box-number" id="checkedCount"><?= $checked_count ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-sm-6 mb-3">
                <div class="info-box">
                    <span class="info-box-icon bg-secondary"><i class="fas fa-clock"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Belum Dicek</span>
                        <span class="info-box-number" id="uncheckedCount"><?= $unchecked_count ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-white py-3">
                        <h3 class="card-title font-weight-bold"><i class="fas fa-table mr-2 text-indigo"></i>Daftar Barang</h3>
                        <div class="card-tools">
                            <span class="text-muted">Total: <?= $total_items ?> barang</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!$is_search): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Silakan gunakan form pencarian di atas</h5>
                                <p class="text-muted">Data barang akan muncul setelah Anda melakukan pencarian atau memilih filter.</p>
                            </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table id="dataTableHasil" class="table table-hover table-striped">
                                <thead>
                                    <tr>
                                        <th width="50">No</th>
                                        <th>Barang / Aset</th>
                                        <th>Lokasi</th>
                                        <th width="150">Status</th>
                                        <th width="130">Kondisi Temuan</th>
                                        <th width="160">Tanggal Cek</th>
                                        <th width="120" class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($items)): ?>
                                        <?php $no = 1; foreach ($items as $row): ?>
                                            <?php
                                                $status_label = !empty($row['id_pengecekan']) ? 'Sudah Dicek' : 'Belum Dicek';
                                                $status_class = !empty($row['id_pengecekan']) ? 'success' : 'secondary';
                                                $badge_text = !empty($row['kondisi_temuan']) ? $row['kondisi_temuan'] : '-';
                                                $badge_class = 'info';
                                                if ($row['kondisi_temuan'] === 'Rusak') $badge_class = 'warning';
                                                if ($row['kondisi_temuan'] === 'Hilang') $badge_class = 'danger';
                                                if ($row['kondisi_temuan'] === 'Baik') $badge_class = 'success';
                                            ?>
                                            <tr id="row-<?= $row['id'] ?>">
                                                <td><?= $no++ ?></td>
                                                <td>
                                                    <code><?= str_pad($row['id'], 5, '0', STR_PAD_LEFT) ?></code><br>
                                                    <strong><?= htmlspecialchars($row['nama_barang']) ?></strong>
                                                </td>
                                                <td>
                                                    <i class="fas fa-map-marker-alt text-danger"></i>
                                                    <?= htmlspecialchars($row['nama_unit'] ?? '-') ?> &rarr; <?= htmlspecialchars($row['nama_ruang'] ?? '-') ?>
                                                </td>
                                                <td>
                                                    <div class="status-col-inner" data-id="<?= $row['id'] ?>">
                                                        <span class="badge badge-<?= $status_class ?> px-3 py-1 font-weight-bold" style="font-size:11px;"><?= $status_label ?></span>
                                                        <?php if (!empty($row['id_pengecekan']) && !empty($row['nama_petugas'])): ?>
                                                            <div class="mt-1" style="font-size:11px; color:#6b7280;">
                                                                Oleh: <strong><?= htmlspecialchars($row['nama_petugas']) ?></strong>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="kondisi-col-inner" data-id="<?= $row['id'] ?>">
                                                        <?php if (!empty($row['id_pengecekan'])): ?>
                                                            <span class="badge badge-<?= $badge_class ?> px-3 py-1 font-weight-bold" style="font-size:11px;"><?= htmlspecialchars($badge_text) ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">Belum dicek</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="tgl-col-inner" data-id="<?= $row['id'] ?>">
                                                        <?php if (!empty($row['id_pengecekan'])): ?>
                                                            <?= date('d-m-Y H:i', strtotime($row['tgl_pengecekan'])) ?>
                                                        <?php else: ?>
                                                            -
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <div class="action-col-inner" data-id="<?= $row['id'] ?>">
                                                        <?php if (!empty($row['id_pengecekan'])): ?>
                                                            <?php if (intval($row['id_petugas']) === intval($_SESSION['user_id'])): ?>
                                                                <button type="button" 
                                                                        class="btn btn-outline-danger btn-sm btn-click-batal" 
                                                                        data-id="<?= $row['id'] ?>" 
                                                                        title="Batalkan Pengecekan">
                                                                    <i class="fas fa-undo mr-1"></i> Batal Cek
                                                                </button>
                                                            <?php else: ?>
                                                                <span class="text-muted" title="Hanya petugas pengecek yang dapat membatalkan"><i class="fas fa-lock text-secondary mr-1"></i> -</span>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </div>
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
        <?php endif; ?>
    </div>
</section>

<!-- SweetAlert2 -->
<script src="../plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
$(function () {
    if ($('#dataTableHasil').length) {
        $('#dataTableHasil').DataTable({
            "paging": true,
            "lengthChange": true,
            "searching": true,
            "ordering": true,
            "info": true,
            "autoWidth": false,
            "responsive": true,
            "order": [[ 0, "asc" ]],
            "language": {
                "emptyTable": "Tidak ada data barang yang sesuai filter.",
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
    }

    $('#unit_id').on('change', function () {
        var unitId = $(this).val();
        var ruangSelect = $('#ruang_id');
        ruangSelect.prop('disabled', !unitId);
        ruangSelect.html('<option value="">Semua Ruangan</option>');

        if (!unitId) {
            return;
        }

        $.getJSON('get_ruang.php', { unit_id: unitId }, function (data) {
            if (Array.isArray(data)) {
                data.forEach(function (room) {
                    ruangSelect.append('<option value="' + room.id + '">' + room.nama_ruang + '</option>');
                });
            }
        });
    });

    // Handle klik tombol "Batal Cek" via AJAX
    $(document).on('click', '.btn-click-batal', function (e) {
        e.preventDefault();
        
        var btn = $(this);
        var idBarang = btn.data('id');
        
        Swal.fire({
            title: 'Batalkan Pengecekan?',
            text: "Status pengecekan barang ini akan dihapus & kembali menjadi 'Belum Dicek'.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Batalkan!',
            cancelButtonText: 'Kembali',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                // Disable tombol dan tampilkan spinner
                var allBatalBtns = $('.btn-click-batal[data-id="' + idBarang + '"]');
                allBatalBtns.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>...');

                $.ajax({
                    url: 'hasil_pengecekan.php',
                    method: 'POST',
                    data: {
                        action: 'batal_check',
                        id_barang: idBarang
                    },
                    dataType: 'json',
                    success: function (res) {
                        if (res.success) {
                            // Putar suara beep konfirmasi (Web Audio API)
                            try {
                                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                                const oscillator = audioCtx.createOscillator();
                                const gainNode = audioCtx.createGain();
                                oscillator.connect(gainNode);
                                gainNode.connect(audioCtx.destination);
                                oscillator.type = 'sine';
                                oscillator.frequency.setValueAtTime(600, audioCtx.currentTime);
                                gainNode.gain.setValueAtTime(0, audioCtx.currentTime);
                                gainNode.gain.linearRampToValueAtTime(0.5, audioCtx.currentTime + 0.05);
                                gainNode.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.2);
                                oscillator.start(audioCtx.currentTime);
                                oscillator.stop(audioCtx.currentTime + 0.2);
                            } catch (e) {
                                console.log("AudioContext not supported", e);
                            }

                            // Tampilkan toast SweetAlert2 sukses
                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'info',
                                title: res.message,
                                showConfirmButton: false,
                                timer: 2500,
                                timerProgressBar: true
                            });

                            // Sinkronisasi kolom untuk barang yang dibatalkan
                            var statusColInner = $('.status-col-inner[data-id="' + idBarang + '"]');
                            var kondisiColInner = $('.kondisi-col-inner[data-id="' + idBarang + '"]');
                            var tglColInner = $('.tgl-col-inner[data-id="' + idBarang + '"]');
                            var actionColInner = $('.action-col-inner[data-id="' + idBarang + '"]');

                            var wasChecked = statusColInner.find('.badge-success').length > 0;

                            // Update kolom
                            statusColInner.html('<span class="badge badge-secondary px-3 py-1 font-weight-bold" style="font-size:11px;">Belum Dicek</span>');
                            kondisiColInner.html('<span class="text-muted">Belum dicek</span>');
                            tglColInner.html('-');
                            actionColInner.html('<span class="text-muted">-</span>');

                            // Update widget ringkasan
                            if (wasChecked) {
                                var checkedEl = $('#checkedCount');
                                var uncheckedEl = $('#uncheckedCount');
                                
                                var curChecked = parseInt(checkedEl.text(), 10) || 0;
                                var curUnchecked = parseInt(uncheckedEl.text(), 10) || 0;
                                
                                checkedEl.text(Math.max(0, curChecked - 1));
                                uncheckedEl.text(curUnchecked + 1);
                            }
                        } else {
                            allBatalBtns.prop('disabled', false).html('<i class="fas fa-undo mr-1"></i> Batal Cek');
                            Swal.fire('Gagal', res.message, 'error');
                        }
                    },
                    error: function () {
                        allBatalBtns.prop('disabled', false).html('<i class="fas fa-undo mr-1"></i> Batal Cek');
                        Swal.fire('Error', 'Gagal menghubungi server.', 'error');
                    }
                });
            }
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>
