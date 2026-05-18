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

$name = trim((string) ($_POST['name'] ?? ''));
if ($name === '') {
    flash_set('error', 'Fan nomini kiriting.');
    redirect('/olimpiada.uz/test/teacher/subjects.php');
}

$teacherId = (int) (auth_user()['id'] ?? 0);

try {
    $db = Database::connection();
    ensure_teacher_tables($db);

    $stmt = $db->prepare('INSERT INTO subjects (teacher_id, name, status, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())');
    if (!$stmt) {
        throw new RuntimeException('Prepare failed');
    }

    $status = 'active';
    $stmt->bind_param('iss', $teacherId, $name, $status);
    $stmt->execute();
    $stmt->close();

    flash_set('success', 'Fan muvaffaqiyatli qo\'shildi.');
} catch (Throwable $e) {
    flash_set('error', 'Fan qo\'shishda xatolik. Bu fan oldin yaratilgan bo\'lishi mumkin.');
}

redirect('/olimpiada.uz/test/teacher/subjects.php');
