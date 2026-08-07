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
                <small><i class="fas fa-circle text-success mr-1" style="font-size:7px;"></i> Admin</small>
            </div>
        </div>

        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column nav-child-indent" data-widget="treeview" role="menu"
                data-accordion="true">

                <!-- Dashboard -->
                <li class="nav-item">
                    <a href="index.php" class="nav-link <?= ($active_menu ?? '') === 'dashboard' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>Dashboard</p>
                    </a>
                </li>

                <!-- Master Data -->
                <li class="nav-header">MASTER DATA</li>
                <li
                    class="nav-item <?= in_array(($active_menu ?? ''), ['unit', 'ruang', 'jenis', 'kategori', 'merk', 'barang', 'barang_qr']) ? 'menu-open' : '' ?>">
                    <a href="#"
                        class="nav-link <?= in_array(($active_menu ?? ''), ['unit', 'ruang', 'jenis', 'kategori', 'merk', 'barang', 'barang_qr']) ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-database"></i>
                        <p>
                            Master Data
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="unit.php" class="nav-link <?= ($active_menu ?? '') === 'unit' ? 'active' : '' ?>">
                                <i class="nav-icon fas fa-building"></i>
                                <p>Unit</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="ruang.php"
                                class="nav-link <?= ($active_menu ?? '') === 'ruang' ? 'active' : '' ?>">
                                <i class="nav-icon fas fa-door-open"></i>
                                <p>Ruang</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="jenis.php"
                                class="nav-link <?= ($active_menu ?? '') === 'jenis' ? 'active' : '' ?>">
                                <i class="nav-icon fas fa-tags"></i>
                                <p>Jenis</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="kategori.php"
                                class="nav-link <?= ($active_menu ?? '') === 'kategori' ? 'active' : '' ?>">
                                <i class="nav-icon fas fa-th-large"></i>
                                <p>Kategori</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="merk.php" class="nav-link <?= ($active_menu ?? '') === 'merk' ? 'active' : '' ?>">
                                <i class="nav-icon fas fa-copyright"></i>
                                <p>Merk</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="barang.php"
                                class="nav-link <?= ($active_menu ?? '') === 'barang' ? 'active' : '' ?>">
                                <i class="nav-icon fas fa-box"></i>
                                <p>Barang</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="barang_qr.php"
                                class="nav-link <?= ($active_menu ?? '') === 'barang_qr' ? 'active' : '' ?>">
                                <i class="nav-icon fas fa-qrcode"></i>
                                <p>Cetak QR Code</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Pengecekan & Penghapusan -->
                <?php
                // Hitung review pending untuk badge
                $pending_count = 0;
                if (isset($conn)) {
                    $res_pending = $conn->query("SELECT COUNT(*) as total FROM pengecekan_barang WHERE status_review = 'menunggu'");
                    if ($res_pending) {
                        $row_pending = $res_pending->fetch_assoc();
                        $pending_count = intval($row_pending['total']);
                    }
                }
                ?>
                <li class="nav-header">PENGECEKAN</li>
                <li class="nav-item">
                    <a href="periode_pengecekan.php" class="nav-link <?= ($active_menu ?? '') === 'periode_pengecekan' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-calendar-alt"></i>
                        <p>Periode Pengecekan</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="review_pengecekan.php" class="nav-link <?= ($active_menu ?? '') === 'review_pengecekan' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-clipboard-check"></i>
                        <p>
                            Review Pengecekan
                            <?php if ($pending_count > 0): ?>
                                <span class="badge badge-warning right"><?= $pending_count ?></span>
                            <?php endif; ?>
                        </p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="monitoring_pengecekan.php" class="nav-link <?= ($active_menu ?? '') === 'monitoring_pengecekan' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-chart-pie"></i>
                        <p>Monitoring Cek</p>
                    </a>
                </li>

                <!-- Penghapusan -->
                <li class="nav-header">PENGHAPUSAN</li>
                <li class="nav-item">
                    <a href="penghapusan_barang.php" class="nav-link <?= ($active_menu ?? '') === 'penghapusan_barang' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-minus-circle"></i>
                        <p>Penghapusan Barang</p>
                    </a>
                </li>

                <!-- Laporan -->
                <li class="nav-header">LAPORAN</li>
                <li class="nav-item">
                    <a href="laporan_inventaris.php"
                        class="nav-link <?= ($active_menu ?? '') === 'laporan_inventaris' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-file-invoice"></i>
                        <p>Inventarisasi</p>
                    </a>
                </li>

                <!-- Separator -->
                <li class="nav-header">AKUN</li>
                <li class="nav-item">
                    <a href="users.php" class="nav-link <?= ($active_menu ?? '') === 'users' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-users-cog"></i>
                        <p>Manajemen User</p>
                    </a>
                </li>
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