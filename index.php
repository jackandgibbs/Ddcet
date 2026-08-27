<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/blog/posts.php';
// If already logged in, go to dashboard
if (currentUser()) {
    header('Location: ' . BASE_PATH . 'dashboard.php');
    exit;
}
$examDate = new DateTime(DDCET_EXAM_DATE);
$diff = (new DateTime())->diff($examDate);
$daysLeft = $diff->invert ? 0 : $diff->days;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DDCET Prep — Your Diploma to Degree Journey Starts Here</title>
    <meta name="description" content="DDCET Prep is the only platform built for Gujarat's Diploma-to-Degree Common Entrance Test. Real exam-pattern mock tests, a 2000+ MCQ question bank, AI college predictor, analytics and free daily challenges.">
    <link rel="canonical" href="<?= APP_URL . BASE_PATH ?>">
    <meta name="theme-color" content="#4361ee">

    <!-- Open Graph / social -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="DDCET Prep">
    <meta property="og:title" content="DDCET Prep — Crack DDCET with Smart Preparation">
    <meta property="og:description" content="Real exam-pattern mocks, 2000+ MCQs, AI college predictor, analytics and free daily challenges — built for Gujarat diploma students.">
    <meta property="og:url" content="<?= APP_URL . BASE_PATH ?>">
    <meta property="og:image" content="<?= APP_URL . BASE_PATH ?>assets/icon-512.png">
    <meta name="twitter:card" content="summary_large_image">

    <!-- Favicons & PWA -->
    <link rel="icon" href="assets/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/favicon-16.png">
    <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
    <link rel="manifest" href="<?= BASE_PATH ?>manifest.webmanifest">

    <!-- Self-hosted fonts (no render-blocking Google CDN). Preload the two used above the fold. -->
    <link rel="preload" href="assets/fonts/dmsans.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="assets/fonts/dmmono-500.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="assets/fonts/fonts.css">
    <script type="application/ld+json">
    <?= json_encode([
        '@context' => 'https://schema.org',
        '@type'    => 'EducationalOrganization',
        'name'     => 'DDCET Prep',
        'url'      => APP_URL . BASE_PATH,
        'logo'     => APP_URL . BASE_PATH . 'assets/icon-512.png',
        'description' => "Gujarat's dedicated preparation platform for the Diploma-to-Degree Common Entrance Test (DDCET).",
        'areaServed' => 'Gujarat, India',
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
    </script>
    <style>
        :root {
            --bg: #f8f9fa; --card: #ffffff; --accent: #4361ee; --accent-hover: #3a56d4;
            --green: #10b981; --orange: #f59e0b; --purple: #8b5cf6; --red: #ef4444;
            --text: #1a1a2e; --text-sec: #6c757d; --text-muted: #adb5bd;
            --border: #e9ecef; --radius: 12px;
            --font: 'DM Sans', sans-serif; --mono: 'DM Mono', monospace;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { overflow-x: hidden; }
        body { background: var(--bg); color: var(--text); font-family: var(--font); line-height: 1.6; overflow-x: hidden; }
        a { color: var(--accent); text-decoration: none; }

        /* Navbar */
        .nav { position: fixed; top: 0; left: 0; right: 0; z-index: 100; background: rgba(255,255,255,0.85); backdrop-filter: blur(20px); border-bottom: 1px solid var(--border); width: 100%; }
        .nav-inner { max-width: 1200px; margin: 0 auto; padding: 16px 32px; display: flex; align-items: center; justify-content: space-between; }
        .nav-brand { font-size: 22px; font-weight: 800; color: var(--text); }
        .nav-brand span { color: var(--accent); }
        .nav-links { display: flex; gap: 32px; align-items: center; }
        .nav-links a { color: var(--text-sec); font-size: 14px; font-weight: 500; transition: color 0.2s; }
        .nav-links a:hover { color: var(--accent); }
        .btn-nav { background: var(--accent); color: #fff !important; padding: 10px 24px; border-radius: 8px; font-weight: 600; font-size: 13px; transition: transform 0.15s, box-shadow 0.2s; }
        .btn-nav:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(67,97,238,0.3); }
        .hamburger { display: none; background: none; border: none; cursor: pointer; padding: 8px; }

        /* Hero */
        .hero { min-height: 100vh; display: flex; align-items: center; padding: 120px 32px 80px; position: relative; }
        .hero-inner { max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: 1fr 1fr; gap: 80px; align-items: center; }
        .hero-text h1 { font-size: 52px; font-weight: 900; line-height: 1.1; letter-spacing: -1.5px; margin-bottom: 20px; }
        .hero-text h1 .highlight { background: linear-gradient(135deg, var(--accent), var(--purple)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .hero-text p { font-size: 18px; color: var(--text-sec); max-width: 480px; margin-bottom: 32px; }
        .hero-cta { display: flex; gap: 16px; align-items: center; }
        .btn-primary { background: var(--accent); color: #fff; padding: 16px 36px; border-radius: 10px; font-weight: 700; font-size: 16px; border: none; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary:hover { background: var(--accent-hover); transform: translateY(-2px); box-shadow: 0 12px 32px rgba(67,97,238,0.3); }
        .btn-ghost { background: transparent; color: var(--text); padding: 16px 28px; border-radius: 10px; font-weight: 600; font-size: 15px; border: 1px solid var(--border); cursor: pointer; transition: all 0.2s; }
        .btn-ghost:hover { border-color: var(--accent); color: var(--accent); }

        /* Floating Dashboard Preview */
        .hero-visual { position: relative; }
        .dash-preview { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 24px; box-shadow: 0 24px 64px rgba(0,0,0,0.08); transform: perspective(1000px) rotateY(-5deg) rotateX(2deg); transition: transform 0.4s; }
        .dash-preview:hover { transform: perspective(1000px) rotateY(0deg) rotateX(0deg); }
        .dash-stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 16px; }
        .dash-stat { background: var(--bg); border-radius: 10px; padding: 16px; }
        .dash-stat .val { font-family: var(--mono); font-size: 28px; font-weight: 700; }
        .dash-stat .lbl { font-size: 11px; color: var(--text-muted); margin-top: 2px; }
        .dash-stat.accent .val { color: var(--accent); }
        .dash-stat.green .val { color: var(--green); }
        .dash-stat.orange .val { color: var(--orange); }
        .dash-stat.purple .val { color: var(--purple); }
        .dash-heatmap { display: flex; gap: 3px; flex-wrap: wrap; }
        .dash-heatmap .cell { width: 10px; height: 10px; border-radius: 2px; background: var(--bg); }
        .dash-heatmap .cell.l1 { background: rgba(67,97,238,0.2); }
        .dash-heatmap .cell.l2 { background: rgba(67,97,238,0.4); }
        .dash-heatmap .cell.l3 { background: rgba(67,97,238,0.7); }
        .dash-heatmap .cell.l4 { background: var(--accent); }

        /* Floating badges */
        .float-badge { position: absolute; background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 12px 18px; box-shadow: var(--shadow-lg); font-size: 13px; font-weight: 600; animation: float 3s ease-in-out infinite; }
        .float-badge.b1 { top: -20px; right: -30px; animation-delay: 0s; }
        .float-badge.b2 { bottom: 40px; left: -40px; animation-delay: 1.5s; }
        .float-badge .dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 6px; }
        @keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }

        /* Countdown Banner */
        .countdown { background: radial-gradient(ellipse at 30% 20%, #312e81 0%, transparent 55%), radial-gradient(ellipse at 75% 80%, #6d28d9 0%, transparent 50%), linear-gradient(135deg, #0b1026 0%, #1a1140 100%); padding: 64px 32px; text-align: center; position: relative; overflow: hidden; }
        .countdown::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: radial-gradient(circle, rgba(255,255,255,0.05) 1px, transparent 1px); background-size: 30px 30px; animation: drift 20s linear infinite; }
        .countdown::after { content: ''; position: absolute; top: 0; right: 0; width: 200px; height: 200px; background: radial-gradient(circle, rgba(255,255,255,0.1), transparent 70%); border-radius: 50%; transform: translate(30%, -30%); }
        @keyframes drift { from { transform: translate(0,0); } to { transform: translate(30px, 30px); } }
        .countdown-inner { max-width: 800px; margin: 0 auto; position: relative; z-index: 2; }
        .countdown h2 { color: #fff; font-size: 14px; font-weight: 500; opacity: 0.9; margin-bottom: 8px; letter-spacing: 1px; text-transform: uppercase; }
        .countdown .big-text { font-size: 28px; font-weight: 800; color: #fff; margin-bottom: 24px; }
        .countdown .big-text span { color: rgba(255,255,255,0.6); font-weight: 400; font-size: 16px; }
        /* Countdown clock — self-contained, no external library */
        .flipdown { display: inline-flex; gap: 12px; margin: 8px auto 0; flex-wrap: wrap; justify-content: center; }
        .fd-unit { display: flex; flex-direction: column; align-items: center; gap: 10px; }
        .fd-card { position: relative; min-width: 78px; padding: 16px 10px; background: linear-gradient(160deg, #20233d 0%, #181a2e 100%); border-radius: 10px; box-shadow: 0 10px 26px rgba(0,0,0,0.45); }
        .fd-card::after { content: ''; position: absolute; left: 0; right: 0; top: 50%; height: 1px; background: rgba(0,0,0,0.35); }
        .fd-num { display: block; font-family: var(--mono); font-size: 40px; font-weight: 500; line-height: 1; color: #fff; font-variant-numeric: tabular-nums; }
        .fd-label { font-size: 11px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: rgba(255,255,255,0.7); }
        @media (max-width: 480px) { .flipdown { gap: 8px; } .fd-card { min-width: 60px; padding: 12px 8px; } .fd-num { font-size: 30px; } }
        .countdown-urgency { margin-top: 20px; display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.1); padding: 8px 20px; border-radius: 20px; font-size: 13px; color: rgba(255,255,255,0.9); border: 1px solid rgba(255,255,255,0.15); }
        .countdown-urgency .pulse { width: 8px; height: 8px; border-radius: 50%; background: #10b981; animation: pulse 1.5s ease-in-out infinite; }
        @keyframes pulse { 0%,100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.5; transform: scale(1.5); } }

        /* Features */
        .section { padding: 100px 32px; }
        .section-inner { max-width: 1100px; margin: 0 auto; }
        .section-title { font-size: 36px; font-weight: 800; text-align: center; margin-bottom: 12px; letter-spacing: -0.5px; }
        .section-sub { text-align: center; color: var(--text-sec); font-size: 16px; margin-bottom: 56px; max-width: 500px; margin-inline: auto; }

        .features-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .feature-card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 32px; transition: all 0.25s; position: relative; overflow: hidden; }
        .feature-card:hover { transform: translateY(-4px); box-shadow: 0 16px 40px rgba(0,0,0,0.06); border-color: var(--accent); }
        .feature-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: var(--accent); opacity: 0; transition: opacity 0.25s; }
        .feature-card:hover::before { opacity: 1; }
        .feature-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-bottom: 16px; font-size: 20px; }
        .feature-card h3 { font-size: 16px; font-weight: 700; margin-bottom: 8px; }
        .feature-card p { font-size: 13px; color: var(--text-sec); line-height: 1.7; }

        /* Feature category tabs + showcase */
        .cat-tabs { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; margin-bottom: 40px; }
        .cat-tab { background: var(--card); border: 1px solid var(--border); color: var(--text-sec); padding: 10px 20px; border-radius: 24px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 7px; }
        .cat-tab svg { width: 15px; height: 15px; }
        .cat-tab:hover { border-color: var(--accent); color: var(--accent); }
        .cat-tab.active { background: var(--accent); border-color: var(--accent); color: #fff; box-shadow: 0 8px 20px rgba(67,97,238,0.25); }
        .cat-panel { display: none; animation: fadeUp 0.45s ease both; }
        .cat-panel.active { display: block; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
        .showcase { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; }
        .show-card { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 24px; transition: all 0.25s; position: relative; overflow: hidden; }
        .show-card:hover { transform: translateY(-5px); box-shadow: 0 18px 44px rgba(0,0,0,0.08); border-color: transparent; }
        .show-card .sc-top { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
        .show-card .sc-icon { width: 42px; height: 42px; border-radius: 11px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .show-card .sc-icon svg { width: 21px; height: 21px; }
        .show-card h3 { font-size: 15px; font-weight: 700; }
        .show-card .sc-tag { font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 6px; text-transform: uppercase; letter-spacing: 0.5px; margin-left: auto; }
        .show-card p { font-size: 13px; color: var(--text-sec); line-height: 1.65; }
        .sc-mini { margin-top: 14px; padding-top: 14px; border-top: 1px dashed var(--border); display: flex; gap: 6px; flex-wrap: wrap; }
        .sc-chip { font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 6px; background: var(--bg); color: var(--text-sec); }
        .tag-free { background: rgba(16,185,129,0.12); color: var(--green); }
        .tag-pro { background: rgba(139,92,246,0.12); color: var(--purple); }
        .tag-new { background: rgba(245,158,11,0.14); color: var(--orange); }
        .tag-ai { background: rgba(67,97,238,0.12); color: var(--accent); }

        /* Bento highlight row */
        .bento { display: grid; grid-template-columns: 1.4fr 1fr 1fr; gap: 18px; margin-top: 22px; }
        .bento-card { border-radius: 16px; padding: 28px; color: #fff; position: relative; overflow: hidden; min-height: 180px; display: flex; flex-direction: column; justify-content: space-between; }
        .bento-card h4 { font-size: 18px; font-weight: 800; margin-bottom: 6px; }
        .bento-card p { font-size: 13px; opacity: 0.85; line-height: 1.6; }
        .bento-card .bento-stat { font-family: var(--mono); font-size: 34px; font-weight: 800; }
        .bento-card { background: radial-gradient(ellipse at 30% 20%, #312e81 0%, transparent 55%), radial-gradient(ellipse at 75% 80%, #6d28d9 0%, transparent 50%), linear-gradient(135deg, #0b1026 0%, #1a1140 100%); }
        .bento-a { grid-column: span 1; }
        .bento-card::after { content: ''; position: absolute; width: 140px; height: 140px; right: -30px; bottom: -40px; background: radial-gradient(circle, rgba(255,255,255,0.18), transparent 70%); border-radius: 50%; }

        /* Social Proof */
        .proof { background: var(--card); border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); }
        .proof-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0; text-align: center; }
        .proof-item { padding: 48px 20px; border-right: 1px solid var(--border); position: relative; }
        .proof-item:last-child { border-right: none; }
        .proof-item .num { font-family: var(--mono); font-size: 40px; font-weight: 800; background: linear-gradient(135deg, var(--accent), var(--purple)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .proof-item .label { font-size: 13px; color: var(--text-sec); margin-top: 4px; font-weight: 500; }
        .proof-item .sublabel { font-size: 11px; color: var(--text-muted); margin-top: 2px; }

        /* Testimonials */
        .testimonials { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .testimonial { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 28px; }
        .testimonial .stars { color: var(--orange); font-size: 14px; margin-bottom: 12px; display: flex; gap: 2px; }
        .testimonial .stars svg { width: 16px; height: 16px; fill: var(--orange); stroke: none; }
        .testimonial .quote { font-size: 14px; color: var(--text-sec); line-height: 1.7; margin-bottom: 16px; font-style: italic; }
        .testimonial .author { display: flex; align-items: center; gap: 10px; }
        .testimonial .avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--accent-light); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; color: var(--accent); }
        .testimonial .name { font-size: 13px; font-weight: 600; }
        .testimonial .college { font-size: 11px; color: var(--text-muted); }

        /* CTA */
        .cta-section { background: linear-gradient(135deg, #1a1a2e 0%, #2d1b69 100%); padding: 80px 32px; text-align: center; }
        .cta-section h2 { color: #fff; font-size: 36px; font-weight: 800; margin-bottom: 12px; }
        .cta-section p { color: rgba(255,255,255,0.7); font-size: 16px; margin-bottom: 32px; }
        .cta-section .btn-primary { font-size: 18px; padding: 18px 48px; }

        /* Blog teaser */
        .blog-teaser { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; max-width: 1000px; margin: 0 auto; }
        .bt-card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 26px; display: flex; flex-direction: column; transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s; }
        .bt-card:hover { transform: translateY(-5px); box-shadow: 0 16px 40px rgba(0,0,0,0.08); border-color: rgba(67,97,238,0.35); }
        .bt-tag { align-self: flex-start; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--accent); background: rgba(67,97,238,0.08); padding: 5px 12px; border-radius: 20px; margin-bottom: 14px; }
        .bt-card h3 { font-size: 17px; font-weight: 800; line-height: 1.3; margin-bottom: 10px; color: var(--text); }
        .bt-card p { font-size: 14px; color: var(--text-sec); flex: 1; }
        .bt-card .bt-more { margin-top: 16px; font-size: 13px; font-weight: 700; color: var(--accent); }
        .blog-teaser-foot { text-align: center; margin-top: 36px; }

        /* FAQ accordion */
        .faq-wrap { max-width: 760px; margin: 0 auto; }
        .faq-item { border: 1px solid var(--border); border-radius: var(--radius); background: var(--card); margin-bottom: 14px; overflow: hidden; }
        .faq-item summary { list-style: none; cursor: pointer; padding: 20px 24px; font-size: 16px; font-weight: 700; color: var(--text); display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .faq-item summary::-webkit-details-marker { display: none; }
        .faq-item summary .chev { flex-shrink: 0; transition: transform 0.25s ease; color: var(--accent); }
        .faq-item[open] summary .chev { transform: rotate(180deg); }
        .faq-item .faq-a { padding: 0 24px 20px; color: var(--text-sec); font-size: 15px; line-height: 1.7; }

        @media (max-width: 768px) { .blog-teaser { grid-template-columns: 1fr; } }

        /* Footer */
        .footer { background: #1a1a2e; padding: 64px 32px 32px; color: rgba(255,255,255,0.6); }
        .footer-brand { font-size: 20px; font-weight: 800; color: #fff; }
        .footer-brand span { color: var(--accent); }
        .footer-links { display: flex; gap: 24px; }
        .footer-links a { color: rgba(255,255,255,0.5); font-size: 13px; transition: color 0.2s; }
        .footer-links a:hover { color: #fff; }

        /* Animations */
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .animate { animation: slideUp 0.6s ease-out both; }
        .delay-1 { animation-delay: 0.1s; } .delay-2 { animation-delay: 0.2s; } .delay-3 { animation-delay: 0.3s; }

        /* Counter animation */
        .counter { font-family: var(--mono); }

        /* Mobile */
        @media (max-width: 768px) {
            .hero-inner { grid-template-columns: 1fr; gap: 40px; text-align: center; }
            .hero-text h1 { font-size: 36px; }
            .hero-text p { margin-inline: auto; }
            .hero-cta { justify-content: center; flex-wrap: wrap; }
            /* Show the dashboard preview (incl. the streak heatmap) on mobile,
               flattened and without the off-canvas floating badges. */
            .hero-visual { display: block; }
            .hero-visual .dash-preview { transform: none; }
            .hero-visual .float-badge { display: none; }
            .dash-heatmap { justify-content: center; }
            .features-grid, .testimonials { grid-template-columns: 1fr; }
            .showcase { grid-template-columns: 1fr; }
            .bento { grid-template-columns: 1fr; }
            .proof-grid { grid-template-columns: repeat(2, 1fr); }
            .proof-item { border-bottom: 1px solid var(--border); }
            #flipdown.flipdown { transform: scale(0.78); transform-origin: top center; }
            .hamburger { display: block; }
            .nav-links { display: none; position: absolute; top: 100%; left: 0; right: 0; background: var(--card); border-bottom: 1px solid var(--border); flex-direction: column; padding: 16px 24px; gap: 16px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); }
            .nav-links.open { display: flex; }
            .nav-links a { width: 100%; }
        }
    </style>
</head>
<body>

<!-- Navigation -->
<nav class="nav">
    <div class="nav-inner">
        <div class="nav-brand"><img src="assets/logo.png" alt="" width="35" height="26" decoding="async" style="height: 26px; width: auto; vertical-align: middle; margin-right: 6px;"><span>DDCET</span> Prep</div>
        <button class="hamburger" onclick="document.querySelector('.nav-links').classList.toggle('open')" aria-label="Menu">
            <svg width="24" height="24" fill="none" stroke="var(--text)" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
        </button>
        <div class="nav-links">
            <a href="#features">Features</a>
            <a href="syllabus.php">Syllabus</a>
            <a href="blog.php">Blog</a>
            <a href="roadmap.php">Roadmap</a>
            <a href="about.php">About</a>
            <a href="institutions.php">For Institutions</a>
            <a href="contact.php">Contact</a>
            <a href="auth/login.php" class="btn-nav">Try the mock test free →</a>
        </div>
    </div>
</nav>

<!-- Hero -->
<section class="hero">
    <div class="hero-inner">
        <div class="hero-text animate">
            <h1>Crack <span class="highlight">DDCET</span> with a Plan Built for Your Branch</h1>
            <p>The only platform built specifically for Gujarat's Diploma-to-Degree Common Entrance Test. <strong>Try a full mock test free</strong> — real exam pattern, instant results and rank prediction. Then get a branch-specific study plan and progress analytics tuned to where you actually start.</p>
            <div class="hero-cta">
                <a href="auth/login.php" class="btn-primary">Try the mock test free →</a>
                <a href="roadmap.php" class="btn-ghost">View Roadmap</a>
            </div>
            <div style="margin-top: 24px; display: flex; gap: 20px; align-items: center;">
                <div style="display: flex;">
                    <?php for($i=0;$i<4;$i++): ?><div style="width:32px;height:32px;border-radius:50%;background:var(--accent-light);border:2px solid var(--card);margin-left:<?= $i?'-8':'0' ?>px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:var(--accent);"><?= chr(65+$i) ?></div><?php endfor; ?>
                </div>
                <span style="font-size: 13px; color: var(--text-sec);"><strong style="color: var(--text);">500+</strong> students already preparing</span>
            </div>
            <?php
            // BUG-038 fix: round to the hour so the string doesn't change every second,
            // which was breaking the cache key in config.php and querying the DB on every hit.
            $threeHoursAgo = urlencode(date('Y-m-d\TH:00:00P', strtotime('-3 hours')));
            $liveTests = supabaseRest('attempts?status=eq.in_progress&started_at=gt.' . $threeHoursAgo . '&select=id');
            $liveCount = is_array($liveTests) ? count($liveTests) : 0;
            if ($liveCount > 0):
            ?>
            <div style="margin-top: 12px; display: inline-flex; align-items: center; gap: 8px; background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.2); padding: 8px 16px; border-radius: 20px;">
                <span style="width: 8px; height: 8px; border-radius: 50%; background: var(--green); animation: pulse 1.5s infinite;"></span>
                <span style="font-size: 13px; color: var(--green); font-weight: 600;"><?= $liveCount ?> student<?= $liveCount > 1 ? 's' : '' ?> taking a test right now</span>
            </div>
            <?php endif; ?>
        </div>
        <div class="hero-visual animate delay-2">
            <div class="dash-preview">
                <div class="dash-stats">
                    <div class="dash-stat accent"><div class="val counter" data-target="<?= $daysLeft ?>"><?= $daysLeft ?></div><div class="lbl">Days to DDCET</div></div>
                    <div class="dash-stat green"><div class="val">78%</div><div class="lbl">Avg Readiness</div></div>
                    <div class="dash-stat orange"><div class="val">142</div><div class="lbl">Tests Available</div></div>
                    <div class="dash-stat purple"><div class="val">7</div><div class="lbl">Day Streak</div></div>
                </div>
                <div style="font-size: 11px; color: var(--text-muted); margin-bottom: 8px;">Activity — Last 60 days</div>
                <div class="dash-heatmap">
                    <?php mt_srand(crc32(date('Y-m-d'))); for($i=0;$i<60;$i++): $l=['','l1','l2','l3','l4'][mt_rand(0,4)]; ?><div class="cell <?= $l ?>"></div><?php endfor; mt_srand(); ?>
                </div>
            </div>
            <div class="float-badge b1"><span class="dot" style="background: var(--green);"></span>+25 XP Earned</div>
            <div class="float-badge b2"><span class="dot" style="background: var(--orange);"></span>🔥 7 Day Streak</div>
        </div>
    </div>
</section>

<!-- Countdown -->
<section class="countdown">
    <div class="countdown-inner">
        <h2><svg width="14" height="14" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" style="vertical-align: -2px; margin-right: 4px;"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg> DDCET <?= $examDate->format('Y') ?> Exam Countdown</h2>
        <div class="big-text">Every second counts. <span>Are you ready?</span></div>
        <div id="flipdown" class="flipdown" role="timer" aria-label="Time remaining until the DDCET exam">
            <div class="fd-unit"><div class="fd-card"><span class="fd-num" data-unit="days">00</span></div><div class="fd-label">Days</div></div>
            <div class="fd-unit"><div class="fd-card"><span class="fd-num" data-unit="hours">00</span></div><div class="fd-label">Hours</div></div>
            <div class="fd-unit"><div class="fd-card"><span class="fd-num" data-unit="minutes">00</span></div><div class="fd-label">Minutes</div></div>
            <div class="fd-unit"><div class="fd-card"><span class="fd-num" data-unit="seconds">00</span></div><div class="fd-label">Seconds</div></div>
        </div>
        <div class="countdown-urgency"><span class="pulse"></span> <?= $daysLeft < 100 ? 'Less than 100 days left!' : number_format($daysLeft) . ' days — Start now, not tomorrow' ?></div>
    </div>
</section>

<!-- Social Proof Numbers -->
<section class="proof">
    <div class="proof-grid">
        <div class="proof-item"><div class="num counter" data-target="500">0</div><div class="label">Active Students</div><div class="sublabel">and growing daily</div></div>
        <div class="proof-item"><div class="num counter" data-target="2000">0</div><div class="label">Questions Bank</div><div class="sublabel">DDCET pattern MCQs</div></div>
        <div class="proof-item"><div class="num counter" data-target="142">0</div><div class="label">Mock Tests</div><div class="sublabel">full-length + topic-wise</div></div>
        <div class="proof-item"><div class="num counter" data-target="95">0</div><div class="label">% Satisfaction</div><div class="sublabel">rated by real students</div></div>
    </div>
</section>

<!-- Features Showcase -->
<section class="section" id="features">
    <div class="section-inner">
        <div style="text-align:center; margin-bottom: 16px;">
            <span style="display:inline-flex; align-items:center; gap:6px; background: rgba(67,97,238,0.08); color: var(--accent); font-size:12px; font-weight:700; padding:6px 14px; border-radius:20px; text-transform:uppercase; letter-spacing:0.5px;">
                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 3l14 9-14 9V3z"/></svg> 20+ Features in One Platform
            </span>
        </div>
        <h2 class="section-title">Everything You Need to Crack DDCET</h2>
        <p class="section-sub">A complete preparation ecosystem — practice, track, compete, and learn. Explore by category.</p>

        <!-- Category Tabs -->
        <div class="cat-tabs">
            <button class="cat-tab active" data-cat="practice"><svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 14l2 2 4-4"/></svg> Practice & Tests</button>
            <button class="cat-tab" data-cat="insights"><svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg> Insights & AI</button>
            <button class="cat-tab" data-cat="compete"><svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg> Compete & Social</button>
            <button class="cat-tab" data-cat="study"><svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg> Study & Learn</button>
        </div>

        <!-- PRACTICE -->
        <div class="cat-panel active" data-panel="practice">
            <div class="showcase">
                <div class="show-card">
                    <div class="sc-top"><div class="sc-icon" style="background:rgba(139,92,246,0.1);color:var(--purple);"><svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg></div><h3>Branch-Specific Study Plan</h3><span class="sc-tag tag-new">Personalized</span></div>
                    <p>Tell us your diploma branch and get a marks-weighted plan — Computer, Mechanical, Civil, Electrical, EC or Chemical — tuned to where you start strong and where to focus.</p>
                    <div class="sc-mini"><span class="sc-chip">Per-branch focus</span><span class="sc-chip">Study-time split</span></div>
                </div>
                <div class="show-card">
                    <div class="sc-top"><div class="sc-icon" style="background:rgba(67,97,238,0.1);color:var(--accent);"><svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 14l2 2 4-4"/></svg></div><h3>Full DDCET Mock</h3><span class="sc-tag tag-pro">Pro</span></div>
                    <p>100 questions, 200 marks, 150 minutes — the exact real exam pattern with auto-submit and instant scoring.</p>
                    <div class="sc-mini"><span class="sc-chip">Physics</span><span class="sc-chip">Chemistry</span><span class="sc-chip">Maths</span><span class="sc-chip">English</span></div>
                </div>
                <div class="show-card">
                    <div class="sc-top"><div class="sc-icon" style="background:rgba(245,158,11,0.1);color:var(--orange);"><svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg></div><h3>Rapid Fire</h3><span class="sc-tag tag-new">Speed</span></div>
                    <p>30 questions, 60 seconds each. Train your reflexes and accuracy under real time pressure.</p>
                    <div class="sc-mini"><span class="sc-chip">⏱ 60s / question</span><span class="sc-chip">Adrenaline mode</span></div>
                </div>
                <div class="show-card">
                    <div class="sc-top"><div class="sc-icon" style="background:rgba(16,185,129,0.1);color:var(--green);"><svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg></div><h3>Daily Challenge</h3><span class="sc-tag tag-free">Free</span></div>
                    <p>A fresh free test every single day. Build an unbreakable streak and keep your momentum till exam day.</p>
                    <div class="sc-mini"><span class="sc-chip">🔥 Streak tracker</span><span class="sc-chip">Always free</span></div>
                </div>
                <div class="show-card">
                    <div class="sc-top"><div class="sc-icon" style="background:rgba(139,92,246,0.1);color:var(--purple);"><svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg></div><h3>Weekly Challenge</h3><span class="sc-tag tag-pro">Pro</span></div>
                    <p>A bigger live test every week. Compete against the whole platform on the same paper, same window.</p>
                    <div class="sc-mini"><span class="sc-chip">Live leaderboard</span><span class="sc-chip">Sundays</span></div>
                </div>
                <div class="show-card">
                    <div class="sc-top"><div class="sc-icon" style="background:rgba(67,97,238,0.1);color:var(--accent);"><svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><path d="M9 22V12h6v10"/></svg></div><h3>Subject-wise & Topic Tests</h3><span class="sc-tag tag-free">Free</span></div>
                    <p>Drill one subject at a time — Physics, Chemistry, Maths, English, Computers & Environment.</p>
                    <div class="sc-mini"><span class="sc-chip">6 subjects</span><span class="sc-chip">Topic filters</span></div>
                </div>
                <div class="show-card">
                    <div class="sc-top"><div class="sc-icon" style="background:rgba(239,68,68,0.1);color:var(--red);"><svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 12a9 9 0 109-9 9.75 9.75 0 00-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg></div><h3>Custom Test + Revision</h3><span class="sc-tag tag-new">Smart</span></div>
                    <p>Build your own paper by subject, difficulty & count — or re-attempt only the questions you got wrong.</p>
                    <div class="sc-mini"><span class="sc-chip">Previous year papers</span><span class="sc-chip">Wrong-Q revision</span></div>
                </div>
            </div>
        </div>

        <!-- INSIGHTS -->
        <div class="cat-panel" data-panel="insights">
            <div class="showcase">
                <div class="show-card">
                    <div class="sc-top"><div class="sc-icon" style="background:rgba(16,185,129,0.1);color:var(--green);"><svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg></div><h3>Smart Analytics</h3><span class="sc-tag tag-free">Free</span></div>
                    <p>Subject-wise accuracy, time-per-question, strong vs weak topics and percentile — visualised after every test.</p>
                    <div class="sc-mini"><span class="sc-chip">Charts & graphs</span><span class="sc-chip">Percentile rank</span></div>
                </div>
                <div class="show-card">
                    <div class="sc-top"><div class="sc-icon" style="background:rgba(67,97,238,0.1);color:var(--accent);"><svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M22 12A10 10 0 0012 2v10z"/></svg></div><h3>AI College Predictor</h3><span class="sc-tag tag-ai">AI</span></div>
                    <p>Enter your expected score and category — our model predicts which colleges & branches you can realistically get.</p>
                    <div class="sc-mini"><span class="sc-chip">2024 + 2025 data</span><span class="sc-chip">Category-aware</span></div>
                </div>
                <div class="show-card">
                    <div class="sc-top"><div class="sc-icon" style="background:rgba(139,92,246,0.1);color:var(--purple);"><svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg></div><h3>College Finder</h3><span class="sc-tag tag-free">Free</span></div>
                    <p>Search and compare Gujarat degree colleges by branch, city and last year's cut-offs in seconds.</p>
                    <div class="sc-mini"><span class="sc-chip">Cut-off explorer</span><span class="sc-chip">Branch filters</span></div>
                </div>
                <div class="show-card">
                    <div class="sc-top"><div class="sc-icon" style="background:rgba(245,158,11,0.1);color:var(--orange);"><svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8M16 17H8M10 9H8"/></svg></div><h3>Detailed Result Reports</h3><span class="sc-tag tag-free">Free</span></div>
                    <p>Every attempt gets a full breakdown with solutions, plus a downloadable PDF report you can keep.</p>
                    <div class="sc-mini"><span class="sc-chip">PDF export</span><span class="sc-chip">Solutions</span></div>
                </div>
                <div class="show-card">
                    <div class="sc-top"><div class="sc-icon" style="background:rgba(239,68,68,0.1);color:var(--red);"><svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div><h3>Activity Heatmap</h3><span class="sc-tag tag-free">Free</span></div>
                    <p>A GitHub-style heatmap of your daily prep. See your consistency at a glance and never lose the habit.</p>
                    <div class="sc-mini"><span class="sc-chip">Streaks</span><span class="sc-chip">60-day view</span></div>
                </div>
                <div class="show-card">
                    <div class="sc-top"><div class="sc-icon" style="background:rgba(16,185,129,0.1);color:var(--green);"><svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5M2 12l10 5 10-5"/></svg></div><h3>Readiness Score</h3><span class="sc-tag tag-ai">AI</span></div>
                    <p>One number that tells you how exam-ready you are, computed from your accuracy, speed and coverage.</p>
                    <div class="sc-mini"><span class="sc-chip">Live tracking</span><span class="sc-chip">Goal-based</span></div>
                </div>
            </div>
        </div>

        <!-- COMPETE -->
        <div class="cat-panel" data-panel="compete">
            <div class="showcase">
                <div class="show-card">
                    <div class="sc-top"><div class="sc-icon" style="background:rgba(245,158,11,0.1);color:var(--orange);"><svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg></div><h3>Global & College Leaderboard</h3><span class="sc-tag tag-free">Free</span></div>
                    <p>Climb the ranks across all of Gujarat or compete within your own college. Updated in real time.</p>
                    <div class="sc-mini"><span class="sc-chip">Live ranks</span><span class="sc-chip">By college</span></div>
                </div>
                <div class="show-card">
                    <div class="sc-top"><div class="sc-icon" style="background:rgba(67,97,238,0.1);color:var(--accent);"><svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg></div><h3>Friends & Challenges</h3><span class="sc-tag tag-free">Free</span></div>
                    <p>Add friends, challenge them head-to-head on the same paper, and see who really knows their stuff.</p>
                    <div class="sc-mini"><span class="sc-chip">1v1 duels</span><span class="sc-chip">Friend feed</span></div>
                </div>
                <div class="show-card">
                    <div class="sc-top"><div class="sc-icon" style="background:rgba(139,92,246,0.1);color:var(--purple);"><svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="8" r="7"/><path d="M8.21 13.89L7 23l5-3 5 3-1.21-9.12"/></svg></div><h3>Leagues & Ranks</h3><span class="sc-tag tag-new">Gamified</span></div>
                    <p>Bronze to Gold — get promoted as you score, and battle to stay at the top of your league each season.</p>
                    <div class="sc-mini"><span class="sc-chip">🥇 Tiers</span><span class="sc-chip">Promotions</span></div>
                </div>
                <div class="show-card">
                    <div class="sc-top"><div class="sc-icon" style="background:rgba(16,185,129,0.1);color:var(--green);"><svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="8" r="6"/><path d="M9 22h6M12 14v8"/></svg></div><h3>XP, Badges & Achievements</h3><span class="sc-tag tag-free">Free</span></div>
                    <p>Earn XP for every test, unlock badges for milestones, and show off your achievement wall.</p>
                    <div class="sc-mini"><span class="sc-chip">Level up</span><span class="sc-chip">Unlockables</span></div>
                </div>
                <div class="show-card">
                    <div class="sc-top"><div class="sc-icon" style="background:rgba(245,158,11,0.1);color:var(--orange);"><svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg></div><h3>Community Feed</h3><span class="sc-tag tag-free">Free</span></div>
                    <p>Share wins, ask for tips, and stay motivated with a feed full of fellow DDCET aspirants.</p>
                    <div class="sc-mini"><span class="sc-chip">Discussions</span><span class="sc-chip">Peer support</span></div>
                </div>
                <div class="show-card">
                    <div class="sc-top"><div class="sc-icon" style="background:rgba(139,92,246,0.1);color:var(--purple);"><svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20 12V8H6a2 2 0 01-2-2c0-1.1.9-2 2-2h12v4"/><path d="M4 6v12c0 1.1.9 2 2 2h14v-4"/><path d="M18 12a2 2 0 000 4h4v-4z"/></svg></div><h3>Top-10 Mock Rewards</h3><span class="sc-tag tag-new">Win</span></div>
                    <p>Finish in the Top 10 of a free scheduled mock and claim 1 month of Pro — completely free.</p>
                    <div class="sc-mini"><span class="sc-chip">🎁 Real rewards</span><span class="sc-chip">Earn Pro</span></div>
                </div>
            </div>
        </div>

        <!-- STUDY -->
        <div class="cat-panel" data-panel="study">
            <div class="showcase">
                <div class="show-card">
                    <div class="sc-top"><div class="sc-icon" style="background:rgba(239,68,68,0.1);color:var(--red);"><svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg></div><h3>70+ Free Resources</h3><span class="sc-tag tag-free">Free</span></div>
                    <p>A curated library of the best YouTube channels and websites, organised topic-by-topic. No paid courses needed.</p>
                    <div class="sc-mini"><span class="sc-chip">Hand-picked</span><span class="sc-chip">Topic-wise</span></div>
                </div>
                <div class="show-card">
                    <div class="sc-top"><div class="sc-icon" style="background:rgba(67,97,238,0.1);color:var(--accent);"><svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 3v4M8 3v4"/></svg></div><h3>Flashcards</h3><span class="sc-tag tag-free">Free</span></div>
                    <p>Quick-revision flashcards for formulas and key concepts — perfect for the final-week sprint.</p>
                    <div class="sc-mini"><span class="sc-chip">Flip to revise</span><span class="sc-chip">Formula sheets</span></div>
                </div>
                <div class="show-card">
                    <div class="sc-top"><div class="sc-icon" style="background:rgba(16,185,129,0.1);color:var(--green);"><svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg></div><h3>Study Materials</h3><span class="sc-tag tag-free">Free</span></div>
                    <p>Downloadable notes, PDFs and reference material curated for the DDCET syllabus.</p>
                    <div class="sc-mini"><span class="sc-chip">PDFs</span><span class="sc-chip">Notes</span></div>
                </div>
                <div class="show-card">
                    <div class="sc-top"><div class="sc-icon" style="background:rgba(245,158,11,0.1);color:var(--orange);"><svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div><h3>Bookmark Questions</h3><span class="sc-tag tag-free">Free</span></div>
                    <p>Save tricky questions while practising and revisit your personal bank anytime before the exam.</p>
                    <div class="sc-mini"><span class="sc-chip">Personal bank</span><span class="sc-chip">One-tap save</span></div>
                </div>
                <div class="show-card">
                    <div class="sc-top"><div class="sc-icon" style="background:rgba(139,92,246,0.1);color:var(--purple);"><svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/><circle cx="12" cy="12" r="10"/></svg></div><h3>Doubt Box</h3><span class="sc-tag tag-free">Free</span></div>
                    <p>Stuck on a problem? Post your doubt and get it answered by our team — tracked till it's resolved.</p>
                    <div class="sc-mini"><span class="sc-chip">Ask anything</span><span class="sc-chip">Admin answers</span></div>
                </div>
                <div class="show-card">
                    <div class="sc-top"><div class="sc-icon" style="background:rgba(67,97,238,0.1);color:var(--accent);"><svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 12a9 9 0 1018 0 9 9 0 00-18 0z"/><path d="M3 9h18M9 21V9"/></svg></div><h3>Roadmap & Contact Support</h3><span class="sc-tag tag-new">Guided</span></div>
                    <p>Follow a proven 4-phase study roadmap, and raise a query anytime via our tracked Contact Us system.</p>
                    <div class="sc-mini"><span class="sc-chip">4-phase plan</span><span class="sc-chip">Query tracking</span></div>
                </div>
            </div>
        </div>

        <!-- Bento highlights -->
        <div class="bento">
            <div class="bento-card bento-a">
                <div><h4>One platform. Zero gaps.</h4><p>From your first practice question to your final college choice — every step of the DDCET journey lives here.</p></div>
                <div class="bento-stat">20+</div>
            </div>
            <div class="bento-card bento-b">
                <div><h4>Free forever core</h4><p>Daily challenge, resources, analytics & community — always free.</p></div>
                <div class="bento-stat">₹0</div>
            </div>
            <div class="bento-card bento-c">
                <div><h4>Works everywhere</h4><p>Any phone, any laptop. No app install. Just open and start.</p></div>
                <div class="bento-stat">100%</div>
            </div>
        </div>
    </div>
</section>

<!-- Testimonials -->
<section class="section" style="background: var(--bg);">
    <div class="section-inner">
        <h2 class="section-title">Students Love Us</h2>
        <p class="section-sub">Real feedback from students who improved their DDCET preparation.</p>
        <div class="testimonials">
            <div class="testimonial">
                <div class="stars"><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg></div>
                <div class="quote">"The mock tests are exactly like DDCET pattern. My score improved from 45% to 78% in just 2 months of practice."</div>
                <div class="author">
                    <div class="avatar">R</div>
                    <div><div class="name">Raj Patel</div><div class="college">GEC Dahod</div></div>
                </div>
            </div>
            <div class="testimonial">
                <div class="stars"><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg></div>
                <div class="quote">"Best part is the daily challenge. It kept me consistent. The leaderboard with friends made it competitive and fun."</div>
                <div class="author">
                    <div class="avatar">P</div>
                    <div><div class="name">Priya Shah</div><div class="college">GPC Ahmedabad</div></div>
                </div>
            </div>
            <div class="testimonial">
                <div class="stars"><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg></div>
                <div class="quote">"Free resources section alone is worth it. Saved me from buying expensive courses. Topic-wise YouTube links are gold!"</div>
                <div class="author">
                    <div class="avatar">K</div>
                    <div><div class="name">Karan Mehta</div><div class="college">BBIT Vadodara</div></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- College Tracker -->
<section class="section" style="background: var(--card); border-top: 1px solid var(--border);">
    <div class="section-inner">
        <h2 class="section-title">College Leaderboard</h2>
        <p class="section-sub">See which colleges are preparing the hardest. Is your college on the list?</p>
        <div style="max-width: 600px; margin: 0 auto;">
            <?php
            $collegeStats = supabaseRest('students?select=college_id,colleges(name,city)&college_id=not.is.null') ?? [];
            $collegeCounts = [];
            foreach ($collegeStats as $s) {
                $name = $s['colleges']['name'] ?? '';
                $city = $s['colleges']['city'] ?? '';
                if (!$name) continue;
                $key = $name;
                $collegeCounts[$key] = ['count' => ($collegeCounts[$key]['count'] ?? 0) + 1, 'city' => $city];
            }
            arsort($collegeCounts);
            $rank = 1;
            foreach (array_slice($collegeCounts, 0, 8, true) as $name => $data):
            ?>
            <div style="display: flex; align-items: center; gap: 14px; padding: 14px 0; border-bottom: 1px solid var(--border);">
                <span style="font-family: var(--mono); font-size: 18px; font-weight: 800; width: 28px; color: <?= $rank <= 3 ? 'var(--accent)' : 'var(--text-muted)' ?>;">#<?= $rank ?></span>
                <div style="flex: 1;">
                    <div style="font-weight: 600; font-size: 14px;"><?= htmlspecialchars($name) ?></div>
                    <div style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($data['city']) ?></div>
                </div>
                <div style="background: var(--accent-light); padding: 6px 14px; border-radius: 20px; font-family: var(--mono); font-size: 14px; font-weight: 700; color: var(--accent);"><?= $data['count'] ?> students</div>
            </div>
            <?php $rank++; endforeach; ?>
            <?php if (empty($collegeCounts)): ?>
                <p style="text-align: center; color: var(--text-muted); padding: 20px;">Be the first from your college to join!</p>
            <?php endif; ?>
        </div>
        <div style="text-align: center; margin-top: 24px;">
            <a href="auth/login.php" class="btn-primary" style="display: inline-block; padding: 14px 32px; border-radius: 10px; font-weight: 700; font-size: 15px; text-decoration: none; background: var(--accent); color: #fff;">Join Your College →</a>
        </div>
    </div>
</section>

<!-- Prep Tips / Blog teaser -->
<section class="section" style="background: var(--bg);">
    <div class="section-inner">
        <h2 class="section-title">Free Prep Tips &amp; Guides</h2>
        <p class="section-sub">Actionable, no-fluff advice from the blog — exam patterns, study plans and mock strategy.</p>
        <div class="blog-teaser">
            <?php foreach (array_slice(blogPosts(), 0, 3) as $p): ?>
            <a class="bt-card" href="blog.php?post=<?= urlencode($p['slug']) ?>">
                <span class="bt-tag"><?= htmlspecialchars($p['category']) ?></span>
                <h3><?= htmlspecialchars($p['title']) ?></h3>
                <p><?= htmlspecialchars($p['excerpt']) ?></p>
                <span class="bt-more"><?= (int)$p['read'] ?> min read · Read article →</span>
            </a>
            <?php endforeach; ?>
        </div>
        <div class="blog-teaser-foot">
            <a href="blog.php" class="btn-ghost">View all articles</a>
        </div>
    </div>
</section>

<!-- FAQ -->
<?php
$faqs = [
    ['q' => 'Is DDCET Prep really free?',
     'a' => 'Yes. The core of the platform — the Daily Challenge, 70+ curated study resources, smart analytics, the community feed and college finder — is free forever. A Pro plan unlocks unlimited mocks and premium features, but you can prepare seriously without ever paying.'],
    ['q' => 'What is the DDCET exam pattern?',
     'a' => 'DDCET is a single objective paper of 100 questions for 200 marks (2 marks each) in 150 minutes, with a negative marking of -0.5 for every wrong answer. Questions are spread across Physics, Mathematics, English, Chemistry, Computers and Environment, with Physics and Maths making up more than half the paper. Our full mocks follow this exact blueprint.'],
    ['q' => 'When is the DDCET ' . $examDate->format('Y') . ' exam?',
     'a' => 'The exam is scheduled around ' . $examDate->format('F Y') . '. The live countdown at the top of this page tracks the exact time remaining. Always confirm the official date on the latest ACPDC / board notification.'],
    ['q' => 'Do the mock tests match the real exam?',
     'a' => 'They are built to mirror the real DDCET — same question count, same subject distribution, same 150-minute timer with auto-submit and instant scoring. The goal is for exam day to feel like just another practice run.'],
    ['q' => 'Do I need to install an app?',
     'a' => 'No. DDCET Prep runs in any browser on any phone or laptop — nothing to download. You can also "Add to Home Screen" to get an app-like icon and offline support, since the site is a Progressive Web App.'],
    ['q' => 'How do I get started?',
     'a' => 'Click "Start Free Prep", sign in with Google, and take your first free test in under a minute. Build a daily streak and let the analytics guide what to study next.'],
];
?>
<section class="section">
    <div class="section-inner">
        <h2 class="section-title">Frequently Asked Questions</h2>
        <p class="section-sub">Everything you need to know before you start preparing.</p>
        <div class="faq-wrap">
            <?php foreach ($faqs as $i => $f): ?>
            <details class="faq-item"<?= $i === 0 ? ' open' : '' ?>>
                <summary><?= htmlspecialchars($f['q']) ?>
                    <svg class="chev" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
                </summary>
                <div class="faq-a"><?= htmlspecialchars($f['a']) ?></div>
            </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<script type="application/ld+json">
<?= json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'FAQPage',
    'mainEntity' => array_map(fn($f) => [
        '@type' => 'Question',
        'name'  => $f['q'],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
    ], $faqs),
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
</script>

<!-- Final CTA -->
<section class="cta-section">
    <h2>Don't Wait Until It's Too Late</h2>
    <p>Only <?= $daysLeft ?> days left. Every day of preparation counts. Start now — it's free.</p>
    <a href="auth/login.php" class="btn-primary">Start Free Prep →</a>
</section>

<!-- Footer -->
<footer class="footer">
    <div style="max-width: 1100px; margin: 0 auto; padding: 0 32px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 32px; padding-bottom: 40px; border-bottom: 1px solid rgba(255,255,255,0.08);">
            <div>
                <div class="footer-brand"><img src="assets/logo.png" alt="" width="29" height="22" loading="lazy" decoding="async" style="height: 22px; width: auto; vertical-align: middle; margin-right: 6px;"><span>DDCET</span> Prep</div>
                <p style="margin-top: 12px; font-size: 13px; line-height: 1.8; color: rgba(255,255,255,0.5); max-width: 280px;">Gujarat's first and only platform built specifically for Diploma-to-Degree Common Entrance Test preparation.</p>
                <div style="display: flex; gap: 12px; margin-top: 16px;">
                    <a href="#" style="width: 36px; height: 36px; border-radius: 8px; background: rgba(255,255,255,0.06); display: flex; align-items: center; justify-content: center;"><svg width="16" height="16" fill="rgba(255,255,255,0.6)" viewBox="0 0 24 24"><path d="M22.46 6c-.77.35-1.6.58-2.46.69a4.3 4.3 0 001.88-2.38 8.59 8.59 0 01-2.72 1.04 4.28 4.28 0 00-7.32 3.91A12.16 12.16 0 013 4.79a4.28 4.28 0 001.32 5.72 4.24 4.24 0 01-1.94-.54v.05a4.28 4.28 0 003.43 4.2 4.27 4.27 0 01-1.93.07 4.29 4.29 0 004 2.97A8.59 8.59 0 012 19.54a12.13 12.13 0 006.56 1.92c7.88 0 12.2-6.53 12.2-12.2 0-.19 0-.37-.01-.56A8.72 8.72 0 0024 5.06a8.48 8.48 0 01-2.54.7z"/></svg></a>
                    <a href="#" style="width: 36px; height: 36px; border-radius: 8px; background: rgba(255,255,255,0.06); display: flex; align-items: center; justify-content: center;"><svg width="16" height="16" fill="rgba(255,255,255,0.6)" viewBox="0 0 24 24"><path d="M12 2.04c-5.5 0-10 4.49-10 10.02 0 5 3.66 9.15 8.44 9.9v-7H7.9v-2.9h2.54V9.85c0-2.51 1.49-3.89 3.78-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56v1.88h2.78l-.45 2.9h-2.33v7a10 10 0 008.44-9.9c0-5.53-4.5-10.02-10-10.02z"/></svg></a>
                    <a href="#" style="width: 36px; height: 36px; border-radius: 8px; background: rgba(255,255,255,0.06); display: flex; align-items: center; justify-content: center;"><svg width="16" height="16" fill="rgba(255,255,255,0.6)" viewBox="0 0 24 24"><path d="M12 2c2.717 0 3.056.01 4.122.06 1.065.05 1.79.217 2.428.465.66.254 1.216.598 1.772 1.153.509.5.902 1.105 1.153 1.772.247.637.415 1.363.465 2.428.047 1.066.06 1.405.06 4.122s-.01 3.056-.06 4.122c-.05 1.065-.218 1.79-.465 2.428a4.883 4.883 0 01-1.153 1.772c-.5.508-1.105.902-1.772 1.153-.637.247-1.363.415-2.428.465-1.066.047-1.405.06-4.122.06s-3.056-.01-4.122-.06c-1.065-.05-1.79-.218-2.428-.465a4.89 4.89 0 01-1.772-1.153 4.904 4.904 0 01-1.153-1.772c-.248-.637-.415-1.363-.465-2.428C2.013 15.056 2 14.717 2 12s.01-3.056.06-4.122c.05-1.066.217-1.79.465-2.428a4.88 4.88 0 011.153-1.772A4.897 4.897 0 015.45 2.525c.638-.248 1.362-.415 2.428-.465C8.944 2.013 9.283 2 12 2zm0 5a5 5 0 100 10 5 5 0 000-10zm0 8.25a3.25 3.25 0 110-6.5 3.25 3.25 0 010 6.5zm5.25-8.5a1.125 1.125 0 11-2.25 0 1.125 1.125 0 012.25 0z"/></svg></a>
                </div>
            </div>
            <div>
                <h4 style="color: #fff; font-size: 13px; font-weight: 700; margin-bottom: 16px; text-transform: uppercase; letter-spacing: 0.5px;">Platform</h4>
                <div class="footer-links" style="flex-direction: column; gap: 10px;">
                    <a href="syllabus.php">Syllabus</a>
                    <a href="roadmap.php">Roadmap</a>
                    <a href="blog.php">Blog</a>
                    <a href="about.php">About Us</a>
                    <a href="auth/login.php">Login</a>
                </div>
            </div>
            <div>
                <h4 style="color: #fff; font-size: 13px; font-weight: 700; margin-bottom: 16px; text-transform: uppercase; letter-spacing: 0.5px;">Exam Info</h4>
                <div class="footer-links" style="flex-direction: column; gap: 10px;">
                    <a href="syllabus.php">BE-01 Syllabus</a>
                    <a href="syllabus.php">BE-02 Syllabus</a>
                    <a href="roadmap.php">Preparation Tips</a>
                </div>
            </div>
            <div>
                <h4 style="color: #fff; font-size: 13px; font-weight: 700; margin-bottom: 16px; text-transform: uppercase; letter-spacing: 0.5px;">Support</h4>
                <div class="footer-links" style="flex-direction: column; gap: 10px;">
                    <a href="contact.php">Contact</a>
                    <a href="institutions.php">For Institutions</a>
                    <a href="privacy.php">Privacy Policy</a>
                    <a href="terms.php">Terms & Conditions</a>
                    <a href="refund.php">Refund Policy</a>
                </div>
            </div>
        </div>
        <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 24px; flex-wrap: wrap; gap: 12px;">
            <span style="font-size: 12px; color: rgba(255,255,255,0.4);">© <?= date('Y') ?> DDCET Prep. All rights reserved. &middot; Gujarat's dedicated DDCET preparation platform.</span>
            <div style="display: flex; gap: 16px; align-items: center;">
                <span style="font-size: 11px; color: rgba(255,255,255,0.3); display: flex; align-items: center; gap: 4px;"><svg width="10" height="10" fill="none" stroke="rgba(255,255,255,0.4)" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg> DDCET <?= (new DateTime(DDCET_EXAM_DATE))->format('Y') ?></span>
                <span style="font-size: 11px; padding: 4px 8px; background: rgba(67,97,238,0.2); color: rgba(67,97,238,0.9); border-radius: 4px; font-weight: 600;">v1.0</span>
            </div>
        </div>
    </div>
</footer>

<script>
// Countdown — target = DDCET exam date (unix seconds)
const examTs = <?= $examDate->getTimestamp() ?>;
(function () {
    const fields = {
        days:    document.querySelector('.fd-num[data-unit="days"]'),
        hours:   document.querySelector('.fd-num[data-unit="hours"]'),
        minutes: document.querySelector('.fd-num[data-unit="minutes"]'),
        seconds: document.querySelector('.fd-num[data-unit="seconds"]'),
    };
    if (!fields.days) return;
    const pad = n => String(n).padStart(2, '0');
    function tick() {
        let diff = examTs - Math.floor(Date.now() / 1000);
        if (diff < 0) diff = 0;
        const days = Math.floor(diff / 86400);
        const hours = Math.floor((diff % 86400) / 3600);
        const minutes = Math.floor((diff % 3600) / 60);
        const seconds = diff % 60;
        fields.days.textContent = pad(days);
        fields.hours.textContent = pad(hours);
        fields.minutes.textContent = pad(minutes);
        fields.seconds.textContent = pad(seconds);
    }
    tick();
    setInterval(tick, 1000);
})();

// Counter animation
const counters = document.querySelectorAll('.counter[data-target]');
const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const el = entry.target;
            const target = +el.dataset.target;
            let current = 0;
            const step = Math.max(1, Math.ceil(target / 60));
            const timer = setInterval(() => {
                current += step;
                if (current >= target) { current = target; clearInterval(timer); }
                el.textContent = current.toLocaleString();
            }, 20);
            observer.unobserve(el);
        }
    });
}, { threshold: 0.5 });
counters.forEach(c => observer.observe(c));

// Feature category tabs
document.querySelectorAll('.cat-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        const cat = tab.dataset.cat;
        document.querySelectorAll('.cat-tab').forEach(t => t.classList.toggle('active', t === tab));
        document.querySelectorAll('.cat-panel').forEach(p => p.classList.toggle('active', p.dataset.panel === cat));
    });
});

// Register the service worker (PWA / offline support, installable to home screen)
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('<?= BASE_PATH ?>sw.js', { scope: '<?= BASE_PATH ?>' }).catch(() => {});
    });
}

// Auto-close mobile menu when clicking anchor links
document.querySelectorAll('.nav-links a').forEach(link => {
    link.addEventListener('click', () => {
        document.querySelector('.nav-links').classList.remove('open');
    });
});
</script>
</body>
</html>
