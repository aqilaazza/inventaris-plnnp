<?php
session_start();

// Jika sudah login dengan level valid, redirect sesuai level
if (isset($_SESSION['user_id']) && !empty($_SESSION['level'])) {
    if ($_SESSION['level'] === 'admin') {
        header('Location: admin/index.php');
        exit;
    } elseif ($_SESSION['level'] === 'petugas') {
        header('Location: petugas/index.php');
        exit;
    }
}

// Sesi tidak valid (level tidak dikenal/tidak ada) -> reset agar tidak terjadi redirect loop
unset($_SESSION['user_id'], $_SESSION['level']);

$error = '';
if (isset($_SESSION['login_error'])) {
    $error = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | Sistem Inventaris Barang</title>
    <meta name="description" content="Sistem Informasi Inventaris Barang - Login">

    <!-- Google Font: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
    <!-- icheck bootstrap -->
    <link rel="stylesheet" href="plugins/icheck-bootstrap/icheck-bootstrap.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="dist/css/adminlte.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f3f4f6;
            color: #111827;
        }

        .login-wrapper {
            width: 100%;
            max-width: 420px;
            padding: 14px;
        }

        .login-card {
            background: #ffffff;
            border-radius: 22px;
            padding: 34px 30px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.08);
            border: 1px solid rgba(15, 23, 42, 0.08);
        }

        .login-logo {
            text-align: center;
            margin-bottom: 28px;
        }

        .logo-img {
            width: 120px;
            height: 120px;
            object-fit: contain;
            display: inline-block;
        }

        .login-logo h1 {
            color: #111827;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.3px;
        }

        .login-logo p {
            color: #6b7280;
            font-size: 13px;
            font-weight: 400;
            margin: 0;
        }

        .form-group {
            margin-bottom: 18px;
            position: relative;
        }

        .form-group label {
            color: #4b5563;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 14px;
            z-index: 2;
        }

        .form-control-custom {
            width: 100%;
            padding: 14px 16px 14px 44px;
            background: #f8fafc;
            border: 1px solid #d1d5db;
            border-radius: 14px;
            color: #111827;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            outline: none;
        }

        .form-control-custom::placeholder {
            color: #9ca3af;
        }

        .form-control-custom:focus {
            border-color: #6366f1;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12);
        }

        .input-wrapper:focus-within i {
            color: #6366f1;
        }

        .btn-login {
            width: 100%;
            padding: 13px;
            background: #4f46e5;
            border: none;
            border-radius: 14px;
            color: #ffffff;
            font-size: 15px;
            font-weight: 700;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
            letter-spacing: 0.3px;
            margin-top: 8px;
        }

        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 30px rgba(79, 70, 229, 0.18);
            background: #4338ca;
        }

        .alert-error {
            background: #fee2e2;
            border: 1px solid #fca5a5;
            border-radius: 12px;
            padding: 12px 16px;
            color: #b91c1c;
            font-size: 13px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error i {
            font-size: 16px;
            color: #b91c1c;
        }

        .footer-text {
            text-align: center;
            margin-top: 18px;
            color: #6b7280;
            font-size: 12px;
        }

        .password-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6b7280;
            cursor: pointer;
            padding: 4px;
            z-index: 2;
            transition: color 0.2s ease;
        }

        .password-toggle:hover {
            color: #111827;
        }

        @media (max-width: 520px) {
            .login-card {
                padding: 28px 22px;
                border-radius: 18px;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-logo">
                <img src="logo.png" alt="Logo" class="logo-img">
                <h1>Sistem Inventaris</h1>
                <p>Masuk ke akun Anda untuk melanjutkan</p>
            </div>

            <?php if ($error): ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form action="login_process.php" method="POST" id="loginForm">
                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="input-wrapper">
                        <input type="text" 
                               id="username" 
                               name="username" 
                               class="form-control-custom" 
                               placeholder="Masukkan username"
                               autocomplete="username"
                               required>
                        <i class="fas fa-user"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="form-control-custom" 
                               placeholder="Masukkan password"
                               autocomplete="current-password"
                               required>
                        <i class="fas fa-lock"></i>
                        <button type="button" class="password-toggle" onclick="togglePassword()" id="toggleBtn">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-login" id="btnLogin">
                    <i class="fas fa-sign-in-alt mr-2"></i> Masuk
                </button>
            </form>
        </div>

        <p class="footer-text">
            &copy; <?= date('Y') ?> Sistem Inventaris Barang. All rights reserved.
        </p>
    </div>

    <!-- Scripts -->
    <script src="plugins/jquery/jquery.min.js"></script>
    <script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="plugins/sweetalert2/sweetalert2.min.js"></script>
    <script src="dist/js/adminlte.min.js"></script>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const icon = document.getElementById('toggleIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
