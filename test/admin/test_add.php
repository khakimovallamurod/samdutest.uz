<?php
include_once 'ximoya.php';

// Test o'chirish
if (isset($_GET['del_id'])) {
    $id = (int)$_GET['del_id'];
    $stmt = mysqli_prepare($link, "DELETE FROM testlar WHERE id=?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    header("Location: test_add.php");
    exit;
}

// Test yangilash
if (isset($_POST['update_test'])) {
    $id          = (int)$_POST['id'];
    $category_id = (int)$_POST['category_id'];
    $category    = filter($_POST['category']);
    $nomi        = filter($_POST['nomi']);
    $soni        = (int)$_POST['soni'];
    $total       = (int)$_POST['total'];
    $izoh        = filter($_POST['izoh']);
    $vaqt        = (int)$_POST['vaqt'];
    $count       = (int)$_POST['count'];

    $stmt = mysqli_prepare($link,
        "UPDATE testlar SET nomi=?,soni=?,total=?,vaqt=?,izoh=?,count=?,category=?,category_id=? WHERE id=?");
    mysqli_stmt_bind_param($stmt, "siiisisii",
        $nomi, $soni, $total, $vaqt, $izoh, $count, $category, $category_id, $id);

    if (mysqli_stmt_execute($stmt)) {
        header("Location: test_add.php");
        exit;
    } else {
        $error_msg = "Yangilashda xatolik yuz berdi.";
    }
}

// Yangi test qo'shish
if (isset($_POST['add_test'])) {
    $category_id = (int)$_POST['category_id'];
    $category    = filter($_POST['category']);
    $nomi        = filter($_POST['nomi']);
    $soni        = (int)$_POST['soni'];
    $total       = (int)$_POST['total'];
    $izoh        = filter($_POST['izoh']);
    $vaqt        = (int)$_POST['vaqt'];
    $count       = 1;
    $id_hash     = uniqid('', true);

    $stmt = mysqli_prepare($link,
        "INSERT INTO testlar (nomi,soni,total,vaqt,izoh,id_hash,count,category,category_id) VALUES (?,?,?,?,?,?,?,?,?)");
    mysqli_stmt_bind_param($stmt, "siiisisii",
        $nomi, $soni, $total, $vaqt, $izoh, $id_hash, $count, $category, $category_id);

    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['id_hash'] = $id_hash;
        header("Location: question_add.php?id_hash=" . urlencode($id_hash));
        exit;
    } else {
        $error_msg = "Test qo'shishda xatolik yuz berdi.";
    }
}

// Edit uchun test ma'lumotini olish
$edit_test = null;
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $stmt = mysqli_prepare($link, "SELECT * FROM testlar WHERE id=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $edit_id);
    mysqli_stmt_execute($stmt);
    $edit_test = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}

// Fanlar ro'yxati
$fans_result = mysqli_query($link, "SELECT * FROM fan WHERE status='1' ORDER BY name ASC");
$fans = [];
while ($f = mysqli_fetch_assoc($fans_result)) {
    $fans[] = $f;
}

