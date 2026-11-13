
<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

/*
|--------------------------------------------------------------------------
| KONFIGURASI
|--------------------------------------------------------------------------
*/

$baseUrl = '/inventra-updated/inventra';

$namaAdmin = $_SESSION['nama_lengkap'] ?? 'Administrator';
$roleAdmin = $_SESSION['role'] ?? 'Admin';

/*
|--------------------------------------------------------------------------
| ANIMASI SAMBUTAN SETELAH LOGIN
|--------------------------------------------------------------------------
*/

$showWelcome = !empty($_SESSION['login_success']);
unset($_SESSION['login_success']);

/*
|--------------------------------------------------------------------------
| DATA DASHBOARD
|--------------------------------------------------------------------------
*/

// Total barang
$totalBarang = (int)$pdo->query("
    SELECT COUNT(*)
    FROM barang
")->fetchColumn();

// Total supplier
$totalSupplier = (int)$pdo->query("
    SELECT COUNT(*)
    FROM supplier
")->fetchColumn();

// Total barang masuk
$totalMasuk = (int)$pdo->query("
    SELECT COALESCE(SUM(jumlah), 0)
    FROM barang_masuk
")->fetchColumn();

// Total barang keluar
$totalKeluar = (int)$pdo->query("
    SELECT COALESCE(SUM(jumlah), 0)
    FROM barang_keluar
")->fetchColumn();

// Stok minimum
$stokMenipis = (int)$pdo->query("
    SELECT COUNT(*)
    FROM barang b
    WHERE (
        COALESCE(
            (
                SELECT SUM(bm.jumlah)
                FROM barang_masuk bm
                WHERE bm.barang_id = b.id
            ), 0
        )
        -
        COALESCE(
            (
                SELECT SUM(bk.jumlah)
                FROM barang_keluar bk
                WHERE bk.barang_id = b.id
            ), 0
        )
    ) <= b.stok_minimum
")->fetchColumn();

// Total pergerakan transaksi
$totalTransaksi = $totalMasuk + $totalKeluar;

// Persentase barang masuk
$persenMasuk = $totalTransaksi > 0
    ? round(($totalMasuk / $totalTransaksi) * 100)
    : 0;

// Persentase barang keluar
$persenKeluar = $totalTransaksi > 0
    ? round(($totalKeluar / $totalTransaksi) * 100)
    : 0;

// Tanggal dan hari
$tanggal = date('d-m-Y');

$hari = [
    'Sunday' => 'Minggu',
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu'
];

$namaHari = $hari[date('l')];

function h($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>INVENTRA | Dashboard</title>

    <link
        rel="stylesheet"
        href="<?= $baseUrl ?>/assets/css/style.css"
    >

    <style>
        /* ANIMASI DASHBOARD */

        .main-content {
            animation: dashboardAppear .7s ease both;
        }

        .stat-card {
            animation: cardAppear .6s ease both;
        }

        .stat-card:nth-child(2) {
            animation-delay: .08s;
        }

        .stat-card:nth-child(3) {
            animation-delay: .16s;
        }

        .stat-card:nth-child(4) {
            animation-delay: .24s;
        }

        .dashboard-welcome {
            position: fixed;
            inset: 0;
            z-index: 99999;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #103c2e, #15965b);
            color: white;
            text-align: center;
            animation: welcomeDisappear 2s ease forwards;
            pointer-events: none;
        }

        .dashboard-welcome .welcome-logo {
            width: 76px;
            height: 76px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            color: #15965b;
            border-radius: 22px;
            font-size: 38px;
            font-weight: 900;
            margin-bottom: 20px;
            box-shadow: 0 10px 35px rgba(0,0,0,.2);
            animation: logoPulse 1s ease infinite alternate;
        }

        .dashboard-welcome h2 {
            font-size: 30px;
            letter-spacing: 3px;
            margin-bottom: 8px;
        }

        .dashboard-welcome p {
            font-size: 12px;
            letter-spacing: 2px;
        }

        @keyframes dashboardAppear {
            from {
                opacity: 0;
                transform: translateY(15px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes cardAppear {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes logoPulse {
            from {
                transform: scale(.95);
            }
            to {
                transform: scale(1.08);
            }
        }

        @keyframes welcomeDisappear {
            0%, 65% {
                opacity: 1;
                visibility: visible;
            }
            100% {
                opacity: 0;
                visibility: hidden;
            }
        }

        /* AKSES CEPAT */

        .quick-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }

        .quick-card {
            min-width: 0;
        }

        @media(max-width: 950px) {
            .quick-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media(max-width: 600px) {
            .quick-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

</head>

<body>

<?php if ($showWelcome): ?>
    <div class="dashboard-welcome">
        <div class="welcome-logo">I</div>
        <h2>INVENTRA</h2>
        <p>WELCOME TO YOUR DASHBOARD</p>
    </div>
<?php endif; ?>

<div class="dashboard">

    <!-- SIDEBAR BARU -->
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>


    <!-- MAIN CONTENT -->
    <main class="main-content">

        <!-- TOPBAR -->
        <header class="topbar">

            <div class="page-title">
                <h1>Dashboard Overview</h1>
                <p>Ringkasan informasi persediaan perusahaan.</p>
            </div>

            <div class="topbar-actions">

                <div class="date-box">
                    <span class="date-icon">▦</span>

                    <div>
                        <strong><?= h($namaHari) ?></strong>
                        <span><?= h($tanggal) ?></span>
                    </div>
                </div>

                <div class="notification">
                    <span>♧</span>

                    <?php if ($stokMenipis > 0): ?>
                        <i></i>
                    <?php endif; ?>
                </div>

                <div class="top-admin">
                    <div class="top-avatar">
                        <?= h(strtoupper(substr($namaAdmin, 0, 1))) ?>
                    </div>

                    <div>
                        <strong><?= h($namaAdmin) ?></strong>
                        <span><?= h($roleAdmin) ?></span>
                    </div>
                </div>

            </div>

        </header>


        <!-- WELCOME BANNER -->
        <section class="welcome-banner">

            <div class="welcome-content">

                <span class="welcome-tag">
                    INVENTORY & PRODUCTION CONTROL SYSTEM
                </span>

                <h2>Selamat Datang di INVENTRA</h2>

                <p>
                    Kelola persediaan, pantau aktivitas gudang,
                    dan kontrol produksi perusahaan melalui
                    satu sistem terintegrasi.
                </p>

                <a
                    href="<?= $baseUrl ?>/pages/barang.php"
                    class="welcome-button"
                >
                    Kelola Inventaris
                    <span>→</span>
                </a>

            </div>

            <div class="banner-decoration">
                <div class="banner-circle circle-one"></div>
                <div class="banner-circle circle-two"></div>
                <div class="banner-symbol">▦</div>
            </div>

        </section>


        <!-- STATISTIK INVENTARIS -->
        <section class="section-block">

            <div class="section-heading">

                <div>
                    <h2>Ringkasan Inventaris</h2>
                    <p>Statistik keseluruhan sistem INVENTRA</p>
                </div>

                <span class="live-label">
                    <i></i> LIVE DATA
                </span>

            </div>


            <div class="stats-grid">

                <!-- TOTAL BARANG -->
                <div class="stat-card">

                    <div class="stat-card-top">

                        <div class="stat-icon icon-blue">
                            ▣
                        </div>

                        <span class="stat-category">
                            INVENTORY
                        </span>

                    </div>

                    <p class="stat-label">Total Barang</p>

                    <h3>
                        <?= number_format($totalBarang, 0, ',', '.') ?>
                    </h3>

                    <div class="stat-footer">
                        <span class="stat-indicator blue-indicator">●</span>
                        Jenis barang terdaftar
                    </div>

                </div>


                <!-- TOTAL SUPPLIER -->
                <div class="stat-card">

                    <div class="stat-card-top">

                        <div class="stat-icon icon-purple">
                            ♧
                        </div>

                        <span class="stat-category">
                            PARTNER
                        </span>

                    </div>

                    <p class="stat-label">Total Supplier</p>

                    <h3>
                        <?= number_format($totalSupplier, 0, ',', '.') ?>
                    </h3>

                    <div class="stat-footer">
                        <span class="stat-indicator purple-indicator">●</span>
                        Pemasok terdaftar
                    </div>

                </div>


                <!-- BARANG MASUK -->
                <div class="stat-card">

                    <div class="stat-card-top">

                        <div class="stat-icon icon-green">
                            ↓
                        </div>

                        <span class="stat-category">
                            INCOMING
                        </span>

                    </div>

                    <p class="stat-label">Barang Masuk</p>

                    <h3>
                        <?= number_format($totalMasuk, 0, ',', '.') ?>
                    </h3>

                    <div class="stat-footer">
                        <span class="stat-indicator green-indicator">●</span>
                        Total unit diterima
                    </div>

                </div>


                <!-- BARANG KELUAR -->
                <div class="stat-card">

                    <div class="stat-card-top">

                        <div class="stat-icon icon-orange">
                            ↑
                        </div>

                        <span class="stat-category">
                            OUTGOING
                        </span>

                    </div>

                    <p class="stat-label">Barang Keluar</p>

                    <h3>
                        <?= number_format($totalKeluar, 0, ',', '.') ?>
                    </h3>

                    <div class="stat-footer">
                        <span class="stat-indicator orange-indicator">●</span>
                        Total unit dikeluarkan
                    </div>

                </div>

            </div>

        </section>


        <!-- ANALISIS DAN MONITORING -->
        <section class="analytics-grid">


            <!-- ANALISIS TRANSAKSI -->
            <div class="content-panel">

                <div class="panel-heading">

                    <div>
                        <h3>Analisis Transaksi</h3>
                        <p>Perbandingan barang masuk dan keluar</p>
                    </div>

                    <span class="panel-badge">
                        Keseluruhan
                    </span>

                </div>


                <div class="transaction-total">

                    <div>
                        <span>Total Pergerakan Barang</span>

                        <h2>
                            <?= number_format($totalTransaksi, 0, ',', '.') ?>
                            <small>unit</small>
                        </h2>
                    </div>

                    <div class="transaction-icon">
                        ⇄
                    </div>

                </div>


                <div class="chart-container">


                    <!-- GRAFIK BARANG MASUK -->
                    <div class="chart-row">

                        <div class="chart-label">

                            <span>
                                <i class="chart-dot dot-green"></i>
                                Barang Masuk
                            </span>

                            <strong><?= $persenMasuk ?>%</strong>

                        </div>

                        <div class="chart-track">

                            <div
                                class="chart-bar bar-green"
                                style="width: <?= $persenMasuk ?>%"
                            ></div>

                        </div>

                        <p>
                            <?= number_format($totalMasuk, 0, ',', '.') ?>
                            unit diterima
                        </p>

                    </div>


                    <!-- GRAFIK BARANG KELUAR -->
                    <div class="chart-row">

                        <div class="chart-label">

                            <span>
                                <i class="chart-dot dot-orange"></i>
                                Barang Keluar
                            </span>

                            <strong><?= $persenKeluar ?>%</strong>

                        </div>

                        <div class="chart-track">

                            <div
                                class="chart-bar bar-orange"
                                style="width: <?= $persenKeluar ?>%"
                            ></div>

                        </div>

                        <p>
                            <?= number_format($totalKeluar, 0, ',', '.') ?>
                            unit dikeluarkan
                        </p>

                    </div>

                </div>


                <div class="analytics-note">
                    Data perbandingan dihitung berdasarkan
                    total transaksi yang tersimpan di database.
                </div>

            </div>


            <!-- MONITORING PERSEDIAAN -->
            <div class="content-panel">

                <div class="panel-heading">

                    <div>
                        <h3>Monitoring Persediaan</h3>
                        <p>Kondisi stok barang minimum</p>
                    </div>

                    <span class="warning-badge">
                        STOCK ALERT
                    </span>

                </div>


                <div class="stock-alert">

                    <div class="stock-alert-icon">
                        !
                    </div>

                    <div>
                        <span>Barang perlu diperiksa</span>

                        <h2>
                            <?= number_format($stokMenipis, 0, ',', '.') ?>
                            <small>barang</small>
                        </h2>
                    </div>

                </div>


                <?php if ($stokMenipis > 0): ?>

                    <div class="stock-message warning-message">
                        Terdapat barang yang stoknya mencapai
                        batas minimum atau berada di bawahnya.
                        Segera periksa persediaan.
                    </div>

                <?php else: ?>

                    <div class="stock-message success-message">
                        Tidak ada barang yang mencapai batas
                        stok minimum berdasarkan data saat ini.
                    </div>

                <?php endif; ?>


                <a
                    href="<?= $baseUrl ?>/pages/barang.php"
                    class="panel-link"
                >
                    Periksa Data Barang
                    <span>→</span>
                </a>

            </div>

        </section>


        <!-- AKSES CEPAT -->
        <section class="section-block quick-section">

            <div class="section-heading">

                <div>
                    <h2>Akses Cepat</h2>
                    <p>Pilih modul untuk mengelola sistem INVENTRA</p>
                </div>

            </div>


            <div class="quick-grid">


                <!-- DATA BARANG -->
                <a
                    href="<?= $baseUrl ?>/pages/barang.php"
                    class="quick-card"
                >

                    <div class="quick-icon quick-blue">▣</div>

                    <div>
                        <h3>Data Barang</h3>
                        <p>Kelola data inventaris</p>
                    </div>

                    <span class="quick-arrow">→</span>

                </a>


                <!-- SUPPLIER -->
                <a
                    href="<?= $baseUrl ?>/pages/supplier.php"
                    class="quick-card"
                >

                    <div class="quick-icon quick-purple">♧</div>

                    <div>
                        <h3>Supplier</h3>
                        <p>Kelola data pemasok</p>
                    </div>

                    <span class="quick-arrow">→</span>

                </a>


                <!-- SURAT JALAN MASUK -->
                <a
                    href="<?= $baseUrl ?>/pages/surat-jalan-masuk.php"
                    class="quick-card"
                >

                    <div class="quick-icon quick-green">↓</div>

                    <div>
                        <h3>Surat Jalan Masuk</h3>
                        <p>Catat penerimaan barang</p>
                    </div>

                    <span class="quick-arrow">→</span>

                </a>


                <!-- SURAT JALAN KELUAR -->
                <a
                    href="<?= $baseUrl ?>/pages/surat-jalan-keluar.php"
                    class="quick-card"
                >

                    <div class="quick-icon quick-orange">↑</div>

                    <div>
                        <h3>Surat Jalan Keluar</h3>
                        <p>Catat pengeluaran barang</p>
                    </div>

                    <span class="quick-arrow">→</span>

                </a>


                <!-- SCAN KANBAN -->
                <a
                    href="<?= $baseUrl ?>/pages/scan-kanban.php"
                    class="quick-card"
                >

                    <div class="quick-icon quick-green">▦</div>

                    <div>
                        <h3>Scan Kanban</h3>
                        <p>Pemindaian barcode barang</p>
                    </div>

                    <span class="quick-arrow">→</span>

                </a>


                <!-- RIWAYAT SURAT JALAN -->
                <a
                    href="<?= $baseUrl ?>/pages/riwayat-surat-jalan.php"
                    class="quick-card"
                >

                    <div class="quick-icon quick-blue">▤</div>

                    <div>
                        <h3>Riwayat Surat Jalan</h3>
                        <p>Lihat transaksi masuk dan keluar</p>
                    </div>

                    <span class="quick-arrow">→</span>

                </a>


                <!-- PRODUKSI -->
                <a
                    href="<?= $baseUrl ?>/pages/produksi.php"
                    class="quick-card"
                >

                    <div class="quick-icon quick-purple">⚙</div>

                    <div>
                        <h3>PPIC & Produksi</h3>
                        <p>Perencanaan produksi</p>
                    </div>

                    <span class="quick-arrow">→</span>

                </a>


                <!-- LAPORAN -->
                <a
                    href="<?= $baseUrl ?>/pages/laporan.php"
                    class="quick-card"
                >

                    <div class="quick-icon quick-orange">▤</div>

                    <div>
                        <h3>Laporan</h3>
                        <p>Informasi dan rekap data</p>
                    </div>

                    <span class="quick-arrow">→</span>

                </a>


            </div>

        </section>


        <!-- FOOTER -->
        <footer class="main-footer">

            <div>
                <strong>INVENTRA</strong>
                <span>Inventory & Production Control System</span>
            </div>

            <p>
                © 2026 INVENTRA. All Rights Reserved.
            </p>

        </footer>


    </main>

</div>

</body>
</html>
