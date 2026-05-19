<?php
include_once 'config.php';
header('Content-Type: text/html; charset=UTF-8');

function initials($name) {
  $parts = preg_split('/\s+/', trim($name));
  $out = '';
  foreach ($parts as $p) {
    if ($p !== '') {
      $out .= mb_substr($p, 0, 1, 'UTF-8');
      if (mb_strlen($out, 'UTF-8') >= 2) {
        break;
      }
    }
  }
  return mb_strtoupper($out ?: 'U', 'UTF-8');
}
function safe_text($value) {
  return htmlspecialchars(html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');
}

function status_badge($status) {
  $s = strtolower(trim((string)$status));
  if ($s === 'open' || $s === 'active') {
    return '<span class="inline-flex rounded-full bg-gradient-to-r from-emerald-500 to-green-500 px-2.5 py-1 text-[11px] font-semibold text-white shadow">Active</span>';
  }
  if ($s === 'waiting' || $s === 'pending') {
    return '<span class="inline-flex rounded-full bg-gradient-to-r from-amber-400 to-orange-500 px-2.5 py-1 text-[11px] font-semibold text-white shadow">Pending</span>';
  }
  if ($s === 'completed' || $s === 'done') {
    return '<span class="inline-flex rounded-full bg-gradient-to-r from-cyan-500 to-sky-500 px-2.5 py-1 text-[11px] font-semibold text-white shadow">Completed</span>';
  }
  return '<span class="inline-flex rounded-full bg-gradient-to-r from-rose-400 to-red-500 px-2.5 py-1 text-[11px] font-semibold text-white shadow">Failed</span>';
}
?>
<table id="table_id" class="display" style="width:100%">
  <thead>
    <tr>
      <th>T/r</th>
      <th>FISH</th>
      <th>Fan nomi</th>
      <th>Telefon</th>
      <th>Status</th>
    </tr>
  </thead>
  <tbody>
    <?php
    $i = 0;
    $fan_id = isset($_POST['fan_id']) ? (int)$_POST['fan_id'] : 0;

    if ($fan_id > 0) {
      $stmt = mysqli_prepare($link, "SELECT id, fio, fan, telefon, status FROM students WHERE fan=? ORDER BY id DESC");
      mysqli_stmt_bind_param($stmt, 'i', $fan_id);
      mysqli_stmt_execute($stmt);
      $students = mysqli_stmt_get_result($stmt);
    } else {
      $students = mysqli_query($link, "SELECT id, fio, fan, telefon, status FROM students ORDER BY id DESC");
    }

    while ($student = mysqli_fetch_assoc($students)) {
      $i++;
      $fid = (int)$student['fan'];
      $stmt2 = mysqli_prepare($link, "SELECT name FROM fan WHERE id=? LIMIT 1");
      mysqli_stmt_bind_param($stmt2, 'i', $fid);
      mysqli_stmt_execute($stmt2);
      $fan = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt2));

      $fio = safe_text($student['fio']);
      $fanName = safe_text($fan['name'] ?? '-');
      $phone = safe_text($student['telefon'] ?? '-');
      $badge = status_badge($student['status'] ?? 'pending');
      $initial = htmlspecialchars(initials($student['fio'] ?? ''), ENT_QUOTES, 'UTF-8');
      ?>
      <tr>
        <td data-label="T/r"><?= $i ?></td>
        <td data-label="FISH">
          <div class="flex items-center gap-3" title="<?= $fio ?>">
            <span class="relative inline-flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-brand to-accent text-xs font-bold text-white">
              <?= $initial ?>
              <span class="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full bg-emerald-400 ring-2 ring-white"></span>
            </span>
            <span class="font-semibold text-slate-800"><?= $fio ?></span>
          </div>
        </td>
        <td data-label="Fan nomi"><?= $fanName ?></td>
        <td data-label="Telefon"><?= $phone ?></td>
        <td data-label="Status"><?= $badge ?></td>
      </tr>
      <?php
    }
    ?>
  </tbody>
</table>
<script>
if (window.jQuery && $('#table_id').length) {
  if ($.fn.DataTable.isDataTable('#table_id')) {
    $('#table_id').DataTable().destroy();
  }
  window.regUsersTable = $('#table_id').DataTable({
    responsive: false,
    pageLength: 20,
    ordering: true,
    language: {
      lengthMenu: "Sahifada _MENU_ ta",
      search: 'Qidiruv',
      paginate: { previous: 'Orqaga', next: 'Keyingi' },
      emptyTable: "Ma'lumot yo'q",
      info: '_START_–_END_ / _TOTAL_',
      infoEmpty: '0–0 / 0',
      zeroRecords: "Topilmadi"
    }
  });
}
</script>
