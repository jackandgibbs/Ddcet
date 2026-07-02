<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/avatars.php';
$user = requireAuth();
$db = getDB();
$pageTitle = 'Profile';

$profileId = (int) ($_GET['id'] ?? $user['id']);

// Only expose the full row for your OWN profile. For others, fetch a public-safe
// column subset so emails / mobile / google_id / referral data aren't leaked (IDOR).
$profileSelect = ($profileId === (int) $user['id'])
    ? '*'
    : 'id,name,avatar_url,college_id,level,xp,streak,department,semester,created_at';

// Parallel fetch: profile + attempts + badges
$results = supabaseMulti([
    'students?id=eq.' . $profileId . '&select=' . urlencode($profileSelect) . '&limit=1',
    'attempts?student_id=eq.' . $profileId . '&status=eq.completed&select=score,total_marks',
    'student_badges?student_id=eq.' . $profileId . '&select=earned_at,badge_id,badges(name,icon,description)&order=earned_at.desc',
]);

$profile = $results[0][0] ?? null;
if (!$profile) { header('Location: ' . BASE_PATH . 'dashboard.php'); exit; }

// Pick a fun SVG avatar. Stored as a data-URI in avatar_url so it renders
// everywhere an <img src="avatar_url"> already exists. Own profile only.
if ($profileId === (int) $user['id']
    && $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'set_avatar') {
    requireCsrf();
    $key = $_POST['avatar_key'] ?? '';
    $lib = avatarLibrary();
    if (isset($lib[$key])) {
        $uri = avatarDataUri($lib[$key]['svg']);
        supabaseRest('students?id=eq.' . $user['id'], 'PATCH', ['avatar_url' => $uri]);
        $profile['avatar_url'] = $uri;
        $_SESSION['user']['avatar_url'] = $uri;
    }
}

// Get college name
if ($profile['college_id']) {
    $college = supabaseRest('colleges?id=eq.' . $profile['college_id'] . '&select=name&limit=1');
    $profile['college_name'] = $college[0]['name'] ?? null;
} else {
    $profile['college_name'] = null;
}

$attemptsData = $results[1] ?? [];
$testCount = count($attemptsData);
$avgPct = 0;
if ($testCount > 0) {
    $totalPct = 0;
    foreach ($attemptsData as $a) {
        $totalPct += ($a['total_marks'] > 0) ? ($a['score'] * 100.0 / $a['total_marks']) : 0;
    }
    $avgPct = round($totalPct / $testCount);
}

$earnedBadges = $results[2] ?? [];

include __DIR__ . '/includes/header.php';
?>

<div class="card" style="margin-bottom: 20px;">
    <div style="display: flex; align-items: center; gap: 20px;">
        <?php if ($profile['avatar_url']): ?>
            <img src="<?= htmlspecialchars($profile['avatar_url']) ?>" style="width: 72px; height: 72px; border-radius: 50%;">
        <?php endif; ?>
        <div>
            <h2 style="font-size: 20px; margin-bottom: 4px;"><?= htmlspecialchars($profile['name']) ?></h2>
            <p style="color: var(--text-secondary); font-size: 13px;"><?= htmlspecialchars($profile['college_name'] ?? 'No college') ?></p>
            <div style="display: flex; gap: 12px; margin-top: 8px; flex-wrap: wrap;">
                <span class="badge badge-accent"><?= htmlspecialchars($profile['level']) ?></span>
                <span style="font-family: var(--font-mono); font-size: 13px; color: var(--accent);"><?= number_format($profile['xp']) ?> XP</span>
                <span style="font-size: 13px;"><?= icon('fire', 14) ?> <?= $profile['streak'] ?> day streak</span>
                <?php if (!empty($profile['department'])): ?><span class="tag"><?= htmlspecialchars($profile['department']) ?></span><?php endif; ?>
                <?php if (!empty($profile['semester'])): ?><span class="tag">Sem <?= $profile['semester'] ?></span><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card blue"><div class="stat-value"><?= $testCount ?></div><div class="stat-label">Tests Taken</div></div>
    <div class="stat-card green"><div class="stat-value"><?= $avgPct ?>%</div><div class="stat-label">Avg Score</div></div>
    <div class="stat-card accent"><div class="stat-value"><?= number_format($profile['xp']) ?></div><div class="stat-label">Total XP</div></div>
    <div class="stat-card"><div class="stat-value"><?= count($earnedBadges) ?></div><div class="stat-label">Badges</div></div>
</div>

