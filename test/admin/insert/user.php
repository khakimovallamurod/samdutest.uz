<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../database/Database.php';

require_auth(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/test/admin/dashboard.php');
}

if (!verify_csrf($_POST['_csrf'] ?? '')) {
    flash_set('error', 'Xavfsizlik tekshiruvi muvaffaqiyatsiz.');
    redirect('/test/admin/dashboard.php');
}

$fullname = trim((string) ($_POST['fullname'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$role = strtolower(trim((string) ($_POST['role'] ?? 'student')));
$status = strtolower(trim((string) ($_POST['status'] ?? 'active')));

if ($fullname === '' || $phone === '' || $email === '' || $username === '' || $password === '') {
    flash_set('error', 'Maydonlarni to\'ldiring.');
    redirect('/test/admin/dashboard.php');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash_set('error', 'Email formati noto\'g\'ri.');
    redirect('/test/admin/dashboard.php');
}

if (!in_array($role, ['admin', 'teacher', 'student'], true)) {
    $role = 'student';
}
if (!in_array($status, ['active', 'blocked', 'pending'], true)) {
    $status = 'active';
}

try {
    $db = Database::connection();
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $sql = 'INSERT INTO users (fullname, phone, email, username, password, role, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())';
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Prepare error');
    }
    $stmt->bind_param('sssssss', $fullname, $phone, $email, $username, $hash, $role, $status);
    $stmt->execute();
    $stmt->close();

    flash_set('success', 'Muvaffaqiyatli saqlandi.');
} catch (Throwable $e) {
    flash_set('error', 'Saqlashda xatolik: username/email/phone band bo\'lishi mumkin.');
}

redirect('/test/admin/dashboard.php');
