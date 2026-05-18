<?php

require_once __DIR__ . '/auth/helpers.php';
require_once __DIR__ . '/auth/AuthService.php';

start_secure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/olimpiada.uz/test/login.php');
}

if (!verify_csrf($_POST['_csrf'] ?? '')) {
    flash_set('error', 'Xavfsizlik tekshiruvi muvaffaqiyatsiz. Qayta urinib ko\'ring.');
    redirect('/olimpiada.uz/test/login.php');
}

$result = AuthService::attemptLogin(
    $_POST['login'] ?? '',
    $_POST['parol'] ?? '',
    isset($_POST['remember_me']) && $_POST['remember_me'] === '1'
);

if (!$result['ok']) {
    flash_set('error', $result['message']);
    redirect('/olimpiada.uz/test/login.php');
}

unset($_SESSION['_csrf']);
redirect($result['redirect']);
