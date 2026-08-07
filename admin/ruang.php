<?php
session_start();
include '../config/database.php';

$page_title = 'Data Ruang';
$active_menu = 'ruang';

// ===== HANDLE ACTIONS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $id_unit = intval($_POST['id_unit']);
        $nama_ruang = trim($_POST['nama_ruang']);

        if (empty($id_unit) || empty($nama_ruang)) {
            $_SESSION['error'] = 'Semua field harus diisi!';
        } else {
            $stmt = $conn->prepare("INSERT INTO ruang (id_unit, nama_ruang) VALUES (?, ?)");
            $stmt->bind_param("is", $id_unit, $nama_ruang);
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Data ruang berhasil ditambahkan!';
            } else {
                $_SESSION['error'] = 'Gagal menambahkan data ruang!';
            }
            $stmt->close();
        }
        header('Location: ruang.php');
        exit;
    }

    if ($action === 'edit') {
        $id = intval($_POST['id']);
        $id_unit = intval($_POST['id_unit']);
        $nama_ruang = trim($_POST['nama_ruang']);

        if (empty($id_unit) || empty($nama_ruang)) {
            $_SESSION['error'] = 'Semua field harus diisi!';
        } else {
            $stmt = $conn->prepare("UPDATE ruang SET id_unit = ?, nama_ruang = ? WHERE id = ?");
            $stmt->bind_param("isi", $id_unit, $nama_ruang, $id);
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Data ruang berhasil diperbarui!';
            } else {
                $_SESSION['error'] = 'Gagal memperbarui data ruang!';
            }
            $stmt->close();
        }
        header('Location: ruang.php');
        exit;
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM ruang WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Data ruang berhasil dihapus!';
    } else {
        $_SESSION['error'] = 'Gagal menghapus data ruang!';
    }
    $stmt->close();
    header('Location: ruang.php');
    exit;
}

// ===== GET DATA =====
$data = $conn->query("SELECT r.*, u.nama_unit FROM ruang r JOIN unit u ON r.id_unit = u.id ORDER BY r.id DESC");
$units = $conn->query("SELECT * FROM unit ORDER BY nama_unit ASC");

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>Data Ruang</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Ruang</li>
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
                <h3 class="card-title"><i class="fas fa-door-open mr-2" style="color:#0ea5e9;"></i>Daftar Ruang</h3>
                <div>
                    <button class="btn btn-success btn-sm mr-1" data-toggle="modal" data-target="#modalImport">
                        <i class="fas fa-file-import mr-1"></i> Import Excel
                    </button>
                    <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalTambah">
                        <i class="fas fa-plus mr-1"></i> Tambah Ruang
                    </button>
                </div>
            </div>
            <div class="card-body">
                <table id="dataTable" class="table table-hover table-striped">
                    <thead>
                        <tr>
                            <th width="60">No</th>
                            <th>Nama Ruang</th>
                            <th>Unit</th>
                            <th width="150" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; while ($row = $data->fetch_assoc()): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= htmlspecialchars($row['nama_ruang']) ?></td>
                            <td>
                                <span class="badge" style="background:rgba(99,102,241,0.1); color:#6366f1; font-size:12px; padding:5px 10px;">
                                    <i class="fas fa-building mr-1"></i><?= htmlspecialchars($row['nama_unit']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <button class="btn btn-warning btn-sm" 
                                    onclick="editData(<?= $row['id'] ?>, <?= $row['id_unit'] ?>, '<?= htmlspecialchars($row['nama_ruang'], ENT_QUOTES) ?>')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-danger btn-sm" 
                                    onclick="confirmDelete('ruang.php?delete=<?= $row['id'] ?>')">
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
</section>

<!-- Modal Import -->
<div class="modal fade" id="modalImport">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="ruang_import.php" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-file-import mr-2 text-success"></i>Import Ruang</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle mr-1"></i> Petunjuk Import:</h6>
                        <ol class="pl-3 mb-0" style="font-size: 13px;">
                            <li>Unduh template Excel (.xlsx) di bawah ini.</li>
                            <li>Gunakan <strong>Sheet Referensi Unit</strong> untuk melihat daftar ID Unit.</li>
                            <li>Isi ID Unit dan Nama Ruang pada kolom yang tersedia.</li>
                        </ol>
                    </div>
                    <div class="mb-3">
                        <a href="ruang_template.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-download mr-1"></i> Download Template (.xlsx)
                        </a>
                    </div>
                    <div class="form-group">
                        <label>Pilih File Excel (.xlsx)</label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" name="file_import" accept=".xlsx" required id="file_ruang">
                            <label class="custom-file-label" for="file_ruang">Pilih file...</label>
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
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="ruang.php">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle mr-2" style="color:#0ea5e9;"></i>Tambah Ruang</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="id_unit">Unit <span class="text-danger">*</span></label>
                        <select class="form-control select2" id="id_unit" name="id_unit" required style="width:100%;">
                            <option value="">-- Pilih Unit --</option>
                            <?php 
                            $units->data_seek(0);
                            while ($u = $units->fetch_assoc()): 
                            ?>
                                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="nama_ruang">Nama Ruang <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nama_ruang" name="nama_ruang" placeholder="Masukkan nama ruang" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" style="border-radius:10px;">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="ruang.php">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit mr-2" style="color:#f59e0b;"></i>Edit Ruang</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_id_unit">Unit <span class="text-danger">*</span></label>
                        <select class="form-control select2" id="edit_id_unit" name="id_unit" required style="width:100%;">
                            <option value="">-- Pilih Unit --</option>
                            <?php 
                            $units->data_seek(0);
                            while ($u = $units->fetch_assoc()): 
                            ?>
                                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_nama_ruang">Nama Ruang <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_nama_ruang" name="nama_ruang" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" style="border-radius:10px;">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Perbarui</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(function () {
    // Show filename on custom file input
    $('.custom-file-input').on('change', function() {
        let fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').addClass("selected").html(fileName);
    });
});

function editData(id, id_unit, nama) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_nama_ruang').value = nama;
    $('#edit_id_unit').val(id_unit).trigger('change');
    $('#modalEdit').modal('show');
}
</script>

<?php include '../includes/footer.php'; ?>
