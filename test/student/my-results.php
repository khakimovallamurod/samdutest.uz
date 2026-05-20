<?php
require_once __DIR__ . '/_shared.php';

$user = auth_user();
$studentId = (int) ($user['id'] ?? 0);
$results = get_student_attempts($studentId, 50);

ob_start();
?>
<section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
  <div class="border-b border-slate-200 p-4"><h2 class="font-heading text-lg font-semibold">Mening natijalarim</h2></div>
  <div class="overflow-x-auto">
    <table class="min-w-full text-sm"><thead class="bg-slate-50 text-slate-600"><tr><th class="px-4 py-3 text-left">Test</th><th class="px-4 py-3 text-left">To'g'ri</th><th class="px-4 py-3 text-left">Xato</th><th class="px-4 py-3 text-left">Kutilmoqda</th><th class="px-4 py-3 text-left">Holat</th><th class="px-4 py-3 text-left">Sana</th><th class="px-4 py-3 text-left">Amallar</th></tr></thead>
      <tbody><?php foreach ($results as $row): ?><tr class="border-t border-slate-100 hover:bg-cyan-50/30"><td class="px-4 py-3"><?= h($row['test_title'] ?? '-') ?></td><td class="px-4 py-3"><?= (int) ($row['correct_count'] ?? 0) ?></td><td class="px-4 py-3"><?= (int) ($row['wrong_count'] ?? 0) ?></td><td class="px-4 py-3"><?= (int) ($row['pending_count'] ?? 0) ?></td><td class="px-4 py-3"><?= h($row['status'] ?? '-') ?></td><td class="px-4 py-3"><?= h($row['taken_at'] ?? '-') ?></td><td class="px-4 py-3"><a class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-300 text-slate-600 hover:bg-slate-50" href="/test/student/attempt-view.php?attempt_id=<?= (int) ($row['id'] ?? 0) ?>">👁</a></td></tr><?php endforeach; ?><?php if (count($results) === 0): ?><tr><td colspan="7" class="px-4 py-4 text-sm text-slate-600">Hozircha ishlangan test yo'q.</td></tr><?php endif; ?></tbody>
    </table>
  </div>
</section>
<?php
$content = ob_get_clean();

render_dashboard_layout([
    'title' => 'SamDU Test Tizimi | Student Results',
    'page_title' => 'Mening natijalarim',
    'subtitle' => 'Ishlangan testlar natijasi',
    'role_name' => 'Student',
    'menu' => student_menu(),
    'active' => 'my-results',
    'user' => $user,
    'content' => $content,
]);
