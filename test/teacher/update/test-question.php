<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../database/Database.php';
require_once __DIR__ . '/../../layouts/role_dashboard.php';

require_auth(['teacher']);
$user = auth_user();
$teacherId = (int) ($user['id'] ?? 0);
$csrf = csrf_token();
$db = Database::connection();

$sanitize = static function ($html) {
    $html = (string) $html;
    $html = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $html);
    $html = preg_replace('#on[a-zA-Z]+\s*=\s*(["\']).*?\\1#is', '', $html);
    return trim($html);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? '')) {
        flash_set('error', 'Xavfsizlik xato');
        redirect('/test/teacher/tests/index.php');
    }

    $id = (int) ($_POST['id'] ?? 0);
    $testId = (int) ($_POST['test_id'] ?? 0);
    $questionType = trim((string) ($_POST['question_type'] ?? 'closed'));
    if (!in_array($questionType, ['open', 'closed'], true)) {
        $questionType = 'closed';
    }

    $q = $sanitize($_POST['question_text'] ?? '');
    $a = $sanitize($_POST['option_a'] ?? '');
    $b = $sanitize($_POST['option_b'] ?? '');
    $c = $sanitize($_POST['option_c'] ?? '');
    $d = $sanitize($_POST['option_d'] ?? '');
    $co = trim((string) ($_POST['correct_option'] ?? ''));

    if ($questionType === 'open') {
        if ($a === '' || $b === '' || $c === '' || $d === '' || !in_array($co, ['A', 'B', 'C', 'D'], true)) {
            flash_set('error', 'Ochiq savol uchun variantlar va to\'g\'ri javob to\'liq bo\'lishi kerak.');
            redirect('/test/teacher/update/test-question.php?id=' . $id . '&test_id=' . $testId);
        }
    } else {
        $a = $b = $c = $d = null;
        $co = null;
    }

    $st = $db->prepare('UPDATE test_questions SET question_text=?,question_type=?,option_a=?,option_b=?,option_c=?,option_d=?,correct_option=?,updated_at=NOW() WHERE id=? AND teacher_id=? LIMIT 1');
    $st->bind_param('sssssssii', $q, $questionType, $a, $b, $c, $d, $co, $id, $teacherId);
    $st->execute();
    $st->close();

    flash_set('success', 'Savol yangilandi.');
    redirect('/test/teacher/tests/questions.php?test_id=' . $testId);
}

$id = (int) ($_GET['id'] ?? 0);
$testId = (int) ($_GET['test_id'] ?? 0);
$st = $db->prepare('SELECT * FROM test_questions WHERE id=? AND teacher_id=? LIMIT 1');
$st->bind_param('ii', $id, $teacherId);
$st->execute();
$q = $st->get_result()->fetch_assoc();
$st->close();

ob_start();
?>
<link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.css">
<script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.js"></script>
<style>.ql-container{min-height:140px}.q-html{display:none}</style>
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
  <div class="mb-4 flex items-center justify-between">
    <h2 class="font-heading text-lg font-semibold">Savol tahrirlash</h2>
    <a href="/test/teacher/tests/questions.php?test_id=<?= (int) $testId ?>" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">Orqaga</a>
  </div>

  <form method="POST" id="editForm" class="space-y-3">
    <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
    <input type="hidden" name="id" value="<?= (int) $id ?>">
    <input type="hidden" name="test_id" value="<?= (int) $testId ?>">

    <div>
      <label class="mb-1 block text-sm font-medium">Savol matni</label>
      <div class="mb-2"><button id="openFormula" type="button" class="rounded-xl border border-slate-300 px-2.5 py-1.5 text-xs font-semibold transition hover:bg-slate-50"><span class="mr-1">∑</span> Equation</button></div>
      <div id="questionEditor"><?= (string) ($q['question_text'] ?? '') ?></div>
      <input type="hidden" name="question_text" id="questionHtml" class="q-html">
    </div>

    <div>
      <label class="mb-1 block text-sm font-medium">Savol turi</label>
      <select name="question_type" id="question_type" class="w-full rounded-xl border border-slate-300 px-3 py-2">
        <option value="closed" <?= (($q['question_type'] ?? 'closed') === 'closed') ? 'selected' : '' ?>>Yopiq</option>
        <option value="open" <?= (($q['question_type'] ?? '') === 'open') ? 'selected' : '' ?>>Ochiq</option>
      </select>
    </div>

    <div id="variantsBlock" class="space-y-3">
      <input name="option_a" class="w-full rounded-xl border px-3 py-2" value="<?= h($q['option_a'] ?? '') ?>" placeholder="A varianti">
      <input name="option_b" class="w-full rounded-xl border px-3 py-2" value="<?= h($q['option_b'] ?? '') ?>" placeholder="B varianti">
      <input name="option_c" class="w-full rounded-xl border px-3 py-2" value="<?= h($q['option_c'] ?? '') ?>" placeholder="C varianti">
      <input name="option_d" class="w-full rounded-xl border px-3 py-2" value="<?= h($q['option_d'] ?? '') ?>" placeholder="D varianti">
      <select name="correct_option" class="w-full rounded-xl border px-3 py-2">
        <option value="">Tanlang</option>
        <?php foreach (['A', 'B', 'C', 'D'] as $x): ?>
          <option value="<?= $x ?>" <?= (($q['correct_option'] ?? '') === $x) ? 'selected' : '' ?>><?= $x ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <button class="rounded-xl bg-green-700 px-4 py-2 text-white">Saqlash</button>
  </form>
