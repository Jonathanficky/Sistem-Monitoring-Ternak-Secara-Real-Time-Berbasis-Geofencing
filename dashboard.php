<?php
require_once __DIR__ . '/config/app.php';
require_login();

$page_title = 'Dashboard';
$active     = 'dashboard';
$page_icon  = 'fa-gauge-high';

$live         = fetch_live_data($conn);
$stats        = $live['stats'];
$animals      = $live['animals'];
$kandangs     = $live['kandangs'];
$recentAlerts = $live['alerts'];

$offlineAfter = (int) get_setting($conn, 'offline_after', '90');
$scope        = scope_kandang('k');

$deviceStats = ['total' => 0, 'online' => 0];
$row = $conn->query(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(d.last_seen_at >= (NOW() - INTERVAL {$offlineAfter} SECOND)), 0) AS online
     FROM devices d
     INNER JOIN animals a ON a.id = d.animal_id
     INNER JOIN kandangs k ON k.id = a.kandang_id
     WHERE d.is_active = 1 AND $scope"
)->fetch_assoc();

$deviceStats['total']  = (int) $row['total'];
$deviceStats['online'] = (int) $row['online'];

$deviceList = [];
$result = $conn->query(
    "SELECT d.kode, d.last_seen_at, a.nama AS animal_nama
     FROM devices d
     INNER JOIN animals a ON a.id = d.animal_id
     INNER JOIN kandangs k ON k.id = a.kandang_id
     WHERE d.is_active = 1 AND $scope
     ORDER BY d.last_seen_at DESC
     LIMIT 4"
);
while ($r = $result->fetch_assoc()) {
    $deviceList[] = $r;
}

$totalTernak   = count($animals);
$insideCount   = $stats['inside'];
$outsideCount  = $stats['outside'];
$unknownCount  = $stats['unknown'];
$activeAlerts  = $stats['alerts'];
$user          = current_user();

require __DIR__ . '/inc/header.php';
?>

<div class="page-head">
    <div class="page-head-text">
        <h2>Selamat datang, <?= e(explode(' ', $user['nama'])[0]) ?> 👋</h2>
        <p>Ringkasan kondisi ternak dan geofence saat ini.</p>
    </div>
    <div class="btn-row">
        <a class="btn btn-outline" href="<?= e(url('riwayat.php')) ?>">
            <i class="fa-solid fa-clock-rotate-left"></i> Riwayat Posisi
        </a>
        <a class="btn btn-primary" href="<?= e(url('peta.php')) ?>">
            <i class="fa-solid fa-map-location-dot"></i> Buka Peta Live
        </a>
    </div>
</div>

<div class="grid-stats">
    <div class="stat-card stat-green">
        <div class="stat-head">
            <div>
                <span class="stat-label">Total Ternak</span>
                <div class="stat-value" id="statTotal"><?= $totalTernak ?></div>
                <div class="stat-sub"><?= count($kandangs) ?> kandang terdaftar</div>
            </div>
            <span class="stat-icon"><?= ikon_ayam() ?></span>
        </div>
    </div>

    <div class="stat-card stat-blue">
        <div class="stat-head">
            <div>
                <span class="stat-label">Di Dalam Radius</span>
                <div class="stat-value" id="statInside"><?= $insideCount ?></div>
                <div class="stat-sub"><?= $unknownCount > 0 ? $unknownCount . ' ternak belum ada data' : 'Semua ternak terpantau' ?></div>
            </div>
            <span class="stat-icon"><i class="fa-solid fa-circle-check"></i></span>
        </div>
    </div>

    <div class="stat-card stat-red">
        <div class="stat-head">
            <div>
                <span class="stat-label">Di Luar Radius</span>
                <div class="stat-value" id="statOutside"><?= $outsideCount ?></div>
                <div class="stat-sub">Keluar dari area aman kandang</div>
            </div>
            <span class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
        </div>
    </div>

    <div class="stat-card stat-amber">
        <div class="stat-head">
            <div>
                <span class="stat-label">Peringatan Aktif</span>
                <div class="stat-value" id="statAlerts"><?= $activeAlerts ?></div>
                <div class="stat-sub">
                    <?= $deviceStats['online'] ?>/<?= $deviceStats['total'] ?> perangkat online
                </div>
            </div>
            <span class="stat-icon"><i class="fa-solid fa-bell"></i></span>
        </div>
    </div>
</div>

