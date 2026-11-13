<?php
// Resolve the application base URL from the current script location.
$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$marker = '/inventra/';
$markerPosition = strpos($scriptPath, $marker);
$baseUrl = $markerPosition !== false
    ? substr($scriptPath, 0, $markerPosition + strlen('/inventra'))
    : '/inventra-updated/inventra';

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$namaAdmin = $_SESSION['nama_lengkap'] ?? 'Administrator';
$roleAdmin = $_SESSION['role'] ?? 'Admin';

$active = [
    'dashboard' => $currentPage === 'index.php',
    'barang' => in_array($currentPage, ['barang.php', 'barang-tambah.php'], true),
    'supplier' => $currentPage === 'supplier.php',
    'masuk' => in_array($currentPage, ['surat-jalan-masuk.php', 'barang-masuk.php'], true),
    'keluar' => in_array($currentPage, ['surat-jalan-keluar.php', 'barang-keluar.php'], true),
    'scan' => $currentPage === 'scan-kanban.php',
    'riwayat' => $currentPage === 'riwayat-surat-jalan.php',
    'produksi' => $currentPage === 'produksi.php',
    'laporan' => $currentPage === 'laporan.php',
];
?>

<aside class="sidebar">
    <div class="brand">
        <div class="brand-logo">
            <img src="<?= $baseUrl ?>/assets/img/logo-mark.png" alt="INVENTRA">
        </div>
        <div class="brand-text">
            <h2>INVENTRA</h2>
            <p>Inventory Management</p>
        </div>
    </div>

    <div class="menu-heading">MAIN MENU</div>
    <nav class="navigation">
        <a href="<?= $baseUrl ?>/index.php" class="menu-item <?= $active['dashboard'] ? 'active' : '' ?>">
            <span class="menu-icon">▦</span><span>Dashboard</span>
        </a>

        <div class="menu-heading submenu-heading">MASTER DATA</div>
        <a href="<?= $baseUrl ?>/pages/barang.php" class="menu-item <?= $active['barang'] ? 'active' : '' ?>">
            <span class="menu-icon">▣</span><span>Data Barang</span>
        </a>
        <a href="<?= $baseUrl ?>/pages/supplier.php" class="menu-item <?= $active['supplier'] ? 'active' : '' ?>">
            <span class="menu-icon">♧</span><span>Supplier</span>
        </a>

        <div class="menu-heading submenu-heading">SURAT JALAN</div>
        <a href="<?= $baseUrl ?>/pages/surat-jalan-masuk.php" class="menu-item <?= $active['masuk'] ? 'active' : '' ?>">
            <span class="menu-icon">↓</span><span>Surat Jalan Masuk</span>
        </a>
        <a href="<?= $baseUrl ?>/pages/surat-jalan-keluar.php" class="menu-item <?= $active['keluar'] ? 'active' : '' ?>">
            <span class="menu-icon">↑</span><span>Surat Jalan Keluar</span>
        </a>
        <a href="<?= $baseUrl ?>/pages/scan-kanban.php" class="menu-item <?= $active['scan'] ? 'active' : '' ?>">
            <span class="menu-icon">▦</span><span>Scan Kanban</span>
        </a>
        <a href="<?= $baseUrl ?>/pages/riwayat-surat-jalan.php" class="menu-item <?= $active['riwayat'] ? 'active' : '' ?>">
            <span class="menu-icon">▤</span><span>Riwayat Surat Jalan</span>
        </a>

        <div class="menu-heading submenu-heading">MANAGEMENT</div>
        <a href="<?= $baseUrl ?>/pages/produksi.php" class="menu-item <?= $active['produksi'] ? 'active' : '' ?>">
            <span class="menu-icon">⚙</span><span>PPIC &amp; Produksi</span>
        </a>
        <a href="<?= $baseUrl ?>/pages/laporan.php" class="menu-item <?= $active['laporan'] ? 'active' : '' ?>">
            <span class="menu-icon">▤</span><span>Laporan</span>
        </a>
    </nav>

    <div class="sidebar-bottom">
        <div class="admin-profile">
            <div class="admin-avatar"><?= htmlspecialchars(strtoupper(substr($namaAdmin, 0, 1)), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="admin-info">
                <strong><?= htmlspecialchars($namaAdmin, ENT_QUOTES, 'UTF-8') ?></strong>
                <span><?= htmlspecialchars($roleAdmin, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <span class="profile-dot"></span>
        </div>
        <div class="sidebar-version">
            <a href="<?= $baseUrl ?>/logout.php">Keluar / Logout</a>
            <small>INVENTRA SYSTEM v1.1</small>
        </div>
    </div>
</aside>
