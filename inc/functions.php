<?php
/**
 * Kumpulan helper aplikasi.
 * Tidak boleh menghasilkan output saat di-include.
 */

// ------------------------------------------------------------
// Util dasar
// ------------------------------------------------------------
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function redirect(string $path): never
{
    $target = preg_match('~^https?://~i', $path) ? $path : url($path);
    header('Location: ' . $target);
    exit;
}

function back_or(string $fallback = 'dashboard.php'): string
{
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if ($referer === '') {
        return url($fallback);
    }

    $refHost = parse_url($referer, PHP_URL_HOST);
    $curHost = $_SERVER['HTTP_HOST'] ?? '';
    if ($refHost && $curHost && strcasecmp($refHost, explode(':', $curHost)[0]) !== 0) {
        return url($fallback);
    }

    return $referer;
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ------------------------------------------------------------
// Flash message
// ------------------------------------------------------------
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_messages(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

// ------------------------------------------------------------
// CSRF
// ------------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_valid(?string $token): bool
{
    return is_string($token) && $token !== '' && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function verify_csrf(): void
{
    if (!csrf_valid($_POST['csrf_token'] ?? null)) {
        flash('danger', 'Sesi formulir kedaluwarsa. Silakan ulangi.');
        redirect(back_or('dashboard.php'));
    }
}

// ------------------------------------------------------------
// Pengguna & hak akses
// ------------------------------------------------------------
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

/**
 * Filter SQL untuk membatasi data kandang sesuai pemilik.
 */
function scope_kandang(string $alias = 'k'): string
{
    $user = current_user();
    if (!$user || $user['role'] === 'admin') {
        return '1=1';
    }
    return $alias . '.pemilik_id = ' . (int) $user['id'];
}

// ------------------------------------------------------------
// Format tanggal & angka
// ------------------------------------------------------------
function bulan_indonesia(): array
{
    return [
        1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des',
    ];
}

function format_tanggal(?string $datetime, bool $dengan_jam = true): string
{
    if (!$datetime) {
        return '-';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '-';
    }
    $bulan = bulan_indonesia();
    $teks  = date('j', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
    return $dengan_jam ? $teks . ' ' . date('H:i', $ts) : $teks;
}

function format_jam(?string $datetime): string
{
    if (!$datetime) {
        return '-';
    }
    $ts = strtotime($datetime);
    return $ts === false ? '-' : date('H:i:s', $ts);
}

function time_ago(?string $datetime): string
{
    if (!$datetime) {
        return 'belum ada data';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return 'belum ada data';
    }

    $diff = time() - $ts;
    if ($diff < 0) {
        return 'baru saja';
    }
    if ($diff < 60) {
        return 'baru saja';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . ' menit lalu';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . ' jam lalu';
    }
    if ($diff < 2592000) {
        return floor($diff / 86400) . ' hari lalu';
    }
    return format_tanggal($datetime, false);
}

function format_jarak(?float $meter): string
{
    if ($meter === null) {
        return '-';
    }
    if ($meter < 1000) {
        return number_format($meter, 1, ',', '.') . ' m';
    }
    return number_format($meter / 1000, 2, ',', '.') . ' km';
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $ini   = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $ini .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    return $ini !== '' ? $ini : '?';
}

// ------------------------------------------------------------
// Geofencing
// ------------------------------------------------------------
/**
 * Jarak dua koordinat dalam meter (rumus Haversine).
 */
function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $earthRadius = 6371000.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);

    $a = sin($dLat / 2) ** 2
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

    return 2 * $earthRadius * asin(min(1.0, sqrt($a)));
}

/**
 * Validasi koordinat GPS. (0,0) dianggap tidak valid (GPS belum lock).
 */
function valid_coordinates(mixed $lat, mixed $lng): bool
{
    if (!is_numeric($lat) || !is_numeric($lng)) {
        return false;
    }

    $lat = (float) $lat;
    $lng = (float) $lng;

    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        return false;
    }

    return !(abs($lat) < 0.0000001 && abs($lng) < 0.0000001);
}

/**
 * Apakah posisi masih dianggap di dalam area aman.
 * Buffer dipakai sebagai toleransi drift GPS (default 3 meter).
 */
function geofence_inside(float $distance, int $radius, int $buffer): bool
{
    return $distance <= ($radius + max(0, $buffer));
}

function normalize_datetime(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return date('Y-m-d H:i:s');
    }

    if (is_numeric($value) && strlen((string) $value) >= 10) {
        $value = '@' . $value;
    }

    $ts = strtotime($value);
    if ($ts === false) {
        return date('Y-m-d H:i:s');
    }

    // Abaikan jam perangkat yang melompat terlalu jauh ke depan.
    if ($ts > time() + 300) {
        return date('Y-m-d H:i:s');
    }

    return date('Y-m-d H:i:s', $ts);
}

