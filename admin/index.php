<?php
require_once __DIR__ . '/../config.php';
requireAdmin();
$pageTitle = 'Dashboard';

// Stats via REST API
$students = supabaseRest('students?select=id');
$totalStudents = is_array($students) ? count($students) : 0;

$now = urlencode(date('c'));
$activeSubs = supabaseRest('subscriptions?status=eq.active&expires_at=gt.' . $now . '&select=id');
$activeSubscribers = is_array($activeSubs) ? count($activeSubs) : 0;

$payments = supabaseRest('payments?status=eq.captured&select=amount');
$totalRevenue = 0;
if ($payments) foreach ($payments as $p) $totalRevenue += (int)$p['amount'];

$today = date('Y-m-d');
$attemptsToday = supabaseRest('attempts?started_at=gte.' . $today . '&select=id');
$testsToday = is_array($attemptsToday) ? count($attemptsToday) : 0;

$weekAgo = date('Y-m-d', strtotime('-7 days'));
$attemptsWeek = supabaseRest('attempts?started_at=gte.' . $weekAgo . '&select=id');
$testsWeek = is_array($attemptsWeek) ? count($attemptsWeek) : 0;

$monthAgo = date('Y-m-d', strtotime('-30 days'));
$attemptsMonth = supabaseRest('attempts?started_at=gte.' . $monthAgo . '&select=id');
$testsMonth = is_array($attemptsMonth) ? count($attemptsMonth) : 0;

$monthlyRevenue = [];
$recentActivity = [];

include __DIR__ . '/includes/header.php';
?>

<div class="stats-grid">
    <div class="stat-card accent">
        <div class="stat-value">₹<?= number_format($totalRevenue / 100) ?></div>
        <div class="stat-label">Total Revenue</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-value"><?= number_format($totalStudents) ?></div>
        <div class="stat-label">Total Students</div>
    </div>
    <div class="stat-card green">
        <div class="stat-value"><?= $activeSubscribers ?></div>
        <div class="stat-label">Active Subscribers</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $testsToday ?></div>
        <div class="stat-label">Tests Today</div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
    <div class="card">
        <div class="card-header"><h3>Test Attempts</h3></div>
        <div class="table-wrap"><table>
            <tr><td style="color: var(--text-secondary);">Today</td><td style="font-family: var(--font-mono); font-weight: 700;"><?= $testsToday ?></td></tr>
            <tr><td style="color: var(--text-secondary);">This Week</td><td style="font-family: var(--font-mono);"><?= $testsWeek ?></td></tr>
            <tr><td style="color: var(--text-secondary);">This Month</td><td style="font-family: var(--font-mono);"><?= $testsMonth ?></td></tr>
            <tr><td style="color: var(--text-secondary);">Free vs Paid</td><td style="font-family: var(--font-mono);"><?= $totalStudents - $activeSubscribers ?> / <?= $activeSubscribers ?></td></tr>
        </table></div>
    </div>
    <div class="card">
        <div class="card-header"><h3>Quick Links</h3></div>
        <div style="display: flex; flex-direction: column; gap: 8px;">
            <a href="tests.php" class="btn btn-primary btn-sm">Manage Tests</a>
            <a href="mocks.php" class="btn btn-primary btn-sm">Schedule Mocks</a>
            <a href="questions.php" class="btn btn-secondary btn-sm">Manage Questions</a>
            <a href="students.php" class="btn btn-secondary btn-sm">View Students</a>
            <a href="colleges.php" class="btn btn-secondary btn-sm">Manage Colleges</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
