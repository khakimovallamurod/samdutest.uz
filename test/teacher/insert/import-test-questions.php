<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../database/Database.php';
require_once __DIR__ . '/../../shared/dashboard_repository.php';

require_auth(['teacher']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('/test/teacher/tests/index.php'); }
if (!verify_csrf($_POST['_csrf'] ?? '')) { flash_set('error', 'Xavfsizlik tekshiruvi muvaffaqiyatsiz.'); redirect('/test/teacher/tests/index.php'); }

$teacherId = (int) (auth_user()['id'] ?? 0);
$testId = (int) ($_POST['test_id'] ?? 0);
if ($testId <= 0) { flash_set('error', 'Noto\'g\'ri test ID.'); redirect('/test/teacher/tests/index.php'); }
if (empty($_FILES['excel_file']['tmp_name'])) { flash_set('error', 'Excel fayl tanlanmagan.'); redirect('/test/teacher/tests/questions.php?test_id=' . $testId); }
if (!is_uploaded_file($_FILES['excel_file']['tmp_name'])) { flash_set('error', 'Yuklangan fayl topilmadi.'); redirect('/test/teacher/tests/questions.php?test_id=' . $testId); }

$db = Database::connection();
ensure_teacher_tables($db);

$check = $db->prepare('SELECT id FROM tests WHERE id=? AND teacher_id=? LIMIT 1');
$check->bind_param('ii', $testId, $teacherId);
$check->execute();
$exists = $check->get_result()->fetch_assoc();
$check->close();
if (!$exists) { flash_set('error', 'Test topilmadi.'); redirect('/test/teacher/tests/index.php'); }

$sanitize = function ($html) {
    $html = (string) $html;
    $allowed = '<p><br><b><strong><i><em><u><s><sub><sup><ol><ul><li><span><div><img><table><tbody><thead><tr><td><th><math><mrow><mi><mn><mo><msup><msub><msubsup><mfrac><msqrt><mroot><mtext><mfenced><mtable><mtr><mtd>';
    return trim(strip_tags($html, $allowed));
};

$tmp = $_FILES['excel_file']['tmp_name'];
$ext = strtolower(pathinfo((string)($_FILES['excel_file']['name'] ?? ''), PATHINFO_EXTENSION));
if (!in_array($ext, ['xlsx', 'csv'], true)) {
    flash_set('error', 'Faqat .xlsx yoki .csv fayl qabul qilinadi.');
    redirect('/test/teacher/tests/questions.php?test_id=' . $testId);
}

$readRows = function (string $filePath, string $extension): array {
    $result = [];
    if ($extension === 'csv') {
        $fp = fopen($filePath, 'rb');
        if ($fp === false) {
            throw new RuntimeException('CSV ochilmadi.');
        }
        $rowNum = 1;
        while (($cols = fgetcsv($fp)) !== false) {
            $result[$rowNum] = [
                'A' => (string) ($cols[0] ?? ''),
                'B' => (string) ($cols[1] ?? ''),
                'C' => (string) ($cols[2] ?? ''),
                'D' => (string) ($cols[3] ?? ''),
                'E' => (string) ($cols[4] ?? ''),
                'F' => (string) ($cols[5] ?? ''),
                'G' => (string) ($cols[6] ?? ''),
            ];
            $rowNum++;
        }
        fclose($fp);
        return $result;
    }

    if ($extension === 'xlsx') {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('ZipArchive yoqilmagan.');
        }
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new RuntimeException('XLSX fayl ochilmadi.');
        }

        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $sx = simplexml_load_string($sharedXml);
            if ($sx !== false && isset($sx->si)) {
                foreach ($sx->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string) $si->t;
                        continue;
                    }
                    $text = '';
                    if (isset($si->r)) {
                        foreach ($si->r as $run) {
                            $text .= (string) ($run->t ?? '');
                        }
                    }
                    $sharedStrings[] = $text;
                }
            }
        }

        $sheetPath = 'xl/worksheets/sheet1.xml';
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbookXml !== false && $relsXml !== false) {
            $wb = simplexml_load_string($workbookXml);
            $rels = simplexml_load_string($relsXml);
            if ($wb !== false && $rels !== false && isset($wb->sheets->sheet[0])) {
                $firstSheet = $wb->sheets->sheet[0];
                $rid = (string) $firstSheet->attributes('r', true)->id;
                if ($rid !== '') {
                    foreach ($rels->Relationship as $rel) {
                        $id = (string) ($rel['Id'] ?? '');
                        if ($id !== $rid) {
                            continue;
                        }
                        $target = (string) ($rel['Target'] ?? '');
                        if ($target !== '') {
                            $target = ltrim(str_replace('\\', '/', $target), '/');
                            if (strpos($target, 'xl/') !== 0) {
                                $target = 'xl/' . $target;
                            }
                            $sheetPath = $target;
                        }
                        break;
                    }
                }
            }
        }

        $sheetXml = $zip->getFromName($sheetPath);
        if ($sheetXml === false) {
            $zip->close();
            throw new RuntimeException('Worksheet topilmadi: ' . $sheetPath);
        }
        $sheet = simplexml_load_string($sheetXml);
        if ($sheet === false || !isset($sheet->sheetData->row)) {
            $zip->close();
            throw new RuntimeException('XLSX sheet o\'qilmadi.');
        }

        foreach ($sheet->sheetData->row as $row) {
            $rowIndex = (int) ($row['r'] ?? 0);
            if ($rowIndex <= 0) {
                continue;
            }
            $line = ['A' => '', 'B' => '', 'C' => '', 'D' => '', 'E' => '', 'F' => '', 'G' => ''];
            foreach ($row->c as $cell) {
                $ref = (string) ($cell['r'] ?? '');
                if (!preg_match('/^([A-Z]+)/', $ref, $m)) {
                    continue;
                }
                $col = $m[1];
                if (!array_key_exists($col, $line)) {
                    continue;
                }
                $type = (string) ($cell['t'] ?? '');
                if ($type === 's') {
                    $raw = (string) ($cell->v ?? '');
                    $idx = (int) $raw;
                    $line[$col] = (string) ($sharedStrings[$idx] ?? '');
                } elseif ($type === 'inlineStr') {
                    $inline = '';
                    if (isset($cell->is->t)) {
                        $inline = (string) $cell->is->t;
                    } elseif (isset($cell->is->r)) {
                        foreach ($cell->is->r as $run) {
                            $inline .= (string) ($run->t ?? '');
                        }
                    }
                    $line[$col] = $inline;
                } else {
                    $line[$col] = (string) ($cell->v ?? '');
                }
            }
            $result[$rowIndex] = $line;
        }
        $zip->close();
        ksort($result);
        return $result;
    }

    throw new RuntimeException('Faqat CSV yoki XLSX qo\'llab-quvvatlanadi.');
};

