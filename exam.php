<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/icons.php';
$user = requireAuth();

$testId = (int) ($_GET['test_id'] ?? 0);
$mode = $_GET['mode'] ?? '';
$resumeAttemptId = (int) ($_GET['attempt_id'] ?? 0);
$freeMockBypass = false;  // BUG-013 fix: initialize before use (was undefined in some code paths)

// Resume an existing attempt (from custom test)
if ($resumeAttemptId) {
    $existingAttempt = supabaseRest('attempts?id=eq.' . $resumeAttemptId . '&student_id=eq.' . $user['id'] . '&status=eq.in_progress&select=*&limit=1');
    $attempt = $existingAttempt[0] ?? null;
    if (!$attempt) { header('Location: ' . BASE_PATH . 'tests.php'); exit; }

    // Load questions from attempt_answers IN THEIR SEEDED ORDER (position), so the
    // sequence is stable across refresh and identical for both duel players.
    // Old rows have NULL position -> fall back to id order (prior behaviour).
    $aa = supabaseRest('attempt_answers?attempt_id=eq.' . $resumeAttemptId . '&select=question_id&order=position,id') ?? [];
    $qIds = array_column($aa, 'question_id');
    
    // Auto-cleanup for completely broken (0 answers) or abandoned expired pool attempts.
    // If someone resumes a link to a dead test, clean it up and send them back to start fresh.
    $mode = $attempt['mode'] ?? '';
    $isPoolMode = empty($attempt['test_id']) && empty($attempt['challenge_id']);
    $duration = $isPoolMode && isset($poolConfigs[$mode]) ? ($poolConfigs[$mode]['time'] ?? 60) * 60 : 0;
    $elapsed = time() - strtotime($attempt['started_at']);
    $isExpired = $duration > 0 && ($elapsed >= $duration + 120);
    
    if (empty($qIds) || ($isExpired && $isPoolMode)) {
        supabaseRest('attempts?id=eq.' . $resumeAttemptId, 'DELETE');
        header('Location: ' . BASE_PATH . 'tests.php');
        exit;
    }
    
    $questions = $qIds ? orderRowsByIds(supabaseRest('questions?id=in.(' . implode(',', $qIds) . ')&select=*') ?? [], $qIds) : [];
    
    $optionsMap = [];
    if ($qIds) {
        $options = supabaseRest('options?question_id=in.(' . implode(',', $qIds) . ')&select=*&order=position') ?? [];
        foreach ($options as $opt) {
            if (strpos($opt['option_text'], 'Placeholder') === false) {
                $optionsMap[$opt['question_id']][] = $opt;
            }
        }
    }

    // A duel attempt carries challenge_id + a real pool mode; use that mode's
    // official duration and a duel title. Otherwise it's a custom test.
    $challengeId = (int) ($attempt['challenge_id'] ?? 0);
    if ($challengeId && !empty($attempt['mode'])) {
        $test = ['title' => '⚔️ Friend Duel · ' . modeLabel($attempt['mode']), 'duration_minutes' => modeDurationMinutes($attempt['mode']), 'total_marks' => count($questions)];
    } else {
        $test = ['title' => 'Custom Test', 'duration_minutes' => max(10, count($questions) * 1.5), 'total_marks' => count($questions)];
    }
    $attemptId = $attempt['id'];
    $duration = (int)($test['duration_minutes']) * 60;
    $startTime = strtotime($attempt['started_at']);
    $elapsed = time() - $startTime;
    $remaining = max(0, $duration - $elapsed);

    // BUG-032 fix: add JSON_HEX_TAG | JSON_HEX_AMP to prevent XSS via question text
    // containing '</script>' (the main path already uses these flags; this resume path didn't).
    $questionsJson = json_encode(array_map(function($q) use ($optionsMap) {
        return ['id' => $q['id'], 'text' => $q['question_text'], 'text_gu' => $q['question_text_gu'] ?? null, 'image' => $q['question_image'] ?? null, 'marks' => $q['marks'] ?? 1, 'negative' => (float)($q['negative_marks'] ?? 0), 'options' => array_map(fn($o) => ['id' => $o['id'], 'text' => $o['option_text'], 'text_gu' => $o['option_text_gu'] ?? null, 'image' => $o['option_image'] ?? null], $optionsMap[$q['id']] ?? [])];
    }, $questions), JSON_HEX_TAG | JSON_HEX_AMP);

    goto render_exam;
}

// Mode-based pool config (questions per subject, time, etc.)
// Official GTU DDCET Pattern (syllabus published 18.01.2024):
//   BE-01 Basics of Science & Engineering (50Q, 100 marks): Physics 30,
//         Chemistry 10, Computers 5, Environment 5
//   BE-02 Aptitude Test — Maths & Soft Skill (50Q, 100 marks): Maths 25, English 25
//   Total = 100Q, 200 marks, 150 min (2 marks per question, no negative marking)
// Per-subject splits come from ddcetPoolDistribution() in config.php (shared with
// the duel question picker) so the syllabus weights live in exactly one place.
$poolConfigs = [
    'full_mock' => ['total' => 100, 'time' => 150, 'subjects' => ddcetPoolDistribution('full_mock')],
    'be01_paper' => ['total' => 50, 'time' => 75, 'subjects' => ddcetPoolDistribution('be01_paper')],
    'be02_paper' => ['total' => 50, 'time' => 75, 'subjects' => ddcetPoolDistribution('be02_paper')],
    'rapid_fire' => ['total' => 30, 'time' => 30, 'subjects' => null],
    'subject_wise' => ['total' => 30, 'time' => 30, 'subjects' => null],
    'daily_challenge' => ['total' => 10, 'time' => 10, 'subjects' => null],
    'weekly_challenge' => ['total' => 50, 'time' => 60, 'subjects' => null],
    'revision' => ['total' => 20, 'time' => 20, 'subjects' => null],
    'previous_year' => ['total' => 100, 'time' => 150, 'subjects' => null],
];

// If test_id provided, use that specific test
if ($testId) {
    $testData = supabaseRest('tests?id=eq.' . $testId . '&is_published=eq.true&limit=1');
    $test = $testData[0] ?? null;
    if (!$test) { header('Location: ' . BASE_PATH . 'tests.php'); exit; }

    // Institution assignment: only members of the owning org may attempt it.
    // This stops a student from one batch (or a public user) opening another
    // institution's private test by guessing its id.
    if (!empty($test['org_id']) && (int)($user['org_id'] ?? 0) !== (int)$test['org_id']) {
        header('Location: ' . BASE_PATH . 'tests.php'); exit;
    }

    $mode = $test['mode'] ?? '';

    // Scheduled mock: enforce the fixed window + one-attempt fairness. Everyone
    // takes the same paper in the same window, so a completed attempt is final
    // and the test can only be STARTED while it is live.
    if (!empty($test['is_scheduled'])) {
        $doneAttempt = supabaseRest('attempts?student_id=eq.' . $user['id']
            . '&test_id=eq.' . $testId . '&status=eq.completed&select=id&order=completed_at.desc&limit=1');
        if (!empty($doneAttempt)) {
            header('Location: ' . BASE_PATH . 'result.php?attempt_id=' . $doneAttempt[0]['id']);
            exit;
        }
        if (mockStatus($test) !== 'live') {
            header('Location: ' . BASE_PATH . 'mocks.php');
            exit;
        }
    }
} elseif ($mode && isset($poolConfigs[$mode])) {
    // Block weekly challenge if not Sunday
    if ($mode === 'weekly_challenge' && date('N') != 7) {
        header('Location: ' . BASE_PATH . 'tests.php');
        exit;
    }
    // Auto-generate from pool
    $config = $poolConfigs[$mode];
    $minPlan = modeMinPlan($mode);
    $test = [
        'id' => 0,
        'title' => ucwords(str_replace('_', ' ', $mode)),
        'mode' => $mode,
        'duration_minutes' => $config['time'],
        'total_marks' => $config['total'],
        'total_questions' => $config['total'],
        'min_plan' => $minPlan,
        'is_free' => ($minPlan === 'free'),
    ];
} else {
    header('Location: ' . BASE_PATH . 'tests.php');
    exit;
}

