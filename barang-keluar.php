
<?php
require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . "/../config/database.php";

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$error = "";

// NOMOR TRANSAKSI
function nomorTransaksi() {
    return "BK-" . date("Ymd-His");
}

// PROSES SIMPAN BARANG KELUAR
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $barang_id = (int)($_POST['barang_id'] ?? 0);
    $tanggal = $_POST['tanggal'] ?? '';
    $jumlah = (int)($_POST['jumlah'] ?? 0);
    $keterangan = trim($_POST['keterangan'] ?? '');

    try {

        if ($barang_id <= 0) {
            throw new Exception("Silakan pilih barang.");
        }

        if ($tanggal === '') {
            throw new Exception("Tanggal wajib diisi.");
        }

        $date = DateTime::createFromFormat('Y-m-d', $tanggal);

        if (!$date || $date->format('Y-m-d') !== $tanggal) {
            throw new Exception("Tanggal tidak valid.");
        }

        if ($jumlah <= 0) {
            throw new Exception("Jumlah harus lebih dari nol.");
        }

        // HITUNG STOK TERSEDIA
        $cekStok = $pdo->prepare("
            SELECT
                b.id,
                COALESCE(
                    (SELECT SUM(bm.jumlah)
                     FROM barang_masuk bm
                     WHERE bm.barang_id = b.id), 0
                ) AS stok_masuk,
                COALESCE(
                    (SELECT SUM(bk.jumlah)
                     FROM barang_keluar bk
                     WHERE bk.barang_id = b.id), 0
                ) AS stok_keluar
            FROM barang b
            WHERE b.id = ?
        ");

        $cekStok->execute([$barang_id]);
        $dataStok = $cekStok->fetch(PDO::FETCH_ASSOC);

        if (!$dataStok) {
            throw new Exception("Barang tidak ditemukan.");
        }

        $stokTersedia = (int)$dataStok['stok_masuk']
                      - (int)$dataStok['stok_keluar'];

        if ($jumlah > $stokTersedia) {
            throw new Exception(
                "Stok tidak mencukupi. Stok tersedia: "
                . number_format($stokTersedia)
            );
        }

        // SIMPAN TRANSAKSI
        $nomor = nomorTransaksi();

        $stmt = $pdo->prepare("
            INSERT INTO barang_keluar
            (
                nomor_transaksi,
                tanggal,
                barang_id,
                jumlah,
                keterangan
            )
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $nomor,
            $tanggal,
            $barang_id,
            $jumlah,
            $keterangan
        ]);

        header("Location: barang-keluar.php?success=1");
        exit;

    } catch (PDOException $ex) {

        $error = "Transaksi gagal disimpan. Periksa struktur tabel database.";

    } catch (Exception $ex) {

        $error = $ex->getMessage();
    }
}

