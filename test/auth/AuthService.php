<?php

require_once __DIR__ . '/../database/Database.php';
require_once __DIR__ . '/helpers.php';

final class AuthService
{
    public static function attemptLogin($identity, $password, $rememberMe = false)
    {
        $identity = trim((string) $identity);
        $password = (string) $password;

        if ($identity === '' || $password === '') {
            return ['ok' => false, 'message' => 'Login va parol majburiy.'];
        }

        try {
            $db = Database::connection();
            $user = self::findUserByIdentity($db, $identity);

            if (!$user) {
                return ['ok' => false, 'message' => 'Foydalanuvchi topilmadi.'];
            }

            $status = strtolower(trim((string) ($user['status'] ?? 'active')));
            if (!in_array($status, ['1', 'active', 'open'], true)) {
                return ['ok' => false, 'message' => 'Hisobingiz faollashtirilmagan.'];
            }

            if (!self::verifyPassword($password, (string) ($user['password'] ?? $user['parol'] ?? ''))) {
                return ['ok' => false, 'message' => 'Login yoki parol noto\'g\'ri.'];
            }

            $role = normalize_role($user['role'] ?? $user['rol'] ?? '');
            if ($role === '') {
                return ['ok' => false, 'message' => 'Rol aniqlanmadi.'];
            }

            self::createSession($user, $role, $rememberMe);

            if (self::needsPasswordRehash((string) ($user['password'] ?? $user['parol'] ?? ''))) {
                self::upgradePasswordHash($db, (int) $user['id'], $password);
            }

            return ['ok' => true, 'redirect' => role_redirect_path($role)];
        } catch (Throwable $e) {
            error_log('Auth login error: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Server xatosi. Qaytadan urinib ko\'ring.'];
        }
    }

