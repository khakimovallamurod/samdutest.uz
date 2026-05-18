<?php
require_once __DIR__ . '/auth/helpers.php';

start_secure_session();

$user = auth_user();
if ($user && !empty($user['role'])) {
    redirect(role_redirect_path($user['role']));
}

$csrf = csrf_token();
$error = flash_get('error');
$success = flash_get('success');
?>
<!doctype html>
<html lang="uz">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SamDU Test Tizimi | Tizimga kirish</title>
  <meta name="description" content="SamDU uchun zamonaviy online test platformasi">
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-cyan-50 via-white to-orange-50 text-slate-800">
  <div class="mx-auto grid min-h-screen max-w-6xl place-items-center px-4 py-10">
    <section class="w-full max-w-lg min-h-[560px] rounded-3xl border border-white/70 bg-white/70 p-7 shadow-2xl backdrop-blur-xl">
      <div class="mb-3 inline-flex items-center gap-2 rounded-full bg-cyan-100 px-3 py-1 text-sm font-semibold text-cyan-800">
        <span aria-hidden="true">🎓</span>
        <span>SamDU Test Tizimi</span>
      </div>
      <h1 class="text-2xl font-bold">Tizimga kirish</h1>
      <p class="mt-1 text-sm text-slate-600">Login va parolingizni kiriting. Rol avtomatik aniqlanadi.</p>

      <form class="mt-6 space-y-4" method="POST" action="/test/index.php" autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
        <div>
          <label class="mb-1 block text-sm font-medium">Login yoki Email</label>
          <input name="login" class="w-full rounded-xl border border-slate-300 px-3 py-3 outline-none transition focus:border-cyan-600 focus:ring-4 focus:ring-cyan-200" required>
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium">Parol</label>
          <input type="password" name="parol" class="w-full rounded-xl border border-slate-300 px-3 py-3 outline-none transition focus:border-cyan-600 focus:ring-4 focus:ring-cyan-200" required>
        </div>
        <button class="w-full rounded-xl bg-green-700 px-4 py-3 font-semibold text-white transition hover:bg-green-700">Kirish</button>
      </form>

      <div class="mt-4 flex items-center justify-between text-sm">
        <a href="/index.php" class="font-semibold text-slate-600 hover:text-slate-800">Bosh sahifaga qaytish</a>
        <a href="/test/register.php" class="font-semibold text-cyan-700 hover:text-cyan-800">Ro'yxatdan o'tish</a>
      </div>
    </section>
  </div>
  <?php if ($error || $success): ?>
    <div class="fixed right-5 top-5 z-50 rounded-xl px-4 py-3 text-sm font-semibold text-white shadow-lg <?= $error ? 'bg-rose-500' : 'bg-emerald-500' ?>">
      <?= htmlspecialchars($error ?: $success, ENT_QUOTES) ?>
    </div>
  <?php endif; ?>
</body>
</html>