<!-- Badges -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header"><h3>Badges</h3></div>
    <?php if (empty($earnedBadges)): ?>
        <p style="color: var(--text-muted); font-size: 13px;">No badges earned yet.</p>
    <?php else: ?>
    <div style="display: flex; gap: 16px; flex-wrap: wrap;">
        <?php foreach ($earnedBadges as $b):
            $badge = $b['badges'] ?? $b;
        ?>
        <div style="background: var(--bg-primary); border-radius: 8px; padding: 12px; text-align: center; width: 100px;">
            <div style="font-size: 24px;"><?= $badge['icon'] ?? '🏆' ?></div>
            <div style="font-size: 11px; margin-top: 4px; color: var(--text-secondary);"><?= htmlspecialchars($badge['name'] ?? '') ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Referral -->
<?php if ($profileId == $user['id']): ?>

<!-- Avatar picker -->
<?php $currentKey = array_search($profile['avatar_url'] ?? '', avatarDataUris(), true); ?>
<style>
    .avatar-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(60px, 1fr)); gap: 12px; }
    .avatar-tile { cursor: pointer; padding: 3px; background: transparent; border: 2px solid transparent; border-radius: 50%; line-height: 0; width: 100%; aspect-ratio: 1 / 1; transition: transform .12s ease, border-color .15s ease; }
    .avatar-tile:hover { transform: scale(1.1); }
    .avatar-tile.is-selected { border-color: var(--accent); }
    .avatar-tile > span { display: block; width: 100%; height: 100%; border-radius: 50%; overflow: hidden; }
    .avatar-tile svg { display: block; width: 100%; height: 100%; }
    @media (max-width: 480px) {
        .avatar-grid { grid-template-columns: repeat(auto-fill, minmax(52px, 1fr)); gap: 10px; }
    }
</style>
<div class="card" style="margin-top: 20px;">
    <div class="card-header"><h3>Pick your avatar</h3></div>
    <p style="color: var(--text-muted); font-size: 12px; margin-bottom: 14px;">Tap a character to set it as your profile photo — it shows up everywhere, including the leaderboards.</p>
    <form method="POST" id="avatarForm">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
        <input type="hidden" name="action" value="set_avatar">
        <input type="hidden" name="avatar_key" id="avatarKey" value="">
        <div class="avatar-grid">
            <?php foreach (avatarLibrary() as $key => $a):
                $selected = ($key === $currentKey);
            ?>
            <button type="button" class="avatar-tile<?= $selected ? ' is-selected' : '' ?>" data-key="<?= htmlspecialchars($key) ?>" title="<?= htmlspecialchars($a['label']) ?>" aria-label="<?= htmlspecialchars($a['label']) ?>">
                <span><?= $a['svg'] ?></span>
            </button>
            <?php endforeach; ?>
        </div>
    </form>
</div>
<script>
document.querySelectorAll('.avatar-tile').forEach(function (tile) {
    tile.addEventListener('click', function () {
        document.getElementById('avatarKey').value = tile.dataset.key;
        document.getElementById('avatarForm').submit();
    });
});
</script>

<!-- Edit Profile -->
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    $updateData = [];
    if (!empty($_POST['semester'])) $updateData['semester'] = (int)$_POST['semester'];
    if (!empty($_POST['department'])) $updateData['department'] = trim($_POST['department']);
    if (!empty($_POST['mobile'])) $updateData['mobile'] = trim($_POST['mobile']);
    if ($updateData) {
        supabaseRest('students?id=eq.' . $user['id'], 'PATCH', $updateData);
        $profile = array_merge($profile, $updateData);
        $_SESSION['user'] = array_merge($_SESSION['user'], $updateData);
    }
}
?>
<div class="card" style="margin-top: 20px;">
    <div class="card-header"><h3>Edit Profile</h3></div>
    <form method="POST">
        <input type="hidden" name="action" value="update_profile">
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
            <div class="form-group">
                <label>Semester</label>
                <select name="semester" class="form-control">
                    <option value="">Select...</option>
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                        <option value="<?= $i ?>" <?= ($profile['semester'] ?? '') == $i ? 'selected' : '' ?>><?= $i ?> Sem</option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Department</label>
                <select name="department" class="form-control">
                    <option value="">Select...</option>
                    <?php foreach (['Computer','Mechanical','Civil','Electrical','EC','Chemical','Other'] as $d): ?>
                        <option value="<?= $d ?>" <?= ($profile['department'] ?? '') === $d ? 'selected' : '' ?>><?= $d ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Mobile</label>
                <input type="tel" name="mobile" class="form-control" value="<?= htmlspecialchars($profile['mobile'] ?? '') ?>" placeholder="9876543210">
            </div>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Save</button>
    </form>
</div>
<div class="card" style="margin-top: 20px;">
    <div class="card-header"><h3>Your Referral Code</h3></div>
    <div style="display: flex; gap: 12px; align-items: center;">
        <code style="font-size: 18px; font-weight: 700; letter-spacing: 3px; color: var(--accent);"><?= $user['referral_code'] ?></code>
        <button class="btn btn-secondary btn-sm" onclick="navigator.clipboard.writeText('<?= $user['referral_code'] ?>'); this.textContent='Copied!'">Copy</button>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
