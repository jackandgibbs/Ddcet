<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/icons.php';
$user = currentUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Prevent browser from caching authenticated pages (fixes back-button after logout)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="/Dddcet/">
    <title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> - <?= APP_NAME ?></title>
    <meta name="theme-color" content="#4361ee">
    <link rel="icon" href="assets/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32.png">
    <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
    <link rel="manifest" href="manifest.webmanifest">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Site-wide math rendering (KaTeX). DDCET is Physics/Maths heavy, so
         formulas render anywhere content shows, not just inside the exam. -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/contrib/auto-render.min.js"></script>
    <script defer src="assets/js/mathrender.js"></script>
    <?php if (!empty($extraHead)) echo $extraHead; ?>
    <style>
    .toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 8px; }
    .toast { background: #10b981; color: white; border-radius: 10px; padding: 14px 20px; box-shadow: 0 8px 24px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 10px; font-size: 14px; max-width: 360px; animation: slideIn 0.3s ease; font-weight: 500; }
    .toast-close { cursor: pointer; margin-left: 10px; font-size: 18px; opacity: 0.8; }
    .toast-close:hover { opacity: 1; }
    @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    </style>
    <script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register('sw.js', { scope: '/Dddcet/' }).catch(() => {}));
    }
    function showToast(msg, duration = 5000) {
        let container = document.querySelector('.toast-container');
        if (!container) { container = document.createElement('div'); container.className = 'toast-container'; document.body.appendChild(container); }
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.innerHTML = '✓ ' + msg + '<span class="toast-close" onclick="this.parentElement.remove()">✕</span>';
        container.appendChild(toast);
        setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateX(100%)'; toast.style.transition = 'all 0.3s'; setTimeout(() => toast.remove(), 300); }, duration);
    }
    function toggleSidebar() {
        const sb = document.getElementById('sidebar');
        const ov = document.getElementById('sidebarOverlay');
        if (!sb) return;
        if (window.innerWidth <= 768) {
            // Mobile: slide the off-canvas sidebar in/out + toggle backdrop
            const open = sb.classList.toggle('open');
            if (ov) ov.classList.toggle('show', open);
        } else {
            // Desktop: collapse/expand the fixed sidebar to free up space
            sb.classList.toggle('collapsed');
        }
    }
    document.addEventListener('DOMContentLoaded', function() {
        // Tapping a nav link on mobile should close the drawer
        document.querySelectorAll('.sidebar-nav a').forEach(function(a) {
            a.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    const sb = document.getElementById('sidebar');
                    const ov = document.getElementById('sidebarOverlay');
                    if (sb) sb.classList.remove('open');
                    if (ov) ov.classList.remove('show');
                }
            });
        });
        // Reset mobile state when resizing up to desktop
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                const sb = document.getElementById('sidebar');
                const ov = document.getElementById('sidebarOverlay');
                if (sb) sb.classList.remove('open');
                if (ov) ov.classList.remove('show');
            }
        });
    });
    function confirmLogout(e) {
        e.preventDefault();
        if (confirm('Are you sure you want to logout?')) {
            window.location.href = 'auth/logout.php';
        }
    }
    history.pushState(null, null, location.href);
    window.onpopstate = function() {
        if (confirm('Are you sure you want to logout?')) {
            window.location.href = 'auth/logout.php';
        } else {
            history.pushState(null, null, location.href);
        }
    };
    <?php if (isset($_SESSION['show_login_success'])): unset($_SESSION['show_login_success']); ?>
    window.addEventListener('DOMContentLoaded', function() { showToast('Successfully logged in'); });
    <?php endif; ?>
    <?php if (isset($_SESSION['show_logout_success'])): unset($_SESSION['show_logout_success']); ?>
    window.addEventListener('DOMContentLoaded', function() { showToast('Successfully logged out'); });
    <?php endif; ?>
    </script>
