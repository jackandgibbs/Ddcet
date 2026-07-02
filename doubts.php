<?php
require_once __DIR__ . '/config.php';
$user = requireAuth();
$db = getDB();
$pageTitle = 'Doubt Box';

// Submit doubt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ask') {
    supabaseRest('doubts', 'POST', [
        'student_id' => $user['id'],
        'title' => $_POST['title'],
        'body' => $_POST['body'] ?? '',
    ]);
    $success = 'Doubt submitted! Admin will answer soon.';
}

// Load doubts with student names in one call
$doubtsData = supabaseRest('doubts?select=*,students(name)&order=created_at.desc&limit=30') ?? [];

// Get all answers in one call
$doubtIds = array_column($doubtsData, 'id');
$allAnswers = [];
if ($doubtIds) {
    $answers = supabaseRest('doubt_answers?doubt_id=in.(' . implode(',', $doubtIds) . ')&select=doubt_id,body&limit=100') ?? [];
    foreach ($answers as $a) {
        if (!isset($allAnswers[$a['doubt_id']])) $allAnswers[$a['doubt_id']] = $a['body'];
    }
}

$doubts = [];
foreach ($doubtsData as $d) {
    $d['student_name'] = $d['students']['name'] ?? 'Anonymous';
    unset($d['students']);
    $d['answer'] = $allAnswers[$d['id']] ?? null;
    $doubts[] = $d;
}

include __DIR__ . '/includes/header.php';
?>

<?php if (!empty($success)): ?><div class="card" style="border-color: var(--green); margin-bottom: 12px; padding: 12px; font-size: 13px; color: var(--green);"><?= $success ?></div><?php endif; ?>

<!-- Ask a Doubt -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header"><h3>Ask a Doubt</h3></div>
    <form method="POST">
        <input type="hidden" name="action" value="ask">
        <div class="form-group"><label>Title</label><input type="text" name="title" class="form-control" required placeholder="e.g. How to solve integration by parts?"></div>
        <div class="form-group"><label>Details (optional)</label><textarea name="body" class="form-control" rows="3" placeholder="Explain your doubt in detail..."></textarea></div>
        <button type="submit" class="btn btn-primary">Submit Doubt</button>
    </form>
</div>

<!-- Doubts Feed -->
<div class="card">
    <div class="card-header"><h3>All Doubts</h3></div>
    <?php foreach ($doubts as $d): ?>
    <div style="border-bottom: 1px solid var(--border); padding: 14px 0;">
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
            <span class="badge <?= $d['is_resolved'] ? 'badge-green' : 'badge-accent' ?>"><?= $d['is_resolved'] ? icon('check', 12) . ' Resolved' : 'Open' ?></span>
            <strong style="font-size: 14px;"><?= htmlspecialchars($d['title']) ?></strong>
            <span style="margin-left: auto; font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($d['student_name']) ?> • <?= date('d M', strtotime($d['created_at'])) ?></span>
        </div>
        <?php if ($d['body']): ?><p style="color: var(--text-secondary); font-size: 13px;"><?= htmlspecialchars($d['body']) ?></p><?php endif; ?>
        <?php if ($d['answer']): ?>
            <div style="background: var(--bg-primary); border-radius: 6px; padding: 10px; margin-top: 8px; font-size: 13px;">
                <strong style="color: var(--green);">Answer:</strong> <?= htmlspecialchars($d['answer']) ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if (empty($doubts)): ?><p style="color: var(--text-muted);">No doubts posted yet.</p><?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
