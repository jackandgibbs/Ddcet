<?php require_once __DIR__ . '/config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms & Conditions — <?= APP_NAME ?></title>
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
        h3 { font-size: 15px; font-weight: 600; margin-top: 20px; margin-bottom: 8px; color: var(--text); }
        p, li { font-size: 15px; color: var(--text-sec); margin-bottom: 12px; }
        ul { padding-left: 20px; }
        li { margin-bottom: 8px; }
        strong { color: var(--text); }
        .highlight-box { background: rgba(67,97,238,0.05); border: 1px solid rgba(67,97,238,0.15); border-radius: 8px; padding: 16px; margin: 16px 0; font-size: 14px; }
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
    <h1>Terms & Conditions</h1>
    <p class="updated">Effective Date: 1 June 2026 | Last Updated: <?= date('d F Y') ?></p>

    <p>These Terms and Conditions ("Terms") govern your use of the DDCET Prep platform ("Platform") operated by DDCET Prep ("we," "our," or "us"). By creating an account or using the Platform, you agree to these Terms in full.</p>

    <h2>1. Eligibility</h2>
    <ul>
        <li>You must be at least 16 years of age to use this Platform</li>
        <li>You must be a student preparing for the Diploma-to-Degree Common Entrance Test (DDCET) or a similar entrance exam in Gujarat, India</li>
        <li>You must have a valid Google account for authentication</li>
        <li>By signing up, you confirm that the college information you provide is accurate</li>
    </ul>

    <h2>2. Account Registration & Security</h2>
    <ul>
        <li>One account per person — creating multiple accounts is prohibited</li>
        <li>You are solely responsible for all activity under your account</li>
        <li>Do not share your Google login credentials with anyone</li>
        <li>If you suspect unauthorized access, sign out immediately and contact us</li>
        <li>We reserve the right to suspend or terminate accounts that violate these Terms</li>
    </ul>

    <h2>3. Subscription Plans & Pricing</h2>
    <h3>3.1 Free Tier</h3>
    <ul>
        <li>Access to daily challenge (1 test per day)</li>
        <li>Basic profile and leaderboard visibility</li>
        <li>Limited analytics</li>
    </ul>
    <h3>3.2 Basic Plan (₹149/year, valid until 30 June)</h3>
    <ul>
        <li>Full mock tests, rapid fire, subject-wise tests</li>
        <li>Weekly challenges</li>
        <li>Community read access</li>
        <li>Basic analytics</li>
    </ul>
    <h3>3.3 Pro Plan (₹299/year, valid until 30 June)</h3>
    <ul>
        <li>Everything in Basic</li>
        <li>Previous year papers</li>
        <li>Revision mode (re-attempt wrong questions)</li>
        <li>Challenge a friend (head-to-head)</li>
        <li>Full analytics with PDF reports</li>
        <li>Priority support</li>
    </ul>

    <div class="highlight-box">
        <strong>Note:</strong> Prices are in Indian Rupees (INR). We reserve the right to modify pricing with at least 7 days advance notice. Existing subscriptions continue at the original price until expiry.
    </div>

    <h2>4. Payments & Refunds</h2>
    <ul>
        <li>All payments are processed securely through <strong>Razorpay</strong></li>
        <li>Accepted methods: UPI, Credit/Debit Cards, Net Banking, Wallets</li>
        <li>Subscriptions are annual and valid until 30 June (aligned to the DDCET exam cycle); buying mid-cycle gives access only until the coming 30 June</li>
        <li>Subscriptions do NOT auto-renew — you must manually subscribe again after expiry</li>
        <li><strong>Refund Policy:</strong> Subscriptions are non-refundable once activated. If you face technical issues preventing access, contact us within 24 hours for resolution or credit</li>
        <li>Failed payments that are charged will be refunded to source within 5-7 business days by Razorpay</li>
    </ul>

    <h2>5. Test Rules & Fair Play</h2>
    <h3>5.1 During Tests</h3>
    <ul>
        <li>Tab switches are monitored and recorded</li>
        <li>Excessive tab switching (10+ times) may flag your attempt as suspicious</li>
        <li>Tests are timed — once started, the timer cannot be paused</li>
        <li>Answers are auto-saved periodically</li>
        <li>If your browser crashes, you can resume the attempt within the time limit</li>
    </ul>
    <h3>5.2 Prohibited Conduct</h3>
    <ul>
        <li>Using automated tools, bots, or scripts to take tests</li>
        <li>Sharing test questions or answers with others (screenshots, text, etc.)</li>
        <li>Using a secondary device or person to look up answers during tests</li>
        <li>Creating fake accounts to manipulate leaderboard rankings</li>
        <li>Exploiting bugs to gain unfair XP or scores (must report bugs instead)</li>
    </ul>
    <h3>5.3 Consequences</h3>
    <ul>
        <li>First offense: Warning notification</li>
        <li>Second offense: Test attempt invalidated, XP deducted</li>
        <li>Third offense: Account permanently banned without refund</li>
    </ul>

    <h2>6. Content & Intellectual Property</h2>
    <ul>
        <li>All questions, explanations, tests, and study materials are owned by DDCET Prep</li>
        <li>The free resource links (YouTube, websites) are curated but owned by their respective creators</li>
        <li>You may use content only for personal DDCET preparation</li>
        <li>You may NOT: copy, screenshot, redistribute, sell, or publish our questions anywhere</li>
        <li>Community posts are owned by their authors but licensed to us for display on the Platform</li>
    </ul>

    <h2>7. Community Guidelines</h2>
    <ul>
        <li>Be respectful to other students</li>
        <li>No spam, self-promotion, or irrelevant content</li>
        <li>No sharing of test answers or copyrighted material</li>
        <li>No hate speech, harassment, or discrimination</li>
        <li>No impersonation of other students, admins, or institutions</li>
        <li>Violation may result in content removal and account suspension</li>
    </ul>

    <h2>8. Referral Program</h2>
    <ul>
        <li>Each user receives a unique referral code</li>
        <li>When someone signs up using your code, they receive a discount on their first Pro subscription</li>
        <li>Self-referrals (using your own code on a second account) are prohibited</li>
        <li>We reserve the right to void rewards if referral abuse is detected</li>
    </ul>

    <h2>9. Leaderboard & Rankings</h2>
    <ul>
        <li>XP is the primary ranking metric</li>
        <li>XP is earned through: completing tests, daily challenges, streaks, and badges</li>
        <li>We may reset leaderboards periodically (weekly leaderboard resets every Monday)</li>
        <li>Manipulated rankings will be corrected and accounts may be penalized</li>
    </ul>

    <h2>10. Service Availability</h2>
    <ul>
        <li>We aim for 99.5% uptime but do not guarantee uninterrupted service</li>
        <li>Scheduled maintenance will be communicated via notifications</li>
        <li>We are not liable for data loss due to third-party outages (Supabase, Google, Razorpay)</li>
        <li>In case of extended downtime (24+ hours), affected paid users may receive subscription extensions</li>
    </ul>

    <h2>11. Account Termination</h2>
    <h3>11.1 By You</h3>
    <ul>
        <li>You may stop using the Platform at any time by simply not logging in</li>
        <li>To permanently delete your account and data, email us at support@ddcetprep.com</li>
    </ul>
    <h3>11.2 By Us</h3>
    <ul>
        <li>We may suspend or terminate accounts for Terms violations</li>
        <li>Banned users lose access to all features including active paid subscriptions</li>
        <li>No refund is provided for termination due to Terms violation</li>
    </ul>

    <h2>12. Disclaimers</h2>
    <ul>
        <li>DDCET Prep is a preparation tool — we do NOT guarantee any specific exam result or rank</li>
        <li>We are NOT affiliated with Gujarat Technological University (GTU), ACPC, or any government body</li>
        <li>Question patterns are based on publicly available past exam information and may not reflect future exam changes</li>
        <li>The Platform is provided "as is" without warranty of any kind</li>
    </ul>

    <h2>13. Limitation of Liability</h2>
    <p>To the maximum extent permitted by law, DDCET Prep shall not be liable for:</p>
    <ul>
        <li>Any indirect, incidental, or consequential damages</li>
        <li>Loss of data, scores, or progress due to technical issues</li>
        <li>Exam results or career outcomes</li>
        <li>Actions of other users on the platform</li>
    </ul>
    <p>Our total liability shall not exceed the amount paid by you to us in the preceding 3 months.</p>

    <h2>14. Governing Law</h2>
    <p>These Terms are governed by the laws of India. Any disputes shall be subject to the exclusive jurisdiction of courts in Dahod, Gujarat, India.</p>

    <h2>15. Changes to Terms</h2>
    <p>We may modify these Terms at any time. Material changes will be notified via in-app notification. Continued use after changes constitutes acceptance of the updated Terms.</p>

    <h2>16. Severability</h2>
    <p>If any provision of these Terms is found unenforceable, the remaining provisions shall remain in full effect.</p>

    <h2>17. Contact</h2>
    <p>For questions about these Terms:</p>
    <ul>
        <li><strong>Email:</strong> <a href="mailto:support@ddcetprep.com">support@ddcetprep.com</a></li>
        <li><strong>Platform:</strong> DDCET Prep</li>
        <li><strong>Location:</strong> Dahod, Gujarat, India</li>
    </ul>

    <div class="highlight-box">
        By clicking "Sign in with Google" and using DDCET Prep, you acknowledge that you have read, understood, and agree to these Terms & Conditions and our <a href="privacy.php">Privacy Policy</a>.
    </div>
</div>
</body>
</html>
