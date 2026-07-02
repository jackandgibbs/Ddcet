<?php
require_once __DIR__ . '/config.php';
$user = requireAuth();
$db = getDB();
$pageTitle = 'Free Study Resources';

$hasPaidAccess = isSubscribed();

// Load active resources grouped by category and topic
$resources = supabaseRest('resources?is_active=eq.true&order=position&select=*') ?? [];

$grouped = [];
foreach ($resources as $r) {
    $grouped[$r['category']][$r['topic']][] = $r;
}

$tabs = [
    'physics' => 'Physics (BE-01)',
    'chemistry' => 'Chemistry (BE-01)',
    'maths' => 'Maths (BE-02)',
    'english' => 'English & Soft Skills (BE-02)',
    'websites' => 'Top Websites',
];

$activeTab = $_GET['tab'] ?? 'physics';
if (!isset($tabs[$activeTab])) $activeTab = 'physics';

include __DIR__ . '/includes/header.php';
?>

<div style="margin-bottom: 20px;">
    <h2 style="font-size: 18px; margin-bottom: 4px;">Free Study Resources — DDCET BE-01 & BE-02</h2>
    <p style="color: var(--text-secondary); font-size: 13px;">Handpicked free YouTube channels and websites, topic by topic. No paid courses needed.</p>
</div>

<?php if (!$hasPaidAccess): ?>
<!-- Locked State -->
<div class="card" style="text-align: center; padding: 60px 20px; border-color: var(--accent); margin-bottom: 24px;">
    <div style="margin-bottom: 16px;"><?= icon('lock', 48) ?></div>
    <h2 style="margin-bottom: 8px;">Subscribe to Unlock Resources</h2>
    <p style="color: var(--text-secondary); margin-bottom: 24px; max-width: 400px; margin-inline: auto;">Get access to 70+ curated free study links for all DDCET subjects. Upgrade to any paid plan.</p>
    <a href="subscription.php" class="btn btn-dark btn-lg">Unlock Resources</a>
</div>

<!-- Blurred Preview -->
<div style="filter: blur(5px); pointer-events: none; opacity: 0.4;">
    <div class="card" style="margin-bottom: 12px; padding: 16px;">
        <span class="tag">Physics</span>
        <h4 style="margin-top: 8px;">Units & Measurement</h4>
        <p style="color: var(--text-secondary); font-size: 13px;">Khan Academy, Physics Wallah, Dron Study...</p>
    </div>
    <div class="card" style="margin-bottom: 12px; padding: 16px;">
        <span class="tag">Maths</span>
        <h4 style="margin-top: 8px;">Trigonometry</h4>
        <p style="color: var(--text-secondary); font-size: 13px;">Khan Academy, MathonGo, Tutorials Point...</p>
    </div>
</div>

<?php else: ?>
<!-- Tabs -->
<div style="display: flex; gap: 6px; margin-bottom: 20px; flex-wrap: wrap;">
    <?php foreach ($tabs as $key => $label): ?>
        <a href="resources.php?tab=<?= $key ?>" class="btn <?= $activeTab === $key ? 'btn-primary' : 'btn-secondary' ?> btn-sm"><?= $label ?></a>
    <?php endforeach; ?>
</div>

<!-- Resources for active tab -->
<?php if (isset($grouped[$activeTab])): ?>
    <?php foreach ($grouped[$activeTab] as $topic => $links): ?>
    <div class="card" style="margin-bottom: 16px;">
        <div class="card-header"><h3><?= htmlspecialchars($topic) ?></h3></div>
        <div style="display: flex; flex-direction: column; gap: 8px;">
            <?php foreach ($links as $link): ?>
            <a href="<?= htmlspecialchars($link['url']) ?>" target="_blank" rel="noopener" style="display: flex; align-items: center; gap: 12px; padding: 10px 12px; background: var(--bg-primary); border-radius: 6px; text-decoration: none; color: var(--text-primary); transition: background 0.15s;">
                <?= icon('trending-up', 14) ?>
                <span style="flex: 1; font-size: 13px;"><?= htmlspecialchars($link['title']) ?></span>
                <span class="badge badge-blue" style="font-size: 10px;"><?= htmlspecialchars($link['source_label']) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="card" style="padding: 40px; text-align: center; color: var(--text-muted);">No resources available for this category.</div>
<?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
