<?php
session_start();
include '../config/database.php';

$page_title = 'Data Barang';
$active_menu = 'barang';

// ===== HANDLE ACTIONS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $nama_barang = trim($_POST['nama_barang']);
        $jumlah      = !empty($_POST['jumlah']) ? intval($_POST['jumlah']) : 1;
        $id_unit     = !empty($_POST['id_unit']) ? intval($_POST['id_unit']) : null;
        $id_ruang    = !empty($_POST['id_ruang']) ? intval($_POST['id_ruang']) : null;
        $kondisi     = $_POST['kondisi'] ?? 'Baik';
        $tgl_pembelian = !empty($_POST['tgl_pembelian']) ? $_POST['tgl_pembelian'] : null;

        // --- Helper function for dynamic ID/Creation ---
        function getOrInsert($conn, $table, $column, $value) {
            if ($value === null) return null;
            $val = trim(strval($value));
            if ($val === '' || strcasecmp($val, 'null') === 0 || $val === '-' || strcasecmp($val, 'tidak ada') === 0 || strcasecmp($val, 'tanpa merk') === 0 || strcasecmp($val, 'none') === 0) {
                return null;
            }
            if (is_numeric($val)) return intval($val);
            
            $stmt = $conn->prepare("SELECT id FROM $table WHERE $column = ?");
            $stmt->bind_param("s", $val);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $stmt->close();
                return $row['id'];
            } else {
                $stmt->close();
                $stmt_in = $conn->prepare("INSERT INTO $table ($column) VALUES (?)");
                $stmt_in->bind_param("s", $val);
                $stmt_in->execute();
                $inserted_id = $stmt_in->insert_id;
                $stmt_in->close();
                return $inserted_id;
            }
        }

        $id_merk     = getOrInsert($conn, 'merk', 'nama_merk', $_POST['id_merk'] ?? null);
        $id_jenis    = getOrInsert($conn, 'jenis', 'nama_jenis', $_POST['id_jenis'] ?? null);
        $id_kategori = getOrInsert($conn, 'kategori', 'nama_kategori', $_POST['id_kategori'] ?? null);
        
        // Handle Image Upload
        $foto_name = $_POST['old_foto'] ?? null;
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {
            $target_dir = "../dist/img/barang/";
            $file_ext = pathinfo($_FILES["foto"]["name"], PATHINFO_EXTENSION);
            $foto_name = "BRG_" . time() . "." . $file_ext;
            $target_file = $target_dir . $foto_name;
            
            if (move_uploaded_file($_FILES["foto"]["tmp_name"], $target_file)) {
                // Delete old file if exists and updating
                if (!empty($_POST['old_foto']) && file_exists($target_dir . $_POST['old_foto'])) {
                    unlink($target_dir . $_POST['old_foto']);
                }
            }
        }

        if ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO barang (nama_barang, id_merk, jumlah, id_jenis, id_kategori, id_unit, id_ruang, kondisi, tgl_pembelian, foto) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("siiiiissss", $nama_barang, $id_merk, $jumlah, $id_jenis, $id_kategori, $id_unit, $id_ruang, $kondisi, $tgl_pembelian, $foto_name);
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Data barang berhasil ditambahkan!';
            } else {
                $_SESSION['error'] = 'Gagal menambahkan data barang: ' . $conn->error;
            }
            $stmt->close();
        } else {
            $id = intval($_POST['id']);
            $stmt = $conn->prepare("UPDATE barang SET nama_barang = ?, id_merk = ?, jumlah = ?, id_jenis = ?, id_kategori = ?, id_unit = ?, id_ruang = ?, kondisi = ?, tgl_pembelian = ?, foto = ? WHERE id = ?");
            $stmt->bind_param("siiiiissssi", $nama_barang, $id_merk, $jumlah, $id_jenis, $id_kategori, $id_unit, $id_ruang, $kondisi, $tgl_pembelian, $foto_name, $id);
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Data barang berhasil diperbarui!';
            } else {
                $_SESSION['error'] = 'Gagal memperbarui data barang!';
            }
            $stmt->close();
        }
        header('Location: barang.php');
        exit;
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Get foto to delete file
    $res = $conn->query("SELECT foto FROM barang WHERE id = $id");
    $row = $res->fetch_assoc();
    if (!empty($row['foto'])) {
        @unlink("../dist/img/barang/" . $row['foto']);
    }

    $stmt = $conn->prepare("DELETE FROM barang WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Data barang berhasil dihapus!';
    } else {
        $_SESSION['error'] = 'Gagal menghapus data barang!';
    }
    $stmt->close();
    header('Location: barang.php');
    exit;
}

