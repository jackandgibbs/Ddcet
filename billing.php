<?php
require_once __DIR__ . '/config.php';
$user = requireAuth();
$pageTitle = 'Billing & Payments';

// Get user's payments and subscriptions
$results = supabaseMulti([
    'payments?student_id=eq.' . $user['id'] . '&order=created_at.desc&select=*',
    'subscriptions?student_id=eq.' . $user['id'] . '&order=created_at.desc&select=*',
]);
$payments = $results[0] ?? [];
$subscriptions = $results[1] ?? [];

// Merge into transaction list
$transactions = [];
foreach ($subscriptions as $i => $sub) {
    $transactions[] = [
        'sr' => $i + 1,
        'invoice' => 'DDCET/' . date('Y', strtotime($sub['created_at'])) . '/' . str_pad($sub['id'], 4, '0', STR_PAD_LEFT),
        'start' => $sub['created_at'],
        'end' => $sub['expires_at'],
        'package' => ucfirst($sub['plan']),
        'amount' => 0,
        'status' => $sub['status'],
    ];
}
foreach ($payments as $p) {
    // Find matching transaction and update amount
    foreach ($transactions as &$t) {
        if ($t['amount'] == 0 && strtolower($t['package']) === $p['plan']) {
            $t['amount'] = $p['amount'];
            break;
        }
    }
    unset($t);
}
// If no subscriptions, show payments directly
if (empty($transactions)) {
    foreach ($payments as $i => $p) {
        $transactions[] = [
            'sr' => $i + 1,
            'invoice' => 'DDCET/' . date('Y', strtotime($p['created_at'])) . '/' . str_pad($p['id'], 4, '0', STR_PAD_LEFT),
            'start' => $p['created_at'],
            'end' => '-',
            'package' => ucfirst($p['plan']),
            'amount' => $p['amount'],
            'status' => $p['status'],
        ];
    }
}

include __DIR__ . '/includes/header.php';
?>

<p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 20px;">View and manage your transaction history.</p>

<div class="card">
    <div class="card-header"><h3>Transaction History</h3></div>
    <?php if (empty($transactions)): ?>
        <p style="color: var(--text-muted); padding: 20px 0;">No transactions yet.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>SR NO.</th>
                    <th>INVOICE NO.</th>
                    <th>START DATE</th>
                    <th>END DATE</th>
                    <th>PACKAGE</th>
                    <th>AMOUNT (₹)</th>
                    <th>STATUS</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($transactions as $t): ?>
                <tr>
                    <td data-label="Sr No."style="font-family: var(--font-mono);"><?= $t['sr'] ?></td>
                    <td data-label="Invoice" style="font-family: var(--font-mono); font-size: 12px;"><?= $t['invoice'] ?></td>
                    <td data-label="Start Date" style="font-size: 12px;"><?= $t['start'] !== '-' ? date('d M Y', strtotime($t['start'])) : '-' ?></td>
                    <td data-label="End Date" style="font-size: 12px;"><?= $t['end'] !== '-' ? date('d M Y', strtotime($t['end'])) : '-' ?></td>
                    <td data-label="Package"><span class="badge badge-accent"><?= $t['package'] ?></span></td>
                    <td data-label="Amount" style="font-family: var(--font-mono); font-weight: 600;">₹<?= number_format(($t['amount'] ?? 0) / 100) ?></td>
                    <td data-label="Status">
                        <?php
                        $statusClass = match($t['status']) {
                            'active', 'captured' => 'badge-green',
                            'pending' => 'badge-accent',
                            default => 'badge-red',
                        };
                        $statusText = match($t['status']) {
                            'active', 'captured' => 'PAID',
                            'pending' => 'PENDING',
                            'expired' => 'EXPIRED',
                            default => strtoupper($t['status']),
                        };
                        ?>
                        <span class="badge <?= $statusClass ?>"><?= $statusText ?></span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p style="font-size: 12px; color: var(--text-muted); margin-top: 12px;">Showing <?= count($transactions) ?> entries</p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
