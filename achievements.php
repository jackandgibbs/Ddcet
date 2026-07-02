<?php
require_once __DIR__ . '/config.php';
$user = requireAuth();
$pageTitle = 'Achievements';

// Level thresholds
$levels = [
    'Beginner' => 0,
    'Bronze' => 200,
    'Silver' => 500,
    'Gold' => 1500,
    'Diamond' => 5000,
];

// Update user level based on current XP
$currentXp = (int) $user['xp'];
$currentLevel = 'Beginner';
foreach ($levels as $name => $threshold) {
    if ($currentXp >= $threshold) $currentLevel = $name;
}
if ($currentLevel !== $user['level']) {
    supabaseRest('students?id=eq.' . $user['id'], 'PATCH', ['level' => $currentLevel]);
    $user['level'] = $currentLevel;
    $_SESSION['user']['level'] = $currentLevel;
}

// Find next level
$nextLevel = null;
$xpToNext = 0;
$found = false;
foreach ($levels as $name => $threshold) {
    if ($found) { $nextLevel = $name; $xpToNext = $threshold - $currentXp; break; }
    if ($name === $currentLevel) $found = true;
}

// Check and award badges
function checkBadges(int $studentId): void {
    $studentData = supabaseRest('students?id=eq.' . $studentId . '&select=*&limit=1');
    $student = $studentData[0] ?? null;
    if (!$student) return;

    $attemptsData = supabaseRest('attempts?student_id=eq.' . $studentId . '&status=eq.completed&select=id,score,total_marks');
    $testCount = is_array($attemptsData) ? count($attemptsData) : 0;

    $friendsData = supabaseRest('friends?or=(student_id.eq.' . $studentId . ',friend_id.eq.' . $studentId . ')&status=eq.accepted&select=id');
    $friendsCount = is_array($friendsData) ? count($friendsData) : 0;

    $hasPerfect = false;
    if ($attemptsData) {
        foreach ($attemptsData as $a) {
            if ($a['score'] == $a['total_marks'] && $a['total_marks'] > 0) { $hasPerfect = true; break; }
        }
    }

    $badges = supabaseRest('badges?select=*') ?? [];

    foreach ($badges as $badge) {
        $earned = supabaseRest('student_badges?student_id=eq.' . $studentId . '&badge_id=eq.' . $badge['id'] . '&select=id&limit=1');
        if (!empty($earned)) continue;

        $award = false;
        $criteria = $badge['criteria'] ?? '';

        if (str_contains($criteria, 'attempts_count >= 1') && $testCount >= 1) $award = true;
        if (str_contains($criteria, 'attempts_count >= 50') && $testCount >= 50) $award = true;
        if (str_contains($criteria, 'streak >= 7') && ($student['streak'] ?? 0) >= 7) $award = true;
        if (str_contains($criteria, 'streak >= 30') && ($student['streak'] ?? 0) >= 30) $award = true;
        if (str_contains($criteria, 'friends_count >= 5') && $friendsCount >= 5) $award = true;
        if (str_contains($criteria, 'score_percent = 100') && $hasPerfect) $award = true;
        if (str_contains($criteria, 'level = Diamond') && ($student['level'] ?? '') === 'Diamond') $award = true;

        if ($award) {
            supabaseRest('student_badges', 'POST', [
                'student_id' => $studentId,
                'badge_id' => $badge['id'],
            ]);
            if ($badge['xp_reward'] > 0) {
                $curStudent = supabaseRest('students?id=eq.' . $studentId . '&select=xp&limit=1');
                $curXp = (int)($curStudent[0]['xp'] ?? 0);
                supabaseRest('students?id=eq.' . $studentId, 'PATCH', ['xp' => $curXp + $badge['xp_reward']]);
                supabaseRest('xp_log', 'POST', [
                    'student_id' => $studentId,
                    'amount' => $badge['xp_reward'],
                    'reason' => 'Badge: ' . $badge['name'],
                    'source_type' => 'badge',
                    'source_id' => $badge['id'],
                ]);
            }
            supabaseRest('notifications', 'POST', [
                'student_id' => $studentId,
                'title' => 'Badge Earned!',
                'body' => $badge['name'] . ' — ' . $badge['description'],
                'type' => 'badge',
            ]);
        }
    }
}

// Run badge check
checkBadges($user['id']);

// Refresh user XP
$refreshedUser = supabaseRest('students?id=eq.' . $_SESSION['user']['id'] . '&select=*&limit=1');
if (!empty($refreshedUser[0])) {
    $user = $refreshedUser[0];
    $currentXp = (int) $user['xp'];
    $_SESSION['user']['xp'] = $currentXp;
}

// All badges
$allBadges = supabaseRest('badges?select=*&order=xp_reward') ?? [];
$earnedData = supabaseRest('student_badges?student_id=eq.' . $user['id'] . '&select=badge_id') ?? [];
$earnedBadgeIds = array_column($earnedData, 'badge_id');

