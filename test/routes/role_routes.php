<?php

function role_redirect_path($role)
{
    $config = require __DIR__ . '/../config/app.php';
    $routes = $config['role_redirects'];
    $normalized = strtolower(trim((string) $role));

    if ($normalized === 'talaba' || $normalized === 'user') {
        $normalized = 'student';
    }

    return $routes[$normalized] ?? $config['default_redirect'];
}

function normalize_role($role)
{
    $normalized = strtolower(trim((string) $role));

    if ($normalized === 'talaba' || $normalized === 'user') {
        return 'student';
    }

    if (in_array($normalized, ['admin', 'teacher', 'student'], true)) {
        return $normalized;
    }

    return '';
}
