<?php
include_once 'ximoya.php';

// Fan tanlanganda AJAX orqali natijalarni qaytarish
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fan_id'])) {
    header('Content-Type: application/json; charset=utf-8');

    $raw_fan_id = $_POST['fan_id'];

    // fan_id raqam bo'lsa — students.fan bo'yicha (admin panel)
    // id_hash string bo'lsa — results.id_hash bo'yicha (ommaviy results.php)
    $is_hash = !ctype_digit((string)$raw_fan_id);

    if ($is_hash) {
        // id_hash orqali so'rov
        $id_hash = preg_match('/^[a-z0-9.]+$/i', $raw_fan_id) ? $raw_fan_id : '';
        if (!$id_hash) {
            echo json_encode(['results' => [], 'stats' => ['student_count' => 0, 'avg_score' => 0]]);
            exit;
        }

        $sql = "
            SELECT
                s.fio,
                s.fakultet,
                COALESCE(r.score,   0) AS togri_javoblar,
                COALESCE(r.wrong,   0) AS notogri_javoblar,
                COALESCE(r.right_p, 0) AS foiz,
                (COALESCE(r.score,0) + COALESCE(r.wrong,0)) AS jami_test,
                r.boshlangan_vaqt, r.t_vaqt
            FROM results r
            LEFT JOIN user     u ON u.login     = r.login
            LEFT JOIN students s ON s.id        = u.student_id
            WHERE r.id_hash = ?
            ORDER BY r.score DESC
        ";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "s", $id_hash);
    } else {
        // integer fan_id orqali so'rov
        $fan_id = (int)$raw_fan_id;
        if ($fan_id <= 0) {
            echo json_encode(['results' => [], 'stats' => ['student_count' => 0, 'avg_score' => 0]]);
            exit;
        }

        $sql = "
            SELECT
                s.fio,
                s.fakultet,
                COALESCE(r.score,   0) AS togri_javoblar,
                COALESCE(r.wrong,   0) AS notogri_javoblar,
                COALESCE(r.right_p, 0) AS foiz,
                (COALESCE(r.score,0) + COALESCE(r.wrong,0)) AS jami_test,
                r.boshlangan_vaqt, r.t_vaqt
            FROM students s
            LEFT JOIN user    u ON u.student_id = s.id
            LEFT JOIN results r ON r.login      = u.login
            WHERE s.fan = ?
            ORDER BY r.score DESC, s.fio ASC
        ";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "i", $fan_id);
    }

    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);


    $results     = [];
    $total_foiz  = 0;
    $has_results = 0;

    while ($row = mysqli_fetch_assoc($res)) {
        // Ishlash vaqtini hisoblash
        if ($row['boshlangan_vaqt'] && $row['t_vaqt']) {
            $seconds = (int)$row['t_vaqt'] - (int)$row['boshlangan_vaqt'];
            $minutes = intdiv($seconds, 60);
            $secs    = $seconds % 60;
            $ishlash_vaqti = sprintf("%02d:%02d", $minutes, $secs);
        } else {
            $ishlash_vaqti = "—";
        }

        $results[] = [
            'fio'             => $row['fio'],
            'fakultet'        => $row['fakultet'],
            'jami_test'       => (int)$row['jami_test'],
            'togri_javoblar'  => (int)$row['togri_javoblar'],
            'notogri_javoblar'=> (int)$row['notogri_javoblar'],
            'foiz'            => (int)$row['foiz'],
            'ishlash_vaqti'   => $ishlash_vaqti,
        ];

        if ($row['foiz'] > 0) {
            $total_foiz += $row['foiz'];
            $has_results++;
        }
    }

    $avg_score = $has_results > 0 ? round($total_foiz / $has_results, 1) : 0;

    echo json_encode([
        'results' => $results,
        'stats'   => [
            'student_count' => count($results),
            'avg_score'     => $avg_score,
        ]
    ]);
    exit;
}

