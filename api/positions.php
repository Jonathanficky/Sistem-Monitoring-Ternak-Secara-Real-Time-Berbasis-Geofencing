<?php
/**
 * GET /api/positions.php
 * Data posisi terbaru untuk peta live (butuh sesi login).
 */

require_once __DIR__ . '/../config/app.php';

if (!current_user()) {
    json_response(['ok' => false, 'message' => 'Sesi berakhir. Silakan masuk kembali.'], 401);
}

try {
    $data = fetch_live_data($conn);
    json_response(['ok' => true] + $data);
} catch (Throwable $error) {
    json_response(['ok' => false, 'message' => 'Gagal memuat data: ' . $error->getMessage()], 500);
}
