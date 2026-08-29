<?php
require_once __DIR__ . '/config.php';
$user = requireAuth();
// BUG-040 fix: removed unused $db = getDB() call
$pageTitle = 'Test Result';
// BUG-016: Chart.js is now loaded only on pages that need it (removed from global header)
$extraHead = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
$attemptId = (int) ($_GET['attempt_id'] ?? 0);
if (!$attemptId) { header('Location: ' . BASE_PATH . 'dashboard.php'); exit; }

// Load attempt via REST
$attemptData = supabaseRest('attempts?id=eq.' . $attemptId . '&student_id=eq.' . $user['id'] . '&status=eq.completed&limit=1');
$attempt = $attemptData[0] ?? null;
if (!$attempt) { header('Location: ' . BASE_PATH . 'dashboard.php'); exit; }

// Load answers (needed by the total_questions fallback below)
$answers = supabaseRest('attempt_answers?attempt_id=eq.' . $attemptId . '&select=*&order=question_id') ?? [];

// Friend duel: pull the challenge so we can show a head-to-head comparison.
$duel = null; $duelOpp = null; $duelOutcome = null;
if (!empty($attempt['challenge_id'])) {
    $duel = challengeLoad((int) $attempt['challenge_id']);
    if ($duel) {
        // Resolve an abandoned/expired live duel on load (don't wait for the poll).
        $duel['status'] = challengeResolveTimeouts($duel);
        $iAmChallenger = (int) $duel['challenger_id'] === (int) $user['id'];
        $oppId = $iAmChallenger ? (int) $duel['opponent_id'] : (int) $duel['challenger_id'];
        $oppRow = supabaseRest('students?id=eq.' . $oppId . '&select=name,avatar_url&limit=1');
        $duelOpp = $oppRow[0] ?? ['name' => 'Opponent'];
        $duel['my_score']  = $iAmChallenger ? $duel['challenger_score'] : $duel['opponent_score'];
        $duel['opp_score'] = $iAmChallenger ? $duel['opponent_score'] : $duel['challenger_score'];
        if ($duel['status'] === 'completed') {
            $duelOutcome = $duel['winner_id'] === null ? 'tie' : ((int) $duel['winner_id'] === (int) $user['id'] ? 'win' : 'loss');
        }
    }
}

// Load test info
if (!empty($attempt['test_id'])) {
    $testData = supabaseRest('tests?id=eq.' . $attempt['test_id'] . '&select=title,mode,total_questions,duration_minutes,'
        . 'series_label,is_scheduled,is_free,opens_at,closes_at,results_at,results_published&limit=1');
    $testInfo = $testData[0] ?? [];
} else {
    $testInfo = [];
}
// Preserve the attempt's own mode (pool attempts store it on the row); fixed
// tests override from the tests table. Title falls back to the real mode name.
$poolMode = $attempt['mode'] ?? null;
$attempt['title'] = $testInfo['title'] ?? modeLabel($poolMode);
$attempt['mode'] = $testInfo['mode'] ?? ($poolMode ?: 'practice');
$attempt['total_questions'] = $testInfo['total_questions'] ?? count($answers ?? []);
$attempt['duration_minutes'] = $testInfo['duration_minutes'] ?? 0;

// Scheduled-mock gating: All-India Rank + full solutions only reveal once the
// window has closed and results are out. While the mock is still live we hide
// the answer key so early submitters can't leak it.
$isScheduledMock = !empty($testInfo['is_scheduled']);
$mockResultsOut  = $isScheduledMock ? mockResultsOut($testInfo) : true;
$air = null;
if ($isScheduledMock && $mockResultsOut && !empty($attempt['test_id'])) {
    $air = mockAIR((int)$attempt['test_id'], (float)$attempt['score']);
}

// Enrich with question data
$qIds = array_column($answers, 'question_id');
$questionsData = [];
if ($qIds) {
    $allQ = supabaseRest('questions?id=in.(' . implode(',', $qIds) . ')&select=*') ?? [];
    foreach ($allQ as $q) $questionsData[$q['id']] = $q;
}
foreach ($answers as &$a) {
    $q = $questionsData[$a['question_id']] ?? [];
    $a['question_text'] = $q['question_text'] ?? '';
    $a['subject'] = $q['subject'] ?? '';
    $a['explanation'] = $q['explanation'] ?? '';
    $a['difficulty'] = $q['difficulty'] ?? '';
    $a['marks'] = $q['marks'] ?? 1;
    $a['negative_marks'] = $q['negative_marks'] ?? 0;
}
unset($a);

// Load options
$optionsMap = [];
if ($qIds) {
    $allOpts = supabaseRest('options?question_id=in.(' . implode(',', $qIds) . ')&select=*&order=position') ?? [];
    foreach ($allOpts as $o) $optionsMap[$o['question_id']][] = $o;
}

// Topper
$topper = null;
if (!empty($attempt['test_id'])) {
    $topperData = supabaseRest('attempts?test_id=eq.' . $attempt['test_id'] . '&status=eq.completed&order=score.desc&limit=1');
    if (!empty($topperData[0])) {
        $topperStudent = supabaseRest('students?id=eq.' . $topperData[0]['student_id'] . '&select=name,avatar_url&limit=1');
        $topper = array_merge($topperData[0], $topperStudent[0] ?? []);
    }
}

// College average (simplified)
$collegeAvgScore = 0;

// Subject-wise breakdown
$subjectBreakdown = [];
foreach ($answers as $a) {
    $sub = $a['subject'] ?: 'General';
    if (!isset($subjectBreakdown[$sub])) $subjectBreakdown[$sub] = ['correct' => 0, 'total' => 0, 'time' => 0];
    $subjectBreakdown[$sub]['total']++;
    $subjectBreakdown[$sub]['time'] += (int) ($a['time_spent_seconds'] ?? 0);
    if ($a['is_correct']) $subjectBreakdown[$sub]['correct']++;
}

