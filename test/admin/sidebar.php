<?php
/**
 * Admin Sidebar Include
 * Barcha admin sahifalarida: include_once 'sidebar.php';
 * $current_page o'zgaruvchisi fayl nomi (basename) bo'lishi kerak
 *
 * Misol: $current_page = 'index'; include_once 'sidebar.php';
 */
if (!defined('SIDEBAR_LOADED')) define('SIDEBAR_LOADED', true);

$current_page = $current_page ?? basename($_SERVER['PHP_SELF'], '.php');

$nav_items = [
    ['file' => 'index',        'icon' => 'fa-users',         'label' => 'Talabalar'],
    ['file' => 'get-results',  'icon' => 'fa-chart-bar',     'label' => 'Natijalar'],
    ['file' => 'natija',       'icon' => 'fa-table',         'label' => 'Natija jadval'],
    ['file' => 'reyting-quiz', 'icon' => 'fa-trophy',        'label' => 'Reyting'],
    ['file' => 'test_add',     'icon' => 'fa-file-alt',      'label' => 'Testlar'],
    ['file' => 'question_add', 'icon' => 'fa-question-circle','label' => 'Savollar'],
    ['file' => 'quiz',         'icon' => 'fa-layer-group',   'label' => 'Test turlari'],
];
?>
<!-- ============ SIDEBAR CSS ============ -->
<style>
:root {
    --sb-w:        240px;
    --sb-w-col:    70px;
    --sb-bg:       rgba(10, 12, 30, 0.97);
    --sb-border:   rgba(255,255,255,0.07);
    --sb-primary:  #7c83fd;
    --sb-active:   rgba(124,131,253,0.15);
    --sb-hover:    rgba(255,255,255,0.05);
    --sb-text:     #9a9ab8;
    --sb-text-act: #fff;
}

* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', sans-serif; background: linear-gradient(135deg, #0f0c29, #302b63, #24243e); min-height: 100vh; color: #e0e0e0; }

/* ---- SIDEBAR ---- */
.sb-sidebar {
    position: fixed;
    top: 0; left: 0;
    width: var(--sb-w);
    height: 100vh;
    background: var(--sb-bg);
    border-right: 1px solid var(--sb-border);
    backdrop-filter: blur(20px);
    display: flex;
    flex-direction: column;
    z-index: 200;
    transition: width 0.25s cubic-bezier(.4,0,.2,1);
    overflow: hidden;
}
.sb-sidebar.collapsed { width: var(--sb-w-col); }

