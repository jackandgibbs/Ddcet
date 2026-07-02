<?php
require_once __DIR__ . '/../config.php';
$instUser = requireInstitution();
$org = currentOrg();
$pageTitle = 'Institution Dashboard';

// ---- First-run: create the organization -----------------------------------
if (!$org && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_org') {
    requireCsrf();
    $name = trim($_POST['name'] ?? '');
    if ($name !== '') {
        // Generate a join code, retrying on the (rare) unique-constraint clash.
        $orgRow = null;
        for ($try = 0; $try < 5 && !$orgRow; $try++) {
            $code = generateJoinCode();
            $exists = supabaseRest('organizations?join_code=eq.' . $code . '&select=id&limit=1');
            if (!empty($exists)) continue;
            $created = supabaseRest('organizations', 'POST', [
                'name'      => $name,
                'join_code' => $code,
                'owner_id'  => $instUser['id'],
            ]);
            $orgRow = $created[0] ?? null;
        }
        if ($orgRow) {
            // The owner is also a member of their own org.
            supabaseRest('students?id=eq.' . $instUser['id'], 'PATCH', ['org_id' => $orgRow['id']]);
            $_SESSION['user']['org_id'] = $orgRow['id'];
            redirect('institution/index.php');
        }
    }
}

// $allowNoOrg lets the shared header render the create-org screen instead of
// bouncing back here in a loop.
$allowNoOrg = true;

// ---- Stats (only meaningful once the org exists) ---------------------------
$batchCount = 0; $testCount = 0; $questionCount = 0; $attemptCount = 0; $recentStudents = [];
if ($org) {
    $oid = (int) $org['id'];
    $batch          = supabaseRest('students?org_id=eq.' . $oid . '&select=id,name,created_at&order=created_at.desc') ?? [];
    $tests          = supabaseRest('tests?org_id=eq.' . $oid . '&select=id') ?? [];
    $questions      = supabaseRest('questions?org_id=eq.' . $oid . '&select=id') ?? [];
    $batchCount     = count($batch);
    $testCount      = count($tests);
    $questionCount  = count($questions);
    $recentStudents = array_slice($batch, 0, 8);

    // Completed attempts on this org's tests.
    $testIds = array_column($tests, 'id');
    if ($testIds) {
        $attempts = supabaseRest('attempts?status=eq.completed&test_id=in.(' . implode(',', $testIds) . ')&select=id') ?? [];
        $attemptCount = count($attempts);
    }
}

include __DIR__ . '/includes/header.php';
?>

<?php if (!$org): ?>
<div class="card" style="max-width: 520px;">
    <div class="card-header"><h3>Set up your institution</h3></div>
    <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 16px;">
        Give your coaching institute or college a name. You'll get a join code to share
        with your students, and a private space for your own question bank and assignments.
    </p>
    <form method="POST">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
        <input type="hidden" name="action" value="create_org">
        <div class="form-group">
            <label>Institution Name</label>
            <input type="text" name="name" class="form-control" required maxlength="255" placeholder="e.g. Sunrise Polytechnic Coaching">
        </div>
        <button type="submit" class="btn btn-primary">Create Institution</button>
    </form>
</div>
<?php else: ?>

<div class="card" style="margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
    <div>
        <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Batch Join Code</div>
        <div style="font-family: var(--font-mono); font-size: 32px; font-weight: 700; color: var(--accent); letter-spacing: 4px;"><?= htmlspecialchars($org['join_code']) ?></div>
    </div>
    <div style="font-size: 13px; color: var(--text-secondary); max-width: 360px;">
        Students join your batch by going to <strong>Join an Institution</strong> and entering this code.
        Share it: <code>join-org.php?code=<?= htmlspecialchars($org['join_code']) ?></code>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card blue"><div class="stat-value"><?= $batchCount ?></div><div class="stat-label">Batch Students</div></div>
    <div class="stat-card accent"><div class="stat-value"><?= $questionCount ?></div><div class="stat-label">Questions</div></div>
    <div class="stat-card green"><div class="stat-value"><?= $testCount ?></div><div class="stat-label">Assignments</div></div>
    <div class="stat-card"><div class="stat-value"><?= $attemptCount ?></div><div class="stat-label">Attempts</div></div>
</div>

<div class="card" style="margin-top: 20px;">
    <div class="card-header"><h3>Recent Students</h3></div>
    <?php if ($recentStudents): ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Name</th><th>Joined</th></tr></thead>
            <tbody>
            <?php foreach ($recentStudents as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['name']) ?></td>
                    <td style="font-size: 12px; color: var(--text-muted);"><?= !empty($s['created_at']) ? date('d M Y', strtotime($s['created_at'])) : '-' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p style="color: var(--text-muted); font-size: 13px;">No students have joined yet. Share your join code <strong><?= htmlspecialchars($org['join_code']) ?></strong> to get started.</p>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