$scorePercent = $attempt['total_marks'] > 0 ? round(($attempt['score'] / $attempt['total_marks']) * 100) : 0;
$avgTimePerQ = count($answers) > 0 ? round($attempt['time_spent_seconds'] / count($answers)) : 0;

// ---------------------------------------------------------------------------
// Engagement / conversion add-ons
// ---------------------------------------------------------------------------
$isPro = isSubscribed('pro');

// Solutions gate: on a scheduled mock, the All-India Rank + analysis are free,
// but the per-question answer key is a Pro perk. Practice tests stay open (you
// already need Basic+ to attempt them).
$solutionsLocked = $isScheduledMock && !$isPro;

// College projection ("close the loop"). Only meaningful for full-length papers
// scored on (or near) the DDCET 200 scale; we normalise marks to /200 so BE-01 /
// BE-02 half-papers project too. Category defaults to Open with an inline switch.
require_once __DIR__ . '/includes/predictor_lib.php';
$projectionModes = ['full_mock', 'be01_paper', 'be02_paper', 'previous_year'];
$showProjection  = $mockResultsOut
    && ($isScheduledMock || in_array($attempt['mode'], $projectionModes, true))
    && $attempt['total_marks'] > 0;
$projection = null; $projCat = 'OP'; $projMarks = 0;
if ($showProjection) {
    $cats    = predictorCategories();
    $projCat = in_array($_GET['cat'] ?? '', $cats, true) ? $_GET['cat'] : 'OP';
    $projMarks = round($attempt['score'] / $attempt['total_marks'] * 200, 1);
    $projection = projectFromMarks((float)$projMarks, $projCat);
}

// "You're #N in your college — go Pro to defend your rank." Shown to a strong,
// non-Pro student so the leaderboard position becomes an upgrade reason.
$myCollegeRank = (!$isPro && !empty($user['college_id']))
    ? collegeRank((int)$user['id'], (int)$user['college_id']) : null;
$defendRank = ($myCollegeRank && $myCollegeRank['rank'] <= 5) ? $myCollegeRank['rank'] : null;

// Top-10 of a FREE mock → 1 month Pro free. Eligible while results are out; the
// claim button (and grant) are re-validated server-side in api/claim_reward.php.
$rewardEligible = false; $rewardClaimed = false;
if ($isScheduledMock && $mockResultsOut && !empty($testInfo['is_free'])
    && $air && $air['rank'] <= 10 && !empty($attempt['test_id'])) {
    $rewardEligible = true;
    $claimRow = supabaseRest('mock_rewards?test_id=eq.' . (int)$attempt['test_id']
        . '&student_id=eq.' . (int)$user['id'] . '&select=id&limit=1');
    $rewardClaimed = !empty($claimRow);
}

// Daily-Challenge habit: surface today's rank + the streak on a daily attempt.
$dailyRank = null; $dailyStreak = 0;
if (($attempt['mode'] ?? '') === 'daily_challenge') {
    $dailyRank = dailyChallengeRank((int)$user['id']);
    $dsRow = supabaseRest('students?id=eq.' . (int)$user['id'] . '&select=daily_streak&limit=1');
    if (!empty($dsRow)) $dailyStreak = (int)($dsRow[0]['daily_streak'] ?? 0);
}

include __DIR__ . '/includes/header.php';
?>

<?php if ($isScheduledMock && $air): ?>
<!-- All-India Rank (scheduled mock, results out) -->
<div class="card" style="margin-bottom:20px; background:linear-gradient(135deg,#1a1a2e,#2d1b69); color:#fff; border:none; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px;">
    <div>
        <div style="font-size:12px; opacity:0.7; text-transform:uppercase; letter-spacing:1px;"><?= htmlspecialchars($testInfo['series_label'] ?: $attempt['title']) ?> · All-India Rank</div>
        <div style="font-size:44px; font-weight:900; font-family:var(--font-mono); line-height:1.1;">#<?= number_format($air['rank']) ?></div>
        <div style="font-size:13px; opacity:0.8;">out of <?= number_format($air['total']) ?> aspirants · <?= $air['percentile'] ?> percentile</div>
    </div>
    <a href="#projection" class="btn btn-orange btn-sm">Where does this rank get me? →</a>
</div>
<?php elseif ($isScheduledMock && !$mockResultsOut): ?>
<!-- Scheduled mock still live / grading: rank + solutions pending -->
<div class="card" style="margin-bottom:20px; border-color:var(--purple); padding:14px 18px;">
    <div style="display:flex; align-items:center; gap:10px; color:var(--purple); font-weight:600; font-size:14px;">
        <?= icon('calendar', 16) ?> Submitted! Your All-India Rank &amp; full solutions unlock
        <?php if (!empty($testInfo['results_at'])): ?>
            on <?= date('D, d M · g:i A', strtotime($testInfo['results_at'])) ?>.
        <?php elseif (!empty($testInfo['closes_at'])): ?>
            when the window closes (<?= date('D, d M · g:i A', strtotime($testInfo['closes_at'])) ?>).
        <?php else: ?>
            when the window closes.
        <?php endif; ?>
    </div>
    <p style="font-size:12px; color:var(--text-muted); margin-top:6px;">Your score is locked in below. Come back after the window to see where you stand.</p>
</div>
<?php endif; ?>

