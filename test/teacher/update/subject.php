<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../database/Database.php';
require_once __DIR__ . '/../../shared/dashboard_repository.php';

require_auth(['teacher']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/test/teacher/subjects.php');
}

if (!verify_csrf($_POST['_csrf'] ?? '')) {
    flash_set('error', 'Xavfsizlik tekshiruvi muvaffaqiyatsiz.');
    redirect('/test/teacher/subjects.php');
}

$id = (int) ($_POST['id'] ?? 0);
$name = trim((string) ($_POST['name'] ?? ''));
$status = trim((string) ($_POST['status'] ?? 'active'));
if ($id <= 0 || $name === '') {
    flash_set('error', 'Fan ma\'lumotlarini to\'g\'ri kiriting.');
    redirect('/test/teacher/subjects.php');
}

$allowedStatuses = ['active', 'inactive'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = 'active';
}

$teacherId = (int) (auth_user()['id'] ?? 0);

try {
    $db = Database::connection();
    ensure_teacher_tables($db);

    $stmt = $db->prepare('UPDATE subjects SET name=?, status=?, updated_at=NOW() WHERE id=? AND teacher_id=? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('Prepare failed');
    }

    $stmt->bind_param('ssii', $name, $status, $id, $teacherId);
    $stmt->execute();

    if ($stmt->affected_rows >= 0) {
        flash_set('success', 'Fan yangilandi.');
    }

    $stmt->close();
} catch (Throwable $e) {
    flash_set('error', 'Fan yangilashda xatolik yuz berdi.');
}

redirect('/test/teacher/subjects.php');
