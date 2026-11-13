
<?php
require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . "/../config/database.php";

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$error = "";
$editSupplier = null;

// PROSES TAMBAH, EDIT, HAPUS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    try {

        if ($action === 'tambah' || $action === 'edit') {

            $nama = trim($_POST['nama_supplier'] ?? '');
            $kontak = trim($_POST['kontak'] ?? '');
            $alamat = trim($_POST['alamat'] ?? '');
            $email = trim($_POST['email'] ?? '');

            if ($nama === '') {
                throw new Exception("Nama supplier wajib diisi.");
            }

            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Format email tidak valid.");
            }

            if ($action === 'tambah') {

                $stmt = $pdo->prepare("
                    INSERT INTO supplier
                    (nama_supplier, kontak, alamat, email)
                    VALUES (?, ?, ?, ?)
                ");

                $stmt->execute([
                    $nama, $kontak, $alamat, $email
                ]);

            } else {

                $id = (int)($_POST['id'] ?? 0);

                $stmt = $pdo->prepare("
                    UPDATE supplier
                    SET nama_supplier = ?,
                        kontak = ?,
                        alamat = ?,
                        email = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $nama, $kontak, $alamat, $email, $id
                ]);
            }

            header("Location: supplier.php?success=1");
            exit;
        }

        if ($action === 'hapus') {

            $id = (int)($_POST['id'] ?? 0);

            $stmt = $pdo->prepare("
                DELETE FROM supplier WHERE id = ?
            ");

            $stmt->execute([$id]);

            header("Location: supplier.php?deleted=1");
            exit;
        }

    } catch (PDOException $ex) {

        $error = "Data gagal diproses. Supplier mungkin masih digunakan dalam transaksi.";

    } catch (Exception $ex) {

        $error = $ex->getMessage();
    }
}