// ===== GET DATA =====
$status_aktif = isset($_GET['status_aktif']) ? $_GET['status_aktif'] : 'aktif';
$where_clauses = [];
if ($status_aktif === 'aktif' || $status_aktif === 'nonaktif') {
    $where_clauses[] = "b.status_aktif = '$status_aktif'";
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

$query = "SELECT b.*, m.nama_merk, j.nama_jenis, k.nama_kategori, u.nama_unit, r.nama_ruang 
          FROM barang b 
          LEFT JOIN merk m ON b.id_merk = m.id 
          LEFT JOIN jenis j ON b.id_jenis = j.id 
          LEFT JOIN kategori k ON b.id_kategori = k.id 
          LEFT JOIN unit u ON b.id_unit = u.id 
          LEFT JOIN ruang r ON b.id_ruang = r.id 
          $where_sql
          ORDER BY b.id DESC";
$data = $conn->query($query);

// Lists for dropdowns
$merk_arr = [];
$merk_res = $conn->query("SELECT * FROM merk ORDER BY nama_merk ASC");
if ($merk_res) { while($r = $merk_res->fetch_assoc()) $merk_arr[] = $r; }

$jenis_arr = [];
$jenis_res = $conn->query("SELECT * FROM jenis ORDER BY nama_jenis ASC");
if ($jenis_res) { while($r = $jenis_res->fetch_assoc()) $jenis_arr[] = $r; }

$kategori_arr = [];
$kategori_res = $conn->query("SELECT * FROM kategori ORDER BY nama_kategori ASC");
if ($kategori_res) { while($r = $kategori_res->fetch_assoc()) $kategori_arr[] = $r; }

$unit_arr = [];
$unit_res = $conn->query("SELECT * FROM unit ORDER BY nama_unit ASC");
if ($unit_res) { while($r = $unit_res->fetch_assoc()) $unit_arr[] = $r; }

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>Data Barang</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Barang</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h3 class="card-title"><i class="fas fa-box mr-2" style="color:#6366f1;"></i>Daftar Barang</h3>
                <div>
                    <button class="btn btn-success btn-sm mr-1" data-toggle="modal" data-target="#modalImport">
                        <i class="fas fa-file-import mr-1"></i> Import Excel
                    </button>
                    <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalTambah">
                        <i class="fas fa-plus mr-1"></i> Tambah Barang
                    </button>
                </div>
            </div>
            <div class="card-body">
                <!-- Tab Status Aktif/Nonaktif -->
                <ul class="nav nav-pills mb-3">
                    <li class="nav-item">
                        <a class="nav-link <?= $status_aktif === 'aktif' ? 'active bg-primary' : '' ?>" href="barang.php?status_aktif=aktif" style="font-size:12px; font-weight:600; border-radius:8px; margin-right:6px;">
                            <i class="fas fa-check-circle mr-1"></i> Aktif
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $status_aktif === 'nonaktif' ? 'active bg-secondary' : '' ?>" href="barang.php?status_aktif=nonaktif" style="font-size:12px; font-weight:600; border-radius:8px; margin-right:6px;">
                            <i class="fas fa-minus-circle mr-1"></i> Nonaktif / Dikeluarkan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $status_aktif === 'semua' ? 'active bg-dark' : '' ?>" href="barang.php?status_aktif=semua" style="font-size:12px; font-weight:600; border-radius:8px;">
                            <i class="fas fa-list mr-1"></i> Semua
                        </a>
                    </li>
                </ul>

                <div class="table-responsive">
                    <table id="dataTable" class="table table-hover table-striped">
                        <thead>
                            <tr>
                                <th width="50">No</th>
                                <th>Foto</th>
                                <th>Nama Barang</th>
                                <th>Merk</th>
                                <th width="100">Tgl Beli</th>
                                <th width="60">Jml</th>
                                <th>Kondisi</th>
                                <th>Status</th>
                                <th>Lokasi</th>
                                <th width="150" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; while ($row = $data->fetch_assoc()): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td>
                                    <?php if (!empty($row['foto'])): ?>
                                        <img src="../dist/img/barang/<?= $row['foto'] ?>" class="img-thumbnail" style="width:50px; height:50px; object-fit:cover;">
                                    <?php else: ?>
                                        <div class="bg-light d-flex align-items-center justify-content-center" style="width:50px; height:50px; border-radius:5px;">
                                            <i class="fas fa-image text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($row['nama_barang']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($row['nama_kategori']) ?> | <?= htmlspecialchars($row['nama_jenis']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($row['nama_merk'] ?? '-') ?></td>
                                <td>
                                    <span class="text-muted" style="font-size: 11px;">
                                        <?= !empty($row['tgl_pembelian']) ? date('d/m/Y', strtotime($row['tgl_pembelian'])) : '-' ?>
                                    </span>
                                </td>
                                <td><?= $row['jumlah'] ?></td>
                                <td>
                                    <?php 
                                    $badge = 'success';
                                    if ($row['kondisi'] == 'Rusak') $badge = 'warning';
                                    if ($row['kondisi'] == 'Hilang') $badge = 'danger';
                                    ?>
                                    <span class="badge badge-<?= $badge ?>"><?= $row['kondisi'] ?></span>
                                </td>
                                <td>
                                    <?php if ($row['status_aktif'] == 'aktif'): ?>
                                        <span class="badge badge-success"><i class="fas fa-check mr-1"></i> Aktif</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary"><i class="fas fa-minus-circle mr-1"></i> Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small>
                                        <i class="fas fa-building mr-1"></i> <?= htmlspecialchars($row['nama_unit'] ?? '-') ?><br>
                                        <i class="fas fa-door-open mr-1"></i> <?= htmlspecialchars($row['nama_ruang'] ?? '-') ?>
                                    </small>
                                </td>
                                <td class="text-center">
                                    <a href="barang_print_qr.php?id=<?= $row['id'] ?>" target="_blank" class="btn btn-info btn-sm">
                                        <i class="fas fa-qrcode"></i>
                                    </a>
                                    <button class="btn btn-warning btn-sm" 
                                        onclick="editData(<?= htmlspecialchars(json_encode($row)) ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm" 
                                        onclick="confirmDelete('barang.php?delete=<?= $row['id'] ?>')">
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

<!-- Modal Import -->
<div class="modal fade" id="modalImport">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="barang_import.php" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-file-import mr-2 text-success"></i>Import Barang</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle mr-1"></i> Petunjuk Import:</h6>
                        <ol class="pl-3 mb-0" style="font-size: 13px;">
                            <li>Unduh template Excel (.xlsx) di bawah ini.</li>
                            <li>Isi data sesuai kolom yang tersedia.</li>
                            <li>Gunakan <strong>Sheet Referensi</strong> di dalam file Excel untuk melihat daftar ID.</li>
                            <li>Simpan dan upload kembali dalam format <strong>.xlsx</strong>.</li>
                        </ol>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <a href="barang_template.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-download mr-1"></i> Download Template (.xlsx)
                        </a>
                        <a href="barang_reference.php" target="_blank" class="btn btn-outline-info btn-sm">
                            <i class="fas fa-list mr-1"></i> Lihat ID Referensi
                        </a>
                    </div>
                    <div class="form-group">
                        <label>Pilih File Excel (.xlsx)</label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" name="file_import" accept=".xlsx" required>
                            <label class="custom-file-label">Pilih file...</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-upload mr-1"></i> Mulai Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambah">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="barang.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle mr-2" style="color:#6366f1;"></i>Tambah Barang</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Nama Barang <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nama_barang" placeholder="Masukkan nama barang" required>
                            </div>
                            <div class="form-group">
                                <label>Merk</label>
                                <select class="form-control select2-tags" name="id_merk" style="width:100%;">
                                    <option value="">-- Pilih Merk --</option>
                                    <?php foreach($merk_arr as $m): ?>
                                        <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nama_merk']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                             <div class="row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <label>Jumlah</label>
                                        <input type="number" class="form-control" name="jumlah" value="1" min="1">
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <label>Kondisi</label>
                                        <select class="form-control" name="kondisi">
                                            <option value="Baik">Baik</option>
                                            <option value="Rusak">Rusak</option>
                                            <option value="Hilang">Hilang</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-group">
                                        <label>Tanggal Pembelian</label>
                                        <input type="date" class="form-control" name="tgl_pembelian">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Jenis</label>
                                <select class="form-control select2-tags" name="id_jenis" style="width:100%;">
                                    <option value="">-- Pilih Jenis --</option>
                                    <?php foreach($jenis_arr as $j): ?>
                                        <option value="<?= $j['id'] ?>"><?= htmlspecialchars($j['nama_jenis']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Kategori</label>
                                <select class="form-control select2-tags" name="id_kategori" style="width:100%;">
                                    <option value="">-- Pilih Kategori --</option>
                                    <?php foreach($kategori_arr as $k): ?>
                                        <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Unit</label>
                                <select class="form-control unit-selector" name="id_unit" style="width:100%;">
                                    <option value="">-- Pilih Unit --</option>
                                    <?php foreach($unit_arr as $u): ?>
                                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Ruang</label>
                                <select class="form-control ruang-selector" name="id_ruang" style="width:100%;">
                                    <option value="">-- Pilih Ruang (Pilih Unit Dulu) --</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Foto Barang</label>
                                <div class="mb-2" id="tambah_foto_preview"></div>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" name="foto" id="foto_tambah" accept="image/*" onchange="previewImage(this, 'tambah_foto_preview')">
                                    <label class="custom-file-label" for="foto_tambah">Pilih file...</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEdit">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="barang.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <input type="hidden" name="old_foto" id="edit_old_foto">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit mr-2" style="color:#f59e0b;"></i>Edit Barang</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Nama Barang <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_nama_barang" name="nama_barang" required>
                            </div>
                            <div class="form-group">
                                <label>Merk</label>
                                <select class="form-control select2-tags" id="edit_id_merk" name="id_merk" style="width:100%;">
                                    <option value="">-- Pilih Merk --</option>
                                    <?php foreach($merk_arr as $m): ?>
                                        <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nama_merk']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                             <div class="row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <label>Jumlah</label>
                                        <input type="number" class="form-control" id="edit_jumlah" name="jumlah" min="1">
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <label>Kondisi</label>
                                        <select class="form-control" id="edit_kondisi" name="kondisi">
                                            <option value="Baik">Baik</option>
                                            <option value="Rusak">Rusak</option>
                                            <option value="Hilang">Hilang</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-group">
                                        <label>Tanggal Pembelian</label>
                                        <input type="date" class="form-control" id="edit_tgl_pembelian" name="tgl_pembelian">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Jenis</label>
                                <select class="form-control select2-tags" id="edit_id_jenis" name="id_jenis" style="width:100%;">
                                    <option value="">-- Pilih Jenis --</option>
                                    <?php foreach($jenis_arr as $j): ?>
                                        <option value="<?= $j['id'] ?>"><?= htmlspecialchars($j['nama_jenis']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Kategori</label>
                                <select class="form-control select2-tags" id="edit_id_kategori" name="id_kategori" style="width:100%;">
                                    <option value="">-- Pilih Kategori --</option>
                                    <?php foreach($kategori_arr as $k): ?>
                                        <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Unit</label>
                                <select class="form-control unit-selector" id="edit_id_unit" name="id_unit" style="width:100%;">
                                    <option value="">-- Pilih Unit --</option>
                                    <?php foreach($unit_arr as $u): ?>
                                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Ruang</label>
                                <select class="form-control ruang-selector" id="edit_id_ruang" name="id_ruang" style="width:100%;">
                                    <option value="">-- Pilih Ruang --</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Foto Barang</label>
                                <div class="mb-2" id="current_foto_preview"></div>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" name="foto" id="foto_edit" accept="image/*" onchange="previewImage(this, 'current_foto_preview')">
                                    <label class="custom-file-label" for="foto_edit">Ganti file...</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Perbarui</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(function () {
    // Initialize Select2 when modal is shown to avoid focus/rendering issues
    $('#modalTambah, #modalEdit').on('shown.bs.modal', function () {
        $(this).find('.select2-tags').select2({
            theme: 'bootstrap4',
            tags: true,
            placeholder: "Pilih atau Ketik Baru...",
            allowClear: true,
            dropdownParent: $(this)
        });

        $(this).find('.unit-selector, .ruang-selector').select2({
            theme: 'bootstrap4',
            placeholder: "Pilih...",
            allowClear: true,
            dropdownParent: $(this)
        });
    });

    // Show filename on custom file input
    $('.custom-file-input').on('change', function() {
        let fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').addClass("selected").html(fileName);
    });

    // Dependent Dropdown
    $(document).on('change', '.unit-selector', function() {
        let unitId = $(this).val();
        let target = $(this).closest('.modal').find('.ruang-selector');
        
        target.html('<option value="">-- Memuat... --</option>').trigger('change');
        
        if (unitId) {
            console.log("Fetching rooms for unit:", unitId);
            $.getJSON('get_ruang.php', { unit_id: unitId }, function(data) {
                console.log("Received data:", data);
                let options = '<option value="">-- Pilih Ruang --</option>';
                if (data.length > 0) {
                    data.forEach(function(r) {
                        options += `<option value="${r.id}">${r.nama_ruang}</option>`;
                    });
                } else {
                    options = '<option value="">-- Tidak ada ruang di unit ini --</option>';
                }
                target.html(options).trigger('change');
            }).fail(function(jqXHR, textStatus, errorThrown) {
                console.error("AJAX Error: ", textStatus, errorThrown);
                target.html('<option value="">-- Gagal memuat data --</option>').trigger('change');
            });
        } else {
            target.html('<option value="">-- Pilih Unit Dulu --</option>').trigger('change');
        }
    });
});

function editData(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_nama_barang').value = data.nama_barang;
    document.getElementById('edit_jumlah').value = data.jumlah;
    document.getElementById('edit_kondisi').value = data.kondisi;
    document.getElementById('edit_tgl_pembelian').value = data.tgl_pembelian;
    document.getElementById('edit_old_foto').value = data.foto;
    
    $('#edit_id_merk').val(data.id_merk).trigger('change');
    $('#edit_id_jenis').val(data.id_jenis).trigger('change');
    $('#edit_id_kategori').val(data.id_kategori).trigger('change');
    $('#edit_id_unit').val(data.id_unit).trigger('change');
    
    // Preview current photo
    if (data.foto) {
        document.getElementById('current_foto_preview').innerHTML = `<img src="../dist/img/barang/${data.foto}" class="img-thumbnail" style="height:80px;">`;
    } else {
        document.getElementById('current_foto_preview').innerHTML = '';
    }

    // Load rooms and set current one
    if (data.id_unit) {
        $.getJSON('get_ruang.php', { unit_id: data.id_unit }, function(rooms) {
            let options = '<option value="">-- Pilih Ruang --</option>';
            rooms.forEach(function(r) {
                let selected = r.id == data.id_ruang ? 'selected' : '';
                options += `<option value="${r.id}" ${selected}>${r.nama_ruang}</option>`;
            });
            $('#edit_id_ruang').html(options).trigger('change');
        });
    }

    $('#modalEdit').modal('show');
}

function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `<img src="${e.target.result}" class="img-thumbnail" style="height:100px; display:block;">`;
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php include '../includes/footer.php'; ?>
