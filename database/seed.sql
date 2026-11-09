-- ============================================================
-- Data awal (seed)
-- Login: admin / admin123   |   petani / petani123
-- ============================================================

USE ternak_monitoring;

-- ------------------------------------------------------------
-- Pengguna
-- ------------------------------------------------------------
INSERT INTO users (id, nama, username, password, role) VALUES
  (1, 'Ariyan',      'admin',  '$2y$12$iEhdxOdpisRPUm/1YSQyKe9YO95UyXsMkIUtpSQexX/tglk/XYDHi', 'admin'),
  (2, 'Petani Mitra', 'petani', '$2y$12$uAr1h5qa9NhrMfQGZf9QXecxI50yRNfNcOX.AjzT/yjC6JkLHjS3e', 'petani');

-- ------------------------------------------------------------
-- Kandang (titik awal: Politeknik Negeri Batam)
-- ------------------------------------------------------------
INSERT INTO kandangs (id, pemilik_id, nama, lokasi, center_lat, center_lng, radius_m, buffer_m) VALUES
  (1, 2, 'Kandang Utama', 'Politeknik Negeri Batam, Batam Center', 1.1186000, 104.0486000, 40, 3),
  (2, 2, 'Kandang Timur', 'Area Hijau Batam Center',                1.1201000, 104.0512000, 30, 3);

-- ------------------------------------------------------------
-- Ternak
-- ------------------------------------------------------------
INSERT INTO animals (id, kandang_id, kode, nama, jenis, kelamin, umur_bulan) VALUES
  (1, 1, 'TRK-001', 'Si Belang', 'Sapi',  'Betina', 18),
  (2, 2, 'TRK-002', 'Si Manis',  'Kambing','Betina', 12);

-- ------------------------------------------------------------
-- Perangkat IoT (token contoh -- ganti saat produksi)
-- ------------------------------------------------------------
INSERT INTO devices (id, animal_id, kode, api_token, is_active) VALUES
  (1, 1, 'ESP32-TRK001', '750624536b620133bbc0f3af17a0eff59a20db98afc77a97fc18938ea3e14855', 1),
  (2, 2, 'ESP32-TRK002', '8882253635edbf92e2d16970446516340471ab590bf6e3500594ddc697294554', 1);
