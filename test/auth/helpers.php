<?php

require_once __DIR__ . '/../routes/role_routes.php';

function app_base_prefix()
{
    static $prefix = null;
    if ($prefix !== null) {
        return $prefix;
    }

    $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $needle = '/test/';
    $pos = strpos($scriptName, $needle);
    if ($pos === false) {
        $prefix = '';
        return $prefix;
    }

    $prefix = rtrim(substr($scriptName, 0, $pos), '/');
    return $prefix;
}

function app_path($path)
{
    $path = (string) $path;
    if ($path === '' || $path[0] !== '/') {
        return $path;
    }

    return app_base_prefix() . $path;
}

function rewrite_test_root_links($buffer)
{
    $prefix = app_base_prefix();
    if ($prefix === '') {
        return $buffer;
    }

    return str_replace(
        ['="/test/', "='/test/", 'url(/test/'],
        ['="' . $prefix . '/test/', "='" . $prefix . "/test/", 'url(' . $prefix . '/test/'],
        $buffer
    );
}

if (PHP_SAPI !== 'cli') {
    ob_start('rewrite_test_root_links');
}

function app_config()
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config/app.php';
    }
    return $config;
}

function start_secure_session()
{
    $cfg = app_config();

    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name($cfg['session_name']);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function csrf_token()
{
    start_secure_session();
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function verify_csrf($token)
{
    start_secure_session();
    return is_string($token) && isset($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $token);
}

function flash_set($key, $message)
{
    start_secure_session();
    $_SESSION['_flash'][$key] = $message;
}

function flash_get($key)
{
    start_secure_session();
    if (!isset($_SESSION['_flash'][$key])) {
        return '';
    }

    $message = $_SESSION['_flash'][$key];
    unset($_SESSION['_flash'][$key]);

    return $message;
}

function redirect($path)
{
    header('Location: ' . app_path($path));
    exit;
}

function auth_user()
{
    start_secure_session();
    return $_SESSION['auth_user'] ?? null;
}

function logout_user()
{
    start_secure_session();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
