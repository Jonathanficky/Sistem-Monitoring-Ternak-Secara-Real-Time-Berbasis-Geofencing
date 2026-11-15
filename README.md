# TernakTrack — Sistem Monitoring Ternak Real-Time Berbasis Geofencing

Aplikasi web untuk memantau posisi ternak secara real-time menggunakan GPS (ESP32 + NEO-6M),
dengan **geofence sirkular** dan peringatan otomatis ketika ternak keluar dari radius aman kandang.

Proyek PBL — Politeknik Negeri Batam, Program Studi Teknik Informatika.

---

## Fitur Utama

| Modul | Keterangan |
|---|---|
| **Dashboard** | KPI ternak, peta sebaran, peringatan terbaru, status perangkat |
| **Peta Live** | Marker ternak real-time, circle geofence, auto-refresh 5 detik |
| **Data Ternak** | CRUD ternak + foto, status geofence, jarak dari pusat |
| **Kandang** | CRUD kandang, titik pusat dipilih lewat klik peta, radius & buffer |
| **Perangkat IoT** | Token API per perangkat, status online/offline, regenerate token |
| **Peringatan** | Riwayat breach alert, filter, tandai selesai |
| **Riwayat Posisi** | Jejak jalur (polyline), filter tanggal, export CSV |
| **Simulator GPS** | Uji alur telemetry tanpa hardware, termasuk simulasi "jalan" |
| **Pengaturan** | Interval polling, radius default, buffer, batas offline |
| **Pengguna** | Role admin & petani (data kandang dibatasi sesuai pemilik) |

---

## Teknologi

- **PHP 8.2+** (native, mysqli, tanpa framework)
- **MySQL 8** — database `ternak_monitoring`
- **Leaflet.js 1.9 + OpenStreetMap** — peta interaktif, tanpa API key
- **HTML5 + CSS3 + JavaScript** (vanilla)
- **Font Awesome** & **Plus Jakarta Sans**
- **ESP32 + GPS NEO-6M** — firmware Arduino (HTTP POST JSON)

---

## Struktur Folder

```
sistem monitoring ternak/
├─ index.php              # redirect ke dashboard/login
├─ login.php / logout.php
├─ dashboard.php
├─ peta.php               # peta live
├─ ternak.php             # CRUD ternak
├─ kandang.php            # CRUD kandang + pemilih peta
├─ perangkat.php          # perangkat & token
├─ peringatan.php         # riwayat alert
├─ riwayat.php            # tracking + export CSV
├─ simulator.php          # simulator GPS
├─ pengaturan.php         # pengaturan sistem (admin)
├─ pengguna.php           # manajemen pengguna (admin)
├─ config/
│  ├─ app.php             # bootstrap, BASE_URL, session
│  └─ db.php              # koneksi mysqli
├─ inc/
│  ├─ functions.php       # helper + Haversine + mesin geofence
│  ├─ auth.php            # login, logout, guard
│  ├─ header.php          # layout sidebar/topbar
│  └─ footer.php
├─ actions/               # handler POST (CRUD)
├─ api/
│  ├─ telemetry.php       # endpoint ESP32 (Bearer token)
│  ├─ positions.php       # data peta live (session)
│  ├─ track.php           # riwayat jalur (session)
│  └─ simulate.php        # kirim titik simulasi (session + CSRF)
├─ assets/css/style.css
├─ assets/js/app.js
├─ assets/js/map.js
├─ database/skema.sql
├─ database/seed.sql
├─ firmware/ternak_gps_esp32.ino
├─ tests/haversine_test.php
└─ uploads/ternak/        # foto ternak
```

---

## Instalasi (Laragon)

### 1. Letakkan project

Salin folder ini ke `C:\laragon\www\` (sudah sesuai).

### 2. Jalankan MySQL

Buka Laragon → **Start All** (pastikan MySQL berjalan di port 3306).

### 3. Import database

Lewat **phpMyAdmin** (`http://localhost/phpmyadmin`) atau terminal:

```bash
# Windows (PowerShell)
Get-Content -Raw database/skema.sql | C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root
Get-Content -Raw database/seed.sql  | C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root
```

Atau import kedua file melalui tab **Import** di phpMyAdmin.

### 4. Sesuaikan koneksi

Edit `config/db.php` bila user/password MySQL berbeda:

```php
$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'ternak_monitoring';
```

### 5. Buka aplikasi

- Virtual host Laragon: `http://sistem-monitoring-ternak.test`
- Atau: `http://localhost/sistem%20monitoring%20ternak/`

