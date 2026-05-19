<?php
include_once 'ximoya.php';

$fan_id_filter = isset($_GET['fan']) ? (int)$_GET['fan'] : 0;
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Natijalar | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            min-height: 100vh; color: #e0e0e0;
        }

        .container { max-width: 1300px; margin: 0 auto; padding: 30px 20px; }

        .card {
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px; padding: 24px; backdrop-filter: blur(10px); margin-bottom: 24px;
        }
        .card-title { font-size: 1rem; font-weight: 600; color: #a0a8ff; margin-bottom: 16px;
            display: flex; align-items: center; gap: 8px; }

        .filter-row { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
        .filter-row label { color: #b0b8ff; font-size: 0.9rem; font-weight: 500; }
        select.fan-select {
            background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);
            color: #fff; padding: 10px 16px; border-radius: 10px; font-size: 0.9rem;
            min-width: 220px; cursor: pointer; outline: none; transition: border-color 0.2s;
        }
        select.fan-select:focus { border-color: #7c83fd; }
        select.fan-select option { background: #1e1e3f; }
        .btn-filter {
            background: linear-gradient(135deg, #7c83fd, #4a00e0); color: #fff;
            border: none; padding: 10px 22px; border-radius: 10px; font-size: 0.9rem;
            font-weight: 600; cursor: pointer; display: inline-flex; align-items: center;
            gap: 7px; transition: 0.2s;
        }
        .btn-filter:hover { transform: translateY(-1px); box-shadow: 0 4px 15px rgba(124,131,253,0.4); }

        /* Summary cards */
        .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 14px; margin-bottom: 24px; }
        .sum-card {
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px; padding: 16px 20px; text-align: center;
        }
        .sum-card .num { font-size: 1.8rem; font-weight: 700; }
        .sum-card .lbl { font-size: 0.78rem; color: #888; margin-top: 4px; }
        .sum-blue   .num { color: #74b9ff; }
        .sum-green  .num { color: #55efc4; }
        .sum-red    .num { color: #ff7675; }
        .sum-yellow .num { color: #fdcb6e; }

        /* Table */
        .table-wrap { overflow-x: auto; border-radius: 10px; }
        table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        thead th {
            background: rgba(124,131,253,0.2); color: #a0a8ff; font-weight: 600;
            padding: 13px 14px; text-align: center; white-space: nowrap;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        tbody tr { border-bottom: 1px solid rgba(255,255,255,0.05); transition: background 0.15s; }
        tbody tr:hover { background: rgba(255,255,255,0.04); }
        tbody td { padding: 11px 14px; text-align: center; vertical-align: middle; }
        .rank { font-weight: 700; color: #7c83fd; }
        .fio { text-align: left !important; font-weight: 500; }

        .badge-p {
            display: inline-block; padding: 4px 12px; border-radius: 20px;
            font-size: 0.8rem; font-weight: 700;
        }
        .p-high   { background: rgba(46,204,113,0.2); color: #55efc4; border: 1px solid rgba(46,204,113,0.3); }
        .p-mid    { background: rgba(243,156,18,0.2); color: #fdcb6e; border: 1px solid rgba(243,156,18,0.3); }
        .p-low    { background: rgba(231,76,60,0.2);  color: #ff7675; border: 1px solid rgba(231,76,60,0.3); }

        .empty-msg { text-align: center; padding: 50px 20px; color: #666; }
        .empty-msg i { font-size: 2.5rem; color: #444; margin-bottom: 12px; display: block; }

        @media (max-width: 600px) { .container { padding: 15px 10px; } }
    </style>
</head>
<body>

<?php include_once 'sidebar.php'; ?>

<div class="container">

    <!-- Filter -->
    <div class="card">
        <div class="card-title"><i class="fas fa-filter"></i> Fan bo'yicha filtrlash</div>
        <form action="natija.php" method="GET" class="filter-row">
            <label for="fan_sel">Fan:</label>
            <select name="fan" id="fan_sel" class="fan-select">
                <option value="">— Barcha fanlar —</option>
                <?php
                $fans = mysqli_query($link, "SELECT * FROM fan ORDER BY name ASC");
                while ($f = mysqli_fetch_assoc($fans)) {
                    $sel = ($fan_id_filter === (int)$f['id']) ? 'selected' : '';
                    echo '<option value="' . (int)$f['id'] . '" ' . $sel . '>'
                       . htmlspecialchars($f['name'], ENT_QUOTES, 'UTF-8') . '</option>';
                }
                ?>
            </select>
            <button type="submit" class="btn-filter">
                <i class="fas fa-search"></i> Ko'rish
            </button>
        </form>
    </div>

    <?php
    // WHERE sharti
    $where   = "";
    $params  = [];
    $types   = "";
    if ($fan_id_filter > 0) {
        $where  = "WHERE s.fan=?";
        $params = [$fan_id_filter];
        $types  = "i";
    }

    // Talabalar va natijalarini olish (JOIN)
    $sql_results = "
        SELECT
            s.id        AS student_id,
            s.fio,
            s.telefon,
            s.fakultet,
            f.name      AS fan_nomi,
            u.login,
            COALESCE(r.score,   0) AS score,
            COALESCE(r.wrong,   0) AS wrong_count,
            COALESCE(r.right_p, 0) AS right_p,
            r.boshlangan_vaqt,
            r.t_vaqt
        FROM students s
        LEFT JOIN fan      f ON s.fan = f.id
        LEFT JOIN user     u ON u.student_id = s.id
        LEFT JOIN results  r ON r.login = u.login
        $where
        ORDER BY r.score DESC, s.fio ASC
    ";

    if ($types) {
        $stmt_r = mysqli_prepare($link, $sql_results);
        mysqli_stmt_bind_param($stmt_r, $types, ...$params);
        mysqli_stmt_execute($stmt_r);
        $res = mysqli_stmt_get_result($stmt_r);
    } else {
        $res = mysqli_query($link, $sql_results);
    }

    $rows        = [];
    $total_score = 0;
    $has_results = 0;

    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
        if ($row['score'] > 0) {
            $total_score += $row['right_p'];
            $has_results++;
        }
    }

    $avg = $has_results > 0 ? round($total_score / $has_results, 1) : 0;
    $passed = count(array_filter($rows, fn($r) => $r['right_p'] >= 56));
    ?>

    <!-- Summary -->
    <?php if (!empty($rows)): ?>
    <div class="summary">
        <div class="sum-card sum-blue">
            <div class="num"><?= count($rows) ?></div>
            <div class="lbl">Jami talabalar</div>
        </div>
        <div class="sum-card sum-yellow">
            <div class="num"><?= $avg ?>%</div>
            <div class="lbl">O'rtacha natija</div>
        </div>
        <div class="sum-card sum-green">
            <div class="num"><?= $passed ?></div>
            <div class="lbl">O'tgan (≥56%)</div>
        </div>
        <div class="sum-card sum-red">
            <div class="num"><?= count($rows) - $passed ?></div>
            <div class="lbl">O'tmagan</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Jadval -->
    <div class="card">
        <div class="card-title"><i class="fas fa-table"></i> Natijalar jadvali</div>
        <?php if (empty($rows)): ?>
        <div class="empty-msg">
            <i class="fas fa-database"></i>
            Ma'lumot topilmadi
        </div>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>TALABA</th>
                        <th>TELEFON</th>
                        <th>FAKULTET</th>
                        <th>FAN</th>
                        <th>LOGIN</th>
                        <th>TO'G'RI</th>
                        <th>XATO</th>
                        <th>FOIZ</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $i => $r):
                    $p = (int)$r['right_p'];
                    $badge_cls = $p >= 86 ? 'p-high' : ($p >= 56 ? 'p-mid' : 'p-low');
                ?>
                <tr>
                    <td class="rank"><?= $i + 1 ?></td>
                    <td class="fio"><?= htmlspecialchars($r['fio'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($r['telefon'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($r['fakultet'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($r['fan_nomi'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><code><?= htmlspecialchars($r['login'] ?? '—', ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td style="color:#55efc4; font-weight:600;"><?= (int)$r['score'] ?></td>
                    <td style="color:#ff7675; font-weight:600;"><?= (int)$r['wrong_count'] ?></td>
                    <td><span class="badge-p <?= $badge_cls ?>"><?= $p ?>%</span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div>

    </div> <!-- sb-content -->
</div> <!-- sb-main -->

</body>
</html>