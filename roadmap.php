<?php require_once __DIR__ . '/config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preparation Roadmap — DDCET Prep</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800;900&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #f8f9fa; --card: #ffffff; --accent: #4361ee; --blue: #4361ee; --green: #10b981; --orange: #f59e0b; --purple: #8b5cf6; --red: #ef4444; --text: #1a1a2e; --text-sec: #6c757d; --text-muted: #adb5bd; --border: #e9ecef; --radius: 12px; --font: 'DM Sans', sans-serif; --mono: 'DM Mono', monospace; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { background: var(--bg); color: var(--text); font-family: var(--font); line-height: 1.6; overflow-x: hidden; }
        a { color: var(--accent); text-decoration: none; }

        /* Scroll reveal */
        .reveal { opacity: 0; transform: translateY(28px); transition: opacity 0.7s ease, transform 0.7s ease; }
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

        .hero { padding: 90px 32px 50px; text-align: center; position: relative; overflow: hidden; }
        .hero::before { content: ''; position: absolute; top: -120px; left: 50%; transform: translateX(-50%); width: 700px; height: 400px; background: radial-gradient(ellipse, rgba(139,92,246,0.12), transparent 70%); z-index: 0; }
        .hero-content { position: relative; z-index: 1; }
        .hero-eyebrow { display: inline-flex; align-items: center; gap: 7px; background: var(--card); border: 1px solid var(--border); color: var(--purple); font-size: 12px; font-weight: 700; padding: 7px 16px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 20px; box-shadow: 0 4px 16px rgba(0,0,0,0.04); }
        .hero h1 { font-size: 46px; font-weight: 900; letter-spacing: -1.2px; margin-bottom: 12px; line-height: 1.1; }
        .hero h1 span { background: linear-gradient(135deg, var(--accent), var(--purple)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .hero p { color: var(--text-sec); font-size: 17px; max-width: 560px; margin: 0 auto; }

        /* Phase progress chips under hero */
        .phase-progress { display: flex; justify-content: center; gap: 10px; flex-wrap: wrap; margin-top: 28px; position: relative; z-index: 1; }
        .pp-chip { display: inline-flex; align-items: center; gap: 8px; background: var(--card); border: 1px solid var(--border); padding: 8px 16px; border-radius: 24px; font-size: 12px; font-weight: 600; color: var(--text-sec); }
        .pp-chip .pp-dot { width: 9px; height: 9px; border-radius: 50%; }

        /* Timeline */
        .timeline { max-width: 800px; margin: 60px auto 80px; padding: 0 32px; position: relative; }
        .timeline::before { content: ''; position: absolute; left: 50%; top: 0; bottom: 0; width: 3px; background: linear-gradient(to bottom, var(--accent), var(--purple), var(--green)); border-radius: 2px; transform: translateX(-50%); }
        .phase { position: relative; margin-bottom: 60px; display: grid; grid-template-columns: 1fr 1fr; gap: 60px; align-items: center; }
        .phase:nth-child(odd) .phase-content { grid-column: 1; text-align: right; }
        .phase:nth-child(odd) .phase-visual { grid-column: 2; }
        .phase:nth-child(even) .phase-content { grid-column: 2; }
        .phase:nth-child(even) .phase-visual { grid-column: 1; grid-row: 1; text-align: right; }
        .phase-dot { position: absolute; left: 50%; top: 20px; transform: translateX(-50%); width: 20px; height: 20px; border-radius: 50%; background: var(--card); border: 3px solid var(--accent); z-index: 2; }
        .phase-dot::after { content: ''; position: absolute; inset: 4px; border-radius: 50%; background: var(--accent); }
        .phase:nth-child(2) .phase-dot { border-color: var(--purple); }
        .phase:nth-child(2) .phase-dot::after { background: var(--purple); }
        .phase:nth-child(3) .phase-dot { border-color: var(--orange); }
        .phase:nth-child(3) .phase-dot::after { background: var(--orange); }
        .phase:nth-child(4) .phase-dot { border-color: var(--green); }
        .phase:nth-child(4) .phase-dot::after { background: var(--green); }

        .phase-content h3 { font-size: 20px; font-weight: 700; margin-bottom: 6px; }
        .phase-content .duration { font-family: var(--mono); font-size: 12px; color: var(--accent); font-weight: 600; margin-bottom: 8px; }
        .phase-content p { font-size: 14px; color: var(--text-sec); }
        .phase-content ul { list-style: none; margin-top: 12px; }
        .phase-content ul li { font-size: 13px; color: var(--text-sec); padding: 4px 0; display: flex; align-items: center; gap: 8px; }
        .phase:nth-child(odd) .phase-content ul li { justify-content: flex-end; }
        .phase-content ul li::before { content: ''; display: inline-block; width: 14px; height: 14px; background: var(--green); mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M20 6L9 17l-5-5'/%3E%3C/svg%3E") center/contain no-repeat; -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M20 6L9 17l-5-5'/%3E%3C/svg%3E") center/contain no-repeat; flex-shrink: 0; }

        .phase-visual .card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px; display: inline-block; max-width: 100%; }
        .phase-visual .subject-tags { display: flex; gap: 6px; flex-wrap: wrap; }
        .phase:nth-child(even) .phase-visual .subject-tags { justify-content: flex-end; }
        .tag { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; }
        .tag-blue { background: rgba(67,97,238,0.1); color: var(--accent); }
        .tag-green { background: rgba(16,185,129,0.1); color: var(--green); }
        .tag-orange { background: rgba(245,158,11,0.1); color: var(--orange); }
        .tag-purple { background: rgba(139,92,246,0.1); color: var(--purple); }

        /* Exam Pattern */
        .pattern-section { background: var(--card); border-top: 1px solid var(--border); padding: 80px 32px; }
        .pattern-section h2 { font-size: 32px; font-weight: 800; text-align: center; margin-bottom: 40px; }
        .pattern-grid { max-width: 900px; margin: 0 auto; display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
        .pattern-card { background: var(--bg); border-radius: var(--radius); padding: 24px; border: 1px solid var(--border); }
        .pattern-card h4 { font-size: 15px; margin-bottom: 4px; }
        .pattern-card .marks { font-family: var(--mono); font-size: 24px; font-weight: 700; color: var(--accent); }
        .pattern-card p { font-size: 12px; color: var(--text-sec); margin-top: 6px; }

        /* CTA */
        .cta { padding: 80px 32px; text-align: center; }
        .cta h2 { font-size: 28px; font-weight: 800; margin-bottom: 12px; }
        .cta p { color: var(--text-sec); margin-bottom: 24px; }
        .btn-lg { background: var(--accent); color: #fff; padding: 16px 40px; border-radius: 10px; font-weight: 700; font-size: 16px; display: inline-block; transition: all 0.2s; }
        .btn-lg:hover { transform: translateY(-2px); box-shadow: 0 12px 32px rgba(67,97,238,0.3); }

        .footer { background: #1a1a2e; padding: 32px; text-align: center; color: rgba(255,255,255,0.5); font-size: 13px; }
        .footer a { color: rgba(255,255,255,0.7); }

        @media (max-width: 768px) {
            .timeline::before { left: 20px; }
            .phase { grid-template-columns: 1fr; gap: 16px; padding-left: 50px; }
            .phase-dot { left: 20px; }
            .phase:nth-child(odd) .phase-content, .phase:nth-child(even) .phase-content { grid-column: 1; text-align: left; }
            .phase:nth-child(odd) .phase-content ul li { justify-content: flex-start; }
            .phase:nth-child(even) .phase-visual { grid-column: 1; grid-row: auto; text-align: left; }
            .phase:nth-child(even) .phase-visual .subject-tags { justify-content: flex-start; }
            .phase-visual .card { width: 100%; }
            .pattern-grid { grid-template-columns: 1fr; }
            .hero h1 { font-size: 28px; }
            .hamburger { display: block; }
            .nav-links { display: none; position: absolute; top: 100%; left: 0; right: 0; background: var(--card); border-bottom: 1px solid var(--border); flex-direction: column; padding: 16px 24px; gap: 16px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); }
            .nav-links.open { display: flex; }
        }
    </style>
</head>
<body>

<nav class="nav">
    <div class="nav-inner">
        <a href="<?= BASE_PATH ?>" class="nav-brand"><img src="assets/logo.png" alt="" style="height: 24px; vertical-align: middle; margin-right: 6px;"><span>DDCET</span> Prep</a>
        <button class="hamburger" onclick="document.querySelector('.nav-links').classList.toggle('open')" aria-label="Menu">
            <svg width="24" height="24" fill="none" stroke="var(--text)" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
        </button>
        <div class="nav-links">
            <a href="<?= BASE_PATH ?>">Home</a>
            <a href="syllabus.php">Syllabus</a>
            <a href="blog.php">Blog</a>
            <a href="about.php">About</a>
            <a href="auth/login.php" class="btn-sm">Start Prep →</a>
        </div>
    </div>
</nav>

<section class="hero">
    <div class="hero-content">
        <span class="hero-eyebrow"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg> Proven 4-Phase Strategy</span>
        <h1>Your <span>DDCET Roadmap</span></h1>
        <p>A step-by-step plan to go from zero to exam-ready. Follow this path and maximize your score.</p>
        <div class="phase-progress">
            <span class="pp-chip"><span class="pp-dot" style="background:var(--accent);"></span> Foundations</span>
            <span class="pp-chip"><span class="pp-dot" style="background:var(--purple);"></span> Practice & Speed</span>
            <span class="pp-chip"><span class="pp-dot" style="background:var(--orange);"></span> Full Mocks</span>
            <span class="pp-chip"><span class="pp-dot" style="background:var(--green);"></span> Final Revision</span>
        </div>
    </div>
</section>

<section class="timeline">
    <!-- Phase 1 -->
    <div class="phase reveal">
        <div class="phase-dot"></div>
        <div class="phase-content">
            <div class="duration">PHASE 1 — Month 1-2</div>
            <h3>Build Foundations</h3>
            <p>Master the basics of each subject. Focus on understanding concepts, not memorizing.</p>
            <ul>
                <li>Complete NCERT-level basics</li>
                <li>Watch curated YouTube lectures</li>
                <li>Make formula sheets</li>
                <li>Take subject-wise tests</li>
            </ul>
        </div>
        <div class="phase-visual">
            <div class="card">
                <div style="font-size: 11px; color: var(--text-muted); margin-bottom: 8px;">Focus Subjects</div>
                <div class="subject-tags">
                    <span class="tag tag-blue">Physics</span>
                    <span class="tag tag-green">Chemistry</span>
                    <span class="tag tag-orange">Maths</span>
                    <span class="tag tag-purple">English</span>
                </div>
                <div style="margin-top: 12px; font-family: var(--mono); font-size: 12px; color: var(--green);">Target: 40% accuracy</div>
            </div>
        </div>
    </div>

    <!-- Phase 2 -->
    <div class="phase reveal">
        <div class="phase-dot"></div>
        <div class="phase-content">
            <div class="duration" style="color: var(--purple);">PHASE 2 — Month 3-4</div>
            <h3>Practice & Speed</h3>
            <p>Shift to timed practice. Use rapid-fire mode to build speed. Focus on weak subjects.</p>
            <ul>
                <li>Daily rapid fire sessions</li>
                <li>Identify weak topics via analytics</li>
                <li>Attempt previous year papers</li>
                <li>Join the community for tips</li>
            </ul>
        </div>
        <div class="phase-visual">
            <div class="card">
                <div style="font-size: 11px; color: var(--text-muted); margin-bottom: 8px;">Speed Goals</div>
                <div style="display: flex; gap: 12px; margin-top: 8px;">
                    <div style="text-align: center;"><div style="font-family: var(--mono); font-size: 20px; font-weight: 700; color: var(--purple);">60s</div><div style="font-size: 10px; color: var(--text-muted);">per MCQ</div></div>
                    <div style="text-align: center;"><div style="font-family: var(--mono); font-size: 20px; font-weight: 700; color: var(--purple);">30</div><div style="font-size: 10px; color: var(--text-muted);">daily Qs</div></div>
                </div>
                <div style="margin-top: 12px; font-family: var(--mono); font-size: 12px; color: var(--purple);">Target: 65% accuracy</div>
            </div>
        </div>
    </div>

    <!-- Phase 3 -->
    <div class="phase reveal">
        <div class="phase-dot"></div>
        <div class="phase-content">
            <div class="duration" style="color: var(--orange);">PHASE 3 — Month 5</div>
            <h3>Full Mocks & Analysis</h3>
            <p>Take full-length mock tests under real conditions. Analyze every mistake. No new topics.</p>
            <ul>
                <li>2 full mocks per week</li>
                <li>Detailed post-test analysis</li>
                <li>Revision mode for wrong Qs</li>
                <li>Compete on leaderboard</li>
            </ul>
        </div>
        <div class="phase-visual">
            <div class="card">
                <div style="font-size: 11px; color: var(--text-muted); margin-bottom: 8px;">Mock Schedule</div>
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <div style="display: flex; align-items: center; gap: 8px;"><div style="width: 8px; height: 8px; border-radius: 50%; background: var(--orange);"></div><span style="font-size: 12px;">Mon/Thu — Full Mock</span></div>
                    <div style="display: flex; align-items: center; gap: 8px;"><div style="width: 8px; height: 8px; border-radius: 50%; background: var(--blue);"></div><span style="font-size: 12px;">Tue/Fri — Subject Test</span></div>
                    <div style="display: flex; align-items: center; gap: 8px;"><div style="width: 8px; height: 8px; border-radius: 50%; background: var(--green);"></div><span style="font-size: 12px;">Daily — Rapid Fire</span></div>
                </div>
                <div style="margin-top: 12px; font-family: var(--mono); font-size: 12px; color: var(--orange);">Target: 80% accuracy</div>
            </div>
        </div>
    </div>

    <!-- Phase 4 -->
    <div class="phase reveal">
        <div class="phase-dot"></div>
        <div class="phase-content">
            <div class="duration" style="color: var(--green);">PHASE 4 — Last 2 Weeks</div>
            <h3>Final Revision & Confidence</h3>
            <p>Light revision only. Focus on formulas, flashcards, and maintaining confidence. No new material.</p>
            <ul>
                <li>Flashcard revision daily</li>
                <li>1 light mock per 3 days</li>
                <li>Focus on bookmarked Qs</li>
                <li>Rest well, stay confident</li>
            </ul>
        </div>
        <div class="phase-visual">
            <div class="card">
                <div style="font-size: 11px; color: var(--text-muted); margin-bottom: 8px;">Final Checklist</div>
                <div style="font-size: 13px; line-height: 2;">
                    <div><svg width="14" height="14" fill="none" stroke="var(--green)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" style="vertical-align: -2px; margin-right: 4px;"><path d="M20 6L9 17l-5-5"/></svg>All formulas revised</div>
                    <div><svg width="14" height="14" fill="none" stroke="var(--green)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" style="vertical-align: -2px; margin-right: 4px;"><path d="M20 6L9 17l-5-5"/></svg>Weak topics practiced</div>
                    <div><svg width="14" height="14" fill="none" stroke="var(--green)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" style="vertical-align: -2px; margin-right: 4px;"><path d="M20 6L9 17l-5-5"/></svg>Exam hall strategy ready</div>
                    <div><svg width="14" height="14" fill="none" stroke="var(--green)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" style="vertical-align: -2px; margin-right: 4px;"><path d="M20 6L9 17l-5-5"/></svg>Time management sorted</div>
                </div>
                <div style="margin-top: 12px; font-family: var(--mono); font-size: 12px; color: var(--green);">Target: 85%+</div>
            </div>
        </div>
    </div>
</section>

<!-- Exam Pattern -->
<section class="pattern-section reveal">
    <h2>DDCET Exam Pattern (BE-01 & BE-02)</h2>
    <div class="pattern-grid">
        <div class="pattern-card">
            <h4>BE-01 — Basics of Science &amp; Engineering</h4>
            <div class="marks">50 Qs · 100 marks</div>
            <p>Physics (60%), Chemistry (20%), Computer Practice (10%) and Environmental Sciences (10%).</p>
        </div>
        <div class="pattern-card">
            <h4>BE-02 — Aptitude Test (Maths &amp; Soft Skill)</h4>
            <div class="marks">50 Qs · 100 marks</div>
            <p>Mathematics (50%) and Soft Skill / English (50%) — comprehension, communication, grammar &amp; writing.</p>
        </div>
    </div>
    <div style="text-align: center; margin-top: 24px;">
        <div style="display: inline-block; background: rgba(67,97,238,0.08); padding: 12px 24px; border-radius: 8px; font-size: 14px; color: var(--accent); font-weight: 600;">Total: 100 Qs · 200 marks · 2 marks each · 150 min (2½ hrs) · -0.5 negative marking</div>
        <div style="margin-top: 14px;"><a href="syllabus.php" style="font-size: 14px; font-weight: 700;">View the full official syllabus →</a></div>
    </div>
</section>

<!-- CTA -->
<section class="cta">
    <h2>Ready to Start Your Journey?</h2>
    <p>Follow this roadmap on our platform with guided tests, analytics, and community support.</p>
    <a href="auth/login.php" class="btn-lg">Start Free Prep →</a>
</section>

<footer class="footer">
    <a href="<?= BASE_PATH ?>">Home</a> · <a href="about.php">About</a> · <a href="auth/login.php">Login</a>
    <div style="margin-top: 12px;">© <?= date('Y') ?> DDCET Prep</div>
</footer>

<script>
// Scroll reveal
const revObserver = new IntersectionObserver((entries) => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); revObserver.unobserve(e.target); } });
}, { threshold: 0.12 });
document.querySelectorAll('.reveal').forEach(el => revObserver.observe(el));
</script>

</body>
</html>