// Check subscription access
$subscription = getSubscription();
$plan = $subscription['plan'] ?? 'free';
$planHierarchy = ['free' => 0, 'basic' => 1, 'pro' => 2];
$minPlanLevel = $planHierarchy[$test['min_plan'] ?? 'free'] ?? 0;
// First-time freebie: a non-subscribed user may take ONE full mock for free.
$freeMockBypass = (!$testId && $mode === 'full_mock' && hasFreeMock($user));
if (($planHierarchy[$plan] ?? 0) < $minPlanLevel && empty($test['is_free']) && !$freeMockBypass) {
    header('Location: ' . BASE_PATH . 'subscription.php?need=' . ($test['min_plan'] ?? 'basic'));
    exit;
}

// Load questions
if ($testId) {
    // Fixed test — load assigned questions
    $questions = supabaseRest('questions?test_id=eq.' . $testId . '&select=*&order=position') ?? [];
} else {
    // Pool mode — smart pick with Never Repeat + Adaptive Difficulty
    $config = $poolConfigs[$mode];
    $subject = $_GET['subject'] ?? '';

    // Get questions this student has already seen in the last 30 days
    $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
    $seenData = supabaseRest('attempt_answers?select=question_id,attempts!inner(student_id,started_at)&attempts.student_id=eq.' . $user['id'] . '&attempts.started_at=gte.' . $thirtyDaysAgo) ?? [];
    $seenIds = array_unique(array_column($seenData, 'question_id'));

    // For adaptive mode: get student's subject accuracy
    $weakSubjects = [];
    if ($mode === 'full_mock' || $mode === 'rapid_fire' || $mode === 'weekly_challenge') {
        $pastAnswers = supabaseRest('attempt_answers?select=question_id,is_correct,questions!inner(subject)&attempts!inner(student_id)&attempts.student_id=eq.' . $user['id'] . '&is_correct=not.is.null') ?? [];
        $subjectStats = [];
        foreach ($pastAnswers as $a) {
            $s = $a['questions']['subject'] ?? '';
            if (!$s) continue;
            $subjectStats[$s]['total'] = ($subjectStats[$s]['total'] ?? 0) + 1;
            $subjectStats[$s]['correct'] = ($subjectStats[$s]['correct'] ?? 0) + ($a['is_correct'] ? 1 : 0);
        }
        foreach ($subjectStats as $s => $stat) {
            $weakSubjects[$s] = $stat['total'] > 0 ? ($stat['correct'] / $stat['total']) : 0.5;
        }
    }

    // Previous year: filter by explanation field containing the year (e.g. "DE-2024")
    if ($mode === 'previous_year') {
        $year = $_GET['year'] ?? '2024';
        $poolQ = supabaseRest('questions?test_id=is.null&explanation=like.*' . urlencode($year) . '*&select=id&limit=200') ?? [];
        if (empty($poolQ)) {
            // Fallback: try chapter field
            $poolQ = supabaseRest('questions?test_id=is.null&chapter=like.*' . urlencode($year) . '*&select=id&limit=200') ?? [];
        }
        if (empty($poolQ)) {
            // Fallback: random
            $poolQ = supabaseRest('questions?test_id=is.null&select=id&limit=500') ?? [];
        }
        shuffle($poolQ);
        $questions = array_slice($poolQ, 0, $config['total']);
    }
    // Daily challenge: 1 unique per day (seed by date for same set all day)
    // BUG-009 fix: use the seeded shuffle DIRECTLY without seen/unseen reordering,
    // so all students get the identical question set regardless of their history.
    elseif ($mode === 'daily_challenge') {
        $poolQ = supabaseRest('questions?test_id=is.null&select=id&limit=2000') ?? [];
        // Use today's date as seed for consistent daily set
        $daySeed = crc32(date('Y-m-d') . 'ddcet_daily');
        mt_srand($daySeed);
        shuffle($poolQ);
        mt_srand(); // Reset seed
        $questions = array_slice($poolQ, 0, $config['total']);
    }
    // Revision mode: pick questions they got wrong (spaced repetition)
    elseif ($mode === 'revision') {
        // Wrong answers belonging to THIS student. We embed `attempts` with an
        // inner join purely to filter by student_id — PostgREST cannot order a
        // parent table by an embedded column, and we shuffle below anyway, so no
        // order clause is used here (an invalid one returns 400 → no questions).
        $wrongAnswers = supabaseRest('attempt_answers?is_correct=eq.false&select=question_id,attempts!inner(student_id)&attempts.student_id=eq.' . $user['id'] . '&limit=500') ?? [];
        $wrongIds = array_values(array_unique(array_column($wrongAnswers, 'question_id')));
        if ($wrongIds) {
            shuffle($wrongIds);
            $questions = array_map(fn($id) => ['id' => $id], array_slice($wrongIds, 0, $config['total']));
        } else {
            $questions = [];
        }
    } elseif ($mode === 'subject_wise' && $subject) {
        $poolQ = supabaseRest('questions?test_id=is.null&subject=eq.' . urlencode($subject) . '&select=id&limit=500') ?? [];
        // Prioritize unseen
        $unseen = array_filter($poolQ, fn($q) => !in_array($q['id'], $seenIds));
        $seen = array_filter($poolQ, fn($q) => in_array($q['id'], $seenIds));
        shuffle($unseen);
        shuffle($seen);
        $questions = array_slice(array_merge(array_values($unseen), array_values($seen)), 0, $config['total']);
    } elseif (!empty($config['subjects'])) {
        // Full mock
        $questions = [];
        $subjectConfig = $config['subjects'];

        foreach ($subjectConfig as $sub => $count) {
            $subQ = supabaseRest('questions?test_id=is.null&subject=eq.' . urlencode($sub) . '&select=id&limit=500') ?? [];
            // Prioritize unseen
            $unseen = array_filter($subQ, fn($q) => !in_array($q['id'], $seenIds));
            $seen = array_filter($subQ, fn($q) => in_array($q['id'], $seenIds));
            shuffle($unseen);
            shuffle($seen);
            $picked = array_slice(array_merge(array_values($unseen), array_values($seen)), 0, $count);
            $questions = array_merge($questions, $picked);
        }
        shuffle($questions);
    } else {
        // Random from all (rapid fire, daily, weekly)
        // BUG-030 fix: only select=id here (fetching 1000 full questions is 2MB+)
        $poolQ = supabaseRest('questions?test_id=is.null&select=id&limit=1000') ?? [];
        // Prioritize unseen
        $unseen = array_filter($poolQ, fn($q) => !in_array($q['id'], $seenIds));
        $seen = array_filter($poolQ, fn($q) => in_array($q['id'], $seenIds));
        shuffle($unseen);
        shuffle($seen);
        $questions = array_slice(array_merge(array_values($unseen), array_values($seen)), 0, $config['total']);
    }

    // BUG-030 fix part 2: Now that we have picked the exact N questions (which currently
    // only have 'id' fields), fetch their full contents from the database.
    $pickedIds = array_column($questions, 'id');
    if ($pickedIds) {
        $fullQs = supabaseRest('questions?id=in.(' . implode(',', $pickedIds) . ')&select=*') ?? [];
        // Preserve the randomized order we just created
        $questions = orderRowsByIds($fullQs, $pickedIds);
    }
}

