
<?php
require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . "/../config/database.php";

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$error = "";

// NOMOR TRANSAKSI OTOMATIS
function nomorTransaksi() {
    return "BM-" . date("Ymd-His");
}

// PROSES SIMPAN TRANSAKSI
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $barang_id = (int)($_POST['barang_id'] ?? 0);
    $supplier_id = !empty($_POST['supplier_id'])
        ? (int)$_POST['supplier_id']
        : null;

    $tanggal = $_POST['tanggal'] ?? '';
    $jumlah = (int)($_POST['jumlah'] ?? 0);
    $keterangan = trim($_POST['keterangan'] ?? '');

    try {

        if ($barang_id <= 0) {
            throw new Exception("Silakan pilih barang.");
        }

        if ($tanggal === '') {
            throw new Exception("Tanggal transaksi wajib diisi.");
        }

        $date = DateTime::createFromFormat('Y-m-d', $tanggal);

        if (!$date || $date->format('Y-m-d') !== $tanggal) {
            throw new Exception("Format tanggal tidak valid.");
        }

        if ($jumlah <= 0) {
            throw new Exception("Jumlah barang harus lebih dari nol.");
        }

        // VALIDASI BARANG
        $cekBarang = $pdo->prepare("
            SELECT id FROM barang WHERE id = ?
        ");
        $cekBarang->execute([$barang_id]);

        if (!$cekBarang->fetch()) {
            throw new Exception("Barang tidak ditemukan.");
        }

        // VALIDASI SUPPLIER
        if ($supplier_id !== null) {
            $cekSupplier = $pdo->prepare("
                SELECT id FROM supplier WHERE id = ?
            ");
            $cekSupplier->execute([$supplier_id]);

            if (!$cekSupplier->fetch()) {
                throw new Exception("Supplier tidak ditemukan.");
            }
        }

        // SIMPAN TRANSAKSI
        $nomor = nomorTransaksi();

        $stmt = $pdo->prepare("
            INSERT INTO barang_masuk
            (
                nomor_transaksi,
                tanggal,
                barang_id,
                supplier_id,
                jumlah,
                keterangan
            )
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $nomor,
            $tanggal,
            $barang_id,
            $supplier_id,
            $jumlah,
            $keterangan
        ]);

        header("Location: barang-masuk.php?success=1");
        exit;

    } catch (PDOException $ex) {

        $error = "Transaksi gagal disimpan. Periksa kembali data dan koneksi database.";

    } catch (Exception $ex) {

        $error = $ex->getMessage();
    }
}

