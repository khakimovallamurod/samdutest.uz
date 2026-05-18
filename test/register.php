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
  <title>SamDU Test Tizimi | Ro'yxatdan o'tish</title>
  <meta name="description" content="SamDU uchun zamonaviy online test platformasi">
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-cyan-50 via-white to-orange-50 text-slate-800">
  <div class="mx-auto grid min-h-screen max-w-6xl place-items-center px-4 py-10">
    <section class="w-full max-w-2xl rounded-3xl border border-white/70 bg-white/70 p-7 shadow-2xl backdrop-blur-xl">
      <div class="mb-3 inline-flex items-center gap-2 rounded-full bg-cyan-100 px-3 py-1 text-sm font-semibold text-cyan-800">
        <span aria-hidden="true">🎓</span>
        <span>SamDU Test Tizimi</span>
      </div>
      <h1 class="text-2xl font-bold">Sistemada ro'yxatdan o'tish</h1>
      <p class="mt-1 text-sm text-slate-600">Foydalanuvchi ma'lumotlarini kiriting va rol tanlang.</p>

      <form class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2" method="POST" action="/olimpiada.uz/test/auth/register.php" autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
        <div class="md:col-span-2">
          <label class="mb-1 block text-sm font-medium">F.I.O</label>
          <input name="fullname" required class="w-full rounded-xl border border-slate-300 px-3 py-3 outline-none transition focus:border-green-600 focus:ring-4 focus:ring-green-200">
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium">Telefon raqam</label>
          <input name="phone" required class="w-full rounded-xl border border-slate-300 px-3 py-3 outline-none transition focus:border-cyan-600 focus:ring-4 focus:ring-cyan-200" placeholder="+998901234567">
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium">Email</label>
          <input type="email" name="email" required class="w-full rounded-xl border border-slate-300 px-3 py-3 outline-none transition focus:border-cyan-600 focus:ring-4 focus:ring-cyan-200">
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium">Login</label>
          <input name="username" required class="w-full rounded-xl border border-slate-300 px-3 py-3 outline-none transition focus:border-cyan-600 focus:ring-4 focus:ring-cyan-200">
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium">Parol</label>
          <input type="password" name="password" required class="w-full rounded-xl border border-slate-300 px-3 py-3 outline-none transition focus:border-cyan-600 focus:ring-4 focus:ring-cyan-200">
        </div>
        <div class="md:col-span-2">
          <label class="mb-1 block text-sm font-medium">Rol tanlash</label>
          <select name="role" required class="w-full rounded-xl border border-slate-300 px-3 py-3 outline-none transition focus:border-orange-500 focus:ring-4 focus:ring-orange-200">
            <option value="teacher">O'qituvchi</option>
            <option value="student">O'quvchi</option>
          </select>
        </div>
        <div class="md:col-span-2">
          <button class="w-full rounded-xl bg-green-700 px-4 py-3 font-semibold text-white transition hover:bg-green-700">Ro'yxatdan o'tish</button>
        </div>
      </form>

      <div class="mt-4 text-sm">
        <a href="/olimpiada.uz/test/login.php" class="font-semibold text-cyan-700 hover:text-cyan-800">Tizimga kirish</a>
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
