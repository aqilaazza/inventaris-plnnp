<?php
session_start();
include '../config/database.php';

$page_title = 'Update Gambar Barang';
$active_menu = 'update_gambar';

// Handle delete image
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
  $id_barang = intval($_POST['id_barang'] ?? 0);
  if ($id_barang > 0) {
    $res = $conn->query("SELECT foto FROM barang WHERE id = $id_barang");
    if ($res && $row = $res->fetch_assoc()) {
      $oldName = $row['foto'];
      // Update DB
      $stmt = $conn->prepare("UPDATE barang SET foto = NULL WHERE id = ?");
      $stmt->bind_param('i', $id_barang);
      if ($stmt->execute()) {
        if ($oldName && file_exists('../uploads/barang/' . $oldName)) {
          @unlink('../uploads/barang/' . $oldName);
        }
        $_SESSION['success'] = 'Gambar barang berhasil dihapus.';
      } else {
        $_SESSION['error'] = 'Gagal menghapus data gambar di database.';
      }
      $stmt->close();
    } else {
      $_SESSION['error'] = 'Data barang tidak ditemukan.';
    }
  } else {
    $_SESSION['error'] = 'ID barang tidak valid.';
  }
  header('Location: update_gambar.php'); exit;
}

// Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    $id_barang = intval($_POST['id_barang'] ?? 0);
    $maxBytes = 500 * 1024; // 500 KB

    if ($id_barang <= 0) {
        $_SESSION['error'] = 'Pilih barang yang valid.';
        header('Location: update_gambar.php'); exit;
    }

    if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = 'File foto belum dipilih atau terjadi error upload.';
        header('Location: update_gambar.php'); exit;
    }

    $tmp = $_FILES['foto']['tmp_name'];
    $info = @getimagesize($tmp);
    if (!$info || empty($info['mime'])) {
      $_SESSION['error'] = 'File bukan gambar yang valid.';
      header('Location: update_gambar.php'); exit;
    }

    // Check GD availability
    $gd_available = (function_exists('imagecreatefromjpeg') || function_exists('imagecreatefrompng') || function_exists('imagecreatefromgif') || function_exists('imagecreatefromwebp'));
    if (!$gd_available) {
      // Fallback: move uploaded file as-is (no compression). Warn user to enable GD for compression.
      $uploadsDir = '../uploads/barang/';
      if (!file_exists($uploadsDir)) mkdir($uploadsDir, 0777, true);
      $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
      $newName = 'IMG_' . $id_barang . '_' . time() . '.' . $ext;
      $targetPath = $uploadsDir . $newName;
      if (move_uploaded_file($tmp, $targetPath)) {
        // Update DB and remove old foto jika ada
        $old = $conn->query("SELECT foto FROM barang WHERE id = $id_barang")->fetch_assoc();
        $oldName = $old['foto'] ?? '';
        $stmt = $conn->prepare("UPDATE barang SET foto = ? WHERE id = ?");
        $stmt->bind_param('si', $newName, $id_barang);
        if ($stmt->execute()) {
          if ($oldName && file_exists('../uploads/barang/' . $oldName)) {
            @unlink('../uploads/barang/' . $oldName);
          }
          $_SESSION['success'] = 'Gambar diunggah (tanpa kompresi karena GD tidak tersedia). Pertimbangkan mengaktifkan ekstensi GD di php.ini untuk kompresi otomatis.';
        } else {
          if (file_exists($targetPath)) @unlink($targetPath);
          $_SESSION['error'] = 'Gagal memperbarui database.';
        }
        $stmt->close();
        header('Location: update_gambar.php'); exit;
      } else {
        $_SESSION['error'] = 'Gagal menyimpan file upload.';
        header('Location: update_gambar.php'); exit;
      }
    }

    $mime = $info['mime'];
    switch ($mime) {
      case 'image/jpeg': $src = imagecreatefromjpeg($tmp); break;
      case 'image/png': $src = imagecreatefrompng($tmp); break;
      case 'image/gif': $src = imagecreatefromgif($tmp); break;
      case 'image/webp':
        if (function_exists('imagecreatefromwebp')) { $src = imagecreatefromwebp($tmp); break; }
        $_SESSION['error'] = 'WebP tidak didukung oleh server ini.'; header('Location: update_gambar.php'); exit;
      default:
        $_SESSION['error'] = 'Format file tidak didukung. Gunakan JPG/PNG/GIF/WebP.';
        header('Location: update_gambar.php'); exit;
    }

    if (!$src) {
      $_SESSION['error'] = 'Gagal memproses gambar.';
      header('Location: update_gambar.php'); exit;
    }

    $width = imagesx($src);
    $height = imagesy($src);

    // Resize if too large (max dimension 1600)
    $maxDim = 1600;
    $scale = 1;
    if ($width > $maxDim || $height > $maxDim) {
        $scale = min($maxDim / $width, $maxDim / $height);
    }
    $newW = max(1, (int)($width * $scale));
    $newH = max(1, (int)($height * $scale));

    $dst = imagecreatetruecolor($newW, $newH);
    // White background for PNG transparency when converting to JPEG
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefill($dst, 0, 0, $white);
    imagecopyresampled($dst, $src, 0,0,0,0, $newW, $newH, $width, $height);
    imagedestroy($src);

    // Save iteratively to meet max size (as JPEG)
    $tmpDir = sys_get_temp_dir();
    $tempOut = tempnam($tmpDir, 'upimg_');
    $quality = 90;
    $saved = false;
    while ($quality >= 10) {
        imagejpeg($dst, $tempOut, $quality);
        if (filesize($tempOut) <= $maxBytes) { $saved = true; break; }
        $quality -= 10;
    }

    // If still too big, downscale and retry a couple times
    $downAttempts = 0;
    $srcForDownscale = $dst;
    while (!$saved && $downAttempts < 3) {
        $newW = (int)($newW * 0.9);
        $newH = (int)($newH * 0.9);
        $tmpDst = imagecreatetruecolor($newW, $newH);
        $white = imagecolorallocate($tmpDst, 255,255,255);
        imagefill($tmpDst,0,0,$white);
        $srcW = imagesx($srcForDownscale);
        $srcH = imagesy($srcForDownscale);
        imagecopyresampled($tmpDst, $srcForDownscale, 0,0,0,0, $newW, $newH, $srcW, $srcH);
        imagedestroy($srcForDownscale);
        $srcForDownscale = $tmpDst;

        $quality = 80;
        while ($quality >= 10) {
            imagejpeg($srcForDownscale, $tempOut, $quality);
            if (filesize($tempOut) <= $maxBytes) { $saved = true; break; }
            $quality -= 10;
        }
        $downAttempts++;
    }

    if (!$saved) {
        // final attempt save with lowest quality
        imagejpeg($srcForDownscale, $tempOut, 10);
    }

    // Move to uploads
    $uploadsDir = '../uploads/barang/';
    if (!file_exists($uploadsDir)) mkdir($uploadsDir, 0777, true);
    $newName = 'IMG_' . $id_barang . '_' . time() . '.jpg';
    $targetPath = $uploadsDir . $newName;
    if (!rename($tempOut, $targetPath)) {
        // fallback to copy
        if (!copy($tempOut, $targetPath)) {
            @unlink($tempOut);
            imagedestroy($srcForDownscale);
            $_SESSION['error'] = 'Gagal menyimpan file hasil kompresi.';
            header('Location: update_gambar.php'); exit;
        } else {
            @unlink($tempOut);
        }
    }

    imagedestroy($srcForDownscale);

    // Update DB and remove old foto jika ada
    $old = $conn->query("SELECT foto FROM barang WHERE id = $id_barang")->fetch_assoc();
    $oldName = $old['foto'] ?? '';
    $stmt = $conn->prepare("UPDATE barang SET foto = ? WHERE id = ?");
    $stmt->bind_param('si', $newName, $id_barang);
    if ($stmt->execute()) {
        // hapus lama
        if ($oldName && file_exists('../uploads/barang/' . $oldName)) {
            @unlink('../uploads/barang/' . $oldName);
        }
        $_SESSION['success'] = 'Gambar berhasil diunggah dan dikompresi.';
    } else {
        // remove new file
        if (file_exists($targetPath)) @unlink($targetPath);
        $_SESSION['error'] = 'Gagal memperbarui database.';
    }
    $stmt->close();
    header('Location: update_gambar.php'); exit;
}

