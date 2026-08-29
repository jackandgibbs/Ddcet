<?php
require_once __DIR__ . '/config.php';
$user = requireAuth();
// BUG-040 fix: removed unused $db = getDB() call
$pageTitle = 'Test Modes';

$subscription = getSubscription();
$plan = $subscription['plan'] ?? 'free';
// One free full mock for non-subscribed users (see hasFreeMock in config.php).
$freeMock = hasFreeMock($user);

// Test modes definition (top grid cards)
// min_plan here mirrors modeMinPlan() in config.php (the authoritative gate in
// exam.php). Keep them in sync so a card never shows "Start" for a mode the
// server will reject, and never locks a mode the server actually allows.
$modes = [
    ['mode' => 'full_mock', 'title' => 'Full DDCET Mock', 'desc' => '100 Qs, 200 marks, 150 min — exact real exam pattern', 'icon' => 'clipboard', 'min_plan' => 'basic'],
    ['mode' => 'be01_paper', 'title' => 'BE-01 Paper', 'desc' => 'Science & Engineering — 50 Qs, 75 min', 'icon' => 'ruler', 'min_plan' => 'basic'],
    ['mode' => 'be02_paper', 'title' => 'BE-02 Paper', 'desc' => 'Maths & Soft Skills — 50 Qs, 75 min', 'icon' => 'calculator', 'min_plan' => 'basic'],
    ['mode' => 'rapid_fire', 'title' => 'Rapid Fire', 'desc' => '30 questions, 60 sec each', 'icon' => 'bolt', 'min_plan' => 'basic'],
    ['mode' => 'subject_wise', 'title' => 'Subject-wise Test', 'desc' => 'Practice by topic', 'icon' => 'ruler', 'min_plan' => 'basic', 'link' => '#subjectSection'],
    ['mode' => 'previous_year', 'title' => 'Previous Year Papers', 'desc' => 'Past DDCET papers — Pro', 'icon' => 'document', 'min_plan' => 'pro', 'link' => '#previousYearSection'],
    ['mode' => 'revision', 'title' => 'Revision Mode', 'desc' => 'Re-attempt your wrong questions', 'icon' => 'refresh', 'min_plan' => 'basic'],
];

$planHierarchy = ['free' => 0, 'basic' => 1, 'pro' => 2];
$userLevel = $planHierarchy[$plan] ?? 0;

include __DIR__ . '/includes/header.php';

// Banner when an attempt could not be created (usually a missing DB migration).
if (($_GET['error'] ?? '') === 'start_failed'): ?>
<div class="card" style="border-color: var(--red); margin-bottom: 16px; padding: 12px 16px; font-size: 13px; color: var(--red);">
    Couldn't start the test. If this just started happening, an admin needs to run the
    <strong>database/test_integrity.sql</strong> migration in Supabase (it adds the required <code>attempts.mode</code> column).
</div>
<?php endif;

// Friendly banner when a server-side attempt limit blocked a start.
$limitHit = $_GET['limit'] ?? '';
if ($limitHit && isset(attemptLimits()[$limitHit])):
    $gate = canAttempt($limitHit, $user);
?>
<div class="card" style="border-color: var(--orange); margin-bottom: 16px; padding: 12px 16px; font-size: 13px; color: var(--orange);">
    <strong><?= htmlspecialchars(ucwords(str_replace('_', ' ', $limitHit))) ?>:</strong>
    <?= htmlspecialchars($gate['reason'] ?? 'Limit reached for now.') ?>
</div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px;">
<?php foreach ($modes as $m):
    $requiredLevel = $planHierarchy[$m['min_plan']] ?? 0;
    $locked = $userLevel < $requiredLevel;
    // The full mock is unlockable once, for free, by a non-subscribed user.
    $freeMockCard = ($locked && $m['mode'] === 'full_mock' && $freeMock);
    if ($freeMockCard) $locked = false;
?>
    <div class="card" style="position: relative; <?= $locked ? 'opacity: 0.6;' : '' ?>">
        <div style="font-size: 32px; margin-bottom: 12px;"><?= icon($m['icon'], 32) ?></div>
        <h3 style="font-size: 16px; margin-bottom: 4px;"><?= $m['title'] ?></h3>
        <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 16px;"><?= $m['desc'] ?></p>
        <?php if ($freeMockCard): ?>
            <span class="badge badge-green" style="margin-bottom: 8px; display:inline-block;">★ 1 Free Mock</span><br>
        <?php endif; ?>
        <?php if ($locked): ?>
            <span class="badge badge-accent"><?= icon('lock', 12) ?> <?= ucfirst($m['min_plan']) ?> Plan</span>
        <?php else: ?>
            <?php
            $btnClass = match($m['mode']) {
                'full_mock' => 'btn-primary',
                'be01_paper' => 'btn-primary',
                'be02_paper' => 'btn-primary',
                'rapid_fire' => 'btn-orange',
                'daily_challenge' => 'btn-success',
                'weekly_challenge' => 'btn-purple',
                'challenge_friend' => 'btn-dark',
                default => 'btn-outline',
            };
            ?>
            <a href="<?= $m['link'] ?? ('exam.php?mode=' . $m['mode']) ?>" class="btn <?= $btnClass ?> btn-sm">Start →</a>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
