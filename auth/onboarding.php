<?php
require_once __DIR__ . '/../config.php';
$user = requireAuth();

if ($user['onboarded']) {
    header('Location: ' . BASE_PATH . 'dashboard.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && honeypotTripped()) {
    // Bot filled the hidden field — silently bounce without writing anything.
    header('Location: ' . BASE_PATH . 'auth/onboarding.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !rateLimit('onboarding_submit', 10, 600)) {
    $error = 'Too many attempts. Please wait a few minutes and try again.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $collegeId = $_POST['college_id'] ?? '';
    $referralCode = trim($_POST['referral_code'] ?? '');

    // Handle "Other" college - create new
    if ($collegeId === 'other' && !empty($_POST['other_college_name'])) {
        $newCollege = supabaseRest('colleges', 'POST', ['name' => trim($_POST['other_college_name']), 'city' => '']);
        $collegeId = $newCollege[0]['id'] ?? 0;
    }
    $collegeId = (int)$collegeId;

    if ($collegeId > 0) {
        supabaseRest('students?id=eq.' . $user['id'], 'PATCH', [
            'college_id' => $collegeId,
            'onboarded' => true,
        ]);

        // Save extra fields in student record
        $extraData = [];
        if (!empty($_POST['semester'])) $extraData['semester'] = (int)$_POST['semester'];
        if (!empty($_POST['department'])) $extraData['department'] = trim($_POST['department']);
        if (!empty($_POST['mobile'])) $extraData['mobile'] = trim($_POST['mobile']);
        if ($extraData) {
            supabaseRest('students?id=eq.' . $user['id'], 'PATCH', $extraData);
        }

        if ($referralCode && $referralCode !== ($user['referral_code'] ?? '')) {
            $referrer = supabaseRest('students?referral_code=eq.' . urlencode($referralCode) . '&id=neq.' . $user['id'] . '&select=id&limit=1');
            if (!empty($referrer[0])) {
                supabaseRest('students?id=eq.' . $user['id'], 'PATCH', ['referred_by' => $referrer[0]['id']]);
                supabaseRest('referrals', 'POST', [
                    'referrer_id' => $referrer[0]['id'],
                    'referred_id' => $user['id'],
                    'status' => 'completed',
                ]);
            }
        }

        $_SESSION['user']['college_id'] = $collegeId;
        $_SESSION['user']['onboarded'] = true;
        header('Location: ' . BASE_PATH . 'dashboard.php');
        exit;
    }
    $error = 'Please select your college.';
}

// Fetch colleges
$colleges = supabaseRest('colleges?select=id,name,city&order=name') ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome — <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #f8f9fa; --card: #ffffff; --accent: #4361ee; --green: #10b981; --text: #1a1a2e; --text-sec: #6c757d; --text-muted: #adb5bd; --border: #e9ecef; --radius: 12px; --font: 'DM Sans', sans-serif; --mono: 'DM Mono', monospace; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: var(--bg); color: var(--text); font-family: var(--font); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 32px; }
        .onboard-card { background: var(--card); border-radius: 16px; padding: 48px; max-width: 480px; width: 100%; border: 1px solid var(--border); box-shadow: 0 16px 48px rgba(0,0,0,0.06); }
        .avatar { width: 56px; height: 56px; border-radius: 50%; margin-bottom: 16px; border: 3px solid var(--accent); }
        .onboard-card h1 { font-size: 24px; font-weight: 800; margin-bottom: 4px; }
        .onboard-card h1 span { color: var(--accent); }
        .onboard-card .subtitle { color: var(--text-sec); font-size: 14px; margin-bottom: 28px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600; color: var(--text-sec); }
        select, input[type="text"] { width: 100%; padding: 12px 14px; background: var(--bg); border: 1px solid var(--border); border-radius: 10px; color: var(--text); font-size: 14px; font-family: var(--font); transition: border-color 0.2s; }
        select:focus, input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(67,97,238,0.1); }
        .btn { width: 100%; padding: 14px; background: var(--accent); color: #fff; border: none; border-radius: 10px; font-size: 15px; font-weight: 700; cursor: pointer; font-family: var(--font); transition: all 0.2s; margin-top: 8px; }
        .btn:hover { background: #3a56d4; transform: translateY(-1px); box-shadow: 0 8px 20px rgba(67,97,238,0.3); }
        .error { background: #fef2f2; color: #dc2626; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; border: 1px solid #fecaca; }
        .steps { display: flex; gap: 8px; margin-bottom: 24px; }
        .step { flex: 1; height: 4px; border-radius: 2px; background: var(--border); }
        .step.active { background: var(--accent); }
        .step.done { background: var(--green); }
        @media (max-width: 600px) {
            body { padding: 16px; }
            .onboard-card { padding: 28px 20px; }
            .field-row { grid-template-columns: 1fr !important; }
        }
    </style>
</head>
<body>
    <div class="onboard-card">
        <div class="steps">
            <div class="step done"></div>
            <div class="step active"></div>
            <div class="step"></div>
        </div>

        <?php if (!empty($user['avatar_url'])): ?>
            <img src="<?= htmlspecialchars($user['avatar_url']) ?>" class="avatar" alt="">
        <?php endif; ?>
        <h1>Welcome, <span><?= htmlspecialchars($user['name'] ?? 'Student') ?></span>!</h1>
        <p class="subtitle">One quick step — tell us your college so we can personalize your experience.</p>

        <?php if (!empty($error)): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= honeypotField() ?>
            <div class="form-group">
                <label>Which college are you studying in?</label>
                <select name="college_id" id="collegeSelect" required onchange="document.getElementById('otherCollege').style.display = this.value === 'other' ? 'block' : 'none'">
                    <option value="">Select your college...</option>
                    <?php foreach ($colleges as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> — <?= htmlspecialchars($c['city'] ?? '') ?></option>
                    <?php endforeach; ?>
                    <option value="other">Other (not listed)</option>
                </select>
            </div>
            <div class="form-group" id="otherCollege" style="display: none;">
                <label>College Name</label>
                <input type="text" name="other_college_name" class="form-control" placeholder="Enter your college name">
            </div>
            <div class="field-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label>Semester</label>
                    <select name="semester">
                        <option value="">Select...</option>
                        <option value="1">1st Sem</option>
                        <option value="2">2nd Sem</option>
                        <option value="3">3rd Sem</option>
                        <option value="4">4th Sem</option>
                        <option value="5">5th Sem</option>
                        <option value="6">6th Sem</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <select name="department">
                        <option value="">Select...</option>
                        <option value="Computer">Computer</option>
                        <option value="Mechanical">Mechanical</option>
                        <option value="Civil">Civil</option>
                        <option value="Electrical">Electrical</option>
                        <option value="EC">Electronics</option>
                        <option value="Chemical">Chemical</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Mobile Number (optional)</label>
                <input type="tel" name="mobile" placeholder="9876543210" pattern="[0-9]{10}">
            </div>
            <div class="form-group">
                <label>Referral Code (optional)</label>
                <input type="text" name="referral_code" placeholder="Enter friend's referral code">
            </div>
            <button type="submit" class="btn">Continue to Dashboard →</button>
        </form>
    </div>
</body>
</html>
