<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/icons.php';
$user = requireAdmin();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?= htmlspecialchars(BASE_PATH) ?>">
    <title><?= htmlspecialchars($pageTitle ?? 'Admin') ?> - Admin Panel</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/contrib/auto-render.min.js"></script>
    <script defer src="assets/js/mathrender.js"></script>
    <script>
    function toggleSidebar() {
        const sb = document.getElementById('sidebar');
        const ov = document.getElementById('sidebarOverlay');
        if (!sb) return;
        if (window.innerWidth <= 768) {
            const open = sb.classList.toggle('open');
            if (ov) ov.classList.toggle('show', open);
        } else {
            sb.classList.toggle('collapsed');
        }
    }
    document.addEventListener('DOMContentLoaded', function() {
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
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                const sb = document.getElementById('sidebar');
                const ov = document.getElementById('sidebarOverlay');
                if (sb) sb.classList.remove('open');
                if (ov) ov.classList.remove('show');
            }
        });
    });
    </script>
</head>
<body>
<div class="app-layout">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand"><img src="assets/logo.png" alt="" style="height: 24px; vertical-align: middle; margin-right: 6px;"><span class="accent">DDCET</span> Admin</div>
        <nav class="sidebar-nav">
            <div class="nav-section">Overview</div>
            <a href="admin/index.php" class="<?= $currentPage === 'index' ? 'active' : '' ?>"><?= icon('dashboard') ?> Dashboard</a>

            <div class="nav-section">Content</div>
            <a href="admin/questions.php" class="<?= $currentPage === 'questions' ? 'active' : '' ?>"><?= icon('question') ?> Questions</a>
            <a href="admin/preview.php" class="<?= $currentPage === 'preview' ? 'active' : '' ?>"><?= icon('document') ?> Preview</a>
            <a href="admin/upload.php" class="<?= $currentPage === 'upload' ? 'active' : '' ?>"><?= icon('trending-up') ?> Bulk Upload</a>
            <a href="admin/dedup.php" class="<?= $currentPage === 'dedup' ? 'active' : '' ?>"><?= icon('refresh') ?> Dedup</a>
            <a href="admin/math-check.php" class="<?= $currentPage === 'math-check' ? 'active' : '' ?>"><?= icon('question') ?> Math Check</a>
            <a href="admin/tests.php" class="<?= $currentPage === 'tests' ? 'active' : '' ?>"><?= icon('test') ?> Tests</a>
            <a href="admin/mocks.php" class="<?= $currentPage === 'mocks' ? 'active' : '' ?>"><?= icon('trophy') ?> Mock Series</a>
            <a href="admin/resources.php" class="<?= $currentPage === 'resources' ? 'active' : '' ?>"><?= icon('book') ?> Resources</a>
            <a href="admin/materials.php" class="<?= $currentPage === 'materials' ? 'active' : '' ?>"><?= icon('book') ?> Study Materials</a>
            <a href="admin/contributions.php" class="<?= $currentPage === 'contributions' ? 'active' : '' ?>"><?= icon('star') ?> Contributions</a>
            <a href="admin/community.php" class="<?= $currentPage === 'community' ? 'active' : '' ?>"><?= icon('chat') ?> Community</a>
            <a href="admin/doubts.php" class="<?= $currentPage === 'doubts' ? 'active' : '' ?>"><?= icon('question') ?> Doubts</a>
            <a href="admin/queries.php" class="<?= $currentPage === 'queries' ? 'active' : '' ?>"><?= icon('chat') ?> Queries</a>

            <div class="nav-section">Users</div>
            <a href="admin/students.php" class="<?= $currentPage === 'students' ? 'active' : '' ?>"><?= icon('users') ?> Students</a>
            <a href="admin/leagues.php" class="<?= $currentPage === 'leagues' ? 'active' : '' ?>"><?= icon('medal') ?> Leagues</a>
            <a href="admin/colleges.php" class="<?= $currentPage === 'colleges' ? 'active' : '' ?>"><?= icon('school') ?> Colleges</a>

            <div class="nav-section">Revenue</div>
            <a href="admin/payments.php" class="<?= $currentPage === 'payments' ? 'active' : '' ?>"><?= icon('money') ?> Payments</a>
            <a href="admin/org-discounts.php" class="<?= $currentPage === 'org-discounts' ? 'active' : '' ?>"><?= icon('school') ?> College Discounts</a>
            <a href="admin/leads.php" class="<?= $currentPage === 'leads' ? 'active' : '' ?>"><?= icon('school') ?> Demo Leads</a>

            <div class="nav-section">Insights</div>
            <a href="admin/analytics.php" class="<?= $currentPage === 'analytics' ? 'active' : '' ?>"><?= icon('trending-up') ?> Analytics</a>
            <a href="admin/email.php" class="<?= $currentPage === 'email' ? 'active' : '' ?>"><?= icon('bell') ?> Email</a>

            <div class="nav-section"></div>
            <a href="dashboard.php"><?= icon('arrow-left') ?> Back to Site</a>
        </nav>
    </aside>
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
    <main class="main-content">
        <header class="navbar">
            <div class="navbar-left">
                <button class="menu-toggle" onclick="toggleSidebar()" aria-label="Toggle menu"><?= icon('menu') ?></button>
                <h2><?= htmlspecialchars($pageTitle ?? 'Admin') ?></h2>
            </div>
            <div class="navbar-right">
                <span style="font-size: 12px; color: var(--text-muted);"><?= htmlspecialchars($user['name']) ?></span>
            </div>
        </header>
        <div class="page-content">
