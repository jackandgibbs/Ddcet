<?php
require_once __DIR__ . '/../config.php';
requireAdmin();
$pageTitle = 'Study Materials';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        supabaseRest('study_materials', 'POST', [
            'title' => $_POST['title'],
            'subject' => $_POST['subject'],
            'chapter' => $_POST['chapter'] ?: null,
            'type' => $_POST['type'],
            'file_url' => $_POST['file_url'] ?: null,
            'video_url' => $_POST['video_url'] ?: null,
        ]);
    } elseif ($action === 'delete') {
        supabaseRest('study_materials?id=eq.' . (int)$_POST['material_id'], 'DELETE');
    }
    header('Location: ' . BASE_PATH . 'admin/materials.php');
    exit;
}

$materials = supabaseRest('study_materials?select=*&order=subject,chapter,title') ?? [];

include __DIR__ . '/includes/header.php';
?>

<div class="card" style="margin-bottom: 20px;">
    <div class="card-header"><h3>Add Study Material</h3></div>
    <form method="POST">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
        <input type="hidden" name="action" value="add">
        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 12px;">
            <div class="form-group"><label>Title</label><input type="text" name="title" class="form-control" required></div>
            <div class="form-group"><label>Subject</label><input type="text" name="subject" class="form-control" required placeholder="Physics"></div>
            <div class="form-group"><label>Chapter</label><input type="text" name="chapter" class="form-control" placeholder="Mechanics"></div>
            <div class="form-group"><label>Type</label><select name="type" class="form-control"><option value="notes">Notes/PDF</option><option value="video">Video</option><option value="formula_sheet">Formula Sheet</option></select></div>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
            <div class="form-group"><label>PDF/File URL</label><input type="url" name="file_url" class="form-control" placeholder="https://drive.google.com/..."></div>
            <div class="form-group"><label>Video URL</label><input type="url" name="video_url" class="form-control" placeholder="https://youtube.com/..."></div>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Add Material</button>
    </form>
</div>

<div class="card">
    <div class="card-header"><h3>All Materials (<?= count($materials) ?>)</h3></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Title</th><th>Subject</th><th>Chapter</th><th>Type</th><th>Links</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($materials as $m): ?>
                <tr>
                    <td><?= htmlspecialchars($m['title']) ?></td>
                    <td><span class="tag"><?= htmlspecialchars($m['subject'] ?? '') ?></span></td>
                    <td style="font-size: 12px; color: var(--text-muted);"><?= htmlspecialchars($m['chapter'] ?? '-') ?></td>
                    <td style="font-size: 12px;"><?= htmlspecialchars($m['type'] ?? '') ?></td>
                    <td style="font-size: 12px;">
                        <?php if (!empty($m['file_url'])): ?><a href="<?= htmlspecialchars($m['file_url']) ?>" target="_blank">PDF</a> <?php endif; ?>
                        <?php if (!empty($m['video_url'])): ?><a href="<?= htmlspecialchars($m['video_url']) ?>" target="_blank">Video</a><?php endif; ?>
                    </td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete?')">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="material_id" value="<?= $m['id'] ?>">
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
