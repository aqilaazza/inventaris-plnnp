<?php
session_start();
include '../config/database.php';

$page_title = 'Cetak QR Code';
$active_menu = 'barang_qr';

// Filtering logic
$unit_id = isset($_GET['unit_id']) ? intval($_GET['unit_id']) : 0;
$ruang_id = isset($_GET['ruang_id']) ? intval($_GET['ruang_id']) : 0;

$where_clauses = [];
if ($unit_id > 0) $where_clauses[] = "b.id_unit = $unit_id";
if ($ruang_id > 0) $where_clauses[] = "b.id_ruang = $ruang_id";

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

// Data for filters
$units = $conn->query("SELECT * FROM unit ORDER BY nama_unit ASC");
$rooms = [];
if ($unit_id > 0) {
    $rooms_res = $conn->query("SELECT * FROM ruang WHERE id_unit = $unit_id ORDER BY nama_ruang ASC");
    while($r = $rooms_res->fetch_assoc()) $rooms[] = $r;
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>Cetak QR Code</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Cetak QR</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <!-- Filter Card -->
        <div class="card mb-3">
            <div class="card-body" style="padding: 12px 20px;">
                <form method="GET" action="barang_qr.php" class="row align-items-end">
                    <div class="col-md-4">
                        <div class="form-group mb-0">
                            <label style="font-size: 12px; font-weight: 700;">Filter Unit</label>
                            <select name="unit_id" id="filter_unit" class="form-control form-control-sm select2">
                                <option value="">-- Semua Unit --</option>
                                <?php while($u = $units->fetch_assoc()): ?>
                                    <option value="<?= $u['id'] ?>" <?= $unit_id == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['nama_unit']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-0">
                            <label style="font-size: 12px; font-weight: 700;">Filter Ruang</label>
                            <select name="ruang_id" id="filter_ruang" class="form-control form-control-sm select2">
                                <option value="">-- Semua Ruang --</option>
                                <?php foreach($rooms as $r): ?>
                                    <option value="<?= $r['id'] ?>" <?= $ruang_id == $r['id'] ? 'selected' : '' ?>><?= htmlspecialchars($r['nama_ruang']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-filter mr-1"></i> Terapkan Filter
                        </button>
                        <a href="barang_qr.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-sync-alt mr-1"></i> Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h3 class="card-title"><i class="fas fa-qrcode mr-2" style="color:#6366f1;"></i>Pilih Barang untuk Dicetak</h3>
                <button type="button" class="btn btn-primary btn-sm" id="btnPrintSelected">
                    <i class="fas fa-print mr-1"></i> Cetak Terpilih (Bulk)
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="dataTableQR" class="table table-hover table-striped">
                        <thead>
                            <tr>
                                <th width="40" class="text-center">
                                    <div class="custom-control custom-checkbox">
                                        <input class="custom-control-input" type="checkbox" id="checkAll">
                                        <label for="checkAll" class="custom-control-label"></label>
                                    </div>
                                </th>
                                <th width="100">Kode</th>
                                <th>Nama Barang</th>
                                <th>Unit / Ruang</th>
                                <th width="100" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $data->fetch_assoc()): 
                                $code = str_pad($row['id'], 5, "0", STR_PAD_LEFT);
                            ?>
                            <tr>
                                <td class="text-center">
                                    <div class="custom-control custom-checkbox">
                                        <input class="custom-control-input checkItem" type="checkbox" id="check_<?= $row['id'] ?>" value="<?= $row['id'] ?>">
                                        <label for="check_<?= $row['id'] ?>" class="custom-control-label"></label>
                                    </div>
                                </td>
                                <td><code><?= $code ?></code></td>
                                <td>
                                    <strong><?= htmlspecialchars($row['nama_barang']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($row['nama_kategori'] ?? '-') ?> | <?= htmlspecialchars($row['nama_merk'] ?? '-') ?></small>
                                </td>
                                <td>
                                    <small>
                                        <i class="fas fa-building mr-1"></i> <?= htmlspecialchars($row['nama_unit'] ?? '-') ?><br>
                                        <i class="fas fa-door-open mr-1"></i> <?= htmlspecialchars($row['nama_ruang'] ?? '-') ?>
                                    </small>
                                </td>
                                <td class="text-center">
                                    <a href="barang_print_qr.php?id=<?= $row['id'] ?>" target="_blank" class="btn btn-info btn-xs">
                                        <i class="fas fa-print"></i>
                                    </a>
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

<script>
$(function () {
    let table = $('#dataTableQR').DataTable({
        "paging": true,
        "lengthChange": true,
        "searching": true,
        "ordering": true,
        "info": true,
        "autoWidth": false,
        "responsive": true,
        "pageLength": 50
    });

    $('#checkAll').click(function () {
        // Toggle on all pages
        table.$('.checkItem').prop('checked', this.checked);
    });

    $('#btnPrintSelected').click(function() {
        let selectedIds = [];
        table.$('.checkItem:checked').each(function() {
            selectedIds.push($(this).val());
        });

        if (selectedIds.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Peringatan',
                text: 'Pilih minimal satu barang untuk dicetak!'
            });
            return;
        }

        let url = 'barang_print_qr_bulk.php?ids=' + selectedIds.join(',');
        window.open(url, '_blank');
    });

    // Dependent Dropdown for Filter
    $('#filter_unit').change(function() {
        let unitId = $(this).val();
        let target = $('#filter_ruang');
        
        target.html('<option value="">-- Memuat... --</option>').trigger('change');
        
        if (unitId) {
            $.getJSON('get_ruang.php', { unit_id: unitId }, function(data) {
                let options = '<option value="">-- Semua Ruang --</option>';
                data.forEach(function(r) {
                    options += `<option value="${r.id}">${r.nama_ruang}</option>`;
                });
                target.html(options).trigger('change');
            });
        } else {
            target.html('<option value="">-- Semua Ruang --</option>').trigger('change');
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
