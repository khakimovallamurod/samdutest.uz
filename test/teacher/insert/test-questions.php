<?php
require_once __DIR__ . '/../../middleware/auth.php'; require_once __DIR__ . '/../../database/Database.php'; require_once __DIR__ . '/../../shared/dashboard_repository.php';
require_auth(['teacher']); if($_SERVER['REQUEST_METHOD']!=='POST'){redirect('/test/teacher/tests.php');}
if(!verify_csrf($_POST['_csrf'] ?? '')){flash_set('error','Xavfsizlik tekshiruvi muvaffaqiyatsiz.'); redirect('/test/teacher/tests.php');}
$teacherId=(int)(auth_user()['id']??0); $testId=(int)($_POST['test_id']??0); $items=$_POST['items']??[];
$db=Database::connection(); ensure_teacher_tables($db);
$db->query("CREATE TABLE IF NOT EXISTS test_questions (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,test_id INT UNSIGNED NOT NULL,teacher_id INT UNSIGNED NOT NULL,question_text TEXT NOT NULL,question_type ENUM('open','closed') NOT NULL DEFAULT 'closed',option_a TEXT DEFAULT NULL,option_b TEXT DEFAULT NULL,option_c TEXT DEFAULT NULL,option_d TEXT DEFAULT NULL,correct_option ENUM('A','B','C','D') DEFAULT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, KEY idx_tq_test (test_id), KEY idx_tq_teacher (teacher_id), KEY idx_tq_type (question_type)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
if(!column_exists($db,'test_questions','question_type')){$db->query("ALTER TABLE test_questions ADD COLUMN question_type ENUM('open','closed') NOT NULL DEFAULT 'closed'");}
$st=$db->prepare('INSERT INTO test_questions (test_id,teacher_id,question_text,question_type,option_a,option_b,option_c,option_d,correct_option,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())');
$sanitize=function($html){
    $html=(string)$html;
    $allowed='<p><br><b><strong><i><em><u><s><sub><sup><ol><ul><li><span><div><img><table><tbody><thead><tr><td><th><math><mrow><mi><mn><mo><msup><msub><msubsup><mfrac><msqrt><mroot><mtext><mfenced><mtable><mtr><mtd>';
    return trim(strip_tags($html,$allowed));
};
$created=0; foreach((array)$items as $it){$q=$sanitize($it['question_text']??''); if(trim(strip_tags($q))==='') continue; $qt=trim((string)($it['question_type']??'closed')); if(!in_array($qt,['open','closed'],true))$qt='closed'; $a=$sanitize($it['option_a']??'');$b=$sanitize($it['option_b']??'');$c=$sanitize($it['option_c']??'');$d=$sanitize($it['option_d']??'');$co=trim((string)($it['correct_option']??''));
if($qt==='open'){ if($a===''||$b===''||$c===''||$d===''||!in_array($co,['A','B','C','D'],true)) continue; } else { $a=$b=$c=$d=$co=null; }
$st->bind_param('iisssssss',$testId,$teacherId,$q,$qt,$a,$b,$c,$d,$co); $st->execute(); $created++;}
$st->close(); flash_set($created>0?'success':'error',$created>0?'Savollar saqlandi.':'Savol topilmadi.'); redirect('/test/teacher/test-questions.php?test_id='.$testId);
