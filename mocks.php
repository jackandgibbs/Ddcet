<?php
require_once __DIR__ . '/config.php';
$user = requireAuth();
$pageTitle = 'Mock Series';

$subscription = getSubscription();
$plan = $subscription['plan'] ?? 'free';
$planHierarchy = ['free' => 0, 'basic' => 1, 'pro' => 2];
$userLevel = $planHierarchy[$plan] ?? 0;

// Register for an upcoming mock (idempotent upsert; ignores duplicates).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $testId = (int) ($_POST['test_id'] ?? 0);
    if ($testId) {
        supabaseRest('mock_registrations', 'POST', [
            'test_id' => $testId, 'student_id' => $user['id'],
        ], ['prefer' => 'resolution=ignore-duplicates']);
    }
    header('Location: ' . BASE_PATH . 'mocks.php?registered=1');
    exit;
}

// All scheduled mocks, newest window first.
$mocks = supabaseRest('tests?is_scheduled=eq.true&is_published=eq.true&org_id=is.null'
    . '&select=id,title,series_label,duration_minutes,total_questions,total_marks,'
    . 'opens_at,closes_at,results_at,results_published,is_free,min_plan'
    . '&order=opens_at.desc') ?? [];

$testIds = array_column($mocks, 'id');

// Batch: this user's completed attempts, the user's registrations, and the
// global registration counts — three calls total instead of N per mock.
$myAttempts = [];   // test_id => attempt row
$myRegs = [];       // test_id => true
$regCounts = [];    // test_id => int
if ($testIds) {
    $idList = implode(',', $testIds);

    $attRows = supabaseRest('attempts?student_id=eq.' . $user['id']
        . '&test_id=in.(' . $idList . ')&status=eq.completed'
        . '&select=id,test_id,score,total_marks,percentile&order=completed_at.desc') ?? [];
    foreach ($attRows as $a) {
        if (!isset($myAttempts[$a['test_id']])) $myAttempts[$a['test_id']] = $a;
    }

    $regRows = supabaseRest('mock_registrations?test_id=in.(' . $idList . ')&select=test_id,student_id') ?? [];
    foreach ($regRows as $r) {
        $regCounts[$r['test_id']] = ($regCounts[$r['test_id']] ?? 0) + 1;
        if ($r['student_id'] == $user['id']) $myRegs[$r['test_id']] = true;
    }
}

// Group by lifecycle status so the live/upcoming ones surface first.
$buckets = ['live' => [], 'upcoming' => [], 'grading' => [], 'results' => []];
foreach ($mocks as $m) {
    $buckets[mockStatus($m)][] = $m;
}

include __DIR__ . '/includes/header.php';

