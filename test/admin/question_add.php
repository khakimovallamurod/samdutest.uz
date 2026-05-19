<?php
include 'ximoya.php';

// Savol qo'shish
if (isset($_POST['add_ques'])) {
    $savol   = filter($_POST['savol']);
    $answer  = filter($_POST['answer']);
    $var1    = filter($_POST['var1']);
    $var2    = filter($_POST['var2']);
    $var3    = filter($_POST['var3']);
    $id_hash = preg_match('/^[a-z0-9.]+$/i', $_POST['id_hash'] ?? '') ? $_POST['id_hash'] : '';
    $test_id = (int)($_POST['test_id'] ?? 0);

    if (empty($id_hash) || $test_id <= 0) {
        header("Location: test_add.php");
        exit;
    }

    $hash        = uniqid('', true);
    $answer_hash = bin2hex(random_bytes(8));
    $var1_hash   = bin2hex(random_bytes(8));
    $var2_hash   = bin2hex(random_bytes(8));
    $var3_hash   = bin2hex(random_bytes(8));

    // Savol kiritish
    $stmt = mysqli_prepare($link, "INSERT INTO savollar (test_id,savol,test_id_hash,hash) VALUES (?,?,?,?)");
    mysqli_stmt_bind_param($stmt, "isss", $test_id, $savol, $id_hash, $hash);
    mysqli_stmt_execute($stmt);

    // Variantlar kiritish
    $stmt2 = mysqli_prepare($link, "INSERT INTO variantlar (savol_id_hash,variant,variant_id_hash) VALUES (?,?,?)");

    foreach ([
        [$hash, $answer, $answer_hash],
        [$hash, $var1,   $var1_hash],
        [$hash, $var2,   $var2_hash],
        [$hash, $var3,   $var3_hash],
    ] as $row) {
        mysqli_stmt_bind_param($stmt2, "sss", $row[0], $row[1], $row[2]);
        mysqli_stmt_execute($stmt2);
    }

    // To'g'ri javob
    $stmt3 = mysqli_prepare($link, "INSERT INTO javoblar (savol_id_hash,variant_id_hash) VALUES (?,?)");
    mysqli_stmt_bind_param($stmt3, "ss", $hash, $answer_hash);
    if (mysqli_stmt_execute($stmt3)) {
        header("Location: question_add.php?id_hash=" . urlencode($id_hash) . "&report=ok");
        exit;
    } else {
        $error = "Kiritishda xatolik yuz berdi.";
    }
}

// id_hash tekshiruvi
if (!isset($_GET['id_hash']) || !preg_match('/^[a-z0-9.]+$/i', $_GET['id_hash'])) {
    exit('Noto\'g\'ri so\'rov. [403]');
}

$id_hash = $_GET['id_hash'];
$stmt    = mysqli_prepare($link, "SELECT * FROM testlar WHERE id_hash=? LIMIT 1");
mysqli_stmt_bind_param($stmt, "s", $id_hash);
mysqli_stmt_execute($stmt);
$test = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$test) {
    exit('Test topilmadi. [404]');
}