if (empty($questions)) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Not Enough Questions — <?= APP_NAME ?></title>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { background: #f8f9fa; font-family: 'DM Sans', sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
            .error-card { background: #fff; border: 1px solid #e9ecef; border-radius: 16px; padding: 48px; max-width: 420px; width: 100%; text-align: center; box-shadow: 0 16px 48px rgba(0,0,0,0.06); }
            .icon { width: 64px; height: 64px; border-radius: 50%; background: #fef2f2; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
            h2 { font-size: 20px; font-weight: 700; color: #1a1a2e; margin-bottom: 8px; }
            p { font-size: 14px; color: #6c757d; line-height: 1.7; margin-bottom: 24px; }
            .btn { display: inline-block; padding: 12px 28px; background: #4361ee; color: #fff; border-radius: 8px; font-weight: 600; font-size: 14px; text-decoration: none; transition: all 0.2s; }
            .btn:hover { background: #3a56d4; transform: translateY(-1px); }
            .btn-ghost { background: transparent; color: #6c757d; border: 1px solid #e9ecef; margin-left: 8px; }
            .btns { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        </style>
    </head>
    <body>
        <div class="error-card">
            <div class="icon"><svg width="28" height="28" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg></div>
            <?php if ($mode === 'revision'): ?>
            <h2>Nothing to Revise Yet</h2>
            <p>Revision mode replays the questions you've previously got wrong. You don't have any yet — take a few tests first, and any mistakes will show up here for targeted practice.</p>
            <?php else: ?>
            <h2>Not Enough Questions</h2>
            <p>There aren't enough questions available for this test mode right now. Try a different mode or come back later when more questions are added.</p>
            <?php endif; ?>
            <div class="btns">
                <a href="<?= BASE_PATH ?>tests.php" class="btn">← Back to Test Modes</a>
                <a href="<?= BASE_PATH ?>dashboard.php" class="btn btn-ghost">Dashboard</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Load options for all questions
$optionsMap = [];
$qIds = array_column($questions, 'id');
if ($qIds) {
    $options = supabaseRest('options?question_id=in.(' . implode(',', $qIds) . ')&select=*&order=position') ?? [];
    foreach ($options as $opt) {
        if (strpos($opt['option_text'], 'Placeholder') === false) {
            $optionsMap[$opt['question_id']][] = $opt;
        }
    }
}

// Create or resume attempt
$attemptTestId = $testId ?: 0;
if ($testId) {
    $existingAttempt = supabaseRest('attempts?student_id=eq.' . $user['id'] . '&test_id=eq.' . $testId . '&status=eq.in_progress&order=started_at.desc&limit=1');
    $attempt = $existingAttempt[0] ?? null;
} else {
    // Pool modes: resume the most recent in-progress attempt of THIS mode so a
    // page refresh doesn't spawn a fresh attempt (DB bloat + limit/XP farming).
    $existingAttempt = supabaseRest('attempts?student_id=eq.' . $user['id'] . '&test_id=is.null&mode=eq.' . urlencode($mode) . '&status=eq.in_progress&order=started_at.desc&limit=1');
    $attempt = $existingAttempt[0] ?? null;
}

// If we resumed an existing attempt, its questions are fixed in attempt_answers.
// Reload them so the rendered set matches what submit_exam.php will score
// (otherwise the freshly-randomized $questions above would not line up).
if ($attempt) {
    $aaRows = supabaseRest('attempt_answers?attempt_id=eq.' . $attempt['id'] . '&select=question_id&order=position,id') ?? [];
    $resumeQIds = array_column($aaRows, 'question_id');
    
    $duration = ($test['duration_minutes'] ?? 60) * 60;
    $elapsed = time() - strtotime($attempt['started_at']);
    $isExpired = ($elapsed >= $duration + 120);
    
    // If the attempt is completely broken (0 answers) OR if it's an abandoned pool test 
    // that ran out of time in the background, delete it so the student gets a fresh 
    // practice test instead of being instantly auto-submitted by the JS timer.
    if (empty($resumeQIds) || ($isExpired && empty($testId))) {
        supabaseRest('attempts?id=eq.' . $attempt['id'], 'DELETE');
        $attempt = null; // force creation of a new one below
    } else {
        $questions = orderRowsByIds(supabaseRest('questions?id=in.(' . implode(',', $resumeQIds) . ')&select=*') ?? [], $resumeQIds);
        $optionsMap = [];
        $rOpts = supabaseRest('options?question_id=in.(' . implode(',', $resumeQIds) . ')&select=*&order=position') ?? [];
        foreach ($rOpts as $opt) {
            if (strpos($opt['option_text'], 'Placeholder') === false) {
                $optionsMap[$opt['question_id']][] = $opt;
            }
        }
    }
}

if (!$attempt) {
    // BUG-023 fix: Check for an existing in-progress attempt first to prevent
    // double-starts if the user double-clicks the "Start" button or navigates
    // back. The old code blindly created a new attempt on every hit.
    $checkFilter = 'student_id=eq.' . $user['id'] . '&status=eq.in_progress';
    $checkFilter .= $testId ? '&test_id=eq.' . $testId : '&mode=eq.' . urlencode($mode);
    // We order by started_at.desc to make sure we don't pick up the one we just deleted (if caching is aggressive)
    $existing = supabaseRest('attempts?' . $checkFilter . '&select=id,started_at&order=started_at.desc&limit=1');
    if (!empty($existing[0])) {
        // If it's a pool mode and it's heavily expired, don't redirect to it, just let the logic create a new one.
        // (This catches any second/third abandoned attempts from history).
        $exElapsed = time() - strtotime($existing[0]['started_at']);
        $exDuration = ($test['duration_minutes'] ?? 60) * 60;
        if (empty($testId) && $exElapsed >= $exDuration + 120) {
            supabaseRest('attempts?id=eq.' . $existing[0]['id'], 'DELETE');
        } else {
            header('Location: ' . BASE_PATH . 'exam.php?attempt_id=' . $existing[0]['id']);
            exit;
        }
    }

    // Enforce per-mode daily limits ONLY when creating a brand-new attempt.
    // The one-time free full mock skips the limit (it has its own eligibility gate).
    if (!$testId && !$freeMockBypass) {
        $gate = canAttempt($mode, $user);
        if (!$gate['allowed']) {
            header('Location: ' . BASE_PATH . 'tests.php?limit=' . urlencode($mode));
            exit;
        }
    }
    $newAttempt = supabaseRest('attempts', 'POST', [
        'student_id' => $user['id'],
        'test_id' => $testId ?: null,
        'mode' => $testId ? ($test['mode'] ?? null) : $mode,
        'total_marks' => count($questions),
        'started_at' => date('c'),
        'status' => 'in_progress',
    ]);
    $attempt = $newAttempt[0] ?? null;

    // Pre-create answer rows, recording the question order (position) so a resume
    // renders them in the same sequence shown on first load.
    if ($attempt) {
        // BUG-004 fix: deduplicate question IDs before inserting answer rows.
        // A small question pool can produce duplicate IDs after shuffle+slice,
        // which violates the unique constraint on (attempt_id, question_id) and
        // causes the entire batch insert to fail silently — the exam starts with
        // 0 answer rows, and submit_exam.php returns 'no_answers' error.
        $answerRows = [];
        $seenQIds = [];
        foreach ($questions as $i => $q) {
            if (isset($seenQIds[$q['id']])) continue;  // skip duplicate
            $seenQIds[$q['id']] = true;
            $answerRows[] = ['attempt_id' => $attempt['id'], 'question_id' => $q['id'], 'position' => $i];
        }
        // Insert in chunks (like custom-test.php does) for reliability.
        foreach (array_chunk($answerRows, 50) as $chunk) {
            supabaseRest('attempt_answers', 'POST', $chunk);
        }
    }
}

// If the attempt row couldn't be created (most often because the
// database/test_integrity.sql migration adding attempts.mode hasn't been run),
// surface it instead of silently bouncing — a bare redirect looks like a loop.
if (!$attempt) { header('Location: ' . BASE_PATH . 'tests.php?error=start_failed'); exit; }

$attemptId = $attempt['id'];
$duration = ($test['duration_minutes'] ?? 60) * 60;
$startTime = strtotime($attempt['started_at']);
$elapsed = time() - $startTime;
$remaining = max(0, $duration - $elapsed);
// Duel attempts created via the lobby are entered through the resume path, so a
// challenge id is only present there; default to 0 for ordinary attempts.
$challengeId = (int) ($attempt['challenge_id'] ?? 0);

// Prepare JSON data for JS
$questionsJson = json_encode(array_map(function($q) use ($optionsMap) {
    return [
        'id' => $q['id'],
        'text' => $q['question_text'],
        'text_gu' => $q['question_text_gu'] ?? null,
        'image' => $q['question_image'] ?? null,
        'marks' => $q['marks'] ?? 1,
        'negative' => (float) ($q['negative_marks'] ?? 0),
        'options' => array_map(fn($o) => ['id' => $o['id'], 'text' => $o['option_text'], 'text_gu' => $o['option_text_gu'] ?? null, 'image' => $o['option_image'] ?? null], $optionsMap[$q['id']] ?? []),
    ];
}, $questions), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
render_exam:
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($test['title']) ?> - Exam</title>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/contrib/auto-render.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #1a1a1a; color: #e0e0e0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; font-size: 14px; height: 100vh; display: flex; flex-direction: column; -webkit-user-select: none; user-select: none; }

        /* Exam Header */
        .exam-header { background: #282828; border-bottom: 1px solid #3a3a3a; padding: 12px 24px; display: flex; align-items: center; justify-content: space-between; }
        .exam-header .title { font-size: 15px; font-weight: 600; }
        .exam-header .timer { font-family: 'JetBrains Mono', monospace; font-size: 18px; font-weight: 700; color: #f0a500; }
        .exam-header .timer.danger { color: #e74c3c; animation: pulse 1s infinite; }
        @keyframes pulse { 50% { opacity: 0.5; } }
        .exam-header .controls { display: flex; gap: 8px; }
        .exam-header .controls button { background: #333; border: 1px solid #444; color: #e0e0e0; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; }
        .exam-header .controls button:hover { background: #444; }

        /* Main exam layout */
        .exam-body { display: flex; flex: 1; overflow: hidden; }

        /* Question panel */
        .question-panel { flex: 1; padding: 32px; overflow-y: auto; }
        .q-number { color: #888; font-size: 12px; margin-bottom: 8px; }
        .q-text { font-size: 16px; line-height: 1.7; margin-bottom: 24px; }
        .q-image { max-width: 100%; border-radius: 8px; margin-bottom: 16px; }

        /* Options */
        .options-list { display: flex; flex-direction: column; gap: 12px; }
        .option-item { background: #282828; border: 2px solid #3a3a3a; border-radius: 8px; padding: 14px 16px; cursor: pointer; display: flex; align-items: center; gap: 12px; transition: all 0.15s; }
        .option-item:hover { border-color: #555; }
        .option-item.selected { border-color: #f0a500; background: rgba(240,165,0,0.08); }
        .option-key { width: 28px; height: 28px; border-radius: 50%; background: #333; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 12px; flex-shrink: 0; }
        .option-item.selected .option-key { background: #f0a500; color: #1a1a1a; }

        /* Question actions */
        .q-actions { margin-top: 24px; display: flex; gap: 12px; }
        .q-actions button { padding: 10px 20px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; }
        .btn-flag { background: #333; color: #e0e0e0; border: 1px solid #444; }
        .btn-flag.flagged { background: rgba(155,89,182,0.2); border-color: #9b59b6; color: #9b59b6; }
        .btn-clear { background: #333; color: #888; border: 1px solid #444; }
        .btn-next { background: #f0a500; color: #1a1a1a; }
        .btn-prev { background: #333; color: #e0e0e0; border: 1px solid #444; }

        /* Sidebar palette */
        .exam-sidebar { width: 240px; background: #282828; border-left: 1px solid #3a3a3a; padding: 16px; overflow-y: auto; display: flex; flex-direction: column; }
        .palette-title { font-size: 12px; color: #888; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 1px; }
        .palette { display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px; margin-bottom: 20px; }
        .palette-btn { width: 36px; height: 36px; border-radius: 6px; border: none; cursor: pointer; font-size: 12px; font-weight: 600; font-family: 'JetBrains Mono', monospace; background: #333; color: #888; }
        .palette-btn.answered { background: #00b894; color: #fff; }
        .palette-btn.skipped { background: #e74c3c; color: #fff; }
        .palette-btn.flagged { background: #9b59b6; color: #fff; }
        .palette-btn.current { outline: 2px solid #f0a500; outline-offset: 2px; }

        .palette-legend { margin-top: auto; }
        .legend-item { display: flex; align-items: center; gap: 8px; font-size: 11px; color: #888; margin-bottom: 6px; }
        .legend-dot { width: 10px; height: 10px; border-radius: 3px; }

        .submit-section { margin-top: 16px; }
        .submit-section button { width: 100%; padding: 12px; background: #00b894; color: #fff; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 14px; }
        .submit-section button:hover { background: #00a382; }

        /* Calculator */
        .calculator-overlay { display: none; position: fixed; bottom: 20px; right: 270px; background: #282828; border: 1px solid #3a3a3a; border-radius: 12px; padding: 16px; z-index: 200; width: 240px; }
        .calculator-overlay.show { display: block; }
        .calc-display { background: #1a1a1a; border: 1px solid #3a3a3a; border-radius: 6px; padding: 12px; font-family: 'JetBrains Mono', monospace; font-size: 18px; text-align: right; margin-bottom: 12px; min-height: 44px; color: #e0e0e0; word-break: break-all; }
        .calc-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; }
        .calc-btn { padding: 10px; background: #333; border: none; border-radius: 6px; color: #e0e0e0; font-size: 14px; cursor: pointer; font-family: 'JetBrains Mono', monospace; }
        .calc-btn:hover { background: #444; }
        .calc-btn.op { background: #f0a500; color: #1a1a1a; }

        /* Scratch Pad */
        .scratch-overlay { display: none; position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: #282828; border: 1px solid #3a3a3a; border-radius: 12px; padding: 16px; z-index: 200; width: 500px; }
        .scratch-overlay.show { display: block; }
        .scratch-toolbar { display: flex; gap: 8px; margin-bottom: 8px; align-items: center; }
        .scratch-toolbar button { padding: 6px 12px; background: #333; border: none; border-radius: 6px; color: #e0e0e0; font-size: 12px; cursor: pointer; }
        .scratch-toolbar button:hover { background: #444; }
        .scratch-toolbar button.active { background: #f0a500; color: #1a1a1a; }
        .scratch-content { width: 100%; height: 300px; background: #1a1a1a; border: 1px solid #3a3a3a; border-radius: 6px; position: relative; }
        .scratch-textarea { width: 100%; height: 100%; background: transparent; border: none; padding: 12px; color: #e0e0e0; font-family: 'JetBrains Mono', monospace; font-size: 13px; resize: none; display: none; }
        .scratch-canvas { display: none; cursor: crosshair; }
        .scratch-nav { display: flex; justify-content: space-between; align-items: center; margin-top: 8px; }
        .scratch-nav button { padding: 6px 12px; background: #333; border: none; border-radius: 6px; color: #e0e0e0; font-size: 12px; cursor: pointer; }
        .scratch-nav button:hover { background: #444; }
        .scratch-page-info { font-size: 12px; color: #888; }

        /* Tab switch warning */
        .tab-warning { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.9); z-index: 999; align-items: center; justify-content: center; flex-direction: column; }
        .tab-warning.show { display: flex; }
        .tab-warning h2 { color: #e74c3c; font-size: 24px; margin-bottom: 12px; }
        .tab-warning p { color: #888; margin-bottom: 24px; }
        .tab-warning button { padding: 12px 32px; background: #f0a500; color: #1a1a1a; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; }

        @media (max-width: 768px) {
            .exam-sidebar { display: none; }
            .exam-sidebar.mobile-open { display: flex; position: fixed; inset: 0; z-index: 500; width: 100%; }
            .question-panel { padding: 16px; padding-bottom: 80px; }
            .mobile-bar { display: flex; position: fixed; bottom: 0; left: 0; right: 0; background: #282828; border-top: 1px solid #3a3a3a; padding: 10px 16px; gap: 8px; z-index: 400; }
            .mobile-bar button { flex: 1; padding: 10px; border-radius: 8px; border: none; font-size: 12px; font-weight: 600; cursor: pointer; }
            .mobile-bar .btn-palette { background: #333; color: #e0e0e0; border: 1px solid #444; }
            .mobile-bar .btn-submit { background: #00b894; color: #fff; }
        }
        @media (min-width: 769px) {
            .mobile-bar { display: none; }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="exam-header">
        <div class="title"><?= htmlspecialchars($test['title']) ?></div>
        <div class="timer" id="timer">--:--</div>
        <div class="controls">
            <?php if ($challengeId): ?>
            <div id="opponentBar" style="display:flex; align-items:center; gap:8px; background:#1f1f1f; border:1px solid #3a3a3a; border-radius:8px; padding:6px 12px; font-size:12px;">
                <span style="color:#888;">vs</span>
                <span id="oppBarName" style="font-weight:600;">Opponent</span>
                <span id="oppBarProgress" style="color:#888; font-family:'JetBrains Mono',monospace;">0</span>
                <span id="oppBarScore" style="color:#f0a500; font-family:'JetBrains Mono',monospace; font-weight:700;"></span>
            </div>
            <?php endif; ?>
            <button id="langToggle" onclick="toggleLanguage()" title="Switch question language (English / ગુજરાતી)"><?= icon('globe') ?> <span id="langLabel">EN</span></button>
            <button onclick="toggleCalculator()"><?= icon('calculator') ?> Calc</button>
            <button onclick="toggleScratch()"><?= icon('notepad') ?> Scratch</button>
        </div>
    </div>

    <!-- Body -->
    <div class="exam-body">
        <!-- Question -->
        <div class="question-panel" id="questionPanel"></div>

        <!-- Palette Sidebar -->
        <div class="exam-sidebar">
            <div class="palette-title">Question Palette</div>
            <div class="palette" id="palette"></div>
            <div class="palette-legend">
                <div class="legend-item"><div class="legend-dot" style="background:#00b894"></div> Answered</div>
                <div class="legend-item"><div class="legend-dot" style="background:#e74c3c"></div> Skipped</div>
                <div class="legend-item"><div class="legend-dot" style="background:#9b59b6"></div> Flagged</div>
                <div class="legend-item"><div class="legend-dot" style="background:#333"></div> Not Visited</div>
            </div>
            <div class="submit-section">
                <button id="submitBtn" onclick="showSubmitModal()">Submit Test</button>
            </div>
        </div>
    </div>

    <!-- Calculator -->
    <div class="calculator-overlay" id="calculator">
        <div class="calc-display" id="calcDisplay">0</div>
        <div class="calc-grid">
            <button class="calc-btn" onclick="calcInput('7')">7</button>
            <button class="calc-btn" onclick="calcInput('8')">8</button>
            <button class="calc-btn" onclick="calcInput('9')">9</button>
            <button class="calc-btn op" onclick="calcInput('/')">/</button>
            <button class="calc-btn" onclick="calcInput('4')">4</button>
            <button class="calc-btn" onclick="calcInput('5')">5</button>
            <button class="calc-btn" onclick="calcInput('6')">6</button>
            <button class="calc-btn op" onclick="calcInput('*')">×</button>
            <button class="calc-btn" onclick="calcInput('1')">1</button>
            <button class="calc-btn" onclick="calcInput('2')">2</button>
            <button class="calc-btn" onclick="calcInput('3')">3</button>
            <button class="calc-btn op" onclick="calcInput('-')">-</button>
            <button class="calc-btn" onclick="calcInput('0')">0</button>
            <button class="calc-btn" onclick="calcInput('.')">.</button>
            <button class="calc-btn" onclick="calcEval()">=</button>
            <button class="calc-btn op" onclick="calcInput('+')">+</button>
            <button class="calc-btn" onclick="calcClear()" style="grid-column: span 4; background: #e74c3c; color: #fff;">Clear</button>
        </div>
    </div>

    <!-- Scratch Pad -->
    <div class="scratch-overlay" id="scratch">
        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
            <span style="font-size: 12px; color: #888;">Rough Work</span>
            <button onclick="toggleScratch()" style="background: none; border: none; color: #888; cursor: pointer;"><?= icon('close', 14) ?></button>
        </div>
        <div class="scratch-toolbar">
            <button id="penBtn" onclick="switchMode('pen')">Pen</button>
            <button id="typeBtn" onclick="switchMode('type')">Type</button>
            <button id="eraseBtn" onclick="eraseScratch()" style="display: none;">Erase</button>
            <button onclick="addScratchPage()">+ Add Page</button>
        </div>
        <div class="scratch-content">
            <textarea id="scratchText" class="scratch-textarea" placeholder="Type your notes here..."></textarea>
            <canvas id="scratchCanvas" class="scratch-canvas" width="468" height="300"></canvas>
        </div>
        <div class="scratch-nav">
            <button onclick="prevScratchPage()">← Prev</button>
            <span class="scratch-page-info" id="pageInfo">Page 1 / 1</span>
            <button onclick="nextScratchPage()">Next →</button>
        </div>
    </div>

    <!-- Tab Switch Warning -->
    <div class="tab-warning" id="tabWarning">
        <h2><?= icon('warning', 24) ?> Tab Switch Detected!</h2>
        <p>Switching tabs is not allowed during the exam. This has been logged.</p>
        <button onclick="dismissWarning()">Return to Exam</button>
    </div>

    <!-- Submit Confirmation Modal -->
    <div id="submitModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.8);z-index:999;align-items:center;justify-content:center;">
        <div style="background:#282828;border:1px solid #3a3a3a;border-radius:16px;padding:32px;max-width:380px;width:90%;text-align:center;">
            <div style="width:56px;height:56px;border-radius:50%;background:rgba(0,184,148,0.1);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                <svg width="24" height="24" fill="none" stroke="#00b894" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 14l2 2 4-4"/></svg>
            </div>
            <h3 style="color:#fff;font-size:18px;margin-bottom:8px;">Submit Test?</h3>
            <p style="color:#888;font-size:13px;margin-bottom:24px;">You won't be able to change your answers after submitting.</p>
            <div id="submitStats" style="background:#1a1a1a;border-radius:8px;padding:12px;margin-bottom:20px;display:flex;justify-content:center;gap:20px;font-size:12px;color:#888;"></div>
            <div style="display:flex;gap:10px;justify-content:center;">
                <button onclick="hideSubmitModal()" style="padding:10px 24px;background:#333;border:1px solid #444;color:#e0e0e0;border-radius:8px;cursor:pointer;font-size:13px;">Go Back</button>
                <button onclick="hideSubmitModal();submitExam()" style="padding:10px 24px;background:#00b894;border:none;color:#fff;border-radius:8px;cursor:pointer;font-size:13px;font-weight:700;">Confirm Submit</button>
            </div>
        </div>
    </div>

    <!-- Submitting Overlay -->
    <div id="submittingOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.95);z-index:1000;align-items:center;justify-content:center;flex-direction:column;">
        <div style="width:48px;height:48px;border:4px solid #333;border-top-color:#00b894;border-radius:50%;animation:spin 0.8s linear infinite;margin-bottom:20px;"></div>
        <h3 style="color:#fff;font-size:18px;margin-bottom:6px;">Submitting your test...</h3>
        <p style="color:#888;font-size:13px;">Calculating your score. Don't close this page.</p>
    </div>
    <style>@keyframes spin{to{transform:rotate(360deg)}}</style>

    <!-- Mobile Bottom Bar -->
    <div class="mobile-bar">
        <button class="btn-palette" onclick="document.querySelector('.exam-sidebar').classList.toggle('mobile-open')">Questions</button>
        <button class="btn-submit" onclick="showSubmitModal()">Submit Test</button>
    </div>

<script>
function showToast(msg, type = 'info', duration = 3500) {
    let container = document.querySelector('.toast-container');
    if (!container) { container = document.createElement('div'); container.className = 'toast-container'; container.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;'; document.body.appendChild(container); }
    const toast = document.createElement('div');
    const colors = { error: ['#fef2f2','#fecaca','#dc2626'], success: ['#f0fdf4','#bbf7d0','#16a34a'], info: ['#eff6ff','#bfdbfe','#2563eb'] };
    const c = colors[type] || colors.info;
    toast.style.cssText = 'background:'+c[0]+';border:1px solid '+c[1]+';color:'+c[2]+';padding:14px 20px;border-radius:10px;font-size:13px;margin-bottom:8px;box-shadow:0 8px 24px rgba(0,0,0,0.1);animation:slideIn 0.3s ease;';
    toast.textContent = msg;
    container.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, duration);
}

const questions = <?= $questionsJson ?>;
const attemptId = <?= $attemptId ?>;
const challengeId = <?= (int) $challengeId ?>;   // 0 unless this is a friend duel
const totalTime = <?= $remaining ?>;
let currentQ = 0;
let answers = {}; // questionId -> optionId
let flags = {};   // questionId -> bool
let timeLeft = totalTime;
// Exam medium: 'en' | 'gu'. Seeded from the attempt, but a per-attempt
// localStorage choice (set by the toggle) wins so a refresh keeps the language.
let examLang = '<?= (($attempt['language'] ?? 'en') === 'gu') ? 'gu' : 'en' ?>';
try { const _l = localStorage.getItem('examLang_' + <?= $attemptId ?>); if (_l === 'gu' || _l === 'en') examLang = _l; } catch (e) {}
let tabSwitches = <?= (int)($attempt['tab_switches'] ?? 0) ?>;
let questionStartTimes = {}; // questionId -> timestamp
let questionTimes = {};      // questionId -> total seconds

// Bilingual text pickers: show Gujarati when the student picked it AND it exists,
// otherwise fall back to the English text (so untranslated items still render).
function qText(q) { return (examLang === 'gu' && q.text_gu) ? q.text_gu : q.text; }
function oText(o) { return (examLang === 'gu' && o.text_gu) ? o.text_gu : o.text; }

function applyLangLabel() {
    const lbl = document.getElementById('langLabel');
    if (lbl) lbl.textContent = (examLang === 'gu') ? 'ગુજ' : 'EN';
}

function toggleLanguage() {
    examLang = (examLang === 'gu') ? 'en' : 'gu';
    try { localStorage.setItem('examLang_' + attemptId, examLang); } catch (e) {}
    applyLangLabel();
    showQuestion(currentQ);   // re-render current question in the new medium
    // Persist to the attempt so the result/review screen can match the medium.
    fetch('<?= BASE_PATH ?>api/set_exam_language.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= htmlspecialchars(csrfToken()) ?>' },
        body: JSON.stringify({ attempt_id: attemptId, language: examLang })
    }).catch(() => {});
}

// Init
function init() {
    applyLangLabel();
    renderPalette();
    showQuestion(0);
    startTimer();
    trackTabSwitches();
    questionStartTimes[questions[0].id] = Date.now();
    switchMode('pen'); // Initialize scratch pad in pen mode
    if (challengeId) startChallengeSync();
}

// --- Friend duel: show the opponent's live progress/score and report ours. ---
function startChallengeSync() {
    const poll = () => {
        fetch('<?= BASE_PATH ?>api/challenge_state.php?challenge_id=' + challengeId)
            .then(r => r.json()).then(d => {
                if (!d.ok) return;
                const nameEl = document.getElementById('oppBarName');
                const progEl = document.getElementById('oppBarProgress');
                const scoreEl = document.getElementById('oppBarScore');
                if (nameEl) nameEl.textContent = d.opponent_name || 'Opponent';
                if (progEl) progEl.textContent = (d.opponent_progress || 0) + '/' + questions.length;
                if (scoreEl && d.opponent_score !== null) scoreEl.textContent = '· ' + d.opponent_score + ' pts';
            }).catch(() => {});
    };
    poll();
    setInterval(poll, 3000);
    // Report my answered-count whenever it changes (debounced by the 3s tick).
    setInterval(reportChallengeProgress, 3000);
}
let lastReported = -1;
function reportChallengeProgress() {
    if (!challengeId) return;
    const answered = Object.keys(answers).length;
    if (answered === lastReported) return;
    lastReported = answered;
    fetch('<?= BASE_PATH ?>api/challenge_progress.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-Token': '<?= htmlspecialchars(csrfToken()) ?>'},
        body: JSON.stringify({ challenge_id: challengeId, answered: answered })
    }).catch(() => {});
}

// Timer
let timerInterval = null;
let examSubmitted = false;   // guards against repeated submits (timer + manual)
function startTimer() {
    const timerEl = document.getElementById('timer');
    // BUG-008 fix: compute remaining time from a fixed end-time instead of
    // decrementing a counter. setInterval pauses when the tab/app is
    // backgrounded (iOS Safari freezes timers entirely in PWA standalone),
    // so a decrementing counter drifts — the student thinks they have more
    // time than actually remains. Using Date.now() each tick means the
    // display always reflects real wall-clock elapsed time.
    const endTime = Date.now() + timeLeft * 1000;
    const tick = () => {
        if (examSubmitted) return;
        timeLeft = Math.max(0, Math.floor((endTime - Date.now()) / 1000));
        if (timeLeft <= 0) {
            if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }
            submitExam();
            return;
        }
        const m = Math.floor(timeLeft / 60);
        const s = timeLeft % 60;
        timerEl.textContent = `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
        if (timeLeft < 300) timerEl.classList.add('danger');
    };
    tick();
    timerInterval = setInterval(tick, 1000);
}

// Pull "fill-in-the-blank" underscores OUT of math mode. A bare "_" is the TeX
// subscript operator, so "$______$" is a KaTeX parse error that (on throwOnError
// surfaces) can blank out the rest of the question. Mirrors normalize_math_blanks()
// in admin/math-check.php. Real subscripts (x_1) use a single "_" and are untouched.
function fixMathBlanks(s) {
    if (!s || s.indexOf('_') === -1) return s;
    return s.replace(/(\${1,2})([^$]*?)\1/g, function (whole, delim, inner) {
        if (!/_{2,}/.test(inner)) return whole;
        return inner.split(/(_{2,})/).map(function (p) {
            if (p === '') return '';
            if (/^_{2,}$/.test(p)) return p;        // the blank → plain text
            if (p.trim() === '') return p;          // spacing → keep
            return delim + p.trim() + delim;        // real math → re-wrap
        }).join('');
    });
}

// Show question
function showQuestion(idx) {
    // Track time on previous question
    if (questions[currentQ]) {
        const prevId = questions[currentQ].id;
        if (questionStartTimes[prevId]) {
            questionTimes[prevId] = (questionTimes[prevId] || 0) + Math.round((Date.now() - questionStartTimes[prevId]) / 1000);
        }
    }

    currentQ = idx;
    const q = questions[idx];
    questionStartTimes[q.id] = Date.now();

    const keys = ['A','B','C','D','E','F'];
    let html = `<div class="q-number">Question ${idx + 1} of ${questions.length} &nbsp;|&nbsp; ${q.marks} mark(s) ${q.negative > 0 ? '&nbsp;|&nbsp; -' + q.negative + ' negative' : ''}</div>`;
    if (q.image) html += `<img src="${q.image}" class="q-image">`;
    html += `<div class="q-text">${fixMathBlanks(qText(q))}</div>`;
    html += `<div class="options-list">`;
    q.options.forEach((opt, i) => {
        const selected = answers[q.id] === opt.id ? 'selected' : '';
        html += `<div class="option-item ${selected}" onclick="selectOption(${q.id}, ${opt.id}, this)">
            <div class="option-key">${keys[i]}</div>
            <div>${fixMathBlanks(oText(opt))}${opt.image ? '<img src="'+opt.image+'" style="max-width:200px;margin-top:8px;">' : ''}</div>
        </div>`;
    });
    html += `</div>`;
    html += `<div class="q-actions">`;
    if (idx > 0) html += `<button class="btn-prev" onclick="showQuestion(${idx-1})">← Previous</button>`;
    html += `<button class="btn-flag ${flags[q.id] ? 'flagged' : ''}" onclick="toggleFlag(${q.id})"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle;"><path d="M14.4 6L14 4H5v17h2v-7h5.6l.4 2h7V6z"/></svg> ${flags[q.id] ? 'Unflag' : 'Flag'}</button>`;
    html += `<button class="btn-clear" onclick="clearAnswer(${q.id})">Clear</button>`;
    if (idx < questions.length - 1) html += `<button class="btn-next" onclick="showQuestion(${idx+1})">Next →</button>`;
    html += `</div>`;

    document.getElementById('questionPanel').innerHTML = html;
    // Render KaTeX math (only for explicit $ delimiters + safe auto-formatting)
    if (window.renderMathInElement) {
        const panel = document.getElementById('questionPanel');
        panel.querySelectorAll('.q-text, .option-item div:last-child').forEach(el => {
            let t = el.innerHTML;
            if (!t.includes('$')) {
                // Only safe patterns that won't break text.
                // NOTE: in a JS replacement string "$$" emits a literal "$", so a
                // "$" delimiter immediately followed by capture group 1 must be
                // written "$$$1" — "$$1" would emit a literal "1" and drop the
                // mantissa (turning "3 x 10^8" into "1 x 10^8").
                t = t.replace(/(\d+)\s*[x×]\s*10\^(-?\d+)/g, '$$$1 \\times 10^{$2}$$');
                t = t.replace(/(^|[^a-zA-Z])(\d+)\/(\d+)(?![a-zA-Z])/g, '$1$$\\frac{$2}{$3}$$');
                t = t.replace(/√\s*(\d+(?:\.\d+)?)/g, '$\\sqrt{$1}$');
                t = t.replace(/±/g, '$\\pm$');
                t = t.replace(/×/g, '$\\times$');
                t = t.replace(/÷/g, '$\\div$');
                t = t.replace(/≤/g, '$\\leq$');
                t = t.replace(/≥/g, '$\\geq$');
                t = t.replace(/≠/g, '$\\neq$');
                t = t.replace(/∞/g, '$\\infty$');
                t = t.replace(/∑/g, '$\\sum$');
                t = t.replace(/π/g, '$\\pi$');
                t = t.replace(/∫/g, '$\\int$');
                t = t.replace(/\/°C/g, '/$^\\circ$C');
                t = t.replace(/°C/g, '$^\\circ$C');
                t = t.replace(/°/g, '$^\\circ$');
                el.innerHTML = t;
            }
        });
        renderMathInElement(panel, {
            delimiters: [
                {left: '$$', right: '$$', display: true},
                {left: '$', right: '$', display: false},
                {left: '\\(', right: '\\)', display: false},
                {left: '\\[', right: '\\]', display: true}
            ],
            throwOnError: false
        });
    }
    renderPalette();
}

function selectOption(qId, optId, el) {
    answers[qId] = optId;
    document.querySelectorAll('.option-item').forEach(o => o.classList.remove('selected'));
    el.classList.add('selected');
    renderPalette();
    // Auto-save
    saveAnswer(qId, optId);
}

function clearAnswer(qId) {
    delete answers[qId];
    showQuestion(currentQ);
    saveAnswer(qId, null);
}

function toggleFlag(qId) {
    flags[qId] = !flags[qId];
    showQuestion(currentQ);
}

// Palette
function renderPalette() {
    let html = '';
    questions.forEach((q, i) => {
        let cls = '';
        if (answers[q.id]) cls = 'answered';
        else if (flags[q.id]) cls = 'flagged';
        else if (i < currentQ && !answers[q.id]) cls = 'skipped';
        if (i === currentQ) cls += ' current';
        html += `<button class="palette-btn ${cls}" onclick="showQuestion(${i})">${i+1}</button>`;
    });
    document.getElementById('palette').innerHTML = html;
    // BUG-033 fix: close the mobile sidebar when a question is selected from palette.
    document.querySelectorAll('.palette-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const sidebar = document.querySelector('.exam-sidebar');
            if (sidebar) sidebar.classList.remove('mobile-open');
        });
    });
}

// Save answer via AJAX
// BUG-015 fix: add error handling so the student knows if a save fails
// (network flap, expired session, etc.). The old fire-and-forget fetch
// silently swallowed errors — the student saw their selection highlighted
// locally but the server had no record.
function saveAnswer(qId, optId) {
    fetch('<?= BASE_PATH ?>api/save_answer.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-Token': '<?= htmlspecialchars(csrfToken()) ?>'},
        body: JSON.stringify({ attempt_id: attemptId, question_id: qId, option_id: optId, is_flagged: !!flags[qId], time_spent: questionTimes[qId] || 0 })
    }).then(r => {
        if (r.status === 401) {
            showToast('Session expired. Please login again.', 'error');
            return;
        }
        if (!r.ok) {
            showToast('Failed to save answer. Check your connection.', 'error');
        }
    }).catch(() => {
        showToast('Network error — answer may not be saved.', 'error');
    });
}

// Submit
function showSubmitModal() {
    const answered = Object.keys(answers).length;
    const flagged = Object.keys(flags).filter(k => flags[k]).length;
    const unanswered = questions.length - answered;
    document.getElementById('submitStats').innerHTML = '<span><strong style="color:#00b894;">' + answered + '</strong> Answered</span><span><strong style="color:#e74c3c;">' + unanswered + '</strong> Unanswered</span><span><strong style="color:#9b59b6;">' + flagged + '</strong> Flagged</span>';
    const modal = document.getElementById('submitModal');
    modal.style.display = 'flex';
}
function hideSubmitModal() { document.getElementById('submitModal').style.display = 'none'; }

function submitExam() {
    if (examSubmitted) return;          // never submit twice (timer race / double click)
    examSubmitted = true;
    if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }

    // Show full-screen overlay immediately — blocks all interaction
    const overlay = document.getElementById('submittingOverlay');
    overlay.style.display = 'flex';

    const btn = document.getElementById('submitBtn');
    btn.textContent = 'Submitting...';
    btn.disabled = true;

    // Let the user recover instead of being stuck on the overlay forever.
    const recover = (msg) => {
        examSubmitted = false;
        overlay.style.display = 'none';
        btn.textContent = 'Submit Test';
        btn.disabled = false;
        if (msg) showToast(msg, 'error');
    };

    // Final time tracking
    const prevId = questions[currentQ].id;
    if (questionStartTimes[prevId]) {
        questionTimes[prevId] = (questionTimes[prevId] || 0) + Math.round((Date.now() - questionStartTimes[prevId]) / 1000);
    }

    fetch('<?= BASE_PATH ?>api/submit_exam.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-Token': '<?= htmlspecialchars(csrfToken()) ?>'},
        body: JSON.stringify({ attempt_id: attemptId, tab_switches: tabSwitches, question_times: questionTimes })
    }).then(r => {
        if (r.status === 401) { showToast('Session expired. Please login again.', 'error'); setTimeout(() => window.location.href = '<?= BASE_PATH ?>auth/login.php', 2000); return null; }
        return r.json().catch(() => ({ error: 'Bad server response' }));
    }).then(data => {
        if (data === null) return;                  // 401 handled above (redirecting)
        if (data.error) { recover('Error: ' + data.error); return; }
        // Success (incl. already_submitted) — leave the page so we never re-submit.
        window.location.href = '<?= BASE_PATH ?>result.php?attempt_id=' + attemptId;
    }).catch(err => { recover('Network error: ' + err.message); });
}

// Tab switch detection
function trackTabSwitches() {
    // Prevent copy/paste/right-click during exam
    document.addEventListener('copy', e => e.preventDefault());
    document.addEventListener('cut', e => e.preventDefault());
    document.addEventListener('contextmenu', e => e.preventDefault());
    document.addEventListener('selectstart', e => e.preventDefault());

    // Tab switch detection
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            tabSwitches++;
            document.getElementById('tabWarning').classList.add('show');
            fetch('<?= BASE_PATH ?>api/save_answer.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-Token': '<?= htmlspecialchars(csrfToken()) ?>'},
                body: JSON.stringify({ attempt_id: attemptId, log_tab_switch: true, tab_switches: tabSwitches })
            });
        }
    });

    // Warn before leaving page — but NOT while/after submitting, otherwise the
    // success redirect triggers the native "leave?" prompt and the submit appears
    // to hang on the overlay.
    window.addEventListener('beforeunload', function(e) {
        if (examSubmitted) return;       // allow the post-submit redirect through
        e.preventDefault();
        e.returnValue = 'Exam is in progress. Are you sure you want to leave?';
    });
}

function dismissWarning() {
    document.getElementById('tabWarning').classList.remove('show');
}

// Calculator
let calcExpr = '';
function toggleCalculator() { document.getElementById('calculator').classList.toggle('show'); }
function calcInput(v) { calcExpr += v; document.getElementById('calcDisplay').textContent = calcExpr; }
function calcClear() { calcExpr = ''; document.getElementById('calcDisplay').textContent = '0'; }
// Safe arithmetic evaluator
// BUG-024 fix: support negative numbers and parentheses. The old shunting-yard
// parser didn't support unary minus, failing on expressions like "5 * -2".
// Instead, strictly whitelist allowed characters and use Function().
function calcEval() {
    try { calcExpr = String(safeEval(calcExpr)); } catch(e) { calcExpr = 'Error'; }
    document.getElementById('calcDisplay').textContent = calcExpr;
}
function safeEval(expr) {
    // Only allow digits, operators, parens, and spaces
    if (!/^[0-9+\-*/().\s]+$/.test(expr)) throw new Error('bad');
    // Using Function is safe here because we guarantee no letters/variables exist in expr
    const result = new Function('return (' + expr + ')')();
    if (!isFinite(result)) throw new Error('bad');
    return Math.round(result * 1e10) / 1e10;
}

// Scratch
let scratchPages = [{ mode: 'pen', text: '', drawing: null }];
let currentPage = 0;
let scratchMode = 'pen';
let isDrawing = false;
let canvas, ctx;

function toggleScratch() { 
    document.getElementById('scratch').classList.toggle('show'); 
    if (!canvas) initCanvas();
}

function initCanvas() {
    canvas = document.getElementById('scratchCanvas');
    ctx = canvas.getContext('2d');
    ctx.strokeStyle = '#fff';
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    
    canvas.addEventListener('mousedown', startDraw);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDraw);
    canvas.addEventListener('mouseout', stopDraw);

    // BUG-011 fix: add touch support so the scratch pad pen mode works on
    // mobile/tablet (iOS/Android). Without these, finger gestures scroll
    // the page instead of drawing.
    canvas.addEventListener('touchstart', function(e) {
        e.preventDefault();
        startDraw({ clientX: e.touches[0].clientX, clientY: e.touches[0].clientY });
    }, { passive: false });
    canvas.addEventListener('touchmove', function(e) {
        e.preventDefault();
        draw({ clientX: e.touches[0].clientX, clientY: e.touches[0].clientY });
    }, { passive: false });
    canvas.addEventListener('touchend', function(e) {
        e.preventDefault();
        stopDraw();
    });
}

function switchMode(mode) {
    scratchMode = mode;
    scratchPages[currentPage].mode = mode;
    
    document.getElementById('penBtn').classList.toggle('active', mode === 'pen');
    document.getElementById('typeBtn').classList.toggle('active', mode === 'type');
    document.getElementById('eraseBtn').style.display = mode === 'pen' ? 'inline-block' : 'none';
    document.getElementById('scratchText').style.display = mode === 'type' ? 'block' : 'none';
    document.getElementById('scratchCanvas').style.display = mode === 'pen' ? 'block' : 'none';
    
    loadPage();
}

function startDraw(e) {
    if (scratchMode !== 'pen') return;
    isDrawing = true;
    const rect = canvas.getBoundingClientRect();
    ctx.beginPath();
    ctx.moveTo(e.clientX - rect.left, e.clientY - rect.top);
}

function draw(e) {
    if (!isDrawing || scratchMode !== 'pen') return;
    const rect = canvas.getBoundingClientRect();
    ctx.lineTo(e.clientX - rect.left, e.clientY - rect.top);
    ctx.stroke();
}

function stopDraw() {
    if (isDrawing) {
        isDrawing = false;
        scratchPages[currentPage].drawing = canvas.toDataURL();
    }
}

function eraseScratch() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    scratchPages[currentPage].drawing = null;
}

function addScratchPage() {
    savePage();
    scratchPages.push({ mode: 'pen', text: '', drawing: null });
    currentPage = scratchPages.length - 1;
    loadPage();
    updatePageInfo();
}

function prevScratchPage() {
    if (currentPage > 0) {
        savePage();
        currentPage--;
        loadPage();
        updatePageInfo();
    }
}

function nextScratchPage() {
    if (currentPage < scratchPages.length - 1) {
        savePage();
        currentPage++;
        loadPage();
        updatePageInfo();
    }
}

function savePage() {
    if (scratchMode === 'type') {
        scratchPages[currentPage].text = document.getElementById('scratchText').value;
    } else {
        scratchPages[currentPage].drawing = canvas.toDataURL();
    }
}

function loadPage() {
    const page = scratchPages[currentPage];
    scratchMode = page.mode;
    
    document.getElementById('penBtn').classList.toggle('active', scratchMode === 'pen');
    document.getElementById('typeBtn').classList.toggle('active', scratchMode === 'type');
    document.getElementById('eraseBtn').style.display = scratchMode === 'pen' ? 'inline-block' : 'none';
    document.getElementById('scratchText').style.display = scratchMode === 'type' ? 'block' : 'none';
    document.getElementById('scratchCanvas').style.display = scratchMode === 'pen' ? 'block' : 'none';
    
    if (scratchMode === 'type') {
        document.getElementById('scratchText').value = page.text || '';
    } else {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        if (page.drawing) {
            const img = new Image();
            img.onload = () => ctx.drawImage(img, 0, 0);
            img.src = page.drawing;
        }
    }
}

function updatePageInfo() {
    document.getElementById('pageInfo').textContent = `Page ${currentPage + 1} / ${scratchPages.length}`;
}

document.addEventListener('DOMContentLoaded', init);
</script>
</body>
</html>
