<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/branches.php';
$user = requireAuth();
$pageTitle = 'Dashboard';
// BUG-016: Chart.js is now loaded only on pages that need it (removed from global header)
$extraHead = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';

// Stats via parallel REST API calls (all at once instead of sequential)
// Stats via parallel REST API calls (all at once instead of sequential)
$results = supabaseRestMulti([
    'attempts' => 'attempts?student_id=eq.' . $user['id'] . '&status=eq.completed&select=id,score,total_marks,completed_at,xp_earned,test_id&order=completed_at.desc',
    'subscriptions' => 'subscriptions?student_id=eq.' . $user['id'] . '&status=eq.active&expires_at=gt.' . urlencode(date('c')) . '&order=expires_at.desc&limit=1',
    'tests' => 'tests?is_scheduled=eq.true&is_published=eq.true&select=id,title,series_label,opens_at,closes_at,results_at,results_published,is_free,min_plan&order=opens_at.desc&limit=10',
]);

$attempts = $results['attempts'] ?? [];
$subscription = !empty($results['subscriptions']) ? $results['subscriptions'][0] : null;
$schedMocks = $results['tests'] ?? [];
$attemptCount = count($attempts);

$avgScorePercent = 0;
$readiness = 0;
if ($attemptCount > 0) {
    $totalPct = 0;
    foreach ($attempts as $a) {
        $totalPct += ($a['total_marks'] > 0) ? ($a['score'] * 100.0 / $a['total_marks']) : 0;
    }
    $avgScorePercent = round($totalPct / $attemptCount);

    // Readiness: avg of last 5
    $last5 = array_slice($attempts, 0, 5);
    $rPct = 0;
    foreach ($last5 as $a) {
        $rPct += ($a['total_marks'] > 0) ? ($a['score'] * 100.0 / $a['total_marks']) : 0;
    }
    $readiness = round($rPct / count($last5));
}

// Parallel ranking ladders so the dashboard shows where the student actually
// stands, not a placeholder. Each is a light live lookup (see config.php).
$rank        = globalRank((int)$user['id'], (int)($user['xp'] ?? 0)) ?? '-';
$collegeRk   = collegeRank((int)$user['id'], $user['college_id'] ?? null);
$dailyRk     = dailyChallengeRank((int)$user['id']);
$dailyStreak = 0;
$department  = null;
$dsRow = supabaseRest('students?id=eq.' . (int)$user['id'] . '&select=daily_streak,department&limit=1');
if (!empty($dsRow)) {
    $dailyStreak = (int)($dsRow[0]['daily_streak'] ?? 0);
    $department  = $dsRow[0]['department'] ?? null;
}
$plan = branchPlan($department);

// Exam countdown
$examDate = new DateTime(DDCET_EXAM_DATE);
$today = new DateTime();
$daysLeft = max(0, (int) $today->diff($examDate)->format('%r%a'));

// Heatmap: last 90 days activity
$heatmapData = [];
if ($attemptCount > 0) {
    foreach ($attempts as $a) {
        $completedAt = $a['completed_at'] ?? $a['created_at'] ?? 'now';
        $day = date('Y-m-d', strtotime($completedAt));
        $heatmapData[$day] = ($heatmapData[$day] ?? 0) + 1;
    }
}

