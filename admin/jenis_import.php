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
        $duplicate_count = 0;
        
        foreach ($rows as $data) {
            $nama_jenis = trim($data[0] ?? '');
            if (empty($nama_jenis)) continue;
            
            // Cek apakah nama jenis sudah ada
            $check = $conn->prepare("SELECT id FROM jenis WHERE nama_jenis = ?");
            $check->bind_param("s", $nama_jenis);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $duplicate_count++;
                $check->close();
                continue;
            }
            $check->close();

            $stmt = $conn->prepare("INSERT INTO jenis (nama_jenis) VALUES (?)");
            $stmt->bind_param("s", $nama_jenis);
            
            if ($stmt->execute()) {
                $success_count++;
            } else {
                $error_count++;
            }
            $stmt->close();
        }
        
        $msg = "Import selesai! Berhasil: $success_count";
        if ($duplicate_count > 0) $msg .= ", Duplikat (diabaikan): $duplicate_count";
        if ($error_count > 0) $msg .= ", Gagal: $error_count";
        
        $_SESSION['success'] = $msg;
    } else {
        $_SESSION['error'] = "Gagal memproses file XLSX: " . SimpleXLSX::parseError();
    }
}

header('Location: jenis.php');
exit;
?>