// Fetch goods list with extra details for preview
$goods_res = $conn->query("SELECT b.id, b.nama_barang, b.foto, b.kondisi, k.nama_kategori, m.nama_merk, u.nama_unit, r.nama_ruang FROM barang b LEFT JOIN kategori k ON b.id_kategori = k.id LEFT JOIN merk m ON b.id_merk = m.id LEFT JOIN unit u ON b.id_unit = u.id LEFT JOIN ruang r ON b.id_ruang = r.id ORDER BY b.nama_barang ASC");
$goods = [];
while ($r = $goods_res->fetch_assoc()) $goods[] = $r;

// GD availability for UI hint (non-invasive)
$gd_available_flag = (function_exists('imagecreatefromjpeg') || function_exists('imagecreatefrompng') || function_exists('imagecreatefromgif') || function_exists('imagecreatefromwebp'));

include '../includes/header_petugas.php';
include '../includes/sidebar_petugas.php';
?>

<style>
    .btn-flat-primary {
        background-image: none !important;
        background-color: #007bff !important;
        border-color: #007bff !important;
        box-shadow: none !important;
        color: #fff !important;
    }
    .btn-flat-primary:hover,
    .btn-flat-primary:focus {
        background-color: #0069d9 !important;
        border-color: #0062cc !important;
        box-shadow: none !important;
    }
