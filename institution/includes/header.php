<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/icons.php';

$instUser = requireInstitution();
$org = currentOrg();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Every page except the portal landing requires an organization to exist. The
// landing (index.php) sets $allowNoOrg before including this header so a freshly
// flagged account can create its org there.
if (empty($org) && empty($allowNoOrg)) {
    redirect('institution/index.php');
}

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
    <title><?= htmlspecialchars($pageTitle ?? 'Institution') ?> — Institution Portal</title>
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
    });
    </script>
</head>
<body>
<div class="app-layout">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand"><img src="assets/logo.png" alt="" style="height: 24px; vertical-align: middle; margin-right: 6px;"><span class="accent">DDCET</span> Institution</div>
        <nav class="sidebar-nav">
            <div class="nav-section">Overview</div>
            <a href="institution/index.php" class="<?= $currentPage === 'index' ? 'active' : '' ?>"><?= icon('dashboard') ?> Dashboard</a>
            <a href="institution/analytics.php" class="<?= $currentPage === 'analytics' ? 'active' : '' ?>"><?= icon('trending-up') ?> Batch Analytics</a>

            <div class="nav-section">Content</div>
            <a href="institution/questions.php" class="<?= $currentPage === 'questions' ? 'active' : '' ?>"><?= icon('question') ?> Question Bank</a>
            <a href="institution/upload.php" class="<?= $currentPage === 'upload' ? 'active' : '' ?>"><?= icon('trending-up') ?> Bulk Upload</a>
            <a href="institution/tests.php" class="<?= $currentPage === 'tests' ? 'active' : '' ?>"><?= icon('test') ?> Assignments</a>

            <div class="nav-section">Batch</div>
            <a href="institution/students.php" class="<?= $currentPage === 'students' ? 'active' : '' ?>"><?= icon('users') ?> Students</a>

            <div class="nav-section"></div>
            <a href="dashboard.php"><?= icon('arrow-left') ?> Back to Site</a>
        </nav>
    </aside>
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
    <main class="main-content">
        <header class="navbar">
            <div class="navbar-left">
                <button class="menu-toggle" onclick="toggleSidebar()" aria-label="Toggle menu"><?= icon('menu') ?></button>
                <h2><?= htmlspecialchars($pageTitle ?? 'Institution') ?></h2>
            </div>
            <div class="navbar-right">
                <?php if ($org): ?><span class="badge badge-accent"><?= htmlspecialchars($org['name']) ?></span><?php endif; ?>
                <span style="font-size: 12px; color: var(--text-muted);"><?= htmlspecialchars($instUser['name']) ?></span>
            </div>
        </header>
        <div class="page-content">
