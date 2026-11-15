<?php
require_once __DIR__ . '/config/app.php';
require_login();

$page_title = 'Simulator GPS';
$active     = 'simulator';
$page_icon  = 'fa-satellite-dish';

$scope = scope_kandang('k');

$devices = [];
$result = $conn->query(
    "SELECT d.id, d.kode, a.nama AS animal_nama, a.kode AS animal_kode,
            k.nama AS kandang_nama, k.center_lat, k.center_lng, k.radius_m, k.buffer_m
     FROM devices d
     INNER JOIN animals a ON a.id = d.animal_id
     INNER JOIN kandangs k ON k.id = a.kandang_id
     WHERE d.is_active = 1 AND $scope
     ORDER BY d.kode"
);
while ($row = $result->fetch_assoc()) {
    $devices[] = $row;
}

$first = $devices[0] ?? null;

require __DIR__ . '/inc/header.php';
?>

<div class="page-head">
    <div class="page-head-text">
        <h2>Simulasi Pengiriman Data GPS</h2>
        <p>Uji alur telemetry &rarr; Haversine &rarr; peta &rarr; peringatan tanpa memerlukan perangkat keras.</p>
    </div>
    <div class="btn-row">
        <a class="btn btn-outline" href="<?= e(url('perangkat.php')) ?>">
            <i class="fa-solid fa-microchip"></i> Kelola Perangkat
        </a>
    </div>
</div>

