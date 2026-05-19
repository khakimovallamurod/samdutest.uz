<?php
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../shared/dashboard_repository.php';
require_once __DIR__ . '/../layouts/role_dashboard.php';
require_once __DIR__ . '/../database/Database.php';

require_auth(['student']);
$user = auth_user();
$studentId = (int) ($user['id'] ?? 0);
$error = flash_get('error');
$success = flash_get('success');

$section = strtolower(trim((string) ($_GET['section'] ?? 'dashboard')));
$allowed = ['dashboard', 'tests', 'my-results', 'profile', 'settings'];
if (!in_array($section, $allowed, true)) {
    $section = 'dashboard';
}

$menu = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'href' => '/test/student/dashboard.php?section=dashboard'],
    ['key' => 'tests', 'label' => 'Testlar', 'icon' => 'tests', 'href' => '/test/student/dashboard.php?section=tests'],
    ['key' => 'my-results', 'label' => 'Mening natijalarim', 'icon' => 'results', 'href' => '/test/student/dashboard.php?section=my-results'],
    ['key' => 'profile', 'label' => 'Profil', 'icon' => 'profile', 'href' => '/test/student/dashboard.php?section=profile'],
    ['key' => 'settings', 'label' => 'Sozlamalar', 'icon' => 'settings', 'href' => '/test/student/dashboard.php?section=settings'],
];

$metrics = get_student_metrics($studentId);
$results = get_student_attempts($studentId, 50);
$subjectFilter = (int) ($_GET['subject_id'] ?? 0);
$subjects = get_student_test_subject_filters();
$tests = get_available_tests_for_student($section === 'tests' ? $subjectFilter : 0, 300);
$privateCode = trim((string)($_GET['private_code'] ?? ''));
$privateTest = null;
if ($section === 'tests' && $privateCode !== '') {
    $privateTest = get_private_test_for_student_by_code($privateCode);
}
$db = Database::connection();
$attemptedTestIds = get_student_attempted_test_ids($studentId);
$attemptedMap = [];
foreach ($attemptedTestIds as $tid) {
    $attemptedMap[(int) $tid] = true;
}
$activeTests = [];
$disabledTests = [];
foreach ($tests as $t) {
    $id = (int) ($t['id'] ?? 0);
    if (isset($attemptedMap[$id])) {
        $disabledTests[] = $t;
    } else {
        $activeTests[] = $t;
    }
}
$tests = array_merge($activeTests, $disabledTests);

ob_start();
?>
<?php if ($error || $success): ?>
  <div class="rounded-xl px-4 py-3 text-sm font-semibold text-white <?= $error ? 'bg-rose-500' : 'bg-emerald-500' ?>"><?= h($error ?: $success) ?></div>
<?php endif; ?>

<?php if ($section === 'dashboard'): ?>
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
  <article class="rounded-2xl border border-cyan-100 bg-cyan-50 p-4 shadow-sm"><div class="mb-2 flex items-center justify-between"><p class="text-sm text-cyan-700">Mavjud testlar</p><span class="h-8 w-8 text-cyan-600"><?= icon_svg('tests') ?></span></div><p class="text-2xl font-bold text-cyan-900"><?= (int) $metrics['available_tests'] ?></p></article>
  <article class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4 shadow-sm"><div class="mb-2 flex items-center justify-between"><p class="text-sm text-emerald-700">Mening natijalarim</p><span class="h-8 w-8 text-emerald-600"><?= icon_svg('results') ?></span></div><p class="text-2xl font-bold text-emerald-900"><?= (int) $metrics['my_results'] ?></p></article>
  <article class="rounded-2xl border border-violet-100 bg-violet-50 p-4 shadow-sm"><div class="mb-2 flex items-center justify-between"><p class="text-sm text-violet-700">Kutilayotgan tekshiruv</p><span class="h-8 w-8 text-violet-600"><?= icon_svg('stats') ?></span></div><p class="text-2xl font-bold text-violet-900"><?= count(array_filter($results, static fn($r) => (($r['status'] ?? '') === 'pending_review'))) ?></p></article>
</div>
<?php endif; ?>

