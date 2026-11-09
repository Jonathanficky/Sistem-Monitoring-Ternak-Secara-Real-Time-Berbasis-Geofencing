<?php
/**
 * Layout: kepala halaman + sidebar + topbar.
 *
 * Variabel yang bisa diisi sebelum include:
 *   $page_title  (string)
 *   $active      (string) kunci menu aktif
 *   $page_icon   (string) ikon Font Awesome
 *   $full_width  (bool)   layout tanpa padding (untuk peta penuh)
 */

$page_title = $page_title ?? 'Dashboard';
$active     = $active ?? '';
$page_icon  = $page_icon ?? 'fa-gauge-high';
$full_width = $full_width ?? false;

$__user        = current_user();
$__pollInterval = (int) get_setting($conn, 'poll_interval', '5000');
$__offlineAfter = (int) get_setting($conn, 'offline_after', '90');
$__appName      = get_setting($conn, 'nama_aplikasi', APP_NAME);

$__scope        = scope_kandang('k');
$__alertCount   = (int) $conn->query(
    "SELECT COUNT(*) AS c FROM alerts al
     INNER JOIN animals a ON a.id = al.animal_id
     INNER JOIN kandangs k ON k.id = a.kandang_id
     WHERE al.is_resolved = 0 AND $__scope"
)->fetch_assoc()['c'];

$__menu = [
    'Menu Utama' => [
        ['key' => 'dashboard', 'label' => 'Dashboard',   'icon' => 'fa-gauge-high',  'href' => 'dashboard.php'],
        ['key' => 'peta',      'label' => 'Peta Live',    'icon' => 'fa-map-location-dot', 'href' => 'peta.php'],
    ],
    'Data Ternak' => [
        ['key' => 'ternak',    'label' => 'Data Ternak',  'icon' => 'ayam',           'href' => 'ternak.php'],
        ['key' => 'kandang',   'label' => 'Kandang',      'icon' => 'fa-house-chimney', 'href' => 'kandang.php'],
        ['key' => 'perangkat', 'label' => 'Perangkat IoT','icon' => 'fa-microchip',   'href' => 'perangkat.php'],
        ['key' => 'riwayat',   'label' => 'Riwayat Posisi','icon' => 'fa-clock-rotate-left', 'href' => 'riwayat.php'],
    ],
    'Monitoring' => [
        ['key' => 'peringatan', 'label' => 'Peringatan',  'icon' => 'fa-bell',        'href' => 'peringatan.php', 'badge' => $__alertCount],
        ['key' => 'simulator',  'label' => 'Simulator GPS','icon' => 'fa-satellite-dish', 'href' => 'simulator.php'],
    ],
    'Sistem' => [
        ['key' => 'pengaturan', 'label' => 'Pengaturan',  'icon' => 'fa-gear',        'href' => 'pengaturan.php', 'admin_only' => true],
        ['key' => 'pengguna',   'label' => 'Pengguna',    'icon' => 'fa-users',       'href' => 'pengguna.php', 'admin_only' => true],
    ],
];

$__flash = flash_messages();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($page_title) ?> &middot; <?= e($__appName) ?></title>
    <meta name="description" content="<?= e(APP_DESCRIPTION) ?>">
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('img/favicon.svg')) ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body>
<div class="app-shell">

    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <span class="brand-logo"><?= ikon_ayam() ?></span>
            <span class="brand-text">
                <strong><?= e($__appName) ?></strong>
                <small>Smart Livestock</small>
            </span>
        </div>

        <nav class="sidebar-nav">
            <?php foreach ($__menu as $group => $items): ?>
                <?php
                $visible = array_filter($items, static fn ($item) => empty($item['admin_only']) || is_admin());
                if (!$visible) {
                    continue;
                }
                ?>
                <div class="nav-group">
                    <span class="nav-group-title"><?= e($group) ?></span>
                    <?php foreach ($visible as $item): ?>
                        <a class="nav-link <?= $active === $item['key'] ? 'active' : '' ?>"
                           href="<?= e(url($item['href'])) ?>">
                            <?php if ($item['icon'] === 'ayam'): ?>
                                <?= ikon_ayam() ?>
                            <?php else: ?>
                                <i class="fa-solid <?= e($item['icon']) ?>"></i>
                            <?php endif; ?>
                            <span><?= e($item['label']) ?></span>
                            <?php if (!empty($item['badge']) && $item['badge'] > 0): ?>
                                <span class="nav-badge"><?= (int) $item['badge'] ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-footer">
            <div class="device-hint">
                <span class="dot dot-live"></span>
                <div>
                    <strong>Sistem aktif</strong>
                    <small>Polling <?= (int) round($__pollInterval / 1000) ?> detik</small>
                </div>
            </div>
            <a class="logout-link" href="<?= e(url('logout.php')) ?>">
                <i class="fa-solid fa-arrow-right-from-bracket"></i> Keluar
            </a>
        </div>
    </aside>

    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <div class="app-main">
        <header class="topbar">
            <button class="icon-btn menu-toggle" id="menuToggle" type="button" aria-label="Buka menu">
                <i class="fa-solid fa-bars"></i>
            </button>

            <div class="topbar-title">
                <h1>
                    <?php if ($page_icon === 'ayam'): ?>
                        <?= ikon_ayam() ?>
                    <?php else: ?>
                        <i class="fa-solid <?= e($page_icon) ?>"></i>
                    <?php endif; ?>
                    <?= e($page_title) ?>
                </h1>
                <p>Monitoring ternak real-time berbasis geofencing</p>
            </div>

            <div class="topbar-right">
                <span class="live-clock" id="liveClock">
                    <i class="fa-regular fa-clock"></i>
                    <span><?= date('H:i:s') ?></span>
                </span>
                <div class="user-chip">
                    <span class="avatar"><?= e(initials($__user['nama'] ?? 'U')) ?></span>
                    <span class="user-meta">
                        <strong><?= e($__user['nama'] ?? '') ?></strong>
                        <small><?= e(ucfirst($__user['role'] ?? '')) ?></small>
                    </span>
                </div>
            </div>
        </header>

        <main class="content <?= $full_width ? 'content-full' : '' ?>">
            <?php foreach ($__flash as $msg): ?>
                <div class="flash flash-<?= e($msg['type']) ?>" role="alert">
                    <i class="fa-solid <?= $msg['type'] === 'success'
                        ? 'fa-circle-check'
                        : ($msg['type'] === 'danger' ? 'fa-circle-exclamation' : 'fa-circle-info') ?>"></i>
                    <span><?= e($msg['message']) ?></span>
                    <button type="button" class="flash-close" aria-label="Tutup">&times;</button>
                </div>
            <?php endforeach; ?>
