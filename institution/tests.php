<?php
require_once __DIR__ . '/../config.php';
$instUser = requireInstitution();
$org = currentOrg();
$pageTitle = 'Assignments';
if (!$org) redirect('institution/index.php');
$oid = (int) $org['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        // org_id stamps it private to this batch; is_free + min_plan=free means
        // batch students can attempt it without a platform subscription.
        supabaseRest('tests', 'POST', [
            'org_id'           => $oid,
            'title'            => $_POST['title'],
            'description'      => $_POST['description'] ?? '',
            'mode'             => 'full_mock',
            'duration_minutes' => (int) $_POST['duration_minutes'],
            'total_marks'      => (int) $_POST['total_marks'],
            'total_questions'  => (int) $_POST['total_questions'],
            'difficulty'       => $_POST['difficulty'],
            'is_free'          => true,
            'min_plan'         => 'free',
            'is_published'     => false,
        ]);
    } elseif ($action === 'toggle') {
        $tid = (int) $_POST['test_id'];
        // Only act on a test owned by this org.
        $test = supabaseRest('tests?id=eq.' . $tid . '&org_id=eq.' . $oid . '&select=is_published&limit=1');
        if (!empty($test)) {
            $current = !empty($test[0]['is_published']);
            supabaseRest('tests?id=eq.' . $tid . '&org_id=eq.' . $oid, 'PATCH', ['is_published' => !$current]);
        }
    } elseif ($action === 'delete') {
        $tid = (int) $_POST['test_id'];
        supabaseRest('tests?id=eq.' . $tid . '&org_id=eq.' . $oid, 'DELETE');
    }
    header('Location: ' . BASE_PATH . 'institution/tests.php');
    exit;
}

$tests = supabaseRest('tests?org_id=eq.' . $oid . '&select=*&order=created_at.desc') ?? [];

include __DIR__ . '/includes/header.php';
?>

<div class="card" style="margin-bottom: 20px;">
    <div class="card-header"><h3>Create Assignment</h3></div>
    <form method="POST">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
        <input type="hidden" name="action" value="create">
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 12px;">
            <div class="form-group"><label>Title</label><input type="text" name="title" class="form-control" required placeholder="Unit Test 1 — Algebra"></div>
            <div class="form-group"><label>Difficulty</label><select name="difficulty" class="form-control"><option value="easy">Easy</option><option value="medium" selected>Medium</option><option value="hard">Hard</option></select></div>
        </div>
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px;">
            <div class="form-group"><label>Duration (min)</label><input type="number" name="duration_minutes" class="form-control" value="60"></div>
            <div class="form-group"><label>Total Marks</label><input type="number" name="total_marks" class="form-control" value="50"></div>
            <div class="form-group"><label>Questions</label><input type="number" name="total_questions" class="form-control" value="25"></div>
        </div>
        <div class="form-group"><label>Description</label><input type="text" name="description" class="form-control"></div>
        <button type="submit" class="btn btn-primary">Create Assignment</button>
    </form>
    <p style="font-size: 12px; color: var(--text-muted); margin-top: 10px;">After creating, add questions to it under <a href="institution/questions.php">Question Bank</a>, then Publish to release it to your batch.</p>
</div>

<div class="card">
    <div class="card-header"><h3>Your Assignments (<?= count($tests) ?>)</h3></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Title</th><th>Duration</th><th>Marks</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($tests as $t): ?>
                <tr>
                    <td><?= htmlspecialchars($t['title']) ?></td>
                    <td style="font-family: var(--font-mono);"><?= $t['duration_minutes'] ?> min</td>
                    <td style="font-family: var(--font-mono);"><?= $t['total_marks'] ?></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="test_id" value="<?= $t['id'] ?>">
                            <button type="submit" class="badge <?= $t['is_published'] ? 'badge-green' : 'badge-red' ?>" style="border:none;cursor:pointer;"><?= $t['is_published'] ? 'Published' : 'Draft' ?></button>
                        </form>
                    </td>
                    <td>
                        <a href="institution/questions.php?test_id=<?= $t['id'] ?>" class="btn btn-sm btn-secondary">Questions</a>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this assignment and its questions?')">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="test_id" value="<?= $t['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
