<?php
require_once __DIR__ . '/../config.php';
requireAdmin();
$pageTitle = 'Analytics';

$students = supabaseRest('students?select=id,created_at') ?? [];
$attempts = supabaseRest('attempts?status=eq.completed&select=id,score,total_marks,completed_at') ?? [];
$payments = supabaseRest('payments?status=eq.captured&select=amount,created_at') ?? [];

$totalStudents = count($students);
$totalAttempts = count($attempts);
$totalRevenue = array_sum(array_column($payments, 'amount')) / 100;
$avgScore = 0;
if ($totalAttempts > 0) {
    $pctSum = 0;
    foreach ($attempts as $a) $pctSum += ($a['total_marks'] > 0) ? ($a['score'] * 100 / $a['total_marks']) : 0;
    $avgScore = round($pctSum / $totalAttempts);
}

include __DIR__ . '/includes/header.php';
?>

<div class="stats-grid">
    <div class="stat-card blue"><div class="stat-value"><?= $totalStudents ?></div><div class="stat-label">Total Students</div></div>
    <div class="stat-card green"><div class="stat-value"><?= $totalAttempts ?></div><div class="stat-label">Tests Completed</div></div>
    <div class="stat-card accent"><div class="stat-value"><?= $avgScore ?>%</div><div class="stat-label">Avg Score</div></div>
    <div class="stat-card"><div class="stat-value">₹<?= number_format($totalRevenue) ?></div><div class="stat-label">Revenue</div></div>
</div>

<div class="card" style="margin-top: 20px;">
    <div class="card-header"><h3>Recent Signups</h3></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Date</th></tr></thead>
            <tbody>
            <?php $recent = array_slice($students, 0, 10); foreach ($recent as $i => $s): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= date('d M Y, H:i', strtotime($s['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
