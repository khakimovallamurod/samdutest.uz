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

$attemptsLeftAfter = 0;

try {
    $test = get_student_test_details_with_private_code($testId, $privateCode);
    $questions = get_student_test_questions($testId);
    if (!$test || count($questions) === 0) {
        flash_set('error', 'Test topilmadi yoki savollar mavjud emas.');
        redirect('/test/student/tests.php');
    }

    $db = Database::connection();
    ensure_test_runtime_tables($db);

    $maxAttempts = max(1, (int)($test['attempts_limit'] ?? 1));
    $cnt = 0;
    $cst = $db->prepare("SELECT COUNT(*) c FROM test_attempts WHERE test_id=? AND student_id=? AND status<>'in_progress'");
    if ($cst) {
        $cst->bind_param('ii', $testId, $studentId);
        $cst->execute();
        $countRow = 0;
        $cst->bind_result($countRow);
        if ($cst->fetch()) { $cnt = (int)$countRow; }
        $cst->close();
    }
    if ($cnt >= $maxAttempts) {
        flash_set('error', 'Urinishlar tugagan');
        redirect('/test/student/tests.php');
    }
    $attemptsLeftAfter = max(0, $maxAttempts - ($cnt + 1));

    $duration = max(1, (int) ($test['duration_minutes'] ?? 60));
    $totalQuestions = count($questions);
    $teacherId = (int) ($test['teacher_id'] ?? 0);
    $subjectId = (int) ($test['subject_id'] ?? 0);
    $attemptId = 0;

    $endTs = time() + ($duration * 60);
} catch (Throwable $e) {
    error_log('Student test-start error: ' . $e->getMessage());
    flash_set('error', 'Testni boshlashda xatolik yuz berdi.');
    redirect('/test/student/tests.php');
}
?>
<!doctype html>
<html lang="uz">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($test['title'], ENT_QUOTES, 'UTF-8') ?> | Test</title>
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <script>
    window.MathJax = {
      tex: { inlineMath: [['\\(', '\\)'], ['$', '$']], displayMath: [['\\[', '\\]'], ['$$', '$$']] },
      startup: { ready: () => { MathJax.startup.defaultReady(); MathJax.typesetPromise().catch(() => {}); } },
      options: { skipHtmlTags: ['script', 'noscript', 'style', 'textarea', 'pre', 'code'] }
    };
  </script>
  <script defer src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    .q-panel { opacity: 0; transform: translateX(20px); transition: opacity .25s ease, transform .25s ease; position: absolute; inset: 0; pointer-events: none; }
    .q-panel.active { opacity: 1; transform: translateX(0); position: relative; pointer-events: auto; }
    .matrix-btn { transition: all .2s ease; }
    .matrix-unseen { background:#e5e7eb; color:#475569; }
    .matrix-current { background:#0ea5e9; color:#fff; }
    .matrix-answered { background:#16a34a; color:#fff; }
    .matrix-empty { background:#ef4444; color:#fff; }
    .opt-card { transition: all .2s ease; }
    .opt-card:hover { box-shadow: 0 0 0 2px rgba(6,182,212,.25); border-color: #22d3ee; transform: translateY(-1px); }
    .opt-card.checked { border-color:#22c55e; background:#f0fdf4; box-shadow: 0 0 0 2px rgba(34,197,94,.18); }
    .opt-radio { accent-color:#16a34a; width:20px; height:20px; }
    @media (max-width: 1023px){ .matrix-aside{display:none;} }
  </style>
</head>
<body class="bg-slate-50 text-slate-800">
  <main class="mx-auto max-w-7xl p-4 sm:p-6">
    <header class="mb-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-xl font-bold"><?= htmlspecialchars($test['title'], ENT_QUOTES, 'UTF-8') ?></h1>
          <p class="mt-1 text-sm text-slate-500">Fan: <?= htmlspecialchars((string) ($test['subject_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700">
          Qolgan vaqt: <span id="timer" class="font-mono">--:--</span>
        </div>
      </div>
    </header>

    <div class="grid gap-4 lg:grid-cols-12">
      <section class="lg:col-span-9 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <form id="testForm" method="POST" action="/test/student/test-submit.php" class="space-y-4">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="attempt_id" value="<?= $attemptId ?>">
          <input type="hidden" name="test_id" value="<?= $testId ?>">
          <input type="hidden" name="duration_minutes" value="<?= $duration ?>">
          <input type="hidden" name="teacher_id" value="<?= $teacherId ?>">
          <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
          <input type="hidden" name="total_questions" value="<?= $totalQuestions ?>">

          <div class="relative min-h-[420px]" id="questionPanels">
            <?php foreach ($questions as $i => $q): ?>
              <?php $qid = (int)$q['id']; $qt = (string)($q['question_type'] ?? 'closed'); ?>
              <section
                class="q-panel <?= $i === 0 ? 'active' : '' ?>"
                data-index="<?= $i ?>"
                data-qid="<?= $qid ?>"
                data-qtype="<?= htmlspecialchars($qt, ENT_QUOTES, 'UTF-8') ?>"
                data-correct="<?= htmlspecialchars((string)($q['correct_option'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
              >
                <article class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
                  <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-cyan-700">Savol <?= ($i + 1) ?> / <?= $totalQuestions ?></p>
                  <div class="prose max-w-none font-medium" data-math><?= (string) ($q['question_text'] ?? '') ?></div>
                  <input type="hidden" name="questions[<?= $qid ?>][id]" value="<?= $qid ?>">

                  <?php if ($qt === 'open'): ?>
                    <div class="mt-4 grid gap-3" data-answer-wrap>
                      <?php foreach (['A','B','C','D'] as $k): $opt = (string) ($q['option_' . strtolower($k)] ?? ''); if ($opt === '') continue; ?>
                        <label class="opt-card flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3" data-option-card>
                          <input class="opt-radio mt-0.5" type="radio" name="questions[<?= $qid ?>][answer_option]" value="<?= $k ?>">
                          <span class="text-sm"><strong><?= $k ?>.</strong> <span data-math><?= (string)$opt ?></span></span>
                        </label>
                      <?php endforeach; ?>
                    </div>
                  <?php else: ?>
                    <div class="mt-4" data-answer-wrap>
                      <textarea name="questions[<?= $qid ?>][answer_text]" rows="5" class="w-full rounded-xl border border-slate-300 px-3 py-2" placeholder="Javobingizni yozing..."></textarea>
                    </div>
                  <?php endif; ?>
                </article>
              </section>
            <?php endforeach; ?>
          </div>

          <div class="sticky bottom-2 z-10 rounded-2xl border border-slate-200 bg-white/95 p-3 shadow">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <button type="button" id="prevBtn" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold">Oldingi</button>
              <div class="hidden text-xs text-slate-500 sm:block">Savol raqamini o'ngdagi navigator orqali ham tanlashingiz mumkin</div>
              <div class="flex items-center gap-2">
                <button type="button" id="nextBtn" class="rounded-xl bg-cyan-600 px-4 py-2 text-sm font-semibold text-white">Keyingi</button>
                <button type="button" id="finishBtn" class="rounded-xl bg-green-700 px-4 py-2 text-sm font-semibold text-white">Tugatish</button>
              </div>
            </div>
          </div>
        </form>

        <div class="mt-3 grid grid-cols-4 gap-2 lg:hidden" id="mobileMatrix"></div>
      </section>

      <aside class="matrix-aside lg:col-span-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <h3 class="mb-3 text-sm font-semibold text-slate-700">Savollar navigatori</h3>
        <div class="grid grid-cols-4 gap-2" id="desktopMatrix"></div>
        <div class="mt-4 text-xs text-slate-500 space-y-1">
          <p><span class="inline-block h-3 w-3 rounded bg-gray-300 mr-1"></span> Ochilmagan</p>
          <p><span class="inline-block h-3 w-3 rounded bg-sky-500 mr-1"></span> Hozirgi</p>
          <p><span class="inline-block h-3 w-3 rounded bg-green-600 mr-1"></span> Javob berilgan</p>
          <p><span class="inline-block h-3 w-3 rounded bg-red-500 mr-1"></span> Bo'sh</p>
        </div>
      </aside>
    </div>
  </main>

  <script>
    const endTs = <?= (int) $endTs ?>;
    const timerEl = document.getElementById('timer');
    const form = document.getElementById('testForm');
    const panels = [...document.querySelectorAll('.q-panel')];
    const total = panels.length;
    const visited = new Set([0]);
    let current = 0;
    let submitted = false;
    let timeUpTriggered = false;

    const desktopMatrix = document.getElementById('desktopMatrix');
    const mobileMatrix = document.getElementById('mobileMatrix');

    function safeSubmit() {
      if (submitted) return;
      submitted = true;
      form.submit();
    }

    function answerState(idx) {
      const panel = panels[idx];
      const qtype = panel.dataset.qtype;
      if (qtype === 'open') {
        const checked = panel.querySelector('input[type="radio"]:checked');
        return checked ? 'answered' : (visited.has(idx) ? 'empty' : 'unseen');
      }
      const txt = (panel.querySelector('textarea')?.value || '').trim();
      return txt ? 'answered' : (visited.has(idx) ? 'empty' : 'unseen');
    }

    function matrixClass(idx) {
      if (idx === current) return 'matrix-current';
      const st = answerState(idx);
      if (st === 'answered') return 'matrix-answered';
      if (st === 'empty') return 'matrix-empty';
      return 'matrix-unseen';
    }

    function renderMatrix(root) {
      root.innerHTML = '';
      for (let i = 0; i < total; i++) {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = `matrix-btn h-10 rounded-lg text-sm font-semibold ${matrixClass(i)}`;
        b.textContent = String(i + 1);
        b.addEventListener('click', () => goTo(i));
        root.appendChild(b);
      }
    }

    function syncOptionCards(panel) {
      panel.querySelectorAll('[data-option-card]').forEach((card) => {
        const radio = card.querySelector('input[type="radio"]');
        card.classList.toggle('checked', !!radio?.checked);
      });
    }

    function bindPanelEvents(panel, idx) {
      panel.querySelectorAll('input[type="radio"]').forEach((r) => {
        r.addEventListener('change', () => {
          visited.add(idx);
          syncOptionCards(panel);
          renderMatrix(desktopMatrix);
          renderMatrix(mobileMatrix);
        });
      });
      panel.querySelectorAll('textarea').forEach((t) => {
        t.addEventListener('input', () => {
          visited.add(idx);
          renderMatrix(desktopMatrix);
          renderMatrix(mobileMatrix);
        });
      });
      syncOptionCards(panel);
    }

    function goTo(idx) {
      if (idx < 0 || idx >= total) return;
      panels[current].classList.remove('active');
      current = idx;
      visited.add(current);
      panels[current].classList.add('active');
      renderMatrix(desktopMatrix);
      renderMatrix(mobileMatrix);
    }

    function localMetrics() {
      let right = 0, wrong = 0, empty = 0, openTotal = 0;
      panels.forEach((p, i) => {
        const st = answerState(i);
        if (st !== 'answered') empty++;
        if (p.dataset.qtype === 'open') {
          openTotal++;
          const ch = p.querySelector('input[type="radio"]:checked');
          if (!ch) return;
          if ((ch.value || '') === (p.dataset.correct || '')) right++; else wrong++;
        }
      });
      const denom = Math.max(1, openTotal);
      const percent = Math.round((right / denom) * 100);
      return { right, wrong, empty, percent };
    }

    function getUnansweredIndexes() {
      const miss = [];
      panels.forEach((p, i) => {
        const qtype = p.dataset.qtype;
        if (qtype === 'open') {
          const checked = p.querySelector('input[type="radio"]:checked');
          if (!checked) miss.push(i);
        } else {
          const txt = (p.querySelector('textarea')?.value || '').trim();
          if (!txt) miss.push(i);
        }
      });
      return miss;
    }

    async function openFinishModal(auto = false) {
      const now = Math.floor(Date.now() / 1000);
      const usedSec = Math.max(0, (<?= (int)$duration ?> * 60) - Math.max(0, endTs - now));
      const mm = String(Math.floor(usedSec / 60)).padStart(2, '0');
      const ss = String(usedSec % 60).padStart(2, '0');
      const m = localMetrics();
      const pass = m.percent >= 60;

      if (auto) {
        await Swal.fire({ icon: 'warning', title: 'Vaqt tugadi', text: 'Test avtomatik yakunlanadi.' });
        safeSubmit();
        return;
      }

      const unanswered = getUnansweredIndexes();
      if (unanswered.length > 0) {
        await Swal.fire({
          icon: 'warning',
          title: 'Barcha savollarga javob bering',
          text: `${unanswered.length} ta savol javobsiz qoldi.`
        });
        goTo(unanswered[0]);
        return;
      }

      const title = pass ? 'Testdan muvaffaqiyatli o\'tdingiz' : 'Testdan o\'ta olmadingiz';
      const bg = pass ? 'linear-gradient(135deg,#dcfce7,#a7f3d0)' : 'linear-gradient(135deg,#ffedd5,#fecaca)';
      const icon = pass ? 'success' : 'error';

      const res = await Swal.fire({
        icon,
        title,
        html: `
          <div style="text-align:left;font-size:14px">
            <p><strong>To'g'ri javoblar:</strong> ${m.right}</p>
            <p><strong>Noto'g'ri javoblar:</strong> ${m.wrong}</p>
            <p><strong>Foiz:</strong> ${m.percent}%</p>
            <p><strong>Ishlagan vaqt:</strong> ${mm}:${ss}</p>
          </div>
        `,
        background: bg,
        showCancelButton: true,
        confirmButtonText: 'Natijani ko\'rish',
        cancelButtonText: 'Dashboardga qaytish',
        showDenyButton: <?= (int)$attemptsLeftAfter ?> > 0 ? true : false,
        denyButtonText: 'Qayta ishlash',
      });

      if (res.isDenied) {
        window.location.href = '/test/student/test-start.php?test_id=<?= (int)$testId ?><?= $privateCode !== '' ? '&private_code='.urlencode($privateCode) : '' ?>';
        return;
      }
      if (res.dismiss === Swal.DismissReason.cancel) {
        window.location.href = '/test/student/dashboard.php';
        return;
      }
      safeSubmit();
    }

    document.getElementById('prevBtn')?.addEventListener('click', () => goTo(current - 1));
    document.getElementById('nextBtn')?.addEventListener('click', () => goTo(current + 1));
    document.getElementById('finishBtn')?.addEventListener('click', () => openFinishModal(false));

    form.addEventListener('submit', () => { submitted = true; });

    function tick() {
      const now = Math.floor(Date.now() / 1000);
      let left = endTs - now;
      if (left < 0) left = 0;
      const m = String(Math.floor(left / 60)).padStart(2, '0');
      const s = String(left % 60).padStart(2, '0');
      timerEl.textContent = `${m}:${s}`;
      if (left === 0) {
        if (timeUpTriggered) return;
        timeUpTriggered = true;
        openFinishModal(true);
      }
    }

    panels.forEach((p, i) => bindPanelEvents(p, i));
    renderMatrix(desktopMatrix);
    renderMatrix(mobileMatrix);
    tick();
    setInterval(tick, 1000);

    document.addEventListener('DOMContentLoaded', function () {
      if (window.MathJax && typeof window.MathJax.typesetPromise === 'function') {
        window.MathJax.typesetPromise().catch(() => {});
      }
    });
  </script>
</body>
</html>
