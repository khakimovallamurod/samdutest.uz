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

$fans = [];
$res = mysqli_query($link, "SELECT id, name FROM fan ORDER BY name ASC");
while ($f = mysqli_fetch_assoc($res)) {
    $fans[] = $f;
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
  <title>Ro'yxatdan o'tganlar | SamDU Olimpiada</title>
  <meta name="description" content="SamDU olimpiada ro'yxatdan o'tgan talabalar jadvali">
  <link rel="shortcut icon" href="img/favicon.png" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = { theme: { extend: {
      colors: { brand:'#22c55e', accent:'#06b6d4', flame:'#f97316' },
      fontFamily: { heading:['Poppins','sans-serif'], body:['Inter','sans-serif'] },
      boxShadow: {
        soft:'0 10px 30px rgba(15,23,42,0.08)',
        premium:'0 10px 40px rgba(0,0,0,0.08)'
      }
    }}};
  </script>
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
  <style>
    body { font-family: Inter, sans-serif; background: radial-gradient(circle at 8% 8%, #ecfeff 0%, #ffffff 50%, #f8fafc 100%); }
    .nav-link::after { content:''; position:absolute; left:0; bottom:-7px; width:100%; height:2px; background:linear-gradient(90deg,#22c55e,#06b6d4); transform:scaleX(0); transform-origin:left; transition:transform .25s ease; }
    .nav-link:hover::after, .nav-link.active::after { transform:scaleX(1); }
    .grid-bg { position:absolute; inset:0; pointer-events:none; opacity:.16; background-image:linear-gradient(to right,rgba(6,182,212,.18) 1px,transparent 1px),linear-gradient(to bottom,rgba(6,182,212,.18) 1px,transparent 1px); background-size:42px 42px; }
    .cursor-landing { position:fixed; width:34px; height:34px; border-radius:9999px; border:2px solid rgba(34,211,238,.95); box-shadow:0 0 0 4px rgba(34,211,238,.15); transform:translate(-50%,-50%); z-index:80; opacity:0; pointer-events:none; transition:opacity .2s ease,left .08s linear,top .08s linear; }
    .cursor-landing::after { content:''; position:absolute; left:50%; top:50%; width:8px; height:8px; border-radius:9999px; background:#22d3ee; box-shadow:0 0 10px rgba(34,211,238,.8); transform:translate(-50%,-50%); }

    .glass-card {
      background: linear-gradient(135deg, rgba(255,255,255,.92), rgba(255,255,255,.85));
      backdrop-filter: blur(12px);
      border: 1px solid rgba(255,255,255,.8);
      box-shadow: 0 10px 40px rgba(0,0,0,.08);
    }
    .header-sticky {
      position: sticky;
      top: 0;
      z-index: 15;
      backdrop-filter: blur(10px);
      background: linear-gradient(90deg, rgba(34,197,94,.10), rgba(6,182,212,.10), rgba(249,115,22,.10));
      border-bottom: 1px solid rgba(148,163,184,.3);
    }
    .control-input {
      border: 1px solid #cbd5e1;
      border-radius: 9999px;
      padding: .75rem 1rem .75rem 2.5rem;
      transition: all .2s ease;
      background: #fff;
    }
    .control-input:focus { outline: none; border-color: #06b6d4; box-shadow: 0 0 0 4px rgba(6,182,212,.15); }

    #table_wrap table.dataTable { width: 100% !important; border-collapse: separate !important; border-spacing: 0 8px !important; }
    #table_wrap table.dataTable thead th {
      position: sticky; top: 0;
      background: linear-gradient(90deg, rgba(34,197,94,.13), rgba(6,182,212,.13));
      color: #0f172a; text-transform: uppercase; font-size: 11px; letter-spacing: .08em;
      border-bottom: 0 !important;
    }
    #table_wrap table.dataTable tbody tr { transition: all .2s ease; cursor: pointer; }
    #table_wrap table.dataTable tbody tr td { background: #fff; border-top: 1px solid #e2e8f0 !important; border-bottom: 1px solid #e2e8f0 !important; }
    #table_wrap table.dataTable tbody tr td:first-child { border-left: 1px solid #e2e8f0; border-top-left-radius: 12px; border-bottom-left-radius: 12px; }
    #table_wrap table.dataTable tbody tr td:last-child { border-right: 1px solid #e2e8f0; border-top-right-radius: 12px; border-bottom-right-radius: 12px; }
    #table_wrap table.dataTable tbody tr:hover { transform: translateX(4px); }
    #table_wrap table.dataTable.stripe tbody tr.odd td { background: #f8fafc; }
    #table_wrap table.dataTable tbody tr.selected-row td { box-shadow: inset 3px 0 0 #06b6d4; }

    #table_wrap .dataTables_filter, #table_wrap .dataTables_length { display: none; }
    #table_wrap .dataTables_info { color: #64748b; font-size: 13px; }
    #table_wrap .dataTables_paginate .paginate_button { border-radius: 10px !important; border: 1px solid #dbeafe !important; background: #fff !important; }
    #table_wrap .dataTables_paginate .paginate_button.current { background: linear-gradient(90deg, #22c55e, #06b6d4) !important; color: #fff !important; border-color: transparent !important; }

    .stat-chip { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 10px 12px; }

    @media (max-width: 768px) {
      #table_wrap table.dataTable thead { display: none; }
      #table_wrap table.dataTable tbody tr,
      #table_wrap table.dataTable tbody td { display: block; width: 100% !important; }
      #table_wrap table.dataTable tbody tr { margin-bottom: 12px; transform: none !important; }
      #table_wrap table.dataTable tbody td {
        border: 1px solid #e2e8f0 !important; border-radius: 10px; margin-bottom: 6px;
        padding: 10px 12px 10px 42% !important; position: relative;
      }
      #table_wrap table.dataTable tbody td::before {
        content: attr(data-label); position: absolute; left: 12px; top: 10px;
        font-weight: 700; color: #475569; font-size: 11px; text-transform: uppercase;
      }
    }
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
        <a class="nav-link active relative text-sm font-semibold text-slate-700" href="reg-users.php">Ro'yxatdan o'tganlar</a>
        <a class="nav-link relative text-sm font-semibold text-slate-700" href="results.php">Natijalar</a>
      </nav>
      <a href="test/" class="hidden rounded-xl bg-gradient-to-r from-brand to-accent px-5 py-2.5 text-sm font-semibold text-white md:inline-flex">Testlarni boshlash</a>
    </div>
    <div id="mobileMenu" class="max-h-0 overflow-hidden transition-all duration-300 md:hidden"><nav class="mt-2 grid gap-2 border-t border-slate-200 pt-3 pb-1"><a class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100" href="index.php">Bosh sahifa</a><a class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100" href="reg-users.php">Ro'yxatdan o'tganlar</a><a class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100" href="results.php">Natijalar</a></nav></div>
  </div>
</header>

<main class="flex-1 pt-32 pb-14">
  <section class="relative mx-auto max-w-7xl px-3 sm:px-4 md:px-6">
    <div class="absolute inset-0 -z-10"><div class="absolute -left-20 top-0 h-56 w-56 rounded-full bg-brand/20 blur-3xl"></div><div class="absolute right-0 top-14 h-52 w-52 rounded-full bg-accent/20 blur-3xl"></div><div class="grid-bg" id="gridBg"></div></div>

    <div class="glass-card rounded-3xl p-3 sm:p-5 md:p-6">
      <div class="header-sticky rounded-2xl px-3 py-4 sm:px-5">
        <h1 class="font-heading text-2xl font-extrabold text-slate-900 sm:text-3xl">Ro'yxatdan o'tgan talabalar</h1>
        <p class="mt-1 text-sm text-slate-600">Premium interaktiv jadval: qidiruv, filter, export, pagination.</p>
        <div class="mt-4 grid gap-3 md:grid-cols-12 md:items-center">
          <div class="relative md:col-span-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z"/></svg>
            <input id="searchInput" type="text" class="control-input w-full text-sm" placeholder="FISH yoki fan bo'yicha qidirish">
          </div>
          <div class="md:col-span-3">
            <select id="fanlar" class="w-full rounded-full border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-accent focus:ring-2 focus:ring-accent/20">
              <option value="0">Barcha fanlar</option>
              <?php foreach ($fans as $f): ?>
              <option value="<?= (int)$f['id'] ?>"><?= safe_text($f['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="md:col-span-5 flex flex-wrap items-center gap-2 md:justify-end">
            <button id="refreshBtn" class="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:border-accent hover:text-accent">Yangilash</button>
            <button id="exportBtn" class="rounded-full bg-gradient-to-r from-brand to-accent px-4 py-2 text-sm font-semibold text-white shadow-soft">Export CSV</button>
          </div>
        </div>
        <div class="mt-4 grid gap-2 sm:grid-cols-3">
          <div class="stat-chip"><p class="text-xs text-slate-500">Jami</p><p id="statTotal" class="text-xl font-extrabold text-slate-900">0</p></div>
          <div class="stat-chip"><p class="text-xs text-slate-500">Ko'rinayotgan</p><p id="statVisible" class="text-xl font-extrabold text-accent">0</p></div>
          <div class="stat-chip"><p class="text-xs text-slate-500">Tanlangan fan</p><p id="statFan" class="text-xl font-extrabold text-brand">Barchasi</p></div>
        </div>
      </div>

      <div class="mt-5 rounded-2xl border border-slate-200 bg-white p-1 sm:p-3">
        <div id="table_wrap" class="min-h-[160px] overflow-x-auto">
          <div class="animate-pulse space-y-2 p-4">
            <div class="h-10 rounded-xl bg-slate-100"></div>
            <div class="h-10 rounded-xl bg-slate-100"></div>
            <div class="h-10 rounded-xl bg-slate-100"></div>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>

<footer class="mt-10 border-t border-transparent bg-gradient-to-r from-brand/20 via-accent/20 to-flame/20">
  <div class="mx-auto max-w-7xl px-4 py-10 md:px-6">
    <div class="grid gap-8 md:grid-cols-3">
      <div><h3 class="font-heading text-lg font-bold">SamDU Olimpiada</h3><p class="mt-2 text-sm text-slate-600">Samarqand davlat universiteti iqtidorli talabalar bilan ishlash bo'limi platformasi.</p></div>
      <div><h3 class="font-heading text-lg font-bold">Aloqa</h3><p class="mt-2 text-sm text-slate-600">Web: olimpiada.samdu.uz</p><p class="text-sm text-slate-600">Natijalar: <a class="font-semibold text-accent" href="results.php">Ko'rish</a></p></div>
      <div><h3 class="font-heading text-lg font-bold">Ijtimoiy tarmoqlar</h3><div class="mt-3 flex items-center gap-2"><a href="https://t.me/samdu_iqtidorli_talaba" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:border-accent hover:text-accent">Telegram</a><a href="http://samsim.uz" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:border-brand hover:text-brand">SamSIM</a></div></div>
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

  function updateStats() {
    const dt = window.regUsersTable;
    if (!dt) return;
    document.getElementById('statTotal').textContent = dt.rows().count();
    document.getElementById('statVisible').textContent = dt.rows({ search: 'applied' }).count();
    const select = document.getElementById('fanlar');
    document.getElementById('statFan').textContent = select.options[select.selectedIndex].text;
  }

  function bindExternalControls() {
    const dt = window.regUsersTable;
    if (!dt) return;

    const searchInput = document.getElementById('searchInput');
    searchInput.oninput = function() { dt.search(this.value).draw(); updateStats(); };

    document.getElementById('exportBtn').onclick = function() {
      const rows = dt.rows({ search: 'applied' }).data().toArray();
      let csv = 'T/r,FISH,Fan nomi,Telefon,Status\n';
      rows.forEach(r => {
        const clean = (txt) => String(txt).replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim().replace(/,/g, ' ');
        csv += `${clean(r[0])},${clean(r[1])},${clean(r[2])},${clean(r[3])},${clean(r[4])}\n`;
      });
      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
      const link = document.createElement('a');
      link.href = URL.createObjectURL(blob);
      link.download = 'samdu-royxat.csv';
      link.click();
    };

    $('#table_id tbody').off('click').on('click', 'tr', function() {
      $(this).toggleClass('selected-row');
    });

    dt.on('draw', updateStats);
    updateStats();
  }

  function loadTable() {
    const fan_id = document.getElementById('fanlar').value;
    $.ajax({
      url: 'get-table.php',
      type: 'post',
      data: { fan_id: fan_id },
      success: function(data) {
        $('#table_wrap').html(data);
        setTimeout(bindExternalControls, 40);
      }
    });
  }

  document.getElementById('fanlar').addEventListener('change', loadTable);
  document.getElementById('refreshBtn').addEventListener('click', loadTable);
  loadTable();

  const gridBg = document.getElementById('gridBg');
  const cursorLanding = document.getElementById('cursorLanding');
  window.addEventListener('mousemove', (e) => {
    if (gridBg) { const gx = (e.clientX / window.innerWidth - 0.5) * 10; const gy = (e.clientY / window.innerHeight - 0.5) * 10; gridBg.style.transform = `translate3d(${gx}px, ${gy}px, 0)`; }
    if (cursorLanding && window.matchMedia('(pointer:fine)').matches) { cursorLanding.style.opacity = '1'; cursorLanding.style.left = e.clientX + 'px'; cursorLanding.style.top = e.clientY + 'px'; }
  }, { passive: true });
</script>
</body>
</html>
