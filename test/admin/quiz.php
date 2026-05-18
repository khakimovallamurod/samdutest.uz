<?php
include_once 'ximoya.php';

// Kategoriya o'chirish
if (isset($_GET['del_id'])) {
    $id   = (int)$_GET['del_id'];
    $stmt = mysqli_prepare($link, "DELETE FROM category WHERE id=?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    header("Location: quiz.php?msg=deleted");
    exit;
}

// Kategoriyalar ro'yxati
$cats = [];
$res  = mysqli_query($link, "SELECT * FROM category ORDER BY name ASC");
while ($row = mysqli_fetch_assoc($res)) {
    $cats[] = $row;
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Turlari | Admin</title>
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
        .topnav-links { display: flex; align-items: center; gap: 10px; }
        .btn-nav {
            padding: 7px 16px; border-radius: 8px; text-decoration: none;
            font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; transition: 0.2s;
        }
        .btn-add    { background: rgba(46,204,113,0.2);  border: 1px solid rgba(46,204,113,0.4); color: #55efc4; }
        .btn-logout { background: rgba(231,76,60,0.2);   border: 1px solid rgba(231,76,60,0.5);  color: #e74c3c; }
        .btn-nav:hover { filter: brightness(1.2); }

        .container { max-width: 900px; margin: 0 auto; padding: 30px 20px; }

        .alert {
            padding: 12px 18px; border-radius: 10px; margin-bottom: 20px;
            font-size: 0.87rem; display: flex; align-items: center; gap: 8px;
        }
        .alert-success { background: rgba(46,204,113,0.12); border: 1px solid rgba(46,204,113,0.3); color: #55efc4; }

        .card {
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px; padding: 24px; backdrop-filter: blur(10px);
        }
        .card-title { font-size: 1rem; font-weight: 600; color: #a0a8ff; margin-bottom: 20px;
            display: flex; align-items: center; gap: 8px; }

        .cat-list { display: flex; flex-direction: column; gap: 10px; }
        .cat-item {
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px; padding: 14px 20px;
            display: flex; align-items: center; justify-content: space-between; gap: 10px;
        }
        .cat-num  { color: #7c83fd; font-weight: 700; min-width: 28px; }
        .cat-name { color: #e0e0e0; font-size: 0.95rem; flex: 1; }
        .btn-del {
            background: rgba(231,76,60,0.2); border: 1px solid rgba(231,76,60,0.3);
            color: #ff7675; padding: 7px 16px; border-radius: 8px; font-size: 0.82rem;
            cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: 0.2s;
        }
        .btn-del:hover { background: rgba(231,76,60,0.35); transform: translateY(-1px); }

        .empty-msg { text-align: center; padding: 50px; color: #666; }
        .empty-msg i { font-size: 2.5rem; display: block; margin-bottom: 12px; color: #444; }
    </style>
</head>
<body>

<?php include_once 'sidebar.php'; ?>

<div class="container">

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> Muvaffaqiyatli o'chirildi
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-title">
            <i class="fas fa-list"></i>
            Test turlari ro'yxati
            <span style="margin-left:auto; font-size:0.8rem; color:#666;">(<?= count($cats) ?> ta)</span>
        </div>

        <?php if (empty($cats)): ?>
        <div class="empty-msg">
            <i class="fas fa-inbox"></i>
            Hali test turi kiritilmagan
        </div>
        <?php else: ?>
        <div class="cat-list">
            <?php foreach ($cats as $i => $cat): ?>
            <div class="cat-item">
                <span class="cat-num"><?= $i + 1 ?>.</span>
                <span class="cat-name">
                    <i class="fas fa-tag" style="color:#7c83fd; margin-right:8px; font-size:0.85rem;"></i>
                    <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                </span>
                <button class="btn-del" onclick="uchir(<?= (int)$cat['id'] ?>)">
                    <i class="fas fa-trash"></i> O'chirish
                </button>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function uchir(id) {
    if (confirm("Bu test turini o'chirishga ishonchingiz komilmi?\nBu amalni qaytarib bo'lmaydi!")) {
        window.location.href = 'quiz.php?del_id=' + id;
    }
}
</script>
    </div> <!-- sb-content -->
</div> <!-- sb-main -->

</body>
</html>
