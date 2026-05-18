<?php
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../shared/dashboard_repository.php';
require_once __DIR__ . '/../database/Database.php';

require_auth(['student']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/test/student/dashboard.php?section=tests');
}
if (!verify_csrf($_POST['_csrf'] ?? '')) {
    flash_set('error', 'Xavfsizlik tekshiruvi muvaffaqiyatsiz.');
    redirect('/test/student/dashboard.php?section=tests');
}

$user = auth_user();
$studentId = (int) ($user['id'] ?? 0);
$attemptId = (int) ($_POST['attempt_id'] ?? 0);
$testId = (int) ($_POST['test_id'] ?? 0);
$submitted = $_POST['questions'] ?? [];

try {
    $db = Database::connection();
    ensure_test_runtime_tables($db);
    $db->begin_transaction();

    $st = $db->prepare("SELECT id FROM test_attempts WHERE id=? AND student_id=? AND test_id=? AND status='in_progress' LIMIT 1");
    if (!$st) {
        throw new RuntimeException('attempt read failed');
    }
    $st->bind_param('iii', $attemptId, $studentId, $testId);
    $st->execute();
    $attempt = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$attempt) {
        $done = $db->prepare("SELECT id FROM test_attempts WHERE id=? AND student_id=? AND test_id=? AND status IN ('submitted','pending_review','checked') LIMIT 1");
        if ($done) {
            $done->bind_param('iii', $attemptId, $studentId, $testId);
            $done->execute();
            $already = $done->get_result()->fetch_assoc();
            $done->close();
            if ($already) {
                $db->commit();
                redirect('/test/student/attempt-view.php?attempt_id=' . $attemptId);
            }
        }
        throw new RuntimeException('attempt not found');
    }

    $questions = get_student_test_questions($testId);
    if (count($questions) === 0) {
        throw new RuntimeException('questions not found');
    }

    $clear = $db->prepare("DELETE FROM test_attempt_answers WHERE attempt_id=?");
    if (!$clear) {
        throw new RuntimeException('answer cleanup failed');
    }
    $clear->bind_param('i', $attemptId);
    $clear->execute();
    $clear->close();

    $insert = $db->prepare("INSERT INTO test_attempt_answers (attempt_id, question_id, question_type, question_text, option_a, option_b, option_c, option_d, correct_option, student_answer_option, student_answer_text, is_correct, checked_by_teacher, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    if (!$insert) {
        throw new RuntimeException('answer insert prepare failed');
    }

    $correct = 0;
    $wrong = 0;
    $pending = 0;

    foreach ($questions as $q) {
        $qid = (int) $q['id'];
        $qtype = (string) ($q['question_type'] ?? 'closed');
        $qtext = (string) ($q['question_text'] ?? '');
        $oa = (string) ($q['option_a'] ?? '');
        $ob = (string) ($q['option_b'] ?? '');
        $oc = (string) ($q['option_c'] ?? '');
        $od = (string) ($q['option_d'] ?? '');
        $coRaw = strtoupper(trim((string) ($q['correct_option'] ?? '')));
        $co = in_array($coRaw, ['A', 'B', 'C', 'D'], true) ? $coRaw : null;

        $ansOpt = null;
        $ansText = null;
        $isCorrect = null;
        $checkedByTeacher = 0;

        $row = is_array($submitted[$qid] ?? null) ? $submitted[$qid] : [];

        if ($qtype === 'open') {
            $ansOptRaw = strtoupper(trim((string) ($row['answer_option'] ?? '')));
            if (in_array($ansOptRaw, ['A', 'B', 'C', 'D'], true)) {
                $ansOpt = $ansOptRaw;
            }
            $isCorrect = ($ansOpt !== null && $co !== null && $ansOpt === $co) ? 1 : 0;
            if ($isCorrect === 1) {
                $correct++;
            } else {
                $wrong++;
            }
            $checkedByTeacher = 1;
        } else {
            $ansText = trim((string) ($row['answer_text'] ?? ''));
            if ($ansText === '') {
                $ansText = null;
            }
            $pending++;
        }

        $insert->bind_param(
            'iisssssssssii',
            $attemptId,
            $qid,
            $qtype,
            $qtext,
            $oa,
            $ob,
            $oc,
            $od,
            $co,
            $ansOpt,
            $ansText,
            $isCorrect,
            $checkedByTeacher
        );
        $insert->execute();
    }
    $insert->close();

    $openTotal = max(1, $correct + $wrong);
    $scorePercent = (int) round(($correct / $openTotal) * 100);
    $status = $pending > 0 ? 'pending_review' : 'submitted';

    $up = $db->prepare("UPDATE test_attempts SET submitted_at=NOW(), finished_at=NOW(), duration_spent=TIMESTAMPDIFF(SECOND, started_at, NOW()), correct_count=?, wrong_count=?, pending_count=?, score_percent=?, status=? WHERE id=? LIMIT 1");
    $up->bind_param('iiiisi', $correct, $wrong, $pending, $scorePercent, $status, $attemptId);
    $up->execute();
    $up->close();
    $db->commit();

    flash_set('success', 'Test yakunlandi.');
    redirect('/test/student/attempt-view.php?attempt_id=' . $attemptId);
} catch (Throwable $e) {
    if (isset($db) && $db instanceof mysqli) {
        $db->rollback();
    }
    error_log('Student test-submit error: ' . $e->getMessage());
    flash_set('error', 'Yakunlashda xatolik yuz berdi. Qayta urinib ko\'ring.');
    redirect('/test/student/dashboard.php?section=tests');
}
