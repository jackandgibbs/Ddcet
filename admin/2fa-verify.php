<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/totp.php';

requireAdmin(false);                          // identity only — no 2FA gate here

// Already passed this session.
if (!empty($_SESSION['admin_2fa_ok'])) {
    redirect('admin/index.php');
}

// No secret configured yet → send admin to enrollment.
if (ADMIN_TOTP_SECRET === '') {
    redirect('admin/2fa-setup.php');
}

const OTP_MAX_TRY = 5;     // wrong codes before a short lockout
const OTP_LOCK    = 30;    // seconds locked after too many wrong codes

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lockedUntil = $_SESSION['admin_2fa_lock'] ?? 0;
    if (time() < $lockedUntil) {
        $error = 'Too many attempts. Please wait ' . ($lockedUntil - time()) . 's and try again.';
    } else {
        $code = preg_replace('/\s+/', '', $_POST['code'] ?? '');
        if ($code !== '' && totp_verify(ADMIN_TOTP_SECRET, $code)) {
            unset($_SESSION['admin_2fa_tries'], $_SESSION['admin_2fa_lock']);
            session_regenerate_id(true);          // prevent session fixation
            $_SESSION['admin_2fa_ok'] = true;
            redirect('admin/index.php');
        }
        $_SESSION['admin_2fa_tries'] = ($_SESSION['admin_2fa_tries'] ?? 0) + 1;
        if ($_SESSION['admin_2fa_tries'] >= OTP_MAX_TRY) {
            $_SESSION['admin_2fa_lock']  = time() + OTP_LOCK;
            $_SESSION['admin_2fa_tries'] = 0;
            $error = 'Too many incorrect codes. Locked for ' . OTP_LOCK . 's.';
        } else {
            $left  = OTP_MAX_TRY - $_SESSION['admin_2fa_tries'];
            $error = "Incorrect or expired code. {$left} attempt(s) left.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify 2FA — <?= htmlspecialchars(APP_NAME) ?> Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#f8f9fa; --card:#fff; --accent:#4361ee; --text:#1a1a2e; --text-sec:#6c757d; --text-muted:#adb5bd; --border:#e9ecef; --font:'DM Sans',sans-serif; --mono:'DM Mono',monospace; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { background:var(--bg); color:var(--text); font-family:var(--font); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:32px; }
        .card { background:var(--card); border:1px solid var(--border); border-radius:16px; box-shadow:0 16px 48px rgba(0,0,0,0.06); padding:40px; max-width:400px; width:100%; text-align:center; }
        .icon { width:56px; height:56px; border-radius:14px; background:rgba(67,97,238,0.1); color:var(--accent); display:flex; align-items:center; justify-content:center; margin:0 auto 18px; font-size:26px; }
        h1 { font-size:22px; font-weight:800; margin-bottom:6px; }
        .subtitle { color:var(--text-sec); font-size:14px; margin-bottom:24px; line-height:1.6; }
        .subtitle b { color:var(--text); }
        label { display:block; font-size:13px; font-weight:600; margin-bottom:8px; text-align:left; }
        input { width:100%; padding:14px 16px; border:1px solid var(--border); border-radius:10px; font-family:var(--mono); font-size:24px; letter-spacing:8px; text-align:center; }
        input:focus { outline:none; border-color:var(--accent); }
        button { width:100%; margin-top:16px; background:var(--accent); color:#fff; border:none; padding:14px; border-radius:10px; font-weight:600; font-size:15px; font-family:var(--font); cursor:pointer; transition:transform .15s; }
        button:hover { transform:translateY(-1px); }
        .msg { padding:12px 14px; border-radius:8px; margin-bottom:18px; font-size:13px; }
        .error-msg { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; }
        .links { display:flex; justify-content:space-between; margin-top:18px; font-size:12px; }
        .links a { color:var(--text-muted); }
        .links a:hover { color:var(--accent); }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">&#128274;</div>
        <h1>Two-factor authentication</h1>
        <p class="subtitle">Open <b>Google Authenticator</b> and enter the current 6-digit code for <b><?= htmlspecialchars(ADMIN_TOTP_ISSUER) ?></b>.</p>

        <?php if ($error): ?><div class="msg error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form method="post" autocomplete="off">
            <label for="code">Authenticator code</label>
            <input id="code" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" placeholder="000000" autofocus required>
            <button type="submit">Verify</button>
        </form>

        <div class="links">
            <a href="<?= BASE_PATH ?>admin/2fa-setup.php">Set up a new device</a>
            <a href="<?= BASE_PATH ?>auth/logout.php">Sign out</a>
        </div>
    </div>
</body>
</html>
