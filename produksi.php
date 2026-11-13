
<?php
require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . "/../config/database.php";

/* AMBIL STRUKTUR TABEL PRODUKSI */
$columns = $pdo->query("SHOW COLUMNS FROM produksi")
               ->fetchAll(PDO::FETCH_ASSOC);

$columnNames = array_column($columns, 'Field');

/* AMBIL DATA PRODUKSI */
$primaryKey = 'id';

foreach ($columns as $column) {
    if ($column['Key'] === 'PRI') {
        $primaryKey = $column['Field'];
        break;
    }
}

if (!in_array($primaryKey, $columnNames)) {
    $primaryKey = $columnNames[0] ?? null;
}

$sql = "SELECT * FROM produksi";

if ($primaryKey) {
    $sql .= " ORDER BY `$primaryKey` DESC";
}

$produksi = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$totalProduksi = count($produksi);

/* STATISTIK STATUS */
$totalSelesai = 0;
$totalProses = 0;
$totalRencana = 0;
$totalRealisasi = 0;

$statusColumn = null;
$rencanaColumn = null;
$realisasiColumn = null;

foreach ($columnNames as $columnName) {
    $name = strtolower($columnName);

    if ($name === 'status') {
        $statusColumn = $columnName;
    }

    if (str_contains($name, 'rencana')) {
        $rencanaColumn = $columnName;
    }

    if (str_contains($name, 'realisasi')) {
        $realisasiColumn = $columnName;
    }
}

foreach ($produksi as $item) {
    if ($statusColumn) {
        $status = strtolower($item[$statusColumn] ?? '');

        if (str_contains($status, 'selesai')) {
            $totalSelesai++;
        } elseif (
            str_contains($status, 'proses') ||
            str_contains($status, 'berjalan')
        ) {
            $totalProses++;
        }
    }

    if ($rencanaColumn && is_numeric($item[$rencanaColumn])) {
        $totalRencana += $item[$rencanaColumn];
    }

    if ($realisasiColumn && is_numeric($item[$realisasiColumn])) {
        $totalRealisasi += $item[$realisasiColumn];
    }
}

