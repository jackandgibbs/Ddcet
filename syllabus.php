<?php
require_once __DIR__ . '/config.php';

/**
 * Official GTU DDCET (Engineering) syllabus — published 18.01.2024.
 * Source of truth: assets/ddcet-syllabus.pdf. Weightages are the official %.
 */
$be01 = [
    ['Units and Measurement', 12, 'Physical quantities and units; interconversion MKS (SI) ↔ CGS; errors, estimation of error, relative & percentage error, propagation of errors; measurement with Vernier caliper and micrometer screw gauge.'],
    ['Classical Mechanics', 12, 'Linear motion, velocity, acceleration, force, Newton\'s laws, linear momentum and impulse; circular motion, angular velocity & acceleration, centripetal and centrifugal force; work, energy, kinetic & potential energy, power.'],
    ['Electric Current', 12, 'Ohm\'s law and applications; charge, interaction of charges, Coulomb\'s force; electric field, potential, flux, current; resistance, conductance, resistivity, conductivity, series & parallel resistors; capacitance and parallel plate capacitors.'],
    ['Heat and Thermometry', 12, 'Modes of heat transfer; temperature scales and conversions (Kelvin–Celsius, Kelvin–Fahrenheit, Fahrenheit–Celsius); heat capacity and specific heat; thermal conductivity and linear thermal expansion.'],
    ['Wave Motion, Optics and Acoustics', 12, 'Types of waves; frequency, wavelength, periodic time; electromagnetic waves (light, LASER) and sound waves; amplitude, intensity, phase, wave equations; reflection, refraction, Snell\'s law, refractive index, total internal reflection, optical fibre; reverberation, Sabine\'s formula, echo.'],
    ['Chemical Reactions and Equations', 6, 'Writing and balancing chemical equations; types of reactions — combination, decomposition, displacement and double displacement.'],
    ['Acids, Bases and Salts', 8, 'Chemical properties of acids and bases; reaction of metallic oxides with acids; acids/bases in water solutions; importance of pH in everyday life; salts — family and pH.'],
    ['Metals and Non-metals', 6, 'Physical properties of metals and non-metals; chemical properties of metals; ionic compounds; corrosion of metals.'],
    ['Computer Practice', 10, 'Basics of computer systems; introduction to the Internet and HTML; using MS Word, MS Excel and MS PowerPoint.'],
    ['Environmental Sciences', 10, 'Ecosystem; pollution and its types; climate change; renewable energy sources — hydro, solar, wind, bio-mass, tidal and geothermal — their availability and limitations.'],
];

$be02 = [
    ['Determinant and Matrices', 8, 'Determinants up to 3rd order; matrices and their types; addition, subtraction and scalar multiplication; product of two matrices; adjoint and inverse (2×2); solving simultaneous linear equations in two variables.'],
    ['Trigonometry', 6, 'Units of angles (degree & radian); trigonometric functions and their periods; allied, compound, multiple and submultiple angles; sum and factor formulae.'],
    ['Vectors', 4, 'Introduction, direction and magnitude; types of vectors; addition and subtraction; scalar (dot) and vector (cross) products; angle between two vectors.'],
    ['Coordinate Geometry', 4, 'Slope of a line; equation of a line (two-point, slope-point, intercept, general form); parallel & perpendicular lines; angle between two lines; equation of a circle, centre and radius.'],
    ['Function & Limit', 6, 'Functions and simple examples; limit of a function; standard formulae of limits with simple examples.'],
    ['Differentiation and its Applications', 8, 'Concept of differentiation; sum, product, quotient and chain rules; implicit & parametric functions; logarithmic and successive differentiation; applications — velocity and acceleration.'],
    ['Integration', 8, 'Concept of integration; integrals of standard functions; substitution and integration by parts; definite integrals (simple examples).'],
    ['Logarithm', 4, 'Logarithm as a function; laws of logarithms with simple examples.'],
    ['Statistics', 2, 'Mean, median and mode for ungrouped data.'],
    ['Comprehension of Unseen Passage', 10, 'An unseen passage followed by 5 questions — read and answer.'],
    ['Theory of Communication', 10, 'Process of communication; verbal and non-verbal communication; barriers to communication.'],
    ['Techniques of Writing', 10, 'Parts of a letter and email; types of business letters (inquiry, reply, order, complaint, adjustment); common business abbreviations.'],
    ['Grammar', 10, 'Parts of speech; tenses; subject–verb agreement.'],
    ['Correction of Incorrect Words and Sentences', 10, 'Word correction (correct spelling) and sentence correction (grammatically/structurally correct sentence).'],
];