// AMBIL DATA UNTUK EDIT
if (isset($_GET['edit'])) {

    $id = (int)$_GET['edit'];

    $stmt = $pdo->prepare("
        SELECT * FROM supplier WHERE id = ?
    ");

    $stmt->execute([$id]);
    $editSupplier = $stmt->fetch();

}

// STATISTIK
$totalSupplier = (int)$pdo->query("
    SELECT COUNT(*) FROM supplier
")->fetchColumn();

$totalTransaksi = (int)$pdo->query("
    SELECT COUNT(*) FROM barang_masuk
")->fetchColumn();

// DAFTAR SUPPLIER
$keyword = trim($_GET['search'] ?? '');

$stmt = $pdo->prepare("
    SELECT s.*,
    (
        SELECT COUNT(*)
        FROM barang_masuk bm
        WHERE bm.supplier_id = s.id
    ) AS total_transaksi
    FROM supplier s
    WHERE s.nama_supplier LIKE ?
       OR s.kontak LIKE ?
       OR s.email LIKE ?
    ORDER BY s.id DESC
");

$like = "%$keyword%";
$stmt->execute([$like, $like, $like]);
$suppliers = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="id">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Supplier | INVENTRA</title>

<link rel="stylesheet" href="../assets/css/style.css">

<style>

.page-heading {
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:25px;
    gap:15px;
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

.btn-primary {
    display:inline-block;
    background:#15965b;
    color:white;
    padding:12px 18px;
    border-radius:9px;
    text-decoration:none;
    font-size:12px;
    font-weight:700;
    border:none;
    cursor:pointer;
    transition:.3s;
}

.btn-primary:hover {
    background:#087443;
    transform:translateY(-3px);
}

.stats-grid {
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:18px;
    margin-bottom:25px;
}

.stat-card {
    background:white;
    padding:22px;
    border:1px solid #e7ede9;
    border-radius:13px;
    animation:fadeIn .6s ease both;
    transition:.3s;
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

.search-box {
    width:320px;
    max-width:100%;
    padding:12px 15px;
    border:1px solid #e2e8e5;
    border-radius:9px;
    outline:none;
    font-size:12px;
}

.search-box:focus,
.form-control:focus {
    border-color:#15965b;
    box-shadow:0 0 0 3px rgba(21,150,91,.08);
}

.table-wrap {
    overflow-x:auto;
}

.data-table {
    width:100%;
    min-width:750px;
    border-collapse:collapse;
    font-size:12px;
}

.data-table th {
    text-align:left;
    padding:15px;
    background:#edf8f1;
    color:#176b45;
    font-size:11px;
}

.data-table td {
    padding:15px;
    border-bottom:1px solid #edf0ee;
    color:#53647b;
}

.data-table tr:hover {
    background:#f5fbf7;
}

.action-btn {
    border:0;
    padding:7px 11px;
    border-radius:6px;
    font-size:11px;
    text-decoration:none;
    cursor:pointer;
    display:inline-block;
    margin:2px;
}

.edit-btn {
    background:#e8f1ff;
    color:#2563a8;
}

.delete-btn {
    background:#fff0f0;
    color:#dc4444;
}

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
    font-size:12px;
    font-weight:600;
    color:#34445b;
}

.form-control {
    padding:12px;
    border:1px solid #e2e8e5;
    border-radius:8px;
    outline:none;
    font-size:12px;
    font-family:inherit;
}

textarea.form-control {
    min-height:90px;
    resize:vertical;
}

.alert {
    padding:13px 16px;
    margin-bottom:20px;
    border-radius:8px;
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
            <h1>Manajemen Supplier</h1>
            <p>Kelola informasi pemasok dan mitra pengadaan perusahaan.</p>
        </div>

        <a href="supplier.php#formSupplier" class="btn-primary">
            + Tambah Supplier
        </a>

    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            Data supplier berhasil disimpan.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">
            Data supplier berhasil dihapus.
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
            <span>Total Supplier</span>
            <h2><?= number_format($totalSupplier) ?></h2>
        </div>

        <div class="stat-card">
            <span>Total Transaksi Pembelian</span>
            <h2><?= number_format($totalTransaksi) ?></h2>
        </div>

        <div class="stat-card">
            <span>Status Kemitraan</span>
            <h2 style="font-size:19px;">Aktif</h2>
        </div>

    </section>

    <!-- FORM SUPPLIER -->

    <section class="panel" id="formSupplier">

        <div class="panel-header">
            <h3>
                <?= $editSupplier ? 'Edit Informasi Supplier' : 'Formulir Supplier Baru' ?>
            </h3>
            <p>Masukkan informasi pemasok perusahaan.</p>
        </div>

        <form method="POST" action="supplier.php">

            <input
                type="hidden"
                name="action"
                value="<?= $editSupplier ? 'edit' : 'tambah' ?>"
            >

            <?php if ($editSupplier): ?>
                <input type="hidden" name="id" value="<?= (int)$editSupplier['id'] ?>">
            <?php endif; ?>

            <div class="form-grid">

                <div class="form-group">
                    <label>Nama Supplier *</label>
                    <input
                        type="text"
                        name="nama_supplier"
                        class="form-control"
                        placeholder="Contoh: PT Sumber Makmur"
                        value="<?= e($editSupplier['nama_supplier'] ?? '') ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>Nomor Kontak</label>
                    <input
                        type="text"
                        name="kontak"
                        class="form-control"
                        placeholder="08xxxxxxxxxx"
                        value="<?= e($editSupplier['kontak'] ?? '') ?>"
                    >
                </div>

                <div class="form-group">
                    <label>Email Supplier</label>
                    <input
                        type="email"
                        name="email"
                        class="form-control"
                        placeholder="supplier@email.com"
                        value="<?= e($editSupplier['email'] ?? '') ?>"
                    >
                </div>

                <div class="form-group full">
                    <label>Alamat Supplier</label>
                    <textarea
                        name="alamat"
                        class="form-control"
                        placeholder="Masukkan alamat lengkap supplier..."
                    ><?= e($editSupplier['alamat'] ?? '') ?></textarea>
                </div>

            </div>

            <div style="margin-top:20px;display:flex;gap:10px;">

                <button type="submit" class="btn-primary">
                    <?= $editSupplier ? 'Simpan Perubahan' : 'Simpan Supplier' ?>
                </button>

                <?php if ($editSupplier): ?>
                    <a href="supplier.php" class="action-btn edit-btn" style="padding:12px 18px;">
                        Batal Edit
                    </a>
                <?php endif; ?>

            </div>

        </form>

    </section>

    <!-- DAFTAR SUPPLIER -->

    <section class="panel">

        <div class="panel-header">
            <h3>Daftar Supplier</h3>
            <p>Informasi seluruh pemasok yang terdaftar di INVENTRA.</p>
        </div>

        <form method="GET" style="margin-bottom:20px;">
            <input
                type="text"
                name="search"
                class="search-box"
                placeholder="Cari nama, kontak, atau email supplier..."
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
                        <th>NAMA SUPPLIER</th>
                        <th>KONTAK</th>
                        <th>EMAIL</th>
                        <th>ALAMAT</th>
                        <th>TRANSAKSI</th>
                        <th>AKSI</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (count($suppliers) > 0): ?>

                    <?php foreach ($suppliers as $i => $s): ?>

                    <tr>

                        <td><?= $i + 1 ?></td>

                        <td>
                            <strong><?= e($s['nama_supplier']) ?></strong>
                        </td>

                        <td><?= e($s['kontak'] ?: '-') ?></td>

                        <td><?= e($s['email'] ?: '-') ?></td>

                        <td><?= e($s['alamat'] ?: '-') ?></td>

                        <td><?= number_format($s['total_transaksi']) ?></td>

                        <td style="white-space:nowrap;">

                            <a
                                href="supplier.php?edit=<?= (int)$s['id'] ?>#formSupplier"
                                class="action-btn edit-btn"
                            >
                                Edit
                            </a>

                            <form
                                method="POST"
                                style="display:inline;"
                                onsubmit="return confirm('Yakin ingin menghapus supplier ini?')"
                            >

                                <input type="hidden" name="action" value="hapus">

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int)$s['id'] ?>"
                                >

                                <button type="submit" class="action-btn delete-btn">
                                    Hapus
                                </button>

                            </form>

                        </td>

                    </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <div style="font-size:40px;">♧</div>
                                <h3>Belum Ada Data Supplier</h3>
                                <p>Tambahkan supplier pertama untuk memulai pengelolaan pemasok.</p>
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