<?php if ($dailyRank): ?>
<!-- Daily Challenge: today's rank + streak (the habit loop) -->
<div class="card" style="margin-bottom:16px; display:flex; align-items:center; gap:14px; flex-wrap:wrap; border-color:var(--accent);">
    <span style="color:var(--accent);"><?= icon('flame', 28) ?></span>
    <div style="flex:1; min-width:180px;">
        <div style="font-size:15px; font-weight:700;">
            <?php if ($dailyStreak > 0): ?>
                Day <?= $dailyStreak ?> streak <span style="font-size:13px;">🔥</span> ·
            <?php endif; ?>
            Ranked <span style="color:var(--accent); font-family:var(--font-mono);">#<?= number_format($dailyRank['rank']) ?></span> today
        </div>
        <div style="font-size:12px; color:var(--text-muted);">out of <?= number_format($dailyRank['total']) ?> who took today's challenge. Come back tomorrow to keep the streak alive.</div>
    </div>
    <a href="leaderboard.php?scope=daily" class="btn btn-secondary btn-sm">Today's board →</a>
</div>
<?php endif; ?>

<?php if ($rewardEligible): ?>
<!-- Top 10 of a FREE mock → 1 month Pro free -->
<div class="card" id="rewardCard" style="margin-bottom:16px; background:linear-gradient(135deg,#0f2027,#203a43,#2c5364); color:#fff; border:none;">
    <div style="display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
        <span style="font-size:30px;"><?= $air['rank'] === 1 ? '🥇' : ($air['rank'] === 2 ? '🥈' : ($air['rank'] === 3 ? '🥉' : '🏅')) ?></span>
        <div style="flex:1; min-width:200px;">
            <div style="font-size:17px; font-weight:800;">Top 10 finish — #<?= $air['rank'] ?> All-India!</div>
            <div style="font-size:13px; opacity:0.85;">You cracked the Top 10 of a free mock. That earns you <strong>1 month of Pro, free</strong> — full solutions, every series, analytics.</div>
        </div>
        <div id="rewardAction">
            <?php if ($rewardClaimed): ?>
                <span class="badge badge-green"><?= icon('check', 12) ?> Pro unlocked</span>
            <?php else: ?>
                <button id="claimRewardBtn" class="btn btn-orange btn-sm" data-test-id="<?= (int)$attempt['test_id'] ?>">Claim 1 month Pro →</button>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($duel): ?>
<!-- Friend duel: head-to-head comparison -->
<div class="card" id="duelCard" style="margin-bottom:20px; border:none; color:#fff; background:linear-gradient(135deg,#1a1a2e,#3a1c5e);">
    <?php if ($duelOutcome): ?>
        <div style="text-align:center; font-size:22px; font-weight:900; margin-bottom:14px;">
            <?php if ($duelOutcome==='win'): ?>🏆 You Won!<?php elseif ($duelOutcome==='loss'): ?>You Lost<?php else: ?>🤝 It's a Tie<?php endif; ?>
        </div>
        <div style="display:flex; align-items:center; justify-content:center; gap:20px; flex-wrap:wrap;">
            <div style="text-align:center; min-width:120px;">
                <div style="font-size:12px; opacity:0.7;">You</div>
                <div style="font-size:34px; font-weight:900; font-family:var(--font-mono);"><?= (float) ($duel['my_score'] ?? 0) ?></div>
            </div>
            <div style="font-size:18px; opacity:0.6;">vs</div>
            <div style="text-align:center; min-width:120px;">
                <div style="font-size:12px; opacity:0.7;"><?= htmlspecialchars($duelOpp['name'] ?? 'Opponent') ?></div>
                <div style="font-size:34px; font-weight:900; font-family:var(--font-mono);"><?= (float) ($duel['opp_score'] ?? 0) ?></div>
            </div>
        </div>
        <div style="text-align:center; margin-top:14px;"><a href="challenges.php" class="btn btn-orange btn-sm">Rematch / New Challenge →</a></div>
    <?php else: ?>
        <!-- Opponent hasn't finished yet — poll until the duel completes. -->
        <div style="text-align:center;">
            <div style="font-size:16px; font-weight:700; margin-bottom:6px;">Duel vs <?= htmlspecialchars($duelOpp['name'] ?? 'Opponent') ?></div>
            <div id="duelWaiting" style="font-size:13px; opacity:0.8;">Your score is locked in. Waiting for your opponent to finish…</div>
        </div>
        <script>
        (function(){
            const CID = <?= (int) $attempt['challenge_id'] ?>;
            const tick = () => fetch('<?= BASE_PATH ?>api/challenge_state.php?challenge_id='+CID)
                .then(r=>r.json()).then(d=>{ if(d.ok && d.status==='completed'){ location.reload(); }});
            setInterval(tick, 4000);
        })();
        </script>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php /* After the free full mock, nudge non-subscribed users to unlock the rest. */ ?>
<?php if (($attempt['mode'] ?? '') === 'full_mock' && !$duel && !isSubscribed()): ?>
<div class="card" style="margin-bottom:20px; background:linear-gradient(135deg,#1a1a2e,#2d1b69); color:#fff; border:none; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:14px;">
    <div>
        <div style="font-size:18px; font-weight:800;">That was your free mock 🎯</div>
        <div style="font-size:13px; opacity:0.9;">Unlock unlimited full mocks, previous-year papers and detailed analytics — from just ₹149/year.</div>
    </div>
    <a href="subscription.php" class="btn btn-orange btn-sm" style="white-space:nowrap;">Unlock everything →</a>
</div>
<?php endif; ?>

