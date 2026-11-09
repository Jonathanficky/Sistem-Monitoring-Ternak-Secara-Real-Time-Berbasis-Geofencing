<?php
/**
 * Uji unit sederhana (tanpa database) untuk mesin geofencing.
 * Jalankan: php tests/haversine_test.php
 */

require_once __DIR__ . '/../inc/functions.php';

$passed = 0;
$failed = 0;
$lines  = [];

function check(string $name, bool $condition, string $detail = ''): void
{
    global $passed, $failed, $lines;

    if ($condition) {
        $passed++;
        $lines[] = "  [PASS] {$name}" . ($detail !== '' ? " ({$detail})" : '');
    } else {
        $failed++;
        $lines[] = "  [FAIL] {$name}" . ($detail !== '' ? " ({$detail})" : '');
    }
}

// ------------------------------------------------------------
// Rumus Haversine
// ------------------------------------------------------------
$same = haversine(1.1186, 104.0486, 1.1186, 104.0486);
check('Jarak titik yang sama = 0 meter', $same < 0.001, round($same, 4) . ' m');

$north = haversine(1.1186, 104.0486, 1.1189590, 104.0486);
check('Geser 0,000359 lintang ≈ 40 meter', abs($north - 39.92) < 1.0, round($north, 2) . ' m');

$equator = haversine(0, 0, 0, 1);
check('1 derajat bujur di ekuator ≈ 111.195 km', abs($equator - 111194.93) < 5, round($equator, 2) . ' m');

$symmetric = haversine(1.1186, 104.0486, 1.1201, 104.0512);
$reverse   = haversine(1.1201, 104.0512, 1.1186, 104.0486);
check('Jarak bersifat simetris', abs($symmetric - $reverse) < 0.0001, round($symmetric, 2) . ' m');

// ------------------------------------------------------------
// Keputusan geofence + buffer
// ------------------------------------------------------------
check('Tepat di batas radius dianggap di dalam', geofence_inside(40.0, 40, 0));
check('Di dalam radius + buffer dianggap aman', geofence_inside(43.0, 40, 3));
check('Melewati radius + buffer dianggap keluar', !geofence_inside(43.01, 40, 3));
check('Buffer nol tetap memakai radius', !geofence_inside(40.5, 40, 0));
check('Buffer negatif diabaikan', geofence_inside(40.0, 40, -5));

// ------------------------------------------------------------
// Validasi koordinat
// ------------------------------------------------------------
check('Koordinat (0,0) ditolak', !valid_coordinates(0, 0));
check('Koordinat null ditolak', !valid_coordinates(null, 104.0486));
check('Latitude > 90 ditolak', !valid_coordinates(91, 104));
check('Longitude < -180 ditolak', !valid_coordinates(1, -181));
check('Koordinat Batam diterima', valid_coordinates(1.1186, 104.0486));
check('Koordinat string numerik diterima', valid_coordinates('1.1186', '104.0486'));

// ------------------------------------------------------------
// Normalisasi waktu
// ------------------------------------------------------------
$iso = normalize_datetime('2025-10-26T10:30:00');
check('ISO 8601 dinormalisasi', $iso === '2025-10-26 10:30:00', $iso);

$nullTime = normalize_datetime(null);
check('Waktu kosong diganti waktu sekarang', strlen($nullTime) === 19, $nullTime);

$future = date('Y-m-d H:i:s', time() + 86400);
$clamped = normalize_datetime($future);
check('Waktu terlalu jauh ke depan di-clamp', strtotime($clamped) <= time() + 5, $clamped);

$invalid = normalize_datetime('bukan-tanggal');
check('Format waktu tidak valid ditangani', strlen($invalid) === 19, $invalid);

// ------------------------------------------------------------
// Format tampilan
// ------------------------------------------------------------
check('Format jarak meter memakai koma', format_jarak(42.7) === '42,7 m', format_jarak(42.7));
check('Format jarak kilometer', format_jarak(1500) === '1,50 km', format_jarak(1500));
check('Format jarak null menjadi strip', format_jarak(null) === '-');

// ------------------------------------------------------------
// Output
// ------------------------------------------------------------
echo "==================================================\n";
echo " Uji unit — Sistem Monitoring Ternak (Geofencing)\n";
echo "==================================================\n";
echo implode("\n", $lines) . "\n\n";
echo "Hasil: {$passed} lulus, {$failed} gagal dari " . ($passed + $failed) . " pengujian.\n";

exit($failed === 0 ? 0 : 1);