// Subject-wise strength: aggregate accuracy per subject across the user's attempts
$subjects = [];
if ($attemptCount > 0) {
    // Bound the workload to the most recent attempts
    $recentIds = array_filter(array_column(array_slice($attempts, 0, 25), 'id'));
    if ($recentIds) {
        $answerRows = supabaseRest(
            'attempt_answers?attempt_id=in.(' . implode(',', $recentIds) . ')&select=question_id,selected_option_id,is_correct&limit=3000'
        ) ?? [];

        if ($answerRows) {
            $qIds = array_values(array_unique(array_filter(array_column($answerRows, 'question_id'))));
            $subjectOf = [];   // question_id => subject
            $correctOpt = [];  // question_id => correct option id
            if ($qIds) {
                $qList = implode(',', $qIds);
                $qRows = supabaseRest('questions?id=in.(' . $qList . ')&select=id,subject') ?? [];
                foreach ($qRows as $q) $subjectOf[$q['id']] = $q['subject'] ?: 'General';

                // Correct option per question — lets us score historical attempts even when is_correct was never stored
                $optRows = supabaseRest('options?question_id=in.(' . $qList . ')&is_correct=eq.true&select=id,question_id') ?? [];
                foreach ($optRows as $o) $correctOpt[$o['question_id']] = $o['id'];
            }

            // Tally correct / total per subject
            $agg = [];
            foreach ($answerRows as $row) {
                $qId = $row['question_id'];
                $sub = $subjectOf[$qId] ?? 'General';
                if (!isset($agg[$sub])) $agg[$sub] = ['correct' => 0, 'total' => 0];
                $agg[$sub]['total']++;

                // Prefer a stored is_correct; otherwise derive it from the selected vs correct option
                if (isset($row['is_correct'])) {
                    $isCorrect = !empty($row['is_correct']);
                } else {
                    $isCorrect = !empty($row['selected_option_id'])
                        && isset($correctOpt[$qId])
                        && $row['selected_option_id'] == $correctOpt[$qId];
                }
                if ($isCorrect) $agg[$sub]['correct']++;
            }

            foreach ($agg as $sub => $t) {
                $subjects[] = [
                    'subject' => $sub,
                    'avg_pct' => $t['total'] > 0 ? ($t['correct'] * 100.0 / $t['total']) : 0,
                ];
            }
            // Stable, readable order: strongest subjects first
            usort($subjects, fn($a, $b) => $b['avg_pct'] <=> $a['avg_pct']);
        }
    }
}

// Recent tests
$recentTests = [];
if ($attemptCount > 0) {
    $last5Attempts = array_slice($attempts, 0, 5);
    // Batch fetch test titles
    $testIds = array_filter(array_unique(array_column($last5Attempts, 'test_id')));
    $testMap = [];
    if ($testIds) {
        $tests = supabaseRest('tests?id=in.(' . implode(',', $testIds) . ')&select=id,title,mode');
        if ($tests) foreach ($tests as $t) $testMap[$t['id']] = $t;
    }
    foreach ($last5Attempts as $a) {
        $t = $testMap[$a['test_id'] ?? 0] ?? null;
        $a['title'] = $t['title'] ?? 'Practice Test';
        $a['mode'] = $t['mode'] ?? '';
        $recentTests[] = $a;
    }
}

// Headline scheduled mock for the FOMO banner: a live one if any, else the next
// upcoming, else the most recent with results out.
$featuredMock = null; $featuredStatus = null; $featuredReg = 0;
$priority = ['live' => 0, 'upcoming' => 1, 'results' => 2, 'grading' => 3];
$bestRank = 99;
foreach ($schedMocks as $sm) {
    $st = mockStatus($sm);
    if (($priority[$st] ?? 9) < $bestRank) { $bestRank = $priority[$st]; $featuredMock = $sm; $featuredStatus = $st; }
}
if ($featuredMock) $featuredReg = mockRegistrationCount((int)$featuredMock['id']);

// "Your next move" — pick the single highest-value action for where the student
// actually is, so the dashboard coaches instead of just reporting numbers.
// $subjects is sorted strongest-first, so the last entry is the weakest subject.
$nextMove = null;
if ($attemptCount > 0) {
    $weakest = end($subjects) ?: null;
    reset($subjects);
    if ($weakest && $weakest['avg_pct'] < 60) {
        $nextMove = [
            'title' => htmlspecialchars($weakest['subject']) . ' is your weak spot — ' . round($weakest['avg_pct']) . '% accuracy',
            'body'  => 'A short, focused drill on your weakest subject is the fastest way to lift your score with ' . $daysLeft . ' days to go.',
            'cta'   => 'Drill ' . htmlspecialchars($weakest['subject']) . ' →',
            'href'  => 'tests.php?mode=subject_wise',
        ];
    } elseif ($readiness >= 75) {
        $nextMove = [
            'title' => "You're tracking exam-ready — " . $readiness . '% readiness',
            'body'  => 'Lock it in under real conditions: take a full timed mock and defend your rank.',
            'cta'   => 'Take a full mock →',
            'href'  => 'mocks.php',
        ];
    } else {
        $nextMove = [
            'title' => 'Keep the momentum going',
            'body'  => 'Your readiness is ' . $readiness . '%. One more test today nudges it up — and keeps your streak alive.',
            'cta'   => 'Start a test →',
            'href'  => 'tests.php',
        ];
    }
}

