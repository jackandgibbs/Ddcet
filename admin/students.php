<?php
require_once __DIR__ . '/../config.php';
requireAdmin();
$pageTitle = 'Students';

$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';
    $sid = (int) ($_POST['student_id'] ?? 0);
    if ($action === 'ban') {
        supabaseRest('students?id=eq.' . $sid, 'PATCH', ['is_banned' => true]);
    } elseif ($action === 'restore') {
        supabaseRest('students?id=eq.' . $sid, 'PATCH', ['is_banned' => false]);
    } elseif ($action === 'make_institution') {
        // Grant institution-portal access. The account sets up its organization
        // itself on first visit to institution/index.php.
        supabaseRest('students?id=eq.' . $sid, 'PATCH', ['is_institution' => true]);
    } elseif ($action === 'revoke_institution') {
        supabaseRest('students?id=eq.' . $sid, 'PATCH', ['is_institution' => false]);
    } elseif ($action === 'activate_sub') {
        $email = trim($_POST['email'] ?? '');
        $plan = $_POST['plan'] ?? 'pro';
        $days = (int) ($_POST['days'] ?? 30);
        // Find student by email
        $student = supabaseRest('students?email=eq.' . urlencode($email) . '&select=id&limit=1');
        if (!empty($student[0])) {
            $expiresAt = date('c', strtotime("+$days days"));
            supabaseRest('subscriptions', 'POST', [
                'student_id' => $student[0]['id'],
                'plan' => $plan,
                'status' => 'active',
                'expires_at' => $expiresAt,
            ]);
            supabaseRest('notifications', 'POST', [
                'student_id' => $student[0]['id'],
                'title' => 'Subscription Activated!',
                'body' => ucfirst($plan) . ' plan active for ' . $days . ' days.',
                'type' => 'subscription',
            ]);
            $success = 'Subscription activated for ' . $email . ' (' . $plan . ', ' . $days . ' days)';
        } else {
            $success = 'Error: Student with email "' . htmlspecialchars($email) . '" not found';
        }
    }
    if ($action !== 'activate_sub') {
        header('Location: ' . BASE_PATH . 'admin/students.php');
        exit;
    }
}

$students = supabaseRest('students?select=*&order=created_at.desc&limit=100') ?? [];
$colleges = supabaseRest('colleges?select=id,name&order=name') ?? [];

// Map college names
$collegeMap = [];
foreach ($colleges as $c) $collegeMap[$c['id']] = $c['name'];

include __DIR__ . '/includes/header.php';
?>

<?php if ($success): ?>
    <div class="card" style="border-color: <?= str_starts_with($success, 'Error') ? 'var(--red)' : 'var(--green)' ?>; margin-bottom: 16px; padding: 12px 16px; font-size: 13px; color: <?= str_starts_with($success, 'Error') ? 'var(--red)' : 'var(--green)' ?>;"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- Activate Subscription -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header"><h3>Activate Subscription (by Email)</h3></div>
    <form method="POST" style="display: flex; gap: 12px; align-items: end; flex-wrap: wrap;">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
        <input type="hidden" name="action" value="activate_sub">
        <div class="form-group" style="margin-bottom: 0; flex: 2;">
            <label>Student Email</label>
            <input type="email" name="email" class="form-control" required placeholder="student@gmail.com">
        </div>
        <div class="form-group" style="margin-bottom: 0;">
            <label>Plan</label>
            <select name="plan" class="form-control">
                <option value="basic">Basic</option>
                <option value="pro" selected>Pro</option>
            </select>
        </div>
        <div class="form-group" style="margin-bottom: 0;">
            <label>Days</label>
            <input type="number" name="days" class="form-control" value="30" min="1" style="width: 80px;">
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Activate</button>
    </form>
</div>

<div class="card">
    <div class="card-header"><h3>Students (<?= count($students) ?>)</h3></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Student</th><th>Email</th><th>College</th><th>XP</th><th>Streak</th><th>Joined</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($students as $s): ?>
                <tr>
                    <td style="display: flex; align-items: center; gap: 8px;">
                        <?php if (!empty($s['avatar_url'])): ?><img src="<?= htmlspecialchars($s['avatar_url']) ?>" style="width:24px;height:24px;border-radius:50%;"><?php endif; ?>
                        <?= htmlspecialchars($s['name']) ?>
                        <?php if (!empty($s['is_banned'])): ?><span class="badge badge-red">Banned</span><?php endif; ?>
                        <?php if (!empty($s['is_institution'])): ?><span class="badge badge-accent">Institution</span><?php endif; ?>
                    </td>
                    <td style="font-size: 12px; color: var(--text-muted);"><?= htmlspecialchars($s['email'] ?? '') ?></td>
                    <td style="font-size: 12px;"><?= htmlspecialchars($collegeMap[$s['college_id'] ?? 0] ?? '-') ?></td>
                    <td style="font-family: var(--font-mono); color: var(--accent);"><?= number_format($s['xp'] ?? 0) ?></td>
                    <td style="font-family: var(--font-mono);"><?= $s['streak'] ?? 0 ?></td>
                    <td style="font-size: 12px; color: var(--text-muted);"><?= !empty($s['created_at']) ? date('d M Y', strtotime($s['created_at'])) : '-' ?></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
                            <input type="hidden" name="student_id" value="<?= $s['id'] ?>">
                            <?php if (!empty($s['is_banned'])): ?>
                                <input type="hidden" name="action" value="restore">
                                <button type="submit" class="btn btn-sm btn-secondary">Restore</button>
                            <?php else: ?>
                                <input type="hidden" name="action" value="ban">
                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Ban this student?')">Ban</button>
                            <?php endif; ?>
                        </form>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
                            <input type="hidden" name="student_id" value="<?= $s['id'] ?>">
                            <?php if (!empty($s['is_institution'])): ?>
                                <input type="hidden" name="action" value="revoke_institution">
                                <button type="submit" class="btn btn-sm btn-secondary" onclick="return confirm('Revoke institution access?')">Revoke Inst.</button>
                            <?php else: ?>
                                <input type="hidden" name="action" value="make_institution">
                                <button type="submit" class="btn btn-sm btn-secondary" onclick="return confirm('Grant institution portal access to this account?')">Make Institution</button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
