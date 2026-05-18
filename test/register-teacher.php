<?php
require_once __DIR__ . '/auth/helpers.php';

start_secure_session();

$user = auth_user();
if ($user) {
    redirect(role_redirect_path($user['role'] ?? 'user'));
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
  <title>Teacher Register | SAMDU Olimpiada</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-cyan-50 via-white to-orange-50 text-slate-800">
  <div class="mx-auto max-w-3xl px-4 py-10">
    <div class="rounded-3xl border border-white bg-white/90 p-7 shadow-xl">
      <h1 class="text-2xl font-bold">O'qituvchi ro'yxatdan o'tish</h1>
      <p class="mt-1 text-sm text-slate-500">Formani to'ldiring. Admin tasdig'idan keyin akkaunt faollashadi.</p>

      <form class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2" method="POST" action="/olimpiada.uz/test/auth/register-teacher.php" autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">

        <div class="md:col-span-2">
          <label class="mb-1 block text-sm font-medium">F.I.Sh</label>
          <input name="fullname" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5" />
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium">Username</label>
          <input name="username" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5" />
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium">Email</label>
          <input type="email" name="email" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5" />
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium">Parol</label>
          <input type="password" name="password" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5" />
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium">Telefon</label>
          <input name="phone" placeholder="+998..." class="w-full rounded-xl border border-slate-300 px-3 py-2.5" />
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium">Kafedra</label>
          <input name="department" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5" />
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium">Fan</label>
          <input name="subject" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5" />
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium">Lavozim</label>
          <input name="position" class="w-full rounded-xl border border-slate-300 px-3 py-2.5" />
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium">Tajriba (yil)</label>
          <input type="number" min="0" max="60" name="experience_years" value="0" class="w-full rounded-xl border border-slate-300 px-3 py-2.5" />
        </div>

        <div class="md:col-span-2 flex gap-3">
          <button class="rounded-xl bg-gradient-to-r from-green-500 to-cyan-500 px-5 py-3 font-semibold text-white">Yuborish</button>
          <a href="/olimpiada.uz/test/login.php" class="rounded-xl bg-slate-100 px-5 py-3 font-semibold text-slate-700">Loginga qaytish</a>
        </div>
      </form>
    </div>
  </div>

  <?php if ($error || $success): ?>
  <div class="fixed right-5 top-5 z-50 rounded-xl px-4 py-3 text-sm font-semibold text-white shadow-lg <?= $error ? 'bg-rose-500' : 'bg-emerald-500' ?>">
    <?= htmlspecialchars($error ?: $success, ENT_QUOTES) ?>
  </div>
  <?php endif; ?>
</body>
</html>