<?php if (!$devices): ?>
    <div class="card">
        <div class="empty-state">
            <i class="fa-solid fa-microchip"></i>
            <strong>Belum ada perangkat aktif</strong>
            <p>Tambahkan perangkat dan hubungkan ke ternak agar simulator dapat digunakan.</p>
            <a class="btn btn-primary mt-2" href="<?= e(url('perangkat.php')) ?>">
                <i class="fa-solid fa-plus"></i> Tambah Perangkat
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="sim-grid">
        <div>
            <div class="card mb-3">
                <div class="card-header">
                    <h3><i class="fa-solid fa-sliders"></i> Kontrol Simulasi</h3>
                </div>
                <div class="card-body">
                    <div class="field">
                        <label for="simDevice">Perangkat / ternak</label>
                        <select class="select" id="simDevice">
                            <?php foreach ($devices as $device): ?>
                                <option value="<?= (int) $device['id'] ?>"
                                        data-center-lat="<?= e($device['center_lat']) ?>"
                                        data-center-lng="<?= e($device['center_lng']) ?>"
                                        data-radius="<?= (int) $device['radius_m'] ?>"
                                        data-buffer="<?= (int) $device['buffer_m'] ?>"
                                        data-animal="<?= e($device['animal_nama']) ?>"
                                        data-kandang="<?= e($device['kandang_nama']) ?>">
                                    <?= e($device['kode']) ?> — <?= e($device['animal_nama']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="settings-note mb-3">
                        <i class="fa-solid fa-location-crosshairs"></i>
                        <span>
                            Pusat kandang <strong id="simCenter">-</strong><br>
                            Radius aman <strong id="simRadius">-</strong> (+ buffer <span id="simBuffer">-</span>)
                        </span>
                    </div>

                    <div class="coord-grid">
                        <div class="field">
                            <label for="simLat">Latitude</label>
                            <input class="input" type="number" step="0.000001" id="simLat"
                                   value="<?= e($first['center_lat']) ?>">
                        </div>
                        <div class="field">
                            <label for="simLng">Longitude</label>
                            <input class="input" type="number" step="0.000001" id="simLng"
                                   value="<?= e($first['center_lng']) ?>">
                        </div>
                    </div>

                    <div class="field">
                        <label for="simAccuracy">Akurasi GPS (meter)</label>
                        <input class="input" type="number" min="0" max="200" id="simAccuracy" value="5">
                    </div>

                    <div class="btn-row">
                        <button class="btn btn-outline btn-sm" type="button" id="btnMyLocation">
                            <i class="fa-solid fa-location-dot"></i> Lokasi Saya
                        </button>
                        <button class="btn btn-outline btn-sm" type="button" data-preset="inside">
                            <i class="fa-solid fa-bullseye"></i> Tepat di pusat
                        </button>
                        <button class="btn btn-outline btn-sm" type="button" data-preset="edge">
                            <i class="fa-solid fa-arrows-to-dot"></i> Tepi radius
                        </button>
                        <button class="btn btn-outline btn-sm" type="button" data-preset="outside">
                            <i class="fa-solid fa-arrow-up-right-dots"></i> Di luar radius
                        </button>
                    </div>

                    <hr style="border:none;border-top:1px solid var(--border-soft);margin:16px 0">

                    <button class="btn btn-primary btn-block mb-2" type="button" id="btnSendPoint">
                        <i class="fa-solid fa-paper-plane"></i> Kirim Titik Ini
                    </button>
                    <button class="btn btn-outline btn-block" type="button" id="btnSimulateWalk">
                        <i class="fa-solid fa-person-walking"></i> Simulasi Jalan (15 titik)
                    </button>

                    <div id="simResult" class="mt-2"></div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3><i class="fa-solid fa-terminal"></i> Log Pengiriman</h3>
                    <div class="card-tools">
                        <button class="btn btn-sm btn-ghost" type="button" id="btnClearLog">Bersihkan</button>
                    </div>
                </div>
                <div class="card-body tight">
                    <div class="sim-log" id="simLog">
                        <div class="info">// Log pengiriman telemetry akan tampil di sini.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fa-solid fa-map-location-dot"></i> Peta Simulasi</h3>
                <div class="card-tools">
                    <span class="badge badge-info"><i class="fa-solid fa-hand-pointer"></i> Klik peta untuk memilih titik</span>
                </div>
            </div>
            <div class="card-body tight">
                <div id="simMap" class="map" style="height:560px"></div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($devices): ?>
<?php
$page_script = <<<'JS'
(function () {
    const utils = window.TernakMapUtils;

    const deviceEl = document.getElementById('simDevice');
    const latEl = document.getElementById('simLat');
    const lngEl = document.getElementById('simLng');
    const accEl = document.getElementById('simAccuracy');
    const logEl = document.getElementById('simLog');
    const resultEl = document.getElementById('simResult');
    const btnSend = document.getElementById('btnSendPoint');
    const btnWalk = document.getElementById('btnSimulateWalk');

    const liveMap = new LiveMap('simMap', {
        refreshMs: window.TERNAK.pollInterval,
        zoom: 17,
        onAlert: function (alert) {
            showToast(
                alert.tipe === 'keluar' ? 'Breach terdeteksi!' : 'Ternak kembali masuk',
                alert.animal_nama + ' - jarak ' + Number(alert.distance_m).toFixed(1) + ' m',
                alert.tipe === 'keluar' ? 'danger' : 'success',
                8000
            );
        }
    });

    let candidate = null;

    const candidateIcon = L.divIcon({
        className: '',
        html: '<div class="map-pin unknown"><i class="fa-solid fa-location-crosshairs"></i></div>',
        iconSize: [32, 32],
        iconAnchor: [16, 32]
    });

    function currentDevice() {
        const option = deviceEl.options[deviceEl.selectedIndex];
        return {
            id: Number(deviceEl.value),
            lat: Number(option.dataset.centerLat),
            lng: Number(option.dataset.centerLng),
            radius: Number(option.dataset.radius),
            buffer: Number(option.dataset.buffer),
            animal: option.dataset.animal,
            kandang: option.dataset.kandang
        };
    }

    function updateDeviceInfo() {
        const device = currentDevice();
        document.getElementById('simCenter').textContent =
            device.lat.toFixed(5) + ', ' + device.lng.toFixed(5);
        document.getElementById('simRadius').textContent = device.radius + ' m';
        document.getElementById('simBuffer').textContent = device.buffer + ' m';

        latEl.value = device.lat.toFixed(6);
        lngEl.value = device.lng.toFixed(6);
        setCandidate(device.lat, device.lng);

        if (liveMap.map) {
            liveMap.map.setView([device.lat, device.lng], 17);
        }
    }

    function setCandidate(lat, lng) {
        if (!liveMap.map) return;
        const pos = [Number(lat), Number(lng)];
        if (candidate) {
            candidate.setLatLng(pos);
        } else {
            candidate = L.marker(pos, { icon: candidateIcon, draggable: true }).addTo(liveMap.map);
            candidate.on('dragend', () => {
                const p = candidate.getLatLng();
                latEl.value = p.lat.toFixed(6);
                lngEl.value = p.lng.toFixed(6);
            });
        }
    }

    function log(message, type) {
        const time = new Date().toLocaleTimeString('id-ID', { hour12: false });
        const line = document.createElement('div');
        line.className = type || '';
        line.textContent = '[' + time + '] ' + message;
        logEl.prepend(line);

        while (logEl.children.length > 80) {
            logEl.lastElementChild.remove();
        }
    }

    function randomPoint(centerLat, centerLng, radius) {
        const angle = Math.random() * Math.PI * 2;
        const distance = radius * (0.35 + Math.random() * 1.25);
        const dLat = (distance * Math.cos(angle)) / 111320;
        const dLng = (distance * Math.sin(angle)) / (111320 * Math.cos(centerLat * Math.PI / 180));
        return {
            lat: centerLat + dLat,
            lng: centerLng + dLng,
            distance: distance
        };
    }

    async function sendPoint(lat, lng, accuracy) {
        const response = await fetch(window.TERNAK.baseUrl + 'api/simulate.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': window.TERNAK.csrf
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                device_id: currentDevice().id,
                latitude: Number(lat),
                longitude: Number(lng),
                accuracy: Number(accuracy) || 5
            })
        });

        const data = await response.json().catch(() => null);

        if (!response.ok || !data || !data.ok) {
            throw new Error(data && data.message ? data.message : 'Gagal mengirim data');
        }

        return data;
    }

    function showResult(data) {
        const color = data.inside ? 'success' : 'danger';
        resultEl.innerHTML =
            '<div class="flash flash-' + color + '" style="margin-bottom:0">' +
                '<i class="fa-solid ' + (data.inside ? 'fa-circle-check' : 'fa-triangle-exclamation') + '"></i>' +
                '<span><strong>' + (data.inside ? 'Di dalam radius' : 'Di luar radius') + '</strong> — ' +
                utils.formatMeters(data.distance_m) + ' dari pusat (batas ' + data.limit_m + ' m)' +
                (data.alert ? ' · alert <strong>' + data.alert + '</strong>' : '') +
                '</span></div>';
    }

    async function sendCurrent() {
        const device = currentDevice();
        const lat = Number(latEl.value);
        const lng = Number(lngEl.value);

        if (!lat || !lng) {
            showToast('Koordinat tidak valid', 'Isi latitude dan longitude terlebih dahulu.', 'warning');
            return;
        }

        btnSend.disabled = true;
        try {
            const data = await sendPoint(lat, lng, accEl.value);
            setCandidate(lat, lng);
            showResult(data);
            log(
                (data.inside ? 'OK' : 'BREACH') + ' ' + data.animal + ' @ ' + lat.toFixed(6) + ', ' + lng.toFixed(6) +
                ' | jarak ' + data.distance_m + ' m' + (data.alert ? ' | alert: ' + data.alert : ''),
                data.inside ? 'ok' : 'bad'
            );
            liveMap.refresh();
        } catch (error) {
            showToast('Gagal mengirim', error.message, 'danger');
            log('ERROR ' + error.message, 'bad');
        } finally {
            btnSend.disabled = false;
        }
    }

    async function simulateWalk() {
        const device = currentDevice();
        btnWalk.disabled = true;
        resultEl.innerHTML = '';
        log('Mulai simulasi 15 titik pada ' + device.kandang + ' (radius ' + device.radius + ' m)', 'info');

        try {
            for (let i = 0; i < 15; i++) {
                const point = randomPoint(device.lat, device.lng, device.radius);
                const data = await sendPoint(point.lat, point.lng, 4 + Math.random() * 6);

                latEl.value = point.lat.toFixed(6);
                lngEl.value = point.lng.toFixed(6);
                setCandidate(point.lat, point.lng);
                showResult(data);
                log(
                    '#' + (i + 1) + ' ' + (data.inside ? 'dalam' : 'LUAR') +
                    ' | ' + data.distance_m + ' m' + (data.alert ? ' | alert: ' + data.alert : ''),
                    data.inside ? 'ok' : 'bad'
                );

                await new Promise((resolve) => setTimeout(resolve, 700));
            }

            liveMap.refresh();
            showToast('Simulasi selesai', '15 titik berhasil dikirim ke server.', 'success');
            log('Simulasi selesai.', 'info');
        } catch (error) {
            showToast('Simulasi terhenti', error.message, 'danger');
            log('ERROR ' + error.message, 'bad');
        } finally {
            btnWalk.disabled = false;
        }
    }

    function applyPreset(type) {
        const device = currentDevice();
        let distance = 0;
        if (type === 'edge') distance = Math.max(1, device.radius - 2);
        if (type === 'outside') distance = device.radius + device.buffer + 25;

        const angle = Math.random() * Math.PI * 2;
        const dLat = (distance * Math.cos(angle)) / 111320;
        const dLng = (distance * Math.sin(angle)) / (111320 * Math.cos(device.lat * Math.PI / 180));
        const lat = device.lat + dLat;
        const lng = device.lng + dLng;

        latEl.value = lat.toFixed(6);
        lngEl.value = lng.toFixed(6);
        setCandidate(lat, lng);

        if (liveMap.map) {
            liveMap.map.setView([lat, lng], 18);
        }
    }

    deviceEl.addEventListener('change', updateDeviceInfo);
    btnSend.addEventListener('click', sendCurrent);
    btnWalk.addEventListener('click', simulateWalk);

    document.querySelectorAll('[data-preset]').forEach((button) => {
        button.addEventListener('click', () => applyPreset(button.dataset.preset));
    });

    document.getElementById('btnMyLocation').addEventListener('click', () => {
        if (!navigator.geolocation) {
            showToast('Tidak didukung', 'Browser tidak mendukung geolokasi.', 'warning');
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                latEl.value = position.coords.latitude.toFixed(6);
                lngEl.value = position.coords.longitude.toFixed(6);
                accEl.value = Math.round(position.coords.accuracy);
                setCandidate(position.coords.latitude, position.coords.longitude);
                liveMap.map.setView([position.coords.latitude, position.coords.longitude], 18);
                showToast('Lokasi ditemukan', 'Koordinat perangkat Anda siap dikirim.', 'info');
            },
            (error) => showToast('Gagal mengambil lokasi', error.message, 'danger'),
            { enableHighAccuracy: true, timeout: 10000 }
        );
    });

    document.getElementById('btnClearLog').addEventListener('click', () => {
        logEl.innerHTML = '<div class="info">// Log dibersihkan.</div>';
    });

    latEl.addEventListener('input', () => setCandidate(latEl.value, lngEl.value));
    lngEl.addEventListener('input', () => setCandidate(latEl.value, lngEl.value));

    liveMap.map.on('click', (event) => {
        latEl.value = event.latlng.lat.toFixed(6);
        lngEl.value = event.latlng.lng.toFixed(6);
        setCandidate(event.latlng.lat, event.latlng.lng);
    });

    setTimeout(() => {
        liveMap.map.invalidateSize();
        updateDeviceInfo();
    }, 250);
})();
JS;

require __DIR__ . '/inc/footer.php';
?>
<?php endif; ?>
