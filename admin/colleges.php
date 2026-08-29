<?php
require_once __DIR__ . '/../config.php';
requireAdmin();
$pageTitle = 'Colleges';

$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'add' && !empty($_POST['name'])) {
        supabaseRest('colleges', 'POST', [
            'name' => trim($_POST['name']),
            'city' => trim($_POST['city'] ?? ''),
        ]);
    } elseif ($action === 'delete') {
        supabaseRest('colleges?id=eq.' . (int)$_POST['college_id'], 'DELETE');
    } elseif ($action === 'discount' && !empty($_POST['college_id'])) {
        // Set / clear the college's discount coupon. The code is the secret a
        // student must present to claim the discount (see resolveCollegeCoupon).
        $cid = (int) $_POST['college_id'];
        $code = strtoupper(trim($_POST['discount_code'] ?? ''));
        $pct = max(0, min(100, (int) ($_POST['discount_percent'] ?? 0)));
        $cap = ($_POST['discount_max_redemptions'] === '' ? null : max(0, (int) $_POST['discount_max_redemptions']));
        // One code per college (the unique index enforces it too).
        $clash = $code !== '' ? supabaseRest('colleges?discount_code=eq.' . urlencode($code) . '&id=neq.' . $cid . '&select=id&limit=1') : [];
        if (!empty($clash)) {
            $flash = 'That coupon code is already used by another college.';
        } else {
            supabaseRest('colleges?id=eq.' . $cid, 'PATCH', [
                'discount_code' => ($code === '' ? null : $code),
                'discount_percent' => $pct,
                'discount_max_redemptions' => $cap,
            ]);
            $flash = 'Discount saved.';
        }
    }
    header('Location: ' . BASE_PATH . 'admin/colleges.php?m=' . urlencode($flash));
    exit;
}

if (!empty($_GET['m'])) $flash = $_GET['m'];

$colleges = supabaseRest('colleges?select=*&order=name') ?? [];

// Get student counts per college. Fetch every student's college_id in bulk and
// tally in PHP — one request per 1000 students instead of one per college
// (the old per-college loop timed out once the college list grew large).
$counts = [];
$offset = 0;
do {
    $batch = supabaseRest('students?select=college_id&limit=1000&offset=' . $offset) ?? [];
    foreach ($batch as $s) {
        if (!empty($s['college_id'])) {
            $counts[$s['college_id']] = ($counts[$s['college_id']] ?? 0) + 1;
        }
    }
    $offset += 1000;
} while (count($batch) === 1000);

foreach ($colleges as &$c) {
    $c['student_count'] = $counts[$c['id']] ?? 0;
}
unset($c);

include __DIR__ . '/includes/header.php';
?>

<?php if ($flash): ?>
<div class="card" style="margin-bottom: 16px; padding: 12px 16px; font-size: 13px; color: var(--text-secondary);"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom: 20px;">
    <div class="card-header"><h3>Add College</h3></div>
    <form method="POST" style="display: flex; gap: 12px; align-items: end; flex-wrap: wrap;">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
        <input type="hidden" name="action" value="add">
        <div class="form-group" style="margin-bottom: 0;"><label>Name</label><input type="text" name="name" class="form-control" required></div>
        <div class="form-group" style="margin-bottom: 0;"><label>City</label><input type="text" name="city" class="form-control" required></div>
        <button type="submit" class="btn btn-primary btn-sm">Add</button>
    </form>
</div>

<div class="card">
    <div class="card-header"><h3>All Colleges (<?= count($colleges) ?>)</h3></div>
    <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 12px;">
        Give a college a <strong>coupon code</strong> + discount % to run a college-scoped discount.
        Share the code only with that college's students — entering it on the Subscription page unlocks
        the discount (a self-selected college does not). Leave the code blank to disable. Cap blank = unlimited.
    </p>

    <?php // One discount form per college, declared outside the table; inputs link via form="". ?>
    <?php foreach ($colleges as $c): ?>
        <form method="POST" id="disc-<?= (int) $c['id'] ?>">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
            <input type="hidden" name="action" value="discount">
            <input type="hidden" name="college_id" value="<?= (int) $c['id'] ?>">
        </form>
    <?php endforeach; ?>

    <div class="table-wrap">
        <table>
            <thead><tr><th>College</th><th>City</th><th>Students</th><th>Coupon Code</th><th>%</th><th>Cap</th><th>Used</th><th></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($colleges as $c): $fid = 'disc-' . (int) $c['id']; ?>
                <tr>
                    <td><?= htmlspecialchars($c['name']) ?></td>
                    <td style="color: var(--text-muted);"><?= htmlspecialchars($c['city'] ?? '') ?></td>
                    <td style="font-family: var(--font-mono);"><?= $c['student_count'] ?></td>
                    <td><input form="<?= $fid ?>" type="text" name="discount_code" maxlength="24" class="form-control" value="<?= htmlspecialchars($c['discount_code'] ?? '') ?>" placeholder="—" style="width: 150px; text-transform: uppercase; font-family: var(--font-mono);"></td>
                    <td><input form="<?= $fid ?>" type="number" name="discount_percent" min="0" max="100" class="form-control" value="<?= (int) ($c['discount_percent'] ?? 0) ?>" style="width: 70px;"></td>
                    <td><input form="<?= $fid ?>" type="number" name="discount_max_redemptions" min="0" class="form-control" value="<?= ($c['discount_max_redemptions'] ?? '') === '' || $c['discount_max_redemptions'] === null ? '' : (int) $c['discount_max_redemptions'] ?>" placeholder="∞" style="width: 80px;"></td>
                    <td style="font-family: var(--font-mono);"><?= (int) ($c['discount_redemptions'] ?? 0) ?></td>
                    <td><button form="<?= $fid ?>" type="submit" class="btn btn-sm btn-primary">Save</button></td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this college?')">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="college_id" value="<?= $c['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