<?php if ($section === 'tests'): ?>
<section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
  <div class="flex flex-wrap items-end justify-between gap-3 border-b border-slate-200 p-4">
    <div>
      <h2 class="font-heading text-lg font-semibold">Testlar</h2>
      <p class="text-sm text-slate-500">Fan bo'yicha filterlab test boshlang</p>
    </div>
    <form method="GET" class="flex items-end gap-2">
      <input type="hidden" name="section" value="tests">
      <select name="subject_id" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
        <option value="0">Barcha fanlar</option>
        <?php foreach ($subjects as $s): ?>
          <option value="<?= (int) $s['id'] ?>" <?= $subjectFilter === (int) $s['id'] ? 'selected' : '' ?>><?= h($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="rounded-xl border border-slate-300 px-3 py-2 text-sm">Filter</button>
    </form>
  </div>
  <div class="border-b border-slate-200 p-4">
    <form method="GET" class="flex flex-wrap items-end gap-2">
      <input type="hidden" name="section" value="tests">
      <input type="hidden" name="subject_id" value="<?= (int)$subjectFilter ?>">
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-600">Private test ID</label>
        <input name="private_code" value="<?= h($privateCode) ?>" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Masalan: 123456">
      </div>
      <button class="rounded-xl bg-slate-800 px-3 py-2 text-sm font-semibold text-white">Qidirish</button>
    </form>
    <?php if ($privateCode !== '' && !$privateTest): ?>
      <p class="mt-2 text-sm text-rose-600">Private test topilmadi.</p>
    <?php endif; ?>
  </div>
  <?php if ($privateTest): ?>
    <?php $ptid=(int)($privateTest['id'] ?? 0); $pMaxAttempts=(int)($privateTest['attempts_limit'] ?? 1); $pUsed=0; $pStA=$db->prepare("SELECT COUNT(*) c FROM test_attempts WHERE test_id=? AND student_id=? AND status<>'in_progress'"); if($pStA){$pStA->bind_param('ii',$ptid,$studentId);$pStA->execute();$pRr=$pStA->get_result()->fetch_assoc();$pUsed=(int)($pRr['c']??0);$pStA->close();} $pLeft=max(0,$pMaxAttempts-$pUsed); $pTaken=$pLeft<=0; ?>
    <div class="p-4">
      <article class="rounded-2xl border border-amber-300 bg-amber-50 p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Private Test</p>
        <h3 class="mt-1 text-base font-semibold text-slate-900"><?= h($privateTest['title'] ?? '-') ?></h3>
        <p class="mt-2 text-sm text-slate-600">Fan: <strong><?= h($privateTest['subject_name'] ?? '-') ?></strong></p>
        <p class="text-sm text-slate-600">Savollar: <strong><?= (int)($privateTest['question_count'] ?? 0) ?></strong></p>
        <p class="text-sm text-slate-600">Vaqt: <strong><?= (int)($privateTest['duration_minutes'] ?? 0) ?> min</strong></p>
        <p class="text-sm text-slate-600">Qolgan urinishlar: <strong><?= $pLeft ?></strong></p>
        <?php if ($pTaken): ?>
          <button type="button" disabled class="mt-3 inline-flex cursor-not-allowed rounded-xl bg-slate-400 px-4 py-2 text-sm font-semibold text-white">Urinishlar tugagan</button>
        <?php else: ?>
          <a href="/test/student/test-start.php?test_id=<?= $ptid ?>&private_code=<?= urlencode((string)($privateTest['private_code'] ?? '')) ?>" class="mt-3 inline-flex rounded-xl bg-amber-600 px-4 py-2 text-sm font-semibold text-white">Private testni boshlash</a>
        <?php endif; ?>
      </article>
    </div>
  <?php endif; ?>
  <div class="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-3">
    <?php foreach ($tests as $t): ?>
      <?php $tid=(int)($t['id'] ?? 0); $maxAttempts=(int)($t['attempts_limit'] ?? 1); $used=0; $stA=$db->prepare("SELECT COUNT(*) c FROM test_attempts WHERE test_id=? AND student_id=? AND status<>'in_progress'"); if($stA){$stA->bind_param('ii',$tid,$studentId);$stA->execute();$rr=$stA->get_result()->fetch_assoc();$used=(int)($rr['c']??0);$stA->close();} $left=max(0,$maxAttempts-$used); $isTaken=$left<=0; ?>
      <article class="rounded-2xl border p-4 <?= $isTaken ? 'border-slate-200 bg-slate-100 opacity-70' : 'border-slate-200 bg-slate-50' ?>">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500"><?= h($t['subject_name'] ?? '-') ?></p>
        <h3 class="mt-1 text-base font-semibold text-slate-900"><?= h($t['title'] ?? '-') ?></h3>
        <p class="mt-2 text-sm text-slate-600">Savollar: <strong><?= (int) ($t['question_count'] ?? 0) ?></strong></p>
        <p class="text-sm text-slate-600">Vaqt: <strong><?= (int) ($t['duration_minutes'] ?? 0) ?> min</strong></p>
        <p class="text-sm text-slate-600">Qolgan urinishlar: <strong><?= $left ?></strong></p>
        <?php if ($isTaken): ?>
          <button type="button" disabled class="mt-3 inline-flex cursor-not-allowed rounded-xl bg-slate-400 px-4 py-2 text-sm font-semibold text-white">Urinishlar tugagan</button>
        <?php else: ?>
          <a href="/test/student/test-start.php?test_id=<?= (int) ($t['id'] ?? 0) ?>" class="mt-3 inline-flex rounded-xl bg-cyan-600 px-4 py-2 text-sm font-semibold text-white">Boshlash</a>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
    <?php if (count($tests) === 0): ?>
      <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">Hozircha mavjud test topilmadi.</div>
    <?php endif; ?>
  </div>
</section>

<?php elseif ($section === 'my-results'): ?>
<section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
  <div class="border-b border-slate-200 p-4"><h2 class="font-heading text-lg font-semibold">Mening natijalarim</h2></div>
  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50 text-slate-600">
      <tr>
        <th class="px-4 py-3 text-left">Test</th>
        <th class="px-4 py-3 text-left">To'g'ri</th>
        <th class="px-4 py-3 text-left">Xato</th>
        <th class="px-4 py-3 text-left">Kutilmoqda</th>
        <th class="px-4 py-3 text-left">Holat</th>
        <th class="px-4 py-3 text-left">Sana</th>
        <th class="px-4 py-3 text-left">Amallar</th>
      </tr>
      </thead>
      <tbody>
      <?php foreach ($results as $row): ?>
      <tr class="border-t border-slate-100 hover:bg-cyan-50/30">
        <td class="px-4 py-3"><?= h($row['test_title'] ?? '-') ?></td>
        <td class="px-4 py-3"><?= (int) ($row['correct_count'] ?? 0) ?></td>
        <td class="px-4 py-3"><?= (int) ($row['wrong_count'] ?? 0) ?></td>
        <td class="px-4 py-3"><?= (int) ($row['pending_count'] ?? 0) ?></td>
        <td class="px-4 py-3"><?= h($row['status'] ?? '-') ?></td>
        <td class="px-4 py-3"><?= h($row['taken_at'] ?? '-') ?></td>
        <td class="px-4 py-3"><a class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-300 text-slate-600 hover:bg-slate-50" href="/test/student/attempt-view.php?attempt_id=<?= (int) ($row['id'] ?? 0) ?>">👁</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (count($results) === 0): ?>
      <tr><td colspan="7" class="px-4 py-4 text-sm text-slate-600">Hozircha ishlangan test yo'q.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php else: ?>
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm text-sm text-slate-600">Dashboardda umumiy ko'rsatkichlar chiqadi. Test ishlash uchun `Testlar` bo'limiga o'ting.</section>
<?php endif; ?>
<?php
$content = ob_get_clean();

render_dashboard_layout([
    'title' => 'SamDU Test Tizimi | Student Dashboard',
    'page_title' => 'Student Dashboard',
    'subtitle' => 'Testlar, natijalar va profilingizni boshqarish',
    'role_name' => 'Student',
    'menu' => $menu,
    'active' => $section,
    'user' => $user,
    'content' => $content,
]);
