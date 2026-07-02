<?php
require_once __DIR__ . '/config.php';

$sent = false;
$error = '';

// Handle "Get a Free Demo" submission. Public form (no auth) — protected by a
// CSRF token + a honeypot field, and persisted to the demo_leads table.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValid($_POST['csrf'] ?? null)) {
        $error = 'Your session expired. Please try submitting again.';
    } elseif (!empty($_POST['website'])) {
        // Honeypot filled → silently treat as success (bot).
        $sent = true;
    } else {
        $organization = trim($_POST['organization'] ?? '');
        $contactName  = trim($_POST['contact_name'] ?? '');
        $email        = trim($_POST['email'] ?? '');
        $phone        = trim($_POST['phone'] ?? '');
        $role         = trim($_POST['role'] ?? '');
        $city         = trim($_POST['city'] ?? '');
        $studentCount = trim($_POST['student_count'] ?? '');
        $message      = trim($_POST['message'] ?? '');

        if ($organization === '' || $contactName === '' || $email === '') {
            $error = 'Please fill in your institution name, contact name and email.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $res = supabaseRest('demo_leads', 'POST', [
                'organization'  => mb_substr($organization, 0, 200),
                'contact_name'  => mb_substr($contactName, 0, 120),
                'email'         => mb_substr($email, 0, 160),
                'phone'         => mb_substr($phone, 0, 20) ?: null,
                'role'          => mb_substr($role, 0, 80) ?: null,
                'city'          => mb_substr($city, 0, 120) ?: null,
                'student_count' => mb_substr($studentCount, 0, 40) ?: null,
                'message'       => $message ?: null,
                'status'        => 'new',
            ]);
            if ($res !== null) {
                $sent = true;
            } else {
                $error = 'Something went wrong saving your request. Please email us at support@ddcetprep.com.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DDCET Prep for Colleges &amp; Coaching Institutes — Get a Free Demo</title>
    <meta name="description" content="Bring DDCET Prep to your diploma students. Branch-specific mock tests, faculty dashboards, batch analytics and rank prediction for colleges and coaching institutes across Gujarat. Get a free demo.">
    <meta name="theme-color" content="#4361ee">
    <link rel="icon" href="assets/favicon.ico" sizes="any">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #f8f9fa; --card: #ffffff; --accent: #4361ee; --accent-hover:#3a56d4; --purple:#8b5cf6; --green:#10b981; --text: #1a1a2e; --text-sec: #6c757d; --text-muted:#adb5bd; --border: #e9ecef; --radius: 14px; --font: 'DM Sans', sans-serif; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: var(--bg); color: var(--text); font-family: var(--font); line-height: 1.65; }
        a { color: var(--accent); text-decoration: none; }
        /* Nav */
        .nav { background: rgba(255,255,255,0.9); backdrop-filter: blur(20px); border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; }
        .nav-inner { max-width: 1100px; margin: 0 auto; padding: 16px 32px; display: flex; align-items: center; justify-content: space-between; }
        .nav-brand { font-size: 20px; font-weight: 800; color: var(--text); } .nav-brand span { color: var(--accent); }
        .nav-links { display: flex; gap: 24px; align-items: center; }
        .nav-links a { color: var(--text-sec); font-size: 14px; font-weight: 500; }
        .nav-links a:hover { color: var(--accent); }
        .btn-nav { background: var(--accent); color: #fff !important; padding: 9px 18px; border-radius: 8px; font-weight: 600; }
        .hamburger { display: none; background: none; border: none; cursor: pointer; padding: 8px; }
        /* Hero */
        .hero { background: radial-gradient(ellipse at 30% 20%, #312e81 0%, transparent 55%), radial-gradient(ellipse at 75% 80%, #6d28d9 0%, transparent 50%), linear-gradient(135deg, #0b1026 0%, #1a1140 100%); color: #fff; padding: 80px 32px 90px; text-align: center; position: relative; overflow: hidden; }
        .hero-inner { max-width: 820px; margin: 0 auto; position: relative; z-index: 1; }
        .eyebrow { display:inline-flex; align-items:center; gap:7px; background: rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.18); color:#fff; font-size:12px; font-weight:700; padding:7px 16px; border-radius:20px; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:20px; }
        .hero h1 { font-size: 42px; font-weight: 900; line-height: 1.15; letter-spacing: -1px; margin-bottom: 16px; }
        .hero h1 span { background: linear-gradient(135deg, #a5b4fc, #c4b5fd); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .hero p { font-size: 18px; color: rgba(255,255,255,0.8); max-width: 640px; margin: 0 auto 28px; }
        .hero-cta { display:flex; gap:14px; justify-content:center; flex-wrap:wrap; }
        .btn-primary { background:#fff; color:#1a1140; padding:15px 32px; border-radius:10px; font-weight:700; font-size:15px; }
        .btn-primary:hover { transform: translateY(-2px); }
        .btn-outline { background: transparent; color:#fff; border:1px solid rgba(255,255,255,0.35); padding:15px 30px; border-radius:10px; font-weight:700; font-size:15px; }
        /* Sections */
        .section { max-width: 1100px; margin: 0 auto; padding: 72px 32px; }
        .section-title { font-size: 32px; font-weight: 800; text-align: center; letter-spacing: -0.5px; margin-bottom: 10px; }
        .section-sub { text-align:center; color: var(--text-sec); font-size: 16px; max-width: 600px; margin: 0 auto 44px; }
        .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .feat { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 28px; }
        .feat .ic { width: 44px; height: 44px; border-radius: 11px; background: rgba(67,97,238,0.1); color: var(--accent); display:flex; align-items:center; justify-content:center; margin-bottom: 16px; }
        .feat h3 { font-size: 17px; font-weight: 700; margin-bottom: 8px; }
        .feat p { font-size: 14px; color: var(--text-sec); }
        /* Stats band */
        .band { background: var(--card); border-top:1px solid var(--border); border-bottom:1px solid var(--border); }
        .band-inner { max-width: 1100px; margin: 0 auto; padding: 40px 32px; display:grid; grid-template-columns: repeat(4,1fr); gap: 24px; text-align:center; }
        .band .num { font-size: 30px; font-weight: 900; color: var(--accent); }
        .band .lbl { font-size: 13px; color: var(--text-sec); }
        /* Demo form */
        .demo { background: linear-gradient(135deg, #0b1026 0%, #1a1140 100%); }
        .demo-inner { max-width: 760px; margin: 0 auto; padding: 72px 32px; }
        .demo h2 { color:#fff; text-align:center; font-size: 30px; font-weight: 800; margin-bottom: 8px; }
        .demo .sub { text-align:center; color: rgba(255,255,255,0.7); margin-bottom: 32px; }
        .form-card { background: var(--card); border-radius: 16px; padding: 32px; box-shadow: 0 24px 64px rgba(0,0,0,0.25); }
        .form-row { display:grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-group { margin-bottom: 16px; }
        label { display:block; font-size: 13px; font-weight: 600; color: var(--text-sec); margin-bottom: 6px; }
        input, select, textarea { width: 100%; padding: 12px 14px; background: var(--bg); border: 1px solid var(--border); border-radius: 10px; font-size: 14px; font-family: var(--font); color: var(--text); }
        input:focus, select:focus, textarea:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(67,97,238,0.1); }
        .btn-submit { width: 100%; padding: 15px; background: var(--accent); color: #fff; border: none; border-radius: 10px; font-size: 15px; font-weight: 700; cursor: pointer; font-family: var(--font); margin-top: 4px; }
        .btn-submit:hover { background: var(--accent-hover); }
        .hp { position: absolute; left: -9999px; }
        .alert { padding: 12px 16px; border-radius: 10px; font-size: 14px; margin-bottom: 20px; }
        .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .success-card { background: var(--card); border-radius: 16px; padding: 48px 32px; text-align: center; box-shadow: 0 24px 64px rgba(0,0,0,0.25); }
        .success-card .check { width: 64px; height: 64px; border-radius: 50%; background: rgba(16,185,129,0.12); color: var(--green); display:flex; align-items:center; justify-content:center; margin: 0 auto 18px; }
        .success-card h3 { font-size: 22px; font-weight: 800; margin-bottom: 8px; }
        .success-card p { color: var(--text-sec); font-size: 15px; }
        /* Footer */
        .footer { background: #1a1a2e; padding: 32px; text-align: center; color: rgba(255,255,255,0.5); font-size: 13px; }
        .footer a { color: rgba(255,255,255,0.7); }
        @media (max-width: 768px) {
            .nav-links { display: none; position: absolute; top: 100%; left: 0; right: 0; background: var(--card); border-bottom: 1px solid var(--border); flex-direction: column; padding: 16px 24px; gap: 16px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); }
            .nav-links.open { display: flex; }
            .hamburger { display: block; }
            .hero h1 { font-size: 32px; }
            .grid { grid-template-columns: 1fr; }
            .band-inner { grid-template-columns: repeat(2,1fr); }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<nav class="nav">
    <div class="nav-inner">
        <a href="<?= BASE_PATH ?>" class="nav-brand"><img src="assets/logo.png" alt="" style="height: 22px; vertical-align: middle; margin-right: 6px;"><span>DDCET</span> Prep</a>
        <button class="hamburger" onclick="document.querySelector('.nav-links').classList.toggle('open')" aria-label="Menu">
            <svg width="24" height="24" fill="none" stroke="var(--text)" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
        </button>
        <div class="nav-links">
            <a href="<?= BASE_PATH ?>">Home</a>
            <a href="syllabus.php">Syllabus</a>
            <a href="about.php">About</a>
            <a href="#features">Why us</a>
            <a href="#demo" class="btn-nav">Get a Free Demo</a>
        </div>
    </div>
</nav>

<!-- Hero -->
<section class="hero">
    <div class="hero-inner">
        <span class="eyebrow">For Colleges &amp; Coaching Institutes</span>
        <h1>Give every diploma student a <span>branch-specific edge</span> in DDCET</h1>
        <p>Bring Gujarat's dedicated DDCET platform to your institution — branch-wise mock tests, faculty dashboards, batch analytics and rank prediction, all under your guidance.</p>
        <div class="hero-cta">
            <a href="#demo" class="btn-primary">Get a Free Demo →</a>
            <a href="#features" class="btn-outline">See what's included</a>
        </div>
    </div>
</section>

<!-- Stats band -->
<div class="band">
    <div class="band-inner">
        <div><div class="num">100Q</div><div class="lbl">Real exam-pattern mocks</div></div>
        <div><div class="num">6</div><div class="lbl">Diploma branches covered</div></div>
        <div><div class="num">2000+</div><div class="lbl">Curated MCQ question bank</div></div>
        <div><div class="num">AIR</div><div class="lbl">Live all-India ranking</div></div>
    </div>
</div>

<!-- Features -->
<section class="section" id="features">
    <h2 class="section-title">Everything your batch needs, managed in one place</h2>
    <p class="section-sub">Run your DDCET coaching with the same tools the top platforms use — set up in days, not months.</p>
    <div class="grid">
        <div class="feat">
            <div class="ic"><svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 14l2 2 4-4"/></svg></div>
            <h3>Branch-specific mock tests</h3>
            <p>Full DDCET papers and subject drills, with study plans tuned per engineering branch — Computer, Mechanical, Civil, Electrical, EC and Chemical.</p>
        </div>
        <div class="feat">
            <div class="ic"><svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg></div>
            <h3>Faculty dashboards</h3>
            <p>Give your teachers a view of every student's attempts, accuracy and weak subjects — so they coach with data, not guesswork.</p>
        </div>
        <div class="feat">
            <div class="ic"><svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg></div>
            <h3>Batch analytics</h3>
            <p>Track your whole batch's readiness over time, compare sections, and spot students who need intervention before the exam.</p>
        </div>
        <div class="feat">
            <div class="ic"><svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg></div>
            <h3>Rank prediction &amp; cutoffs</h3>
            <p>Students see a realistic All-India Rank and which colleges/branches their score can reach, using GTU/ACPC cutoff data.</p>
        </div>
        <div class="feat">
            <div class="ic"><svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5M2 12l10 5 10-5"/></svg></div>
            <h3>Your branding, your batch</h3>
            <p>Onboard your students under your institution so their college leaderboard and reports are grouped together.</p>
        </div>
        <div class="feat">
            <div class="ic"><svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg></div>
            <h3>Priority support</h3>
            <p>A dedicated point of contact for onboarding, question imports and any help your faculty needs during the season.</p>
        </div>
    </div>
</section>

<!-- Demo form -->
<section class="demo" id="demo">
    <div class="demo-inner">
        <?php if ($sent): ?>
            <div class="success-card">
                <div class="check"><svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg></div>
                <h3>Thanks — your demo request is in!</h3>
                <p>Our team will reach out within one business day to schedule your free walkthrough. For anything urgent, email <a href="mailto:support@ddcetprep.com">support@ddcetprep.com</a>.</p>
            </div>
        <?php else: ?>
            <h2>Get a Free Demo</h2>
            <p class="sub">Tell us about your institution and we'll set up a personalized walkthrough — no commitment.</p>
            <div class="form-card">
                <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <form method="POST" action="institutions.php#demo">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
                    <!-- honeypot: bots fill this, humans never see it -->
                    <div class="hp"><label>Website</label><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Institution name *</label>
                            <input type="text" name="organization" required value="<?= htmlspecialchars($_POST['organization'] ?? '') ?>" placeholder="e.g. Govt. Polytechnic, Ahmedabad">
                        </div>
                        <div class="form-group">
                            <label>Your name *</label>
                            <input type="text" name="contact_name" required value="<?= htmlspecialchars($_POST['contact_name'] ?? '') ?>" placeholder="Full name">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="you@college.edu">
                        </div>
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="tel" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" placeholder="9876543210">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Your role</label>
                            <select name="role">
                                <?php $roleVal = $_POST['role'] ?? '';
                                foreach (['', 'Principal / Director', 'HOD', 'Faculty / Lecturer', 'Placement / TPO', 'Coaching Owner', 'Other'] as $r): ?>
                                    <option value="<?= htmlspecialchars($r) ?>" <?= $roleVal === $r ? 'selected' : '' ?>><?= $r === '' ? 'Select...' : htmlspecialchars($r) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>City</label>
                            <input type="text" name="city" value="<?= htmlspecialchars($_POST['city'] ?? '') ?>" placeholder="e.g. Surat">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Approx. number of diploma students</label>
                        <select name="student_count">
                            <?php $scVal = $_POST['student_count'] ?? '';
                            foreach (['', 'Under 50', '50–150', '150–400', '400–1000', '1000+'] as $sc): ?>
                                <option value="<?= htmlspecialchars($sc) ?>" <?= $scVal === $sc ? 'selected' : '' ?>><?= $sc === '' ? 'Select...' : htmlspecialchars($sc) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Anything else? (optional)</label>
                        <textarea name="message" rows="3" placeholder="Tell us what you're looking for..."><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn-submit">Request My Free Demo →</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</section>

<footer class="footer">
    <p>© <?= date('Y') ?> DDCET Prep · Gujarat's dedicated DDCET preparation platform · <a href="<?= BASE_PATH ?>">Home</a> · <a href="privacy.php">Privacy</a> · <a href="terms.php">Terms</a> · <a href="refund.php">Refund</a></p>
</footer>

</body>
</html>
