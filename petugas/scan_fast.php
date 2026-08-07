<?php
session_start();
include '../config/database.php';

$page_title = 'Cek Barang Mode Fast';
$active_menu = 'scan_fast';

// Handle AJAX Request untuk mendapatkan detail barang
if (isset($_GET['ajax_get_barang'])) {
    header('Content-Type: application/json');
    $id = intval($_GET['ajax_get_barang']);
    $res = $conn->query("
        SELECT b.nama_barang, b.status_aktif, u.nama_unit, r.nama_ruang 
        FROM barang b 
        LEFT JOIN unit u ON b.id_unit = u.id 
        LEFT JOIN ruang r ON b.id_ruang = r.id 
        WHERE b.id = $id
    ");
    if ($res && $res->num_rows > 0) {
        echo json_encode(['success' => true, 'data' => $res->fetch_assoc()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Barang tidak ditemukan']);
    }
    exit;
}

// Ambil Periode Pengecekan Aktif
$res_periode_aktif = $conn->query("SELECT * FROM periode_pengecekan WHERE status = 'aktif' ORDER BY id DESC LIMIT 1");
$periode_aktif = null;
if ($res_periode_aktif && $res_periode_aktif->num_rows > 0) {
    $periode_aktif = $res_periode_aktif->fetch_assoc();
}

// Handle Form Submission (Mode Fast)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_fast'])) {
    if (!$periode_aktif) {
        $_SESSION['error'] = 'Tidak ada periode pengecekan aktif saat ini!';
        header('Location: scan_fast.php');
        exit;
    }

    $id_periode = $periode_aktif['id'];
    $id_barang = intval($_POST['id_barang']);
    $id_petugas = $_SESSION['user_id'];
    $kondisi_temuan = 'Baik'; // Auto set Baik
    $catatan = ''; // Auto kosong
    $foto_name = null; // Auto null
    $status_review = 'disetujui'; // Auto disetujui

    // Validasi barang aktif
    $res_barang = $conn->query("SELECT status_aktif, nama_barang FROM barang WHERE id = $id_barang");
    $barang_data = $res_barang->fetch_assoc();
    
    if (!$barang_data || $barang_data['status_aktif'] !== 'aktif') {
        $_SESSION['error'] = 'Barang tidak ditemukan atau sudah tidak aktif!';
        header('Location: scan_fast.php');
        exit;
    }

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
            
            // Hapus foto lama jika ada karena mode fast tidak pakai foto
            if (!empty($old_data['foto_bukti'])) {
                @unlink("../uploads/bukti/" . $old_data['foto_bukti']);
            }

            $stmt_up = $conn->prepare("UPDATE pengecekan_barang SET id_petugas = ?, kondisi_temuan = ?, catatan = ?, foto_bukti = ?, status_review = ?, tgl_pengecekan = NOW() WHERE id = ?");
            $stmt_up->bind_param("issssi", $id_petugas, $kondisi_temuan, $catatan, $foto_name, $status_review, $id_pengecekan);
            $stmt_up->execute();
            $stmt_up->close();
        } else {
            // Insert baru
            $stmt_in = $conn->prepare("INSERT INTO pengecekan_barang (id_periode, id_barang, id_petugas, kondisi_temuan, catatan, foto_bukti, status_review) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt_in->bind_param("iiissss", $id_periode, $id_barang, $id_petugas, $kondisi_temuan, $catatan, $foto_name, $status_review);
            $stmt_in->execute();
            $stmt_in->close();
        }

        // Update kondisi barang di tabel barang langsung ke 'Baik'
        $stmt_brg = $conn->prepare("UPDATE barang SET kondisi = 'Baik' WHERE id = ?");
        $stmt_brg->bind_param("i", $id_barang);
        $stmt_brg->execute();
        $stmt_brg->close();

        $conn->commit();
        
        $_SESSION['success_fast'] = 'Pengecekan barang "' . htmlspecialchars($barang_data['nama_barang']) . '" berhasil (Kondisi: Baik).';
        // Set flag untuk trigger suara beep di halaman
        $_SESSION['play_beep'] = true;

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = 'Terjadi kesalahan sistem: ' . $e->getMessage();
    }
    header('Location: scan_fast.php');
    exit;
}

include '../includes/header_petugas.php';
include '../includes/sidebar_petugas.php';
?>

<!-- Base64 Beep Sound -->
<audio id="beepSound" preload="auto">
  <source src="data:audio/mp3;base64,//OExAAAAANIAAAAAExBTUUzLjEwMKqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqq//OExEAAANIAAAAAExBTUUzLjEwMKqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqq" type="audio/mpeg">
  <!-- Note: This is a placeholder valid mp3 frame, we will replace this with a generated simple beep via JS to ensure it works cross-browser without external files -->
</audio>

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
    }
    .camera-container {
        position: relative;
        width: 100%;
        max-width: 400px;
        margin: 0 auto;
        border-radius: 12px;
        overflow: hidden;
        background: #000;
        display: none; /* Disembunyikan awalnya */
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
    }
    #preview {
        width: 100%;
        height: auto;
        display: block;
    }
    .scanner-overlay {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.4);
        display: flex;
        align-items: center;
        justify-content: center;
        pointer-events: none;
    }
    .scanner-box {
        width: 70%;
        height: 70%;
        border: 2px solid #10b981;
        border-radius: 10px;
        position: relative;
        box-shadow: 0 0 0 1000px rgba(0,0,0,0.5);
    }
    .fast-input {
        font-size: 24px;
        text-align: center;
        letter-spacing: 2px;
        font-weight: 600;
        height: 60px;
        border-radius: 12px;
        border: 2px solid #6366f1;
    }
    .fast-input:focus {
        box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
        border-color: #4f46e5;
    }
