<?php
require_once __DIR__ . '/../config.php';
$instUser = requireInstitution();
$org = currentOrg();
$pageTitle = 'Students';
if (!$org) redirect('institution/index.php');
$oid = (int) $org['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    if (($_POST['action'] ?? '') === 'remove') {
        $sid = (int) $_POST['student_id'];
        // Can't remove yourself (the owner); only detach a current member.
        if ($sid !== (int) $instUser['id']) {
            supabaseRest('students?id=eq.' . $sid . '&org_id=eq.' . $oid, 'PATCH', ['org_id' => null]);
        }
    }
    header('Location: ' . BASE_PATH . 'institution/students.php');
    exit;
}

$students = supabaseRest('students?org_id=eq.' . $oid . '&select=id,name,email,avatar_url,xp,streak,created_at&order=created_at.desc') ?? [];

include __DIR__ . '/includes/header.php';
?>

<div class="card" style="margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
    <div>
        <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Join Code</div>
        <div style="font-family: var(--font-mono); font-size: 24px; font-weight: 700; color: var(--accent); letter-spacing: 3px;"><?= htmlspecialchars($org['join_code']) ?></div>
    </div>
    <div style="font-size: 13px; color: var(--text-secondary); max-width: 360px;">Share this code (or <code>join-org.php?code=<?= htmlspecialchars($org['join_code']) ?></code>) so students can join your batch.</div>
</div>

<div class="card">
    <div class="card-header"><h3>Batch Students (<?= count($students) ?>)</h3></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Student</th><th>Email</th><th>XP</th><th>Streak</th><th>Joined</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($students as $s): ?>
                <tr>
                    <td style="display: flex; align-items: center; gap: 8px;">
                        <?php if (!empty($s['avatar_url'])): ?><img src="<?= htmlspecialchars($s['avatar_url']) ?>" style="width:24px;height:24px;border-radius:50%;"><?php endif; ?>
                        <?= htmlspecialchars($s['name']) ?>
                        <?php if ((int) $s['id'] === (int) $instUser['id']): ?><span class="badge badge-accent">Owner</span><?php endif; ?>
                    </td>
                    <td style="font-size: 12px; color: var(--text-muted);"><?= htmlspecialchars($s['email'] ?? '') ?></td>
                    <td style="font-family: var(--font-mono); color: var(--accent);"><?= number_format($s['xp'] ?? 0) ?></td>
                    <td style="font-family: var(--font-mono);"><?= $s['streak'] ?? 0 ?></td>
                    <td style="font-size: 12px; color: var(--text-muted);"><?= !empty($s['created_at']) ? date('d M Y', strtotime($s['created_at'])) : '-' ?></td>
                    <td>
                        <?php if ((int) $s['id'] !== (int) $instUser['id']): ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this student from your batch?')">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="student_id" value="<?= $s['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
