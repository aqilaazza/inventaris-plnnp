<?php
session_start();
include '../config/database.php';

$page_title = 'Data Merk';
$active_menu = 'merk';

// ===== HANDLE ACTIONS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $nama_merk = trim($_POST['nama_merk']);

        if (empty($nama_merk)) {
            $_SESSION['error'] = 'Nama merk harus diisi!';
        } else {
            // Cek apakah nama merk sudah ada
            $check = $conn->prepare("SELECT id FROM merk WHERE nama_merk = ?");
            $check->bind_param("s", $nama_merk);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $_SESSION['error'] = 'Nama merk sudah ada!';
            } else {
                $stmt = $conn->prepare("INSERT INTO merk (nama_merk) VALUES (?)");
                $stmt->bind_param("s", $nama_merk);
                if ($stmt->execute()) {
                    $_SESSION['success'] = 'Data merk berhasil ditambahkan!';
                } else {
                    $_SESSION['error'] = 'Gagal menambahkan data merk!';
                }
                $stmt->close();
            }
            $check->close();
        }
        header('Location: merk.php');
        exit;
    }

    if ($action === 'edit') {
        $id = intval($_POST['id']);
        $nama_merk = trim($_POST['nama_merk']);

        if (empty($nama_merk)) {
            $_SESSION['error'] = 'Nama merk harus diisi!';
        } else {
            // Cek apakah nama merk sudah ada (kecuali ID saat ini)
            $check = $conn->prepare("SELECT id FROM merk WHERE nama_merk = ? AND id != ?");
            $check->bind_param("si", $nama_merk, $id);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $_SESSION['error'] = 'Nama merk sudah ada!';
            } else {
                $stmt = $conn->prepare("UPDATE merk SET nama_merk = ? WHERE id = ?");
                $stmt->bind_param("si", $nama_merk, $id);
                if ($stmt->execute()) {
                    $_SESSION['success'] = 'Data merk berhasil diperbarui!';
                } else {
                    $_SESSION['error'] = 'Gagal memperbarui data merk!';
                }
                $stmt->close();
            }
            $check->close();
        }
        header('Location: merk.php');
        exit;
    }
}

// Handle delete (GET)
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM merk WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Data merk berhasil dihapus!';
    } else {
        $_SESSION['error'] = 'Gagal menghapus data merk!';
    }
    $stmt->close();
    header('Location: merk.php');
    exit;
}

// ===== GET DATA =====
$data = $conn->query("SELECT * FROM merk ORDER BY id DESC");

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>Data Merk</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Merk</li>
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
                <h3 class="card-title"><i class="fas fa-copyright mr-2" style="color:#6366f1;"></i>Daftar Merk</h3>
                <div>
                    <button class="btn btn-success btn-sm mr-1" data-toggle="modal" data-target="#modalImport">
                        <i class="fas fa-file-import mr-1"></i> Import Excel
                    </button>
                    <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalTambah">
                        <i class="fas fa-plus mr-1"></i> Tambah Merk
                    </button>
                </div>
            </div>
            <div class="card-body">
                <table id="dataTable" class="table table-hover table-striped">
                    <thead>
                        <tr>
                            <th width="60">No</th>
                            <th>Nama Merk</th>
                            <th width="150" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; while ($row = $data->fetch_assoc()): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= htmlspecialchars($row['nama_merk']) ?></td>
                            <td class="text-center">
                                <button class="btn btn-warning btn-sm" 
                                    onclick="editData(<?= $row['id'] ?>, '<?= htmlspecialchars($row['nama_merk'], ENT_QUOTES) ?>')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-danger btn-sm" 
                                    onclick="confirmDelete('merk.php?delete=<?= $row['id'] ?>')">
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
            <form method="POST" action="merk_import.php" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-file-import mr-2 text-success"></i>Import Merk</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle mr-1"></i> Petunjuk Import:</h6>
                        <ol class="pl-3 mb-0" style="font-size: 13px;">
                            <li>Unduh template Excel (.xlsx) di bawah ini.</li>
                            <li>Isi nama merk pada kolom yang tersedia.</li>
                            <li>Sistem akan mengabaikan data jika nama merk sudah ada.</li>
                        </ol>
                    </div>
                    <div class="mb-3">
                        <a href="merk_template.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-download mr-1"></i> Download Template (.xlsx)
                        </a>
                    </div>
                    <div class="form-group">
                        <label>Pilih File Excel (.xlsx)</label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" name="file_import" accept=".xlsx" required id="file_merk">
                            <label class="custom-file-label" for="file_merk">Pilih file...</label>
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
            <form method="POST" action="merk.php">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle mr-2" style="color:#6366f1;"></i>Tambah Merk</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="nama_merk">Nama Merk <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nama_merk" name="nama_merk" placeholder="Masukkan nama merk" required>
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
            <form method="POST" action="merk.php">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit mr-2" style="color:#f59e0b;"></i>Edit Merk</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_nama_merk">Nama Merk <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_nama_merk" name="nama_merk" required>
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

function editData(id, nama) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_nama_merk').value = nama;
    $('#modalEdit').modal('show');
}
</script>

<?php include '../includes/footer.php'; ?>
