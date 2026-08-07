<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

include 'config/database.php';

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    $_SESSION['login_error'] = 'Username dan password harus diisi!';
    header('Location: index.php');
    exit;
}

// Cari user berdasarkan username
$stmt = $conn->prepare("SELECT id, username, password, nama_lengkap, level FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    
    if (password_verify($password, $user['password'])) {
        // Login berhasil
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
        $_SESSION['level'] = $user['level'];

        // Redirect sesuai level
        if ($user['level'] === 'admin') {
            header('Location: admin/index.php');
        } else {
            header('Location: petugas/index.php');
        }
        exit;
    } else {
        $_SESSION['login_error'] = 'Password yang Anda masukkan salah!';
    }
} else {
    $_SESSION['login_error'] = 'Username tidak ditemukan!';
}

$stmt->close();
$conn->close();

header('Location: index.php');
exit;
?>