// Barcha testlar
$testlar_result = mysqli_query($link, "SELECT * FROM testlar ORDER BY id DESC");
$testlar = [];
while ($t = mysqli_fetch_assoc($testlar_result)) {
    $testlar[] = $t;
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Boshqaruvi | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            min-height: 100vh;
            color: #e0e0e0;
        }
        .topnav {
            background: rgba(255,255,255,0.07);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding: 14px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
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

        /* TABS */
        .tabs { display: flex; gap: 4px; margin-bottom: 24px; }
        .tab-btn {
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
            color: #aaa; padding: 10px 22px; border-radius: 10px 10px 0 0;
            cursor: pointer; font-size: 0.9rem; transition: 0.2s;
        }
        .tab-btn.active, .tab-btn:hover {
            background: rgba(124,131,253,0.2); border-color: #7c83fd; color: #fff;
        }

        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .card {
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
            border-radius: 0 16px 16px 16px; padding: 28px;
            backdrop-filter: blur(10px);
        }
        .card h3 { font-size: 1.1rem; color: #a0a8ff; margin-bottom: 20px;
            display: flex; align-items: center; gap: 8px; }

        .alert-success {
            background: rgba(46,204,113,0.15); border: 1px solid rgba(46,204,113,0.3);
            color: #2ecc71; padding: 12px 16px; border-radius: 10px; margin-bottom: 16px;
        }
        .alert-error {
            background: rgba(231,76,60,0.15); border: 1px solid rgba(231,76,60,0.3);
            color: #e74c3c; padding: 12px 16px; border-radius: 10px; margin-bottom: 16px;
        }

        /* FORM */
        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 0.85rem; color: #a0a8ff; margin-bottom: 6px; font-weight: 500; }
        .form-control {
            width: 100%; padding: 10px 14px; background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.12); border-radius: 9px; color: #fff;
            font-size: 0.9rem; outline: none; transition: border-color 0.2s;
        }
        .form-control:focus { border-color: #7c83fd; background: rgba(255,255,255,0.12); }
        .form-control option { background: #1e1e3f; }
        textarea.form-control { resize: vertical; min-height: 90px; }

        .btn-submit {
            background: linear-gradient(135deg, #7c83fd, #4a00e0);
            color: #fff; border: none; padding: 11px 28px; border-radius: 10px;
            font-size: 0.9rem; font-weight: 600; cursor: pointer; transition: 0.2s;
            display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-submit:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(124,131,253,0.4); }

        /* TEST LIST */
        .test-list { display: flex; flex-direction: column; gap: 10px; }
        .test-item {
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08);
            border-radius: 10px; padding: 14px 18px;
            display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;
        }
        .test-item-name { font-weight: 500; color: #e0e0e0; }
        .test-item-name a { color: #7c83fd; text-decoration: none; }
        .test-item-name a:hover { text-decoration: underline; }
        .test-actions { display: flex; gap: 8px; }
        .btn-sm {
            padding: 6px 14px; border-radius: 7px; font-size: 0.8rem;
            border: none; cursor: pointer; font-weight: 500; text-decoration: none;
            display: inline-flex; align-items: center; gap: 5px; transition: 0.15s;
        }
        .btn-edit   { background: rgba(52,152,219,0.25); color: #74b9ff; border: 1px solid rgba(52,152,219,0.3); }
        .btn-delete { background: rgba(231,76,60,0.25);  color: #ff7675; border: 1px solid rgba(231,76,60,0.3); }
        .btn-sm:hover { transform: translateY(-1px); }
    </style>
</head>
<body>

<?php include_once 'sidebar.php'; ?>

<div class="container">

    <?php if (isset($error_msg)): ?>
    <div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['done'])): ?>
    <div class="alert-success"><i class="fas fa-check-circle"></i> Muvaffaqiyatli bajarildi!</div>
    <?php endif; ?>

    <div class="tabs">
        <button class="tab-btn <?= !isset($_GET['edit_id']) ? 'active' : '' ?>" onclick="switchTab('tab-add')">
            <i class="fas fa-plus-circle"></i> Yangi test kiritish
        </button>
        <button class="tab-btn <?= isset($_GET['edit_id']) ? 'active' : '' ?>" onclick="switchTab('tab-list')">
            <i class="fas fa-list"></i> Avvalgi testlar
        </button>
    </div>

    <!-- Yangi test qo'shish -->
    <div id="tab-add" class="tab-content <?= !isset($_GET['edit_id']) ? 'active' : '' ?>">
        <div class="card">
            <h3><i class="fas fa-file-alt"></i>
                <?= $edit_test ? 'Testni tahrirlash' : 'Yangi test yaratish' ?>
            </h3>
            <form action="test_add.php<?= $edit_test ? '?edit_id='.(int)$_GET['edit_id'] : '' ?>" method="POST">

                <div class="form-group">
                    <label>Kategoriya (fan)</label>
                    <select name="category" class="form-control" required>
                        <option value="">— Tanlang —</option>
                        <?php foreach ($fans as $f): ?>
                        <option value="<?= htmlspecialchars($f['name'], ENT_QUOTES) ?>"
                            <?= ($edit_test && $edit_test['category'] === $f['name']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($f['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Fan ID</label>
                    <select name="category_id" class="form-control" required>
                        <option value="">— Tanlang —</option>
                        <?php foreach ($fans as $f): ?>
                        <option value="<?= (int)$f['id'] ?>"
                            <?= ($edit_test && (int)$edit_test['category_id'] === (int)$f['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($f['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Test nomi</label>
                    <input type="text" name="nomi" class="form-control" required
                        value="<?= htmlspecialchars($edit_test['nomi'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px;">
                    <div class="form-group">
                        <label>Jami testlar soni</label>
                        <input type="number" name="soni" class="form-control" required min="1"
                            value="<?= (int)($edit_test['soni'] ?? 0) ?>">
                    </div>
                    <div class="form-group">
                        <label>Yechilishi lozim soni</label>
                        <input type="number" name="total" class="form-control" required min="1"
                            value="<?= (int)($edit_test['total'] ?? 0) ?>">
                    </div>
                    <div class="form-group">
                        <label>Vaqt (daqiqada)</label>
                        <input type="number" name="vaqt" class="form-control" required min="1"
                            value="<?= (int)($edit_test['vaqt'] ?? 0) ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Qisqacha izoh</label>
                    <textarea name="izoh" class="form-control" required><?= htmlspecialchars($edit_test['izoh'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <input type="hidden" name="count" value="1">
                <?php if ($edit_test): ?>
                <input type="hidden" name="id" value="<?= (int)$edit_test['id'] ?>">
                <button type="submit" name="update_test" class="btn-submit">
                    <i class="fas fa-save"></i> Saqlash
                </button>
                <?php else: ?>
                <button type="submit" name="add_test" class="btn-submit">
                    <i class="fas fa-plus"></i> Kiritish
                </button>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Testlar ro'yxati -->
    <div id="tab-list" class="tab-content <?= isset($_GET['edit_id']) ? 'active' : '' ?>">
        <div class="card">
            <h3><i class="fas fa-database"></i> Kiritilgan testlar</h3>
            <div class="test-list">
                <?php if (empty($testlar)): ?>
                <p style="color:#666; text-align:center; padding:30px;">Hali test kiritilmagan</p>
                <?php endif; ?>
                <?php foreach ($testlar as $t): ?>
                <div class="test-item">
                    <div class="test-item-name">
                        <a href="question_add.php?id_hash=<?= urlencode($t['id_hash']) ?>">
                            <i class="fas fa-file-alt"></i>
                            <?= htmlspecialchars($t['nomi'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                        <small style="color:#777; margin-left:10px;">
                            (<?= (int)$t['soni'] ?> ta savol, <?= (int)$t['vaqt'] ?> daqiqa)
                        </small>
                    </div>
                    <div class="test-actions">
                        <a href="test_add.php?edit_id=<?= (int)$t['id'] ?>" class="btn-sm btn-edit">
                            <i class="fas fa-edit"></i> Tahrirlash
                        </a>
                        <button class="btn-sm btn-delete"
                            onclick="udalit(<?= (int)$t['id'] ?>)">
                            <i class="fas fa-trash"></i> O'chirish
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

</div>

<script>
function switchTab(id) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    event.currentTarget.classList.add('active');
}

function udalit(id) {
    if (confirm("Bu testni o'chirishga ishonchingiz komilmi?\nBu amalni qaytarib bo'lmaydi!")) {
        window.location.href = 'test_add.php?del_id=' + id;
    }
}
</script>
    </div> <!-- sb-content -->
</div> <!-- sb-main -->

</body>
</html>