function e($value) {
    return htmlspecialchars((string)($value ?? '-'), ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>PPIC & Produksi | INVENTRA</title>

<link rel="stylesheet" href="../assets/css/style.css">

<style>
* {
    box-sizing: border-box;
}

.main-content {
    animation: pageEnter .6s ease both;
}

@keyframes pageEnter {
    from {
        opacity: 0;
        transform: translateY(15px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.page-heading {
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:25px;
    gap:15px;
}

.page-heading h1 {
    font-size:26px;
    color:#193b30;
    margin-bottom:7px;
}

.page-heading p {
    color:#84918c;
    font-size:13px;
}

.production-label {
    background:#e6f7ed;
    color:#087b49;
    padding:10px 15px;
    border-radius:8px;
    font-size:12px;
    font-weight:700;
}

.production-stats {
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:18px;
    margin-bottom:25px;
}

.production-card {
    background:white;
    padding:23px;
    border:1px solid #e7ede9;
    border-radius:13px;
    transition:.3s ease;
    animation:cardEnter .6s ease both;
}

.production-card:nth-child(2) {
    animation-delay:.08s;
}
.production-card:nth-child(3) {
    animation-delay:.16s;
}
.production-card:nth-child(4) {
    animation-delay:.24s;
}

@keyframes cardEnter {
    from {
        opacity:0;
        transform:translateY(18px);
    }
    to {
        opacity:1;
        transform:translateY(0);
    }
}

.production-card:hover {
    transform:translateY(-5px);
    box-shadow:0 12px 25px rgba(20,80,55,.08);
}

.production-card span {
    font-size:12px;
    color:#83918a;
}

.production-card h2 {
    font-size:28px;
    margin-top:12px;
    color:#126b45;
}

.production-icon {
    width:38px;
    height:38px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:10px;
    background:#e6f7ed;
    color:#087b49;
    font-size:18px;
    margin-bottom:16px;
}

.production-panel {
    background:white;
    border:1px solid #e7ede9;
    border-radius:13px;
    padding:23px;
    margin-bottom:22px;
}

.panel-heading {
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:20px;
}

.panel-heading h3 {
    color:#193b30;
    font-size:16px;
    margin-bottom:6px;
}

.panel-heading p {
    color:#87948e;
    font-size:12px;
}

.table-toolbar {
    margin-bottom:18px;
}

.search-production {
    width:320px;
    max-width:100%;
    padding:12px 15px;
    border:1px solid #e1e9e4;
    border-radius:8px;
    outline:none;
    font-size:13px;
}

.search-production:focus {
    border-color:#168451;
    box-shadow:0 0 0 3px rgba(22,132,81,.09);
}

.table-wrap {
    width:100%;
    overflow-x:auto;
}

.production-table {
    width:100%;
    min-width:650px;
    border-collapse:collapse;
    font-size:12px;
}

.production-table th {
    text-align:left;
    background:#effaf3;
    color:#126b45;
    padding:15px;
    white-space:nowrap;
}

.production-table td {
    padding:15px;
    border-bottom:1px solid #edf1ee;
    color:#46564e;
}

.production-table tbody tr {
    transition:.2s;
}

.production-table tbody tr:hover {
    background:#f7fcf8;
}

.empty-state {
    text-align:center;
    padding:55px 15px !important;
    color:#87948e !important;
}

.empty-state strong {
    display:block;
    margin:12px 0 7px;
    color:#43584c;
}

.empty-state span {
    font-size:35px;
    color:#168451;
}

.status-badge {
    display:inline-block;
    padding:6px 10px;
    border-radius:20px;
    background:#e8f7ee;
    color:#087b49;
    font-size:11px;
    font-weight:600;
}

.production-footer {
    color:#87948e;
    font-size:12px;
    padding:10px 0 20px;
}

@media(max-width:950px) {
    .production-stats {
        grid-template-columns:repeat(2,1fr);
    }
}

@media(max-width:600px) {
    .production-stats {
        grid-template-columns:1fr;
    }

    .page-heading {
        align-items:flex-start;
        flex-direction:column;
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
            <h1>PPIC & Produksi</h1>
            <p>Perencanaan, pengawasan, dan realisasi produksi perusahaan.</p>
        </div>

        <div class="production-label">
            ● PRODUCTION MANAGEMENT
        </div>
    </div>

    <!-- STATISTIK -->
    <section class="production-stats">

        <div class="production-card">
            <div class="production-icon">⚙</div>
            <span>Total Produksi</span>
            <h2><?= number_format($totalProduksi) ?></h2>
        </div>

        <div class="production-card">
            <div class="production-icon">◷</div>
            <span>Produksi Berjalan</span>
            <h2><?= number_format($totalProses) ?></h2>
        </div>

        <div class="production-card">
            <div class="production-icon">✓</div>
            <span>Produksi Selesai</span>
            <h2><?= number_format($totalSelesai) ?></h2>
        </div>

        <div class="production-card">
            <div class="production-icon">▤</div>
            <span>Total Rencana</span>
            <h2><?= number_format($totalRencana) ?></h2>
        </div>

    </section>

    <!-- ANALISIS PRODUKSI -->
    <section class="production-panel">

        <div class="panel-heading">
            <div>
                <h3>Ringkasan Aktivitas Produksi</h3>
                <p>Informasi rencana dan realisasi produksi.</p>
            </div>
        </div>

        <div class="production-stats">

            <div>
                <span>Target Produksi</span>
                <h2><?= number_format($totalRencana) ?></h2>
            </div>

            <div>
                <span>Realisasi Produksi</span>
                <h2><?= number_format($totalRealisasi) ?></h2>
            </div>

        </div>

        <?php
        $persentase = $totalRencana > 0
            ? min(100, ($totalRealisasi / $totalRencana) * 100)
            : 0;
        ?>

        <div style="height:10px;background:#edf1ee;border-radius:20px;overflow:hidden;">
            <div style="
                width:<?= $persentase ?>%;
                height:100%;
                background:linear-gradient(90deg,#087b49,#20b875);
                border-radius:20px;
                transition:width 1s ease;
            "></div>
        </div>

        <p style="font-size:12px;color:#87948e;margin-top:10px;">
            Pencapaian target:
            <strong style="color:#087b49;">
                <?= number_format($persentase, 1) ?>%
            </strong>
        </p>

    </section>

    <!-- TABEL PRODUKSI -->
    <section class="production-panel">

        <div class="panel-heading">
            <div>
                <h3>Daftar Data Produksi</h3>
                <p>Seluruh aktivitas produksi yang tersimpan di database.</p>
            </div>
        </div>

        <div class="table-toolbar">
            <input
                type="text"
                id="searchProduction"
                class="search-production"
                placeholder="Cari data produksi..."
            >
        </div>

        <div class="table-wrap">

            <table class="production-table">

                <thead>
                    <tr>
                        <?php foreach ($columns as $column): ?>
                            <th><?= e(ucwords(str_replace('_', ' ', $column['Field']))) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>

                <tbody id="productionBody">

                    <?php if ($totalProduksi > 0): ?>

                        <?php foreach ($produksi as $item): ?>

                            <tr>
                                <?php foreach ($columns as $column): ?>

                                    <?php
                                    $field = $column['Field'];
                                    $value = $item[$field] ?? '-';
                                    ?>

                                    <td>
                                        <?php if ($field === $statusColumn): ?>
                                            <span class="status-badge">
                                                <?= e($value) ?>
                                            </span>
                                        <?php else: ?>
                                            <?= e($value) ?>
                                        <?php endif; ?>
                                    </td>

                                <?php endforeach; ?>
                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="<?= max(1, count($columns)) ?>"
                                class="empty-state">
                                <span>⚙</span>
                                <strong>Belum ada data produksi</strong>
                                Data produksi akan ditampilkan setelah tersimpan.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

    <footer class="production-footer">
        INVENTRA © 2026 | Inventory & Production Control System
    </footer>

</main>

<script>
const searchProduction = document.getElementById("searchProduction");

searchProduction.addEventListener("input", function() {

    const keyword = this.value.toLowerCase();

    document.querySelectorAll("#productionBody tr").forEach(row => {

        const content = row.innerText.toLowerCase();

        row.style.display = content.includes(keyword) ? "" : "none";

    });

});
</script>


</div><!-- /.dashboard -->

</body>
</html>
