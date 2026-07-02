<?php
require_once __DIR__ . '/../config.php';
requireAdmin();
$pageTitle = 'Community';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        supabaseRest('community_posts', 'POST', [
            'title' => $_POST['title'],
            'body' => $_POST['body'],
            'category' => $_POST['category'],
            'is_visible' => true,
        ]);
    } elseif ($action === 'delete') {
        supabaseRest('community_posts?id=eq.' . (int)$_POST['post_id'], 'DELETE');
    } elseif ($action === 'hide') {
        supabaseRest('community_posts?id=eq.' . (int)$_POST['post_id'], 'PATCH', ['is_visible' => false]);
    } elseif ($action === 'show') {
        supabaseRest('community_posts?id=eq.' . (int)$_POST['post_id'], 'PATCH', ['is_visible' => true]);
    } elseif ($action === 'pin') {
        supabaseRest('community_posts?id=eq.' . (int)$_POST['post_id'], 'PATCH', ['is_pinned' => true]);
    } elseif ($action === 'unpin') {
        supabaseRest('community_posts?id=eq.' . (int)$_POST['post_id'], 'PATCH', ['is_pinned' => false]);
    }
    header('Location: ' . BASE_PATH . 'admin/community.php');
    exit;
}

$posts = supabaseRest('community_posts?select=*&order=is_pinned.desc,created_at.desc&limit=80') ?? [];

// Author names for student-authored posts (admin posts have a null student_id).
$authorIds = array_unique(array_filter(array_map(fn($p) => (int)($p['student_id'] ?? 0), $posts)));
$authorMap = [];
if ($authorIds) {
    $rows = supabaseRest('students?id=in.(' . implode(',', $authorIds) . ')&select=id,name') ?? [];
    foreach ($rows as $s) $authorMap[(int) $s['id']] = $s['name'];
}

include __DIR__ . '/includes/header.php';
?>

<div class="card" style="margin-bottom: 20px;">
    <div class="card-header"><h3>New Post</h3></div>
    <form method="POST">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
        <input type="hidden" name="action" value="add">
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 12px;">
            <div class="form-group"><label>Title</label><input type="text" name="title" class="form-control" required></div>
            <div class="form-group"><label>Category</label><select name="category" class="form-control"><option value="tip">Tip</option><option value="announcement">Announcement</option><option value="motivation">Motivation</option></select></div>
        </div>
        <div class="form-group"><label>Body</label><textarea name="body" class="form-control" rows="3" required></textarea></div>
        <button type="submit" class="btn btn-primary btn-sm">Publish</button>
    </form>
</div>

<div class="card">
    <div class="card-header"><h3>Posts (<?= count($posts) ?>)</h3></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Author</th><th>Title / Body</th><th>Category</th><th>Visible</th><th>Date</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($posts as $p): $csrf = htmlspecialchars(csrfToken()); $author = !empty($p['student_id']) ? ($authorMap[(int) $p['student_id']] ?? 'Student #' . (int) $p['student_id']) : 'DDCET Team'; ?>
                <tr>
                    <td style="font-size: 12px; <?= empty($p['student_id']) ? 'color: var(--accent); font-weight:600;' : '' ?>"><?= htmlspecialchars($author) ?></td>
                    <td style="max-width: 320px;">
                        <?php if (!empty($p['title'])): ?><strong><?= htmlspecialchars($p['title']) ?></strong><br><?php endif; ?>
                        <span style="font-size: 12px; color: var(--text-muted);"><?= htmlspecialchars(mb_strimwidth($p['body'] ?? '', 0, 90, '…')) ?></span>
                        <?= !empty($p['is_pinned']) ? ' <span class="badge badge-accent" style="font-size:9px;">Pinned</span>' : '' ?>
                    </td>
                    <td><span class="tag"><?= htmlspecialchars($p['category'] ?? '') ?></span></td>
                    <td><?= $p['is_visible'] ? '<span class="badge badge-green">Yes</span>' : '<span class="badge badge-red">Hidden</span>' ?></td>
                    <td style="font-size: 12px; color: var(--text-muted);"><?= date('d M Y', strtotime($p['created_at'])) ?></td>
                    <td style="white-space:nowrap;">
                        <?php $pinAction = !empty($p['is_pinned']) ? 'unpin' : 'pin'; ?>
                        <form method="POST" style="display:inline;"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="action" value="<?= $pinAction ?>"><input type="hidden" name="post_id" value="<?= $p['id'] ?>"><button class="btn btn-sm btn-secondary"><?= $pinAction === 'pin' ? 'Pin' : 'Unpin' ?></button></form>
                        <?php $visAction = $p['is_visible'] ? 'hide' : 'show'; ?>
                        <form method="POST" style="display:inline;"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="action" value="<?= $visAction ?>"><input type="hidden" name="post_id" value="<?= $p['id'] ?>"><button class="btn btn-sm btn-secondary"><?= $p['is_visible'] ? 'Hide' : 'Show' ?></button></form>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete?')"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="post_id" value="<?= $p['id'] ?>"><button class="btn btn-sm btn-danger">Del</button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
