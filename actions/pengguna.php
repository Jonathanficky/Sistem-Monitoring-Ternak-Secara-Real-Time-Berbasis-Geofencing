<?php
require_once __DIR__ . '/../config/app.php';
require_admin();
verify_csrf();

$action = $_POST['action'] ?? '';
$me     = current_user();

try {
    switch ($action) {
        case 'simpan':
            $id       = (int) ($_POST['id'] ?? 0);
            $nama     = trim($_POST['nama'] ?? '');
            $username = strtolower(trim($_POST['username'] ?? ''));
            $role     = ($_POST['role'] ?? 'petani') === 'admin' ? 'admin' : 'petani';
            $password = $_POST['password'] ?? '';

            if ($nama === '' || $username === '') {
                throw new RuntimeException('Nama dan username wajib diisi.');
            }

            if (!preg_match('/^[a-z0-9._\-]{3,50}$/', $username)) {
                throw new RuntimeException('Username 3-50 karakter, hanya huruf kecil, angka, titik, strip, dan garis bawah.');
            }

            if ($id === (int) $me['id'] && $role !== 'admin') {
                throw new RuntimeException('Anda tidak dapat menurunkan role akun sendiri.');
            }

            $stmt = $conn->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
            $stmt->bind_param('si', $username, $id);
            $stmt->execute();
            $duplicate = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($duplicate) {
                throw new RuntimeException('Username "' . $username . '" sudah digunakan.');
            }

            if ($id > 0) {
                $stmt = $conn->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $existing = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$existing) {
                    throw new RuntimeException('Pengguna tidak ditemukan.');
                }

                if ($password !== '') {
                    if (strlen($password) < 6) {
                        throw new RuntimeException('Password minimal 6 karakter.');
                    }

                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare('UPDATE users SET nama = ?, username = ?, role = ?, password = ? WHERE id = ?');
                    $stmt->bind_param('ssssi', $nama, $username, $role, $hash, $id);
                } else {
                    $stmt = $conn->prepare('UPDATE users SET nama = ?, username = ?, role = ? WHERE id = ?');
                    $stmt->bind_param('sssi', $nama, $username, $role, $id);
                }

                $stmt->execute();
                $stmt->close();

                flash('success', 'Data pengguna "' . $nama . '" diperbarui.');
            } else {
                if (strlen($password) < 6) {
                    throw new RuntimeException('Password minimal 6 karakter untuk pengguna baru.');
                }

                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare('INSERT INTO users (nama, username, password, role) VALUES (?, ?, ?, ?)');
                $stmt->bind_param('ssss', $nama, $username, $hash, $role);
                $stmt->execute();
                $stmt->close();

                flash('success', 'Pengguna "' . $nama . '" berhasil ditambahkan.');
            }

            redirect('pengguna.php');

        case 'hapus':
            $id = (int) ($_POST['id'] ?? 0);

            if ($id === (int) $me['id']) {
                throw new RuntimeException('Anda tidak dapat menghapus akun sendiri.');
            }

            $stmt = $conn->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $target = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$target) {
                throw new RuntimeException('Pengguna tidak ditemukan.');
            }

            if ($target['role'] === 'admin') {
                $admins = (int) $conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'admin'")->fetch_assoc()['c'];
                if ($admins <= 1) {
                    throw new RuntimeException('Tidak dapat menghapus admin terakhir.');
                }
            }

            $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();

            flash('success', 'Pengguna "' . $target['nama'] . '" dihapus.');
            redirect('pengguna.php');

        default:
            throw new RuntimeException('Aksi tidak dikenali.');
    }
} catch (RuntimeException $error) {
    flash('danger', $error->getMessage());
    redirect('pengguna.php');
} catch (Throwable $error) {
    flash('danger', 'Terjadi kesalahan: ' . $error->getMessage());
    redirect('pengguna.php');
}
