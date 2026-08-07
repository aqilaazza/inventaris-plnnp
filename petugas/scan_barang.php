<?php
session_start();
include '../config/database.php';

$page_title = 'Scan & Cek Barang';
$active_menu = 'scan_barang';

// Ambil Periode Pengecekan Aktif
$res_periode_aktif = $conn->query("SELECT * FROM periode_pengecekan WHERE status = 'aktif' ORDER BY id DESC LIMIT 1");
$periode_aktif = null;
if ($res_periode_aktif && $res_periode_aktif->num_rows > 0) {
    $periode_aktif = $res_periode_aktif->fetch_assoc();
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_sensus'])) {
    if (!$periode_aktif) {
        $_SESSION['error'] = 'Tidak ada periode pengecekan aktif saat ini!';
        header('Location: scan_barang.php');
        exit;
    }

    $id_periode = $periode_aktif['id'];
    $id_barang = intval($_POST['id_barang']);
    $id_petugas = $_SESSION['user_id'];
    $kondisi_temuan = $_POST['kondisi_temuan'];
    $catatan = trim($_POST['catatan'] ?? '');
    
    // Validasi barang aktif
    $res_barang = $conn->query("SELECT status_aktif, nama_barang FROM barang WHERE id = $id_barang");
    $barang_data = $res_barang->fetch_assoc();
    if (!$barang_data || $barang_data['status_aktif'] !== 'aktif') {
        $_SESSION['error'] = 'Barang tidak ditemukan atau sudah tidak aktif!';
        header('Location: scan_barang.php');
        exit;
    }

    $foto_name = null;
    $upload_ok = true;

    // Foto wajib jika Rusak atau Hilang
    if (in_array($kondisi_temuan, ['Rusak', 'Hilang'])) {
        if (!isset($_FILES['foto_bukti']) || $_FILES['foto_bukti']['error'] !== 0) {
            $_SESSION['error'] = 'Foto bukti wajib dilampirkan jika barang rusak atau hilang!';
            $upload_ok = false;
        } else {
            $tmp = $_FILES['foto_bukti']['tmp_name'];
            $info = @getimagesize($tmp);
            if (!$info || empty($info['mime'])) {
                $_SESSION['error'] = 'File bukan gambar yang valid!';
                $upload_ok = false;
            } else {
                $target_dir = "../uploads/bukti/";
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }

                // Check GD availability
                $gd_available = (function_exists('imagecreatefromjpeg') || function_exists('imagecreatefrompng') || function_exists('imagecreatefromgif') || function_exists('imagecreatefromwebp'));
                
                if (!$gd_available) {
                    // Fallback: move as-is
                    $file_ext = strtolower(pathinfo($_FILES["foto_bukti"]["name"], PATHINFO_EXTENSION));
                    $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
                    if (!in_array($file_ext, $allowed_exts)) {
                        $_SESSION['error'] = 'Format foto tidak valid. Gunakan JPG, JPEG, PNG, atau WEBP!';
                        $upload_ok = false;
                    } else {
                        $foto_name = "BUKTI_" . time() . "_" . $id_barang . "." . $file_ext;
                        $target_file = $target_dir . $foto_name;
                        if (!move_uploaded_file($tmp, $target_file)) {
                            $_SESSION['error'] = 'Gagal mengunggah foto bukti!';
                            $upload_ok = false;
                        }
                    }
                } else {
                    $mime = $info['mime'];
                    $src = null;
                    switch ($mime) {
                        case 'image/jpeg': $src = imagecreatefromjpeg($tmp); break;
                        case 'image/png': $src = imagecreatefrompng($tmp); break;
                        case 'image/gif': $src = imagecreatefromgif($tmp); break;
                        case 'image/webp':
                            if (function_exists('imagecreatefromwebp')) { $src = imagecreatefromwebp($tmp); break; }
                            $_SESSION['error'] = 'WebP tidak didukung oleh server ini.';
                            $upload_ok = false;
                            break;
                        default:
                            $_SESSION['error'] = 'Format foto tidak valid. Gunakan JPG, JPEG, PNG, atau WEBP!';
                            $upload_ok = false;
                    }

                    if ($upload_ok && $src) {
                        $width = imagesx($src);
                        $height = imagesy($src);
                        $maxDim = 1600;
                        $scale = 1;
                        if ($width > $maxDim || $height > $maxDim) {
                            $scale = min($maxDim / $width, $maxDim / $height);
                        }
                        $newW = max(1, (int)($width * $scale));
                        $newH = max(1, (int)($height * $scale));

                        $dst = imagecreatetruecolor($newW, $newH);
                        $white = imagecolorallocate($dst, 255, 255, 255);
                        imagefill($dst, 0, 0, $white);
                        imagecopyresampled($dst, $src, 0,0,0,0, $newW, $newH, $width, $height);
                        imagedestroy($src);

                        $maxBytes = 500 * 1024; // 500 KB
                        $tmpDir = sys_get_temp_dir();
                        $tempOut = tempnam($tmpDir, 'upimg_');
                        $quality = 90;
                        $saved = false;
                        while ($quality >= 10) {
                            imagejpeg($dst, $tempOut, $quality);
                            if (filesize($tempOut) <= $maxBytes) { $saved = true; break; }
                            $quality -= 10;
                        }

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
                            imagejpeg($srcForDownscale, $tempOut, 10);
                        }

                        $foto_name = "BUKTI_" . time() . "_" . $id_barang . ".jpg";
                        $target_file = $target_dir . $foto_name;

                        if (!rename($tempOut, $target_file)) {
                            if (!copy($tempOut, $target_file)) {
                                @unlink($tempOut);
                                imagedestroy($srcForDownscale);
                                $_SESSION['error'] = 'Gagal menyimpan file hasil kompresi.';
                                $upload_ok = false;
                            } else {
                                @unlink($tempOut);
                            }
                        }
                        imagedestroy($srcForDownscale);
                    }
                }
            }
        }
    }

    if ($upload_ok) {
        // Tentukan status_review: Baik -> disetujui (auto), Rusak/Hilang -> menunggu
        $status_review = ($kondisi_temuan === 'Baik') ? 'disetujui' : 'menunggu';
        
        $conn->begin_transaction();
        try {
            // Periksa jika barang sudah pernah dicek di periode ini oleh siapapun
            $stmt_check_exist = $conn->prepare("SELECT id, foto_bukti FROM pengecekan_barang WHERE id_periode = ? AND id_barang = ?");
            $stmt_check_exist->bind_param("ii", $id_periode, $id_barang);
            $stmt_check_exist->execute();
            $exist_res = $stmt_check_exist->get_result();
            $stmt_check_exist->close();

            if ($exist_res && $exist_res->num_rows > 0) {
                // Update pengecekan sebelumnya
                $old_data = $exist_res->fetch_assoc();
                $id_pengecekan = $old_data['id'];
                
                // Jika ada foto baru dan ada foto lama, hapus yang lama
                if ($foto_name !== null && !empty($old_data['foto_bukti'])) {
                    @unlink("../uploads/bukti/" . $old_data['foto_bukti']);
                }
                
                $final_foto = ($foto_name !== null) ? $foto_name : $old_data['foto_bukti'];

                $stmt_up = $conn->prepare("UPDATE pengecekan_barang SET id_petugas = ?, kondisi_temuan = ?, catatan = ?, foto_bukti = ?, status_review = ?, tgl_pengecekan = NOW() WHERE id = ?");
                $stmt_up->bind_param("issssi", $id_petugas, $kondisi_temuan, $catatan, $final_foto, $status_review, $id_pengecekan);
                $stmt_up->execute();
                $stmt_up->close();
            } else {
                // Insert baru
                $stmt_in = $conn->prepare("INSERT INTO pengecekan_barang (id_periode, id_barang, id_petugas, kondisi_temuan, catatan, foto_bukti, status_review) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt_in->bind_param("iiissss", $id_periode, $id_barang, $id_petugas, $kondisi_temuan, $catatan, $foto_name, $status_review);
                $stmt_in->execute();
                $stmt_in->close();
            }

            // Jika 'Baik' (auto-approved), update kondisi barang di tabel barang langsung
            if ($kondisi_temuan === 'Baik') {
                $stmt_brg = $conn->prepare("UPDATE barang SET kondisi = 'Baik' WHERE id = ?");
                $stmt_brg->bind_param("i", $id_barang);
                $stmt_brg->execute();
                $stmt_brg->close();
            }

            $conn->commit();
            
            if ($kondisi_temuan === 'Baik') {
                $_SESSION['success'] = 'Pengecekan barang "' . $barang_data['nama_barang'] . '" berhasil dikirim dan otomatis disetujui (Kondisi: Baik).';
            } else {
                $_SESSION['success'] = 'Pengecekan barang "' . $barang_data['nama_barang'] . '" berhasil dikirim dan menunggu persetujuan admin.';
            }
            $_SESSION['play_beep'] = true;
        } catch (Exception $e) {
            $conn->rollback();
            if ($foto_name !== null) {
                @unlink("../uploads/bukti/" . $foto_name);
            }
            $_SESSION['error'] = 'Terjadi kesalahan sistem: ' . $e->getMessage();
        }
    }
    header('Location: scan_barang.php');
    exit;
}