</style>

<div class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6"><h1>Update Gambar Barang</h1></div>
      <div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="index.php">Dashboard</a></li><li class="breadcrumb-item active">Update Gambar</li></ol></div>
    </div>
  </div>
</div>

<section class="content"><div class="container-fluid">

<?php if (isset($_SESSION['success'])): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert"><?= $_SESSION['success'] ?><button type="button" class="close" data-dismiss="alert">&times;</button></div>
  <?php unset($_SESSION['success']); ?>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
  <div class="alert alert-danger alert-dismissible fade show" role="alert"><?= $_SESSION['error'] ?><button type="button" class="close" data-dismiss="alert">&times;</button></div>
  <?php unset($_SESSION['error']); ?>
<?php endif; ?>
<?php if (!$gd_available_flag): ?>
  <div class="alert alert-warning">Ekstensi GD PHP tidak terdeteksi pada server. Kompresi otomatis akan <strong>tidak tersedia</strong>. Untuk mengaktifkan kompresi, buka <code>php.ini</code> (XAMPP: <code>c:\xampp\php\php.ini</code>), uncomment ekstensi GD (mis. <code>extension=gd</code> atau <code>extension=php_gd2.dll</code>), lalu restart Apache.</div>
<?php endif; ?>

  <div class="row">
    <div class="col-md-12">
      <div class="card">
        <div class="card-body">

          <!-- Dropdown pilih barang (di luar kedua form) -->
          <div class="form-group">
            <label>Pilih Barang</label>
            <select id="select_barang" class="form-control select2">
              <option value="">-- Pilih Barang --</option>
              <?php foreach($goods as $g): ?>
                <option value="<?= $g['id'] ?>"
                  data-foto="<?= htmlspecialchars($g['foto']) ?>"
                  data-kondisi="<?= htmlspecialchars($g['kondisi']) ?>"
                  data-kategori="<?= htmlspecialchars($g['nama_kategori'] ?? '') ?>"
                  data-merk="<?= htmlspecialchars($g['nama_merk'] ?? '') ?>"
                  data-unit="<?= htmlspecialchars($g['nama_unit'] ?? '') ?>"
                  data-ruang="<?= htmlspecialchars($g['nama_ruang'] ?? '') ?>"
                  data-nama="<?= htmlspecialchars($g['nama_barang']) ?>"
                  data-kode="<?= htmlspecialchars('BRG-' . str_pad($g['id'],5,'0',STR_PAD_LEFT)) ?>"
                >[<?= str_pad($g['id'],5,'0',STR_PAD_LEFT) ?>] <?= htmlspecialchars($g['nama_barang']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Preview card dengan form hapus TERPISAH -->
          <div id="preview_card" style="display:none;" class="mb-3">
            <div class="card">
              <div class="card-body">
                <div class="row">
                  <div class="col-4 text-center">
                    <img id="preview_img" src="../uploads/barang/default.png" style="max-width:100%; height:140px; object-fit:cover;" class="img-thumbnail" />
                  </div>
                  <div class="col-8">
                    <h5 id="preview_nama" class="mb-1"></h5>
                    <p class="mb-1"><strong>Kode:</strong> <span id="preview_kode"></span></p>
                    <p class="mb-1"><strong>Kategori:</strong> <span id="preview_kategori"></span></p>
                    <p class="mb-1"><strong>Merk:</strong> <span id="preview_merk"></span></p>
                    <p class="mb-1"><strong>Lokasi:</strong> <span id="preview_unit"></span> &rarr; <span id="preview_ruang"></span></p>
                    <p class="mb-0"><strong>Kondisi:</strong> <span id="preview_kondisi"></span></p>
                    <div class="mt-2">
                      <!-- FORM HAPUS: form terpisah, BUKAN nested di dalam formUpload -->
                      <form id="formDelete" method="POST" action="update_gambar.php" onsubmit="return confirm('Yakin hapus gambar barang ini?');" style="display:none;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id_barang" id="delete_id_barang" value="">
                        <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash-alt mr-1"></i> Hapus Gambar</button>
                      </form>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- FORM UPLOAD: form terpisah, tidak ada form lain di dalamnya -->
          <form id="formUpload" method="POST" action="update_gambar.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload">
            <input type="hidden" name="id_barang" id="upload_id_barang" value="">

            <div class="form-group">
              <label>Unggah Gambar</label>
              <input type="file" name="foto" id="foto_input" accept="image/*" capture="environment" class="form-control-file" required>
              <small class="text-muted d-block mb-2">Format: JPG/PNG/GIF/WebP. Server akan mengompresi otomatis.</small>
              
              <div id="new_image_preview_container" style="display:none;" class="mt-2">
                <span class="d-block text-xs font-weight-bold text-muted mb-1"><i class="fas fa-eye mr-1 text-primary"></i> Pratinjau Gambar Baru:</span>
                <div style="border: 2px dashed #d1d5db; border-radius: 12px; padding: 10px; background: #fafafa; display: inline-block; max-width: 280px; position: relative; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                  <img id="new_image_preview" src="" style="max-width: 100%; max-height: 200px; border-radius: 8px; object-fit: contain; display: block;" />
                </div>
              </div>
            </div>

            <button type="submit" class="btn btn-flat-primary">Unggah & Kompres</button>
          </form>

        </div>
      </div>
    </div>
  </div>

</div></section>

<script>
$(function(){
  $('.select2').select2({theme:'bootstrap4', placeholder:'Pilih...'});

  $('#select_barang').on('change', function(){
    var $opt = $(this).find('option:selected');
    if (!$opt.val()) { $('#preview_card').slideUp(); return; }
    
    // Reset file input & new preview
    $('#foto_input').val('');
    $('#new_image_preview_container').slideUp();
    $('#new_image_preview').attr('src', '');

    var foto = $opt.data('foto');
    var kondisi = $opt.data('kondisi') || '-';
    var kategori = $opt.data('kategori') || '-';
    var merk = $opt.data('merk') || '-';
    var unit = $opt.data('unit') || '-';
    var ruang = $opt.data('ruang') || '-';
    var nama = $opt.data('nama') || $opt.text().trim();
    var kode = $opt.data('kode') || '';

    if (foto) {
      $('#preview_img').attr('src','../uploads/barang/'+foto);
    } else {
      $('#preview_img').attr('src','../uploads/barang/default.png');
    }
    $('#preview_nama').text(nama);
    $('#preview_kode').text(kode);
    $('#preview_kategori').text(kategori);
    $('#preview_merk').text(merk);
    $('#preview_unit').text(unit);
    $('#preview_ruang').text(ruang);
    $('#preview_kondisi').text(kondisi);
    // set id_barang pada form hapus DAN form upload
    $('#delete_id_barang').val($opt.val());
    $('#upload_id_barang').val($opt.val());
    // tampilkan tombol hapus hanya jika ada gambar
    if (foto) {
      $('#formDelete').show();
    } else {
      $('#formDelete').hide();
    }
    $('#preview_card').slideDown();
  });

  // Handle new file input preview
  $('#foto_input').on('change', function() {
    var fileInput = this;
    if (fileInput.files && fileInput.files[0]) {
      var reader = new FileReader();
      reader.onload = function(e) {
        $('#new_image_preview').attr('src', e.target.result);
        $('#new_image_preview_container').slideDown();
      };
      reader.readAsDataURL(fileInput.files[0]);
    } else {
      $('#new_image_preview_container').slideUp();
      $('#new_image_preview').attr('src', '');
    }
  });

  // Upload form validation and feedback
  $('#formUpload').on('submit', function(e){
    var barang = $('#select_barang').val();
    var fileInput = $('#foto_input')[0];
    var file = fileInput.files && fileInput.files.length > 0 ? fileInput.files[0] : null;

    if (!barang) {
      e.preventDefault();
      Swal.fire({icon:'warning', title:'Pilih barang', text:'Silakan pilih barang terlebih dahulu.'});
      return false;
    }
    if (!file) {
      e.preventDefault();
      Swal.fire({icon:'warning', title:'Pilih file', text:'Silakan pilih file gambar untuk diunggah.'});
      return false;
    }
    // show loading indicator
    Swal.fire({title:'Unggah...', text:'Sedang mengunggah dan mengompresi gambar. Tunggu sebentar.', allowOutsideClick:false, didOpen: () => { Swal.showLoading(); }});
    return true; // allow normal submit; server will redirect
  });
});
</script>

<?php include '../includes/footer.php'; ?>
