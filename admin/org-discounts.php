<?php
require_once __DIR__ . '/../config.php';
requireAdmin();
$pageTitle = 'College Discounts';

$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_org' && !empty($_POST['name'])) {
        // Create an organization (with a unique join code) for a college so its
        // students can verify membership via join-org.php and earn the discount.
        $name = trim($_POST['name']);
        $pct = max(0, min(100, (int) ($_POST['discount_percent'] ?? 0)));
        $cap = ($_POST['discount_max_redemptions'] === '' ? null : max(0, (int) $_POST['discount_max_redemptions']));
        $created = null;
        for ($try = 0; $try < 5 && !$created; $try++) {
            $code = generateJoinCode();
            $exists = supabaseRest('organizations?join_code=eq.' . $code . '&select=id&limit=1');
            if (!empty($exists)) continue;
            $created = supabaseRest('organizations', 'POST', [
                'name' => $name,
                'join_code' => $code,
                'discount_percent' => $pct,
                'discount_max_redemptions' => $cap,
            ]);
        }
        if ($created) { $msg = 'Created "' . htmlspecialchars($name) . '" with join code ' . htmlspecialchars($created[0]['join_code'] ?? '') . '.'; $msgType = 'green'; }
        else { $msg = 'Could not generate a unique join code, please retry.'; $msgType = 'red'; }

    } elseif ($action === 'update' && !empty($_POST['org_id'])) {
        $pct = max(0, min(100, (int) ($_POST['discount_percent'] ?? 0)));
        $cap = ($_POST['discount_max_redemptions'] === '' ? null : max(0, (int) $_POST['discount_max_redemptions']));
        supabaseRest('organizations?id=eq.' . (int) $_POST['org_id'], 'PATCH', [
            'discount_percent' => $pct,
            'discount_max_redemptions' => $cap,
        ]);
        $msg = 'Discount updated.'; $msgType = 'green';
    }
    // PRG redirect to avoid resubmits; flash via query.
    header('Location: ' . BASE_PATH . 'admin/org-discounts.php?m=' . urlencode($msg) . '&t=' . $msgType);
    exit;
}

if (!empty($_GET['m'])) { $msg = $_GET['m']; $msgType = $_GET['t'] ?? 'green'; }

$orgs = supabaseRest('organizations?select=*&order=name') ?? [];

// Member count per org (org_id is the verified membership signal).
foreach ($orgs as &$o) {
    $members = supabaseRest('students?org_id=eq.' . (int) $o['id'] . '&select=id');
    $o['member_count'] = is_array($members) ? count($members) : 0;
}
unset($o);

include __DIR__ . '/includes/header.php';
?>

<?php if ($msg): ?>
<div class="card" style="border-color: var(--<?= htmlspecialchars($msgType) ?>); margin-bottom: 16px; padding: 12px 16px; font-size: 13px; color: var(--<?= htmlspecialchars($msgType) ?>);"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom: 20px;">
    <p style="font-size: 13px; color: var(--text-secondary); margin: 0;">
        Discounts apply to <strong>Basic & Pro</strong> plans and are granted only to students who joined a
        college's batch with its <strong>join code</strong> — a self-selected college on the profile does not qualify.
        The discount is applied automatically at checkout. Leave the cap blank for unlimited redemptions.
    </p>
</div>

<div class="card" style="margin-bottom: 20px;">
    <div class="card-header"><h3>Create College Organization</h3></div>
    <form method="POST" style="display: flex; gap: 12px; align-items: end; flex-wrap: wrap;">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
        <input type="hidden" name="action" value="create_org">
        <div class="form-group" style="margin-bottom: 0;"><label>College / Org Name</label><input type="text" name="name" class="form-control" required></div>
        <div class="form-group" style="margin-bottom: 0;"><label>Discount %</label><input type="number" name="discount_percent" class="form-control" min="0" max="100" value="0" style="width: 100px;"></div>
        <div class="form-group" style="margin-bottom: 0;"><label>Max redemptions</label><input type="number" name="discount_max_redemptions" class="form-control" min="0" placeholder="unlimited" style="width: 130px;"></div>
        <button type="submit" class="btn btn-primary btn-sm">Create + Generate Code</button>
    </form>
</div>

<div class="card">
    <div class="card-header"><h3>Organizations (<?= count($orgs) ?>)</h3></div>

    <?php // One form per org, declared outside the table; inputs link via form="". ?>
    <?php foreach ($orgs as $o): ?>
        <form method="POST" id="org-<?= (int) $o['id'] ?>">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="org_id" value="<?= (int) $o['id'] ?>">
        </form>
    <?php endforeach; ?>

    <div class="table-wrap">
        <table>
            <thead><tr><th>Organization</th><th>Join Code</th><th>Members</th><th>Discount %</th><th>Cap</th><th>Used</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($orgs as $o): $fid = 'org-' . (int) $o['id']; ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($o['name']) ?>
                        <?php if (empty($o['is_active'])): ?><span class="badge badge-red" style="margin-left:6px;">inactive</span><?php endif; ?>
                    </td>
                    <td style="font-family: var(--font-mono); letter-spacing: 1px;"><?= htmlspecialchars($o['join_code']) ?></td>
                    <td style="font-family: var(--font-mono);"><?= (int) $o['member_count'] ?></td>
                    <td><input form="<?= $fid ?>" type="number" name="discount_percent" class="form-control" min="0" max="100" value="<?= (int) ($o['discount_percent'] ?? 0) ?>" style="width: 80px;"></td>
                    <td><input form="<?= $fid ?>" type="number" name="discount_max_redemptions" class="form-control" min="0" placeholder="∞" value="<?= ($o['discount_max_redemptions'] === null ? '' : (int) $o['discount_max_redemptions']) ?>" style="width: 90px;"></td>
                    <td style="font-family: var(--font-mono);"><?= (int) ($o['discount_redemptions'] ?? 0) ?></td>
                    <td><button form="<?= $fid ?>" type="submit" class="btn btn-sm btn-primary">Save</button></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($orgs)): ?>
                <tr><td colspan="7" style="color: var(--text-muted);">No organizations yet. Create one above.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
