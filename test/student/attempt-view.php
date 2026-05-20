<?php
require_once __DIR__ . '/_shared.php';

require_auth(['student']);
$user = auth_user();
$studentId = (int) ($user['id'] ?? 0);
$attemptId = (int) ($_GET['attempt_id'] ?? 0);
$error = flash_get('error');
$success = flash_get('success');

$db = db_conn();
ensure_test_runtime_tables($db);

$st = $db->prepare("SELECT a.*, t.title, s.name AS subject_name
                   FROM test_attempts a
                   LEFT JOIN tests t ON t.id=a.test_id
                   LEFT JOIN subjects s ON s.id=a.subject_id
                   WHERE a.id=? AND a.student_id=? LIMIT 1");
$st->bind_param('ii', $attemptId, $studentId);
$st->execute();
$attempt = $st->get_result()->fetch_assoc();
$st->close();
if (!$attempt) {
    flash_set('error', 'Natija topilmadi.');
    redirect('/test/student/my-results.php');
}

$ansSt = $db->prepare("SELECT * FROM test_attempt_answers WHERE attempt_id=? ORDER BY question_id ASC");
$ansSt->bind_param('i', $attemptId);
$ansSt->execute();
$res = $ansSt->get_result();
$answers = [];
while ($res && ($row = $res->fetch_assoc())) {
    $answers[] = $row;
}
$ansSt->close();

ob_start();
?>
<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
<?php if ($error || $success): ?>
  <div class="rounded-xl px-4 py-3 text-sm font-semibold text-white <?= $error ? 'bg-rose-500' : 'bg-emerald-500' ?>"><?= h($error ?: $success) ?></div>
<?php endif; ?>

<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-3">
  <div class="flex items-center justify-between">
    <h2 class="font-heading text-lg font-semibold"><?= h($attempt['title'] ?? 'Natija') ?></h2>
    <a class="rounded-xl border border-slate-300 px-3 py-2 text-sm" href="/test/student/my-results.php">Orqaga</a>
  </div>
  <p class="text-sm text-slate-500">Fan: <?= h($attempt['subject_name'] ?? '-') ?> | Status: <?= h($attempt['status'] ?? '-') ?></p>
  <p class="text-sm text-slate-700">Boshlagan: <strong><?= h((string)($attempt['started_at'] ?? '-')) ?></strong> | Tugatgan: <strong><?= h((string)($attempt['finished_at'] ?? ($attempt['submitted_at'] ?? '-'))) ?></strong> | Vaqt: <strong><?= (int)($attempt['duration_spent'] ?? 0) ?> sek</strong></p>
  <p class="text-sm text-slate-700">To'g'ri: <strong><?= (int) ($attempt['correct_count'] ?? 0) ?></strong> | Xato: <strong><?= (int) ($attempt['wrong_count'] ?? 0) ?></strong> | Tekshirilmagan: <strong><?= (int) ($attempt['pending_count'] ?? 0) ?></strong></p>
</section>

<div class="mt-4 space-y-3">
  <?php foreach ($answers as $a): ?>
    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <p class="text-xs text-slate-500 mb-1">Savol #<?= (int) $a['question_id'] ?> | <?= h($a['question_type']) ?></p>
      <div class="font-medium mb-2 prose max-w-none"><?= (string)$a['question_text'] ?></div>

      <?php if (($a['question_type'] ?? 'closed') === 'open'): ?>
        <p class="text-sm">Sizning javobingiz: <strong><?= h($a['student_answer_option'] ?? '-') ?></strong></p>
        <p class="text-sm">To'g'ri javob: <strong><?= h($a['correct_option'] ?? '-') ?></strong></p>
        <p class="text-sm mt-1 <?= (int) ($a['is_correct'] ?? 0) === 1 ? 'text-emerald-700' : 'text-rose-700' ?>"><?= (int) ($a['is_correct'] ?? 0) === 1 ? 'To\'g\'ri' : 'Xato' ?></p>
      <?php else: ?>
        <p class="text-sm">Sizning javobingiz:</p>
        <div class="mt-1 rounded-xl bg-slate-50 p-2 text-sm"><?= nl2br(h($a['student_answer_text'] ?? '')) ?></div>
        <?php if ((int) ($a['checked_by_teacher'] ?? 0) === 1): ?>
          <p class="text-sm mt-2 <?= (int) ($a['is_correct'] ?? 0) === 1 ? 'text-emerald-700' : 'text-rose-700' ?>">O'qituvchi baholashi: <?= (int) ($a['is_correct'] ?? 0) === 1 ? 'To\'g\'ri' : 'Xato' ?></p>
        <?php else: ?>
          <p class="text-sm mt-2 text-amber-700">Yopiq savol: o'qituvchi tekshirganidan keyin baho ko'rinadi.</p>
        <?php endif; ?>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
</div>
<?php
$content = ob_get_clean();

render_dashboard_layout([
    'title' => 'Student Natija',
    'page_title' => 'Natija Detali',
    'subtitle' => 'Ishlangan test bo\'yicha tafsilot',
    'role_name' => 'Student',
    'menu' => student_menu(),
    'active' => 'my-results',
    'user' => $user,
    'content' => $content,
]);
