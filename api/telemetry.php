<?php
/**
 * POST /api/telemetry.php
 * Endpoint penerima data GPS dari ESP32 (atau perangkat lain) via HTTP POST.
 *
 * Header : Authorization: Bearer <api_token>
 * Body   : {"latitude":1.1186,"longitude":104.0486,"accuracy":5,"recorded_at":"2026-10-26 10:00:00"}
 */

require_once __DIR__ . '/../config/app.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    json_response(['ok' => false, 'message' => 'Metode tidak diizinkan. Gunakan POST.'], 405);
}

header('Content-Type: application/json; charset=utf-8');

// ------------------------------------------------------------
// Ambil token perangkat
// ------------------------------------------------------------
$authorization = $_SERVER['HTTP_AUTHORIZATION']
    ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
    ?? '';

$token = '';

if (preg_match('/Bearer\s+(.+)/i', $authorization, $match)) {
    $token = trim($match[1]);
} elseif (!empty($_SERVER['HTTP_X_API_TOKEN'])) {
    $token = trim($_SERVER['HTTP_X_API_TOKEN']);
}

// ------------------------------------------------------------
// Baca payload (JSON atau form-urlencoded)
// ------------------------------------------------------------
$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!is_array($input)) {
    if (trim($raw) !== '') {
        json_response([
            'ok'      => false,
            'message' => 'Payload bukan JSON yang valid.',
        ], 400);
    }
    $input = $_POST;
}

if ($token === '') {
    $token = trim((string) ($input['api_token'] ?? $_GET['token'] ?? ''));
}

if ($token === '') {
    json_response([
        'ok'      => false,
        'message' => 'Token perangkat tidak ditemukan. Gunakan header Authorization: Bearer <token>.',
    ], 401);
}

$device = fetch_device_by_token($conn, $token);

if (!$device) {
    json_response([
        'ok'      => false,
        'message' => 'Token perangkat tidak valid atau perangkat sedang nonaktif.',
    ], 401);
}

if (empty($device['animal_id'])) {
    json_response([
        'ok'      => false,
        'message' => 'Perangkat belum dihubungkan ke ternak mana pun.',
    ], 422);
}

// ------------------------------------------------------------
// Validasi koordinat
// ------------------------------------------------------------
$latitude  = $input['latitude'] ?? $input['lat'] ?? null;
$longitude = $input['longitude'] ?? $input['lng'] ?? $input['lon'] ?? null;
$accuracy  = $input['accuracy'] ?? $input['hdop'] ?? null;
$recorded  = $input['recorded_at'] ?? $input['timestamp'] ?? null;

if (!valid_coordinates($latitude, $longitude)) {
    json_response([
        'ok'      => false,
        'code'    => 'GPS_SIGNAL_LOST',
        'message' => 'GPS Signal Lost: koordinat kosong, tidak valid, atau masih (0,0).',
    ], 422);
}

try {
    $result = record_telemetry(
        $conn,
        (int) $device['id'],
        $latitude,
        $longitude,
        is_numeric($accuracy) ? (float) $accuracy : null,
        is_string($recorded) ? $recorded : null
    );

    json_response($result, 200);
} catch (RuntimeException $error) {
    json_response([
        'ok'      => false,
        'message' => $error->getMessage(),
    ], 422);
} catch (Throwable $error) {
    json_response([
        'ok'      => false,
        'message' => 'Terjadi kesalahan server: ' . $error->getMessage(),
    ], 500);
}
