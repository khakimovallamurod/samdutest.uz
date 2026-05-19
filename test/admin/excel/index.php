<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>EXCELDAN IMPORT TOVARLAR</title>
</head>
<body>
	<form action="index.php" method="POST" enctype="multipart/form-data">
		<input type="file" name="fayl">
		<input type="hidden" name="submit">
		<p><button type="submit">Yuborish</button></p>
	</form>
	<table cellpadding="5" cellspacing="0" border="1" align="center">
		<tr>
			<th>T/r</th>
			<th>Savol</th>
			<th>To'g'ri javob</th>
			<th>Var1</th>
			<th>Var2</th>
			<th>Var3</th>
			<th>TEST_ID / TEST_HASH</th>
		</tr>
	<?
		if(isset($_POST['submit'])){
			function uniqidReal($lenght = 13) {
		        // uniqid gives 13 chars, but you could adjust it to your needs.
		        if (function_exists("random_bytes")) {
		            $bytes = random_bytes(ceil($lenght / 2));
		        } elseif (function_exists("openssl_random_pseudo_bytes")) {
		            $bytes = openssl_random_pseudo_bytes(ceil($lenght / 2));
		        } else {
		            throw new Exception("no cryptographically secure random function available");
		        }
		        return substr(bin2hex($bytes), 0, $lenght);
		    }
			echo $file = $_FILES['fayl']['name'];
			echo "<br>";
			include_once '../../../config.php';
			require_once 'PHPExcel/Classes/PHPExcel.php';
			require_once 'PHPExcel/Classes/PHPExcel/IOFactory.php';
			move_uploaded_file($_FILES['fayl']['tmp_name'],"upload/".$file);

			$objexcel = PHPExcel_IOFactory::load("upload/".$file);
			$t=0;
			$id_hash = "64c0c0bbb9aa9";
			$test_id = "7";

			foreach ($objexcel->getWorksheetIterator() as $sheet) {
				$n = $sheet->getHighestRow();
				$m = $sheet->getHighestColumn();
				for($i=0; $i<=$n; $i++){
					if ($i<4) {
						continue;
					}
					$savol = $sheet->getCellByColumnAndRow(1,$i)->getValue();
					$answer = $sheet->getCellByColumnAndRow(2,$i)->getValue();
					$var1 = $sheet->getCellByColumnAndRow(3,$i)->getValue();
					$var2 = $sheet->getCellByColumnAndRow(4,$i)->getValue();
					$var3 = $sheet->getCellByColumnAndRow(5,$i)->getValue();
					if($savol==""){
						continue;
					}
					$answer_hash = uniqidReal();
				    $var1_hash = uniqidReal();
				    $var2_hash = uniqidReal();
				    $var3_hash = uniqidReal();
				    $hash = uniqid();
				    $sql = mysqli_query($link, "INSERT into savollar (test_id,savol,test_id_hash,hash) values ('$test_id','$savol','$id_hash','$hash')");
				    $sql = mysqli_query($link, "INSERT INTO variantlar (savol_id_hash,variant,variant_id_hash) values ('$hash','$answer','$answer_hash')");
				    $sql = mysqli_query($link, "INSERT INTO variantlar (savol_id_hash,variant,variant_id_hash) values ('$hash','$var1','$var1_hash')");
				    $sql = mysqli_query($link, "INSERT INTO variantlar (savol_id_hash,variant,variant_id_hash) values ('$hash','$var2','$var2_hash')");
				    $sql = mysqli_query($link, "INSERT INTO variantlar (savol_id_hash,variant,variant_id_hash) values ('$hash','$var3','$var3_hash')");
				    $sql = mysqli_query($link, "INSERT into javoblar (savol_id_hash,variant_id_hash) values ('$hash','$answer_hash')");
				    if($sql==true){
				    	$color = "green";
				    }
				    else{
				    	$color = "red";
				    }
				?>
					<tr bgcolor="<?=$color?>">
						<td><?=++$t?></td>
						<td><?=$savol?></td>
						<td><?=$answer?>/<?=$answer_hash?></td>
						<td><?=$var1?>/<?=$var1_hash?></td>
						<td><?=$var2?>/<?=$var2_hash?></td>
						<td><?=$var3?>/<?=$var3_hash?></td>
						<td><?=$test_id?>/<?=$id_hash?></td>
					</tr>
				<?
				}
			}
		}
	?>
	</table>
</body>
</html>
