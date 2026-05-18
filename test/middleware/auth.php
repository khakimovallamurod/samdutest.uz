<?php

require_once __DIR__ . '/../auth/helpers.php';

function require_auth($roles = [])
{
    start_secure_session();

    $auth = auth_user();
    if (!$auth || empty($auth['id'])) {
        flash_set('error', 'Iltimos, tizimga kiring.');
        redirect('/olimpiada.uz/test/login.php');
    }

    $ttl = (int) app_config()['session_ttl'];
    $loggedAt = (int) ($auth['logged_at'] ?? 0);
    if ($loggedAt > 0 && (time() - $loggedAt > $ttl)) {
        logout_user();
        flash_set('error', 'Sessiya vaqti tugadi. Qayta kiring.');
        redirect('/olimpiada.uz/test/login.php');
    }

    if (!empty($roles)) {
        $currentRole = normalize_role($auth['role'] ?? '');
        $allowedRoles = array_map('normalize_role', $roles);
        if (!in_array($currentRole, $allowedRoles, true)) {
            flash_set('error', 'Bu sahifaga kirish huquqingiz yo\'q.');
            redirect(role_redirect_path($auth['role']));
        }
    }
}
