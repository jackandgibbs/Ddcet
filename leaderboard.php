<?php
require_once __DIR__ . '/config.php';
$user = requireAuth();
$pageTitle = 'Leaderboard';

// ----------------------------------------------------------------------------
// Board catalogue. Only the selected board is computed, so each tab stays light.
// ----------------------------------------------------------------------------
$boards = [
    'league'   => ['label' => 'My League',         'icon' => 'trophy',      'value' => 'Weekly XP'],
    'weekly'   => ['label' => 'Weekly XP',          'icon' => 'calendar',    'value' => 'This week'],
    'global'   => ['label' => 'All-Time XP',        'icon' => 'globe',       'value' => 'XP'],
    'college'  => ['label' => 'My College',         'icon' => 'school',      'value' => 'XP'],
    'streak'   => ['label' => 'Streak',             'icon' => 'fire',        'value' => 'Days'],
    'accuracy' => ['label' => 'Accuracy',           'icon' => 'target',      'value' => 'Accuracy'],
    'speed'    => ['label' => 'Speed',              'icon' => 'bolt',        'value' => 'Sec / Q'],
    'improved' => ['label' => 'Most Improved',      'icon' => 'trending-up', 'value' => 'Jump'],
    'daily'    => ['label' => "Today's Challenge",  'icon' => 'flame',       'value' => 'Score'],
    'friends'  => ['label' => 'Friends',            'icon' => 'users',       'value' => 'XP'],
];
$scope = $_GET['scope'] ?? 'league';
if (!isset($boards[$scope])) $scope = 'league';

/** Fetch id => {name, avatar_url, level, college_id} for a set of student ids. */
function namesByIds(array $ids): array {
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids) return [];
    $rows = supabaseRest('students?id=in.(' . implode(',', $ids) . ')&select=id,name,avatar_url,level,college_id') ?? [];
    $map = [];
    foreach ($rows as $r) $map[$r['id']] = $r;
    return $map;
}

// Normalised, pre-ranked output: each entry => ['id','name','avatar_url','value','sub'].
$rows = [];
$note = '';            // optional explanatory line under the tabs
$leagueInfo = null;    // [tier, promoteIdx, relegateIdx] when scope === 'league'

