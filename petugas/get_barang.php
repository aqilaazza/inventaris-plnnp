<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['level'] !== 'petugas') {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak. Silakan login kembali.']);
    exit;
}

include '../config/database.php';

$code = trim($_GET['code'] ?? '');

if ($code === '') {
    echo json_encode(['success' => false, 'message' => 'Kode barang tidak boleh kosong.']);
    exit;
}

// Convert zero-padded code to integer ID
$id_barang = intval($code);

if ($id_barang <= 0) {
    echo json_encode(['success' => false, 'message' => 'Format kode barang tidak valid.']);
    exit;
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

if ($res && $res->num_rows > 0) {
    $barang = $res->fetch_assoc();
    
    // Check if item is active
    if ($barang['status_aktif'] !== 'aktif') {
        echo json_encode([
            'success' => false, 
            'is_inactive' => true,
            'message' => 'Barang sudah dikeluarkan dari inventaris.'
        ]);
        exit;
    }
    
    // Format zero-padded code
    $barang['formatted_code'] = str_pad($barang['id'], 5, "0", STR_PAD_LEFT);
    
    echo json_encode([
        'success' => true,
        'data' => $barang
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Barang dengan kode "' . htmlspecialchars($code) . '" tidak ditemukan.'
    ]);
}
$stmt->close();
?>
