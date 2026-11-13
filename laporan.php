
<?php
require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . "/../config/database.php";

function e($value) {
    return htmlspecialchars((string)($value ?? '-'), ENT_QUOTES, 'UTF-8');
}

// FILTER TANGGAL
$tanggalAwal = $_GET['awal'] ?? date('Y-m-01');
$tanggalAkhir = $_GET['akhir'] ?? date('Y-m-d');

function validTanggal($tanggal) {
    $date = DateTime::createFromFormat('Y-m-d', $tanggal);
    return $date && $date->format('Y-m-d') === $tanggal;
}

if (!validTanggal($tanggalAwal)) {
    $tanggalAwal = date('Y-m-01');
}

if (!validTanggal($tanggalAkhir) || $tanggalAkhir < $tanggalAwal) {
    $tanggalAkhir = date('Y-m-d');
}

// DATA PERSEDIAAN
$barang = $pdo->query("
    SELECT
        b.id,
        b.kode_barang,
        b.nama_barang,
        b.satuan,
        b.stok_minimum,
        k.nama_kategori,

        COALESCE((
            SELECT SUM(bm.jumlah)
            FROM barang_masuk bm
            WHERE bm.barang_id = b.id
        ), 0) AS total_masuk,

        COALESCE((
            SELECT SUM(bk.jumlah)
            FROM barang_keluar bk
            WHERE bk.barang_id = b.id
        ), 0) AS total_keluar

    FROM barang b
    LEFT JOIN kategori k ON b.kategori_id = k.id
    ORDER BY b.nama_barang ASC
")->fetchAll(PDO::FETCH_ASSOC);

// HITUNG STOK
$totalBarang = count($barang);
$totalStok = 0;
$totalJenisMenipis = 0;

foreach ($barang as &$b) {
    $b['stok'] = (int)$b['total_masuk'] - (int)$b['total_keluar'];

    $totalStok += $b['stok'];

    if ($b['stok'] <= (int)$b['stok_minimum']) {
        $totalJenisMenipis++;
    }
}
unset($b);

// REKAP BARANG MASUK
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS transaksi,
        COALESCE(SUM(jumlah), 0) AS total
    FROM barang_masuk
    WHERE tanggal BETWEEN ? AND ?
");
$stmt->execute([$tanggalAwal, $tanggalAkhir]);
$rekapMasuk = $stmt->fetch(PDO::FETCH_ASSOC);

// REKAP BARANG KELUAR
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS transaksi,
        COALESCE(SUM(jumlah), 0) AS total
    FROM barang_keluar
    WHERE tanggal BETWEEN ? AND ?
");
$stmt->execute([$tanggalAwal, $tanggalAkhir]);
$rekapKeluar = $stmt->fetch(PDO::FETCH_ASSOC);

// REKAP PRODUKSI
try {
    $totalProduksi = (int)$pdo->query("
        SELECT COUNT(*) FROM produksi
    ")->fetchColumn();
} catch (PDOException $ex) {
    $totalProduksi = 0;
}

// RIWAYAT BARANG MASUK
$stmt = $pdo->prepare("
    SELECT
        bm.nomor_transaksi,
        bm.tanggal,
        bm.jumlah,
        b.kode_barang,
        b.nama_barang,
        b.satuan,
        s.nama_supplier
    FROM barang_masuk bm
    INNER JOIN barang b ON bm.barang_id = b.id
    LEFT JOIN supplier s ON bm.supplier_id = s.id
    WHERE bm.tanggal BETWEEN ? AND ?
    ORDER BY bm.tanggal DESC, bm.id DESC
");
$stmt->execute([$tanggalAwal, $tanggalAkhir]);
$riwayatMasuk = $stmt->fetchAll(PDO::FETCH_ASSOC);

// RIWAYAT BARANG KELUAR
$stmt = $pdo->prepare("
    SELECT
        bk.nomor_transaksi,
        bk.tanggal,
        bk.jumlah,
        bk.keterangan,
        b.kode_barang,
        b.nama_barang,
        b.satuan
    FROM barang_keluar bk
    INNER JOIN barang b ON bk.barang_id = b.id
    WHERE bk.tanggal BETWEEN ? AND ?
    ORDER BY bk.tanggal DESC, bk.id DESC
");
$stmt->execute([$tanggalAwal, $tanggalAkhir]);
$riwayatKeluar = $stmt->fetchAll(PDO::FETCH_ASSOC);

// EKSPOR CSV
if (isset($_GET['export'])) {

    $filename = "laporan-inventra-" . date('Ymd-His') . ".csv";

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // BOM AGAR EXCEL MEMBACA UTF-8
    fwrite($output, "\xEF\xBB\xBF");

    fputcsv($output, ['LAPORAN PERSEDIAAN INVENTRA']);
    fputcsv($output, ['Tanggal Cetak', date('d-m-Y H:i:s')]);
    fputcsv($output, []);
    fputcsv($output, [
        'Kode Barang',
        'Nama Barang',
        'Kategori',
        'Total Masuk',
        'Total Keluar',
        'Stok Tersedia',
        'Stok Minimum',
        'Satuan',
        'Status'
    ]);

    foreach ($barang as $b) {
        $status = $b['stok'] <= (int)$b['stok_minimum']
            ? 'Stok Menipis'
            : 'Aman';

        fputcsv($output, [
            $b['kode_barang'],
            $b['nama_barang'],
            $b['nama_kategori'],
            $b['total_masuk'],
            $b['total_keluar'],
            $b['stok'],
            $b['stok_minimum'],
            $b['satuan'],
            $status
        ]);
    }

    fclose($output);
    exit;
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Laporan | INVENTRA</title>

<link rel="stylesheet" href="../assets/css/style.css">

<style>

* {
    box-sizing:border-box;
}

.main-content {
    animation:fadeIn .6s ease both;
}

@keyframes fadeIn {
    from {
        opacity:0;
        transform:translateY(15px);
    }
    to {
        opacity:1;
        transform:translateY(0);
    }
}

/* HEADER */

.page-heading {
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:15px;
    margin-bottom:25px;
}

.page-heading h1 {
    color:#193b30;
    font-size:26px;
    margin-bottom:7px;
}

.page-heading p {
    color:#84918c;
    font-size:12px;
}

.report-label {
    background:#e6f7ed;
    color:#087b49;
    padding:11px 15px;
    border-radius:8px;
    font-size:11px;
    font-weight:700;
}

/* STATISTIK */

.report-stats {
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:17px;
    margin-bottom:25px;
}

.report-card {
    background:white;
    border:1px solid #e7ede9;
    border-radius:13px;
    padding:21px;
    transition:.3s;
    animation:fadeIn .6s ease both;
}

.report-card:hover {
    transform:translateY(-5px);
    box-shadow:0 10px 25px rgba(21,150,91,.09);
}

.report-card span {
    color:#87948e;
    font-size:12px;
}

.report-card h2 {
    color:#15965b;
    font-size:27px;
    margin-top:12px;
}

.report-icon {
    width:39px;
    height:39px;
    border-radius:10px;
    background:#e8f7ee;
    color:#15965b;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:19px;
    margin-bottom:14px;
}

/* PANEL */

.report-panel {
    background:white;
    border:1px solid #e7ede9;
    border-radius:13px;
    padding:23px;
    margin-bottom:23px;
}

.panel-heading {
    margin-bottom:20px;
}

.panel-heading h3 {
    color:#193b30;
    font-size:16px;
}

.panel-heading p {
    color:#87948e;
    font-size:12px;
    margin-top:6px;
}

/* FILTER */

.filter-form {
    display:flex;
    flex-wrap:wrap;
    align-items:end;
    gap:15px;
}

.filter-group {
    display:flex;
    flex-direction:column;
    gap:8px;
}

.filter-group label {
    font-size:12px;
    font-weight:600;
    color:#43584c;
}

.filter-input {
    padding:11px 13px;
    border:1px solid #e1e9e4;
    border-radius:8px;
    font-size:12px;
    outline:none;
}

.filter-input:focus {
    border-color:#15965b;
}

.btn {
    border:0;
    padding:12px 17px;
    border-radius:8px;
    cursor:pointer;
    font-size:12px;
    font-weight:600;
    text-decoration:none;
    display:inline-block;
    transition:.3s;
}

.btn-green {
    background:#15965b;
    color:white;
}

.btn-green:hover {
    background:#087443;
    transform:translateY(-2px);
}

.btn-light {
    background:#edf8f1;
    color:#126b45;
}

.btn-light:hover {
    background:#d9f1e2;
}

/* REKAP */

.recap-grid {
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:18px;
}

.recap-box {
    border:1px solid #e8eee9;
    border-radius:11px;
    padding:20px;
    transition:.3s;
}

.recap-box:hover {
    background:#f8fcf9;
    transform:translateY(-3px);
}

.recap-box span {
    color:#87948e;
    font-size:12px;
}

.recap-box h2 {
    color:#15965b;
    font-size:25px;
    margin:12px 0 5px;
}

.recap-box p {
    color:#87948e;
    font-size:11px;
}

/* TABEL */

.table-wrap {
    overflow-x:auto;
}

.report-table {
    width:100%;
    min-width:800px;
    border-collapse:collapse;
    font-size:12px;
}

.report-table th {
    background:#edf8f1;
    color:#176b45;
    padding:14px;
    text-align:left;
    white-space:nowrap;
}

.report-table td {
    padding:14px;
    border-bottom:1px solid #edf0ee;
    color:#53647b;
}

.report-table tbody tr {
    transition:.2s;
}

.report-table tbody tr:hover {
    background:#f5faf6;
}

.status-safe {
    background:#e5f7eb;
    color:#087b49;
    padding:6px 10px;
    border-radius:20px;
    font-size:10px;
    font-weight:700;
}

.status-low {
    background:#fff1df;
    color:#b56b16;
    padding:6px 10px;
    border-radius:20px;
    font-size:10px;
    font-weight:700;
}

.empty-state {
    text-align:center;
    padding:35px;
    color:#87948e;
}

/* FOOTER */

.report-footer {
    color:#87948e;
    font-size:12px;
    padding:10px 0 20px;
}

@media(max-width:1000px) {
    .report-stats {
        grid-template-columns:repeat(2,1fr);
    }
}

@media(max-width:650px) {
    .report-stats,
    .recap-grid {
        grid-template-columns:1fr;
    }

    .page-heading {
        align-items:flex-start;
        flex-direction:column;
    }

    .filter-form {
        align-items:stretch;
        flex-direction:column;
    }

    .filter-input {
        width:100%;
    }
}

/* MODE CETAK */

@media print {

    .sidebar,
    .filter-form,
    .no-print,
    .report-footer {
        display:none !important;
    }

    .main-content {
        margin:0 !important;
        padding:0 !important;
        width:100% !important;
    }

    body {
        background:white !important;
    }

    .report-panel,
    .report-card {
        box-shadow:none !important;
        break-inside:avoid;
    }

    .report-table {
        min-width:0;
        font-size:9px;
    }

    .report-table th,
    .report-table td {
        padding:7px;
    }

}

</style>

</head>

<body>

<div class="dashboard">

    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content">

    <div class="page-heading">

        <div>
            <h1>Laporan & Analisis</h1>
            <p>Pusat informasi persediaan dan aktivitas operasional INVENTRA.</p>
        </div>

        <div class="report-label">
            ● REPORT MANAGEMENT
        </div>

    </div>

    <!-- STATISTIK -->

    <section class="report-stats">

        <div class="report-card">
            <div class="report-icon">▣</div>
            <span>Total Jenis Barang</span>
            <h2><?= number_format($totalBarang) ?></h2>
        </div>

        <div class="report-card">
            <div class="report-icon">▤</div>
            <span>Total Stok Tersedia</span>
            <h2><?= number_format($totalStok) ?></h2>
        </div>

        <div class="report-card">
            <div class="report-icon">↓</div>
            <span>Barang Masuk</span>
            <h2><?= number_format($rekapMasuk['total']) ?></h2>
        </div>

        <div class="report-card">
            <div class="report-icon">↑</div>
            <span>Barang Keluar</span>
            <h2><?= number_format($rekapKeluar['total']) ?></h2>
        </div>

    </section>

    <!-- FILTER TANGGAL -->

    <section class="report-panel">

        <div class="panel-heading">
            <h3>Filter Periode Laporan</h3>
            <p>Pilih periode untuk melihat aktivitas transaksi gudang.</p>
        </div>

        <form method="GET" class="filter-form">

            <div class="filter-group">
                <label>Tanggal Awal</label>
                <input
                    type="date"
                    name="awal"
                    class="filter-input"
                    value="<?= e($tanggalAwal) ?>"
                    required
                >
            </div>

            <div class="filter-group">
                <label>Tanggal Akhir</label>
                <input
                    type="date"
                    name="akhir"
                    class="filter-input"
                    value="<?= e($tanggalAkhir) ?>"
                    required
                >
            </div>

            <button type="submit" class="btn btn-green">
                Terapkan Filter
            </button>

            <a href="laporan.php" class="btn btn-light">
                Reset
            </a>

        </form>

    </section>

    <!-- REKAP TRANSAKSI -->

    <section class="report-panel">

        <div class="panel-heading">
            <h3>Ringkasan Transaksi</h3>
            <p>
                Periode
                <?= date('d-m-Y', strtotime($tanggalAwal)) ?>
                sampai
                <?= date('d-m-Y', strtotime($tanggalAkhir)) ?>
            </p>
        </div>

        <div class="recap-grid">

            <div class="recap-box">
                <span>Transaksi Barang Masuk</span>
                <h2><?= number_format($rekapMasuk['transaksi']) ?></h2>
                <p><?= number_format($rekapMasuk['total']) ?> unit diterima</p>
            </div>

            <div class="recap-box">
                <span>Transaksi Barang Keluar</span>
                <h2><?= number_format($rekapKeluar['transaksi']) ?></h2>
                <p><?= number_format($rekapKeluar['total']) ?> unit dikeluarkan</p>
            </div>

            <div class="recap-box">
                <span>Total Data Produksi</span>
                <h2><?= number_format($totalProduksi) ?></h2>
                <p>Data produksi tersimpan</p>
            </div>

        </div>

    </section>

    <!-- LAPORAN STOK -->

    <section class="report-panel">

        <div class="panel-heading">
            <h3>Laporan Persediaan Barang</h3>
            <p>Informasi stok seluruh barang berdasarkan transaksi masuk dan keluar.</p>
        </div>

        <div class="table-wrap">

            <table class="report-table">

                <thead>
                    <tr>
                        <th>NO</th>
                        <th>KODE BARANG</th>
                        <th>NAMA BARANG</th>
                        <th>KATEGORI</th>
                        <th>TOTAL MASUK</th>
                        <th>TOTAL KELUAR</th>
                        <th>STOK</th>
                        <th>STATUS</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($totalBarang > 0): ?>

                    <?php foreach ($barang as $i => $b): ?>

                    <tr>

                        <td><?= $i + 1 ?></td>

                        <td><strong><?= e($b['kode_barang']) ?></strong></td>

                        <td><?= e($b['nama_barang']) ?></td>

                        <td><?= e($b['nama_kategori']) ?></td>

                        <td><?= number_format($b['total_masuk']) ?></td>

                        <td><?= number_format($b['total_keluar']) ?></td>

                        <td>
                            <strong><?= number_format($b['stok']) ?></strong>
                            <?= e($b['satuan']) ?>
                        </td>

                        <td>
                            <?php if ($b['stok'] <= (int)$b['stok_minimum']): ?>

                                <span class="status-low">Stok Menipis</span>

                            <?php else: ?>

                                <span class="status-safe">Aman</span>

                            <?php endif; ?>
                        </td>

                    </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="8" class="empty-state">
                            Belum ada data persediaan.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

    <!-- RIWAYAT BARANG MASUK -->

    <section class="report-panel">

        <div class="panel-heading">
            <h3>Detail Barang Masuk</h3>
            <p>Riwayat penerimaan barang berdasarkan periode terpilih.</p>
        </div>

        <div class="table-wrap">

            <table class="report-table">

                <thead>
                    <tr>
                        <th>NO</th>
                        <th>NOMOR TRANSAKSI</th>
                        <th>TANGGAL</th>
                        <th>NAMA BARANG</th>
                        <th>SUPPLIER</th>
                        <th>JUMLAH</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (count($riwayatMasuk) > 0): ?>

                    <?php foreach ($riwayatMasuk as $i => $r): ?>

                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= e($r['nomor_transaksi']) ?></td>
                        <td><?= date('d-m-Y', strtotime($r['tanggal'])) ?></td>
                        <td><?= e($r['nama_barang']) ?></td>
                        <td><?= e($r['nama_supplier'] ?: '-') ?></td>
                        <td><?= number_format($r['jumlah']) ?> <?= e($r['satuan']) ?></td>
                    </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6" class="empty-state">
                            Tidak ada transaksi barang masuk pada periode ini.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

    <!-- RIWAYAT BARANG KELUAR -->

    <section class="report-panel">

        <div class="panel-heading">
            <h3>Detail Barang Keluar</h3>
            <p>Riwayat pengeluaran barang berdasarkan periode terpilih.</p>
        </div>

        <div class="table-wrap">

            <table class="report-table">

                <thead>
                    <tr>
                        <th>NO</th>
                        <th>NOMOR TRANSAKSI</th>
                        <th>TANGGAL</th>
                        <th>NAMA BARANG</th>
                        <th>JUMLAH</th>
                        <th>KETERANGAN</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (count($riwayatKeluar) > 0): ?>

                    <?php foreach ($riwayatKeluar as $i => $r): ?>

                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= e($r['nomor_transaksi']) ?></td>
                        <td><?= date('d-m-Y', strtotime($r['tanggal'])) ?></td>
                        <td><?= e($r['nama_barang']) ?></td>
                        <td><?= number_format($r['jumlah']) ?> <?= e($r['satuan']) ?></td>
                        <td><?= e($r['keterangan'] ?: '-') ?></td>
                    </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6" class="empty-state">
                            Tidak ada transaksi barang keluar pada periode ini.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

    <!-- TOMBOL LAPORAN -->

    <section class="report-panel no-print">

        <div class="panel-heading">
            <h3>Ekspor & Cetak Laporan</h3>
            <p>Simpan laporan untuk kebutuhan dokumentasi perusahaan.</p>
        </div>

        <div style="display:flex;gap:12px;flex-wrap:wrap;">

            <a
                href="laporan.php?export=1"
                class="btn btn-green"
            >
                ↓ Download Excel / CSV
            </a>

            <button
                type="button"
                onclick="window.print()"
                class="btn btn-light"
            >
                Cetak / Simpan PDF
            </button>

        </div>

    </section>

    <footer class="report-footer">
        INVENTRA © 2026 | Inventory & Production Control System
    </footer>

</main>


</div><!-- /.dashboard -->

</body>
</html>
