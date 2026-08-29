<?php
require_once __DIR__ . '/../config.php';
requireAdmin();
$pageTitle = 'Question Bank';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCsrf();
    if ($_POST['action'] === 'add') {
        $q = supabaseRest('questions', 'POST', [
            'test_id' => (int) $_POST['test_id'],
            'subject' => $_POST['subject'],
            'chapter' => $_POST['chapter'] ?? null,
            'question_text' => $_POST['question_text'],
            'explanation' => $_POST['explanation'] ?? null,
            'difficulty' => $_POST['difficulty'],
            'marks' => (int) $_POST['marks'],
            'negative_marks' => (float) $_POST['negative_marks'],
        ]);
        $qId = $q[0]['id'] ?? null;

        if ($qId) {
            for ($i = 1; $i <= 4; $i++) {
                if (!empty($_POST["option_$i"])) {
                    supabaseRest('options', 'POST', [
                        'question_id' => $qId,
                        'option_text' => $_POST["option_$i"],
                        'is_correct' => ((int)$_POST['correct_option']) === $i,
                        'position' => $i,
                    ]);
                }
            }
        }
    } elseif ($_POST['action'] === 'delete') {
        supabaseRest('options?question_id=eq.' . (int)$_POST['question_id'], 'DELETE');
        supabaseRest('questions?id=eq.' . (int)$_POST['question_id'], 'DELETE');
    }
    header('Location: ' . BASE_PATH . 'admin/questions.php' . (isset($_GET['test_id']) ? '?test_id=' . (int)$_GET['test_id'] : ''));
    exit;
}

// Filters
$filterTest = (int) ($_GET['test_id'] ?? 0);
$filter = 'select=*&order=created_at.desc&limit=50';
if ($filterTest) $filter .= '&test_id=eq.' . $filterTest;
$questions = supabaseRest('questions?' . $filter) ?? [];

$tests = supabaseRest('tests?select=id,title&order=title') ?? [];

include __DIR__ . '/includes/header.php';
?>

<!-- Filters -->
<div class="card" style="margin-bottom: 20px;">
    <form method="GET" style="display: flex; gap: 12px; align-items: end; flex-wrap: wrap;">
        <div class="form-group" style="margin-bottom: 0;">
            <label>Test</label>
            <select name="test_id" class="form-control" style="width: 250px;">
                <option value="0">All Tests</option>
                <?php foreach ($tests as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $filterTest == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
    </form>
</div>

<!-- Add Question -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header"><h3>Add Question</h3></div>
    <form method="POST">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
        <input type="hidden" name="action" value="add">
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 12px;">
            <div class="form-group"><label>Test</label><select name="test_id" class="form-control" required><?php foreach ($tests as $t): ?><option value="<?= $t['id'] ?>" <?= $filterTest == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['title']) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>Subject</label><input type="text" name="subject" class="form-control" required placeholder="Maths"></div>
            <div class="form-group"><label>Chapter</label><input type="text" name="chapter" class="form-control" placeholder="Algebra"></div>
            <div class="form-group"><label>Difficulty</label><select name="difficulty" class="form-control"><option value="easy">Easy</option><option value="medium" selected>Medium</option><option value="hard">Hard</option></select></div>
        </div>
        <div class="form-group"><label>Question Text</label><textarea name="question_text" class="form-control" rows="3" required></textarea></div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
            <div class="form-group"><label>Option A</label><input type="text" name="option_1" class="form-control" required></div>
            <div class="form-group"><label>Option B</label><input type="text" name="option_2" class="form-control" required></div>
            <div class="form-group"><label>Option C</label><input type="text" name="option_3" class="form-control" required></div>
            <div class="form-group"><label>Option D</label><input type="text" name="option_4" class="form-control" required></div>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 12px;">
            <div class="form-group"><label>Correct Option</label><select name="correct_option" class="form-control"><option value="1">A</option><option value="2">B</option><option value="3">C</option><option value="4">D</option></select></div>
            <div class="form-group"><label>Marks</label><input type="number" name="marks" class="form-control" value="1"></div>
            <div class="form-group"><label>Negative Marks</label><input type="number" step="0.25" name="negative_marks" class="form-control" value="0"></div>
        </div>
        <div class="form-group"><label>Explanation</label><textarea name="explanation" class="form-control" rows="2"></textarea></div>
        <button type="submit" class="btn btn-primary">Add Question</button>
    </form>
</div>

<!-- Questions List -->
<div class="card">
    <div class="card-header"><h3>Questions (<?= count($questions) ?>)</h3></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Question</th><th>Subject</th><th>Difficulty</th><th>Marks</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($questions as $q): ?>
                <tr>
                    <td style="font-family: var(--font-mono);"><?= $q['id'] ?></td>
                    <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars(substr($q['question_text'], 0, 80)) ?></td>
                    <td><span class="tag"><?= htmlspecialchars($q['subject'] ?? '') ?></span></td>
                    <td><span class="badge <?= ($q['difficulty'] ?? '') === 'hard' ? 'badge-red' : (($q['difficulty'] ?? '') === 'easy' ? 'badge-green' : 'badge-accent') ?>"><?= htmlspecialchars($q['difficulty'] ?? '') ?></span></td>
                    <td style="font-family: var(--font-mono);"><?= $q['marks'] ?? 1 ?></td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete?')">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
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
