<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../database/Database.php';
require_once __DIR__ . '/../../shared/dashboard_repository.php';
require_once __DIR__ . '/../../layouts/role_dashboard.php';

require_auth(['teacher']);
$user = auth_user();
$teacherId = (int) ($user['id'] ?? 0);
$csrf = csrf_token();
$error = flash_get('error');
$success = flash_get('success');
$testId = (int) ($_GET['test_id'] ?? 0);
$qTypeFilter = trim((string) ($_GET['qtype'] ?? ''));
$deletedFlag = (int) ($_GET['deleted'] ?? 0) === 1;

$db = Database::connection();
ensure_teacher_tables($db);

$test = null;
$questions = [];
$totalQuestionsCount = 0;
if ($testId > 0) {
    $st = $db->prepare('SELECT t.id,t.title,t.duration_minutes,t.question_limit,t.visibility,t.private_code,s.name AS subject_name FROM tests t LEFT JOIN subjects s ON s.id=t.subject_id WHERE t.id=? AND t.teacher_id=? LIMIT 1');
    $st->bind_param('ii', $testId, $teacherId);
    $st->execute();
    $test = $st->get_result()->fetch_assoc();
    $st->close();

    if ($test) {
        $cq = $db->prepare('SELECT COUNT(*) AS cnt FROM test_questions WHERE test_id=? AND teacher_id=?');
        $cq->bind_param('ii', $testId, $teacherId);
        $cq->execute();
        $crow = $cq->get_result()->fetch_assoc();
        $cq->close();
        $totalQuestionsCount = (int) ($crow['cnt'] ?? 0);

        if (in_array($qTypeFilter, ['open', 'closed'], true)) {
            $sq = $db->prepare('SELECT id,question_text,question_type,correct_option,created_at FROM test_questions WHERE test_id=? AND teacher_id=? AND question_type=? ORDER BY id DESC');
            $sq->bind_param('iis', $testId, $teacherId, $qTypeFilter);
        } else {
            $sq = $db->prepare('SELECT id,question_text,question_type,correct_option,created_at FROM test_questions WHERE test_id=? AND teacher_id=? ORDER BY id DESC');
            $sq->bind_param('ii', $testId, $teacherId);
        }
        $sq->execute();
        $r = $sq->get_result();
        while ($x = $r->fetch_assoc()) {
            $questions[] = $x;
        }
        $sq->close();
    }
}

ob_start();
?>
<?php if ($error || $success): ?>
  <div class="rounded-xl px-4 py-3 text-sm font-semibold text-white <?= $error ? 'bg-rose-500' : 'bg-emerald-500' ?>"><?= h($error ?: $success) ?></div>
<?php endif; ?>

<?php if (!$test): ?>
<section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-700">
  Test topilmadi yoki sizga tegishli emas.
