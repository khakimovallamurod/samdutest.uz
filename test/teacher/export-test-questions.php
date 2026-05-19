<?php
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../database/Database.php';
require_once __DIR__ . '/../shared/dashboard_repository.php';

require_auth(['teacher']);
$teacherId = (int) (auth_user()['id'] ?? 0);
$testId = (int) ($_GET['test_id'] ?? 0);
if ($testId <= 0) {
    http_response_code(400);
    exit('Noto\'g\'ri test ID');
}

$db = Database::connection();
ensure_teacher_tables($db);

$testStmt = $db->prepare('SELECT id, title FROM tests WHERE id=? AND teacher_id=? LIMIT 1');
$testStmt->bind_param('ii', $testId, $teacherId);
$testStmt->execute();
$test = $testStmt->get_result()->fetch_assoc();
$testStmt->close();
if (!$test) {
    http_response_code(404);
    exit('Test topilmadi');
}

$qStmt = $db->prepare('SELECT question_text,question_type,option_a,option_b,option_c,option_d,correct_option FROM test_questions WHERE test_id=? AND teacher_id=? ORDER BY id ASC');
$qStmt->bind_param('ii', $testId, $teacherId);
$qStmt->execute();
$res = $qStmt->get_result();

$filename = 'test-' . $testId . '-questions-' . date('Ymd-His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'w');
fputcsv($out, ['question_text', 'question_type', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option']);

while ($row = $res->fetch_assoc()) {
    $line = [
        trim(strip_tags((string) ($row['question_text'] ?? ''))),
        (string) ($row['question_type'] ?? 'closed'),
        trim(strip_tags((string) ($row['option_a'] ?? ''))),
        trim(strip_tags((string) ($row['option_b'] ?? ''))),
        trim(strip_tags((string) ($row['option_c'] ?? ''))),
        trim(strip_tags((string) ($row['option_d'] ?? ''))),
        (string) ($row['correct_option'] ?? ''),
    ];
    fputcsv($out, $line);
}

fclose($out);
$qStmt->close();
exit;
