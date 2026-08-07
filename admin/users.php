<?php
session_start();
include '../config/database.php';

$page_title = 'Manajemen User';
$active_menu = 'users';

// ===== HANDLE ACTIONS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $nama_lengkap = trim($_POST['nama_lengkap']);
        $level = $_POST['level'];

        if (empty($username) || empty($password) || empty($nama_lengkap) || empty($level)) {
            $_SESSION['error'] = 'Semua field harus diisi!';
        } else {
            // Cek duplikat username
            $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $check->bind_param("s", $username);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $_SESSION['error'] = 'Username sudah digunakan!';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (username, password, nama_lengkap, level) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $username, $hashed, $nama_lengkap, $level);
                if ($stmt->execute()) {
                    $_SESSION['success'] = 'User berhasil ditambahkan!';
                } else {
                    $_SESSION['error'] = 'Gagal menambahkan user!';
                }
                $stmt->close();
            }
            $check->close();
        }
        header('Location: users.php');
        exit;
    }

    if ($action === 'edit') {
        $id = intval($_POST['id']);
        $username = trim($_POST['username']);
        $nama_lengkap = trim($_POST['nama_lengkap']);
        $level = $_POST['level'];
        $password = $_POST['password'];

        if (empty($username) || empty($nama_lengkap) || empty($level)) {
            $_SESSION['error'] = 'Field username, nama lengkap, dan level harus diisi!';
        } else {
            // Cek duplikat username (kecuali diri sendiri)
            $check = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $check->bind_param("si", $username, $id);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $_SESSION['error'] = 'Username sudah digunakan!';
            } else {
                if (!empty($password)) {
                    // Update dengan password baru
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET username = ?, password = ?, nama_lengkap = ?, level = ? WHERE id = ?");
                    $stmt->bind_param("ssssi", $username, $hashed, $nama_lengkap, $level, $id);
                } else {
                    // Update tanpa password
                    $stmt = $conn->prepare("UPDATE users SET username = ?, nama_lengkap = ?, level = ? WHERE id = ?");
                    $stmt->bind_param("sssi", $username, $nama_lengkap, $level, $id);
                }
                if ($stmt->execute()) {
                    $_SESSION['success'] = 'Data user berhasil diperbarui!';
                } else {
                    $_SESSION['error'] = 'Gagal memperbarui data user!';
                }
                $stmt->close();
            }
            $check->close();
        }
        header('Location: users.php');
        exit;
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    // Jangan hapus diri sendiri
    if ($id == $_SESSION['user_id']) {
        $_SESSION['error'] = 'Anda tidak bisa menghapus akun sendiri!';
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $_SESSION['success'] = 'User berhasil dihapus!';
        } else {
            $_SESSION['error'] = 'Gagal menghapus user!';
        }
        $stmt->close();
    }
    header('Location: users.php');
    exit;
}

// ===== GET DATA =====
$data = $conn->query("SELECT * FROM users ORDER BY id DESC");

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>Manajemen User</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Users</li>
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
                <h3 class="card-title"><i class="fas fa-users-cog mr-2" style="color:#8b5cf6;"></i>Daftar User</h3>
                <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalTambah">
                    <i class="fas fa-user-plus mr-1"></i> Tambah User
                </button>
            </div>
            <div class="card-body">
                <table id="dataTable" class="table table-hover table-striped">
                    <thead>
                        <tr>
                            <th width="60">No</th>
                            <th>Username</th>
                            <th>Nama Lengkap</th>
                            <th>Level</th>
                            <th>Dibuat</th>
                            <th width="150" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; while ($row = $data->fetch_assoc()): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td>
                                <i class="fas fa-user-circle mr-1" style="color:#6366f1;"></i>
                                <?= htmlspecialchars($row['username']) ?>
                            </td>
                            <td><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                            <td>
                                <?php if ($row['level'] === 'admin'): ?>
                                    <span class="badge" style="background:rgba(239,68,68,0.1); color:#ef4444; font-size:11px; padding:5px 12px;">
                                        <i class="fas fa-shield-alt mr-1"></i>Admin
                                    </span>
                                <?php else: ?>
                                    <span class="badge" style="background:rgba(14,165,233,0.1); color:#0ea5e9; font-size:11px; padding:5px 12px;">
                                        <i class="fas fa-user mr-1"></i>Petugas
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:12px; color:#9ca3af;">
                                <?= date('d M Y, H:i', strtotime($row['created_at'])) ?>
                            </td>
                            <td class="text-center">
                                <button class="btn btn-warning btn-sm" 
                                    onclick="editData(<?= $row['id'] ?>, '<?= htmlspecialchars($row['username'], ENT_QUOTES) ?>', '<?= htmlspecialchars($row['nama_lengkap'], ENT_QUOTES) ?>', '<?= $row['level'] ?>')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if ($row['id'] != $_SESSION['user_id']): ?>
                                <button class="btn btn-danger btn-sm" 
                                    onclick="confirmDelete('users.php?delete=<?= $row['id'] ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
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
            <form method="POST" action="users.php">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus mr-2" style="color:#6366f1;"></i>Tambah User</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="nama_lengkap">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" placeholder="Masukkan nama lengkap" required>
                    </div>
                    <div class="form-group">
                        <label for="username">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="username" name="username" placeholder="Masukkan username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" placeholder="Masukkan password" required>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePass('password', 'eyeIcon1')" style="border-radius:0 10px 10px 0;">
                                    <i class="fas fa-eye" id="eyeIcon1"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="level">Level Akses <span class="text-danger">*</span></label>
                        <select class="form-control" id="level" name="level" required style="border-radius:10px;">
                            <option value="">-- Pilih Level --</option>
                            <option value="admin">Admin</option>
                            <option value="petugas">Petugas</option>
                        </select>
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
            <form method="POST" action="users.php">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-edit mr-2" style="color:#f59e0b;"></i>Edit User</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_nama_lengkap">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_nama_lengkap" name="nama_lengkap" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_username">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_password">Password Baru</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="edit_password" name="password" placeholder="Kosongkan jika tidak ingin mengubah">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePass('edit_password', 'eyeIcon2')" style="border-radius:0 10px 10px 0;">
                                    <i class="fas fa-eye" id="eyeIcon2"></i>
                                </button>
                            </div>
                        </div>
                        <small class="text-muted">Kosongkan jika tidak ingin mengubah password.</small>
                    </div>
                    <div class="form-group">
                        <label for="edit_level">Level Akses <span class="text-danger">*</span></label>
                        <select class="form-control" id="edit_level" name="level" required style="border-radius:10px;">
                            <option value="admin">Admin</option>
                            <option value="petugas">Petugas</option>
                        </select>
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
function editData(id, username, nama, level) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_nama_lengkap').value = nama;
    document.getElementById('edit_level').value = level;
    document.getElementById('edit_password').value = '';
    $('#modalEdit').modal('show');
}

function togglePass(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>

<?php include '../includes/footer.php'; ?>
