<?php
session_start();
include '../config/database.php';

$page_title = 'Cek Barang Mode Fast 2';
$active_menu = 'scan_fast_2';

// Ambil Periode Pengecekan Aktif
$res_periode_aktif = $conn->query("SELECT * FROM periode_pengecekan WHERE status = 'aktif' ORDER BY id DESC LIMIT 1");
$periode_aktif = null;
if ($res_periode_aktif && $res_periode_aktif->num_rows > 0) {
    $periode_aktif = $res_periode_aktif->fetch_assoc();
}

// Handle AJAX Request untuk Menyimpan Status 'Baik'
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_baik') {
    header('Content-Type: application/json');
    
    if (!$periode_aktif) {
        echo json_encode(['success' => false, 'message' => 'Tidak ada periode Pengecekan aktif saat ini!']);
        exit;
    }

    $id_periode = $periode_aktif['id'];
    $id_barang = intval($_POST['id_barang']);
    $id_petugas = $_SESSION['user_id'];
    $kondisi_temuan = 'Baik'; // Auto set Baik
    $catatan = ''; // Auto kosong
    $foto_name = null; // Auto null
    $status_review = 'disetujui'; // Auto disetujui

    // Validasi barang aktif
    $res_barang = $conn->query("SELECT status_aktif, nama_barang FROM barang WHERE id = $id_barang");
    $barang_data = $res_barang->fetch_assoc();
    
    if (!$barang_data || $barang_data['status_aktif'] !== 'aktif') {
        echo json_encode(['success' => false, 'message' => 'Barang tidak ditemukan atau sudah tidak aktif!']);
        exit;
    }

    $conn->begin_transaction();
    try {
        // Periksa jika barang sudah pernah dicek di periode ini
        $stmt_check_exist = $conn->prepare("SELECT id, foto_bukti FROM pengecekan_barang WHERE id_periode = ? AND id_barang = ?");
        $stmt_check_exist->bind_param("ii", $id_periode, $id_barang);
        $stmt_check_exist->execute();
        $exist_res = $stmt_check_exist->get_result();
        $stmt_check_exist->close();

        if ($exist_res && $exist_res->num_rows > 0) {
            // Update pengecekan sebelumnya
            $old_data = $exist_res->fetch_assoc();
            $id_pengecekan = $old_data['id'];
            
            // Hapus foto lama jika ada karena mode fast tidak pakai foto
            if (!empty($old_data['foto_bukti'])) {
                @unlink("../uploads/bukti/" . $old_data['foto_bukti']);
            }

            $stmt_up = $conn->prepare("UPDATE pengecekan_barang SET id_petugas = ?, kondisi_temuan = ?, catatan = ?, foto_bukti = ?, status_review = ?, tgl_pengecekan = NOW() WHERE id = ?");
            $stmt_up->bind_param("issssi", $id_petugas, $kondisi_temuan, $catatan, $foto_name, $status_review, $id_pengecekan);
            $stmt_up->execute();
            $stmt_up->close();
        } else {
            // Insert baru
            $stmt_in = $conn->prepare("INSERT INTO pengecekan_barang (id_periode, id_barang, id_petugas, kondisi_temuan, catatan, foto_bukti, status_review) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt_in->bind_param("iiissss", $id_periode, $id_barang, $id_petugas, $kondisi_temuan, $catatan, $foto_name, $status_review);
            $stmt_in->execute();
            $stmt_in->close();
        }

        // Update kondisi barang di tabel barang langsung ke 'Baik'
        $stmt_brg = $conn->prepare("UPDATE barang SET kondisi = 'Baik' WHERE id = ?");
        $stmt_brg->bind_param("i", $id_barang);
        $stmt_brg->execute();
        $stmt_brg->close();

        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Barang "' . htmlspecialchars($barang_data['nama_barang']) . '" berhasil disimpan dengan kondisi Baik.',
            'petugas' => $_SESSION['nama_lengkap']
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()]);
    }
    exit;
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

$selected_unit = isset($_GET['unit_id']) ? intval($_GET['unit_id']) : 0;
$selected_ruang = isset($_GET['ruang_id']) ? intval($_GET['ruang_id']) : 0;

