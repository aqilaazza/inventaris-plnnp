<?php
session_start();
include '../config/database.php';

$page_title = 'Penghapusan Barang';
$active_menu = 'penghapusan_barang';

// Parameters for pre-selection (e.g. from review page)
$pre_selected_barang_id = isset($_GET['barang_id']) ? intval($_GET['barang_id']) : 0;
$pre_selected_alasan    = isset($_GET['alasan']) ? $_GET['alasan'] : '';
$pre_selected_ket       = isset($_GET['ket']) ? $_GET['ket'] : '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $id_barang        = intval($_POST['id_barang']);
        $alasan           = $_POST['alasan'];
        $keterangan       = trim($_POST['keterangan']);
        $tgl_penghapusan  = $_POST['tgl_penghapusan'];
        $tujuan_hibah     = ($alasan === 'Hibah') ? trim($_POST['tujuan_hibah'] ?? '') : null;
        $id_admin         = $_SESSION['user_id'];

        // Handle File Upload (Dokumen Pendukung)
        $doc_name = null;
        if (isset($_FILES['dokumen_pendukung']) && $_FILES['dokumen_pendukung']['error'] === 0) {
            $target_dir = "../uploads/dokumen/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $file_ext = pathinfo($_FILES["dokumen_pendukung"]["name"], PATHINFO_EXTENSION);
            $doc_name = "DOC_BATAL_" . time() . "_" . rand(100, 999) . "." . $file_ext;
            $target_file = $target_dir . $doc_name;
            move_uploaded_file($_FILES["dokumen_pendukung"]["tmp_name"], $target_file);
        }

        if ($id_barang > 0 && !empty($alasan)) {
            $conn->begin_transaction();
            try {
                // 1. Simpan ke tabel penghapusan_barang
                $stmt = $conn->prepare("INSERT INTO penghapusan_barang (id_barang, alasan, keterangan, dokumen_pendukung, tgl_penghapusan, tujuan_hibah, id_admin) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssssi", $id_barang, $alasan, $keterangan, $doc_name, $tgl_penghapusan, $tujuan_hibah, $id_admin);
                $stmt->execute();
                $stmt->close();

                // 2. Update status_aktif di tabel barang jadi 'nonaktif'
                $stmt_brg = $conn->prepare("UPDATE barang SET status_aktif = 'nonaktif' WHERE id = ?");
                $stmt_brg->bind_param("i", $id_barang);
                $stmt_brg->execute();
                $stmt_brg->close();

                $conn->commit();
                $_SESSION['success'] = 'Barang berhasil dikeluarkan dari inventaris aktif!';
            } catch (Exception $e) {
                $conn->rollback();
                // Hapus file dokumen yang sudah terlanjur diupload jika error
                if ($doc_name && file_exists("../uploads/dokumen/" . $doc_name)) {
                    @unlink("../uploads/dokumen/" . $doc_name);
                }
                $_SESSION['error'] = 'Gagal mengeluarkan barang: ' . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = 'Form data tidak lengkap!';
        }
        header('Location: penghapusan_barang.php');
        exit;
    }

    if ($action === 'reactivate') {
        $id_penghapusan = intval($_POST['id_penghapusan']);
        
        // Ambil detail barang dari record penghapusan
        $res = $conn->query("SELECT id_barang, dokumen_pendukung FROM penghapusan_barang WHERE id = $id_penghapusan");
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $id_barang = $row['id_barang'];
            $doc_file  = $row['dokumen_pendukung'];

            $conn->begin_transaction();
            try {
                // 1. Update barang status_aktif = 'aktif'
                $stmt_brg = $conn->prepare("UPDATE barang SET status_aktif = 'aktif' WHERE id = ?");
                $stmt_brg->bind_param("i", $id_barang);
                $stmt_brg->execute();
                $stmt_brg->close();

                // 2. Hapus record penghapusan
                $conn->query("DELETE FROM penghapusan_barang WHERE id = $id_penghapusan");

                $conn->commit();

                // Hapus file dokumen pendukung lama jika ada
                if ($doc_file && file_exists("../uploads/dokumen/" . $doc_file)) {
                    @unlink("../uploads/dokumen/" . $doc_file);
                }

                $_SESSION['success'] = 'Barang berhasil diaktifkan kembali ke inventaris aktif!';
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['error'] = 'Gagal mengaktifkan kembali barang: ' . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = 'Data penghapusan tidak ditemukan!';
        }
        header('Location: penghapusan_barang.php');
        exit;
    }
}

