<?php
require_once __DIR__ . '/../../middleware/auth.php'; require_once __DIR__ . '/../../database/Database.php'; require_auth(['teacher']);
if($_SERVER['REQUEST_METHOD']!=='POST' || !verify_csrf($_POST['_csrf'] ?? '')){redirect('/test/teacher/tests/index.php');}
$teacherId=(int)(auth_user()['id']??0); $id=(int)($_POST['id']??0); $testId=(int)($_POST['test_id']??0);
$db=Database::connection(); $st=$db->prepare('DELETE FROM test_questions WHERE id=? AND teacher_id=? LIMIT 1'); $st->bind_param('ii',$id,$teacherId); $st->execute(); $st->close();
flash_set('success','Savol o\'chirildi.'); redirect('/test/teacher/tests/questions.php?test_id='.$testId.'&deleted=1');
