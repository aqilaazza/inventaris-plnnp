<?php
/**
 * Setup Script - Jalankan sekali untuk membuat database dan user default
 * Akses: http://localhost/inventaris/setup.php
 * HAPUS FILE INI SETELAH SETUP SELESAI!
 */

$host = 'localhost';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Baca dan jalankan SQL
$sql = file_get_contents(__DIR__ . '/database/db_inventaris.sql');

// Jalankan multi query
$conn->multi_query($sql);

// Tunggu semua query selesai
while ($conn->next_result()) {;}

// Sekarang connect ke database yang sudah dibuat
$conn->close();
$conn = new mysqli($host, $user, $pass, 'db_inventaris');
$conn->set_charset("utf8mb4");

// Cek apakah user sudah ada
$check = $conn->query("SELECT COUNT(*) as total FROM users");
$row = $check->fetch_assoc();

if ($row['total'] == 0) {
    // Insert default users
    $admin_pass = password_hash('admin123', PASSWORD_DEFAULT);
    $petugas_pass = password_hash('petugas123', PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (username, password, nama_lengkap, level) VALUES (?, ?, ?, ?)");
    
    $username = 'admin';
    $nama = 'Administrator';
    $level = 'admin';
    $stmt->bind_param("ssss", $username, $admin_pass, $nama, $level);
    $stmt->execute();

    $username = 'petugas';
    $nama = 'Petugas Inventaris';
    $level = 'petugas';
    $stmt->bind_param("ssss", $username, $petugas_pass, $nama, $level);
    $stmt->execute();

    $stmt->close();
    
    echo "<h2 style='color: green; font-family: Arial;'>✅ Setup berhasil!</h2>";
    echo "<p style='font-family: Arial;'>Database <b>db_inventaris</b> berhasil dibuat.</p>";
    echo "<p style='font-family: Arial;'>User default:</p>";
    echo "<ul style='font-family: Arial;'>";
    echo "<li><b>Admin</b> - Username: admin | Password: admin123</li>";
    echo "<li><b>Petugas</b> - Username: petugas | Password: petugas123</li>";
    echo "</ul>";
    echo "<p style='font-family: Arial; color: red;'><b>⚠️ HAPUS FILE setup.php SETELAH SELESAI!</b></p>";
    echo "<p style='font-family: Arial;'><a href='index.php'>→ Ke Halaman Login</a></p>";
} else {
    echo "<h2 style='font-family: Arial;'>ℹ️ Database sudah pernah di-setup.</h2>";
    echo "<p style='font-family: Arial;'><a href='index.php'>→ Ke Halaman Login</a></p>";
}

$conn->close();
?>
