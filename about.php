<?php require_once __DIR__ . '/config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About — DDCET Prep</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800;900&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #f8f9fa; --card: #ffffff; --accent: #4361ee; --green: #10b981; --orange: #f59e0b; --purple: #8b5cf6; --red: #ef4444; --text: #1a1a2e; --text-sec: #6c757d; --text-muted: #adb5bd; --border: #e9ecef; --radius: 12px; --font: 'DM Sans', sans-serif; --mono: 'DM Mono', monospace; }
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

        .hero { padding: 90px 32px 70px; text-align: center; position: relative; overflow: hidden; }
        .hero::before { content: ''; position: absolute; top: -120px; left: 50%; transform: translateX(-50%); width: 700px; height: 400px; background: radial-gradient(ellipse, rgba(67,97,238,0.12), transparent 70%); z-index: 0; }
        .hero-content { position: relative; z-index: 1; max-width: 800px; margin: 0 auto; }
        .hero-eyebrow { display: inline-flex; align-items: center; gap: 7px; background: var(--card); border: 1px solid var(--border); color: var(--accent); font-size: 12px; font-weight: 700; padding: 7px 16px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 20px; box-shadow: 0 4px 16px rgba(0,0,0,0.04); }
        .hero-eyebrow .live-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--green); animation: blink 1.5s ease-in-out infinite; }
        @keyframes blink { 0%,100% { opacity: 1; } 50% { opacity: 0.3; } }
        .hero h1 { font-size: 46px; font-weight: 900; letter-spacing: -1.2px; margin-bottom: 16px; line-height: 1.1; }
        .hero h1 span { background: linear-gradient(135deg, var(--accent), var(--purple)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .hero p { color: var(--text-sec); font-size: 18px; line-height: 1.8; max-width: 620px; margin: 0 auto; }

        .section { padding: 60px 32px; max-width: 1000px; margin: 0 auto; }
        .section h2 { font-size: 28px; font-weight: 800; margin-bottom: 20px; }

        /* Story */
        .story { display: grid; grid-template-columns: 1fr 1fr; gap: 60px; align-items: center; padding: 60px 32px; max-width: 1100px; margin: 0 auto; }
        .story-text h2 { font-size: 32px; font-weight: 800; margin-bottom: 16px; }
        .story-text p { color: var(--text-sec); font-size: 15px; line-height: 1.8; margin-bottom: 16px; }
        .story-visual { position: relative; }
        .story-card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 24px; box-shadow: 0 8px 32px rgba(0,0,0,0.06); }
        .story-card .emoji { font-size: 48px; margin-bottom: 12px; }
        .story-card blockquote { font-style: italic; color: var(--text-sec); font-size: 15px; line-height: 1.7; border-left: 3px solid var(--accent); padding-left: 16px; }

        /* Values */
        .values-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 32px; }
        .value-card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 28px; text-align: center; transition: transform 0.2s; }
        .value-card:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(0,0,0,0.06); }
        .value-card .icon { font-size: 32px; margin-bottom: 12px; }
        .value-card h3 { font-size: 16px; font-weight: 700; margin-bottom: 6px; }
        .value-card p { font-size: 13px; color: var(--text-sec); }

        /* Trust */
        .trust { background: var(--card); border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); padding: 60px 32px; }
        .trust-inner { max-width: 900px; margin: 0 auto; text-align: center; }
        .trust h2 { font-size: 28px; font-weight: 800; margin-bottom: 32px; }
        .trust-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; }
        .trust-item { padding: 20px; }
        .trust-item .big { font-family: var(--mono); font-size: 36px; font-weight: 700; color: var(--accent); }
        .trust-item .desc { font-size: 12px; color: var(--text-sec); margin-top: 4px; }

        /* Team */
        .team-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 32px; }
        .team-card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 28px; text-align: center; }
        .team-avatar { width: 64px; height: 64px; border-radius: 50%; background: linear-gradient(135deg, var(--accent), var(--purple)); display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; font-size: 24px; font-weight: 700; color: #fff; }
        .team-card h4 { font-size: 15px; font-weight: 700; margin-bottom: 2px; }
        .team-card .role { font-size: 12px; color: var(--accent); font-weight: 600; }
        .team-card p { font-size: 12px; color: var(--text-sec); margin-top: 8px; }

        /* Tech Stack */
        .tech { margin-top: 40px; }
        .tech-pills { display: flex; gap: 10px; flex-wrap: wrap; justify-content: center; margin-top: 16px; }
        .pill { background: var(--card); border: 1px solid var(--border); padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 500; color: var(--text-sec); }

        .cta { padding: 80px 32px; text-align: center; background: linear-gradient(135deg, #1a1a2e 0%, #2d1b69 100%); }
        .cta h2 { color: #fff; font-size: 32px; font-weight: 800; margin-bottom: 12px; }
        .cta p { color: rgba(255,255,255,0.7); margin-bottom: 24px; }
        .btn-lg { background: var(--accent); color: #fff; padding: 16px 40px; border-radius: 10px; font-weight: 700; font-size: 16px; display: inline-block; transition: all 0.2s; }
        .btn-lg:hover { transform: translateY(-2px); box-shadow: 0 12px 32px rgba(67,97,238,0.4); }

        .footer { background: #1a1a2e; padding: 32px; text-align: center; color: rgba(255,255,255,0.5); font-size: 13px; }
        .footer a { color: rgba(255,255,255,0.7); }

        @media (max-width: 768px) {
            .story { grid-template-columns: 1fr; gap: 32px; }
            .values-grid, .team-grid { grid-template-columns: 1fr; }
            .trust-grid { grid-template-columns: repeat(2, 1fr); }
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
            <a href="roadmap.php">Roadmap</a>
            <a href="auth/login.php" class="btn-sm">Start Prep →</a>
        </div>
    </div>
</nav>

<section class="hero">
    <div class="hero-content">
        <span class="hero-eyebrow"><span class="live-dot"></span> Gujarat's first DDCET platform</span>
        <h1>Built <span>By Students, For Students</span></h1>
        <p>We're diploma students who struggled to find quality DDCET preparation material. So we built the platform we wished we had — and made it the most complete prep tool in Gujarat.</p>
    </div>
</section>

<!-- Mission band -->
<section style="background: linear-gradient(135deg, #1a1a2e 0%, #2d1b69 100%); padding: 56px 32px; position: relative; overflow: hidden;">
    <div style="max-width: 820px; margin: 0 auto; text-align: center; position: relative; z-index: 1;">
        <div style="font-size: 12px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: rgba(255,255,255,0.5); margin-bottom: 14px;">Our Mission</div>
        <p style="font-size: 26px; font-weight: 700; color: #fff; line-height: 1.5;">To give <span style="color: #8b5cf6;">every diploma student in Gujarat</span> the same quality of exam preparation that's normally reserved for JEE and NEET aspirants — <span style="color: #10b981;">at a price every family can afford.</span></p>
    </div>
</section>

<!-- Story -->
<section class="story reveal">
    <div class="story-text">
        <h2>The Problem We Saw</h2>
        <p>When we started preparing for DDCET, we found <strong>zero dedicated platforms</strong>. No mock tests matching the actual pattern. No analytics. No community of fellow diploma students.</p>
        <p>Existing platforms focused on JEE/NEET/CET — none cared about the specific needs of Gujarat's diploma-to-degree aspirants. We were left with random PDFs and outdated books.</p>
        <p>So we built <strong>DDCET Prep</strong> — the platform that treats diploma students as first-class citizens, not an afterthought.</p>
    </div>
    <div class="story-visual">
        <div class="story-card">
            <div style="margin-bottom: 12px;"><svg width="48" height="48" fill="none" stroke="var(--accent)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l.146.146A2 2 0 0114.5 19h-5a2 2 0 01-1.182-3.636l.146-.146z"/></svg></div>
            <blockquote>"Why isn't there a single platform that gives me DDCET-pattern mock tests with proper analytics? Am I the only one preparing?"</blockquote>
            <div style="margin-top: 12px; font-size: 12px; color: var(--text-muted);">— The thought that started it all, 2024</div>
        </div>
    </div>
</section>

<!-- Values -->
<section class="section reveal">
    <h2 style="text-align: center;">What We Stand For</h2>
    <div class="values-grid">
        <div class="value-card">
            <div class="icon"><svg width="32" height="32" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg></div>
            <h3>DDCET-Specific</h3>
            <p>Every question, test, and feature is designed specifically for the DDCET exam pattern. Not generic. Not repurposed.</p>
        </div>
        <div class="value-card">
            <div class="icon"><svg width="32" height="32" fill="none" stroke="var(--green)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 2v4m0 12v4M4.93 4.93l2.83 2.83m8.48 8.48l2.83 2.83M2 12h4m12 0h4M4.93 19.07l2.83-2.83m8.48-8.48l2.83-2.83"/></svg></div>
            <h3>Free Core Access</h3>
            <p>Daily challenges, free resources, and community access will always be free. Premium is for power users, not a paywall.</p>
        </div>
        <div class="value-card">
            <div class="icon"><svg width="32" height="32" fill="none" stroke="var(--purple)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg></div>
            <h3>Data-Driven Prep</h3>
            <p>Know exactly where you stand. Subject-wise analytics, percentile ranking, and time analysis guide your preparation.</p>
        </div>
        <div class="value-card">
            <div class="icon"><svg width="32" height="32" fill="none" stroke="var(--orange)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg></div>
            <h3>Community First</h3>
            <p>Preparation is better with peers. Compete, collaborate, and motivate each other through the journey.</p>
        </div>
        <div class="value-card">
            <div class="icon"><svg width="32" height="32" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg></div>
            <h3>Built for Speed</h3>
            <p>Fast, lightweight, works on any device. No heavy app downloads. Just open your browser and start practicing.</p>
        </div>
        <div class="value-card">
            <div class="icon"><svg width="32" height="32" fill="none" stroke="var(--green)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg></div>
            <h3>Privacy Respected</h3>
            <p>We only collect what's necessary. No ads. No data selling. Your preparation data belongs to you.</p>
        </div>
    </div>
</section>

<!-- Trust Numbers -->
<section class="trust">
    <div class="trust-inner reveal">
        <h2>Platform in Numbers</h2>
        <div class="trust-grid">
            <div class="trust-item"><div class="big"><span class="count" data-target="500">0</span>+</div><div class="desc">Registered Students</div></div>
            <div class="trust-item"><div class="big"><span class="count" data-target="2000">0</span>+</div><div class="desc">Quality Questions</div></div>
            <div class="trust-item"><div class="big"><span class="count" data-target="70">0</span>+</div><div class="desc">Free Resources</div></div>
            <div class="trust-item"><div class="big"><span class="count" data-target="20">0</span>+</div><div class="desc">Powerful Features</div></div>
        </div>
    </div>
</section>

<!-- What's inside -->
<section class="section reveal">
    <h2 style="text-align: center;">What's Inside the Platform</h2>
    <p style="text-align: center; color: var(--text-sec); margin-bottom: 32px;">One login unlocks a complete DDCET preparation ecosystem.</p>
    <div class="values-grid">
        <div class="value-card">
            <div class="icon"><svg width="32" height="32" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 14l2 2 4-4"/></svg></div>
            <h3>Tests for Every Mood</h3>
            <p>Full mocks, rapid fire, daily & weekly challenges, subject-wise, custom and previous-year papers.</p>
        </div>
        <div class="value-card">
            <div class="icon"><svg width="32" height="32" fill="none" stroke="var(--green)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg></div>
            <h3>Analytics & AI Predictor</h3>
            <p>Deep result analysis, readiness scoring, and an AI college predictor trained on real cut-off data.</p>
        </div>
        <div class="value-card">
            <div class="icon"><svg width="32" height="32" fill="none" stroke="var(--orange)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg></div>
            <h3>Compete & Win</h3>
            <p>Leaderboards, leagues, friend duels, XP, badges — and real Pro rewards for top mock finishers.</p>
        </div>
        <div class="value-card">
            <div class="icon"><svg width="32" height="32" fill="none" stroke="var(--purple)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg></div>
            <h3>Learn Free</h3>
            <p>70+ curated resources, flashcards, study materials, bookmarks and a doubt box answered by our team.</p>
        </div>
        <div class="value-card">
            <div class="icon"><svg width="32" height="32" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg></div>
            <h3>College Finder</h3>
            <p>Search Gujarat degree colleges, compare branches and last year's cut-offs to plan your admission.</p>
        </div>
        <div class="value-card">
            <div class="icon"><svg width="32" height="32" fill="none" stroke="var(--green)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg></div>
            <h3>Community & Support</h3>
            <p>A community feed, friend network and a tracked Contact-Us query system whenever you need help.</p>
        </div>
    </div>
</section>

<!-- Team -->
<section class="section reveal">
    <h2 style="text-align: center;">The Team</h2>
    <p style="text-align: center; color: var(--text-sec); margin-bottom: 8px;">Built by diploma students from Gujarat's top polytechnics.</p>
    <div class="team-grid">
        <div class="team-card">
            <div class="team-avatar">A</div>
            <h4>Abdullah Kapadia</h4>
            <div class="role">Founder & Developer</div>
            <p>GEC Dahod. Full-stack developer passionate about making education accessible for diploma students.</p>
        </div>
        <div class="team-card">
            <div class="team-avatar">T</div>
            <h4>Taher Bootwala</h4>
            <div class="role">Co-Founder & Content</div>
            <p>Curates question banks, manages community, and ensures content quality matches the actual DDCET pattern.</p>
        </div>
        <div class="team-card">
            <div class="team-avatar">+</div>
            <h4>Want to Join?</h4>
            <div class="role">Open Positions</div>
            <p>We're looking for content creators, subject experts, and campus ambassadors across Gujarat.</p>
        </div>
    </div>
</section>

<!-- Tech -->
<section class="section" style="text-align: center; padding-bottom: 40px;">
    <h2>Built With</h2>
    <p style="color: var(--text-sec); margin-bottom: 16px;">Modern, reliable tech stack for a smooth experience.</p>
    <div class="tech-pills">
        <span class="pill">PHP 8.2</span>
        <span class="pill">Supabase (PostgreSQL)</span>
        <span class="pill">Google OAuth</span>
        <span class="pill">Razorpay Payments</span>
        <span class="pill">Chart.js</span>
        <span class="pill">Responsive Design</span>
    </div>
</section>

<!-- CTA -->
<section class="cta">
    <h2>Join the Movement</h2>
    <p>Be part of Gujarat's first dedicated DDCET preparation community.</p>
    <a href="auth/login.php" class="btn-lg">Start Free Prep →</a>
</section>

<footer class="footer">
    <a href="<?= BASE_PATH ?>">Home</a> · <a href="roadmap.php">Roadmap</a> · <a href="auth/login.php">Login</a>
    <div style="margin-top: 12px;">© <?= date('Y') ?> DDCET Prep. All rights reserved. &middot; Gujarat's dedicated DDCET preparation platform.</div>
</footer>

<script>
// Scroll reveal
const revObserver = new IntersectionObserver((entries) => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); revObserver.unobserve(e.target); } });
}, { threshold: 0.15 });
document.querySelectorAll('.reveal').forEach(el => revObserver.observe(el));

// Count-up numbers
const numObserver = new IntersectionObserver((entries) => {
    entries.forEach(e => {
        if (!e.isIntersecting) return;
        const el = e.target, target = +el.dataset.target;
        let cur = 0; const step = Math.max(1, Math.ceil(target / 50));
        const t = setInterval(() => { cur += step; if (cur >= target) { cur = target; clearInterval(t); } el.textContent = cur.toLocaleString(); }, 22);
        numObserver.unobserve(el);
    });
}, { threshold: 0.5 });
document.querySelectorAll('.count[data-target]').forEach(el => numObserver.observe(el));
</script>

</body>
</html>
