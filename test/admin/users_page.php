<?php
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../shared/dashboard_repository.php';
require_once __DIR__ . '/../layouts/role_dashboard.php';

require_auth(['admin']);
$user = auth_user();
$csrf = csrf_token();
$error = flash_get('error');
$success = flash_get('success');

$section = strtolower(trim((string) ($section ?? 'dashboard')));
$allowed = ['dashboard', 'teachers', 'students', 'tests', 'results', 'stats', 'settings'];
if (!in_array($section, $allowed, true)) {
    $section = 'dashboard';
}

$menu = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'href' => '/test/admin/dashboard.php'],
    ['key' => 'teachers', 'label' => "O'qituvchilar", 'icon' => 'teachers', 'href' => '/test/admin/teachers.php'],
    ['key' => 'students', 'label' => 'Talabalar', 'icon' => 'students', 'href' => '/test/admin/students.php'],
    ['key' => 'tests', 'label' => 'Testlar', 'icon' => 'tests', 'href' => '/test/admin/tests.php'],
    ['key' => 'results', 'label' => 'Natijalar', 'icon' => 'results', 'href' => '/test/admin/results.php'],
    ['key' => 'stats', 'label' => 'Statistika', 'icon' => 'stats', 'href' => '/test/admin/stats.php'],
    ['key' => 'settings', 'label' => 'Sozlamalar', 'icon' => 'settings', 'href' => '/test/admin/settings.php'],
];

$counts = get_system_counts();
$users = get_admin_users(50);
$adminTests = get_admin_tests(500);
$adminResultTestFilter = (int) ($_GET['test_id'] ?? 0);
$adminAttempts = get_admin_attempts(1000, $adminResultTestFilter);

$teacherRows = array_values(array_filter($users, static function ($r) {
    return (($r['role'] ?? '') === 'teacher');
}));
$studentRows = array_values(array_filter($users, static function ($r) {
    return (($r['role'] ?? '') === 'student');
}));

function badge_class($status)
{
    $s = strtolower((string) $status);
    if ($s === 'active') {
        return 'bg-emerald-100 text-emerald-700';
    }
    if ($s === 'blocked') {
        return 'bg-rose-100 text-rose-700';
    }
    return 'bg-amber-100 text-amber-700';
}

ob_start();
?>
<?php if ($error || $success): ?>
  <div class="rounded-xl px-4 py-3 text-sm font-semibold text-white <?= $error ? 'bg-rose-500' : 'bg-emerald-500' ?>"><?= h($error ?: $success) ?></div>
<?php endif; ?>

<?php if ($section === 'dashboard'): ?>
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
  <article class="rounded-2xl border border-indigo-100 bg-indigo-50 p-4 shadow-sm"><div class="mb-2 flex items-center justify-between"><p class="text-sm text-indigo-700">Foydalanuvchilar</p><span class="h-8 w-8 text-indigo-600"><?= icon_svg('students') ?></span></div><p class="text-2xl font-bold text-indigo-900"><?= (int) $counts['users'] ?></p></article>
  <article class="rounded-2xl border border-cyan-100 bg-cyan-50 p-4 shadow-sm"><div class="mb-2 flex items-center justify-between"><p class="text-sm text-cyan-700">O'qituvchilar</p><span class="h-8 w-8 text-cyan-600"><?= icon_svg('teachers') ?></span></div><p class="text-2xl font-bold text-cyan-900"><?= (int) $counts['teachers'] ?></p></article>
  <article class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4 shadow-sm"><div class="mb-2 flex items-center justify-between"><p class="text-sm text-emerald-700">Talabalar</p><span class="h-8 w-8 text-emerald-600"><?= icon_svg('students') ?></span></div><p class="text-2xl font-bold text-emerald-900"><?= (int) $counts['students'] ?></p></article>
  <article class="rounded-2xl border border-amber-100 bg-amber-50 p-4 shadow-sm"><div class="mb-2 flex items-center justify-between"><p class="text-sm text-amber-700">Testlar</p><span class="h-8 w-8 text-amber-600"><?= icon_svg('tests') ?></span></div><p class="text-2xl font-bold text-amber-900"><?= (int) $counts['tests'] ?></p></article>
