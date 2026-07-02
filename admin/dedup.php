<?php
set_time_limit(0);
require_once __DIR__ . '/../config.php';
requireAdmin();
$pageTitle = 'Manage Questions';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_all') {
        // Delete all options first, then all pool questions
        supabaseRest('options?question_id=not.is.null', 'DELETE');
        supabaseRest('questions?test_id=is.null', 'DELETE');
        $msg = 'success:All pool questions deleted.';
    }

    if ($action === 'dedup') {
        $all = supabaseRest('questions?select=id,question_text&order=id&limit=10000') ?? [];
        $seen = [];
        $toDelete = [];
        foreach ($all as $q) {
            $key = strtolower(trim($q['question_text']));
            if (isset($seen[$key])) { $toDelete[] = $q['id']; } else { $seen[$key] = true; }
        }
        if ($toDelete) {
            foreach (array_chunk($toDelete, 100) as $batch) {
                supabaseRest('options?question_id=in.(' . implode(',', $batch) . ')', 'DELETE');
                supabaseRest('questions?id=in.(' . implode(',', $batch) . ')', 'DELETE');
            }
        }
        $msg = 'success:Removed ' . count($toDelete) . ' duplicates.';
    }
}

$totalQ = supabaseRest('questions?test_id=is.null&select=id&limit=10000') ?? [];
$totalCount = count($totalQ);

include __DIR__ . '/includes/header.php';
?>

<?php if ($msg): $type = str_starts_with($msg, 'success') ? 'green' : 'red'; ?>
<div class="card" style="border-color: var(--<?= $type ?>); margin-bottom: 16px; padding: 12px 16px; font-size: 13px; color: var(--<?= $type ?>);"><?= substr($msg, strpos($msg,':')+1) ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom: 20px;">
    <div class="card-header"><h3>Question Pool: <?= $totalCount ?> questions</h3></div>
    <div style="display: flex; gap: 12px; margin-top: 12px;">
        <form method="POST" onsubmit="return confirm('Remove duplicate questions?')">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
            <input type="hidden" name="action" value="dedup">
            <button type="submit" class="btn btn-secondary btn-sm">Remove Duplicates</button>
        </form>
        <form method="POST" onsubmit="return confirm('DELETE ALL QUESTIONS? This cannot be undone!')">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
            <input type="hidden" name="action" value="delete_all">
            <button type="submit" class="btn btn-danger btn-sm">Delete All Questions</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