// ------------------------------------------------------------
// Pengaturan aplikasi
// ------------------------------------------------------------
function get_setting(mysqli $conn, string $nama, ?string $default = null): string
{
    static $cache = null;

    if ($cache === null) {
        $cache = [];
        $result = $conn->query('SELECT nama, nilai FROM settings');
        while ($row = $result->fetch_assoc()) {
            $cache[$row['nama']] = $row['nilai'];
        }
    }

    return $cache[$nama] ?? ($default ?? '');
}

function set_setting(mysqli $conn, string $nama, string $nilai): void
{
    $stmt = $conn->prepare(
        'INSERT INTO settings (nama, nilai) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)'
    );
    $stmt->bind_param('ss', $nama, $nilai);
    $stmt->execute();
    $stmt->close();
}

// ------------------------------------------------------------
// Status perangkat
// ------------------------------------------------------------
function is_online(?string $lastSeen, int $threshold = 90): bool
{
    if (!$lastSeen) {
        return false;
    }
    $ts = strtotime($lastSeen);
    return $ts !== false && (time() - $ts) <= $threshold;
}

// ------------------------------------------------------------
// Query bantu
// ------------------------------------------------------------
function fetch_kandang(mysqli $conn, int $id, bool $batasi_pemilik = true): ?array
{
    $scope = $batasi_pemilik ? scope_kandang('k') : '1=1';
    $stmt  = $conn->prepare(
        "SELECT k.*, u.nama AS pemilik_nama
         FROM kandangs k
         LEFT JOIN users u ON u.id = k.pemilik_id
         WHERE k.id = ? AND $scope
         LIMIT 1"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function fetch_animal(mysqli $conn, int $id, bool $batasi_pemilik = true): ?array
{
    $scope = $batasi_pemilik ? scope_kandang('k') : '1=1';
    $stmt  = $conn->prepare(
        "SELECT a.*, k.nama AS kandang_nama, k.center_lat, k.center_lng,
                k.radius_m, k.buffer_m, k.pemilik_id,
                d.kode AS device_kode, d.last_seen_at AS device_last_seen
         FROM animals a
         INNER JOIN kandangs k ON k.id = a.kandang_id
         LEFT JOIN devices d ON d.id = (
             SELECT d2.id FROM devices d2
             WHERE d2.animal_id = a.id AND d2.is_active = 1
             ORDER BY d2.id ASC LIMIT 1
         )
         WHERE a.id = ? AND $scope
         LIMIT 1"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function fetch_device_by_token(mysqli $conn, string $token): ?array
{
    $stmt = $conn->prepare(
        'SELECT d.*, a.nama AS animal_nama
         FROM devices d
         LEFT JOIN animals a ON a.id = d.animal_id
         WHERE d.api_token = ? AND d.is_active = 1
         LIMIT 1'
    );
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function fetch_live_data(mysqli $conn): array
{
    $scope = scope_kandang('k');

    $kandangs = [];
    $result   = $conn->query(
        "SELECT k.id, k.nama, k.lokasi, k.center_lat, k.center_lng,
                k.radius_m, k.buffer_m, k.pemilik_id, u.nama AS pemilik_nama,
                (SELECT COUNT(*) FROM animals a WHERE a.kandang_id = k.id) AS jumlah_ternak
         FROM kandangs k
         LEFT JOIN users u ON u.id = k.pemilik_id
         WHERE $scope
         ORDER BY k.nama ASC"
    );
    while ($row = $result->fetch_assoc()) {
        $kandangs[] = [
            'id'         => (int) $row['id'],
            'nama'       => $row['nama'],
            'lokasi'     => $row['lokasi'],
            'lat'        => (float) $row['center_lat'],
            'lng'        => (float) $row['center_lng'],
            'radius'     => (int) $row['radius_m'],
            'buffer'     => (int) $row['buffer_m'],
            'jumlah'     => (int) $row['jumlah_ternak'],
            'pemilik'    => $row['pemilik_nama'],
        ];
    }

    $animals = [];
    $result  = $conn->query(
        "SELECT a.id, a.kode, a.nama, a.jenis, a.kelamin, a.is_inside,
                a.last_distance_m, a.last_seen_at, a.kandang_id,
                k.nama AS kandang_nama, k.radius_m, k.buffer_m,
                d.kode AS device_kode, d.last_seen_at AS device_last_seen,
                l.latitude, l.longitude, l.accuracy_m, l.recorded_at
         FROM animals a
         INNER JOIN kandangs k ON k.id = a.kandang_id
         LEFT JOIN devices d ON d.id = (
             SELECT d2.id FROM devices d2
             WHERE d2.animal_id = a.id AND d2.is_active = 1
             ORDER BY d2.id ASC LIMIT 1
         )
         LEFT JOIN locations l ON l.id = (
             SELECT l2.id FROM locations l2
             WHERE l2.animal_id = a.id
             ORDER BY l2.recorded_at DESC, l2.id DESC
             LIMIT 1
         )
         WHERE $scope
         ORDER BY a.nama ASC"
    );

    while ($row = $result->fetch_assoc()) {
        $animals[] = [
            'id'            => (int) $row['id'],
            'kode'          => $row['kode'],
            'nama'          => $row['nama'],
            'jenis'         => $row['jenis'],
            'kelamin'       => $row['kelamin'],
            'kandang_id'    => (int) $row['kandang_id'],
            'kandang_nama'  => $row['kandang_nama'],
            'radius'        => (int) $row['radius_m'],
            'buffer'        => (int) $row['buffer_m'],
            'is_inside'     => $row['is_inside'] === null ? null : (int) $row['is_inside'],
            'distance_m'    => $row['last_distance_m'] === null ? null : (float) $row['last_distance_m'],
            'last_seen_at'  => $row['last_seen_at'],
            'device_kode'   => $row['device_kode'],
            'device_online' => is_online($row['device_last_seen']),
            'lat'           => $row['latitude'] === null ? null : (float) $row['latitude'],
            'lng'           => $row['longitude'] === null ? null : (float) $row['longitude'],
            'accuracy'      => $row['accuracy_m'] === null ? null : (float) $row['accuracy_m'],
            'recorded_at'   => $row['recorded_at'],
        ];
    }

    $perHalaman = [];
    foreach ($animals as $animal) {
        $status = $animal['is_inside'] === null ? 'unknown' : ($animal['is_inside'] ? 'inside' : 'outside');
        $perHalaman[$status] = ($perHalaman[$status] ?? 0) + 1;
    }

    $alertCount = (int) $conn->query(
        "SELECT COUNT(*) AS c
         FROM alerts al
         INNER JOIN animals a ON a.id = al.animal_id
         INNER JOIN kandangs k ON k.id = a.kandang_id
         WHERE al.is_resolved = 0 AND $scope"
    )->fetch_assoc()['c'];

    $recentAlerts = [];
    $result = $conn->query(
        "SELECT al.id, al.tipe, al.distance_m, al.radius_m, al.created_at, al.is_resolved,
                a.id AS animal_id, a.nama AS animal_nama, a.kode AS animal_kode,
                k.nama AS kandang_nama
         FROM alerts al
         INNER JOIN animals a ON a.id = al.animal_id
         INNER JOIN kandangs k ON k.id = a.kandang_id
         WHERE al.is_resolved = 0 AND $scope
         ORDER BY al.created_at DESC
         LIMIT 5"
    );
    while ($row = $result->fetch_assoc()) {
        $recentAlerts[] = $row;
    }

    $online = 0;
    foreach ($animals as $animal) {
        if ($animal['device_online']) {
            $online++;
        }
    }

    return [
        'kandangs'      => $kandangs,
        'animals'       => $animals,
        'stats'         => [
            'total'   => count($animals),
            'inside'  => $perHalaman['inside'] ?? 0,
            'outside' => $perHalaman['outside'] ?? 0,
            'unknown' => $perHalaman['unknown'] ?? 0,
            'online'  => $online,
            'alerts'  => $alertCount,
        ],
        'alerts'        => $recentAlerts,
        'server_time'   => date('Y-m-d H:i:s'),
    ];
}

// ------------------------------------------------------------
// Mesin telemetry + geofencing
// ------------------------------------------------------------
/**
 * Menyimpan satu titik GPS, menghitung status geofence, dan membuat alert
 * hanya ketika status berubah (mencegah spam false alarm).
 *
 * @throws RuntimeException
 */
function record_telemetry(
    mysqli $conn,
    int $deviceId,
    mixed $latitude,
    mixed $longitude,
    mixed $accuracy = null,
    ?string $recordedAt = null
): array {
    if (!valid_coordinates($latitude, $longitude)) {
        throw new RuntimeException('GPS Signal Lost: koordinat tidak valid atau masih (0,0).');
    }

    $lat = (float) $latitude;
    $lng = (float) $longitude;
    $acc = is_numeric($accuracy) ? max(0.0, (float) $accuracy) : null;

    $stmt = $conn->prepare(
        "SELECT d.id AS device_id, d.kode AS device_kode, d.animal_id,
                a.kode AS animal_kode, a.nama AS animal_nama, a.is_inside, a.kandang_id,
                k.nama AS kandang_nama, k.center_lat, k.center_lng, k.radius_m, k.buffer_m
         FROM devices d
         INNER JOIN animals a ON a.id = d.animal_id
         INNER JOIN kandangs k ON k.id = a.kandang_id
         WHERE d.id = ? AND d.is_active = 1
         LIMIT 1"
    );
    $stmt->bind_param('i', $deviceId);
    $stmt->execute();
    $device = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$device) {
        throw new RuntimeException('Perangkat tidak aktif atau belum dihubungkan ke ternak.');
    }

    $radius   = (int) $device['radius_m'];
    $buffer   = (int) $device['buffer_m'];
    $distance = haversine($lat, $lng, (float) $device['center_lat'], (float) $device['center_lng']);
    $inside   = geofence_inside($distance, $radius, $buffer);
    $previous = $device['is_inside'] === null ? null : (int) $device['is_inside'];
    $alertType = null;

    if ($previous !== null && $previous !== ($inside ? 1 : 0)) {
        $alertType = $inside ? 'masuk' : 'keluar';
    }

    $recorded = normalize_datetime($recordedAt);
    $distance = round($distance, 2);

    // Cegah baris duplikat bila ESP32 mengirim ulang paket yang sama.
    $stmt = $conn->prepare(
        'SELECT latitude, longitude, recorded_at FROM locations
         WHERE device_id = ? ORDER BY id DESC LIMIT 1'
    );
    $stmt->bind_param('i', $device['device_id']);
    $stmt->execute();
    $last = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $duplicate = $last
        && abs((float) $last['latitude'] - $lat) < 0.0000001
        && abs((float) $last['longitude'] - $lng) < 0.0000001
        && $last['recorded_at'] === $recorded;

    $conn->begin_transaction();

    try {
        if (!$duplicate) {
            $stmt = $conn->prepare(
                'INSERT INTO locations
                    (animal_id, device_id, latitude, longitude, accuracy_m, distance_m, is_inside, recorded_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $isInsideInt = $inside ? 1 : 0;
            $stmt->bind_param(
                'iiddddis',
                $device['animal_id'],
                $device['device_id'],
                $lat,
                $lng,
                $acc,
                $distance,
                $isInsideInt,
                $recorded
            );
            $stmt->execute();
            $stmt->close();
        }

        $stmt = $conn->prepare(
            'UPDATE animals
             SET is_inside = ?, last_distance_m = ?, last_seen_at = ?
             WHERE id = ?'
        );
        $isInsideInt = $inside ? 1 : 0;
        $stmt->bind_param('idsi', $isInsideInt, $distance, $recorded, $device['animal_id']);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare('UPDATE devices SET last_seen_at = NOW() WHERE id = ?');
        $stmt->bind_param('i', $device['device_id']);
        $stmt->execute();
        $stmt->close();

        if ($alertType !== null) {
            // Saat ternak kembali, selesaikan otomatis alert "keluar" yang masih terbuka.
            if ($alertType === 'masuk') {
                $stmt = $conn->prepare(
                    "UPDATE alerts
                     SET is_resolved = 1, is_read = 1, resolved_at = NOW()
                     WHERE animal_id = ? AND tipe = 'keluar' AND is_resolved = 0"
                );
                $stmt->bind_param('i', $device['animal_id']);
                $stmt->execute();
                $stmt->close();
            }

            $resolvedAt = $alertType === 'masuk' ? date('Y-m-d H:i:s') : null;
            $resolvedFlag = $alertType === 'masuk' ? 1 : 0;

            $stmt = $conn->prepare(
                'INSERT INTO alerts (animal_id, kandang_id, tipe, distance_m, radius_m, is_resolved, resolved_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param(
                'iisdiis',
                $device['animal_id'],
                $device['kandang_id'],
                $alertType,
                $distance,
                $radius,
                $resolvedFlag,
                $resolvedAt
            );
            $stmt->execute();
            $stmt->close();
        }

        $conn->commit();
    } catch (Throwable $ex) {
        $conn->rollback();
        throw $ex;
    }

    return [
        'ok'          => true,
        'duplicate'   => $duplicate,
        'device'      => $device['device_kode'],
        'animal_id'   => (int) $device['animal_id'],
        'animal'      => $device['animal_nama'],
        'animal_kode' => $device['animal_kode'],
        'kandang'     => $device['kandang_nama'],
        'latitude'    => $lat,
        'longitude'   => $lng,
        'inside'      => $inside,
        'distance_m'  => $distance,
        'radius_m'    => $radius,
        'buffer_m'    => $buffer,
        'limit_m'     => $radius + $buffer,
        'alert'       => $alertType,
        'recorded_at' => $recorded,
        'message'     => $inside
            ? 'Ternak berada di dalam radius aman.'
            : 'Ternak keluar dari radius aman!',
    ];
}

// ------------------------------------------------------------
// Upload foto ternak
// ------------------------------------------------------------
function upload_foto_ternak(array $file, ?string $fotoLama = null): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload foto gagal (kode ' . $file['error'] . ').');
    }

    if (($file['size'] ?? 0) > 3 * 1024 * 1024) {
        throw new RuntimeException('Ukuran foto maksimal 3 MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    $map   = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($map[$mime])) {
        throw new RuntimeException('Format foto harus JPG, PNG, atau WEBP.');
    }

    $dir = ROOT_PATH . '/uploads/ternak';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Folder uploads/ternak tidak dapat dibuat.');
    }

    $namaFile = 'ternak-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $map[$mime];
    $tujuan   = $dir . '/' . $namaFile;

    if (!move_uploaded_file($file['tmp_name'], $tujuan)) {
        throw new RuntimeException('Gagal menyimpan file foto.');
    }

    if ($fotoLama) {
        $lama = ROOT_PATH . '/' . ltrim($fotoLama, '/');
        if (is_file($lama)) {
            @unlink($lama);
        }
    }

    return 'uploads/ternak/' . $namaFile;
}

function hapus_foto_ternak(?string $foto): void
{
    if (!$foto) {
        return;
    }
    $path = ROOT_PATH . '/' . ltrim($foto, '/');
    if (is_file($path)) {
        @unlink($path);
    }
}

// ------------------------------------------------------------
// Ikon ayam (logo & penanda ternak)
// ------------------------------------------------------------
/**
 * SVG ayam inline. Memakai currentColor agar warna mengikuti konteks
 * (putih di logo hijau, hijau di latar terang, putih di marker peta).
 */
function ikon_ayam(string $class = ''): string
{
    $classes = 'ikon-ayam' . ($class !== '' ? ' ' . $class : '');

    return '<svg class="' . e($classes) . '" viewBox="0 0 64 64" aria-hidden="true" focusable="false">'
        . '<g fill="currentColor">'
        . '<path d="M17.5 42.5C7 40.5 2.5 30 6 19.5c1.2 7.5 6.4 11.8 13.6 13.8z"/>'
        . '<ellipse cx="31" cy="40" rx="20" ry="15"/>'
        . '<circle cx="46" cy="24" r="11"/>'
        . '<path d="M37.2 17.3c-1-6.2 4.2-9 6.1-4.7 1-5.2 6.2-5.3 7.2-1.1 4.1-1.9 7.1 2.4 4.1 6.4z"/>'
        . '<polygon points="55.8,20.8 63.6,24.8 55.8,28.9"/>'
        . '<ellipse cx="52.2" cy="33.4" rx="3.4" ry="5.2"/>'
        . '</g>'
        . '<g fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round">'
        . '<path d="M25.5 53.4v8.4M36.5 53.4v8.4"/>'
        . '<path d="M20.5 61.8h10M31.5 61.8h10"/>'
        . '</g>'
        . '</svg>';
}

// ------------------------------------------------------------
// Paginasi sederhana
// ------------------------------------------------------------
function paginate(int $total, int $perPage, int $currentPage): array
{
    $perPage = max(1, $perPage);
    $pages   = max(1, (int) ceil($total / $perPage));
    $page    = min(max(1, $currentPage), $pages);

    return [
        'total'    => $total,
        'per_page' => $perPage,
        'page'     => $page,
        'pages'    => $pages,
        'offset'   => ($page - 1) * $perPage,
    ];
}
