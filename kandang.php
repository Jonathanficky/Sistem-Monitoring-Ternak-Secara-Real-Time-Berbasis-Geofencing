<?php
require_once __DIR__ . '/config/app.php';
require_login();

$page_title = 'Kandang';
$active     = 'kandang';
$page_icon  = 'fa-house-chimney';

$scope  = scope_kandang('k');
$defaultRadius = (int) get_setting($conn, 'default_radius', '40');
$defaultBuffer = (int) get_setting($conn, 'default_buffer', '3');

$kandangs = [];
$result = $conn->query(
    "SELECT k.*, u.nama AS pemilik_nama,
            (SELECT COUNT(*) FROM animals a WHERE a.kandang_id = k.id) AS jumlah_ternak,
            (SELECT COUNT(*) FROM devices d
             INNER JOIN animals a2 ON a2.id = d.animal_id
             WHERE a2.kandang_id = k.id AND d.is_active = 1) AS jumlah_device
     FROM kandangs k
     LEFT JOIN users u ON u.id = k.pemilik_id
     WHERE $scope
     ORDER BY k.nama ASC"
);
while ($row = $result->fetch_assoc()) {
    $kandangs[] = $row;
}

$petani = [];
if (is_admin()) {
    $result = $conn->query("SELECT id, nama FROM users WHERE role = 'petani' ORDER BY nama");
    while ($row = $result->fetch_assoc()) {
        $petani[] = $row;
    }
}

require __DIR__ . '/inc/header.php';
?>

<div class="page-head">
    <div class="page-head-text">
        <h2>Area Geofence Kandang</h2>
        <p>Atur titik pusat kandang dan radius aman yang digunakan mesin Haversine.</p>
    </div>
    <div class="btn-row">
        <button class="btn btn-primary" type="button" id="btnAddKandang">
            <i class="fa-solid fa-plus"></i> Tambah Kandang
        </button>
    </div>
</div>

<?php if (!$kandangs): ?>
    <div class="card">
        <div class="empty-state">
            <i class="fa-solid fa-house-chimney"></i>
            <strong>Belum ada kandang</strong>
            <p>Tambahkan kandang dan tentukan titik pusat geofence pada peta.</p>
        </div>
    </div>
<?php else: ?>
    <div class="kandang-grid">
        <?php foreach ($kandangs as $kandang): ?>
            <?php
            $payload = [
                'id'         => (int) $kandang['id'],
                'nama'       => $kandang['nama'],
                'lokasi'     => $kandang['lokasi'],
                'lat'        => (float) $kandang['center_lat'],
                'lng'        => (float) $kandang['center_lng'],
                'radius'     => (int) $kandang['radius_m'],
                'buffer'     => (int) $kandang['buffer_m'],
                'pemilik_id' => (int) $kandang['pemilik_id'],
            ];
            ?>
            <div class="card kandang-card">
                <div class="card-body">
                    <div class="kandang-top">
                        <span class="kandang-icon"><i class="fa-solid fa-house-chimney"></i></span>
                        <div style="min-width:0">
                            <div class="kandang-name truncate"><?= e($kandang['nama']) ?></div>
                            <div class="kandang-loc">
                                <i class="fa-solid fa-location-dot"></i>
                                <span class="truncate"><?= e($kandang['lokasi'] ?: 'Lokasi belum diisi') ?></span>
                            </div>
                        </div>
                        <span class="badge badge-success" style="margin-left:auto">
                            <?= (int) $kandang['radius_m'] ?> m
                        </span>
                    </div>

                    <div class="kandang-stats">
                        <div class="kandang-stat">
                            <span>Radius</span>
                            <strong><?= (int) $kandang['radius_m'] ?> m</strong>
                        </div>
                        <div class="kandang-stat">
                            <span>Buffer</span>
                            <strong>&plusmn;<?= (int) $kandang['buffer_m'] ?> m</strong>
                        </div>
                        <div class="kandang-stat">
                            <span>Ternak</span>
                            <strong><?= (int) $kandang['jumlah_ternak'] ?> ekor</strong>
                        </div>
                    </div>

                    <div class="kandang-loc" style="margin-top:12px">
                        <i class="fa-solid fa-user"></i>
                        <span>Pemilik: <?= e($kandang['pemilik_nama'] ?: 'Belum ditentukan') ?></span>
                    </div>
                    <div class="kandang-loc" style="margin-top:4px">
                        <i class="fa-solid fa-microchip"></i>
                        <span><?= (int) $kandang['jumlah_device'] ?> perangkat aktif</span>
                    </div>

                    <div class="kandang-actions">
                        <button class="btn btn-sm btn-outline" type="button"
                                data-edit-kandang="<?= e(json_encode($payload, JSON_UNESCAPED_UNICODE)) ?>">
                            <i class="fa-solid fa-pen"></i> Edit
                        </button>
                        <a class="btn btn-sm btn-outline" href="<?= e(url('peta.php')) ?>">
                            <i class="fa-solid fa-map"></i> Peta
                        </a>
                        <form method="post" action="<?= e(url('actions/kandang.php')) ?>"
                              data-confirm="Hapus kandang <?= e($kandang['nama']) ?>?"
                              style="margin-left:auto">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="hapus">
                            <input type="hidden" name="id" value="<?= (int) $kandang['id'] ?>">
                            <button class="icon-btn danger" type="submit" title="Hapus">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Modal kandang -->
