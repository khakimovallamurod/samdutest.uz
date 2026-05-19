<?php
include_once 'ximoya.php';

$success = false;
$error   = '';

if (isset($_POST['ok'])) {
    $name = filter($_POST['name'] ?? '');

    if (empty($name)) {
        $error = "Nom bo'sh bo'lishi mumkin emas.";
    } else {
        $stmt = mysqli_prepare($link, "INSERT INTO category (name) VALUES (?)");
        mysqli_stmt_bind_param($stmt, "s", $name);
        if (mysqli_stmt_execute($stmt)) {
            header("Location: quiz.php?msg=created");
            exit;
        } else {
            $error = "Qo'shishda xatolik yuz berdi.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yangi Test Turi | Admin</title>
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
            border-radius: 20px; padding: 38px 36px; max-width: 480px; width: 100%;
            backdrop-filter: blur(14px);
        }
        .card-title { font-size: 1.1rem; font-weight: 700; color: #a0a8ff;
            margin-bottom: 24px; display: flex; align-items: center; gap: 10px; }
        .alert-error {
            background: rgba(231,76,60,0.12); border: 1px solid rgba(231,76,60,0.3);
            color: #ff7675; padding: 11px 16px; border-radius: 10px; margin-bottom: 18px;
            font-size: 0.87rem; display: flex; align-items: center; gap: 8px;
        }
        .form-group { margin-bottom: 18px; }
        label { display: block; font-size: 0.83rem; color: #a0a8ff; margin-bottom: 7px; font-weight: 500; }
        .form-input {
            width: 100%; padding: 11px 14px;
            background: rgba(255,255,255,0.08); border: 1.5px solid rgba(255,255,255,0.12);
            border-radius: 10px; color: #fff; font-size: 0.92rem; outline: none; transition: 0.2s;
        }
        .form-input::placeholder { color: rgba(255,255,255,0.25); }
        .form-input:focus { border-color: #7c83fd; background: rgba(124,131,253,0.08); }
        .btn-row { display: flex; gap: 10px; }
        .btn-submit {
            flex: 1; background: linear-gradient(135deg, #7c83fd, #4a00e0);
            color: #fff; border: none; padding: 12px; border-radius: 10px;
            font-size: 0.92rem; font-weight: 700; cursor: pointer; transition: 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-submit:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(124,131,253,0.4); }
        .btn-back {
            background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);
            color: #ccc; padding: 12px 20px; border-radius: 10px; text-decoration: none;
            font-size: 0.9rem; display: flex; align-items: center; gap: 7px; transition: 0.2s;
        }
        .btn-back:hover { background: rgba(255,255,255,0.15); }
    </style>
</head>
<?php include_once 'sidebar.php'; ?>
<div class="container-form">
<div class="card">
    <div class="card-title">
        <i class="fas fa-plus-circle"></i> Yangi test turi yaratish
    </div>

    <?php if ($error): ?>
    <div class="alert-error">
        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form action="quizcreate.php" method="POST">
        <div class="form-group">
            <label><i class="fas fa-tag"></i> Test turi nomi</label>
            <input type="text" name="name" class="form-input"
                   placeholder="Masalan: Matematika" required maxlength="100"
                   value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name'], ENT_QUOTES) : '' ?>">
        </div>
        <div class="btn-row">
            <a href="quiz.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Orqaga
            </a>
            <button type="submit" name="ok" class="btn-submit">
                <i class="fas fa-save"></i> Yaratish
            </button>
        </div>
    </form>
</div>
</div>
</div>
    </div> <!-- sb-content -->
</div> <!-- sb-main -->
</body>
</html>
