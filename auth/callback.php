<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/supabase_auth.php';

// Supabase returns access_token and refresh_token in URL hash
// We'll handle it with JavaScript or check for token in query params
if (isset($_GET['access_token'])) {
    $accessToken = $_GET['access_token'];
    $refreshToken = $_GET['refresh_token'] ?? null;
} else {
    // Token is in URL hash, need to extract with JS
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Authenticating — <?= APP_NAME ?></title>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { background: #f8f9fa; font-family: 'DM Sans', sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
            .auth-card { text-align: center; padding: 48px; }
            .spinner { width: 48px; height: 48px; border: 4px solid #e9ecef; border-top-color: #4361ee; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 24px; }
            @keyframes spin { to { transform: rotate(360deg); } }
            h2 { font-size: 20px; font-weight: 700; color: #1a1a2e; margin-bottom: 8px; }
            p { font-size: 14px; color: #6c757d; }
        </style>
    </head>
    <body>
        <div class="auth-card">
            <div class="spinner"></div>
            <h2>Authenticating...</h2>
            <p>Setting up your account. Just a moment.</p>
        </div>
        <script>
            const hash = window.location.hash.substring(1);
            const params = new URLSearchParams(hash);
            const accessToken = params.get('access_token');
            const refreshToken = params.get('refresh_token');
            
            if (accessToken) {
                window.location.href = '<?= BASE_PATH ?>auth/callback.php?access_token=' + accessToken + 
                    (refreshToken ? '&refresh_token=' + refreshToken : '');
            } else {
                window.location.href = '<?= BASE_PATH ?>auth/login.php?error=no_token';
            }
        </script>
    </body>
    </html>
    <?php
    exit;
}

// Get user info from Supabase
$user = getSupabaseUser($accessToken);

if (!$user || empty($user['id'])) {
    header('Location: ' . BASE_PATH . 'auth/login.php?error=no_user');
    exit;
}

// Check if student exists via REST API
$checkStudent = supabaseRequest(
    '/rest/v1/students?google_id=eq.' . $user['id'] . '&select=*',
    'GET',
    null,
    SUPABASE_KEY
);

$student = !empty($checkStudent) ? $checkStudent[0] : null;

if (!$student) {
    // Create new student via REST API
    $referralCode = strtoupper(substr(md5($user['id'] . time()), 0, 8));
    $newStudent = supabaseRequest(
        '/rest/v1/students',
        'POST',
        [
            'google_id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['user_metadata']['full_name'] ?? $user['user_metadata']['name'] ?? 'User',
            'avatar_url' => $user['user_metadata']['avatar_url'] ?? $user['user_metadata']['picture'] ?? null,
            'referral_code' => $referralCode,
        ],
        SUPABASE_KEY
    );
    
    // Fetch the created student
    $checkStudent = supabaseRequest(
        '/rest/v1/students?google_id=eq.' . $user['id'] . '&select=*',
        'GET',
        null,
        SUPABASE_KEY
    );
    $student = $checkStudent[0] ?? null;

    // Welcome email (no-op if email isn't configured) — lead with the free mock.
    if ($student && !empty($student['email'])) {
        $firstName = trim(explode(' ', $student['name'] ?? '')[0] ?: 'there');
        $body = 'Welcome to ' . htmlspecialchars(MAIL_FROM_NAME) . '. Your account also includes <strong>one full DDCET mock test, free</strong> — '
              . '100 questions in the exact real exam pattern, with your score, percentile and weak areas at the end.'
              . '<br><br>Take it whenever you\'re ready — no payment required.';
        sendEmail(
            $student['email'], $student['name'] ?? '',
            'Welcome to ' . MAIL_FROM_NAME . ' — your free mock test is ready',
            emailTemplate('Welcome, ' . htmlspecialchars($firstName), $body, 'Take your free mock', APP_URL . BASE_PATH . 'dashboard.php')
        );
    }
}

if (!$student) {
    header('Location: ' . BASE_PATH . 'auth/login.php?error=db_error');
    exit;
}

// Check if banned
if (!empty($student['is_banned'])) {
    session_destroy();
    header('Location: ' . BASE_PATH . 'auth/login.php?error=banned');
    exit;
}

// Check if admin
$adminCheck = supabaseRequest(
    '/rest/v1/admins?email=eq.' . $student['email'] . '&select=id',
    'GET',
    null,
    SUPABASE_KEY
);
$student['is_admin'] = !empty($adminCheck);

// Check premium status
$subscriptionCheck = supabaseRequest(
    '/rest/v1/subscriptions?student_id=eq.' . $student['id'] . '&status=eq.active&expires_at=gt.' . urlencode(date('c')) . '&limit=1',
    'GET',
    null,
    SUPABASE_KEY
);
$hasPremium = !empty($subscriptionCheck);

// Set session
$_SESSION['user'] = $student;
$_SESSION['supabase_token'] = $accessToken;
$_SESSION['supabase_refresh_token'] = $refreshToken;
$_SESSION['show_login_success'] = true;
$_SESSION['has_premium'] = $hasPremium;
$_SESSION['show_subscription_popup'] = !$hasPremium;

// Redirect based on onboarding status
if (empty($student['onboarded'])) {
    header('Location: ' . BASE_PATH . 'auth/onboarding.php');
} elseif (!empty($_SESSION['post_login_redirect'])) {
    // Send the user back to the page they were trying to reach before login
    // (e.g. Contact Us). Only honour same-site relative paths.
    $target = $_SESSION['post_login_redirect'];
    unset($_SESSION['post_login_redirect']);
    if (is_string($target) && str_starts_with($target, '/') && !str_starts_with($target, '//')) {
        header('Location: ' . $target);
    } else {
        header('Location: ' . BASE_PATH . 'dashboard.php');
    }
} else {
    header('Location: ' . BASE_PATH . 'dashboard.php');
}
exit;