<!-- Score Overview -->
<div class="stats-grid">
    <div class="stat-card <?= $scorePercent >= 60 ? 'green' : 'red' ?>">
        <div class="stat-value"><?= $attempt['score'] ?>/<?= $attempt['total_marks'] ?></div>
        <div class="stat-label">Score (<?= $scorePercent ?>%)</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-value"><?= $air ? $air['percentile'] : ($attempt['percentile'] ?? 0) ?>%</div>
        <div class="stat-label"><?= $air ? 'AIR Percentile' : 'Percentile' ?></div>
    </div>
    <div class="stat-card green">
        <div class="stat-value"><?= $attempt['correct_count'] ?? 0 ?></div>
        <div class="stat-label">Correct</div>
    </div>
    <div class="stat-card red">
        <div class="stat-value"><?= $attempt['incorrect_count'] ?? 0 ?></div>
        <div class="stat-label">Incorrect</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $attempt['skipped_count'] ?? 0 ?></div>
        <div class="stat-label">Skipped</div>
    </div>
    <div class="stat-card accent">
        <div class="stat-value">+<?= $attempt['xp_earned'] ?? 0 ?></div>
        <div class="stat-label">XP Earned</div>
    </div>
</div>

<!-- Ask AI About Your Report -->
<div class="card" id="aiReportCard" style="margin-bottom: 20px; border: 1px solid rgba(99, 102, 241, 0.3); overflow: hidden;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div style="width: 40px; height: 40px; border-radius: 12px; background: linear-gradient(135deg, #10b981, #3b82f6); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 20px; flex-shrink: 0;">
                <?= icon('cpu', 22) ?>
            </div>
            <div>
                <h3 style="margin: 0; font-size: 15px;">AI Performance Analysis</h3>
                <p style="margin: 0; font-size: 12px; color: var(--text-muted);">Get a detailed, personalized assessment of your performance powered by AI</p>
            </div>
        </div>
        <button id="analyzeReportBtn" class="btn btn-sm" onclick="analyzeReport()" style="background: linear-gradient(135deg, #10b981, #3b82f6); color: #fff; border: none; padding: 8px 20px; font-weight: 700; font-size: 13px; border-radius: 8px; white-space: nowrap;">
            <?= icon('zap', 14) ?> Analyze My Report ✨
        </button>
    </div>
    <div id="aiReportResult" style="display: none; margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border);"></div>
</div>

<?php if ($projection): $best = $projection['best']; $next = $projection['next']; ?>
<!-- College projection — turns the score into the seat they actually want -->
<div class="card" id="projection" style="margin-bottom:20px; border-color:var(--accent);">
    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:12px;">
        <h3 style="font-size:16px; margin:0;"><?= icon('school', 16) ?> Your college projection</h3>
        <form method="GET" style="display:flex; align-items:center; gap:6px; font-size:12px;">
            <input type="hidden" name="attempt_id" value="<?= $attemptId ?>">
            <label style="color:var(--text-muted);">Category</label>
            <select name="cat" onchange="this.form.submit()" class="btn btn-secondary btn-sm" style="padding:4px 8px;">
                <?php foreach (predictorCategories() as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>" <?= $c === $projCat ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <p style="font-size:12px; color:var(--text-muted); margin-bottom:14px;">
        Projecting <strong style="color:var(--text-primary);"><?= rtrim(rtrim(number_format($projMarks, 1), '0'), '.') ?>/200 marks</strong>
        against <?= htmlspecialchars($projCat) ?>-category closing cutoffs (DDCET 2024–25).
    </p>

    <?php if ($best): ?>
    <div style="background:rgba(0,184,148,0.08); border:1px solid var(--green); border-radius:8px; padding:14px; margin-bottom:<?= $next ? '12px' : '0' ?>;">
        <div style="font-size:11px; color:var(--green); text-transform:uppercase; letter-spacing:1px; font-weight:700;"><?= icon('check', 12) ?> Projects to</div>
        <div style="font-size:17px; font-weight:800; margin-top:2px;"><?= htmlspecialchars($best['college']) ?></div>
        <div style="font-size:13px; color:var(--text-secondary);"><?= htmlspecialchars($best['branch']) ?><?= $best['city'] ? ' · ' . htmlspecialchars($best['city']) : '' ?> · cutoff <?= rtrim(rtrim(number_format($best['last_marks'], 1), '0'), '.') ?> marks</div>
        <?php if ($projection['safe_count'] > 1): ?>
            <div style="font-size:12px; color:var(--text-muted); margin-top:4px;"><?= number_format($projection['safe_count']) ?> colleges within reach at this score.</div>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div style="background:rgba(231,76,60,0.08); border:1px solid var(--red); border-radius:8px; padding:14px; margin-bottom:<?= $next ? '12px' : '0' ?>;">
        <div style="font-size:14px; font-weight:700;">No seat clears at this score yet in <?= htmlspecialchars($projCat) ?>.</div>
        <div style="font-size:12px; color:var(--text-muted); margin-top:2px;">Keep practising — the gap below is your target.</div>
    </div>
    <?php endif; ?>

    <?php if ($next): ?>
    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; background:var(--bg-primary); border-radius:8px; padding:12px 14px;">
        <div>
            <div style="font-size:13px;">Improve by <strong style="color:var(--accent); font-family:var(--font-mono);">+<?= $projection['marks_needed'] ?> marks</strong> to unlock</div>
            <div style="font-size:14px; font-weight:700;"><?= htmlspecialchars($next['college']) ?> <span style="font-weight:400; color:var(--text-muted); font-size:12px;">· <?= htmlspecialchars($next['branch']) ?></span></div>
        </div>
        <a href="predictor.php" class="btn btn-secondary btn-sm">Full predictor →</a>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($defendRank): ?>
<!-- "Defend your college rank" — converts standing into an upgrade reason -->
<div class="card" style="margin-bottom:20px; display:flex; align-items:center; gap:14px; flex-wrap:wrap; background:linear-gradient(135deg,#2d1b69,#1a1a2e); color:#fff; border:none;">
    <span style="font-size:26px;"><?= $defendRank === 1 ? '👑' : '🛡️' ?></span>
    <div style="flex:1; min-width:200px;">
        <div style="font-size:16px; font-weight:800;">You're #<?= $defendRank ?> in your college</div>
        <div style="font-size:13px; opacity:0.85;">Rivals are practising with the full Pro series. Go Pro to defend your rank with unlimited mocks, solutions and analytics.</div>
    </div>
    <a href="subscription.php?need=pro" class="btn btn-orange btn-sm">Defend with Pro →</a>
</div>
<?php endif; ?>

<?php if (!$mockResultsOut): ?>
<div class="card" style="text-align:center; padding:40px;">
    <div style="font-size:32px; margin-bottom:12px;"><?= icon('lock', 32) ?></div>
    <h3 style="margin-bottom:6px;">Solutions locked until results are out</h3>
    <p style="color:var(--text-muted); font-size:13px; max-width:480px; margin:0 auto;">To keep this scheduled mock fair, the answer key, analysis and All-India Rank stay hidden until the window closes. Your score above is final.</p>
</div>
<?php else: ?>

<!-- Comparison & Time Analysis -->
<div class="grid-1-mobile" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
    <!-- Comparison -->
    <div class="card">
        <div class="card-header"><h3>Compare</h3></div>
        <div class="table-wrap"><table>
            <tr><td style="color: var(--text-secondary);">Your Score</td><td style="font-family: var(--font-mono); font-weight: 700;"><?= $attempt['score'] ?></td></tr>
            <tr><td style="color: var(--text-secondary);">Topper Score</td><td style="font-family: var(--font-mono); font-weight: 700; color: var(--green);"><?= $topper ? $topper['score'] : '-' ?> <?= $topper ? '(' . htmlspecialchars($topper['name']) . ')' : '' ?></td></tr>
            <tr><td style="color: var(--text-secondary);">College Average</td><td style="font-family: var(--font-mono);"><?= $collegeAvgScore ?></td></tr>
            <tr><td style="color: var(--text-secondary);">Avg Time/Question</td><td style="font-family: var(--font-mono);"><?= $avgTimePerQ ?>s</td></tr>
            <tr><td style="color: var(--text-secondary);">Tab Switches</td><td style="font-family: var(--font-mono); <?= $attempt['tab_switches'] > 0 ? 'color: var(--red)' : '' ?>"><?= $attempt['tab_switches'] ?></td></tr>
        </table></div>
    </div>

    <!-- Subject Breakdown Chart -->
    <div class="card">
        <div class="card-header"><h3>Subject Performance</h3></div>
        <canvas id="subjectChart" height="180"></canvas>
    </div>
</div>

<!-- Time per Question Chart -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header"><h3>Time Spent per Question</h3></div>
    <canvas id="timeChart" height="80"></canvas>
</div>

<?php if ($solutionsLocked): ?>
<!-- Scheduled-mock solutions are a Pro perk: AIR + analysis above stay free -->
<div class="card" style="text-align:center; padding:40px;">
    <div style="font-size:32px; margin-bottom:12px; color:var(--accent);"><?= icon('lock', 32) ?></div>
    <h3 style="margin-bottom:6px;">Full solutions are a Pro perk</h3>
    <p style="color:var(--text-muted); font-size:13px; max-width:480px; margin:0 auto 16px;">Your All-India Rank, score and analysis above are free. Unlock the answer key with worked explanations for every question — plus the full mock series and analytics — with Pro.</p>
    <a href="subscription.php?need=pro" class="btn btn-orange">Unlock solutions with Pro →</a>
</div>
<?php else: ?>
<!-- Detailed Solutions -->
<div class="card">
    <div class="card-header">
        <h3>Solutions & Explanations</h3>
        <a href="api/result_pdf.php?attempt_id=<?= $attemptId ?>" class="btn btn-secondary btn-sm" target="_blank"><?= icon('download') ?> Download PDF</a>
    </div>

    <?php foreach ($answers as $idx => $ans):
        $options = $optionsMap[$ans['question_id']] ?? [];
        $correctOpt = null;
        foreach ($options as $o) { if ($o['is_correct']) { $correctOpt = $o; break; } }
    ?>
    <div style="border-bottom: 1px solid var(--border); padding: 20px 0; <?= $idx === 0 ? 'padding-top: 0;' : '' ?>">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px; flex-wrap: wrap;">
            <span style="font-family: var(--font-mono); color: var(--text-muted); font-size: 12px;">Q<?= $idx + 1 ?></span>
            <?php if ($ans['is_correct'] === true): ?>
                <span class="badge badge-green"><?= icon('check', 12) ?> Correct</span>
            <?php elseif ($ans['is_correct'] === false): ?>
                <span class="badge badge-red"><?= icon('x', 12) ?> Incorrect</span>
            <?php else: ?>
                <span class="badge" style="background: #333; color: #888;">— Skipped</span>
            <?php endif; ?>
            <span class="tag"><?= htmlspecialchars($ans['subject']) ?></span>
            <span class="tag"><?= htmlspecialchars($ans['difficulty'] ?? '') ?></span>
            <span style="margin-left: auto; font-size: 11px; color: var(--text-muted);"><?= $ans['time_spent_seconds'] ?>s</span>
        </div>
        <p class="mathy" style="margin-bottom: 12px; line-height: 1.6;"><?= htmlspecialchars($ans['question_text']) ?></p>

        <div style="display: flex; flex-direction: column; gap: 6px; margin-bottom: 12px;">
        <?php $keys = ['A','B','C','D','E','F']; foreach ($options as $oi => $opt):
            $isSelected = $ans['selected_option_id'] == $opt['id'];
            $isCorrectOpt = $opt['is_correct'];
            $style = '';
            if ($isCorrectOpt) $style = 'border-color: var(--green); background: rgba(0,184,148,0.08);';
            elseif ($isSelected && !$isCorrectOpt) $style = 'border-color: var(--red); background: rgba(231,76,60,0.08);';
        ?>
            <div style="padding: 8px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px; display: flex; align-items: center; gap: 10px; <?= $style ?>">
                <span style="font-weight: 600; color: var(--text-muted);"><?= $keys[$oi] ?></span>
                <span class="mathy"><?= htmlspecialchars($opt['option_text']) ?></span>
                <?php if ($isCorrectOpt): ?><span style="margin-left: auto; color: var(--green);"><?= icon('check', 14) ?></span><?php endif; ?>
                <?php if ($isSelected && !$isCorrectOpt): ?><span style="margin-left: auto; color: var(--red);"><?= icon('x', 14) ?></span><?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>

        <?php 
            $hasExp = !empty($ans['explanation']);
            $isAi = $hasExp && (str_starts_with($ans['explanation'], '✨') || str_starts_with($ans['explanation'], '&#10024;'));
            $expHtml = '';
            if ($hasExp) {
                $expHtml = htmlspecialchars($ans['explanation']);
                if ($isAi) {
                    $expHtml = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $expHtml);
                    $expHtml = nl2br($expHtml);
                }
            }
        ?>
        <div id="ai-exp-<?= $ans['question_id'] ?>" style="background: var(--bg-primary); border-radius: 6px; padding: 12px; font-size: 13px; color: var(--text-secondary); line-height: 1.6;">
            <?php if ($hasExp): ?>
                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px;">
                    <div class="mathy" style="flex: 1;">
                        <?php if (!$isAi): ?><strong style="color: var(--accent);">Explanation:</strong><?php endif; ?>
                        <?= $expHtml ?>
                    </div>
                    <?php if (!$isAi && $isPro): ?>
                        <button class="btn btn-sm" style="background: linear-gradient(135deg, #10b981, #3b82f6); color: #fff; border: none; padding: 4px 12px; font-weight: 600; white-space: nowrap; flex-shrink: 0;" onclick="askAI(<?= $ans['question_id'] ?>)">Ask AI ✨</button>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <span style="color: var(--text-muted);">No explanation available.</span>
                    <?php if ($isPro): ?>
                    <button class="btn btn-sm" style="background: linear-gradient(135deg, #10b981, #3b82f6); color: #fff; border: none; padding: 4px 12px; font-weight: 600;" onclick="askAI(<?= $ans['question_id'] ?>)">Ask AI ✨</button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Difficulty Rating -->
        <div style="margin-top: 10px; display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <span style="font-size: 11px; color: var(--text-muted);">Rate difficulty:</span>
            <button class="btn btn-sm btn-secondary rate-btn" data-qid="<?= $ans['question_id'] ?>" data-rating="too_easy">Too Easy</button>
            <button class="btn btn-sm btn-secondary rate-btn" data-qid="<?= $ans['question_id'] ?>" data-rating="fair">Fair</button>
            <button class="btn btn-sm btn-secondary rate-btn" data-qid="<?= $ans['question_id'] ?>" data-rating="hard">Hard</button>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; /* solutionsLocked */ ?>
<?php endif; /* mockResultsOut */ ?>

<?php
$subjectLabels  = json_encode(array_keys($subjectBreakdown));
$subjectCorrect = json_encode(array_values(array_map(fn($s) => $s['correct'], $subjectBreakdown)));
$subjectTotal   = json_encode(array_values(array_map(fn($s) => $s['total'], $subjectBreakdown)));
$timesPerQ      = json_encode(array_column($answers, 'time_spent_seconds'));
$base           = BASE_PATH;

// Built with output buffering (not a heredoc) so an auto-formatter can never
// break it by indenting/merging a closing identifier. footer.php echoes it raw.
ob_start();
?>
<script>
// Charts are wrapped so a Chart.js load failure (e.g. blocked CDN) or an empty
// dataset can't abort the script and break the rating buttons below.
function ddcetRenderCharts() {
    if (typeof Chart === 'undefined') return;   // Chart.js not available

    const subjectEl = document.getElementById('subjectChart');
    if (subjectEl) {
        try {
            new Chart(subjectEl, {
                type: 'bar',
                data: {
                    labels: <?= $subjectLabels ?>,
                    datasets: [
                        { label: 'Correct', data: <?= $subjectCorrect ?>, backgroundColor: '#00b894' },
                        { label: 'Total', data: <?= $subjectTotal ?>, backgroundColor: '#333' }
                    ]
                },
                options: {
                    responsive: true,
                    scales: { x: { grid: { display: false } }, y: { beginAtZero: true, grid: { color: '#333' } } },
                    plugins: { legend: { labels: { color: '#888' } } }
                }
            });
        } catch (e) { console.error('subjectChart failed', e); }
    }

    const timeEl = document.getElementById('timeChart');
    if (timeEl) {
        try {
            const times = <?= $timesPerQ ?>;
            new Chart(timeEl, {
                type: 'bar',
                data: {
                    labels: Array.from({length: times.length}, (_, i) => 'Q' + (i+1)),
                    datasets: [{ label: 'Seconds', data: times, backgroundColor: '#3498db', borderRadius: 3 }]
                },
                options: {
                    responsive: true,
                    scales: { x: { grid: { display: false }, ticks: { font: { size: 10 } } }, y: { beginAtZero: true, grid: { color: '#333' } } },
                    plugins: { legend: { display: false } }
                }
            });
        } catch (e) { console.error('timeChart failed', e); }
    }
}
ddcetRenderCharts();

// Difficulty rating — bound independently of the charts so it always works.
document.querySelectorAll('.rate-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        fetch('<?= $base ?>api/rate_question.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ question_id: this.dataset.qid, rating: this.dataset.rating })
        }).catch(() => {});
        this.parentElement.querySelectorAll('.rate-btn').forEach(b => b.style.opacity = '0.4');
        this.style.opacity = '1';
        this.style.borderColor = 'var(--accent)';
    });
});
</script>
<?php
$extraScripts = ob_get_clean();
?>

