<?php
require_once __DIR__ . '/../config.php';
$instUser = requireInstitution();
$org = currentOrg();
$pageTitle = 'Question Bank';
if (!$org) redirect('institution/index.php');
$oid = (int) $org['id'];

// The org's own tests — questions must hang off one of these (test_id set), so
// they can never appear in the public practice pool (which is test_id IS NULL).
$tests = supabaseRest('tests?org_id=eq.' . $oid . '&select=id,title&order=title') ?? [];
$ownTestIds = array_column($tests, 'id');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCsrf();
    if ($_POST['action'] === 'add') {
        $testId = (int) $_POST['test_id'];
        // Guard: the chosen test must belong to THIS org.
        if (!in_array($testId, $ownTestIds, true)) {
            http_response_code(403); exit('Invalid test for this institution.');
        }
        $q = supabaseRest('questions', 'POST', [
            'test_id'        => $testId,
            'org_id'         => $oid,
            'subject'        => $_POST['subject'],
            'chapter'        => $_POST['chapter'] ?? null,
            'question_text'  => $_POST['question_text'],
            'explanation'    => $_POST['explanation'] ?? null,
            'difficulty'     => $_POST['difficulty'],
            'marks'          => (int) $_POST['marks'],
            'negative_marks' => (float) $_POST['negative_marks'],
        ]);
        $qId = $q[0]['id'] ?? null;
        if ($qId) {
            for ($i = 1; $i <= 4; $i++) {
                if (!empty($_POST["option_$i"])) {
                    supabaseRest('options', 'POST', [
                        'question_id' => $qId,
                        'option_text' => $_POST["option_$i"],
                        'is_correct'  => ((int) $_POST['correct_option']) === $i,
                        'position'    => $i,
                    ]);
                }
            }
        }
    } elseif ($_POST['action'] === 'delete') {
        $qid = (int) $_POST['question_id'];
        // Guard: only delete a question that belongs to this org.
        $owned = supabaseRest('questions?id=eq.' . $qid . '&org_id=eq.' . $oid . '&select=id&limit=1');
        if (!empty($owned)) {
            supabaseRest('options?question_id=eq.' . $qid, 'DELETE');
            supabaseRest('questions?id=eq.' . $qid, 'DELETE');
        }
    }
    header('Location: ' . BASE_PATH . 'institution/questions.php' . (isset($_GET['test_id']) ? '?test_id=' . (int) $_GET['test_id'] : ''));
    exit;
}

// List — always scoped to this org.
$filterTest = (int) ($_GET['test_id'] ?? 0);
$filter = 'org_id=eq.' . $oid . '&select=*&order=created_at.desc&limit=100';
if ($filterTest && in_array($filterTest, $ownTestIds, true)) $filter .= '&test_id=eq.' . $filterTest;
$questions = supabaseRest('questions?' . $filter) ?? [];

include __DIR__ . '/includes/header.php';
?>

<?php if (!$tests): ?>
<div class="card" style="margin-bottom: 20px; border-color: var(--accent);">
    <p style="font-size: 13px; color: var(--text-secondary);">Create an <a href="institution/tests.php">assignment</a> first — every question belongs to one of your assignments.</p>
</div>
<?php else: ?>

<!-- Filter -->
<div class="card" style="margin-bottom: 20px;">
    <form method="GET" style="display: flex; gap: 12px; align-items: end;">
        <div class="form-group" style="margin-bottom: 0;">
            <label>Assignment</label>
            <select name="test_id" class="form-control" style="width: 250px;">
                <option value="0">All Assignments</option>
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
            <div class="form-group"><label>Assignment</label><select name="test_id" class="form-control" required><?php foreach ($tests as $t): ?><option value="<?= $t['id'] ?>" <?= $filterTest == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['title']) ?></option><?php endforeach; ?></select></div>
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
<?php endif; ?>

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
