<?php
require_once __DIR__ . '/../config.php';
requireAdmin();
$pageTitle = 'Resources';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        supabaseRest('resources', 'POST', [
            'title' => $_POST['title'],
            'url' => $_POST['url'],
            'category' => $_POST['category'],
            'topic' => $_POST['topic'],
            'source_label' => $_POST['source_label'] ?? '',
            'is_active' => true,
        ]);
    } elseif ($action === 'delete') {
        supabaseRest('resources?id=eq.' . (int)$_POST['resource_id'], 'DELETE');
    }
    header('Location: ' . BASE_PATH . 'admin/resources.php');
    exit;
}

$resources = supabaseRest('resources?select=*&order=category,topic,position&limit=200') ?? [];

include __DIR__ . '/includes/header.php';
?>

<div class="card" style="margin-bottom: 20px;">
    <div class="card-header"><h3>Add Resource</h3></div>
    <form method="POST">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
        <input type="hidden" name="action" value="add">
        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 12px;">
            <div class="form-group"><label>Title</label><input type="text" name="title" class="form-control" required></div>
            <div class="form-group"><label>Category</label><select name="category" class="form-control"><option value="physics">Physics</option><option value="chemistry">Chemistry</option><option value="maths">Maths</option><option value="english">English</option><option value="websites">Websites</option></select></div>
            <div class="form-group"><label>Topic</label><input type="text" name="topic" class="form-control" required placeholder="e.g. Trigonometry"></div>
        </div>
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 12px;">
            <div class="form-group"><label>URL</label><input type="url" name="url" class="form-control" required></div>
            <div class="form-group"><label>Source Label</label><input type="text" name="source_label" class="form-control" placeholder="e.g. YouTube"></div>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Add</button>
    </form>
</div>

<div class="card">
    <div class="card-header"><h3>All Resources (<?= count($resources) ?>)</h3></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Title</th><th>Category</th><th>Topic</th><th>Source</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($resources as $r): ?>
                <tr>
                    <td><a href="<?= htmlspecialchars($r['url'] ?? '') ?>" target="_blank"><?= htmlspecialchars($r['title'] ?? '') ?></a></td>
                    <td><span class="tag"><?= htmlspecialchars($r['category'] ?? '') ?></span></td>
                    <td style="font-size: 12px;"><?= htmlspecialchars($r['topic'] ?? '') ?></td>
                    <td style="font-size: 12px; color: var(--text-muted);"><?= htmlspecialchars($r['source_label'] ?? '') ?></td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete?')">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="resource_id" value="<?= $r['id'] ?>">
                            <button class="btn btn-sm btn-danger">Del</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
