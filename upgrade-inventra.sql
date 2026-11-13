-- INVENTRA upgrade: jalankan sekali melalui phpMyAdmin pada database inventra_db
ALTER TABLE barang
  ADD COLUMN part_no VARCHAR(100) NULL AFTER kode_barang,
  ADD COLUMN kanban_barcode VARCHAR(120) NULL AFTER part_no;

UPDATE barang SET part_no = kode_barang WHERE part_no IS NULL OR part_no = '';
UPDATE barang SET kanban_barcode = CONCAT('INV-', LPAD(id, 8, '0'))
WHERE kanban_barcode IS NULL OR kanban_barcode = '';

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(60) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  nama_lengkap VARCHAR(120) NOT NULL,
  role ENUM('admin','operator') NOT NULL DEFAULT 'operator',
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS surat_jalan (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  nomor_surat VARCHAR(50) NOT NULL UNIQUE,
  jenis ENUM('MASUK','KELUAR') NOT NULL,
  tanggal DATE NOT NULL,
  pihak VARCHAR(160) NULL,
  keterangan TEXT NULL,
  user_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sj_jenis_tanggal (jenis, tanggal),
  CONSTRAINT fk_sj_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS surat_jalan_detail (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  surat_jalan_id BIGINT NOT NULL,
  barang_id INT NOT NULL,
  part_no VARCHAR(100) NOT NULL,
  kanban_barcode VARCHAR(120) NOT NULL,
  qty INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sjd_barang (barang_id),
  CONSTRAINT fk_sjd_sj FOREIGN KEY (surat_jalan_id) REFERENCES surat_jalan(id) ON DELETE CASCADE,
  CONSTRAINT fk_sjd_barang FOREIGN KEY (barang_id) REFERENCES barang(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Akun awal: admin / Admin123! (segera ganti password setelah login)

