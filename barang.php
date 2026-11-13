
<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$baseUrl = '/inventra-updated/inventra';

// Token keamanan untuk aksi penghapusan data.
if (empty($_SESSION['csrf_barang'])) {
    $_SESSION['csrf_barang'] = bin2hex(random_bytes(32));
}
$pesanHapus = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus_barang') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_barang'], $token)) {
        http_response_code(403);
        exit('Permintaan tidak valid. Silakan muat ulang halaman.');
    }
    $idHapus = filter_input(INPUT_POST, 'barang_id', FILTER_VALIDATE_INT);
    if (!$idHapus) {
        $_SESSION['flash_barang'] = 'Data barang tidak valid.';
    } else {
        try {
            $stmtHapus = $pdo->prepare('DELETE FROM barang WHERE id = ?');
            $stmtHapus->execute([$idHapus]);
            $_SESSION['flash_barang'] = $stmtHapus->rowCount() > 0
                ? 'Data barang berhasil dihapus.'
                : 'Data barang tidak ditemukan.';
        } catch (PDOException $e) {
            // Foreign key mencegah penghapusan barang yang sudah dipakai transaksi.
            $_SESSION['flash_barang'] = 'Barang tidak dapat dihapus karena sudah digunakan dalam transaksi atau riwayat.';
        }
    }
    header('Location: barang.php');
    exit;
}
$pesanHapus = $_SESSION['flash_barang'] ?? '';
unset($_SESSION['flash_barang']);

// Mengambil data barang
$barang = $pdo->query("
    SELECT b.*, k.nama_kategori
    FROM barang b
    LEFT JOIN kategori k ON b.kategori_id = k.id
    ORDER BY b.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Statistik
$totalBarang = count($barang);
$totalBahanBaku = 0;
$totalBarangJadi = 0;

foreach ($barang as $b) {
    if ($b['jenis'] === 'bahan_baku') {
        $totalBahanBaku++;
    } else {
        $totalBarangJadi++;
    }
}

// Format data
function e($value) {
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

<title>Data Barang | INVENTRA</title>

<link
    rel="stylesheet"
    href="<?= $baseUrl ?>/assets/css/style.css"
>

<style>

/* HALAMAN DATA BARANG */

.page-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    animation: fadeIn .6s ease both;
}

.page-heading h1 {
    font-size: 25px;
    color: #192d49;
    margin-bottom: 6px;
}

.page-heading p {
    font-size: 12px;
    color: #8996a9;
}

.btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #15965b;
    color: white;
    padding: 12px 18px;
    border-radius: 9px;
    text-decoration: none;
    font-size: 12px;
    font-weight: 700;
    transition: .3s;
}

.btn-primary:hover {
    background: #087443;
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(21,150,91,.2);
}

/* STATISTIK */

.barang-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0,1fr));
    gap: 18px;
    margin-bottom: 25px;
}

.barang-stat {
    background: white;
    border: 1px solid #e7ede9;
    border-radius: 13px;
    padding: 22px;
    transition: .3s;
    animation: fadeIn .7s ease both;
}

.barang-stat:nth-child(1) {
    animation-delay: .1s;
}

.barang-stat:nth-child(2) {
    animation-delay: .2s;
}

.barang-stat:nth-child(3) {
    animation-delay: .3s;
}

.barang-stat:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 25px rgba(21,150,91,.1);
    border-color: #15965b;
}

.stat-icon {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    background: #e7f7ee;
    color: #15965b;
    font-size: 20px;
    margin-bottom: 15px;
}

.barang-stat span {
    color: #8996a9;
    font-size: 12px;
}

.barang-stat h2 {
    color: #15965b;
    font-size: 29px;
    margin-top: 10px;
}

/* PANEL */

