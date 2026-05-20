<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../shared/dashboard_repository.php';
require_once __DIR__ . '/../../layouts/role_dashboard.php';

require_auth(['teacher']);
$user = auth_user();
$teacherId = (int) ($user['id'] ?? 0);
$csrf = csrf_token();
$error = flash_get('error');
$success = flash_get('success');

$subjects = get_teacher_subjects($teacherId);
$subjectFilter = (int) ($_GET['subject_id'] ?? 0);
$tests = get_teacher_tests_filtered($teacherId, $subjectFilter, 300);

ob_start();
?>
<?php if ($error || $success): ?>
  <div class="rounded-xl px-4 py-3 text-sm font-semibold text-white <?= $error ? 'bg-rose-500' : 'bg-emerald-500' ?>"><?= h($error ?: $success) ?></div>
<?php endif; ?>

<section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
  <div class="flex flex-wrap items-end justify-between gap-3 border-b border-slate-200 p-4">
    <div>
      <h2 class="font-heading text-lg font-semibold">Testlar ro'yxati</h2>
      <p class="text-sm text-slate-500">Barcha testlar: view questions, edit, delete</p>
    </div>
    <div class="flex flex-wrap items-end gap-2">
      <form method="GET" action="/test/teacher/tests/index.php" id="subjectFilterForm" class="flex items-end gap-2">
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Fan bo'yicha filter</label>
          <select name="subject_id" id="subjectFilterSelect" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
            <option value="0">Barcha fanlar</option>
            <?php foreach ($subjects as $subject): ?>
              <option value="<?= (int) $subject['id'] ?>" <?= $subjectFilter === (int) $subject['id'] ? 'selected' : '' ?>><?= h($subject['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </form>
      <a href="/test/teacher/tests/create.php" class="rounded-xl bg-green-700 px-3 py-2 text-sm font-semibold text-white">+ Test qo'shish</a>
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
        <tr class="border-t border-slate-100 transition hover:bg-cyan-50/40">
          <td class="px-4 py-3"><a class="test-title text-cyan-700 hover:underline" href="/test/teacher/tests/questions.php?test_id=<?= (int) ($test['id'] ?? 0) ?>"><?= h($test['title'] ?? '-') ?></a></td>
          <td class="px-4 py-3"><?= h($test['subject_name'] ?? '-') ?></td>
          <td class="px-4 py-3"><?= (int) ($test['duration_minutes'] ?? 0) ?> min</td>
          <td class="px-4 py-3"><?= ($test['visibility'] ?? 'open') === 'closed' ? 'Private #'.h($test['private_code'] ?? '-') : 'Public' ?></td>
          <td class="px-4 py-3"><?= (int)($test['attempts_limit'] ?? 1) ?> / <?= (int)($test['question_limit'] ?? 10) ?></td>
          <td class="px-4 py-3"><?= h($test['created_at'] ?? '-') ?></td>
          <td class="px-4 py-3">
            <div class="flex gap-2">
              <a href="/test/teacher/tests/questions.php?test_id=<?= (int) ($test['id'] ?? 0) ?>" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-emerald-200 text-emerald-700 transition hover:-translate-y-0.5 hover:bg-emerald-50" title="View questions">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12Z"/><circle cx="12" cy="12" r="3"/></svg>
              </a>
              <button type="button" class="editTest inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-300 text-slate-600 transition hover:-translate-y-0.5 hover:bg-slate-50" data-id="<?= (int) ($test['id'] ?? 0) ?>" data-title="<?= h($test['title'] ?? '') ?>" data-subject-id="<?= (int) ($test['subject_id'] ?? 0) ?>" data-duration="<?= (int) ($test['duration_minutes'] ?? 60) ?>" data-attempts="<?= (int)($test['attempts_limit'] ?? 1) ?>" data-qlimit="<?= (int)($test['question_limit'] ?? 10) ?>" data-visibility="<?= h($test['visibility'] ?? 'open') ?>" title="Edit">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg>
              </button>
              <button type="button" class="deleteTest inline-flex h-9 w-9 items-center justify-center rounded-xl border border-rose-200 text-rose-600 transition hover:-translate-y-0.5 hover:bg-rose-50" data-id="<?= (int) ($test['id'] ?? 0) ?>" data-name="<?= h($test['title'] ?? '') ?>" title="Delete">
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
      <div><label class="mb-1 block text-sm font-medium">Davomiyligi (daqiqa)</label><input id="edit_test_duration" type="number" min="1" name="duration_minutes" class="w-full rounded-xl border border-slate-300 px-3 py-2.5" required></div>
      <div><label class="mb-1 block text-sm font-medium">Urinishlar soni</label><input id="edit_test_attempts" type="number" min="1" name="attempts_limit" class="w-full rounded-xl border border-slate-300 px-3 py-2.5" required></div>
      <div><label class="mb-1 block text-sm font-medium">Savollar soni</label><input id="edit_test_qlimit" type="number" min="1" name="question_limit" class="w-full rounded-xl border border-slate-300 px-3 py-2.5" required></div>
      <div><label class="mb-1 block text-sm font-medium">Test turi</label><select id="edit_test_visibility" name="visibility" class="w-full rounded-xl border border-slate-300 px-3 py-2.5"><option value="open">Public</option><option value="closed">Private</option></select></div>
      <button class="rounded-xl bg-green-700 px-5 py-2.5 font-semibold text-white">Saqlash</button>
    </form>
  </div>
</div>

<form id="deleteTestForm" method="POST" action="/test/teacher/delete/test.php" class="hidden">
  <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
  <input type="hidden" name="id" id="delete_test_id">
</form>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        text: `${name} testi o\'chirilsinmi?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ha',
        cancelButtonText: "Yo'q",
        confirmButtonColor: '#ef4444',
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

  document.getElementById('subjectFilterSelect')?.addEventListener('change', () => {
    document.getElementById('subjectFilterForm')?.submit();
  });
</script>

<?php
$content = ob_get_clean();
render_dashboard_layout([
    'title' => 'Testlar ro\'yxati',
    'page_title' => 'Testlar',
    'subtitle' => 'Teacher test list',
    'role_name' => 'Teacher',
    'menu' => [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'href' => '/test/teacher/dashboard.php'],
        ['key' => 'subjects', 'label' => 'Fanlar', 'icon' => 'teachers', 'href' => '/test/teacher/subjects.php'],
        ['key' => 'tests', 'label' => 'Testlar', 'icon' => 'tests', 'href' => '/test/teacher/tests/index.php'],
        ['key' => 'results', 'label' => 'Natijalar', 'icon' => 'results', 'href' => '/test/teacher/results.php'],
        ['key' => 'profile', 'label' => 'Profil', 'icon' => 'profile', 'href' => '/test/teacher/profile.php'],
        ['key' => 'settings', 'label' => 'Sozlamalar', 'icon' => 'settings', 'href' => '/test/teacher/settings.php'],
    ],
    'active' => 'tests',
    'user' => $user,
    'content' => $content,
]);
