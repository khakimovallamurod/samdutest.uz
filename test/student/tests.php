<?php
require_once __DIR__ . '/_shared.php';

$user = auth_user();
$studentId = (int) ($user['id'] ?? 0);
$subjectFilter = (int) ($_GET['subject_id'] ?? 0);
$subjects = get_student_test_subject_filters();
$tests = get_available_tests_for_student($subjectFilter, 300);
$privateCode = trim((string)($_GET['private_code'] ?? ''));
$privateTest = null;
if ($privateCode !== '') {
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
<section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
  <div class="flex flex-wrap items-end justify-between gap-3 border-b border-slate-200 p-4">
    <div>
      <h2 class="font-heading text-lg font-semibold">Testlar</h2>
      <p class="text-sm text-slate-500">Fan bo'yicha filterlab test boshlang</p>
    </div>
    <form method="GET" class="flex items-end gap-2">
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
    <div class="p-4"><article class="rounded-2xl border border-amber-300 bg-amber-50 p-4"><p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Private Test</p><h3 class="mt-1 text-base font-semibold text-slate-900"><?= h($privateTest['title'] ?? '-') ?></h3><p class="mt-2 text-sm text-slate-600">Fan: <strong><?= h($privateTest['subject_name'] ?? '-') ?></strong></p><p class="text-sm text-slate-600">Savollar: <strong><?= (int)($privateTest['question_count'] ?? 0) ?></strong></p><p class="text-sm text-slate-600">Vaqt: <strong><?= (int)($privateTest['duration_minutes'] ?? 0) ?> min</strong></p><p class="text-sm text-slate-600">Qolgan urinishlar: <strong><?= $pLeft ?></strong></p><?php if ($pTaken): ?><button type="button" disabled class="mt-3 inline-flex cursor-not-allowed rounded-xl bg-slate-400 px-4 py-2 text-sm font-semibold text-white">Urinishlar tugagan</button><?php else: ?><a href="/test/student/test-start.php?test_id=<?= $ptid ?>&private_code=<?= urlencode((string)($privateTest['private_code'] ?? '')) ?>" class="mt-3 inline-flex rounded-xl bg-amber-600 px-4 py-2 text-sm font-semibold text-white">Private testni boshlash</a><?php endif; ?></article></div>
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
<?php
$content = ob_get_clean();

render_dashboard_layout([
    'title' => 'SamDU Test Tizimi | Student Tests',
    'page_title' => 'Testlar',
    'subtitle' => 'Mavjud testlar ro‘yxati',
    'role_name' => 'Student',
    'menu' => student_menu(),
    'active' => 'tests',
    'user' => $user,
    'content' => $content,
]);