.panel {
    background: white;
    border: 1px solid #e7ede9;
    border-radius: 13px;
    padding: 23px;
    margin-bottom: 25px;
    animation: fadeIn .8s ease .3s both;
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.panel-header h3 {
    color: #1d304b;
    font-size: 16px;
}

.panel-header p {
    color: #8996a9;
    font-size: 11px;
    margin-top: 5px;
}

/* TOOLBAR */

.table-toolbar {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 18px;
}

.search-box {
    width: 330px;
    max-width: 100%;
    padding: 12px 15px;
    border: 1px solid #e2e8e5;
    border-radius: 9px;
    font-size: 12px;
    outline: none;
    transition: .3s;
}

.search-box:focus {
    border-color: #15965b;
    box-shadow: 0 0 0 3px rgba(21,150,91,.1);
    transform: translateY(-2px);
}

.filter-box {
    padding: 12px;
    border: 1px solid #e2e8e5;
    border-radius: 9px;
    font-size: 12px;
    outline: none;
}

/* TABEL */

.table-wrap {
    width: 100%;
    overflow-x: auto;
}

.barang-table {
    width: 100%;
    min-width: 850px;
    border-collapse: collapse;
    font-size: 12px;
}

.barang-table th {
    background: #edf8f1;
    color: #176b45;
    padding: 15px 13px;
    text-align: left;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

.barang-table td {
    padding: 15px 13px;
    border-bottom: 1px solid #edf0ee;
    color: #53647b;
}

.barang-table tbody tr {
    transition: .25s;
    animation: fadeIn .5s ease both;
}

.barang-table tbody tr:hover {
    background: #f0faf4;
}

.kode {
    color: #15965b !important;
    font-weight: 700;
}

.jenis-badge {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    background: #eaf7ef;
    color: #15965b;
    font-size: 10px;
    font-weight: 600;
}

.stok-badge {
    display: inline-block;
    padding: 6px 10px;
    background: #eaf7ef;
    color: #15965b;
    border-radius: 20px;
    font-weight: 600;
    font-size: 10px;
}

/* EMPTY STATE */

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #8996a9;
}

.empty-icon {
    font-size: 42px;
    color: #15965b;
    margin-bottom: 15px;
}

.empty-state strong {
    display: block;
    color: #34445b;
    font-size: 14px;
}

.empty-state p {
    font-size: 12px;
    margin-top: 8px;
}

/* FOOTER */

.main-content footer {
    margin-top: 25px;
    padding: 18px 0;
    border-top: 1px solid #e7ede9;
    color: #8996a9;
    font-size: 11px;
}

/* ANIMASI */

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(18px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* RESPONSIVE */

@media(max-width: 900px) {
    .barang-stats {
        grid-template-columns: 1fr 1fr;
    }
}

@media(max-width: 700px) {
    .page-heading {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }

    .barang-stats {
        grid-template-columns: 1fr;
    }

    .table-toolbar {
        flex-direction: column;
    }

    .search-box {
        width: 100%;
        box-sizing: border-box;
    }

    .panel {
        padding: 15px;
    }
}

</style>

</head>

<body>

<!-- LAYOUT UTAMA -->

<div class="dashboard">

    <!-- SIDEBAR TERINTEGRASI -->
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>


    <!-- MAIN CONTENT -->

    <main class="main-content">

        <?php if ($pesanHapus !== ''): ?><div class="flash-bar" role="status"><?= e($pesanHapus) ?></div><?php endif; ?>

        <!-- JUDUL HALAMAN -->

        <div class="page-heading">

            <div>
                <h1>Data Barang</h1>
                <p>Kelola dan pantau seluruh persediaan perusahaan.</p>
            </div>

            <a
                href="<?= $baseUrl ?>/pages/barang-tambah.php"
                class="btn-primary"
            >
                + Tambah Barang
            </a>

        </div>


        <!-- STATISTIK -->

        <section class="barang-stats">

            <div class="barang-stat">
                <div class="stat-icon">▣</div>
                <span>Total Jenis Barang</span>
                <h2><?= number_format($totalBarang) ?></h2>
            </div>

            <div class="barang-stat">
                <div class="stat-icon">▤</div>
                <span>Bahan Baku</span>
                <h2><?= number_format($totalBahanBaku) ?></h2>
            </div>

            <div class="barang-stat">
                <div class="stat-icon">⚙</div>
                <span>Barang Jadi</span>
                <h2><?= number_format($totalBarangJadi) ?></h2>
            </div>

        </section>


        <!-- DAFTAR BARANG -->

        <section class="panel">

            <div class="panel-header">

                <div>
                    <h3>Daftar Persediaan</h3>

                    <p>
                        Informasi seluruh barang yang terdaftar
                        dalam sistem INVENTRA.
                    </p>
                </div>

            </div>


            <!-- PENCARIAN DAN FILTER -->

            <div class="table-toolbar">

                <input
                    type="text"
                    id="searchBarang"
                    class="search-box"
                    placeholder="Cari kode atau nama barang..."
                >

                <select id="filterJenis" class="filter-box">
                    <option value="">Semua Jenis</option>
                    <option value="bahan baku">Bahan Baku</option>
                    <option value="barang jadi">Barang Jadi</option>
                </select>

            </div>


            <!-- TABEL BARANG -->

            <div class="table-wrap">

                <table class="barang-table">

                    <thead>
                        <tr>
                            <th>PART NUMBER</th>
                            <th>NAMA BARANG</th>
                            <th>KATEGORI</th>
                            <th>JENIS</th>
                            <th>SATUAN</th>
                            <th>STOK MINIMUM</th>
                            <th class="aksi-heading">AKSI</th>
                        </tr>
                    </thead>


                    <tbody id="tabelBarang">

                    <?php if ($totalBarang > 0): ?>

                        <?php foreach ($barang as $b): ?>

                            <tr>

                                <td class="kode">
                                    <?= e($b['kode_barang']) ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= e($b['nama_barang']) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= e($b['nama_kategori'] ?? '-') ?>
                                </td>

                                <td>

                                    <span class="jenis-badge">
                                        <?= $b['jenis'] === 'bahan_baku'
                                            ? 'Bahan Baku'
                                            : 'Barang Jadi' ?>
                                    </span>

                                </td>

                                <td>
                                    <?= e($b['satuan']) ?>
                                </td>

                                <td>

                                    <span class="stok-badge">
                                        <?= number_format(
                                            (float)$b['stok_minimum']
                                        ) ?>
                                    </span>

                                </td>

                                <td class="aksi-cell">
                                    <form method="POST" class="form-hapus" onsubmit="return confirm('Hapus barang ' + <?= json_encode($b['nama_barang'], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?> + '? Tindakan ini tidak dapat dibatalkan.');">
                                        <input type="hidden" name="aksi" value="hapus_barang">
                                        <input type="hidden" name="barang_id" value="<?= (int)$b['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_barang']) ?>">
                                        <button type="submit" class="btn-hapus" aria-label="Hapus <?= e($b['nama_barang']) ?>">Hapus</button>
                                    </form>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="7">

                                <div class="empty-state">

                                    <div class="empty-icon">▣</div>

                                    <strong>Belum ada data barang</strong>

                                    <p>
                                        Tambahkan barang pertama untuk mulai
                                        mengelola persediaan perusahaan.
                                    </p>

                                </div>

                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>


        <!-- FOOTER -->

        <footer>
            INVENTRA © 2026 | Inventory & Production Control System
        </footer>

    </main>

</div>


<!-- JAVASCRIPT PENCARIAN DAN FILTER -->

<script>

const searchInput = document.getElementById("searchBarang");
const filterJenis = document.getElementById("filterJenis");

function filterBarang() {

    const keyword = searchInput.value.toLowerCase();
    const jenis = filterJenis.value.toLowerCase();

    const rows = document.querySelectorAll("#tabelBarang tr");

    rows.forEach(row => {

        const text = row.innerText.toLowerCase();

        const cocokNama = text.includes(keyword);
        const cocokJenis = jenis === "" || text.includes(jenis);

        row.style.display =
            cocokNama && cocokJenis ? "" : "none";

    });

}

searchInput.addEventListener("input", filterBarang);
filterJenis.addEventListener("change", filterBarang);

</script>

</body>
</html>
