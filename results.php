<?php
include_once 'config.php';

$ip = get_ip();
$stmt = mysqli_prepare($link, "SELECT id FROM blocklist WHERE ip=? LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $ip);
mysqli_stmt_execute($stmt);
$blocked = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!empty($blocked['id'])) {
    header('Location: 400.html');
    exit;
}

$tests = [];
$res = mysqli_query($link, "SELECT id_hash, nomi FROM testlar ORDER BY nomi ASC");
while ($row = mysqli_fetch_assoc($res)) {
    $tests[] = $row;
}
function safe_text($value) {
    return htmlspecialchars(html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="uz">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Natijalar | SamDU Olimpiada</title>
  <meta name="description" content="SamDU olimpiada natijalari jadvali">
  <link rel="shortcut icon" href="img/favicon.png" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = { theme: { extend: {
      colors: { brand:'#22c55e', accent:'#0ea5e9', flame:'#f97316' },
      fontFamily: { heading:['Poppins','sans-serif'], body:['Inter','sans-serif'] },
      boxShadow: { soft:'0 10px 30px rgba(15, 23, 42, 0.08)' }
    }}};
  </script>
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
  <style>
    body { font-family: Inter, sans-serif; background: radial-gradient(circle at 10% 10%, #ecfeff 0%, #ffffff 48%, #f8fafc 100%); }
    .nav-link::after { content:''; position:absolute; left:0; bottom:-7px; width:100%; height:2px; background:linear-gradient(90deg,#22c55e,#0ea5e9); transform:scaleX(0); transform-origin:left; transition:transform .25s ease; }
    .nav-link:hover::after, .nav-link.active::after { transform:scaleX(1); }
    .grid-bg { position:absolute; inset:0; pointer-events:none; opacity:.18; background-image:linear-gradient(to right,rgba(14,165,233,.16) 1px,transparent 1px),linear-gradient(to bottom,rgba(14,165,233,.16) 1px,transparent 1px); background-size:40px 40px; will-change:transform; }
    .cursor-landing { position:fixed; width:34px; height:34px; border-radius:9999px; border:2px solid rgba(34,211,238,.95); box-shadow:0 0 0 4px rgba(34,211,238,.15); transform:translate(-50%,-50%); z-index:80; opacity:0; pointer-events:none; transition:opacity .2s ease,left .08s linear,top .08s linear; }
    .cursor-landing::after { content:''; position:absolute; left:50%; top:50%; width:8px; height:8px; border-radius:9999px; background:#22d3ee; box-shadow:0 0 10px rgba(34,211,238,.8); transform:translate(-50%,-50%); }
    #table table.dataTable { width:100% !important; border-collapse: collapse !important; border-spacing: 0 !important; }
    #table .dataTables_wrapper { padding: 8px; }
    #table table.dataTable thead th { font-weight:700; color:#0f172a; white-space:nowrap; background: linear-gradient(90deg, rgba(34,197,94,.08), rgba(14,165,233,.08)); border-bottom: 1px solid #dbeafe !important; }
    #table table.dataTable tbody td { color:#1e293b; border-bottom: 1px solid #eef2ff !important; }
    #table table.dataTable tbody tr:hover td { background: #f8fafc !important; }
    #table table.dataTable tbody tr.selected td { box-shadow: none !important; }
    #table .dataTables_filter input, #table .dataTables_length select { border:1px solid #cbd5e1; border-radius:10px; padding:6px 10px; background:#fff; }
    #table .dataTables_paginate .paginate_button { border-radius:8px !important; }
  </style>
</head>
<body class="min-h-screen flex flex-col text-slate-800 antialiased">
<header class="fixed top-0 z-50 w-full transition-all duration-300">
  <div id="navShell" class="mx-auto mt-3 max-w-7xl rounded-2xl border border-white/70 bg-white/80 px-4 py-2.5 shadow-soft backdrop-blur-xl md:px-6">
    <div class="flex items-center justify-between">
      <a href="index.php" class="flex items-center gap-3"><img src="img/logo-sam.png" alt="SamDU" class="h-11 w-auto"><p class="hidden font-heading text-sm font-bold sm:block">SAMDU OLIMPIADA</p></a>
      <button id="menuBtn" class="rounded-xl border border-slate-200 p-2 md:hidden" aria-expanded="false" aria-controls="mobileMenu"><svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 12h16M4 18h16" /></svg></button>
      <nav class="hidden items-center gap-7 md:flex">
        <a class="nav-link relative text-sm font-semibold text-slate-700" href="index.php">Bosh sahifa</a>
        <a class="nav-link relative text-sm font-semibold text-slate-700" href="reg-users.php">Ro'yxatdan o'tganlar</a>
        <a class="nav-link active relative text-sm font-semibold text-slate-700" href="results.php">Natijalar</a>
      </nav>
      <a href="test/" class="hidden rounded-xl bg-gradient-to-r from-brand to-accent px-5 py-2.5 text-sm font-semibold text-white md:inline-flex">Testlarni boshlash</a>
    </div>
    <div id="mobileMenu" class="max-h-0 overflow-hidden transition-all duration-300 md:hidden"><nav class="mt-2 grid gap-2 border-t border-slate-200 pt-3 pb-1"><a class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100" href="index.php">Bosh sahifa</a><a class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100" href="reg-users.php">Ro'yxatdan o'tganlar</a><a class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100" href="results.php">Natijalar</a></nav></div>
  </div>
</header>

<main class="flex-1 pt-32 pb-14">
  <section class="relative mx-auto max-w-7xl px-3 sm:px-4 md:px-6">
    <div class="absolute inset-0 -z-10"><div class="absolute -left-20 top-0 h-56 w-56 rounded-full bg-brand/20 blur-3xl"></div><div class="absolute right-0 top-14 h-52 w-52 rounded-full bg-accent/20 blur-3xl"></div><div class="grid-bg" id="gridBg"></div></div>
    <div class="rounded-3xl border border-white/70 bg-white/90 p-4 shadow-soft backdrop-blur-xl sm:p-6 md:p-8">
      <h1 class="font-heading text-2xl font-extrabold text-slate-900 sm:text-3xl">Olimpiada Natijalari</h1>
      <p class="mt-2 text-sm text-slate-600">Fan tanlang va reyting jadvalini ko'ring.</p>
      <div class="mt-5 w-full max-w-lg"><label for="fanlar" class="mb-2 block text-sm font-semibold text-slate-700">Fan yo'nalishi</label><select id="fanlar" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/20"><option value="">Fan tanlang</option><?php foreach ($tests as $test): ?><option value="<?= safe_text($test['id_hash']) ?>"><?= safe_text($test['nomi']) ?></option><?php endforeach; ?></select></div>
      <div class="mt-6 rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto p-1 sm:p-3"><div id="table" class="min-w-full"></div></div>
      </div>
    </div>
  </section>
</main>

<footer class="mt-10 border-t border-transparent bg-gradient-to-r from-brand/20 via-accent/20 to-flame/20">
  <div class="mx-auto max-w-7xl px-4 py-10 md:px-6">
    <div class="grid gap-8 md:grid-cols-3">
      <div>
        <h3 class="font-heading text-lg font-bold">SamDU Olimpiada</h3>
        <p class="mt-2 text-sm text-slate-600">Samarqand davlat universiteti iqtidorli talabalar bilan ishlash bo'limi platformasi.</p>
      </div>
      <div>
        <h3 class="font-heading text-lg font-bold">Aloqa</h3>
        <p class="mt-2 text-sm text-slate-600">Web: olimpiada.samdu.uz</p>
        <p class="text-sm text-slate-600">Natijalar: <a class="font-semibold text-accent" href="results.php">Ko'rish</a></p>
      </div>
      <div>
        <h3 class="font-heading text-lg font-bold">Ijtimoiy tarmoqlar</h3>
        <div class="mt-3 flex items-center gap-2">
          <a href="https://t.me/samdu_iqtidorli_talaba" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:border-accent hover:text-accent">Telegram</a>
          <a href="http://samsim.uz" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:border-brand hover:text-brand">SamSIM</a>
        </div>
      </div>
    </div>
    <div class="mt-8 border-t border-slate-200 pt-4 text-sm text-slate-500">© <?= date('Y') ?> SamDU Iqtidorli talabalar bo'limi. Barcha huquqlar himoyalangan.</div>
  </div>
</footer>
<div id="cursorLanding" class="cursor-landing"></div>

<script src="js/jquery.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script>
  const menuBtn = document.getElementById('menuBtn');
  const mobileMenu = document.getElementById('mobileMenu');
  menuBtn?.addEventListener('click', () => { const open = mobileMenu.style.maxHeight && mobileMenu.style.maxHeight !== '0px'; mobileMenu.style.maxHeight = open ? '0px' : mobileMenu.scrollHeight + 'px'; menuBtn.setAttribute('aria-expanded', String(!open)); });
  const navShell = document.getElementById('navShell');
  window.addEventListener('scroll', () => { navShell.style.background = window.scrollY > 20 ? 'rgba(255,255,255,.96)' : 'rgba(255,255,255,.8)'; }, { passive: true });

  function loadResults() {
    const fan_id = document.getElementById('fanlar').value;
    if (!fan_id) { document.getElementById('table').innerHTML = '<div class="p-6 text-sm text-slate-500">Natijalarni ko\'rish uchun fan tanlang.</div>'; return; }
    $.ajax({ url: 'get-results.php', type: 'post', data: { fan_id: fan_id }, success: function(data) { $('#table').html(data); } });
  }
  document.getElementById('fanlar').addEventListener('change', loadResults);
  loadResults();
  setInterval(loadResults, 60000);

  const gridBg = document.getElementById('gridBg');
  const cursorLanding = document.getElementById('cursorLanding');
  window.addEventListener('mousemove', (e) => {
    if (gridBg) { const gx = (e.clientX / window.innerWidth - 0.5) * 10; const gy = (e.clientY / window.innerHeight - 0.5) * 10; gridBg.style.transform = `translate3d(${gx}px, ${gy}px, 0)`; }
    if (cursorLanding && window.matchMedia('(pointer:fine)').matches) { cursorLanding.style.opacity = '1'; cursorLanding.style.left = e.clientX + 'px'; cursorLanding.style.top = e.clientY + 'px'; }
  }, { passive: true });
</script>
</body>
</html>
