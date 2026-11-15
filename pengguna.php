<?php
require_once __DIR__ . '/config/app.php';
require_admin();

$page_title = 'Pengguna';
$active     = 'pengguna';
$page_icon  = 'fa-users';

$users = [];
$result = $conn->query('SELECT id, nama, username, role, created_at FROM users ORDER BY role ASC, nama ASC');
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

$me = current_user();

require __DIR__ . '/inc/header.php';
?>

<div class="page-head">
    <div class="page-head-text">
        <h2>Manajemen Pengguna</h2>
        <p>Admin dapat mengelola seluruh data; petani hanya melihat kandang miliknya.</p>
    </div>
    <div class="btn-row">
        <button class="btn btn-primary" type="button" id="btnAddUser">
            <i class="fa-solid fa-user-plus"></i> Tambah Pengguna
        </button>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fa-solid fa-users"></i> Daftar Pengguna</h3>
        <div class="card-tools"><span class="badge badge-muted"><?= count($users) ?> akun</span></div>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Pengguna</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Dibuat</th>
                    <th class="text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <?php
                    $payload = [
                        'id'       => (int) $user['id'],
                        'nama'     => $user['nama'],
                        'username' => $user['username'],
                        'role'     => $user['role'],
                    ];
                    ?>
                    <tr>
                        <td>
                            <div class="cell-user">
                                <span class="avatar"><?= e(initials($user['nama'])) ?></span>
                                <div>
                                    <strong><?= e($user['nama']) ?></strong>
                                    <small><?= (int) $user['id'] === (int) $me['id'] ? 'Akun Anda' : 'Pengguna sistem' ?></small>
                                </div>
                            </div>
                        </td>
                        <td class="num"><?= e($user['username']) ?></td>
                        <td>
                            <?php if ($user['role'] === 'admin'): ?>
                                <span class="badge badge-dark"><i class="fa-solid fa-shield-halved"></i> Admin</span>
                            <?php else: ?>
                                <span class="badge badge-success"><i class="fa-solid fa-seedling"></i> Petani</span>
                            <?php endif; ?>
                        </td>
                        <td class="muted small"><?= e(format_tanggal($user['created_at'], false)) ?></td>
                        <td>
                            <div class="actions">
                                <button class="icon-btn" type="button" title="Edit"
                                        data-edit-user="<?= e(json_encode($payload, JSON_UNESCAPED_UNICODE)) ?>">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <?php if ((int) $user['id'] !== (int) $me['id']): ?>
                                    <form method="post" action="<?= e(url('actions/pengguna.php')) ?>"
                                          data-confirm="Hapus pengguna <?= e($user['nama']) ?>? Kandang miliknya akan menjadi tanpa pemilik."
                                          style="display:inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="hapus">
                                        <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                                        <button class="icon-btn danger" type="submit" title="Hapus">
                                            <i class="fa-solid fa-trash"></i>
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
</div>

<!-- Modal pengguna -->
<div class="modal" id="modalPengguna" role="dialog" aria-modal="true">
    <div class="modal-card">
        <form method="post" action="<?= e(url('actions/pengguna.php')) ?>" data-no-lock>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="simpan">
            <input type="hidden" name="id" id="userId" value="">

            <div class="modal-header">
                <div>
                    <h3 id="modalPenggunaTitle">Tambah Pengguna</h3>
                    <p>Password minimal 6 karakter.</p>
                </div>
                <button class="icon-btn modal-close" type="button" data-modal-close>
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="modal-body">
                <div class="field">
                    <label for="userNama">Nama lengkap <span class="req">*</span></label>
                    <input class="input" id="userNama" name="nama" required maxlength="100">
                </div>

                <div class="field">
                    <label for="userUsername">Username <span class="req">*</span></label>
                    <input class="input" id="userUsername" name="username" required maxlength="50"
                           pattern="[a-z0-9._\-]{3,50}" placeholder="cth. petani2">
                    <span class="help">Huruf kecil, angka, titik, strip, atau garis bawah.</span>
                </div>

                <div class="field">
                    <label for="userRole">Role</label>
                    <select class="select" id="userRole" name="role">
                        <option value="petani">Petani</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <div class="field">
                    <label for="userPassword">Password <span id="passwordReq" class="req">*</span></label>
                    <input class="input" id="userPassword" name="password" type="password"
                           minlength="6" placeholder="Minimal 6 karakter">
                    <span class="help" id="passwordHelp">Wajib diisi untuk pengguna baru.</span>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-outline" type="button" data-modal-close>Batal</button>
                <button class="btn btn-primary" type="submit">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan Pengguna
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$page_script = <<<'JS'
(function () {
    const modal = document.getElementById('modalPengguna');
    const form = modal.querySelector('form');
    const idField = document.getElementById('userId');
    const title = document.getElementById('modalPenggunaTitle');
    const nama = document.getElementById('userNama');
    const username = document.getElementById('userUsername');
    const role = document.getElementById('userRole');
    const password = document.getElementById('userPassword');
    const passwordReq = document.getElementById('passwordReq');
    const passwordHelp = document.getElementById('passwordHelp');

    function setMode(isEdit) {
        passwordReq.style.display = isEdit ? 'none' : 'inline';
        password.required = !isEdit;
        passwordHelp.textContent = isEdit
            ? 'Kosongkan jika tidak ingin mengubah password.'
            : 'Wajib diisi untuk pengguna baru.';
    }

    document.getElementById('btnAddUser').addEventListener('click', () => {
        form.reset();
        idField.value = '';
        title.textContent = 'Tambah Pengguna';
        setMode(false);
        openModal(modal);
    });

    document.querySelectorAll('[data-edit-user]').forEach((button) => {
        button.addEventListener('click', () => {
            const data = JSON.parse(button.dataset.editUser);
            form.reset();
            idField.value = data.id;
            title.textContent = 'Edit Pengguna: ' + data.nama;
            nama.value = data.nama;
            username.value = data.username;
            role.value = data.role;
            setMode(true);
            openModal(modal);
        });
    });
})();
JS;

require __DIR__ . '/inc/footer.php';