// Ambil semua unit untuk filter
$units = $conn->query("SELECT * FROM unit ORDER BY nama_unit ASC");

// Ambil ruangan berdasarkan unit terpilih
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

// Ambil daftar barang jika filter unit & ruang sudah diterapkan
$items = [];
$checked_count = 0;
$unchecked_count = 0;
$total_items = 0;

if ($periode_aktif && $selected_unit && $selected_ruang) {
    $id_periode = $periode_aktif['id'];
    $sql = "SELECT b.*, pb.id AS id_pengecekan, pb.kondisi_temuan, pb.status_review, pb.tgl_pengecekan, pb.id_petugas, ptg.nama_lengkap AS nama_petugas
            FROM barang b
            LEFT JOIN pengecekan_barang pb ON pb.id_barang = b.id AND pb.id_periode = ?
            LEFT JOIN users ptg ON pb.id_petugas = ptg.id
            WHERE b.status_aktif = 'aktif' AND b.id_unit = ? AND b.id_ruang = ?
            ORDER BY b.nama_barang ASC";
            
    $stmt_items = $conn->prepare($sql);
    if ($stmt_items) {
        $stmt_items->bind_param("iii", $id_periode, $selected_unit, $selected_ruang);
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

<!-- Animate.css untuk animasi premium -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

<style>
    .btn-flat-primary {
        background-color: #6366f1;
        color: white;
        border-radius: 8px;
        border: none;
        box-shadow: 0 4px 6px -1px rgba(99, 102, 241, 0.2);
        transition: all 0.2s;
    }
    .btn-flat-primary:hover {
        background-color: #4f46e5;
        color: white;
        transform: translateY(-1px);
    }
    .btn-fast-baik {
        background-color: #10b981;
        color: white;
        border-radius: 8px;
        border: none;
        box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.2);
        transition: all 0.2s;
        padding: 6px 16px;
        font-weight: 600;
    }
    .btn-fast-baik:hover {
        background-color: #059669;
        color: white;
        transform: scale(1.05);
    }
    .btn-fast-baik:active {
        transform: scale(0.95);
    }
    .status-badge-baik {
        background-color: #d1fae5;
        color: #065f46;
        border: 1px solid #10b981;
        border-radius: 8px;
        padding: 6px 12px;
        font-size: 11px;
        font-weight: bold;
    }
</style>

    <!-- Header Halaman -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 font-weight-bold" style="color: #1f2937;">
                        <i class="fas fa-bolt text-success mr-2"></i>Cek Barang Mode Fast 2
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Cek Fast 2</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Konten Utama -->
    <section class="content">
        <div class="container-fluid">
            
            <!-- Peringatan Periode Aktif -->
            <?php if (!$periode_aktif): ?>
                <div class="alert alert-warning" style="border-radius: 10px; border-left: 5px solid #d97706;">
                    <h5><i class="icon fas fa-exclamation-triangle"></i> Perhatian!</h5>
                    Saat ini tidak ada periode pengecekan barang yang sedang aktif. Anda tidak dapat melakukan pengecekan barang.
                </div>
            <?php else: ?>
                <div class="alert alert-info" style="border-radius: 10px; border-left: 5px solid #0ea5e9;">
                    <i class="fas fa-info-circle mr-1"></i> 
                    <strong>Periode Aktif:</strong> <?= htmlspecialchars($periode_aktif['nama_periode']) ?>
                    (Batas akhir: <?= date('d M Y', strtotime($periode_aktif['tgl_selesai'])) ?>)
                </div>

                <!-- Form Filter -->
                <div class="row">
                    <div class="col-12">
                        <div class="card card-outline card-success" style="border-radius: 15px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);">
                            <div class="card-header bg-white py-3" style="border-top-left-radius: 15px; border-top-right-radius: 15px;">
                                <h3 class="card-title font-weight-bold text-dark"><i class="fas fa-filter mr-2 text-success"></i>Filter Unit & Ruangan</h3>
                            </div>
                            <div class="card-body">
                                <form method="GET" action="scan_fast_2.php" class="row align-items-end">
                                    <div class="form-group col-md-5 mb-3 mb-md-0">
                                        <label for="unit_id" class="font-weight-bold text-muted">Unit / Unit Kerja</label>
                                        <select id="unit_id" name="unit_id" class="form-control form-control-lg" style="border-radius: 8px;" required>
                                            <option value="">-- Pilih Unit --</option>
                                            <?php while ($unit = $units->fetch_assoc()): ?>
                                                <option value="<?= $unit['id'] ?>" <?= $selected_unit === intval($unit['id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($unit['nama_unit']) ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-5 mb-3 mb-md-0">
                                        <label for="ruang_id" class="font-weight-bold text-muted">Ruangan</label>
                                        <select id="ruang_id" name="ruang_id" class="form-control form-control-lg" style="border-radius: 8px;" <?= $selected_unit ? '' : 'disabled' ?> required>
                                            <option value="">-- Pilih Ruangan --</option>
                                            <?php foreach ($rooms as $room): ?>
                                                <option value="<?= $room['id'] ?>" <?= $selected_ruang === intval($room['id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($room['nama_ruang']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-2 text-right">
                                        <button type="submit" class="btn btn-flat-primary btn-lg btn-block" style="height: calc(2.875rem + 2px);">
                                            <i class="fas fa-search mr-1"></i> Terapkan
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Info Box Summary & Daftar Barang -->
                <?php if ($selected_unit && $selected_ruang): ?>
                    <!-- Summary Widgets -->
                    <div class="row">
                        <div class="col-md-4 col-sm-6 mb-4">
                            <div class="info-box shadow-sm" style="border-radius: 12px; border-left: 5px solid #007bff;">
                                <span class="info-box-icon bg-blue" style="border-radius: 8px;"><i class="fas fa-box"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text text-muted font-weight-bold">Total Barang</span>
                                    <span class="info-box-number h3 mb-0" id="totalCount"><?= $total_items ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6 mb-4">
                            <div class="info-box shadow-sm" style="border-radius: 12px; border-left: 5px solid #28a745;">
                                <span class="info-box-icon bg-success" style="border-radius: 8px;"><i class="fas fa-check-circle"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text text-muted font-weight-bold">Sudah Dicek</span>
                                    <span class="info-box-number h3 mb-0 text-success" id="checkedCount"><?= $checked_count ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6 mb-4">
                            <div class="info-box shadow-sm" style="border-radius: 12px; border-left: 5px solid #6c757d;">
                                <span class="info-box-icon bg-secondary" style="border-radius: 8px;"><i class="fas fa-clock"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text text-muted font-weight-bold">Belum Dicek</span>
                                    <span class="info-box-number h3 mb-0 text-muted" id="uncheckedCount"><?= $unchecked_count ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabel Daftar Barang -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card" style="border-radius: 15px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); overflow: hidden;">
                                <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                                    <h3 class="card-title font-weight-bold text-dark mb-0">
                                        <i class="fas fa-table mr-2 text-success"></i>Daftar Barang di Ruangan
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table id="dataTableFast" class="table table-hover table-striped">
                                            <thead>
                                                <tr>
                                                    <th width="60" class="text-center">No</th>
                                                    <th width="120">Kode Barang</th>
                                                    <th>Nama Barang / Aset</th>
                                                    <th width="200">Status</th>
                                                    <th width="150" class="text-center">Aksi Cepat</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (!empty($items)): ?>
                                                    <?php $no = 1; foreach ($items as $row): ?>
                                                        <?php
                                                            $is_checked = !empty($row['id_pengecekan']);
                                                            $is_baik = $is_checked && ($row['kondisi_temuan'] === 'Baik');
                                                        ?>
                                                        <tr id="row-<?= $row['id'] ?>">
                                                            <td class="text-center align-middle font-weight-bold text-muted"><?= $no++ ?></td>
                                                            <td class="align-middle">
                                                                <code><?= str_pad($row['id'], 5, '0', STR_PAD_LEFT) ?></code>
                                                            </td>
                                                            <td class="align-middle">
                                                                <strong><?= htmlspecialchars($row['nama_barang']) ?></strong>
                                                            </td>
                                                             <td class="align-middle">
                                                                 <div class="status-col-inner" data-id="<?= $row['id'] ?>">
                                                                     <?php if ($is_checked): ?>
                                                                         <span class="badge badge-success px-3 py-1 font-weight-bold" style="font-size:11px;">Sudah Dicek</span>
                                                                         <?php if (!empty($row['nama_petugas'])): ?>
                                                                             <div class="mt-1" style="font-size:11px; color:#6b7280;">
                                                                                 Oleh: <strong><?= htmlspecialchars($row['nama_petugas']) ?></strong>
                                                                             </div>
                                                                         <?php endif; ?>
                                                                     <?php else: ?>
                                                                         <span class="badge badge-secondary px-3 py-1 font-weight-bold" style="font-size:11px;">Belum Dicek</span>
                                                                     <?php endif; ?>
                                                                 </div>
                                                             </td>
                                                             <td class="align-middle text-center">
                                                                 <div class="action-col-inner" data-id="<?= $row['id'] ?>">
                                                                     <?php if ($is_baik): ?>
                                                                         <span class="status-badge-baik d-inline-block mr-1">
                                                                             <i class="fas fa-check-circle mr-1"></i> Sudah Baik
                                                                         </span>
                                                                     <?php elseif ($is_checked): ?>
                                                                         <?php
                                                                             $badge_class = 'info';
                                                                             if ($row['kondisi_temuan'] === 'Rusak') $badge_class = 'warning';
                                                                             if ($row['kondisi_temuan'] === 'Hilang') $badge_class = 'danger';
                                                                         ?>
                                                                         <span class="badge badge-<?= $badge_class ?> px-3 py-1 font-weight-bold mr-2" style="font-size: 11px;">
                                                                             <?= htmlspecialchars($row['kondisi_temuan']) ?>
                                                                         </span>
                                                                     <?php else: ?>
                                                                         <button type="button" 
                                                                                 class="btn btn-fast-baik btn-sm btn-click-baik" 
                                                                                 data-id="<?= $row['id'] ?>">
                                                                             <i class="fas fa-bolt mr-1"></i> Baik
                                                                         </button>
                                                                     <?php endif; ?>
                                                                     
                                                                     <?php if ($is_checked): ?>
                                                                         <?php if (intval($row['id_petugas']) === intval($_SESSION['user_id'])): ?>
                                                                             <button type="button" 
                                                                                     class="btn btn-outline-danger btn-sm btn-click-batal ml-1" 
                                                                                     data-id="<?= $row['id'] ?>" 
                                                                                     title="Batalkan Pengecekan">
                                                                                 <i class="fas fa-undo"></i> Batal
                                                                             </button>
                                                                         <?php endif; ?>
                                                                     <?php endif; ?>
                                                                 </div>
                                                             </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Tampilan Placeholder jika filter belum diisi -->
                    <div class="row justify-content-center my-5">
                        <div class="col-md-6 text-center">
                            <div class="p-5 bg-white shadow-sm" style="border-radius: 20px;">
                                <i class="fas fa-filter text-success mb-4" style="font-size: 64px; opacity: 0.5;"></i>
                                <h4 class="font-weight-bold text-dark">Pilih Lokasi Terlebih Dahulu</h4>
                                <p class="text-muted">
                                    Silakan tentukan **Unit Kerja** dan **Ruangan** pada filter di atas, lalu klik **Terapkan** untuk menampilkan daftar aset yang siap dicek dengan cepat.
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </section>

<!-- Scripts -->
<script src="../plugins/jquery/jquery.min.js"></script>
<script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../dist/js/adminlte.min.js"></script>
<!-- DataTables -->
<script src="../plugins/datatables/jquery.dataTables.min.js"></script>
<script src="../plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="../plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<!-- SweetAlert2 -->
<script src="../plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
// Fungsi Web Audio API untuk suara beep
function playBeep() {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioCtx.createOscillator();
        const gainNode = audioCtx.createGain();

        oscillator.connect(gainNode);
        gainNode.connect(audioCtx.destination);

        oscillator.type = 'sine';
        oscillator.frequency.setValueAtTime(800, audioCtx.currentTime); // Frekuensi 800Hz
        
        // Volume ramping/envelope
        gainNode.gain.setValueAtTime(0, audioCtx.currentTime);
        gainNode.gain.linearRampToValueAtTime(0.8, audioCtx.currentTime + 0.05);
        gainNode.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.25);

        oscillator.start(audioCtx.currentTime);
        oscillator.stop(audioCtx.currentTime + 0.25);
    } catch(e) {
        console.log("AudioContext not supported", e);
    }
}

$(document).ready(function() {
    // Inisialisasi DataTables
    if ($('#dataTableFast').length) {
        $('#dataTableFast').DataTable({
            "paging": true,
            "lengthChange": true,
            "searching": true,
            "ordering": true,
            "info": true,
            "autoWidth": false,
            "responsive": true,
            "order": [[ 0, "asc" ]],
            "language": {
                "emptyTable": "Tidak ada data barang di ruangan ini.",
                "search": "Cari barang:",
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

    // Dynamic dropdown untuk ruangan
    $('#unit_id').on('change', function() {
        var unitId = $(this).val();
        var ruangSelect = $('#ruang_id');
        
        ruangSelect.prop('disabled', !unitId);
        ruangSelect.html('<option value="">-- Pilih Ruangan --</option>');
        
        if (!unitId) {
            return;
        }

        $.getJSON('get_ruang.php', { unit_id: unitId }, function(data) {
            if (Array.isArray(data)) {
                data.forEach(function(room) {
                    ruangSelect.append('<option value="' + room.id + '">' + room.nama_ruang + '</option>');
                });
            }
        });
    });

    // Handle klik tombol "Baik" via AJAX
    $(document).on('click', '.btn-click-baik', function(e) {
        e.preventDefault();
        
        var btn = $(this);
        var idBarang = btn.data('id');
        
        // Cari semua tombol yang memiliki data-id ini (baik di baris utama maupun di tampilan responsive hp/child row)
        var allBtns = $('.btn-click-baik[data-id="' + idBarang + '"]');
        allBtns.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>...');

        $.ajax({
            url: 'scan_fast_2.php',
            method: 'POST',
            data: {
                action: 'check_baik',
                id_barang: idBarang
            },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    // Putar suara beep sukses
                    playBeep();

                    // Tampilkan SweetAlert Toast sukses
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: res.message,
                        showConfirmButton: false,
                        timer: 2500,
                        timerProgressBar: true
                    });

                    // Cari kontainer berdasarkan class di dalam cell (aman untuk responsive view hp)
                    var statusColInner = $('.status-col-inner[data-id="' + idBarang + '"]');
                    var actionColInner = $('.action-col-inner[data-id="' + idBarang + '"]');

                    // Cek jika status sebelumnya "Belum Dicek"
                    var wasUnchecked = statusColInner.find('.badge-secondary').length > 0;

                    // Ganti kolom status secara menyeluruh
                    statusColInner.html(`
                        <span class="badge badge-success px-3 py-1 font-weight-bold animate__animated animate__fadeIn" style="font-size:11px;">Sudah Dicek</span>
                        <div class="mt-1 animate__animated animate__fadeIn" style="font-size:11px; color:#6b7280;">
                            Oleh: <strong>${res.petugas}</strong>
                        </div>
                    `);

                    // Ganti kolom aksi dengan badge "Sudah Baik" yang cantik dan tombol Batal
                    actionColInner.html(`
                        <span class="status-badge-baik d-inline-block animate__animated animate__zoomIn mr-1">
                            <i class="fas fa-check-circle mr-1"></i> Sudah Baik
                        </span>
                        <button type="button" 
                                class="btn btn-outline-danger btn-sm btn-click-batal ml-1 animate__animated animate__fadeIn" 
                                data-id="${idBarang}" 
                                title="Batalkan Pengecekan">
                            <i class="fas fa-undo"></i> Batal
                        </button>
                    `);

                    // Update summary counters jika barangnya sebelumnya "Belum Dicek"
                    if (wasUnchecked) {
                        var checkedEl = $('#checkedCount');
                        var uncheckedEl = $('#uncheckedCount');
                        
                        var curChecked = parseInt(checkedEl.text(), 10) || 0;
                        var curUnchecked = parseInt(uncheckedEl.text(), 10) || 0;
                        
                        checkedEl.text(curChecked + 1);
                        uncheckedEl.text(Math.max(0, curUnchecked - 1));
                    }
                } else {
                    // Aktifkan kembali semua tombol jika gagal
                    allBtns.prop('disabled', false).html('<i class="fas fa-bolt mr-1"></i> Baik');
                    Swal.fire('Gagal', res.message, 'error');
                }
            },
            error: function() {
                // Aktifkan kembali semua tombol jika error koneksi
                allBtns.prop('disabled', false).html('<i class="fas fa-bolt mr-1"></i> Baik');
                Swal.fire('Error', 'Gagal menghubungi server.', 'error');
            }
        });
    });

    // Handle klik tombol "Batal" via AJAX
    $(document).on('click', '.btn-click-batal', function(e) {
        e.preventDefault();
        
        var btn = $(this);
        var idBarang = btn.data('id');
        
        // Konfirmasi terlebih dahulu menggunakan SweetAlert2
        Swal.fire({
            title: 'Batalkan pengecekan?',
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
                // Cari semua tombol batal untuk barang ini (baik utama maupun child/responsive row)
                var allBatalBtns = $('.btn-click-batal[data-id="' + idBarang + '"]');
                allBatalBtns.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

                $.ajax({
                    url: 'scan_fast_2.php',
                    method: 'POST',
                    data: {
                        action: 'batal_check',
                        id_barang: idBarang
                    },
                    dataType: 'json',
                    success: function(res) {
                        if (res.success) {
                            // Putar suara beep/konfirmasi singkat
                            playBeep();

                            // Tampilkan toast sukses
                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'info',
                                title: res.message,
                                showConfirmButton: false,
                                timer: 2500,
                                timerProgressBar: true
                            });

                            var statusColInner = $('.status-col-inner[data-id="' + idBarang + '"]');
                            var actionColInner = $('.action-col-inner[data-id="' + idBarang + '"]');

                            // Cek jika status sebelumnya memang "Sudah Dicek"
                            var wasChecked = statusColInner.find('.badge-success').length > 0;

                            // Kembalikan ke status "Belum Dicek"
                            statusColInner.html(`
                                <span class="badge badge-secondary px-3 py-1 font-weight-bold animate__animated animate__fadeIn" style="font-size:11px;">Belum Dicek</span>
                            `);

                            // Kembalikan kolom aksi menjadi tombol "Baik"
                            actionColInner.html(`
                                <button type="button" 
                                        class="btn btn-fast-baik btn-sm btn-click-baik animate__animated animate__fadeIn" 
                                        data-id="${idBarang}">
                                    <i class="fas fa-bolt mr-1"></i> Baik
                                </button>
                            `);

                            // Update summary counters
                            if (wasChecked) {
                                var checkedEl = $('#checkedCount');
                                var uncheckedEl = $('#uncheckedCount');
                                
                                var curChecked = parseInt(checkedEl.text(), 10) || 0;
                                var curUnchecked = parseInt(uncheckedEl.text(), 10) || 0;
                                
                                checkedEl.text(Math.max(0, curChecked - 1));
                                uncheckedEl.text(curUnchecked + 1);
                            }
                        } else {
                            allBatalBtns.prop('disabled', false).html('<i class="fas fa-undo"></i> Batal');
                            Swal.fire('Gagal', res.message, 'error');
                        }
                    },
                    error: function() {
                        allBatalBtns.prop('disabled', false).html('<i class="fas fa-undo"></i> Batal');
                        Swal.fire('Error', 'Gagal menghubungi server.', 'error');
                    }
                });
            }
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>
