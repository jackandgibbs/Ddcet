<?php
require_once __DIR__ . '/../config.php';
requireAdmin();
$pageTitle = 'Leagues';

$tiers   = leagueTiers();
$thisWeek = weekStart();
$prevWeek = date('Y-m-d', strtotime('-7 days', strtotime($thisWeek)));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    if (($_POST['action'] ?? '') === 'rollover') {
        // Promote/relegate based on LAST week's XP window [prevWeek, thisWeek).
        $students = supabaseRest('students?is_banned=eq.false&select=id,league_tier,league_week&limit=10000') ?? [];
        $prevXp   = weeklyXp($prevWeek, $thisWeek);

        // Only process students not already rolled for this week (idempotent).
        $pending = array_filter($students, fn($s) => ($s['league_week'] ?? null) !== $thisWeek);

        // Group pending students by their current tier.
        $byTier = [];
        foreach ($pending as $s) {
            $tier = $s['league_tier'] ?: 'Bronze';
            $byTier[$tier][] = $s;
        }

        $promoteN = leaguePromoteCount();
        $relegateN = leagueRelegateCount();
        $updates = []; // newTier => [ids]
        $promoted = 0; $relegated = 0;

        foreach ($byTier as $tier => $members) {
            $tIdx = array_search($tier, $tiers, true);
            if ($tIdx === false) $tIdx = 0;

            usort($members, fn($a, $b) => ($prevXp[$b['id']] ?? 0) <=> ($prevXp[$a['id']] ?? 0));
            $count = count($members);
            $relegateStart = max($promoteN, $count - $relegateN); // promoted are never relegated

            foreach ($members as $i => $m) {
                $newTier = $tier;
                $xp = $prevXp[$m['id']] ?? 0;
                if ($i < $promoteN && $xp > 0 && $tIdx < count($tiers) - 1) {
                    $newTier = $tiers[$tIdx + 1]; $promoted++;
                } elseif ($i >= $relegateStart && $tIdx > 0) {
                    $newTier = $tiers[$tIdx - 1]; $relegated++;
                }
                $updates[$newTier][] = (int)$m['id'];
            }
        }

        // One PATCH per destination tier (sets league_week so re-runs are no-ops).
        foreach ($updates as $newTier => $ids) {
            if (!$ids) continue;
            supabaseRest('students?id=in.(' . implode(',', $ids) . ')', 'PATCH', [
                'league_tier' => $newTier, 'league_week' => $thisWeek,
            ]);
        }

        $processed = count($pending);
        header('Location: ' . BASE_PATH . 'admin/leagues.php?done=1&p=' . $processed . '&up=' . $promoted . '&down=' . $relegated);
        exit;
    }
}

// Tier distribution + how many already rolled this week.
$all = supabaseRest('students?is_banned=eq.false&select=id,league_tier,league_week&limit=10000') ?? [];
$dist = array_fill_keys($tiers, 0);
$rolledThisWeek = 0;
foreach ($all as $s) {
    $t = $s['league_tier'] ?: 'Bronze';
    if (isset($dist[$t])) $dist[$t]++;
    if (($s['league_week'] ?? null) === $thisWeek) $rolledThisWeek++;
}
$totalStudents = count($all);

include __DIR__ . '/includes/header.php';
?>

<?php if (isset($_GET['done'])): ?>
<div class="card" style="margin-bottom:16px; padding:12px 16px; font-size:13px; border-color:var(--green); color:var(--green);">
    Rollover complete — processed <strong><?= (int)$_GET['p'] ?></strong> students:
    <strong><?= (int)$_GET['up'] ?></strong> promoted, <strong><?= (int)$_GET['down'] ?></strong> relegated.
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:20px;">
    <div class="card-header"><h3>Weekly Rollover</h3></div>
    <p style="font-size:13px; color:var(--text-secondary); line-height:1.7; margin-bottom:14px;">
        Ranks each tier by XP earned during <strong>last week</strong>
        (<?= date('d M', strtotime($prevWeek)) ?> – <?= date('d M', strtotime($thisWeek)) ?>),
        then promotes the top <?= leaguePromoteCount() ?> and relegates the bottom <?= leagueRelegateCount() ?> of every tier.
        Run this once at the start of each week — it's safe to click again, already-processed students are skipped.
    </p>
    <div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
        <form method="POST" onsubmit="return confirm('Run the weekly league rollover now?')">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
            <input type="hidden" name="action" value="rollover">
            <button type="submit" class="btn btn-primary">Run Rollover for week of <?= date('d M Y', strtotime($thisWeek)) ?></button>
        </form>
        <span style="font-size:12px; color:var(--text-muted);">
            <?= $rolledThisWeek ?> / <?= $totalStudents ?> students already rolled this week
        </span>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Tier Distribution</h3></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Tier</th><th>Students</th></tr></thead>
            <tbody>
            <?php foreach ($tiers as $t): $m = leagueMeta($t); ?>
                <tr>
                    <td style="display:flex; align-items:center; gap:8px;"><span style="color:<?= $m['color'] ?>;"><?= icon($m['icon'], 18) ?></span> <?= $t ?></td>
                    <td style="font-family:var(--font-mono);"><?= $dist[$t] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
