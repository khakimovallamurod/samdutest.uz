<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../database/Database.php';
require_once __DIR__ . '/../../shared/dashboard_repository.php';

require_auth(['teacher']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('/test/teacher/tests.php'); }
if (!verify_csrf($_POST['_csrf'] ?? '')) { flash_set('error', 'Xavfsizlik tekshiruvi muvaffaqiyatsiz.'); redirect('/test/teacher/tests.php'); }

$teacherId = (int) (auth_user()['id'] ?? 0);
$testId = (int) ($_POST['test_id'] ?? 0);
if ($testId <= 0) { flash_set('error', 'Noto\'g\'ri test ID.'); redirect('/test/teacher/tests.php'); }
if (empty($_FILES['excel_file']['tmp_name'])) { flash_set('error', 'Excel fayl tanlanmagan.'); redirect('/test/teacher/test-questions.php?test_id=' . $testId); }

$db = Database::connection();
ensure_teacher_tables($db);

$check = $db->prepare('SELECT id FROM tests WHERE id=? AND teacher_id=? LIMIT 1');
$check->bind_param('ii', $testId, $teacherId);
$check->execute();
$exists = $check->get_result()->fetch_assoc();
$check->close();
if (!$exists) { flash_set('error', 'Test topilmadi.'); redirect('/test/teacher/tests.php'); }

require_once __DIR__ . '/../../admin/excel/PHPExcel/Classes/PHPExcel.php';

$sanitize = function ($html) {
    $html = (string) $html;
    $allowed = '<p><br><b><strong><i><em><u><s><sub><sup><ol><ul><li><span><div><img><table><tbody><thead><tr><td><th><math><mrow><mi><mn><mo><msup><msub><msubsup><mfrac><msqrt><mroot><mtext><mfenced><mtable><mtr><mtd>';
    return trim(strip_tags($html, $allowed));
};

$tmp = $_FILES['excel_file']['tmp_name'];
try {
    $reader = PHPExcel_IOFactory::createReaderForFile($tmp);
    $reader->setReadDataOnly(true);
    $excel = $reader->load($tmp);
    $sheet = $excel->getActiveSheet();
    $rows = $sheet->toArray('', true, true, true);
} catch (Throwable $e) {
    flash_set('error', 'Excel o\'qib bo\'lmadi: ' . $e->getMessage());
    redirect('/test/teacher/test-questions.php?test_id=' . $testId);
}

$insert = $db->prepare('INSERT INTO test_questions (test_id,teacher_id,question_text,question_type,option_a,option_b,option_c,option_d,correct_option,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())');
if (!$insert) {
    flash_set('error', 'INSERT prepare xatoligi: ' . $db->error);
    redirect('/test/teacher/test-questions.php?test_id=' . $testId);
}

$created = 0;
foreach ($rows as $idx => $row) {
    if ($idx === 1) { continue; }
    $q = $sanitize($row['A'] ?? '');
    if (trim(strip_tags($q)) === '') { continue; }

    $qt = strtolower(trim((string)($row['B'] ?? 'closed')));
    if (!in_array($qt, ['open', 'closed'], true)) { $qt = 'closed'; }

    $a = $sanitize($row['C'] ?? '');
    $b = $sanitize($row['D'] ?? '');
    $c = $sanitize($row['E'] ?? '');
    $d = $sanitize($row['F'] ?? '');
    $co = strtoupper(trim((string)($row['G'] ?? '')));

    if ($qt === 'open') {
        if ($a === '' || $b === '' || $c === '' || $d === '' || !in_array($co, ['A', 'B', 'C', 'D'], true)) {
            continue;
        }
    } else {
        $a = $b = $c = $d = null;
        $co = null;
    }

    $insert->bind_param('iisssssss', $testId, $teacherId, $q, $qt, $a, $b, $c, $d, $co);
    if ($insert->execute()) {
        $created++;
    }
}

$insert->close();
flash_set($created > 0 ? 'success' : 'error', $created > 0 ? ($created . ' ta savol import qilindi.') : 'Importda yaroqli satr topilmadi.');
redirect('/test/teacher/test-questions.php?test_id=' . $testId);