### 6. Login

| Role | Username | Password |
|---|---|---|
| Admin | `admin` | `admin123` |
| Petani | `petani` | `petani123` |

> Ganti password default sebelum presentasi.

---

## Alur Kerja Sistem

```
ESP32 + GPS NEO-6M
        │  HTTP POST JSON (Bearer token)
        ▼
api/telemetry.php
        │  validasi koordinat
        ▼
record_telemetry()
        │  Haversine(lat, lng, center_lat, center_lng)
        ▼
jarak <= radius + buffer ?
        │
        ├── status sama  → simpan lokasi & update ternak (tanpa alert)
        └── status berubah → simpan lokasi + buat alert (keluar / masuk)
        ▼
Dashboard & Peta Live (polling 5 detik) → marker + toast peringatan
```

### Rumus Haversine

```php
$earthRadius = 6371000; // meter
$dLat = deg2rad($lat2 - $lat1);
$dLng = deg2rad($lng2 - $lng1);

$a = sin($dLat / 2) ** 2
   + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

$jarak = 2 * $earthRadius * asin(min(1.0, sqrt($a)));
```

**Aturan geofence:**

1. Ternak dianggap **di dalam** jika `jarak <= radius + buffer`.
2. `buffer` (default 3 m) mencegah *false alarm* akibat drift GPS.
3. Alert hanya dibuat saat **status berubah**, bukan setiap kiriman data.
4. Saat ternak kembali masuk radius, alert `keluar` yang masih terbuka otomatis
   ditandai selesai dan sistem mencatat alert `masuk` sebagai riwayat.
5. Kiriman pertama hanya menyimpan status (tidak memicu alert).
6. Koordinat `(0,0)` atau kosong ditolak dengan pesan `GPS Signal Lost`.

---

## API Telemetry

### `POST /api/telemetry.php`

**Header**

```
Authorization: Bearer <api_token>
Content-Type: application/json
```

**Body**

```json
{
  "latitude": 1.1186,
  "longitude": 104.0486,
  "accuracy": 5,
  "recorded_at": "2026-10-26 10:30:00"
}
```

`recorded_at` opsional (default waktu server).

**Respons sukses (200)**

```json
{
  "ok": true,
  "duplicate": false,
  "device": "ESP32-TRK001",
  "animal_id": 1,
  "animal": "Si Belang",
  "animal_kode": "TRK-001",
  "kandang": "Kandang Utama",
  "latitude": 1.1186,
  "longitude": 104.0486,
  "inside": false,
  "distance_m": 42.7,
  "radius_m": 40,
  "buffer_m": 3,
  "limit_m": 43,
  "alert": "keluar",
  "recorded_at": "2026-10-26 10:30:00",
  "message": "Ternak keluar dari radius aman!"
}
```

**Respons gagal**

| Status | Arti |
|---|---|
| `401` | Token tidak ada / tidak valid / perangkat nonaktif |
| `405` | Metode bukan POST |
| `422` | Koordinat tidak valid (`GPS_SIGNAL_LOST`) atau perangkat belum terhubung ke ternak |
| `500` | Kesalahan server / database |

**Contoh uji dengan curl**

```bash
curl -X POST "http://localhost/sistem%20monitoring%20ternak/api/telemetry.php" \
  -H "Authorization: Bearer 750624536b620133bbc0f3af17a0eff59a20db98afc77a97fc18938ea3e14855" \
  -H "Content-Type: application/json" \
  -d "{\"latitude\":1.1186,\"longitude\":104.0486,\"accuracy\":5}"
```

Token contoh tersebut terhubung ke **Si Belang / TRK-001**, kandang pusat
`1.1186000, 104.0486000`, radius **40 m**.

---

## Simulator GPS

Buka menu **Simulator GPS** (login sebagai admin atau petani).

1. Pilih perangkat/ternak.
2. Klik peta atau isi latitude/longitude manual.
3. Gunakan tombol preset:
   - **Tepat di pusat** → status dalam radius.
   - **Tepi radius** → mendekati batas.
   - **Di luar radius** → memicu alert `keluar`.
4. Klik **Kirim Titik Ini** untuk satu titik.
5. Klik **Simulasi Jalan (15 titik)** untuk mengirim rangkaian titik acak di sekitar
   batas radius (memunculkan beberapa alert).

Perubahan posisi langsung terlihat di **Peta Live** dan **Dashboard**, karena keduanya
melakukan polling setiap 5 detik.

