<?php
    // exit;
	session_start();
	include_once 'config.php';
	$ret = [];
	$ret = ['xatolik' => 1, 'xabar' => "Yakunlangan!"];		
	//print_r($_POST);
	if($_POST['_csrf']!=$_SESSION['_csrf']){
		$_SESSION['_csrf'] = md5(time());
		$ret = ['xatolik' => 1, 'xabar' => "Sahifani yangilab qaytadan urining"];
		echo json_encode($ret);
		exit;
	}
	$fio = filter($_POST['fio']);
	$sana = strtotime($_POST['sana']);
	$telefon = filter($_POST['telefon']);
	$fakultet = filter($_POST['fakultet']);
	$fan = filter($_POST['fan']);
	$ip = get_ip();
	$sql = mysqli_query($link, "SELECT * FROM blocklist WHERE ip='$ip'");
	$fetch = mysqli_fetch_assoc($sql);
	if($fetch['id']>0){
		$ret = ['xatolik' => 1, 'xabar' => "Qurilma bloklangan iltimos adminga murojat qiling!!! +998 94 684 40 96 :("];
	}
	else{
		$sql = mysqli_query($link,"SELECT * FROM students WHERE telefon='$telefon'");
		$fetch = mysqli_fetch_assoc($sql);
		if($fetch['id']>0){
			$urinish = 1;
			$ss = time();
			//$sql = mysqli_query($link,"INSERT INTO blocklist (ip,urinish,sana) VALUES ('$ip','$urinish','$ss')");
			if($sql){
				$ret = ['xatolik' => 2, 'xabar' => "Ushbu raqam allaqachon ro'yxatdan o'tgan tekshirib qaytadan urining"];
			}
			else{
				$ret = ['xatolik' => 2, 'xabar' => "Ushbu raqam allaqachon ro'yxatdan o'tgan tekshirib qaytadan urining"];
			}
		}
		else{
			$sql = mysqli_query($link,"INSERT INTO students (fio,sana,fakultet,fan,ip,telefon,status) VALUES ('$fio','$sana','$fakultet','$fan','$ip','$telefon','waiting')");
			if($sql){
				$ret = ['xatolik' => 0, 'xabar' => "Tabriklaymiz barchasi muvaffaqqiyatli siz ro'yxatdan o'tdingiz. Iltimos bu qurilmadan qayta urinmang aks xolda qurilma bloklanadi. Platforma va saytda e'lon beriladi. Sizning telefon raqamingizga login va parol jo'natiladi."];
			}
			else{
				$ret = ['xatolik' => 4, 'xabar' => "Kechirasiz malumotlarda kamchilik bor qaytadan tekshirib urinib ko'ring. Yoki adminga xabar bering. +998 94 684 40 96"];
			}
		}
	}
	echo json_encode($ret);
?>