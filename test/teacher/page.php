<?php
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../shared/dashboard_repository.php';
require_once __DIR__ . '/../layouts/role_dashboard.php';

require_auth(['teacher']);
$user = auth_user();
$teacherId = (int) ($user['id'] ?? 0);
$csrf = csrf_token();
$error = flash_get('error');
$success = flash_get('success');

$section = strtolower(trim((string) ($section ?? 'dashboard')));
$allowed = ['dashboard', 'subjects', 'tests', 'results', 'profile', 'settings'];
if (!in_array($section, $allowed, true)) {
    $section = 'dashboard';
}

$menu = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'href' => '/test/teacher/dashboard.php'],
    ['key' => 'subjects', 'label' => 'Fanlar', 'icon' => 'teachers', 'href' => '/test/teacher/subjects.php'],
    ['key' => 'tests', 'label' => 'Testlar', 'icon' => 'tests', 'href' => '/test/teacher/tests/index.php'],
    ['key' => 'results', 'label' => 'Natijalar', 'icon' => 'results', 'href' => '/test/teacher/results.php'],
    ['key' => 'profile', 'label' => 'Profil', 'icon' => 'profile', 'href' => '/test/teacher/profile.php'],
    ['key' => 'settings', 'label' => 'Sozlamalar', 'icon' => 'settings', 'href' => '/test/teacher/settings.php'],
];

$metrics = get_teacher_metrics($teacherId);
$subjects = get_teacher_subjects($teacherId);
$subjectFilter = (int) ($_GET['subject_id'] ?? 0);
$tests = get_teacher_tests_filtered($teacherId, $subjectFilter, 200);
$resultTestFilter = (int) ($_GET['test_id'] ?? 0);
$teacherAttempts = get_teacher_attempts($teacherId, $resultTestFilter, 500);
$teacherTestsAll = get_teacher_tests_filtered($teacherId, 0, 500);

ob_start();
?>
<?php if ($error || $success): ?>
  <div class="rounded-xl px-4 py-3 text-sm font-semibold text-white <?= $error ? 'bg-rose-500' : 'bg-emerald-500' ?>"><?= h($error ?: $success) ?></div>
<?php endif; ?>

<?php if ($section === 'dashboard'): ?>
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
  <article class="rounded-2xl border border-cyan-100 bg-cyan-50 p-4 shadow-sm"><div class="mb-2 flex items-center justify-between"><p class="text-sm text-cyan-700">Yaratilgan testlar</p><span class="h-8 w-8 text-cyan-600"><?= icon_svg('tests') ?></span></div><p class="text-2xl font-bold text-cyan-900"><?= (int) $metrics['tests'] ?></p></article>
  <article class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4 shadow-sm"><div class="mb-2 flex items-center justify-between"><p class="text-sm text-emerald-700">Natijalar soni</p><span class="h-8 w-8 text-emerald-600"><?= icon_svg('results') ?></span></div><p class="text-2xl font-bold text-emerald-900"><?= (int) $metrics['results'] ?></p></article>
  <article class="rounded-2xl border border-amber-100 bg-amber-50 p-4 shadow-sm"><div class="mb-2 flex items-center justify-between"><p class="text-sm text-amber-700">Fanlar soni</p><span class="h-8 w-8 text-amber-600"><?= icon_svg('teachers') ?></span></div><p class="text-2xl font-bold text-amber-900"><?= count($subjects) ?></p></article>
