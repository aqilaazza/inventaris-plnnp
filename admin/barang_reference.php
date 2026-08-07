<?php
session_start();
include '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$merk = $conn->query("SELECT id, nama_merk FROM merk ORDER BY nama_merk ASC");
$jenis = $conn->query("SELECT id, nama_jenis FROM jenis ORDER BY nama_jenis ASC");
$kategori = $conn->query("SELECT id, nama_kategori FROM kategori ORDER BY nama_kategori ASC");
$unit = $conn->query("SELECT id, nama_unit FROM unit ORDER BY nama_unit ASC");
$ruang = $conn->query("SELECT r.id, r.nama_ruang, u.nama_unit FROM ruang r JOIN unit u ON r.id_unit = u.id ORDER BY u.nama_unit ASC, r.nama_ruang ASC");

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>ID Referensi Import Barang</title>
    <link rel="stylesheet" href="../dist/css/adminlte.min.css">
    <style>
        body { padding: 20px; font-size: 13px; }
        .card-header { background: #f8f9fa; font-weight: bold; }
        table { margin-bottom: 30px !important; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <h4 class="mb-4">Daftar ID Referensi untuk Import Barang</h4>
        <p class="text-muted">Gunakan angka ID di bawah ini pada file CSV Anda.</p>

        <div class="row">
            <div class="col-md-3">
                <div class="card card-outline card-primary">
                    <div class="card-header">REF: MERK</div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-striped mb-0">
                            <thead><tr><th>ID</th><th>Nama Merk</th></tr></thead>
                            <tbody>
                                <?php while($row = $merk->fetch_assoc()): ?>
                                <tr><td><?= $row['id'] ?></td><td><?= $row['nama_merk'] ?></td></tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-outline card-success">
                    <div class="card-header">REF: JENIS</div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-striped mb-0">
                            <thead><tr><th>ID</th><th>Nama Jenis</th></tr></thead>
                            <tbody>
                                <?php while($row = $jenis->fetch_assoc()): ?>
                                <tr><td><?= $row['id'] ?></td><td><?= $row['nama_jenis'] ?></td></tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-outline card-warning">
                    <div class="card-header">REF: KATEGORI</div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-striped mb-0">
                            <thead><tr><th>ID</th><th>Nama Kategori</th></tr></thead>
                            <tbody>
                                <?php while($row = $kategori->fetch_assoc()): ?>
                                <tr><td><?= $row['id'] ?></td><td><?= $row['nama_kategori'] ?></td></tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-outline card-danger">
                    <div class="card-header">REF: UNIT</div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-striped mb-0">
                            <thead><tr><th>ID</th><th>Nama Unit</th></tr></thead>
                            <tbody>
                                <?php while($row = $unit->fetch_assoc()): ?>
                                <tr><td><?= $row['id'] ?></td><td><?= $row['nama_unit'] ?></td></tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card card-outline card-info">
                    <div class="card-header">REF: RUANG (Detail)</div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-striped mb-0">
                            <thead><tr><th>ID</th><th>Nama Ruang</th><th>Unit</th></tr></thead>
                            <tbody>
                                <?php while($row = $ruang->fetch_assoc()): ?>
                                <tr><td><?= $row['id'] ?></td><td><?= $row['nama_ruang'] ?></td><td><?= $row['nama_unit'] ?></td></tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
