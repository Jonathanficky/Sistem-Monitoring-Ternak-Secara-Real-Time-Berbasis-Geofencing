<?php
require_once __DIR__ . '/config/app.php';
require_login();

$page_title = 'Perangkat IoT';
$active     = 'perangkat';
$page_icon  = 'fa-microchip';

$scope        = scope_kandang('k');
$offlineAfter = (int) get_setting($conn, 'offline_after', '90');

$devices = [];
$result = $conn->query(
    "SELECT d.*, a.nama AS animal_nama, a.kode AS animal_kode, k.nama AS kandang_nama
     FROM devices d
     LEFT JOIN animals a ON a.id = d.animal_id
     LEFT JOIN kandangs k ON k.id = a.kandang_id
     WHERE $scope
     ORDER BY d.kode ASC"
);
while ($row = $result->fetch_assoc()) {
    $devices[] = $row;
}

$animals = [];
$result = $conn->query(
    "SELECT a.id, a.kode, a.nama, k.nama AS kandang_nama
     FROM animals a
     INNER JOIN kandangs k ON k.id = a.kandang_id
     WHERE $scope
     ORDER BY a.nama ASC"
);
while ($row = $result->fetch_assoc()) {
    $animals[] = $row;
}

$apiUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url('api/telemetry.php');

require __DIR__ . '/inc/header.php';
?>

<div class="page-head">
    <div class="page-head-text">
        <h2>Perangkat GPS (ESP32)</h2>
        <p>Setiap perangkat memiliki token unik untuk mengirim data koordinat ke server.</p>
    </div>
    <div class="btn-row">
        <button class="btn btn-primary" type="button" id="btnAddDevice">
            <i class="fa-solid fa-plus"></i> Tambah Perangkat
        </button>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="form-grid" style="align-items:center">
            <div>
                <div class="section-title" style="margin-top:0"><i class="fa-solid fa-satellite-dish"></i> Endpoint Telemetry</div>
                <div class="token-cell">
                    <code class="token-mask" style="font-size:12px;color:var(--green-800)"><?= e($apiUrl) ?></code>
                    <button class="icon-btn" type="button" data-copy="<?= e($apiUrl) ?>" title="Salin URL">
                        <i class="fa-regular fa-copy"></i>
                    </button>
                </div>
                <p class="small muted mt-1">
                    Gunakan alamat IP LAN komputer ini (bukan <em>localhost</em>) pada firmware agar dapat diakses ESP32.
                </p>
            </div>
            <div>
                <div class="settings-note">
                    <i class="fa-solid fa-shield-halved"></i>
                    <span>Token bersifat rahasia. Jangan bagikan ke pihak lain dan ganti token jika perangkat hilang.</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fa-solid fa-microchip"></i> Daftar Perangkat</h3>
        <div class="card-tools">
            <span class="badge badge-muted"><?= count($devices) ?> perangkat</span>
        </div>
    </div>

    <?php if (!$devices): ?>
        <div class="empty-state">
            <i class="fa-solid fa-microchip"></i>
            <strong>Belum ada perangkat</strong>
            <p>Tambahkan perangkat ESP32 lalu salin token API ke firmware.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kode Perangkat</th>
                        <th>Ternak</th>
                        <th>Token API</th>
                        <th>Terakhir Kirim</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($devices as $device): ?>
                        <?php
                        $online = is_online($device['last_seen_at'], $offlineAfter);
                        $payload = [
                            'id'        => (int) $device['id'],
                            'kode'      => $device['kode'],
                            'animal_id' => (int) ($device['animal_id'] ?? 0),
                            'is_active' => (int) $device['is_active'],
                        ];
                        $masked = substr($device['api_token'], 0, 8) . '····' . substr($device['api_token'], -6);
                        ?>
                        <tr>
                            <td>
                                <span class="strong"><?= e($device['kode']) ?></span>
                                <div class="small muted">ID #<?= (int) $device['id'] ?></div>
                            </td>
                            <td>
                                <?php if ($device['animal_nama']): ?>
                                    <div class="strong"><?= e($device['animal_nama']) ?></div>
                                    <div class="small muted"><?= e($device['kandang_nama']) ?></div>
                                <?php else: ?>
                                    <span class="badge badge-warning">Belum terhubung</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="token-cell">
                                    <code class="token-mask" data-token-text><?= e($masked) ?></code>
                                    <button class="icon-btn" type="button" title="Tampilkan"
                                            data-reveal-token="<?= e($device['api_token']) ?>">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>
                                    <button class="icon-btn" type="button" title="Salin token"
                                            data-copy="<?= e($device['api_token']) ?>">
                                        <i class="fa-regular fa-copy"></i>
                                    </button>
                                </div>
                            </td>
                            <td class="muted small">
                                <?= e(time_ago($device['last_seen_at'])) ?>
                                <?php if ($device['last_seen_at']): ?>
                                    <div class="small"><?= e(format_tanggal($device['last_seen_at'])) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$device['is_active']): ?>
                                    <span class="badge badge-muted">Nonaktif</span>
                                <?php elseif ($online): ?>
                                    <span class="badge badge-success"><span class="dot dot-live"></span> Online</span>
                                <?php else: ?>
                                    <span class="badge badge-danger"><span class="dot dot-off"></span> Offline</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions">
                                    <button class="icon-btn" type="button" title="Edit perangkat"
                                            data-edit-device="<?= e(json_encode($payload, JSON_UNESCAPED_UNICODE)) ?>">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <form method="post" action="<?= e(url('actions/perangkat.php')) ?>"
                                          data-confirm="Buat token baru untuk <?= e($device['kode']) ?>? Token lama tidak akan berlaku lagi."
                                          style="display:inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="regenerate">
                                        <input type="hidden" name="id" value="<?= (int) $device['id'] ?>">
                                        <button class="icon-btn" type="submit" title="Regenerate token">
                                            <i class="fa-solid fa-arrows-rotate"></i>
                                        </button>
                                    </form>
                                    <form method="post" action="<?= e(url('actions/perangkat.php')) ?>"
                                          data-confirm="Hapus perangkat <?= e($device['kode']) ?>?"
                                          style="display:inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="hapus">
                                        <input type="hidden" name="id" value="<?= (int) $device['id'] ?>">
                                        <button class="icon-btn danger" type="submit" title="Hapus">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal perangkat -->
