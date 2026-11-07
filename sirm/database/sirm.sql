-- SIRM - Skema Database (MySQL 5.7+/MariaDB 10.3+)
CREATE DATABASE IF NOT EXISTS sirm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sirm;

CREATE TABLE IF NOT EXISTS poli (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL UNIQUE,
  tarif DECIMAL(12,2) NOT NULL DEFAULT 0,
  aktif TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  nama_lengkap VARCHAR(100) NOT NULL,
  role ENUM('admin','dokter','petugas') NOT NULL,
  poli_id INT NULL,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_poli FOREIGN KEY (poli_id) REFERENCES poli(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pasien (
  id INT AUTO_INCREMENT PRIMARY KEY,
  no_rm VARCHAR(20) NOT NULL UNIQUE,
  nik CHAR(16) NOT NULL UNIQUE,
  nama VARCHAR(100) NOT NULL,
  jenis_kelamin ENUM('L','P') NOT NULL,
  tanggal_lahir DATE NOT NULL,
  alamat TEXT NOT NULL,
  no_telp VARCHAR(20) NULL,
  golongan_darah ENUM('A','B','AB','O','-') NOT NULL DEFAULT '-',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pasien_nama (nama)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS kunjungan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pasien_id INT NOT NULL,
  poli_id INT NOT NULL,
  dokter_id INT NULL,
  tanggal DATE NOT NULL,
  no_antrean INT NOT NULL,
  status ENUM('menunggu','diperiksa','selesai','batal') NOT NULL DEFAULT 'menunggu',
  created_by INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_antrean (poli_id, tanggal, no_antrean),
  INDEX idx_kunjungan_tgl (tanggal, status),
  CONSTRAINT fk_kj_pasien FOREIGN KEY (pasien_id) REFERENCES pasien(id),
  CONSTRAINT fk_kj_poli   FOREIGN KEY (poli_id)   REFERENCES poli(id),
  CONSTRAINT fk_kj_dokter FOREIGN KEY (dokter_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_kj_by     FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rekam_medis (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kunjungan_id INT NOT NULL UNIQUE,
  dokter_id INT NOT NULL,
  keluhan TEXT NOT NULL,
  diagnosa TEXT NOT NULL,
  kode_icd10 VARCHAR(10) NULL,
  tindakan TEXT NULL,
  biaya_tindakan DECIMAL(12,2) NOT NULL DEFAULT 0,
  catatan TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_rm_kj     FOREIGN KEY (kunjungan_id) REFERENCES kunjungan(id),
  CONSTRAINT fk_rm_dokter FOREIGN KEY (dokter_id)    REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS obat (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode VARCHAR(20) NOT NULL UNIQUE,
  nama VARCHAR(120) NOT NULL,
  satuan VARCHAR(20) NOT NULL DEFAULT 'tablet',
  harga DECIMAL(12,2) NOT NULL DEFAULT 0,
  stok INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS resep (
  id INT AUTO_INCREMENT PRIMARY KEY,
  rekam_medis_id INT NOT NULL UNIQUE,
  status ENUM('baru','disiapkan','diserahkan') NOT NULL DEFAULT 'baru',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_resep_rm FOREIGN KEY (rekam_medis_id) REFERENCES rekam_medis(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS resep_detail (
  id INT AUTO_INCREMENT PRIMARY KEY,
  resep_id INT NOT NULL,
  obat_id INT NOT NULL,
  jumlah INT NOT NULL,
  aturan_pakai VARCHAR(100) NULL,
  harga_satuan DECIMAL(12,2) NOT NULL,
  CONSTRAINT fk_rd_resep FOREIGN KEY (resep_id) REFERENCES resep(id) ON DELETE CASCADE,
  CONSTRAINT fk_rd_obat  FOREIGN KEY (obat_id)  REFERENCES obat(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pembayaran (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kunjungan_id INT NOT NULL UNIQUE,
  no_invoice VARCHAR(30) NOT NULL UNIQUE,
  biaya_layanan DECIMAL(12,2) NOT NULL DEFAULT 0,
  biaya_tindakan DECIMAL(12,2) NOT NULL DEFAULT 0,
  biaya_obat DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  metode ENUM('tunai','transfer','qris','bpjs') NULL,
  status ENUM('belum','lunas') NOT NULL DEFAULT 'belum',
  dibayar_pada DATETIME NULL,
  kasir_id INT NULL,
  CONSTRAINT fk_bayar_kj    FOREIGN KEY (kunjungan_id) REFERENCES kunjungan(id),
  CONSTRAINT fk_bayar_kasir FOREIGN KEY (kasir_id)     REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Data awal
INSERT IGNORE INTO poli (nama, tarif) VALUES
 ('Umum',50000),('Gigi',75000),('Anak',70000),('Kebidanan',80000);

INSERT IGNORE INTO obat (kode, nama, satuan, harga, stok) VALUES
 ('OBT001','Paracetamol 500 mg','tablet',1500,500),
 ('OBT002','Amoxicillin 500 mg','kapsul',3000,300),
 ('OBT003','Ibuprofen 400 mg','tablet',2500,300),
 ('OBT004','Cetirizine 10 mg','tablet',2000,200),
 ('OBT005','Antasida Doen','tablet',1000,400),
 ('OBT006','Vitamin C 500 mg','tablet',1200,500);
-- User awal dibuat oleh install.php (agar password di-hash dengan password_hash)
