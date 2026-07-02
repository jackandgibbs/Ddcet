<?php
require_once __DIR__ . '/../config.php';
$instUser = requireInstitution();
$org = currentOrg();
$pageTitle = 'Batch Analytics';
if (!$org) redirect('institution/index.php');
$oid = (int) $org['id'];

// Batch roster (for name lookups + headcount).
$students = supabaseRest('students?org_id=eq.' . $oid . '&select=id,name,avatar_url') ?? [];
$studentMap = [];
foreach ($students as $s) $studentMap[$s['id']] = $s;

// This org's assignments.
$tests = supabaseRest('tests?org_id=eq.' . $oid . '&select=id,title') ?? [];
$testMap = [];
foreach ($tests as $t) $testMap[$t['id']] = $t['title'];
$testIds = array_column($tests, 'id');

$attempts = [];
if ($testIds) {
    $attempts = supabaseRest('attempts?status=eq.completed&test_id=in.(' . implode(',', $testIds)
        . ')&select=id,student_id,test_id,score,total_marks,completed_at&order=completed_at.desc') ?? [];
}

$totalAttempts = count($attempts);
$avgScore = 0;
$perTest = [];   // test_id => [sum%, count]
$perStudent = []; // student_id => [sumScore, count]
if ($totalAttempts) {
    $pctSum = 0;
    foreach ($attempts as $a) {
        $pct = ($a['total_marks'] > 0) ? ($a['score'] * 100 / $a['total_marks']) : 0;
        $pctSum += $pct;
        $tid = $a['test_id'];
        if (!isset($perTest[$tid])) $perTest[$tid] = ['sum' => 0, 'n' => 0];
        $perTest[$tid]['sum'] += $pct; $perTest[$tid]['n']++;
        $sid = $a['student_id'];
        if (!isset($perStudent[$sid])) $perStudent[$sid] = ['sum' => 0, 'n' => 0];
        $perStudent[$sid]['sum'] += $pct; $perStudent[$sid]['n']++;
    }
    $avgScore = round($pctSum / $totalAttempts);
}

// Top performers by average % (min 1 attempt).
$leaders = [];
foreach ($perStudent as $sid => $d) {
    $leaders[] = ['sid' => $sid, 'avg' => $d['n'] ? $d['sum'] / $d['n'] : 0, 'n' => $d['n']];
}
usort($leaders, fn($a, $b) => $b['avg'] <=> $a['avg']);
$leaders = array_slice($leaders, 0, 10);

include __DIR__ . '/includes/header.php';
?>

<div class="stats-grid">
    <div class="stat-card blue"><div class="stat-value"><?= count($students) ?></div><div class="stat-label">Batch Students</div></div>
    <div class="stat-card green"><div class="stat-value"><?= $totalAttempts ?></div><div class="stat-label">Attempts Completed</div></div>
    <div class="stat-card accent"><div class="stat-value"><?= $avgScore ?>%</div><div class="stat-label">Avg Score</div></div>
    <div class="stat-card"><div class="stat-value"><?= count($tests) ?></div><div class="stat-label">Assignments</div></div>
</div>

<div class="card" style="margin-top: 20px;">
    <div class="card-header"><h3>Per-Assignment Performance</h3></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Assignment</th><th>Attempts</th><th>Avg Score</th></tr></thead>
            <tbody>
            <?php foreach ($tests as $t): $d = $perTest[$t['id']] ?? null; ?>
                <tr>
                    <td><?= htmlspecialchars($t['title']) ?></td>
                    <td style="font-family: var(--font-mono);"><?= $d['n'] ?? 0 ?></td>
                    <td style="font-family: var(--font-mono);"><?= $d ? round($d['sum'] / $d['n']) : 0 ?>%</td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$tests): ?><tr><td colspan="3" style="color: var(--text-muted);">No assignments yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card" style="margin-top: 20px;">
    <div class="card-header"><h3>Top Performers</h3></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Student</th><th>Avg Score</th><th>Attempts</th></tr></thead>
            <tbody>
            <?php foreach ($leaders as $i => $l): $s = $studentMap[$l['sid']] ?? null; if (!$s) continue; ?>
                <tr>
                    <td style="font-family: var(--font-mono);"><?= $i + 1 ?></td>
                    <td style="display: flex; align-items: center; gap: 8px;">
                        <?php if (!empty($s['avatar_url'])): ?><img src="<?= htmlspecialchars($s['avatar_url']) ?>" style="width:24px;height:24px;border-radius:50%;"><?php endif; ?>
                        <?= htmlspecialchars($s['name']) ?>
                    </td>
                    <td style="font-family: var(--font-mono); color: var(--accent);"><?= round($l['avg']) ?>%</td>
                    <td style="font-family: var(--font-mono);"><?= $l['n'] ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$leaders): ?><tr><td colspan="4" style="color: var(--text-muted);">No attempts yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