</section>
<?php else: ?>
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div>
      <h2 class="font-heading text-lg font-semibold">Test savollari: <?= h($test['title'] ?? '-') ?></h2>
      <p class="text-sm text-slate-500">Savollar ro'yxati, edit/delete/view</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <a href="/test/teacher/tests/create-question.php?test_id=<?= (int)$testId ?>" class="rounded-xl bg-green-700 px-4 py-2 text-sm font-semibold text-white">+ Savol qo'shish</a>
      <form method="POST" action="/test/teacher/insert/import-test-questions.php" enctype="multipart/form-data" class="flex items-center gap-2">
        <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="test_id" value="<?= (int)$testId ?>">
        <input type="file" name="excel_file" accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" required class="max-w-[180px] rounded-xl border border-slate-300 bg-white px-2 py-1.5 text-xs">
        <button class="rounded-xl border border-blue-300 bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-100">Excel import</button>
      </form>
      <a href="/test/teacher/tests/index.php" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">Orqaga</a>
    </div>
  </div>

  <div class="grid gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm md:grid-cols-2 lg:grid-cols-5">
    <div><p class="text-slate-500">Fan</p><p class="font-semibold text-slate-800"><?= h($test['subject_name'] ?? '-') ?></p></div>
    <div><p class="text-slate-500">Davomiyligi</p><p class="font-semibold text-slate-800"><?= (int) ($test['duration_minutes'] ?? 0) ?> min</p></div>
    <div><p class="text-slate-500">Savollar soni limiti</p><p class="font-semibold text-slate-800"><?= (int) ($test['question_limit'] ?? 0) ?></p></div>
    <div><p class="text-slate-500">Jami qo'shilgan savollar</p><p class="font-semibold text-slate-800"><?= $totalQuestionsCount ?></p></div>
    <div><p class="text-slate-500">Status</p><p class="font-semibold text-slate-800"><?= (($test['visibility'] ?? 'open') === 'closed') ? 'Private #' . h($test['private_code'] ?? '-') : 'Public' ?></p></div>
  </div>

  <div class="flex items-center justify-between gap-2">
    <form method="GET" id="qtypeFilterForm" class="flex items-center gap-2">
      <input type="hidden" name="test_id" value="<?= (int)$testId ?>">
      <select name="qtype" id="qtypeFilterSelect" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
        <option value="">Barcha turlar</option>
        <option value="open" <?= $qTypeFilter === 'open' ? 'selected' : '' ?>>Ochiq</option>
        <option value="closed" <?= $qTypeFilter === 'closed' ? 'selected' : '' ?>>Yopiq</option>
      </select>
    </form>
    <a href="/test/teacher/export-test-questions.php?test_id=<?= (int)$testId ?>" class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Export (CSV)</a>
  </div>

  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50">
        <tr>
          <th class="px-3 py-2 text-left">Savol</th>
          <th class="px-3 py-2 text-left">Turi</th>
          <th class="px-3 py-2 text-left">To'g'ri javob</th>
          <th class="px-3 py-2 text-left">Sana</th>
          <th class="px-3 py-2 text-left">Amallar</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($questions as $q): ?>
          <tr class="border-t hover:bg-slate-50/70">
            <td class="formula-content px-3 py-2"><?= (string) ($q['question_text'] ?? '') ?></td>
            <td class="px-3 py-2"><?= h($q['question_type'] ?? 'closed') ?></td>
            <td class="px-3 py-2"><?= h($q['correct_option'] ?? '-') ?></td>
            <td class="px-3 py-2"><?= h($q['created_at'] ?? '-') ?></td>
            <td class="px-3 py-2">
              <div class="flex items-center gap-2">
                <a href="/test/teacher/tests/edit-question.php?id=<?= (int)($q['id'] ?? 0) ?>&test_id=<?= (int)$testId ?>" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-300 text-slate-600 hover:bg-slate-50" title="Edit">
                  <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg>
                </a>
                <button type="button" class="deleteQuestion inline-flex h-9 w-9 items-center justify-center rounded-xl border border-rose-200 text-rose-600 hover:bg-rose-50" data-id="<?= (int)($q['id'] ?? 0) ?>" title="Delete">
                  <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                </button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<form id="deleteQuestionForm" method="POST" action="/test/teacher/delete/test-question.php" class="hidden">
  <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
  <input type="hidden" name="id" id="delete_question_id">
  <input type="hidden" name="test_id" value="<?= (int)$testId ?>">
</form>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  window.MathJax = { tex: { inlineMath: [['\\(', '\\)']] } };
</script>
<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
<script>
  document.querySelectorAll('.deleteQuestion').forEach((btn) => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.id || '';
      Swal.fire({
        title: "Savolni o‘chirmoqchimisiz?",
        text: "Bu amalni ortga qaytarib bo‘lmaydi.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: "Ha, o‘chirish",
        cancelButtonText: 'Bekor qilish',
        reverseButtons: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280'
      }).then((result) => {
        if (result.isConfirmed) {
          document.getElementById('delete_question_id').value = id;
          document.getElementById('deleteQuestionForm').submit();
        }
      });
    });
  });

  if (window.MathJax && window.MathJax.typesetPromise) {
    window.MathJax.typesetPromise(document.querySelectorAll('.formula-content'));
  }

  document.getElementById('qtypeFilterSelect')?.addEventListener('change', () => {
    document.getElementById('qtypeFilterForm')?.submit();
  });

  <?php if ($deletedFlag): ?>
    Swal.fire({ icon: 'success', title: 'Savol muvaffaqiyatli o‘chirildi', timer: 1600, showConfirmButton: false });
  <?php endif; ?>
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
render_dashboard_layout([
    'title' => 'Test savollari',
    'page_title' => 'Savollar ro\'yxati',
    'subtitle' => 'Questions list page',
    'role_name' => 'Teacher',
    'menu' => [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'href' => '/test/teacher/dashboard.php'],
        ['key' => 'subjects', 'label' => 'Fanlar', 'icon' => 'teachers', 'href' => '/test/teacher/subjects.php'],
        ['key' => 'tests', 'label' => 'Testlar', 'icon' => 'tests', 'href' => '/test/teacher/tests/index.php'],
        ['key' => 'results', 'label' => 'Natijalar', 'icon' => 'results', 'href' => '/test/teacher/results.php'],
    ],
    'active' => 'tests',
    'user' => $user,
    'content' => $content,
]);
