INVENTRA - PROFESSIONAL UI UPDATE
=================================

LOKASI PROYEK
-------------
Folder aplikasi yang digunakan:
C:\xampp\htdocs\inventra-updated\inventra

URL aplikasi:
http://localhost/inventra-updated/inventra/

PERUBAHAN DALAM PAKET INI
-------------------------
- Semua halaman aplikasi menggunakan satu sidebar bersama dari includes/sidebar.php.
- Menu sidebar disamakan: Dashboard, Data Barang, Supplier, Surat Jalan Masuk,
  Surat Jalan Keluar, Scan Kanban, Riwayat Surat Jalan, PPIC & Produksi, dan Laporan.
- Menu aktif mengikuti halaman yang sedang dibuka.
- URL sidebar dan aset mengikuti lokasi folder inventra-updated/inventra.
- Halaman Data Barang, Tambah Barang, Supplier, Barang Masuk/Keluar, PPIC,
  Laporan, Surat Jalan, Scan Kanban, dan Riwayat memakai layout sidebar yang sama.
- Halaman cetak surat jalan tetap dibuat terpisah agar hasil cetak/PDF bersih.
- Fitur pencarian dan filter Data Barang, pencarian Riwayat Surat Jalan,
  pencarian barcode, statistik dashboard, dan proses transaksi dipertahankan.

CARA MEMASANG
-------------
1. Backup folder project yang sekarang terlebih dahulu.
2. Ekstrak folder inventra-updated dari ZIP ini ke C:\xampp\htdocs\.
3. Jika Windows bertanya apakah file project akan diganti, pastikan backup sudah dibuat,
   lalu pilih Replace/Timpa untuk file aplikasi.
4. Jangan hapus atau buat ulang database inventra_db.
5. Pastikan konfigurasi config/database.php masih sesuai dengan MySQL lokal.
6. Jalankan Apache dan MySQL melalui XAMPP.
7. Buka http://localhost/inventra-updated/inventra/login.php lalu login dengan akun
   INVENTRA yang sudah dibuat sebelumnya.

PENTING TENTANG DATABASE
------------------------
- Paket UI ini tidak menyertakan salinan database MySQL.
- Database inventra_db yang sudah ada tidak perlu dihapus atau dibuat ulang.
- Jangan menjalankan database/upgrade-inventra.sql berulang kali. File tersebut
  merupakan upgrade satu kali untuk instalasi yang belum memiliki kolom/tabel tambahan.
- Sebelum mengubah struktur database, selalu buat backup melalui phpMyAdmin.

PEMERIKSAAN
-----------
Seluruh file PHP pada paket ini telah diperiksa dengan PHP CLI (php -l) dan
lolos pemeriksaan sintaks. Tampilan runtime tetap perlu dibuka di XAMPP lokal
untuk memastikan koneksi database dan data milik instalasi masing-masing.