/** Render one mock card. */
function mockCard(array $m, string $status, array $ctx): string {
    $id        = (int) $m['id'];
    $title     = $m['series_label'] ?: $m['title'];
    $reg       = $ctx['regCounts'][$id] ?? 0;
    $myAttempt = $ctx['myAttempts'][$id] ?? null;
    $registered= !empty($ctx['myRegs'][$id]);
    $userLevel = $ctx['userLevel'];
    $planHierarchy = ['free' => 0, 'basic' => 1, 'pro' => 2];
    $needLevel = $m['is_free'] ? 0 : ($planHierarchy[$m['min_plan'] ?? 'basic'] ?? 1);
    $locked    = $userLevel < $needLevel;

    $statusBadge = [
        'live'     => '<span class="badge badge-green">● LIVE NOW</span>',
        'upcoming' => '<span class="badge badge-purple">UPCOMING</span>',
        'grading'  => '<span class="badge" style="background:#444;color:#bbb;">GRADING</span>',
        'results'  => '<span class="badge badge-accent">RESULTS OUT</span>',
    ][$status];

    $freeBadge = $m['is_free']
        ? '<span class="badge badge-green">FREE</span>'
        : '<span class="badge badge-accent">' . icon('lock', 11) . ' ' . htmlspecialchars(ucfirst($m['min_plan'] ?? 'basic')) . '</span>';

    ob_start(); ?>
    <div class="card" style="position:relative;">
        <div style="display:flex; align-items:center; gap:8px; margin-bottom:10px; flex-wrap:wrap;">
            <?= $statusBadge ?> <?= $freeBadge ?>
        </div>
        <h3 style="font-size:17px; margin-bottom:4px;"><?= htmlspecialchars($title) ?></h3>
        <p style="color:var(--text-secondary); font-size:13px; margin-bottom:12px;">
            <?= (int)$m['total_questions'] ?> Qs · <?= (int)$m['total_marks'] ?> marks · <?= (int)$m['duration_minutes'] ?> min
        </p>

        <div style="font-size:12px; color:var(--text-muted); margin-bottom:14px; line-height:1.7;">
            <?php if (!empty($m['opens_at'])): ?>
                <div><?= icon('calendar', 12) ?> Opens: <?= date('D, d M · g:i A', strtotime($m['opens_at'])) ?></div>
            <?php endif; ?>
            <?php if (!empty($m['closes_at'])): ?>
                <div><?= icon('flag', 12) ?> Closes: <?= date('D, d M · g:i A', strtotime($m['closes_at'])) ?></div>
            <?php endif; ?>
            <div style="color:var(--accent); font-weight:600; margin-top:4px;">
                <?= icon('users', 12) ?> <span id="regcount-<?= $id ?>" data-count="<?= $reg ?>"><?= number_format($reg) ?></span><span id="reglabel-<?= $id ?>"> aspirant<?= $reg === 1 ? '' : 's' ?> registered</span>
            </div>
        </div>

        <?php if ($status === 'upcoming'): ?>
            <div class="mock-countdown" data-target="<?= date('c', strtotime($m['opens_at'])) ?>"
                 style="font-family:var(--font-mono); font-size:15px; font-weight:700; color:var(--purple); margin-bottom:12px;"></div>
            <?php if ($locked): ?>
                <a href="subscription.php?need=<?= htmlspecialchars($m['min_plan'] ?? 'basic') ?>" class="btn btn-primary btn-sm"><?= icon('lock', 12) ?> Unlock with <?= ucfirst($m['min_plan'] ?? 'basic') ?></a>
            <?php elseif ($registered): ?>
                <span id="regslot-<?= $id ?>" class="badge badge-green"><?= icon('check', 12) ?> You're registered</span>
            <?php else: ?>
                <form method="POST" style="display:inline;" class="mock-reg-form" data-test-id="<?= $id ?>">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
                    <input type="hidden" name="test_id" value="<?= $id ?>">
                    <span id="regslot-<?= $id ?>"><button type="submit" class="btn btn-purple btn-sm">Register →</button></span>
                </form>
            <?php endif; ?>

        <?php elseif ($status === 'live'): ?>
            <div class="mock-countdown" data-target="<?= date('c', strtotime($m['closes_at'] ?? 'now')) ?>" data-prefix="Closes in "
                 style="font-family:var(--font-mono); font-size:13px; color:var(--green); margin-bottom:12px;"></div>
            <?php if ($myAttempt): ?>
                <span class="badge badge-green"><?= icon('check', 12) ?> Submitted</span>
                <a href="result.php?attempt_id=<?= (int)$myAttempt['id'] ?>" class="btn btn-secondary btn-sm">View Answers</a>
            <?php elseif ($locked): ?>
                <a href="subscription.php?need=<?= htmlspecialchars($m['min_plan'] ?? 'basic') ?>" class="btn btn-primary btn-sm"><?= icon('lock', 12) ?> Unlock with <?= ucfirst($m['min_plan'] ?? 'basic') ?></a>
            <?php else: ?>
                <a href="exam.php?test_id=<?= $id ?>" class="btn btn-success btn-sm">Start Mock →</a>
            <?php endif; ?>

        <?php elseif ($status === 'grading'): ?>
            <p style="font-size:12px; color:var(--text-muted); margin-bottom:10px;">Window closed. All-India Rank &amp; solutions reveal soon.</p>
            <?php if ($myAttempt): ?>
                <span class="badge" style="background:#444;color:#bbb;">You attempted · awaiting rank</span>
            <?php else: ?>
                <span class="badge badge-red">Missed</span>
            <?php endif; ?>

        <?php else: /* results */ ?>
            <?php if ($myAttempt):
                $air = mockAIR($id, (float)$myAttempt['score']); ?>
                <?php if ($air): ?>
                    <div style="display:flex; align-items:baseline; gap:8px; margin-bottom:10px;">
                        <span style="font-family:var(--font-mono); font-size:28px; font-weight:900; color:var(--accent);">#<?= number_format($air['rank']) ?></span>
                        <span style="font-size:12px; color:var(--text-muted);">of <?= number_format($air['total']) ?> · <?= $air['percentile'] ?> %ile</span>
                    </div>
                <?php endif; ?>
                <a href="result.php?attempt_id=<?= (int)$myAttempt['id'] ?>" class="btn btn-primary btn-sm">View Rank &amp; Solutions →</a>
            <?php else: ?>
                <p style="font-size:12px; color:var(--text-muted);">You didn't take this mock.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

$ctx = ['regCounts' => $regCounts, 'myAttempts' => $myAttempts, 'myRegs' => $myRegs, 'userLevel' => $userLevel];
?>

<?php if (($_GET['registered'] ?? '') === '1'): ?>
<div class="card" style="border-color:var(--green); margin-bottom:16px; padding:12px 16px; font-size:13px; color:var(--green);">
    You're registered. We'll see you when the mock goes live — one paper, one window, one All-India Rank.
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:20px; background:linear-gradient(135deg,#1a1a2e,#2d1b69); color:#fff; border:none;">
    <h2 style="font-size:20px; margin-bottom:6px;">DDCET Mock Series</h2>
    <p style="font-size:13px; opacity:0.85; max-width:640px;">Everyone takes the same paper in the same window. When it closes, your All-India Rank and full solutions unlock. One free mock every series — the rest are part of Pro.</p>
</div>

<?php
$sections = [
    'live'     => 'Live Now',
    'upcoming' => 'Upcoming',
    'results'  => 'Results Out',
    'grading'  => 'Grading',
];
$any = false;
foreach ($sections as $key => $label):
    if (empty($buckets[$key])) continue;
    $any = true;
?>
    <h3 style="font-size:14px; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px; margin:24px 0 12px;"><?= $label ?></h3>
    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:16px;">
        <?php foreach ($buckets[$key] as $m) echo mockCard($m, $key, $ctx); ?>
    </div>
<?php endforeach; ?>

<?php if (!$any): ?>
    <div class="card" style="text-align:center; padding:48px;">
        <div style="font-size:32px; margin-bottom:12px;"><?= icon('calendar', 32) ?></div>
        <h3 style="margin-bottom:6px;">No scheduled mocks yet</h3>
        <p style="color:var(--text-muted); font-size:13px;">The next DDCET mock will appear here. Meanwhile, sharpen up with <a href="tests.php">practice tests →</a></p>
    </div>
<?php endif; ?>

<script>
window.DDCET_CSRF = '<?= csrfToken() ?>';

// Register without a page reload: flip the button to "registered" and tick the
// aspirant counter up live. Falls back to a normal form POST if JS is off.
document.querySelectorAll('.mock-reg-form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var id = form.dataset.testId;
        var btn = form.querySelector('button');
        if (btn) { btn.disabled = true; btn.textContent = 'Registering…'; }

        fetch('api/register_mock.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.DDCET_CSRF },
            body: JSON.stringify({ test_id: id })
        }).then(function (r) { return r.json(); }).then(function (d) {
            if (d && d.ok) {
                var slot = document.getElementById('regslot-' + id);
                if (slot) slot.outerHTML = '<span class="badge badge-green">✓ You\'re registered</span>';
                var c = document.getElementById('regcount-' + id);
                if (c) {
                    var n = (typeof d.count === 'number') ? d.count : (parseInt(c.dataset.count || '0', 10) + 1);
                    c.dataset.count = n;
                    c.textContent = n.toLocaleString();
                    var lbl = document.getElementById('reglabel-' + id);
                    if (lbl) lbl.textContent = (n === 1 ? ' aspirant registered' : ' aspirants registered');
                }
                if (typeof showToast === 'function') showToast("You're registered for this mock");
            } else {
                if (btn) { btn.disabled = false; btn.textContent = 'Register →'; }
                if (typeof showToast === 'function') showToast('Could not register. Try again.');
            }
        }).catch(function () {
            if (btn) { btn.disabled = false; btn.textContent = 'Register →'; }
            if (typeof showToast === 'function') showToast('Network error. Try again.');
        });
    });
});

// Live countdowns for upcoming/live mocks.
function ddcetMockCountdowns() {
    document.querySelectorAll('.mock-countdown').forEach(function (el) {
        var target = new Date(el.dataset.target).getTime();
        var prefix = el.dataset.prefix || '';
        function tick() {
            var diff = target - Date.now();
            if (diff <= 0) { el.textContent = prefix + 'now'; setTimeout(function(){location.reload();}, 1500); return; }
            var d = Math.floor(diff / 86400000),
                h = Math.floor((diff % 86400000) / 3600000),
                m = Math.floor((diff % 3600000) / 60000),
                s = Math.floor((diff % 60000) / 1000);
            el.textContent = prefix + (d > 0 ? d + 'd ' : '') + h + 'h ' + m + 'm ' + s + 's';
        }
        tick(); setInterval(tick, 1000);
    });
}
ddcetMockCountdowns();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