switch ($scope) {

case 'global':
    $list = supabaseRest('students?is_banned=eq.false&order=xp.desc&limit=50&select=id,name,avatar_url,xp,level') ?? [];
    foreach ($list as $s) $rows[] = ['id' => $s['id'], 'name' => $s['name'], 'avatar_url' => $s['avatar_url'], 'value' => number_format((int)$s['xp']) . ' XP', 'sub' => $s['level']];
    break;

case 'college':
    if (!empty($user['college_id'])) {
        $list = supabaseRest('students?is_banned=eq.false&college_id=eq.' . (int)$user['college_id'] . '&order=xp.desc&limit=50&select=id,name,avatar_url,xp,level') ?? [];
        foreach ($list as $s) $rows[] = ['id' => $s['id'], 'name' => $s['name'], 'avatar_url' => $s['avatar_url'], 'value' => number_format((int)$s['xp']) . ' XP', 'sub' => $s['level']];
        $note = 'Ranked against everyone in your college.';
    } else {
        $note = 'Set your college in your profile to see your college board.';
    }
    break;

case 'streak':
    $list = supabaseRest('students?is_banned=eq.false&order=streak.desc&limit=50&select=id,name,avatar_url,streak') ?? [];
    foreach ($list as $s) $rows[] = ['id' => $s['id'], 'name' => $s['name'], 'avatar_url' => $s['avatar_url'], 'value' => (int)$s['streak'] . ' day' . ((int)$s['streak'] === 1 ? '' : 's'), 'sub' => ''];
    $note = 'Consistency board — longest active study streaks.';
    break;

case 'weekly':
    $since = weekStart();
    $xp = weeklyXp($since);
    arsort($xp);
    $top = array_slice($xp, 0, 50, true);
    $names = namesByIds(array_keys($top));
    foreach ($top as $sid => $amount) {
        $s = $names[$sid] ?? null; if (!$s) continue;
        $rows[] = ['id' => $sid, 'name' => $s['name'], 'avatar_url' => $s['avatar_url'], 'value' => number_format($amount) . ' XP', 'sub' => $s['level'] ?? ''];
    }
    $note = 'XP earned since Monday (' . date('d M', strtotime($since)) . '). Resets every week.';
    break;

case 'league':
    $myTier = $user['league_tier'] ?? 'Bronze';
    $members = supabaseRest('students?is_banned=eq.false&league_tier=eq.' . urlencode($myTier) . '&select=id,name,avatar_url,level&limit=200') ?? [];
    $ids = array_column($members, 'id');
    $xp = $ids ? weeklyXp(weekStart(), null, $ids) : [];
    foreach ($members as &$m) $m['_xp'] = $xp[$m['id']] ?? 0;
    unset($m);
    usort($members, fn($a, $b) => $b['_xp'] <=> $a['_xp']);
    foreach ($members as $s) $rows[] = ['id' => $s['id'], 'name' => $s['name'], 'avatar_url' => $s['avatar_url'], 'value' => number_format($s['_xp']) . ' XP', 'sub' => ''];
    $leagueInfo = [
        'tier'        => $myTier,
        'promoteIdx'  => leaguePromoteCount(),                 // rows [0..promoteIdx-1] promote
        'relegateIdx' => count($members) - leagueRelegateCount(), // rows >= relegateIdx relegate
    ];
    break;

case 'accuracy':
case 'speed':
    // One bounded pull of recent completed attempts, aggregated per student.
    $att = supabaseRest('attempts?status=eq.completed&select=student_id,correct_count,incorrect_count,time_spent_seconds,total_marks&order=completed_at.desc&limit=4000') ?? [];
    $agg = [];
    foreach ($att as $a) {
        $sid = (int)($a['student_id'] ?? 0); if (!$sid) continue;
        if (!isset($agg[$sid])) $agg[$sid] = ['correct' => 0, 'wrong' => 0, 'time' => 0, 'q' => 0, 'n' => 0];
        $agg[$sid]['correct'] += (int)($a['correct_count'] ?? 0);
        $agg[$sid]['wrong']   += (int)($a['incorrect_count'] ?? 0);
        $agg[$sid]['time']    += (int)($a['time_spent_seconds'] ?? 0);
        $agg[$sid]['q']       += (int)round(((int)($a['total_marks'] ?? 0)) / 2); // 2 marks per question
        $agg[$sid]['n']++;
    }
    $ranked = [];
    foreach ($agg as $sid => $g) {
        $answered = $g['correct'] + $g['wrong'];
        $acc = $answered > 0 ? $g['correct'] / $answered * 100 : 0;
        if ($scope === 'accuracy') {
            if ($answered < 20) continue;                 // need a meaningful sample
            $ranked[$sid] = ['sort' => $acc, 'value' => round($acc, 1) . '%', 'sub' => $g['n'] . ' tests'];
        } else { // speed: fastest sec/question, but only among reasonably accurate players
            if ($g['q'] < 30 || $acc < 40) continue;
            $spq = $g['time'] / max(1, $g['q']);
            $ranked[$sid] = ['sort' => -$spq, 'value' => round($spq, 1) . 's', 'sub' => round($acc) . '% acc'];
        }
    }
    uasort($ranked, fn($a, $b) => $b['sort'] <=> $a['sort']);
    $ranked = array_slice($ranked, 0, 50, true);
    $names = namesByIds(array_keys($ranked));
    foreach ($ranked as $sid => $r) {
        $s = $names[$sid] ?? null; if (!$s) continue;
        $rows[] = ['id' => $sid, 'name' => $s['name'], 'avatar_url' => $s['avatar_url'], 'value' => $r['value'], 'sub' => $r['sub']];
    }
    $note = $scope === 'accuracy'
        ? 'Highest correct rate (min 20 questions answered).'
        : 'Fastest per-question pace among accurate players (min 30 questions, 40%+ accuracy).';
    break;

case 'improved':
    // Biggest score-% jump between a student's two most recent mocks.
    $att = supabaseRest('attempts?status=eq.completed&total_marks=gt.0&select=student_id,score,total_marks,completed_at&order=completed_at.desc&limit=4000') ?? [];
    $byStudent = [];
    foreach ($att as $a) {
        $sid = (int)($a['student_id'] ?? 0); if (!$sid) continue;
        if (!isset($byStudent[$sid])) $byStudent[$sid] = [];
        if (count($byStudent[$sid]) < 2) {
            $byStudent[$sid][] = ((float)$a['score']) / max(1, (float)$a['total_marks']) * 100;
        }
    }
    $ranked = [];
    foreach ($byStudent as $sid => $pcts) {
        if (count($pcts) < 2) continue;          // need a previous mock to compare
        $delta = $pcts[0] - $pcts[1];            // latest minus previous
        if ($delta <= 0) continue;               // only show genuine improvers
        $ranked[$sid] = $delta;
    }
    arsort($ranked);
    $ranked = array_slice($ranked, 0, 50, true);
    $names = namesByIds(array_keys($ranked));
    foreach ($ranked as $sid => $delta) {
        $s = $names[$sid] ?? null; if (!$s) continue;
        $rows[] = ['id' => $sid, 'name' => $s['name'], 'avatar_url' => $s['avatar_url'], 'value' => '+' . round($delta) . '%', 'sub' => 'vs last mock'];
    }
    $note = 'Biggest jump between your two most recent mocks — unlimited practice pays off here.';
    break;

case 'daily':
    $today = date('Y-m-d');
    $att = supabaseRest('attempts?mode=eq.daily_challenge&status=eq.completed&started_at=gte.' . $today . '&order=score.desc&limit=50&select=student_id,score,correct_count') ?? [];
    $names = namesByIds(array_column($att, 'student_id'));
    foreach ($att as $a) {
        $s = $names[$a['student_id']] ?? null; if (!$s) continue;
        $rows[] = ['id' => $a['student_id'], 'name' => $s['name'], 'avatar_url' => $s['avatar_url'], 'value' => rtrim(rtrim((string)$a['score'], '0'), '.') . ' pts', 'sub' => (int)$a['correct_count'] . ' correct'];
    }
    $note = "Today's daily challenge ranking. Come back tomorrow to defend your spot.";
    break;

case 'friends':
    $friendRows = supabaseRest('friends?or=(student_id.eq.' . $user['id'] . ',friend_id.eq.' . $user['id'] . ')&status=eq.accepted&select=student_id,friend_id') ?? [];
    $friendIds = [$user['id']];
    foreach ($friendRows as $f) $friendIds[] = ($f['student_id'] == $user['id']) ? $f['friend_id'] : $f['student_id'];
    $friendIds = array_values(array_unique($friendIds));
    $list = supabaseRest('students?id=in.(' . implode(',', array_map('intval', $friendIds)) . ')&is_banned=eq.false&order=xp.desc&limit=50&select=id,name,avatar_url,xp,level') ?? [];
    foreach ($list as $s) $rows[] = ['id' => $s['id'], 'name' => $s['name'], 'avatar_url' => $s['avatar_url'], 'value' => number_format((int)$s['xp']) . ' XP', 'sub' => $s['level']];
    $note = count($friendIds) <= 1 ? 'Add friends to race them on this board.' : 'You and your friends, ranked by XP.';
    break;
}