// Savollar ro'yxatini olish
$stmt_s = mysqli_prepare($link, "SELECT * FROM savollar WHERE test_id_hash=? ORDER BY id ASC");
mysqli_stmt_bind_param($stmt_s, "s", $id_hash);
mysqli_stmt_execute($stmt_s);
$savollar_res = mysqli_stmt_get_result($stmt_s);
$savollar     = [];
while ($s = mysqli_fetch_assoc($savollar_res)) {
    $savollar[] = $s;
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Savol Kiritish | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            min-height: 100vh; color: #e0e0e0;
        }
        .topnav {
            background: rgba(255,255,255,0.07); backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding: 14px 30px; display: flex; align-items: center; justify-content: space-between;
        }
        .topnav h1 { font-size: 1.2rem; font-weight: 700; color: #fff; }
        .topnav h1 span { color: #7c83fd; }
        .btn-back {
            background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);
            color: #ccc; padding: 7px 16px; border-radius: 8px; text-decoration: none;
            font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; transition: 0.2s;
        }
        .btn-back:hover { background: rgba(255,255,255,0.15); }
        .container { max-width: 1000px; margin: 0 auto; padding: 30px 20px; }

        .tabs { display: flex; gap: 4px; margin-bottom: 0; }
        .tab-btn {
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
            border-bottom: none; color: #aaa; padding: 10px 22px; border-radius: 10px 10px 0 0;
            cursor: pointer; font-size: 0.9rem; transition: 0.2s;
        }
        .tab-btn.active { background: rgba(124,131,253,0.2); border-color: #7c83fd; color: #fff; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .card {
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
            border-radius: 0 16px 16px 16px; padding: 28px; backdrop-filter: blur(10px);
        }
        .card h3 { font-size: 1.05rem; color: #a0a8ff; margin-bottom: 20px;
            display: flex; align-items: center; gap: 8px; }

        .alert-success {
            background: rgba(46,204,113,0.15); border: 1px solid rgba(46,204,113,0.3);
            color: #2ecc71; padding: 12px 16px; border-radius: 10px; margin-bottom: 20px;
            display: flex; align-items: center; gap: 8px;
        }
        .alert-error {
            background: rgba(231,76,60,0.15); border: 1px solid rgba(231,76,60,0.3);
            color: #e74c3c; padding: 12px 16px; border-radius: 10px; margin-bottom: 20px;
        }

        .form-group { margin-bottom: 18px; }
        label { display: block; font-size: 0.85rem; color: #a0a8ff; margin-bottom: 6px; font-weight: 500; }
        .form-control {
            width: 100%; padding: 10px 14px; background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.12); border-radius: 9px; color: #fff;
            font-size: 0.9rem; outline: none; transition: border-color 0.2s; resize: vertical;
        }
        .form-control:focus { border-color: #7c83fd; }
        .btn-submit {
            background: linear-gradient(135deg, #7c83fd, #4a00e0); color: #fff;
            border: none; padding: 11px 28px; border-radius: 10px; font-size: 0.9rem;
            font-weight: 600; cursor: pointer; transition: 0.2s;
            display: inline-flex; align-items: center; gap: 8px; margin-top: 8px;
        }
        .btn-submit:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(124,131,253,0.4); }

        /* Savol kartochkasi */
        .q-card {
            background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px; padding: 16px 20px; margin-bottom: 12px;
        }
        .q-card-header {
            display: flex; align-items: flex-start; justify-content: space-between; gap: 10px;
        }
        .q-num { color: #7c83fd; font-weight: 700; font-size: 0.95rem; min-width: 28px; }
        .q-text { color: #e0e0e0; flex: 1; font-size: 0.9rem; line-height: 1.5; }
        .q-actions { display: flex; gap: 6px; flex-shrink: 0; }
        .btn-qedit   { background: rgba(52,152,219,0.2); border: 1px solid rgba(52,152,219,0.3);
            color: #74b9ff; padding: 5px 12px; border-radius: 6px; font-size: 0.78rem;
            text-decoration: none; transition: 0.15s; }
        .btn-qdel    { background: rgba(231,76,60,0.2); border: 1px solid rgba(231,76,60,0.3);
            color: #ff7675; padding: 5px 12px; border-radius: 6px; font-size: 0.78rem;
            text-decoration: none; transition: 0.15s; }
        .btn-qedit:hover, .btn-qdel:hover { transform: translateY(-1px); }

        .q-variants { margin-top: 10px; padding-left: 28px; }
        .q-variants li {
            font-size: 0.82rem; color: #aaa; list-style: none; padding: 3px 0;
        }
        .q-variants li:first-child { color: #2ecc71; font-weight: 600; }
        .q-variants li:first-child::before { content: "✓ "; }
        .q-variants li:not(:first-child)::before { content: "• "; color: #555; }

        .stats-bar {
            display: flex; gap: 16px; flex-wrap: wrap;
            background: rgba(124,131,253,0.1); border: 1px solid rgba(124,131,253,0.2);
            border-radius: 12px; padding: 14px 20px; margin-bottom: 20px;
        }
        .stat { font-size: 0.85rem; color: #a0a8ff; }
        .stat strong { color: #fff; }
    </style>
</head>
<body>

<?php include_once 'sidebar.php'; ?>

<div class="container">

    <?php if (isset($_GET['report']) && $_GET['report'] === 'ok'): ?>
    <div class="alert-success"><i class="fas fa-check-circle"></i> Savol muvaffaqiyatli kiritildi!</div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
    <div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="stats-bar">
        <div class="stat">
            Test: <strong><?= htmlspecialchars($test['nomi'], ENT_QUOTES, 'UTF-8') ?></strong>
        </div>
        <div class="stat">
            Kiritilgan savollar: <strong><?= count($savollar) ?></strong>
        </div>
        <div class="stat">
            Kerakli soni: <strong><?= (int)$test['soni'] ?></strong>
        </div>
        <div class="stat">
            Vaqt: <strong><?= (int)$test['vaqt'] ?> daqiqa</strong>
        </div>
    </div>

    <div class="tabs">
        <button class="tab-btn active" id="btn-add" onclick="switchTab('tab-add','btn-add')">
            <i class="fas fa-plus-circle"></i> Yangi savol qo'shish
        </button>
        <button class="tab-btn" id="btn-list" onclick="switchTab('tab-list','btn-list')">
            <i class="fas fa-list"></i> Kiritilganlar (<?= count($savollar) ?>)
        </button>
    </div>

    <!-- Yangi savol -->
    <div id="tab-add" class="tab-content active">
        <div class="card">
            <h3><i class="fas fa-edit"></i>
                <?= htmlspecialchars($test['nomi'], ENT_QUOTES, 'UTF-8') ?> ga savol qo'shish
            </h3>
            <form method="POST" action="question_add.php">
                <input type="hidden" name="id_hash" value="<?= htmlspecialchars($test['id_hash'], ENT_QUOTES) ?>">
                <input type="hidden" name="test_id"  value="<?= (int)$test['id'] ?>">

                <div class="form-group">
                    <label><i class="fas fa-question"></i> Savol matni</label>
                    <textarea name="savol" class="form-control" required rows="3"
                        placeholder="Savol matnini kiriting..."></textarea>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-check" style="color:#2ecc71;"></i> To'g'ri javob</label>
                    <textarea name="answer" class="form-control" required rows="2"
                        placeholder="To'g'ri javobni kiriting..."></textarea>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px;">
                    <?php foreach ([1, 2, 3] as $n): ?>
                    <div class="form-group">
                        <label><i class="fas fa-times" style="color:#e74c3c;"></i> <?= $n ?>-variant</label>
                        <textarea name="var<?= $n ?>" class="form-control" required rows="2"
                            placeholder="<?= $n ?>-variant..."></textarea>
                    </div>
                    <?php endforeach; ?>
                </div>

                <button type="submit" name="add_ques" class="btn-submit">
                    <i class="fas fa-plus"></i> Kiritish
                </button>
            </form>
        </div>
    </div>

    <!-- Kiritilganlar -->
    <div id="tab-list" class="tab-content">
        <div class="card">
            <h3><i class="fas fa-database"></i> Kiritilgan savollar</h3>
            <?php if (empty($savollar)): ?>
            <p style="text-align:center; color:#666; padding:30px;">Hali savol kiritilmagan</p>
            <?php endif; ?>
            <?php foreach ($savollar as $j => $s):
                $stmt_v = mysqli_prepare($link, "SELECT * FROM variantlar WHERE savol_id_hash=? ORDER BY id ASC");
                mysqli_stmt_bind_param($stmt_v, "s", $s['hash']);
                mysqli_stmt_execute($stmt_v);
                $variants = [];
                $vres = mysqli_stmt_get_result($stmt_v);
                while ($v = mysqli_fetch_assoc($vres)) $variants[] = $v;
            ?>
            <div class="q-card">
                <div class="q-card-header">
                    <span class="q-num"><?= $j + 1 ?>.</span>
                    <div class="q-text"><?= htmlspecialchars($s['savol'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="q-actions">
                        <a href="qupdate.php?q_id=<?= (int)$s['id'] ?>&id_hash=<?= urlencode($id_hash) ?>"
                           class="btn-qedit"><i class="fas fa-edit"></i></a>
                        <a href="delete.php?q_id=<?= (int)$s['id'] ?>&id_hash=<?= urlencode($id_hash) ?>"
                           class="btn-qdel"
                           onclick="return confirm('O\'chirishga ishonchingiz komilmi?')">
                           <i class="fas fa-trash"></i></a>
                    </div>
                </div>
                <ul class="q-variants">
                    <?php foreach ($variants as $v): ?>
                    <li><?= htmlspecialchars($v['variant'], ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
function switchTab(id, btnId) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    document.getElementById(btnId).classList.add('active');
}
</script>
    </div> <!-- sb-content -->
</div> <!-- sb-main -->

</body>
</html>
