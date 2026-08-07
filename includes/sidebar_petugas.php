<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="index.php" class="brand-link" style="text-decoration:none;">
        <span class="brand-image" style="display:inline-flex; align-items:center; justify-content:center; width:33px; height:33px; border-radius:10px; margin-left:6px; overflow:hidden; background:#ffffff;">
            <img src="../logo.png" alt="Logo" style="width:100%; height:100%; object-fit:contain;">
        </span>
        <span class="brand-text" style="color:#fff;">Inventaris</span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
        <!-- User Panel -->
        <div class="user-panel mt-3 pb-3 mb-3 d-flex" style="border-bottom: 1px solid rgba(255,255,255,0.08);">
            <div class="image" style="padding-top:2px;">
                <i class="fas fa-user-circle text-light" style="font-size:28px; opacity:0.7;"></i>
            </div>
            <div class="info">
                <a href="#" class="d-block" style="color:#fff; text-decoration:none;">
                    <?= htmlspecialchars($_SESSION['nama_lengkap']) ?>
                </a>
                <small><i class="fas fa-circle text-success mr-1" style="font-size:7px;"></i> Petugas</small>
            </div>
        </div>

        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu"
                data-accordion="false">

                <!-- Dashboard -->
                <li class="nav-item">
                    <a href="index.php" class="nav-link <?= ($active_menu ?? '') === 'dashboard' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>Dashboard</p>
                    </a>
                </li>

                <!-- Sensus / Pengecekan -->
                <li class="nav-header">PENGECEKAN BARANG</li>
                <li class="nav-item">
                    <a href="scan_barang.php" class="nav-link <?= ($active_menu ?? '') === 'scan_barang' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-qrcode"></i>
                        <p>Scan & Cek Barang</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="scan_fast.php" class="nav-link <?= ($active_menu ?? '') === 'scan_fast' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-bolt text-warning"></i>
                        <p>Cek Barang Mode Fast</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="scan_fast_2.php" class="nav-link <?= ($active_menu ?? '') === 'scan_fast_2' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-bolt text-success"></i>
                        <p>Cek Fast 2 (Tabel)</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="riwayat_pengecekan.php" class="nav-link <?= ($active_menu ?? '') === 'riwayat_pengecekan' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-history"></i>
                        <p>Riwayat Pengecekan Saya</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="hasil_pengecekan.php" class="nav-link <?= ($active_menu ?? '') === 'hasil_pengecekan' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-clipboard-check"></i>
                        <p>Hasil Pengecekan</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="update_gambar.php" class="nav-link <?= ($active_menu ?? '') === 'update_gambar' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-image"></i>
                        <p>Update Gambar Barang</p>
                    </a>
                </li>

                <!-- Akun & Logout -->
                <li class="nav-header">AKUN</li>
                <li class="nav-item">
                    <a href="../logout.php" class="nav-link">
                        <i class="nav-icon fas fa-sign-out-alt"></i>
                        <p>Logout</p>
                    </a>
                </li>

            </ul>
        </nav>
    </div>
</aside>

<!-- Content Wrapper -->
<div class="content-wrapper">
