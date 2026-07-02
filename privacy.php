<?php require_once __DIR__ . '/config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy — <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #f8f9fa; --card: #ffffff; --accent: #4361ee; --text: #1a1a2e; --text-sec: #6c757d; --border: #e9ecef; --font: 'DM Sans', sans-serif; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: var(--bg); color: var(--text); font-family: var(--font); line-height: 1.8; }
        .nav { background: rgba(255,255,255,0.9); backdrop-filter: blur(20px); border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; }
        .nav-inner { max-width: 800px; margin: 0 auto; padding: 16px 32px; display: flex; align-items: center; justify-content: space-between; }
        .nav-brand { font-size: 20px; font-weight: 800; color: var(--text); } .nav-brand span { color: var(--accent); }
        .content { max-width: 800px; margin: 0 auto; padding: 60px 32px 80px; }
        h1 { font-size: 32px; font-weight: 800; margin-bottom: 8px; }
        .updated { font-size: 13px; color: var(--text-sec); margin-bottom: 40px; }
        h2 { font-size: 18px; font-weight: 700; margin-top: 36px; margin-bottom: 12px; }
        p, li { font-size: 15px; color: var(--text-sec); margin-bottom: 12px; }
        ul { padding-left: 20px; }
        li { margin-bottom: 8px; }
        strong { color: var(--text); }
        @media (max-width: 600px) {
            .nav-inner, .content { padding-left: 16px; padding-right: 16px; }
            .content { padding-top: 32px; padding-bottom: 48px; }
            h1 { font-size: 24px; }
            h2 { font-size: 16px; }
        }
    </style>