</style>

    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 font-weight-bold" style="color: #1f2937;"><i class="fas fa-bolt text-warning mr-2"></i>Cek Barang Mode Fast</h1>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <!-- Peringatan jika tidak ada periode aktif -->
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
                
                <div class="row justify-content-center">
                    <div class="col-md-6 col-12">
                        <div class="card card-outline card-warning" style="border-radius: 15px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);">
                            <div class="card-header bg-white" style="border-top-left-radius: 15px; border-top-right-radius: 15px;">
                                <h3 class="card-title font-weight-bold text-center w-100">Scan QR Code / Input ID</h3>
                            </div>
                            <div class="card-body">
                                
                                <?php if (isset($_SESSION['error'])): ?>
                                    <div class="alert alert-danger" style="border-radius: 8px;">
                                        <i class="fas fa-times-circle mr-1"></i> <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Container Kamera -->
                                <div class="p-3 bg-dark mb-4" id="cameraContainer" style="border-radius: 12px; display: none;">
                                    <div id="reader" style="width: 100%; border-radius: 8px; overflow: hidden; background:#000;"></div>
                                </div>

                                <div class="text-center mb-4">
                                    <button type="button" class="btn btn-flat-primary" id="btnToggleCamera" style="padding: 10px 20px;" onclick="toggleCamera()">
                                        <i class="fas fa-camera mr-1"></i> Mulai Kamera Scanner
                                    </button>
                                </div>

                                <!-- Form Input Manual / Penampung Hasil Scan -->
                                <form id="fastSubmitForm" action="scan_fast.php" method="POST">
                                    <input type="hidden" name="submit_fast" value="1">
                                    <div class="form-group text-center">
                                        <label for="manual_id" class="text-muted mb-2">Atau masukkan ID Barang secara manual & tekan Enter</label>
                                        <input type="text" id="manual_id" class="form-control fast-input" placeholder="Masukkan ID / Barcode" autocomplete="off" autofocus>
                                        <input type="hidden" name="id_barang" id="hidden_id_barang">
                                    </div>
                                </form>

                                <div class="text-center text-muted mt-4">
                                    <small><i class="fas fa-bolt text-warning"></i> Mode Fast: Barang yang di-scan akan otomatis berstatus <strong>Baik</strong>.</small>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

<!-- Scripts -->
<script src="../plugins/jquery/jquery.min.js"></script>
<script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../dist/js/adminlte.min.js"></script>
<!-- HTML5 QR Code -->
<script src="https://unpkg.com/html5-qrcode"></script>
<!-- SweetAlert2 -->
<script src="../plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
// Fungsi untuk memutar suara beep menggunakan Web Audio API (cross-browser compatible tanpa file eksternal)
function playBeep() {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioCtx.createOscillator();
        const gainNode = audioCtx.createGain();

        oscillator.connect(gainNode);
        gainNode.connect(audioCtx.destination);

        oscillator.type = 'sine';
        oscillator.frequency.setValueAtTime(800, audioCtx.currentTime); // Frekuensi (800Hz)
        
        // Envelope untuk suara beep pendek
        gainNode.gain.setValueAtTime(0, audioCtx.currentTime);
        gainNode.gain.linearRampToValueAtTime(1, audioCtx.currentTime + 0.05);
        gainNode.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.3);

        oscillator.start(audioCtx.currentTime);
        oscillator.stop(audioCtx.currentTime + 0.3);
    } catch(e) {
        console.log("AudioContext not supported", e);
    }
}

let html5QrcodeScanner = null;
let cameraActive = false;

function toggleCamera() {
    if (cameraActive) {
        stopCamera();
    } else {
        startCamera();
    }
}

