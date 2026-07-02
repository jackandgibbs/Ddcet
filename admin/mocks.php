<?php
require_once __DIR__ . '/../config.php';
requireAdmin();
$pageTitle = 'Mock Series';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        // datetime-local fields arrive as "Y-m-dTH:i"; normalise to a timestamp
        // string Postgres accepts. Empty -> null.
        $toTs = fn($v) => $v ? date('Y-m-d H:i:s', strtotime($v)) : null;
        $wantQ = (int) $_POST['total_questions'];
        $created = supabaseRest('tests', 'POST', [
            'title'            => $_POST['title'] ?: ($_POST['series_label'] ?: 'DDCET Mock'),
            'series_label'     => $_POST['series_label'] ?: null,
            'description'      => $_POST['description'] ?: '',
            'mode'             => 'full_mock',
            'duration_minutes' => (int) $_POST['duration_minutes'],
            'total_marks'      => (int) $_POST['total_marks'],
            'total_questions'  => $wantQ,
            'difficulty'       => 'medium',
            'min_plan'         => $_POST['min_plan'] ?? 'pro',
            'is_free'          => isset($_POST['is_free']),
            'is_scheduled'     => true,
            'opens_at'         => $toTs($_POST['opens_at'] ?? ''),
            'closes_at'        => $toTs($_POST['closes_at'] ?? ''),
            'results_at'       => $toTs($_POST['results_at'] ?? ''),
            'is_published'     => true,   // visible on the series page immediately
            'results_published'=> false,
        ]);
        $mockId = $created[0]['id'] ?? null;

        // Optionally snapshot a paper from the pool on the DDCET pattern.
        if ($mockId && isset($_POST['generate_questions'])) {
            $generated = generateMockFromPool((int) $mockId, $wantQ);
            header('Location: ' . BASE_PATH . 'admin/mocks.php?generated=' . $generated . '&want=' . $wantQ);
            exit;
        }
    } elseif ($action === 'publish_results') {
        supabaseRest('tests?id=eq.' . (int) $_POST['test_id'], 'PATCH', ['results_published' => true]);
    } elseif ($action === 'unpublish_results') {
        supabaseRest('tests?id=eq.' . (int) $_POST['test_id'], 'PATCH', ['results_published' => false]);
    } elseif ($action === 'toggle') {
        $tid = (int) $_POST['test_id'];
        $t = supabaseRest('tests?id=eq.' . $tid . '&select=is_published&limit=1');
        supabaseRest('tests?id=eq.' . $tid, 'PATCH', ['is_published' => empty($t[0]['is_published'])]);
    } elseif ($action === 'delete') {
        supabaseRest('tests?id=eq.' . (int) $_POST['test_id'], 'DELETE');
    }
    header('Location: ' . BASE_PATH . 'admin/mocks.php');
    exit;
}

/**
 * Build a frozen mock paper by SNAPSHOTTING pool questions (test_id IS NULL)
 * onto this mock, following the DDCET syllabus distribution. Questions and their
 * options are duplicated (not moved) so the shared pool stays intact and every
 * student sees the exact same paper. Returns how many questions were attached.
 */
function generateMockFromPool(int $mockId, int $totalWanted): int {
    $dist = ddcetSyllabusDistribution($totalWanted);

    // 1. Pick source questions per subject from the pool.
    $picked = [];
    foreach ($dist as $subject => $count) {
        if ($count <= 0) continue;
        $pool = supabaseRest('questions?test_id=is.null&subject=eq.' . urlencode($subject)
            . '&select=*&limit=1000') ?? [];
        shuffle($pool);
        $picked = array_merge($picked, array_slice($pool, 0, $count));
    }
    if (!$picked) return 0;

    // 2. Insert the duplicated question rows in one bulk POST. PostgREST returns
    //    the inserted rows in input order, so $inserted[$i] maps to $picked[$i].
    $newRows = [];
    foreach ($picked as $i => $q) {
        $newRows[] = [
            'test_id'        => $mockId,
            'subject'        => $q['subject'],
            'chapter'        => $q['chapter'] ?? null,
            'question_text'  => $q['question_text'],
            'question_image' => $q['question_image'] ?? null,
            'explanation'    => $q['explanation'] ?? null,
            'difficulty'     => $q['difficulty'] ?? 'medium',
            'marks'          => $q['marks'] ?? 1,
            'negative_marks' => $q['negative_marks'] ?? 0,
            'position'       => $i + 1,
        ];
    }
    $inserted = supabaseRest('questions', 'POST', $newRows) ?? [];
    if (!$inserted) return 0;

    // 3. Fetch every source question's options in one call, grouped by source id.
    $srcIds = array_column($picked, 'id');
    $srcOpts = supabaseRest('options?question_id=in.(' . implode(',', $srcIds)
        . ')&select=*&order=position') ?? [];
    $optsBySrc = [];
    foreach ($srcOpts as $o) $optsBySrc[$o['question_id']][] = $o;

    // 4. Duplicate the options onto the new question ids and bulk-insert.
    $newOpts = [];
    foreach ($picked as $i => $q) {
        $newQid = $inserted[$i]['id'] ?? null;
        if (!$newQid) continue;
        foreach ($optsBySrc[$q['id']] ?? [] as $o) {
            $newOpts[] = [
                'question_id'  => $newQid,
                'option_text'  => $o['option_text'],
                'option_image' => $o['option_image'] ?? null,
                'is_correct'   => !empty($o['is_correct']),
                'position'     => $o['position'] ?? 0,
            ];
        }
    }
    if ($newOpts) supabaseRest('options', 'POST', $newOpts);

    return count($inserted);
}

