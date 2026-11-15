<?php
require_once __DIR__ . '/config/app.php';
require_login();

$page_title = 'Peta Live';
$active     = 'peta';
$page_icon  = 'fa-map-location-dot';
$full_width = true;

$kandangs = [];
$scope    = scope_kandang('k');
$result   = $conn->query(
    "SELECT k.id, k.nama, COUNT(a.id) AS jumlah
     FROM kandangs k
     LEFT JOIN animals a ON a.kandang_id = k.id
     WHERE $scope
     GROUP BY k.id, k.nama
     ORDER BY k.nama"
);
while ($row = $result->fetch_assoc()) {
    $kandangs[] = $row;
}

require __DIR__ . '/inc/header.php';
?>

<div class="map-shell">
    <div id="liveMap" class="map"></div>

    <div class="map-panel">
        <div class="map-panel-head">
            <h3><i class="fa-solid fa-satellite-dish text-green"></i> Posisi Ternak</h3>
            <span class="badge badge-muted" id="animalCount">0</span>
            <button class="icon-btn" type="button" id="btnRefresh" title="Muat ulang">
                <i class="fa-solid fa-rotate"></i>
            </button>
        </div>

        <div style="padding:10px 12px;border-bottom:1px solid var(--border-soft)">
            <select class="select" id="filterKandang">
                <option value="">Semua kandang</option>
                <?php foreach ($kandangs as $kandang): ?>
                    <option value="<?= (int) $kandang['id'] ?>">
                        <?= e($kandang['nama']) ?> (<?= (int) $kandang['jumlah'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <div class="flex-between" style="margin-top:9px">
                <span class="badge badge-success"><i class="fa-solid fa-circle"></i> <span id="insideCount">0</span> dalam</span>
                <span class="badge badge-danger"><i class="fa-solid fa-circle"></i> <span id="outsideCount">0</span> luar</span>
                <span class="badge badge-muted"><i class="fa-solid fa-circle"></i> <span id="unknownCount">0</span> n/a</span>
            </div>
        </div>

        <div class="map-panel-body" id="liveList">
            <div class="empty-state" style="padding:26px 10px">
                <i class="fa-solid fa-spinner fa-spin"></i>
                <p>Memuat posisi ternak...</p>
            </div>
        </div>
    </div>

    <div class="map-legend">
        <div class="legend-row"><span class="legend-dot green"></span> Di dalam radius</div>
        <div class="legend-row"><span class="legend-dot red"></span> Di luar radius</div>
        <div class="legend-row"><span class="legend-circle"></span> Geofence kandang</div>
    </div>

    <div class="map-update">
        <i class="fa-solid fa-arrows-rotate"></i>
        <span id="lastUpdate">menunggu data...</span>
    </div>
</div>

<?php
$page_script = <<<'JS'
(function () {
    const utils = window.TernakMapUtils;
    const listEl = document.getElementById('liveList');
    const filterEl = document.getElementById('filterKandang');
    let lastData = { animals: [], kandangs: [] };

    const map = new LiveMap('liveMap', {
        refreshMs: window.TERNAK.pollInterval,
        zoom: 16,

        onUpdate: function (data) {
            lastData = data;
            render();

            const el = document.getElementById('lastUpdate');
            if (el) {
                el.textContent = 'Diperbarui ' + new Date().toLocaleTimeString('id-ID', { hour12: false });
            }
        },

        onAlert: function (alert) {
            showToast(
                alert.tipe === 'keluar' ? 'Geofence breach!' : 'Ternak kembali masuk',
                alert.animal_nama + ' - ' + alert.kandang_nama + ' (' + Number(alert.distance_m).toFixed(1) + ' m dari pusat)',
                alert.tipe === 'keluar' ? 'danger' : 'success',
                10000
            );
        },

        onError: function (error) {
            const el = document.getElementById('lastUpdate');
            if (el) el.textContent = 'Gagal memuat: ' + error.message;
        }
    });

    function render() {
        const animals = lastData.animals || [];
        const stats = lastData.stats || {};
        const filter = filterEl.value;

        document.getElementById('animalCount').textContent = animals.length;
        document.getElementById('insideCount').textContent = stats.inside ?? 0;
        document.getElementById('outsideCount').textContent = stats.outside ?? 0;
        document.getElementById('unknownCount').textContent = stats.unknown ?? 0;

        const visible = animals.filter((a) => !filter || String(a.kandang_id) === filter);

        if (!visible.length) {
            listEl.innerHTML = '<div class="empty-state" style="padding:26px 10px">' +
                window.TernakIcons.ayam + '<strong>Belum ada ternak</strong>' +
                '<p>Tambahkan ternak dan hubungkan perangkat GPS.</p></div>';
            return;
        }

        listEl.innerHTML = visible.map((animal) => {
            const meta = utils.statusMeta(animal);
            const distance = animal.distance_m === null ? '-' : utils.formatMeters(animal.distance_m);
            return '<div class="live-item ' + meta.key + '" data-animal-id="' + animal.id + '">' +
                '<span class="li-icon">' + window.TernakIcons.ayam + '</span>' +
                '<div class="li-main">' +
                    '<strong>' + utils.escapeHtml(animal.nama) + ' <span class="muted">· ' + utils.escapeHtml(animal.kode) + '</span></strong>' +
                    '<small>' + utils.escapeHtml(animal.kandang_nama) + ' · ' + (animal.device_online ? 'online' : 'offline') + '</small>' +
                '</div>' +
                '<span class="li-dist">' + distance + '</span>' +
            '</div>';
        }).join('');
    }

    listEl.addEventListener('click', (event) => {
        const item = event.target.closest('[data-animal-id]');
        if (item) map.focusAnimal(Number(item.dataset.animalId));
    });

    filterEl.addEventListener('change', render);

    document.getElementById('btnRefresh').addEventListener('click', async (event) => {
        const icon = event.currentTarget.querySelector('i');
        icon.classList.add('fa-spin');
        await map.refresh();
        icon.classList.remove('fa-spin');
    });

})();
JS;

require __DIR__ . '/inc/footer.php';