<div class="dashboard-grid">
    <div class="card">
        <div class="card-header">
            <h3><i class="fa-solid fa-map-location-dot"></i> Sebaran Ternak</h3>
            <div class="card-tools">
                <span class="badge badge-success"><i class="fa-solid fa-circle"></i> Live</span>
                <a class="btn btn-sm btn-outline" href="<?= e(url('peta.php')) ?>">
                    Perbesar <i class="fa-solid fa-arrow-up-right-from-square"></i>
                </a>
            </div>
        </div>
        <div class="card-body tight">
            <div id="dashboardMap" class="map map-sm"></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fa-solid fa-bell"></i> Peringatan Terbaru</h3>
            <div class="card-tools">
                <a class="small" href="<?= e(url('peringatan.php')) ?>">Lihat semua</a>
            </div>
        </div>

        <?php if (!$recentAlerts): ?>
            <div class="empty-state">
                <i class="fa-regular fa-bell-slash"></i>
                <strong>Tidak ada peringatan aktif</strong>
                <p>Semua ternak berada di dalam radius aman.</p>
            </div>
        <?php else: ?>
            <div class="alert-list">
                <?php foreach ($recentAlerts as $alert): ?>
                    <div class="alert-item">
                        <span class="alert-icon <?= e($alert['tipe']) ?>">
                            <i class="fa-solid <?= $alert['tipe'] === 'keluar' ? 'fa-arrow-right-from-bracket' : 'fa-arrow-right-to-bracket' ?>"></i>
                        </span>
                        <div class="alert-main">
                            <strong><?= e($alert['animal_nama']) ?> <?= $alert['tipe'] === 'keluar' ? 'keluar radius' : 'kembali ke radius' ?></strong>
                            <p><?= e($alert['kandang_nama']) ?> &middot; jarak <?= e(format_jarak((float) $alert['distance_m'])) ?> dari pusat</p>
                        </div>
                        <span class="alert-meta"><?= e(time_ago($alert['created_at'])) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="dashboard-grid-2">
    <div class="card">
        <div class="card-header">
            <h3><?= ikon_ayam() ?> Ternak Terpantau</h3>
            <div class="card-tools">
                <a class="btn btn-sm btn-outline" href="<?= e(url('ternak.php')) ?>">Kelola data</a>
            </div>
        </div>

        <?php if (!$animals): ?>
            <div class="empty-state">
                <?= ikon_ayam() ?>
                <strong>Belum ada ternak</strong>
                <p>Tambahkan data ternak dan hubungkan dengan perangkat GPS.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Ternak</th>
                            <th>Kandang</th>
                            <th>Status</th>
                            <th class="text-right">Jarak</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($animals, 0, 5) as $animal): ?>
                            <?php
                            $statusClass = $animal['is_inside'] === null ? 'unknown' : ($animal['is_inside'] ? 'inside' : 'outside');
                            $statusText  = $animal['is_inside'] === null ? 'Belum ada data' : ($animal['is_inside'] ? 'Dalam radius' : 'Di luar radius');
                            ?>
                            <tr>
                                <td>
                                    <div class="cell-user">
                                        <span class="thumb-fallback"><?= ikon_ayam() ?></span>
                                        <div>
                                            <strong><?= e($animal['nama']) ?></strong>
                                            <small><?= e($animal['kode']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td class="muted"><?= e($animal['kandang_nama']) ?></td>
                                <td><span class="status-pill status-<?= $statusClass ?>"><?= e($statusText) ?></span></td>
                                <td class="text-right num strong"><?= e(format_jarak($animal['distance_m'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fa-solid fa-microchip"></i> Status Perangkat</h3>
            <div class="card-tools">
                <a class="btn btn-sm btn-outline" href="<?= e(url('perangkat.php')) ?>">Detail</a>
            </div>
        </div>
        <div class="card-body">
            <div class="flex-between mb-3">
                <div>
                    <span class="small muted">Perangkat online</span>
                    <div style="font-size:24px;font-weight:800;line-height:1.2">
                        <?= $deviceStats['online'] ?>
                        <span class="muted" style="font-size:14px;font-weight:600">/ <?= $deviceStats['total'] ?></span>
                    </div>
                </div>
                <span class="badge <?= $deviceStats['online'] > 0 ? 'badge-success' : 'badge-muted' ?>">
                    <i class="fa-solid fa-wifi"></i>
                    <?= $deviceStats['online'] > 0 ? 'Terhubung' : 'Tidak ada koneksi' ?>
                </span>
            </div>

            <?php if (!$deviceList): ?>
                <p class="small muted">Belum ada perangkat aktif. Tambahkan perangkat pada menu Perangkat IoT.</p>
            <?php else: ?>
                <?php foreach ($deviceList as $device): ?>
                    <?php $online = is_online($device['last_seen_at'], $offlineAfter); ?>
                    <div class="flex-between" style="padding:9px 0;border-bottom:1px dashed var(--border-soft)">
                        <div class="inline-flag">
                            <span class="dot <?= $online ? 'dot-live' : 'dot-off' ?>"></span>
                            <div>
                                <div class="strong" style="font-size:12.5px"><?= e($device['kode']) ?></div>
                                <div class="small muted"><?= e($device['animal_nama'] ?? 'Belum terhubung') ?></div>
                            </div>
                        </div>
                        <span class="small muted"><?= e($online ? 'Online' : time_ago($device['last_seen_at'])) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="settings-note mt-3">
                <i class="fa-solid fa-circle-info"></i>
                <span>Interval pembaruan posisi: <strong><?= (int) round(((int) get_setting($conn, 'poll_interval', '5000')) / 1000) ?> detik</strong>.</span>
            </div>
        </div>
    </div>
</div>

<?php
$page_script = <<<'JS'
(function () {
    const map = new LiveMap('dashboardMap', {
        refreshMs: window.TERNAK.pollInterval,
        zoom: 16,
        onUpdate: function (data) {
            const stats = data.stats || {};
            const set = (id, value) => {
                const el = document.getElementById(id);
                if (el) el.textContent = value;
            };
            set('statTotal', stats.total ?? 0);
            set('statInside', stats.inside ?? 0);
            set('statOutside', stats.outside ?? 0);
            set('statAlerts', stats.alerts ?? 0);
        },
        onAlert: function (alert) {
            showToast(
                alert.tipe === 'keluar' ? 'Ternak keluar radius!' : 'Ternak kembali ke radius',
                alert.animal_nama + ' - ' + alert.kandang_nama + ' (' + Number(alert.distance_m).toFixed(1) + ' m)',
                alert.tipe === 'keluar' ? 'danger' : 'success',
                9000
            );
        }
    });
})();
JS;

require __DIR__ . '/inc/footer.php';