$mocks = supabaseRest('tests?is_scheduled=eq.true&select=*&order=opens_at.desc') ?? [];
$ids = array_column($mocks, 'id');

// Batch counts: registrations, completed attempts, and assigned questions.
$regCount = []; $attCount = []; $qCount = [];
if ($ids) {
    $idList = implode(',', $ids);
    foreach (supabaseRest('mock_registrations?test_id=in.(' . $idList . ')&select=test_id') ?? [] as $r)
        $regCount[$r['test_id']] = ($regCount[$r['test_id']] ?? 0) + 1;
    foreach (supabaseRest('attempts?test_id=in.(' . $idList . ')&status=eq.completed&select=test_id') ?? [] as $r)
        $attCount[$r['test_id']] = ($attCount[$r['test_id']] ?? 0) + 1;
    foreach (supabaseRest('questions?test_id=in.(' . $idList . ')&select=test_id') ?? [] as $r)
        $qCount[$r['test_id']] = ($qCount[$r['test_id']] ?? 0) + 1;
}

include __DIR__ . '/includes/header.php';
?>

<?php if (isset($_GET['generated'])):
    $gen = (int) $_GET['generated']; $want = (int) ($_GET['want'] ?? 0);
    $short = $want > 0 && $gen < $want;
?>
<div class="card" style="margin-bottom:16px; padding:12px 16px; font-size:13px; border-color:<?= $short ? 'var(--orange)' : 'var(--green)' ?>; color:<?= $short ? 'var(--orange)' : 'var(--green)' ?>;">
    <?php if ($gen === 0): ?>
        No questions were generated — the pool has no matching questions yet. Add questions to the pool, then use <strong>Questions</strong> to attach them manually.
    <?php elseif ($short): ?>
        Generated <strong><?= $gen ?></strong> of <?= $want ?> questions — the pool ran short in some subjects. Top it up and add the rest via <strong>Questions</strong>.
    <?php else: ?>
        Generated <strong><?= $gen ?></strong> questions from the pool on the DDCET pattern. The paper is frozen — same for every student.
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:20px;">
    <div class="card-header"><h3>Schedule a Mock</h3></div>
    <p style="font-size:12px; color:var(--text-muted); margin-bottom:14px;">
        Creates a fixed-window mock. After saving, click <strong>Questions</strong> to attach the paper.
        Set one mock per series as <strong>Free</strong> for acquisition; keep the rest Pro.
        Times are in IST (<?= date('d M Y, g:i A') ?> now).
    </p>
    <form method="POST">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
        <input type="hidden" name="action" value="create">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
            <div class="form-group"><label>Series label (shown to users)</label><input type="text" name="series_label" class="form-control" placeholder="DDCET Mock #12" required></div>
            <div class="form-group"><label>Internal title</label><input type="text" name="title" class="form-control" placeholder="DDCET Mock #12 — Sunday"></div>
        </div>
        <div class="form-group"><label>Description</label><input type="text" name="description" class="form-control"></div>
        <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:12px;">
            <div class="form-group"><label>Opens at</label><input type="datetime-local" name="opens_at" class="form-control" required></div>
            <div class="form-group"><label>Closes at</label><input type="datetime-local" name="closes_at" class="form-control" required></div>
            <div class="form-group"><label>Results at (AIR + solutions)</label><input type="datetime-local" name="results_at" class="form-control"></div>
        </div>
        <div style="display:grid; grid-template-columns:repeat(5, 1fr); gap:12px;">
            <div class="form-group"><label>Duration (min)</label><input type="number" name="duration_minutes" class="form-control" value="150"></div>
            <div class="form-group"><label>Total Marks</label><input type="number" name="total_marks" class="form-control" value="200"></div>
            <div class="form-group"><label>Questions</label><input type="number" name="total_questions" class="form-control" value="100"></div>
            <div class="form-group"><label>Min Plan</label><select name="min_plan" class="form-control"><option value="basic">Basic</option><option value="pro" selected>Pro</option></select></div>
            <div class="form-group" style="display:flex; align-items:end;"><label><input type="checkbox" name="is_free"> Free mock</label></div>
        </div>
        <div style="background:var(--bg-primary); border:1px solid var(--border); border-radius:8px; padding:12px 14px; margin-bottom:14px;">
            <label style="display:flex; align-items:center; gap:8px; font-weight:600; font-size:13px;">
                <input type="checkbox" name="generate_questions" checked>
                Auto-generate paper from the pool (DDCET syllabus pattern)
            </label>
            <p style="font-size:11px; color:var(--text-muted); margin:6px 0 0 24px;">
                Picks random pool questions per the real DDCET split — Physics 30 · Chemistry 15 · Computers 5 · Maths 25 · English 20 · Environment 5 (scaled to your question count) — and snapshots them into this mock so everyone gets the same paper. Leave unchecked to attach questions manually.
            </p>
        </div>
        <button type="submit" class="btn btn-primary">Schedule Mock</button>
    </form>
