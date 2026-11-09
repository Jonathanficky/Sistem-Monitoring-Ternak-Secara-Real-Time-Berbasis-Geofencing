<?php
require_once __DIR__ . '/config/app.php';
require_login();

$page_title = 'Data Ternak';
$active     = 'ternak';
$page_icon  = 'ayam';

$scope = scope_kandang('k');
$q     = trim($_GET['q'] ?? '');
$kandangFilter = (int) ($_GET['kandang'] ?? 0);

$where  = [$scope];
$params = [];
$types  = '';

if ($q !== '') {
    $where[]  = '(a.kode LIKE ? OR a.nama LIKE ?)';
    $like     = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}

if ($kandangFilter > 0) {
    $where[]  = 'a.kandang_id = ?';
    $params[] = $kandangFilter;
    $types   .= 'i';
}

$sql = "SELECT a.*, k.nama AS kandang_nama, k.radius_m, k.buffer_m,
               d.kode AS device_kode, d.last_seen_at AS device_last_seen
        FROM animals a
        INNER JOIN kandangs k ON k.id = a.kandang_id
        LEFT JOIN devices d ON d.id = (
            SELECT d2.id FROM devices d2
            WHERE d2.animal_id = a.id AND d2.is_active = 1
            ORDER BY d2.id ASC LIMIT 1
        )
        WHERE " . implode(' AND ', $where) . "
        ORDER BY a.nama ASC";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$animals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$kandangs = [];
$result = $conn->query(
    "SELECT k.id, k.nama FROM kandangs k WHERE $scope ORDER BY k.nama"
);
while ($row = $result->fetch_assoc()) {
    $kandangs[] = $row;
}

$offlineAfter = (int) get_setting($conn, 'offline_after', '90');

require __DIR__ . '/inc/header.php';
?>

<div class="page-head">
    <div class="page-head-text">
        <h2>Manajemen Ternak</h2>
        <p>Kelola identitas ternak, kandang, dan keterhubungan perangkat GPS.</p>
    </div>
    <div class="btn-row">
        <button class="btn btn-primary" type="button" id="btnAddAnimal">
            <i class="fa-solid fa-plus"></i> Tambah Ternak
        </button>
    </div>
</div>

