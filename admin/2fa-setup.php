<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/totp.php';

requireAdmin(false);                          // identity only — no 2FA gate here

if (ADMIN_TOTP_SECRET === '') {
    $configError = 'ADMIN_TOTP_SECRET is not set in .env. Add it (a Base32 string), then reload this page.';
    $uri = '';
} else {
    $configError = null;
    $account = $_SESSION['user']['email'] ?? 'admin';
    $uri = totp_provisioning_uri(ADMIN_TOTP_SECRET, $account, ADMIN_TOTP_ISSUER);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set up 2FA — <?= htmlspecialchars(APP_NAME) ?> Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#f8f9fa; --card:#fff; --accent:#4361ee; --text:#1a1a2e; --text-sec:#6c757d; --text-muted:#adb5bd; --border:#e9ecef; --font:'DM Sans',sans-serif; --mono:'DM Mono',monospace; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { background:var(--bg); color:var(--text); font-family:var(--font); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:32px; }
        .card { background:var(--card); border:1px solid var(--border); border-radius:16px; box-shadow:0 16px 48px rgba(0,0,0,0.06); padding:40px; max-width:420px; width:100%; text-align:center; }
        h1 { font-size:22px; font-weight:800; margin-bottom:6px; }
        .subtitle { color:var(--text-sec); font-size:14px; margin-bottom:24px; line-height:1.6; }
        ol { text-align:left; font-size:14px; color:var(--text-sec); line-height:1.8; margin:0 0 20px 18px; }
        .qr { display:inline-flex; padding:14px; background:#fff; border:1px solid var(--border); border-radius:12px; margin-bottom:18px; }
        .secret { font-family:var(--mono); font-size:14px; background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:10px 12px; word-break:break-all; user-select:all; margin-bottom:20px; }
        .secret span { color:var(--text-muted); display:block; font-family:var(--font); font-size:11px; text-transform:uppercase; letter-spacing:.5px; margin-bottom:4px; }
        .btn { display:inline-block; width:100%; background:var(--accent); color:#fff; text-decoration:none; padding:14px; border-radius:10px; font-weight:600; font-size:15px; }
        .error-msg { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; padding:12px 14px; border-radius:8px; font-size:13px; text-align:left; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Set up Google Authenticator</h1>
        <?php if ($configError): ?>
            <div class="error-msg"><?= htmlspecialchars($configError) ?></div>
        <?php else: ?>
            <p class="subtitle">Scan this QR code with Google Authenticator (or any TOTP app), then enter a code to confirm.</p>
            <ol>
                <li>Open <b>Google Authenticator</b>.</li>
                <li>Tap <b>+</b> → <b>Scan a QR code</b>.</li>
                <li>Scan the code below (or enter the key manually).</li>
            </ol>
            <div class="qr"><canvas id="qr"></canvas></div>
            <div class="secret">
                <span>Manual entry key</span>
                <?= htmlspecialchars(ADMIN_TOTP_SECRET) ?>
            </div>
            <a class="btn" href="<?= BASE_PATH ?>admin/2fa-verify.php">I&rsquo;ve scanned it — continue</a>

            <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
            <script>
                const uri = <?= json_encode($uri) ?>;
                QRCode.toCanvas(document.getElementById('qr'), uri, { width: 200, margin: 1 }, function (err) {
                    if (err) {
                        document.getElementById('qr').replaceWith(
                            Object.assign(document.createElement('p'),
                                { textContent: 'Could not render QR — use the manual key below.',
                                  style: 'font-size:13px;color:#dc2626;' }));
                    }
                });
            </script>
        <?php endif; ?>
    </div>
</body>
</html>
