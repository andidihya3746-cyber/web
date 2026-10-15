-- ============================================================
-- Skema database: Sistem Prediksi Keberhasilan Siswa
-- Import lewat phpMyAdmin / HeidiSQL (Run SQL file) atau: mysql -u root < database.sql
-- ============================================================
CREATE DATABASE IF NOT EXISTS prediksi_siswa_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE prediksi_siswa_db;

CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama          VARCHAR(100) NOT NULL,
  username      VARCHAR(50)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('admin','dosen') NOT NULL DEFAULT 'dosen',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS siswa (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_siswa   VARCHAR(30)  NOT NULL,      -- berisi NIS (VARCHAR agar angka nol di depan tidak hilang)
  nama       VARCHAR(100) NULL,
  kelas      VARCHAR(20)  NULL,          -- contoh: Macca 1, Mattinulu 8, Mallempu 7
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_siswa_id_siswa (id_siswa)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS prediksi (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  siswa_id          INT UNSIGNED NULL,
  id_siswa          VARCHAR(30)  NULL,     -- berisi NIS
  kelas             VARCHAR(20)  NULL,
  rata_rata_nilai   DECIMAL(6,2) NOT NULL,
  nilai_tugas       DECIMAL(6,2) NOT NULL,
  nilai_uts         DECIMAL(6,2) NOT NULL,
  nilai_uas         DECIMAL(6,2) NOT NULL,
  kehadiran_persen  DECIMAL(6,2) NOT NULL,
  cluster           TINYINT UNSIGNED NOT NULL,
  kategori          ENUM('Berhasil','Sedang','Berisiko') NOT NULL,
  jarak_ke_centroid DECIMAL(8,4) NOT NULL,
  sumber            ENUM('tunggal','massal') NOT NULL DEFAULT 'tunggal',
  batch_id          VARCHAR(16)  NULL,
  user_id           INT UNSIGNED NULL,
  created_at        DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_prediksi_id_siswa (id_siswa),
  KEY idx_prediksi_kategori (kategori),
  KEY idx_prediksi_kelas (kelas),
  KEY idx_prediksi_created (created_at),
  CONSTRAINT fk_prediksi_siswa FOREIGN KEY (siswa_id) REFERENCES siswa (id) ON DELETE SET NULL,
  CONSTRAINT fk_prediksi_user  FOREIGN KEY (user_id)  REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Untuk database yang SUDAH ada: perlebar kolom kelas (nama kelas lebih panjang dari 10 karakter).
-- Aman dijalankan berulang kali dan tidak menghapus data.
ALTER TABLE siswa    MODIFY kelas VARCHAR(20) NULL;
ALTER TABLE prediksi MODIFY kelas VARCHAR(20) NULL;

-- Akun contoh (GANTI kata sandinya setelah login pertama)
--   admin / admin123      dosen / dosen123  (role "dosen" = Guru/Staf Akademik)
INSERT INTO users (nama, username, password_hash, role) VALUES
  ('Administrator', 'admin', '$2y$10$k7It.02adcNrjKWm1aEe6eK08vLhcOopknKvhvJCO8.vn7DsVfHQO', 'admin'),
  ('Guru Contoh',   'dosen', '$2y$10$Y2zOsbIrVBQ75DMI3Cgv0OLaq7eX8hDoAQOsVQhX7Ucw1Bd3SaukC', 'dosen')
ON DUPLICATE KEY UPDATE nama = VALUES(nama);