// DATA BARANG UNTUK PILIHAN
$barangList = $pdo->query("
    SELECT id, kode_barang, nama_barang, satuan
    FROM barang
    ORDER BY nama_barang ASC
")->fetchAll(PDO::FETCH_ASSOC);

// DATA SUPPLIER
$supplierList = $pdo->query("
    SELECT id, nama_supplier
    FROM supplier
    ORDER BY nama_supplier ASC
")->fetchAll(PDO::FETCH_ASSOC);

// STATISTIK
$totalTransaksi = (int)$pdo->query("
    SELECT COUNT(*) FROM barang_masuk
")->fetchColumn();

$totalUnit = (int)$pdo->query("
    SELECT COALESCE(SUM(jumlah), 0)
    FROM barang_masuk
")->fetchColumn();

$transaksiBulanIni = (int)$pdo->query("
    SELECT COUNT(*)
    FROM barang_masuk
    WHERE MONTH(tanggal) = MONTH(CURDATE())
    AND YEAR(tanggal) = YEAR(CURDATE())
")->fetchColumn();

// PENCARIAN
$keyword = trim($_GET['search'] ?? '');

$stmt = $pdo->prepare("
    SELECT
        bm.*,
        b.kode_barang,
        b.nama_barang,
        b.satuan,
        s.nama_supplier
    FROM barang_masuk bm
    INNER JOIN barang b ON bm.barang_id = b.id
    LEFT JOIN supplier s ON bm.supplier_id = s.id
    WHERE bm.nomor_transaksi LIKE ?
       OR b.nama_barang LIKE ?
       OR b.kode_barang LIKE ?
       OR s.nama_supplier LIKE ?
    ORDER BY bm.tanggal DESC, bm.id DESC
");

$like = "%$keyword%";
$stmt->execute([$like, $like, $like, $like]);

$transaksi = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Barang Masuk | INVENTRA</title>

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
    min-width:850px;
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
            <h1>Barang Masuk</h1>
            <p>Kelola penerimaan barang dan pencatatan persediaan gudang.</p>
        </div>

    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            Transaksi barang masuk berhasil disimpan.
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
            <div class="stat-icon">↓</div>
            <span>Total Transaksi Masuk</span>
            <h2><?= number_format($totalTransaksi) ?></h2>
        </div>

        <div class="stat-card">
            <div class="stat-icon">▣</div>
            <span>Total Unit Diterima</span>
            <h2><?= number_format($totalUnit) ?></h2>
        </div>

        <div class="stat-card">
            <div class="stat-icon">▦</div>
            <span>Transaksi Bulan Ini</span>
            <h2><?= number_format($transaksiBulanIni) ?></h2>
        </div>

    </section>

    <!-- FORM TRANSAKSI -->

    <section class="panel">

        <div class="panel-header">
            <h3>Input Barang Masuk</h3>
            <p>Isi formulir berikut untuk mencatat penerimaan barang.</p>
        </div>

        <form method="POST" action="barang-masuk.php">

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
                        class="form-control"
                        required
                    >

                        <option value="">-- Pilih Barang --</option>

                        <?php foreach ($barangList as $b): ?>

                            <option value="<?= (int)$b['id'] ?>">
                                <?= e($b['kode_barang']) ?>
                                -
                                <?= e($b['nama_barang']) ?>
                                (<?= e($b['satuan']) ?>)
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="form-group">
                    <label>Supplier</label>

                    <select
                        name="supplier_id"
                        class="form-control"
                    >

                        <option value="">-- Pilih Supplier --</option>

                        <?php foreach ($supplierList as $s): ?>

                            <option value="<?= (int)$s['id'] ?>">
                                <?= e($s['nama_supplier']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="form-group">
                    <label>Jumlah Barang *</label>

                    <input
                        type="number"
                        name="jumlah"
                        class="form-control"
                        min="1"
                        placeholder="Masukkan jumlah barang"
                        required
                    >
                </div>

                <div class="form-group full">
                    <label>Keterangan</label>

                    <textarea
                        name="keterangan"
                        class="form-control"
                        placeholder="Tambahkan keterangan transaksi jika diperlukan..."
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

    <!-- RIWAYAT TRANSAKSI -->

    <section class="panel">

        <div class="panel-header">
            <h3>Riwayat Barang Masuk</h3>
            <p>Daftar seluruh penerimaan barang yang tercatat dalam sistem.</p>
        </div>

        <form method="GET" style="margin-bottom:20px;">

            <input
                type="text"
                name="search"
                class="search-box"
                placeholder="Cari nomor transaksi, barang, atau supplier..."
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
                        <th>NAMA BARANG</th>
                        <th>SUPPLIER</th>
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

                        <td>
                            <strong><?= e($t['nama_barang']) ?></strong>
                            <br>
                            <small><?= e($t['kode_barang']) ?></small>
                        </td>

                        <td>
                            <?= e($t['nama_supplier'] ?: 'Tidak ditentukan') ?>
                        </td>

                        <td>
                            <strong>
                                <?= number_format($t['jumlah']) ?>
                            </strong>
                            <?= e($t['satuan']) ?>
                        </td>

                        <td>
                            <?= e($t['keterangan'] ?: '-') ?>
                        </td>

                    </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="7">

                            <div class="empty-state">
                                <div style="font-size:40px;">↓</div>

                                <h3>Belum Ada Transaksi</h3>

                                <p>
                                    Transaksi barang masuk akan muncul
                                    setelah kamu menyimpan data penerimaan.
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


</div><!-- /.dashboard -->

</body>
</html>
