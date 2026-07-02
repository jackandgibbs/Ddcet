<?php
require_once __DIR__ . '/config.php';
// Contact Us requires a logged-in account. requireAuth() bounces guests to the
// login page; after they log in they land back here (see the redirect param the
// footer link sends, handled in auth/callback.php).
$user = requireAuth();
$pageTitle = 'Contact Us';

$STATUSES = [
    'new'         => ['label' => 'New',         'badge' => 'badge-blue'],
    'in_progress' => ['label' => 'In Progress', 'badge' => 'badge-accent'],
    'completed'   => ['label' => 'Completed',   'badge' => 'badge-green'],
];

// Submit a new query
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit') {
    requireCsrf();
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    if (honeypotTripped()) {
        // Bot filled the hidden field. Pretend success so it doesn't probe further.
        $_SESSION['flash'] = 'Your query has been submitted. We will get back to you soon!';
        header('Location: ' . BASE_PATH . 'contact.php');
        exit;
    } elseif (!rateLimit('contact_submit', 5, 600)) {
        // Max 5 queries per 10 minutes per user.
        $error = 'You are sending queries too quickly. Please wait a few minutes and try again.';
    } elseif ($subject === '' || $message === '') {
        $error = 'Please fill in both a subject and a message.';
    } else {
        supabaseRest('queries', 'POST', [
            'student_id' => $user['id'],
            'name'       => $user['name'] ?? null,
            'email'      => $user['email'] ?? null,
            'subject'    => mb_substr($subject, 0, 200),
            'message'    => $message,
            'status'     => 'new',
        ]);
        $_SESSION['flash'] = 'Your query has been submitted. We will get back to you soon!';
        header('Location: ' . BASE_PATH . 'contact.php');
        exit;
    }
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Load this student's own queries (never anyone else's)
$myQueries = supabaseRest(
    'queries?student_id=eq.' . (int)$user['id'] . '&select=*&order=created_at.desc&limit=50'
) ?? [];

include __DIR__ . '/includes/header.php';
?>

<?php if ($flash): ?>
    <div class="card" style="border-color: var(--green); margin-bottom: 16px; padding: 12px 16px; font-size: 13px; color: var(--green);">
        <?= icon('check-circle', 14) ?> <?= htmlspecialchars($flash) ?>
    </div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="card" style="border-color: var(--red); margin-bottom: 16px; padding: 12px 16px; font-size: 13px; color: var(--red);">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 1.2fr; gap: 20px; align-items: start;">

    <!-- Submit a query -->
    <div class="card">
        <div class="card-header"><h3><?= icon('chat', 18) ?> Send us a Query</h3></div>
        <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 14px;">
            Have a question, an issue, or feedback? Send it here and our team will track it through to completion.
        </p>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
            <input type="hidden" name="action" value="submit">
            <?= honeypotField() ?>
            <div class="form-group">
                <label>Subject</label>
                <input type="text" name="subject" class="form-control" required maxlength="200" placeholder="e.g. Payment not reflecting on my account">
            </div>
            <div class="form-group">
                <label>Message</label>
                <textarea name="message" class="form-control" rows="5" required placeholder="Describe your query in detail..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Submit Query</button>
        </form>
    </div>

    <!-- My queries -->
    <div class="card">
        <div class="card-header"><h3><?= icon('clipboard', 18) ?> My Queries (<?= count($myQueries) ?>)</h3></div>
        <?php if (empty($myQueries)): ?>
            <p style="color: var(--text-muted); font-size: 13px;">You haven't raised any queries yet.</p>
        <?php else: ?>
            <?php foreach ($myQueries as $q):
                $st = $STATUSES[$q['status'] ?? 'new'] ?? $STATUSES['new']; ?>
            <div style="border-bottom: 1px solid var(--border); padding: 14px 0;">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                    <span class="badge <?= $st['badge'] ?>"><?= $st['label'] ?></span>
                    <strong style="font-size: 14px;"><?= htmlspecialchars($q['subject']) ?></strong>
                    <span style="margin-left: auto; font-size: 11px; color: var(--text-muted);"><?= date('d M Y', strtotime($q['created_at'])) ?></span>
                </div>
                <p style="color: var(--text-secondary); font-size: 13px; white-space: pre-wrap;"><?= htmlspecialchars($q['message']) ?></p>
                <?php if (!empty($q['admin_reply'])): ?>
                    <div style="background: var(--bg-primary); border-radius: 6px; padding: 10px; margin-top: 8px; font-size: 13px;">
                        <strong style="color: var(--green);">Reply:</strong> <span style="white-space: pre-wrap;"><?= htmlspecialchars($q['admin_reply']) ?></span>
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
