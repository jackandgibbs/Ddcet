<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/supabase_auth.php';

if (currentUser()) {
    header('Location: ' . BASE_PATH . 'dashboard.php');
    exit;
}

$googleAuthUrl = getSupabaseAuthUrl();
$isConfigured = !empty(SUPABASE_URL) && !empty(SUPABASE_KEY);
$errorMsg = $_GET['error'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #f8f9fa; --card: #ffffff; --accent: #4361ee; --green: #10b981; --text: #1a1a2e; --text-sec: #6c757d; --text-muted: #adb5bd; --border: #e9ecef; --radius: 12px; --font: 'DM Sans', sans-serif; --mono: 'DM Mono', monospace; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: var(--bg); color: var(--text); font-family: var(--font); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 32px; }
        .login-wrapper { display: grid; grid-template-columns: 1fr 1fr; max-width: 900px; width: 100%; background: var(--card); border-radius: 16px; overflow: hidden; box-shadow: 0 16px 48px rgba(0,0,0,0.06); border: 1px solid var(--border); }
        .login-left { background: linear-gradient(135deg, #1a1a2e, #2d1b69); padding: 48px; display: flex; flex-direction: column; justify-content: center; color: #fff; }
        .login-left h2 { font-size: 28px; font-weight: 800; margin-bottom: 12px; line-height: 1.2; }
        .login-left p { font-size: 14px; opacity: 0.8; line-height: 1.7; margin-bottom: 32px; }
        .login-stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
        .login-stat { background: rgba(255,255,255,0.1); backdrop-filter: blur(10px); border-radius: 10px; padding: 16px; text-align: center; }
        .login-stat .val { font-family: var(--mono); font-size: 22px; font-weight: 700; }
        .login-stat .lbl { font-size: 11px; opacity: 0.7; margin-top: 2px; }
        .login-right { padding: 48px; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; }
        .login-right h1 { font-size: 24px; font-weight: 800; margin-bottom: 4px; }
        .login-right h1 span { color: var(--accent); }
        .login-right .subtitle { color: var(--text-sec); font-size: 14px; margin-bottom: 32px; }
        .error-msg { background: #fef2f2; color: #dc2626; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; width: 100%; border: 1px solid #fecaca; }
        .warning-msg { background: #fffbeb; color: #d97706; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 12px; width: 100%; border: 1px solid #fde68a; }
        .google-btn { display: inline-flex; align-items: center; gap: 12px; background: var(--card); color: var(--text); padding: 14px 32px; border-radius: 10px; font-weight: 600; font-size: 15px; transition: all 0.2s; border: 1px solid var(--border); box-shadow: 0 2px 8px rgba(0,0,0,0.04); text-decoration: none; }
        .google-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.08); border-color: var(--accent); }
        .google-btn svg { width: 20px; height: 20px; }
        .footer-text { margin-top: 24px; font-size: 12px; color: var(--text-muted); }
        .footer-text a { color: var(--accent); }
        .back-link { position: absolute; top: 24px; left: 24px; font-size: 13px; color: var(--text-sec); }
        .back-link:hover { color: var(--accent); }

        @media (max-width: 768px) {
            .login-wrapper { grid-template-columns: 1fr; }
            .login-left { display: none; }
            .login-right { padding: 40px 24px; }
        }
    </style>
</head>
<body>
    <a href="<?= BASE_PATH ?>" class="back-link">← Back to home</a>
    <style>
    .toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; }
    .toast { background: #10b981; color: white; border-radius: 10px; padding: 14px 20px; box-shadow: 0 8px 24px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 10px; font-size: 14px; max-width: 360px; animation: slideIn 0.3s ease; font-weight: 500; }
    .toast-close { cursor: pointer; margin-left: 10px; font-size: 18px; opacity: 0.8; }
    .toast-close:hover { opacity: 1; }
    @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    </style>
    <script>
    function showToast(msg, duration = 5000) {
        let container = document.querySelector('.toast-container');
        if (!container) { container = document.createElement('div'); container.className = 'toast-container'; document.body.appendChild(container); }
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.innerHTML = '✓ ' + msg + '<span class="toast-close" onclick="this.parentElement.remove()">✕</span>';
        container.appendChild(toast);
        setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateX(100%)'; toast.style.transition = 'all 0.3s'; setTimeout(() => toast.remove(), 300); }, duration);
    }
    <?php if (isset($_SESSION['show_logout_success'])): unset($_SESSION['show_logout_success']); ?>
    window.addEventListener('DOMContentLoaded', function() { showToast('Successfully logged out'); });
    <?php endif; ?>
    history.pushState(null, null, location.href);
    window.onpopstate = function() {
        history.go(1);
    };
    </script>
    <div class="login-wrapper">
        <div class="login-left">
            <h2>Your DDCET Prep Journey Starts Here</h2>
            <p>Join 500+ students already preparing smarter with AI-powered analytics, real-pattern mock tests, and a competitive community.</p>
            <div class="login-stats">
                <div class="login-stat"><div class="val">500+</div><div class="lbl">Students</div></div>
                <div class="login-stat"><div class="val">2000+</div><div class="lbl">Questions</div></div>
                <div class="login-stat"><div class="val">142</div><div class="lbl">Tests</div></div>
                <div class="login-stat"><div class="val">95%</div><div class="lbl">Satisfaction</div></div>
            </div>
        </div>
        <div class="login-right">
            <img src="<?= BASE_PATH ?>assets/logo.png" alt="" style="height: 32px; margin-bottom: 8px;">
            <h1><span>DDCET</span> Prep</h1>
            <p class="subtitle">Sign in to start your preparation</p>

            <?php if (!$isConfigured): ?>
                <div class="warning-msg">Supabase not configured. Check SUPABASE_URL and SUPABASE_KEY in .env</div>
            <?php endif; ?>

            <?php if ($errorMsg): ?>
                <div class="error-msg">
                    <?php
                    $errors = [
                        'token_failed' => 'Failed to exchange code for token',
                        'no_token' => 'No access token received',
                        'no_user' => 'Could not fetch user information',
                        'banned' => 'Your account has been banned',
                        'db_error' => 'Database error. Please try again.',
                    ];
                    echo $errors[$errorMsg] ?? 'Authentication failed. Please try again.';
                    ?>
                </div>
            <?php endif; ?>

            <a href="<?= htmlspecialchars($googleAuthUrl) ?>" class="google-btn" <?= !$isConfigured ? 'onclick="showToast(\'Please configure Supabase credentials first.\', \'error\'); return false;"' : '' ?>>
                <svg viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                Continue with Google
            </a>
            <p class="footer-text">No email/password needed. Just your Google account.<br><a href="<?= BASE_PATH ?>">← Back to homepage</a></p>
        </div>
    </div>
</body>
</html>
