<?php
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/auth.php';
$keyword=trim($_GET['q']??'');
$stmt=$pdo->prepare("SELECT sj.*,u.nama_lengkap,(SELECT COUNT(*) FROM surat_jalan_detail d WHERE d.surat_jalan_id=sj.id) jumlah_item,(SELECT COALESCE(SUM(d.qty),0) FROM surat_jalan_detail d WHERE d.surat_jalan_id=sj.id) total_qty FROM surat_jalan sj LEFT JOIN users u ON u.id=sj.user_id WHERE sj.nomor_surat LIKE ? OR sj.pihak LIKE ? ORDER BY sj.tanggal DESC,sj.id DESC");
$like='%'.$keyword.'%';$stmt->execute([$like,$like]);$rows=$stmt->fetchAll();
function h($v){return htmlspecialchars((string)($v??''),ENT_QUOTES,'UTF-8');}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Riwayat Surat Jalan | INVENTRA</title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
.history-panel{background:#fff;border:1px solid #e5eee8;border-radius:15px;padding:22px;box-shadow:0 7px 25px rgba(20,70,44,.04)}
.history-toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:18px}
.history-search{display:flex;gap:8px;flex:1;max-width:560px}
.history-search input{flex:1;min-width:180px;padding:11px 13px;border:1px solid #dce7e0;border-radius:8px;outline:none}
.history-search input:focus{border-color:#15965b;box-shadow:0 0 0 3px rgba(21,150,91,.09)}
.history-search button,.history-btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 14px;border:0;border-radius:8px;background:#15965b;color:#fff;text-decoration:none;font-weight:700;font-size:12px;cursor:pointer;white-space:nowrap}
.history-search button:hover,.history-btn:hover{background:#087443}
.history-table-wrap{overflow-x:auto}
.history-table{width:100%;min-width:850px;border-collapse:collapse}
.history-table th{background:#edf8f1;color:#176b45;text-align:left;padding:13px;font-size:11px;white-space:nowrap}
.history-table td{padding:13px;border-bottom:1px solid #edf0ee;color:#53647b;font-size:12px}
.history-table tbody tr:hover{background:#f7fbf8}
.history-number{font-weight:700;color:#15965b}
.history-tag{display:inline-block;padding:5px 9px;border-radius:20px;font-size:10px;font-weight:800;background:#eaf7ef;color:#087443}
.history-tag.keluar{background:#fff3e5;color:#bd6a13}
.history-empty{text-align:center;padding:35px;color:#8996a9}
@media(max-width:600px){.history-panel{padding:14px}.history-search{width:100%;max-width:none}}
</style>
</head>
<body>
<div class="dashboard">
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
<main class="main-content">
    <div class="page-heading">
        <div><h1>Riwayat Surat Jalan</h1><p>Daftar dokumen penerimaan dan pengeluaran barang.</p></div>
    </div>
    <section class="history-panel">
        <div class="history-toolbar">
            <div><h3>Daftar Dokumen</h3><p style="color:#8996a9;font-size:11px;margin-top:4px">Cari berdasarkan nomor surat atau supplier / tujuan.</p></div>
            <form class="history-search" method="get">
                <input name="q" value="<?=h($keyword)?>" placeholder="Cari nomor surat / supplier / tujuan">
                <button type="submit">Cari</button>
            </form>
        </div>
        <div class="history-table-wrap">
            <table class="history-table">
                <thead><tr><th>NO. SURAT</th><th>JENIS</th><th>TANGGAL</th><th>SUPPLIER / TUJUAN</th><th>ITEM</th><th>TOTAL QTY</th><th>DIBUAT OLEH</th><th>DOKUMEN</th></tr></thead>
                <tbody>
                <?php foreach($rows as $r): ?>
                    <tr>
                        <td class="history-number"><?=h($r['nomor_surat'])?></td>
                        <td><span class="history-tag <?=strtoupper($r['jenis'])==='KELUAR'?'keluar':''?>"><?=h($r['jenis'])?></span></td>
                        <td><?=h(date('d-m-Y',strtotime($r['tanggal'])))?></td>
                        <td><?=h($r['pihak'])?></td>
                        <td><?=number_format((int)$r['jumlah_item'])?></td>
                        <td><?=number_format((int)$r['total_qty'])?></td>
                        <td><?=h($r['nama_lengkap']?:'-')?></td>
                        <td><a class="history-btn" target="_blank" rel="noopener" href="cetak-surat-jalan.php?id=<?=(int)$r['id']?>">Lihat / Cetak</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if(!$rows): ?><tr><td colspan="8" class="history-empty">Belum ada surat jalan yang sesuai.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</div>
</body>
</html>
