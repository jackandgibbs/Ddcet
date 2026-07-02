<?php
/* ---------------------------------------------------------------------------
 * Admin · Preview Questions
 *
 * Renders every question exactly as a student sees it (KaTeX math, options,
 * highlighted correct answer, explanation) so an admin can eyeball the bank for
 * mistakes. Flags structural problems per question: no correct answer set,
 * multiple correct answers, or fewer than two options.
 * ------------------------------------------------------------------------- */
require_once __DIR__ . '/../config.php';
requireAdmin();
$pageTitle = 'Preview Questions';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    requireCsrf();
    $qid = (int) ($_POST['question_id'] ?? 0);
    if ($qid) {
        supabaseRest('options?question_id=eq.' . $qid, 'DELETE');
        supabaseRest('questions?id=eq.' . $qid, 'DELETE');
        foreach (glob(sys_get_temp_dir() . '/ddcet_*.json') ?: [] as $f) @unlink($f);
        $msg = 'success:Question #' . $qid . ' deleted.';
    }
}

/* ---- structural problem detector (shared) ------------------------------ */
function question_warnings(array $opts): array {
    $correct = array_filter($opts, fn($o) => $o['is_correct']);
    $w = [];
    if (count($opts) < 2)      $w[] = 'Fewer than 2 options';
    if (count($correct) === 0) $w[] = 'No correct answer set';
    if (count($correct) > 1)   $w[] = 'Multiple correct answers';
    return $w;
}

/* ---- filters + pagination ---------------------------------------------- */
$perPage    = 20;
$page       = max(1, (int) ($_GET['page'] ?? 1));
$offset     = ($page - 1) * $perPage;
$subject    = trim($_GET['subject'] ?? '');
$search     = trim($_GET['q'] ?? '');
$testId     = (int) ($_GET['test_id'] ?? 0);
$issuesOnly = !empty($_GET['issues']);

$where = [];
if ($subject !== '') $where[] = 'subject=eq.' . rawurlencode($subject);
if ($search  !== '') $where[] = 'question_text=ilike.*' . rawurlencode($search) . '*';
if ($testId)         $where[] = 'test_id=eq.' . $testId;

$optsByQ = $questions = [];
$totalIssues = null;

if ($issuesOnly) {
    // Issues mode can't be a DB filter (warnings depend on the options join),
    // so pull every matching question + its options, flag in PHP, then paginate.
    // Page through questions (PostgREST caps each response at 1000 rows).
    $all = []; $qoff = 0;
    do {
        $allPath = 'questions?select=*&order=id.desc&limit=1000&offset=' . $qoff;
        foreach ($where as $w) $allPath .= '&' . $w;
        $pg = supabaseRest($allPath) ?? [];
        $all = array_merge($all, $pg);
        $qoff += 1000;
    } while (count($pg) === 1000);

    // Fetch options in id-chunks small enough to stay under the 1000-row cap
    // (≈4 options per question → 150 ids ≈ 600 rows).
    $allOpts = [];
    foreach (array_chunk(array_column($all, 'id'), 150) as $batch) {
        if (!$batch) continue;
        $os = supabaseRest('options?question_id=in.(' . implode(',', $batch) . ')&select=*&order=position') ?? [];
        foreach ($os as $o) $allOpts[$o['question_id']][] = $o;
    }

    $flagged = array_values(array_filter($all, fn($q) => question_warnings($allOpts[$q['id']] ?? [])));
    $totalIssues = count($flagged);
    $questions = array_slice($flagged, $offset, $perPage);
    $optsByQ = $allOpts;   // already holds this page's options
    $hasNext = ($offset + $perPage) < $totalIssues;
} else {
    $path = 'questions?select=*&order=id.desc&limit=' . $perPage . '&offset=' . $offset;
    foreach ($where as $w) $path .= '&' . $w;
    $questions = supabaseRest($path) ?? [];

    $ids = array_column($questions, 'id');
    if ($ids) {
        $opts = supabaseRest('options?question_id=in.(' . implode(',', $ids) . ')&select=*&order=position') ?? [];
        foreach ($opts as $o) $optsByQ[$o['question_id']][] = $o;
    }
    $hasNext = count($questions) === $perPage;
}

