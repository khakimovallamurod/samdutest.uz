<?php
include_once 'config.php';
header('Content-Type: text/html; charset=UTF-8');
function safe_text($value) {
  return htmlspecialchars(html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');
}
?>
<table id="table_id" class="display results-table" style="width:100%">
  <thead>
    <tr>
      <th>T/r</th>
      <th>FISH</th>
      <th>Fakultet</th>
      <th>Jami test soni</th>
      <th>To'g'ri javoblar soni</th>
      <th>Noto'g'ri javoblar soni</th>
      <th>Ishlash vaqti</th>
      <th>Foiz ko'rsatkichi</th>
    </tr>
  </thead>
  <tbody>
    <?php
    $i = 0;
    if (!empty($_POST['fan_id'])) {
      $fan_id = trim($_POST['fan_id']);
      $stmt = mysqli_prepare($link, "SELECT login, score, wrong, t_vaqt, boshlangan_vaqt, right_p FROM results WHERE id_hash=? ORDER BY score DESC");
      mysqli_stmt_bind_param($stmt, 's', $fan_id);
      mysqli_stmt_execute($stmt);
      $res = mysqli_stmt_get_result($stmt);

      while ($result = mysqli_fetch_assoc($res)) {
        $login = $result['login'];
        $i++;

        $stmtU = mysqli_prepare($link, "SELECT student_id FROM user WHERE login=? LIMIT 1");
        mysqli_stmt_bind_param($stmtU, 's', $login);
        mysqli_stmt_execute($stmtU);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtU));

        $fio = '-';
        $fakultet = '-';
        if (!empty($user['student_id'])) {
          $sid = (int)$user['student_id'];
          $stmtS = mysqli_prepare($link, "SELECT fio, fakultet FROM students WHERE id=? LIMIT 1");
          mysqli_stmt_bind_param($stmtS, 'i', $sid);
          mysqli_stmt_execute($stmtS);
          $talaba = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtS));
          if ($talaba) {
            $fio = $talaba['fio'];
            $fakultet = $talaba['fakultet'];
          }
        }

        $work = max(0, (int)$result['t_vaqt'] - (int)$result['boshlangan_vaqt']);
        $min = intdiv($work, 60);
        $sec = str_pad((string)($work % 60), 2, '0', STR_PAD_LEFT);
        ?>
        <tr>
          <td><?= $i ?></td>
          <td><?= safe_text($fio) ?></td>
          <td><?= safe_text($fakultet) ?></td>
          <td><?= (int)$result['score'] + (int)$result['wrong'] ?></td>
          <td><?= (int)$result['score'] ?></td>
          <td><?= (int)$result['wrong'] ?></td>
          <td><?= $min . ':' . $sec ?></td>
          <td><?= (int)$result['right_p'] ?></td>
        </tr>
        <?php
      }
    }
    ?>
  </tbody>
</table>
<script>
if (window.jQuery && $('#table_id').length) {
  if ($.fn.DataTable.isDataTable('#table_id')) {
    $('#table_id').DataTable().destroy();
  }
  $('#table_id').DataTable({
    responsive: true,
    language: {
      lengthMenu: "Sahifada _MENU_ ta ma'lumot",
      search: 'Qidiruv',
      paginate: { previous: 'Orqaga', next: 'Keyingi' },
      emptyTable: "Bu jadval bo'sh. Ma'lumot yo'q",
      info: '_START_–_END_ / _TOTAL_',
      infoEmpty: '0–0 / 0',
      zeroRecords: 'Bunday ma\'lumot topilmadi'
    }
  });
}
</script>
