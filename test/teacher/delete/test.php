<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../database/Database.php';
require_once __DIR__ . '/../../shared/dashboard_repository.php';

require_auth(['teacher']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('/test/teacher/tests/index.php'); }
if (!verify_csrf($_POST['_csrf'] ?? '')) { flash_set('error', 'Xavfsizlik tekshiruvi muvaffaqiyatsiz.'); redirect('/test/teacher/tests/index.php'); }
$teacherId = (int) (auth_user()['id'] ?? 0); $id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) { flash_set('error', 'Noto\'g\'ri test ID.'); redirect('/test/teacher/tests/index.php'); }

try {
  $db = Database::connection(); ensure_teacher_tables($db);
  $db->query("CREATE TABLE IF NOT EXISTS test_questions (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      test_id INT UNSIGNED NOT NULL,
      teacher_id INT UNSIGNED NOT NULL,
      question_text TEXT NOT NULL,
      option_a TEXT DEFAULT NULL, option_b TEXT DEFAULT NULL, option_c TEXT DEFAULT NULL, option_d TEXT DEFAULT NULL,
      correct_option ENUM('A','B','C','D') DEFAULT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      KEY idx_tq_test (test_id), KEY idx_tq_teacher (teacher_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $q = $db->prepare('DELETE FROM test_questions WHERE test_id=? AND teacher_id=?');
  if ($q) { $q->bind_param('ii', $id, $teacherId); $q->execute(); $q->close(); }

  $stmt = $db->prepare('DELETE FROM tests WHERE id=? AND teacher_id=? LIMIT 1');
  if (!$stmt) { throw new RuntimeException('Prepare failed'); }
  $stmt->bind_param('ii', $id, $teacherId); $stmt->execute(); $stmt->close();
  flash_set('success', 'Test o\'chirildi.');
} catch (Throwable $e) {
  flash_set('error', 'Testni o\'chirishda xatolik.');
}
redirect('/test/teacher/tests/index.php');