<?php
// Share text reflects All-India Rank when it's a scheduled mock with results out,
// otherwise the score percentage. One string drives WhatsApp + copy.
$shareTitle = $testInfo['series_label'] ?? ($attempt['title'] ?? 'Mock Test');
$shareText  = $air
    ? "I ranked #{$air['rank']} of " . number_format($air['total']) . " in the {$shareTitle} on DDCET Prep! 🎯 Think you can beat my All-India Rank? "
    : "I scored {$scorePercent}% on DDCET Mock Test! 🎯 Can you beat me? Start preparing: ";
$shareText .= APP_URL . BASE_PATH;
?>
<!-- Share Card -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header"><h3>Share Your <?= $air ? 'Rank' : 'Score' ?></h3></div>
    <div id="shareCard" style="background: linear-gradient(135deg, #1a1a2e, #2d1b69); border-radius: 16px; padding: 32px; text-align: center; color: #fff; max-width: 400px; margin: 0 auto 16px;">
        <div style="font-size: 12px; opacity: 0.7; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">DDCET Prep<?= $air ? ' · All-India Rank' : '' ?></div>
        <?php if ($air): ?>
            <div style="font-size: 48px; font-weight: 900; font-family: var(--font-mono); margin-bottom: 0; color: #f0a500;">#<?= number_format($air['rank']) ?></div>
            <div style="font-size: 12px; opacity: 0.7; margin-bottom: 8px;">of <?= number_format($air['total']) ?> · <?= $scorePercent ?>%</div>
        <?php else: ?>
            <div style="font-size: 48px; font-weight: 900; font-family: var(--font-mono); margin-bottom: 4px; color: <?= $scorePercent >= 60 ? '#10b981' : '#ef4444' ?>;"><?= $scorePercent ?>%</div>
        <?php endif; ?>
        <div style="font-size: 14px; opacity: 0.8; margin-bottom: 16px;"><?= htmlspecialchars($shareTitle) ?></div>
        <div style="display: flex; justify-content: center; gap: 20px; font-size: 12px; opacity: 0.7;">
            <span><?= $attempt['correct_count'] ?? 0 ?> Correct</span>
            <span><?= $attempt['incorrect_count'] ?? 0 ?> Wrong</span>
            <span><?= $attempt['skipped_count'] ?? 0 ?> Skipped</span>
        </div>
        <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid rgba(255,255,255,0.1); font-size: 11px; opacity: 0.5;">Can you beat my score? → ddcetprep.com</div>
    </div>
    <div style="display: flex; gap: 10px; justify-content: center;">
        <a href="https://wa.me/?text=<?= urlencode($shareText) ?>" target="_blank" class="btn btn-success btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
            <svg width="16" height="16" fill="#fff" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            Share on WhatsApp
        </a>
        <button onclick="copyShareText()" class="btn btn-secondary btn-sm">Copy Text</button>
    </div>
