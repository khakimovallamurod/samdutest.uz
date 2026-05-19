<?php

require_once __DIR__ . '/../database/Database.php';

function db_conn()
{
    return Database::connection();
}

function table_exists(mysqli $db, $table)
{
    $safe = $db->real_escape_string($table);
    $res = $db->query("SHOW TABLES LIKE '{$safe}'");
    return $res && $res->num_rows > 0;
}

function column_exists(mysqli $db, $table, $column)
{
    $safeTable = $db->real_escape_string($table);
    $safeColumn = $db->real_escape_string($column);
    $res = $db->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return $res && $res->num_rows > 0;
}

function scalar_query(mysqli $db, $sql)
{
    $res = $db->query($sql);
    if (!$res) {
        return 0;
    }
    $row = $res->fetch_row();
    return (int) ($row[0] ?? 0);
}

function stmt_fetch_assoc(mysqli_stmt $stmt)
{
    if (method_exists($stmt, 'get_result')) {
        $result = $stmt->get_result();
        if ($result instanceof mysqli_result) {
            return $result->fetch_assoc() ?: null;
        }
    }

    $meta = $stmt->result_metadata();
    if (!$meta) {
        return null;
    }

    $row = [];
    $bind = [];
    while ($field = $meta->fetch_field()) {
        $row[$field->name] = null;
        $bind[] = &$row[$field->name];
    }

    call_user_func_array([$stmt, 'bind_result'], $bind);
    if (!$stmt->fetch()) {
        return null;
    }

    $assoc = [];
    foreach ($row as $k => $v) {
        $assoc[$k] = $v;
    }
    return $assoc;
}

function stmt_fetch_all_assoc(mysqli_stmt $stmt)
{
    if (method_exists($stmt, 'get_result')) {
        $result = $stmt->get_result();
        $rows = [];
        while ($result && ($row = $result->fetch_assoc())) {
            $rows[] = $row;
        }
        return $rows;
    }

    $meta = $stmt->result_metadata();
    if (!$meta) {
        return [];
    }

    $row = [];
    $bind = [];
    while ($field = $meta->fetch_field()) {
        $row[$field->name] = null;
        $bind[] = &$row[$field->name];
    }

    call_user_func_array([$stmt, 'bind_result'], $bind);
    $rows = [];
    while ($stmt->fetch()) {
        $item = [];
        foreach ($row as $k => $v) {
            $item[$k] = $v;
        }
        $rows[] = $item;
    }
    return $rows;
}

function get_system_counts()
{
    $db = db_conn();

    $usersTable = table_exists($db, 'users') ? 'users' : (table_exists($db, 'user') ? 'user' : null);
    $testsTable = table_exists($db, 'tests') ? 'tests' : (table_exists($db, 'testlar') ? 'testlar' : null);
    $resultsTable = table_exists($db, 'results') ? 'results' : null;

    $counts = [
        'users' => 0,
        'teachers' => 0,
        'students' => 0,
        'tests' => 0,
        'results' => 0,
    ];

    if ($usersTable === 'users') {
        $counts['users'] = scalar_query($db, "SELECT COUNT(*) FROM users");
        $counts['teachers'] = scalar_query($db, "SELECT COUNT(*) FROM users WHERE role='teacher'");
        $counts['students'] = scalar_query($db, "SELECT COUNT(*) FROM users WHERE role='student'");
    } elseif ($usersTable === 'user') {
        $counts['users'] = scalar_query($db, "SELECT COUNT(*) FROM user");
        $counts['teachers'] = scalar_query($db, "SELECT COUNT(*) FROM user WHERE LOWER(rol) IN ('teacher','oqtuvchi','o`qituvchi')");
        $counts['students'] = scalar_query($db, "SELECT COUNT(*) FROM user WHERE LOWER(rol) IN ('talaba','student','user')");
    }

    if ($testsTable) {
        $counts['tests'] = scalar_query($db, "SELECT COUNT(*) FROM {$testsTable}");
    }

    if ($resultsTable) {
        $counts['results'] = scalar_query($db, "SELECT COUNT(*) FROM results");
    }

    return $counts;
}

function get_admin_users($limit = 20)
{
    $db = db_conn();
    if (!table_exists($db, 'users')) {
        return [];
    }

    $limit = max(1, min(200, (int) $limit));
    $sql = "SELECT id, fullname, username, email, phone, role, status, created_at FROM users ORDER BY id DESC LIMIT ?";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $rows[] = $row;
    }
    $stmt->close();

    return $rows;
}