$be01Total = array_sum(array_column($be01, 1));
$be02Total = array_sum(array_column($be02, 1));

$pageTitle = 'DDCET Syllabus 2026 — BE-01 & BE-02 Topics & Weightage';
$pageDesc  = 'Complete official GTU DDCET (Engineering) syllabus: BE-01 Basics of Science & Engineering and BE-02 Aptitude Test (Mathematics & Soft Skill), with topic-wise weightage and the exam pattern.';
$canonical = APP_URL . '/syllabus.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDesc) ?>">
    <link rel="canonical" href="<?= htmlspecialchars($canonical) ?>">
    <meta name="theme-color" content="#4361ee">
    <meta property="og:type" content="article">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDesc) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($canonical) ?>">
    <meta property="og:image" content="<?= APP_URL ?>/assets/icon-512.png">

    <link rel="icon" href="assets/favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
    <link rel="manifest" href="<?= BASE_PATH ?>manifest.webmanifest">

    <link rel="preload" href="assets/fonts/dmsans.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="assets/fonts/fonts.css">
    <style>
        :root { --bg: #f8f9fa; --card: #ffffff; --accent: #4361ee; --green: #10b981; --orange: #f59e0b; --purple: #8b5cf6; --red: #ef4444; --text: #1a1a2e; --text-sec: #6c757d; --text-muted: #adb5bd; --border: #e9ecef; --radius: 12px; --font: 'DM Sans', sans-serif; --mono: 'DM Mono', monospace; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { background: var(--bg); color: var(--text); font-family: var(--font); line-height: 1.6; overflow-x: hidden; }
        a { color: var(--accent); text-decoration: none; }
        .reveal { opacity: 0; transform: translateY(24px); transition: opacity .6s ease, transform .6s ease; }
        .reveal.in { opacity: 1; transform: translateY(0); }

        .nav { background: rgba(255,255,255,0.9); backdrop-filter: blur(20px); border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; }
        .nav-inner { max-width: 1100px; margin: 0 auto; padding: 16px 32px; display: flex; align-items: center; justify-content: space-between; }
        .nav-brand { font-size: 20px; font-weight: 800; color: var(--text); }
        .nav-brand span { color: var(--accent); }
        .nav-links { display: flex; gap: 24px; align-items: center; }
        .nav-links a { color: var(--text-sec); font-size: 14px; font-weight: 500; }
        .nav-links a:hover { color: var(--accent); }
        .btn-sm { background: var(--accent); color: #fff !important; padding: 8px 20px; border-radius: 8px; font-weight: 600; font-size: 13px; }
        .hamburger { display: none; background: none; border: none; cursor: pointer; padding: 8px; }

        .hero { padding: 64px 32px 36px; text-align: center; position: relative; overflow: hidden; }
        .hero::before { content: ''; position: absolute; top: -120px; left: 50%; transform: translateX(-50%); width: 700px; height: 380px; background: radial-gradient(ellipse, rgba(67,97,238,0.12), transparent 70%); z-index: 0; }
        .hero-content { position: relative; z-index: 1; max-width: 760px; margin: 0 auto; }
        .hero-eyebrow { display: inline-flex; align-items: center; gap: 7px; background: var(--card); border: 1px solid var(--border); color: var(--accent); font-size: 12px; font-weight: 700; padding: 7px 16px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 20px; box-shadow: 0 4px 16px rgba(0,0,0,0.04); }
        .hero h1 { font-size: 38px; font-weight: 900; letter-spacing: -1px; margin-bottom: 14px; line-height: 1.14; }
        .hero h1 span { background: linear-gradient(135deg, var(--accent), var(--purple)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .hero p { color: var(--text-sec); font-size: 16px; max-width: 620px; margin: 0 auto 24px; }
        .hero-actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
        .btn-primary { background: var(--accent); color: #fff; padding: 13px 28px; border-radius: 10px; font-weight: 700; font-size: 14px; display: inline-block; }
        .btn-ghost { background: var(--card); border: 1px solid var(--border); color: var(--text); padding: 13px 28px; border-radius: 10px; font-weight: 700; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; }

        /* Pattern summary */
        .pattern { max-width: 900px; margin: 0 auto; padding: 12px 32px 8px; }
        .pattern-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .pcard { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 22px; }
        .pcard .tag { font-size: 11px; font-weight: 800; letter-spacing: 0.5px; text-transform: uppercase; color: var(--accent); }
        .pcard h3 { font-size: 17px; font-weight: 800; margin: 6px 0 4px; }
        .pcard .meta { font-family: var(--mono); font-size: 13px; color: var(--text-sec); }
        .pattern-foot { text-align: center; margin-top: 16px; }
        .pattern-foot span { display: inline-block; background: rgba(67,97,238,0.08); padding: 10px 22px; border-radius: 8px; font-size: 14px; color: var(--accent); font-weight: 700; }

        /* Syllabus tables */
        .wrap { max-width: 900px; margin: 0 auto; padding: 32px; }
        .sec-head { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; margin: 40px 0 6px; flex-wrap: wrap; }
        .sec-head h2 { font-size: 24px; font-weight: 800; }
        .sec-head .sub { color: var(--text-sec); font-size: 14px; }
        .sec-head .pill { font-family: var(--mono); font-size: 12px; font-weight: 700; color: var(--accent); background: rgba(67,97,238,0.08); padding: 4px 12px; border-radius: 20px; }
        .syl { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
        .syl-row { display: grid; grid-template-columns: 1fr auto; gap: 14px 18px; padding: 18px 22px; border-bottom: 1px solid var(--border); }
        .syl-row:last-child { border-bottom: none; }
        .syl-row .t { font-size: 15px; font-weight: 700; }
        .syl-row .s { grid-column: 1 / -1; font-size: 13.5px; color: var(--text-sec); line-height: 1.65; }
        .wt { align-self: start; font-family: var(--mono); font-size: 13px; font-weight: 700; color: var(--accent); white-space: nowrap; background: rgba(67,97,238,0.07); padding: 4px 10px; border-radius: 8px; height: fit-content; }
        .note { font-size: 12.5px; color: var(--text-muted); margin-top: 14px; text-align: center; }

        .footer { background: #1a1a2e; padding: 32px; text-align: center; color: rgba(255,255,255,0.5); font-size: 13px; }
        .footer a { color: rgba(255,255,255,0.7); }

        @media (max-width: 768px) {
            .hero h1 { font-size: 27px; }
            .pattern-grid { grid-template-columns: 1fr; }
            .hamburger { display: block; }
            .nav-links { display: none; position: absolute; top: 100%; left: 0; right: 0; background: var(--card); border-bottom: 1px solid var(--border); flex-direction: column; padding: 16px 24px; gap: 16px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); }
            .nav-links.open { display: flex; }
        }
    </style>
    <script type="application/ld+json">
    <?= json_encode([
        '@context' => 'https://schema.org',
        '@type'    => 'Course',
        'name'     => 'DDCET (Engineering) Syllabus',
        'description' => $pageDesc,
        'provider' => ['@type' => 'Organization', 'name' => 'Gujarat Technological University', 'sameAs' => 'https://www.gtu.ac.in'],
        'url'      => $canonical,
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
    </script>
</head>
<body>

<nav class="nav">
    <div class="nav-inner">
        <a href="<?= BASE_PATH ?>" class="nav-brand"><img src="assets/logo.png" alt="" width="24" height="18" style="height:24px;width:auto;vertical-align:middle;margin-right:6px;"><span>DDCET</span> Prep</a>
        <button class="hamburger" onclick="document.querySelector('.nav-links').classList.toggle('open')" aria-label="Menu">
            <svg width="24" height="24" fill="none" stroke="var(--text)" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
        </button>
        <div class="nav-links">
            <a href="<?= BASE_PATH ?>">Home</a>
            <a href="syllabus.php">Syllabus</a>
            <a href="blog.php">Blog</a>
            <a href="roadmap.php">Roadmap</a>
            <a href="auth/login.php" class="btn-sm">Start Prep →</a>
        </div>
    </div>
</nav>

<section class="hero">
    <div class="hero-content">
        <span class="hero-eyebrow">Official GTU Syllabus · Published 18.01.2024</span>
        <h1>DDCET <span>Syllabus &amp; Exam Pattern</span></h1>
        <p>The complete official syllabus for Gujarat's Diploma-to-Degree Common Entrance Test — every topic across BE-01 and BE-02, with its exam weightage.</p>
        <div class="hero-actions">
            <a href="auth/login.php" class="btn-primary">Start Free Prep →</a>
            <a href="assets/ddcet-syllabus.pdf" class="btn-ghost" target="_blank" rel="noopener">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg>
                Download PDF
            </a>
        </div>
    </div>
</section>

<!-- Exam pattern summary -->
<section class="pattern reveal">
    <div class="pattern-grid">
        <div class="pcard">
            <div class="tag">Paper BE-01</div>
            <h3>Basics of Science &amp; Engineering</h3>
            <div class="meta">50 questions · 100 marks</div>
        </div>
        <div class="pcard">
            <div class="tag">Paper BE-02</div>
            <h3>Aptitude Test — Mathematics &amp; Soft Skill</h3>
            <div class="meta">50 questions · 100 marks</div>
        </div>
    </div>
    <div class="pattern-foot">
        <span>Total: 100 questions · 200 marks · 2 marks each · 150 minutes (2½ hours)</span>
    </div>
</section>

<div class="wrap">
    <!-- BE-01 -->
    <div class="sec-head reveal">
        <div>
            <h2>Section 01 — BE-01</h2>
            <div class="sub">Basics of Science &amp; Engineering &nbsp;·&nbsp; Physics, Chemistry, Computer Practice &amp; Environmental Sciences</div>
        </div>
        <span class="pill"><?= $be01Total ?>% total</span>
    </div>
    <div class="syl reveal">
        <?php foreach ($be01 as $i => [$topic, $wt, $subs]): ?>
        <div class="syl-row">
            <div class="t"><?= ($i + 1) . '. ' . htmlspecialchars($topic) ?></div>
            <div class="wt"><?= $wt ?>%</div>
            <div class="s"><?= htmlspecialchars($subs) ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- BE-02 -->
    <div class="sec-head reveal">
        <div>
            <h2>Section 02 — BE-02</h2>
            <div class="sub">Aptitude Test &nbsp;·&nbsp; Mathematics (50%) &amp; Soft Skill / English (50%)</div>
        </div>
        <span class="pill"><?= $be02Total ?>% total</span>
    </div>
    <div class="syl reveal">
        <?php foreach ($be02 as $i => [$topic, $wt, $subs]): ?>
        <div class="syl-row">
            <div class="t"><?= ($i + 1) . '. ' . htmlspecialchars($topic) ?></div>
            <div class="wt"><?= $wt ?>%</div>
            <div class="s"><?= htmlspecialchars($subs) ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <p class="note">Source: official GTU DDCET (Engineering) syllabus, published 18.01.2024. References: Std-10 Science (State/NCERT), AICTE Model Curriculum 2019. Always confirm against the latest GTU notification.</p>
</div>

<footer class="footer">
    <a href="<?= BASE_PATH ?>">Home</a> · <a href="syllabus.php">Syllabus</a> · <a href="blog.php">Blog</a> · <a href="roadmap.php">Roadmap</a> · <a href="auth/login.php">Login</a>
    <div style="margin-top: 12px;">© <?= date('Y') ?> DDCET Prep. All rights reserved. &middot; Gujarat's dedicated DDCET preparation platform.</div>
</footer>

<script>
const revObserver = new IntersectionObserver((entries) => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); revObserver.unobserve(e.target); } });
}, { threshold: 0.1 });
document.querySelectorAll('.reveal').forEach(el => revObserver.observe(el));
</script>

</body>
</html>