// DATA BARANG DAN STOK
$barangList = $pdo->query("
    SELECT
        b.id,
        b.kode_barang,
        b.nama_barang,
        b.satuan,

        COALESCE(
            (SELECT SUM(bm.jumlah)
             FROM barang_masuk bm
             WHERE bm.barang_id = b.id), 0
        ) AS stok_masuk,

        COALESCE(
            (SELECT SUM(bk.jumlah)
             FROM barang_keluar bk
             WHERE bk.barang_id = b.id), 0
        ) AS stok_keluar

    FROM barang b
    ORDER BY b.nama_barang ASC
")->fetchAll(PDO::FETCH_ASSOC);

// STATISTIK
$totalTransaksi = (int)$pdo->query("
    SELECT COUNT(*) FROM barang_keluar
")->fetchColumn();

$totalUnit = (int)$pdo->query("
    SELECT COALESCE(SUM(jumlah), 0)
    FROM barang_keluar
")->fetchColumn();

$transaksiBulanIni = (int)$pdo->query("
    SELECT COUNT(*)
    FROM barang_keluar
    WHERE MONTH(tanggal) = MONTH(CURDATE())
    AND YEAR(tanggal) = YEAR(CURDATE())
")->fetchColumn();

// PENCARIAN RIWAYAT
$keyword = trim($_GET['search'] ?? '');

$stmt = $pdo->prepare("
    SELECT
        bk.*,
        b.kode_barang,
        b.nama_barang,
        b.satuan

    FROM barang_keluar bk

    INNER JOIN barang b
        ON bk.barang_id = b.id

    WHERE bk.nomor_transaksi LIKE ?
       OR b.nama_barang LIKE ?
       OR b.kode_barang LIKE ?

    ORDER BY bk.tanggal DESC, bk.id DESC
");

$like = "%$keyword%";
$stmt->execute([$like, $like, $like]);

$transaksi = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Barang Keluar | INVENTRA</title>

<link rel="stylesheet" href="../assets/css/style.css">

<style>

/* HEADER */

.page-heading {
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:25px;
    animation:fadeIn .5s ease;
}

.page-heading h1 {
    color:#192d49;
    font-size:25px;
}

.page-heading p {
    color:#8996a9;
    font-size:12px;
    margin-top:6px;
}

/* STATISTIK */

.stats-grid {
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:18px;
    margin-bottom:25px;
}

.stat-card {
    background:white;
    border:1px solid #e7ede9;
    border-radius:13px;
    padding:22px;
    transition:.3s;
    animation:fadeIn .6s ease both;
}

.stat-card:hover {
    transform:translateY(-5px);
    box-shadow:0 10px 25px rgba(21,150,91,.1);
}

.stat-card span {
    color:#8996a9;
    font-size:12px;
}

.stat-card h2 {
    color:#15965b;
    font-size:28px;
    margin-top:12px;
}

.stat-icon {
    width:40px;
    height:40px;
    display:flex;
    justify-content:center;
    align-items:center;
    background:#e7f7ee;
    color:#15965b;
    border-radius:10px;
    font-size:20px;
    margin-bottom:14px;
}

/* PANEL */

.panel {
    background:white;
    border:1px solid #e7ede9;
    border-radius:13px;
    padding:23px;
    margin-bottom:25px;
    animation:fadeIn .7s ease both;
}

.panel-header {
    margin-bottom:20px;
}

.panel-header h3 {
    color:#1d304b;
    font-size:16px;
}

.panel-header p {
    color:#8996a9;
    font-size:11px;
    margin-top:5px;
}

/* FORM */

.form-grid {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:18px;
}

.form-group {
    display:flex;
    flex-direction:column;
    gap:8px;
}

.form-group.full {
    grid-column:1/-1;
}

.form-group label {
    color:#34445b;
    font-size:12px;
    font-weight:600;
}

.form-control {
    width:100%;
    box-sizing:border-box;
    padding:12px 14px;
    border:1px solid #e2e8e5;
    border-radius:8px;
    outline:none;
    font-size:12px;
    font-family:inherit;
    transition:.3s;
    background:white;
}

.form-control:focus {
    border-color:#15965b;
    box-shadow:0 0 0 3px rgba(21,150,91,.08);
}

textarea.form-control {
    min-height:85px;
    resize:vertical;
}

.btn-primary {
    display:inline-block;
    background:#15965b;
    color:white;
    border:0;
    padding:12px 19px;
    border-radius:8px;
    font-size:12px;
    font-weight:700;
    cursor:pointer;
    text-decoration:none;
    transition:.3s;
}

.btn-primary:hover {
    background:#087443;
    transform:translateY(-3px);
    box-shadow:0 7px 15px rgba(21,150,91,.15);
}

/* INFORMASI STOK */

.stock-info {
    background:#edf8f1;
    color:#176b45;
    padding:13px 15px;
    border-radius:8px;
    font-size:12px;
    margin-top:10px;
    display:none;
}

.stock-info strong {
    font-size:17px;
}

/* ALERT */

.alert {
    padding:14px 17px;
    border-radius:9px;
    margin-bottom:20px;
    font-size:12px;
}

.alert-success {
    background:#e9f8ef;
    color:#167747;
}

.alert-error {
    background:#fff0f0;
    color:#bd3333;
}

/* TABEL */

.search-box {
    width:320px;
    max-width:100%;
    box-sizing:border-box;
    padding:12px 14px;
    border:1px solid #e2e8e5;
    border-radius:8px;
    outline:none;
    font-size:12px;
}

.search-box:focus {
    border-color:#15965b;
}

.table-wrap {
    overflow-x:auto;
}

.data-table {
    width:100%;
    min-width:800px;
    border-collapse:collapse;
    font-size:12px;
}

.data-table th {
    background:#edf8f1;
    color:#176b45;
    text-align:left;
    padding:15px;
    font-size:11px;
}

.data-table td {
    padding:15px;
    border-bottom:1px solid #edf0ee;
    color:#53647b;
}

.data-table tbody tr {
    transition:.2s;
}

.data-table tbody tr:hover {
    background:#f3faf5;
}

.transaction-badge {
    background:#eaf7ef;
    color:#15965b;
    padding:6px 10px;
    border-radius:20px;
    font-size:10px;
    font-weight:700;
}

.empty-state {
    text-align:center;
    padding:45px 20px;
    color:#8996a9;
}

.empty-state h3 {
    color:#34445b;
    margin:10px;
    font-size:14px;
}

/* ANIMASI */

@keyframes fadeIn {
    from {
        opacity:0;
        transform:translateY(18px);
    }

    to {
        opacity:1;
        transform:translateY(0);
    }
}

/* RESPONSIVE */

@media(max-width:750px) {

    .stats-grid {
        grid-template-columns:1fr;
    }

    .form-grid {
        grid-template-columns:1fr;
    }

    .form-group.full {
        grid-column:auto;
    }

    .page-heading {
        align-items:flex-start;
        flex-direction:column;
        gap:10px;
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
            <h1>Barang Keluar</h1>
            <p>Kelola pengeluaran barang dan pantau pergerakan stok gudang.</p>
        </div>

    </div>

    <?php if (isset($_GET['success'])): ?>

        <div class="alert alert-success">
            Transaksi barang keluar berhasil disimpan.
        </div>

    <?php endif; ?>

    <?php if ($error): ?>

        <div class="alert alert-error">
            <?= e($error) ?>
        </div>

    <?php endif; ?>

    <!-- STATISTIK -->

    <section class="stats-grid">

        <div class="stat-card">
            <div class="stat-icon">↑</div>
            <span>Total Transaksi Keluar</span>
            <h2><?= number_format($totalTransaksi) ?></h2>
        </div>

        <div class="stat-card">
            <div class="stat-icon">▣</div>
            <span>Total Unit Dikeluarkan</span>
            <h2><?= number_format($totalUnit) ?></h2>
        </div>

        <div class="stat-card">
            <div class="stat-icon">▦</div>
            <span>Transaksi Bulan Ini</span>
            <h2><?= number_format($transaksiBulanIni) ?></h2>
        </div>

    </section>

    <!-- FORM -->

    <section class="panel">

        <div class="panel-header">
            <h3>Input Barang Keluar</h3>
            <p>Catat pengeluaran barang dari persediaan gudang.</p>
        </div>

        <form method="POST" action="barang-keluar.php">

            <div class="form-grid">

                <div class="form-group">

                    <label>Nomor Transaksi</label>

                    <input
                        type="text"
                        class="form-control"
                        value="<?= e(nomorTransaksi()) ?>"
                        readonly
                    >

                </div>

                <div class="form-group">

                    <label>Tanggal Transaksi *</label>

                    <input
                        type="date"
                        name="tanggal"
                        class="form-control"
                        value="<?= date('Y-m-d') ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>Pilih Barang *</label>

                    <select
                        name="barang_id"
                        id="barang_id"
                        class="form-control"
                        required
                    >

                        <option value="">-- Pilih Barang --</option>

                        <?php foreach ($barangList as $b): ?>

                            <?php
                            $stok = (int)$b['stok_masuk']
                                  - (int)$b['stok_keluar'];
                            ?>

                            <option
                                value="<?= (int)$b['id'] ?>"
                                data-stok="<?= $stok ?>"
                                data-satuan="<?= e($b['satuan']) ?>"
                            >

                                <?= e($b['kode_barang']) ?>
                                -
                                <?= e($b['nama_barang']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                    <div class="stock-info" id="stockInfo">
                        Stok tersedia:
                        <strong id="stockValue">0</strong>
                        <span id="stockUnit"></span>
                    </div>

                </div>

                <div class="form-group">

                    <label>Jumlah Barang *</label>

                    <input
                        type="number"
                        name="jumlah"
                        id="jumlah"
                        class="form-control"
                        min="1"
                        placeholder="Masukkan jumlah barang"
                        required
                    >

                </div>

                <div class="form-group full">

                    <label>Keterangan / Tujuan Pengeluaran</label>

                    <textarea
                        name="keterangan"
                        class="form-control"
                        placeholder="Contoh: Pengiriman barang ke bagian produksi atau pelanggan..."
                    ></textarea>

                </div>

            </div>

            <div style="margin-top:22px;">

                <button type="submit" class="btn-primary">
                    + Simpan Transaksi
                </button>

            </div>

        </form>

    </section>

    <!-- RIWAYAT -->

    <section class="panel">

        <div class="panel-header">
            <h3>Riwayat Barang Keluar</h3>
            <p>Daftar seluruh transaksi pengeluaran barang.</p>
        </div>

        <form method="GET" style="margin-bottom:20px;">

            <input
                type="text"
                name="search"
                class="search-box"
                placeholder="Cari nomor transaksi atau nama barang..."
                value="<?= e($keyword) ?>"
            >

            <button type="submit" class="btn-primary">
                Cari
            </button>

        </form>

        <div class="table-wrap">

            <table class="data-table">

                <thead>
                    <tr>
                        <th>NO</th>
                        <th>NOMOR TRANSAKSI</th>
                        <th>TANGGAL</th>
                        <th>KODE BARANG</th>
                        <th>NAMA BARANG</th>
                        <th>JUMLAH</th>
                        <th>KETERANGAN</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (count($transaksi) > 0): ?>

                    <?php foreach ($transaksi as $i => $t): ?>

                    <tr>

                        <td><?= $i + 1 ?></td>

                        <td>
                            <span class="transaction-badge">
                                <?= e($t['nomor_transaksi']) ?>
                            </span>
                        </td>

                        <td>
                            <?= date('d-m-Y', strtotime($t['tanggal'])) ?>
                        </td>

                        <td><?= e($t['kode_barang']) ?></td>

                        <td>
                            <strong><?= e($t['nama_barang']) ?></strong>
                        </td>

                        <td>
                            <strong><?= number_format($t['jumlah']) ?></strong>
                            <?= e($t['satuan']) ?>
                        </td>

                        <td><?= e($t['keterangan'] ?: '-') ?></td>

                    </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="7">

                            <div class="empty-state">

                                <div style="font-size:40px;">↑</div>

                                <h3>Belum Ada Transaksi</h3>

                                <p>
                                    Riwayat barang keluar akan muncul
                                    setelah transaksi berhasil disimpan.
                                </p>

                            </div>

                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

    <footer>
        INVENTRA © 2026 | Inventory & Production Control System
    </footer>

</main>

<!-- JAVASCRIPT -->

<script>

const barangSelect = document.getElementById("barang_id");
const stockInfo = document.getElementById("stockInfo");
const stockValue = document.getElementById("stockValue");
const stockUnit = document.getElementById("stockUnit");
const jumlahInput = document.getElementById("jumlah");

barangSelect.addEventListener("change", function() {

    const option = this.options[this.selectedIndex];

    if (!option.value) {
        stockInfo.style.display = "none";
        jumlahInput.removeAttribute("max");
        return;
    }

    const stok = Number(option.dataset.stok);
    const satuan = option.dataset.satuan;

    stockValue.textContent = stok.toLocaleString("id-ID");
    stockUnit.textContent = satuan;

    stockInfo.style.display = "block";

    jumlahInput.max = stok;

});

jumlahInput.addEventListener("input", function() {

    const max = Number(this.max);

    if (max > 0 && Number(this.value) > max) {
        this.setCustomValidity(
            "Jumlah melebihi stok yang tersedia."
        );
    } else {
        this.setCustomValidity("");
    }

});

</script>


</div><!-- /.dashboard -->

</body>
</html>
