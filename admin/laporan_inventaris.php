<?php
session_start();
include '../config/database.php';

$page_title = 'Laporan Inventarisasi';
$active_menu = 'laporan_inventaris';

// Filter
$unit_id = isset($_GET['unit_id']) ? intval($_GET['unit_id']) : 0;
$ruang_id = isset($_GET['ruang_id']) ? intval($_GET['ruang_id']) : 0;

$where_clauses = [];
if ($unit_id > 0) $where_clauses[] = "b.id_unit = $unit_id";
if ($ruang_id > 0) $where_clauses[] = "b.id_ruang = $ruang_id";

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

// Get Selected Names for Title
$selected_unit_name = "SEMUA UNIT";
$selected_ruang_name = "SEMUA RUANG";
if ($unit_id > 0) {
    $u_stmt = $conn->prepare("SELECT nama_unit FROM unit WHERE id = ?");
    $u_stmt->bind_param("i", $unit_id);
    $u_stmt->execute();
    if ($ur = $u_stmt->get_result()->fetch_assoc()) $selected_unit_name = strtoupper($ur['nama_unit']);
}
if ($ruang_id > 0) {
    $r_stmt = $conn->prepare("SELECT nama_ruang FROM ruang WHERE id = ?");
    $r_stmt->bind_param("i", $ruang_id);
    $r_stmt->execute();
    if ($rr = $r_stmt->get_result()->fetch_assoc()) $selected_ruang_name = strtoupper($rr['nama_ruang']);
}

$report_title = "DATA INVENTARIS";
$report_subtitle = "RUANG \"$selected_ruang_name\" UNIT \"$selected_unit_name\"";

// Grouped Query
$query = "SELECT 
            GROUP_CONCAT(b.id ORDER BY b.id ASC SEPARATOR ',') as id_list, 
            b.nama_barang, 
            m.nama_merk, 
            SUM(b.jumlah) as total_jumlah, 
            b.kondisi
          FROM barang b 
          LEFT JOIN merk m ON b.id_merk = m.id 
          $where_sql
          GROUP BY b.nama_barang, b.id_merk, b.kondisi, b.id_unit, b.id_ruang
          ORDER BY b.nama_barang ASC";
$data = $conn->query($query);

// Filter Data
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
                <h1>Laporan Inventarisasi</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Laporan Inventarisasi</li>
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
                <form method="GET" action="laporan_inventaris.php" class="row align-items-end">
                    <div class="col-md-4">
                        <div class="form-group mb-0">
                            <label style="font-size: 12px; font-weight: 700;">Unit</label>
                            <select name="unit_id" id="filter_unit" class="form-control form-control-sm select2">
                                <option value="">-- Semua Unit --</option>
                                <?php $units->data_seek(0); while($u = $units->fetch_assoc()): ?>
                                    <option value="<?= $u['id'] ?>" <?= $unit_id == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['nama_unit']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-0">
                            <label style="font-size: 12px; font-weight: 700;">Ruang</label>
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
                            <i class="fas fa-filter mr-1"></i> Tampilkan
                        </button>
                        <a href="laporan_inventaris.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-sync-alt"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-file-invoice mr-2" style="color:#6366f1;"></i>Data Inventarisasi</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="dataTableLaporan" class="table table-hover table-striped table-bordered">
                        <thead>
                            <tr class="bg-light">
                                <th width="50" class="text-center">No</th>
                                <th>Nama Barang</th>
                                <th>Merk</th>
                                <th width="200" class="text-center">ID Barang</th>
                                <th width="80" class="text-center">Jumlah</th>
                                <th width="100" class="text-center">Kondisi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; while ($row = $data->fetch_assoc()): 
                                $raw_ids = explode(',', $row['id_list']);
                                $formatted_ids = array_map(function($id) { return str_pad(trim($id), 5, "0", STR_PAD_LEFT); }, $raw_ids);
                                $code = implode(', ', $formatted_ids);
                            ?>
                            <tr>
                                <td class="text-center"><?= $no++ ?></td>
                                <td><strong><?= htmlspecialchars($row['nama_barang']) ?></strong></td>
                                <td><?= htmlspecialchars($row['nama_merk'] ?? '-') ?></td>
                                <td class="text-center"><code><?= $code ?></code></td>
                                <td class="text-center"><strong><?= $row['total_jumlah'] ?></strong></td>
                                <td class="text-center">
                                    <?php 
                                    $badge = 'success';
                                    if ($row['kondisi'] == 'Rusak') $badge = 'warning';
                                    if ($row['kondisi'] == 'Hilang') $badge = 'danger';
                                    ?>
                                    <span class="badge badge-<?= $badge ?>"><?= $row['kondisi'] ?></span>
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

<!-- Required DataTables Buttons for Export -->
<script>
$(function () {
    let reportTitle = '<?= $report_title ?>';
    let reportSubtitle = '<?= $report_subtitle ?>';

    $("#dataTableLaporan").DataTable({
        "responsive": true, 
        "lengthChange": false, 
        "autoWidth": false,
        "ordering": true,
        "order": [[0, 'asc']], 
        "buttons": [
            {
                extend: 'excel',
                text: '<i class="fas fa-file-excel mr-1"></i> Excel',
                className: 'btn-success btn-sm',
                title: reportTitle,
                messageTop: reportSubtitle,
                exportOptions: { columns: [0, 1, 2, 3, 4, 5] }
            },
            {
                extend: 'pdf',
                text: '<i class="fas fa-file-pdf mr-1"></i> PDF',
                className: 'btn-danger btn-sm',
                title: reportTitle,
                messageTop: reportSubtitle,
                orientation: 'portrait',
                pageSize: 'A4',
                exportOptions: { columns: [0, 1, 2, 3, 4, 5] },
                customize: function (doc) {
                    doc.content[1].margin = [0, 0, 0, 10]; // Margin for subtitle
                    doc.styles.tableHeader.fontSize = 10;
                    doc.styles.tableHeader.alignment = 'center';
                }
            },
            {
                extend: 'print',
                text: '<i class="fas fa-print mr-1"></i> Print',
                className: 'btn-info btn-sm',
                title: reportTitle,
                messageTop: '<h4>' + reportSubtitle + '</h4>',
                exportOptions: { columns: [0, 1, 2, 3, 4, 5] }
            }
        ]
    }).buttons().container().appendTo('#dataTableLaporan_wrapper .col-md-6:eq(0)');

    // Dependent Dropdown
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
