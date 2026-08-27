<?php
require_once __DIR__ . '/../config.php';

// Already logged in as admin
if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: ' . BASE_PATH . 'admin/index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    
    if (!rateLimit('wb_admin_login', 5, 300)) {
        $error = 'Too many login attempts. Please wait 5 minutes.';
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

    // Credentials come from .env (WB_ADMIN_USERNAME / WB_ADMIN_PASSWORD_HASH).
    // Username compared in constant time; password verified against a bcrypt hash.
    $userOk = WB_ADMIN_USERNAME !== '' && hash_equals(WB_ADMIN_USERNAME, $username);
    $passOk = WB_ADMIN_PASSWORD_HASH !== '' && password_verify($password, WB_ADMIN_PASSWORD_HASH);

    if ($userOk && $passOk) {
        session_regenerate_id(true);            // prevent session fixation
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['user'] = [
            'id' => 0,
            'name' => 'Admin',
            'email' => 'admin@ddcetprep.com',
            'is_admin' => true,
            'avatar_url' => null,
            'xp' => 0,
            'level' => 'Admin',
            'streak' => 0,
            'referral_code' => '',
            'onboarded' => true,
        ];
        header('Location: ' . BASE_PATH . 'admin/index.php');
        exit;
    }
        $error = 'Invalid credentials';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #f8f9fa; --card: #ffffff; --accent: #4361ee; --text: #1a1a2e; --text-sec: #6c757d; --border: #e9ecef; --font: 'DM Sans', sans-serif; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: var(--bg); font-family: var(--font); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 40px; max-width: 380px; width: 100%; box-shadow: 0 16px 48px rgba(0,0,0,0.06); }
        h1 { font-size: 22px; font-weight: 800; margin-bottom: 4px; }
        h1 span { color: var(--accent); }
        .sub { color: var(--text-sec); font-size: 13px; margin-bottom: 24px; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 12px; font-weight: 600; color: var(--text-sec); margin-bottom: 6px; }
        input { width: 100%; padding: 12px 14px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; font-family: var(--font); }
        input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(67,97,238,0.1); }
        .btn { width: 100%; padding: 13px; background: var(--accent); color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; font-family: var(--font); margin-top: 8px; }
        .btn:hover { background: #3a56d4; }
        .error { background: #fef2f2; color: #dc2626; padding: 10px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; border: 1px solid #fecaca; text-align: center; }
    </style>
</head>
<body>
    <div class="card">
        <h1><span>DDCET</span> Admin</h1>
        <p class="sub">Enter credentials to access the admin panel.</p>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required autofocus>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn">Login →</button>
        </form>
    </div>
</body>
</html>
