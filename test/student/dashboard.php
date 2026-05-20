<?php
require_once __DIR__ . '/_shared.php';

$legacySection = strtolower(trim((string) ($_GET['section'] ?? '')));
$legacyMap = [
    'dashboard' => '/test/student/dashboard.php',
    'tests' => '/test/student/tests.php',
    'my-results' => '/test/student/my-results.php',
    'profile' => '/test/student/profile.php',
    'settings' => '/test/student/settings.php',
];

if ($legacySection !== '' && isset($legacyMap[$legacySection])) {
    header('Location: ' . $legacyMap[$legacySection]);
    exit;
}

$user = auth_user();
$studentId = (int) ($user['id'] ?? 0);
$error = flash_get('error');
$success = flash_get('success');

$metrics = get_student_metrics($studentId);
$results = get_student_attempts($studentId, 50);

ob_start();
?>
<?php if ($error || $success): ?>
  <div class="rounded-xl px-4 py-3 text-sm font-semibold text-white <?= $error ? 'bg-rose-500' : 'bg-emerald-500' ?>"><?= h($error ?: $success) ?></div>
<?php endif; ?>

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
  <article class="rounded-2xl border border-cyan-100 bg-cyan-50 p-4 shadow-sm"><div class="mb-2 flex items-center justify-between"><p class="text-sm text-cyan-700">Mavjud testlar</p><span class="h-8 w-8 text-cyan-600"><?= icon_svg('tests') ?></span></div><p class="text-2xl font-bold text-cyan-900"><?= (int) $metrics['available_tests'] ?></p></article>
  <article class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4 shadow-sm"><div class="mb-2 flex items-center justify-between"><p class="text-sm text-emerald-700">Mening natijalarim</p><span class="h-8 w-8 text-emerald-600"><?= icon_svg('results') ?></span></div><p class="text-2xl font-bold text-emerald-900"><?= (int) $metrics['my_results'] ?></p></article>
  <article class="rounded-2xl border border-violet-100 bg-violet-50 p-4 shadow-sm"><div class="mb-2 flex items-center justify-between"><p class="text-sm text-violet-700">Kutilayotgan tekshiruv</p><span class="h-8 w-8 text-violet-600"><?= icon_svg('stats') ?></span></div><p class="text-2xl font-bold text-violet-900"><?= count(array_filter($results, static fn($r) => (($r['status'] ?? '') === 'pending_review'))) ?></p></article>
</div>
<?php
$content = ob_get_clean();

render_dashboard_layout([
    'title' => 'SamDU Test Tizimi | Student Dashboard',
    'page_title' => 'Student Dashboard',
    'subtitle' => 'Testlar, natijalar va profilingizni boshqarish',
    'role_name' => 'Student',
    'menu' => student_menu(),
    'active' => 'dashboard',
    'user' => $user,
    'content' => $content,
]);
