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
            $nama_merk = trim($data[0] ?? '');
            if (empty($nama_merk)) continue;
            
            // Cek apakah nama merk sudah ada
            $check = $conn->prepare("SELECT id FROM merk WHERE nama_merk = ?");
            $check->bind_param("s", $nama_merk);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $duplicate_count++;
                $check->close();
                continue;
            }
            $check->close();

            $stmt = $conn->prepare("INSERT INTO merk (nama_merk) VALUES (?)");
            $stmt->bind_param("s", $nama_merk);
            
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

header('Location: merk.php');
exit;
?>
