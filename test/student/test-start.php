<?php
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../shared/dashboard_repository.php';
require_once __DIR__ . '/../database/Database.php';

require_auth(['student']);
$user = auth_user();
$studentId = (int) ($user['id'] ?? 0);
$testId = (int) ($_GET['test_id'] ?? 0);
$privateCode = trim((string)($_GET['private_code'] ?? ''));
$csrf = csrf_token();

try {
    $test = get_student_test_details_with_private_code($testId, $privateCode);
    $questions = get_student_test_questions($testId);
    if (!$test || count($questions) === 0) {
        flash_set('error', 'Test topilmadi yoki savollar mavjud emas.');
        redirect('/test/student/dashboard.php?section=tests');
    }

    $db = Database::connection();
    ensure_test_runtime_tables($db);

    $guard = $db->prepare("SELECT id, status FROM test_attempts WHERE test_id=? AND student_id=? ORDER BY id DESC LIMIT 1");
    if ($guard) {
        $guard->bind_param('ii', $testId, $studentId);
        $guard->execute();
        $latest = $guard->get_result()->fetch_assoc();
        $guard->close();
        $maxAttempts = max(1, (int)($test['attempts_limit'] ?? 1));
        $cnt = 0; $cst = $db->prepare("SELECT COUNT(*) c FROM test_attempts WHERE test_id=? AND student_id=? AND status<>'in_progress'"); if($cst){$cst->bind_param('ii',$testId,$studentId);$cst->execute();$row=$cst->get_result()->fetch_assoc();$cnt=(int)($row['c']??0);$cst->close();}
        if ($cnt >= $maxAttempts) { flash_set('error', 'Urinishlar tugagan'); redirect('/test/student/dashboard.php?section=tests'); }
    }

    $duration = max(1, (int) ($test['duration_minutes'] ?? 60));
    $totalQuestions = count($questions);
    $teacherId = (int) ($test['teacher_id'] ?? 0);
    $subjectId = (int) ($test['subject_id'] ?? 0);

    $stmt = $db->prepare("INSERT INTO test_attempts (test_id, student_id, teacher_id, subject_id, started_at, duration_minutes, total_questions, status) VALUES (?, ?, ?, ?, NOW(), ?, ?, 'in_progress')");
    if (!$stmt) {
        throw new RuntimeException('Attempt create failed: ' . $db->error);
    }
    $stmt->bind_param('iiiiii', $testId, $studentId, $teacherId, $subjectId, $duration, $totalQuestions);
    if (!$stmt->execute()) {
        throw new RuntimeException('Attempt execute failed: ' . $stmt->error);
    }
    $attemptId = (int) $db->insert_id;
    $stmt->close();

    $endTs = time() + ($duration * 60);
} catch (Throwable $e) {
    error_log('Student test-start error: ' . $e->getMessage());
    flash_set('error', 'Testni boshlashda xatolik yuz berdi.');
    redirect('/test/student/dashboard.php?section=tests');
}
?>
<!doctype html>
<html lang="uz">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($test['title'], ENT_QUOTES, 'UTF-8') ?> | Test</title>
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
</head>
<body class="bg-slate-50 text-slate-800">
  <main class="mx-auto max-w-4xl p-4 sm:p-6">
    <header class="mb-4 rounded-2xl border border-slate-200 bg-white p-4">
      <h1 class="text-xl font-bold"><?= htmlspecialchars($test['title'], ENT_QUOTES, 'UTF-8') ?></h1>
      <p class="mt-1 text-sm text-slate-500">Fan: <?= htmlspecialchars((string) ($test['subject_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></p>
      <p class="mt-1 text-sm font-semibold text-rose-600">Qolgan vaqt: <span id="timer">--:--</span></p>
    </header>

    <form id="testForm" method="POST" action="/test/student/test-submit.php" class="space-y-4">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="attempt_id" value="<?= $attemptId ?>">
      <input type="hidden" name="test_id" value="<?= $testId ?>">

      <?php foreach ($questions as $i => $q): ?>
        <section class="rounded-2xl border border-slate-200 bg-white p-4">
          <p class="mb-2 text-sm text-slate-500"><?= ($i + 1) ?>-savol | <?= htmlspecialchars((string) ($q['question_type'] ?? 'closed'), ENT_QUOTES, 'UTF-8') ?></p>
          <div class="mb-3 font-semibold prose max-w-none"><?= (string) ($q['question_text'] ?? '') ?></div>
          <input type="hidden" name="questions[<?= (int) $q['id'] ?>][id]" value="<?= (int) $q['id'] ?>">

          <?php if (($q['question_type'] ?? 'closed') === 'open'): ?>
            <div class="space-y-2">
              <?php foreach (['A','B','C','D'] as $k): $opt = (string) ($q['option_' . strtolower($k)] ?? ''); if ($opt === '') continue; ?>
                <label class="flex items-center gap-2 rounded-xl border border-slate-200 p-2">
                  <input type="radio" name="questions[<?= (int) $q['id'] ?>][answer_option]" value="<?= $k ?>">
                  <span class="text-sm"><strong><?= $k ?>.</strong> <?= (string) $opt ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <textarea name="questions[<?= (int) $q['id'] ?>][answer_text]" rows="4" class="w-full rounded-xl border border-slate-300 px-3 py-2" placeholder="Javobingizni yozing..."></textarea>
          <?php endif; ?>
        </section>
      <?php endforeach; ?>

      <button class="w-full rounded-xl bg-green-700 px-4 py-3 font-semibold text-white">Yakunlash</button>
    </form>
  </main>

  <script>
    const endTs = <?= (int) $endTs ?>;
    const timerEl = document.getElementById('timer');
    const form = document.getElementById('testForm');
    let submitted = false;

    function safeSubmit() {
      if (submitted) return;
      submitted = true;
      form.submit();
    }

    form.addEventListener('submit', () => { submitted = true; });

    function tick() {
      const now = Math.floor(Date.now() / 1000);
      let left = endTs - now;
      if (left < 0) left = 0;
      const m = String(Math.floor(left / 60)).padStart(2, '0');
      const s = String(left % 60).padStart(2, '0');
      timerEl.textContent = `${m}:${s}`;
      if (left === 0) {
        safeSubmit();
      }
    }

    tick();
    setInterval(tick, 1000);
  </script>
</body>
</html>
