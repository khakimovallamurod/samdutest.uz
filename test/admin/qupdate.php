<?php
include_once 'ximoya.php';

// id_hash validatsiyasi uchun yordamchi
function validate_id_hash($val) {
    return (isset($val) && preg_match('/^[a-z0-9.]+$/i', $val)) ? $val : '';
}

// Savol yangilash
if (isset($_POST['updateq'])) {
    $id      = (int)($_POST['id'] ?? 0);
    $id_hash = validate_id_hash($_POST['id_hash'] ?? '');
    $savol   = filter($_POST['savol'] ?? '');

    if ($id > 0 && $id_hash) {
        $stmt = mysqli_prepare($link, "UPDATE savollar SET savol=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "si", $savol, $id);
        if (mysqli_stmt_execute($stmt)) {
            header("Location: question_add.php?id_hash=" . urlencode($id_hash) . "&report=ok");
            exit;
        }
    }
    header("Location: question_add.php?id_hash=" . urlencode($id_hash));
    exit;
}

// Variant yangilash
if (isset($_POST['updatev'])) {
    $id      = (int)($_POST['id'] ?? 0);
    $id_hash = validate_id_hash($_POST['id_hash'] ?? '');
    $variant = filter($_POST['variant'] ?? '');

    if ($id > 0 && $id_hash) {
        $stmt = mysqli_prepare($link, "UPDATE variantlar SET variant=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "si", $variant, $id);
        if (mysqli_stmt_execute($stmt)) {
            header("Location: question_add.php?id_hash=" . urlencode($id_hash) . "&report=ok");
            exit;
        }
    }
    header("Location: question_add.php?id_hash=" . urlencode($id_hash));
    exit;
}

// Savol tahrirlash uchun yuklash
$edit_savol   = null;
$edit_variant = null;
$id_hash      = validate_id_hash($_GET['id_hash'] ?? '');

if (!$id_hash) {
    header("Location: test_add.php");
    exit;
}

if (isset($_GET['q_id'])) {
    $q_id = (int)$_GET['q_id'];
    $stmt = mysqli_prepare($link, "SELECT * FROM savollar WHERE id=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $q_id);
    mysqli_stmt_execute($stmt);
    $edit_savol = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}

if (isset($_GET['v_id'])) {
    $v_id = (int)$_GET['v_id'];
    $stmt = mysqli_prepare($link, "SELECT * FROM variantlar WHERE id=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $v_id);
    mysqli_stmt_execute($stmt);
    $edit_variant = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}

if (!$edit_savol && !$edit_variant) {
    header("Location: question_add.php?id_hash=" . urlencode($id_hash));
    exit;
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tahrirlash | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            min-height: 100vh; color: #e0e0e0;
        }
        .container-form { display: flex; align-items: center; justify-content: center; min-height: 70vh; padding: 20px; }
        .card {
            background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.12);
            border-radius: 20px; padding: 36px; max-width: 620px; width: 100%;
            backdrop-filter: blur(14px);
        }
        .card-title {
            font-size: 1.1rem; font-weight: 700; color: #a0a8ff;
            margin-bottom: 24px; display: flex; align-items: center; gap: 10px;
        }
        .form-group { margin-bottom: 18px; }
        label { display: block; font-size: 0.85rem; color: #a0a8ff; margin-bottom: 7px; font-weight: 500; }
        textarea {
            width: 100%; padding: 12px 14px;
            background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.12);
            border-radius: 10px; color: #fff; font-size: 0.9rem; resize: vertical;
            min-height: 120px; outline: none; transition: border-color 0.2s; line-height: 1.6;
        }
        textarea:focus { border-color: #7c83fd; background: rgba(255,255,255,0.12); }

        .btn-row { display: flex; gap: 10px; margin-top: 8px; }
        .btn-save {
            flex: 1; background: linear-gradient(135deg, #7c83fd, #4a00e0);
            color: #fff; border: none; padding: 12px; border-radius: 10px;
            font-size: 0.95rem; font-weight: 700; cursor: pointer; transition: 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-save:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(124,131,253,0.4); }
        .btn-back {
            background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);
            color: #ccc; padding: 12px 22px; border-radius: 10px;
            text-decoration: none; font-size: 0.9rem; display: flex; align-items: center; gap: 7px;
            transition: 0.2s;
        }
        .btn-back:hover { background: rgba(255,255,255,0.15); }

        .type-badge {
            display: inline-block; padding: 4px 12px; border-radius: 20px;
            font-size: 0.78rem; font-weight: 600; margin-left: 8px;
        }
        .type-q { background: rgba(124,131,253,0.2); color: #a0a8ff; border: 1px solid rgba(124,131,253,0.3); }
        .type-v { background: rgba(243,156,18,0.2);  color: #fdcb6e; border: 1px solid rgba(243,156,18,0.3); }
    </style>
</head>
<body>

<?php include_once 'sidebar.php'; ?>
<div class="container-form">
<div class="card">
    <div class="card-title">
        <i class="fas fa-edit"></i>
        Tahrirlash
        <span class="type-badge <?= $edit_savol ? 'type-q' : 'type-v' ?>">
            <?= $edit_savol ? 'Savol' : 'Variant' ?>
        </span>
    </div>

    <?php if ($edit_savol): ?>
    <!-- Savol tahrirlash -->
    <form action="qupdate.php" method="POST">
        <div class="form-group">
            <label><i class="fas fa-question-circle"></i> Savol matni:</label>
            <textarea name="savol" required><?= htmlspecialchars($edit_savol['savol'], ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
        <input type="hidden" name="id"      value="<?= (int)$edit_savol['id'] ?>">
        <input type="hidden" name="id_hash" value="<?= htmlspecialchars($id_hash, ENT_QUOTES) ?>">
        <div class="btn-row">
            <a href="question_add.php?id_hash=<?= urlencode($id_hash) ?>" class="btn-back">
                <i class="fas fa-arrow-left"></i> Orqaga
            </a>
            <button type="submit" name="updateq" class="btn-save">
                <i class="fas fa-save"></i> Saqlash
            </button>
        </div>
    </form>

    <?php elseif ($edit_variant): ?>
    <!-- Variant tahrirlash -->
    <form action="qupdate.php" method="POST">
        <div class="form-group">
            <label><i class="fas fa-list-ul"></i> Variant matni:</label>
            <textarea name="variant" required><?= htmlspecialchars($edit_variant['variant'], ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
        <input type="hidden" name="id"      value="<?= (int)$edit_variant['id'] ?>">
        <input type="hidden" name="id_hash" value="<?= htmlspecialchars($id_hash, ENT_QUOTES) ?>">
        <div class="btn-row">
            <a href="question_add.php?id_hash=<?= urlencode($id_hash) ?>" class="btn-back">
                <i class="fas fa-arrow-left"></i> Orqaga
            </a>
            <button type="submit" name="updatev" class="btn-save">
                <i class="fas fa-save"></i> Saqlash
            </button>
        </div>
    </form>
    <?php endif; ?>
</div>
</div>
    </div> <!-- sb-content -->
</div> <!-- sb-main -->
</body>
</html>