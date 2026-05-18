<?php
include_once 'ximoya.php';

/*=============== Javobni tekshirish ===============*/
if (isset($_POST['check'])) {
    $id_hash = $_SESSION['test_savol_id_hash'] ?? '';
    $var     = $_POST['variant'] ?? '';

    // variant hash tekshiruvi (faqat hex raqamlar)
    if (!preg_match('/^[a-f0-9]+$/i', $var)) {
        header("Location: quiz-result.php");
        exit;
    }

    // To'g'ri javobni DB dan olish
    $stmt = mysqli_prepare($link, "SELECT variant_id_hash FROM javoblar WHERE savol_id_hash=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $id_hash);
    mysqli_stmt_execute($stmt);
    $ans = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (isset($ans['variant_id_hash']) && hash_equals($ans['variant_id_hash'], $var)) {
        $_SESSION['test_score_right'] = ($_SESSION['test_score_right'] ?? 0) + 1;
    } else {
        $_SESSION['test_score_wrong'] = ($_SESSION['test_score_wrong'] ?? 0) + 1;
    }

    // Vaqt tekshiruvi
    $ajratilgan = ($_SESSION['test_vaqt'] ?? 0) * 60;
    $ketgan     = time() - ($_SESSION['vaqt'] ?? time());
    if ($ketgan > $ajratilgan) {
        header("Location: quiz-result.php");
        exit;
    }

    // Test tugadimi?
    $yechilgan = ($_SESSION['test_yechilgan'] ?? 0) + 1;
    $_SESSION['test_yechilgan'] = $yechilgan;

    if ((int)$_POST['n'] >= (int)($_SESSION['test_total'] ?? 0)) {
        header("Location: quiz-result.php");
        exit;
    }

    // Keyingi savolni yuklash
    $qid = array_shift($_SESSION['ques_arr']);
    if (!$qid) {
        header("Location: quiz-result.php");
        exit;
    }

    _load_question($link, $qid);
    $_SESSION['test_status'] = "doing";
    header("Location: quiz.php");
    exit;
}

/*=============== Test boshlash (id_hash orqali) ===============*/
if ($_SESSION['test_status'] !== "begin") {
    if (isset($_GET['id_hash'])) {
        if ($_SESSION['test_status'] === "doing") {
            header("Location: quiz.php");
            exit;
        }

        $id_hash = $_GET['id_hash'];
        if (!preg_match('/^[a-z0-9.]+$/i', $id_hash)) {
            header("Location: quiz.php");
            exit;
        }

        $login = $_SESSION['login'] ?? '';

        // Oldin test ishlagan-ishlmaganligini tekshirish
        $stmt = mysqli_prepare($link,
            "SELECT COUNT(*) AS cnt FROM results WHERE id_hash=? AND login=?");
        mysqli_stmt_bind_param($stmt, "ss", $id_hash, $login);
        mysqli_stmt_execute($stmt);
        $check = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if ((int)$check['cnt'] >= 1) {
            echo "<script>alert('Bu testni ishlash limitingiz to\\'lgan'); window.location='quiz-result.php';</script>";
            exit;
        }

        // Test ma'lumotini olish
        $stmt2 = mysqli_prepare($link, "SELECT * FROM testlar WHERE id_hash=? LIMIT 1");
        mysqli_stmt_bind_param($stmt2, "s", $id_hash);
        mysqli_stmt_execute($stmt2);
        $test = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt2));

        if (!$test) {
            header("Location: quiz.php");
            exit;
        }

        $_SESSION['test_score_right']   = 0;
        $_SESSION['test_score_wrong']   = 0;
        $_SESSION['test_total_count']   = (int)$test['soni'];
        $_SESSION['test_total']         = (int)$test['total'];
        $_SESSION['test_vaqt']          = (int)$test['vaqt'];
        $_SESSION['test_id_hash']       = $id_hash;
        $_SESSION['test_status']        = "begin";
        $_SESSION['test_yechilgan']     = 0;

        $boshlangan_vaqt = time();
        $_SESSION['vaqt'] = $boshlangan_vaqt;

        // Natijani yaratish
        $stmt3 = mysqli_prepare($link,
            "INSERT INTO results (login, id_hash, boshlangan_vaqt) VALUES (?,?,?)");
        mysqli_stmt_bind_param($stmt3, "ssi", $login, $id_hash, $boshlangan_vaqt);
        mysqli_stmt_execute($stmt3);

        $stmt4 = mysqli_prepare($link,
            "SELECT id FROM results WHERE id_hash=? AND login=? AND boshlangan_vaqt=? LIMIT 1");
        mysqli_stmt_bind_param($stmt4, "ssi", $id_hash, $login, $boshlangan_vaqt);
        mysqli_stmt_execute($stmt4);
        $res4 = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt4));
        $_SESSION['test_status_id'] = $res4['id'] ?? 0;
    }
}

