<?php
require_once __DIR__ . '/config/app.php';

logout_user();
session_start();
$_SESSION['flash'][] = ['type' => 'info', 'message' => 'Anda telah keluar dari sistem.'];

redirect('login.php');
