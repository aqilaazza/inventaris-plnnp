<?php
session_start();
include '../config/database.php';
require_once '../plugins/simplexlsx/SimpleXLSX.php';

use Shuchkin\SimpleXLSX;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file_import'])) {
    $file = $_FILES['file_import']['tmp_name'];
    
    if ($xlsx = SimpleXLSX::parse($file)) {
        $rows = $xlsx->rows();
        // Skip header
        array_shift($rows);
        
        $success_count = 0;
        $error_count = 0;
        
        foreach ($rows as $data) {
            // 0: id_unit
            // 1: nama_ruang
            
            $id_unit    = !empty($data[0]) ? intval($data[0]) : null;
            $nama_ruang = trim($data[1] ?? '');
            
            if (empty($id_unit) || empty($nama_ruang)) continue;
            
            // Optional: Cek apakah nama ruang sudah ada di unit yang sama
            $check = $conn->prepare("SELECT id FROM ruang WHERE nama_ruang = ? AND id_unit = ?");
            $check->bind_param("si", $nama_ruang, $id_unit);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $check->close();
                continue; // Skip if already exists in same unit
            }
            $check->close();

            $stmt = $conn->prepare("INSERT INTO ruang (id_unit, nama_ruang) VALUES (?, ?)");
            $stmt->bind_param("is", $id_unit, $nama_ruang);
            
            if ($stmt->execute()) {
                $success_count++;
            } else {
                $error_count++;
            }
            $stmt->close();
        }
        
        $_SESSION['success'] = "Import selesai! Berhasil: $success_count, Gagal/Lewati: $error_count.";
    } else {
        $_SESSION['error'] = "Gagal memproses file XLSX: " . SimpleXLSX::parseError();
    }
}

header('Location: ruang.php');
exit;
?>