// Fetch active goods for the selection dropdown with extra detail for preview
$active_goods_res = $conn->query("SELECT b.id, b.nama_barang, b.foto, b.kondisi, m.nama_merk, k.nama_kategori, u.nama_unit, r.nama_ruang FROM barang b LEFT JOIN merk m ON b.id_merk = m.id LEFT JOIN kategori k ON b.id_kategori = k.id LEFT JOIN unit u ON b.id_unit = u.id LEFT JOIN ruang r ON b.id_ruang = r.id WHERE b.status_aktif = 'aktif' ORDER BY b.nama_barang ASC");
$active_goods = [];
while ($g = $active_goods_res->fetch_assoc()) {
    $active_goods[] = $g;
}

// Fetch decommissioned goods history
$history_query = "SELECT pb.*, b.nama_barang, m.nama_merk, k.nama_kategori, u.nama_unit, r.nama_ruang, 
                         adm.nama_lengkap as nama_admin
                  FROM penghapusan_barang pb
                  JOIN barang b ON pb.id_barang = b.id
                  LEFT JOIN merk m ON b.id_merk = m.id
                  LEFT JOIN kategori k ON b.id_kategori = k.id
                  LEFT JOIN unit u ON b.id_unit = u.id
                  LEFT JOIN ruang r ON b.id_ruang = r.id
                  LEFT JOIN users adm ON pb.id_admin = adm.id
                  ORDER BY pb.created_at DESC";