    public static function register($payload)
    {
        $fullname = trim((string) ($payload['fullname'] ?? ''));
        $phone = trim((string) ($payload['phone'] ?? ''));
        $username = trim((string) ($payload['username'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));
        $password = (string) ($payload['password'] ?? '');
        $role = normalize_role($payload['role'] ?? 'student');

        if ($fullname === '' || $phone === '' || $username === '' || $email === '' || $password === '') {
            return ['ok' => false, 'message' => 'Barcha maydonlarni to\'ldiring.'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Email noto\'g\'ri formatda.'];
        }

        if (strlen($password) < 6) {
            return ['ok' => false, 'message' => 'Parol kamida 6 ta belgidan iborat bo\'lsin.'];
        }

        if (!in_array($role, ['teacher', 'student'], true)) {
            $role = 'student';
        }

        try {
            $db = Database::connection();
            $table = self::resolveUserTable($db);
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $status = 'active';

            $columns = self::existingColumns($db, $table);
            $createdAt = date('Y-m-d H:i:s');
            $roleRaw = in_array('role', $columns, true)
                ? $role
                : ($role === 'teacher' ? 'oqtuvchi' : 'talaba');

            $insertData = [
                'fullname' => $fullname,
                'phone' => $phone,
                'username' => $username,
                'login' => $username,
                'email' => $email,
                'password' => $hash,
                'parol' => $hash,
                'role' => $roleRaw,
                'rol' => $roleRaw,
                'status' => $status,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            $userId = self::insertDynamic($db, $table, $columns, $insertData);
            $ok = $userId > 0;

            if (!$ok) {
                return ['ok' => false, 'message' => 'Bu username yoki email band.'];
            }

            return ['ok' => true, 'message' => 'Ro\'yxatdan o\'tish muvaffaqiyatli.'];
        } catch (Throwable $e) {
            error_log('Auth register error: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Server xatosi.'];
        }
    }

    public static function registerTeacher($payload)
    {
        $fullname = trim((string) ($payload['fullname'] ?? ''));
        $username = trim((string) ($payload['username'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));
        $password = (string) ($payload['password'] ?? '');
        $phone = trim((string) ($payload['phone'] ?? ''));
        $department = trim((string) ($payload['department'] ?? ''));
        $subject = trim((string) ($payload['subject'] ?? ''));
        $position = trim((string) ($payload['position'] ?? ''));
        $experienceYears = (int) ($payload['experience_years'] ?? 0);

        if ($fullname === '' || $username === '' || $email === '' || $password === '' || $department === '' || $subject === '') {
            return ['ok' => false, 'message' => 'Majburiy maydonlarni to\'ldiring.'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Email noto\'g\'ri formatda.'];
        }

        if (strlen($password) < 6) {
            return ['ok' => false, 'message' => 'Parol kamida 6 ta belgidan iborat bo\'lsin.'];
        }

        try {
            $db = Database::connection();
            $table = self::resolveUserTable($db);
            $columns = self::existingColumns($db, $table);

            $db->begin_transaction();

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $createdAt = date('Y-m-d H:i:s');
            $status = 'active';
            $roleRaw = in_array('role', $columns, true) ? 'teacher' : 'oqtuvchi';

            $insertData = [
                'fullname' => $fullname,
                'username' => $username,
                'login' => $username,
                'email' => $email,
                'password' => $passwordHash,
                'parol' => $passwordHash,
                'role' => $roleRaw,
                'rol' => $roleRaw,
                'status' => $status,
                'created_at' => $createdAt,
                'fan_id' => null,
                'student_id' => null,
            ];

            $userId = self::insertDynamic($db, $table, $columns, $insertData);
            if ($userId <= 0) {
                $db->rollback();
                return ['ok' => false, 'message' => 'Ro\'yxatdan o\'tishda xatolik (username yoki email band bo\'lishi mumkin).'];
            }

            self::ensureTeacherProfilesTable($db);
            $stmt = $db->prepare('INSERT INTO teacher_profiles (user_id, phone, department, subject, position, experience_years, approved, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())');
            if (!$stmt) {
                $db->rollback();
                return ['ok' => false, 'message' => 'O\'qituvchi profili saqlanmadi.'];
            }
            $stmt->bind_param('issssi', $userId, $phone, $department, $subject, $position, $experienceYears);
            $ok = $stmt->execute();
            $stmt->close();

            if (!$ok) {
                $db->rollback();
                return ['ok' => false, 'message' => 'O\'qituvchi profili saqlanmadi.'];
            }

            $db->commit();
            return ['ok' => true, 'message' => 'O\'qituvchi akkaunti muvaffaqiyatli yaratildi.'];
        } catch (Throwable $e) {
            if (isset($db) && $db instanceof mysqli) {
                $db->rollback();
            }
            error_log('Teacher register error: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Server xatosi. Qaytadan urinib ko\'ring.'];
        }
    }

    public static function requestPasswordReset($identity)
    {
        $identity = trim((string) $identity);
        if ($identity === '') {
            return ['ok' => false, 'message' => 'Username yoki email kiriting.'];
        }

        try {
            $db = Database::connection();
            $user = self::findUserByIdentity($db, $identity);
            if (!$user) {
                return ['ok' => true, 'message' => 'Agar akkaunt mavjud bo\'lsa, reset link yuborildi.'];
            }

            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);

            $db->query("CREATE TABLE IF NOT EXISTS password_resets (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, token_hash VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(user_id), INDEX(token_hash)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $sql = "INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))";
            $stmt = $db->prepare($sql);
            if ($stmt) {
                $uid = (int) $user['id'];
                $stmt->bind_param('is', $uid, $tokenHash);
                $stmt->execute();
                $stmt->close();
            }

            return ['ok' => true, 'message' => 'Reset token: ' . $token];
        } catch (Throwable $e) {
            error_log('Password reset request error: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Server xatosi.'];
        }
    }

    public static function resetPassword($token, $newPassword)
    {
        $token = trim((string) $token);
        $newPassword = (string) $newPassword;

        if ($token === '' || strlen($newPassword) < 6) {
            return ['ok' => false, 'message' => 'Token yoki yangi parol noto\'g\'ri.'];
        }

        try {
            $db = Database::connection();
            $db->query("CREATE TABLE IF NOT EXISTS password_resets (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, token_hash VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(user_id), INDEX(token_hash)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $tokenHash = hash('sha256', $token);
            $sql = "SELECT id, user_id FROM password_resets WHERE token_hash=? AND expires_at > NOW() ORDER BY id DESC LIMIT 1";
            $stmt = $db->prepare($sql);
            if (!$stmt) {
                return ['ok' => false, 'message' => 'Server xatosi.'];
            }
            $stmt->bind_param('s', $tokenHash);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            if (!$row) {
                return ['ok' => false, 'message' => 'Token yaroqsiz yoki muddati tugagan.'];
            }

            $table = self::resolveUserTable($db);
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $up = $db->prepare("UPDATE {$table} SET password=? WHERE id=?");
            if (!$up) {
                return ['ok' => false, 'message' => 'Server xatosi.'];
            }
            $uid = (int) $row['user_id'];
            $up->bind_param('si', $hash, $uid);
            $up->execute();
            $up->close();

            $db->query('DELETE FROM password_resets WHERE user_id=' . $uid);

            return ['ok' => true, 'message' => 'Parol yangilandi.'];
        } catch (Throwable $e) {
            error_log('Reset password error: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Server xatosi.'];
        }
    }

    private static function createSession($user, $role, $rememberMe)
    {
        start_secure_session();
        session_regenerate_id(true);

        $_SESSION['auth_user'] = [
            'id' => (int) $user['id'],
            'fullname' => (string) ($user['fullname'] ?? ''),
            'username' => (string) ($user['username'] ?? $user['login'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'role' => $role,
            'logged_at' => time(),
        ];

        $_SESSION['id'] = (int) $user['id'];
        $_SESSION['login'] = (string) ($user['username'] ?? $user['login'] ?? '');
        $_SESSION['rol'] = $role === 'student' ? 'talaba' : $role;
        $_SESSION['kirish_vaqti'] = time();

        if ($rememberMe) {
            $days = (int) app_config()['remember_me_days'];
            setcookie(session_name(), session_id(), [
                'expires' => time() + ($days * 86400),
                'path' => '/',
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    private static function verifyPassword($plainPassword, $storedPassword)
    {
        if ($storedPassword === '') {
            return false;
        }

        if (password_get_info($storedPassword)['algo'] !== null && password_verify($plainPassword, $storedPassword)) {
            return true;
        }

        return hash_equals($storedPassword, $plainPassword) || hash_equals($storedPassword, md5($plainPassword));
    }

    private static function needsPasswordRehash($storedPassword)
    {
        $info = password_get_info($storedPassword);

        if ($info['algo'] === null) {
            return true;
        }

        return password_needs_rehash($storedPassword, PASSWORD_DEFAULT);
    }

    private static function upgradePasswordHash($db, $userId, $plainPassword)
    {
        $table = self::resolveUserTable($db);
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);

        foreach (['password', 'parol'] as $column) {
            $sql = "UPDATE {$table} SET {$column}=? WHERE id=?";
            $stmt = $db->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('si', $hash, $userId);
                if ($stmt->execute()) {
                    $stmt->close();
                    return;
                }
                $stmt->close();
            }
        }
    }

    private static function findUserByIdentity($db, $identity)
    {
        $table = self::resolveUserTable($db);

        $columns = self::existingColumns($db, $table);
        if (empty($columns)) {
            throw new RuntimeException('Users table columns are not readable.');
        }

        $usernameCol = in_array('username', $columns, true) ? 'username' : 'login';
        $passwordCol = in_array('password', $columns, true) ? 'password' : 'parol';
        $roleCol = in_array('role', $columns, true) ? 'role' : 'rol';
        $emailCol = in_array('email', $columns, true) ? 'email' : null;
        $statusCol = in_array('status', $columns, true) ? 'status' : null;
        $fullnameCol = in_array('fullname', $columns, true) ? 'fullname' : null;
        $fanIdCol = in_array('fan_id', $columns, true) ? 'fan_id' : null;
        $studentIdCol = in_array('student_id', $columns, true) ? 'student_id' : null;

        $selectEmail = $emailCol ? "{$emailCol} AS email" : "NULL AS email";
        $selectStatus = $statusCol ? "{$statusCol} AS status" : "'active' AS status";
        $selectFullname = $fullnameCol ? "{$fullnameCol} AS fullname" : "NULL AS fullname";
        $selectFanId = $fanIdCol ? "{$fanIdCol} AS fan_id" : "NULL AS fan_id";
        $selectStudentId = $studentIdCol ? "{$studentIdCol} AS student_id" : "NULL AS student_id";

        $where = "{$usernameCol}=?";
        $types = 's';
        $params = [$identity];
        if ($emailCol) {
            $where .= " OR {$emailCol}=?";
            $types .= 's';
            $params[] = $identity;
        }

        $sql = "SELECT id, {$usernameCol} AS username, {$selectEmail}, {$passwordCol} AS password, {$roleCol} AS role, {$selectStatus}, {$selectFullname}, {$selectFanId}, {$selectStudentId} FROM {$table} WHERE {$where} LIMIT 1";
        $stmt = $db->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException('Unable to prepare auth query.');
        }

        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        return $user;
    }

    private static function resolveUserTable($db)
    {
        $result = $db->query("SHOW TABLES LIKE 'users'");
        if ($result && $result->num_rows > 0) {
            return 'users';
        }

        $result = $db->query("SHOW TABLES LIKE 'user'");
        if ($result && $result->num_rows > 0) {
            return 'user';
        }

        throw new RuntimeException('Users table is missing.');
    }

    private static function existingColumns($db, $table)
    {
        $columns = [];
        $res = $db->query("SHOW COLUMNS FROM {$table}");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $columns[] = $row['Field'];
            }
        }
        return $columns;
    }

    private static function ensureTeacherProfilesTable($db)
    {
        $sql = "CREATE TABLE IF NOT EXISTS teacher_profiles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            phone VARCHAR(30) DEFAULT NULL,
            department VARCHAR(150) NOT NULL,
            subject VARCHAR(150) NOT NULL,
            position VARCHAR(120) DEFAULT NULL,
            experience_years INT NOT NULL DEFAULT 0,
            approved TINYINT(1) NOT NULL DEFAULT 0,
            approved_by INT DEFAULT NULL,
            approved_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_teacher_user (user_id),
            KEY idx_teacher_department (department),
            KEY idx_teacher_subject (subject)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $db->query($sql);
    }

    private static function insertDynamic($db, $table, $columns, $data)
    {
        $allowed = [];
        foreach ($data as $field => $value) {
            if (in_array($field, $columns, true)) {
                $allowed[$field] = $value;
            }
        }

        if (empty($allowed)) {
            return 0;
        }

        $fields = array_keys($allowed);
        $placeholders = implode(',', array_fill(0, count($fields), '?'));
        $sql = "INSERT INTO {$table} (" . implode(',', $fields) . ") VALUES ({$placeholders})";
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return 0;
        }

        $types = '';
        $values = [];
        foreach ($allowed as $value) {
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
            $values[] = $value;
        }

        $stmt->bind_param($types, ...$values);
        $ok = $stmt->execute();
        $insertId = $ok ? (int) $db->insert_id : 0;
        $stmt->close();
        return $insertId;
    }
}
