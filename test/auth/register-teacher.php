<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/AuthService.php';

start_secure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/olimpiada.uz/test/register-teacher.php');
}

if (!verify_csrf($_POST['_csrf'] ?? '')) {
    flash_set('error', 'Xavfsizlik tekshiruvi muvaffaqiyatsiz.');
    redirect('/olimpiada.uz/test/register-teacher.php');
}

$result = AuthService::registerTeacher($_POST);
flash_set($result['ok'] ? 'success' : 'error', $result['message']);
redirect('/olimpiada.uz/test/register-teacher.php');
