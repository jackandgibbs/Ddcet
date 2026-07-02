<?php
require_once __DIR__ . '/config.php';
$user = requireAuth();
$pageTitle = 'Join an Institution';

$msg = ''; $msgType = '';
$prefill = strtoupper(trim($_GET['code'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    if (!empty($user['is_institution'])) {
        // Institution owners run their own batch — they shouldn't join another.
        $msg = 'Institution accounts manage their own batch and cannot join another.'; $msgType = 'red';
    } else {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $org = $code ? supabaseRest('organizations?join_code=eq.' . urlencode($code) . '&is_active=eq.true&select=id,name&limit=1') : [];
        if (!empty($org)) {
            supabaseRest('students?id=eq.' . (int) $user['id'], 'PATCH', ['org_id' => $org[0]['id']]);
            $_SESSION['user']['org_id'] = $org[0]['id'];
            $msg = 'You have joined ' . htmlspecialchars($org[0]['name']) . '. Your institution\'s assignments will appear in your tests.'; $msgType = 'green';
        } else {
            $msg = 'No institution found for that code. Check it with your faculty and try again.'; $msgType = 'red';
        }
    }
}

// Current membership, if any.
$currentOrg = currentOrg();

include __DIR__ . '/includes/header.php';
?>

<?php if ($msg): ?>
<div class="card" style="border-color: var(--<?= $msgType ?>); margin-bottom: 16px; padding: 12px 16px; font-size: 13px; color: var(--<?= $msgType ?>);"><?= $msg ?></div>
<?php endif; ?>

<?php if ($currentOrg): ?>
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header"><h3>Your Institution</h3></div>
    <p style="font-size: 14px;">You're a member of <strong><?= htmlspecialchars($currentOrg['name']) ?></strong>. Their assignments show up under <a href="tests.php">Test Modes</a> when published.</p>
</div>
<?php endif; ?>

<div class="card" style="max-width: 480px;">
    <div class="card-header"><h3><?= $currentOrg ? 'Switch Institution' : 'Join an Institution' ?></h3></div>
    <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 16px;">Enter the join code your coaching institute or college gave you.</p>
    <form method="POST">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
        <div class="form-group">
            <label>Join Code</label>
            <input type="text" name="code" class="form-control" required maxlength="12" value="<?= htmlspecialchars($prefill) ?>" placeholder="e.g. ABC123" style="font-family: var(--font-mono); letter-spacing: 2px; text-transform: uppercase;">
        </div>
        <button type="submit" class="btn btn-primary">Join Batch</button>
    </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