/*=============== Test boshlanganidan keyin ===============*/
if (isset($_SESSION['test_status']) && $_SESSION['test_status'] === "begin") {
    $id_hash = $_SESSION['test_id_hash'];

    // Savollar tartibini tasodifiy aralashtirish
    $stmt_s = mysqli_prepare($link,
        "SELECT id FROM savollar WHERE test_id_hash=? ORDER BY id ASC");
    mysqli_stmt_bind_param($stmt_s, "s", $id_hash);
    mysqli_stmt_execute($stmt_s);
    $res_s = mysqli_stmt_get_result($stmt_s);

    $arr = [];
    while ($f = mysqli_fetch_assoc($res_s)) {
        $arr[] = $f['id'];
    }
    shuffle($arr);
    $_SESSION['ques_arr'] = $arr;

    $qid = array_shift($_SESSION['ques_arr']);
    _load_question($link, $qid);
    $_SESSION['test_status'] = "doing";
}

/*=============== Qolgan vaqtni hisoblash ===============*/
$display_time = '00:00';
$t_remaining  = 0;
if (isset($_SESSION['test_vaqt'], $_SESSION['vaqt'])) {
    $t_remaining = ($_SESSION['test_vaqt'] * 60) - (time() - $_SESSION['vaqt']);
    if ($t_remaining < 0) {
        header("Location: quiz-result.php");
        exit;
    }
    $display_time = sprintf("%02d:%02d", intdiv($t_remaining, 60), $t_remaining % 60);
}

