
<?php
require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode = trim($_POST['kode_barang'] ?? '');
    $part_no = trim($_POST['part_no'] ?? '');
    $kanban_barcode = trim($_POST['kanban_barcode'] ?? '');
    $nama = trim($_POST['nama_barang'] ?? '');
    $kategori_id = $_POST['kategori_id'] ?? '';
    $jenis = trim($_POST['jenis'] ?? '');
    $satuan = trim($_POST['satuan'] ?? '');
    $stok_minimum = (int)($_POST['stok_minimum'] ?? 0);

    if ($kode === '' || $nama === '' || $kategori_id === '') {
        $error = 'Kode, nama, dan kategori wajib diisi.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO barang
                (kode_barang, part_no, kanban_barcode, nama_barang, kategori_id, jenis, satuan, stok_minimum)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $kode, ($part_no !== '' ? $part_no : $kode), $kanban_barcode, $nama, $kategori_id, $jenis, $satuan, $stok_minimum
            ]);

            header('Location: barang.php?status=tambah');
            exit;
        } catch (PDOException $e) {
            $error = 'Gagal menyimpan barang. Periksa kode barang dan kategori.';
        }
    }
}

$kategori = $pdo->query(
    "SELECT * FROM kategori ORDER BY nama_kategori ASC"
)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tambah Barang | INVENTRA</title>
<link rel="stylesheet" href="../assets/css/style.css">

<style>
* { box-sizing: border-box; }

body {
    background: #f2f7f3;
}

.form-container {
    max-width: 820px;
    margin: 0 auto;
    padding: 35px;
    background: white;
    border-radius: 20px;
    box-shadow: 0 12px 35px rgba(25, 90, 52, .09);
    animation: masuk .7s ease both;
}

@keyframes masuk {
    from { opacity: 0; transform: translateY(35px); }
    to { opacity: 1; transform: translateY(0); }
}

.form-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 28px;
}

.form-icon {
    width: 58px;
    height: 58px;
    border-radius: 16px;
    display: grid;
    place-items: center;
    background: #e1f4e7;
    color: #16834b;
    font-size: 28px;
    animation: melayang 3s ease-in-out infinite;
}

@keyframes melayang {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-6px); }
}

.form-header h2 {
    color: #176b43;
    margin: 0 0 5px;
}

.form-header p {
    color: #829087;
    margin: 0;
    font-size: 14px;
}

.form-group {
    margin-bottom: 20px;
    animation: muncul .6s ease both;
}

.form-group:nth-child(2) { animation-delay: .08s; }
.form-group:nth-child(3) { animation-delay: .16s; }
.form-group:nth-child(4) { animation-delay: .24s; }
.form-group:nth-child(5) { animation-delay: .32s; }
.form-group:nth-child(6) { animation-delay: .40s; }

@keyframes muncul {
    from { opacity: 0; transform: translateX(-15px); }
    to { opacity: 1; transform: translateX(0); }
}

.form-group label {
    display: block;
    margin-bottom: 9px;
    font-weight: 600;
    color: #34483b;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 14px 15px;
    border: 1px solid #dce7df;
    border-radius: 11px;
    outline: none;
    background: #fbfdfb;
    color: #263b2e;
    transition: .25s ease;
}

.form-group input:focus,
.form-group select:focus {
    border-color: #16834b;
    background: white;
    box-shadow: 0 0 0 4px rgba(22, 131, 75, .10);
    transform: translateY(-2px);
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 30px;
}

.btn-save, .btn-cancel {
    border: none;
    border-radius: 10px;
    padding: 13px 23px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: .25s ease;
}

.btn-save {
    background: #16834b;
    color: white;
    box-shadow: 0 5px 12px rgba(22,131,75,.2);
}

.btn-save:hover {
    background: #106b3c;
    transform: translateY(-3px);
    box-shadow: 0 9px 18px rgba(22,131,75,.25);
}

.btn-save:active {
    transform: scale(.96);
}

.btn-cancel {
    background: #edf2ee;
    color: #405348;
}

.btn-cancel:hover {
    background: #dce9df;
    transform: translateY(-3px);
}

.error {
    background: #fff0f0;
    border-left: 4px solid #d94343;
    color: #a11;
    padding: 14px;
    border-radius: 9px;
    margin-bottom: 20px;
    animation: masuk .4s ease;
}

@media(max-width:600px) {
    .form-container { margin: 20px 12px; padding: 22px; }
    .form-actions { flex-direction: column; }
    .btn-save, .btn-cancel { text-align: center; }
}

@media(prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        animation-duration: .01ms !important;
        transition-duration: .01ms !important;
    }
}
</style>
</head>

<body>

<div class="dashboard">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="main-content">

<div class="form-container">

    <div class="form-header">
        <div class="form-icon">📦</div>
        <div>
            <h2>Tambah Data Barang</h2>
            <p>Lengkapi informasi barang untuk inventaris perusahaan.</p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <div class="form-group">
            <label>Kode Barang</label>
            <input type="text" name="kode_barang"
                   placeholder="Contoh: BRG-001" required>
        </div>

        <div class="form-group">
            <label>Part Number</label>
            <input type="text" name="part_no" placeholder="Contoh: PRT-001">
        </div>

        <div class="form-group">
            <label>Kanban Barcode</label>
            <input type="text" name="kanban_barcode" placeholder="Scan atau input kode barcode">
        </div>

        <div class="form-group">
            <label>Nama Barang</label>
            <input type="text" name="nama_barang"
                   placeholder="Masukkan nama barang" required>
        </div>

        <div class="form-group">
            <label>Kategori Barang</label>
            <select name="kategori_id" required>
                <option value="">-- Pilih Kategori --</option>
                <?php foreach ($kategori as $k): ?>
                    <option value="<?= htmlspecialchars($k['id']) ?>">
                        <?= htmlspecialchars($k['nama_kategori']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Jenis Barang</label>
            <input type="text" name="jenis"
                   placeholder="Contoh: Elektronik">
        </div>

        <div class="form-group">
            <label>Satuan</label>
            <input type="text" name="satuan"
                   placeholder="Contoh: Pcs, Unit, Kg">
        </div>

        <div class="form-group">
            <label>Stok Minimum</label>
            <input type="number" name="stok_minimum"
                   min="0" value="0">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-save">
                ✓ Simpan Barang
            </button>

            <a href="barang.php" class="btn-cancel">
                Kembali
            </a>
        </div>

    </form>
</div>

    </main>
</div><!-- /.dashboard -->

</body>
</html>