function get_teacher_metrics($teacherId)
{
    $db = db_conn();
    ensure_test_runtime_tables($db);
    $teacherId = (int) $teacherId;

    $tests = 0;
    $results = 0;

    if (table_exists($db, 'tests')) {
        $stmt = $db->prepare('SELECT COUNT(*) FROM tests WHERE teacher_id=?');
        if ($stmt) {
            $stmt->bind_param('i', $teacherId);
            $stmt->execute();
            $stmt->bind_result($tests);
            $stmt->fetch();
            $stmt->close();
        }

        if (table_exists($db, 'test_attempts')) {
            $stmt2 = $db->prepare("SELECT COUNT(*) FROM test_attempts WHERE teacher_id=? AND status<>'in_progress'");
            if ($stmt2) {
                $stmt2->bind_param('i', $teacherId);
                $stmt2->execute();
                $stmt2->bind_result($results);
                $stmt2->fetch();
                $stmt2->close();
            }
        } else {
            $sql = 'SELECT COUNT(*) FROM results r INNER JOIN tests t ON t.id = r.test_id WHERE t.teacher_id=?';
            $stmt2 = $db->prepare($sql);
            if ($stmt2) {
                $stmt2->bind_param('i', $teacherId);
                $stmt2->execute();
                $stmt2->bind_result($results);
                $stmt2->fetch();
                $stmt2->close();
            }
        }
    }

    return [
        'tests' => (int) $tests,
        'results' => (int) $results,
    ];
}

