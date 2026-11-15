<?php
require_once __DIR__ . '/config/app.php';
require_login();

$page_title = 'Peringatan Geofence';
$active     = 'peringatan';
$page_icon  = 'fa-bell';

$scope    = scope_kandang('k');
$status   = $_GET['status'] ?? 'aktif';
$tipe     = $_GET['tipe'] ?? '';
$animalId = (int) ($_GET['animal'] ?? 0);
$page     = max(1, (int) ($_GET['page'] ?? 1));

$where  = [$scope];
$params = [];
$types  = '';

if ($status === 'aktif') {
    $where[] = 'al.is_resolved = 0';
} elseif ($status === 'selesai') {
    $where[] = 'al.is_resolved = 1';
}

if (in_array($tipe, ['keluar', 'masuk'], true)) {
    $where[]  = 'al.tipe = ?';
    $params[] = $tipe;
    $types   .= 's';
}

if ($animalId > 0) {
    $where[]  = 'al.animal_id = ?';
    $params[] = $animalId;
    $types   .= 'i';
}

$whereSql = implode(' AND ', $where);

$countStmt = $conn->prepare(
    "SELECT COUNT(*) AS c
     FROM alerts al
     INNER JOIN animals a ON a.id = al.animal_id
     INNER JOIN kandangs k ON k.id = a.kandang_id
     WHERE $whereSql"
);
if ($params) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$total = (int) $countStmt->get_result()->fetch_assoc()['c'];
$countStmt->close();

$pager = paginate($total, 20, $page);

$sql = "SELECT al.*, a.nama AS animal_nama, a.kode AS animal_kode, k.nama AS kandang_nama
        FROM alerts al
        INNER JOIN animals a ON a.id = al.animal_id
        INNER JOIN kandangs k ON k.id = a.kandang_id
        WHERE $whereSql
        ORDER BY al.created_at DESC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
if ($params) {
    $allTypes = $types . 'ii';
    $allParams = array_merge($params, [$pager['per_page'], $pager['offset']]);
    $stmt->bind_param($allTypes, ...$allParams);
} else {
    $stmt->bind_param('ii', $pager['per_page'], $pager['offset']);
}
$stmt->execute();
$alerts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stats = $conn->query(
    "SELECT
        SUM(al.is_resolved = 0) AS aktif,
        SUM(DATE(al.created_at) = CURDATE()) AS hari_ini,
        COUNT(*) AS total
     FROM alerts al
     INNER JOIN animals a ON a.id = al.animal_id
     INNER JOIN kandangs k ON k.id = a.kandang_id
     WHERE $scope"
)->fetch_assoc();

$animals = [];
$result = $conn->query(
    "SELECT a.id, a.nama, a.kode
     FROM animals a
     INNER JOIN kandangs k ON k.id = a.kandang_id
     WHERE $scope ORDER BY a.nama"
);
while ($row = $result->fetch_assoc()) {
    $animals[] = $row;
}

function peringatan_url(array $overrides = []): string
{
    $query = array_merge([
        'status' => $_GET['status'] ?? 'aktif',
        'tipe'   => $_GET['tipe'] ?? '',
        'animal' => $_GET['animal'] ?? '',
        'page'   => $_GET['page'] ?? 1,
    ], $overrides);

    $query = array_filter($query, static fn ($value) => $value !== '' && $value !== null);

    return url('peringatan.php' . ($query ? '?' . http_build_query($query) : ''));
}

require __DIR__ . '/inc/header.php';
?>

<div class="page-head">
    <div class="page-head-text">
        <h2>Riwayat Peringatan</h2>
        <p>Peringatan dibuat otomatis saat status ternak berubah melewati batas radius.</p>
    </div>
    <div class="btn-row">
        <?php if ((int) ($stats['aktif'] ?? 0) > 0): ?>
            <form method="post" action="<?= e(url('actions/peringatan.php')) ?>"
                  data-confirm="Tandai semua peringatan aktif sebagai selesai?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="resolve_all">
                <button class="btn btn-outline" type="submit">
                    <i class="fa-solid fa-check-double"></i> Tandai Semua Selesai
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="grid-stats">
    <div class="stat-card stat-red">
        <div class="stat-head">
            <div>
                <span class="stat-label">Peringatan Aktif</span>
                <div class="stat-value"><?= (int) ($stats['aktif'] ?? 0) ?></div>
                <div class="stat-sub">Belum ditindaklanjuti</div>
            </div>
            <span class="stat-icon"><i class="fa-solid fa-bell"></i></span>
        </div>
    </div>
    <div class="stat-card stat-amber">
        <div class="stat-head">
            <div>
                <span class="stat-label">Hari Ini</span>
                <div class="stat-value"><?= (int) ($stats['hari_ini'] ?? 0) ?></div>
                <div class="stat-sub">Total kejadian hari ini</div>
            </div>
            <span class="stat-icon"><i class="fa-regular fa-calendar"></i></span>
        </div>
    </div>
    <div class="stat-card stat-green">
        <div class="stat-head">
            <div>
                <span class="stat-label">Total Peringatan</span>
                <div class="stat-value"><?= (int) ($stats['total'] ?? 0) ?></div>
                <div class="stat-sub">Sejak sistem berjalan</div>
            </div>
            <span class="stat-icon"><i class="fa-solid fa-clock-rotate-left"></i></span>
        </div>
    </div>