</head>
<body>
<div class="app-layout">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <img src="assets/logo.png" alt="DDCET Prep" style="height: 28px; vertical-align: middle; margin-right: 8px;">
            <span class="accent">DDCET</span> Prep
        </div>
        <nav class="sidebar-nav">
            <div class="nav-section">Main</div>
            <a href="dashboard.php" class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>"><?= icon('dashboard') ?> Dashboard</a>
            <a href="tests.php" class="<?= $currentPage === 'tests' ? 'active' : '' ?>"><?= icon('test') ?> Test Modes</a>
            <a href="mocks.php" class="<?= $currentPage === 'mocks' ? 'active' : '' ?>"><?= icon('trophy') ?> Mock Series</a>
            <a href="history.php" class="<?= $currentPage === 'history' ? 'active' : '' ?>"><?= icon('clipboard') ?> Test History</a>
            <a href="custom-test.php" class="<?= $currentPage === 'custom-test' ? 'active' : '' ?>"><?= icon('settings') ?> Custom Test</a>
            <a href="leaderboard.php" class="<?= $currentPage === 'leaderboard' ? 'active' : '' ?>"><?= icon('trophy') ?> Leaderboard</a>
            <a href="community.php" class="<?= $currentPage === 'community' ? 'active' : '' ?>"><?= icon('chat') ?> Community</a>

            <div class="nav-section">Study</div>
            <a href="predictor.php" class="<?= $currentPage === 'predictor' ? 'active' : '' ?>"><?= icon('target') ?> College Predictor</a>
            <a href="resources.php" class="<?= $currentPage === 'resources' ? 'active' : '' ?>"><?= icon('book') ?> Resources</a>
            <a href="materials.php" class="<?= $currentPage === 'materials' ? 'active' : '' ?>"><?= icon('book') ?> Study Material</a>
            <a href="flashcards.php" class="<?= $currentPage === 'flashcards' ? 'active' : '' ?>"><?= icon('cards') ?> Flashcards</a>
            <a href="bookmarks.php" class="<?= $currentPage === 'bookmarks' ? 'active' : '' ?>"><?= icon('bookmark') ?> Bookmarks</a>

            <div class="nav-section">Social</div>
            <a href="friends.php" class="<?= $currentPage === 'friends' ? 'active' : '' ?>"><?= icon('users') ?> Friends</a>
            <a href="challenges.php" class="<?= $currentPage === 'challenges' ? 'active' : '' ?>">⚔️ Challenges</a>
            <a href="profile.php" class="<?= $currentPage === 'profile' ? 'active' : '' ?>"><?= icon('user') ?> Profile</a>
            <a href="achievements.php" class="<?= $currentPage === 'achievements' ? 'active' : '' ?>"><?= icon('award') ?> Achievements</a>
            <a href="doubts.php" class="<?= $currentPage === 'doubts' ? 'active' : '' ?>"><?= icon('question') ?> Doubt Box</a>

            <?php if (!empty($user['is_institution'])): ?>
            <div class="nav-section">Institution</div>
            <a href="institution/index.php" class="<?= $currentPage === 'index' && strpos($_SERVER['PHP_SELF'], '/institution/') !== false ? 'active' : '' ?>"><?= icon('school') ?> Institution Portal</a>
            <?php endif; ?>

            <div class="nav-section">Account</div>
            <a href="subscription.php" class="<?= $currentPage === 'subscription' ? 'active' : '' ?>"><?= icon('credit-card') ?> Subscription</a>
            <a href="billing.php" class="<?= $currentPage === 'billing' ? 'active' : '' ?>"><?= icon('money') ?> Billing</a>
            <a href="notifications.php" class="<?= $currentPage === 'notifications' ? 'active' : '' ?>"><?= icon('bell') ?> Notifications</a>
            <a href="contact.php" class="<?= $currentPage === 'contact' ? 'active' : '' ?>"><?= icon('chat') ?> Contact Us</a>
            <?php if (empty($user['is_institution'])): ?>
            <a href="join-org.php" class="<?= $currentPage === 'join-org' ? 'active' : '' ?>"><?= icon('school') ?> Join Institution</a>
            <?php endif; ?>
            <a href="auth/logout.php" onclick="confirmLogout(event)"><?= icon('logout') ?> Logout</a>
        </nav>
        <div class="sidebar-footer">
            <?php if ($user && $user['avatar_url']): ?>
                <img src="<?= htmlspecialchars($user['avatar_url']) ?>" alt="">
            <?php endif; ?>
            <div>
                <div class="name"><?= htmlspecialchars($user['name'] ?? 'Guest') ?></div>
                <div class="plan"><?= htmlspecialchars(ucfirst($user['level'] ?? 'Beginner')) ?></div>
            </div>
        </div>
    </aside>

    <!-- Mobile overlay (tap to close sidebar) -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Main -->
    <main class="main-content">
        <header class="navbar">
            <div class="navbar-left">
                <button class="menu-toggle" onclick="toggleSidebar()" aria-label="Toggle menu"><?= icon('menu') ?></button>
                <h2><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h2>
            </div>
            <div class="navbar-right">
                <span class="streak-badge"><?= icon('fire') ?> <?= (int)($user['streak'] ?? 0) ?></span>
                <span class="xp-badge"><?= number_format((int)($user['xp'] ?? 0)) ?> XP</span>
            </div>
        </header>
        <div class="page-content">
