-- ===================================================
-- DATABASE APLIKASI PEMINJAMAN ALAT (SiPinjam)
-- File: database.sql
-- Nama Database: db_peminjaman_alat
-- ===================================================

CREATE DATABASE IF NOT EXISTS `db_peminjaman_alat` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `db_peminjaman_alat`;

-- ---------------------------------------------------
-- 1. TABEL USERS (Pengguna)
-- ---------------------------------------------------
DROP TABLE IF EXISTS `pengembalian`;
DROP TABLE IF EXISTS `detail_peminjaman`;
DROP TABLE IF EXISTS `peminjaman`;
DROP TABLE IF EXISTS `alat`;
DROP TABLE IF EXISTS `kategori`;
DROP TABLE IF EXISTS `log_aktivitas`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id_user` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `nama_lengkap` VARCHAR(100) NOT NULL,
  `role` ENUM('admin', 'petugas', 'peminjam') NOT NULL DEFAULT 'peminjam',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- 2. TABEL KATEGORI (Kategori Alat)
-- ---------------------------------------------------
CREATE TABLE `kategori` (
  `id_kategori` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_kategori` VARCHAR(100) NOT NULL,
  `keterangan` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- 3. TABEL ALAT (Inventaris Alat)
-- ---------------------------------------------------
CREATE TABLE `alat` (
  `id_alat` INT AUTO_INCREMENT PRIMARY KEY,
  `id_kategori` INT NOT NULL,
  `nama_alat` VARCHAR(100) NOT NULL,
  `merek` VARCHAR(50) DEFAULT NULL,
  `stok` INT NOT NULL DEFAULT 0,
  `kondisi` ENUM('Baik', 'Rusak Ringan', 'Rusak Berat') DEFAULT 'Baik',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_kategori`) REFERENCES `kategori`(`id_kategori`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- 4. TABEL PEMINJAMAN (Transaksi Peminjaman)
-- ---------------------------------------------------
CREATE TABLE `peminjaman` (
  `id_peminjaman` INT AUTO_INCREMENT PRIMARY KEY,
  `kode_peminjaman` VARCHAR(25) NOT NULL UNIQUE,
  `id_user` INT NOT NULL,
  `tgl_pinjam` DATE NOT NULL,
  `tgl_kembali_rencana` DATE NOT NULL,
  `status` ENUM('Pending', 'Disetujui', 'Ditolak', 'Dikembalikan') NOT NULL DEFAULT 'Pending',
  `catatan` TEXT NULL,
  `id_petugas` INT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_user`) REFERENCES `users`(`id_user`) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (`id_petugas`) REFERENCES `users`(`id_user`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- 5. TABEL DETAIL_PEMINJAMAN (Detail Barang Pinjam)
-- ---------------------------------------------------
CREATE TABLE `detail_peminjaman` (
  `id_detail` INT AUTO_INCREMENT PRIMARY KEY,
  `id_peminjaman` INT NOT NULL,
  `id_alat` INT NOT NULL,
  `jumlah` INT NOT NULL DEFAULT 1,
  FOREIGN KEY (`id_peminjaman`) REFERENCES `peminjaman`(`id_peminjaman`) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (`id_alat`) REFERENCES `alat`(`id_alat`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- 6. TABEL PENGEMBALIAN (Pengembalian & Denda)
-- ---------------------------------------------------
CREATE TABLE `pengembalian` (
  `id_pengembalian` INT AUTO_INCREMENT PRIMARY KEY,
  `id_peminjaman` INT NOT NULL UNIQUE,
  `tgl_pengembalian_aktual` DATE NOT NULL,
  `keterangan_kondisi` ENUM('Baik', 'Rusak', 'Hilang') DEFAULT 'Baik',
  `terlambat_hari` INT DEFAULT 0,
  `denda` DECIMAL(10,2) DEFAULT 0.00,
  `id_petugas` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_peminjaman`) REFERENCES `peminjaman`(`id_peminjaman`) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (`id_petugas`) REFERENCES `users`(`id_user`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- 7. TABEL LOG_AKTIVITAS (Audit Trail)
-- ---------------------------------------------------
CREATE TABLE `log_aktivitas` (
  `id_log` INT AUTO_INCREMENT PRIMARY KEY,
  `id_user` INT DEFAULT NULL,
  `nama_user` VARCHAR(100) DEFAULT NULL,
  `role` VARCHAR(20) DEFAULT NULL,
  `aktivitas` VARCHAR(255) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ===================================================
-- 8. FUNCTION: HITUNG DENDA KETERLAMBATAN
-- ===================================================
DELIMITER $$
DROP FUNCTION IF EXISTS `fn_hitung_denda`$$
CREATE FUNCTION `fn_hitung_denda`(
  p_tgl_rencana DATE,
  p_tgl_aktual DATE,
  p_tarip_denda DECIMAL(10,2)
) RETURNS DECIMAL(10,2)
DETERMINISTIC
BEGIN
  DECLARE v_terlambat INT;
  DECLARE v_total_denda DECIMAL(10,2);
  
  IF p_tgl_aktual > p_tgl_rencana THEN
    SET v_terlambat = DATEDIFF(p_tgl_aktual, p_tgl_rencana);
    SET v_total_denda = v_terlambat * p_tarip_denda;
  ELSE
    SET v_total_denda = 0.00;
  END IF;
  
  RETURN v_total_denda;
END$$
DELIMITER ;


-- ===================================================
-- 9. STORED PROCEDURE: SETUJUI PEMINJAMAN (COMMIT/ROLLBACK)
-- ===================================================
DELIMITER $$
DROP PROCEDURE IF EXISTS `sp_setujui_peminjaman`$$
CREATE PROCEDURE `sp_setujui_peminjaman`(
  IN p_id_peminjaman INT,
  IN p_id_petugas INT,
  OUT p_status_code INT,
  OUT p_message VARCHAR(255)
)
proc_label: BEGIN
  DECLARE v_id_alat INT;
  DECLARE v_jumlah INT;
  DECLARE v_stok INT;
  DECLARE v_nama_alat VARCHAR(100);
  DECLARE v_done INT DEFAULT FALSE;
  DECLARE v_nama_petugas VARCHAR(100);
  
  DECLARE cur_detail CURSOR FOR 
    SELECT dp.id_alat, dp.jumlah, a.stok, a.nama_alat
    FROM detail_peminjaman dp
    JOIN alat a ON dp.id_alat = a.id_alat
    WHERE dp.id_peminjaman = p_id_peminjaman;
    
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = TRUE;
  
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    SET p_status_code = 500;
    SET p_message = 'Terjadi galat SQL. Transaksi dibatalkan (ROLLBACK).';
  END;

  START TRANSACTION;
  
  SELECT nama_lengkap INTO v_nama_petugas FROM users WHERE id_user = p_id_petugas;
  
  OPEN cur_detail;
  check_loop: LOOP
    FETCH cur_detail INTO v_id_alat, v_jumlah, v_stok, v_nama_alat;
    IF v_done THEN
      LEAVE check_loop;
    END IF;
    
    IF v_stok < v_jumlah THEN
      CLOSE cur_detail;
      ROLLBACK;
      SET p_status_code = 400;
      SET p_message = CONCAT('Gagal! Stok untuk "', v_nama_alat, '" tidak mencukupi. (Tersedia: ', v_stok, ', Diminta: ', v_jumlah, ')');
      LEAVE proc_label;
    END IF;
  END LOOP check_loop;
  CLOSE cur_detail;
  
  UPDATE peminjaman 
  SET status = 'Disetujui', id_petugas = p_id_petugas 
  WHERE id_peminjaman = p_id_peminjaman;
  
  SET v_done = FALSE;
  OPEN cur_detail;
  update_loop: LOOP
    FETCH cur_detail INTO v_id_alat, v_jumlah, v_stok, v_nama_alat;
    IF v_done THEN
      LEAVE update_loop;
    END IF;
    
    UPDATE alat SET stok = stok - v_jumlah WHERE id_alat = v_id_alat;
  END LOOP update_loop;
  CLOSE cur_detail;
  
  INSERT INTO log_aktivitas (id_user, nama_user, role, aktivitas)
  VALUES (p_id_petugas, v_nama_petugas, 'petugas', CONCAT('Menyetujui pengajuan TRX #', p_id_peminjaman));
  
  COMMIT;
SET p_status_code = 200;
    SET p_message = 'Persetujuan berhasil dan stok alat diperbarui.';
END$$
DELIMITER ;


-- ===================================================
-- 10. STORED PROCEDURE: PROSES PENGEMBALIAN (COMMIT/ROLLBACK)
-- ===================================================
DELIMITER $$
DROP PROCEDURE IF EXISTS `sp_proses_pengembalian`$$
CREATE PROCEDURE `sp_proses_pengembalian`(
  IN p_id_peminjaman INT,
  IN p_tgl_kembali_aktual DATE,
  IN p_kondisi VARCHAR(20),
  IN p_id_petugas INT,
  OUT p_status_code INT,
  OUT p_message VARCHAR(255)
)
proc_label: BEGIN
  DECLARE v_tgl_rencana DATE;
  DECLARE v_terlambat INT DEFAULT 0;
  DECLARE v_denda DECIMAL(10,2) DEFAULT 0.00;
  DECLARE v_id_alat INT;
  DECLARE v_jumlah INT;
  DECLARE v_done INT DEFAULT FALSE;
  DECLARE v_nama_petugas VARCHAR(100);
  
  DECLARE cur_detail CURSOR FOR 
    SELECT id_alat, jumlah FROM detail_peminjaman WHERE id_peminjaman = p_id_peminjaman;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = TRUE;
  
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    SET p_status_code = 500;
    SET p_message = 'Terjadi galat SQL saat pengembalian. Transaksi dibatalkan (ROLLBACK).';
  END;

  START TRANSACTION;
  
  SELECT tgl_kembali_rencana INTO v_tgl_rencana FROM peminjaman WHERE id_peminjaman = p_id_peminjaman;
  SELECT nama_lengkap INTO v_nama_petugas FROM users WHERE id_user = p_id_petugas;
  
  IF p_tgl_kembali_aktual > v_tgl_rencana THEN
    SET v_terlambat = DATEDIFF(p_tgl_kembali_aktual, v_tgl_rencana);
    SET v_denda = fn_hitung_denda(v_tgl_rencana, p_tgl_kembali_aktual, 5000.00);
  END IF;
  
  INSERT INTO pengembalian (id_peminjaman, tgl_pengembalian_aktual, keterangan_kondisi, terlambat_hari, denda, id_petugas)
  VALUES (p_id_peminjaman, p_tgl_kembali_aktual, p_kondisi, v_terlambat, v_denda, p_id_petugas);
  
  UPDATE peminjaman SET status = 'Dikembalikan' WHERE id_peminjaman = p_id_peminjaman;
  
  OPEN cur_detail;
  restore_loop: LOOP
    FETCH cur_detail INTO v_id_alat, v_jumlah;
    IF v_done THEN
      LEAVE restore_loop;
    END IF;
    
    UPDATE alat SET stok = stok + v_jumlah WHERE id_alat = v_id_alat;
  END LOOP restore_loop;
  CLOSE cur_detail;
  
  INSERT INTO log_aktivitas (id_user, nama_user, role, aktivitas)
  VALUES (p_id_petugas, v_nama_petugas, 'petugas', CONCAT('Memproses pengembalian TRX #', p_id_peminjaman, ' (Telat: ', v_terlambat, ' hari, Denda: Rp ', v_denda, ')'));
  
  COMMIT;
SET p_status_code = 200;
    SET p_message = 'Pengembalian diproses. Stok alat dikembalikan dan denda dicatat.';
END$$
DELIMITER ;


-- ===================================================
-- 11. TRIGGER: LOG OTOMATIS SAAT ALAT BARU DITAMBAH
-- ===================================================
DELIMITER $$
DROP TRIGGER IF EXISTS `trg_after_alat_insert`$$
CREATE TRIGGER `trg_after_alat_insert`
AFTER INSERT ON `alat`
FOR EACH ROW
BEGIN
  INSERT INTO log_aktivitas (id_user, nama_user, role, aktivitas)
  VALUES (NULL, 'System', 'admin', CONCAT('Alat baru ditambahkan: ', NEW.nama_alat, ' (Stok: ', NEW.stok, ')'));
END$$
DELIMITER ;


-- ===================================================
-- 12. DATA AWAL (SEED DATA)
-- Password default 'admin123' (hash bcrypt valid):
--   admin    : admin123
--   petugas  : admin123
--   peminjam : admin123
-- ===================================================

INSERT INTO `users` (`id_user`, `username`, `password`, `nama_lengkap`, `role`) VALUES
(1, 'admin', '$2y$10$5r4pEl0kJSEDqDJ32kaH7ODM1paegu6uSyZolyHwNkwgPzbx1P976', 'Administrator Utama', 'admin'),
(2, 'petugas', '$2y$10$ZMUrN.qyPfzwKkx1SAkaRuwjhjDgHpDcPvLBaYH8Q6a70L/wvMBE2', 'Petugas Lab', 'petugas'),
(3, 'peminjam', '$2y$10$dNTi/UJqNWTjfakPDnqlXuvhUEodSJgJbAAkRth8km/DvVvIKxE9G', 'Siswa Peminjam', 'peminjam');

-- Kategori Alat
INSERT INTO `kategori` (`id_kategori`, `nama_kategori`, `keterangan`) VALUES
(1, 'Elektronik & Multimedia', 'Proyektor, kamera, dan perangkat audio-video'),
(2, 'Mekanik & Perkakas', 'Solder, tang crimping, dan peralatan tangan'),
(3, 'Peralatan Jaringan', 'LAN tester, crimper, router, dan switch');

-- Contoh Data Alat
INSERT INTO `alat` (`id_alat`, `id_kategori`, `nama_alat`, `merek`, `stok`, `kondisi`) VALUES
(1, 1, 'LCD Proyektor HDMI', 'Epson EB-X400', 5, 'Baik'),
(2, 1, 'Kamera DSLR EOS 1300D', 'Canon', 3, 'Baik'),
(3, 2, 'Solder Listrik 40W', 'Dekko', 10, 'Baik'),
(4, 2, 'Tang Crimping RJ45', 'Tekiro', 8, 'Baik'),
(5, 3, 'LAN Tester Digital', 'Schneider', 6, 'Baik'),
(6, 3, 'Routerboard Mikrotik', 'RB951Ui-2HnD', 4, 'Baik');

-- Log Awal
INSERT INTO `log_aktivitas` (`id_user`, `nama_user`, `role`, `aktivitas`) VALUES
(1, 'Administrator Utama', 'admin', 'Inisialisasi database aplikasi peminjaman alat');