</head>
<body>
<nav class="nav"><div class="nav-inner"><a href="<?= BASE_PATH ?>" class="nav-brand"><img src="<?= BASE_PATH ?>assets/logo.png" alt="" style="height: 22px; vertical-align: middle; margin-right: 6px;"><span>DDCET</span> Prep</a><a href="<?= BASE_PATH ?>" style="font-size:14px;color:var(--text-sec);">← Back to Home</a></div></nav>
<div class="content">
    <h1>Privacy Policy</h1>
    <p class="updated">Effective Date: 1 June 2026 | Last Updated: <?= date('d F Y') ?></p>

    <p>DDCET Prep ("we," "our," or "us") operates the website located at <strong>ddcetprep.com</strong> (the "Platform"). This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our Platform.</p>

    <h2>1. Information We Collect</h2>
    <p><strong>a) Information from Google Sign-In:</strong></p>
    <ul>
        <li>Full name</li>
        <li>Email address</li>
        <li>Profile picture URL</li>
        <li>Google account unique identifier</li>
    </ul>
    <p><strong>b) Information you provide:</strong></p>
    <ul>
        <li>College name and city (during onboarding)</li>
        <li>Referral codes entered</li>
        <li>Doubt/question submissions in the Doubt Box</li>
    </ul>
    <p><strong>c) Automatically collected data:</strong></p>
    <ul>
        <li>Test attempt data: answers selected, time spent per question, scores, tab switch counts</li>
        <li>Activity data: login dates, streak information, XP earned</li>
        <li>Device information: browser type, screen resolution (via standard HTTP headers)</li>
        <li>IP address (for security and fraud prevention only)</li>
    </ul>
    <p><strong>d) Payment data:</strong></p>
    <ul>
        <li>We do NOT store credit/debit card numbers or UPI PINs</li>
        <li>Payment processing is handled entirely by Razorpay</li>
        <li>We store: Razorpay order ID, payment ID, plan purchased, amount, and transaction status</li>
    </ul>

    <h2>2. How We Use Your Information</h2>
    <ul>
        <li><strong>Platform functionality:</strong> Display your dashboard, scores, leaderboard rank, and progress analytics</li>
        <li><strong>Personalization:</strong> Show college-specific leaderboards, recommend tests based on weak areas</li>
        <li><strong>Communication:</strong> Send in-app notifications (test reminders, streak alerts, badge achievements)</li>
        <li><strong>Payment processing:</strong> Activate and manage your subscription plan</li>
        <li><strong>Platform improvement:</strong> Analyze aggregate usage patterns to improve question quality and features</li>
        <li><strong>Security:</strong> Detect multi-accounting, bot usage, and exam cheating through tab-switch monitoring</li>
    </ul>

    <h2>3. Data Storage & Security</h2>
    <ul>
        <li>All data is stored on <strong>Supabase</strong> (PostgreSQL database hosted on AWS infrastructure)</li>
        <li>Data transmission uses <strong>HTTPS/TLS encryption</strong></li>
        <li>Database access is protected by Row Level Security (RLS) policies</li>
        <li>API keys are stored server-side and never exposed to the browser</li>
        <li>We do NOT store your Google password — authentication is delegated to Google OAuth 2.0</li>
        <li>Session data is stored in server-side PHP sessions</li>
    </ul>

    <h2>4. Data Sharing & Third Parties</h2>
    <p>We share data ONLY with the following services, strictly for functionality:</p>
    <ul>
        <li><strong>Google OAuth:</strong> For authentication (receives nothing beyond standard OAuth flow)</li>
        <li><strong>Razorpay:</strong> For payment processing (receives your name, email, and payment amount)</li>
        <li><strong>Supabase:</strong> For database hosting (data processor, not data controller)</li>
    </ul>
    <p><strong>We do NOT:</strong></p>
    <ul>
        <li>Sell personal data to any third party</li>
        <li>Share data with advertisers or ad networks</li>
        <li>Use data for training AI models</li>
        <li>Share individual test scores with colleges or employers</li>
        <li>Display your email publicly anywhere on the platform</li>
    </ul>

    <h2>5. Public vs Private Data</h2>
    <p><strong>Visible to other registered users:</strong></p>
    <ul>
        <li>Name, profile picture, college, level, XP, streak (on leaderboards)</li>
        <li>Badge achievements</li>
    </ul>
    <p><strong>Private (visible only to you):</strong></p>
    <ul>
        <li>Email address</li>
        <li>Individual test answers and question-level analytics</li>
        <li>Payment history and subscription details</li>
        <li>Bookmarked questions</li>
    </ul>

    <h2>6. Cookies & Local Storage</h2>
    <ul>
        <li><strong>Session cookie (PHPSESSID):</strong> Maintains your login state. Expires when browser closes or after inactivity.</li>
        <li>We do NOT use advertising cookies, tracking pixels, or analytics services like Google Analytics</li>
        <li>No data is stored in browser localStorage or IndexedDB</li>
    </ul>

    <h2>7. Data Retention</h2>
    <ul>
        <li>Account data: Retained until you request deletion</li>
        <li>Test attempt data: Retained permanently for progress tracking (unless account deleted)</li>
        <li>Payment records: Retained for 7 years (legal/tax requirement)</li>
        <li>Deleted accounts: Data removed within 30 days of deletion request</li>
    </ul>

    <h2>8. Your Rights</h2>
    <ul>
        <li><strong>Access:</strong> View all your data via Profile, Analytics, and test history pages</li>
        <li><strong>Correction:</strong> Update your name/college via Profile settings</li>
        <li><strong>Deletion:</strong> Request complete account deletion by emailing us</li>
        <li><strong>Export:</strong> Request a copy of your data in JSON format</li>
        <li><strong>Withdraw consent:</strong> Logout and stop using the platform at any time</li>
    </ul>

    <h2>9. Children's Privacy</h2>
    <p>DDCET Prep is designed for diploma students (typically 18+ years). We do not knowingly collect data from children under 16. If we discover such data has been collected, we will delete it immediately.</p>

    <h2>10. International Data Transfers</h2>
    <p>Your data is stored on servers located in the Asia-Pacific region (AWS ap-south-1, Mumbai). Data may occasionally be processed in other regions by Supabase infrastructure for backup purposes.</p>

    <h2>11. Changes to This Policy</h2>
    <p>We may update this Privacy Policy from time to time. Changes will be reflected by the "Last Updated" date. Continued use of the Platform after changes constitutes acceptance.</p>

    <h2>12. Contact Us</h2>
    <p>For any privacy-related concerns or data requests:</p>
    <ul>
        <li><strong>Email:</strong> <a href="mailto:support@ddcetprep.com">support@ddcetprep.com</a></li>
        <li><strong>Response time:</strong> Within 48 hours for privacy requests</li>
    </ul>
</div>
</body>
</html>
