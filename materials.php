<?php
require_once __DIR__ . '/config.php';
$user = requireAuth();
$db = getDB();
$pageTitle = 'Study Material';

$filterSubject = $_GET['subject'] ?? '';

$filter = 'select=*&order=subject,chapter,title';
if ($filterSubject) $filter .= '&subject=eq.' . urlencode($filterSubject);
$materials = supabaseRest('study_materials?' . $filter) ?? [];

// If no study materials, show from resources table instead
if (empty($materials)) {
    $rFilter = 'select=*&is_active=eq.true&order=category,topic,title';
    if ($filterSubject) $rFilter .= '&category=eq.' . urlencode(strtolower($filterSubject));
    $resourcesData = supabaseRest('resources?' . $rFilter) ?? [];
    // Convert resources to material-like format
    foreach ($resourcesData as $r) {
        $materials[] = [
            'subject' => ucfirst($r['category'] ?? ''),
            'chapter' => $r['topic'] ?? null,
            'title' => $r['title'] ?? '',
            'type' => 'video',
            'file_url' => null,
            'video_url' => $r['url'] ?? '',
        ];
    }
}

$subjectsData = supabaseRest('resources?select=category&is_active=eq.true&order=category') ?? [];
$subjects = array_unique(array_map(fn($r) => ucfirst($r['category']), $subjectsData));

// Group by subject
$grouped = [];
foreach ($materials as $m) { $grouped[$m['subject']][] = $m; }

include __DIR__ . '/includes/header.php';
?>

<!-- Filters -->
<div style="display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap;">
    <a href="materials.php?subject=" class="btn <?= !$filterSubject ? 'btn-primary' : 'btn-secondary' ?> btn-sm">All</a>
    <?php foreach ($subjects as $s): ?>
        <a href="materials.php?subject=<?= urlencode(strtolower($s)) ?>" class="btn <?= strtolower($filterSubject) === strtolower($s) ? 'btn-primary' : 'btn-secondary' ?> btn-sm"><?= htmlspecialchars($s) ?></a>
    <?php endforeach; ?>
</div>

<?php if (empty($grouped)): ?>
    <div class="card" style="text-align: center; padding: 40px; color: var(--text-muted);">No study materials uploaded yet. Check back soon!</div>
<?php endif; ?>

<?php foreach ($grouped as $subject => $items): ?>
<div class="card" style="margin-bottom: 16px;">
    <div class="card-header"><h3><?= icon('book') ?> <?= htmlspecialchars($subject) ?></h3></div>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px;">
    <?php foreach ($items as $item): ?>
        <div style="background: var(--bg-primary); border-radius: 8px; padding: 14px;">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                <span><?= $item['type'] === 'notes' ? icon('notepad', 14) : ($item['type'] === 'formula_sheet' ? icon('calculator', 14) : icon('video', 14)) ?></span>
                <strong style="font-size: 13px;"><?= htmlspecialchars($item['title']) ?></strong>
            </div>
            <?php if ($item['chapter']): ?><p style="font-size: 11px; color: var(--text-muted); margin-bottom: 8px;">Chapter: <?= htmlspecialchars($item['chapter']) ?></p><?php endif; ?>
            <?php if (!empty($item['file_url'])): ?>
                <a href="<?= htmlspecialchars($item['file_url']) ?>" target="_blank" class="btn btn-secondary btn-sm">View PDF →</a>
            <?php endif; ?>
            <?php if (!empty($item['video_url'])): ?>
                <?php if (strtolower($item['subject'] ?? '') === 'websites'): ?>
                    <a href="<?= htmlspecialchars($item['video_url']) ?>" target="_blank" class="btn btn-secondary btn-sm"><?= icon('trending-up', 14) ?> Visit Link →</a>
                <?php else: ?>
                    <a href="<?= htmlspecialchars($item['video_url']) ?>" target="_blank" class="btn btn-secondary btn-sm"><?= icon('video', 14) ?> Watch Video</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
