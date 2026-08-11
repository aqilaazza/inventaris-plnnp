<?php
/**
 * API Bridge untuk Aplikasi Flutter Inventaris Petugas
 * File ini TIDAK mengubah kode web atau database yang ada.
 * Hanya menyediakan endpoint REST JSON untuk aplikasi mobile.
 * 
 * Endpoint:
 *   ?action=login          (POST) - Login petugas
 *   ?action=dashboard      (GET)  - Data dashboard
 *   ?action=get_barang     (GET)  - Cari barang by kode
 *   ?action=check_status   (GET)  - Cek status pengecekan barang
 *   ?action=submit_pengecekan (POST) - Kirim pengecekan
 *   ?action=riwayat        (GET)  - Riwayat pengecekan
 *   ?action=riwayat_kalender (GET) - Riwayat pengecekan per bulan (untuk kalender)
 *   ?action=profil         (GET)  - Data profil petugas
 *   ?action=list_barang_all(GET)  - Daftar semua barang untuk dropdown update gambar
 *   ?action=update_gambar  (POST) - Unggah / Hapus foto barang (Update Gambar Barang)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include '../config/database.php';

$action = $_GET['action'] ?? '';

// ============================================================
// Helper: Authenticate request via Authorization header
// Format: "Bearer user_id:token_hash"
// ============================================================
function authenticate($conn) {
    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    if (empty($auth) || !str_starts_with($auth, 'Bearer ')) {
        return null;
    }
    
    $token = substr($auth, 7);
    $parts = explode(':', $token, 2);
    if (count($parts) !== 2) return null;
    
    $user_id = intval($parts[0]);
    $token_hash = $parts[1];
    
    // Verify token
    $stmt = $conn->prepare("SELECT id, username, nama_lengkap, level FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    if ($result->num_rows === 0) return null;
    
    $user = $result->fetch_assoc();
    
    // Verify hash (simple: sha256 of id + username + secret)
    $expected = hash('sha256', $user['id'] . ':' . $user['username'] . ':inventaris_secret_2026');
    if (!hash_equals($expected, $token_hash)) return null;
    
    return $user;
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError($message, $code = 400) {
    jsonResponse(['success' => false, 'message' => $message], $code);
}

// ============================================================
// ACTION: login
// ============================================================
if ($action === 'login') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('Method not allowed', 405);
    }
    
    // Accept both form-data and JSON body
    $input = json_decode(file_get_contents('php://input'), true);
    $username = trim($input['username'] ?? $_POST['username'] ?? '');
    $password = $input['password'] ?? $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        jsonError('Username dan password harus diisi!');
    }
    
    $stmt = $conn->prepare("SELECT id, username, password, nama_lengkap, level FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows !== 1) {
        jsonError('Username tidak ditemukan!', 401);
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (!password_verify($password, $user['password'])) {
        jsonError('Password yang Anda masukkan salah!', 401);
    }
    
    if ($user['level'] !== 'petugas') {
        jsonError('Akun ini bukan akun petugas!', 403);
    }
    
    // Generate token
    $token_hash = hash('sha256', $user['id'] . ':' . $user['username'] . ':inventaris_secret_2026');
    $token = $user['id'] . ':' . $token_hash;
    
    jsonResponse([
        'success' => true,
        'message' => 'Login berhasil!',
        'data' => [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'nama_lengkap' => $user['nama_lengkap'],
            'level' => $user['level'],
            'token' => $token,
        ]
    ]);
}

// ============================================================
// All other actions require authentication
// ============================================================
$currentUser = authenticate($conn);
if (!$currentUser) {
    jsonError('Sesi tidak valid. Silakan login kembali.', 401);
}

$userId = (int)$currentUser['id'];

// ============================================================
// ACTION: dashboard
// ============================================================
if ($action === 'dashboard') {
    // Periode aktif
    $res = $conn->query("SELECT * FROM periode_pengecekan WHERE status = 'aktif' ORDER BY id DESC LIMIT 1");
    $periode_aktif = null;
    $total_checked_petugas = 0;
    $total_barang_aktif = 0;
    $pct_checked = 0;
    
    if ($res && $res->num_rows > 0) {
        $periode_aktif = $res->fetch_assoc();
        $id_periode = (int)$periode_aktif['id'];
        
        // Total barang aktif
        $total_barang_aktif = (int)$conn->query("SELECT COUNT(*) as total FROM barang WHERE status_aktif = 'aktif'")->fetch_assoc()['total'];
        
        // Total checked by this petugas
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM pengecekan_barang WHERE id_periode = ? AND id_petugas = ?");
        $stmt->bind_param("ii", $id_periode, $userId);
        $stmt->execute();
        $total_checked_petugas = (int)$stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
        
        // Total checked all (distinct barang)
        $stmt = $conn->prepare("SELECT COUNT(DISTINCT id_barang) as total FROM pengecekan_barang WHERE id_periode = ?");
        $stmt->bind_param("i", $id_periode);
        $stmt->execute();
        $total_checked_all = (int)$stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
        
        if ($total_barang_aktif > 0) {
            $pct_checked = round(($total_checked_all / $total_barang_aktif) * 100);
        }
    }
    
    // Riwayat terbaru (5)
    $riwayat = [];
    $rq = $conn->prepare("SELECT pb.*, b.nama_barang, pe.nama_periode 
                          FROM pengecekan_barang pb
                          JOIN barang b ON pb.id_barang = b.id
                          JOIN periode_pengecekan pe ON pb.id_periode = pe.id
                          WHERE pb.id_petugas = ?
                          ORDER BY pb.tgl_pengecekan DESC LIMIT 5");
    $rq->bind_param("i", $userId);
    $rq->execute();
    $rr = $rq->get_result();
    while ($row = $rr->fetch_assoc()) {
        $riwayat[] = [
            'id' => (int)$row['id'],
            'nama_barang' => $row['nama_barang'],
            'nama_periode' => $row['nama_periode'],
            'kondisi_temuan' => $row['kondisi_temuan'],
            'status_review' => $row['status_review'],
            'tgl_pengecekan' => $row['tgl_pengecekan'],
        ];
    }
    $rq->close();
    
    jsonResponse([
        'success' => true,
        'data' => [
            'user' => [
                'id' => $userId,
                'nama_lengkap' => $currentUser['nama_lengkap'],
            ],
            'periode_aktif' => $periode_aktif ? [
                'id' => (int)$periode_aktif['id'],
                'nama_periode' => $periode_aktif['nama_periode'],
                'tahun' => $periode_aktif['tahun'],
                'tgl_mulai' => $periode_aktif['tgl_mulai'],
                'tgl_selesai' => $periode_aktif['tgl_selesai'],
                'status' => $periode_aktif['status'],
            ] : null,
            'total_checked_petugas' => $total_checked_petugas,
            'total_barang_aktif' => $total_barang_aktif,
            'pct_checked' => $pct_checked,
            'riwayat_terbaru' => $riwayat,
        ]
    ]);
}

// ============================================================
// ACTION: get_barang
// ============================================================
if ($action === 'get_barang') {
    $code = trim($_GET['code'] ?? '');
    if ($code === '') {
        jsonError('Kode barang tidak boleh kosong.');
    }
    
    $id_barang = intval($code);
    if ($id_barang <= 0) {
        jsonError('Format kode barang tidak valid.');
    }
    
    $stmt = $conn->prepare("
        SELECT b.*, m.nama_merk, j.nama_jenis, k.nama_kategori, u.nama_unit, r.nama_ruang 
        FROM barang b 
        LEFT JOIN merk m ON b.id_merk = m.id 
        LEFT JOIN jenis j ON b.id_jenis = j.id 
        LEFT JOIN kategori k ON b.id_kategori = k.id 
        LEFT JOIN unit u ON b.id_unit = u.id 
        LEFT JOIN ruang r ON b.id_ruang = r.id 
        WHERE b.id = ?
    ");
    $stmt->bind_param("i", $id_barang);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if (!$res || $res->num_rows === 0) {
        jsonError('Barang dengan kode "' . htmlspecialchars($code) . '" tidak ditemukan.');
    }
    
    $barang = $res->fetch_assoc();
    $stmt->close();
    
    if ($barang['status_aktif'] !== 'aktif') {
        jsonResponse([
            'success' => false,
            'is_inactive' => true,
            'message' => 'Barang sudah dikeluarkan dari inventaris.'
        ]);
    }
    
    $foto_url = null;
    if (!empty($barang['foto'])) {
        $foto_url = $barang['foto'];
    }
    
    jsonResponse([
        'success' => true,
        'data' => [
            'id' => (int)$barang['id'],
            'nama_barang' => $barang['nama_barang'],
            'formatted_code' => str_pad($barang['id'], 5, "0", STR_PAD_LEFT),
            'nama_merk' => $barang['nama_merk'] ?? '-',
            'nama_jenis' => $barang['nama_jenis'] ?? '-',
            'nama_kategori' => $barang['nama_kategori'] ?? '-',
            'nama_unit' => $barang['nama_unit'] ?? '-',
            'nama_ruang' => $barang['nama_ruang'] ?? '-',
            'kondisi' => $barang['kondisi'],
            'status_aktif' => $barang['status_aktif'],
            'foto' => $foto_url,
            'jumlah' => (int)$barang['jumlah'],
            'tgl_pembelian' => $barang['tgl_pembelian'],
        ]
    ]);
}

// ============================================================
// ACTION: list_barang_all (untuk Update Gambar Barang)
// ============================================================
if ($action === 'list_barang_all') {
    $search = trim($_GET['search'] ?? '');
    $where = "";
    if ($search !== '') {
        $search_escaped = $conn->real_escape_string($search);
        $where = "WHERE b.nama_barang LIKE '%$search_escaped%' OR b.id LIKE '%$search_escaped%'";
    }
    
    $goods_res = $conn->query("SELECT b.id, b.nama_barang, b.foto, b.kondisi, k.nama_kategori, m.nama_merk, u.nama_unit, r.nama_ruang 
                              FROM barang b 
                              LEFT JOIN kategori k ON b.id_kategori = k.id 
                              LEFT JOIN merk m ON b.id_merk = m.id 
                              LEFT JOIN unit u ON b.id_unit = u.id 
                              LEFT JOIN ruang r ON b.id_ruang = r.id 
                              $where
                              ORDER BY b.nama_barang ASC");
    
    $goods = [];
    if ($goods_res) {
        while ($row = $goods_res->fetch_assoc()) {
            $goods[] = [
                'id' => (int)$row['id'],
                'formatted_code' => 'BRG-' . str_pad($row['id'], 5, "0", STR_PAD_LEFT),
                'nama_barang' => $row['nama_barang'],
                'foto' => $row['foto'],
                'kondisi' => $row['kondisi'],
                'nama_kategori' => $row['nama_kategori'] ?? '-',
                'nama_merk' => $row['nama_merk'] ?? '-',
                'nama_unit' => $row['nama_unit'] ?? '-',
                'nama_ruang' => $row['nama_ruang'] ?? '-',
            ];
        }
    }
    
    jsonResponse([
        'success' => true,
        'data' => $goods,
    ]);
}

// ============================================================
// ACTION: update_gambar (Upload atau Delete foto barang)
// ============================================================
if ($action === 'update_gambar') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('Method not allowed', 405);
    }
    
    $id_barang = intval($_POST['id_barang'] ?? 0);
    $sub_action = $_POST['sub_action'] ?? 'upload'; // 'upload' or 'delete'
    
    if ($id_barang <= 0) {
        jsonError('Pilih barang yang valid.');
    }
    
    $uploadsDir = '../uploads/barang/';
    if (!file_exists($uploadsDir)) mkdir($uploadsDir, 0777, true);
    
    // Handle Delete
    if ($sub_action === 'delete') {
        $res = $conn->query("SELECT foto FROM barang WHERE id = $id_barang");
        if ($res && $row = $res->fetch_assoc()) {
            $oldName = $row['foto'];
            $stmt = $conn->prepare("UPDATE barang SET foto = NULL WHERE id = ?");
            $stmt->bind_param('i', $id_barang);
            if ($stmt->execute()) {
                if ($oldName && file_exists($uploadsDir . $oldName)) {
                    @unlink($uploadsDir . $oldName);
                }
                jsonResponse(['success' => true, 'message' => 'Gambar barang berhasil dihapus.']);
            } else {
                jsonError('Gagal menghapus data gambar di database.');
            }
            $stmt->close();
        } else {
            jsonError('Data barang tidak ditemukan.');
        }
    }
    
    // Handle Upload
    if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        jsonError('File foto belum dipilih atau terjadi error upload.');
    }
    
    $tmp = $_FILES['foto']['tmp_name'];
    $info = @getimagesize($tmp);
    if (!$info || empty($info['mime'])) {
        jsonError('File bukan gambar yang valid.');
    }
    
    // Compress and Save Image
    $gd_available = (function_exists('imagecreatefromjpeg') || function_exists('imagecreatefrompng'));
    $newName = 'IMG_' . $id_barang . '_' . time() . '.jpg';
    $targetPath = $uploadsDir . $newName;
    
    if (!$gd_available) {
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $newName = 'IMG_' . $id_barang . '_' . time() . '.' . $ext;
        $targetPath = $uploadsDir . $newName;
        if (!move_uploaded_file($tmp, $targetPath)) {
            jsonError('Gagal menyimpan file upload.');
        }
    } else {
        $mime = $info['mime'];
        $src = null;
        switch ($mime) {
            case 'image/jpeg': $src = imagecreatefromjpeg($tmp); break;
            case 'image/png': $src = imagecreatefrompng($tmp); break;
            case 'image/gif': if (function_exists('imagecreatefromgif')) $src = imagecreatefromgif($tmp); break;
            case 'image/webp': if (function_exists('imagecreatefromwebp')) $src = imagecreatefromwebp($tmp); break;
            default: jsonError('Format file tidak didukung.');
        }
        
        if (!$src) {
            jsonError('Gagal memproses gambar.');
        }
        
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
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $width, $height);
        imagedestroy($src);
        
        $maxBytes = 500 * 1024;
        $tempOut = tempnam(sys_get_temp_dir(), 'upimg_');
        $quality = 90;
        $saved = false;
        while ($quality >= 10) {
            imagejpeg($dst, $tempOut, $quality);
            if (filesize($tempOut) <= $maxBytes) { $saved = true; break; }
            $quality -= 10;
        }
        if (!$saved) {
            imagejpeg($dst, $tempOut, 10);
        }
        imagedestroy($dst);
        
        if (!rename($tempOut, $targetPath)) {
            if (!copy($tempOut, $targetPath)) {
                @unlink($tempOut);
                jsonError('Gagal menyimpan hasil kompresi.');
            }
            @unlink($tempOut);
        }
    }
    
    // Update DB
    $old = $conn->query("SELECT foto FROM barang WHERE id = $id_barang")->fetch_assoc();
    $oldName = $old['foto'] ?? '';
    
    $stmt = $conn->prepare("UPDATE barang SET foto = ? WHERE id = ?");
    $stmt->bind_param('si', $newName, $id_barang);
    if ($stmt->execute()) {
        if ($oldName && file_exists($uploadsDir . $oldName)) {
            @unlink($uploadsDir . $oldName);
        }
        jsonResponse([
            'success' => true,
            'message' => 'Gambar berhasil diunggah dan dikompresi.',
            'foto' => $newName,
        ]);
    } else {
        if (file_exists($targetPath)) @unlink($targetPath);
        jsonError('Gagal memperbarui database.');
    }
    $stmt->close();
}

// ============================================================
// ACTION: check_status
// ============================================================
if ($action === 'check_status') {
    $barang_id = intval($_GET['barang_id'] ?? 0);
    if ($barang_id <= 0) {
        jsonResponse(['already_checked' => false]);
    }
    
    $res_periode = $conn->query("SELECT id FROM periode_pengecekan WHERE status = 'aktif' ORDER BY id DESC LIMIT 1");
    if ($res_periode && $res_periode->num_rows > 0) {
        $periode = $res_periode->fetch_assoc();
        $id_periode = (int)$periode['id'];
        
        $stmt = $conn->prepare("
            SELECT pb.*, u.nama_lengkap 
            FROM pengecekan_barang pb
            JOIN users u ON pb.id_petugas = u.id
            WHERE pb.id_periode = ? AND pb.id_barang = ?
        ");
        $stmt->bind_param("ii", $id_periode, $barang_id);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res && $res->num_rows > 0) {
            $data = $res->fetch_assoc();
            jsonResponse([
                'already_checked' => true,
                'petugas' => $data['nama_lengkap'],
                'tanggal' => date('d/m/Y H:i', strtotime($data['tgl_pengecekan'])),
                'kondisi' => $data['kondisi_temuan'],
            ]);
        }
        $stmt->close();
    }
    
    jsonResponse(['already_checked' => false]);
}

// ============================================================
// ACTION: submit_pengecekan
// ============================================================
if ($action === 'submit_pengecekan') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('Method not allowed', 405);
    }
    
    // Get active period
    $res = $conn->query("SELECT * FROM periode_pengecekan WHERE status = 'aktif' ORDER BY id DESC LIMIT 1");
    if (!$res || $res->num_rows === 0) {
        jsonError('Tidak ada periode pengecekan aktif saat ini!');
    }
    $periode_aktif = $res->fetch_assoc();
    $id_periode = (int)$periode_aktif['id'];
    
    $id_barang = intval($_POST['id_barang'] ?? 0);
    $kondisi_temuan = $_POST['kondisi_temuan'] ?? '';
    $catatan = trim($_POST['catatan'] ?? '');
    
    if ($id_barang <= 0) {
        jsonError('ID barang tidak valid.');
    }
    
    if (!in_array($kondisi_temuan, ['Baik', 'Rusak', 'Hilang'])) {
        jsonError('Kondisi temuan tidak valid.');
    }
    
    // Validasi barang aktif
    $res_barang = $conn->query("SELECT status_aktif, nama_barang FROM barang WHERE id = $id_barang");
    $barang_data = $res_barang->fetch_assoc();
    if (!$barang_data || $barang_data['status_aktif'] !== 'aktif') {
        jsonError('Barang tidak ditemukan atau sudah tidak aktif!');
    }
    
    $foto_name = null;
    $upload_ok = true;
    
    // Handle foto upload
    if (in_array($kondisi_temuan, ['Rusak', 'Hilang'])) {
        if (!isset($_FILES['foto_bukti']) || $_FILES['foto_bukti']['error'] !== 0) {
            jsonError('Foto bukti wajib dilampirkan jika barang rusak atau hilang!');
        }
        
        $tmp = $_FILES['foto_bukti']['tmp_name'];
        $info = @getimagesize($tmp);
        if (!$info || empty($info['mime'])) {
            jsonError('File bukan gambar yang valid!');
        }
        
        $target_dir = "../uploads/bukti/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        // Check GD availability
        $gd_available = (function_exists('imagecreatefromjpeg') || function_exists('imagecreatefrompng'));
        
        if (!$gd_available) {
            $file_ext = strtolower(pathinfo($_FILES["foto_bukti"]["name"], PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
            if (!in_array($file_ext, $allowed_exts)) {
                jsonError('Format foto tidak valid.');
            }
            $foto_name = "BUKTI_" . time() . "_" . $id_barang . "." . $file_ext;
            if (!move_uploaded_file($tmp, $target_dir . $foto_name)) {
                jsonError('Gagal mengunggah foto bukti!');
            }
        } else {
            $mime = $info['mime'];
            $src = null;
            switch ($mime) {
                case 'image/jpeg': $src = imagecreatefromjpeg($tmp); break;
                case 'image/png': $src = imagecreatefrompng($tmp); break;
                case 'image/gif': if (function_exists('imagecreatefromgif')) $src = imagecreatefromgif($tmp); break;
                case 'image/webp': if (function_exists('imagecreatefromwebp')) $src = imagecreatefromwebp($tmp); break;
                default: jsonError('Format foto tidak valid.');
            }
            
            if ($src) {
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
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $width, $height);
                imagedestroy($src);
                
                $maxBytes = 500 * 1024;
                $tempOut = tempnam(sys_get_temp_dir(), 'upimg_');
                $quality = 90;
                $saved = false;
                while ($quality >= 10) {
                    imagejpeg($dst, $tempOut, $quality);
                    if (filesize($tempOut) <= $maxBytes) { $saved = true; break; }
                    $quality -= 10;
                }
                if (!$saved) {
                    imagejpeg($dst, $tempOut, 10);
                }
                imagedestroy($dst);
                
                $foto_name = "BUKTI_" . time() . "_" . $id_barang . ".jpg";
                if (!rename($tempOut, $target_dir . $foto_name)) {
                    if (!copy($tempOut, $target_dir . $foto_name)) {
                        @unlink($tempOut);
                        jsonError('Gagal menyimpan foto.');
                    }
                    @unlink($tempOut);
                }
            } else {
                jsonError('Gagal memproses gambar.');
            }
        }
    }
    
    // Optional foto for "Baik" condition
    if ($kondisi_temuan === 'Baik' && isset($_FILES['foto_bukti']) && $_FILES['foto_bukti']['error'] === 0) {
        $tmp = $_FILES['foto_bukti']['tmp_name'];
        $file_ext = strtolower(pathinfo($_FILES["foto_bukti"]["name"], PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($file_ext, $allowed_exts)) {
            $target_dir = "../uploads/bukti/";
            if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
            $foto_name = "BUKTI_" . time() . "_" . $id_barang . "." . $file_ext;
            move_uploaded_file($tmp, $target_dir . $foto_name);
        }
    }
    
    // Determine review status
    $status_review = ($kondisi_temuan === 'Baik') ? 'disetujui' : 'menunggu';
    
    $conn->begin_transaction();
    try {
        // Check existing
        $stmt = $conn->prepare("SELECT id, foto_bukti FROM pengecekan_barang WHERE id_periode = ? AND id_barang = ?");
        $stmt->bind_param("ii", $id_periode, $id_barang);
        $stmt->execute();
        $exist_res = $stmt->get_result();
        $stmt->close();
        
        if ($exist_res && $exist_res->num_rows > 0) {
            $old_data = $exist_res->fetch_assoc();
            $id_pengecekan = $old_data['id'];
            
            if ($foto_name !== null && !empty($old_data['foto_bukti'])) {
                @unlink("../uploads/bukti/" . $old_data['foto_bukti']);
            }
            
            $final_foto = ($foto_name !== null) ? $foto_name : $old_data['foto_bukti'];
            
            $stmt_up = $conn->prepare("UPDATE pengecekan_barang SET id_petugas = ?, kondisi_temuan = ?, catatan = ?, foto_bukti = ?, status_review = ?, tgl_pengecekan = NOW() WHERE id = ?");
            $stmt_up->bind_param("issssi", $userId, $kondisi_temuan, $catatan, $final_foto, $status_review, $id_pengecekan);
            $stmt_up->execute();
            $stmt_up->close();
        } else {
            $stmt_in = $conn->prepare("INSERT INTO pengecekan_barang (id_periode, id_barang, id_petugas, kondisi_temuan, catatan, foto_bukti, status_review) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt_in->bind_param("iiissss", $id_periode, $id_barang, $userId, $kondisi_temuan, $catatan, $foto_name, $status_review);
            $stmt_in->execute();
            $stmt_in->close();
        }
        
        if ($kondisi_temuan === 'Baik') {
            $stmt_brg = $conn->prepare("UPDATE barang SET kondisi = 'Baik' WHERE id = ?");
            $stmt_brg->bind_param("i", $id_barang);
            $stmt_brg->execute();
            $stmt_brg->close();
        }
        
        $conn->commit();
        
        $msg = ($kondisi_temuan === 'Baik')
            ? 'Pengecekan barang "' . $barang_data['nama_barang'] . '" berhasil dikirim dan otomatis disetujui.'
            : 'Pengecekan barang "' . $barang_data['nama_barang'] . '" berhasil dikirim dan menunggu persetujuan admin.';
        
        jsonResponse([
            'success' => true,
            'message' => $msg,
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        if ($foto_name !== null) {
            @unlink("../uploads/bukti/" . $foto_name);
        }
        jsonError('Terjadi kesalahan sistem: ' . $e->getMessage(), 500);
    }
}

// ============================================================
// ACTION: riwayat  (VERSI LENGKAP - filter kondisi + summary count)
// ============================================================
if ($action === 'riwayat') {
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = max(1, min(100, intval($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    $search = trim($_GET['search'] ?? '');
    // kondisi: '' (semua), 'baik', atau 'bermasalah' (Rusak/Hilang)
    $kondisi = trim($_GET['kondisi'] ?? '');

    $where_extra = "";
    $params = [$userId];
    $types = "i";

    if ($search !== '') {
        $where_extra .= " AND (b.nama_barang LIKE ? OR k.nama_kategori LIKE ?)";
        $search_like = "%$search%";
        $params[] = $search_like;
        $params[] = $search_like;
        $types .= "ss";
    }

    // Filter kondisi dilakukan di query (server-side), bukan di Flutter
    if ($kondisi === 'baik') {
        $where_extra .= " AND pb.kondisi_temuan = 'Baik'";
    } elseif ($kondisi === 'bermasalah') {
        $where_extra .= " AND pb.kondisi_temuan != 'Baik'";
    }

    // ------------------------------------------------------------
    // Summary counts: dihitung dari SEMUA data yang match search
    // (tidak terpengaruh filter kondisi, supaya angka card ringkasan
    // tetap stabil walau chip filter lagi dipilih yang mana)
    // ------------------------------------------------------------
    $summary_where = "";
    $summary_params = [$userId];
    $summary_types = "i";
    if ($search !== '') {
        $summary_where = " AND (b.nama_barang LIKE ? OR k.nama_kategori LIKE ?)";
        $search_like = "%$search%";
        $summary_params[] = $search_like;
        $summary_params[] = $search_like;
        $summary_types .= "ss";
    }

    $summary_sql = "SELECT
                        COUNT(*) as total,
                        SUM(CASE WHEN pb.kondisi_temuan = 'Baik' THEN 1 ELSE 0 END) as total_baik,
                        SUM(CASE WHEN pb.kondisi_temuan != 'Baik' THEN 1 ELSE 0 END) as total_bermasalah
                     FROM pengecekan_barang pb
                     JOIN barang b ON pb.id_barang = b.id
                     LEFT JOIN kategori k ON b.id_kategori = k.id
                     WHERE pb.id_petugas = ? $summary_where";
    $stmt_summary = $conn->prepare($summary_sql);
    $stmt_summary->bind_param($summary_types, ...$summary_params);
    $stmt_summary->execute();
    $summary_row = $stmt_summary->get_result()->fetch_assoc();
    $stmt_summary->close();

    $total = (int)$summary_row['total'];
    $total_baik = (int)$summary_row['total_baik'];
    $total_bermasalah = (int)$summary_row['total_bermasalah'];

    // ------------------------------------------------------------
    // Fetch data (dengan filter kondisi + pagination)
    // ------------------------------------------------------------
    $sql = "SELECT pb.*, b.nama_barang, b.foto as foto_barang, m.nama_merk, k.nama_kategori,
                   u.nama_unit, r.nama_ruang, pe.nama_periode, pe.tahun,
                   rv.nama_lengkap as nama_reviewer
            FROM pengecekan_barang pb
            JOIN barang b ON pb.id_barang = b.id
            LEFT JOIN merk m ON b.id_merk = m.id
            LEFT JOIN kategori k ON b.id_kategori = k.id
            LEFT JOIN unit u ON b.id_unit = u.id
            LEFT JOIN ruang r ON b.id_ruang = r.id
            JOIN periode_pengecekan pe ON pb.id_periode = pe.id
            LEFT JOIN users rv ON pb.id_reviewer = rv.id
            WHERE pb.id_petugas = ? $where_extra
            ORDER BY pb.tgl_pengecekan DESC
            LIMIT $limit OFFSET $offset";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    // Total setelah difilter kondisi (untuk pagination yang akurat saat filter aktif)
    $count_filtered_sql = "SELECT COUNT(*) as total FROM pengecekan_barang pb
                            JOIN barang b ON pb.id_barang = b.id
                            LEFT JOIN kategori k ON b.id_kategori = k.id
                            WHERE pb.id_petugas = ? $where_extra";
    $stmt_cf = $conn->prepare($count_filtered_sql);
    $stmt_cf->bind_param($types, ...$params);
    $stmt_cf->execute();
    $total_filtered = (int)$stmt_cf->get_result()->fetch_assoc()['total'];
    $stmt_cf->close();

    $items = [];
    while ($row = $res->fetch_assoc()) {
        $items[] = [
            'id' => (int)$row['id'],
            'id_barang' => (int)$row['id_barang'],
            'kode_barang' => str_pad($row['id_barang'], 5, "0", STR_PAD_LEFT),
            'nama_barang' => $row['nama_barang'],
            'nama_kategori' => $row['nama_kategori'] ?? '-',
            'nama_merk' => $row['nama_merk'] ?? '-',
            'nama_unit' => $row['nama_unit'] ?? '-',
            'nama_ruang' => $row['nama_ruang'] ?? '-',
            'nama_periode' => $row['nama_periode'],
            'tahun' => $row['tahun'],
            'kondisi_temuan' => $row['kondisi_temuan'],
            'catatan' => $row['catatan'] ?? '',
            'foto_bukti' => $row['foto_bukti'],
            'status_review' => $row['status_review'],
            'nama_reviewer' => $row['nama_reviewer'],
            'catatan_reviewer' => $row['catatan_reviewer'] ?? '',
            'tgl_pengecekan' => $row['tgl_pengecekan'],
            'tgl_review' => $row['tgl_review'],
        ];
    }
    $stmt->close();

    jsonResponse([
        'success' => true,
        'data' => $items,
        'pagination' => [
            'total' => $total_filtered,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total_filtered / $limit),
        ],
        // Summary selalu dari total keseluruhan (tidak terpengaruh filter kondisi)
        'summary' => [
            'total' => $total,
            'total_baik' => $total_baik,
            'total_bermasalah' => $total_bermasalah,
        ],
    ]);
}

// ============================================================
// ACTION: riwayat_kalender
// Ambil SEMUA riwayat pengecekan petugas ini untuk satu bulan+tahun
// tertentu saja (dipakai oleh popup kalender).
// ============================================================
if ($action === 'riwayat_kalender') {
    $bulan = intval($_GET['bulan'] ?? 0);
    $tahun = intval($_GET['tahun'] ?? 0);

    if ($bulan < 1 || $bulan > 12) {
        jsonError('Parameter bulan tidak valid (harus 1-12).');
    }
    if ($tahun < 2000 || $tahun > 2100) {
        jsonError('Parameter tahun tidak valid.');
    }

    $sql = "SELECT pb.*, b.nama_barang, b.foto as foto_barang, m.nama_merk, k.nama_kategori,
                   u.nama_unit, r.nama_ruang, pe.nama_periode, pe.tahun,
                   rv.nama_lengkap as nama_reviewer
            FROM pengecekan_barang pb
            JOIN barang b ON pb.id_barang = b.id
            LEFT JOIN merk m ON b.id_merk = m.id
            LEFT JOIN kategori k ON b.id_kategori = k.id
            LEFT JOIN unit u ON b.id_unit = u.id
            LEFT JOIN ruang r ON b.id_ruang = r.id
            JOIN periode_pengecekan pe ON pb.id_periode = pe.id
            LEFT JOIN users rv ON pb.id_reviewer = rv.id
            WHERE pb.id_petugas = ?
              AND MONTH(pb.tgl_pengecekan) = ?
              AND YEAR(pb.tgl_pengecekan) = ?
            ORDER BY pb.tgl_pengecekan ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $userId, $bulan, $tahun);
    $stmt->execute();
    $res = $stmt->get_result();

    $items = [];
    while ($row = $res->fetch_assoc()) {
        $items[] = [
            'id' => (int)$row['id'],
            'id_barang' => (int)$row['id_barang'],
            'kode_barang' => str_pad($row['id_barang'], 5, "0", STR_PAD_LEFT),
            'nama_barang' => $row['nama_barang'],
            'nama_kategori' => $row['nama_kategori'] ?? '-',
            'nama_merk' => $row['nama_merk'] ?? '-',
            'nama_unit' => $row['nama_unit'] ?? '-',
            'nama_ruang' => $row['nama_ruang'] ?? '-',
            'nama_periode' => $row['nama_periode'],
            'tahun' => $row['tahun'],
            'kondisi_temuan' => $row['kondisi_temuan'],
            'catatan' => $row['catatan'] ?? '',
            'foto_bukti' => $row['foto_bukti'],
            'status_review' => $row['status_review'],
            'nama_reviewer' => $row['nama_reviewer'],
            'catatan_reviewer' => $row['catatan_reviewer'] ?? '',
            'tgl_pengecekan' => $row['tgl_pengecekan'],
            'tgl_review' => $row['tgl_review'],
        ];
    }
    $stmt->close();

    jsonResponse([
        'success' => true,
        'data' => $items,
        'bulan' => $bulan,
        'tahun' => $tahun,
    ]);
}

// ============================================================
// ACTION: profil
// ============================================================
if ($action === 'profil') {
    jsonResponse([
        'success' => true,
        'data' => [
            'id' => $userId,
            'username' => $currentUser['username'],
            'nama_lengkap' => $currentUser['nama_lengkap'],
            'level' => $currentUser['level'],
        ]
    ]);
}

// ============================================================
// Unknown action
// ============================================================
jsonError('Action tidak dikenali: ' . htmlspecialchars($action), 404);