<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['level'] !== 'petugas') {
    echo json_encode(['already_checked' => false]);
    exit;
}

include '../config/database.php';

$barang_id = intval($_GET['barang_id'] ?? 0);

if ($barang_id <= 0) {
    echo json_encode(['already_checked' => false]);
    exit;
}

// Get active checking period
$res_periode = $conn->query("SELECT id FROM periode_pengecekan WHERE status = 'aktif' ORDER BY id DESC LIMIT 1");
if ($res_periode && $res_periode->num_rows > 0) {
    $periode = $res_periode->fetch_assoc();
    $id_periode = $periode['id'];

    // Query to find if checking already exists
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
        echo json_encode([
            'already_checked' => true,
            'petugas' => htmlspecialchars($data['nama_lengkap']),
            'tanggal' => date('d/m/Y H:i', strtotime($data['tgl_pengecekan'])),
            'kondisi' => htmlspecialchars($data['kondisi_temuan'])
        ]);
        $stmt->close();
        exit;
    }
    $stmt->close();
}

echo json_encode(['already_checked' => false]);
?>