include __DIR__ . '/includes/header.php';
?>

<!-- Ask AI About Overall Performance -->
<?php if ($attemptCount > 0): ?>
<div class="card" id="overallAiCard" style="margin-bottom: 20px; border: 1px solid rgba(99, 102, 241, 0.3); overflow: hidden;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div style="width: 40px; height: 40px; border-radius: 12px; background: var(--accent-light); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; color: var(--accent); font-size: 20px; flex-shrink: 0;">
                <?= icon('activity', 22) ?>
            </div>
            <div>
                <h3 style="margin: 0; font-size: 15px;">Overall Performance AI Report</h3>
                <p style="margin: 0; font-size: 12px; color: var(--text-muted);">Get a comprehensive AI assessment of your entire prep journey so far</p>
            </div>
        </div>
        <button id="analyzeOverallBtn" class="btn btn-primary btn-sm" onclick="analyzeOverallReport()" style="padding: 8px 20px; font-weight: 700; font-size: 13px; border-radius: 8px; white-space: nowrap;">
            <?= icon('zap', 14) ?> Analyze Trajectory
        </button>
    </div>
    <div id="overallAiResult" style="display: none; margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border);"></div>
</div>
<?php endif; ?>

<?php if ($featuredMock): ?>
<div class="card" style="margin-bottom:20px; background:var(--accent-light); border:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:14px;">
    <div>
        <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
            <?php if ($featuredStatus === 'live'): ?><span class="badge badge-green">● LIVE NOW</span>
            <?php elseif ($featuredStatus === 'upcoming'): ?><span class="badge badge-purple">UPCOMING</span>
            <?php else: ?><span class="badge badge-accent">RESULTS OUT</span><?php endif; ?>
            <?= !empty($featuredMock['is_free']) ? '<span class="badge badge-green">FREE</span>' : '' ?>
        </div>
        <div style="font-size:18px; font-weight:800;"><?= htmlspecialchars($featuredMock['series_label'] ?: $featuredMock['title']) ?></div>
        <div style="font-size:12px; opacity:0.8;">
            <?php if ($featuredStatus === 'upcoming'): ?>
                <span class="dash-countdown" data-target="<?= date('c', strtotime($featuredMock['opens_at'])) ?>"></span> ·
            <?php elseif ($featuredStatus === 'live'): ?>
                <span class="dash-countdown" data-prefix="Closes in " data-target="<?= date('c', strtotime($featuredMock['closes_at'] ?? 'now')) ?>"></span> ·
            <?php endif; ?>
            <?= number_format($featuredReg) ?> aspirants registered
        </div>
    </div>
    <a href="mocks.php" class="btn btn-orange btn-sm">
        <?= $featuredStatus === 'live' ? 'Take it now →' : ($featuredStatus === 'upcoming' ? 'Register →' : 'See your rank →') ?>
    </a>
</div>
<script>
document.querySelectorAll('.dash-countdown').forEach(function(el){
    var t=new Date(el.dataset.target).getTime(), p=el.dataset.prefix||'';
    function tick(){var d=t-Date.now();if(d<=0){el.textContent=p+'now';return;}
        var dd=Math.floor(d/86400000),h=Math.floor((d%86400000)/3600000),m=Math.floor((d%3600000)/60000),s=Math.floor((d%60000)/1000);
        el.textContent=p+(dd>0?dd+'d ':'')+h+'h '+m+'m '+s+'s';}
    tick();setInterval(tick,1000);
});
</script>
<?php endif; ?>

