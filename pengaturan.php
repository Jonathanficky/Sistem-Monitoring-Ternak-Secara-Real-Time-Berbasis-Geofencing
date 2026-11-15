<?php
require_once __DIR__ . '/config/app.php';
require_admin();

$page_title = 'Pengaturan';
$active     = 'pengaturan';
$page_icon  = 'fa-gear';

$settings = [];
$result = $conn->query('SELECT nama, nilai FROM settings');
while ($row = $result->fetch_assoc()) {
    $settings[$row['nama']] = $row['nilai'];
}

$apiUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url('api/telemetry.php');

$firmwareUrl = url('firmware/ternak_gps_esp32.ino');
$readmeUrl   = url('README.md');

require __DIR__ . '/inc/header.php';
?>

<div class="page-head">
    <div class="page-head-text">
        <h2>Pengaturan Sistem</h2>
        <p>Konfigurasi interval monitoring, radius default, dan integrasi perangkat.</p>
    </div>
</div>

<div class="dashboard-grid">
    <div class="card">
        <div class="card-header">
            <h3><i class="fa-solid fa-sliders"></i> Konfigurasi Umum</h3>
        </div>
        <form method="post" action="<?= e(url('actions/pengaturan.php')) ?>" data-no-lock>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="simpan">

            <div class="card-body">
                <div class="field">
                    <label for="nama_aplikasi">Nama aplikasi</label>
                    <input class="input" id="nama_aplikasi" name="nama_aplikasi" maxlength="60"
                           value="<?= e($settings['nama_aplikasi'] ?? APP_NAME) ?>" required>
                </div>

                <div class="form-grid">
                    <div class="field">
                        <label for="poll_interval">Interval polling peta</label>
                        <select class="select" id="poll_interval" name="poll_interval">
                            <?php
                            $poll = (int) ($settings['poll_interval'] ?? 5000);
                            $options = [2000 => '2 detik', 3000 => '3 detik', 5000 => '5 detik', 10000 => '10 detik', 15000 => '15 detik', 30000 => '30 detik'];
                            foreach ($options as $value => $label): ?>
                                <option value="<?= $value ?>" <?= $poll === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="help">Frekuensi pembaruan peta live & dashboard.</span>
                    </div>

                    <div class="field">
                        <label for="offline_after">Batas perangkat offline</label>
                        <div class="input-group">
                            <input class="input" id="offline_after" name="offline_after" type="number"
                                   min="30" max="3600" value="<?= (int) ($settings['offline_after'] ?? 90) ?>">
                            <span class="badge badge-muted" style="align-self:center">detik</span>
                        </div>
                        <span class="help">Jika tidak ada data selama durasi ini, perangkat dianggap offline.</span>
                    </div>

                    <div class="field">
                        <label for="default_radius">Radius default kandang</label>
                        <div class="input-group">
                            <input class="input" id="default_radius" name="default_radius" type="number"
                                   min="5" max="2000" value="<?= (int) ($settings['default_radius'] ?? 40) ?>">
                            <span class="badge badge-muted" style="align-self:center">meter</span>
                        </div>
                    </div>

                    <div class="field">
                        <label for="default_buffer">Buffer GPS default</label>
                        <div class="input-group">
                            <input class="input" id="default_buffer" name="default_buffer" type="number"
                                   min="0" max="100" value="<?= (int) ($settings['default_buffer'] ?? 3) ?>">
                            <span class="badge badge-muted" style="align-self:center">meter</span>
                        </div>
                        <span class="help">Toleransi pergerakan GPS (drift) agar tidak false alarm.</span>
                    </div>
                </div>

                <div class="settings-note mt-2">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>Perubahan berlaku untuk pembaruan berikutnya. Data historis tidak berubah.</span>
                </div>
            </div>

            <div class="card-footer" style="text-align:right">
                <button class="btn btn-primary" type="submit">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan Pengaturan
                </button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fa-solid fa-satellite-dish"></i> Integrasi Perangkat</h3>
        </div>
        <div class="card-body">
            <div class="info-row">
                <span>Endpoint telemetry</span>
                <span><?= e($apiUrl) ?></span>
            </div>
            <div class="info-row">
                <span>Metode</span>
                <span>POST &middot; JSON</span>
            </div>
            <div class="info-row">
                <span>Autentikasi</span>
                <span>Bearer token perangkat</span>
            </div>
            <div class="info-row">
                <span>Database</span>
                <span>ternak_monitoring (MySQL)</span>
            </div>

            <div class="section-title mt-3"><i class="fa-solid fa-terminal"></i> Contoh pengujian</div>
            <pre class="code-block">curl -X POST "<?= e($apiUrl) ?>" \
  -H "Authorization: Bearer &lt;TOKEN_PERANGKAT&gt;" \
  -H "Content-Type: application/json" \
  -d '{"latitude":1.1186,"longitude":104.0486,"accuracy":5}'</pre>

            <div class="section-title mt-3"><i class="fa-solid fa-microchip"></i> Firmware</div>
            <p class="small muted mb-2">
                Sketch ESP32 + GPS NEO-6M sudah tersedia. Sesuaikan SSID, password Wi-Fi,
                URL server, dan token perangkat sebelum upload.
            </p>
            <div class="btn-row">
                <a class="btn btn-outline btn-sm" href="<?= e($firmwareUrl) ?>" download>
                    <i class="fa-solid fa-download"></i> Unduh firmware
                </a>
                <a class="btn btn-outline btn-sm" href="<?= e($readmeUrl) ?>" target="_blank">
                    <i class="fa-solid fa-book"></i> Dokumentasi
                </a>
            </div>

            <div class="settings-note mt-3">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span>
                    Saat menguji dengan ESP32, gunakan alamat IP LAN komputer
                    (mis. <code>http://192.168.1.10/sistem monitoring ternak/api/telemetry.php</code>),
                    bukan <em>localhost</em>.
                </span>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">
        <h3><i class="fa-solid fa-circle-info"></i> Informasi Aplikasi</h3>
    </div>
    <div class="card-body">
        <div class="info-grid">
            <div class="info-tile">
                <span>Nama aplikasi</span>
                <strong><?= e($settings['nama_aplikasi'] ?? APP_NAME) ?></strong>
            </div>
            <div class="info-tile">
                <span>Versi</span>
                <strong>v<?= e(APP_VERSION) ?></strong>
            </div>
            <div class="info-tile">
                <span>Zona waktu</span>
                <strong><?= e(date_default_timezone_get()) ?></strong>
            </div>
            <div class="info-tile">
                <span>PHP</span>
                <strong><?= e(PHP_VERSION) ?></strong>
            </div>
            <div class="info-tile">
                <span>MySQL</span>
                <strong><?= e($conn->server_info) ?></strong>
            </div>
            <div class="info-tile">
                <span>Server waktu</span>
                <strong><?= e(format_tanggal(date('Y-m-d H:i:s'))) ?></strong>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