$history_res = $conn->query($history_query);

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>Penghapusan / Pengeluaran Barang</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Penghapusan</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle mr-1"></i> <?= $_SESSION['success'] ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle mr-1"></i> <?= $_SESSION['error'] ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="row">
            <!-- Form Section -->
            <div class="col-lg-4 col-12">
                <div class="card card-indigo card-outline">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-minus-circle mr-2 text-indigo"></i>Form Pengeluaran Barang</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="penghapusan_barang.php" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="add">
                            
                            <div class="form-group">
                                <label>Pilih Barang <span class="text-danger">*</span></label>
                                <select name="id_barang" id="select_barang" class="form-control select2" style="width:100%;" required>
                                    <option value="">-- Pilih Barang Aktif --</option>
                                    <?php foreach($active_goods as $g): 
                                        $code = str_pad($g['id'], 5, "0", STR_PAD_LEFT);
                                        $foto = $g['foto'] ?? '';
                                    ?>
                                        <option value="<?= $g['id'] ?>" <?= $pre_selected_barang_id == $g['id'] ? 'selected' : '' ?>
                                            data-foto="<?= htmlspecialchars($foto) ?>"
                                            data-kategori="<?= htmlspecialchars($g['nama_kategori'] ?? '') ?>"
                                            data-merk="<?= htmlspecialchars($g['nama_merk'] ?? '') ?>"
                                            data-unit="<?= htmlspecialchars($g['nama_unit'] ?? '') ?>"
                                            data-ruang="<?= htmlspecialchars($g['nama_ruang'] ?? '') ?>"
                                            data-kondisi="<?= htmlspecialchars($g['kondisi'] ?? '') ?>"
                                        >
                                            [<?= $code ?>] <?= htmlspecialchars($g['nama_barang']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Hanya menampilkan barang berstatus aktif.</small>
                            </div>

                            <!-- Detail Barang (Preview) -->
                            <div id="detail_barang" style="display:none;">
                                <div class="card mt-2">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-4 text-center">
                                                <img id="detail_foto" src="../uploads/barang/default.png" class="img-thumbnail" style="width:100%; height:140px; object-fit:cover;" />
                                            </div>
                                            <div class="col-8">
                                                <h5 id="detail_nama" class="mb-1"></h5>
                                                <p class="mb-1"><strong>Kategori:</strong> <span id="detail_kategori"></span></p>
                                                <p class="mb-1"><strong>Merk:</strong> <span id="detail_merk"></span></p>
                                                <p class="mb-1"><strong>Lokasi:</strong> <span id="detail_unit"></span> → <span id="detail_ruang"></span></p>
                                                <p class="mb-0"><strong>Kondisi:</strong> <span id="detail_kondisi"></span></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Alasan Pengeluaran <span class="text-danger">*</span></label>
                                <select name="alasan" id="select_alasan" class="form-control" required>
                                    <option value="">-- Pilih Alasan --</option>
                                    <option value="Rusak" <?= $pre_selected_alasan === 'Rusak' ? 'selected' : '' ?>>Rusak Berat / Tidak Bisa Dipakai</option>
                                    <option value="Hilang" <?= $pre_selected_alasan === 'Hilang' ? 'selected' : '' ?>>Hilang / Dicuri</option>
                                    <option value="Hibah" <?= $pre_selected_alasan === 'Hibah' ? 'selected' : '' ?>>Dihibahkan ke Instansi Lain</option>
                                    <option value="Lelang" <?= $pre_selected_alasan === 'Lelang' ? 'selected' : '' ?>>Dilelang</option>
                                    <option value="Musnah" <?= $pre_selected_alasan === 'Musnah' ? 'selected' : '' ?>>Dimusnahkan</option>
                                    <option value="Tidak Digunakan" <?= $pre_selected_alasan === 'Tidak Digunakan' ? 'selected' : '' ?>>Tidak Digunakan Lagi</option>
                                    <option value="Lainnya" <?= $pre_selected_alasan === 'Lainnya' ? 'selected' : '' ?>>Lainnya</option>
                                </select>
                            </div>

                            <!-- Input Hibah (Dynamic) -->
                            <div class="form-group" id="group_hibah" style="display:none;">
                                <label>Tujuan Hibah <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="tujuan_hibah" placeholder="Nama instansi/sekolah penerima hibah">
                            </div>

                            <div class="form-group">
                                <label>Keterangan Detail <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="keterangan" rows="3" placeholder="Tulis rincian kondisi, berita acara, dll..." required><?= htmlspecialchars($pre_selected_ket) ?></textarea>
                            </div>

                            <div class="form-group">
                                <label>Tanggal Efektif <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="tgl_penghapusan" value="<?= date('Y-m-d') ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Upload Dokumen Pendukung</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" name="dokumen_pendukung" id="dokumen_pendukung" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <label class="custom-file-label" for="dokumen_pendukung">Pilih Dokumen Pendukung...</label>
                                </div>
                                <small class="text-muted text-xs">Mendukung format PDF, Word, atau Gambar.</small>
                            </div>

                            <button type="submit" class="btn btn-primary btn-block py-2 mt-3" style="border-radius:10px; font-weight:700;">
                                <i class="fas fa-trash-alt mr-1"></i> Proses Pengeluaran
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- History Section -->
            <div class="col-lg-8 col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-history mr-2 text-indigo"></i>Riwayat Pengeluaran & Penghapusan Barang</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="dataTablePenghapusan" class="table table-hover table-striped">
                                <thead>
                                    <tr>
                                        <th width="40">No</th>
                                        <th>Barang</th>
                                        <th width="100">Alasan</th>
                                        <th>Keterangan / Tujuan</th>
                                        <th width="100">Tgl Keluar</th>
                                        <th>Dokumen</th>
                                        <th>Admin</th>
                                        <th width="100" class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $no = 1; 
                                    while ($row = $history_res->fetch_assoc()): 
                                        $code = str_pad($row['id_barang'], 5, "0", STR_PAD_LEFT);
                                    ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td>
                                            <code><?= $code ?></code><br>
                                            <strong><?= htmlspecialchars($row['nama_barang']) ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($row['nama_kategori'] ?? '-') ?> | <?= htmlspecialchars($row['nama_merk'] ?? '-') ?></small>
                                        </td>
                                        <td>
                                            <?php 
                                            $al_badge = 'danger';
                                            if ($row['alasan'] === 'Hibah') $al_badge = 'info';
                                            if ($row['alasan'] === 'Tidak Digunakan') $al_badge = 'secondary';
                                            if ($row['alasan'] === 'Lelang') $al_badge = 'warning';
                                            ?>
                                            <span class="badge badge-<?= $al_badge ?>"><?= $row['alasan'] ?></span>
                                        </td>
                                        <td>
                                            <small class="text-dark font-italic">"<?= htmlspecialchars($row['keterangan']) ?>"</small>
                                            <?php if ($row['alasan'] === 'Hibah' && !empty($row['tujuan_hibah'])): ?>
                                                <br><small class="text-indigo font-weight-bold"><i class="fas fa-hand-holding-heart"></i> Hibah Ke: <?= htmlspecialchars($row['tujuan_hibah']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?= date('d/m/Y', strtotime($row['tgl_penghapusan'])) ?></small>
                                        </td>
                                        <td>
                                            <?php if (!empty($row['dokumen_pendukung']) && file_exists('../uploads/dokumen/' . $row['dokumen_pendukung'])): ?>
                                                <a href="../uploads/dokumen/<?= $row['dokumen_pendukung'] ?>" target="_blank" class="btn btn-outline-info btn-xs">
                                                    <i class="fas fa-file-download mr-1"></i> File
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted text-xs">Tidak ada</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small class="font-weight-bold"><?= htmlspecialchars($row['nama_admin'] ?? '-') ?></small>
                                        </td>
                                        <td class="text-center">
                                            <button class="btn btn-success btn-xs" onclick="confirmReactivate(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['nama_barang'])) ?>')" title="Aktifkan Kembali Barang">
                                                <i class="fas fa-undo mr-1"></i> Pulihkan
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
        </div>

    </div>
</section>

<!-- Reactivate Form (Hidden) -->
<form id="formReactivate" method="POST" action="penghapusan_barang.php" style="display:none;">
    <input type="hidden" name="action" value="reactivate">
    <input type="hidden" name="id_penghapusan" id="reactivate_id">
</form>

<script>
$(function () {
    $('.select2').select2({
        theme: 'bootstrap4',
        placeholder: 'Pilih...',
        allowClear: true
    });

    $('#dataTablePenghapusan').DataTable({
        "paging": true,
        "lengthChange": true,
        "searching": true,
        "ordering": true,
        "info": true,
        "autoWidth": false,
        "responsive": true
    });

    // Custom file input show filename
    $('.custom-file-input').on('change', function() {
        let fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').addClass("selected").html(fileName);
    });

    // Toggle hibah input field based on selection
    function toggleHibahField() {
        let alasan = $('#select_alasan').val();
        if (alasan === 'Hibah') {
            $('#group_hibah').slideDown();
            $('input[name="tujuan_hibah"]').attr('required', true);
        } else {
            $('#group_hibah').slideUp();
            $('input[name="tujuan_hibah"]').attr('required', false);
        }
    }

    $('#select_alasan').change(toggleHibahField);
    toggleHibahField(); // Run on load in case of pre-selected values

    // Show detail preview when selecting a barang
    function updateDetailBarang() {
        var $opt = $('#select_barang').find('option:selected');
        var val = $opt.val();
        if (!val) {
            $('#detail_barang').hide();
            return;
        }
        var rawText = $opt.text().trim();
        var nama = rawText.replace(/^\[[^\]]+\]\s*/, '');
        var foto = $opt.data('foto') || '';
        var kategori = $opt.data('kategori') || '-';
        var merk = $opt.data('merk') || '-';
        var unit = $opt.data('unit') || '-';
        var ruang = $opt.data('ruang') || '-';
        var kondisi = $opt.data('kondisi') || '-';

        $('#detail_nama').text(nama);
        $('#detail_kategori').text(kategori);
        $('#detail_merk').text(merk);
        $('#detail_unit').text(unit);
        $('#detail_ruang').text(ruang);
        $('#detail_kondisi').text(kondisi);

        if (foto) {
            $('#detail_foto').attr('src', '../uploads/barang/' + foto);
        } else {
            $('#detail_foto').attr('src', '../uploads/barang/default.png');
        }

        $('#detail_barang').slideDown();
    }

    $('#select_barang').on('change', updateDetailBarang);
    // If there's a pre-selected barang, show its details on load
    if ($('#select_barang').val()) {
        updateDetailBarang();
    }
});

function confirmReactivate(id, nama) {
    Swal.fire({
        title: 'Aktifkan Kembali Barang?',
        text: `Apakah Anda yakin ingin memulihkan barang "${nama}" ke dalam inventaris aktif? Barang ini akan muncul kembali dalam monitoring dan pengecekan berkala.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Pulihkan!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('reactivate_id').value = id;
            document.getElementById('formReactivate').submit();
        }
    });
}
</script>

<?php include '../includes/footer.php'; ?>
