<?php
set_time_limit(0);
require_once __DIR__ . '/../config.php';
$instUser = requireInstitution();
$org = currentOrg();
$pageTitle = 'Bulk Upload';
if (!$org) redirect('institution/index.php');
$oid = (int) $org['id'];

// Questions must attach to one of this org's assignments (keeps them private).
$tests = supabaseRest('tests?org_id=eq.' . $oid . '&select=id,title&order=title') ?? [];
$ownTestIds = array_column($tests, 'id');

$result = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    requireCsrf();
    $targetTest = (int) ($_POST['test_id'] ?? 0);
    $file = $_FILES['csv_file']['tmp_name'];
    if (!in_array($targetTest, $ownTestIds, true)) {
        $result = 'error:Select one of your assignments to upload into.';
    } elseif (!$file || !is_uploaded_file($file)) {
        $result = 'error:No file uploaded';
    } else {
        $handle = fopen($file, 'r');
        fgetcsv($handle); // header row
        $count = 0; $errors = 0; $dupes = 0;

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 8) { $errors++; continue; }
            $rows[] = $row;
        }
        fclose($handle);

        // Dedup only WITHIN this org's existing questions.
        $existingData = supabaseRest('questions?org_id=eq.' . $oid . '&select=question_text&limit=10000') ?? [];
        $existingTexts = [];
        foreach ($existingData as $eq) $existingTexts[strtolower(trim($eq['question_text']))] = true;

        $batches = array_chunk($rows, 500);
        foreach ($batches as $batch) {
            $questionsData = [];
            $validRows = [];
            foreach ($batch as $row) {
                $subject      = trim($row[0]);
                $chapter      = trim($row[1]);
                $questionText = trim($row[2]);
                $correct      = strtoupper(trim($row[7]));
                $difficulty   = strtolower(trim($row[8] ?? 'medium'));

                if (empty($subject) || empty($questionText)) { $errors++; continue; }
                if (!in_array($correct, ['A','B','C','D'])) { $errors++; continue; }
                if (!in_array($difficulty, ['easy','medium','hard'])) $difficulty = 'medium';

                $key = strtolower($questionText);
                if (isset($existingTexts[$key])) { $dupes++; continue; }
                $existingTexts[$key] = true;

                $questionsData[] = [
                    'test_id'        => $targetTest,
                    'org_id'         => $oid,
                    'subject'        => $subject,
                    'chapter'        => $chapter ?: null,
                    'question_text'  => $questionText,
                    'difficulty'     => $difficulty,
                    'explanation'    => trim($row[9] ?? ''),
                    'marks'          => 2,
                    'negative_marks' => 0.5,
                ];
                $validRows[] = $row;
            }
            if (empty($questionsData)) continue;

            $inserted = supabaseRest('questions', 'POST', $questionsData);
            if (!$inserted) continue;

            $optionsBatch = [];
            foreach ($inserted as $i => $q) {
                $row = $validRows[$i] ?? null;
                if (!$row || empty($q['id'])) continue;
                $correct = strtoupper(trim($row[7]));
                $opts = ['A' => trim($row[3]), 'B' => trim($row[4]), 'C' => trim($row[5]), 'D' => trim($row[6])];
                $pos = 1;
                foreach ($opts as $k => $text) {
                    if (empty($text)) { $pos++; continue; }
                    $optionsBatch[] = [
                        'question_id' => $q['id'],
                        'option_text' => $text,
                        'is_correct'  => ($k === $correct),
                        'position'    => $pos,
                    ];
                    $pos++;
                }
                $count++;
            }
            if ($optionsBatch) supabaseRest('options', 'POST', $optionsBatch);
        }

        $result = "success:Uploaded $count questions" . ($dupes ? " | $dupes duplicates skipped" : "") . ($errors ? " | $errors rows with errors" : "");
    }
}

include __DIR__ . '/includes/header.php';
?>

<?php if ($result):
    $type = str_starts_with($result, 'success') ? 'green' : 'red';
    $msg = substr($result, strpos($result, ':') + 1);
?>
<div class="card" style="border-color: var(--<?= $type ?>); margin-bottom: 16px; padding: 12px 16px; font-size: 13px; color: var(--<?= $type ?>);"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if (!$tests): ?>
<div class="card"><p style="font-size: 13px; color: var(--text-secondary);">Create an <a href="institution/tests.php">assignment</a> first, then upload questions into it.</p></div>
<?php else: ?>
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header"><h3>Upload CSV</h3></div>
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
        <div class="form-group">
            <label>Target Assignment</label>
            <select name="test_id" class="form-control" required>
                <?php foreach ($tests as $t): ?><option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['title']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>CSV File</label>
            <input type="file" name="csv_file" accept=".csv" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">Upload & Import</button>
    </form>
    <div style="margin-top: 16px; padding: 16px; background: var(--bg-primary); border-radius: 8px;">
        <p style="font-size: 13px; font-weight: 600; margin-bottom: 8px;">CSV Format (with header row):</p>
        <code style="font-size: 12px; color: var(--text-secondary); display: block; line-height: 1.8;">
            subject,chapter,question,option_a,option_b,option_c,option_d,correct,difficulty,source<br>
            Physics,Units &amp; Measurement,What is SI unit of power?,Joule,Volt,Watt,Ampere,C,easy,Note<br>
            Maths,Algebra,Solve 2x=6,x=1,x=2,x=3,x=4,C,medium,Note
        </code>
        <p style="font-size: 11px; color: var(--text-muted); margin-top: 8px;">• <strong>correct</strong> = A, B, C, or D &nbsp; • <strong>difficulty</strong> = easy, medium, or hard</p>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Download Template</h3></div>
    <a href="data:text/csv,subject,chapter,question,option_a,option_b,option_c,option_d,correct,difficulty%0APhysics,Mechanics,What is Newton's first law about?,Inertia,Force,Energy,Momentum,A,easy%0AMaths,Algebra,Solve: 2x%2B3%3D7,x%3D1,x%3D2,x%3D3,x%3D4,B,medium" download="ddcet_questions_template.csv" class="btn btn-secondary btn-sm">Download Template CSV</a>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
