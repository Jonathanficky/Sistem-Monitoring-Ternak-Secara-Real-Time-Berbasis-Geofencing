<?php
require_once __DIR__ . '/config/app.php';
require_login();

$scope = scope_kandang('k');

$animals = [];
$result = $conn->query(
    "SELECT a.id, a.kode, a.nama, k.nama AS kandang_nama
     FROM animals a
     INNER JOIN kandangs k ON k.id = a.kandang_id
     WHERE $scope
     ORDER BY a.nama"
);
while ($row = $result->fetch_assoc()) {
    $animals[] = $row;
}

$animalId = (int) ($_GET['animal_id'] ?? 0);
if ($animalId <= 0 && $animals) {
    $animalId = (int) $animals[0]['id'];
}

$animal = $animalId > 0 ? fetch_animal($conn, $animalId) : null;

$tanggal = trim((string) ($_GET['tanggal'] ?? ''));
$from    = trim((string) ($_GET['from'] ?? ($tanggal !== '' ? $tanggal : date('Y-m-d'))));
$to      = trim((string) ($_GET['to'] ?? ($tanggal !== '' ? $tanggal : date('Y-m-d'))));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = date('Y-m-d');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = $from;
}
if ($to < $from) {
    [$from, $to] = [$to, $from];
}

$start = $from . ' 00:00:00';
$end   = $to . ' 23:59:59';

$points = [];
if ($animal) {
    $stmt = $conn->prepare(
        'SELECT latitude, longitude, accuracy_m, distance_m, is_inside, recorded_at
         FROM locations
         WHERE animal_id = ? AND recorded_at BETWEEN ? AND ?
         ORDER BY recorded_at ASC, id ASC
         LIMIT 5000'
    );
    $stmt->bind_param('iss', $animalId, $start, $end);
    $stmt->execute();
    $points = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// ------------------------------------------------------------
// Export CSV
// ------------------------------------------------------------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    if (!$animal) {
        flash('danger', 'Pilih ternak terlebih dahulu.');
        redirect('riwayat.php');
    }

    $filename = 'riwayat-' . preg_replace('/[^A-Za-z0-9\-_]/', '', $animal['kode'])
        . '-' . $from . '_' . $to . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Waktu', 'Latitude', 'Longitude', 'Akurasi (m)', 'Jarak dari pusat (m)', 'Status']);

    foreach ($points as $point) {
        fputcsv($out, [
            $point['recorded_at'],
            $point['latitude'],
            $point['longitude'],
            $point['accuracy_m'],
            $point['distance_m'],
            (int) $point['is_inside'] === 1 ? 'Dalam radius' : 'Di luar radius',
        ]);
    }

    fclose($out);
    exit;
}

$totalPoints  = count($points);
$distanceSum  = 0.0;
$outsideCount = 0;
$prev         = null;

foreach ($points as $point) {
    if ($prev !== null) {
        $distanceSum += haversine(
            (float) $prev['latitude'],
            (float) $prev['longitude'],
            (float) $point['latitude'],
            (float) $point['longitude']
        );
    }
    if ((int) $point['is_inside'] === 0) {
        $outsideCount++;
    }
    $prev = $point;
}

$firstPoint = $points[0] ?? null;
$lastPoint  = $points ? $points[$totalPoints - 1] : null;

$tableView = array_reverse($points);
$tableView = array_slice($tableView, 0, 100);

$page_title = 'Riwayat Posisi';
$active     = 'riwayat';
$page_icon  = 'fa-clock-rotate-left';

$exportUrl = url('riwayat.php?' . http_build_query([
    'animal_id' => $animalId,
    'from'      => $from,
    'to'        => $to,
    'export'    => 'csv',
]));

require __DIR__ . '/inc/header.php';
?>

<div class="page-head">
    <div class="page-head-text">
        <h2>Riwayat Perjalanan Ternak</h2>
        <p>Jejak koordinat GPS, jarak dari pusat kandang, dan status geofence.</p>
    </div>
    <div class="btn-row">
        <?php if ($animal): ?>
            <a class="btn btn-outline" href="<?= e($exportUrl) ?>">
                <i class="fa-solid fa-file-csv"></i> Export CSV
            </a>
        <?php endif; ?>
        <a class="btn btn-primary" href="<?= e(url('peta.php')) ?>">
            <i class="fa-solid fa-map-location-dot"></i> Peta Live
        </a>
    </div>
</div>