// XP history
$xpLogs = supabaseRest('xp_log?student_id=eq.' . $user['id'] . '&order=created_at.desc&limit=20&select=*') ?? [];

include __DIR__ . '/includes/header.php';
?>

<!-- Level Progress -->
<div class="card" style="margin-bottom: 20px;">
    <div style="display: flex; align-items: center; gap: 20px;">
        <div style="font-size: 40px;">
            <?= match($currentLevel) { 'Diamond' => icon('diamond', 40), 'Gold' => icon('gold', 40), 'Silver' => icon('silver', 40), 'Bronze' => icon('bronze', 40), default => icon('seedling', 40) } ?>
        </div>
        <div style="flex: 1;">
            <h3 style="font-size: 18px; margin-bottom: 4px;"><?= $currentLevel ?> Level</h3>
            <p style="font-size: 13px; color: var(--text-secondary);"><?= number_format($currentXp) ?> XP total</p>
            <?php if ($nextLevel): ?>
            <div style="margin-top: 8px;">
                <div style="display: flex; justify-content: space-between; font-size: 11px; color: var(--text-muted); margin-bottom: 4px;">
                    <span><?= $currentLevel ?></span>
                    <span><?= $nextLevel ?> (<?= $xpToNext ?> XP to go)</span>
                </div>
                <div style="background: var(--bg-tertiary); border-radius: 4px; height: 8px; overflow: hidden;">
                    <?php
                    $prevThreshold = $levels[$currentLevel];
                    $nextThreshold = $levels[$nextLevel];
                    $progress = (($currentXp - $prevThreshold) / ($nextThreshold - $prevThreshold)) * 100;
                    ?>
                    <div style="background: var(--accent); height: 100%; width: <?= min(100, $progress) ?>%; border-radius: 4px;"></div>
                </div>
            </div>
            <?php else: ?>
                <p style="color: var(--accent); font-size: 12px; margin-top: 4px;">Max level reached! <?= icon('star', 14) ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Level Map -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header"><h3>Level Map</h3></div>
    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
        <?php foreach ($levels as $name => $threshold): ?>
        <div style="background: <?= $currentXp >= $threshold ? 'rgba(240,165,0,0.1)' : 'var(--bg-primary)' ?>; border: 1px solid <?= $currentXp >= $threshold ? 'var(--accent)' : 'var(--border)' ?>; border-radius: 8px; padding: 12px 16px; text-align: center; min-width: 100px;">
            <div style="font-size: 20px;"><?= match($name) { 'Diamond' => icon('diamond', 20), 'Gold' => icon('gold', 20), 'Silver' => icon('silver', 20), 'Bronze' => icon('bronze', 20), default => icon('seedling', 20) } ?></div>
            <div style="font-size: 12px; font-weight: 600; margin-top: 4px; color: <?= $currentXp >= $threshold ? 'var(--accent)' : 'var(--text-muted)' ?>;"><?= $name ?></div>
            <div style="font-size: 10px; color: var(--text-muted);"><?= number_format($threshold) ?> XP</div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Badges -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header"><h3>Badges (<?= count($earnedBadgeIds) ?>/<?= count($allBadges) ?>)</h3></div>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px;">
        <?php foreach ($allBadges as $b):
            $earned = in_array($b['id'], $earnedBadgeIds);
        ?>
        <div style="background: var(--bg-primary); border-radius: 8px; padding: 14px; text-align: center; <?= !$earned ? 'opacity: 0.4;' : '' ?>">
            <div style="font-size: 28px;"><?= icon(match($b['name']) { 'First Test' => 'target', '7-Day Streak' => 'fire', '30-Day Streak' => 'fire', 'Top 10 College' => 'trophy', 'Perfect Score' => 'star', 'Speed Demon' => 'bolt', 'Social Butterfly' => 'users', 'Bookworm' => 'book', 'Challenger' => 'swords', 'Diamond Level' => 'diamond', default => 'award' }, 28) ?></div>
            <div style="font-size: 13px; font-weight: 600; margin-top: 6px;"><?= htmlspecialchars($b['name']) ?></div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;"><?= htmlspecialchars($b['description']) ?></div>
            <div style="font-size: 11px; color: var(--accent); margin-top: 4px;">+<?= $b['xp_reward'] ?> XP</div>
            <?php if ($earned): ?><span class="badge badge-green" style="margin-top: 6px;">Earned <?= icon('check', 12) ?></span><?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- XP History -->
<div class="card">
    <div class="card-header"><h3>XP Activity</h3></div>
    <?php if (empty($xpLogs)): ?>
        <p style="color: var(--text-muted);">No XP earned yet. Start taking tests!</p>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>XP</th><th>Reason</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($xpLogs as $log): ?>
            <tr>
                <td style="font-family: var(--font-mono); color: var(--green); font-weight: 600;">+<?= $log['amount'] ?></td>
                <td style="font-size: 13px;"><?= htmlspecialchars($log['reason']) ?></td>
                <td style="font-size: 12px; color: var(--text-muted);"><?= date('d M, H:i', strtotime($log['created_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
