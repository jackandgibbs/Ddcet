<?php
/**
 * One-click unsubscribe from marketing email (reminders, challenge invites).
 * No login required — the HMAC token in the link is the authorization. Welcome
 * and payment-receipt mail are transactional and unaffected.
 */
require_once __DIR__ . '/config.php';

$id = (int) ($_GET['u'] ?? 0);
$token = (string) ($_GET['t'] ?? '');
$ok = $id > 0 && CRON_SECRET !== '' && hash_equals(mailToken($id), $token);

if ($ok) {
    supabaseRest('students?id=eq.' . $id, 'PATCH', ['email_opt_out' => true]);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Unsubscribe — <?= htmlspecialchars(APP_NAME) ?></title>
    <style>
        body { margin:0; font-family: Arial, Helvetica, sans-serif; background:#f4f5f7; color:#1a1a2e; }
        .box { max-width: 460px; margin: 80px auto; background:#fff; border-radius:12px; padding:36px; text-align:center; box-shadow:0 4px 20px rgba(0,0,0,.06); }
        h1 { font-size: 20px; margin: 0 0 10px; }
        p { color:#555; font-size:14px; line-height:1.6; }
        a.btn { display:inline-block; margin-top:18px; background:#f0a500; color:#1a1a2e; text-decoration:none; font-weight:700; padding:11px 22px; border-radius:8px; }
    </style>
</head>
<body>
    <div class="box">
        <?php if ($ok): ?>
            <h1>You're unsubscribed <?= icon('check-circle', 32) ?></h1>
            <p>You won't receive reminder or challenge emails from <?= htmlspecialchars(APP_NAME) ?> anymore. Important account emails (like payment receipts) may still be sent.</p>
            <p style="font-size:12px;color:#999;">Changed your mind? You can re-enable email reminders from your profile settings.</p>
        <?php else: ?>
            <h1>Invalid link</h1>
            <p>This unsubscribe link is invalid or has expired. If you keep getting unwanted emails, please contact support.</p>
        <?php endif; ?>
        <a class="btn" href="<?= htmlspecialchars(BASE_PATH) ?>">Back to <?= htmlspecialchars(APP_NAME) ?></a>
    </div>
</body>
</html>