</div>
<?php endif; ?>

<?php if ($section !== 'dashboard'): ?>
<section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
  <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-4">
    <h2 class="font-heading text-lg font-semibold">
      <?php if (in_array($section, ['teachers', 'students'], true)): ?>
        Foydalanuvchilar boshqaruvi
      <?php elseif ($section === 'tests'): ?>
        Testlar
      <?php else: ?>
        Natijalar
      <?php endif; ?>
    </h2>
    <?php if (in_array($section, ['teachers', 'students'], true)): ?>
      <div class="flex items-center gap-2">
        <input class="w-44 rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Qidirish">
        <button id="openInsertModal" class="rounded-xl bg-green-700 px-3 py-2 text-sm font-semibold text-white">+ Qo'shish</button>
      </div>
    <?php endif; ?>
  </div>

  <?php if (in_array($section, ['teachers', 'students', 'dashboard'], true)): ?>
  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="sticky top-0 bg-slate-50 text-slate-600">
        <tr>
          <th class="px-4 py-3 text-left">F.I.O</th>
          <th class="px-4 py-3 text-left">Login</th>
          <th class="px-4 py-3 text-left">Email</th>
          <th class="px-4 py-3 text-left">Rol</th>
          <th class="px-4 py-3 text-left">Status</th>
          <th class="px-4 py-3 text-left">Amallar</th>
        </tr>
      </thead>
      <tbody>
      <?php
        $rows = $users;
        if ($section === 'teachers') {
            $rows = $teacherRows;
        }
        if ($section === 'students') {
            $rows = $studentRows;
        }
      ?>
      <?php foreach ($rows as $row): ?>
        <tr class="border-t border-slate-100 hover:bg-cyan-50/30">
          <td class="px-4 py-3"><?= h($row['fullname'] ?? '-') ?></td>
          <td class="px-4 py-3"><?= h($row['username'] ?? '-') ?></td>
          <td class="px-4 py-3"><?= h($row['email'] ?? '-') ?></td>
          <td class="px-4 py-3 capitalize"><?= h($row['role'] ?? '-') ?></td>
          <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-semibold <?= badge_class($row['status'] ?? 'pending') ?>"><?= h($row['status'] ?? 'pending') ?></span></td>
          <td class="px-4 py-3">
            <div class="flex gap-2">
              <button
                type="button"
                class="editBtn inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-300 text-slate-600 hover:bg-slate-50"
                data-id="<?= (int) ($row['id'] ?? 0) ?>"
                data-fullname="<?= h($row['fullname'] ?? '') ?>"
                data-phone="<?= h($row['phone'] ?? '') ?>"
                data-email="<?= h($row['email'] ?? '') ?>"
                data-username="<?= h($row['username'] ?? '') ?>"
                data-role="<?= h($row['role'] ?? 'student') ?>"
                data-status="<?= h($row['status'] ?? 'active') ?>"
                title="Tahrirlash"
              >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg>
              </button>

              <button
                type="button"
                class="deleteBtn inline-flex h-9 w-9 items-center justify-center rounded-xl border border-rose-200 text-rose-600 hover:bg-rose-50"
                data-id="<?= (int) ($row['id'] ?? 0) ?>"
                data-name="<?= h($row['fullname'] ?? '') ?>"
                title="O'chirish"
              >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg>
              </button>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php elseif ($section === 'tests'): ?>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="sticky top-0 bg-slate-50 text-slate-600">
          <tr>
            <th class="px-4 py-3 text-left">Test</th>
            <th class="px-4 py-3 text-left">Fan</th>
            <th class="px-4 py-3 text-left">O'qituvchi</th>
            <th class="px-4 py-3 text-left">Davomiyligi</th>
            <th class="px-4 py-3 text-left">Sana</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($adminTests as $t): ?>
          <tr class="border-t border-slate-100 hover:bg-cyan-50/30">
            <td class="px-4 py-3"><?= h($t['title'] ?? '-') ?></td>
            <td class="px-4 py-3"><?= h($t['subject_name'] ?? '-') ?></td>
            <td class="px-4 py-3"><?= h($t['teacher_name'] ?? '-') ?></td>
            <td class="px-4 py-3"><?= (int) ($t['duration_minutes'] ?? 0) ?> min</td>
            <td class="px-4 py-3"><?= h($t['created_at'] ?? '-') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (count($adminTests) === 0): ?>
          <tr><td colspan="5" class="px-4 py-4 text-sm text-slate-600">Testlar topilmadi.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  <?php elseif ($section === 'results'): ?>
    <div class="border-b border-slate-200 p-4">
      <form method="GET" action="/test/admin/results.php" class="flex items-end gap-2">
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Test bo'yicha filter</label>
          <select name="test_id" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
            <option value="0">Barcha testlar</option>
            <?php foreach ($adminTests as $t): ?>
              <option value="<?= (int) ($t['id'] ?? 0) ?>" <?= $adminResultTestFilter === (int) ($t['id'] ?? 0) ? 'selected' : '' ?>><?= h($t['title'] ?? '-') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="rounded-xl border border-slate-300 px-3 py-2 text-sm">Filter</button>
      </form>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="sticky top-0 bg-slate-50 text-slate-600">
          <tr>
            <th class="px-4 py-3 text-left">O'quvchi</th>
            <th class="px-4 py-3 text-left">Test</th>
            <th class="px-4 py-3 text-left">O'qituvchi</th>
            <th class="px-4 py-3 text-left">To'g'ri</th>
            <th class="px-4 py-3 text-left">Xato</th>
            <th class="px-4 py-3 text-left">Kutilmoqda</th>
            <th class="px-4 py-3 text-left">Foiz</th>
            <th class="px-4 py-3 text-left">Sana</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($adminAttempts as $a): ?>
          <tr class="border-t border-slate-100 hover:bg-cyan-50/30">
            <td class="px-4 py-3"><?= h($a['student_name'] ?? '-') ?></td>
            <td class="px-4 py-3"><?= h($a['test_title'] ?? '-') ?></td>
            <td class="px-4 py-3"><?= h($a['teacher_name'] ?? '-') ?></td>
            <td class="px-4 py-3"><?= (int) ($a['correct_count'] ?? 0) ?></td>
            <td class="px-4 py-3"><?= (int) ($a['wrong_count'] ?? 0) ?></td>
            <td class="px-4 py-3"><?= (int) ($a['pending_count'] ?? 0) ?></td>
            <td class="px-4 py-3"><?= (int) ($a['score_percent'] ?? 0) ?>%</td>
            <td class="px-4 py-3"><?= h($a['submitted_at'] ?? '-') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (count($adminAttempts) === 0): ?>
          <tr><td colspan="8" class="px-4 py-4 text-sm text-slate-600">Natijalar topilmadi.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="p-6 text-sm text-slate-600">Bu bo'lim uchun modul tayyor.</div>
  <?php endif; ?>