function startCamera() {
    $('#cameraContainer').slideDown();
    $('#btnToggleCamera').removeClass('btn-flat-primary').addClass('btn-danger').html('<i class="fas fa-stop-circle mr-1"></i> Matikan Kamera Scanner');
    
    html5QrcodeScanner = new Html5Qrcode("reader");
    const config = { fps: 10, qrbox: { width: 250, height: 250 } };
    
    html5QrcodeScanner.start(
        { facingMode: "environment" }, 
        config,
        function(decodedText, decodedResult) {
            // onScanSuccess
            stopCamera();
            // Call our fast handler
            handleScanResult(decodedText);
        },
        function(error) {
            // onScanFailure - silence
        }
    ).then(() => {
        cameraActive = true;
    }).catch(err => {
        console.error("Camera access failed:", err);
        Swal.fire('Error', 'Gagal mengakses kamera. Pastikan izin kamera diberikan.', 'error');
        stopCamera();
    });
}

function stopCamera() {
    if (html5QrcodeScanner) {
        html5QrcodeScanner.stop().then(() => {
            html5QrcodeScanner = null;
            cameraActive = false;
        }).catch(err => {
            html5QrcodeScanner = null;
            cameraActive = false;
        });
    }
    $('#cameraContainer').slideUp();
    $('#btnToggleCamera').removeClass('btn-danger').addClass('btn-flat-primary').html('<i class="fas fa-camera mr-1"></i> Mulai Kamera Scanner');
    cameraActive = false;
}

$(document).ready(function() {
    // Handle session success & play beep
    <?php if (isset($_SESSION['success_fast'])): ?>
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: '<?= addslashes($_SESSION['success_fast']) ?>',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
        <?php unset($_SESSION['success_fast']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['play_beep'])): ?>
        playBeep();
        <?php unset($_SESSION['play_beep']); ?>
    <?php endif; ?>

    // Fokus ke input manual saat load
    $('#manual_id').focus();

    // Handle Input Manual Enter
    $('#manual_id').on('keypress', function(e) {
        if (e.which == 13) { // Enter key
            e.preventDefault();
            let val = $(this).val().trim();
            if (val !== '') {
                handleScanResult(val);
            }
        }
    });

});

// Fungsi pemroses hasil scan/input
function handleScanResult(code) {
    // Hilangkan leading zeros yang mungkin ada jika format kode berbeda
    let idBarang = parseInt(code, 10);
    
    if (isNaN(idBarang) || idBarang <= 0) {
        Swal.fire('Error', 'ID Barang tidak valid!', 'error');
        $('#manual_id').val('').focus();
        return;
    }

    // Ambil detail barang via AJAX
    Swal.fire({
        title: 'Mencari...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    $.ajax({
        url: 'scan_fast.php?ajax_get_barang=' + idBarang,
        method: 'GET',
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                let b = res.data;
                if (b.status_aktif !== 'aktif') {
                    Swal.fire('Error', 'Barang ini sudah tidak aktif/dihapus.', 'error');
                    $('#manual_id').val('').focus();
                    return;
                }
                
                let ruang = b.nama_ruang || '-';
                let unit = b.nama_unit || '-';

                Swal.fire({
                    title: 'Konfirmasi Mode Fast',
                    html: `
                        <div style="text-align:left; background:#f3f4f6; padding:15px; border-radius:10px; margin-bottom:15px;">
                            <div style="font-size:12px; color:#6b7280;">ID / Kode Barang:</div>
                            <div style="font-weight:bold; margin-bottom:8px;">${String(idBarang).padStart(5, '0')}</div>
                            <div style="font-size:12px; color:#6b7280;">Nama Barang:</div>
                            <div style="font-weight:bold; margin-bottom:8px;">${b.nama_barang}</div>
                            <div style="font-size:12px; color:#6b7280;">Lokasi:</div>
                            <div style="font-weight:bold;">${unit} &rarr; ${ruang}</div>
                        </div>
                        Simpan barang ini dengan status <strong>Baik</strong>?
                    `,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#10b981',
                    cancelButtonColor: '#ef4444',
                    confirmButtonText: '<i class="fas fa-check mr-1"></i> Ya, Simpan!',
                    cancelButtonText: 'Batal',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        $('#hidden_id_barang').val(idBarang);
                        $('#fastSubmitForm').submit();
                    } else {
                        $('#manual_id').val('').focus();
                    }
                });
            } else {
                Swal.fire('Error', 'Barang tidak ditemukan!', 'error');
                $('#manual_id').val('').focus();
            }
        },
        error: function() {
            Swal.fire('Error', 'Gagal menghubungi server.', 'error');
            $('#manual_id').val('').focus();
        }
    });
}
</script>
</body>
</html>