<div class="card">
    <form class="filter-bar" method="get" action="<?= e(url('ternak.php')) ?>">
        <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Cari kode atau nama ternak...">
        </div>

        <select class="select" name="kandang">
            <option value="">Semua kandang</option>
            <?php foreach ($kandangs as $kandang): ?>
                <option value="<?= (int) $kandang['id'] ?>" <?= $kandangFilter === (int) $kandang['id'] ? 'selected' : '' ?>>
                    <?= e($kandang['nama']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button class="btn btn-outline" type="submit"><i class="fa-solid fa-filter"></i> Filter</button>
        <?php if ($q !== '' || $kandangFilter > 0): ?>
            <a class="btn btn-ghost" href="<?= e(url('ternak.php')) ?>">Reset</a>
        <?php endif; ?>

        <span class="small muted" style="margin-left:auto"><?= count($animals) ?> ternak ditemukan</span>
    </form>

    <?php if (!$animals): ?>
        <div class="empty-state">
            <?= ikon_ayam() ?>
            <strong>Belum ada data ternak</strong>
            <p>Klik "Tambah Ternak" untuk mulai memantau ternak Anda.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Ternak</th>
                        <th>Jenis</th>
                        <th>Kandang</th>
                        <th>Perangkat</th>
                        <th>Status</th>
                        <th class="text-right">Jarak</th>
                        <th>Update</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($animals as $animal): ?>
                        <?php
                        $statusClass = $animal['is_inside'] === null ? 'unknown' : ($animal['is_inside'] ? 'inside' : 'outside');
                        $statusText  = $animal['is_inside'] === null ? 'Belum ada data' : ($animal['is_inside'] ? 'Dalam radius' : 'Di luar radius');
                        $online      = is_online($animal['device_last_seen'], $offlineAfter);
                        $payload     = [
                            'id'         => (int) $animal['id'],
                            'kode'       => $animal['kode'],
                            'nama'       => $animal['nama'],
                            'jenis'      => $animal['jenis'],
                            'kelamin'    => $animal['kelamin'],
                            'umur'       => $animal['umur_bulan'],
                            'kandang_id' => (int) $animal['kandang_id'],
                            'foto'       => $animal['foto'] ? url($animal['foto']) : '',
                        ];
                        ?>
                        <tr>
                            <td>
                                <div class="cell-user">
                                    <?php if ($animal['foto']): ?>
                                        <img class="animal-thumb" src="<?= e(url($animal['foto'])) ?>" alt="<?= e($animal['nama']) ?>">
                                    <?php else: ?>
                                        <span class="thumb-fallback"><?= ikon_ayam() ?></span>
                                    <?php endif; ?>
                                    <div>
                                        <strong><?= e($animal['nama']) ?></strong>
                                        <small><?= e($animal['kode']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td class="muted"><?= e($animal['jenis']) ?><br><span class="small"><?= e($animal['kelamin']) ?><?= $animal['umur_bulan'] ? ' · ' . (int) $animal['umur_bulan'] . ' bln' : '' ?></span></td>
                            <td>
                                <div class="strong"><?= e($animal['kandang_nama']) ?></div>
                                <span class="small muted">radius <?= (int) $animal['radius_m'] ?> m</span>
                            </td>
                            <td>
                                <?php if ($animal['device_kode']): ?>
                                    <span class="inline-flag">
                                        <span class="dot <?= $online ? 'dot-live' : 'dot-off' ?>"></span>
                                        <span class="small"><?= e($animal['device_kode']) ?></span>
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Belum terhubung</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="status-pill status-<?= $statusClass ?>"><?= e($statusText) ?></span></td>
                            <td class="text-right num"><?= e(format_jarak($animal['last_distance_m'] !== null ? (float) $animal['last_distance_m'] : null)) ?></td>
                            <td class="muted small"><?= e(time_ago($animal['last_seen_at'])) ?></td>
                            <td>
                                <div class="actions">
                                    <a class="icon-btn" href="<?= e(url('riwayat.php?animal_id=' . (int) $animal['id'])) ?>" title="Riwayat posisi">
                                        <i class="fa-solid fa-chart-line"></i>
                                    </a>
                                    <button class="icon-btn" type="button" title="Edit"
                                            data-edit-animal="<?= e(json_encode($payload, JSON_UNESCAPED_UNICODE)) ?>">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <form method="post" action="<?= e(url('actions/ternak.php')) ?>"
                                          data-confirm="Hapus ternak <?= e($animal['nama']) ?>? Riwayat posisi dan peringatan juga akan terhapus."
                                          style="display:inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="hapus">
                                        <input type="hidden" name="id" value="<?= (int) $animal['id'] ?>">
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

<!-- Modal tambah/edit ternak -->
<div class="modal" id="modalTernak" role="dialog" aria-modal="true" aria-labelledby="modalTernakTitle">
    <div class="modal-card modal-lg">
        <form method="post" action="<?= e(url('actions/ternak.php')) ?>" enctype="multipart/form-data" data-no-lock>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="simpan">
            <input type="hidden" name="id" id="animalId" value="">

            <div class="modal-header">
                <div>
                    <h3 id="modalTernakTitle">Tambah Ternak</h3>
                    <p>Data ini digunakan untuk menghubungkan perangkat GPS dengan ternak.</p>
                </div>
                <button class="icon-btn modal-close" type="button" data-modal-close>
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="modal-body">
                <div class="form-grid">
                    <div class="field">
                        <label for="kode">Kode ternak <span class="req">*</span></label>
                        <input class="input" id="kode" name="kode" required maxlength="30" placeholder="cth. TRK-003">
                        <span class="help">Kode unik, digunakan pada laporan.</span>
                    </div>

                    <div class="field">
                        <label for="nama">Nama ternak <span class="req">*</span></label>
                        <input class="input" id="nama" name="nama" required maxlength="80" placeholder="cth. Si Belang">
                    </div>

                    <div class="field">
                        <label for="jenis">Jenis <span class="req">*</span></label>
                        <select class="select" id="jenis" name="jenis" required>
                            <?php foreach (['Sapi', 'Kambing', 'Domba', 'Kerbau', 'Ayam', 'Lainnya'] as $jenis): ?>
                                <option value="<?= e($jenis) ?>"><?= e($jenis) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="kelamin">Kelamin</label>
                        <select class="select" id="kelamin" name="kelamin">
                            <option value="Betina">Betina</option>
                            <option value="Jantan">Jantan</option>
                        </select>
                    </div>

                    <div class="field">
                        <label for="umur">Umur (bulan)</label>
                        <input class="input" id="umur" name="umur_bulan" type="number" min="0" max="300" placeholder="cth. 18">
                    </div>

                    <div class="field">
                        <label for="kandang_id">Kandang <span class="req">*</span></label>
                        <select class="select" id="kandang_id" name="kandang_id" required>
                            <?php foreach ($kandangs as $kandang): ?>
                                <option value="<?= (int) $kandang['id'] ?>"><?= e($kandang['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field span-2">
                        <label for="foto">Foto ternak (opsional)</label>
                        <div class="input-group">
                            <input class="input" id="foto" name="foto" type="file" accept="image/jpeg,image/png,image/webp" data-preview="#fotoPreview">
                            <span class="animal-thumb" id="fotoPreview" style="width:52px;height:52px;display:grid;place-items:center;overflow:hidden">
                                <i class="fa-solid fa-image"></i>
                            </span>
                        </div>
                        <span class="help">Format JPG/PNG/WEBP, maksimal 3 MB.</span>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-outline" type="button" data-modal-close>Batal</button>
                <button class="btn btn-primary" type="submit">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan Ternak
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$page_script = <<<'JS'
(function () {
    const modal = document.getElementById('modalTernak');
    const form = modal.querySelector('form');
    const title = document.getElementById('modalTernakTitle');
    const idField = document.getElementById('animalId');
    const fields = {
        kode: document.getElementById('kode'),
        nama: document.getElementById('nama'),
        jenis: document.getElementById('jenis'),
        kelamin: document.getElementById('kelamin'),
        umur: document.getElementById('umur'),
        kandang_id: document.getElementById('kandang_id')
    };
    const preview = document.getElementById('fotoPreview');
    const fileInput = document.getElementById('foto');

    function resetForm() {
        form.reset();
        idField.value = '';
        title.textContent = 'Tambah Ternak';
        preview.innerHTML = '<i class="fa-solid fa-image"></i>';
    }

    document.getElementById('btnAddAnimal').addEventListener('click', () => {
        resetForm();
        openModal(modal);
    });

    document.querySelectorAll('[data-edit-animal]').forEach((button) => {
        button.addEventListener('click', () => {
            const animal = JSON.parse(button.dataset.editAnimal);

            form.reset();
            idField.value = animal.id;
            title.textContent = 'Edit Ternak: ' + animal.nama;

            fields.kode.value = animal.kode || '';
            fields.nama.value = animal.nama || '';
            fields.jenis.value = animal.jenis || 'Sapi';
            fields.kelamin.value = animal.kelamin || 'Betina';
            fields.umur.value = animal.umur ?? '';
            fields.kandang_id.value = animal.kandang_id;

            preview.innerHTML = animal.foto
                ? '<img src="' + animal.foto + '" alt="Foto" style="width:100%;height:100%;object-fit:cover">'
                : '<i class="fa-solid fa-image"></i>';

            openModal(modal);
        });
    });

    fileInput.addEventListener('change', () => {
        if (!fileInput.files.length) {
            preview.innerHTML = idField.value ? preview.innerHTML : '<i class="fa-solid fa-image"></i>';
        }
    });
})();
JS;

require __DIR__ . '/inc/footer.php';