</div>

<?php
// Institution assignments — published tests private to the student's own batch.
// These are deliberately excluded from the public lists above (org_id=is.null),
// so this is the one place a batch student sees their institution's tests.
$myOrg = currentOrg();
if ($myOrg):
    $orgTests = supabaseRest('tests?org_id=eq.' . (int) $myOrg['id'] . '&is_published=eq.true&order=created_at.desc&select=*') ?? [];
?>
<div class="card" style="margin-top: 24px; border-color: var(--accent);">
    <div class="card-header"><h3><?= htmlspecialchars($myOrg['name']) ?> — Assignments</h3><span class="badge badge-accent">Institution</span></div>
    <?php if (empty($orgTests)): ?>
        <p style="color: var(--text-muted);">No assignments published yet. Your institution will release them here.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Title</th><th>Questions</th><th>Duration</th><th>Marks</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($orgTests as $test): ?>
                    <tr>
                        <td><?= htmlspecialchars($test['title']) ?></td>
                        <td style="font-family: var(--font-mono);"><?= $test['total_questions'] ?></td>
                        <td style="font-family: var(--font-mono);"><?= $test['duration_minutes'] ?> min</td>
                        <td style="font-family: var(--font-mono);"><?= $test['total_marks'] ?></td>
                        <td><a href="exam.php?test_id=<?= $test['id'] ?>" class="btn btn-primary btn-sm">Attempt</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php
// If mode selected, show available tests
if (isset($_GET['list']) && isset($_GET['mode'])):
    $mode = $_GET['mode'];
    // org_id=is.null keeps private institution assignments out of the public pool.
    $tests = supabaseRest('tests?mode=eq.' . urlencode($mode) . '&is_published=eq.true&org_id=is.null&order=created_at.desc&select=*') ?? [];
?>
<div class="card" style="margin-top: 24px;">
    <div class="card-header"><h3>Available Tests</h3></div>
    <?php if (empty($tests)): ?>
        <p style="color: var(--text-muted);">No fixed tests available. Use "Start →" button above to take from question pool.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Title</th><th>Questions</th><th>Duration</th><th>Marks</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($tests as $test): ?>
                    <tr>
                        <td><?= htmlspecialchars($test['title']) ?></td>
                        <td style="font-family: var(--font-mono);"><?= $test['total_questions'] ?></td>
                        <td style="font-family: var(--font-mono);"><?= $test['duration_minutes'] ?> min</td>
                        <td style="font-family: var(--font-mono);"><?= $test['total_marks'] ?></td>
                        <td><a href="exam.php?test_id=<?= $test['id'] ?>" class="btn btn-primary btn-sm">Attempt</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Previous Year Papers (Pro) -->
<div class="card" style="margin-top: 24px;" id="previousYearSection">
    <div class="card-header"><h3>Previous Year Papers</h3><span class="badge badge-accent"><?= icon('lock', 12) ?> Pro</span></div>
    <?php if ($userLevel >= 2): ?>
        <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 12px;">Select a year to attempt that year's DDCET paper:</p>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="exam.php?mode=previous_year&year=2024" class="btn btn-outline btn-sm">DDCET 2024</a>
            <a href="exam.php?mode=previous_year&year=2025" class="btn btn-outline btn-sm">DDCET 2025</a>
            <a href="exam.php?mode=previous_year&year=2026" class="btn btn-outline btn-sm">DDCET 2026</a>
        </div>
    <?php else: ?>
        <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 12px;">Previous-year DDCET papers are a Pro feature. Upgrade to unlock every year's paper.</p>
        <a href="subscription.php?need=pro" class="btn btn-primary btn-sm">Upgrade to Pro →</a>
    <?php endif; ?>
</div>

<!-- Subject Selection -->
<div class="card" style="margin-top: 24px;" id="subjectSection">
    <div class="card-header"><h3>Subject-wise Practice</h3></div>
    <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 12px;">Pick a subject and get 30 random questions:</p>
    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <a href="exam.php?mode=subject_wise&subject=Physics&force_new=1" class="btn btn-outline btn-sm">Physics</a>
        <a href="exam.php?mode=subject_wise&subject=Chemistry&force_new=1" class="btn btn-outline btn-sm">Chemistry</a>
        <a href="exam.php?mode=subject_wise&subject=Maths&force_new=1" class="btn btn-outline btn-sm">Maths</a>
        <a href="exam.php?mode=subject_wise&subject=English&force_new=1" class="btn btn-outline btn-sm">English</a>
        <a href="exam.php?mode=subject_wise&subject=Computers&force_new=1" class="btn btn-outline btn-sm">Computers</a>
        <a href="exam.php?mode=subject_wise&subject=Environment&force_new=1" class="btn btn-outline btn-sm">Environment</a>
    </div>
