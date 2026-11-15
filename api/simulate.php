<?php
/**
 * POST /api/simulate.php
 * Mengirim titik GPS simulasi dari halaman Simulator (butuh sesi login).
 */

require_once __DIR__ . '/../config/app.php';

if (!current_user()) {
    json_response(['ok' => false, 'message' => 'Sesi berakhir. Silakan masuk kembali.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Metode tidak diizinkan.'], 405);
}

if (!csrf_valid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    json_response(['ok' => false, 'message' => 'CSRF token tidak valid. Muat ulang halaman.'], 419);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    json_response(['ok' => false, 'message' => 'Payload JSON tidak valid.'], 400);
}

$deviceId  = (int) ($input['device_id'] ?? 0);
$latitude  = $input['latitude'] ?? null;
$longitude = $input['longitude'] ?? null;
$accuracy  = $input['accuracy'] ?? 8;

if ($deviceId <= 0) {
    json_response(['ok' => false, 'message' => 'Pilih perangkat terlebih dahulu.'], 422);
}

$scope = scope_kandang('k');
$stmt  = $conn->prepare(
    "SELECT d.id AS device_id, d.kode
     FROM devices d
     INNER JOIN animals a ON a.id = d.animal_id
     INNER JOIN kandangs k ON k.id = a.kandang_id
     WHERE d.id = ? AND d.is_active = 1 AND $scope
     LIMIT 1"
);
$stmt->bind_param('i', $deviceId);
$stmt->execute();
$device = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$device) {
    json_response(['ok' => false, 'message' => 'Perangkat tidak ditemukan atau bukan milik akun Anda.'], 404);
}

try {
    $result = record_telemetry(
        $conn,
        $deviceId,
        $latitude,
        $longitude,
        is_numeric($accuracy) ? (float) $accuracy : null,
        null
    );

    $result['simulated'] = true;
    json_response($result, 200);
} catch (RuntimeException $error) {
    json_response(['ok' => false, 'message' => $error->getMessage()], 422);
} catch (Throwable $error) {
    json_response(['ok' => false, 'message' => 'Kesalahan server: ' . $error->getMessage()], 500);
}
