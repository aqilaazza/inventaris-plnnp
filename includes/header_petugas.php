<?php
if (!isset($_SESSION['user_id']) || $_SESSION['level'] !== 'petugas') {
    header('Location: ../index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $page_title ?? 'Dashboard' ?> | Petugas</title>
    <meta name="description" content="Sistem Informasi Inventaris Barang - Panel Petugas">

    <!-- Google Font: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="../plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="../plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
    <link rel="stylesheet" href="../plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="../plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">
    <!-- Select2 -->
    <link rel="stylesheet" href="../plugins/select2/css/select2.min.css">
    <link rel="stylesheet" href="../plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="../dist/css/adminlte.min.css">

    <!-- jQuery -->
    <script src="../plugins/jquery/jquery.min.js"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif !important;
        }

        /* Sidebar custom styling (Slightly different gradient for Petugas - Indigo to Violet) */
        .main-sidebar {
            background: linear-gradient(180deg, #312e81 0%, #4c1d95 100%) !important;
        }

        .main-sidebar .brand-link {
            border-bottom: 1px solid rgba(255,255,255,0.08);
            padding: 16px 20px;
        }

        .main-sidebar .brand-text {
            font-weight: 700 !important;
            font-size: 16px;
            letter-spacing: -0.3px;
        }

        .sidebar .nav-link {
            border-radius: 10px !important;
            margin: 2px 12px;
            padding: 10px 16px !important;
            font-size: 13.5px;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.65) !important;
            transition: all 0.2s ease;
        }

        .sidebar .nav-link:hover {
            background: rgba(255, 255, 255, 0.08) !important;
            color: #fff !important;
        }

        .sidebar .nav-link.active {
            background: rgba(139, 92, 246, 0.3) !important;
            color: #fff !important;
        }

        .sidebar .nav-link .nav-icon {
            font-size: 14px;
            width: 22px;
            text-align: center;
        }

        .sidebar .nav-header {
            color: rgba(255, 255, 255, 0.3) !important;
            font-size: 11px;
            letter-spacing: 1px;
            text-transform: uppercase;
            padding: 16px 24px 8px;
            font-weight: 600;
        }

        /* Navbar */
        .main-header.navbar {
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }

        /* Content */
        .content-wrapper {
            background: #f3f4f6 !important;
        }

        .content-header h1 {
            font-size: 22px;
            font-weight: 700;
            color: #1f2937;
            letter-spacing: -0.3px;
        }

        .breadcrumb {
            font-size: 13px;
        }

        /* Cards */
        .card {
            border: none;
            border-radius: 14px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
            transition: box-shadow 0.2s ease;
        }

        .card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .card-header {
            border-bottom: 1px solid #f3f4f6;
            background: transparent;
            padding: 8px 15px;
        }

        .card-header .card-title {
            font-weight: 600;
            font-size: 14px;
            color: #1f2937;
        }

        .card-body {
            padding: 10px 15px;
        }

        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, #8b5cf6, #ec4899) !important;
            border: none !important;
            border-radius: 8px;
            font-weight: 600;
            font-size: 12px;
            padding: 8px 16px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(139, 92, 246, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(139, 92, 246, 0.4);
        }

        /* Small stat cards */
        .small-box {
            border-radius: 14px;
            overflow: hidden;
        }

        .small-box .inner h3 {
            font-weight: 800;
            font-size: 28px;
        }

        .small-box .inner p {
            font-weight: 500;
        }

        /* Badge */
        .badge {
            font-weight: 600;
            font-size: 11px;
            padding: 5px 10px;
            border-radius: 6px;
        }

        /* User panel */
        .user-panel .info a {
            font-weight: 600 !important;
            font-size: 13.5px;
        }

        .user-panel .info small {
            display: block;
            color: rgba(255,255,255,0.4);
            font-size: 11px;
            margin-top: 2px;
        }

        /* Page transition */
        .content-wrapper {
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
        </ul>

        <ul class="navbar-nav ml-auto">
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" data-toggle="dropdown" href="#" style="font-size:13px; font-weight:500; color:#374151;">
                    <i class="fas fa-user-circle mr-1" style="font-size:16px; color:#8b5cf6;"></i>
                    <?= htmlspecialchars($_SESSION['nama_lengkap']) ?>
                </a>
                <div class="dropdown-menu dropdown-menu-right" style="border-radius:12px; border:none; box-shadow: 0 10px 30px rgba(0,0,0,0.12); padding:8px;">
                    <a class="dropdown-item" href="#" style="border-radius:8px; font-size:13px; padding:10px 16px;">
                        <i class="fas fa-user mr-2 text-muted"></i> Profil
                    </a>
                    <div class="dropdown-divider" style="margin:4px 12px;"></div>
                    <a class="dropdown-item text-danger" href="../logout.php" style="border-radius:8px; font-size:13px; padding:10px 16px;">
                        <i class="fas fa-sign-out-alt mr-2"></i> Logout
                    </a>
                </div>
            </li>
        </ul>
    </nav>