<?php /* First-time freebie: invite non-subscribed users to try one full mock. */ ?>
<?php if (hasFreeMock($user)): ?>
<div class="card" style="margin-bottom:20px; background:var(--accent-light); border:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:14px;">
    <div>
        <div style="margin-bottom:4px;"><span class="badge badge-green">★ FREE</span></div>
        <div style="font-size:20px; font-weight:800;">Try the mock test for free — see your results</div>
        <div style="font-size:13px; color:var(--text-secondary);">One full DDCET mock (100 Qs, real exam pattern) on us. No payment needed — just start and get your score, rank and weak areas.</div>
    </div>
    <a href="exam.php?mode=full_mock" class="btn btn-orange btn-sm" style="white-space:nowrap;">Start free mock →</a>
</div>
<?php endif; ?>

<!-- Subscription Popup Modal -->
<?php if (isset($_SESSION['show_subscription_popup']) && $_SESSION['show_subscription_popup']): ?>
<div id="subscriptionModal" class="modal-overlay">
    <div class="modal-content">
        <button class="modal-close" onclick="closeSubscriptionModal()">✕</button>
        <h2 style="font-size: 24px; font-weight: 800; margin-bottom: 8px; color: #1a1a2e;">Subscribe Now</h2>
        <p style="color: #6c757d; margin-bottom: 32px; font-size: 14px;">Enjoy unlimited access and no more ads!</p>

        <div style="margin-bottom: 24px;">
            <div style="border-bottom: 1px solid #e9ecef; padding-bottom: 12px; margin-bottom: 12px;">
                <div style="font-weight: 600; font-size: 15px; margin-bottom: 8px;">Basic Plan - ₹149/year</div>
                <ul style="list-style: none; padding: 0; margin: 0; font-size: 13px; color: #6c757d;">
                    <li style="padding: 4px 0;">• Full Mock Tests</li>
                    <li style="padding: 4px 0;">• Rapid Fire & Subject Tests</li>
                    <li style="padding: 4px 0;">• Weekly Challenges</li>
                </ul>
            </div>

            <div>
                <div style="font-weight: 600; font-size: 15px; margin-bottom: 8px;">Pro Plan - ₹299/year</div>
                <ul style="list-style: none; padding: 0; margin: 0; font-size: 13px; color: #6c757d;">
                    <li style="padding: 4px 0;">• Everything in Basic</li>
                    <li style="padding: 4px 0;">• Previous Year Papers</li>
                    <li style="padding: 4px 0;">• Full Analytics & PDF Reports</li>
                    <li style="padding: 4px 0;">• Priority Support</li>
                </ul>
            </div>
        </div>

        <a href="subscription.php" class="btn btn-orange" style="width: 100%; display: block; text-align: center; padding: 14px;">View Detailed Plans →</a>
    </div>