// Find current user's own position for the "your rank" strip.
$myRank = null;
foreach ($rows as $i => $r) { if ($r['id'] == $user['id']) { $myRank = $i + 1; break; } }

include __DIR__ . '/includes/header.php';
?>

<!-- Board tabs -->
<div style="display:flex; gap:8px; margin-bottom:16px; overflow-x:auto; padding-bottom:4px;">
    <?php foreach ($boards as $key => $b): ?>
        <a href="leaderboard.php?scope=<?= $key ?>"
           class="btn <?= $scope === $key ? 'btn-primary' : 'btn-secondary' ?> btn-sm"
           style="white-space:nowrap; flex:0 0 auto;"><?= icon($b['icon'], 14) ?> <?= $b['label'] ?></a>
    <?php endforeach; ?>
</div>

<?php if ($scope === 'league' && $leagueInfo):
    $meta = leagueMeta($leagueInfo['tier']);
    $tiers = leagueTiers();
    $idx = array_search($leagueInfo['tier'], $tiers, true);
?>
<!-- League header -->
<div class="card" style="margin-bottom:16px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
    <div style="display:flex; align-items:center; gap:12px;">
        <span style="color:<?= $meta['color'] ?>;"><?= icon($meta['icon'], 32) ?></span>
        <div>
            <div style="font-size:18px; font-weight:800;"><?= htmlspecialchars($leagueInfo['tier']) ?> League</div>
            <div style="font-size:12px; color:var(--text-muted);">Top <?= leaguePromoteCount() ?> promote · bottom <?= leagueRelegateCount() ?> relegate · resets Monday</div>
        </div>
    </div>
    <div style="display:flex; align-items:center; gap:6px; font-size:11px;">
        <?php foreach ($tiers as $i => $t): $tm = leagueMeta($t); ?>
            <span title="<?= $t ?>" style="opacity:<?= $i === $idx ? '1' : '0.35' ?>; color:<?= $tm['color'] ?>; font-weight:<?= $i === $idx ? '700' : '400' ?>;"><?= icon($tm['icon'], 16) ?></span>
            <?php if ($i < count($tiers) - 1): ?><span style="color:var(--text-muted);">›</span><?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($note): ?>
