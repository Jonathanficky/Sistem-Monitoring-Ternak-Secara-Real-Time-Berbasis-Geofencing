<?php
require_once __DIR__ . '/config/app.php';

redirect(current_user() ? 'dashboard.php' : 'login.php');
