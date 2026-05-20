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

ob_start();
?>
<?php if ($error || $success): ?>
  <div class="rounded-xl px-4 py-3 text-sm font-semibold text-white <?= $error ? 'bg-rose-500' : 'bg-emerald-500' ?>"><?= h($error ?: $success) ?></div>
<?php endif; ?>

<section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
  <div class="flex items-center justify-between gap-3 border-b border-slate-200 p-5">
    <div>
      <h2 class="font-heading text-xl font-semibold text-slate-900">Yangi test yaratish</h2>
      <p class="text-sm text-slate-500">Test qo'shish formasi alohida faylda ishlaydi va multiple qo'shishni qo'llab-quvvatlaydi</p>
    </div>
    <a href="/test/teacher/tests/index.php" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400">Orqaga</a>
  </div>

  <div class="p-5 sm:p-6 lg:p-8">
    <?php if (count($subjects) === 0): ?>
      <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700">
        Avval fan yarating, keyin test qo'shasiz.
      </div>
    <?php else: ?>
      <form method="POST" action="/test/teacher/insert/tests.php" id="multiCreateForm" class="space-y-4">
        <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">

        <div id="testBlocks" class="space-y-4"></div>

        <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
          <button type="button" id="addBlock" class="rounded-xl border border-emerald-400 px-4 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-50">+ Yana test qo'shish</button>
          <div class="flex flex-wrap items-center gap-3">
            <button class="rounded-xl bg-green-700 px-5 py-2.5 font-semibold text-white transition hover:bg-green-800">Barchasini saqlash</button>
            <a href="/test/teacher/tests/index.php" class="rounded-xl border border-slate-300 px-5 py-2.5 font-semibold text-slate-700 transition hover:border-slate-400">Bekor qilish</a>
          </div>
        </div>
      </form>
    <?php endif; ?>
  </div>
</section>

<?php if (count($subjects) > 0): ?>
<script>
  const subjectsHtml = `<?php foreach ($subjects as $subject): ?><option value="<?= (int) $subject['id'] ?>"><?= h($subject['name']) ?></option><?php endforeach; ?>`;
  const blocksRoot = document.getElementById('testBlocks');
  const addBlockBtn = document.getElementById('addBlock');

  const blockTemplate = (i) => `
    <article class="rounded-2xl border border-slate-200 p-4">
      <div class="mb-3 flex items-center justify-between gap-3">
        <h3 class="font-heading text-base font-semibold text-slate-800">Test #${i + 1}</h3>
        <button type="button" class="removeBlock rounded-xl border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-600 transition hover:bg-rose-50">O'chirish</button>
      </div>
      <div class="grid gap-4 lg:grid-cols-2">
        <div class="space-y-1">
          <label class="block text-sm font-medium text-slate-700">Fan</label>
          <select name="items[${i}][subject_id]" class="w-full rounded-xl border border-slate-300 px-3 py-2.5" required>
            <option value="">Tanlang</option>${subjectsHtml}
          </select>
        </div>
        <div class="space-y-1 lg:col-span-2">
          <label class="block text-sm font-medium text-slate-700">Test nomi</label>
          <input name="items[${i}][title]" class="w-full rounded-xl border border-slate-300 px-3 py-2.5" required placeholder="Masalan: Matematika yakuniy testi">
        </div>
        <div class="space-y-1">
          <label class="block text-sm font-medium text-slate-700">Davomiyligi (daqiqa)</label>
          <input type="number" min="1" name="items[${i}][duration_minutes]" value="60" class="w-full rounded-xl border border-slate-300 px-3 py-2.5" required>
        </div>
        <div class="space-y-1">
          <label class="block text-sm font-medium text-slate-700">Urinishlar soni</label>
          <input type="number" min="1" name="items[${i}][attempts_limit]" value="1" class="w-full rounded-xl border border-slate-300 px-3 py-2.5" required>
        </div>
        <div class="space-y-1">
          <label class="block text-sm font-medium text-slate-700">Savollar soni</label>
          <input type="number" min="1" name="items[${i}][question_limit]" value="10" class="w-full rounded-xl border border-slate-300 px-3 py-2.5" required>
        </div>
        <div class="space-y-1">
          <label class="block text-sm font-medium text-slate-700">Test turi</label>
          <select name="items[${i}][visibility]" class="visibilitySel w-full rounded-xl border border-slate-300 px-3 py-2.5">
            <option value="open">Public</option>
            <option value="closed">Private</option>
          </select>
        </div>
        <div class="privateInfo hidden rounded-xl border border-indigo-200 bg-indigo-50 p-3 text-xs text-indigo-700 lg:col-span-2">
          Private test saqlanganda tizim avtomatik noyob Private ID yaratadi.
        </div>
      </div>
    </article>`;

  const rebuild = () => {
    const cards = [...blocksRoot.querySelectorAll('article')];
    cards.forEach((card, idx) => {
      card.querySelector('h3').textContent = `Test #${idx + 1}`;
      card.querySelectorAll('input,select').forEach((el) => {
        el.name = el.name.replace(/items\[\d+\]/, `items[${idx}]`);
      });
    });
  };

  const bindBlockEvents = (card) => {
    card.querySelector('.removeBlock')?.addEventListener('click', () => {
      const cards = blocksRoot.querySelectorAll('article');
      if (cards.length <= 1) return;
      card.remove();
      rebuild();
    });
    const sel = card.querySelector('.visibilitySel');
    const info = card.querySelector('.privateInfo');
    const sync = () => info.classList.toggle('hidden', sel.value !== 'closed');
    sel.addEventListener('change', sync);
    sync();
  };

  const addBlock = () => {
    const idx = blocksRoot.querySelectorAll('article').length;
    blocksRoot.insertAdjacentHTML('beforeend', blockTemplate(idx));
    const card = blocksRoot.lastElementChild;
    bindBlockEvents(card);
  };

  addBlockBtn?.addEventListener('click', addBlock);
  addBlock();
</script>
<?php endif; ?>
<?php
$content = ob_get_clean();
render_dashboard_layout([
    'title' => 'Test yaratish',
    'page_title' => 'Yangi test',
    'subtitle' => 'Teacher test create system',
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