</div>

<div class="card">
    <form class="filter-bar" method="get" action="<?= e(url('peringatan.php')) ?>">
        <select class="select" name="status">
            <option value="aktif" <?= $status === 'aktif' ? 'selected' : '' ?>>Aktif</option>
            <option value="selesai" <?= $status === 'selesai' ? 'selected' : '' ?>>Selesai</option>
            <option value="semua" <?= $status === 'semua' ? 'selected' : '' ?>>Semua</option>
        </select>

        <select class="select" name="tipe">
            <option value="">Semua tipe</option>
            <option value="keluar" <?= $tipe === 'keluar' ? 'selected' : '' ?>>Keluar radius</option>
            <option value="masuk" <?= $tipe === 'masuk' ? 'selected' : '' ?>>Kembali ke radius</option>
        </select>

        <select class="select" name="animal">
            <option value="">Semua ternak</option>
            <?php foreach ($animals as $animal): ?>
                <option value="<?= (int) $animal['id'] ?>" <?= $animalId === (int) $animal['id'] ? 'selected' : '' ?>>
                    <?= e($animal['nama']) ?> (<?= e($animal['kode']) ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <button class="btn btn-outline" type="submit"><i class="fa-solid fa-filter"></i> Filter</button>
        <span class="small muted" style="margin-left:auto"><?= $total ?> data</span>
    </form>

    <?php if (!$alerts): ?>
        <div class="empty-state">
            <i class="fa-regular fa-bell-slash"></i>
            <strong>Tidak ada peringatan</strong>
            <p>Belum ada kejadian geofence yang sesuai filter.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Ternak</th>
                        <th>Kandang</th>
                        <th>Tipe</th>
                        <th class="text-right">Jarak</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alerts as $alert): ?>
                        <tr>
                            <td>
                                <div class="strong small"><?= e(format_tanggal($alert['created_at'])) ?></div>
                                <div class="small muted"><?= e(time_ago($alert['created_at'])) ?></div>
                            </td>
                            <td>
                                <div class="strong"><?= e($alert['animal_nama']) ?></div>
                                <div class="small muted"><?= e($alert['animal_kode']) ?></div>
                            </td>
                            <td class="muted"><?= e($alert['kandang_nama']) ?></td>
                            <td>
                                <?php if ($alert['tipe'] === 'keluar'): ?>
                                    <span class="badge badge-danger"><i class="fa-solid fa-arrow-right-from-bracket"></i> Keluar</span>
                                <?php else: ?>
                                    <span class="badge badge-success"><i class="fa-solid fa-arrow-right-to-bracket"></i> Kembali</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <div class="strong num"><?= e(format_jarak((float) $alert['distance_m'])) ?></div>
                                <div class="small muted">radius <?= (int) $alert['radius_m'] ?> m</div>
                            </td>
                            <td>
                                <?php if ((int) $alert['is_resolved'] === 1): ?>
                                    <span class="badge badge-success">Selesai</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Aktif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions">
                                    <a class="icon-btn" title="Lihat riwayat"
                                       href="<?= e(url('riwayat.php?animal_id=' . (int) $alert['animal_id'] . '&tanggal=' . date('Y-m-d', strtotime($alert['created_at'])))) ?>">
                                        <i class="fa-solid fa-chart-line"></i>
                                    </a>
                                    <?php if ((int) $alert['is_resolved'] === 0): ?>
                                        <form method="post" action="<?= e(url('actions/peringatan.php')) ?>" style="display:inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="resolve">
                                            <input type="hidden" name="id" value="<?= (int) $alert['id'] ?>">
                                            <button class="icon-btn" type="submit" title="Tandai selesai">
                                                <i class="fa-solid fa-check"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pager['pages'] > 1): ?>
            <div class="pagination">
                <a class="<?= $pager['page'] <= 1 ? 'disabled' : '' ?>"
                   href="<?= e(peringatan_url(['page' => $pager['page'] - 1])) ?>">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>

                <?php
                $start = max(1, $pager['page'] - 2);
                $end   = min($pager['pages'], $start + 4);
                $start = max(1, $end - 4);
                ?>
                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <?php if ($i === $pager['page']): ?>
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="<?= e(peringatan_url(['page' => $i])) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <a class="<?= $pager['page'] >= $pager['pages'] ? 'disabled' : '' ?>"
                   href="<?= e(peringatan_url(['page' => $pager['page'] + 1])) ?>">
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