<div class="modal" id="modalPerangkat" role="dialog" aria-modal="true">
    <div class="modal-card">
        <form method="post" action="<?= e(url('actions/perangkat.php')) ?>" data-no-lock>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="simpan">
            <input type="hidden" name="id" id="deviceId" value="">

            <div class="modal-header">
                <div>
                    <h3 id="modalPerangkatTitle">Tambah Perangkat</h3>
                    <p>Token API dibuat otomatis oleh sistem.</p>
                </div>
                <button class="icon-btn modal-close" type="button" data-modal-close>
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="modal-body">
                <div class="field">
                    <label for="deviceKode">Kode perangkat <span class="req">*</span></label>
                    <input class="input" id="deviceKode" name="kode" required maxlength="50" placeholder="cth. ESP32-TRK003">
                    <span class="help">Gunakan kode yang sama dengan yang ditulis pada casing perangkat.</span>
                </div>

                <div class="field">
                    <label for="deviceAnimal">Dihubungkan ke ternak</label>
                    <select class="select" id="deviceAnimal" name="animal_id">
                        <option value="">— Belum dihubungkan —</option>
                        <?php foreach ($animals as $animal): ?>
                            <option value="<?= (int) $animal['id'] ?>">
                                <?= e($animal['nama']) ?> (<?= e($animal['kode']) ?>) — <?= e($animal['kandang_nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <label class="switch">
                    <input type="checkbox" name="is_active" value="1" id="deviceActive" checked>
                    <span>Perangkat aktif</span>
                </label>
            </div>

            <div class="modal-footer">
                <button class="btn btn-outline" type="button" data-modal-close>Batal</button>
                <button class="btn btn-primary" type="submit">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan Perangkat
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$page_script = <<<'JS'
(function () {
    const modal = document.getElementById('modalPerangkat');
    const form = modal.querySelector('form');
    const idField = document.getElementById('deviceId');
    const title = document.getElementById('modalPerangkatTitle');
    const kode = document.getElementById('deviceKode');
    const animal = document.getElementById('deviceAnimal');
    const active = document.getElementById('deviceActive');

    document.getElementById('btnAddDevice').addEventListener('click', () => {
        form.reset();
        idField.value = '';
        title.textContent = 'Tambah Perangkat';
        active.checked = true;
        openModal(modal);
    });

    document.querySelectorAll('[data-edit-device]').forEach((button) => {
        button.addEventListener('click', () => {
            const data = JSON.parse(button.dataset.editDevice);
            form.reset();
            idField.value = data.id;
            title.textContent = 'Edit Perangkat: ' + data.kode;
            kode.value = data.kode;
            animal.value = data.animal_id || '';
            active.checked = data.is_active === 1;
            openModal(modal);
        });
    });

    document.querySelectorAll('[data-reveal-token]').forEach((button) => {
        button.addEventListener('click', () => {
            const token = button.dataset.revealToken;
            const cell = button.closest('.token-cell').querySelector('[data-token-text]');
            const icon = button.querySelector('i');
            const shown = cell.dataset.shown === '1';

            if (shown) {
                cell.textContent = token.substring(0, 8) + '····' + token.slice(-6);
                cell.dataset.shown = '0';
                icon.className = 'fa-regular fa-eye';
            } else {
                cell.textContent = token;
                cell.dataset.shown = '1';
                icon.className = 'fa-regular fa-eye-slash';
            }
        });
    });
})();
JS;

require __DIR__ . '/inc/footer.php';