// Filter dropdowns.
$subjRows = supabaseRest('questions?select=subject&limit=10000') ?? [];
$subjects = array_values(array_unique(array_filter(array_map(fn($r) => $r['subject'] ?? '', $subjRows))));
sort($subjects);
$tests = supabaseRest('tests?select=id,title&order=title') ?? [];

function buildQs(array $overrides): string {
    $base = ['subject' => $_GET['subject'] ?? '', 'q' => $_GET['q'] ?? '', 'test_id' => $_GET['test_id'] ?? '', 'issues' => $_GET['issues'] ?? '', 'page' => $_GET['page'] ?? 1];
    $merged = array_merge($base, $overrides);
    return 'admin/preview.php?' . http_build_query(array_filter($merged, fn($v) => $v !== '' && $v !== null));
}

include __DIR__ . '/includes/header.php';
?>

<?php if ($msg): $type = str_starts_with($msg, 'success') ? 'green' : 'red'; ?>
<div class="card" style="border-color: var(--<?= $type ?>); margin-bottom: 16px; padding: 12px 16px; font-size: 13px; color: var(--<?= $type ?>);"><?= htmlspecialchars(substr($msg, strpos($msg, ':') + 1)) ?></div>
<?php endif; ?>

<!-- Filters -->
<div class="card" style="margin-bottom: 20px;">
    <form method="GET" style="display: flex; gap: 12px; align-items: end; flex-wrap: wrap;">
        <div class="form-group" style="margin-bottom: 0;">
            <label>Subject</label>
            <select name="subject" class="form-control" style="width: 180px;">
                <option value="">All subjects</option>
                <?php foreach ($subjects as $s): ?>
                    <option value="<?= htmlspecialchars($s) ?>" <?= $subject === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin-bottom: 0;">
            <label>Test</label>
            <select name="test_id" class="form-control" style="width: 200px;">
                <option value="0">All / Pool</option>
                <?php foreach ($tests as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $testId == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 160px;">
            <label>Search text</label>
            <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Find in question text…">
        </div>
        <label style="display:flex; align-items:center; gap:6px; font-size:13px; white-space:nowrap; padding-bottom:8px;">
            <input type="checkbox" name="issues" value="1" <?= $issuesOnly ? 'checked' : '' ?>> Issues only
        </label>
        <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
        <a href="admin/preview.php" class="btn btn-secondary btn-sm">Reset</a>
    </form>
</div>

<?php if ($issuesOnly): ?>
<div class="card" style="margin-bottom: 16px; padding: 12px 16px; font-size: 13px; <?= $totalIssues ? 'border-color: var(--red); color: var(--red);' : 'border-color: var(--green); color: var(--green);' ?>">
    <?php if ($totalIssues): ?>
        <?= icon('warning', 14) ?> <strong><?= number_format($totalIssues) ?></strong> question(s) with structural problems match these filters (no correct answer, too few options, or multiple correct).
    <?php else: ?>
        <?= icon('check', 14) ?> No structural problems found for these filters.
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (!$questions): ?>
    <div class="card" style="text-align:center; padding:40px; color:var(--text-muted);"><?= $issuesOnly ? 'No questions with issues here.' : 'No questions match these filters.' ?></div>
<?php else: ?>

<?php
$letters = ['A','B','C','D','E','F'];
foreach ($questions as $q):
    $opts = $optsByQ[$q['id']] ?? [];
    $warn = question_warnings($opts);
?>
<div class="card" style="margin-bottom: 16px;">
    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:12px;">
        <span class="tag" style="font-family: var(--font-mono);">#<?= $q['id'] ?></span>
        <span class="tag"><?= htmlspecialchars($q['subject'] ?? '') ?></span>
        <?php if (!empty($q['chapter'])): ?><span class="tag"><?= htmlspecialchars($q['chapter']) ?></span><?php endif; ?>
        <span class="badge <?= ($q['difficulty'] ?? '') === 'hard' ? 'badge-red' : (($q['difficulty'] ?? '') === 'easy' ? 'badge-green' : 'badge-accent') ?>"><?= htmlspecialchars($q['difficulty'] ?? '') ?></span>
        <span class="tag"><?= (int)($q['marks'] ?? 1) ?> mark(s)</span>
        <span class="tag" style="color:var(--text-muted);"><?= $q['test_id'] ? 'Test #' . (int)$q['test_id'] : 'Pool' ?></span>
        <?php foreach ($warn as $w): ?>
            <span class="badge badge-red"><?= icon('warning', 12) ?> <?= htmlspecialchars($w) ?></span>
        <?php endforeach; ?>
        <form method="POST" style="margin-left:auto;" onsubmit="return confirm('Delete question #<?= $q['id'] ?>? This also deletes its options.')">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
            <button type="submit" class="btn btn-sm btn-danger"><?= icon('x', 12) ?> Delete</button>
        </form>
    </div>

    <p class="mathy" style="font-size:15px; line-height:1.7; margin-bottom:12px;"><?= htmlspecialchars($q['question_text'] ?? '') ?></p>
    <?php if (!empty($q['question_image'])): ?>
        <img src="<?= htmlspecialchars($q['question_image']) ?>" style="max-width:320px; border-radius:8px; margin-bottom:12px;">
    <?php endif; ?>

    <div style="display:flex; flex-direction:column; gap:6px; margin-bottom:12px;">
        <?php foreach ($opts as $oi => $o):
            $isCorrect = $o['is_correct'];
            $style = $isCorrect ? 'border-color: var(--green); background: rgba(0,184,148,0.10);' : 'border-color: var(--border);';
        ?>
            <div style="padding:8px 12px; border:1px solid; border-radius:6px; font-size:13px; display:flex; align-items:center; gap:10px; <?= $style ?>">
                <span style="font-weight:600; color:var(--text-muted);"><?= $letters[$oi] ?? '?' ?></span>
                <span class="mathy"><?= htmlspecialchars($o['option_text'] ?? '') ?></span>
                <?php if (!empty($o['option_image'])): ?><img src="<?= htmlspecialchars($o['option_image']) ?>" style="max-width:120px; margin-left:8px;"><?php endif; ?>
                <?php if ($isCorrect): ?><span style="margin-left:auto; color:var(--green); font-weight:600;"><?= icon('check', 14) ?> Correct</span><?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php if (!$opts): ?><div style="font-size:13px; color:var(--red);">No options stored for this question.</div><?php endif; ?>
    </div>

    <?php if (!empty($q['explanation'])): ?>
        <div class="mathy" style="background:var(--bg-primary); border-radius:6px; padding:10px 12px; font-size:13px; color:var(--text-secondary); line-height:1.6;">
            <strong style="color:var(--accent);">Explanation:</strong> <?= htmlspecialchars($q['explanation']) ?>
        </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<!-- Pagination -->
<div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px;">
    <div>
        <?php if ($page > 1): ?>
            <a href="<?= htmlspecialchars(buildQs(['page' => $page - 1])) ?>" class="btn btn-secondary btn-sm">← Previous</a>
        <?php endif; ?>
    </div>
    <span style="font-size:13px; color:var(--text-muted);">Page <?= $page ?></span>
    <div>
        <?php if ($hasNext): ?>
            <a href="<?= htmlspecialchars(buildQs(['page' => $page + 1])) ?>" class="btn btn-secondary btn-sm">Next →</a>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
