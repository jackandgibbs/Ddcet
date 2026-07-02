<?php require_once __DIR__ . '/config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refund &amp; Cancellation Policy — <?= APP_NAME ?></title>
    <meta name="description" content="Refund and cancellation policy for DDCET Prep subscriptions, processed in accordance with Razorpay payment terms.">
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
        .callout { background: #fff8e1; border: 1px solid #ffe082; border-radius: 10px; padding: 16px 18px; margin: 24px 0; }
        .callout p { color: #7a5c00; margin: 0; }
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
    <h1>Refund &amp; Cancellation Policy</h1>
    <p class="updated">Effective Date: 1 June 2026 | Last Updated: <?= date('d F Y') ?></p>

    <p>This Refund &amp; Cancellation Policy applies to all paid subscriptions ("Plans") purchased on <strong>DDCET Prep</strong> (the "Platform"). All payments are processed securely through our payment partner, <strong>Razorpay</strong>. By purchasing a Plan, you agree to the terms below.</p>

    <div class="callout">
        <p><strong>In short:</strong> DDCET Prep sells access to digital content that is delivered instantly. Because of this, subscription fees are generally <strong>non-refundable</strong> once access has been granted — except in the specific cases described below.</p>
    </div>

    <h2>1. Free Trial Before You Pay</h2>
    <p>We strongly encourage every student to use the <strong>Free tier</strong> before purchasing. The Free tier includes the Daily Challenge, study resources, and core analytics so you can evaluate the Platform at no cost. Upgrading to a paid Plan is entirely optional, and we recommend it only after you are satisfied with the free experience.</p>

    <h2>2. Nature of the Service</h2>
    <ul>
        <li>Paid Plans unlock <strong>digital, instantly-accessible content</strong> (mock tests, previous-year papers, premium analytics, etc.).</li>
        <li>Access is granted immediately upon successful payment.</li>
        <li>As the content is consumed digitally and cannot be "returned", subscription fees are non-refundable except as set out in Section 3.</li>
    </ul>

    <h2>3. When You Are Eligible for a Refund</h2>
    <p>We will issue a full or partial refund in the following situations:</p>
    <ul>
        <li><strong>Duplicate payment:</strong> You were charged more than once for the same Plan due to a technical error. The extra charge(s) will be refunded in full.</li>
        <li><strong>Payment deducted, access not granted:</strong> Your payment was successful but your Plan was not activated within 24 hours and our team could not restore it manually.</li>
        <li><strong>Proven technical failure:</strong> A platform-side defect prevented you from using a core paid feature for a sustained period, and we were unable to resolve it.</li>
    </ul>

    <h2>4. When Refunds Are NOT Available</h2>
    <ul>
        <li>You changed your mind after the Plan was activated.</li>
        <li>You did not use the Plan, or used it only partially, during the subscription period.</li>
        <li>You were unhappy with your exam result, mock score, rank prediction, or any educational outcome.</li>
        <li>Your account was suspended or terminated for violating our <a href="<?= BASE_PATH ?>terms.php">Terms &amp; Conditions</a> (e.g. cheating, multi-accounting, content scraping).</li>
        <li>The subscription period has already ended.</li>
    </ul>

    <h2>5. Cancellation of Subscription</h2>
    <ul>
        <li>You may cancel your subscription at any time from <strong>Billing &rarr; Subscription</strong> in your account, or by contacting us.</li>
        <li>Cancellation stops any future renewals. It does <strong>not</strong> trigger a refund for the current, already-active period — your access continues until the current period expires.</li>
        <li>We do not auto-charge a renewal without a clear prior indication; please review your plan details at checkout.</li>
    </ul>

    <h2>6. How to Request a Refund</h2>
    <p>To request a refund under Section 3, email us within <strong>7 days</strong> of the transaction with the following details:</p>
    <ul>
        <li>Registered email address on your DDCET Prep account</li>
        <li>Razorpay Payment ID / Order ID (from your payment receipt)</li>
        <li>Date and amount of the transaction</li>
        <li>A short description of the issue</li>
    </ul>
    <p>Send requests to <a href="mailto:support@ddcetprep.com">support@ddcetprep.com</a>.</p>

    <h2>7. Refund Processing Time</h2>
    <ul>
        <li>Approved refunds are initiated within <strong>5–7 business days</strong> of approval.</li>
        <li>Refunds are credited to the <strong>original payment method</strong> used at checkout, via Razorpay.</li>
        <li>Depending on your bank or card issuer, it may take an additional 5–10 business days for the amount to reflect in your account.</li>
        <li>We do not control bank-side processing times.</li>
    </ul>

    <h2>8. Chargebacks</h2>
    <p>If you believe a charge is incorrect, please contact us first so we can resolve it quickly. Raising a chargeback without contacting us may result in your account being suspended pending investigation.</p>

    <h2>9. Changes to This Policy</h2>
    <p>We may update this Refund &amp; Cancellation Policy from time to time. Changes take effect when posted, with the "Last Updated" date revised accordingly. The policy in force at the time of your purchase governs that transaction.</p>

    <h2>10. Contact Us</h2>
    <p>For any billing, refund, or cancellation queries:</p>
    <ul>
        <li><strong>Email:</strong> <a href="mailto:support@ddcetprep.com">support@ddcetprep.com</a></li>
        <li><strong>Response time:</strong> Within 48 hours on business days</li>
    </ul>
</div>
</body>
</html>
