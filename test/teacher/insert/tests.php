<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../database/Database.php';
require_once __DIR__ . '/../../shared/dashboard_repository.php';

require_auth(['teacher']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/test/teacher/tests/index.php');
}
if (!verify_csrf($_POST['_csrf'] ?? '')) {
    flash_set('error', 'Xavfsizlik tekshiruvi muvaffaqiyatsiz.');
    redirect('/test/teacher/tests/create.php');
}

$teacherId = (int) (auth_user()['id'] ?? 0);

$items = $_POST['items'] ?? null;
if (!is_array($items) || count($items) === 0) {
    $items = [[
        'subject_id' => $_POST['subject_id'] ?? 0,
        'title' => $_POST['title'] ?? '',
        'duration_minutes' => $_POST['duration_minutes'] ?? 0,
        'attempts_limit' => $_POST['attempts_limit'] ?? 1,
        'question_limit' => $_POST['question_limit'] ?? 10,
        'visibility' => $_POST['visibility'] ?? 'open',
    ]];
}

try {
    $db = Database::connection();
    ensure_teacher_tables($db);

    $st = $db->prepare('INSERT INTO tests (teacher_id,subject_id,title,duration_minutes,visibility,private_code,attempts_limit,question_limit,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())');
    if (!$st) {
        throw new RuntimeException('Insert prepare failed: ' . $db->error);
    }

    $created = 0;
    $lastId = 0;

    foreach ($items as $it) {
        $subjectId = (int) ($it['subject_id'] ?? 0);
        $title = trim((string) ($it['title'] ?? ''));
        $duration = (int) ($it['duration_minutes'] ?? 0);
        $attempts = (int) ($it['attempts_limit'] ?? 1);
        $qLimit = (int) ($it['question_limit'] ?? 10);
        $testType = trim((string) ($it['visibility'] ?? 'open'));

        if ($subjectId <= 0 || $title === '' || $duration <= 0 || $attempts <= 0 || $qLimit <= 0) {
            continue;
        }
        if (!in_array($testType, ['open', 'closed'], true)) {
            $testType = 'open';
        }

        $privateCode = null;
        if ($testType === 'closed') {
            do {
                $privateCode = (string) random_int(100000, 999999999);
                $codeRes = $db->query("SELECT id FROM tests WHERE private_code='" . $db->real_escape_string($privateCode) . "' LIMIT 1");
            } while ($codeRes && $codeRes->num_rows > 0);
        }

        $st->bind_param('iisissii', $teacherId, $subjectId, $title, $duration, $testType, $privateCode, $attempts, $qLimit);
        if ($st->execute()) {
            $created++;
            $lastId = (int) $db->insert_id;
        }
    }

    $st->close();

    if ($created <= 0) {
        flash_set('error', 'To\'g\'ri ma\'lumot bilan kamida bitta test kiriting.');
        redirect('/test/teacher/tests/create.php');
    }

    flash_set('success', $created . ' ta test yaratildi.');
    if ($created === 1 && $lastId > 0) {
        redirect('/test/teacher/tests/questions.php?test_id=' . $lastId);
    }
    redirect('/test/teacher/tests/index.php');
} catch (Throwable $e) {
    error_log('Create test failed: ' . $e->getMessage());
    flash_set('error', 'Test yaratishda xatolik: ' . $e->getMessage());
}

redirect('/test/teacher/tests/create.php');
