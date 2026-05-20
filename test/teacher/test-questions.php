<?php
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../database/Database.php';
require_once __DIR__ . '/../shared/dashboard_repository.php';
require_once __DIR__ . '/../layouts/role_dashboard.php';

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
if ($testId > 0) {
    $st = $db->prepare('SELECT t.id,t.title,t.duration_minutes,t.question_limit,t.visibility,t.private_code,s.name AS subject_name FROM tests t LEFT JOIN subjects s ON s.id=t.subject_id WHERE t.id=? AND t.teacher_id=? LIMIT 1');
    $st->bind_param('ii', $testId, $teacherId);
    $st->execute();
    $test = $st->get_result()->fetch_assoc();
    $st->close();

    if ($test) {
        if (in_array($qTypeFilter, ['open', 'closed'], true)) {
            $sq = $db->prepare('SELECT id,question_text,question_type,option_a,option_b,option_c,option_d,correct_option,created_at FROM test_questions WHERE test_id=? AND teacher_id=? AND question_type=? ORDER BY id DESC');
            $sq->bind_param('iis', $testId, $teacherId, $qTypeFilter);
        } else {
            $sq = $db->prepare('SELECT id,question_text,question_type,option_a,option_b,option_c,option_d,correct_option,created_at FROM test_questions WHERE test_id=? AND teacher_id=? ORDER BY id DESC');
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

<link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.css">
<script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
  .ql-container { min-height: 110px; }
  .q-html { display: none; }
  .ql-editor img { max-width: 100%; height: auto; }
  .q-action { transition: all .2s ease; }
</style>

<?php if (!$test): ?>
  <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-700">
    Test topilmadi yoki sizga tegishli emas.
  </section>
<?php else: ?>
  <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h2 class="font-heading text-lg font-semibold">Savol qo'shish: <?= h($test['title'] ?? '-') ?></h2>
        <p class="text-sm text-slate-500">Fullscreen savol yaratish sahifasi</p>
      </div>
      <a href="/test/teacher/tests/index.php" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">Orqaga</a>
    </div>

    <div class="grid gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm md:grid-cols-2 lg:grid-cols-4">
      <div><p class="text-slate-500">Fan</p><p class="font-semibold text-slate-800"><?= h($test['subject_name'] ?? '-') ?></p></div>
      <div><p class="text-slate-500">Davomiyligi</p><p class="font-semibold text-slate-800"><?= (int) ($test['duration_minutes'] ?? 0) ?> min</p></div>
      <div><p class="text-slate-500">Savollar soni limiti</p><p class="font-semibold text-slate-800"><?= (int) ($test['question_limit'] ?? 0) ?></p></div>
      <div><p class="text-slate-500">Status</p><p class="font-semibold text-slate-800"><?= (($test['visibility'] ?? 'open') === 'closed') ? 'Private #' . h($test['private_code'] ?? '-') : 'Public' ?></p></div>
    </div>

    <form method="POST" action="/test/teacher/insert/test-questions.php" id="qForm" class="space-y-4">
      <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
      <input type="hidden" name="test_id" value="<?= (int) $testId ?>">

      <section class="rounded-2xl border border-slate-200 p-4">
        <div class="mb-3 flex items-center justify-between">
          <h3 class="font-heading text-base font-semibold">Savol editori</h3>
          <button type="button" id="openFormulaQuestion" class="rounded-xl border border-slate-300 px-2.5 py-1.5 text-xs font-semibold transition hover:bg-slate-50"><span class="mr-1">∑</span> Equation</button>
        </div>
        <div id="questionEditor"></div>
        <input type="hidden" name="items[0][question_text]" id="questionHtml" class="q-html">
      </section>

      <section class="rounded-2xl border border-slate-200 p-4">
        <div class="grid gap-3 md:grid-cols-2">
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Savol turi</label>
            <select name="items[0][question_type]" id="questionType" class="w-full rounded-xl border border-slate-300 px-3 py-2.5">
              <option value="closed">Yopiq test</option>
              <option value="open">Ochiq test</option>
            </select>
          </div>
          <div id="correctWrap">
            <label class="mb-1 block text-sm font-medium text-slate-700">To'g'ri javob</label>
            <select name="items[0][correct_option]" id="correctOption" class="w-full rounded-xl border border-slate-300 px-3 py-2.5">
              <option value="">Tanlang</option>
              <option value="A">A</option>
              <option value="B">B</option>
              <option value="C">C</option>
              <option value="D">D</option>
            </select>
          </div>
        </div>
      </section>

      <section id="variantsBlock" class="rounded-2xl border border-slate-200 p-4">
        <h3 class="mb-3 font-heading text-base font-semibold">Variantlar (Rich Text)</h3>
        <div class="grid gap-4 md:grid-cols-2">
          <div>
            <div class="mb-2 flex items-center justify-between"><label class="text-xs font-semibold text-slate-600">A varianti</label><button type="button" class="formulaBtn rounded border border-slate-300 px-2 py-1 text-xs" data-target="A">Equation</button></div>
            <div id="editorA"></div><input type="hidden" name="items[0][option_a]" id="htmlA" class="q-html">
          </div>
          <div>
            <div class="mb-2 flex items-center justify-between"><label class="text-xs font-semibold text-slate-600">B varianti</label><button type="button" class="formulaBtn rounded border border-slate-300 px-2 py-1 text-xs" data-target="B">Equation</button></div>
            <div id="editorB"></div><input type="hidden" name="items[0][option_b]" id="htmlB" class="q-html">
          </div>
          <div>
            <div class="mb-2 flex items-center justify-between"><label class="text-xs font-semibold text-slate-600">C varianti</label><button type="button" class="formulaBtn rounded border border-slate-300 px-2 py-1 text-xs" data-target="C">Equation</button></div>
            <div id="editorC"></div><input type="hidden" name="items[0][option_c]" id="htmlC" class="q-html">
          </div>
          <div>
            <div class="mb-2 flex items-center justify-between"><label class="text-xs font-semibold text-slate-600">D varianti</label><button type="button" class="formulaBtn rounded border border-slate-300 px-2 py-1 text-xs" data-target="D">Equation</button></div>
            <div id="editorD"></div><input type="hidden" name="items[0][option_d]" id="htmlD" class="q-html">
          </div>
        </div>
      </section>

      <div class="sticky bottom-3 z-10 flex items-center justify-between rounded-2xl border border-slate-200 bg-white/95 p-3 shadow-lg backdrop-blur">
        <p class="text-xs text-slate-500">Savolni saqlashdan oldin barcha maydonlarni tekshiring</p>
        <button class="rounded-xl bg-green-700 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-green-800">Savolni saqlash</button>
      </div>
    </form>

    <form method="POST" action="/test/teacher/insert/import-test-questions.php" enctype="multipart/form-data" class="rounded-xl border border-slate-200 bg-slate-50 p-3">
      <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
      <input type="hidden" name="test_id" value="<?= (int) $testId ?>">
      <p class="mb-2 text-xs text-slate-600">Excel format: <strong>A=question_text</strong>, <strong>B=question_type(open/closed)</strong>, <strong>C=option_a</strong>, <strong>D=option_b</strong>, <strong>E=option_c</strong>, <strong>F=option_d</strong>, <strong>G=correct_option(A/B/C/D)</strong></p>
      <div class="flex flex-wrap items-center gap-2">
        <input type="file" name="excel_file" accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" required class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
        <button class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Excel/CSV import</button>
      </div>
    </form>
  </section>

  <section class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
      <h3 class="font-heading text-base font-semibold">Qo'shilgan savollar</h3>
      <div class="flex items-center gap-2">
        <form method="GET" class="flex items-center gap-2">
          <input type="hidden" name="test_id" value="<?= (int) $testId ?>">
          <select name="qtype" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
            <option value="">Barcha turlar</option>
            <option value="open" <?= $qTypeFilter === 'open' ? 'selected' : '' ?>>Ochiq</option>
            <option value="closed" <?= $qTypeFilter === 'closed' ? 'selected' : '' ?>>Yopiq</option>
          </select>
          <button class="rounded-xl border border-slate-300 px-3 py-2 text-sm">Filter</button>
        </form>
        <a href="/test/teacher/export-test-questions.php?test_id=<?= (int) $testId ?>" class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Export (CSV)</a>
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-3 py-2 text-left">Savol</th>
            <th class="px-3 py-2 text-left">Turi</th>
            <th class="px-3 py-2 text-left">To'g'ri javob</th>
            <th class="px-3 py-2 text-left">Amallar</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($questions as $q): ?>
            <tr class="border-t">
              <td class="px-3 py-2"><?= (string) ($q['question_text'] ?? '') ?></td>
              <td class="px-3 py-2"><?= h($q['question_type'] ?? 'closed') ?></td>
              <td class="px-3 py-2"><?= h($q['correct_option'] ?? '-') ?></td>
              <td class="px-3 py-2">
                <div class="flex items-center gap-2">
                  <a href="/test/teacher/update/test-question.php?id=<?= (int) ($q['id'] ?? 0) ?>&test_id=<?= (int) $testId ?>" class="q-action inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-300 text-slate-600 hover:-translate-y-0.5 hover:bg-slate-50" title="Edit">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg>
                  </a>
                  <button type="button" class="deleteQuestion q-action inline-flex h-9 w-9 items-center justify-center rounded-xl border border-rose-200 text-rose-600 hover:-translate-y-0.5 hover:bg-rose-50" data-id="<?= (int) ($q['id'] ?? 0) ?>" title="Delete">
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
    <input type="hidden" name="test_id" value="<?= (int) $testId ?>">
  </form>

  <div id="formulaModal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-slate-900/40 p-4">
    <div class="w-full max-w-3xl max-h-[85vh] overflow-y-auto rounded-2xl bg-white p-4 shadow-2xl">
      <div class="mb-2 flex items-center justify-between">
        <h3 class="font-heading text-lg font-semibold">Formula panel</h3>
        <button type="button" id="closeFormulaModal">X</button>
      </div>
      <div id="formulaBody"></div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
  <script>
    const byId = (x) => document.getElementById(x);
    const toolbar = [['bold','italic','underline','strike'], [{'size':['small',false,'large','huge']}], [{'align':[]}], [{'list':'ordered'},{'list':'bullet'}], ['subscript','superscript'], [{'color':[]}], ['formula','image']];

    const questionEditor = new Quill('#questionEditor', { theme: 'snow', modules: { toolbar } });
    const editorA = new Quill('#editorA', { theme: 'snow', modules: { toolbar } });
    const editorB = new Quill('#editorB', { theme: 'snow', modules: { toolbar } });
    const editorC = new Quill('#editorC', { theme: 'snow', modules: { toolbar } });
    const editorD = new Quill('#editorD', { theme: 'snow', modules: { toolbar } });

    const formulaTemplates = {
      'Fraction': [{label:'a/b',latex:'\\frac{a}{b}'}, {label:'(a+b)/(c+d)',latex:'\\frac{a+b}{c+d}'}],
      'Power': [{label:'x²',latex:'x^2'}, {label:'xⁿ',latex:'x^n'}],
      'Root': [{label:'√x',latex:'\\sqrt{x}'}, {label:'∛x',latex:'\\sqrt[3]{x}'}],
      'Sigma': [{label:'Σ',latex:'\\sum_{i=1}^{n} i'}],
      'Integral': [{label:'∫',latex:'\\int_a^b f(x)dx'}],
      'Greek': [{label:'α',latex:'\\alpha'}, {label:'β',latex:'\\beta'}, {label:'θ',latex:'\\theta'}, {label:'π',latex:'\\pi'}]
    };

    let activeFormulaQuill = questionEditor;
    const setActiveQuill = (q) => { activeFormulaQuill = q; };

    [questionEditor, editorA, editorB, editorC, editorD].forEach((q) => {
      q.root.addEventListener('focus', () => setActiveQuill(q));
      q.root.addEventListener('click', () => setActiveQuill(q));
    });

    const openFormulaModal = () => {
      const body = byId('formulaBody');
      body.innerHTML = '';
      Object.entries(formulaTemplates).forEach(([title, arr]) => {
        const sec = document.createElement('div');
        sec.className = 'mb-3';
        const h = document.createElement('div');
        h.className = 'mb-1 text-sm font-semibold';
        h.textContent = title;
        sec.appendChild(h);
        const wrap = document.createElement('div');
        wrap.className = 'flex flex-wrap gap-2';
        arr.forEach((item) => {
          const b = document.createElement('button');
          b.type = 'button';
          b.className = 'rounded border border-slate-300 px-2 py-1 text-xs hover:bg-slate-50';
          b.textContent = item.label;
          b.onclick = () => {
            const r = activeFormulaQuill.getSelection(true) || { index: activeFormulaQuill.getLength(), length: 0 };
            activeFormulaQuill.insertEmbed(r.index, 'formula', item.latex, 'user');
            activeFormulaQuill.insertText(r.index + 1, ' ', 'user');
            activeFormulaQuill.setSelection(r.index + 2, 0, 'silent');
          };
          wrap.appendChild(b);
        });
        sec.appendChild(wrap);
        body.appendChild(sec);
      });
      byId('formulaModal').classList.remove('hidden');
      byId('formulaModal').classList.add('flex');
    };

    byId('openFormulaQuestion')?.addEventListener('click', () => { setActiveQuill(questionEditor); openFormulaModal(); });
    document.querySelectorAll('.formulaBtn').forEach((btn) => {
      btn.addEventListener('click', () => {
        const map = { A: editorA, B: editorB, C: editorC, D: editorD };
        setActiveQuill(map[btn.dataset.target] || questionEditor);
        openFormulaModal();
      });
    });
    byId('closeFormulaModal')?.addEventListener('click', () => {
      byId('formulaModal').classList.add('hidden');
      byId('formulaModal').classList.remove('flex');
    });

    const questionType = byId('questionType');
    const variantsBlock = byId('variantsBlock');
    const correctOption = byId('correctOption');
    const syncType = () => {
      const isClosed = questionType.value === 'closed';
      variantsBlock.classList.toggle('hidden', !isClosed);
      correctOption.required = isClosed;
    };
    questionType?.addEventListener('change', syncType);
    syncType();

    byId('qForm')?.addEventListener('submit', (e) => {
      byId('questionHtml').value = questionEditor.root.innerHTML.trim();
      byId('htmlA').value = editorA.root.innerHTML.trim();
      byId('htmlB').value = editorB.root.innerHTML.trim();
      byId('htmlC').value = editorC.root.innerHTML.trim();
      byId('htmlD').value = editorD.root.innerHTML.trim();

      const qText = (questionEditor.getText() || '').trim();
      if (!qText) {
        e.preventDefault();
        Swal.fire({ icon: 'warning', title: 'Savol matni bo\'sh', text: 'Kamida bitta savol kiriting.' });
      }
    });

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
          customClass: {
            popup: 'rounded-2xl',
            confirmButton: 'swal2-confirm-gradient',
            cancelButton: 'swal2-cancel-soft'
          },
          showClass: { popup: 'swal2-show swal2-animate-show' },
          hideClass: { popup: 'swal2-hide swal2-animate-hide' }
        }).then((result) => {
          if (result.isConfirmed) {
            byId('delete_question_id').value = id;
            byId('deleteQuestionForm').submit();
          }
        });
      });
    });

    <?php if ($deletedFlag): ?>
      Swal.fire({
        icon: 'success',
        title: 'Savol muvaffaqiyatli o‘chirildi',
        timer: 1800,
        showConfirmButton: false,
        customClass: { popup: 'rounded-2xl' }
      });
    <?php endif; ?>
  </script>

  <style>
    .swal2-confirm-gradient {
      background: linear-gradient(135deg, #ef4444, #dc2626) !important;
      border-radius: 12px !important;
      box-shadow: 0 10px 20px rgba(220, 38, 38, 0.25) !important;
    }
    .swal2-cancel-soft {
      background: linear-gradient(135deg, #86efac, #10b981) !important;
      border-radius: 12px !important;
      color: #064e3b !important;
    }
  </style>
<?php endif; ?>

<?php
$content = ob_get_clean();
render_dashboard_layout([
    'title' => 'Test savollari',
    'page_title' => 'Savol qo\'shish',
    'subtitle' => 'Professional question editor',
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
