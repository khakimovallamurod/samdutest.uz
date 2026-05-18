<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../database/Database.php';

require_auth(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/olimpiada.uz/test/admin/dashboard.php');
}

if (!verify_csrf($_POST['_csrf'] ?? '')) {
    flash_set('error', 'Xavfsizlik tekshiruvi muvaffaqiyatsiz.');
    redirect('/olimpiada.uz/test/admin/dashboard.php');
}

$id = (int) ($_POST['id'] ?? 0);
$auth = auth_user();
if ($id <= 0) {
    flash_set('error', 'Noto\'g\'ri foydalanuvchi.');
    redirect('/olimpiada.uz/test/admin/dashboard.php');
}

if ((int) ($auth['id'] ?? 0) === $id) {
    flash_set('error', 'O\'zingizni o\'chira olmaysiz.');
    redirect('/olimpiada.uz/test/admin/dashboard.php');
}

try {
    $db = Database::connection();
    $stmt = $db->prepare('DELETE FROM users WHERE id=? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('Prepare error');
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    flash_set('success', 'Foydalanuvchi o\'chirildi.');
} catch (Throwable $e) {
    flash_set('error', 'O\'chirishda xatolik yuz berdi.');
}

redirect('/olimpiada.uz/test/admin/dashboard.php');
