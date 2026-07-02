<?php
require_once __DIR__ . '/../config.php';
requireAdmin();
$pageTitle = 'Queries';

$STATUSES = [
    'new'         => ['label' => 'New',         'badge' => 'badge-blue'],
    'in_progress' => ['label' => 'In Progress', 'badge' => 'badge-accent'],
    'completed'   => ['label' => 'Completed',   'badge' => 'badge-green'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['query_id'] ?? 0);

    if ($action === 'update' && $id) {
        $status = $_POST['status'] ?? 'new';
        if (!isset($STATUSES[$status])) $status = 'new';
        $patch = [
            'status'      => $status,
            'admin_reply' => trim($_POST['admin_reply'] ?? '') ?: null,
            'updated_at'  => date('c'),
        ];
        supabaseRest('queries?id=eq.' . $id, 'PATCH', $patch);
    } elseif ($action === 'delete' && $id) {
        supabaseRest('queries?id=eq.' . $id, 'DELETE');
    }
    header('Location: ' . BASE_PATH . 'admin/queries.php' . (isset($_GET['filter']) ? '?filter=' . urlencode($_GET['filter']) : ''));
    exit;
}

// Optional status filter
$filter = $_GET['filter'] ?? '';
$query = 'queries?select=*,students(name)&order=created_at.desc&limit=100';
if (isset($STATUSES[$filter])) {
    $query = 'queries?status=eq.' . $filter . '&select=*,students(name)&order=created_at.desc&limit=100';
}
$queries = supabaseRest($query) ?? [];

// Counts per status (for filter tabs)
$counts = ['all' => 0, 'new' => 0, 'in_progress' => 0, 'completed' => 0];
$allForCount = supabaseRest('queries?select=status&limit=1000') ?? [];
$counts['all'] = count($allForCount);
foreach ($allForCount as $row) {
    $s = $row['status'] ?? 'new';
    if (isset($counts[$s])) $counts[$s]++;
}

include __DIR__ . '/includes/header.php';
?>

<div style="display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap;">
    <?php
    $tabs = ['' => 'All (' . $counts['all'] . ')', 'new' => 'New (' . $counts['new'] . ')', 'in_progress' => 'In Progress (' . $counts['in_progress'] . ')', 'completed' => 'Completed (' . $counts['completed'] . ')'];
    foreach ($tabs as $key => $label):
        $active = ($filter === $key) || ($key === '' && !isset($STATUSES[$filter]));
    ?>
        <a href="admin/queries.php<?= $key ? '?filter=' . $key : '' ?>" class="btn btn-sm <?= $active ? 'btn-primary' : 'btn-secondary' ?>"><?= $label ?></a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header"><h3>Student Queries (<?= count($queries) ?>)</h3></div>
    <?php if (empty($queries)): ?>
        <p style="color: var(--text-muted);">No queries found.</p>
    <?php endif; ?>
    <?php foreach ($queries as $q):
        $st = $STATUSES[$q['status'] ?? 'new'] ?? $STATUSES['new']; ?>
    <div style="border-bottom: 1px solid var(--border); padding: 16px 0;">
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
            <span class="badge <?= $st['badge'] ?>"><?= $st['label'] ?></span>
            <strong><?= htmlspecialchars($q['subject'] ?? '') ?></strong>
            <span style="margin-left: auto; font-size: 11px; color: var(--text-muted);">
                <?= htmlspecialchars($q['students']['name'] ?? ($q['name'] ?? 'Unknown')) ?>
                <?php if (!empty($q['email'])): ?> · <?= htmlspecialchars($q['email']) ?><?php endif; ?>
                · <?= date('d M Y', strtotime($q['created_at'])) ?>
            </span>
        </div>
        <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 10px; white-space: pre-wrap;"><?= htmlspecialchars($q['message'] ?? '') ?></p>

        <form method="POST" action="admin/queries.php<?= $filter ? '?filter=' . htmlspecialchars($filter) : '' ?>" style="display: flex; gap: 8px; align-items: flex-start; flex-wrap: wrap;">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="query_id" value="<?= (int)$q['id'] ?>">
            <select name="status" class="form-control" style="width: auto; min-width: 140px;">
                <?php foreach ($STATUSES as $key => $info): ?>
                    <option value="<?= $key ?>" <?= ($q['status'] ?? 'new') === $key ? 'selected' : '' ?>><?= $info['label'] ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="admin_reply" class="form-control" placeholder="Reply to student (optional)..." value="<?= htmlspecialchars($q['admin_reply'] ?? '') ?>" style="flex: 1; min-width: 220px;">
            <button class="btn btn-primary btn-sm" type="submit">Save</button>
        </form>
        <form method="POST" action="admin/queries.php<?= $filter ? '?filter=' . htmlspecialchars($filter) : '' ?>" style="margin-top: 6px;" onsubmit="return confirm('Delete this query?');">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="query_id" value="<?= (int)$q['id'] ?>">
            <button class="btn btn-secondary btn-sm" type="submit" style="color: var(--red);">Delete</button>
        </form>
    </div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
