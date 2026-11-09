-- ============================================================
-- Sistem Monitoring Ternak Real-Time Berbasis Geofencing
-- Skema Database (MySQL 8)
-- ============================================================

CREATE DATABASE IF NOT EXISTS ternak_monitoring
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE ternak_monitoring;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS alerts;
DROP TABLE IF EXISTS locations;
DROP TABLE IF EXISTS devices;
DROP TABLE IF EXISTS animals;
DROP TABLE IF EXISTS kandangs;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- Pengguna (admin / petani)
-- ------------------------------------------------------------
CREATE TABLE users (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  nama       VARCHAR(100) NOT NULL,
  username   VARCHAR(50)  NOT NULL UNIQUE,
  password   VARCHAR(255) NOT NULL,
  role       ENUM('admin','petani') NOT NULL DEFAULT 'petani',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Kandang + titik pusat & radius geofence
-- ------------------------------------------------------------
CREATE TABLE kandangs (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  pemilik_id INT NULL,
  nama       VARCHAR(100) NOT NULL,
  lokasi     VARCHAR(150) NULL,
  center_lat DECIMAL(10,7) NOT NULL,
  center_lng DECIMAL(10,7) NOT NULL,
  radius_m   INT NOT NULL DEFAULT 40,
  buffer_m   INT NOT NULL DEFAULT 3,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_kandang_pemilik FOREIGN KEY (pemilik_id)
    REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Ternak
-- ------------------------------------------------------------
CREATE TABLE animals (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  kandang_id      INT NOT NULL,
  kode            VARCHAR(30) NOT NULL UNIQUE,
  nama            VARCHAR(80) NOT NULL,
  jenis           VARCHAR(50) NOT NULL DEFAULT 'Sapi',
  kelamin         ENUM('Jantan','Betina') NOT NULL DEFAULT 'Betina',
  umur_bulan      INT NULL,
  foto            VARCHAR(255) NULL,
  is_inside       TINYINT(1) NULL DEFAULT NULL,
  last_distance_m DECIMAL(10,2) NULL,
  last_seen_at    DATETIME NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_animal_kandang FOREIGN KEY (kandang_id)
    REFERENCES kandangs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Perangkat IoT (ESP32 + GPS)
-- ------------------------------------------------------------
CREATE TABLE devices (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  animal_id    INT NULL,
  kode         VARCHAR(50) NOT NULL UNIQUE,
  api_token    CHAR(64) NOT NULL UNIQUE,
  is_active    TINYINT(1) NOT NULL DEFAULT 1,
  last_seen_at DATETIME NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_device_animal FOREIGN KEY (animal_id)
    REFERENCES animals(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Riwayat titik koordinat
-- ------------------------------------------------------------
CREATE TABLE locations (
  id          BIGINT AUTO_INCREMENT PRIMARY KEY,
  animal_id   INT NOT NULL,
  device_id   INT NULL,
  latitude    DECIMAL(10,7) NOT NULL,
  longitude   DECIMAL(10,7) NOT NULL,
  accuracy_m  DECIMAL(8,2) NULL,
  distance_m  DECIMAL(10,2) NULL,
  is_inside   TINYINT(1) NOT NULL,
  recorded_at DATETIME NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_lokasi_ternak_waktu (animal_id, recorded_at),
  CONSTRAINT fk_lokasi_ternak FOREIGN KEY (animal_id)
    REFERENCES animals(id) ON DELETE CASCADE,
  CONSTRAINT fk_lokasi_device FOREIGN KEY (device_id)
    REFERENCES devices(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Peringatan (breach alert)
-- ------------------------------------------------------------
CREATE TABLE alerts (
  id          BIGINT AUTO_INCREMENT PRIMARY KEY,
  animal_id   INT NOT NULL,
  kandang_id  INT NULL,
  tipe        ENUM('keluar','masuk') NOT NULL,
  distance_m  DECIMAL(10,2) NULL,
  radius_m    INT NULL,
  is_read     TINYINT(1) NOT NULL DEFAULT 0,
  is_resolved TINYINT(1) NOT NULL DEFAULT 0,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  INDEX idx_alert_waktu (created_at),
  CONSTRAINT fk_alert_ternak FOREIGN KEY (animal_id)
    REFERENCES animals(id) ON DELETE CASCADE,
  CONSTRAINT fk_alert_kandang FOREIGN KEY (kandang_id)
    REFERENCES kandangs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Pengaturan aplikasi
-- ------------------------------------------------------------
CREATE TABLE settings (
  nama  VARCHAR(50) PRIMARY KEY,
  nilai VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (nama, nilai) VALUES
  ('nama_aplikasi',   'TernakTrack'),
  ('poll_interval',   '5000'),
  ('default_radius',  '40'),
  ('default_buffer',  '3'),
  ('offline_after',   '90');