</div>

<script>
function copyShareText() {
    const text = <?= json_encode($shareText) ?>;
    navigator.clipboard.writeText(text).then(() => { showToast('Copied! Paste it anywhere.', 'success'); });
}

// Top-10 reward claim: grants 1 month Pro server-side, then flips the button.
(function () {
    var btn = document.getElementById('claimRewardBtn');
    if (!btn) return;
    btn.addEventListener('click', function () {
        btn.disabled = true; btn.textContent = 'Claiming…';
        fetch('<?= BASE_PATH ?>api/claim_reward.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= csrfToken() ?>' },
            body: JSON.stringify({ test_id: btn.dataset.testId })
        }).then(function (r) { return r.json(); }).then(function (d) {
            var action = document.getElementById('rewardAction');
            if (d && d.ok) {
                if (action) action.innerHTML = '<span class="badge badge-green">✓ Pro unlocked</span>';
                if (typeof showToast === 'function') showToast('1 month of Pro unlocked 🎉', 'success');
            } else {
                btn.disabled = false; btn.textContent = 'Claim 1 month Pro →';
                if (typeof showToast === 'function') showToast('Could not claim — ' + ((d && d.error) || 'try again') + '.', 'error');
            }
        }).catch(function () {
            btn.disabled = false; btn.textContent = 'Claim 1 month Pro →';
            if (typeof showToast === 'function') showToast('Network error. Try again.', 'error');
        });
    });
})();

