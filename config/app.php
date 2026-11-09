<?php
/**
 * Konfigurasi utama aplikasi.
 * Semua halaman cukup memanggil: require_once __DIR__ . '/config/app.php';
 */

date_default_timezone_set('Asia/Jakarta');

define('APP_NAME', 'TernakTrack');
define('APP_DESCRIPTION', 'Sistem Monitoring Ternak Real-Time Berbasis Geofencing');
define('APP_VERSION', '1.0.0');

// ------------------------------------------------------------
// Deteksi BASE_URL otomatis (mendukung vhost maupun subfolder)
// ------------------------------------------------------------
$__root    = str_replace('\\', '/', dirname(__DIR__));
$__docRoot = isset($_SERVER['DOCUMENT_ROOT'])
    ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT'])
    : '';

$__base = '/';
if ($__docRoot !== '' && str_starts_with($__root, rtrim($__docRoot, '/'))) {
    $__rel = trim(substr($__root, strlen(rtrim($__docRoot, '/'))), '/');
    $__base = $__rel === '' ? '/' : '/' . $__rel . '/';
}

define('ROOT_PATH', $__root);
define('BASE_URL', $__base);

// ------------------------------------------------------------
// Session
// ------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}

// ------------------------------------------------------------
// Error reporting (matikan saat produksi)
// ------------------------------------------------------------
error_reporting(E_ALL);
ini_set('display_errors', '1');

// ------------------------------------------------------------
// Dependensi
// ------------------------------------------------------------
require_once __DIR__ . '/db.php';
require_once ROOT_PATH . '/inc/functions.php';
require_once ROOT_PATH . '/inc/auth.php';
