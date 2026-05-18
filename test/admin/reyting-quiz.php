<?php
include_once 'ximoya.php';

$id_hash   = '';
$test_name = '';
$id_hash_sel = $_GET['id_hash'] ?? '';

// Natijani o'chirish
if (isset($_GET['del_id'])) {
    $del_id = (int)$_GET['del_id'];
    $stmt   = mysqli_prepare($link, "DELETE FROM results WHERE id=?");
    mysqli_stmt_bind_param($stmt, "i", $del_id);
    mysqli_stmt_execute($stmt);

    $back_hash = preg_match('/^[a-z0-9.]+$/i', $_GET['id_hash'] ?? '') ? $_GET['id_hash'] : '';
    header("Location: reyting-quiz.php?id_hash=" . urlencode($back_hash) . "&msg=deleted");
    exit;
}

// Testlar ro'yxati
$testlar = [];
$res = mysqli_query($link, "SELECT id_hash, nomi FROM testlar ORDER BY nomi ASC");
while ($t = mysqli_fetch_assoc($res)) {
    $testlar[] = $t;
}

// Tanlangan test natijalari
$results = [];
if ($id_hash_sel && preg_match('/^[a-z0-9.]+$/i', $id_hash_sel)) {
    $id_hash = $id_hash_sel;

    // Test nomini olish
    $stmt_t = mysqli_prepare($link, "SELECT nomi FROM testlar WHERE id_hash=? LIMIT 1");
    mysqli_stmt_bind_param($stmt_t, "s", $id_hash);
    mysqli_stmt_execute($stmt_t);
    $trow      = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_t));
    $test_name = $trow['nomi'] ?? '';

    // Natijalar (prepared statement)
    $stmt_r = mysqli_prepare($link, "
        SELECT r.id, r.score, r.wrong, r.right_p,
               s.fio, s.telefon
        FROM results r
        LEFT JOIN user    u ON u.login = r.login
        LEFT JOIN students s ON s.id   = u.student_id
        WHERE r.id_hash = ?
        ORDER BY r.score DESC
    ");
    mysqli_stmt_bind_param($stmt_r, "s", $id_hash);
    mysqli_stmt_execute($stmt_r);
    $res_r = mysqli_stmt_get_result($stmt_r);
    while ($row = mysqli_fetch_assoc($res_r)) {
        $results[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reyting | Admin</title>
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
        .btn-logout {
            background: rgba(231,76,60,0.2); border: 1px solid rgba(231,76,60,0.5);
            color: #e74c3c; padding: 7px 16px; border-radius: 8px; text-decoration: none;
            font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; transition: 0.2s;
        }
        .btn-logout:hover { background: rgba(231,76,60,0.4); }

        .container { max-width: 1100px; margin: 0 auto; padding: 30px 20px; }

        .card {
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px; padding: 24px; backdrop-filter: blur(10px); margin-bottom: 22px;
        }
        .card-title { font-size: 1rem; font-weight: 600; color: #a0a8ff; margin-bottom: 16px;
            display: flex; align-items: center; gap: 8px; }

        /* Filter */
        .filter-row { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
        select.test-select {
            background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);
            color: #fff; padding: 10px 16px; border-radius: 10px; font-size: 0.9rem;
            min-width: 260px; cursor: pointer; outline: none; transition: 0.2s;
        }
        select.test-select:focus { border-color: #7c83fd; }
        select.test-select option { background: #1e1e3f; }
        .btn-filter {
            background: linear-gradient(135deg, #7c83fd, #4a00e0); color: #fff;
            border: none; padding: 10px 22px; border-radius: 10px; font-size: 0.9rem;
            font-weight: 600; cursor: pointer; display: inline-flex; align-items: center;
            gap: 7px; transition: 0.2s;
        }
        .btn-filter:hover { transform: translateY(-1px); box-shadow: 0 4px 15px rgba(124,131,253,0.4); }

        .alert-success {
            background: rgba(46,204,113,0.12); border: 1px solid rgba(46,204,113,0.3);
            color: #55efc4; padding: 11px 16px; border-radius: 10px; margin-bottom: 16px;
            font-size: 0.87rem; display: flex; align-items: center; gap: 8px;
        }

        /* Table */
        .table-wrap { overflow-x: auto; border-radius: 10px; }
        table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        thead th {
            background: rgba(124,131,253,0.2); color: #a0a8ff; font-weight: 600;
            padding: 12px 14px; text-align: center; white-space: nowrap;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        tbody tr { border-bottom: 1px solid rgba(255,255,255,0.05); transition: background 0.15s; }
        tbody tr:hover { background: rgba(255,255,255,0.04); }
        tbody td { padding: 10px 14px; text-align: center; vertical-align: middle; }
        .rank-1 { color: #ffd700; font-weight: 800; }
        .rank-2 { color: #c0c0c0; font-weight: 800; }
        .rank-3 { color: #cd7f32; font-weight: 800; }
        .rank-n { color: #7c83fd; font-weight: 700; }
        .td-name { text-align: left !important; font-weight: 500; }
        .badge-p {
            display: inline-block; padding: 3px 10px; border-radius: 20px;
            font-size: 0.78rem; font-weight: 700;
        }
        .p-high { background: rgba(46,204,113,0.2); color: #55efc4; border: 1px solid rgba(46,204,113,0.3); }
        .p-mid  { background: rgba(243,156,18,0.2); color: #fdcb6e; border: 1px solid rgba(243,156,18,0.3); }
        .p-low  { background: rgba(231,76,60,0.2);  color: #ff7675; border: 1px solid rgba(231,76,60,0.3); }

        .btn-del {
            background: rgba(231,76,60,0.2); border: 1px solid rgba(231,76,60,0.3);
            color: #ff7675; padding: 5px 12px; border-radius: 7px; font-size: 0.78rem;
            cursor: pointer; display: inline-flex; align-items: center; gap: 5px; transition: 0.15s;
        }
        .btn-del:hover { background: rgba(231,76,60,0.35); transform: translateY(-1px); }

        .test-header { font-size: 1rem; font-weight: 700; color: #fff; margin-bottom: 14px; }
        .test-header span { color: #7c83fd; }

        .empty-msg { text-align: center; padding: 50px 20px; color: #666; }
        .empty-msg i { font-size: 2.5rem; display: block; margin-bottom: 12px; color: #444; }
    </style>
</head>
<body>

<?php include_once 'sidebar.php'; ?>

<div class="container">

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
    <div class="alert-success">
        <i class="fas fa-check-circle"></i> Natija muvaffaqiyatli o'chirildi
    </div>
    <?php endif; ?>

    <!-- Test tanlash -->
    <div class="card">
        <div class="card-title"><i class="fas fa-search"></i> Testni tanlang</div>
        <form action="reyting-quiz.php" method="GET" class="filter-row">
            <select name="id_hash" class="test-select">
                <option value="">— Test tanlang —</option>
                <?php foreach ($testlar as $t): ?>
                <option value="<?= htmlspecialchars($t['id_hash'], ENT_QUOTES) ?>"
                    <?= ($t['id_hash'] === $id_hash) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($t['nomi'], ENT_QUOTES, 'UTF-8') ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-filter">
                <i class="fas fa-eye"></i> Ko'rish
            </button>
        </form>
    </div>

    <!-- Natijalar -->
    <?php if ($id_hash && !empty($results)): ?>
    <div class="card">
        <div class="test-header">
            <i class="fas fa-clipboard-list" style="color:#7c83fd;"></i>
            "<span><?= htmlspecialchars($test_name, ENT_QUOTES, 'UTF-8') ?></span>"
            bo'yicha natijalar
            <small style="color:#666; font-size:0.8rem; margin-left:8px;">(<?= count($results) ?> ta)</small>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>O'rin</th>
                        <th>TALABA</th>
                        <th>TELEFON</th>
                        <th>TO'G'RI</th>
                        <th>XATO</th>
                        <th>FOIZ</th>
                        <th>AMAL</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($results as $i => $r):
                    $rank = $i + 1;
                    $p    = (int)$r['right_p'];
                    $rank_cls = match(true) {
                        $rank === 1 => 'rank-1',
                        $rank === 2 => 'rank-2',
                        $rank === 3 => 'rank-3',
                        default     => 'rank-n',
                    };
                    $badge_cls = $p >= 70 ? 'p-high' : ($p >= 50 ? 'p-mid' : 'p-low');
                ?>
                <tr>
                    <td class="<?= $rank_cls ?>">
                        <?php if ($rank <= 3): ?>
                        <i class="fas fa-medal"></i>
                        <?php endif; ?>
                        <?= $rank ?>
                    </td>
                    <td class="td-name">
                        <?= htmlspecialchars($r['fio'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <td><?= htmlspecialchars($r['telefon'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                    <td style="color:#55efc4; font-weight:600;"><?= (int)$r['score'] ?></td>
                    <td style="color:#ff7675; font-weight:600;"><?= (int)$r['wrong'] ?></td>
                    <td><span class="badge-p <?= $badge_cls ?>"><?= $p ?>%</span></td>
                    <td>
                        <button class="btn-del"
                            onclick="uchir(<?= (int)$r['id'] ?>, '<?= htmlspecialchars($id_hash, ENT_QUOTES) ?>')">
                            <i class="fas fa-trash"></i> O'chirish
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php elseif ($id_hash && empty($results)): ?>
    <div class="card">
        <div class="empty-msg">
            <i class="fas fa-inbox"></i>
            Bu test uchun hali natija yo'q
        </div>
    </div>

    <?php elseif (!$id_hash): ?>
    <div class="card">
        <div class="empty-msg">
            <i class="fas fa-hand-point-up"></i>
            Yuqoridan testni tanlang
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
function uchir(id, id_hash) {
    if (confirm("Bu natijani o'chirishga ishonchingiz komilmi?")) {
        window.location.href = 'reyting-quiz.php?del_id=' + id + '&id_hash=' + encodeURIComponent(id_hash);
    }
}
</script>
    </div> <!-- sb-content -->
</div> <!-- sb-main -->

</body>
</html>