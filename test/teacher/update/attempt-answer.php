<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../database/Database.php';
require_once __DIR__ . '/../../shared/dashboard_repository.php';

require_auth(['teacher']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/olimpiada.uz/test/teacher/results.php');
}
if (!verify_csrf($_POST['_csrf'] ?? '')) {
    flash_set('error', 'Xavfsizlik tekshiruvi muvaffaqiyatsiz.');
    redirect('/olimpiada.uz/test/teacher/results.php');
}

$teacherId = (int) (auth_user()['id'] ?? 0);
$attemptId = (int) ($_POST['attempt_id'] ?? 0);
$answerId = (int) ($_POST['answer_id'] ?? 0);
$markRaw = trim((string) ($_POST['mark'] ?? ''));
$isCorrect = ($markRaw === '1') ? 1 : 0;

if ($attemptId <= 0 || $answerId <= 0) {
    flash_set('error', 'Noto\'g\'ri so\'rov.');
    redirect('/olimpiada.uz/test/teacher/results.php');
}

try {
    $db = Database::connection();
    ensure_test_runtime_tables($db);

    $check = $db->prepare("SELECT aa.id, aa.attempt_id
                           FROM test_attempt_answers aa
                           INNER JOIN test_attempts a ON a.id=aa.attempt_id
                           WHERE aa.id=? AND aa.attempt_id=? AND aa.question_type='closed' AND a.teacher_id=?
                           LIMIT 1");
    if (!$check) {
        throw new RuntimeException('Prepare failed');
    }
    $check->bind_param('iii', $answerId, $attemptId, $teacherId);
    $check->execute();
    $row = $check->get_result()->fetch_assoc();
    $check->close();

    if (!$row) {
        flash_set('error', 'Baholash uchun javob topilmadi.');
        redirect('/olimpiada.uz/test/teacher/attempt-view.php?attempt_id=' . $attemptId);
    }

    $up = $db->prepare("UPDATE test_attempt_answers SET is_correct=?, checked_by_teacher=1, updated_at=NOW() WHERE id=? LIMIT 1");
    if (!$up) {
        throw new RuntimeException('Prepare failed');
    }
    $up->bind_param('ii', $isCorrect, $answerId);
    $up->execute();
    $up->close();

    recalculate_attempt_stats($db, $attemptId);

    flash_set('success', 'Yopiq savol baholandi.');
} catch (Throwable $e) {
    flash_set('error', 'Baholashda xatolik yuz berdi.');
}

redirect('/olimpiada.uz/test/teacher/attempt-view.php?attempt_id=' . $attemptId);
