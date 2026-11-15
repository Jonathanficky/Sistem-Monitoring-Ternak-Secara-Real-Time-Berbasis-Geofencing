<?php
require_once __DIR__ . '/../config/app.php';
require_login();
verify_csrf();

$action = $_POST['action'] ?? '';

try {
    if (!is_admin()) {
        throw new RuntimeException('Hanya admin yang dapat mengubah pengaturan sistem.');
    }

    if ($action === 'simpan') {
        $namaApp       = trim($_POST['nama_aplikasi'] ?? APP_NAME);
        $pollInterval  = (int) ($_POST['poll_interval'] ?? 5000);
        $defaultRadius = (int) ($_POST['default_radius'] ?? 40);
        $defaultBuffer = (int) ($_POST['default_buffer'] ?? 3);
        $offlineAfter  = (int) ($_POST['offline_after'] ?? 90);

        if ($namaApp === '' || mb_strlen($namaApp) > 60) {
            throw new RuntimeException('Nama aplikasi wajib diisi (maksimal 60 karakter).');
        }

        $pollInterval  = max(2000, min(60000, $pollInterval));
        $defaultRadius = max(5, min(2000, $defaultRadius));
        $defaultBuffer = max(0, min(100, $defaultBuffer));
        $offlineAfter  = max(30, min(3600, $offlineAfter));

        set_setting($conn, 'nama_aplikasi', $namaApp);
        set_setting($conn, 'poll_interval', (string) $pollInterval);
        set_setting($conn, 'default_radius', (string) $defaultRadius);
        set_setting($conn, 'default_buffer', (string) $defaultBuffer);
        set_setting($conn, 'offline_after', (string) $offlineAfter);

        flash('success', 'Pengaturan berhasil disimpan.');
        redirect('pengaturan.php');
    }

    throw new RuntimeException('Aksi tidak dikenali.');
} catch (RuntimeException $error) {
    flash('danger', $error->getMessage());
    redirect('pengaturan.php');
} catch (Throwable $error) {
    flash('danger', 'Terjadi kesalahan: ' . $error->getMessage());
    redirect('pengaturan.php');
}
