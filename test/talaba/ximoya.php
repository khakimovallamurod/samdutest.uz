<?php
require_once __DIR__ . '/../middleware/auth.php';
require_auth(['user']);
require_once __DIR__ . '/../../config.php';

function defilter($s) {
    return htmlspecialchars_decode($s, ENT_QUOTES);
}

function saqla($xabar) {
    $id = (int)($_SESSION['id'] ?? 0);
    if ($id <= 0) {
        return;
    }

    $dir = __DIR__ . '/answers/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($dir . $id . '.txt', $xabar . "\n", FILE_APPEND | LOCK_EX);
}
