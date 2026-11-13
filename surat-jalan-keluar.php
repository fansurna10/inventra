
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

function h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$jenis = 'KELUAR';
$error = '';
$today = date('Y-m-d');

$nomor = 'SJ-K-' . date('Ymd-His') . '-' .
         strtoupper(bin2hex(random_bytes(2)));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tanggal = trim($_POST['tanggal'] ?? '');
    $pihak = trim($_POST['pihak'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');

    $ids = $_POST['barang_id'] ?? [];
    $qtys = $_POST['qty'] ?? [];
    $barcodes = $_POST['barcode'] ?? [];

    try {

        // 1. Validasi tanggal
        $dt = DateTime::createFromFormat('!Y-m-d', $tanggal);

        if (!$dt || $dt->format('Y-m-d') !== $tanggal) {
            throw new Exception('Tanggal surat jalan tidak valid.');
        }

        // 2. Validasi penerima
        if ($pihak === '') {
            throw new Exception('Nama tujuan atau penerima wajib diisi.');
        }

        // 3. Validasi daftar barang
        if (
            !is_array($ids) ||
            count($ids) === 0 ||
            !is_array($qtys) ||
            !is_array($barcodes) ||
            count($ids) !== count($qtys) ||
            count($ids) !== count($barcodes)
        ) {
            throw new Exception('Tambahkan minimal satu item barang dengan data lengkap.');
        }

        $items = [];

        // 4. Mengambil data barang
        foreach ($ids as $i => $rawId) {

            $id = filter_var($rawId, FILTER_VALIDATE_INT);
            $rawQty = $qtys[$i] ?? '';
            $qty = filter_var($rawQty, FILTER_VALIDATE_INT);
            $barcode = trim($barcodes[$i] ?? '');

            if (!$id || $id <= 0 || $qty === false || $qty <= 0) {
                throw new Exception('Pilih barang dan isi Qty dengan bilangan bulat lebih dari nol.');
            }

            $q = $pdo->prepare("
                SELECT
                    id,
                    kode_barang,
                    nama_barang,
                    part_no,
                    kanban_barcode,
                    satuan
                FROM barang
                WHERE id = ?
                LIMIT 1
            ");

            $q->execute([$id]);
            $barang = $q->fetch(PDO::FETCH_ASSOC);

            if (!$barang) {
                throw new Exception('Data barang tidak ditemukan.');
            }

            if ($barcode === '') {
                $barcode = $barang['kanban_barcode'] ?? '';
            }

            if ($barcode === '') {
                throw new Exception(
                    'Kanban Barcode untuk ' .
                    $barang['nama_barang'] .
                    ' belum tersedia. Lengkapi di Data Barang.'
                );
            }

            $items[] = [
                'barang' => $barang,
                'qty' => $qty,
                'barcode' => $barcode
            ];
        }

        // 5. Menghitung total Qty per barang
        // Agar barang yang dipilih dua kali tidak lolos validasi stok.
        $totalPerBarang = [];

        foreach ($items as $item) {

            $idBarang = (int)$item['barang']['id'];

            if (!isset($totalPerBarang[$idBarang])) {
                $totalPerBarang[$idBarang] = [
                    'nama' => $item['barang']['nama_barang'],
                    'qty' => 0
                ];
            }

            $totalPerBarang[$idBarang]['qty'] += (int)$item['qty'];
        }

        // 6. Memulai transaksi database
        $pdo->beginTransaction();

        // 7. Memeriksa stok tersedia
        foreach ($totalPerBarang as $idBarang => $data) {

            $q = $pdo->prepare("
                SELECT
                    COALESCE(
                        (SELECT SUM(jumlah)
                         FROM barang_masuk
                         WHERE barang_id = ?), 0
                    ) -
                    COALESCE(
                        (SELECT SUM(jumlah)
                         FROM barang_keluar
                         WHERE barang_id = ?), 0
                    )
            ");

            $q->execute([$idBarang, $idBarang]);
            $stok = (int)$q->fetchColumn();

            if ($data['qty'] > $stok) {
                throw new Exception(
                    'Stok ' . $data['nama'] .
                    ' tidak cukup. Tersedia: ' . $stok .
                    ', total diminta: ' . $data['qty']
                );
            }
        }

        // 8. Menyimpan header surat jalan
        $ins = $pdo->prepare("
            INSERT INTO surat_jalan
            (
                nomor_surat,
                jenis,
                tanggal,
                pihak,
                keterangan,
                user_id
            )
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $ins->execute([
            $nomor,
            $jenis,
            $tanggal,
            $pihak,
            $keterangan,
            $_SESSION['user_id'] ?? null
        ]);

        $sjid = (int)$pdo->lastInsertId();

        // 9. Menyiapkan query detail surat jalan
        $detail = $pdo->prepare("
            INSERT INTO surat_jalan_detail
            (
                surat_jalan_id,
                barang_id,
                part_no,
                kanban_barcode,
                qty
            )
            VALUES (?, ?, ?, ?, ?)
        ");

        // 10. Menyiapkan transaksi barang keluar
        $trx = $pdo->prepare("
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

        // 11. Menyimpan setiap item barang
        foreach ($items as $item) {

            $barang = $item['barang'];
            $qty = $item['qty'];

            $partNo = !empty($barang['part_no'])
                ? $barang['part_no']
                : $barang['kode_barang'];

            // Detail surat jalan
            $detail->execute([
                $sjid,
                $barang['id'],
                $partNo,
                $item['barcode'],
                $qty
            ]);

            // Transaksi stok keluar
            $trx->execute([
                $nomor,
                $tanggal,
                $barang['id'],
                $qty,
                $keterangan
            ]);
        }

        // 12. Menyelesaikan transaksi
        $pdo->commit();

        // 13. Menuju halaman cetak
        header('Location: cetak-surat-jalan.php?id=' . $sjid);
        exit;

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($e instanceof PDOException) {
            $error = 'Gagal menyimpan surat jalan. Periksa struktur database dan data transaksi.';
        } else {
            $error = $e->getMessage();
        }
    }
}

// 14. Mengambil daftar barang
$barang = $pdo->query("
    SELECT
        id,
        kode_barang,
        nama_barang,
        COALESCE(NULLIF(part_no, ''), kode_barang) AS part_no,
        kanban_barcode,
        satuan
    FROM barang
    ORDER BY nama_barang ASC
")->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="id">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Surat Jalan Keluar | INVENTRA</title>

<link rel="stylesheet" href="../assets/css/style.css">

<style>
* {
    box-sizing: border-box;
}

body {
    background: #f5f8f6;
}

.main-content {
    padding: 28px;
}

.heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 22px;
}

.heading h1 {
    font-size: 24px;
    color: #192d49;
}

.heading p {
    font-size: 13px;
    color: #829087;
    margin-top: 6px;
}

.panel {
    background: #ffffff;
    border: 1px solid #e7ede9;
    border-radius: 14px;
    padding: 22px;
    margin-bottom: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.03);
}

.grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.field label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    color: #34445b;
    margin-bottom: 7px;
}

.field input {
    width: 100%;
    padding: 11px;
    border: 1px solid #dce7e0;
    border-radius: 8px;
    background: #ffffff;
    outline: none;
}

.field input:focus,
td input:focus,
td select:focus {
    border-color: #15965b;
}

.table-wrap {
    overflow-x: auto;
    margin-top: 22px;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 760px;
}

th {
    background: #15965b;
    color: white;
    text-align: left;
    padding: 13px;
    font-size: 12px;
}

td {
    padding: 10px;
    border-bottom: 1px solid #edf0ee;
    font-size: 12px;
}

td input,
td select {
    width: 100%;
    padding: 9px;
    border: 1px solid #dce7e0;
    border-radius: 7px;
}

.barcode {
    font-family: monospace;
    color: #087443;
    letter-spacing: 1px;
}

.btn {
    display: inline-block;
    border: 0;
    border-radius: 8px;
    padding: 11px 16px;
    font-weight: 700;
    font-size: 12px;
    text-decoration: none;
    cursor: pointer;
}

.primary {
    background: #15965b;
    color: white;
}

.secondary {
    background: #eaf7ef;
    color: #087443;
}

.danger {
    background: #fff0f0;
    color: #b42318;
}

.actions {
    display: flex;
    gap: 9px;
    justify-content: flex-end;
    margin-top: 18px;
}

.alert {
    background: #fff0f0;
    color: #b42318;
    padding: 13px;
    border-radius: 8px;
    margin-bottom: 15px;
    font-size: 13px;
}

@media(max-width: 700px) {
    .grid {
        grid-template-columns: 1fr;
    }

    .heading {
        align-items: flex-start;
        gap: 12px;
        flex-direction: column;
    }

    .main-content {
        padding: 15px;
    }
}
</style>

</head>

<body>
<div class="dashboard">
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-content">

    <div class="heading">

        <div>
            <h1>Surat Jalan Keluar</h1>
            <p>Dokumen pengeluaran barang dari gudang INVENTRA.</p>
        </div>

        <a class="btn secondary" href="riwayat-surat-jalan.php">
            Riwayat Surat Jalan
        </a>

    </div>

    <?php if ($error): ?>
        <div class="alert">
            <?= h($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="panel">

        <div class="grid">

            <div class="field">
                <label>Nomor Surat Jalan</label>
                <input type="text" value="<?= h($nomor) ?>" readonly>
            </div>

            <div class="field">
                <label>Tanggal *</label>
                <input
                    type="date"
                    name="tanggal"
                    value="<?= h($today) ?>"
                    required
                >
            </div>

            <div class="field">
                <label>Tujuan / Penerima *</label>
                <input
                    type="text"
                    name="pihak"
                    placeholder="Nama departemen atau penerima"
                    required
                >
            </div>

            <div class="field">
                <label>Keterangan</label>
                <input
                    type="text"
                    name="keterangan"
                    placeholder="Catatan transaksi (opsional)"
                >
            </div>

        </div>

        <div class="table-wrap">

            <table>
                <thead>
                    <tr>
                        <th style="width:27%">Part No / Barang</th>
                        <th style="width:22%">Kanban Barcode</th>
                        <th style="width:13%">Qty</th>
                        <th style="width:12%">Satuan</th>
                        <th style="width:10%">Aksi</th>
                    </tr>
                </thead>

                <tbody id="item-body"></tbody>

            </table>

        </div>

        <div class="actions" style="justify-content:flex-start">

            <button
                type="button"
                class="btn secondary"
                onclick="addRow()"
            >
                + Tambah Item
            </button>

        </div>

        <div class="actions">

            <a href="../index.php" class="btn secondary">
                Batal
            </a>

            <button type="submit" class="btn primary">
                Simpan & Cetak Surat Jalan
            </button>

        </div>

    </form>

</div>

<script>

const products = <?= json_encode(
    $barang,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
) ?>;

function addRow() {

    const tbody = document.getElementById('item-body');
    const tr = document.createElement('tr');

    // Kolom pilihan barang
    const tdBarang = document.createElement('td');
    const select = document.createElement('select');

    select.name = 'barang_id[]';
    select.required = true;
    select.addEventListener('change', function () {
        fillRow(this);
    });

    const defaultOption = document.createElement('option');
    defaultOption.value = '';
    defaultOption.textContent = 'Pilih Part No / Barang';
    select.appendChild(defaultOption);

    products.forEach(function (product) {

        const option = document.createElement('option');

        option.value = product.id;
        option.textContent =
            product.part_no + ' | ' + product.nama_barang;

        option.dataset.barcode = product.kanban_barcode || '';
        option.dataset.unit = product.satuan || '';

        select.appendChild(option);

    });

    tdBarang.appendChild(select);

    // Kolom barcode
    const tdBarcode = document.createElement('td');
    const barcode = document.createElement('input');

    barcode.type = 'text';
    barcode.name = 'barcode[]';
    barcode.className = 'barcode';
    barcode.placeholder = 'Barcode Kanban';
    barcode.required = true;

    tdBarcode.appendChild(barcode);

    // Kolom Qty
    const tdQty = document.createElement('td');
    const qty = document.createElement('input');

    qty.type = 'number';
    qty.name = 'qty[]';
    qty.min = '1';
    qty.step = '1';
    qty.required = true;
    qty.placeholder = '0';

    tdQty.appendChild(qty);

    // Kolom satuan
    const tdUnit = document.createElement('td');
    tdUnit.className = 'unit';
    tdUnit.textContent = '-';

    // Kolom tombol hapus
    const tdAction = document.createElement('td');
    const deleteButton = document.createElement('button');

    deleteButton.type = 'button';
    deleteButton.className = 'btn danger';
    deleteButton.textContent = 'Hapus';

    deleteButton.addEventListener('click', function () {
        tr.remove();
    });

    tdAction.appendChild(deleteButton);

    // Memasukkan semua kolom ke baris
    tr.appendChild(tdBarang);
    tr.appendChild(tdBarcode);
    tr.appendChild(tdQty);
    tr.appendChild(tdUnit);
    tr.appendChild(tdAction);

    tbody.appendChild(tr);
}

function fillRow(select) {

    const option = select.selectedOptions[0];
    const tr = select.closest('tr');

    tr.querySelector('[name="barcode[]"]').value =
        option.dataset.barcode || '';

    tr.querySelector('.unit').textContent =
        option.dataset.unit || '-';
}

// Baris pertama otomatis muncul
addRow();

</script>


</div><!-- /.dashboard -->
</body>
</html>