</div>
<style>
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    animation: fadeIn 0.3s;
}
.modal-content {
    background: white;
    border-radius: 16px;
    padding: 40px;
    max-width: 500px;
    width: 90%;
    position: relative;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    animation: slideUp 0.3s;
}
.modal-close {
    position: absolute;
    top: 16px;
    right: 16px;
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #adb5bd;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    transition: all 0.2s;
}
.modal-close:hover {
    background: #f8f9fa;
    color: #1a1a2e;
}
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
@keyframes slideUp {
    from { transform: translateY(30px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
</style>
<script>
function closeSubscriptionModal() {
    document.getElementById('subscriptionModal').style.display = 'none';
    fetch('<?= BASE_PATH ?>api/dismiss_popup.php');
}
</script>
<?php endif; ?>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?= $daysLeft ?></div>
        <div class="stat-label">Days to DDCET</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $readiness ?>%</div>
        <div class="stat-label">Readiness Score</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $attemptCount ?></div>
        <div class="stat-label">Tests Completed</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= is_int($rank) ? '#' . number_format($rank) : $rank ?></div>
        <div class="stat-label">Global Rank</div>
    </div>
    <?php if ($collegeRk): ?>
    <div class="stat-card">
        <div class="stat-value">#<?= number_format($collegeRk['rank']) ?></div>
        <div class="stat-label">In Your College</div>
    </div>
    <?php endif; ?>
</div>

<?php if ($attemptCount === 0): ?>
<!-- First-time getting-started checklist (shown until the first test is taken) -->
<div class="card" style="margin-bottom:24px;">
    <div class="card-header"><h3><?= icon('flag', 18) ?> Get started — 3 quick steps</h3></div>
    <p style="font-size:13px; color:var(--text-secondary); margin:4px 0 16px;">Finish these to unlock your stats, rank and a personalised study plan.</p>
    <?php
    $steps = [
        ['done' => ($department && $department !== 'Other'), 'label' => 'Set your branch for a tailored study plan', 'cta' => 'Set branch', 'href' => 'profile.php'],
        ['done' => false, 'label' => 'Take your first practice test', 'cta' => 'Start a test', 'href' => 'tests.php'],
        ['done' => (bool)$dailyRk, 'label' => "Try today's Daily Challenge", 'cta' => 'Play', 'href' => 'exam.php?mode=daily_challenge'],
    ];
    foreach ($steps as $i => $s): ?>
        <div style="display:flex; align-items:center; gap:12px; padding:10px 0; <?= $i < count($steps) - 1 ? 'border-bottom:1px solid var(--border);' : '' ?>">
            <span style="width:22px; height:22px; border-radius:50%; flex-shrink:0; display:inline-flex; align-items:center; justify-content:center; font-size:13px; <?= $s['done'] ? 'background:var(--green); color:#fff;' : 'border:2px solid var(--border); color:var(--text-muted);' ?>"><?= $s['done'] ? '✓' : ($i + 1) ?></span>
            <span style="flex:1; font-size:14px; <?= $s['done'] ? 'color:var(--text-muted); text-decoration:line-through;' : 'color:var(--text-primary);' ?>"><?= $s['label'] ?></span>
            <?php if (!$s['done']): ?><a href="<?= $s['href'] ?>" class="btn btn-primary btn-sm"><?= $s['cta'] ?> →</a><?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php elseif ($nextMove): ?>
<!-- Your next move: one concrete, data-driven action -->
<div class="card" style="margin-bottom:24px; display:flex; align-items:center; gap:16px; flex-wrap:wrap; border-left:3px solid var(--accent);">
    <span style="color:var(--accent); flex-shrink:0;"><?= icon('target', 26) ?></span>
    <div style="flex:1; min-width:220px;">
        <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:var(--accent); font-weight:700; margin-bottom:4px;">Your next move</div>
        <div style="font-size:16px; font-weight:700; margin-bottom:4px;"><?= $nextMove['title'] ?></div>
        <div style="font-size:13px; color:var(--text-secondary);"><?= $nextMove['body'] ?></div>
    </div>
    <a href="<?= $nextMove['href'] ?>" class="btn btn-primary btn-sm" style="white-space:nowrap;"><?= $nextMove['cta'] ?></a>
</div>
<?php endif; ?>

<!-- Daily Challenge habit strip: streak + today's global rank -->
<div class="card" style="margin-bottom:24px; display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
    <span style="color:var(--accent);"><?= icon('flame', 26) ?></span>
    <div style="flex:1; min-width:200px;">
        <div style="font-size:15px; font-weight:700;">
            <?php if ($dailyStreak > 0): ?>
                Day <?= $dailyStreak ?> <span style="font-size:13px;">🔥</span>
                <?php if ($dailyRk): ?> · ranked <span style="color:var(--accent); font-family:var(--font-mono);">#<?= number_format($dailyRk['rank']) ?></span> today<?php endif; ?>
            <?php elseif ($dailyRk): ?>
                Ranked <span style="color:var(--accent); font-family:var(--font-mono);">#<?= number_format($dailyRk['rank']) ?></span> in today's challenge
            <?php else: ?>
                Daily Challenge
            <?php endif; ?>
        </div>
        <div style="font-size:12px; color:var(--text-muted);">
            <?= $dailyRk ? "You're on the board — come back tomorrow to keep the streak alive."
                         : 'One quick challenge a day builds your streak and your rank.' ?>
        </div>
    </div>
    <?php if ($dailyRk): ?>
        <a href="leaderboard.php?scope=daily" class="btn btn-secondary btn-sm">Today's board →</a>
    <?php else: ?>
        <a href="exam.php?mode=daily_challenge" class="btn btn-orange btn-sm">Take today's challenge →</a>
    <?php endif; ?>
</div>

<!-- Branch-Specific Study Plan -->
<?php
// Marks per subject (single source of truth) — used to render the focus split.
$subjMarks = ddcetSyllabusDistribution(100);
$subjMarks = array_map(fn($q) => $q * 2, $subjMarks); // 2 marks/question → marks
// Stacked-bar colours — every value is drawn from the theme palette (:root).
$splitColors = ['Physics' => '#4361ee', 'Maths' => '#8b5cf6', 'English' => '#ef4444', 'Chemistry' => '#10b981', 'Computers' => '#f59e0b', 'Environment' => '#adb5bd'];
?>
<div class="card" style="margin-bottom:24px;">
    <div class="card-header" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;">
        <h3><?= icon('target', 18) ?> Your Branch-Specific Plan</h3>
        <span class="tag"><?= htmlspecialchars($plan['label']) ?></span>
    </div>
    <p style="font-size:13px; color:var(--text-muted); margin:4px 0 16px;"><?= htmlspecialchars($plan['intro']) ?></p>

    <div class="grid-1-mobile" style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
        <div>
            <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-muted); font-weight:700; margin-bottom:8px;">Your head start</div>
            <div style="display:flex; flex-wrap:wrap; gap:6px;">
                <?php foreach ($plan['edge'] as $s): ?>
                    <span class="badge badge-green"><?= htmlspecialchars($s) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <div>
            <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-muted); font-weight:700; margin-bottom:8px;">Focus here (highest impact)</div>
            <div style="display:flex; flex-wrap:wrap; gap:6px;">
                <?php foreach ($plan['focus'] as $s): ?>
                    <span class="badge badge-accent"><?= htmlspecialchars($s) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-muted); font-weight:700; margin-bottom:8px;">Suggested study-time split</div>
    <div style="display:flex; height:10px; border-radius:6px; overflow:hidden; margin-bottom:10px;">
        <?php foreach ($plan['split'] as $subject => $pct): ?>
            <div title="<?= htmlspecialchars($subject) ?>: ~<?= (int)$pct ?>% of study time (<?= (int)($subjMarks[$subject] ?? 0) ?> marks)"
                 style="width:<?= (int)$pct ?>%; background:<?= $splitColors[$subject] ?? '#adb5bd' ?>;"></div>
        <?php endforeach; ?>
    </div>
    <div style="display:flex; flex-wrap:wrap; gap:10px 16px; margin-bottom:14px;">
        <?php foreach ($plan['split'] as $subject => $pct): ?>
            <span style="font-size:11px; color:var(--text-secondary); display:inline-flex; align-items:center; gap:5px;">
                <span style="width:9px; height:9px; border-radius:2px; background:<?= $splitColors[$subject] ?? '#adb5bd' ?>; display:inline-block;"></span>
                <?= htmlspecialchars($subject) ?> <span style="color:var(--text-muted);">(<?= (int)($subjMarks[$subject] ?? 0) ?> marks)</span>
            </span>
        <?php endforeach; ?>
    </div>

    <p class="mathy" style="font-size:13px; color:var(--text-secondary); background:var(--bg-tertiary,rgba(67,97,238,0.06)); border-radius:8px; padding:10px 12px; margin-bottom:14px;">
        <strong style="color:var(--accent);">Tip:</strong> <?= htmlspecialchars($plan['tip']) ?>
    </p>

    <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <a href="tests.php?mode=subject_wise" class="btn btn-primary btn-sm">Practice a focus subject →</a>
        <a href="mocks.php" class="btn btn-secondary btn-sm">Take a branch-ready mock</a>
        <?php if (!$department || $department === 'Other'): ?>
            <a href="profile.php" class="btn btn-secondary btn-sm">Set your branch for a tailored plan</a>
        <?php endif; ?>
    </div>