/*=============== Yordamchi: Savol yuklash ===============*/
function _load_question($link, $qid) {
    if (!$qid) return;

    $stmt = mysqli_prepare($link, "SELECT * FROM savollar WHERE id=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $qid);
    mysqli_stmt_execute($stmt);
    $savol = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    $_SESSION['test_savol']       = $savol['savol'] ?? '';
    $_SESSION['test_savol_id_hash'] = $savol['hash'] ?? '';
    $savol_hash = $savol['hash'] ?? '';

    $stmt2 = mysqli_prepare($link,
        "SELECT * FROM variantlar WHERE savol_id_hash=? ORDER BY id ASC");
    mysqli_stmt_bind_param($stmt2, "s", $savol_hash);
    mysqli_stmt_execute($stmt2);
    $vres = mysqli_stmt_get_result($stmt2);

    $variants = [];
    while ($v = mysqli_fetch_assoc($vres)) {
        $variants[] = $v;
    }
    // Tasodifiy aralashtirish
    shuffle($variants);

    for ($i = 0; $i < 4; $i++) {
        $n = $i + 1;
        if (isset($variants[$i])) {
            $_SESSION["var$n"]      = $variants[$i]['variant'];
            $_SESSION["var_hash$n"] = $variants[$i]['variant_id_hash'];
        } else {
            $_SESSION["var$n"]      = '';
            $_SESSION["var_hash$n"] = '';
        }
    }
}

$progress_pct = 0;
if (!empty($_SESSION['test_total'])) {
    $progress_pct = (int)(($_SESSION['test_yechilgan'] / $_SESSION['test_total']) * 100);
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Ishlash | SamDU</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            min-height: 100vh;
            user-select: none;
            -webkit-user-select: none;
        }

        /* NAV */
        .topnav {
            background: rgba(255,255,255,0.07); backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding: 12px 24px; display: flex; align-items: center; justify-content: space-between;
        }
        .topnav-title { font-size: 1rem; font-weight: 700; color: #fff; }
        .topnav-title span { color: #7c83fd; }
        .btn-logout {
            background: rgba(231,76,60,0.2); border: 1px solid rgba(231,76,60,0.5);
            color: #e74c3c; padding: 7px 14px; border-radius: 8px; text-decoration: none;
            font-size: 0.8rem; display: inline-flex; align-items: center; gap: 5px;
        }

        /* TIMER */
        .timer-bar {
            background: rgba(0,0,0,0.3); padding: 10px 24px;
            display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;
        }
        .timer {
            display: flex; align-items: center; gap: 10px;
        }
        .timer-label { font-size: 0.8rem; color: #aaa; }
        .timer-value {
            font-size: 1.5rem; font-weight: 800; color: #fff;
            font-variant-numeric: tabular-nums;
            background: rgba(124,131,253,0.2); border: 1px solid rgba(124,131,253,0.3);
            padding: 4px 16px; border-radius: 10px;
        }
        .timer-value.warning { color: #fdcb6e; border-color: rgba(243,156,18,0.5); }
        .timer-value.danger  { color: #ff7675; border-color: rgba(231,76,60,0.5); animation: blink 1s infinite; }

        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.5} }

        .question-info { color: #aaa; font-size: 0.9rem; }
        .question-info strong { color: #fff; }

        /* PROGRESS */
        .progress-wrap { background: rgba(255,255,255,0.06); height: 6px; }
        .progress-bar  {
            height: 6px; background: linear-gradient(90deg, #7c83fd, #4a00e0);
            transition: width 0.4s ease;
        }

        /* MAIN */
        .quiz-wrapper { max-width: 800px; margin: 0 auto; padding: 30px 20px; }

        .quiz-card {
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px; padding: 32px; backdrop-filter: blur(10px);
        }

        .q-number {
            font-size: 0.8rem; color: #7c83fd; font-weight: 600; letter-spacing: 1px;
            text-transform: uppercase; margin-bottom: 12px;
        }
        .q-text {
            font-size: 1.15rem; color: #f0f0f0; line-height: 1.7; margin-bottom: 28px;
            font-weight: 500;
        }

        /* VARIANTLAR */
        .variants { display: flex; flex-direction: column; gap: 12px; margin-bottom: 24px; }
        .variant-label {
            display: flex; align-items: center; gap: 14px;
            background: rgba(255,255,255,0.05); border: 1.5px solid rgba(255,255,255,0.1);
            border-radius: 12px; padding: 14px 18px; cursor: pointer;
            transition: all 0.2s; user-select: none;
        }
        .variant-label:hover {
            background: rgba(124,131,253,0.12); border-color: rgba(124,131,253,0.4);
        }
        .variant-label input[type="radio"] { display: none; }
        .variant-label.selected {
            background: rgba(124,131,253,0.2); border-color: #7c83fd;
        }
        .variant-marker {
            width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            background: rgba(255,255,255,0.08); border: 1.5px solid rgba(255,255,255,0.15);
            font-size: 0.8rem; font-weight: 700; color: #aaa; transition: 0.2s;
        }
        .selected .variant-marker {
            background: #7c83fd; border-color: #7c83fd; color: #fff;
        }
        .variant-text { font-size: 0.95rem; color: #e0e0e0; line-height: 1.5; flex: 1; }

        .btn-next {
            width: 100%; background: linear-gradient(135deg, #7c83fd, #4a00e0);
            color: #fff; border: none; padding: 14px; border-radius: 12px;
            font-size: 1rem; font-weight: 700; cursor: pointer; transition: 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        .btn-next:hover { transform: translateY(-1px); box-shadow: 0 8px 25px rgba(124,131,253,0.4); }
        .btn-next:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

        /* Test boshlash sahifasi */
        .start-wrap { text-align: center; }
        .start-wrap h1 { font-size: 1.5rem; color: #fff; margin-bottom: 24px; }
        .test-list { display: flex; flex-direction: column; gap: 12px; }
        .test-card {
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
            border-radius: 14px; padding: 18px 22px;
            display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;
        }
        .test-card-info h3 { font-size: 1rem; color: #e0e0e0; }
        .test-card-info p  { font-size: 0.8rem; color: #888; margin-top: 4px; }
        .btn-start {
            background: linear-gradient(135deg, #11998e, #38ef7d);
            color: #1a1a1a; border: none; padding: 10px 22px; border-radius: 10px;
            font-size: 0.9rem; font-weight: 700; cursor: pointer; text-decoration: none;
            display: inline-flex; align-items: center; gap: 7px; transition: 0.2s;
        }
        .btn-start:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(56,239,125,0.3); }

        @media (max-width: 500px) {
            .quiz-card { padding: 20px 16px; }
            .q-text { font-size: 1rem; }
        }
    </style>
</head>
<body oncopy="return false" oncut="return false" onpaste="return false">

<nav class="topnav">
    <div class="topnav-title">
        <i class="fas fa-clipboard-list" style="color:#7c83fd;"></i>
        Test <span>Tizimi</span>
    </div>
    <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Chiqish</a>
</nav>

<?php if (isset($_SESSION['test_status'])): ?>
<!-- Timer bar -->
<div class="timer-bar">
    <div class="timer">
        <div class="timer-label"><i class="fas fa-clock"></i> Qolgan vaqt:</div>
        <div class="timer-value" id="timerDisplay"><?= htmlspecialchars($display_time) ?></div>
    </div>
    <div class="question-info">
        Savol: <strong><?= (int)($_SESSION['test_yechilgan'] ?? 0) + 1 ?></strong>
        / <?= (int)($_SESSION['test_total'] ?? 0) ?>
    </div>
</div>
<!-- Progress -->
<div class="progress-wrap">
    <div class="progress-bar" style="width:<?= $progress_pct ?>%;"></div>
</div>
<?php endif; ?>

<div class="quiz-wrapper">

<?php if (!isset($_SESSION['test_status']) || $_SESSION['test_status'] === ''): ?>
<!-- ========== TEST BOSHLASH SAHIFASI ========== -->
<div class="quiz-card start-wrap">
    <h1><i class="fas fa-play-circle" style="color:#7c83fd;"></i> Testni boshlash</h1>
    <div class="test-list">
    <?php
    $fan_id = (int)($_SESSION['fan_id'] ?? 0);
    $stmt_t = mysqli_prepare($link, "SELECT * FROM testlar WHERE status=1 ORDER BY nomi ASC");
    mysqli_stmt_execute($stmt_t);
    $tests = mysqli_stmt_get_result($stmt_t);
    while ($fan = mysqli_fetch_assoc($tests)):
    ?>
    <div class="test-card">
        <div class="test-card-info">
            <h3><i class="fas fa-book" style="color:#7c83fd;"></i>
                <?= htmlspecialchars_decode($fan['nomi']) ?>
            </h3>
            <p>
                <i class="fas fa-question-circle"></i> <?= (int)$fan['total'] ?> ta savol &nbsp;|&nbsp;
                <i class="fas fa-clock"></i> <?= (int)$fan['vaqt'] ?> daqiqa
            </p>
        </div>
        <a href="quiz.php?id_hash=<?= urlencode($fan['id_hash']) ?>" class="btn-start">
            <i class="fas fa-play"></i> Boshlash
        </a>
    </div>
    <?php endwhile; ?>
    </div>
</div>

<?php elseif (isset($_SESSION['test_status'])): ?>
<!-- ========== TEST SAVOLLARI ========== -->
<div class="quiz-card">
    <div class="q-number">
        <i class="fas fa-question-circle"></i>
        <?= (int)($_SESSION['test_yechilgan'] ?? 0) + 1 ?>-savol
    </div>
    <div class="q-text">
        <?= defilter($_SESSION['test_savol'] ?? '') ?>
    </div>

    <form action="quiz.php" method="POST" id="quizForm">
        <div class="variants">
        <?php
        $markers = ['A', 'B', 'C', 'D'];
        for ($n = 1; $n <= 4; $n++):
            if (empty($_SESSION["var$n"])) continue;
        ?>
        <label class="variant-label" for="var<?= $n ?>">
            <input type="radio" name="variant" id="var<?= $n ?>"
                   value="<?= htmlspecialchars($_SESSION["var_hash$n"], ENT_QUOTES) ?>"
                   required>
            <div class="variant-marker"><?= $markers[$n - 1] ?></div>
            <div class="variant-text">
<?=$_SESSION["var$n"]; ?>
</div>
        </label>
        <?php endfor; ?>
        </div>

        <input type="hidden" name="n" value="<?= (int)($_SESSION['test_yechilgan'] ?? 0) + 1 ?>">
        <button type="submit" name="check" class="btn-next" id="btnNext" disabled>
            <i class="fas fa-arrow-right"></i>
            <?php
            $next_num = ($_SESSION['test_yechilgan'] ?? 0) + 1;
            $total    = $_SESSION['test_total'] ?? 0;
            echo ($next_num >= $total) ? 'Tugatish' : 'Keyingisi';
            ?>
        </button>
    </form>
</div>
<?php endif; ?>

</div>

<?php if (isset($_SESSION['test_status'])): ?>
<script>
// Variant tanlash
document.querySelectorAll('.variant-label').forEach(function(lbl) {
    lbl.addEventListener('click', function() {
        document.querySelectorAll('.variant-label').forEach(l => l.classList.remove('selected'));
        this.classList.add('selected');
        this.querySelector('input').checked = true;
        document.getElementById('btnNext').disabled = false;
    });
});

// Countdown timer
(function() {
    var remaining = <?= (int)$t_remaining ?>;
    var display   = document.getElementById('timerDisplay');
    if (!display) return;

    function tick() {
        if (remaining <= 0) {
            window.location.href = 'quiz-result.php';
            return;
        }
        var m = Math.floor(remaining / 60);
        var s = remaining % 60;
        display.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;

        if (remaining <= 60)       display.className = 'timer-value danger';
        else if (remaining <= 180) display.className = 'timer-value warning';

        remaining--;
        setTimeout(tick, 1000);
    }
    tick();
})();
</script>
<?php endif; ?>
</body>
</html>