function get_teacher_attempts($teacherId, $testId = 0, $limit = 300)
{
    $db = db_conn();
    ensure_test_runtime_tables($db);
    $teacherId = (int) $teacherId;
    $testId = (int) $testId;
    $limit = max(1, min(1000, (int) $limit));

    $studentExpr = "CONCAT('Student #', a.student_id)";
    if (table_exists($db, 'users')) {
        $studentExpr = "COALESCE(NULLIF(TRIM(u.fullname),''), NULLIF(TRIM(u.username),''), CONCAT('Student #', a.student_id))";
    }

    $joinUsers = table_exists($db, 'users') ? " LEFT JOIN users u ON u.id=a.student_id " : "";
    $baseSql = "SELECT a.id, a.test_id, a.student_id, a.correct_count, a.wrong_count, a.pending_count, a.score_percent, a.status, a.started_at, a.submitted_at,
                       t.title AS test_title, s.name AS subject_name, {$studentExpr} AS student_name
                FROM test_attempts a
                LEFT JOIN tests t ON t.id=a.test_id
                LEFT JOIN subjects s ON s.id=a.subject_id
                {$joinUsers}
                WHERE a.teacher_id=? AND a.status<>'in_progress'";

    if ($testId > 0) {
        $sql = $baseSql . " AND a.test_id=? ORDER BY a.id DESC LIMIT ?";
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('iii', $teacherId, $testId, $limit);
    } else {
        $sql = $baseSql . " ORDER BY a.id DESC LIMIT ?";
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('ii', $teacherId, $limit);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function get_teacher_tests($teacherId, $limit = 20)
{
    $db = db_conn();
    if (!table_exists($db, 'tests')) {
        return [];
    }

    $teacherId = (int) $teacherId;
    $limit = max(1, min(100, (int) $limit));
    $sql = 'SELECT id, title, status, created_at FROM tests WHERE teacher_id=? ORDER BY id DESC LIMIT ?';
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('ii', $teacherId, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $rows[] = $row;
    }
    $stmt->close();

    return $rows;
}

function get_student_metrics($studentId)
{
    $db = db_conn();
    ensure_test_runtime_tables($db);
    $studentId = (int) $studentId;

    $availableTests = 0;
    $myResults = 0;

    if (table_exists($db, 'tests') && table_exists($db, 'test_questions')) {
        $availableTests = scalar_query($db, "SELECT COUNT(*) FROM tests t WHERE COALESCE(t.visibility,'open')='open' AND EXISTS (SELECT 1 FROM test_questions q WHERE q.test_id=t.id)");
    }

    if (table_exists($db, 'test_attempts')) {
        $stmtA = $db->prepare("SELECT COUNT(*) FROM test_attempts WHERE student_id=? AND status<>'in_progress'");
        if ($stmtA) {
            $stmtA->bind_param('i', $studentId);
            $stmtA->execute();
            $stmtA->bind_result($myResults);
            $stmtA->fetch();
            $stmtA->close();
        }
    } elseif (table_exists($db, 'results')) {
        $ownerColumn = column_exists($db, 'results', 'user_id') ? 'user_id' : (column_exists($db, 'results', 'student_id') ? 'student_id' : 'user_id');
        $stmt = $db->prepare("SELECT COUNT(*) FROM results WHERE {$ownerColumn}=?");
        if ($stmt) {
            $stmt->bind_param('i', $studentId);
            $stmt->execute();
            $stmt->bind_result($myResults);
            $stmt->fetch();
            $stmt->close();
        }
    }

    return [
        'available_tests' => (int) $availableTests,
        'my_results' => (int) $myResults,
    ];
}

function get_student_results($studentId, $limit = 20)
{
    $db = db_conn();
    if (!table_exists($db, 'results')) {
        return [];
    }

    $studentId = (int) $studentId;
    $limit = max(1, min(100, (int) $limit));

    $joinTests = table_exists($db, 'tests') && column_exists($db, 'results', 'test_id');
    $ownerColumn = column_exists($db, 'results', 'user_id') ? 'user_id' : (column_exists($db, 'results', 'student_id') ? 'student_id' : 'user_id');
    $scoreColumn = column_exists($db, 'results', 'score') ? 'score' : 'right_p';
    $takenAtExpr = column_exists($db, 'results', 'taken_at')
        ? 'r.taken_at'
        : (column_exists($db, 'results', 'boshlangan_vaqt') ? 'FROM_UNIXTIME(r.boshlangan_vaqt)' : (column_exists($db, 'results', 't_vaqt') ? 'r.t_vaqt' : 'NULL'));

    if ($joinTests) {
        $titleExpr = column_exists($db, 'tests', 'title') ? 't.title' : 'CONCAT("Test #", r.test_id)';
        $sql = "SELECT r.id, r.{$scoreColumn} AS score, {$takenAtExpr} AS taken_at, {$titleExpr} AS test_title
                FROM results r
                LEFT JOIN tests t ON t.id=r.test_id
                WHERE r.{$ownerColumn}=?
                ORDER BY r.id DESC
                LIMIT ?";
    } else {
        $testTitle = column_exists($db, 'results', 'test_id') ? 'CONCAT("Test #", r.test_id)' : "'Test'";
        $sql = "SELECT r.id, r.{$scoreColumn} AS score, {$takenAtExpr} AS taken_at, {$testTitle} AS test_title
                FROM results r
                WHERE r.{$ownerColumn}=?
                ORDER BY r.id DESC
                LIMIT ?";
    }

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('ii', $studentId, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $rows[] = $row;
    }
    $stmt->close();

    return $rows;
}

function ensure_teacher_tables(mysqli $db)
{
    $db->query("CREATE TABLE IF NOT EXISTS subjects (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT UNSIGNED NOT NULL,
        name VARCHAR(150) NOT NULL,
        status ENUM('active','inactive') NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_teacher_subject (teacher_id, name),
        KEY idx_subject_teacher (teacher_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS tests (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT UNSIGNED NOT NULL,
        subject_id INT UNSIGNED DEFAULT NULL,
        title VARCHAR(255) NOT NULL,
        question_text TEXT DEFAULT NULL,
        test_type ENUM('open','closed') NOT NULL DEFAULT 'closed',
        option_a TEXT DEFAULT NULL,
        option_b TEXT DEFAULT NULL,
        option_c TEXT DEFAULT NULL,
        option_d TEXT DEFAULT NULL,
        correct_option ENUM('A','B','C','D') DEFAULT NULL,
        visibility ENUM('open','closed') NOT NULL DEFAULT 'open',
        private_code VARCHAR(32) DEFAULT NULL,
        attempts_limit INT UNSIGNED NOT NULL DEFAULT 1,
        question_limit INT UNSIGNED NOT NULL DEFAULT 10,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_tests_teacher (teacher_id),
        KEY idx_tests_subject (subject_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if (!column_exists($db, 'tests', 'subject_id')) $db->query("ALTER TABLE tests ADD COLUMN subject_id INT UNSIGNED DEFAULT NULL");
    if (!column_exists($db, 'tests', 'question_text')) $db->query("ALTER TABLE tests ADD COLUMN question_text TEXT DEFAULT NULL");
    if (!column_exists($db, 'tests', 'test_type')) $db->query("ALTER TABLE tests ADD COLUMN test_type ENUM('open','closed') NOT NULL DEFAULT 'closed'");
    if (!column_exists($db, 'tests', 'option_a')) $db->query("ALTER TABLE tests ADD COLUMN option_a TEXT DEFAULT NULL");
    if (!column_exists($db, 'tests', 'option_b')) $db->query("ALTER TABLE tests ADD COLUMN option_b TEXT DEFAULT NULL");
    if (!column_exists($db, 'tests', 'option_c')) $db->query("ALTER TABLE tests ADD COLUMN option_c TEXT DEFAULT NULL");
    if (!column_exists($db, 'tests', 'option_d')) $db->query("ALTER TABLE tests ADD COLUMN option_d TEXT DEFAULT NULL");
    if (!column_exists($db, 'tests', 'correct_option')) $db->query("ALTER TABLE tests ADD COLUMN correct_option ENUM('A','B','C','D') DEFAULT NULL");
    if (!column_exists($db, 'tests', 'visibility')) $db->query("ALTER TABLE tests ADD COLUMN visibility ENUM('open','closed') NOT NULL DEFAULT 'open'");
    if (!column_exists($db, 'tests', 'private_code')) $db->query("ALTER TABLE tests ADD COLUMN private_code VARCHAR(32) DEFAULT NULL");
    if (!column_exists($db, 'tests', 'attempts_limit')) $db->query("ALTER TABLE tests ADD COLUMN attempts_limit INT UNSIGNED NOT NULL DEFAULT 1");
    if (!column_exists($db, 'tests', 'question_limit')) $db->query("ALTER TABLE tests ADD COLUMN question_limit INT UNSIGNED NOT NULL DEFAULT 10");
    if (!column_exists($db, 'tests', 'duration_minutes')) $db->query("ALTER TABLE tests ADD COLUMN duration_minutes INT UNSIGNED NOT NULL DEFAULT 60");
    if (!column_exists($db, 'tests', 'updated_at')) $db->query("ALTER TABLE tests ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
}

function get_teacher_subjects($teacherId)
{
    $db = db_conn();
    ensure_teacher_tables($db);
    $teacherId = (int) $teacherId;

    $stmt = $db->prepare('SELECT id, name, status, created_at FROM subjects WHERE teacher_id=? ORDER BY name ASC');
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $teacherId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function get_teacher_tests_filtered($teacherId, $subjectId = 0, $limit = 200)
{
    $db = db_conn();
    ensure_teacher_tables($db);
    $teacherId = (int) $teacherId;
    $subjectId = (int) $subjectId;
    $limit = max(1, min(500, (int) $limit));

    if ($subjectId > 0) {
        $sql = "SELECT t.id, t.title, t.question_text, t.test_type, t.duration_minutes, t.visibility, t.private_code, t.attempts_limit, t.question_limit, t.created_at, s.name AS subject_name
                FROM tests t
                LEFT JOIN subjects s ON s.id=t.subject_id
                WHERE t.teacher_id=? AND t.subject_id=?
                ORDER BY t.id DESC
                LIMIT ?";
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('iii', $teacherId, $subjectId, $limit);
    } else {
        $sql = "SELECT t.id, t.title, t.question_text, t.test_type, t.duration_minutes, t.visibility, t.private_code, t.attempts_limit, t.question_limit, t.created_at, s.name AS subject_name
                FROM tests t
                LEFT JOIN subjects s ON s.id=t.subject_id
                WHERE t.teacher_id=?
                ORDER BY t.id DESC
                LIMIT ?";
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('ii', $teacherId, $limit);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function ensure_test_runtime_tables(mysqli $db)
{
    ensure_teacher_tables($db);

    $db->query("CREATE TABLE IF NOT EXISTS test_attempts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        test_id INT UNSIGNED NOT NULL,
        student_id INT UNSIGNED NOT NULL,
        teacher_id INT UNSIGNED DEFAULT NULL,
        subject_id INT UNSIGNED DEFAULT NULL,
        started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        finished_at DATETIME DEFAULT NULL,
        submitted_at DATETIME DEFAULT NULL,
        duration_spent INT UNSIGNED NOT NULL DEFAULT 0,
        duration_minutes INT UNSIGNED NOT NULL DEFAULT 0,
        total_questions INT UNSIGNED NOT NULL DEFAULT 0,
        correct_count INT UNSIGNED NOT NULL DEFAULT 0,
        wrong_count INT UNSIGNED NOT NULL DEFAULT 0,
        pending_count INT UNSIGNED NOT NULL DEFAULT 0,
        score_percent INT UNSIGNED NOT NULL DEFAULT 0,
        status ENUM('in_progress','submitted','pending_review','checked') NOT NULL DEFAULT 'in_progress',
        KEY idx_attempt_student (student_id),
        KEY idx_attempt_test (test_id),
        KEY idx_attempt_teacher (teacher_id),
        KEY idx_attempt_subject (subject_id),
        KEY idx_attempt_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS test_attempt_answers (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        attempt_id INT UNSIGNED NOT NULL,
        question_id INT UNSIGNED NOT NULL,
        question_type ENUM('open','closed') NOT NULL DEFAULT 'closed',
        question_text TEXT NOT NULL,
        option_a TEXT DEFAULT NULL,
        option_b TEXT DEFAULT NULL,
        option_c TEXT DEFAULT NULL,
        option_d TEXT DEFAULT NULL,
        correct_option ENUM('A','B','C','D') DEFAULT NULL,
        student_answer_option ENUM('A','B','C','D') DEFAULT NULL,
        student_answer_text TEXT DEFAULT NULL,
        is_correct TINYINT(1) DEFAULT NULL,
        checked_by_teacher TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_attempt_question (attempt_id, question_id),
        KEY idx_answer_attempt (attempt_id),
        KEY idx_answer_correct (is_correct)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if (!column_exists($db, 'test_questions', 'question_type')) $db->query("ALTER TABLE test_questions ADD COLUMN question_type ENUM('open','closed') NOT NULL DEFAULT 'closed'");

    // Keep runtime schema compatible across old/new server deployments.
    if (!column_exists($db, 'test_attempts', 'test_id')) $db->query("ALTER TABLE test_attempts ADD COLUMN test_id INT UNSIGNED NOT NULL DEFAULT 0");
    if (!column_exists($db, 'test_attempts', 'student_id')) $db->query("ALTER TABLE test_attempts ADD COLUMN student_id INT UNSIGNED NOT NULL DEFAULT 0");
    if (!column_exists($db, 'test_attempts', 'teacher_id')) $db->query("ALTER TABLE test_attempts ADD COLUMN teacher_id INT UNSIGNED DEFAULT NULL");
    if (!column_exists($db, 'test_attempts', 'subject_id')) $db->query("ALTER TABLE test_attempts ADD COLUMN subject_id INT UNSIGNED DEFAULT NULL");
    if (!column_exists($db, 'test_attempts', 'started_at')) $db->query("ALTER TABLE test_attempts ADD COLUMN started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
    if (!column_exists($db, 'test_attempts', 'finished_at')) $db->query("ALTER TABLE test_attempts ADD COLUMN finished_at DATETIME DEFAULT NULL");
    if (!column_exists($db, 'test_attempts', 'submitted_at')) $db->query("ALTER TABLE test_attempts ADD COLUMN submitted_at DATETIME DEFAULT NULL");
    if (!column_exists($db, 'test_attempts', 'duration_spent')) $db->query("ALTER TABLE test_attempts ADD COLUMN duration_spent INT UNSIGNED NOT NULL DEFAULT 0");
    if (!column_exists($db, 'test_attempts', 'duration_minutes')) $db->query("ALTER TABLE test_attempts ADD COLUMN duration_minutes INT UNSIGNED NOT NULL DEFAULT 0");
    if (!column_exists($db, 'test_attempts', 'total_questions')) $db->query("ALTER TABLE test_attempts ADD COLUMN total_questions INT UNSIGNED NOT NULL DEFAULT 0");
    if (!column_exists($db, 'test_attempts', 'correct_count')) $db->query("ALTER TABLE test_attempts ADD COLUMN correct_count INT UNSIGNED NOT NULL DEFAULT 0");
    if (!column_exists($db, 'test_attempts', 'wrong_count')) $db->query("ALTER TABLE test_attempts ADD COLUMN wrong_count INT UNSIGNED NOT NULL DEFAULT 0");
    if (!column_exists($db, 'test_attempts', 'pending_count')) $db->query("ALTER TABLE test_attempts ADD COLUMN pending_count INT UNSIGNED NOT NULL DEFAULT 0");
    if (!column_exists($db, 'test_attempts', 'score_percent')) $db->query("ALTER TABLE test_attempts ADD COLUMN score_percent INT UNSIGNED NOT NULL DEFAULT 0");
    if (!column_exists($db, 'test_attempts', 'status')) $db->query("ALTER TABLE test_attempts ADD COLUMN status VARCHAR(32) NOT NULL DEFAULT 'in_progress'");

    if (!column_exists($db, 'test_attempt_answers', 'attempt_id')) $db->query("ALTER TABLE test_attempt_answers ADD COLUMN attempt_id INT UNSIGNED NOT NULL DEFAULT 0");
    if (!column_exists($db, 'test_attempt_answers', 'question_id')) $db->query("ALTER TABLE test_attempt_answers ADD COLUMN question_id INT UNSIGNED NOT NULL DEFAULT 0");
    if (!column_exists($db, 'test_attempt_answers', 'question_type')) $db->query("ALTER TABLE test_attempt_answers ADD COLUMN question_type ENUM('open','closed') NOT NULL DEFAULT 'closed'");
    if (!column_exists($db, 'test_attempt_answers', 'question_text')) $db->query("ALTER TABLE test_attempt_answers ADD COLUMN question_text TEXT NOT NULL");
    if (!column_exists($db, 'test_attempt_answers', 'option_a')) $db->query("ALTER TABLE test_attempt_answers ADD COLUMN option_a TEXT DEFAULT NULL");
    if (!column_exists($db, 'test_attempt_answers', 'option_b')) $db->query("ALTER TABLE test_attempt_answers ADD COLUMN option_b TEXT DEFAULT NULL");
    if (!column_exists($db, 'test_attempt_answers', 'option_c')) $db->query("ALTER TABLE test_attempt_answers ADD COLUMN option_c TEXT DEFAULT NULL");
    if (!column_exists($db, 'test_attempt_answers', 'option_d')) $db->query("ALTER TABLE test_attempt_answers ADD COLUMN option_d TEXT DEFAULT NULL");
    if (!column_exists($db, 'test_attempt_answers', 'correct_option')) $db->query("ALTER TABLE test_attempt_answers ADD COLUMN correct_option ENUM('A','B','C','D') DEFAULT NULL");
    if (!column_exists($db, 'test_attempt_answers', 'student_answer_option')) $db->query("ALTER TABLE test_attempt_answers ADD COLUMN student_answer_option ENUM('A','B','C','D') DEFAULT NULL");
    if (!column_exists($db, 'test_attempt_answers', 'student_answer_text')) $db->query("ALTER TABLE test_attempt_answers ADD COLUMN student_answer_text TEXT DEFAULT NULL");
    if (!column_exists($db, 'test_attempt_answers', 'is_correct')) $db->query("ALTER TABLE test_attempt_answers ADD COLUMN is_correct TINYINT(1) DEFAULT NULL");
    if (!column_exists($db, 'test_attempt_answers', 'checked_by_teacher')) $db->query("ALTER TABLE test_attempt_answers ADD COLUMN checked_by_teacher TINYINT(1) NOT NULL DEFAULT 0");
    if (!column_exists($db, 'test_attempt_answers', 'created_at')) $db->query("ALTER TABLE test_attempt_answers ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
    if (!column_exists($db, 'test_attempt_answers', 'updated_at')) $db->query("ALTER TABLE test_attempt_answers ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

    // Some servers keep legacy schema where id is not AUTO_INCREMENT; normalize it.
    try {
        $idAttempt = $db->query("SHOW COLUMNS FROM test_attempts LIKE 'id'");
        $idAttemptRow = $idAttempt ? $idAttempt->fetch_assoc() : null;
        if ($idAttemptRow) {
            $extra = strtolower((string)($idAttemptRow['Extra'] ?? ''));
            if (strpos($extra, 'auto_increment') === false) {
                $db->query("ALTER TABLE test_attempts MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT");
            }
            $pkRes = $db->query("SHOW INDEX FROM test_attempts WHERE Key_name='PRIMARY'");
            $hasPk = $pkRes && $pkRes->num_rows > 0;
            if (!$hasPk) {
                $db->query("ALTER TABLE test_attempts ADD PRIMARY KEY (id)");
            }
        }
    } catch (Throwable $e) {
        error_log('ensure_test_runtime_tables(test_attempts id normalize): ' . $e->getMessage());
    }

    try {
        $idAnswer = $db->query("SHOW COLUMNS FROM test_attempt_answers LIKE 'id'");
        $idAnswerRow = $idAnswer ? $idAnswer->fetch_assoc() : null;
        if ($idAnswerRow) {
            $extra = strtolower((string)($idAnswerRow['Extra'] ?? ''));
            if (strpos($extra, 'auto_increment') === false) {
                $db->query("ALTER TABLE test_attempt_answers MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT");
            }
            $pkRes = $db->query("SHOW INDEX FROM test_attempt_answers WHERE Key_name='PRIMARY'");
            $hasPk = $pkRes && $pkRes->num_rows > 0;
            if (!$hasPk) {
                $db->query("ALTER TABLE test_attempt_answers ADD PRIMARY KEY (id)");
            }
        }
    } catch (Throwable $e) {
        error_log('ensure_test_runtime_tables(test_attempt_answers id normalize): ' . $e->getMessage());
    }
}

function get_student_test_subject_filters()
{
    $db = db_conn();
    ensure_test_runtime_tables($db);

    $sql = "SELECT s.id, s.name
            FROM subjects s
            INNER JOIN tests t ON t.subject_id = s.id
            WHERE COALESCE(t.visibility,'open')='open'
            GROUP BY s.id, s.name
            ORDER BY s.name ASC";
    $res = $db->query($sql);
    if (!$res) {
        return [];
    }

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

function get_available_tests_for_student($subjectId = 0, $limit = 200)
{
    $db = db_conn();
    ensure_test_runtime_tables($db);
    $subjectId = (int) $subjectId;
    $limit = max(1, min(500, (int) $limit));

    $base = "SELECT t.id, t.title, t.duration_minutes, t.created_at, t.teacher_id, t.subject_id, t.attempts_limit, t.question_limit,
                    s.name AS subject_name,
                    (SELECT COUNT(*) FROM test_questions q WHERE q.test_id=t.id) AS question_count
             FROM tests t
             LEFT JOIN subjects s ON s.id=t.subject_id
             WHERE COALESCE(t.visibility,'open')='open'";

    if ($subjectId > 0) {
        $sql = $base . " AND t.subject_id=? ORDER BY t.id DESC LIMIT ?";
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('ii', $subjectId, $limit);
    } else {
        $sql = $base . " ORDER BY t.id DESC LIMIT ?";
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $limit);
    }

    $stmt->execute();
    $rows = stmt_fetch_all_assoc($stmt);
    $filtered = [];
    foreach ($rows as $row) {
        if ((int) ($row['question_count'] ?? 0) > 0) {
            $filtered[] = $row;
        }
    }
    $stmt->close();
    return $filtered;
}

function get_student_test_details($testId)
{
    $db = db_conn();
    ensure_test_runtime_tables($db);
    $testId = (int) $testId;

    $stmt = $db->prepare("SELECT t.id, t.title, t.duration_minutes, t.teacher_id, t.subject_id, t.attempts_limit, t.question_limit, s.name AS subject_name,
                                (SELECT COUNT(*) FROM test_questions q WHERE q.test_id=t.id) AS question_count
                         FROM tests t
                         LEFT JOIN subjects s ON s.id=t.subject_id
                         WHERE t.id=? AND COALESCE(t.visibility,'open')='open'
                         LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $testId);
    $stmt->execute();
    $row = stmt_fetch_assoc($stmt);
    $stmt->close();
    return $row ?: null;
}

function get_student_test_details_with_private_code($testId, $privateCode = '')
{
    $db = db_conn();
    ensure_test_runtime_tables($db);
    $testId = (int)$testId;
    $privateCode = trim((string)$privateCode);

    if ($privateCode !== '') {
        $stmt = $db->prepare("SELECT t.id, t.title, t.duration_minutes, t.teacher_id, t.subject_id, t.attempts_limit, t.question_limit, s.name AS subject_name,
                                    (SELECT COUNT(*) FROM test_questions q WHERE q.test_id=t.id) AS question_count
                             FROM tests t
                             LEFT JOIN subjects s ON s.id=t.subject_id
                             WHERE t.id=? AND ((COALESCE(t.visibility,'open')='open') OR (COALESCE(t.visibility,'open')='closed' AND t.private_code=?))
                             LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('is', $testId, $privateCode);
        $stmt->execute();
        $row = stmt_fetch_assoc($stmt);
        $stmt->close();
        return $row ?: null;
    }

    return get_student_test_details($testId);
}

function get_private_test_for_student_by_code($privateCode)
{
    $db = db_conn();
    ensure_test_runtime_tables($db);
    $privateCode = trim((string)$privateCode);
    if ($privateCode === '') {
        return null;
    }

    $stmt = $db->prepare("SELECT t.id, t.title, t.duration_minutes, t.teacher_id, t.subject_id, t.attempts_limit, t.question_limit, t.private_code, s.name AS subject_name,
                                (SELECT COUNT(*) FROM test_questions q WHERE q.test_id=t.id) AS question_count
                         FROM tests t
                         LEFT JOIN subjects s ON s.id=t.subject_id
                         WHERE COALESCE(t.visibility,'open')='closed' AND t.private_code=?
                         LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $privateCode);
    $stmt->execute();
    $row = stmt_fetch_assoc($stmt);
    $stmt->close();
    if (!$row || (int)($row['question_count'] ?? 0) <= 0) {
        return null;
    }
    return $row;
}

function get_student_test_questions($testId)
{
    $db = db_conn();
    ensure_test_runtime_tables($db);
    $testId = (int) $testId;
    $limit = 10;

    $limitStmt = $db->prepare("SELECT GREATEST(1, COALESCE(question_limit, 10)) AS q_limit FROM tests WHERE id=? LIMIT 1");
    if ($limitStmt) {
        $limitStmt->bind_param('i', $testId);
        $limitStmt->execute();
        $limitRow = stmt_fetch_assoc($limitStmt);
        $limit = max(1, (int)($limitRow['q_limit'] ?? 10));
        $limitStmt->close();
    }

    $stmt = $db->prepare("SELECT id, question_text, question_type, option_a, option_b, option_c, option_d, correct_option
                          FROM test_questions
                          WHERE test_id=?
                          ORDER BY id ASC
                          LIMIT ?");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('ii', $testId, $limit);
    $stmt->execute();
    $rows = stmt_fetch_all_assoc($stmt);
    $stmt->close();
    return $rows;
}

function get_student_attempts($studentId, $limit = 50)
{
    $db = db_conn();
    ensure_test_runtime_tables($db);
    $studentId = (int) $studentId;
    $limit = max(1, min(200, (int) $limit));

    if (table_exists($db, 'test_attempts')) {
        $sql = "SELECT a.id, a.score_percent AS score, a.correct_count, a.wrong_count, a.pending_count, a.status,
                       a.submitted_at AS taken_at, t.title AS test_title
                FROM test_attempts a
                LEFT JOIN tests t ON t.id=a.test_id
                WHERE a.student_id=? AND a.status<>'in_progress'
                ORDER BY a.id DESC
                LIMIT ?";
        $stmt = $db->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('ii', $studentId, $limit);
            $stmt->execute();
            $rows = stmt_fetch_all_assoc($stmt);
            $stmt->close();
            return $rows;
        }
    }

    return get_student_results($studentId, $limit);
}

function get_student_attempted_test_ids($studentId)
{
    $db = db_conn();
    ensure_test_runtime_tables($db);
    $studentId = (int) $studentId;

    if (!table_exists($db, 'test_attempts')) {
        return [];
    }

    $stmt = $db->prepare("SELECT DISTINCT test_id FROM test_attempts WHERE student_id=? AND status IN ('submitted','pending_review','checked')");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $ids = [];
    foreach (stmt_fetch_all_assoc($stmt) as $row) {
        $ids[] = (int) ($row['test_id'] ?? 0);
    }
    $stmt->close();
    return $ids;
}

function get_teacher_attempt_detail($teacherId, $attemptId)
{
    $db = db_conn();
    ensure_test_runtime_tables($db);
    $teacherId = (int) $teacherId;
    $attemptId = (int) $attemptId;

    $studentExpr = "CONCAT('Student #', a.student_id)";
    if (table_exists($db, 'users')) {
        $studentExpr = "COALESCE(NULLIF(TRIM(u.fullname),''), NULLIF(TRIM(u.username),''), CONCAT('Student #', a.student_id))";
    }
    $joinUsers = table_exists($db, 'users') ? " LEFT JOIN users u ON u.id=a.student_id " : "";

    $sql = "SELECT a.*, t.title AS test_title, s.name AS subject_name, {$studentExpr} AS student_name
            FROM test_attempts a
            LEFT JOIN tests t ON t.id=a.test_id
            LEFT JOIN subjects s ON s.id=a.subject_id
            {$joinUsers}
            WHERE a.id=? AND a.teacher_id=?
            LIMIT 1";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('ii', $attemptId, $teacherId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function get_attempt_answers($attemptId)
{
    $db = db_conn();
    ensure_test_runtime_tables($db);
    $attemptId = (int) $attemptId;

    $stmt = $db->prepare("SELECT * FROM test_attempt_answers WHERE attempt_id=? ORDER BY question_id ASC");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $attemptId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function recalculate_attempt_stats(mysqli $db, $attemptId)
{
    $attemptId = (int) $attemptId;
    $stmt = $db->prepare("SELECT
            SUM(CASE WHEN is_correct=1 THEN 1 ELSE 0 END) AS c_ok,
            SUM(CASE WHEN is_correct=0 THEN 1 ELSE 0 END) AS c_bad,
            SUM(CASE WHEN is_correct IS NULL THEN 1 ELSE 0 END) AS c_pending
        FROM test_attempt_answers
        WHERE attempt_id=?");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $attemptId);
    $stmt->execute();
    $agg = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $correct = (int) ($agg['c_ok'] ?? 0);
    $wrong = (int) ($agg['c_bad'] ?? 0);
    $pending = (int) ($agg['c_pending'] ?? 0);
    $denom = max(1, $correct + $wrong);
    $score = (int) round(($correct / $denom) * 100);
    $status = $pending > 0 ? 'pending_review' : 'checked';

    $up = $db->prepare("UPDATE test_attempts SET correct_count=?, wrong_count=?, pending_count=?, score_percent=?, status=? WHERE id=? LIMIT 1");
    if (!$up) {
        return;
    }
    $up->bind_param('iiiisi', $correct, $wrong, $pending, $score, $status, $attemptId);
    $up->execute();
    $up->close();
}

function get_admin_tests($limit = 300)
{
    $db = db_conn();
    ensure_test_runtime_tables($db);
    $limit = max(1, min(1000, (int) $limit));

    $teacherExpr = "CONCAT('Teacher #', t.teacher_id)";
    if (table_exists($db, 'users')) {
        $teacherExpr = "COALESCE(NULLIF(TRIM(u.fullname),''), NULLIF(TRIM(u.username),''), CONCAT('Teacher #', t.teacher_id))";
    }
    $joinUsers = table_exists($db, 'users') ? " LEFT JOIN users u ON u.id=t.teacher_id " : "";

    $sql = "SELECT t.id, t.title, t.duration_minutes, t.created_at, s.name AS subject_name, {$teacherExpr} AS teacher_name
            FROM tests t
            LEFT JOIN subjects s ON s.id=t.subject_id
            {$joinUsers}
            ORDER BY t.id DESC
            LIMIT ?";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function get_admin_attempts($limit = 500, $testId = 0)
{
    $db = db_conn();
    ensure_test_runtime_tables($db);
    $limit = max(1, min(2000, (int) $limit));
    $testId = (int) $testId;

    $studentExpr = "CONCAT('Student #', a.student_id)";
    $teacherExpr = "CONCAT('Teacher #', a.teacher_id)";
    if (table_exists($db, 'users')) {
        $studentExpr = "COALESCE(NULLIF(TRIM(us.fullname),''), NULLIF(TRIM(us.username),''), CONCAT('Student #', a.student_id))";
        $teacherExpr = "COALESCE(NULLIF(TRIM(ut.fullname),''), NULLIF(TRIM(ut.username),''), CONCAT('Teacher #', a.teacher_id))";
    }
    $joinUsers = table_exists($db, 'users')
        ? " LEFT JOIN users us ON us.id=a.student_id LEFT JOIN users ut ON ut.id=a.teacher_id "
        : "";

    $base = "SELECT a.id, a.correct_count, a.wrong_count, a.pending_count, a.score_percent, a.status, a.submitted_at,
                    t.title AS test_title, s.name AS subject_name,
                    {$studentExpr} AS student_name, {$teacherExpr} AS teacher_name
             FROM test_attempts a
             LEFT JOIN tests t ON t.id=a.test_id
             LEFT JOIN subjects s ON s.id=a.subject_id
             {$joinUsers}
             WHERE a.status<>'in_progress'";

    if ($testId > 0) {
        $sql = $base . " AND a.test_id=? ORDER BY a.id DESC LIMIT ?";
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('ii', $testId, $limit);
    } else {
        $sql = $base . " ORDER BY a.id DESC LIMIT ?";
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $limit);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}