</section>

<div id="formulaModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 p-4">
  <div class="w-full max-w-3xl max-h-[85vh] overflow-y-auto rounded-2xl bg-white p-4">
    <div class="mb-2 flex items-center justify-between">
      <h3 class="font-heading text-lg font-semibold">Formula panel</h3>
      <button type="button" id="closeFormula">X</button>
    </div>
    <div id="formulaBody"></div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
<script>
  const qEditor = new Quill('#questionEditor', { theme: 'snow', modules: { toolbar: [['bold','italic','underline'], [{'list':'ordered'},{'list':'bullet'}], ['formula']] } });
  const typeEl = document.getElementById('question_type');
  const variantsBlock = document.getElementById('variantsBlock');
  const formulaModal = document.getElementById('formulaModal');
  const questionHtml = document.getElementById('questionHtml');

  const formulaTemplates = {
    'Fraction': ['\\frac{a}{b}', '\\frac{a+b}{c+d}'],
    'Power': ['x^2', 'x^n'],
    'Root': ['\\sqrt{x}', '\\sqrt[3]{x}'],
    'Integral': ['\\int_a^b f(x)dx'],
    'Sigma': ['\\sum_{i=1}^{n} i'],
    'Greek': ['\\alpha', '\\beta', '\\theta', '\\pi']
  };

  const body = document.getElementById('formulaBody');
  Object.entries(formulaTemplates).forEach(([title, list]) => {
    const sec = document.createElement('div'); sec.className = 'mb-3';
    const h = document.createElement('div'); h.className = 'mb-1 text-sm font-semibold'; h.textContent = title; sec.appendChild(h);
    const row = document.createElement('div'); row.className = 'flex flex-wrap gap-2';
    list.forEach((latex) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'rounded border border-slate-300 px-2 py-1 text-xs hover:bg-slate-50';
      b.textContent = latex;
      b.onclick = () => {
        const r = qEditor.getSelection(true) || { index: qEditor.getLength(), length: 0 };
        qEditor.insertEmbed(r.index, 'formula', latex, 'user');
        qEditor.insertText(r.index + 1, ' ', 'user');
        qEditor.setSelection(r.index + 2, 0, 'silent');
      };
      row.appendChild(b);
    });
    sec.appendChild(row);
    body.appendChild(sec);
  });

  const syncType = () => {
    const isOpen = typeEl.value === 'open';
    variantsBlock.classList.toggle('hidden', !isOpen);
  };
  typeEl.addEventListener('change', syncType);
  syncType();

  document.getElementById('openFormula')?.addEventListener('click', () => {
    formulaModal.classList.remove('hidden');
    formulaModal.classList.add('flex');
  });
  document.getElementById('closeFormula')?.addEventListener('click', () => {
    formulaModal.classList.add('hidden');
    formulaModal.classList.remove('flex');
  });

  document.getElementById('editForm')?.addEventListener('submit', () => {
    questionHtml.value = qEditor.root.innerHTML.trim();
  });
</script>
<?php
$content = ob_get_clean();
render_dashboard_layout([
    'title' => 'Savol edit',
    'page_title' => 'Savol tahrirlash',
    'subtitle' => '',
    'role_name' => 'Teacher',
    'menu' => [['key' => 'tests', 'label' => 'Testlar', 'icon' => 'tests', 'href' => '/test/teacher/tests/index.php']],
    'active' => 'tests',
    'user' => $user,
    'content' => $content,
]);