try {
    $rows = $readRows($tmp, $ext);
} catch (Throwable $e) {
    flash_set('error', 'Fayl o\'qib bo\'lmadi: ' . $e->getMessage());
    redirect('/test/teacher/tests/questions.php?test_id=' . $testId);
}

try {
    $insert = $db->prepare('INSERT INTO test_questions (test_id,teacher_id,question_text,question_type,option_a,option_b,option_c,option_d,correct_option,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())');
    if (!$insert) {
        throw new RuntimeException('INSERT prepare xatoligi: ' . $db->error);
    }

    $normalizeHeader = function ($value): string {
        $s = (string) $value;
        $s = preg_replace('/^\xEF\xBB\xBF/u', '', $s);
        $s = strtolower(trim($s));
        $s = str_replace(["\t", "\r", "\n"], '', $s);
        $s = preg_replace('/\s+/', '', $s);
        $s = str_replace(['-', '.'], '_', $s);
        return $s;
    };

    $headerAliases = [
        'question_text' => ['question_text', 'savol', 'question', 'savol_matni'],
        'question_type' => ['question_type', 'type', 'tur', 'status', 'javob_turi'],
        'option_a' => ['option_a', 'a', 'varianta'],
        'option_b' => ['option_b', 'b', 'variantb'],
        'option_c' => ['option_c', 'c', 'variantc'],
        'option_d' => ['option_d', 'd', 'variantd'],
        'correct_option' => ['correct_option', 'correct', 'answer', 'javob', 'togri_javob', 'to_gri_javob'],
    ];
    $normalizedToCanonical = [];
    foreach ($headerAliases as $canonical => $aliases) {
        foreach ($aliases as $alias) {
            $normalizedToCanonical[$normalizeHeader($alias)] = $canonical;
        }
    }

    $headerRowIndex = null;
    $columnMap = [];
    foreach ($rows as $idx => $row) {
        $candidate = [];
        foreach ($row as $col => $rawHeader) {
            $normalized = $normalizeHeader($rawHeader);
            if ($normalized === '' || !isset($normalizedToCanonical[$normalized])) {
                continue;
            }
            $candidate[$normalizedToCanonical[$normalized]] = $col;
        }
        if (isset($candidate['question_text'], $candidate['question_type'], $candidate['option_a'], $candidate['option_b'], $candidate['option_c'], $candidate['option_d'], $candidate['correct_option'])) {
            $headerRowIndex = (int) $idx;
            $columnMap = $candidate;
            break;
        }
    }
    if ($headerRowIndex === null) {
        foreach ($rows as $idx => $row) {
            $a1 = $normalizeHeader($row['A'] ?? '');
            $b1 = $normalizeHeader($row['B'] ?? '');
            $c1 = $normalizeHeader($row['C'] ?? '');
            $d1 = $normalizeHeader($row['D'] ?? '');
            $e1 = $normalizeHeader($row['E'] ?? '');
            $f1 = $normalizeHeader($row['F'] ?? '');
            $g1 = $normalizeHeader($row['G'] ?? '');
            if ($a1 === 'a' && $b1 === 'b' && $c1 === 'c' && $d1 === 'd' && $e1 === 'e' && $f1 === 'f' && $g1 === 'g') {
                $headerRowIndex = (int) $idx;
                $columnMap = [
                    'question_text' => 'A',
                    'question_type' => 'B',
                    'option_a' => 'C',
                    'option_b' => 'D',
                    'option_c' => 'E',
                    'option_d' => 'F',
                    'correct_option' => 'G',
                ];
                break;
            }
        }
    }
    if ($headerRowIndex === null) {
        flash_set('error', "Excel header topilmadi. Kerakli ustunlar: Savol, Type, A, B, C, D, Javob.");
        redirect('/test/teacher/tests/questions.php?test_id=' . $testId);
    }

    $created = 0;
    $skipped = 0;
    $errors = [];
    $txStarted = false;
    if (method_exists($db, 'begin_transaction')) {
        $txStarted = $db->begin_transaction();
    }

    foreach ($rows as $idx => $row) {
        if ($idx <= $headerRowIndex) { continue; }

        $qRaw = (string) ($row[$columnMap['question_text']] ?? '');
        $qtRaw = (string) ($row[$columnMap['question_type']] ?? '');
        $aRaw = (string) ($row[$columnMap['option_a']] ?? '');
        $bRaw = (string) ($row[$columnMap['option_b']] ?? '');
        $cRaw = (string) ($row[$columnMap['option_c']] ?? '');
        $dRaw = (string) ($row[$columnMap['option_d']] ?? '');
        $coRaw = (string) ($row[$columnMap['correct_option']] ?? '');

        $q = $sanitize($qRaw);
        $qt = strtolower(trim($qtRaw));
        $a = $sanitize($aRaw);
        $b = $sanitize($bRaw);
        $c = $sanitize($cRaw);
        $d = $sanitize($dRaw);
        $co = strtoupper(trim($coRaw));

        $isCompletelyEmpty = trim($qRaw) === ''
            && trim($qtRaw) === ''
            && trim($aRaw) === ''
            && trim($bRaw) === ''
            && trim($cRaw) === ''
            && trim($dRaw) === ''
            && trim($coRaw) === '';
        if ($isCompletelyEmpty) {
            continue;
        }

        if (trim(strip_tags($q)) === '') {
            $skipped++;
            $errors[] = "{$idx}-qator: question_text bo'sh.";
            continue;
        }
        if (!in_array($qt, ['open', 'closed'], true)) {
            $skipped++;
            $errors[] = "{$idx}-qator: question_type faqat open/closed bo'lishi kerak.";
            continue;
        }

        if ($qt === 'open') {
            if ($a === '' || $b === '' || $c === '' || $d === '' || !in_array($co, ['A', 'B', 'C', 'D'], true)) {
                $skipped++;
                $errors[] = "{$idx}-qator: open savolda C..F va G (A/B/C/D) majburiy.";
                continue;
            }
        } else {
            $a = $b = $c = $d = null;
            $co = null;
        }

        $insert->bind_param('iisssssss', $testId, $teacherId, $q, $qt, $a, $b, $c, $d, $co);
        if ($insert->execute()) {
            $created++;
        } else {
            $skipped++;
            $errors[] = "{$idx}-qator: bazaga saqlashda xato.";
        }
    }

    if ($txStarted) {
        $db->commit();
    }
    $insert->close();
    if ($created > 0) {
        $msg = $created . " ta savol import qilindi.";
        if ($skipped > 0) {
            $msg .= " {$skipped} ta qator o'tkazib yuborildi.";
            if (count($errors) > 0) {
                $msg .= ' ' . implode(' | ', array_slice($errors, 0, 5));
                if (count($errors) > 5) {
                    $msg .= ' ...';
                }
            }
        }
        flash_set('success', $msg);
    } else {
        flash_set('error', "Yaroqli qator topilmadi. " . (count($errors) ? implode(' | ', array_slice($errors, 0, 5)) : ''));
    }
} catch (Throwable $e) {
    if (isset($txStarted) && $txStarted && method_exists($db, 'rollback')) {
        $db->rollback();
    }
    error_log('Import test questions failed: ' . $e->getMessage());
    flash_set('error', 'Importda xatolik yuz berdi: ' . $e->getMessage());
}
redirect('/test/teacher/tests/questions.php?test_id=' . $testId);
