<? include_once 'config.php'; ?>
<option value="0">Tanlang</option>
<?
	$sql = mysqli_query($link,"SELECT * FROM fan WHERE status='1'");
	while($fan = mysqli_fetch_assoc($sql)){
		?>
		<option value="<?=$fan['id']?>"><?=$fan['name']?></option>
		<?
	}
?>