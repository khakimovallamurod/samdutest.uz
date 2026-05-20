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

$db = Database::connection();
ensure_teacher_tables($db);
$test = null;
if ($testId > 0) {
    $st = $db->prepare('SELECT t.id,t.title,t.duration_minutes,t.question_limit,t.visibility,t.private_code,s.name AS subject_name FROM tests t LEFT JOIN subjects s ON s.id=t.subject_id WHERE t.id=? AND t.teacher_id=? LIMIT 1');
    $st->bind_param('ii', $testId, $teacherId);
    $st->execute();
    $test = $st->get_result()->fetch_assoc();
    $st->close();
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
  .editor-shell { border: 1px solid #d1d5db; border-radius: 12px; overflow: hidden; background: #fff; }
  .editor-toolbar { background: #f3f4f6; border-bottom: 1px solid #e5e7eb; padding: 6px; display: flex; gap: 6px; overflow-x: auto; white-space: nowrap; }
  .editor-toolbar .ql-formats { margin-right: 0 !important; display: inline-flex; align-items: center; gap: 4px; }
  .editor-toolbar button, .editor-toolbar .ql-picker { border: 1px solid #d1d5db; border-radius: 8px; background: #fff; height: 28px; }
  .editor-shell .ql-container { border: 0 !important; min-height: 220px; font-size: 14px; }
  .editor-shell .ql-editor { min-height: 220px; line-height: 1.55; }
  .opt-editor .ql-container { min-height: 120px; }
  .opt-editor .ql-editor { min-height: 120px; }
  .radio-dot { width: 20px; height: 20px; }
  .question-card { border: 1px solid #e5e7eb; border-radius: 14px; box-shadow: 0 8px 20px rgba(15, 23, 42, .05); }
</style>

<?php if (!$test): ?>
<section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-700">
  Test topilmadi yoki sizga tegishli emas.
</section>
<?php else: ?>
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
  <div class="flex items-center justify-between">
    <div>
      <h2 class="font-heading text-lg font-semibold">Savol qo'shish: <?= h($test['title'] ?? '-') ?></h2>
      <p class="text-sm text-slate-500">Dynamic multiple question create page</p>
    </div>
    <a href="/test/teacher/tests/questions.php?test_id=<?= (int)$testId ?>" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">Savollar ro'yxati</a>
  </div>

  <form method="POST" action="/test/teacher/insert/test-questions.php" id="qForm" class="space-y-4">
    <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
    <input type="hidden" name="test_id" value="<?= (int)$testId ?>">

    <div id="questionCards" class="space-y-4"></div>

    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
      <button type="button" id="addQuestionBtn" class="rounded-xl border border-emerald-400 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50">+ Yana savol qo'shish</button>
      <button class="rounded-xl bg-green-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-800">Savollarni saqlash</button>
    </div>
  </form>
</section>

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
  const questionCards = byId('questionCards');
  const editors = [];
  let editorCounter = 0;
  let activeFormulaQuill = null;

  const mainToolbarTemplate = (idSuffix) => `
    <div id="toolbar_${idSuffix}" class="editor-toolbar">
      <span class="ql-formats"><button class="ql-bold"></button><button class="ql-italic"></button><button class="ql-underline"></button><button class="ql-strike"></button></span>
      <span class="ql-formats"><button class="ql-script" value="super"></button><button class="ql-script" value="sub"></button></span>
      <span class="ql-formats"><select class="ql-size"><option selected></option><option value="small"></option><option value="large"></option><option value="huge"></option></select></span>
      <span class="ql-formats"><select class="ql-align"></select><button class="ql-list" value="ordered"></button><button class="ql-list" value="bullet"></button></span>
      <span class="ql-formats"><select class="ql-color"></select><button class="ql-link"></button><button class="ql-image"></button><button class="ql-code-block"></button><button class="ql-formula"></button></span>
      <span class="ql-formats"><button type="button" data-undo="${idSuffix}" title="Undo">↶</button><button type="button" data-redo="${idSuffix}" title="Redo">↷</button><button type="button" data-formula-main="${idSuffix}" title="Formula">∑</button></span>
    </div>`;

  const optionToolbar = [['bold','italic','underline'], [{'script':'super'},{'script':'sub'}], [{'color':[]}], ['link','formula']];

  const cardTemplate = (idx, idSuffix) => `
    <article class="question-card bg-white p-4" data-q-idx="${idx}" data-suffix="${idSuffix}">
      <div class="mb-3 flex items-center justify-between">
        <h3 class="font-heading text-base font-semibold">Savol #${idx + 1}</h3>
        <button type="button" class="removeQuestion rounded-xl border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">O'chirish</button>
      </div>

      <div class="space-y-4">
        <section class="rounded-xl border border-slate-200 p-3">
          <label class="mb-2 block text-sm font-medium text-slate-700">Savol matni</label>
          <div class="editor-shell">
            ${mainToolbarTemplate(idSuffix)}
            <div id="questionEditor_${idSuffix}"></div>
          </div>
          <input type="hidden" name="items[${idx}][question_text]" id="questionHtml_${idSuffix}">
        </section>

        <section class="rounded-xl border border-slate-200 p-3">
          <label class="mb-2 block text-sm font-medium text-slate-700">Savol turi</label>
          <select name="items[${idx}][question_type]" class="questionType w-full rounded-xl border border-slate-300 px-3 py-2.5">
            <option value="closed">Yopiq test</option>
            <option value="open">Ochiq test</option>
          </select>
        </section>

        <section class="variantsBlock rounded-xl border border-slate-200 p-3">
          <h4 class="mb-3 font-heading text-sm font-semibold">Variantlar</h4>
          <div class="grid gap-4 md:grid-cols-2">
            ${['A','B','C','D'].map(letter => `
              <div class="optionRow rounded-xl border border-slate-200 p-2">
                <div class="mb-2 flex items-center gap-2">
                  <input class="radio-dot" id="radio_${letter}_${idSuffix}" type="radio" name="items[${idx}][correct_option]" value="${letter}">
                  <button type="button" class="optionPick text-xs font-semibold text-slate-700 hover:text-slate-900" data-radio-id="radio_${letter}_${idSuffix}">${letter} varianti</button>
                  <button type="button" class="formulaBtn ml-auto rounded border border-slate-300 px-2 py-1 text-xs" data-editor="opt_${letter}_${idSuffix}">Equation</button>
                </div>
                <div class="editor-shell opt-editor"><div id="opt_${letter}_${idSuffix}"></div></div>
                <input type="hidden" name="items[${idx}][option_${letter.toLowerCase()}]" id="html_${letter}_${idSuffix}">
              </div>
            `).join('')}
          </div>
        </section>
      </div>
    </article>`;

  const formulaTemplates = {
    Fraction:[{label:'a/b',latex:'\\frac{a}{b}'},{label:'(a+b)/(c+d)',latex:'\\frac{a+b}{c+d}'}],
    Power:[{label:'x²',latex:'x^2'},{label:'xⁿ',latex:'x^n'}],
    Root:[{label:'√x',latex:'\\sqrt{x}'},{label:'∛x',latex:'\\sqrt[3]{x}'}],
    Sigma:[{label:'Σ',latex:'\\sum_{i=1}^{n} i'}],
    Integral:[{label:'∫',latex:'\\int_a^b f(x)dx'}]
  };

  const openFormulaModal = () => {
    const body = byId('formulaBody'); body.innerHTML = '';
    Object.entries(formulaTemplates).forEach(([title, arr]) => {
      const sec = document.createElement('div'); sec.className = 'mb-3';
      const h = document.createElement('div'); h.className = 'mb-1 text-sm font-semibold'; h.textContent = title; sec.appendChild(h);
      const wrap = document.createElement('div'); wrap.className = 'flex flex-wrap gap-2';
      arr.forEach((it) => {
        const b = document.createElement('button');
        b.type = 'button'; b.className = 'rounded border border-slate-300 px-2 py-1 text-xs hover:bg-slate-50'; b.textContent = it.label;
        b.onclick = () => {
          if (!activeFormulaQuill) return;
          const r = activeFormulaQuill.getSelection(true) || { index: activeFormulaQuill.getLength(), length: 0 };
          activeFormulaQuill.insertEmbed(r.index, 'formula', it.latex, 'user');
          activeFormulaQuill.insertText(r.index + 1, ' ', 'user');
          activeFormulaQuill.setSelection(r.index + 2, 0, 'silent');
        };
        wrap.appendChild(b);
      });
      sec.appendChild(wrap); body.appendChild(sec);
    });
    byId('formulaModal').classList.remove('hidden'); byId('formulaModal').classList.add('flex');
  };

  const initCard = (card) => {
    const suffix = card.dataset.suffix;
    const idx = Number(card.dataset.qIdx);

    const qEditor = new Quill(`#questionEditor_${suffix}`, { theme:'snow', modules:{ toolbar:`#toolbar_${suffix}`, history:{delay:250,maxStack:50,userOnly:true} } });
    const optEditors = {};
    ['A','B','C','D'].forEach((l) => {
      optEditors[l] = new Quill(`#opt_${l}_${suffix}`, { theme:'snow', modules:{ toolbar: optionToolbar, history:{delay:250,maxStack:25,userOnly:true} } });
      optEditors[l].root.addEventListener('focus', () => activeFormulaQuill = optEditors[l]);
      optEditors[l].root.addEventListener('click', () => activeFormulaQuill = optEditors[l]);
    });
    qEditor.root.addEventListener('focus', () => activeFormulaQuill = qEditor);
    qEditor.root.addEventListener('click', () => activeFormulaQuill = qEditor);

    editors.push({ idx, suffix, qEditor, optEditors });

    card.querySelector(`[data-formula-main="${suffix}"]`)?.addEventListener('click', () => { activeFormulaQuill = qEditor; openFormulaModal(); });
    card.querySelector(`[data-undo="${suffix}"]`)?.addEventListener('click', () => qEditor.history.undo());
    card.querySelector(`[data-redo="${suffix}"]`)?.addEventListener('click', () => qEditor.history.redo());

    card.querySelectorAll('.formulaBtn').forEach((btn) => {
      btn.addEventListener('click', () => {
        const editorId = btn.dataset.editor;
        const letter = editorId.split('_')[1];
        activeFormulaQuill = optEditors[letter] || qEditor;
        openFormulaModal();
      });
    });

    card.querySelectorAll('.optionPick').forEach((pick) => {
      pick.addEventListener('click', () => {
        const radioId = pick.dataset.radioId || '';
        const radio = byId(radioId);
        if (radio) radio.checked = true;
      });
    });

    const typeSel = card.querySelector('.questionType');
    const variantsBlock = card.querySelector('.variantsBlock');
    const sync = () => {
      const isOpen = typeSel.value === 'open';
      variantsBlock.classList.toggle('hidden', !isOpen);
      card.querySelectorAll('input[type="radio"]').forEach((r) => r.required = isOpen);
    };
    typeSel.addEventListener('change', sync);
    sync();

    card.querySelector('.removeQuestion')?.addEventListener('click', () => {
      const cards = [...questionCards.querySelectorAll('.question-card')];
      if (cards.length <= 1) {
        Swal.fire({ icon:'info', title:'Kamida bitta savol qolsin' });
        return;
      }
      card.remove();
      rebuildIndexes();
    });
  };

  const rebuildIndexes = () => {
    const cards = [...questionCards.querySelectorAll('.question-card')];
    cards.forEach((card, idx) => {
      card.dataset.qIdx = String(idx);
      card.querySelector('h3').textContent = `Savol #${idx + 1}`;
      card.querySelectorAll('input,select').forEach((el) => {
        if (el.name) el.name = el.name.replace(/items\[\d+\]/, `items[${idx}]`);
      });
    });
  };

  const addCard = () => {
    const idx = questionCards.querySelectorAll('.question-card').length;
    const suffix = `q${editorCounter++}`;
    questionCards.insertAdjacentHTML('beforeend', cardTemplate(idx, suffix));
    initCard(questionCards.lastElementChild);
  };

  byId('addQuestionBtn')?.addEventListener('click', addCard);
  byId('closeFormulaModal')?.addEventListener('click', () => { byId('formulaModal').classList.add('hidden'); byId('formulaModal').classList.remove('flex'); });

  byId('qForm')?.addEventListener('submit', (e) => {
    let hasQuestion = false;
    editors.forEach((it) => {
      byId(`questionHtml_${it.suffix}`).value = it.qEditor.root.innerHTML.trim();
      ['A','B','C','D'].forEach((l) => {
        byId(`html_${l}_${it.suffix}`).value = it.optEditors[l].root.innerHTML.trim();
      });
      if ((it.qEditor.getText() || '').trim()) hasQuestion = true;
    });
    if (!hasQuestion) {
      e.preventDefault();
      Swal.fire({ icon:'warning', title:'Savol matni bo\'sh', text:'Kamida bitta savol kiriting.' });
    }
  });

  addCard();
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
render_dashboard_layout([
    'title' => 'Savol qo\'shish',
    'page_title' => 'Create Question',
    'subtitle' => 'Dynamic question create page',
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