</section>
<?php endif; ?>

<div id="insertModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 p-4">
  <div class="w-full max-w-xl rounded-2xl bg-white p-5 shadow-2xl">
    <div class="mb-4 flex items-center justify-between"><h3 class="font-heading text-lg font-semibold">Yangi foydalanuvchi</h3><button type="button" data-close="insertModal">✕</button></div>
    <form method="POST" action="/test/admin/insert/user.php" class="grid gap-3 md:grid-cols-2">
      <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
      <input class="rounded-xl border border-slate-200 px-3 py-2.5" name="fullname" placeholder="F.I.O" required>
      <input class="rounded-xl border border-slate-200 px-3 py-2.5" name="phone" placeholder="Telefon" required>
      <input class="rounded-xl border border-slate-200 px-3 py-2.5" type="email" name="email" placeholder="Email" required>
      <input class="rounded-xl border border-slate-200 px-3 py-2.5" name="username" placeholder="Login" required>
      <input class="rounded-xl border border-slate-200 px-3 py-2.5" type="password" name="password" placeholder="Parol" required>
      <select class="rounded-xl border border-slate-200 px-3 py-2.5" name="role"><option value="teacher">Teacher</option><option value="student">Student</option><option value="admin">Admin</option></select>
      <select class="rounded-xl border border-slate-200 px-3 py-2.5" name="status"><option value="active">active</option><option value="pending">pending</option><option value="blocked">blocked</option></select>
      <div class="md:col-span-2 flex justify-end gap-2"><button type="button" data-close="insertModal" class="rounded-xl border border-slate-200 px-4 py-2">Bekor qilish</button><button class="rounded-xl bg-green-700 px-4 py-2 font-semibold text-white">Saqlash</button></div>
    </form>
  </div>
