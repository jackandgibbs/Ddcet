<?php
set_time_limit(0); // No timeout for bulk upload
require_once __DIR__ . '/../config.php';
requireAdmin();
$pageTitle = 'Bulk Upload Questions';

$result = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    if (!$file || !is_uploaded_file($file)) {
        $result = 'error:No file uploaded';
    } else {
        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);
        $count = 0;
        $errors = 0;

        // Collect all rows first
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 8) { $errors++; continue; }
            $rows[] = $row;
        }
        fclose($handle);

        // Fetch ALL existing question texts for dedup
        $existingData = supabaseRest('questions?select=question_text&limit=10000') ?? [];
        $existingTexts = [];
        foreach ($existingData as $eq) {
            $existingTexts[strtolower(trim($eq['question_text']))] = true;
        }
        $dupes = 0;

        // Batch insert questions (50 at a time)
        $batches = array_chunk($rows, 500);
        foreach ($batches as $batch) {
            $questionsData = [];
            $validRows = [];
            foreach ($batch as $row) {
                $subject = trim($row[0]);
                $chapter = trim($row[1]);
                $questionText = trim($row[2]);
                $correct = strtoupper(trim($row[7]));
                $difficulty = strtolower(trim($row[8] ?? 'medium'));

                if (empty($subject) || empty($questionText)) { $errors++; continue; }
                if (!in_array($correct, ['A','B','C','D'])) { $errors++; continue; }
                if (!in_array($difficulty, ['easy','medium','hard'])) $difficulty = 'medium';

                // Guard against the two ways a question ends up unusable:
                //  (1) the correct option's TEXT is empty → it gets skipped below
                //      and the question is saved with no correct answer;
                //  (2) fewer than two options have text → not a real MCQ.
                $optText = ['A' => trim($row[3] ?? ''), 'B' => trim($row[4] ?? ''), 'C' => trim($row[5] ?? ''), 'D' => trim($row[6] ?? '')];
                $nonEmpty = array_filter($optText, fn($t) => $t !== '');
                if (empty($optText[$correct])) { $errors++; continue; }   // correct option blank
                if (count($nonEmpty) < 2)       { $errors++; continue; }   // not enough options

                // DUPLICATE CHECK
                $key = strtolower($questionText);
                if (isset($existingTexts[$key])) { $dupes++; continue; }
                $existingTexts[$key] = true; // Also skip within same upload

                $questionsData[] = [
                    'test_id' => null,
                    'subject' => $subject,
                    'chapter' => $chapter ?: null,
                    'question_text' => $questionText,
                    'difficulty' => $difficulty,
                    'explanation' => trim($row[9] ?? ''),
                    'marks' => 2,
                    'negative_marks' => 0.5,
                ];
                $validRows[] = $row;
            }

            if (empty($questionsData)) continue;

            // Bulk insert questions
            $inserted = supabaseRest('questions', 'POST', $questionsData);
            if (!$inserted) continue;

            // Now insert options for each question
            $optionsBatch = [];
            foreach ($inserted as $i => $q) {
                $row = $validRows[$i] ?? null;
                if (!$row || !$q['id']) continue;
                $correct = strtoupper(trim($row[7]));
                $opts = ['A' => trim($row[3]), 'B' => trim($row[4]), 'C' => trim($row[5]), 'D' => trim($row[6])];
                $pos = 1;
                foreach ($opts as $key => $text) {
                    if (empty($text)) { $pos++; continue; }
                    $optionsBatch[] = [
                        'question_id' => $q['id'],
                        'option_text' => $text,
                        'is_correct' => ($key === $correct),
                        'position' => $pos,
                    ];
                    $pos++;
                }
                $count++;
            }

            // Bulk insert options. There are no transactions across the two
            // POSTs, so if the options insert fails we delete the questions we
            // just created — otherwise they linger with no options/answer.
            if ($optionsBatch) {
                $optIns = supabaseRest('options', 'POST', $optionsBatch);
                if ($optIns === null) {
                    $orphanIds = array_filter(array_column($inserted, 'id'));
                    if ($orphanIds) supabaseRest('questions?id=in.(' . implode(',', $orphanIds) . ')', 'DELETE');
                    $count -= count($orphanIds);
                    $errors += count($orphanIds);
                }
            }
        }

        foreach (glob(sys_get_temp_dir() . '/ddcet_*.json') ?: [] as $f) @unlink($f);
        $result = "success:Uploaded $count questions successfully" . ($dupes ? " | $dupes duplicates skipped" : "") . ($errors ? " | $errors rows skipped/failed" : "");
    }
}

// Pool stats
$poolQuestions = supabaseRest('questions?test_id=is.null&select=subject') ?? [];
$subjectCounts = [];
foreach ($poolQuestions as $q) {
    $s = $q['subject'] ?? 'Unknown';
    $subjectCounts[$s] = ($subjectCounts[$s] ?? 0) + 1;
}
$totalPool = count($poolQuestions);

include __DIR__ . '/includes/header.php';
?>

<?php if ($result):
    $type = str_starts_with($result, 'success') ? 'green' : 'red';
    $msg = substr($result, strpos($result, ':') + 1);
?>
<div class="card" style="border-color: var(--<?= $type ?>); margin-bottom: 16px; padding: 12px 16px; font-size: 13px; color: var(--<?= $type ?>);"><?= $msg ?></div>
<?php endif; ?>

<!-- Pool Stats -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header"><h3>Question Pool — <?= $totalPool ?> questions</h3></div>
    <?php if ($subjectCounts): ?>
    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
        <?php foreach ($subjectCounts as $sub => $cnt): ?>
            <div style="background: var(--bg-primary); border-radius: 8px; padding: 12px 16px; text-align: center;">
                <div style="font-family: var(--font-mono); font-size: 20px; font-weight: 700; color: var(--accent);"><?= $cnt ?></div>
                <div style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($sub) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
        <p style="color: var(--text-muted);">No questions in pool yet. Upload a CSV below.</p>
    <?php endif; ?>
</div>

<!-- Upload Form -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header"><h3>Upload CSV</h3></div>
    <form method="POST" enctype="multipart/form-data">
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
            Physics,Units & Measurement,What is SI unit of power?,Joule,Volt,Watt,Ampere,C,easy,DE-2024<br>
            Maths,Algebra,Solve 2x=6,x=1,x=2,x=3,x=4,C,medium,DE-2025
        </code>
        <p style="font-size: 11px; color: var(--text-muted); margin-top: 8px;">
            • <strong>correct</strong> = A, B, C, or D<br>
            • <strong>difficulty</strong> = easy, medium, or hard<br>
            • <strong>source</strong> = year tag like DE-2024, DE-2025 (used for Previous Year Papers)
        </p>
    </div>
</div>

<!-- Download Template -->
<div class="card">
    <div class="card-header"><h3>Download Template</h3></div>
    <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 12px;">Download a sample CSV template, fill it with your questions, then upload above.</p>
    <a href="data:text/csv,subject,chapter,question,option_a,option_b,option_c,option_d,correct,difficulty%0APhysics,Mechanics,What is Newton's first law about?,Inertia,Force,Energy,Momentum,A,easy%0AMaths,Algebra,Solve: 2x%2B3%3D7,x%3D1,x%3D2,x%3D3,x%3D4,B,medium" download="ddcet_questions_template.csv" class="btn btn-secondary btn-sm">Download Template CSV</a>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