<div class="modal" id="modalKandang" role="dialog" aria-modal="true">
    <div class="modal-card modal-lg">
        <form method="post" action="<?= e(url('actions/kandang.php')) ?>" data-no-lock>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="simpan">
            <input type="hidden" name="id" id="kandangId" value="">
            <input type="hidden" name="center_lat" id="kandangLat" value="">
            <input type="hidden" name="center_lng" id="kandangLng" value="">

            <div class="modal-header">
                <div>
                    <h3 id="modalKandangTitle">Tambah Kandang</h3>
                    <p>Klik pada peta untuk menentukan titik pusat geofence.</p>
                </div>
                <button class="icon-btn modal-close" type="button" data-modal-close>
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="modal-body">
                <div class="form-grid">
                    <div class="field">
                        <label for="kandangNama">Nama kandang <span class="req">*</span></label>
                        <input class="input" id="kandangNama" name="nama" required maxlength="100" placeholder="cth. Kandang Utama">
                    </div>

                    <div class="field">
                        <label for="kandangLokasi">Lokasi / keterangan</label>
                        <input class="input" id="kandangLokasi" name="lokasi" maxlength="150" placeholder="cth. Batam Center">
                    </div>

                    <?php if (is_admin()): ?>
                        <div class="field span-2">
                            <label for="kandangPemilik">Pemilik kandang</label>
                            <select class="select" id="kandangPemilik" name="pemilik_id">
                                <option value="">Admin (saya sendiri)</option>
                                <?php foreach ($petani as $p): ?>
                                    <option value="<?= (int) $p['id'] ?>"><?= e($p['nama']) ?> (petani)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="field span-2">
                        <div class="flex-between">
                            <label>Radius geofence <span class="req">*</span></label>
                            <span class="badge badge-success" id="radiusBadge"><?= $defaultRadius ?> meter</span>
                        </div>
                        <div class="range-row">
                            <input type="range" id="radiusRange" min="5" max="300" step="5" value="<?= $defaultRadius ?>">
                            <input class="input" style="width:96px" type="number" id="radiusNumber" name="radius_m"
                                   min="5" max="2000" value="<?= $defaultRadius ?>">
                        </div>
                        <span class="help">Jarak maksimum ternak dari titik pusat sebelum dianggap keluar.</span>
                    </div>

                    <div class="field">
                        <label for="bufferInput">Buffer GPS (meter)</label>
                        <input class="input" id="bufferInput" name="buffer_m" type="number" min="0" max="100" value="<?= $defaultBuffer ?>">
                        <span class="help">Toleransi drift GPS agar tidak false alarm.</span>
                    </div>

                    <div class="field">
                        <label>Koordinat pusat</label>
                        <input class="input" id="coordDisplay" value="Belum dipilih" readonly>
                    </div>

                    <div class="field span-2 mb-0">
                        <div id="kandangPicker" style="height:290px;border-radius:12px;overflow:hidden;border:1px solid var(--border)"></div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-outline" type="button" data-modal-close>Batal</button>
                <button class="btn btn-primary" type="submit">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan Kandang
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$jsDefaultRadius = (int) $defaultRadius;
$page_script = <<<JS
(function () {
    const modal = document.getElementById('modalKandang');
    const form = modal.querySelector('form');
    const idField = document.getElementById('kandangId');
    const title = document.getElementById('modalKandangTitle');
    const nama = document.getElementById('kandangNama');
    const lokasi = document.getElementById('kandangLokasi');
    const owner = document.getElementById('kandangPemilik');
    const latInput = document.getElementById('kandangLat');
    const lngInput = document.getElementById('kandangLng');
    const coordDisplay = document.getElementById('coordDisplay');
    const radiusRange = document.getElementById('radiusRange');
    const radiusNumber = document.getElementById('radiusNumber');
    const radiusBadge = document.getElementById('radiusBadge');
    const bufferInput = document.getElementById('bufferInput');

    const DEFAULT_RADIUS = {$jsDefaultRadius};
    const DEFAULT_CENTER = window.TERNAK.mapCenter;

    let picker = null;
    let marker = null;
    let circle = null;
    let current = { lat: DEFAULT_CENTER[0], lng: DEFAULT_CENTER[1] };

    function syncRadius(value) {
        const radius = Math.max(5, Math.min(2000, Number(value) || 5));
        radiusBadge.textContent = radius + ' meter';
        radiusRange.value = Math.min(Number(radiusRange.max), Math.max(Number(radiusRange.min), radius));
        if (circle) circle.setRadius(radius);
    }

    function setPoint(lat, lng) {
        current = { lat: Number(lat), lng: Number(lng) };
        latInput.value = current.lat.toFixed(7);
        lngInput.value = current.lng.toFixed(7);
        coordDisplay.value = current.lat.toFixed(6) + ', ' + current.lng.toFixed(6);

        if (!picker) return;
        if (marker) marker.setLatLng(current);
        if (circle) circle.setLatLng(current);
        picker.setView(current, Math.max(picker.getZoom(), 16));
    }

    function ensurePicker() {
        if (picker) {
            picker.invalidateSize();
            return;
        }

        picker = L.map('kandangPicker', { zoomControl: true }).setView(current, 16);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap'
        }).addTo(picker);

        const homeIcon = L.divIcon({
            className: '',
            html: '<div class="map-pin home"><i class="fa-solid fa-house"></i></div>',
            iconSize: [30, 30],
            iconAnchor: [15, 15]
        });

        marker = L.marker(current, { icon: homeIcon, draggable: true }).addTo(picker);
        circle = L.circle(current, {
            radius: Number(radiusNumber.value) || DEFAULT_RADIUS,
            color: '#16a34a',
            weight: 2,
            dashArray: '7 7',
            fillColor: '#22c55e',
            fillOpacity: 0.12
        }).addTo(picker);

        picker.on('click', (event) => setPoint(event.latlng.lat, event.latlng.lng));
        marker.on('dragend', () => {
            const pos = marker.getLatLng();
            setPoint(pos.lat, pos.lng);
        });

        setTimeout(() => picker.invalidateSize(), 180);
    }

    function resetForm() {
        form.reset();
        idField.value = '';
        title.textContent = 'Tambah Kandang';
        current = { lat: DEFAULT_CENTER[0], lng: DEFAULT_CENTER[1] };
        radiusNumber.value = DEFAULT_RADIUS;
        syncRadius(DEFAULT_RADIUS);
        setPoint(current.lat, current.lng);
        if (picker && circle) circle.setRadius(DEFAULT_RADIUS);
        if (picker) picker.setView(current, 16);
    }

    document.getElementById('btnAddKandang').addEventListener('click', () => {
        resetForm();
        if (owner) owner.value = '';
        openModal(modal);
        setTimeout(ensurePicker, 120);
    });

    document.querySelectorAll('[data-edit-kandang]').forEach((button) => {
        button.addEventListener('click', () => {
            const data = JSON.parse(button.dataset.editKandang);

            form.reset();
            idField.value = data.id;
            title.textContent = 'Edit Kandang: ' + data.nama;
            nama.value = data.nama || '';
            lokasi.value = data.lokasi || '';
            if (owner) owner.value = data.pemilik_id || '';
            bufferInput.value = data.buffer || 0;
            radiusNumber.value = data.radius || DEFAULT_RADIUS;
            syncRadius(data.radius || DEFAULT_RADIUS);
            current = { lat: data.lat, lng: data.lng };

            openModal(modal);
            setTimeout(() => {
                ensurePicker();
                setPoint(data.lat, data.lng);
                picker.setView(current, 17);
            }, 140);
        });
    });

    radiusRange.addEventListener('input', () => {
        radiusNumber.value = radiusRange.value;
        syncRadius(radiusRange.value);
    });

    radiusNumber.addEventListener('input', () => {
        syncRadius(radiusNumber.value);
    });

    modal.addEventListener('modal:open', () => {
        setTimeout(ensurePicker, 150);
    });
})();
JS;

require __DIR__ . '/inc/footer.php';
