<?
    $url = "http://olimp.samdu.uz";
    $host = "localhost"; //"a345111.mysql.mchost.ru"; //;
    $user_db = "cpadmin_sdg"; //""; //
    $password = "SMaTCv9RCK"; //""; //
    $db = "cpadmin_olimp"; //""; //

    $host = "localhost"; //"a345111.mysql.mchost.ru"; //;
    $user_db = "root"; //""; //
    $password = "qwertyuiop[]"; //""; //
    $db = "u1782683_olimptest"; //""; //

    $link = mysqli_connect($host, $user_db, $password, $db);
	$sql = mysqli_query($link,"INSERT INTO students (fio) VALUES ('salom')");
	if($sql){
      echo "Yozildi";
    }
    else{
      echo "Yozilmadi";
    }
?>
