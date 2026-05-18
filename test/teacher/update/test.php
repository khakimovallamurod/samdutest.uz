<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../database/Database.php';
require_once __DIR__ . '/../../shared/dashboard_repository.php';

require_auth(['teacher']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/test/teacher/tests.php');
}

if (!verify_csrf($_POST['_csrf'] ?? '')) {
    flash_set('error', 'Xavfsizlik tekshiruvi muvaffaqiyatsiz.');
    redirect('/test/teacher/tests.php');
}

$teacherId = (int) (auth_user()['id'] ?? 0);
$id = (int) ($_POST['id'] ?? 0);
$title = trim((string) ($_POST['title'] ?? ''));
$subjectId = (int) ($_POST['subject_id'] ?? 0);
$duration = (int) ($_POST['duration_minutes'] ?? 0);
$attempts = (int) ($_POST['attempts_limit'] ?? 1);
$qLimit = (int) ($_POST['question_limit'] ?? 10);
$visibility = trim((string) ($_POST['visibility'] ?? 'open'));

if ($id <= 0 || $subjectId <= 0 || $title === '' || $duration <= 0 || $attempts <= 0 || $qLimit <= 0) {
    flash_set('error', 'Barcha maydonlarni to\'g\'ri kiriting.');
    redirect('/test/teacher/tests.php');
}
try {
    $db = Database::connection();
    ensure_teacher_tables($db);
    if (!in_array($visibility, ['open', 'closed'], true)) { $visibility = 'open'; }
    $privateCode = null;
    if ($visibility === 'closed') {
        $get = $db->prepare('SELECT private_code FROM tests WHERE id=? AND teacher_id=? LIMIT 1');
        $get->bind_param('ii', $id, $teacherId); $get->execute(); $row = $get->get_result()->fetch_assoc(); $get->close();
        $privateCode = trim((string)($row['private_code'] ?? ''));
        if ($privateCode === '') { do { $privateCode = (string) random_int(100000, 999999999); } while (($r = $db->query("SELECT id FROM tests WHERE private_code='".$db->real_escape_string($privateCode)."' AND id<>".$id." LIMIT 1")) && $r->num_rows > 0); }
    }
    $stmt = $db->prepare('UPDATE tests SET title=?, subject_id=?, duration_minutes=?, attempts_limit=?, question_limit=?, visibility=?, private_code=?, updated_at=NOW() WHERE id=? AND teacher_id=? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('Prepare failed');
    }
    $stmt->bind_param('siiiissii', $title, $subjectId, $duration, $attempts, $qLimit, $visibility, $privateCode, $id, $teacherId);
    $stmt->execute();
    $stmt->close();
    flash_set('success', 'Test yangilandi.');
} catch (Throwable $e) {
    flash_set('error', 'Test yangilashda xatolik yuz berdi.');
}

redirect('/test/teacher/tests.php');
