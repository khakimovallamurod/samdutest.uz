<?php
require_once __DIR__ . '/_shared.php';
$user = auth_user();
ob_start();
?>
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm text-sm text-slate-600">
  Profil bo'limi tayyor. Login: <strong><?= h($user['username'] ?? '-') ?></strong>
</section>
<?php
$content = ob_get_clean();
render_dashboard_layout([
  'title' => 'SamDU Test Tizimi | Student Profile',
  'page_title' => 'Profil',
  'subtitle' => 'Shaxsiy ma’lumotlar',
  'role_name' => 'Student',
  'menu' => student_menu(),
  'active' => 'profile',
  'user' => $user,
  'content' => $content,
]);