</div>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php if ($section === 'subjects'): ?>
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
  <div class="mb-5 flex items-center justify-between">
    <h2 class="font-heading text-lg font-semibold">Fanlar</h2>
    <button id="openCreateSubject" type="button" class="rounded-xl bg-green-700 px-4 py-2.5 font-semibold text-white">Fan yaratish</button>
  </div>

  <div class="mt-5 overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50 text-slate-600">
        <tr><th class="px-4 py-3 text-left">Fan</th><th class="px-4 py-3 text-left">Holat</th><th class="px-4 py-3 text-left">Sana</th><th class="px-4 py-3 text-left">Amallar</th></tr>
      </thead>
      <tbody>
      <?php foreach ($subjects as $subject): ?>
        <tr class="border-t border-slate-100">
          <td class="px-4 py-3"><?= h($subject['name'] ?? '-') ?></td>
          <td class="px-4 py-3"><?= h($subject['status'] ?? 'active') ?></td>
          <td class="px-4 py-3"><?= h($subject['created_at'] ?? '-') ?></td>
          <td class="px-4 py-3">
            <div class="flex items-center gap-2">
              <button
                type="button"
                class="editSubject inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-300 text-slate-600 hover:bg-slate-50"
                data-id="<?= (int) ($subject['id'] ?? 0) ?>"
                data-name="<?= h($subject['name'] ?? '') ?>"
                data-status="<?= h($subject['status'] ?? 'active') ?>"
                title="Tahrirlash"
              ><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg></button>
              <button
                type="button"
                class="deleteSubject inline-flex h-9 w-9 items-center justify-center rounded-xl border border-rose-200 text-rose-600 hover:bg-rose-50"
                data-id="<?= (int) ($subject['id'] ?? 0) ?>"
                data-name="<?= h($subject['name'] ?? '') ?>"
                title="O'chirish"
              ><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg></button>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<div id="createSubjectModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 p-4">
  <div class="w-full max-w-xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl">
    <div class="mb-4 flex items-center justify-between">
      <h3 class="font-heading text-xl font-semibold">Fan yaratish</h3>
      <button type="button" data-close="createSubjectModal" class="text-slate-500">X</button>
    </div>
    <form method="POST" action="/test/teacher/insert/subject.php" class="space-y-3">
      <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
      <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Fan nomi</label>
        <input name="name" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none focus:border-cyan-600 focus:ring-4 focus:ring-cyan-200" placeholder="Masalan: Matematika">
      </div>
      <button class="rounded-xl bg-green-700 px-4 py-2.5 font-semibold text-white">Saqlash</button>
    </form>
  </div>
</div>

<div id="editSubjectModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 p-4">
  <div class="w-full max-w-xl rounded-2xl bg-white p-5 shadow-2xl">
    <div class="mb-4 flex items-center justify-between">
      <h3 class="font-heading text-xl font-semibold">Fan tahrirlash</h3>
      <button type="button" data-close="editSubjectModal" class="text-slate-500">X</button>
    </div>
    <form method="POST" action="/test/teacher/update/subject.php" class="space-y-3">
      <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
      <input type="hidden" name="id" id="edit_subject_id">
      <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Fan nomi</label>
        <input id="edit_subject_name" name="name" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none focus:border-cyan-600 focus:ring-4 focus:ring-cyan-200">
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Holat</label>
        <select id="edit_subject_status" name="status" class="w-full rounded-xl border border-slate-300 px-3 py-2.5">
          <option value="active">active</option>
          <option value="inactive">inactive</option>
        </select>
      </div>
      <button class="rounded-xl bg-cyan-700 px-4 py-2.5 font-semibold text-white">Yangilash</button>
    </form>
  </div>
</div>

<form id="deleteSubjectForm" method="POST" action="/test/teacher/delete/subject.php" class="hidden">
  <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
  <input type="hidden" name="id" id="delete_subject_id">
</form>

