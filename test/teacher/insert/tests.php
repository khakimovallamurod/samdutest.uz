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
$subjectId = (int) ($_POST['subject_id'] ?? 0);
$title = trim((string) ($_POST['title'] ?? ''));
$duration = (int) ($_POST['duration_minutes'] ?? 0);
$attempts = (int) ($_POST['attempts_limit'] ?? 1);
$qLimit = (int) ($_POST['question_limit'] ?? 10);
$testType = trim((string) ($_POST['visibility'] ?? 'open'));

if ($subjectId <= 0 || $title === '' || $duration <= 0 || $attempts <= 0 || $qLimit <= 0) {
    flash_set('error', 'Barcha maydonlarni to‘g‘ri kiriting.');
    redirect('/test/teacher/tests.php');
}
if (!in_array($testType, ['open', 'closed'], true)) {
    $testType = 'open';
}

try {
    $db = Database::connection();
    ensure_teacher_tables($db);

    $privateCode = null;
    if ($testType === 'closed') {
        do {
            $privateCode = (string) random_int(100000, 999999999);
            $codeRes = $db->query("SELECT id FROM tests WHERE private_code='" . $db->real_escape_string($privateCode) . "' LIMIT 1");
        } while ($codeRes && $codeRes->num_rows > 0);
    }

    $st = $db->prepare('INSERT INTO tests (teacher_id,subject_id,title,duration_minutes,visibility,private_code,attempts_limit,question_limit,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())');
    if (!$st) {
        throw new RuntimeException('Insert prepare failed: ' . $db->error);
    }
    $st->bind_param('iisissii', $teacherId, $subjectId, $title, $duration, $testType, $privateCode, $attempts, $qLimit);
    if (!$st->execute()) {
        throw new RuntimeException('Insert execute failed: ' . $st->error);
    }

    $newId = (int) $db->insert_id;
    $st->close();
    flash_set('success', 'Test yaratildi.' . ($newId > 0 ? ' Savol kiritishni boshlang.' : ''));
    redirect('/test/teacher/test-questions.php?test_id=' . $newId);
} catch (Throwable $e) {
    error_log('Create test failed: ' . $e->getMessage());
    flash_set('error', 'Test yaratishda xatolik: ' . $e->getMessage());
}
redirect('/test/teacher/tests.php');