</div>

<!-- Two Column Layout -->
<div class="dash-2col wide">
    <!-- Heatmap -->
    <div class="card">
        <div class="card-header">
            <h3>Activity Heatmap</h3>
            <span class="tag"><?= $user['streak'] ?? 0 ?> day streak</span>
        </div>
        <div class="heatmap" id="heatmap">
            <?php
            for ($i = 89; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $count = $heatmapData[$date] ?? 0;
                $level = $count === 0 ? '' : ($count <= 1 ? 'l1' : ($count <= 3 ? 'l2' : ($count <= 5 ? 'l3' : 'l4')));
                echo "<div class=\"heatmap-cell $level\" title=\"$date: $count tests\"></div>";
            }
            ?>
        </div>
    </div>

    <!-- Readiness Ring -->
    <div class="card" style="text-align: center;">
        <div class="card-header"><h3>Readiness</h3></div>
        <div class="progress-ring">
            <svg width="120" height="120">
                <circle cx="60" cy="60" r="52" stroke="var(--bg-tertiary)" stroke-width="8" fill="none"/>
                <circle cx="60" cy="60" r="52" stroke="var(--green)" stroke-width="8" fill="none"
                    stroke-dasharray="<?= 2 * 3.14159 * 52 ?>"
                    stroke-dashoffset="<?= 2 * 3.14159 * 52 * (1 - $readiness / 100) ?>"
                    stroke-linecap="round"/>
            </svg>
            <span class="value"><?= $readiness ?>%</span>
        </div>
        <p style="color: var(--text-secondary); font-size: 12px; margin-top: 12px;">Based on last 5 tests</p>
    </div>
