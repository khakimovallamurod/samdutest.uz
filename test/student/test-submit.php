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
$duration = max(1, (int) ($_POST['duration_minutes'] ?? 60));
$teacherId = (int) ($_POST['teacher_id'] ?? 0);
$subjectId = (int) ($_POST['subject_id'] ?? 0);
$totalQuestionsPosted = max(0, (int) ($_POST['total_questions'] ?? 0));
$submitted = $_POST['questions'] ?? [];

try {
    $db = Database::connection();
    ensure_test_runtime_tables($db);
    $answersWarning = null;
    $submitWarning = null;
    $isAutoIncrementId = static function(mysqli $db, $table) {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
        if ($table === '') return true;
        $res = $db->query("SHOW COLUMNS FROM `{$table}` LIKE 'id'");
        $row = $res ? $res->fetch_assoc() : null;
        if (!$row) return true;
        $extra = strtolower((string)($row['Extra'] ?? ''));
        return strpos($extra, 'auto_increment') !== false;
    };
    $nextId = static function(mysqli $db, $table) {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
        if ($table === '') return 1;
        $res = $db->query("SELECT COALESCE(MAX(id),0)+1 AS nid FROM `{$table}`");
        $row = $res ? $res->fetch_assoc() : null;
        return max(1, (int)($row['nid'] ?? 1));
    };

    $fetchAssoc = static function(mysqli_stmt $stmt) {
        if (method_exists($stmt, 'get_result')) {
            $res = $stmt->get_result();
            if ($res instanceof mysqli_result) {
                return $res->fetch_assoc() ?: null;
            }
        }
        $meta = $stmt->result_metadata();
        if (!$meta) return null;
        $row = [];
        $bind = [];
        while ($f = $meta->fetch_field()) {
            $row[$f->name] = null;
            $bind[] = &$row[$f->name];
        }
        call_user_func_array([$stmt, 'bind_result'], $bind);
        if (!$stmt->fetch()) return null;
        $out = [];
        foreach ($row as $k => $v) $out[$k] = $v;
        return $out;
    };

    $questions = get_student_test_questions($testId);
    if (!is_array($questions)) {
        $questions = [];
    }
    $totalQuestions = $totalQuestionsPosted > 0 ? $totalQuestionsPosted : count($questions);

    $findInProgress = $db->prepare("SELECT id FROM test_attempts WHERE id=? AND student_id=? AND test_id=? AND status='in_progress' LIMIT 1");
    if ($attemptId > 0 && $findInProgress) {
        $findInProgress->bind_param('iii', $attemptId, $studentId, $testId);
        $findInProgress->execute();
        $inProgress = $fetchAssoc($findInProgress);
        $findInProgress->close();
        if (!$inProgress) {
            $done = $db->prepare("SELECT id FROM test_attempts WHERE id=? AND student_id=? AND test_id=? AND status IN ('submitted','pending_review','checked') LIMIT 1");
            if ($done) {
                $done->bind_param('iii', $attemptId, $studentId, $testId);
                $done->execute();
                $already = $fetchAssoc($done);
                $done->close();
                if ($already) {
                    $db->commit();
                    redirect('/test/student/attempt-view.php?attempt_id=' . $attemptId);
                }
            }
            $attemptId = 0;
        }
    }

    if ($attemptId <= 0) {
        $statusValue = 'in_progress';
        $statusMeta = $db->query("SHOW COLUMNS FROM test_attempts LIKE 'status'");
        $srow = $statusMeta ? $statusMeta->fetch_assoc() : null;
        if (!empty($srow['Type'])) {
            $stype = strtolower((string)$srow['Type']);
            if (strpos($stype, 'enum(') === 0) {
                preg_match_all("/'([^']+)'/", $stype, $mm);
                $opts = $mm[1] ?? [];
                if (!in_array($statusValue, $opts, true) && count($opts) > 0) {
                    $statusValue = (string)$opts[0];
                }
            }
        }
        $createErrors = [];
        $attemptsNeedsManualId = !$isAutoIncrementId($db, 'test_attempts');
        $manualAttemptId = $attemptsNeedsManualId ? $nextId($db, 'test_attempts') : null;
        $createVariants = [
            ["INSERT INTO test_attempts (test_id, student_id, teacher_id, subject_id, started_at, duration_minutes, total_questions, status) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?)", 'iiiiiis', [$testId, $studentId, $teacherId, $subjectId, $duration, $totalQuestions, $statusValue]],
            ["INSERT INTO test_attempts (test_id, student_id, teacher_id, subject_id, duration_minutes, total_questions, status) VALUES (?, ?, ?, ?, ?, ?, ?)", 'iiiiiis', [$testId, $studentId, $teacherId, $subjectId, $duration, $totalQuestions, $statusValue]],
            ["INSERT INTO test_attempts (test_id, student_id, duration_minutes, total_questions, status) VALUES (?, ?, ?, ?, ?)", 'iiiis', [$testId, $studentId, $duration, $totalQuestions, $statusValue]],
            ["INSERT INTO test_attempts (test_id, student_id, status) VALUES (?, ?, ?)", 'iis', [$testId, $studentId, $statusValue]],
            ["INSERT INTO test_attempts (test_id, student_id) VALUES (?, ?)", 'ii', [$testId, $studentId]],
        ];
        if ($attemptsNeedsManualId) {
            $createVariants = [
                ["INSERT INTO test_attempts (id, test_id, student_id, teacher_id, subject_id, started_at, duration_minutes, total_questions, status) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?)", 'iiiiiiis', [$manualAttemptId, $testId, $studentId, $teacherId, $subjectId, $duration, $totalQuestions, $statusValue]],
                ["INSERT INTO test_attempts (id, test_id, student_id, teacher_id, subject_id, duration_minutes, total_questions, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)", 'iiiiiiis', [$manualAttemptId, $testId, $studentId, $teacherId, $subjectId, $duration, $totalQuestions, $statusValue]],
                ["INSERT INTO test_attempts (id, test_id, student_id, duration_minutes, total_questions, status) VALUES (?, ?, ?, ?, ?, ?)", 'iiiiis', [$manualAttemptId, $testId, $studentId, $duration, $totalQuestions, $statusValue]],
                ["INSERT INTO test_attempts (id, test_id, student_id, status) VALUES (?, ?, ?, ?)", 'iiis', [$manualAttemptId, $testId, $studentId, $statusValue]],
                ["INSERT INTO test_attempts (id, test_id, student_id) VALUES (?, ?, ?)", 'iii', [$manualAttemptId, $testId, $studentId]],
            ];
        }
        foreach ($createVariants as $cv) {
            [$sql, $types, $vals] = $cv;
            $attemptCreate = $db->prepare($sql);
            if (!$attemptCreate) {
                $createErrors[] = 'prepare: ' . $db->error . ' | SQL: ' . $sql;
                continue;
            }
            $attemptCreate->bind_param($types, ...$vals);
            if ($attemptCreate->execute()) {
                $attemptId = (int) $db->insert_id;
                $attemptCreate->close();
                break;
            }
            $createErrors[] = 'execute: ' . $attemptCreate->error . ' | SQL: ' . $sql;
            $attemptCreate->close();
        }
        if ($attemptId <= 0) {
            throw new RuntimeException('attempt create failed: ' . implode(' || ', $createErrors));
        }
    }
    if ($attemptId <= 0) {
        throw new RuntimeException('attempt id is invalid after creation');
    }

    $correct = 0;
    $wrong = 0;
    $pending = 0;
    $ansCols = [];
    $ansMeta = [];
    $canWriteAnswers = true;

    $clear = $db->prepare("DELETE FROM test_attempt_answers WHERE attempt_id=?");
    if ($clear) {
        $clear->bind_param('i', $attemptId);
        if (!$clear->execute()) {
            $canWriteAnswers = false;
            $answersWarning = 'answer cleanup failed: ' . $clear->error;
        }
        $clear->close();
    } else {
        $canWriteAnswers = false;
        $answersWarning = 'answer cleanup prepare failed: ' . $db->error;
    }

    if ($canWriteAnswers) {
        $ansRes = $db->query("SHOW COLUMNS FROM test_attempt_answers");
        while ($ansRes && ($c = $ansRes->fetch_assoc())) {
            $name = (string)($c['Field'] ?? '');
            if ($name === '') continue;
            $ansCols[] = $name;
            $ansMeta[$name] = $c;
        }
    }
    $answersNeedsManualId = !$isAutoIncrementId($db, 'test_attempt_answers');
    $manualAnswerId = $answersNeedsManualId ? $nextId($db, 'test_attempt_answers') : 0;

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

        $baseMap = [
            'attempt_id' => $attemptId,
            'question_id' => $qid,
            'question_type' => $qtype,
            'question_text' => $qtext,
            'option_a' => $oa,
            'option_b' => $ob,
            'option_c' => $oc,
            'option_d' => $od,
            'correct_option' => $co,
            'student_answer_option' => $ansOpt,
            'student_answer_text' => $ansText,
            'is_correct' => $isCorrect,
            'checked_by_teacher' => $checkedByTeacher,
        ];
        if ($answersNeedsManualId) {
            $baseMap = ['id' => $manualAnswerId++] + $baseMap;
        }

        $fields = [];
        $types = '';
        $vals = [];
        foreach ($baseMap as $f => $v) {
            if (!in_array($f, $ansCols, true)) continue;
            $fields[] = $f;
            if (is_int($v) || is_bool($v)) $types .= 'i';
            elseif (is_float($v)) $types .= 'd';
            else $types .= 's';
            $vals[] = $v;
        }

        foreach ($ansMeta as $f => $m) {
            if (in_array($f, $fields, true)) continue;
            if (strtoupper((string)($m['Key'] ?? '')) === 'PRI' || strtolower($f) === 'id') continue;
            $isReq = strtoupper((string)($m['Null'] ?? 'YES')) === 'NO';
            $hasDef = array_key_exists('Default', $m) && $m['Default'] !== null;
            $extra = strtolower((string)($m['Extra'] ?? ''));
            if (!$isReq || $hasDef || strpos($extra, 'auto_increment') !== false) continue;

            $t = strtolower((string)($m['Type'] ?? ''));
            $fv = '';
            $tc = 's';
            if (preg_match('/^(tinyint|smallint|mediumint|int|bigint)/', $t)) { $fv = 0; $tc = 'i'; }
            elseif (preg_match('/^(float|double|decimal)/', $t)) { $fv = 0; $tc = 'd'; }
            elseif (strpos($t, 'enum(') === 0) { preg_match_all("/'([^']+)'/", $t, $mm); $opts = $mm[1] ?? []; $fv = count($opts) ? (string)$opts[0] : ''; $tc = 's'; }
            elseif (strpos($t, 'date') !== false || strpos($t, 'time') !== false) { $fv = date('Y-m-d H:i:s'); $tc = 's'; }
            $fields[] = $f; $types .= $tc; $vals[] = $fv;
        }

        if (empty($fields)) {
            $canWriteAnswers = false;
            $answersWarning = 'answer insert fields unavailable';
            continue;
        }

        if ($canWriteAnswers) {
            $sqlIns = "INSERT INTO test_attempt_answers (" . implode(',', $fields) . ") VALUES (" . implode(',', array_fill(0, count($fields), '?')) . ")";
            $ins = $db->prepare($sqlIns);
            if (!$ins) {
                $canWriteAnswers = false;
                $answersWarning = 'answer insert prepare failed: ' . $db->error;
                continue;
            }
            $ins->bind_param($types, ...$vals);
            if (!$ins->execute()) {
                $canWriteAnswers = false;
                $answersWarning = 'answer insert execute failed: ' . $ins->error;
            }
            $ins->close();
        }
    }

    $openTotal = max(1, $correct + $wrong);
    $scorePercent = (int) round(($correct / $openTotal) * 100);
    $status = $pending > 0 ? 'pending_review' : 'submitted';
    $atCols = [];
    $atMeta = [];
    $atRes = $db->query("SHOW COLUMNS FROM test_attempts");
    while ($atRes && ($c = $atRes->fetch_assoc())) { $n=(string)($c['Field'] ?? ''); if($n==='') continue; $atCols[]=$n; $atMeta[$n]=$c; }
    if (isset($atMeta['status'])) {
        $stType = strtolower((string)($atMeta['status']['Type'] ?? ''));
        if (strpos($stType, 'enum(') === 0) {
            preg_match_all("/'([^']+)'/", $stType, $m);
            $opts = $m[1] ?? [];
            if (!in_array($status, $opts, true) && count($opts) > 0) $status = (string)$opts[0];
        }
    }

    $setParts = [];
    $setTypes = '';
    $setVals = [];
    $put = static function($col, $val, $type='s') use (&$setParts, &$setTypes, &$setVals, $atCols) {
        if (!in_array($col, $atCols, true)) return;
        $setParts[] = $col . "=?";
        $setTypes .= $type;
        $setVals[] = $val;
    };
    $put('submitted_at', date('Y-m-d H:i:s'), 's');
    $put('finished_at', date('Y-m-d H:i:s'), 's');
    $put('duration_spent', 0, 'i');
    $put('correct_count', $correct, 'i');
    $put('wrong_count', $wrong, 'i');
    $put('pending_count', $pending, 'i');
    $put('score_percent', $scorePercent, 'i');
    $put('status', $status, 's');
    if (empty($setParts)) {
        $submitWarning = 'attempt update columns unavailable';
    }
    if (!empty($setParts)) {
        $sqlUp = "UPDATE test_attempts SET " . implode(', ', $setParts) . " WHERE id=? LIMIT 1";
        $setTypes .= 'i';
        $setVals[] = $attemptId;
        $up = $db->prepare($sqlUp);
        if (!$up) {
            $submitWarning = 'attempt update prepare failed: ' . $db->error;
        } else {
            $up->bind_param($setTypes, ...$setVals);
            if (!$up->execute()) {
                $submitWarning = 'attempt update execute failed: ' . $up->error;
            }
            $up->close();
        }
    }

    if ($answersWarning) {
        error_log('Student test-submit warning: ' . $answersWarning . ' | attempt_id=' . $attemptId);
    }
    if ($submitWarning) {
        error_log('Student test-submit warning: ' . $submitWarning . ' | attempt_id=' . $attemptId);
    }

    flash_set('success', 'Test yakunlandi.');
    redirect('/test/student/attempt-view.php?attempt_id=' . $attemptId);
} catch (Throwable $e) {
    error_log('Student test-submit error: ' . $e->getMessage());
    flash_set('error', 'Yakunlashda xatolik yuz berdi: ' . $e->getMessage());
    redirect('/test/student/dashboard.php?section=tests');
}
