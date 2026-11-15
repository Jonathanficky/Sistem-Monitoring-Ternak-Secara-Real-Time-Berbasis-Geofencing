<?php
require_once __DIR__ . '/../config/app.php';
require_login();
verify_csrf();

$action = $_POST['action'] ?? '';
$scope  = scope_kandang('k');

function find_device(mysqli $conn, int $id, string $scope): ?array
{
    if (is_admin()) {
        $sql = "SELECT d.*, a.kandang_id
                FROM devices d
                LEFT JOIN animals a ON a.id = d.animal_id
                WHERE d.id = ?
                LIMIT 1";
    } else {
        $sql = "SELECT d.*, a.kandang_id
                FROM devices d
                LEFT JOIN animals a ON a.id = d.animal_id
                LEFT JOIN kandangs k ON k.id = a.kandang_id
                WHERE d.id = ? AND $scope
                LIMIT 1";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

try {
    switch ($action) {
        case 'simpan':
            $id       = (int) ($_POST['id'] ?? 0);
            $kode     = strtoupper(trim($_POST['kode'] ?? ''));
            $animalId = (int) ($_POST['animal_id'] ?? 0);
            $aktif    = isset($_POST['is_active']) ? 1 : 0;

            if ($kode === '') {
                throw new RuntimeException('Kode perangkat wajib diisi.');
            }

            if (!preg_match('/^[A-Z0-9\-_]+$/', $kode)) {
                throw new RuntimeException('Kode perangkat hanya boleh huruf, angka, strip, dan garis bawah.');
            }

            if ($animalId > 0 && !fetch_animal($conn, $animalId)) {
                throw new RuntimeException('Ternak tidak ditemukan atau bukan milik akun Anda.');
            }

            if (!is_admin() && $animalId <= 0) {
                throw new RuntimeException('Petani wajib menghubungkan perangkat ke salah satu ternak.');
            }

            $stmt = $conn->prepare('SELECT id FROM devices WHERE kode = ? AND id <> ? LIMIT 1');
            $stmt->bind_param('si', $kode, $id);
            $stmt->execute();
            $duplicate = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($duplicate) {
                throw new RuntimeException('Kode perangkat "' . $kode . '" sudah dipakai.');
            }

            if ($id > 0) {
                if (!find_device($conn, $id, $scope)) {
                    throw new RuntimeException('Perangkat tidak ditemukan atau bukan milik akun Anda.');
                }

                $stmt = $conn->prepare(
                    'UPDATE devices SET kode = ?, animal_id = ?, is_active = ? WHERE id = ?'
                );
                $animalParam = $animalId > 0 ? $animalId : null;
                $stmt->bind_param('siii', $kode, $animalParam, $aktif, $id);
                $stmt->execute();
                $stmt->close();

                flash('success', 'Perangkat "' . $kode . '" berhasil diperbarui.');
            } else {
                $token = bin2hex(random_bytes(32));
                $stmt  = $conn->prepare(
                    'INSERT INTO devices (kode, api_token, animal_id, is_active) VALUES (?, ?, ?, ?)'
                );
                $animalParam = $animalId > 0 ? $animalId : null;
                $stmt->bind_param('ssii', $kode, $token, $animalParam, $aktif);
                $stmt->execute();
                $stmt->close();

                flash('success', 'Perangkat "' . $kode . '" dibuat. Salin token API untuk dimasukkan ke firmware ESP32.');
            }

            redirect('perangkat.php');

        case 'regenerate':
            $id = (int) ($_POST['id'] ?? 0);
            if (!find_device($conn, $id, $scope)) {
                throw new RuntimeException('Perangkat tidak ditemukan atau bukan milik akun Anda.');
            }

            $token = bin2hex(random_bytes(32));
            $stmt  = $conn->prepare('UPDATE devices SET api_token = ? WHERE id = ?');
            $stmt->bind_param('si', $token, $id);
            $stmt->execute();
            $stmt->close();

            flash('success', 'Token API baru berhasil dibuat. Perbarui firmware ESP32 dengan token ini.');
            redirect('perangkat.php');

        case 'hapus':
            $id = (int) ($_POST['id'] ?? 0);
            $device = find_device($conn, $id, $scope);

            if (!$device) {
                throw new RuntimeException('Perangkat tidak ditemukan atau bukan milik akun Anda.');
            }

            $stmt = $conn->prepare('DELETE FROM devices WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();

            flash('success', 'Perangkat "' . $device['kode'] . '" dihapus.');
            redirect('perangkat.php');

        default:
            throw new RuntimeException('Aksi tidak dikenali.');
    }
} catch (RuntimeException $error) {
    flash('danger', $error->getMessage());
    redirect('perangkat.php');
} catch (Throwable $error) {
    flash('danger', 'Terjadi kesalahan: ' . $error->getMessage());
    redirect('perangkat.php');
}
