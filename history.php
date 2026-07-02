<?php
require_once __DIR__ . '/config.php';
$user = requireAuth();
$pageTitle = 'Test History';

$attempts = supabaseRest('attempts?student_id=eq.' . $user['id'] . '&status=eq.completed&order=completed_at.desc&select=*') ?? [];

// Get test titles for attempts that have test_id
$testIds = array_filter(array_unique(array_column($attempts, 'test_id')));
$testMap = [];
if ($testIds) {
    $tests = supabaseRest('tests?id=in.(' . implode(',', $testIds) . ')&select=id,title,mode');
    if ($tests) foreach ($tests as $t) $testMap[$t['id']] = $t;
}

include __DIR__ . '/includes/header.php';
?>

<p style="color: var(--text-muted); font-size: 13px; margin-bottom: 16px;"><?= count($attempts) ?> tests completed</p>

<?php if (empty($attempts)): ?>
    <div class="card" style="text-align: center; padding: 60px; color: var(--text-muted);">
        <svg width="48" height="48" fill="none" stroke="var(--text-muted)" stroke-width="1.5" viewBox="0 0 24 24" style="margin-bottom: 12px;"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2" stroke-linecap="round" stroke-linejoin="round"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg>
        <h3 style="margin-bottom: 4px;">No tests taken yet</h3>
        <p style="font-size: 13px;">Start a test from <a href="tests.php">Test Modes</a> to see your history here.</p>
    </div>
<?php else: ?>
<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Test</th><th>Score</th><th>Correct</th><th>Time</th><th>Date</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($attempts as $a):
                $test = $testMap[$a['test_id'] ?? 0] ?? null;
                // Fixed tests keep their title; pool-mode attempts (no test_id)
                // show their actual mode name instead of a generic label.
                $title = $test['title'] ?? modeLabel($a['mode'] ?? null);
                $pct = $a['total_marks'] > 0 ? round(($a['score'] / $a['total_marks']) * 100) : 0;
                $timeMin = round(($a['time_spent_seconds'] ?? 0) / 60);
            ?>
                <tr>
                    <td data-label="Test">
                        <div style="font-weight: 600; font-size: 13px;"><?= htmlspecialchars($title) ?></div>
                    </td>
                    <td data-label="Score">
                        <span class="badge <?= $pct >= 60 ? 'badge-green' : ($pct >= 40 ? 'badge-accent' : 'badge-red') ?>">
                            <?= $a['score'] ?>/<?= $a['total_marks'] ?> (<?= $pct ?>%)
                        </span>
                    </td>
                    <td data-label="Correct/Wrong/Skip" style="font-family: var(--font-mono); font-size: 12px;">
                        <span style="color: var(--green);"><?= $a['correct_count'] ?? 0 ?></span> /
                        <span style="color: var(--red);"><?= $a['incorrect_count'] ?? 0 ?></span> /
                        <span style="color: var(--text-muted);"><?= $a['skipped_count'] ?? 0 ?></span>
                    </td>
                    <td data-label="Time" style="font-family: var(--font-mono); font-size: 12px;"><?= $timeMin ?> min</td>
                    <td data-label="Date" style="font-size: 12px; color: var(--text-muted);"><?= date('d M Y', strtotime($a['completed_at'])) ?></td>
                    <td data-label=""><a href="result.php?attempt_id=<?= $a['id'] ?>" class="btn btn-secondary btn-sm">View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
