<?php
require_once __DIR__ . '/config.php';
$user = requireAuth();
$db = getDB();
$pageTitle = 'Notifications';

// Mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_read') {
    supabaseRest('notifications?student_id=eq.' . $user['id'] . '&is_read=eq.false', 'PATCH', ['is_read' => true]);
}

$notifs = supabaseRest('notifications?student_id=eq.' . $user['id'] . '&order=created_at.desc&limit=50&select=*') ?? [];

$unreadCount = 0;
foreach ($notifs as $n) { if (empty($n['is_read'])) $unreadCount++; }

include __DIR__ . '/includes/header.php';
?>

<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
    <span style="color: var(--text-muted); font-size: 13px;"><?= $unreadCount ?> unread</span>
    <?php if ($unreadCount > 0): ?>
    <form method="POST"><input type="hidden" name="action" value="mark_read"><button type="submit" class="btn btn-secondary btn-sm">Mark All Read</button></form>
    <?php endif; ?>
</div>

<?php if (empty($notifs)): ?>
    <div class="card" style="text-align: center; padding: 40px; color: var(--text-muted);">No notifications yet.</div>
<?php else: ?>
<?php foreach ($notifs as $n): ?>
<div class="card" style="margin-bottom: 8px; padding: 14px; <?= !$n['is_read'] ? 'border-left: 3px solid var(--accent);' : 'opacity: 0.7;' ?>">
    <div style="display: flex; align-items: center; gap: 12px;">
        <span style="font-size: 18px;"><?= match($n['type'] ?? '') { 'subscription' => icon('credit-card', 18), 'test' => icon('test', 18), 'reminder' => icon('bell', 18), 'content' => icon('book', 18), default => icon('bell', 18) } ?></span>
        <div style="flex: 1;">
            <div style="font-size: 13px; font-weight: 500;"><?= htmlspecialchars($n['title']) ?></div>
            <?php if ($n['body']): ?><div style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;"><?= htmlspecialchars($n['body']) ?></div><?php endif; ?>
        </div>
        <span style="font-size: 11px; color: var(--text-muted);"><?= date('d M, H:i', strtotime($n['created_at'])) ?></span>
        <?php if ($n['link']): ?><a href="<?= htmlspecialchars($n['link']) ?>" class="btn btn-sm btn-secondary">View</a><?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
