<?php
require_once __DIR__ . '/../config.php';
requireAdmin();
$pageTitle = 'Test Management';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        supabaseRest('tests', 'POST', [
            'title' => $_POST['title'],
            'description' => $_POST['description'] ?? '',
            'mode' => $_POST['mode'],
            'subject' => $_POST['subject'] ?: null,
            'duration_minutes' => (int) $_POST['duration_minutes'],
            'total_marks' => (int) $_POST['total_marks'],
            'total_questions' => (int) $_POST['total_questions'],
            'difficulty' => $_POST['difficulty'],
            'year' => $_POST['year'] ?: null,
            'is_free' => isset($_POST['is_free']),
            'min_plan' => $_POST['min_plan'],
            'is_published' => false,
        ]);
    } elseif ($action === 'toggle') {
        $tid = (int) $_POST['test_id'];
        $test = supabaseRest('tests?id=eq.' . $tid . '&select=is_published&limit=1');
        $current = !empty($test[0]['is_published']);
        supabaseRest('tests?id=eq.' . $tid, 'PATCH', ['is_published' => !$current]);
    } elseif ($action === 'delete') {
        supabaseRest('tests?id=eq.' . (int)$_POST['test_id'], 'DELETE');
    }
    header('Location: ' . BASE_PATH . 'admin/tests.php');
    exit;
}

$tests = supabaseRest('tests?select=*&order=created_at.desc') ?? [];

include __DIR__ . '/includes/header.php';
?>

<div class="card" style="margin-bottom: 20px;">
    <div class="card-header"><h3>Create Test</h3></div>
    <form method="POST">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
        <input type="hidden" name="action" value="create">
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
            <div class="form-group"><label>Title</label><input type="text" name="title" class="form-control" required></div>
            <div class="form-group">
                <label>Mode</label>
                <select name="mode" class="form-control">
                    <option value="full_mock">Full Mock</option>
                    <option value="rapid_fire">Rapid Fire</option>
                    <option value="subject_wise">Subject-wise</option>
                    <option value="previous_year">Previous Year</option>
                    <option value="daily_challenge">Daily Challenge</option>
                    <option value="weekly_challenge">Weekly Challenge</option>
                </select>
            </div>
            <div class="form-group"><label>Subject (optional)</label><input type="text" name="subject" class="form-control"></div>
        </div>
        <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px;">
            <div class="form-group"><label>Duration (min)</label><input type="number" name="duration_minutes" class="form-control" value="120"></div>
            <div class="form-group"><label>Total Marks</label><input type="number" name="total_marks" class="form-control" value="200"></div>
            <div class="form-group"><label>Questions</label><input type="number" name="total_questions" class="form-control" value="100"></div>
            <div class="form-group"><label>Difficulty</label><select name="difficulty" class="form-control"><option value="easy">Easy</option><option value="medium" selected>Medium</option><option value="hard">Hard</option></select></div>
            <div class="form-group"><label>Min Plan</label><select name="min_plan" class="form-control"><option value="free">Free</option><option value="basic" selected>Basic</option><option value="pro">Pro</option></select></div>
        </div>
        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 12px;">
            <div class="form-group"><label>Description</label><input type="text" name="description" class="form-control"></div>
            <div class="form-group"><label>Year (optional)</label><input type="number" name="year" class="form-control" placeholder="2024"></div>
            <div class="form-group" style="display: flex; align-items: end;"><label><input type="checkbox" name="is_free"> Free for all</label></div>
        </div>
        <button type="submit" class="btn btn-primary">Create Test</button>
    </form>
</div>

<div class="card">
    <div class="card-header"><h3>All Tests (<?= count($tests) ?>)</h3></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Title</th><th>Mode</th><th>Duration</th><th>Marks</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($tests as $t): ?>
                <tr>
                    <td><?= htmlspecialchars($t['title']) ?></td>
                    <td><span class="tag"><?= htmlspecialchars($t['mode'] ?? '') ?></span></td>
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
                        <a href="admin/questions.php?test_id=<?= $t['id'] ?>" class="btn btn-sm btn-secondary">Questions</a>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete?')">
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