<p style="font-size:12px; color:var(--text-muted); margin-bottom:14px;"><?= htmlspecialchars($note) ?></p>
<?php endif; ?>

<?php if ($myRank): ?>
<div class="card" style="margin-bottom:14px; padding:10px 16px; display:flex; align-items:center; gap:10px; border-color:var(--accent);">
    <span class="badge badge-accent">You</span>
    <span style="font-size:13px;">You're ranked <strong style="color:var(--accent); font-family:var(--font-mono);">#<?= $myRank ?></strong> on this board.</span>
</div>
<?php endif; ?>

<div class="card">
    <?php if (empty($rows)): ?>
        <p style="color:var(--text-muted); font-size:13px; padding:24px; text-align:center;">No one's on this board yet. Be the first — <a href="tests.php">take a test →</a></p>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Student</th><th style="text-align:right;"><?= $boards[$scope]['value'] ?></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $i => $r):
                $isMe = $r['id'] == $user['id'];
                // League promotion / relegation zone shading.
                $zoneStyle = '';
                if ($scope === 'league' && $leagueInfo) {
                    if ($i < $leagueInfo['promoteIdx'])       $zoneStyle = 'box-shadow: inset 3px 0 0 var(--green);';
                    elseif ($i >= $leagueInfo['relegateIdx']) $zoneStyle = 'box-shadow: inset 3px 0 0 var(--red);';
                }
            ?>
                <tr style="<?= $isMe ? 'background: rgba(240,165,0,0.05);' : '' ?> <?= $zoneStyle ?>">
                    <td style="font-family: var(--font-mono); font-weight:700; color:<?= $i < 3 ? 'var(--accent)' : 'var(--text-muted)' ?>;">
                        <?= $i === 0 ? icon('gold', 18) : ($i === 1 ? icon('silver', 18) : ($i === 2 ? icon('bronze', 18) : $i + 1)) ?>
                    </td>
                    <td style="display:flex; align-items:center; gap:10px;">
                        <?php if (!empty($r['avatar_url'])): ?><img src="<?= htmlspecialchars($r['avatar_url']) ?>" style="width:28px;height:28px;border-radius:50%;"><?php endif; ?>
                        <span <?= $isMe ? 'style="color: var(--accent); font-weight:600;"' : '' ?>><?= htmlspecialchars($r['name']) ?></span>
                        <?php if ($isMe): ?><span class="badge badge-accent">You</span><?php endif; ?>
                        <?php if (!empty($r['sub'])): ?><span class="tag" style="font-size:10px;"><?= htmlspecialchars($r['sub']) ?></span><?php endif; ?>
                    </td>
                    <td style="text-align:right; font-family: var(--font-mono); font-weight:600; color: var(--accent);"><?= htmlspecialchars($r['value']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
