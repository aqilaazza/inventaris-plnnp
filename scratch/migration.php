<?php
include __DIR__ . '/../config/database.php';

echo "Memulai migrasi database...\n";

// 1. ALTER TABLE barang
$alter_query = "ALTER TABLE barang ADD COLUMN status_aktif ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif' AFTER kondisi";
if ($conn->query($alter_query)) {
    echo "✅ Kolom status_aktif berhasil ditambahkan ke tabel barang.\n";
} else {
    echo "⚠️ Gagal/Sudah ditambahkan: " . $conn->error . "\n";
}

// 2. CREATE TABLE periode_pengecekan
$create_periode = "CREATE TABLE IF NOT EXISTS periode_pengecekan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_periode VARCHAR(100) NOT NULL,
    tahun YEAR NOT NULL,
    tgl_mulai DATE NOT NULL,
    tgl_selesai DATE NOT NULL,
    status ENUM('aktif','selesai') NOT NULL DEFAULT 'aktif',
    id_user_pembuat INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_user_pembuat) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB";

if ($conn->query($create_periode)) {
    echo "✅ Tabel periode_pengecekan berhasil dibuat.\n";
} else {
    echo "❌ Gagal membuat tabel periode_pengecekan: " . $conn->error . "\n";
}

// 3. CREATE TABLE pengecekan_barang
$create_pengecekan = "CREATE TABLE IF NOT EXISTS pengecekan_barang (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_periode INT NOT NULL,
    id_barang INT NOT NULL,
    id_petugas INT NOT NULL,
    kondisi_temuan ENUM('Baik','Rusak','Hilang') NOT NULL,
    catatan TEXT NULL,
    foto_bukti VARCHAR(255) NULL,
    status_review ENUM('menunggu','disetujui','ditolak') NOT NULL DEFAULT 'menunggu',
    id_reviewer INT NULL,
    catatan_reviewer TEXT NULL,
    tgl_review DATETIME NULL,
    tgl_pengecekan TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_periode) REFERENCES periode_pengecekan(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_barang) REFERENCES barang(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_petugas) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_reviewer) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB";

if ($conn->query($create_pengecekan)) {
    echo "✅ Tabel pengecekan_barang berhasil dibuat.\n";
} else {
    echo "❌ Gagal membuat tabel pengecekan_barang: " . $conn->error . "\n";
}

// 4. CREATE TABLE penghapusan_barang
$create_penghapusan = "CREATE TABLE IF NOT EXISTS penghapusan_barang (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_barang INT NOT NULL,
    alasan ENUM('Rusak','Hilang','Hibah','Lelang','Musnah','Tidak Digunakan','Lainnya') NOT NULL,
    keterangan TEXT NOT NULL,
    dokumen_pendukung VARCHAR(255) NULL,
    tgl_penghapusan DATE NOT NULL,
    tujuan_hibah VARCHAR(255) NULL,
    id_admin INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_barang) REFERENCES barang(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_admin) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB";

if ($conn->query($create_penghapusan)) {
    echo "✅ Tabel penghapusan_barang berhasil dibuat.\n";
} else {
    echo "❌ Gagal membuat tabel penghapusan_barang: " . $conn->error . "\n";
}

$conn->close();
echo "Migrasi selesai!\n";
?>
