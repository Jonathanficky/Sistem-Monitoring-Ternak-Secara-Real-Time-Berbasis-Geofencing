<?php
require_once __DIR__ . '/config/app.php';

if (current_user()) {
    redirect('dashboard.php');
}

$error    = null;
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Sesi formulir kedaluwarsa. Silakan muat ulang halaman.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Username dan password wajib diisi.';
        } else {
            $stmt = $conn->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($user && password_verify($password, $user['password'])) {
                login_user($user);
                flash('success', 'Selamat datang, ' . $user['nama'] . '!');
                redirect('dashboard.php');
            }

            $error = 'Username atau password salah.';
        }
    }
}

$flashMessages = flash_messages();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk &middot; <?= e(APP_NAME) ?></title>
    <meta name="description" content="<?= e(APP_DESCRIPTION) ?>">
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('img/favicon.svg')) ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body>
<div class="login-page">
    <div class="login-card">

        <section class="login-brand">
            <div class="login-logo">
                <span class="brand-logo"><?= ikon_ayam() ?></span>
                <span>
                    <strong><?= e(APP_NAME) ?></strong>
                    <small>Smart Livestock Monitoring</small>
                </span>
            </div>

            <div class="login-headline">
                <h2>Pantau ternak Anda<br><span>kapan saja, di mana saja.</span></h2>
                <p>
                    Sistem monitoring real-time berbasis geofencing yang mengawasi posisi
                    ternak dan mengirim peringatan otomatis ketika keluar dari radius aman kandang.
                </p>
            </div>

            <div class="login-features">
                <div class="login-feature">
                    <i class="fa-solid fa-location-crosshairs"></i>
                    <span>Pelacakan koordinat GPS secara real-time</span>
                </div>
                <div class="login-feature">
                    <i class="fa-solid fa-draw-polygon"></i>
                    <span>Geofence sirkular dengan rumus Haversine</span>
                </div>
                <div class="login-feature">
                    <i class="fa-solid fa-bell"></i>
                    <span>Peringatan otomatis saat ternak keluar radius</span>
                </div>
                <div class="login-feature">
                    <i class="fa-solid fa-map"></i>
                    <span>Dashboard peta interaktif &amp; riwayat perjalanan</span>
                </div>
            </div>
        </section>

        <section class="login-form-wrap">
            <h1>Masuk ke Dashboard</h1>
            <p>Gunakan akun yang diberikan oleh manajer proyek.</p>

            <?php foreach ($flashMessages as $msg): ?>
                <div class="flash flash-<?= e($msg['type']) ?>">
                    <i class="fa-solid fa-circle-info"></i>
                    <span><?= e($msg['message']) ?></span>
                </div>
            <?php endforeach; ?>

            <?php if ($error): ?>
                <div class="flash flash-danger">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= e(url('login.php')) ?>" autocomplete="on" data-no-lock>
                <?= csrf_field() ?>

                <div class="field">
                    <label for="username">Username</label>
                    <input class="input" type="text" id="username" name="username"
                           value="<?= e($username) ?>" placeholder="cth. admin"
                           autocomplete="username" required autofocus>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <input class="input" type="password" id="password" name="password"
                               placeholder="Masukkan password" autocomplete="current-password" required>
                        <button class="icon-btn" type="button" id="togglePassword" aria-label="Tampilkan password">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button class="btn btn-primary btn-block" type="submit">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i> Masuk
                </button>
            </form>

            <div class="login-tips">
                <strong>Akun demo:</strong><br>
                Admin &mdash; <code>admin</code> / <code>admin123</code><br>
                Petani &mdash; <code>petani</code> / <code>petani123</code>
            </div>

            <p class="login-footer">
                &copy; <?= date('Y') ?> <?= e(APP_NAME) ?> &middot; Politeknik Negeri Batam
            </p>
        </section>
    </div>
</div>

<script>
    const toggle = document.getElementById('togglePassword');
    const password = document.getElementById('password');
    toggle?.addEventListener('click', () => {
        const show = password.type === 'password';
        password.type = show ? 'text' : 'password';
        toggle.innerHTML = show
            ? '<i class="fa-regular fa-eye-slash"></i>'
            : '<i class="fa-regular fa-eye"></i>';
    });
</script>
</body>
</html>
