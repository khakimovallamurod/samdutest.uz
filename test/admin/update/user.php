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

$id = (int) ($_POST['id'] ?? 0);
$fullname = trim((string) ($_POST['fullname'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$username = trim((string) ($_POST['username'] ?? ''));
$role = strtolower(trim((string) ($_POST['role'] ?? 'student')));
$status = strtolower(trim((string) ($_POST['status'] ?? 'active')));

if ($id <= 0 || $fullname === '' || $phone === '' || $email === '' || $username === '') {
    flash_set('error', 'Maydonlarni to\'g\'ri kiriting.');
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
    $sql = 'UPDATE users SET fullname=?, phone=?, email=?, username=?, role=?, status=?, updated_at=NOW() WHERE id=?';
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Prepare error');
    }
    $stmt->bind_param('ssssssi', $fullname, $phone, $email, $username, $role, $status, $id);
    $stmt->execute();
    $stmt->close();

    flash_set('success', 'Ma\'lumot yangilandi.');
} catch (Throwable $e) {
    flash_set('error', 'Yangilashda xatolik: username/email/phone band bo\'lishi mumkin.');
}

redirect('/test/admin/dashboard.php');
