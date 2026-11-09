<?php
/**
 * Koneksi database MySQL (mysqli).
 * Default Laragon: host 127.0.0.1, user root, password kosong.
 */

$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$DB_NAME = getenv('DB_NAME') ?: 'ternak_monitoring';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    $isApi = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/');

    if ($isApi) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'      => false,
            'message' => 'Koneksi database gagal: ' . $e->getMessage(),
        ]);
        exit;
    }

    http_response_code(500);
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Koneksi Database Gagal</title>'
        . '<style>body{font-family:system-ui,Segoe UI,Arial,sans-serif;background:#f4f6f4;'
        . 'display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:24px}'
        . '.box{max-width:620px;background:#fff;border-radius:16px;padding:32px;'
        . 'box-shadow:0 20px 45px rgba(15,46,29,.12);border-top:5px solid #dc2626}'
        . 'h1{margin:0 0 8px;color:#0f172a;font-size:22px}'
        . 'p{color:#475569;line-height:1.6;margin:0 0 14px}'
        . 'code{background:#f1f5f9;padding:3px 7px;border-radius:6px;font-size:13px;color:#b91c1c}'
        . 'ol{color:#475569;line-height:1.9;font-size:14px;padding-left:20px;margin:0}'
        . '</style></head><body><div class="box">'
        . '<h1>Koneksi database gagal</h1>'
        . '<p><code>' . htmlspecialchars($e->getMessage()) . '</code></p>'
        . '<p>Pastikan MySQL Laragon sudah berjalan dan database sudah diimpor:</p>'
        . '<ol>'
        . '<li>Buka Laragon &rarr; <strong>Database</strong> atau jalankan MySQL.</li>'
        . '<li>Impor <code>database/skema.sql</code> lalu <code>database/seed.sql</code>.</li>'
        . '<li>Periksa host/user/password pada <code>config/db.php</code>.</li>'
        . '</ol></div></body></html>';
    exit;
}
