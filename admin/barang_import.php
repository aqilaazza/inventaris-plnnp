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
        
        // Helper function for dynamic lookup/insertion during import
        $getOrInsertRef = function($conn, $table, $column, $value) {
            if ($value === null) return null;
            $val = trim(strval($value));
            if ($val === '' || strcasecmp($val, 'null') === 0 || $val === '-' || strcasecmp($val, 'tidak ada') === 0 || strcasecmp($val, 'tanpa merk') === 0 || strcasecmp($val, 'none') === 0) {
                return null;
            }
            
            // 1. If it's a numeric ID, check if it exists in the database
            if (is_numeric($val)) {
                $id = intval($val);
                $stmt = $conn->prepare("SELECT id FROM $table WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    $stmt->close();
                    return $id;
                }
                $stmt->close();
            }
            
            // 2. Treat as name: check if exists, otherwise insert
            $stmt = $conn->prepare("SELECT id FROM $table WHERE $column = ?");
            $stmt->bind_param("s", $val);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $stmt->close();
                return $row['id'];
            } else {
                $stmt->close();
                $stmt_in = $conn->prepare("INSERT INTO $table ($column) VALUES (?)");
                $stmt_in->bind_param("s", $val);
                $stmt_in->execute();
                $inserted_id = $stmt_in->insert_id;
                $stmt_in->close();
                return $inserted_id;
            }
        };

        // Specialized helper for ruang (requires id_unit)
        $getOrInsertRuangRef = function($conn, $value, $id_unit) {
            if ($value === null) return null;
            $val = trim(strval($value));
            if ($val === '' || strcasecmp($val, 'null') === 0 || $val === '-' || strcasecmp($val, 'tidak ada') === 0 || strcasecmp($val, 'none') === 0) {
                return null;
            }
            
            // 1. If it's a numeric ID, check if it exists in the database
            if (is_numeric($val)) {
                $id = intval($val);
                $stmt = $conn->prepare("SELECT id FROM ruang WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    $stmt->close();
                    return $id;
                }
                $stmt->close();
            }
            
            // 2. Look up or insert by name under the resolved id_unit
            if ($id_unit > 0) {
                $stmt = $conn->prepare("SELECT id FROM ruang WHERE nama_ruang = ? AND id_unit = ?");
                $stmt->bind_param("si", $val, $id_unit);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    $stmt->close();
                    return $row['id'];
                } else {
                    $stmt->close();
                    $stmt_in = $conn->prepare("INSERT INTO ruang (nama_ruang, id_unit) VALUES (?, ?)");
                    $stmt_in->bind_param("si", $val, $id_unit);
                    $stmt_in->execute();
                    $inserted_id = $stmt_in->insert_id;
                    $stmt_in->close();
                    return $inserted_id;
                }
            }
            
            // 3. Fallback: if we have no id_unit but the room name exists elsewhere
            $stmt = $conn->prepare("SELECT id FROM ruang WHERE nama_ruang = ? LIMIT 1");
            $stmt->bind_param("s", $val);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $stmt->close();
                return $row['id'];
            }
            $stmt->close();
            return null;
        };

        foreach ($rows as $data) {
            // Mapping:
            // 0: nama_barang
            // 1: id_merk
            // 2: jumlah
            // 3: id_jenis
            // 4: id_kategori
            // 5: id_unit
            // 6: id_ruang
            // 7: kondisi
            // 8: tgl_pembelian
            
            $nama_barang = $data[0] ?? '';
            if (empty($nama_barang)) continue;
            
            $id_unit     = $getOrInsertRef($conn, 'unit', 'nama_unit', $data[5] ?? null);
            $id_merk     = $getOrInsertRef($conn, 'merk', 'nama_merk', $data[1] ?? null);
            $jumlah      = !empty($data[2]) ? max(1, intval($data[2])) : 1;
            $id_jenis    = $getOrInsertRef($conn, 'jenis', 'nama_jenis', $data[3] ?? null);
            $id_kategori = $getOrInsertRef($conn, 'kategori', 'nama_kategori', $data[4] ?? null);
            $id_ruang    = $getOrInsertRuangRef($conn, $data[6] ?? null, $id_unit);
            $kondisi     = !empty($data[7]) ? trim($data[7]) : 'Baik';
            
            $tgl_beli    = !empty($data[8]) ? trim(strval($data[8])) : null;
            if ($tgl_beli === '' || $tgl_beli === '-' || strcasecmp($tgl_beli, 'null') === 0) {
                $tgl_beli = null;
            }
            
            // Validate Enum Kondisi
            if (!in_array($kondisi, ['Baik', 'Rusak', 'Hilang'])) {
                $kondisi = 'Baik';
            }

            $stmt = $conn->prepare("INSERT INTO barang (nama_barang, id_merk, jumlah, id_jenis, id_kategori, id_unit, id_ruang, kondisi, tgl_pembelian) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("siiiiisss", $nama_barang, $id_merk, $jumlah, $id_jenis, $id_kategori, $id_unit, $id_ruang, $kondisi, $tgl_beli);
            
            if ($stmt->execute()) {
                $success_count++;
            } else {
                $error_count++;
            }
            $stmt->close();
        }
        
        $_SESSION['success'] = "Import XLSX selesai! Berhasil: $success_count, Gagal: $error_count.";
    } else {
        $_SESSION['error'] = "Gagal memproses file XLSX: " . SimpleXLSX::parseError();
    }
}

header('Location: barang.php');
exit;
?>
