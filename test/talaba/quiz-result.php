<?php
include_once 'ximoya.php';

$login = $_SESSION['login'] ?? '';
$score = 0; $wrong = 0; $right_p = 0; $wrong_p = 0;

if (isset($_SESSION['test_status'])) {
    // Test yendi tugadi — natijani DB ga saqlash
    $tugash_vaqti = time();
    $score   = (int)($_SESSION['test_score_right'] ?? 0);
    $wrong   = (int)($_SESSION['test_score_wrong'] ?? 0);
    $total   = max(1, (int)($_SESSION['test_total'] ?? 1));
    $right_p = (int)(($score / $total) * 100);
    $wrong_p = 100 - $right_p;
    $id      = (int)($_SESSION['test_status_id'] ?? 0);

    if ($id > 0) {
        //$stmt = mysqli_prepare($link,
          //  "UPDATE results SET t_vaqt=?,score=?,wrong=?,right_p=?,w_p=? WHERE id=?");
        //mysqli_stmt_bind_param($stmt, "iiiiii",
    //$tugash_vaqti, $score, $wrong, $right_p, $wrong_p, $id);
        // Fix: bind_param ta'rif turini to'g'irlab
        $stmt = mysqli_prepare($link,
            "UPDATE results SET t_vaqt=?,score=?,wrong=?,right_p=?,w_p=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "iiiiii",
    $tugash_vaqti, $score, $wrong, $right_p, $wrong_p, $id);
        mysqli_stmt_execute($stmt);
    }

    // Session tozalash (faqat test ma'lumotlari)
    $keep = ['id','rol','fan_id','student_id','kirish_vaqti','login'];
    foreach (array_keys($_SESSION) as $key) {
        if (!in_array($key, $keep)) {
            unset($_SESSION[$key]);
        }
    }
} else {
    // Oldingi natijani ko'rish
    $stmt = mysqli_prepare($link,
        "SELECT score, wrong, right_p, w_p FROM results WHERE login=? ORDER BY id DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $login);
    mysqli_stmt_execute($stmt);
    $fetch = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    $score   = (int)($fetch['score']   ?? 0);
    $wrong   = (int)($fetch['wrong']   ?? 0);
    $right_p = (int)($fetch['right_p'] ?? 0);
    $wrong_p = (int)($fetch['w_p']     ?? 0);
}

$total_answered = $score + $wrong;

// Natija rangi
if ($right_p >= 86)      $result_class = 'excellent';
elseif ($right_p >= 56)  $result_class = 'good';
else                     $result_class = 'poor';
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Natijasi | SamDU</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            min-height: 100vh;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 20px;
        }

        .result-card {
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 24px;
            padding: 40px 36px;
            max-width: 580px;
            width: 100%;
            backdrop-filter: blur(16px);
            text-align: center;
            animation: fadeInUp 0.5s ease;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .result-icon {
            font-size: 3.5rem;
            margin-bottom: 12px;
        }
        .excellent .result-icon { color: #55efc4; }
        .good      .result-icon { color: #fdcb6e; }
        .poor      .result-icon { color: #ff7675; }

        h1.result-title {
            font-size: 1.5rem; font-weight: 800; color: #fff; margin-bottom: 6px;
        }
        .result-subtitle { font-size: 0.9rem; color: #888; margin-bottom: 32px; }

        /* Donut chart replacement — CSS ring */
        .score-ring-wrap {
            display: flex; justify-content: center; margin-bottom: 32px;
        }
        .score-ring {
            position: relative; width: 180px; height: 180px;
        }
        .score-ring svg { transform: rotate(-90deg); }
        .ring-bg { fill: none; stroke: rgba(255,255,255,0.08); stroke-width: 14; }
        .ring-fill { fill: none; stroke-width: 14; stroke-linecap: round;
            transition: stroke-dashoffset 1.2s ease; }
        .excellent .ring-fill { stroke: #55efc4; }
        .good      .ring-fill { stroke: #fdcb6e; }
        .poor      .ring-fill { stroke: #ff7675; }

        .ring-center {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            text-align: center;
        }
        .ring-pct {
            font-size: 2.5rem; font-weight: 800; line-height: 1;
        }
        .excellent .ring-pct { color: #55efc4; }
        .good      .ring-pct { color: #fdcb6e; }
        .poor      .ring-pct { color: #ff7675; }
        .ring-label { font-size: 0.75rem; color: #888; margin-top: 4px; }

        /* Stats grid */
        .stats {
            display: grid; grid-template-columns: 1fr 1fr 1fr;
            gap: 14px; margin-bottom: 30px;
        }
        .stat-box {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 14px; padding: 16px 10px;
        }
        .stat-box .num  { font-size: 1.7rem; font-weight: 800; }
        .stat-box .lbl  { font-size: 0.75rem; color: #888; margin-top: 4px; }
        .stat-total .num { color: #74b9ff; }
        .stat-right .num { color: #55efc4; }
        .stat-wrong .num { color: #ff7675; }

        /* Result message */
        .result-msg {
            padding: 14px 20px; border-radius: 12px; margin-bottom: 24px;
            font-size: 0.9rem; font-weight: 500; line-height: 1.5;
        }
        .excellent .result-msg { background: rgba(85,239,196,0.1); border: 1px solid rgba(85,239,196,0.2); color: #55efc4; }
        .good      .result-msg { background: rgba(253,203,110,0.1); border: 1px solid rgba(253,203,110,0.2); color: #fdcb6e; }
        .poor      .result-msg { background: rgba(255,118,117,0.1); border: 1px solid rgba(255,118,117,0.2); color: #ff7675; }

        .btn-home {
            display: inline-flex; align-items: center; gap: 9px;
            background: linear-gradient(135deg, #7c83fd, #4a00e0);
            color: #fff; border: none; padding: 13px 32px; border-radius: 12px;
            font-size: 0.95rem; font-weight: 700; cursor: pointer; text-decoration: none;
            transition: 0.2s; width: 100%; justify-content: center;
        }
        .btn-home:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(124,131,253,0.4); }

        @media (max-width: 400px) {
            .stats { grid-template-columns: 1fr 1fr; }
            .stat-total { grid-column: 1 / -1; }
            .result-card { padding: 28px 18px; }
        }
    </style>
</head>
<body>

<div class="result-card <?= $result_class ?>">
    <div class="result-icon">
        <?php if ($result_class === 'excellent'): ?>
        <i class="fas fa-trophy"></i>
        <?php elseif ($result_class === 'good'): ?>
        <i class="fas fa-thumbs-up"></i>
        <?php else: ?>
        <i class="fas fa-redo-alt"></i>
        <?php endif; ?>
    </div>
    <h1 class="result-title">Test Natijasi</h1>
    <p class="result-subtitle">
        <?= htmlspecialchars($login, ENT_QUOTES, 'UTF-8') ?> — test yakunlandi
    </p>

    <!-- SVG Donut ring -->
    <div class="score-ring-wrap">
        <div class="score-ring">
            <svg viewBox="0 0 180 180" width="180" height="180">
                <circle class="ring-bg" cx="90" cy="90" r="76"/>
                <circle class="ring-fill" cx="90" cy="90" r="76"
                    stroke-dasharray="477.5"
                    stroke-dashoffset="<?= 477.5 - (477.5 * $right_p / 100) ?>"
                    id="ringFill"/>
            </svg>
            <div class="ring-center">
                <div class="ring-pct" id="pctCounter">0%</div>
                <div class="ring-label">to'g'ri</div>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats">
        <div class="stat-box stat-total">
            <div class="num"><?= $total_answered ?></div>
            <div class="lbl"><i class="fas fa-list-ol"></i> Jami savollar</div>
        </div>
        <div class="stat-box stat-right">
            <div class="num"><?= $score ?></div>
            <div class="lbl"><i class="fas fa-check"></i> To'g'ri</div>
        </div>
        <div class="stat-box stat-wrong">
            <div class="num"><?= $wrong ?></div>
            <div class="lbl"><i class="fas fa-times"></i> Xato</div>
        </div>
    </div>



    <a href="quiz.php" class="btn-home">
        <i class="fas fa-home"></i> Bosh sahifaga qaytish
    </a>
</div>

<script>
// Counter animation
(function() {
    var target  = <?= (int)$right_p ?>;
    var current = 0;
    var el      = document.getElementById('pctCounter');
    if (!el) return;

    var step = Math.ceil(target / 60);
    var timer = setInterval(function() {
        current = Math.min(current + step, target);
        el.textContent = current + '%';
        if (current >= target) clearInterval(timer);
    }, 20);
})();
</script>

</body>
</html>
