<? include_once '../config.php'; ?>
<table align="center" cellspacing="0" cellpadding="10" border="1">
	<tr>
		<th>ID</th>
		<th>Summa</th>
		<th>Client</th>
		<th>Telefon</th>
		<th>Valyuta</th>
	</tr>
<?
	$sql = mysqli_query($link,"SELECT * FROM sale_orders WHERE tulov_turi='qarz'");
	while($fetch = mysqli_fetch_assoc($sql)) {
		$id = $fetch['id'];
		$sql2 = mysqli_query($link, "UPDATE sale_orders SET valyuta='usd' WHERE id='$id'");
		$sql3 = mysqli_query($link, "SELECT * FROM sale_order_items WHERE sale_order_id='$id'");
		while ($fetch3 = mysqli_fetch_assoc($sql3)) {
			$it_id = $fetch3['id'];
			$sql2 = mysqli_query($link, "UPDATE sale_order_items SET valyuta='usd' WHERE id='$it_id'");
		}
		?>
		<tr>
			<td><?=$fetch['id']?></td>
			<td><?=$fetch['summa']?></td>
			<td><?=$fetch['client_id']?></td>
			<td><?=$fetch['telefon']?></td>
			<td><?=$fetch['valyuta']?></td>
		</tr>
		<?
	}
?>
</table>