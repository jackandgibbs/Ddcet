<?php
require_once __DIR__ . '/../config.php';
requireAdmin();
$pageTitle = 'Demo Leads';

$STATUSES = [
    'new'       => ['label' => 'New',       'badge' => 'badge-blue'],
    'contacted' => ['label' => 'Contacted', 'badge' => 'badge-accent'],
    'converted' => ['label' => 'Converted', 'badge' => 'badge-green'],
    'closed'    => ['label' => 'Closed',    'badge' => 'badge-red'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['lead_id'] ?? 0);

    if ($action === 'update' && $id) {
        $status = $_POST['status'] ?? 'new';
        if (!isset($STATUSES[$status])) $status = 'new';
        supabaseRest('demo_leads?id=eq.' . $id, 'PATCH', [
            'status'      => $status,
            'admin_notes' => trim($_POST['admin_notes'] ?? '') ?: null,
            'updated_at'  => date('c'),
        ]);
    } elseif ($action === 'delete' && $id) {
        supabaseRest('demo_leads?id=eq.' . $id, 'DELETE');
    }
    header('Location: ' . BASE_PATH . 'admin/leads.php' . (isset($_GET['filter']) ? '?filter=' . urlencode($_GET['filter']) : ''));
    exit;
}

// Optional status filter
$filter = $_GET['filter'] ?? '';
$base = 'demo_leads?select=*&order=created_at.desc&limit=200';
if (isset($STATUSES[$filter])) {
    $base = 'demo_leads?status=eq.' . $filter . '&select=*&order=created_at.desc&limit=200';
}
$leads = supabaseRest($base) ?? [];

// Counts per status (for filter tabs)
$counts = ['all' => 0, 'new' => 0, 'contacted' => 0, 'converted' => 0, 'closed' => 0];
$allForCount = supabaseRest('demo_leads?select=status&limit=2000') ?? [];
$counts['all'] = count($allForCount);
foreach ($allForCount as $row) {
    $s = $row['status'] ?? 'new';
    if (isset($counts[$s])) $counts[$s]++;
}

include __DIR__ . '/includes/header.php';
?>

<div style="display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap;">
    <?php
    $tabs = ['' => 'All (' . $counts['all'] . ')', 'new' => 'New (' . $counts['new'] . ')', 'contacted' => 'Contacted (' . $counts['contacted'] . ')', 'converted' => 'Converted (' . $counts['converted'] . ')', 'closed' => 'Closed (' . $counts['closed'] . ')'];
    foreach ($tabs as $key => $label):
        $active = ($filter === $key) || ($key === '' && !isset($STATUSES[$filter]));
    ?>
        <a href="admin/leads.php<?= $key ? '?filter=' . $key : '' ?>" class="btn btn-sm <?= $active ? 'btn-primary' : 'btn-secondary' ?>"><?= $label ?></a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header"><h3>Institution Demo Leads (<?= count($leads) ?>)</h3></div>
    <?php if (empty($leads)): ?>
        <p style="color: var(--text-muted);">No demo leads yet.</p>
    <?php endif; ?>
    <?php foreach ($leads as $l):
        $st = $STATUSES[$l['status'] ?? 'new'] ?? $STATUSES['new']; ?>
    <div style="border-bottom: 1px solid var(--border); padding: 16px 0;">
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px; flex-wrap: wrap;">
            <span class="badge <?= $st['badge'] ?>"><?= $st['label'] ?></span>
            <strong><?= htmlspecialchars($l['organization'] ?? '') ?></strong>
            <span style="margin-left: auto; font-size: 11px; color: var(--text-muted);">
                <?= date('d M Y, H:i', strtotime($l['created_at'])) ?>
            </span>
        </div>
        <div style="font-size: 13px; color: var(--text-secondary); margin-bottom: 8px; line-height: 1.7;">
            <strong><?= htmlspecialchars($l['contact_name'] ?? '') ?></strong><?php if (!empty($l['role'])): ?> · <?= htmlspecialchars($l['role']) ?><?php endif; ?><br>
            <a href="mailto:<?= htmlspecialchars($l['email'] ?? '') ?>"><?= htmlspecialchars($l['email'] ?? '') ?></a>
            <?php if (!empty($l['phone'])): ?> · <a href="tel:<?= htmlspecialchars($l['phone']) ?>"><?= htmlspecialchars($l['phone']) ?></a><?php endif; ?>
            <?php if (!empty($l['city'])): ?> · <?= htmlspecialchars($l['city']) ?><?php endif; ?>
            <?php if (!empty($l['student_count'])): ?> · <?= htmlspecialchars($l['student_count']) ?> students<?php endif; ?>
        </div>
        <?php if (!empty($l['message'])): ?>
            <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 10px; white-space: pre-wrap; background: var(--bg-primary, rgba(0,0,0,0.02)); padding: 10px; border-radius: 6px;"><?= htmlspecialchars($l['message']) ?></p>
        <?php endif; ?>

        <form method="POST" action="admin/leads.php<?= $filter ? '?filter=' . htmlspecialchars($filter) : '' ?>" style="display: flex; gap: 8px; align-items: flex-start; flex-wrap: wrap;">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="lead_id" value="<?= (int)$l['id'] ?>">
            <select name="status" class="form-control" style="width: auto; min-width: 140px;">
                <?php foreach ($STATUSES as $key => $info): ?>
                    <option value="<?= $key ?>" <?= ($l['status'] ?? 'new') === $key ? 'selected' : '' ?>><?= $info['label'] ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="admin_notes" class="form-control" placeholder="Internal notes (e.g. demo scheduled 12 Jun)..." value="<?= htmlspecialchars($l['admin_notes'] ?? '') ?>" style="flex: 1; min-width: 220px;">
            <button class="btn btn-primary btn-sm" type="submit">Save</button>
        </form>
        <form method="POST" action="admin/leads.php<?= $filter ? '?filter=' . htmlspecialchars($filter) : '' ?>" style="margin-top: 6px;" onsubmit="return confirm('Delete this lead?');">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="lead_id" value="<?= (int)$l['id'] ?>">
            <button class="btn btn-secondary btn-sm" type="submit" style="color: var(--red);">Delete</button>
        </form>
    </div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
