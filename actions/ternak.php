<?php
require_once __DIR__ . '/../config/app.php';
require_login();
verify_csrf();

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'simpan':
            $id       = (int) ($_POST['id'] ?? 0);
            $kode     = strtoupper(trim($_POST['kode'] ?? ''));
            $nama     = trim($_POST['nama'] ?? '');
            $jenis    = trim($_POST['jenis'] ?? 'Sapi');
            $kelamin  = ($_POST['kelamin'] ?? 'Betina') === 'Jantan' ? 'Jantan' : 'Betina';
            $umur     = ($_POST['umur_bulan'] ?? '') === '' ? null : max(0, (int) $_POST['umur_bulan']);
            $kandangId = (int) ($_POST['kandang_id'] ?? 0);

            if ($kode === '' || $nama === '' || $kandangId <= 0) {
                throw new RuntimeException('Kode, nama, dan kandang wajib diisi.');
            }

            if (!preg_match('/^[A-Z0-9\-_]+$/', $kode)) {
                throw new RuntimeException('Kode hanya boleh berisi huruf, angka, strip, dan garis bawah.');
            }

            if (!fetch_kandang($conn, $kandangId)) {
                throw new RuntimeException('Kandang tidak ditemukan atau bukan milik akun Anda.');
            }

            $stmt = $conn->prepare('SELECT id FROM animals WHERE kode = ? AND id <> ? LIMIT 1');
            $stmt->bind_param('si', $kode, $id);
            $stmt->execute();
            $duplicate = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($duplicate) {
                throw new RuntimeException('Kode ternak "' . $kode . '" sudah dipakai.');
            }

            $existing = null;
            if ($id > 0) {
                $existing = fetch_animal($conn, $id);
                if (!$existing) {
                    throw new RuntimeException('Data ternak tidak ditemukan atau bukan milik akun Anda.');
                }
            }

            $fotoBaru = null;
            if (!empty($_FILES['foto']['name'])) {
                $fotoBaru = upload_foto_ternak($_FILES['foto'], $existing['foto'] ?? null);
            }

            if ($id > 0) {
                $foto = $fotoBaru ?? $existing['foto'];

                $stmt = $conn->prepare(
                    'UPDATE animals
                     SET kandang_id = ?, kode = ?, nama = ?, jenis = ?, kelamin = ?, umur_bulan = ?, foto = ?
                     WHERE id = ?'
                );
                $stmt->bind_param(
                    'issssisi',
                    $kandangId,
                    $kode,
                    $nama,
                    $jenis,
                    $kelamin,
                    $umur,
                    $foto,
                    $id
                );
                $stmt->execute();
                $stmt->close();

                flash('success', 'Data ternak "' . $nama . '" berhasil diperbarui.');
            } else {
                $stmt = $conn->prepare(
                    'INSERT INTO animals (kandang_id, kode, nama, jenis, kelamin, umur_bulan, foto)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->bind_param(
                    'issssis',
                    $kandangId,
                    $kode,
                    $nama,
                    $jenis,
                    $kelamin,
                    $umur,
                    $fotoBaru
                );
                $stmt->execute();
                $stmt->close();

                flash('success', 'Ternak "' . $nama . '" berhasil ditambahkan. Hubungkan perangkat GPS pada menu Perangkat IoT.');
            }

            redirect('ternak.php');

        case 'hapus':
            $id = (int) ($_POST['id'] ?? 0);
            $animal = fetch_animal($conn, $id);

            if (!$animal) {
                throw new RuntimeException('Data ternak tidak ditemukan atau bukan milik akun Anda.');
            }

            $stmt = $conn->prepare('DELETE FROM animals WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();

            hapus_foto_ternak($animal['foto']);

            flash('success', 'Ternak "' . $animal['nama'] . '" dihapus.');
            redirect('ternak.php');

        default:
            throw new RuntimeException('Aksi tidak dikenali.');
    }
} catch (RuntimeException $error) {
    flash('danger', $error->getMessage());
    redirect('ternak.php');
} catch (Throwable $error) {
    flash('danger', 'Terjadi kesalahan: ' . $error->getMessage());
    redirect('ternak.php');
}