function askAI(questionId) {
    const box = document.getElementById('ai-exp-' + questionId);
    if (!box) return;
    
    box.innerHTML = `<div style="display: flex; align-items: center; gap: 10px; width: 100%;"><span class="spinner" style="width:16px;height:16px;border:2px solid var(--accent);border-top-color:transparent;border-radius:50%;animation:spin 1s linear infinite;"></span> <span>AI is thinking...</span></div>`;
    
    fetch('<?= BASE_PATH ?>api/get_explanation.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= htmlspecialchars(csrfToken()) ?>' },
        body: JSON.stringify({ question_id: questionId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) {
            box.innerHTML = `<span style="color: var(--red);">Failed to generate: ${data.error}</span> <button class="btn btn-sm btn-secondary" onclick="askAI(${questionId})">Retry</button>`;
        } else {
            // Very simple markdown bold to <strong> parsing, and \n to <br>
            let formatted = data.explanation.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>').replace(/\n/g, '<br>');
            box.innerHTML = `<strong style="color: var(--accent);">Explanation:</strong> <span class="explanation-content mathy" style="margin-left: 8px;">${formatted}</span>`;
            if (window.renderMathInElement) {
                renderMathInElement(box, {
                    delimiters: [
                        {left: '$$', right: '$$', display: true},
                        {left: '$', right: '$', display: false}
                    ],
                    throwOnError: false
                });
            }
        }
    })
    .catch(() => {
        box.innerHTML = `<span style="color: var(--red);">Network error.</span> <button class="btn btn-sm btn-secondary" onclick="askAI(${questionId})">Retry</button>`;
    });
}

