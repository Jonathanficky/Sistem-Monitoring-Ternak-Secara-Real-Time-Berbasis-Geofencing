<?php
/**
 * GET /api/track.php?animal_id=1&from=2026-10-26&to=2026-10-26
 * Riwayat jalur ternak untuk peta (butuh sesi login).
 */

require_once __DIR__ . '/../config/app.php';

if (!current_user()) {
    json_response(['ok' => false, 'message' => 'Sesi berakhir. Silakan masuk kembali.'], 401);
}

$animalId = (int) ($_GET['animal_id'] ?? 0);
$from     = trim((string) ($_GET['from'] ?? ''));
$to       = trim((string) ($_GET['to'] ?? ''));

$animal = $animalId > 0 ? fetch_animal($conn, $animalId) : null;

if (!$animal) {
    json_response(['ok' => false, 'message' => 'Ternak tidak ditemukan.'], 404);
}

$fromDate = DateTime::createFromFormat('Y-m-d', $from) ?: new DateTime('today');
$toDate   = DateTime::createFromFormat('Y-m-d', $to) ?: new DateTime('today');

$start = $fromDate->format('Y-m-d') . ' 00:00:00';
$end   = $toDate->format('Y-m-d') . ' 23:59:59';

$stmt = $conn->prepare(
    'SELECT latitude, longitude, accuracy_m, distance_m, is_inside, recorded_at
     FROM locations
     WHERE animal_id = ? AND recorded_at BETWEEN ? AND ?
     ORDER BY recorded_at ASC, id ASC
     LIMIT 3000'
);
$stmt->bind_param('iss', $animalId, $start, $end);
$stmt->execute();
$result = $stmt->get_result();

$points = [];
while ($row = $result->fetch_assoc()) {
    $points[] = [
        'lat'         => (float) $row['latitude'],
        'lng'         => (float) $row['longitude'],
        'accuracy'    => $row['accuracy_m'] === null ? null : (float) $row['accuracy_m'],
        'distance_m'  => $row['distance_m'] === null ? null : (float) $row['distance_m'],
        'is_inside'   => (int) $row['is_inside'],
        'recorded_at' => $row['recorded_at'],
    ];
}
$stmt->close();

json_response([
    'ok'     => true,
    'animal' => [
        'id'           => (int) $animal['id'],
        'kode'         => $animal['kode'],
        'nama'         => $animal['nama'],
        'jenis'        => $animal['jenis'],
        'kandang_nama' => $animal['kandang_nama'],
        'radius'       => (int) $animal['radius_m'],
        'buffer'       => (int) $animal['buffer_m'],
    ],
    'kandang' => [
        'lat'    => (float) $animal['center_lat'],
        'lng'    => (float) $animal['center_lng'],
        'radius' => (int) $animal['radius_m'],
        'buffer' => (int) $animal['buffer_m'],
    ],
    'range'  => ['from' => $start, 'to' => $end],
    'total'  => count($points),
    'points' => $points,
]);