<script>
  const subById = (id) => document.getElementById(id);
  const subOpen = (id) => { const el = subById(id); if (!el) return; el.classList.remove('hidden'); el.classList.add('flex'); };
  const subClose = (id) => { const el = subById(id); if (!el) return; el.classList.add('hidden'); el.classList.remove('flex'); };

  subById('openCreateSubject')?.addEventListener('click', () => subOpen('createSubjectModal'));
  document.querySelectorAll('[data-close]').forEach((el) => {
    el.addEventListener('click', () => subClose(el.getAttribute('data-close')));
  });

  document.querySelectorAll('.editSubject').forEach((btn) => {
    btn.addEventListener('click', () => {
      subById('edit_subject_id').value = btn.dataset.id || '';
      subById('edit_subject_name').value = btn.dataset.name || '';
      subById('edit_subject_status').value = (btn.dataset.status || 'active');
      subOpen('editSubjectModal');
    });
  });

  document.querySelectorAll('.deleteSubject').forEach((btn) => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.id || '';
      const name = btn.dataset.name || 'Tanlangan fan';
      Swal.fire({
        title: 'Tasdiqlang',
        text: `${name} ni o'chirasizmi?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ha',
        cancelButtonText: "Yo'q",
        confirmButtonColor: '#6366f1',
        cancelButtonColor: '#6b7280',
        reverseButtons: true,
      }).then((result) => {
        if (result.isConfirmed) {
          subById('delete_subject_id').value = id;
          subById('deleteSubjectForm').submit();
        }
      });
    });
  });
</script>

<?php elseif ($section === 'tests'): ?>
<section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
  <div class="flex flex-wrap items-end justify-between gap-3 border-b border-slate-200 p-4">
    <div>
      <h2 class="font-heading text-lg font-semibold">Testlar</h2>
      <p class="text-sm text-slate-500">Avval test yarating, keyin Insert orqali savollar kiriting</p>
    </div>
    <div class="flex flex-wrap items-end gap-2">
      <form method="GET" action="/test/teacher/tests/index.php" class="flex items-end gap-2">
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Fan bo'yicha filter</label>
          <select name="subject_id" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
            <option value="0">Barcha fanlar</option>
            <?php foreach ($subjects as $subject): ?>
              <option value="<?= (int) $subject['id'] ?>" <?= $subjectFilter === (int) $subject['id'] ? 'selected' : '' ?>><?= h($subject['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="rounded-xl border border-slate-300 px-3 py-2 text-sm">Filter</button>
      </form>
      <a href="/test/teacher/tests/create.php" class="rounded-xl bg-green-700 px-3 py-2 text-sm font-semibold text-white">Yangi test</a>
    </div>
  </div>

  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50 text-slate-600">
        <tr>
          <th class="px-4 py-3 text-left">Test nomi</th>
          <th class="px-4 py-3 text-left">Fan</th>
          <th class="px-4 py-3 text-left">Davomiyligi</th>
          <th class="px-4 py-3 text-left">Turi/ID</th>
          <th class="px-4 py-3 text-left">Urinish/Savol</th>
          <th class="px-4 py-3 text-left">Sana</th>
          <th class="px-4 py-3 text-left">Amallar</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($tests as $test): ?>
        <tr class="border-t border-slate-100 hover:bg-cyan-50/30">
          <td class="px-4 py-3"><a class="test-title text-cyan-700 hover:underline" href="/test/teacher/tests/questions.php?test_id=<?= (int) ($test['id'] ?? 0) ?>"><?= h($test['title'] ?? '-') ?></a></td>
          <td class="px-4 py-3"><?= h($test['subject_name'] ?? '-') ?></td>
          <td class="px-4 py-3"><?= (int) ($test['duration_minutes'] ?? 0) ?> min</td>
          <td class="px-4 py-3"><?= ($test['visibility'] ?? 'open') === 'closed' ? 'Private #'.h($test['private_code'] ?? '-') : 'Public' ?></td>
          <td class="px-4 py-3"><?= (int)($test['attempts_limit'] ?? 1) ?> / <?= (int)($test['question_limit'] ?? 10) ?></td>
          <td class="px-4 py-3"><?= h($test['created_at'] ?? '-') ?></td>
          <td class="px-4 py-3">
            <div class="flex gap-2">
            <a href="/test/teacher/tests/questions.php?test_id=<?= (int) ($test['id'] ?? 0) ?>" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-emerald-200 text-emerald-700 hover:bg-emerald-50" title="Insert">+</a>
            <button type="button" class="editTest inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-300 text-slate-600 hover:bg-slate-50" data-id="<?= (int) ($test['id'] ?? 0) ?>" data-title="<?= h($test['title'] ?? '') ?>" data-subject-id="<?= (int) ($test['subject_id'] ?? 0) ?>" data-duration="<?= (int) ($test['duration_minutes'] ?? 60) ?>" data-attempts="<?= (int)($test['attempts_limit'] ?? 1) ?>" data-qlimit="<?= (int)($test['question_limit'] ?? 10) ?>" data-visibility="<?= h($test['visibility'] ?? 'open') ?>" title="Edit">✎</button>
            <button type="button" class="deleteTest inline-flex h-9 w-9 items-center justify-center rounded-xl border border-rose-200 text-rose-600 hover:bg-rose-50" data-id="<?= (int) ($test['id'] ?? 0) ?>" data-name="<?= h($test['title'] ?? '') ?>" title="O'chirish">
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

<div id="editTestModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 p-4">
  <div class="w-full max-w-xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl">
    <div class="mb-4 flex items-center justify-between">
      <h3 class="font-heading text-2xl font-semibold">Test tahrirlash</h3>
      <button type="button" data-close="editTestModal" class="text-slate-500">X</button>
    </div>
    <form method="POST" action="/test/teacher/update/test.php" class="space-y-4">
      <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
      <input type="hidden" name="id" id="edit_test_id">
      <div><label class="mb-1 block text-sm font-medium">Fan</label><select id="edit_test_subject_id" name="subject_id" class="w-full rounded-xl border border-slate-300 px-3 py-2.5" required><option value="" disabled>Tanlang</option><?php foreach ($subjects as $subject): ?><option value="<?= (int) $subject['id'] ?>"><?= h($subject['name']) ?></option><?php endforeach; ?></select></div>
      <div><label class="mb-1 block text-sm font-medium">Test nomi</label><input id="edit_test_title" name="title" class="w-full rounded-xl border border-slate-300 px-3 py-2.5" required></div>
      <div><label class="mb-1 block text-sm font-medium">Davomiyligi (daqiqa)</label><input id="edit_test_duration" type="number" min="1" name="duration_minutes" class="w-full rounded-xl border border-slate-300 px-3 py-2.5" required></div><div><label class="mb-1 block text-sm font-medium">Urinishlar soni</label><input id="edit_test_attempts" type="number" min="1" name="attempts_limit" class="w-full rounded-xl border border-slate-300 px-3 py-2.5" required></div><div><label class="mb-1 block text-sm font-medium">Savollar soni</label><input id="edit_test_qlimit" type="number" min="1" name="question_limit" class="w-full rounded-xl border border-slate-300 px-3 py-2.5" required></div><div><label class="mb-1 block text-sm font-medium">Test turi</label><select id="edit_test_visibility" name="visibility" class="w-full rounded-xl border border-slate-300 px-3 py-2.5"><option value="open">Public</option><option value="closed">Private</option></select></div>
      <button class="rounded-xl bg-green-700 px-5 py-2.5 font-semibold text-white">Saqlash</button>
    </form>
  </div>
</div>

<form id="deleteTestForm" method="POST" action="/test/teacher/delete/test.php" class="hidden">
  <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
  <input type="hidden" name="id" id="delete_test_id">
</form>

<script>
  window.MathJax = { tex: { inlineMath: [['\\(', '\\)']] } };
</script>
<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
<script>
  (function renderMathInTestTitles(){
    const decodeHtml = (html) => {
      const txt = document.createElement('textarea');
      txt.innerHTML = html;
      return txt.value;
    };
    document.querySelectorAll('.test-title').forEach((el) => {
      const decoded = decodeHtml(el.innerHTML);
      const rendered = decoded.replace(/\\\((.+?)\\\)/g, '<span class="inline-block">\\($1\\)</span>');
      el.innerHTML = rendered;
    });
  })();
  if (window.MathJax && window.MathJax.typesetPromise) {
    window.MathJax.typesetPromise(document.querySelectorAll('.test-title'));
  }

  const byId = (id) => document.getElementById(id);
  const open = (id) => { const el = byId(id); if (!el) return; el.classList.remove('hidden'); el.classList.add('flex'); };
  const close = (id) => { const el = byId(id); if (!el) return; el.classList.add('hidden'); el.classList.remove('flex'); };

  document.querySelectorAll('[data-close]').forEach((el) => {
    el.addEventListener('click', () => close(el.getAttribute('data-close')));
  });

  document.querySelectorAll('.editTest').forEach((btn) => {
    btn.addEventListener('click', () => {
      byId('edit_test_id').value = btn.dataset.id || '';
      byId('edit_test_title').value = btn.dataset.title || '';
      const subjectSelect = byId('edit_test_subject_id');
      subjectSelect.value = btn.dataset.subjectId || '';
      if (!subjectSelect.value && subjectSelect.options.length > 1) {
        subjectSelect.selectedIndex = 1;
      }
      byId('edit_test_duration').value = btn.dataset.duration || '60';
      byId('edit_test_attempts').value = btn.dataset.attempts || '1';
      byId('edit_test_qlimit').value = btn.dataset.qlimit || '10';
      byId('edit_test_visibility').value = btn.dataset.visibility || 'open';
      open('editTestModal');
    });
  });

  document.querySelectorAll('.deleteTest').forEach((btn) => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.id || '';
      const name = btn.dataset.name || 'test';
      Swal.fire({
        title: 'Tasdiqlang',
        text: `${name} testi o'chirilsinmi?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ha',
        cancelButtonText: "Yo'q",
        confirmButtonColor: '#6366f1',
        cancelButtonColor: '#6b7280',
        reverseButtons: true,
      }).then((result) => {
        if (result.isConfirmed) {
          byId('delete_test_id').value = id;
          byId('deleteTestForm').submit();
        }
      });
    });
  });
