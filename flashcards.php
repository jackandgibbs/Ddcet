<?php
require_once __DIR__ . '/config.php';
$user = requireAuth();
$db = getDB();
$pageTitle = 'Flashcards';

$filterSubject = $_GET['subject'] ?? '';

$filter = 'select=*&order=subject,chapter';
if ($filterSubject) $filter .= '&subject=eq.' . urlencode($filterSubject);
$cards = supabaseRest('flashcards?' . $filter) ?? [];

$subjectsData = supabaseRest('flashcards?select=subject&order=subject') ?? [];
$subjects = array_unique(array_column($subjectsData, 'subject'));

include __DIR__ . '/includes/header.php';
?>

<style>
.flashcard-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
.flashcard { perspective: 1000px; height: 200px; cursor: pointer; }
.flashcard-inner { position: relative; width: 100%; height: 100%; transition: transform 0.6s; transform-style: preserve-3d; }
.flashcard.flipped .flashcard-inner { transform: rotateY(180deg); }
.flashcard-front, .flashcard-back { position: absolute; width: 100%; height: 100%; backface-visibility: hidden; border-radius: 12px; display: flex; align-items: center; justify-content: center; padding: 20px; text-align: center; font-size: 14px; line-height: 1.5; }
.flashcard-front { background: var(--bg-secondary); border: 1px solid var(--border); }
.flashcard-back { background: rgba(240,165,0,0.08); border: 1px solid var(--accent); transform: rotateY(180deg); color: var(--accent); }
.flashcard-subject { position: absolute; top: 8px; left: 12px; font-size: 10px; color: var(--text-muted); }
.flashcard-hint { position: absolute; bottom: 8px; font-size: 10px; color: var(--text-muted); }
</style>

<!-- Subject Filters -->
<div style="display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap;">
    <a href="flashcards.php?subject=" class="btn <?= !$filterSubject ? 'btn-primary' : 'btn-secondary' ?> btn-sm">All</a>
    <?php foreach ($subjects as $s): ?>
        <a href="flashcards.php?subject=<?= urlencode($s) ?>" class="btn <?= $filterSubject === $s ? 'btn-primary' : 'btn-secondary' ?> btn-sm"><?= htmlspecialchars($s) ?></a>
    <?php endforeach; ?>
</div>

<?php if (empty($cards)): ?>
    <div class="card" style="text-align: center; padding: 40px; color: var(--text-muted);">No flashcards available yet.</div>
<?php else: ?>
<p style="color: var(--text-muted); font-size: 12px; margin-bottom: 16px;">Click a card to flip it • <?= count($cards) ?> cards</p>
<div class="flashcard-grid">
    <?php foreach ($cards as $c): ?>
    <div class="flashcard" onclick="this.classList.toggle('flipped')">
        <div class="flashcard-inner">
            <div class="flashcard-front">
                <span class="flashcard-subject"><?= htmlspecialchars($c['subject']) ?><?= $c['chapter'] ? ' / ' . htmlspecialchars($c['chapter']) : '' ?></span>
                <?= htmlspecialchars($c['front_text']) ?>
                <span class="flashcard-hint">Click to reveal</span>
            </div>
            <div class="flashcard-back">
                <?= htmlspecialchars($c['back_text']) ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
