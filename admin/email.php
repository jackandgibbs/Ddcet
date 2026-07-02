<?php
require_once __DIR__ . '/../config.php';
requireAdmin();
$pageTitle = 'Email';

$flash = ''; $flashOk = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    if (($_POST['action'] ?? '') === 'test') {
        $to = trim($_POST['email'] ?? '');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $flash = 'Enter a valid email address.';
        } elseif (BREVO_API_KEY === '' || MAIL_FROM_EMAIL === '') {
            $flash = 'Email is not configured yet — set BREVO_API_KEY and MAIL_FROM_EMAIL in .env first.';
        } else {
            $body = 'This is a test message from your <strong>' . htmlspecialchars(MAIL_FROM_NAME) . '</strong> email setup. '
                  . 'If it reached your inbox, delivery is working correctly.<br><br>'
                  . 'Sent at ' . date('d M Y, H:i') . ' IST.';
            $ok = sendEmail($to, 'Admin', 'Test email from ' . MAIL_FROM_NAME,
                emailTemplate('Your email setup is working', $body, 'Open the dashboard', APP_URL . BASE_PATH . 'dashboard.php'));
            $flashOk = $ok;
            $flash = $ok
                ? 'Test email sent to ' . htmlspecialchars($to) . '. Check the inbox (and spam folder).'
                : 'Brevo rejected the send. Check that the API key is valid and MAIL_FROM_EMAIL is a verified sender in Brevo.';
        }
    }
}

// Configuration status for the checklist.
$cfg = [
    'BREVO_API_KEY'   => BREVO_API_KEY !== '',
    'MAIL_FROM_EMAIL' => MAIL_FROM_EMAIL !== '',
    'CRON_SECRET'     => CRON_SECRET !== '',
];
$cronUrl = APP_URL . BASE_PATH . 'cron/send_reminders.php?key=' . (CRON_SECRET !== '' ? CRON_SECRET : 'YOUR_CRON_SECRET');

include __DIR__ . '/includes/header.php';
?>

<?php if ($flash): ?>
<div class="card" style="margin-bottom:16px; padding:12px 16px; font-size:13px; border-color: var(--<?= $flashOk ? 'green' : 'red' ?>); color: var(--<?= $flashOk ? 'green' : 'red' ?>);">
    <?= htmlspecialchars($flash) ?>
</div>
<?php endif; ?>

<!-- Config checklist -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header"><h3>Email configuration</h3></div>
    <div class="table-wrap"><table style="font-size:13px;">
        <?php foreach ($cfg as $k => $set): ?>
        <tr>
            <td style="padding:4px 16px 4px 0; font-family:var(--font-mono);"><?= $k ?></td>
            <td><?= $set
                ? '<span class="badge badge-green">set</span>'
                : '<span class="badge badge-red">missing</span>' ?></td>
        </tr>
        <?php endforeach; ?>
    </table></div>
    <p style="font-size:12px; color:var(--text-muted); margin-top:10px;">
        Set these in the project <code>.env</code> file, then reload. Until <code>BREVO_API_KEY</code> and
        <code>MAIL_FROM_EMAIL</code> are set, all emails are silently skipped (the app keeps working).
    </p>
</div>

<!-- Send test -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header"><h3>Send a test email</h3></div>
    <form method="POST" style="display:flex; gap:12px; align-items:end; flex-wrap:wrap;">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
        <input type="hidden" name="action" value="test">
        <div class="form-group" style="margin-bottom:0; flex:1; min-width:240px;">
            <label>Send to</label>
            <input type="email" name="email" class="form-control" required placeholder="you@example.com">
        </div>
        <button type="submit" class="btn btn-primary">Send test</button>
    </form>
</div>

<!-- Reminder cron -->
<div class="card">
    <div class="card-header"><h3>Reminder cron</h3></div>
    <p style="font-size:13px; color:var(--text-secondary); margin-bottom:10px;">
        Reminders (mock-starting-soon, inactivity nudges) need an external pinger since this stack has no cron.
        At <a href="https://cron-job.org" target="_blank" rel="noopener">cron-job.org</a> (free), create a job that
        does a GET on the URL below every 30–60 minutes.
    </p>
    <input type="text" class="form-control" readonly value="<?= htmlspecialchars($cronUrl) ?>"
        style="font-family:var(--font-mono); font-size:12px;" onclick="this.select()">
    <?php if (CRON_SECRET === ''): ?>
    <p style="font-size:12px; color:var(--red); margin-top:8px;">Set <code>CRON_SECRET</code> in .env first — the endpoint returns 403 until then.</p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