</div>

<div id="editModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 p-4">
  <div class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl">
    <div class="mb-4 flex items-center justify-between"><h3 class="font-heading text-lg font-semibold">Foydalanuvchini tahrirlash</h3><button type="button" data-close="editModal">✕</button></div>
    <form method="POST" action="/test/admin/update/user.php" class="grid gap-3 md:grid-cols-2">
      <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
      <input type="hidden" name="id" id="edit_id">
      <input class="rounded-xl border border-slate-200 px-3 py-2.5" name="fullname" id="edit_fullname" placeholder="F.I.O" required>
      <input class="rounded-xl border border-slate-200 px-3 py-2.5" name="phone" id="edit_phone" placeholder="Telefon" required>
      <input class="rounded-xl border border-slate-200 px-3 py-2.5" type="email" name="email" id="edit_email" placeholder="Email" required>
      <input class="rounded-xl border border-slate-200 px-3 py-2.5" name="username" id="edit_username" placeholder="Login" required>
      <select class="rounded-xl border border-slate-200 px-3 py-2.5" name="role" id="edit_role"><option value="teacher">Teacher</option><option value="student">Student</option><option value="admin">Admin</option></select>
      <select class="rounded-xl border border-slate-200 px-3 py-2.5" name="status" id="edit_status"><option value="active">active</option><option value="pending">pending</option><option value="blocked">blocked</option></select>
      <div class="md:col-span-2 flex justify-end gap-2"><button type="button" data-close="editModal" class="rounded-xl border border-slate-200 px-4 py-2">Bekor qilish</button><button class="rounded-xl bg-green-700 px-4 py-2 font-semibold text-white">Yangilash</button></div>
    </form>
  </div>
</div>

<form id="deleteForm" method="POST" action="/test/admin/delete/user.php" class="hidden">
  <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
  <input type="hidden" name="id" id="delete_id">
</form>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  const byId = (id) => document.getElementById(id);
  const open = (id) => {
    const el = byId(id);
    if (!el) return;
    el.classList.remove('hidden');
    el.classList.add('flex');
  };
  const close = (id) => {
    const el = byId(id);
    if (!el) return;
    el.classList.add('hidden');
    el.classList.remove('flex');
  };

  document.getElementById('openInsertModal')?.addEventListener('click', () => open('insertModal'));

  document.querySelectorAll('[data-close]').forEach((el) => {
    el.addEventListener('click', () => close(el.getAttribute('data-close')));
  });

  document.querySelectorAll('.editBtn').forEach((btn) => {
    btn.addEventListener('click', () => {
      byId('edit_id').value = btn.dataset.id || '';
      byId('edit_fullname').value = btn.dataset.fullname || '';
      byId('edit_phone').value = btn.dataset.phone || '';
      byId('edit_email').value = btn.dataset.email || '';
      byId('edit_username').value = btn.dataset.username || '';
      byId('edit_role').value = btn.dataset.role || 'student';
      byId('edit_status').value = btn.dataset.status || 'active';
      open('editModal');
    });
  });

  document.querySelectorAll('.deleteBtn').forEach((btn) => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.id || '';
      const name = btn.dataset.name || 'Tanlangan foydalanuvchi';

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
          byId('delete_id').value = id;
          byId('deleteForm').submit();
        }
      });
    });
  });
</script>
<?php
$content = ob_get_clean();

render_dashboard_layout([
    'title' => 'SamDU Test Tizimi | Admin Dashboard',
    'page_title' => 'Admin Dashboard',
    'subtitle' => 'Platforma boshqaruvi, foydalanuvchilar va monitoring',
    'role_name' => 'Admin',
    'menu' => $menu,
    'active' => $section,
    'user' => $user,
    'content' => $content,
]);