// Ask AI About Your Report functionality
function analyzeReport() {
    const btn = document.getElementById('analyzeReportBtn');
    const resultBox = document.getElementById('aiReportResult');
    
    // Show loading state
    btn.innerHTML = `<span class="spinner" style="width:14px;height:14px;border:2px solid #fff;border-top-color:transparent;border-radius:50%;animation:spin 1s linear infinite;display:inline-block;vertical-align:middle;margin-right:6px;"></span> Analyzing...`;
    btn.disabled = true;
    resultBox.style.display = 'block';
    resultBox.innerHTML = `
        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 20px 0; color: var(--text-muted);">
            <div class="spinner" style="width:30px;height:30px;border:3px solid var(--accent);border-top-color:transparent;border-radius:50%;animation:spin 1s linear infinite;margin-bottom:12px;"></div>
            <p style="margin:0;font-size:14px;">The AI is reviewing your test data...</p>
            <p style="margin:5px 0 0;font-size:12px;opacity:0.7;">This usually takes about 3-5 seconds</p>
        </div>
    `;
    
    // Scroll to the card so the user sees the loading state
    document.getElementById('aiReportCard').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    
    fetch('<?= BASE_PATH ?>api/analyze_report.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= htmlspecialchars(csrfToken()) ?>' },
        body: JSON.stringify({ attempt_id: <?= $attemptId ?> })
    })
    .then(r => r.json())
    .then(data => {
        btn.innerHTML = `<?= icon('refresh-cw', 14) ?> Analyze Again`;
        btn.disabled = false;
        
        if (data.error) {
            resultBox.innerHTML = `
                <div style="background: rgba(231,76,60,0.1); border: 1px solid var(--red); border-radius: 8px; padding: 16px; color: var(--red);">
                    <div style="font-weight: 700; margin-bottom: 6px;">Failed to generate analysis</div>
                    <div style="font-size: 13px;">${data.error}</div>
                </div>
            `;
        } else {
            // Simple markdown parser
            let html = data.analysis;
            
            // Format headers
            html = html.replace(/^##\s+(.*)$/gm, '<h4 style="margin: 20px 0 10px; color: var(--text-primary); font-size: 16px; border-bottom: 1px solid var(--border); padding-bottom: 6px;">$1</h4>');
            html = html.replace(/^#\s+(.*)$/gm, '<h3 style="margin: 24px 0 12px; color: var(--text-primary);">$1</h3>');
            
            // Format lists (handle both - and * bullets, even if inline)
            html = html.replace(/(?:^|\s)(?:-|\*)\s(.*?)(?=(?:\s(?:-|\*)\s|$))/gm, '<li style="margin-bottom: 6px;">$1</li>');
            html = html.replace(/(<li.*?>.*?<\/li>)+/g, match => `<ul style="margin: 10px 0 16px; padding-left: 20px;">${match}</ul>`);
            
            // Format bold
            html = html.replace(/\*\*(.*?)\*\*/g, '<strong style="color: var(--text-primary);">$1</strong>');
            
            // Format paragraphs (newlines)
            html = html.split('\n\n').map(p => {
                if (p.trim().startsWith('<h') || p.trim().startsWith('<ul')) return p;
                return `<p style="margin: 0 0 16px; line-height: 1.6;">${p.replace(/\n/g, '<br>')}</p>`;
            }).join('');
            
            resultBox.innerHTML = `
                <div class="ai-analysis-content" style="font-size: 14px; color: var(--text-secondary);">
                    ${html}
                </div>
                <div style="margin-top: 16px; font-size: 11px; color: var(--text-muted); text-align: right; opacity: 0.7;">
                    Analysis generated by Google Gemini AI
                </div>
            `;
            
            // Let the KaTeX renderer process any math if there happens to be any
            if (window.renderMathInElement) {
                renderMathInElement(resultBox, {
                    delimiters: [
                        {left: '$$', right: '$$', display: true},
                        {left: '$', right: '$', display: false}
                    ],
                    throwOnError: false
                });
            }
        }
    })
    .catch(err => {
        btn.innerHTML = `<?= icon('refresh-cw', 14) ?> Try Again`;
        btn.disabled = false;
        resultBox.innerHTML = `
            <div style="background: rgba(231,76,60,0.1); border: 1px solid var(--red); border-radius: 8px; padding: 16px; color: var(--red);">
                <div style="font-weight: 700; margin-bottom: 6px;">Network Error</div>
                <div style="font-size: 13px;">Could not connect to the analysis server. Please check your internet connection and try again.</div>
            </div>
        `;
    });
}
</script>

<?php
include __DIR__ . '/includes/footer.php';
?>
