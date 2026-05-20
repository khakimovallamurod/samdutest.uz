<?php
require_once __DIR__ . '/_shared.php';
$user = auth_user();
ob_start();
?>
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm text-sm text-slate-600">
  Sozlamalar bo'limi tayyor. Bu yerga keyin individual sozlamalar qo'shiladi.
</section>
<?php
$content = ob_get_clean();
render_dashboard_layout([
  'title' => 'SamDU Test Tizimi | Student Settings',
  'page_title' => 'Sozlamalar',
  'subtitle' => 'Hisob sozlamalari',
  'role_name' => 'Student',
  'menu' => student_menu(),
  'active' => 'settings',
  'user' => $user,
  'content' => $content,
]);