---

## Firmware ESP32

File: `firmware/ternak_gps_esp32.ino`

### Konfigurasi wajib

```cpp
const char* WIFI_SSID    = "NamaWiFi";
const char* WIFI_PASS    = "PasswordWiFi";
const char* SERVER_URL   = "http://192.168.1.10/sistem monitoring ternak/api/telemetry.php";
const char* DEVICE_TOKEN = "<token dari menu Perangkat IoT>";
```

> Gunakan **IP LAN** komputer (cek dengan `ipconfig`), bukan `localhost`,
> karena ESP32 mengakses server dari jaringan.

### Wiring GPS NEO-6M

| Modul GPS | ESP32 |
|---|---|
| VCC | 3V3 (atau 5V bila modul punya regulator) |
| GND | GND |
| TX | GPIO16 (RX2) |
| RX | GPIO17 (TX2) |

### Library Arduino

- `TinyGPSPlus` (mikrocontroller.net)
- Board package **ESP32 by Espressif**

### Indikator LED (GPIO 2)

| Pola | Arti |
|---|---|
| Kedip cepat | Gagal koneksi Wi-Fi |
| Nyala | Wi-Fi terhubung, menunggu fix GPS |
| Kedip 2x | Data berhasil dikirim |
| Padam | GPS belum fix / gagal kirim |

### Perakitan (MVP)

- ESP32 + GPS NEO-6M + powerbank mini melalui USB.
- Casing: food container plastik kedap air.
- Diikatkan ke tubuh ternak menggunakan strap/harness.

---

## Pengujian

### Uji unit geofencing (tanpa database)

```bash
php tests/haversine_test.php
```

Menguji Haversine, keputusan radius + buffer, validasi koordinat, normalisasi waktu,
dan format tampilan.

### Uji end-to-end manual

1. Login admin → buka **Simulator GPS**.
2. Kirim titik **di luar radius**.
3. Buka **Peta Live** → marker berubah merah, muncul toast peringatan.
4. Buka **Peringatan** → ada entri `keluar`.
5. Buka **Riwayat Posisi** → jalur dan tabel terisi, coba **Export CSV**.
6. Kirim titik kembali ke dalam radius → muncul alert `masuk`.

---

## Troubleshooting

| Masalah | Penyebab & solusi |
|---|---|
| "Koneksi database gagal" | MySQL belum jalan atau database belum diimpor. Jalankan `database/skema.sql` lalu `seed.sql`. |
| Peta kosong / abu-abu | CDN Leaflet/OpenStreetMap terblokir. Cek koneksi internet. |
| Ikon tidak muncul | Font Awesome CDN gagal dimuat. |
| ESP32 `-1` / timeout | `SERVER_URL` masih `localhost`, IP salah, firewall Windows memblokir, atau perangkat beda jaringan. |
| ESP32 `401` | Token salah, perangkat nonaktif, atau token sudah diregenerate. |
| `GPS Signal Lost` | Modul belum mendapat fix satelit. Uji di area terbuka, tunggu 1–3 menit. |
| Alert tidak muncul | Status belum berubah (sistem hanya memberi alert saat transisi). Pastikan titik benar-benar melewati `radius + buffer`. |
| Jam tidak sesuai | Zona waktu diatur `Asia/Jakarta` pada `config/app.php`. |

---

## Keamanan & Catatan Pengembangan

Sudah diterapkan:

- Prepared statement (anti SQL injection) di seluruh query.
- Escaping output (`e()`) untuk mencegah XSS.
- CSRF token pada semua form dan endpoint simulator.
- Password di-hash `password_hash()` (bcrypt).
- Token perangkat unik + dapat dinonaktifkan/diregenerasi.
- Pembatasan data berdasarkan pemilik kandang (role petani).
- Folder `config/`, `inc/`, `database/` diblokir dari akses langsung via `.htaccess`.
- Upload foto divalidasi MIME dan ukuran, eksekusi PHP di folder upload diblokir.

Untuk pengembangan lanjutan:

- Gunakan HTTPS saat deploy (token saat ini dikirim apa adanya).
- Simpan hash token perangkat, bukan token mentah.
- Tambahkan rate limiting pada endpoint telemetry.
- Arsipkan `locations` lama secara berkala agar tabel tetap ringan.
- Ganti polling dengan WebSocket/SSE bila jumlah perangkat bertambah banyak.

---

## Lisensi

Dibuat untuk keperluan akademik PBL Politeknik Negeri Batam.