include '../includes/header_petugas.php';
include '../includes/sidebar_petugas.php';
?>

<style>
    .btn-flat-primary {
        background-color: #6366f1;
        color: white;
        border-radius: 8px;
        border: none;
        box-shadow: 0 4px 6px -1px rgba(99, 102, 241, 0.2);
        transition: all 0.2s;
    }
    .btn-flat-primary:hover {
        background-color: #4f46e5;
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 6px 12px -2px rgba(99, 102, 241, 0.3);
    }
    .btn-flat-danger {
        background-color: #ef4444;
        color: white;
        border-radius: 8px;
        border: none;
        box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.2);
        transition: all 0.2s;
    }
    .btn-flat-danger:hover {
        background-color: #dc2626;
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 6px 12px -2px rgba(239, 68, 68, 0.3);
    }
    .card-modern {
        border-radius: 15px !important;
        box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05) !important;
        border: none !important;
        overflow: hidden;
    }
    .card-modern .card-header {
        border-top-left-radius: 15px !important;
        border-top-right-radius: 15px !important;
        background-color: #fff !important;
        border-bottom: 1px solid #f3f4f6 !important;
    }
    .card-modern-header-indigo {
        background: linear-gradient(135deg, #6366f1, #4f46e5) !important;
        border-top-left-radius: 15px !important;
        border-top-right-radius: 15px !important;
        border: none !important;
    }
    .scan-input {
        font-size: 16px;
        text-align: center;
        letter-spacing: 1px;
        font-weight: 600;
        height: 48px;
        border-radius: 10px;
        border: 2px solid #6366f1;
        transition: all 0.2s;
    }
    .scan-input:focus {
        box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
        border-color: #4f46e5;
    }
</style>

<!-- Include HTML5-QRCode CDN -->
<script src="https://unpkg.com/html5-qrcode"></script>
<!-- SweetAlert2 -->
<script src="../plugins/sweetalert2/sweetalert2.all.min.js"></script>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 font-weight-bold" style="color: #1f2937;"><i class="fas fa-qrcode text-indigo mr-2"></i>Scan & Cek Barang</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Scan & Cek</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">

        <?php if (!$periode_aktif): ?>
            <div class="alert alert-warning" style="border-radius: 10px; border-left: 5px solid #d97706;">
                <h5><i class="icon fas fa-exclamation-triangle"></i> Perhatian!</h5>
                Saat ini tidak ada periode pengecekan barang yang sedang aktif. Anda tidak dapat melakukan pengecekan barang.
            </div>
        <?php else: ?>
            <div class="alert alert-info" style="border-radius: 10px; border-left: 5px solid #0ea5e9;">
                <i class="fas fa-info-circle mr-1"></i> 
                <strong>Periode Aktif:</strong> <?= htmlspecialchars($periode_aktif['nama_periode']) ?>
                (Batas akhir: <?= date('d M Y', strtotime($periode_aktif['tgl_selesai'])) ?>)
            </div>

            <div class="row">
                <!-- Scanner / Input Manual -->
                <div class="col-md-5 col-12 mb-4">
                    <div class="card h-100 card-modern">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title font-weight-bold text-dark mb-0"><i class="fas fa-qrcode mr-2 text-indigo"></i>Identifikasi Aset</h3>
                        </div>
                        <div class="card-body">
                            <!-- Container Kamera -->
                            <div class="p-3 bg-dark mb-4" id="cameraContainer" style="border-radius: 12px; display: none;">
                                <div id="reader" style="width: 100%; border-radius: 8px; overflow: hidden; background:#000;"></div>
                            </div>

                            <div class="text-center mb-4">
                                <button type="button" class="btn btn-flat-primary" id="btn-toggle-cam" style="padding: 10px 20px;" onclick="toggleCamera()">
                                    <i class="fas fa-camera mr-1"></i> Mulai Kamera Scanner
                                </button>
                            </div>

                            <hr class="my-4">

                            <!-- Form Input Manual -->
                            <form id="form-manual" onsubmit="searchManual(event)">
                                <div class="form-group text-center">
                                    <label for="input-code" class="text-muted mb-2">Atau masukkan Kode Barang secara manual</label>
                                    <div class="input-group">
                                        <input type="text" id="input-code" class="form-control scan-input" placeholder="Contoh: 00005 atau 5" style="border-top-right-radius: 0; border-bottom-right-radius: 0;" required>
                                        <div class="input-group-append">
                                            <button type="submit" class="btn btn-flat-primary px-4" style="border-top-left-radius: 0; border-bottom-left-radius: 0; border-top-right-radius: 10px; border-bottom-right-radius: 10px;"><i class="fas fa-search mr-1"></i> Cari</button>
                                        </div>
                                    </div>
                                    <small class="form-text text-muted mt-2">Scan QR-code di barang atau ketikkan 5 digit kode barang.</small>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Detail Barang & Form Pengecekan -->
                <div class="col-md-7 col-12 mb-4">
                    <div id="item-details-card" class="card h-100 card-modern" style="display: none;">
                        <div class="card-header text-white py-3 card-modern-header-indigo">
                            <div class="d-flex align-items-center flex-wrap">
                                <span class="mr-3 mb-2" style="display:inline-flex; align-items:center; justify-content:center; width:40px; height:40px; background:rgba(255,255,255,0.2); border-radius:10px;">
                                    <i class="fas fa-clipboard-list" style="font-size:18px;"></i>
                                </span>
                                <div class="flex-grow-1 min-w-0">
                                    <h5 class="card-title font-weight-bold text-white mb-1" id="detail-item-title" style="word-break:break-word;">-</h5>
                                    <div class="text-xs" style="color:rgba(255,255,255,0.85); word-break:break-all;">
                                        <i class="fas fa-qrcode mr-1"></i> Kode: <strong id="detail-item-code">-</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Info Barang Grid -->
                            <div class="row mb-4 p-3 bg-light" style="border-radius:10px;">
                                <div class="col-md-4 col-12 text-center mb-3 mb-md-0" id="detail-foto-container">
                                    <!-- Foto placeholder -->
                                </div>
                                <div class="col-md-8 col-12">
                                    <div class="row" style="font-size:13px;">
                                        <div class="col-6 mb-2">
                                            <span class="text-muted d-block text-xs uppercase font-weight-bold">Kategori</span>
                                            <span id="detail-kategori" class="font-weight-bold text-dark">-</span>
                                        </div>
                                        <div class="col-6 mb-2">
                                            <span class="text-muted d-block text-xs uppercase font-weight-bold">Merk</span>
                                            <span id="detail-merk" class="font-weight-bold text-dark">-</span>
                                        </div>
                                        <div class="col-6 mb-2">
                                            <span class="text-muted d-block text-xs uppercase font-weight-bold">Kondisi Terakhir</span>
                                            <span id="detail-kondisi" class="badge py-1 px-2 font-weight-bold">-</span>
                                        </div>
                                        <div class="col-6 mb-2">
                                            <span class="text-muted d-block text-xs uppercase font-weight-bold">Status Aktif</span>
                                            <span id="detail-status" class="badge py-1 px-2 font-weight-bold">-</span>
                                        </div>
                                        <div class="col-12 mt-1">
                                            <span class="text-muted d-block text-xs uppercase font-weight-bold">Lokasi Penempatan</span>
                                            <span id="detail-lokasi" class="text-dark font-weight-bold"><i class="fas fa-map-marker-alt text-danger mr-1"></i> -</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Alert status pengecekan jika barang sudah pernah dicek di periode aktif -->
                            <div id="check-alert-info" class="alert alert-info py-2 px-3 mb-3 text-sm" style="display:none; border-radius:10px;">
                                <i class="fas fa-info-circle mr-1"></i> <span id="check-alert-text">Barang ini sudah dicek sebelumnya. Anda dapat memperbarui data pengecekan di bawah jika diperlukan.</span>
                            </div>

                            <!-- Form input sensus -->
                            <form method="POST" action="scan_barang.php" enctype="multipart/form-data" id="sensus-form">
                                <input type="hidden" name="id_barang" id="form-id-barang">
                                
                                <div class="form-group">
                                    <label class="font-weight-bold" style="font-size:13px;">Kondisi Fisik Saat Ini <span class="text-danger">*</span></label>
                                    <div class="row">
                                        <div class="col-4">
                                            <label class="btn btn-outline-success btn-block py-2" style="border-radius:10px; cursor:pointer;">
                                                <input type="radio" name="kondisi_temuan" value="Baik" checked onclick="handleKondisiChange('Baik')" style="position:absolute; opacity:0;">
                                                <i class="fas fa-check-circle mr-1"></i> Baik
                                            </label>
                                        </div>
                                        <div class="col-4">
                                            <label class="btn btn-outline-warning btn-block py-2" style="border-radius:10px; cursor:pointer;">
                                                <input type="radio" name="kondisi_temuan" value="Rusak" onclick="handleKondisiChange('Rusak')" style="position:absolute; opacity:0;">
                                                <i class="fas fa-exclamation-triangle mr-1"></i> Rusak
                                            </label>
                                        </div>
                                        <div class="col-4">
                                            <label class="btn btn-outline-danger btn-block py-2" style="border-radius:10px; cursor:pointer;">
                                                <input type="radio" name="kondisi_temuan" value="Hilang" onclick="handleKondisiChange('Hilang')" style="position:absolute; opacity:0;">
                                                <i class="fas fa-times-circle mr-1"></i> Hilang
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Photo Upload (Wajib jika rusak/hilang) -->
                                <div class="form-group" id="photo-upload-group" style="display: none;">
                                    <label class="font-weight-bold text-danger" style="font-size:13px;">Lampirkan Foto Bukti <span class="text-danger">*</span></label>
                                    <div class="mb-2" id="preview_bukti_foto"></div>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" name="foto_bukti" id="foto_bukti" accept="image/*" onchange="previewUpload(this, 'preview_bukti_foto')">
                                        <label class="custom-file-label" for="foto_bukti">Ambil Foto / Pilih File...</label>
                                    </div>
                                    <small class="form-text text-muted text-danger font-weight-bold"><i class="fas fa-info-circle mr-1"></i> Melampirkan foto bukti kondisi fisik wajib untuk temuan barang rusak atau hilang.</small>
                                </div>

                                <div class="form-group">
                                    <label class="font-weight-bold" style="font-size:13px;">Catatan Temuan Fisik</label>
                                    <textarea class="form-control" name="catatan" id="form-catatan" rows="3" placeholder="Tuliskan keterangan detail kondisi fisik barang, merk cadangan, nomor seri, atau detail kerusakan..."></textarea>
                                </div>

                                <div class="mt-4">
                                    <button type="submit" name="submit_sensus" class="btn btn-flat-primary btn-block py-2" style="border-radius:10px; font-weight:700; font-size:14px;">
                                        <i class="fas fa-paper-plane mr-2"></i> Kirim Laporan
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>
</section>

<script>
// Keep track of scanner status
let html5QrcodeScanner = null;
let cameraRunning = false;

// Fungsi untuk memutar suara beep menggunakan Web Audio API (premium & cross-browser)
function playBeep() {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioCtx.createOscillator();
        const gainNode = audioCtx.createGain();

        oscillator.connect(gainNode);
        gainNode.connect(audioCtx.destination);

        oscillator.type = 'sine';
        oscillator.frequency.setValueAtTime(800, audioCtx.currentTime); // Frekuensi 800Hz
        
        // Envelope volume beep pendek
        gainNode.gain.setValueAtTime(0, audioCtx.currentTime);
        gainNode.gain.linearRampToValueAtTime(0.8, audioCtx.currentTime + 0.05);
        gainNode.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.25);

        oscillator.start(audioCtx.currentTime);
        oscillator.stop(audioCtx.currentTime + 0.25);
    } catch(e) {
        console.log("AudioContext not supported", e);
    }
}

$(function() {
    // Show SweetAlert2 Toast for Session Messages
    <?php if (isset($_SESSION['success'])): ?>
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: '<?= addslashes($_SESSION['success']) ?>',
            showConfirmButton: false,
            timer: 3500,
            timerProgressBar: true
        });
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'error',
            title: '<?= addslashes($_SESSION['error']) ?>',
            showConfirmButton: false,
            timer: 4000,
            timerProgressBar: true
        });
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['play_beep'])): ?>
        playBeep();
        <?php unset($_SESSION['play_beep']); ?>
    <?php endif; ?>

    // Show filename on custom file input
    $(document).on('change', '.custom-file-input', function() {
        let fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').addClass("selected").html(fileName);
    });

    // Auto focus ke input manual saat load
    $('#input-code').focus();

    // Tampilkan indikator loading premium SweetAlert2 saat submit form sensus (sangat berguna ketika kompresi gambar sedang berjalan)
    $('#sensus-form').on('submit', function() {
        Swal.fire({
            title: 'Memproses...',
            text: 'Sedang menyimpan laporan pengecekan dan mengompresi gambar bukti. Silakan tunggu.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        return true;
    });
});

function toggleCamera() {
    if (cameraRunning) {
        stopCamera();
    } else {
        startCamera();
    }
}

function startCamera() {
    $('#cameraContainer').slideDown();
    document.getElementById('btn-toggle-cam').innerHTML = '<i class="fas fa-stop-circle mr-1"></i> Matikan Kamera Scanner';
    document.getElementById('btn-toggle-cam').classList.replace('btn-flat-primary', 'btn-danger');
    
    html5QrcodeScanner = new Html5Qrcode("reader");
    const config = { fps: 10, qrbox: { width: 250, height: 250 } };
    
    html5QrcodeScanner.start(
        { facingMode: "environment" }, 
        config,
        onScanSuccess,
        onScanFailure
    ).then(() => {
        cameraRunning = true;
    }).catch(err => {
        console.error("Camera access failed:", err);
        Swal.fire({
            title: 'Kamera Gagal Diakses',
            text: 'Pastikan situs ini memiliki izin kamera dan perangkat Anda mendukung input kamera!',
            icon: 'error',
            confirmButtonColor: '#4c1d95'
        });
        stopCamera();
    });
}

function stopCamera() {
    if (html5QrcodeScanner) {
        html5QrcodeScanner.stop().then(() => {
            html5QrcodeScanner = null;
            cameraRunning = false;
        }).catch(err => {
            console.error("Error stopping camera:", err);
            html5QrcodeScanner = null;
            cameraRunning = false;
        });
    }
    $('#cameraContainer').slideUp();
    document.getElementById('btn-toggle-cam').innerHTML = '<i class="fas fa-camera mr-1"></i> Mulai Kamera Scanner';
    document.getElementById('btn-toggle-cam').classList.replace('btn-danger', 'btn-flat-primary');
    cameraRunning = false;
}

function onScanSuccess(decodedText, decodedResult) {
    // Putar suara beep sukses
    playBeep();
    
    console.log(`Scan result: ${decodedText}`);
    stopCamera();
    
    // Fetch details
    fetchItemDetails(decodedText);
}

function onScanFailure(error) {
    // Silence scan failures to prevent high verbose logs
}

function searchManual(e) {
    e.preventDefault();
    const code = document.getElementById('input-code').value;
    fetchItemDetails(code);
}

function fetchItemDetails(code) {
    Swal.fire({
        title: 'Mencari Barang...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    $.getJSON('get_barang.php', { code: code }, function(response) {
        Swal.close();
        if (response.success) {
            const data = response.data;
            
            // Populate details card
            document.getElementById('detail-item-title').innerText = data.nama_barang;
            document.getElementById('detail-item-code').innerText = data.formatted_code;
            document.getElementById('detail-kategori').innerText = data.nama_kategori || '-';
            document.getElementById('detail-merk').innerText = data.nama_merk || '-';
            
            // Format Kondisi badge
            const kondisiBadge = document.getElementById('detail-kondisi');
            kondisiBadge.innerText = data.kondisi;
            kondisiBadge.className = 'badge py-1 px-2 font-weight-bold';
            if (data.kondisi === 'Baik') kondisiBadge.classList.add('badge-success');
            else if (data.kondisi === 'Rusak') kondisiBadge.classList.add('badge-warning');
            else if (data.kondisi === 'Hilang') kondisiBadge.classList.add('badge-danger');
            
            // Format Status badge
            const statusBadge = document.getElementById('detail-status');
            statusBadge.innerText = data.status_aktif === 'aktif' ? 'Aktif' : 'Nonaktif';
            statusBadge.className = 'badge py-1 px-2 font-weight-bold';
            statusBadge.classList.add(data.status_aktif === 'aktif' ? 'badge-success' : 'badge-secondary');
            
            // Location
            document.getElementById('detail-lokasi').innerHTML = `<i class="fas fa-map-marker-alt text-danger mr-1"></i> ${data.nama_unit || '-'} &rarr; ${data.nama_ruang || '-'}`;
            
            // Foto barang
            const fotoContainer = document.getElementById('detail-foto-container');
            if (data.foto) {
                fotoContainer.innerHTML = `<img src="../dist/img/barang/${data.foto}" class="img-fluid rounded-lg shadow-sm" style="max-height:120px; object-fit:cover;">`;
            } else {
                fotoContainer.innerHTML = `
                    <div class="bg-indigo-100 text-indigo d-flex align-items-center justify-content-center mx-auto" style="width:100px; height:100px; border-radius:12px; background: #e0e7ff; color: #4338ca;">
                        <i class="fas fa-box" style="font-size:40px;"></i>
                    </div>
                `;
            }
            
            // Set form ID
            document.getElementById('form-id-barang').value = data.id;
            
            // Show details panel
            document.getElementById('item-details-card').style.display = 'block';
            
            // Auto-scroll ke formulir secara mulus agar petugas tidak perlu scroll manual
            setTimeout(() => {
                const formCard = document.getElementById('item-details-card');
                if (formCard) {
                    formCard.scrollIntoView({ 
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }, 100);
            
            // Reset checking form state
            document.getElementById('sensus-form').reset();
            // Trigger radio button default styling
            handleKondisiChange('Baik');
            
            // Check if item is already inspected in this period (Optional Premium check via AJAX)
            checkInspectedStatus(data.id);
            
        } else {
            Swal.fire({
                title: 'Gagal Identifikasi',
                text: response.message,
                icon: 'warning',
                confirmButtonColor: '#4c1d95'
            });
        }
    }).fail(function() {
        Swal.close();
        Swal.fire({
            title: 'Koneksi Bermasalah',
            text: 'Gagal terhubung dengan server untuk mengambil detail barang.',
            icon: 'error',
            confirmButtonColor: '#4c1d95'
        });
    });
}

function checkInspectedStatus(barangId) {
    $.getJSON('check_status_sensus.php', { barang_id: barangId }, function(res) {
        if (res.already_checked) {
            document.getElementById('check-alert-text').innerHTML = `Barang ini sudah pernah dicek oleh <strong>${res.petugas}</strong> pada <strong>${res.tanggal}</strong> dengan temuan <strong>${res.kondisi}</strong>. Mengisi form ini akan memperbarui hasil pengecekan tersebut.`;
            document.getElementById('check-alert-info').style.display = 'block';
        } else {
            document.getElementById('check-alert-info').style.display = 'none';
        }
    }).fail(function() {
        document.getElementById('check-alert-info').style.display = 'none';
    });
}

function handleKondisiChange(kondisi) {
    const photoGroup = document.getElementById('photo-upload-group');
    const fileInput = document.getElementById('foto_bukti');
    
    // Reset buttons outline classes
    const radioButtons = document.querySelectorAll('input[name="kondisi_temuan"]');
    radioButtons.forEach(btn => {
        const parentLabel = btn.closest('label');
        if (btn.value === 'Baik') {
            parentLabel.className = btn.checked ? 'btn btn-success btn-block py-2' : 'btn btn-outline-success btn-block py-2';
        } else if (btn.value === 'Rusak') {
            parentLabel.className = btn.checked ? 'btn btn-warning btn-block py-2' : 'btn btn-outline-warning btn-block py-2';
        } else if (btn.value === 'Hilang') {
            parentLabel.className = btn.checked ? 'btn btn-danger btn-block py-2' : 'btn btn-outline-danger btn-block py-2';
        }
    });

    if (kondisi === 'Rusak' || kondisi === 'Hilang') {
        photoGroup.style.display = 'block';
        fileInput.setAttribute('required', 'required');
    } else {
        photoGroup.style.display = 'none';
        fileInput.removeAttribute('required');
        
        // Reset file upload
        fileInput.value = '';
        $(fileInput).next('.custom-file-label').removeClass("selected").html("Ambil Foto / Pilih File...");
        document.getElementById('preview_bukti_foto').innerHTML = '';
    }
}

function previewUpload(input, previewId) {
    const preview = document.getElementById(previewId);
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `<img src="${e.target.result}" class="img-thumbnail mt-2" style="height:120px; border-radius:8px; display:block; object-fit:cover;">`;
        }
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.innerHTML = '';
    }
}
</script>

<?php include '../includes/footer.php'; ?>
