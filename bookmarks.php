<?php
require_once __DIR__ . '/config.php';
$user = requireAuth();
$db = getDB();
$pageTitle = 'Bookmarked Questions';

// Remove bookmark
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove') {
    supabaseRest('bookmarked_questions?student_id=eq.' . $user['id'] . '&question_id=eq.' . (int)$_POST['question_id'], 'DELETE');
}

$bookmarksData = supabaseRest('bookmarked_questions?student_id=eq.' . $user['id'] . '&select=created_at,question_id,questions(*)&order=created_at.desc') ?? [];

// Get all correct answers in one call
$bqIds = array_column($bookmarksData, 'question_id');
$correctAnswers = [];
if ($bqIds) {
    $opts = supabaseRest('options?question_id=in.(' . implode(',', $bqIds) . ')&is_correct=eq.true&select=question_id,option_text') ?? [];
    foreach ($opts as $o) $correctAnswers[$o['question_id']] = $o['option_text'];
}

$saved = [];
foreach ($bookmarksData as $bq) {
    $q = $bq['questions'] ?? [];
    $q['saved_at'] = $bq['created_at'];
    $q['correct_answer'] = $correctAnswers[$bq['question_id']] ?? '';
    $saved[] = $q;
}

include __DIR__ . '/includes/header.php';
?>

<?php if (empty($saved)): ?>
    <div class="card" style="text-align: center; padding: 40px; color: var(--text-muted);">
        No bookmarked questions yet. Save questions during tests to revisit later!
    </div>
<?php else: ?>
<p style="color: var(--text-muted); font-size: 12px; margin-bottom: 16px;"><?= count($saved) ?> saved questions</p>
<?php foreach ($saved as $q): ?>
<div class="card" style="margin-bottom: 12px;">
    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
        <span class="tag"><?= htmlspecialchars($q['subject']) ?></span>
        <span class="badge <?= $q['difficulty'] === 'hard' ? 'badge-red' : ($q['difficulty'] === 'easy' ? 'badge-green' : 'badge-accent') ?>"><?= $q['difficulty'] ?></span>
        <span style="margin-left: auto; font-size: 11px; color: var(--text-muted);">Saved <?= date('d M', strtotime($q['saved_at'])) ?></span>
    </div>
    <p class="mathy" style="font-size: 14px; line-height: 1.6; margin-bottom: 8px;"><?= htmlspecialchars($q['question_text']) ?></p>
    <div style="display: flex; align-items: center; gap: 12px;">
        <span class="mathy" style="font-size: 12px; color: var(--green);"><?= icon('check', 12) ?> <?= htmlspecialchars($q['correct_answer'] ?? '') ?></span>
        <?php if ($q['explanation']): ?><span style="font-size: 12px; color: var(--text-muted);"><?= icon('bulb', 12) ?> <?= htmlspecialchars(substr($q['explanation'], 0, 100)) ?></span><?php endif; ?>
        <form method="POST" style="margin-left: auto;">
            <input type="hidden" name="action" value="remove">
            <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
            <button type="submit" class="btn btn-sm btn-secondary">Remove</button>
        </form>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
