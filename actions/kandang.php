<?php
require_once __DIR__ . '/../config/app.php';
require_login();
verify_csrf();

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'simpan':
            $id      = (int) ($_POST['id'] ?? 0);
            $nama    = trim($_POST['nama'] ?? '');
            $lokasi  = trim($_POST['lokasi'] ?? '');
            $lat     = $_POST['center_lat'] ?? null;
            $lng     = $_POST['center_lng'] ?? null;
            $radius  = max(5, min(2000, (int) ($_POST['radius_m'] ?? 40)));
            $buffer  = max(0, min(100, (int) ($_POST['buffer_m'] ?? 3)));

            if ($nama === '') {
                throw new RuntimeException('Nama kandang wajib diisi.');
            }

            if (!valid_coordinates($lat, $lng)) {
                throw new RuntimeException('Titik pusat kandang belum diatur. Klik peta untuk memilih lokasi.');
            }

            $lat = (float) $lat;
            $lng = (float) $lng;

            if (is_admin()) {
                $pemilik = isset($_POST['pemilik_id']) && $_POST['pemilik_id'] !== ''
                    ? (int) $_POST['pemilik_id']
                    : (int) current_user()['id'];
            } else {
                $pemilik = (int) current_user()['id'];
            }

            if ($id > 0) {
                if (!fetch_kandang($conn, $id)) {
                    throw new RuntimeException('Kandang tidak ditemukan atau bukan milik akun Anda.');
                }

                $stmt = $conn->prepare(
                    'UPDATE kandangs
                     SET nama = ?, lokasi = ?, center_lat = ?, center_lng = ?, radius_m = ?, buffer_m = ?, pemilik_id = ?
                     WHERE id = ?'
                );
                $stmt->bind_param('ssddiiii', $nama, $lokasi, $lat, $lng, $radius, $buffer, $pemilik, $id);
                $stmt->execute();
                $stmt->close();

                flash('success', 'Kandang "' . $nama . '" berhasil diperbarui.');
            } else {
                $stmt = $conn->prepare(
                    'INSERT INTO kandangs (nama, lokasi, center_lat, center_lng, radius_m, buffer_m, pemilik_id)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->bind_param('ssddiii', $nama, $lokasi, $lat, $lng, $radius, $buffer, $pemilik);
                $stmt->execute();
                $stmt->close();

                flash('success', 'Kandang "' . $nama . '" berhasil ditambahkan.');
            }

            redirect('kandang.php');

        case 'hapus':
            $id = (int) ($_POST['id'] ?? 0);
            $kandang = fetch_kandang($conn, $id);

            if (!$kandang) {
                throw new RuntimeException('Kandang tidak ditemukan atau bukan milik akun Anda.');
            }

            $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM animals WHERE kandang_id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $jumlah = (int) $stmt->get_result()->fetch_assoc()['c'];
            $stmt->close();

            if ($jumlah > 0) {
                throw new RuntimeException(
                    'Kandang masih memiliki ' . $jumlah . ' ternak. Pindahkan atau hapus ternak terlebih dahulu.'
                );
            }

            $stmt = $conn->prepare('DELETE FROM kandangs WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();

            flash('success', 'Kandang "' . $kandang['nama'] . '" dihapus.');
            redirect('kandang.php');

        default:
            throw new RuntimeException('Aksi tidak dikenali.');
    }
} catch (RuntimeException $error) {
    flash('danger', $error->getMessage());
    redirect('kandang.php');
} catch (Throwable $error) {
    flash('danger', 'Terjadi kesalahan: ' . $error->getMessage());
    redirect('kandang.php');
}
