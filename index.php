<?php
session_start();
include_once 'config.php';

function table_exists($link, $table) {
    $table = mysqli_real_escape_string($link, $table);
    $res = mysqli_query($link, "SHOW TABLES LIKE '{$table}'");
    return $res && mysqli_num_rows($res) > 0;
}

function scalar_count($link, $sql) {
    $res = mysqli_query($link, $sql);
    if ($res && ($row = mysqli_fetch_row($res))) {
        return (int) ($row[0] ?? 0);
    }
    return 0;
}

$studentCount = 0;
$teacherCount = 0;
$subjectCount = 0;
$testCount = 0;

if (table_exists($link, 'users')) {
    $studentCount = scalar_count($link, "SELECT COUNT(*) FROM users WHERE role='student'");
    $teacherCount = scalar_count($link, "SELECT COUNT(*) FROM users WHERE role='teacher'");
}
if ($studentCount === 0 && table_exists($link, 'students')) {
    $studentCount = scalar_count($link, "SELECT COUNT(*) FROM students");
}
if ($teacherCount === 0 && table_exists($link, 'teacher_profiles')) {
    $teacherCount = scalar_count($link, "SELECT COUNT(*) FROM teacher_profiles");
}
if (table_exists($link, 'subjects')) {
    $subjectCount = scalar_count($link, "SELECT COUNT(*) FROM subjects");
} elseif (table_exists($link, 'fan')) {
    $subjectCount = scalar_count($link, "SELECT COUNT(*) FROM fan");
}
if (table_exists($link, 'tests')) {
    $testCount = scalar_count($link, "SELECT COUNT(*) FROM tests");
} elseif (table_exists($link, 'testlar')) {
    $testCount = scalar_count($link, "SELECT COUNT(*) FROM testlar");
}
?>
<!doctype html>
<html lang="uz">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SamDU Test Platformasi</title>
  <meta name="description" content="Samarqand davlat universiteti test platformasi. Ro'yxatdan o'tish, test ishlash, natijalarni kuzatish.">
  <meta name="keywords" content="SamDU, test, talaba, reyting, sertifikat">
  <meta property="og:title" content="SamDU Test Platformasi">
  <meta property="og:description" content="SamDU onlayn test platformasi">
  <meta property="og:type" content="website">
  <meta property="og:url" content="http://olimpiada.samdu.uz/">
  <meta name="theme-color" content="#22c55e">
  <link rel="shortcut icon" href="img/favicon.png" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: '#22c55e',
            accent: '#0ea5e9',
            flame: '#f97316'
          },
          fontFamily: {
            heading: ['Poppins', 'sans-serif'],
            body: ['Inter', 'sans-serif']
          },
          boxShadow: {
            soft: '0 10px 30px rgba(15, 23, 42, 0.08)',
            glow: '0 0 0 1px rgba(14, 165, 233, 0.28), 0 12px 28px rgba(14, 165, 233, 0.14)'
          }
        }
      }
    };
  </script>
  <link rel="stylesheet" href="https://unpkg.com/aos@2.3.4/dist/aos.css">
  <style>
    html { scroll-behavior: smooth; }
    body {
      font-family: Inter, sans-serif;
      background: radial-gradient(circle at 10% 10%, #f0fdf4 0%, #ffffff 48%, #f8fafc 100%);
    }
    .nav-link::after {
      content: '';
      position: absolute;
      left: 0;
      bottom: -7px;
      width: 100%;
      height: 2px;
      background: linear-gradient(90deg, #22c55e, #0ea5e9);
      transform: scaleX(0);
      transform-origin: left;
      transition: transform .25s ease;
    }
    .nav-link:hover::after,
    .nav-link.active::after { transform: scaleX(1); }
    .hero-wrap { perspective: 1200px; }
    .hero-tilt { transform-style: preserve-3d; transition: transform .2s ease-out; }
    .depth { transform: translateZ(28px); }
    .floating { animation: floating 6s ease-in-out infinite; }
    @keyframes floating {
      0%,100% { transform: translateY(0px); }
      50% { transform: translateY(-12px); }
    }
    .particle { position: absolute; border-radius: 9999px; opacity: .32; pointer-events: none; }
    .glow-border {
      border: 1px solid transparent;
      background: linear-gradient(#ffffff, #ffffff) padding-box,
        linear-gradient(125deg, rgba(34,197,94,.7), rgba(14,165,233,.65), rgba(249,115,22,.62)) border-box;
    }
    .grid-bg {
      position: absolute;
      inset: 0;
      pointer-events: none;
      opacity: .28;
      background-image:
        linear-gradient(to right, rgba(14,165,233,.17) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(14,165,233,.17) 1px, transparent 1px);
      background-size: 42px 42px;
      transform: translate3d(0,0,0);
      will-change: transform;
    }
    .interactive-hover {
      transition: transform .2s ease-out, box-shadow .2s ease-out, border-color .2s ease-out;
      will-change: transform;
    }
    .interactive-hover:hover {
      transform: translateY(-5px) scale(1.02);
      box-shadow: 0 0 0 1px rgba(14,165,233,.25), 0 14px 28px rgba(14,165,233,.14);
    }
    .cursor-landing {
      position: fixed;
      width: 38px;
      height: 38px;
      border-radius: 9999px;
      border: 2px solid rgba(34, 211, 238, .95);
      box-shadow: 0 0 0 4px rgba(34, 211, 238, .15);
      transform: translate(-50%, -50%);
      pointer-events: none;
      opacity: 0;
      z-index: 80;
      transition: opacity .2s ease, left .08s linear, top .08s linear;
      will-change: left, top;
    }
    .cursor-landing::after {
      content: '';
      position: absolute;
      left: 50%;
      top: 50%;
      width: 8px;
      height: 8px;
      border-radius: 9999px;
      background: #22d3ee;
      box-shadow: 0 0 10px rgba(34, 211, 238, .8);
      transform: translate(-50%, -50%);
    }
  </style>
</head>
<body class="text-slate-800 font-body antialiased">
  <header id="top" class="fixed top-0 z-50 w-full transition-all duration-300">
    <div id="navShell" class="mx-auto mt-3 max-w-7xl rounded-2xl border border-white/70 bg-white/65 px-4 py-2.5 shadow-soft backdrop-blur-xl md:px-6">
      <div class="flex items-center justify-between">
        <a href="#" class="flex items-center gap-3">
          <img src="img/logo-sam.png" alt="SamDU Logo" class="h-11 w-auto">
          <div class="hidden sm:block">
            <p class="font-heading text-sm font-bold leading-4">SAMDU TEST TIZIM</p>
          </div>
        </a>
        <button id="menuBtn" type="button" class="inline-flex items-center rounded-xl border border-slate-200 p-2 text-slate-700 md:hidden" aria-label="Menu ochish" aria-expanded="false" aria-controls="mobileMenu">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 12h16M4 18h16" /></svg>
        </button>
        <nav class="hidden items-center gap-7 md:flex" aria-label="Asosiy menu">
          <a class="nav-link relative text-sm font-semibold text-slate-700 transition hover:text-brand active" href="#">Bosh sahifa</a>
          <a class="nav-link relative text-sm font-semibold text-slate-700 transition hover:text-brand" href="#features">Imkoniyatlar</a>
          <a class="nav-link relative text-sm font-semibold text-slate-700 transition hover:text-brand" href="#categories">Fanlar</a>
          <a class="nav-link relative text-sm font-semibold text-slate-700 transition hover:text-brand" href="#faq">FAQ</a>
          <a class="nav-link relative text-sm font-semibold text-slate-700 transition hover:text-brand" href="results.php">Natijalar</a>
        </nav>
        <a href="test/" class="interactive-hover hidden rounded-xl bg-gradient-to-r from-brand to-accent px-5 py-2.5 text-sm font-semibold text-white shadow-soft transition hover:scale-[1.02] md:inline-flex">Testlarni boshlash</a>
      </div>
      <div id="mobileMenu" class="max-h-0 overflow-hidden transition-all duration-300 md:hidden" aria-hidden="true">
        <nav class="mt-2 grid gap-2 border-t border-slate-200 pt-3 pb-1" aria-label="Mobil menu">
          <a class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100" href="#">Bosh sahifa</a>
          <a class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100" href="#features">Imkoniyatlar</a>
          <a class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100" href="#categories">Fanlar</a>
          <a class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100" href="#faq">FAQ</a>
          <a class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100" href="results.php">Natijalar</a>
          <a href="test/" class="mt-1 rounded-lg bg-gradient-to-r from-brand to-accent px-3 py-2 text-center text-sm font-semibold text-white">Testlarni boshlash</a>
        </nav>
      </div>
    </div>
  </header>
  <main>
    <section class="relative isolate overflow-hidden pt-36 pb-20 sm:pt-40 sm:pb-24">
      <div class="absolute inset-0 -z-10">
        <div class="absolute -left-20 top-12 h-64 w-64 rounded-full bg-brand/20 blur-3xl"></div>
        <div class="absolute right-6 top-20 h-56 w-56 rounded-full bg-accent/20 blur-3xl"></div>
        <div class="absolute bottom-10 left-1/2 h-56 w-56 -translate-x-1/2 rounded-full bg-flame/20 blur-3xl"></div>
        <div id="particles" class="absolute inset-0"></div>
        <div id="gridBg" class="grid-bg"></div>
      </div>
      <div class="mx-auto grid max-w-7xl gap-10 px-4 md:grid-cols-12 md:items-center md:px-6 hero-wrap">
        <div class="md:col-span-7" data-aos="fade-up">
          <p class="inline-flex items-center gap-2 rounded-full border border-brand/30 bg-brand/10 px-3 py-1 text-xs font-semibold text-brand">IT Markaz</p>
          <h1 class="mt-5 font-heading text-3xl font-extrabold leading-tight text-slate-900 sm:text-5xl">Onlayn fan testlari uchun test platformasi</h1>
          <p class="mt-5 max-w-2xl text-base leading-7 text-slate-600 sm:text-lg">Talabalar uchun zamonaviy test muhiti, real-time natijalar, reyting tizimi va sertifikatlash tizimi.</p>
          <div class="mt-7 flex flex-wrap items-center gap-3">
            <a href="test/" class="interactive-hover rounded-xl bg-gradient-to-r from-brand to-accent px-6 py-3 text-sm font-semibold text-white shadow-soft transition hover:scale-[1.02]">Testlarni boshlash</a>
            <a href="#categories" class="rounded-xl border border-slate-300 bg-white px-6 py-3 text-sm font-semibold text-slate-700 transition hover:border-accent hover:text-accent">Yo'nalishlar</a>
          </div>
        </div>
        <div class="md:col-span-5" data-aos="fade-left">
          <div id="tiltPanel" class="hero-tilt rounded-3xl border border-white/70 bg-white/80 p-5 shadow-soft backdrop-blur-xl">
            <div class="grid gap-4 sm:grid-cols-2">
              <article class="floating depth rounded-2xl bg-gradient-to-br from-brand/10 to-brand/5 p-4 ring-1 ring-brand/20">
                <p class="text-xs text-slate-500">Talabalar</p>
                <p class="mt-1 text-2xl font-extrabold text-slate-900" data-counter="<?= $studentCount ?>">0</p>
                <p class="text-xs font-medium text-brand">Ro'yxatdan o'tganlar</p>
              </article>
              <article class="floating depth rounded-2xl bg-gradient-to-br from-accent/10 to-accent/5 p-4 ring-1 ring-accent/20" style="animation-delay: .5s;">
                <p class="text-xs text-slate-500">O'qituvchilar</p>
                <p class="mt-1 text-2xl font-extrabold text-slate-900" data-counter="<?= $teacherCount ?>">0</p>
                <p class="text-xs font-medium text-accent">Faol o'qituvchilar</p>
              </article>
              <article class="floating depth rounded-2xl bg-gradient-to-br from-flame/10 to-flame/5 p-4 ring-1 ring-flame/20" style="animation-delay: .8s;">
                <p class="text-xs text-slate-500">Fanlar</p>
                <p class="mt-1 text-2xl font-extrabold text-slate-900" data-counter="<?= $subjectCount ?>">0</p>
                <p class="text-xs font-medium text-flame">Mavjud fanlar</p>
              </article>
              <article class="floating depth rounded-2xl bg-white p-4 ring-1 ring-slate-200" style="animation-delay: 1.1s;">
                <p class="text-xs text-slate-500">Testlar soni</p>
                <p class="mt-1 text-2xl font-extrabold text-slate-900" data-counter="<?= $testCount ?>">0</p>
                <p class="text-xs font-medium text-slate-500">Jami testlar</p>
              </article>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section id="features" class="mx-auto max-w-7xl px-4 py-10 md:px-6 md:py-14">
      <div class="mb-8 text-center" data-aos="fade-up">
        <h2 class="font-heading text-3xl font-extrabold text-slate-900">Platforma imkoniyatlari</h2>
        <p class="mt-2 text-slate-600">Bir xil dizayn tizimi asosida tezkor, aniq va qulay tajriba</p>
      </div>
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <article class="glow-border interactive-hover rounded-2xl p-5 transition hover:-translate-y-1 hover:shadow-glow" data-aos="fade-up">
          <p class="text-2xl">📝</p><h3 class="mt-3 font-heading text-lg font-bold">Onlayn test tizimi</h3><p class="mt-2 text-sm text-slate-600">Adolatli va vaqt bo'yicha nazorat qilingan test jarayoni.</p>
        </article>
        <article class="glow-border interactive-hover rounded-2xl p-5 transition hover:-translate-y-1 hover:shadow-glow" data-aos="fade-up" data-aos-delay="80">
          <p class="text-2xl">🏆</p><h3 class="mt-3 font-heading text-lg font-bold">Reyting tizimi</h3><p class="mt-2 text-sm text-slate-600">To'g'ri javob va sarflangan vaqt asosida avtomatik baholash.</p>
        </article>
        <article class="glow-border interactive-hover rounded-2xl p-5 transition hover:-translate-y-1 hover:shadow-glow" data-aos="fade-up" data-aos-delay="160">
          <p class="text-2xl">📜</p><h3 class="mt-3 font-heading text-lg font-bold">Sertifikatlash</h3><p class="mt-2 text-sm text-slate-600">Yakuniy natijaga ko'ra elektron sertifikatlar.</p>
        </article>
        <article class="glow-border interactive-hover rounded-2xl p-5 transition hover:-translate-y-1 hover:shadow-glow" data-aos="fade-up" data-aos-delay="240">
          <p class="text-2xl">⚡</p><h3 class="mt-3 font-heading text-lg font-bold">Real-time natija</h3><p class="mt-2 text-sm text-slate-600">Test yakunida darhol statistik natija va tahlil.</p>
        </article>
      </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-6 md:px-6 md:py-10">
      <div class="grid gap-4 rounded-3xl bg-white/90 p-5 shadow-soft ring-1 ring-slate-100 sm:grid-cols-3" data-aos="fade-up">
        <div><p class="text-sm text-slate-500">Talabalar soni</p><p class="text-3xl font-extrabold" data-counter="<?= $studentCount ?>">0</p></div>
        <div><p class="text-sm text-slate-500">O'qituvchilar soni</p><p class="text-3xl font-extrabold" data-counter="<?= $teacherCount ?>">0</p></div>
        <div><p class="text-sm text-slate-500">Fanlar soni</p><p class="text-3xl font-extrabold" data-counter="<?= $subjectCount ?>">0</p></div>
      </div>
    </section>

    <section id="categories" class="mx-auto max-w-7xl px-4 py-10 md:px-6 md:py-14">
      <div class="mb-8 flex items-end justify-between gap-3" data-aos="fade-up">
        <div>
          <h2 class="font-heading text-3xl font-extrabold text-slate-900">Test fan yo'nalishlari</h2>
          <p class="mt-2 text-slate-600">Har bir fan uchun alohida test bloklari</p>
        </div>
        <a href="test/" class="text-sm font-semibold text-accent hover:text-brand">Barchasini ko'rish</a>
      </div>
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <?php
        $cats = [
          ['name' => 'Matematika', 'icon' => '∑', 'meta' => 'Formula va mantiqiy masalalar'],
          ['name' => 'Informatika', 'icon' => '</>', 'meta' => 'Algoritm va dasturlash testlari'],
          ['name' => 'Fizika', 'icon' => '⚛', 'meta' => 'Nazariya va amaliy yechimlar'],
          ['name' => 'Tarix', 'icon' => '🏛', 'meta' => 'Davrlar va tarixiy tahlil'],
          ['name' => 'Ingliz tili', 'icon' => 'EN', 'meta' => 'Grammar va comprehension'],
          ['name' => 'Biologiya', 'icon' => '🧬', 'meta' => 'Tirik tizimlar bo‘yicha blok'],
          ['name' => 'Iqtisodiyot', 'icon' => '₿', 'meta' => 'Iqtisodiy nazariya va amaliyot'],
          ['name' => 'Ona tili', 'icon' => 'Aa', 'meta' => 'Til qoidalari va adabiyot'],
        ];
        foreach ($cats as $i => $cat):
        ?>
        <article class="group interactive-hover rounded-2xl bg-gradient-to-br from-white to-slate-50 p-5 ring-1 ring-slate-200 transition duration-300 hover:-translate-y-1 hover:shadow-glow" data-aos="zoom-in" data-aos-delay="<?= $i * 50 ?>">
          <div class="mb-3 inline-flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-brand to-accent text-base font-extrabold text-white shadow-soft"><?= htmlspecialchars($cat['icon'], ENT_QUOTES, 'UTF-8') ?></div>
          <h3 class="font-heading text-lg font-bold"><?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></h3>
          <p class="mt-2 text-sm text-slate-600"><?= htmlspecialchars($cat['meta'], ENT_QUOTES, 'UTF-8') ?></p>
        </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section id="faq" class="mx-auto max-w-7xl px-4 py-10 md:px-6 md:py-14">
      <div class="mb-8 text-center" data-aos="fade-up">
        <h2 class="font-heading text-3xl font-extrabold text-slate-900">Ko‘p So‘raladigan Savollar</h2>
        <p class="mt-2 text-slate-600">Jarayon bo‘yicha eng muhim savollarga qisqa javoblar</p>
      </div>
      <div class="grid gap-3 md:grid-cols-2">
        <details class="rounded-2xl border border-slate-200 bg-white p-4 shadow-soft"><summary class="cursor-pointer font-semibold text-slate-800">Testni necha marta ishlash mumkin?</summary><p class="mt-3 text-sm leading-7 text-slate-600">Har bir fan bo‘yicha odatda bitta urinish beriladi.</p></details>
        <details class="rounded-2xl border border-slate-200 bg-white p-4 shadow-soft"><summary class="cursor-pointer font-semibold text-slate-800">Vaqt tugasa nima bo‘ladi?</summary><p class="mt-3 text-sm leading-7 text-slate-600">Vaqt tugashi bilan tizim javoblarni avtomatik yakunlaydi.</p></details>
        <details class="rounded-2xl border border-slate-200 bg-white p-4 shadow-soft"><summary class="cursor-pointer font-semibold text-slate-800">Natijalarni qayerdan ko‘raman?</summary><p class="mt-3 text-sm leading-7 text-slate-600">Asosiy menyudagi “Natijalar” bo‘limidan fan tanlab ko‘rishingiz mumkin.</p></details>
        <details class="rounded-2xl border border-slate-200 bg-white p-4 shadow-soft"><summary class="cursor-pointer font-semibold text-slate-800">Sertifikat qanday olinadi?</summary><p class="mt-3 text-sm leading-7 text-slate-600">Yakuniy baholashdan so‘ng sertifikatlar elektron shaklda taqdim etiladi.</p></details>
      </div>
    </section>
  </main>

  <footer class="mt-10 border-t border-transparent bg-gradient-to-r from-brand/20 via-accent/20 to-flame/20">
    <div class="mx-auto max-w-7xl px-4 py-10 md:px-6">
      <div class="grid gap-8 md:grid-cols-3">
        <div>
          <h3 class="font-heading text-lg font-bold">IT Markaz</h3>
          <p class="mt-2 text-sm text-slate-600">Sharof Rashidov nomidagi Samarqand davlat universiteti qoshidagi IT Markaz.</p>
          <p class="mt-2 text-sm text-slate-600">Manzil: Sun'iy intellekt va raqamli texnalogiyalari fakulteti binosi, 1-qavat.</p>
        </div>
        <div>
          <h3 class="font-heading text-lg font-bold">Aloqa</h3>
          <p class="mt-2 text-sm text-slate-600">Telefon: <a class="font-semibold text-accent" href="tel:+998937286867">+998 93 728 68 67</a></p>
          <p class="text-sm text-slate-600">Email: <a class="font-semibold text-accent" href="mailto:saidqulov98@bk.ru">saidqulov98@bk.ru</a></p>
          <p class="text-sm text-slate-600">Telegram: <a class="font-semibold text-accent" href="https://t.me/itcenter_samdu" target="_blank" rel="noopener">@itcenter_samdu</a></p>
        </div>
        <div>
          <h3 class="font-heading text-lg font-bold">Foydali havolalar</h3>
          <div class="mt-3 flex flex-col items-start gap-2">
            <a href="https://it-markaz.samdu.uz/" target="_blank" rel="noopener" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:border-accent hover:text-accent">IT Markaz rasmiy sayti</a>
            <a href="http://itmarkaz-system.sampc.uz/" target="_blank" rel="noopener" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:border-brand hover:text-brand">IT Markaz System platformasi</a>
          </div>
        </div>
      </div>
      <div class="mt-8 border-t border-slate-200 pt-4 text-sm text-slate-500">© <?= date('Y') ?> IT Markaz. Barcha huquqlar himoyalangan.</div>
    </div>
</footer>
  <div id="cursorLanding" class="cursor-landing"></div>

  <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
  <script>
    AOS.init({ duration: 700, once: true, offset: 40 });

    const menuBtn = document.getElementById('menuBtn');
    const mobileMenu = document.getElementById('mobileMenu');
    menuBtn?.addEventListener('click', () => {
      const open = mobileMenu.style.maxHeight && mobileMenu.style.maxHeight !== '0px';
      mobileMenu.style.maxHeight = open ? '0px' : mobileMenu.scrollHeight + 'px';
      menuBtn.setAttribute('aria-expanded', String(!open));
      mobileMenu.setAttribute('aria-hidden', String(open));
    });

    const navShell = document.getElementById('navShell');
    window.addEventListener('scroll', () => {
      const y = window.scrollY;
      navShell.style.background = y > 20 ? 'rgba(255,255,255,.92)' : 'rgba(255,255,255,.65)';
      navShell.style.borderColor = y > 20 ? 'rgba(226,232,240,1)' : 'rgba(255,255,255,.7)';
    }, { passive: true });

    const sections = [...document.querySelectorAll('main section[id]')];
    const navLinks = [...document.querySelectorAll('.nav-link')];
    const highlight = () => {
      const y = window.scrollY + 140;
      let activeId = 'top';
      sections.forEach(sec => {
        if (y >= sec.offsetTop) activeId = sec.id;
      });
      navLinks.forEach(a => {
        const href = a.getAttribute('href');
        a.classList.toggle('active', href === '#' + activeId || (activeId === 'top' && href === '#'));
      });
    };
    window.addEventListener('scroll', highlight, { passive: true });
    highlight();

    const tiltPanel = document.getElementById('tiltPanel');
    const hero = document.querySelector('.hero-wrap');
    hero?.addEventListener('mousemove', (e) => {
      const rect = hero.getBoundingClientRect();
      const x = (e.clientX - rect.left) / rect.width - 0.5;
      const y = (e.clientY - rect.top) / rect.height - 0.5;
      tiltPanel.style.transform = `rotateY(${x * 10}deg) rotateX(${y * -8}deg)`;
    });
    hero?.addEventListener('mouseleave', () => {
      tiltPanel.style.transform = 'rotateY(0deg) rotateX(0deg)';
    });

    const counters = document.querySelectorAll('[data-counter]');
    const animateCounter = (el) => {
      const target = Number(el.dataset.counter || 0);
      let start = 0;
      const inc = Math.max(1, Math.ceil(target / 60));
      const timer = setInterval(() => {
        start += inc;
        if (start >= target) {
          el.textContent = target.toLocaleString('en-US');
          clearInterval(timer);
        } else {
          el.textContent = start.toLocaleString('en-US');
        }
      }, 18);
    };
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          animateCounter(entry.target);
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: .5 });
    counters.forEach(c => observer.observe(c));

    const particlesRoot = document.getElementById('particles');
    for (let i = 0; i < 34; i++) {
      const dot = document.createElement('span');
      dot.className = 'particle';
      const size = Math.random() * 5 + 3;
      dot.style.width = size + 'px';
      dot.style.height = size + 'px';
      dot.style.left = Math.random() * 100 + '%';
      dot.style.top = Math.random() * 100 + '%';
      dot.style.background = i % 3 === 0 ? '#22c55e' : (i % 3 === 1 ? '#0ea5e9' : '#f97316');
      dot.style.animation = `floating ${Math.random() * 5 + 4}s ease-in-out ${Math.random() * 2}s infinite`;
      particlesRoot.appendChild(dot);
    }

    const gridBg = document.getElementById('gridBg');
    const cursorLanding = document.getElementById('cursorLanding');
    const particleNodes = [];
    particlesRoot.querySelectorAll('.particle').forEach(p => particleNodes.push({ el: p }));
    let mx = window.innerWidth / 2;
    let my = window.innerHeight / 2;

    window.addEventListener('mousemove', (e) => {
      mx = e.clientX;
      my = e.clientY;
      if (gridBg) {
        const gx = (mx / window.innerWidth - 0.5) * 10;
        const gy = (my / window.innerHeight - 0.5) * 10;
        gridBg.style.transform = `translate3d(${gx}px, ${gy}px, 0)`;
      }
      if (cursorLanding && window.matchMedia('(pointer:fine)').matches) {
        cursorLanding.style.opacity = '1';
        cursorLanding.style.left = `${mx}px`;
        cursorLanding.style.top = `${my}px`;
      }
      particleNodes.forEach((n, i) => {
        const r = n.el.getBoundingClientRect();
        const px = r.left + r.width / 2;
        const py = r.top + r.height / 2;
        const dx = px - mx;
        const dy = py - my;
        const dist = Math.hypot(dx, dy);
        if (dist < 110) {
          const force = (110 - dist) / 110;
          n.el.style.transform = `translate(${(dx / (dist || 1)) * force * 14}px, ${(dy / (dist || 1)) * force * 14}px)`;
        } else if (i % 5 === 0) {
          n.el.style.transform = 'translate(0,0)';
        }
      });
    }, { passive: true });
  </script>
</body>
</html>