/* Logo */
.sb-logo {
    display: flex; align-items: center; gap: 12px;
    padding: 20px 16px 16px;
    border-bottom: 1px solid var(--sb-border);
    text-decoration: none; flex-shrink: 0;
    min-height: 68px;
}
.sb-logo-icon {
    width: 38px; height: 38px; border-radius: 10px; flex-shrink: 0;
    background: linear-gradient(135deg, var(--sb-primary), #4a00e0);
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; color: #fff;
    box-shadow: 0 4px 14px rgba(124,131,253,0.35);
}
.sb-logo-text { line-height: 1.2; overflow: hidden; white-space: nowrap; transition: opacity 0.2s; }
.sb-logo-text strong { color: #fff; font-size: 0.92rem; display: block; }
.sb-logo-text span   { color: var(--sb-text); font-size: 0.72rem; }
.collapsed .sb-logo-text { opacity: 0; width: 0; pointer-events: none; }

/* Toggle btn */
.sb-toggle {
    position: absolute; top: 20px; right: -14px;
    width: 28px; height: 28px; border-radius: 50%;
    background: var(--sb-primary); border: 2px solid var(--sb-bg);
    color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center;
    font-size: 0.7rem; transition: all 0.2s; z-index: 10;
    box-shadow: 0 2px 8px rgba(124,131,253,0.4);
}
.sb-toggle:hover { transform: scale(1.1); }

/* Nav */
.sb-nav { flex: 1; overflow-y: auto; overflow-x: hidden; padding: 12px 8px; }
.sb-nav::-webkit-scrollbar { width: 4px; }
.sb-nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }

.sb-section-label {
    font-size: 0.65rem; letter-spacing: 1.5px; text-transform: uppercase;
    color: #444; padding: 10px 12px 4px; white-space: nowrap; overflow: hidden;
    transition: opacity 0.2s;
}
.collapsed .sb-section-label { opacity: 0; }

.sb-item {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 12px; border-radius: 10px;
    text-decoration: none; color: var(--sb-text);
    font-size: 0.875rem; font-weight: 500;
    transition: all 0.15s; white-space: nowrap; margin-bottom: 2px;
    position: relative;
}
.sb-item i { font-size: 1rem; flex-shrink: 0; width: 20px; text-align: center; transition: color 0.15s; }
.sb-item .sb-label { overflow: hidden; transition: opacity 0.2s, width 0.25s; }
.sb-item:hover { background: var(--sb-hover); color: var(--sb-text-act); }
.sb-item:hover i { color: var(--sb-primary); }
.sb-item.active {
    background: var(--sb-active);
    color: var(--sb-text-act);
    border-left: 3px solid var(--sb-primary);
}
.sb-item.active i { color: var(--sb-primary); }

.collapsed .sb-item .sb-label { opacity: 0; width: 0; pointer-events: none; }
.collapsed .sb-item { justify-content: center; padding: 10px; }

/* Tooltip (collapsed holat) */
.collapsed .sb-item::after {
    content: attr(data-tooltip);
    position: absolute;
    left: calc(100% + 10px);
    background: rgba(20,20,40,0.95);
    color: #fff; font-size: 0.78rem; font-weight: 500;
    padding: 6px 12px; border-radius: 8px; white-space: nowrap;
    pointer-events: none; opacity: 0; transition: opacity 0.15s;
    border: 1px solid rgba(255,255,255,0.08);
    z-index: 999;
}
.collapsed .sb-item:hover::after { opacity: 1; }

/* Footer */
.sb-footer {
    padding: 12px 8px;
    border-top: 1px solid var(--sb-border);
    flex-shrink: 0;
}
.sb-footer a {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 12px; border-radius: 10px;
    text-decoration: none; color: var(--sb-text); font-size: 0.875rem;
    transition: all 0.15s; white-space: nowrap;
}
.sb-footer a:hover { background: rgba(231,76,60,0.12); color: #ff7675; }
.sb-footer a i { flex-shrink: 0; width: 20px; text-align: center; }
.sb-footer a .sb-label { overflow: hidden; transition: opacity 0.2s; }
.collapsed .sb-footer a .sb-label { opacity: 0; width: 0; }
.collapsed .sb-footer a { justify-content: center; }

/* ---- MAIN CONTENT ---- */
.sb-main {
    margin-left: var(--sb-w);
    transition: margin-left 0.25s cubic-bezier(.4,0,.2,1);
    min-height: 100vh;
    display: flex; flex-direction: column;
}
.sb-sidebar.collapsed ~ .sb-main { margin-left: var(--sb-w-col); }

/* TOPBAR */
.sb-topbar {
    background: rgba(255,255,255,0.05);
    border-bottom: 1px solid rgba(255,255,255,0.07);
    backdrop-filter: blur(10px);
    padding: 12px 24px;
    display: flex; align-items: center; justify-content: space-between;
    gap: 12px; flex-shrink: 0;
}
.sb-topbar-title {
    font-size: 1.05rem; font-weight: 700; color: #fff;
}
.sb-topbar-title span { color: var(--sb-primary); }
.sb-topbar-right { display: flex; align-items: center; gap: 10px; }
.sb-user {
    display: flex; align-items: center; gap: 8px;
    background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.1);
    padding: 6px 14px; border-radius: 30px; font-size: 0.82rem; color: #ccc;
}
.sb-user i { color: var(--sb-primary); }

/* PAGE CONTENT */
.sb-content { flex: 1; padding: 28px 28px 40px; }

/* Cards */
.sb-card {
    background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px; padding: 24px; backdrop-filter: blur(10px); margin-bottom: 22px;
}
.sb-card-title {
    font-size: 1rem; font-weight: 600; color: #a0a8ff; margin-bottom: 16px;
    display: flex; align-items: center; gap: 8px;
}

@media (max-width: 768px) {
    .sb-sidebar { width: var(--sb-w-col); }
    .sb-logo-text, .sb-item .sb-label, .sb-footer a .sb-label, .sb-section-label { opacity: 0; width: 0; }
    .sb-item, .sb-footer a { justify-content: center; }
    .sb-main { margin-left: var(--sb-w-col); }
    .sb-content { padding: 16px; }
}
</style>

<!-- ============ SIDEBAR HTML ============ -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="sb-sidebar" id="sidebar">
    <button class="sb-toggle" id="sidebarToggle" title="Kengaytirish/Yopish">
        <i class="fas fa-chevron-left" id="toggleIcon"></i>
    </button>

    <a href="index.php" class="sb-logo">
        <div class="sb-logo-icon"><i class="fas fa-graduation-cap"></i></div>
        <div class="sb-logo-text">
            <strong>SamDU Admin</strong>
            <span><?= htmlspecialchars($_SESSION['login'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </a>

    <nav class="sb-nav">
        <div class="sb-section-label">Asosiy</div>

        <?php foreach ($nav_items as $item): ?>
        <a href="<?= $item['file'] ?>.php"
           class="sb-item <?= ($current_page === $item['file']) ? 'active' : '' ?>"
           data-tooltip="<?= $item['label'] ?>">
            <i class="fas <?= $item['icon'] ?>"></i>
            <span class="sb-label"><?= $item['label'] ?></span>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="sb-footer">
        <a href="logout.php" data-tooltip="Chiqish">
            <i class="fas fa-sign-out-alt" style="color:#ff7675;"></i>
            <span class="sb-label">Chiqish</span>
        </a>
    </div>
</div>

<div class="sb-main">
    <!-- TOPBAR -->
    <div class="sb-topbar">
        <div class="sb-topbar-title">
            <?php
            $titles = [
                'index'        => ['icon'=>'fa-users',          'label'=>'Talabalar'],
                'get-results'  => ['icon'=>'fa-chart-bar',      'label'=>'Natijalar'],
                'natija'       => ['icon'=>'fa-table',           'label'=>'Natija jadvali'],
                'reyting-quiz' => ['icon'=>'fa-trophy',          'label'=>'Reyting'],
                'test_add'     => ['icon'=>'fa-file-alt',        'label'=>'Test boshqaruvi'],
                'question_add' => ['icon'=>'fa-question-circle', 'label'=>'Savol kiritish'],
                'quiz'         => ['icon'=>'fa-layer-group',     'label'=>'Test turlari'],
                'quizcreate'   => ['icon'=>'fa-plus-circle',     'label'=>'Yangi tur yaratish'],
                'qupdate'      => ['icon'=>'fa-edit',            'label'=>'Tahrirlash'],
                'results'      => ['icon'=>'fa-chart-bar',       'label'=>'Natijalar'],
            ];
            $t = $titles[$current_page] ?? ['icon'=>'fa-tachometer-alt', 'label'=>'Admin paneli'];
            ?>
            <i class="fas <?= $t['icon'] ?>" style="color:var(--sb-primary); margin-right:8px;"></i>
            <span><?= $t['label'] ?></span>
        </div>
        <div class="sb-topbar-right">
            <div class="sb-user">
                <i class="fas fa-user-shield"></i>
                <?= htmlspecialchars($_SESSION['login'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>
    </div>

    <!-- Sahifa content shu yerdan boshlanadi -->
    <div class="sb-content">

<!-- ============ SIDEBAR JS ============ -->
<script>
(function() {
    var sidebar    = document.getElementById('sidebar');
    var toggleBtn  = document.getElementById('sidebarToggle');
    var toggleIcon = document.getElementById('toggleIcon');

    var COLLAPSED_KEY = 'sb_collapsed';
    var isCollapsed = localStorage.getItem(COLLAPSED_KEY) === '1';

    function applyState(collapsed, animate) {
        if (!animate) sidebar.style.transition = 'none';
        sidebar.classList.toggle('collapsed', collapsed);
        toggleIcon.className = collapsed ? 'fas fa-chevron-right' : 'fas fa-chevron-left';
        if (!animate) setTimeout(function() { sidebar.style.transition = ''; }, 10);
    }

    applyState(isCollapsed, false);

    toggleBtn.addEventListener('click', function() {
        isCollapsed = !isCollapsed;
        localStorage.setItem(COLLAPSED_KEY, isCollapsed ? '1' : '0');
        applyState(isCollapsed, true);
    });
})();
</script>