</script>

<?php elseif ($section === 'results'): ?>
<section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
  <div class="flex flex-wrap items-end justify-between gap-3 border-b border-slate-200 p-4">
    <div>
      <h2 class="font-heading text-lg font-semibold">Natijalar</h2>
      <p class="text-sm text-slate-500">O'zingiz yaratgan testlarni ishlagan o'quvchilar natijalari</p>
    </div>
    <form method="GET" action="/test/teacher/results.php" class="flex items-end gap-2">
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-600">Test bo'yicha filter</label>
        <select name="test_id" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
          <option value="0">Barcha testlar</option>
          <?php foreach ($teacherTestsAll as $t): ?>
            <option value="<?= (int) ($t['id'] ?? 0) ?>" <?= $resultTestFilter === (int) ($t['id'] ?? 0) ? 'selected' : '' ?>><?= h($t['title'] ?? '-') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="rounded-xl border border-slate-300 px-3 py-2 text-sm">Filter</button>
    </form>
  </div>

  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50 text-slate-600">
      <tr>
        <th class="px-4 py-3 text-left">O'quvchi</th>
        <th class="px-4 py-3 text-left">Test</th>
        <th class="px-4 py-3 text-left">Fan</th>
        <th class="px-4 py-3 text-left">To'g'ri</th>
        <th class="px-4 py-3 text-left">Xato</th>
        <th class="px-4 py-3 text-left">Kutilmoqda</th>
        <th class="px-4 py-3 text-left">Foiz</th>
        <th class="px-4 py-3 text-left">Holat</th>
        <th class="px-4 py-3 text-left">Sana</th>
        <th class="px-4 py-3 text-left">Amallar</th>
      </tr>
      </thead>
      <tbody>
      <?php foreach ($teacherAttempts as $a): ?>
        <tr class="border-t border-slate-100 hover:bg-cyan-50/30">
          <td class="px-4 py-3"><?= h($a['student_name'] ?? ('Student #' . (int) ($a['student_id'] ?? 0))) ?></td>
          <td class="px-4 py-3"><?= h($a['test_title'] ?? '-') ?></td>
          <td class="px-4 py-3"><?= h($a['subject_name'] ?? '-') ?></td>
          <td class="px-4 py-3"><?= (int) ($a['correct_count'] ?? 0) ?></td>
          <td class="px-4 py-3"><?= (int) ($a['wrong_count'] ?? 0) ?></td>
          <td class="px-4 py-3"><?= (int) ($a['pending_count'] ?? 0) ?></td>
          <td class="px-4 py-3"><?= (int) ($a['score_percent'] ?? 0) ?>%</td>
          <td class="px-4 py-3"><?= h($a['status'] ?? '-') ?></td>
          <td class="px-4 py-3"><?= h($a['submitted_at'] ?? $a['started_at'] ?? '-') ?></td>
          <td class="px-4 py-3">
            <a href="/test/teacher/attempt-view.php?attempt_id=<?= (int) ($a['id'] ?? 0) ?>" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-300 text-slate-600 hover:bg-slate-50" title="Ko'rish">👁</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (count($teacherAttempts) === 0): ?>
        <tr><td colspan="10" class="px-4 py-4 text-sm text-slate-600">Hozircha natijalar topilmadi.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php else: ?>
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm text-sm text-slate-600">Bu bo'lim keyingi bosqichda to'ldiriladi.</section>
<?php endif; ?>
<?php
$content = ob_get_clean();

render_dashboard_layout([
    'title' => 'SamDU Test Tizimi | Teacher Dashboard',
    'page_title' => 'Teacher Dashboard',
    'subtitle' => "O'zingiz yaratgan testlar va natijalar boshqaruvi",
    'role_name' => 'Teacher',
    'menu' => $menu,
    'active' => $section,
    'user' => $user,
    'content' => $content,
]);