</div>

<!-- Radar Chart + Recent Tests -->
<div class="dash-2col even">
    <!-- Subject Performance Radar -->
    <div class="card">
        <div class="card-header"><h3>Subject Strength</h3></div>
        <?php if (empty($subjects)): ?>
            <p style="color: var(--text-muted); font-size: 13px;">Complete a test to see your subject-wise strength here. <a href="tests.php">Take a test →</a></p>
        <?php else: ?>
            <canvas id="radarChart" height="200"></canvas>
        <?php endif; ?>
    </div>

    <!-- Recent Tests -->
    <div class="card">
        <div class="card-header"><h3>Recent Tests</h3></div>
        <?php if (empty($recentTests)): ?>
            <p style="color: var(--text-muted); font-size: 13px;">No tests attempted yet. <a href="tests.php">Start now →</a></p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Test</th><th>Score</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentTests as $t): ?>
                        <tr>
                            <td><?= htmlspecialchars($t['title']) ?></td>
                            <td>
                                <span class="badge <?= ($t['score'] / max(1,$t['total_marks']) * 100) >= 60 ? 'badge-green' : 'badge-red' ?>">
                                    <?= $t['score'] ?>/<?= $t['total_marks'] ?>
                                </span>
                            </td>
                            <td style="color: var(--text-muted);"><?= date('d M', strtotime($t['completed_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Subscription Status -->
<div class="card" style="margin-top: 20px; display: flex; align-items: center; justify-content: space-between;">
    <div>
        <h3 style="font-size: 14px; margin-bottom: 4px;">Subscription</h3>
        <?php if ($subscription): ?>
            <span class="badge badge-green"><?= ucfirst($subscription['plan']) ?> Plan</span>
            <span style="color: var(--text-muted); font-size: 12px; margin-left: 8px;">Expires <?= date('d M Y', strtotime($subscription['expires_at'])) ?></span>
        <?php else: ?>
            <span class="badge badge-red">Free Tier</span>
            <span style="color: var(--text-muted); font-size: 12px; margin-left: 8px;">Only daily challenge available</span>
        <?php endif; ?>
    </div>
    <?php if (!$subscription): ?>
        <a href="subscription.php" class="btn btn-orange btn-sm">Upgrade →</a>
    <?php endif; ?>
</div>

<?php
$subjectsJson = json_encode(array_column($subjects, 'subject'));
$scoresJson = json_encode(array_map(fn($s) => round((float)$s['avg_pct']), $subjects));
$csrf = htmlspecialchars(csrfToken());
$base = BASE_PATH;
$iconRefresh = icon('refresh-cw', 14);

$extraScripts = <<<HTML
<script>
const subjects = {$subjectsJson};
const scores = {$scoresJson};
if (subjects.length > 0) {
    new Chart(document.getElementById("radarChart"), {
        type: "radar",
        data: {
            labels: subjects,
            datasets: [{
                label: "Accuracy %",
                data: scores,
                borderColor: "#4361ee",
                backgroundColor: "rgba(67,97,238,0.1)",
                pointBackgroundColor: "#4361ee",
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            scales: {
                r: {
                    beginAtZero: true,
                    max: 100,
                    grid: { color: "#e9ecef" },
                    angleLines: { color: "#e9ecef" },
                    ticks: { display: false }
                }
            },
            plugins: { legend: { display: false } }
        }
    });
}

function analyzeOverallReport() {
    const btn = document.getElementById("analyzeOverallBtn");
    const resultBox = document.getElementById("overallAiResult");
    
    btn.innerHTML = `<span class="spinner" style="width:14px;height:14px;border:2px solid #fff;border-top-color:transparent;border-radius:50%;animation:spin 1s linear infinite;display:inline-block;vertical-align:middle;margin-right:6px;"></span> Analyzing...`;
    btn.disabled = true;
    resultBox.style.display = "block";
    resultBox.innerHTML = `
        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 20px 0; color: var(--text-muted);">
            <div class="spinner" style="width:30px;height:30px;border:3px solid #8b5cf6;border-top-color:transparent;border-radius:50%;animation:spin 1s linear infinite;margin-bottom:12px;"></div>
            <p style="margin:0;font-size:14px;">The AI is reviewing your entire prep history...</p>
            <p style="margin:5px 0 0;font-size:12px;opacity:0.7;">This usually takes about 5 seconds</p>
        </div>
    `;
    
    fetch("{$base}api/analyze_overall.php", {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-CSRF-Token": "{$csrf}" }
    })
    .then(r => r.json())
    .then(data => {
        btn.innerHTML = `{$iconRefresh} Analyze Again`;
        btn.disabled = false;
        
        if (data.error) {
            resultBox.innerHTML = `
                <div style="background: rgba(231,76,60,0.1); border: 1px solid var(--red); border-radius: 8px; padding: 16px; color: var(--red);">
                    <div style="font-weight: 700; margin-bottom: 6px;">Failed to generate analysis</div>
                    <div style="font-size: 13px;">\${data.error}</div>
                </div>
            `;
        } else {
            let html = data.analysis;
            html = html.replace(/^##\s+(.*)$/gm, '<h4 style="margin: 20px 0 10px; color: var(--text-primary); font-size: 16px; border-bottom: 1px solid var(--border); padding-bottom: 6px;">$1</h4>');
            html = html.replace(/(?:^|\s)(?:-|\*)\s(.*?)(?=(?:\s(?:-|\*)\s|$))/gm, '<li style="margin-bottom: 6px;">$1</li>');
            html = html.replace(/(<li.*?>.*?<\/li>)+/g, match => `<ul style="margin: 10px 0 16px; padding-left: 20px;">\${match}</ul>`);
            html = html.replace(/\*\*(.*?)\*\*/g, '<strong style="color: var(--text-primary);">$1</strong>');
            html = html.split('\\n\\n').map(p => {
                if (p.trim().startsWith('<h') || p.trim().startsWith('<ul')) return p;
                return `<p style="margin: 0 0 16px; line-height: 1.6;">\${p.replace(/\\n/g, '<br>')}</p>`;
            }).join('');
            
            resultBox.innerHTML = `
                <div class="ai-analysis-content" style="font-size: 14px; color: var(--text-secondary);">
                    \${html}
                </div>
            `;
        }
    })
    .catch(err => {
        btn.innerHTML = `{$iconRefresh} Try Again`;
        btn.disabled = false;
        resultBox.innerHTML = `
            <div style="background: rgba(231,76,60,0.1); border: 1px solid var(--red); border-radius: 8px; padding: 16px; color: var(--red);">
                <div style="font-weight: 700; margin-bottom: 6px;">Network Error</div>
                <div style="font-size: 13px;">Could not connect to the analysis server.</div>
            </div>
        `;
    });
}
</script>
HTML;
include __DIR__ . '/includes/footer.php';
?>
