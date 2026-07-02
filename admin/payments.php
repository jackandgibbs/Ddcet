<?php
require_once __DIR__ . '/../config.php';
requireAdmin();
$pageTitle = 'Payments';

$payments = supabaseRest('payments?select=*&order=created_at.desc&limit=100') ?? [];

// Get student names
$studentIds = array_unique(array_column($payments, 'student_id'));
$studentMap = [];
if ($studentIds) {
    $students = supabaseRest('students?id=in.(' . implode(',', $studentIds) . ')&select=id,name,email');
    if ($students) foreach ($students as $s) $studentMap[$s['id']] = $s;
}

include __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div class="card-header"><h3>All Payments (<?= count($payments) ?>)</h3></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Student</th><th>Plan</th><th>Amount</th><th>Status</th><th>Order ID</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($payments as $p): $stu = $studentMap[$p['student_id']] ?? []; ?>
                <tr>
                    <td><?= htmlspecialchars($stu['name'] ?? 'Unknown') ?><br><span style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($stu['email'] ?? '') ?></span></td>
                    <td><span class="badge badge-accent"><?= ucfirst($p['plan'] ?? '') ?></span></td>
                    <td style="font-family: var(--font-mono); font-weight: 700;">₹<?= number_format(($p['amount'] ?? 0) / 100) ?></td>
                    <td><span class="badge <?= $p['status'] === 'captured' ? 'badge-green' : ($p['status'] === 'pending' ? 'badge-accent' : 'badge-red') ?>"><?= ucfirst($p['status']) ?></span></td>
                    <td style="font-family: var(--font-mono); font-size: 11px;"><?= htmlspecialchars($p['razorpay_order_id'] ?? '-') ?></td>
                    <td style="font-size: 12px; color: var(--text-muted);"><?= date('d M Y, H:i', strtotime($p['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
