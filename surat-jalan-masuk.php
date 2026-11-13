<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
function h($v){return htmlspecialchars((string)($v??''),ENT_QUOTES,'UTF-8');}
$jenis = 'MASUK';
$masuk = $jenis === 'MASUK';
$error = '';
$today = date('Y-m-d');
$nomor = ($masuk ? 'SJ-M-' : 'SJ-K-') . date('Ymd-His');
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $tanggal = $_POST['tanggal'] ?? '';
    $pihak = trim($_POST['pihak'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');
    $ids = $_POST['barang_id'] ?? [];
    $qtys = $_POST['qty'] ?? [];
    $barcodes = $_POST['barcode'] ?? [];
    try {
        $dt=DateTime::createFromFormat('Y-m-d',$tanggal);
        if(!$dt || $dt->format('Y-m-d')!==$tanggal) throw new Exception('Tanggal surat jalan tidak valid.');
        if($pihak==='') throw new Exception($masuk?'Nama supplier wajib diisi.':'Tujuan/penerima wajib diisi.');
        if(!is_array($ids) || count($ids)===0) throw new Exception('Tambahkan minimal satu item barang.');
        $items=[];
        foreach($ids as $i=>$rawId){
            $id=(int)$rawId; $qty=(int)($qtys[$i]??0); $barcode=trim($barcodes[$i]??'');
            if($id<=0 || $qty<=0) throw new Exception('Pilih barang dan isi Qty lebih dari nol pada setiap baris.');
            $q=$pdo->prepare("SELECT id,kode_barang,nama_barang,part_no,kanban_barcode,satuan FROM barang WHERE id=?");
            $q->execute([$id]); $b=$q->fetch();
            if(!$b) throw new Exception('Data barang tidak ditemukan.');
            $barcode=$barcode!==''?$barcode:($b['kanban_barcode']?:'');
            if($barcode==='') throw new Exception('Kanban Barcode belum diisi untuk '.$b['nama_barang'].'. Lengkapi di Data Barang.');
            $items[]=['barang'=>$b,'qty'=>$qty,'barcode'=>$barcode];
        }
        $pdo->beginTransaction();
        if(!$masuk){
            foreach($items as $it){
                $q=$pdo->prepare("SELECT
                    COALESCE((SELECT SUM(jumlah) FROM barang_masuk WHERE barang_id=?),0) -
                    COALESCE((SELECT SUM(jumlah) FROM barang_keluar WHERE barang_id=?),0)");
                $q->execute([$it['barang']['id'],$it['barang']['id']]);
                $stok=(int)$q->fetchColumn();
                if($it['qty']>$stok) throw new Exception('Stok '.$it['barang']['nama_barang'].' tidak cukup. Tersedia: '.$stok);
            }
        }
        $ins=$pdo->prepare("INSERT INTO surat_jalan(nomor_surat,jenis,tanggal,pihak,keterangan,user_id) VALUES(?,?,?,?,?,?)");
        $ins->execute([$nomor,$jenis,$tanggal,$pihak,$keterangan,$_SESSION['user_id']??null]);
        $sjid=(int)$pdo->lastInsertId();
        $detail=$pdo->prepare("INSERT INTO surat_jalan_detail(surat_jalan_id,barang_id,part_no,kanban_barcode,qty) VALUES(?,?,?,?,?)");
        if($masuk){
            $trx=$pdo->prepare("INSERT INTO barang_masuk(nomor_transaksi,tanggal,barang_id,supplier_id,jumlah,keterangan) VALUES(?,?,?,?,?,?)");
            $supplier=$pdo->prepare("SELECT id FROM supplier WHERE nama_supplier=? LIMIT 1");
            $supplier->execute([$pihak]); $supplierId=$supplier->fetchColumn() ?: null;
        } else {
            $trx=$pdo->prepare("INSERT INTO barang_keluar(nomor_transaksi,tanggal,barang_id,jumlah,keterangan) VALUES(?,?,?,?,?)");
        }
        foreach($items as $it){
            $b=$it['barang']; $qty=$it['qty'];
            $detail->execute([$sjid,$b['id'],$b['part_no']?:$b['kode_barang'],$it['barcode'],$qty]);
            if($masuk) $trx->execute([$nomor,$tanggal,$b['id'],$supplierId,$qty,$keterangan]);
            else $trx->execute([$nomor,$tanggal,$b['id'],$qty,$keterangan]);
        }
        $pdo->commit();
        header('Location: cetak-surat-jalan.php?id='.$sjid); exit;
    } catch(Throwable $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        $error=$e instanceof PDOException?'Gagal menyimpan surat jalan. Pastikan upgrade database sudah dijalankan dan data sesuai.':$e->getMessage();
    }
}
$barang=$pdo->query("SELECT id,kode_barang,nama_barang,COALESCE(NULLIF(part_no,''),kode_barang) AS part_no,kanban_barcode,satuan FROM barang ORDER BY nama_barang")->fetchAll();
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Surat Jalan <?=strtolower($jenis)?> | INVENTRA</title><link rel="stylesheet" href="../assets/css/style.css">
<style>
*{box-sizing:border-box}.main-content{padding:28px}.heading{display:flex;justify-content:space-between;align-items:center;margin-bottom:22px}.heading h1{font-size:24px;color:#192d49}.heading p{font-size:12px;color:#829087;margin-top:5px}.panel{background:#fff;border:1px solid #e7ede9;border-radius:14px;padding:22px;margin-bottom:20px}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.field label{display:block;font-size:12px;font-weight:700;color:#34445b;margin-bottom:7px}.field input,.field select,.field textarea{width:100%;padding:11px;border:1px solid #dce7e0;border-radius:8px;background:#fff}.table-wrap{overflow:auto;margin-top:20px}table{width:100%;border-collapse:collapse;min-width:760px}th{background:#15965b;color:white;text-align:left;padding:12px;font-size:12px}td{padding:10px;border-bottom:1px solid #edf0ee;font-size:12px}td input,td select{width:100%;padding:9px;border:1px solid #dce7e0;border-radius:7px}.btn{display:inline-block;border:0;border-radius:8px;padding:11px 16px;font-weight:700;font-size:12px;text-decoration:none;cursor:pointer}.primary{background:#15965b;color:white}.secondary{background:#eaf7ef;color:#087443}.danger{background:#fff0f0;color:#b42318}.actions{display:flex;gap:9px;justify-content:flex-end;margin-top:18px}.alert{background:#fff0f0;color:#b42318;padding:12px;border-radius:8px;margin-bottom:15px;font-size:13px}.barcode{font-family:monospace;letter-spacing:1px;color:#087443}@media(max-width:700px){.grid{grid-template-columns:1fr}.heading{align-items:flex-start;gap:12px;flex-direction:column}}
</style></head><body>
<div class="dashboard">
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
<?php /* halaman transaksi berdiri sendiri agar tidak mengubah layout halaman lama */ ?>
<div class="main-content">
<div class="heading"><div><h1>Surat Jalan <?= $masuk?'Masuk':'Keluar' ?></h1><p><?= $masuk?'Dokumen penerimaan barang ke gudang.':'Dokumen pengeluaran barang dari gudang.' ?></p></div><a class="btn secondary" href="riwayat-surat-jalan.php">Riwayat Surat Jalan</a></div>
<?php if($error): ?><div class="alert"><?=h($error)?></div><?php endif; ?>
<form method="post" class="panel">
<div class="grid">
<div class="field"><label>Nomor Surat Jalan</label><input value="<?=h($nomor)?>" readonly></div>
<div class="field"><label>Tanggal *</label><input type="date" name="tanggal" value="<?=h($today)?>" required></div>
<div class="field"><label><?= $masuk?'Supplier':'Tujuan / Penerima' ?> *</label><input name="pihak" list="pihak-list" required placeholder="<?= $masuk?'Nama supplier':'Nama departemen / penerima' ?>"><datalist id="pihak-list"><?php if($masuk): foreach($pdo->query("SELECT nama_supplier FROM supplier ORDER BY nama_supplier") as $s): ?><option value="<?=h($s['nama_supplier'])?>"><?php endforeach; endif; ?></datalist></div>
<div class="field"><label>Keterangan</label><input name="keterangan" placeholder="Catatan transaksi (opsional)"></div>
</div>
<div class="table-wrap"><table id="items"><thead><tr><th style="width:27%">Part No / Barang</th><th style="width:22%">Kanban Barcode</th><th style="width:13%">Qty</th><th style="width:12%">Satuan</th><th style="width:10%">Aksi</th></tr></thead><tbody id="item-body"></tbody></table></div>
<div class="actions" style="justify-content:flex-start"><button type="button" class="btn secondary" onclick="addRow()">+ Tambah Item</button></div>
<div class="actions"><a href="../index.php" class="btn secondary">Batal</a><button class="btn primary" type="submit">Simpan & Cetak Surat Jalan</button></div>
</form></div>
<script>
const products = <?=json_encode($barang,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)?>;
function addRow(){
 const tr=document.createElement('tr');
 let opts='<option value="">Pilih Part No / Barang</option>'+products.map(p=>`<option value="${p.id}" data-barcode="${String(p.kanban_barcode||'').replace(/"/g,'&quot;')}" data-unit="${String(p.satuan||'').replace(/"/g,'&quot;')}">${p.part_no} | ${p.nama_barang}</option>`).join('');
 tr.innerHTML=`<td><select name="barang_id[]" required onchange="fillRow(this)">${opts}</select></td><td><input class="barcode" name="barcode[]" placeholder="Barcode Kanban" required></td><td><input name="qty[]" type="number" min="1" step="1" required placeholder="0"></td><td class="unit">-</td><td><button type="button" class="btn danger" onclick="this.closest('tr').remove()">Hapus</button></td>`;
 document.getElementById('item-body').appendChild(tr);
}
function fillRow(sel){const o=sel.selectedOptions[0],tr=sel.closest('tr');tr.querySelector('[name="barcode[]"]').value=o.dataset.barcode||'';tr.querySelector('.unit').textContent=o.dataset.unit||'-';}
addRow();
</script>
</div><!-- /.dashboard -->
</body></html>