<div class="card mb-3">
    <form class="filter-bar" method="get" action="<?= e(url('riwayat.php')) ?>">
        <select class="select" name="animal_id" required>
            <?php if (!$animals): ?>
                <option value="">Belum ada ternak</option>
            <?php endif; ?>
            <?php foreach ($animals as $item): ?>
                <option value="<?= (int) $item['id'] ?>" <?= $animalId === (int) $item['id'] ? 'selected' : '' ?>>
                    <?= e($item['nama']) ?> (<?= e($item['kode']) ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <div class="inline-flag">
            <span class="small muted">Dari</span>
            <input class="input" type="date" name="from" value="<?= e($from) ?>">
        </div>
        <div class="inline-flag">
            <span class="small muted">Sampai</span>
            <input class="input" type="date" name="to" value="<?= e($to) ?>">
        </div>

        <button class="btn btn-outline" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Tampilkan</button>
    </form>
</div>

<?php if (!$animal): ?>
    <div class="card">
        <div class="empty-state">
            <?= ikon_ayam() ?>
            <strong>Belum ada ternak</strong>
            <p>Tambahkan data ternak terlebih dahulu untuk melihat riwayat posisi.</p>
        </div>
    </div>
<?php else: ?>
    <div class="grid-stats">
        <div class="stat-card stat-green">
            <div class="stat-head">
                <div>
                    <span class="stat-label">Total Titik</span>
                    <div class="stat-value"><?= $totalPoints ?></div>
                    <div class="stat-sub"><?= e(format_tanggal($start, false)) ?> &ndash; <?= e(format_tanggal($end, false)) ?></div>
                </div>
                <span class="stat-icon"><i class="fa-solid fa-location-dot"></i></span>
            </div>
        </div>

        <div class="stat-card stat-blue">
            <div class="stat-head">
                <div>
                    <span class="stat-label">Estimasi Jarak</span>
                    <div class="stat-value" style="font-size:24px"><?= e(format_jarak($distanceSum)) ?></div>
                    <div class="stat-sub">Akumulasi antar titik GPS</div>
                </div>
                <span class="stat-icon"><i class="fa-solid fa-route"></i></span>
            </div>
        </div>

        <div class="stat-card stat-red">
            <div class="stat-head">
                <div>
                    <span class="stat-label">Titik di Luar Radius</span>
                    <div class="stat-value"><?= $outsideCount ?></div>
                    <div class="stat-sub">Dari <?= $totalPoints ?> titik</div>
                </div>
                <span class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
            </div>
        </div>

        <div class="stat-card stat-amber">
            <div class="stat-head">
                <div>
                    <span class="stat-label">Update Terakhir</span>
                    <div class="stat-value" style="font-size:22px"><?= e($lastPoint ? format_jam($lastPoint['recorded_at']) : '-') ?></div>
                    <div class="stat-sub"><?= e($lastPoint ? time_ago($lastPoint['recorded_at']) : 'Belum ada data') ?></div>
                </div>
                <span class="stat-icon"><i class="fa-regular fa-clock"></i></span>
            </div>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="card">
            <div class="card-header">
                <h3><i class="fa-solid fa-map"></i> Jalur <?= e($animal['nama']) ?></h3>
                <div class="card-tools">
                    <span class="badge badge-success">radius <?= (int) $animal['radius_m'] ?> m</span>
                </div>
            </div>
            <div class="card-body tight">
                <?php if (!$points): ?>
                    <div class="empty-state" style="padding:60px 20px">
                        <i class="fa-solid fa-map"></i>
                        <strong>Tidak ada titik pada rentang tanggal ini</strong>
                        <p>Coba pilih tanggal lain atau kirim data dari simulator.</p>
                    </div>
                <?php else: ?>
                    <div id="trackMap" class="map map-md"></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fa-solid fa-circle-info"></i> Ringkasan</h3>
            </div>
            <div class="card-body">
                <div class="info-row"><span>Ternak</span><span><?= e($animal['nama']) ?> (<?= e($animal['kode']) ?>)</span></div>
                <div class="info-row"><span>Kandang</span><span><?= e($animal['kandang_nama']) ?></span></div>
                <div class="info-row"><span>Titik pusat</span><span><?= e(number_format((float) $animal['center_lat'], 6, ',', '.')) ?>, <?= e(number_format((float) $animal['center_lng'], 6, ',', '.')) ?></span></div>
                <div class="info-row"><span>Radius + buffer</span><span><?= (int) $animal['radius_m'] ?> m + <?= (int) $animal['buffer_m'] ?> m</span></div>
                <div class="info-row"><span>Titik pertama</span><span><?= e($firstPoint ? format_jam($firstPoint['recorded_at']) : '-') ?></span></div>
                <div class="info-row"><span>Titik terakhir</span><span><?= e($lastPoint ? format_jam($lastPoint['recorded_at']) : '-') ?></span></div>
                <div class="info-row"><span>Status saat ini</span>
                    <span>
                        <?php if ($animal['is_inside'] === null): ?>
                            Belum ada data
                        <?php elseif ((int) $animal['is_inside'] === 1): ?>
                            <span class="status-pill status-inside">Dalam radius</span>
                        <?php else: ?>
                            <span class="status-pill status-outside">Di luar radius</span>
                        <?php endif; ?>
                    </span>
                </div>

                <div class="mt-3">
                    <div class="section-title"><i class="fa-solid fa-list"></i> Legenda Peta</div>
                    <div class="legend-row small"><span class="legend-dot green"></span> Titik di dalam radius</div>
                    <div class="legend-row small"><span class="legend-dot red"></span> Titik di luar radius</div>
                    <div class="legend-row small"><span class="legend-circle"></span> Geofence kandang</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header">
            <h3><i class="fa-solid fa-table-list"></i> Detail Titik GPS</h3>
            <div class="card-tools">
                <span class="small muted">Menampilkan maksimal 100 titik terakhir</span>
            </div>
        </div>

        <?php if (!$tableView): ?>
            <div class="empty-state" style="padding:34px">
                <i class="fa-regular fa-folder-open"></i>
                <strong>Data tidak tersedia</strong>
                <p>Belum ada titik koordinat pada rentang tanggal yang dipilih.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Latitude</th>
                            <th>Longitude</th>
                            <th class="text-right">Akurasi</th>
                            <th class="text-right">Jarak dari Pusat</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tableView as $point): ?>
                            <tr>
                                <td class="strong small"><?= e(format_tanggal($point['recorded_at'])) ?></td>
                                <td class="num small"><?= e($point['latitude']) ?></td>
                                <td class="num small"><?= e($point['longitude']) ?></td>
                                <td class="text-right num small"><?= e(format_jarak($point['accuracy_m'] !== null ? (float) $point['accuracy_m'] : null)) ?></td>
                                <td class="text-right num strong"><?= e(format_jarak((float) $point['distance_m'])) ?></td>
                                <td>
                                    <?php if ((int) $point['is_inside'] === 1): ?>
                                        <span class="status-pill status-inside">Dalam</span>
                                    <?php else: ?>
                                        <span class="status-pill status-outside">Luar</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php
