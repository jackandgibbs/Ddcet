<?php
require_once __DIR__ . '/../config.php';
requireAdmin();
$pageTitle = 'Doubts';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'answer') {
        supabaseRest('doubt_answers', 'POST', [
            'doubt_id' => (int)$_POST['doubt_id'],
            'body' => $_POST['answer_body'],
        ]);
        supabaseRest('doubts?id=eq.' . (int)$_POST['doubt_id'], 'PATCH', ['is_resolved' => true]);
    } elseif ($action === 'delete') {
        supabaseRest('doubts?id=eq.' . (int)$_POST['doubt_id'], 'DELETE');
    }
    header('Location: ' . BASE_PATH . 'admin/doubts.php');
    exit;
}

$doubts = supabaseRest('doubts?select=*,students(name)&order=created_at.desc&limit=50') ?? [];

include __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div class="card-header"><h3>Student Doubts (<?= count($doubts) ?>)</h3></div>
    <?php foreach ($doubts as $d): ?>
    <div style="border-bottom: 1px solid var(--border); padding: 16px 0;">
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
            <span class="badge <?= !empty($d['is_resolved']) ? 'badge-green' : 'badge-accent' ?>"><?= !empty($d['is_resolved']) ? 'Resolved' : 'Open' ?></span>
            <strong><?= htmlspecialchars($d['title'] ?? '') ?></strong>
            <span style="margin-left: auto; font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($d['students']['name'] ?? '') ?> · <?= date('d M', strtotime($d['created_at'])) ?></span>
        </div>
        <?php if (!empty($d['body'])): ?><p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 8px;"><?= htmlspecialchars($d['body']) ?></p><?php endif; ?>
        <?php if (empty($d['is_resolved'])): ?>
        <form method="POST" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
            <input type="hidden" name="action" value="answer">
            <input type="hidden" name="doubt_id" value="<?= $d['id'] ?>">
            <input type="text" name="answer_body" class="form-control" placeholder="Type answer..." required style="flex:1;">
            <button class="btn btn-primary btn-sm">Answer</button>
        </form>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