</div>

<!-- Topic-wise Practice (Pro) -->
<div class="card" style="margin-top: 24px;" id="topicSection">
    <div class="card-header"><h3>Topic-wise Practice</h3><span class="badge badge-accent"><?= icon('lock', 12) ?> Pro</span></div>
    <?php if ($userLevel >= 2): ?>
        <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 12px;">Select a specific topic to practice (20 questions):</p>
        <?php
        $topicsData = supabaseRest('questions?select=subject,chapter&chapter=not.is.null&limit=3000') ?? [];
        $topicsBySubject = [];
        foreach ($topicsData as $row) {
            if (!empty($row['subject']) && !empty($row['chapter'])) {
                $topicsBySubject[$row['subject']][$row['chapter']] = true;
            }
        }
        ?>
        <?php if (empty($topicsBySubject)): ?>
            <p style="color: var(--text-muted); font-size: 13px;">No topics available yet.</p>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <?php foreach ($topicsBySubject as $subj => $topics): ?>
                    <div>
                        <strong style="display: block; margin-bottom: 8px; font-size: 14px; color: var(--text-primary);"><?= htmlspecialchars($subj) ?></strong>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                            <?php foreach ($topics as $chap => $val): ?>
                                <a href="exam.php?mode=topic_wise&subject=<?= urlencode($subj) ?>&chapter=<?= urlencode($chap) ?>&force_new=1" class="btn btn-outline btn-sm"><?= htmlspecialchars($chap) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div style="text-align: center; padding: 20px 0;">
            <div style="font-size: 32px; margin-bottom: 12px; color: var(--accent);"><?= icon('lock', 32) ?></div>
            <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 16px;">Topic-wise testing is a Pro feature.</p>
            <a href="subscription.php?need=pro" class="btn btn-primary btn-sm">Upgrade to Pro →</a>
        </div>
    <?php endif; ?>
</div>

<!-- Daily Challenge -->
<div class="card" style="margin-top: 24px;">
    <div class="card-header"><h3>Daily Challenge</h3><span class="badge badge-green">Free</span></div>
    <?php
    $todayDone = false;
    $todayAttempts = supabaseRest('attempts?student_id=eq.' . $user['id'] . '&mode=eq.daily_challenge&started_at=gte.' . date('Y-m-d') . '&select=id&limit=1') ?? [];
    if (!empty($todayAttempts)) $todayDone = true;
    ?>
    <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 12px;">10 unique DDCET questions every day. Free for all users.</p>
    <?php if ($todayDone): ?>
        <span class="badge badge-green">Today's challenge completed! Come back tomorrow.</span>
    <?php else: ?>
        <a href="exam.php?mode=daily_challenge" class="btn btn-success btn-sm">Start Today's Challenge →</a>
    <?php endif; ?>
</div>

<!-- Weekly Challenge - Only visible on Sunday -->
<?php $isSunday = (date('N') == 7); ?>
<?php if ($isSunday): ?>
<div class="card" style="margin-top: 24px;" id="weeklySection">
    <div class="card-header"><h3>Weekly Challenge</h3><span class="badge badge-purple">LIVE NOW</span></div>
    <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 12px;">It's Sunday! 50 questions, bonus XP. Pro users only.</p>
    <?php if ($userLevel >= 2): ?>
        <a href="exam.php?mode=weekly_challenge" class="btn btn-purple btn-sm">Start Weekly Challenge →</a>
    <?php else: ?>
        <a href="subscription.php" class="btn btn-purple btn-sm">Upgrade to Pro →</a>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="card" style="margin-top: 24px;" id="weeklySection">
    <div class="card-header"><h3>Weekly Challenge</h3><span class="badge badge-purple">Pro · Sundays</span></div>
    <p style="font-size: 13px; color: var(--text-secondary);">Available every Sunday. Next one in:</p>
    <div style="margin-top: 8px; display: inline-flex; align-items: center; gap: 8px; background: var(--bg-primary); padding: 10px 16px; border-radius: 8px;">
        <svg width="14" height="14" fill="none" stroke="var(--purple)" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
        <span style="font-family: var(--font-mono); font-size: 14px; color: var(--purple); font-weight: 600;" id="weeklyTimer"></span>
    </div>
    <script>
    (function(){
        const next = new Date('<?= date('Y-m-d', strtotime('next sunday')) ?>T00:00:00');
        function tick(){
            const diff = next - new Date();
            if(diff<=0){location.reload();return;}
            const d=Math.floor(diff/86400000),h=Math.floor((diff%86400000)/3600000),m=Math.floor((diff%3600000)/60000),s=Math.floor((diff%60000)/1000);
            document.getElementById('weeklyTimer').textContent=d+'d '+h+'h '+m+'m '+s+'s';
        }
        tick();setInterval(tick,1000);
    })();
    </script>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