$trackAnimalId = (int) ($animal['id'] ?? 0);
$trackFrom     = $from;
$trackTo       = $to;

$page_script = <<<JS
(function () {
    const mapEl = document.getElementById('trackMap');
    if (!mapEl) return;

    const utils = window.TernakMapUtils;
    const map = L.map(mapEl).setView([1.1186, 104.0486], 16);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    function pointPopup(point, index) {
        return '<div class="popup-title">Titik #' + (index + 1) + '</div>' +
            '<div class="popup-sub">' + utils.escapeHtml(point.recorded_at) + '</div>' +
            '<div class="popup-row"><span>Jarak dari pusat</span><span>' + utils.formatMeters(point.distance_m) + '</span></div>' +
            '<div class="popup-row"><span>Status</span><span>' + (point.is_inside ? 'Dalam radius' : 'Di luar radius') + '</span></div>';
    }

    fetch(window.TERNAK.baseUrl + 'api/track.php?animal_id={$trackAnimalId}&from={$trackFrom}&to={$trackTo}', {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin'
    })
    .then((response) => response.json())
    .then((data) => {
        if (!data.ok) throw new Error(data.message || 'Gagal memuat riwayat');

        const kandang = data.kandang;
        L.circle([kandang.lat, kandang.lng], {
            radius: kandang.radius,
            color: '#16a34a',
            weight: 2,
            dashArray: '7 7',
            fillColor: '#22c55e',
            fillOpacity: 0.1
        }).addTo(map).bindTooltip('Radius aman ' + kandang.radius + ' m', { sticky: true });

        const coords = data.points.map((point) => [point.lat, point.lng]);

        if (coords.length > 1) {
            L.polyline(coords, { color: '#16a34a', weight: 3, opacity: 0.75 }).addTo(map);
        }

        data.points.forEach((point, index) => {
            L.circleMarker([point.lat, point.lng], {
                radius: 4,
                color: '#ffffff',
                weight: 1,
                fillColor: point.is_inside ? '#16a34a' : '#dc2626',
                fillOpacity: 1
            }).addTo(map).bindPopup(pointPopup(point, index));
        });

        if (data.points.length) {
            const first = data.points[0];
            const last = data.points[data.points.length - 1];

            L.marker([first.lat, first.lng]).addTo(map).bindPopup('Titik awal - ' + first.recorded_at);
            L.marker([last.lat, last.lng]).addTo(map).bindPopup('Titik terakhir - ' + last.recorded_at);

            map.fitBounds(coords, { padding: [36, 36], maxZoom: 18 });
        } else {
            map.setView([kandang.lat, kandang.lng], 17);
        }
    })
    .catch((error) => {
        console.warn(error);
        showToast('Gagal memuat peta', error.message, 'danger');
    });
})();
JS;

require __DIR__ . '/inc/footer.php';
