<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'umumcsrc_inventaris';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>
