<?php

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function icon_svg($name)
{
    $icons = [
        'dashboard' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 13h8V3H3v10Zm10 8h8V11h-8v10Zm0-18v4h8V3h-8ZM3 21h8v-4H3v4Z"/></svg>',
        'teachers' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 1 9l11 6 9-4.9V17M5 11.2V16c0 1.7 3.1 3 7 3s7-1.3 7-3v-4.8"/></svg>',
        'students' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm14 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'tests' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 3h6M10 7h4M6 3h12l-1 18H7L6 3Z"/></svg>',
        'create' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 5v14M5 12h14"/></svg>',
        'results' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 3v18h18M7 13l3-3 3 2 4-5"/></svg>',
        'stats' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20V10m6 10V4m6 16v-7m6 7V7"/></svg>',
        'settings' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 15.5A3.5 3.5 0 1 0 12 8.5a3.5 3.5 0 0 0 0 7Zm7.4-2.7 1.6 1.2-1.8 3.2-2-.5a7.8 7.8 0 0 1-1.8 1l-.3 2.1h-3.7l-.3-2.1a7.8 7.8 0 0 1-1.8-1l-2 .5L3 14l1.6-1.2a8.2 8.2 0 0 1 0-2.6L3 9 4.8 5.8l2 .5c.6-.4 1.2-.7 1.8-1L8.9 3h3.7l.3 2.3c.6.3 1.2.6 1.8 1l2-.5L21 9l-1.6 1.2c.2.9.2 1.7 0 2.6Z"/></svg>',
        'profile' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21a8 8 0 0 0-16 0M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/></svg>',
        'logout' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>',
    ];

    return $icons[$name] ?? $icons['dashboard'];
}

function render_dashboard_layout($opts)
{
    $title = $opts['title'] ?? 'SamDU Test Tizimi';
    $pageTitle = $opts['page_title'] ?? 'Dashboard';
    $subtitle = $opts['subtitle'] ?? '';
    $roleName = $opts['role_name'] ?? 'User';
    $menu = $opts['menu'] ?? [];
    $active = $opts['active'] ?? 'dashboard';
    $user = $opts['user'] ?? [];
    $content = $opts['content'] ?? '';
    $displayName = trim((string) ($user['fullname'] ?? $user['username'] ?? 'U'));
    $parts = preg_split('/\s+/', $displayName);
    $initials = '';
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        $initials .= strtoupper(substr($part, 0, 1));
        if (strlen($initials) >= 2) {
            break;
        }
    }
    if ($initials === '') {
        $initials = 'U';
    }

    ?>
<!doctype html>
<html lang="uz">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($title) ?></title>
  <meta name="description" content="SamDU uchun zamonaviy online test platformasi">
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
  <style>
    .font-heading { font-family: 'Poppins', sans-serif; }
    body { font-family: 'Inter', sans-serif; }
  </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased">
  <div class="min-h-screen md:flex">
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 w-72 -translate-x-full bg-gradient-to-b from-green-800 via-green-700 to-emerald-700 text-white shadow-2xl transition-transform duration-300 md:translate-x-0">
      <div class="flex h-16 items-center justify-between border-b border-white/15 px-5">
        <div>
          <p class="font-heading text-lg font-semibold">SamDU Test Tizimi</p>
          <p class="text-xs text-emerald-100"><?= h($roleName) ?> paneli</p>
        </div>
        <button id="closeSidebar" class="md:hidden rounded-lg bg-white/10 px-2 py-1">✕</button>
      </div>
      <nav class="space-y-1 p-4">
        <?php foreach ($menu as $item): ?>
          <?php $isActive = ($active === $item['key']); ?>
          <a href="<?= h($item['href']) ?>" class="group flex items-center gap-3 rounded-xl px-3 py-2.5 transition <?= $isActive ? 'bg-white/20 shadow-lg shadow-cyan-500/20' : 'hover:bg-white/10' ?>">
            <span class="h-5 w-5 text-cyan-100"><?= icon_svg($item['icon']) ?></span>
            <span class="text-sm font-medium"><?= h($item['label']) ?></span>
          </a>
        <?php endforeach; ?>
      </nav>
      <div class="absolute bottom-0 left-0 right-0 p-4">
        <a href="/olimpiada.uz/test/auth/logout.php" class="flex items-center gap-3 rounded-xl bg-white/10 px-3 py-2.5 text-sm font-medium hover:bg-white/20">
          <span class="h-5 w-5"><?= icon_svg('logout') ?></span>
          Chiqish
        </a>
      </div>
    </aside>

    <div id="backdrop" class="fixed inset-0 z-30 hidden bg-slate-900/35 md:hidden"></div>

    <main class="flex-1 md:ml-72">
      <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/90 backdrop-blur">
        <div class="flex h-16 w-full items-center gap-4 px-4 sm:px-6 lg:px-8">
          <button id="openSidebar" class="rounded-lg border border-slate-200 p-2 md:hidden">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
          </button>
          <div class="min-w-0 flex-1">
            <p class="font-heading text-lg font-semibold"><?= h($pageTitle) ?></p>
            <p class="truncate text-xs text-slate-500"><?= h($subtitle) ?></p>
          </div>
          <div class="hidden items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 sm:flex">
            <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input placeholder="Qidiruv" class="w-44 bg-transparent text-sm outline-none">
          </div>
          <button type="button" title="Account" class="flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white font-semibold text-slate-700 shadow-sm">
            <?= h($initials) ?>
          </button>
        </div>
      </header>

      <section class="w-full space-y-6 p-4 sm:p-6 lg:p-8">
        <?= $content ?>
      </section>
    </main>
  </div>

  <script>
    const sidebar = document.getElementById('sidebar');
    const openBtn = document.getElementById('openSidebar');
    const closeBtn = document.getElementById('closeSidebar');
    const backdrop = document.getElementById('backdrop');

    function openSidebar() {
      sidebar.classList.remove('-translate-x-full');
      backdrop.classList.remove('hidden');
    }

    function closeSidebar() {
      sidebar.classList.add('-translate-x-full');
      backdrop.classList.add('hidden');
    }

    openBtn && openBtn.addEventListener('click', openSidebar);
    closeBtn && closeBtn.addEventListener('click', closeSidebar);
    backdrop && backdrop.addEventListener('click', closeSidebar);
  </script>
</body>
</html>
<?php
}
