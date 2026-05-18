<?php
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../shared/dashboard_repository.php';
require_once __DIR__ . '/../layouts/role_dashboard.php';

require_auth(['teacher']);
$user = auth_user();
$teacherId = (int) ($user['id'] ?? 0);
$attemptId = (int) ($_GET['attempt_id'] ?? 0);
$csrf = csrf_token();
$error = flash_get('error');
$success = flash_get('success');

$attempt = get_teacher_attempt_detail($teacherId, $attemptId);
if (!$attempt) {
    flash_set('error', 'Urinish topilmadi.');
    redirect('/olimpiada.uz/test/teacher/results.php');
}
$answers = get_attempt_answers($attemptId);

$menu = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'href' => '/olimpiada.uz/test/teacher/dashboard.php'],
    ['key' => 'subjects', 'label' => 'Fanlar', 'icon' => 'teachers', 'href' => '/olimpiada.uz/test/teacher/subjects.php'],
    ['key' => 'tests', 'label' => 'Testlar', 'icon' => 'tests', 'href' => '/olimpiada.uz/test/teacher/tests.php'],
    ['key' => 'results', 'label' => 'Natijalar', 'icon' => 'results', 'href' => '/olimpiada.uz/test/teacher/results.php'],
    ['key' => 'profile', 'label' => 'Profil', 'icon' => 'profile', 'href' => '/olimpiada.uz/test/teacher/profile.php'],
    ['key' => 'settings', 'label' => 'Sozlamalar', 'icon' => 'settings', 'href' => '/olimpiada.uz/test/teacher/settings.php'],
];

ob_start();
?>
<?php if ($error || $success): ?>
  <div class="rounded-xl px-4 py-3 text-sm font-semibold text-white <?= $error ? 'bg-rose-500' : 'bg-emerald-500' ?>"><?= h($error ?: $success) ?></div>
<?php endif; ?>

<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-3">
  <div class="flex items-center justify-between">
    <h2 class="font-heading text-lg font-semibold"><?= h($attempt['student_name'] ?? ('Student #' . (int) ($attempt['student_id'] ?? 0))) ?> natijasi</h2>
    <a href="/olimpiada.uz/test/teacher/results.php" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">Orqaga</a>
  </div>
  <p class="text-sm text-slate-600">Test: <strong><?= h($attempt['test_title'] ?? '-') ?></strong> | Fan: <strong><?= h($attempt['subject_name'] ?? '-') ?></strong></p>
  <p class="text-sm text-slate-600">To'g'ri: <strong><?= (int) ($attempt['correct_count'] ?? 0) ?></strong> | Xato: <strong><?= (int) ($attempt['wrong_count'] ?? 0) ?></strong> | Kutilmoqda: <strong><?= (int) ($attempt['pending_count'] ?? 0) ?></strong> | Holat: <strong><?= h($attempt['status'] ?? '-') ?></strong></p>
</section>

<div class="mt-4 space-y-3">
  <?php foreach ($answers as $a): ?>
    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <p class="text-xs text-slate-500 mb-1">Savol #<?= (int) $a['question_id'] ?> | <?= h($a['question_type'] ?? 'closed') ?></p>
      <div class="font-medium mb-2 prose max-w-none"><?= (string)($a['question_text'] ?? '') ?></div>

      <?php if (($a['question_type'] ?? 'closed') === 'open'): ?>
        <p class="text-sm">O'quvchi javobi: <strong><?= h($a['student_answer_option'] ?? '-') ?></strong></p>
        <p class="text-sm">To'g'ri javob: <strong><?= h($a['correct_option'] ?? '-') ?></strong></p>
        <p class="text-sm mt-1 <?= (int) ($a['is_correct'] ?? 0) === 1 ? 'text-emerald-700' : 'text-rose-700' ?>"><?= (int) ($a['is_correct'] ?? 0) === 1 ? 'To\'g\'ri' : 'Xato' ?></p>
      <?php else: ?>
        <p class="text-sm">O'quvchi yozgan javob:</p>
        <div class="mt-1 rounded-xl bg-slate-50 p-2 text-sm prose max-w-none"><?= (string)($a['student_answer_text'] ?? '') ?></div>

        <?php if ((int) ($a['checked_by_teacher'] ?? 0) === 1): ?>
          <p class="mt-2 text-sm <?= (int) ($a['is_correct'] ?? 0) === 1 ? 'text-emerald-700' : 'text-rose-700' ?>">Baholangan: <?= (int) ($a['is_correct'] ?? 0) === 1 ? 'To\'g\'ri' : 'Xato' ?></p>
        <?php else: ?>
          <form method="POST" action="/olimpiada.uz/test/teacher/update/attempt-answer.php" class="mt-2 flex flex-wrap gap-2">
            <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="attempt_id" value="<?= (int) $attemptId ?>">
            <input type="hidden" name="answer_id" value="<?= (int) ($a['id'] ?? 0) ?>">
            <button name="mark" value="1" class="rounded-xl bg-emerald-600 px-3 py-2 text-sm font-semibold text-white">To'g'ri</button>
            <button name="mark" value="0" class="rounded-xl bg-rose-600 px-3 py-2 text-sm font-semibold text-white">Xato</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
</div>

<?php
$content = ob_get_clean();

render_dashboard_layout([
    'title' => 'Teacher Natija Detali',
    'page_title' => 'Urinish Detali',
    'subtitle' => 'Savollar bo\'yicha to\'g\'ri/xato ko\'rish va baholash',
    'role_name' => 'Teacher',
    'menu' => $menu,
    'active' => 'results',
    'user' => $user,
    'content' => $content,
]);
