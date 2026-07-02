<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/blog/posts.php';

$slug = isset($_GET['post']) ? trim($_GET['post']) : '';
$post = $slug !== '' ? blogPost($slug) : null;
$isSingle = $post !== null;

// 404 for an unknown slug rather than silently showing the list.
if ($slug !== '' && !$isSingle) {
    http_response_code(404);
}

$pageTitle = $isSingle ? $post['title'] . ' — DDCET Prep Blog' : 'DDCET Prep Blog — Tips, Strategy & Study Plans';
$pageDesc  = $isSingle ? $post['excerpt'] : 'Free DDCET preparation tips, exam-pattern guides, study plans and mock-test strategy for Gujarat diploma-to-degree students.';
$canonical = APP_URL . '/blog.php' . ($isSingle ? '?post=' . urlencode($post['slug']) : '');
$fmtDate = fn($d) => date('M j, Y', strtotime($d));
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
    <meta property="og:type" content="<?= $isSingle ? 'article' : 'website' ?>">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDesc) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($canonical) ?>">
    <meta property="og:image" content="<?= APP_URL ?>/assets/icon-512.png">

    <link rel="icon" href="assets/favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
    <link rel="manifest" href="<?= BASE_PATH ?>manifest.webmanifest">

    <link rel="preload" href="assets/fonts/dmsans.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="assets/fonts/fonts.css">

    <?php if ($isSingle): ?>
    <script type="application/ld+json">
    <?= json_encode([
        '@context' => 'https://schema.org',
        '@type'    => 'BlogPosting',
        'headline' => $post['title'],
        'description' => $post['excerpt'],
        'datePublished' => $post['date'],
        'author'   => ['@type' => 'Person', 'name' => $post['author']],
        'publisher' => ['@type' => 'Organization', 'name' => 'DDCET Prep',
                        'logo' => ['@type' => 'ImageObject', 'url' => APP_URL . '/assets/icon-512.png']],
        'mainEntityOfPage' => $canonical,
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
    </script>
    <?php else: ?>
    <script type="application/ld+json">
    <?= json_encode([
        '@context' => 'https://schema.org',
        '@type'    => 'Blog',
        'name'     => 'DDCET Prep Blog',
        'url'      => APP_URL . '/blog.php',
        'blogPost' => array_map(fn($p) => [
            '@type' => 'BlogPosting',
            'headline' => $p['title'],
            'description' => $p['excerpt'],
            'datePublished' => $p['date'],
            'url' => APP_URL . '/blog.php?post=' . $p['slug'],
        ], blogPosts()),
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
    </script>
    <?php endif; ?>

    <style>
        :root { --bg: #f8f9fa; --card: #ffffff; --accent: #4361ee; --green: #10b981; --orange: #f59e0b; --purple: #8b5cf6; --red: #ef4444; --text: #1a1a2e; --text-sec: #6c757d; --text-muted: #adb5bd; --border: #e9ecef; --radius: 12px; --font: 'DM Sans', sans-serif; --mono: 'DM Mono', monospace; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { background: var(--bg); color: var(--text); font-family: var(--font); line-height: 1.6; overflow-x: hidden; }
        a { color: var(--accent); text-decoration: none; }

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

        .hero { padding: 70px 32px 40px; text-align: center; position: relative; overflow: hidden; }
        .hero::before { content: ''; position: absolute; top: -120px; left: 50%; transform: translateX(-50%); width: 700px; height: 400px; background: radial-gradient(ellipse, rgba(67,97,238,0.12), transparent 70%); z-index: 0; }
        .hero-content { position: relative; z-index: 1; max-width: 760px; margin: 0 auto; }
        .hero-eyebrow { display: inline-flex; align-items: center; gap: 7px; background: var(--card); border: 1px solid var(--border); color: var(--accent); font-size: 12px; font-weight: 700; padding: 7px 16px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 20px; box-shadow: 0 4px 16px rgba(0,0,0,0.04); }
        .hero h1 { font-size: 40px; font-weight: 900; letter-spacing: -1px; margin-bottom: 14px; line-height: 1.12; }
        .hero h1 span { background: linear-gradient(135deg, var(--accent), var(--purple)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .hero p { color: var(--text-sec); font-size: 17px; max-width: 600px; margin: 0 auto; }

        /* Post grid */
        .wrap { max-width: 1000px; margin: 0 auto; padding: 24px 32px 70px; }
        .post-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 24px; }
        .post-card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 26px; display: flex; flex-direction: column; transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s; }
        .post-card:hover { transform: translateY(-5px); box-shadow: 0 16px 40px rgba(0,0,0,0.08); border-color: rgba(67,97,238,0.35); }
        .post-tag { align-self: flex-start; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--accent); background: rgba(67,97,238,0.08); padding: 5px 12px; border-radius: 20px; margin-bottom: 14px; }
        .post-card h2 { font-size: 19px; font-weight: 800; line-height: 1.3; margin-bottom: 10px; color: var(--text); }
        .post-card p { font-size: 14px; color: var(--text-sec); flex: 1; }
        .post-meta { display: flex; align-items: center; gap: 8px; margin-top: 18px; font-size: 12px; color: var(--text-muted); }
        .post-meta .read { margin-left: auto; }

        /* Single post */
        .article { max-width: 720px; margin: 0 auto; padding: 16px 32px 70px; }
        .crumb { font-size: 13px; color: var(--text-sec); margin-bottom: 24px; }
        .article-head h1 { font-size: 34px; font-weight: 900; letter-spacing: -0.5px; line-height: 1.2; margin-bottom: 14px; }
        .article-meta { display: flex; align-items: center; gap: 10px; font-size: 13px; color: var(--text-muted); margin-bottom: 32px; padding-bottom: 24px; border-bottom: 1px solid var(--border); }
        .article-meta .dot { width: 28px; height: 28px; border-radius: 50%; background: linear-gradient(135deg, var(--accent), var(--purple)); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px; }
        .article-body { font-size: 16px; color: #2b2b3d; }
        .article-body h2 { font-size: 22px; font-weight: 800; margin: 32px 0 12px; color: var(--text); }
        .article-body p { margin-bottom: 16px; }
        .article-body ul { margin: 0 0 16px 22px; }
        .article-body li { margin-bottom: 8px; }
        .article-cta { margin-top: 40px; padding: 28px; background: linear-gradient(135deg, #1a1a2e 0%, #2d1b69 100%); border-radius: var(--radius); text-align: center; }
        .article-cta h3 { color: #fff; font-size: 20px; font-weight: 800; margin-bottom: 8px; }
        .article-cta p { color: rgba(255,255,255,0.7); font-size: 14px; margin-bottom: 18px; }
        .btn-lg { background: var(--accent); color: #fff; padding: 14px 32px; border-radius: 10px; font-weight: 700; font-size: 15px; display: inline-block; transition: all 0.2s; }
        .btn-lg:hover { transform: translateY(-2px); box-shadow: 0 12px 32px rgba(67,97,238,0.4); }

        .footer { background: #1a1a2e; padding: 32px; text-align: center; color: rgba(255,255,255,0.5); font-size: 13px; }
        .footer a { color: rgba(255,255,255,0.7); }

        @media (max-width: 768px) {
            .hero h1 { font-size: 28px; }
            .article-head h1 { font-size: 26px; }
            .hamburger { display: block; }
            .nav-links { display: none; position: absolute; top: 100%; left: 0; right: 0; background: var(--card); border-bottom: 1px solid var(--border); flex-direction: column; padding: 16px 24px; gap: 16px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); }
            .nav-links.open { display: flex; }
        }
    </style>
</head>
<body>

<nav class="nav">
    <div class="nav-inner">
        <a href="<?= BASE_PATH ?>" class="nav-brand"><img src="assets/logo.png" alt="" width="24" height="18" style="height: 24px; width: auto; vertical-align: middle; margin-right: 6px;"><span>DDCET</span> Prep</a>
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

<?php if ($isSingle): ?>
<!-- Single article -->
<article class="article">
    <div class="crumb"><a href="blog.php">← All articles</a></div>
    <div class="article-head">
        <span class="post-tag" style="display:inline-block;"><?= htmlspecialchars($post['category']) ?></span>
        <h1><?= htmlspecialchars($post['title']) ?></h1>
        <div class="article-meta">
            <span class="dot"><?= htmlspecialchars(strtoupper(substr($post['author'], 0, 1))) ?></span>
            <span><?= htmlspecialchars($post['author']) ?></span>
            <span>·</span>
            <span><?= $fmtDate($post['date']) ?></span>
            <span>·</span>
            <span><?= (int)$post['read'] ?> min read</span>
        </div>
    </div>
    <div class="article-body">
        <?= $post['body'] ?>
    </div>
    <div class="article-cta">
        <h3>Ready to put this into practice?</h3>
        <p>Start with a free DDCET-pattern mock and see where you stand today.</p>
        <a href="auth/login.php" class="btn-lg">Start Free Prep →</a>
    </div>
</article>
<?php else: ?>
<!-- Blog list -->
<section class="hero">
    <div class="hero-content">
        <span class="hero-eyebrow">DDCET Prep Blog</span>
        <h1>Tips, Strategy &amp; <span>Study Plans</span></h1>
        <p>Practical, no-fluff advice to help you crack the DDCET — from exam-pattern guides to mock-test strategy.</p>
    </div>
</section>
<div class="wrap">
    <div class="post-grid">
        <?php foreach (blogPosts() as $p): ?>
        <a class="post-card reveal" href="blog.php?post=<?= urlencode($p['slug']) ?>">
            <span class="post-tag"><?= htmlspecialchars($p['category']) ?></span>
            <h2><?= htmlspecialchars($p['title']) ?></h2>
            <p><?= htmlspecialchars($p['excerpt']) ?></p>
            <div class="post-meta">
                <span><?= htmlspecialchars($p['author']) ?></span>
                <span>·</span>
                <span><?= $fmtDate($p['date']) ?></span>
                <span class="read"><?= (int)$p['read'] ?> min read</span>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<footer class="footer">
    <a href="<?= BASE_PATH ?>">Home</a> · <a href="blog.php">Blog</a> · <a href="roadmap.php">Roadmap</a> · <a href="auth/login.php">Login</a>
    <div style="margin-top: 12px;">© <?= date('Y') ?> DDCET Prep. All rights reserved. &middot; Gujarat's dedicated DDCET preparation platform.</div>
</footer>

<script>
const revObserver = new IntersectionObserver((entries) => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); revObserver.unobserve(e.target); } });
}, { threshold: 0.12 });
document.querySelectorAll('.reveal').forEach(el => revObserver.observe(el));
</script>

</body>
</html>
