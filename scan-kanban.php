<?php
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/auth.php';
$hasil=null;$kode=trim($_GET['barcode']??'');
if($kode!==''){$q=$pdo->prepare("SELECT b.id,b.kode_barang,b.part_no,b.kanban_barcode,b.nama_barang,b.satuan,COALESCE((SELECT SUM(jumlah) FROM barang_masuk WHERE barang_id=b.id),0)-COALESCE((SELECT SUM(jumlah) FROM barang_keluar WHERE barang_id=b.id),0) stok FROM barang b WHERE b.kanban_barcode=? OR b.part_no=? OR b.kode_barang=? LIMIT 1");$q->execute([$kode,$kode,$kode]);$hasil=$q->fetch();}
function h($v){return htmlspecialchars((string)($v??''),ENT_QUOTES,'UTF-8');}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Scan Kanban | INVENTRA</title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
.scan-card{max-width:820px;background:#fff;border:1px solid #e5eee8;border-radius:16px;padding:28px;box-shadow:0 8px 28px rgba(20,70,44,.05)}
.scan-card h2{font-size:19px;color:#193b2c;margin-bottom:8px}
.scan-card .sub{color:#7c8b81;font-size:13px;margin-bottom:22px}
.scan-form{display:flex;gap:10px;align-items:center}
.scan-form input{flex:1;min-width:0;padding:14px 15px;border:1px solid #d9e7de;border-radius:9px;font-size:14px;outline:none}
.scan-form input:focus{border-color:#15965b;box-shadow:0 0 0 3px rgba(21,150,91,.1)}
.scan-form button{padding:14px 20px;background:#15965b;color:#fff;border:0;border-radius:9px;font-weight:700;cursor:pointer}
.scan-result{margin-top:22px;background:#edf8f1;border:1px solid #d7efdf;border-radius:12px;padding:20px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}
.scan-result small{display:block;color:#789081;font-size:11px;margin-bottom:5px}
.scan-result strong{color:#173c2b;font-size:14px;overflow-wrap:anywhere}
.scan-bad{margin-top:20px;padding:15px;border-radius:10px;background:#fff1f0;color:#a8322a}
.scan-hint{margin-top:16px;color:#8996a9;font-size:11px}
@media(max-width:600px){.scan-card{padding:18px}.scan-form{flex-direction:column;align-items:stretch}.scan-form button{width:100%}.scan-result{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="dashboard">
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
<main class="main-content">
    <div class="page-heading">
        <div><h1>Scan Kanban</h1><p>Pindai barcode untuk melihat identitas dan ketersediaan barang.</p></div>
    </div>
    <section class="scan-card">
        <h2>Scan Kanban Barcode</h2>
        <p class="sub">Gunakan scanner barcode USB atau masukkan kode secara manual.</p>
        <form class="scan-form" method="get" action="scan-kanban.php">
            <input autofocus name="barcode" value="<?=h($kode)?>" placeholder="Scan / ketik Kanban Barcode" autocomplete="off">
            <button type="submit">Cari Barang</button>
        </form>
        <?php if($kode!=='' && $hasil): ?>
            <div class="scan-result">
                <div><small>Part Number</small><strong><?=h($hasil['part_no']?:$hasil['kode_barang'])?></strong></div>
                <div><small>Nama Barang</small><strong><?=h($hasil['nama_barang'])?></strong></div>
                <div><small>Kanban Barcode</small><strong><?=h($hasil['kanban_barcode'])?></strong></div>
                <div><small>Stok Tersedia</small><strong><?=number_format($hasil['stok'])?> <?=h($hasil['satuan'])?></strong></div>
            </div>
        <?php elseif($kode!==''): ?>
            <div class="scan-bad">Barcode tidak ditemukan. Pastikan kode sudah terdaftar pada master barang.</div>
        <?php endif; ?>
        <p class="scan-hint">Tips: setelah melakukan scan, tekan Enter untuk mencari data barang.</p>
    </section>
</main>
</div>
</body>
</html>