</div>

<div class="card">
    <div class="card-header"><h3>Scheduled Mocks (<?= count($mocks) ?>)</h3></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Mock</th><th>Window</th><th>Status</th><th>Qs</th><th>Reg.</th><th>Attempts</th><th>Results</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($mocks as $m):
                $status = mockStatus($m);
                $statusColor = ['live'=>'badge-green','upcoming'=>'badge-purple','grading'=>'','results'=>'badge-accent'][$status] ?? '';
            ?>
                <tr>
                    <td>
                        <div style="font-weight:600;"><?= htmlspecialchars($m['series_label'] ?: $m['title']) ?></div>
                        <div style="font-size:11px; color:var(--text-muted);">
                            <?= $m['is_free'] ? '<span class="badge badge-green">Free</span>' : '<span class="badge badge-accent">'.htmlspecialchars(ucfirst($m['min_plan'] ?? 'pro')).'</span>' ?>
                            <?= empty($m['is_published']) ? '<span class="badge badge-red">Hidden</span>' : '' ?>
                        </div>
                    </td>
                    <td style="font-size:11px; color:var(--text-secondary);">
                        <?= !empty($m['opens_at']) ? date('d M, g:i A', strtotime($m['opens_at'])) : '—' ?>
                        →
                        <?= !empty($m['closes_at']) ? date('g:i A', strtotime($m['closes_at'])) : '—' ?>
                    </td>
                    <td><span class="badge <?= $statusColor ?>" style="<?= $statusColor === '' ? 'background:#444;color:#bbb;' : '' ?>"><?= strtoupper($status) ?></span></td>
                    <td style="font-family:var(--font-mono);"><?= $qCount[$m['id']] ?? 0 ?></td>
                    <td style="font-family:var(--font-mono);"><?= $regCount[$m['id']] ?? 0 ?></td>
                    <td style="font-family:var(--font-mono);"><?= $attCount[$m['id']] ?? 0 ?></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
                            <input type="hidden" name="action" value="<?= !empty($m['results_published']) ? 'unpublish_results' : 'publish_results' ?>">
                            <input type="hidden" name="test_id" value="<?= $m['id'] ?>">
                            <button type="submit" class="badge <?= !empty($m['results_published']) ? 'badge-green' : 'badge-red' ?>" style="border:none;cursor:pointer;"><?= !empty($m['results_published']) ? 'Published' : 'Publish' ?></button>
                        </form>
                    </td>
                    <td style="white-space:nowrap;">
                        <a href="admin/questions.php?test_id=<?= $m['id'] ?>" class="btn btn-sm btn-secondary">Questions</a>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="test_id" value="<?= $m['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline"><?= empty($m['is_published']) ? 'Show' : 'Hide' ?></button>
                        </form>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this mock and all its attempts?')">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="test_id" value="<?= $m['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($mocks)): ?>
                <tr><td colspan="8" style="text-align:center; color:var(--text-muted); padding:24px;">No scheduled mocks yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
