<?php
session_start();
include '../config/database.php';

$page_title = 'Data Unit';
$active_menu = 'unit';

// ===== HANDLE ACTIONS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $nama_unit = trim($_POST['nama_unit']);

        if (empty($nama_unit)) {
            $_SESSION['error'] = 'Nama unit harus diisi!';
        } else {
            $stmt = $conn->prepare("INSERT INTO unit (nama_unit) VALUES (?)");
            $stmt->bind_param("s", $nama_unit);
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Data unit berhasil ditambahkan!';
            } else {
                $_SESSION['error'] = 'Gagal menambahkan data unit!';
            }
            $stmt->close();
        }
        header('Location: unit.php');
        exit;
    }

    if ($action === 'edit') {
        $id = intval($_POST['id']);
        $nama_unit = trim($_POST['nama_unit']);

        if (empty($nama_unit)) {
            $_SESSION['error'] = 'Nama unit harus diisi!';
        } else {
            $stmt = $conn->prepare("UPDATE unit SET nama_unit = ? WHERE id = ?");
            $stmt->bind_param("si", $nama_unit, $id);
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Data unit berhasil diperbarui!';
            } else {
                $_SESSION['error'] = 'Gagal memperbarui data unit!';
            }
            $stmt->close();
        }
        header('Location: unit.php');
        exit;
    }
}

// Handle delete (GET)
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM unit WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Data unit berhasil dihapus!';
    } else {
        $_SESSION['error'] = 'Gagal menghapus! Pastikan tidak ada ruang yang menggunakan unit ini.';
    }
    $stmt->close();
    header('Location: unit.php');
    exit;
}

// ===== GET DATA =====
$data = $conn->query("SELECT * FROM unit ORDER BY id DESC");

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>Data Unit</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Unit</li>
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
                <h3 class="card-title"><i class="fas fa-building mr-2" style="color:#6366f1;"></i>Daftar Unit</h3>
                <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalTambah">
                    <i class="fas fa-plus mr-1"></i> Tambah Unit
                </button>
            </div>
            <div class="card-body">
                <table id="dataTable" class="table table-hover table-striped">
                    <thead>
                        <tr>
                            <th width="60">No</th>
                            <th>Nama Unit</th>
                            <th width="150" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; while ($row = $data->fetch_assoc()): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= htmlspecialchars($row['nama_unit']) ?></td>
                            <td class="text-center">
                                <button class="btn btn-warning btn-sm" 
                                    onclick="editData(<?= $row['id'] ?>, '<?= htmlspecialchars($row['nama_unit'], ENT_QUOTES) ?>')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-danger btn-sm" 
                                    onclick="confirmDelete('unit.php?delete=<?= $row['id'] ?>')">
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

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="unit.php">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle mr-2" style="color:#6366f1;"></i>Tambah Unit</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="nama_unit">Nama Unit <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nama_unit" name="nama_unit" placeholder="Masukkan nama unit" required>
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
            <form method="POST" action="unit.php">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit mr-2" style="color:#f59e0b;"></i>Edit Unit</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_nama_unit">Nama Unit <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_nama_unit" name="nama_unit" required>
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
function editData(id, nama) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_nama_unit').value = nama;
    $('#modalEdit').modal('show');
}
</script>

<?php include '../includes/footer.php'; ?>