// Fan ro'yxatini olish (HTML sahifa uchun)
$fans = [];
$fans_res = mysqli_query($link, "SELECT * FROM fan ORDER BY name ASC");
while ($f = mysqli_fetch_assoc($fans_res)) {
    $fans[] = $f;
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Talaba Test Natijalari | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            min-height: 100vh; color: #e0e0e0;
        }
        .topnav {
            background: rgba(255,255,255,0.07); backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding: 14px 30px; display: flex; align-items: center; justify-content: space-between;
        }
        .topnav h1 { font-size: 1.2rem; font-weight: 700; color: #fff; }
        .topnav h1 span { color: #7c83fd; }
        .btn-logout {
            background: rgba(231,76,60,0.2); border: 1px solid rgba(231,76,60,0.5);
            color: #e74c3c; padding: 7px 16px; border-radius: 8px; text-decoration: none;
            font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; transition: 0.2s;
        }
        .btn-logout:hover { background: rgba(231,76,60,0.4); }

        .main-container { max-width: 1300px; margin: 0 auto; padding: 30px 20px; }

        .glass-card {
            background: rgba(255,255,255,0.06) !important; border: 1px solid rgba(255,255,255,0.1) !important;
            border-radius: 16px !important; backdrop-filter: blur(10px);
            margin-bottom: 20px;
        }
        .glass-card .card-header {
            background: linear-gradient(135deg, rgba(124,131,253,0.3), rgba(74,0,224,0.3)) !important;
            border-bottom: 1px solid rgba(255,255,255,0.1) !important;
            border-radius: 16px 16px 0 0 !important; padding: 16px 22px;
        }
        .glass-card .card-header h3 { color: #fff; font-weight: 700; font-size: 1.1rem; margin: 0; }
        .glass-card .card-body { padding: 24px; }

        /* Summary */
        .stats-row { display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 20px; }
        .stat-badge {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 8px 16px; border-radius: 12px; font-size: 0.85rem; font-weight: 600;
        }
        .stat-total { background: rgba(52,152,219,0.2); border: 1px solid rgba(52,152,219,0.3); color: #74b9ff; }
        .stat-avg   { background: rgba(243,156,18,0.2); border: 1px solid rgba(243,156,18,0.3); color: #fdcb6e; }

        /* Select */
        .fan-select-wrap { margin-bottom: 20px; }
        label.select-label { color: #a0a8ff; font-size: 0.9rem; font-weight: 500; margin-bottom: 8px; display: block; }
        #fan_select {
            background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);
            color: #fff; padding: 10px 16px; border-radius: 10px; font-size: 0.9rem;
            width: 300px; max-width: 100%; cursor: pointer; outline: none;
        }
        #fan_select option { background: #1e1e3f; }

        /* DataTable */
        table.dataTable { border-collapse: collapse !important; }
        .table { color: #e0e0e0 !important; }
        .table th {
            background: rgba(124,131,253,0.15) !important; color: #a0a8ff !important;
            font-weight: 600; text-align: center; padding: 12px 10px !important;
            border-color: rgba(255,255,255,0.08) !important;
        }
        .table td { padding: 10px 12px !important; border-color: rgba(255,255,255,0.06) !important;
            vertical-align: middle; text-align: center; }
        .table-striped > tbody > tr:nth-of-type(odd) > * { background-color: rgba(255,255,255,0.03) !important; color: #e0e0e0; }
        .table-hover tbody tr:hover { background: rgba(124,131,253,0.08) !important; }

        .dataTables_wrapper .dataTables_filter input,
        .dataTables_wrapper .dataTables_length select {
            background: rgba(255,255,255,0.08) !important; border: 1px solid rgba(255,255,255,0.15) !important;
            color: #fff !important; border-radius: 8px; padding: 6px 12px;
        }
        .dataTables_wrapper .dataTables_filter input::placeholder { color: #888; }
        .dataTables_info, .dataTables_paginate { color: #888 !important; }
        .page-link { background: rgba(255,255,255,0.08) !important; border-color: rgba(255,255,255,0.1) !important; color: #a0a8ff !important; }
        .page-item.active .page-link { background: #7c83fd !important; border-color: #7c83fd !important; color: #fff !important; }
        .dt-buttons { margin-bottom: 14px; }
        .dt-button { background: rgba(255,255,255,0.1) !important; color: #fff !important;
            border: 1px solid rgba(255,255,255,0.15) !important; border-radius: 8px !important; }

        .badge-foiz {
            display: inline-block; padding: 4px 12px; border-radius: 20px;
            font-size: 0.8rem; font-weight: 700;
        }
        .p-high { background: rgba(46,204,113,0.2); color: #55efc4; border: 1px solid rgba(46,204,113,0.3); }
        .p-mid  { background: rgba(243,156,18,0.2); color: #fdcb6e; border: 1px solid rgba(243,156,18,0.3); }
        .p-low  { background: rgba(231,76,60,0.2);  color: #ff7675; border: 1px solid rgba(231,76,60,0.3); }

        .empty-notice {
            text-align: center; padding: 50px 20px; color: #666;
        }
        .empty-notice i { font-size: 2.5rem; margin-bottom: 12px; display: block; color: #444; }

        #loading { display: none; text-align: center; padding: 30px; color: #7c83fd; }
    </style>
</head>
<body>

<?php include_once 'sidebar.php'; ?>

<div class="main-container">
    <div class="card glass-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3><i class="fas fa-chart-bar me-2"></i> Talaba Test Natijalari</h3>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-light" id="btnExcel">
                    <i class="fas fa-file-excel"></i> Excel
                </button>
                <button class="btn btn-sm btn-light" id="btnPdf">
                    <i class="fas fa-file-pdf"></i> PDF
                </button>
                <button class="btn btn-sm btn-light" id="btnPrint">
                    <i class="fas fa-print"></i> Chop etish
                </button>
            </div>
        </div>
        <div class="card-body">

            <!-- Fan tanlash -->
            <div class="fan-select-wrap">
                <label class="select-label" for="fan_select">
                    <i class="fas fa-book"></i> Fanni tanlang:
                </label>
                <select id="fan_select">
                    <option value="">— Fan tanlang —</option>
                    <?php foreach ($fans as $f): ?>
                    <option value="<?= (int)$f['id'] ?>">
                        <?= htmlspecialchars($f['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Statistika -->
            <div class="stats-row" id="statsRow" style="display:none !important;">
                <span class="stat-badge stat-total">
                    <i class="fas fa-users"></i>
                    Jami talabalar: <strong id="studentCount">0</strong>
                </span>
                <span class="stat-badge stat-avg">
                    <i class="fas fa-chart-line"></i>
                    O'rtacha ball: <strong id="avgScore">0%</strong>
                </span>
            </div>

            <!-- Loading -->
            <div id="loading">
                <i class="fas fa-spinner fa-spin fa-2x"></i>
                <p style="margin-top:10px; color:#aaa;">Ma'lumotlar yuklanmoqda...</p>
            </div>

            <!-- Jadval -->
            <div class="table-responsive">
                <table id="resultsTable" class="table table-bordered table-striped table-hover">
                    <thead>
                        <tr>
                            <th>T/r</th>
                            <th>FISH</th>
                            <th>Fakultet</th>
                            <th>Jami test</th>
                            <th>To'g'ri</th>
                            <th>Xato</th>
                            <th>Vaqt</th>
                            <th>Foiz</th>
                        </tr>
                    </thead>
                    <tbody id="results_body">
                    </tbody>
                </table>
            </div>

            <!-- Fan tanlanmagan holat -->
            <div id="noFanMsg">
                <div class="empty-notice">
                    <i class="fas fa-hand-point-up"></i>
                    Natijalarni ko'rish uchun fan tanlang
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

<script>
$(function() {
    // DataTable init
    var dt = $('#resultsTable').DataTable({
        language: {
            lengthMenu:  "Sahifada _MENU_ ta ko'rsatish",
            search:      "Qidiruv:",
            paginate:    { previous: "← Oldingi", next: "Keyingi →" },
            emptyTable:  "Ma'lumot yo'q",
            info:        "_START_–_END_ / _TOTAL_ ta",
            infoEmpty:   "0 ta",
            zeroRecords: "Topilmadi",
        },
        dom: 'Bfrtip',
        buttons: [
            { extend: 'excel', text: '<i class="fas fa-file-excel"></i> Excel',
              className: 'btn btn-success btn-sm', exportOptions: { columns: ':visible' } },
            { extend: 'pdf',   text: '<i class="fas fa-file-pdf"></i> PDF',
              className: 'btn btn-danger btn-sm',  exportOptions: { columns: ':visible' } },
            { extend: 'print', text: '<i class="fas fa-print"></i> Chop',
              className: 'btn btn-secondary btn-sm', exportOptions: { columns: ':visible' } }
        ],
        initComplete: function() {
            $('.dataTables_filter input').attr('placeholder', 'Qidiruv...');
            $('.dt-buttons').hide();
        }
    });

    dt.clear().draw();

    // Fan tanlash
    $('#fan_select').on('change', function() {
        var fanId = $(this).val();

        if (!fanId) {
            $('#noFanMsg').show();
            $('#statsRow').hide();
            dt.clear().draw();
            return;
        }

        $('#noFanMsg').hide();
        $('#loading').show();

        $.ajax({
            url:      'get-results.php',
            type:     'POST',
            data:     { fan_id: fanId },
            dataType: 'json',
            success: function(data) {
                $('#loading').hide();
                dt.clear();

                if (data.results && data.results.length > 0) {
                    $.each(data.results, function(i, item) {
                        var foiz     = item.foiz;
                        var badgeCls = foiz >= 70 ? 'p-high' : (foiz >= 50 ? 'p-mid' : 'p-low');
                        dt.row.add([
                            i + 1,
                            item.fio,
                            item.fakultet,
                            item.jami_test,
                            '<span style="color:#55efc4;font-weight:600;">' + item.togri_javoblar + '</span>',
                            '<span style="color:#ff7675;font-weight:600;">' + item.notogri_javoblar + '</span>',
                            item.ishlash_vaqti,
                            '<span class="badge-foiz ' + badgeCls + '">' + foiz + '%</span>'
                        ]);
                    });

                    $('#studentCount').text(data.stats.student_count);
                    $('#avgScore').text(data.stats.avg_score + '%');
                    $('#statsRow').show();
                } else {
                    $('#statsRow').hide();
                }

                dt.draw();
            },
            error: function() {
                $('#loading').hide();
                alert("Ma'lumotlarni yuklashda xatolik yuz berdi!");
            }
        });
    });

    // Export tugmalar
    $('#btnExcel').click(function() { dt.button('.buttons-excel').trigger(); });
    $('#btnPdf').click(function()   { dt.button('.buttons-pdf').trigger();   });
    $('#btnPrint').click(function() { dt.button('.buttons-print').trigger(); });
});
</script>
    </div> <!-- sb-content -->
</div> <!-- sb-main -->

</body>
</html>