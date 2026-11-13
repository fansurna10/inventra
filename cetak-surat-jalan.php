<?php
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/auth.php';
$id=(int)($_GET['id']??0);
$q=$pdo->prepare("SELECT sj.*,u.nama_lengkap FROM surat_jalan sj LEFT JOIN users u ON u.id=sj.user_id WHERE sj.id=?");
$q->execute([$id]);$sj=$q->fetch();
if(!$sj){http_response_code(404);exit('Surat jalan tidak ditemukan.');}
$q=$pdo->prepare("SELECT d.*,b.nama_barang,b.satuan FROM surat_jalan_detail d JOIN barang b ON b.id=d.barang_id WHERE d.surat_jalan_id=? ORDER BY d.id");
$q->execute([$id]);$items=$q->fetchAll();
function h($v){return htmlspecialchars((string)($v??''),ENT_QUOTES,'UTF-8');}
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title><?=h($sj['nomor_surat'])?></title><style>
body{font-family:Arial,sans-serif;color:#172b25;margin:35px}.sheet{max-width:850px;margin:auto}.top{display:flex;justify-content:space-between;border-bottom:3px solid #15965b;padding-bottom:15px}.top h1{color:#087443;margin:0}.meta{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:24px 0}.meta div{padding:8px;background:#f4f8f5}table{width:100%;border-collapse:collapse}th{background:#15965b;color:white}th,td{border:1px solid #cddbd2;padding:11px;text-align:left;font-size:13px}.sign{display:flex;justify-content:space-between;margin-top:60px;text-align:center}.sign div{width:220px}.print{background:#15965b;color:white;border:0;padding:11px 18px;border-radius:6px;cursor:pointer;margin-bottom:18px}@media print{.print{display:none}body{margin:10mm}}
</style></head><body><div class="sheet"><button class="print" onclick="window.print()">Cetak / Simpan PDF</button>
<div class="top"><div><h1>INVENTRA</h1><p>Inventory & Kanban System</p></div><div><h2>SURAT JALAN <?=h($sj['jenis'])?></h2><strong><?=h($sj['nomor_surat'])?></strong></div></div>
<div class="meta"><div><b>Tanggal:</b> <?=h(date('d-m-Y',strtotime($sj['tanggal'])))?></div><div><b><?= $sj['jenis']==='MASUK'?'Supplier':'Tujuan / Penerima' ?>:</b> <?=h($sj['pihak'])?></div><div style="grid-column:1/-1"><b>Keterangan:</b> <?=h($sj['keterangan']?:'-')?></div></div>
<table><thead><tr><th>No</th><th>Part Number</th><th>Nama Barang</th><th>Kanban Barcode</th><th>Qty</th><th>Satuan</th></tr></thead><tbody>
<?php $total=0;foreach($items as $i=>$it):$total+=$it['qty'];?><tr><td><?=$i+1?></td><td><?=h($it['part_no'])?></td><td><?=h($it['nama_barang'])?></td><td><?=h($it['kanban_barcode'])?></td><td><?=number_format($it['qty'])?></td><td><?=h($it['satuan'])?></td></tr><?php endforeach;?>
</tbody><tfoot><tr><th colspan="4" style="text-align:right">Total Qty</th><th><?=number_format($total)?></th><th></th></tr></tfoot></table>
<div class="sign"><div>Diserahkan oleh,<br><br><br>(........................)</div><div>Diterima oleh,<br><br><br>(........................)</div><div>Mengetahui,<br><br><br>(........................)</div></div><p style="font-size:11px;color:#718078;margin-top:35px">Dicetak dari INVENTRA oleh <?=h($sj['nama_lengkap']?:'System')?>.</p></div></body></html>
