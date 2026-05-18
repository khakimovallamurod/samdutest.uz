<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../database/Database.php';
require_once __DIR__ . '/../../shared/dashboard_repository.php';

require_auth(['teacher']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/olimpiada.uz/test/teacher/subjects.php');
}

if (!verify_csrf($_POST['_csrf'] ?? '')) {
    flash_set('error', 'Xavfsizlik tekshiruvi muvaffaqiyatsiz.');
    redirect('/olimpiada.uz/test/teacher/subjects.php');
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    flash_set('error', 'Noto\'g\'ri fan ID.');
    redirect('/olimpiada.uz/test/teacher/subjects.php');
}

$teacherId = (int) (auth_user()['id'] ?? 0);

try {
    $db = Database::connection();
    ensure_teacher_tables($db);

    $stmt = $db->prepare('DELETE FROM subjects WHERE id=? AND teacher_id=? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('Prepare failed');
    }

    $stmt->bind_param('ii', $id, $teacherId);
    $stmt->execute();
    $stmt->close();

    flash_set('success', 'Fan o\'chirildi.');
} catch (Throwable $e) {
    flash_set('error', 'Fan o\'chirishda xatolik yuz berdi.');
}

redirect('/olimpiada.uz/test/teacher/subjects.php');
