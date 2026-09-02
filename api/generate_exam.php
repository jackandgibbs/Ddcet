<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$user = currentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'not_logged_in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$testId = (int) ($data['test_id'] ?? 0);
$mode = $data['mode'] ?? '';
$subject = $data['subject'] ?? '';
$chapter = $data['chapter'] ?? '';
$forceNew = !empty($data['force_new']);
$resumeAttemptId = (int) ($data['attempt_id'] ?? 0);

if ($forceNew && !empty($mode)) {
    supabaseRest('attempts?student_id=eq.' . $user['id'] . '&test_id=is.null&mode=eq.' . urlencode($mode) . '&status=eq.in_progress', 'DELETE');
}

// 1. If we are just fetching an existing attempt (e.g. they tapped resume or we just generated it)
if ($resumeAttemptId) {
    $existingAttempt = supabaseRest('attempts?id=eq.' . $resumeAttemptId . '&student_id=eq.' . $user['id'] . '&status=eq.in_progress&select=*&limit=1');
    $attempt = $existingAttempt[0] ?? null;
    if (!$attempt) {
        echo json_encode(['error' => 'not_found']);
        exit;
    }

    $aa = supabaseRest('attempt_answers?attempt_id=eq.' . $resumeAttemptId . '&select=question_id,selected_option_id,status,is_flagged&order=position,id') ?? [];
    $qIds = array_column($aa, 'question_id');
    
    if (empty($qIds)) {
        supabaseRest('attempts?id=eq.' . $resumeAttemptId, 'DELETE');
        echo json_encode(['error' => 'empty_attempt']);
        exit;
    }

    $questions = orderRowsByIds(supabaseRest('questions?id=in.(' . implode(',', $qIds) . ')&select=*') ?? [], $qIds);
    $optionsMap = [];
    $options = supabaseRest('options?question_id=in.(' . implode(',', $qIds) . ')&select=*&order=position') ?? [];
    foreach ($options as $opt) {
        if (strpos($opt['option_text'], 'Placeholder') === false) {
            $optionsMap[$opt['question_id']][] = $opt;
        }
    }

    $outQs = [];
    foreach ($questions as $idx => $q) {
        // Map the selected option from attempt_answers
        $selectedOpt = null;
        $status = 'unseen';
        $flagged = false;
        if (isset($aa[$idx])) {
            $selectedOpt = $aa[$idx]['selected_option_id'];
            $status = $aa[$idx]['status'] ?? 'unseen';
            $flagged = !empty($aa[$idx]['is_flagged']);
        }
        
        $outQs[] = [
            'id' => $q['id'],
            'text' => $q['question_text'],
            'text_gu' => $q['question_text_gu'] ?? null,
            'image' => $q['question_image'] ?? null,
            'marks' => $q['marks'] ?? 1,
            'negative' => (float)($q['negative_marks'] ?? 0),
            'selected_option_id' => $selectedOpt,
            'status' => $status,
            'is_flagged' => $flagged,
            'options' => array_map(fn($o) => [
                'id' => $o['id'],
                'text' => $o['option_text'],
                'text_gu' => $o['option_text_gu'] ?? null,
                'image' => $o['option_image'] ?? null
            ], $optionsMap[$q['id']] ?? [])
        ];
    }
    
    // Add mode duration logic from exam.php
    $poolConfigs = [
        'full_mock' => ['total' => 100, 'time' => 150],
        'be01_paper' => ['total' => 50, 'time' => 75],
        'be02_paper' => ['total' => 50, 'time' => 75],
        'rapid_fire' => ['total' => 30, 'time' => 30],
        'subject_wise' => ['total' => 30, 'time' => 30],
        'topic_wise' => ['total' => 20, 'time' => 20],
        'daily_challenge' => ['total' => 10, 'time' => 10],
        'weekly_challenge' => ['total' => 50, 'time' => 60],
        'revision' => ['total' => 20, 'time' => 20],
        'previous_year' => ['total' => 100, 'time' => 150],
    ];
    $isPoolMode = empty($attempt['test_id']) && empty($attempt['challenge_id']);
    $m = $attempt['mode'];
    if ($isPoolMode && isset($poolConfigs[$m])) {
        $attempt['duration_minutes'] = $poolConfigs[$m]['time'];
    } elseif ($attempt['test_id']) {
        $testData = supabaseRest('tests?id=eq.' . $attempt['test_id'] . '&select=duration_minutes&limit=1');
        $attempt['duration_minutes'] = $testData[0]['duration_minutes'] ?? 60;
    } else {
        $attempt['duration_minutes'] = max(10, count($questions) * 1.5);
    }

    echo json_encode([
        'ok' => true,
        'attempt' => $attempt,
        'questions' => $outQs
    ]);
    exit;
}

// Mode-based pool config 
$poolConfigs = [
    'full_mock' => ['total' => 100, 'time' => 150, 'subjects' => ddcetPoolDistribution('full_mock')],
    'be01_paper' => ['total' => 50, 'time' => 75, 'subjects' => ddcetPoolDistribution('be01_paper')],
    'be02_paper' => ['total' => 50, 'time' => 75, 'subjects' => ddcetPoolDistribution('be02_paper')],
    'rapid_fire' => ['total' => 30, 'time' => 30, 'subjects' => null],
    'subject_wise' => ['total' => 30, 'time' => 30, 'subjects' => null],
    'topic_wise' => ['total' => 20, 'time' => 20, 'subjects' => null],
    'daily_challenge' => ['total' => 10, 'time' => 10, 'subjects' => null],
    'weekly_challenge' => ['total' => 50, 'time' => 60, 'subjects' => null],
    'revision' => ['total' => 20, 'time' => 20, 'subjects' => null],
    'previous_year' => ['total' => 100, 'time' => 150, 'subjects' => null],
];

// GENERATION LOGIC
if ($testId) {
    $testData = supabaseRest('tests?id=eq.' . $testId . '&is_published=eq.true&limit=1');
    $test = $testData[0] ?? null;
    if (!$test) { echo json_encode(['error' => 'test_not_found']); exit; }
    
    if (!empty($test['is_scheduled'])) {
        $doneAttempt = supabaseRest('attempts?student_id=eq.' . $user['id'] . '&test_id=eq.' . $testId . '&status=eq.completed&select=id&order=completed_at.desc&limit=1');
        if (!empty($doneAttempt)) {
            echo json_encode(['error' => 'already_completed', 'attempt_id' => $doneAttempt[0]['id']]);
            exit;
        }
    }
    
    $mode = $test['mode'] ?? '';
} elseif ($mode && isset($poolConfigs[$mode])) {
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
    echo json_encode(['error' => 'invalid_mode']); exit;
}

// Sub Check
$subscription = getSubscription();
$plan = $subscription['plan'] ?? 'free';
$planHierarchy = ['free' => 0, 'basic' => 1, 'pro' => 2];
$minPlanLevel = $planHierarchy[$test['min_plan'] ?? 'free'] ?? 0;
$freeMockBypass = (!$testId && $mode === 'full_mock' && hasFreeMock($user));
if (($planHierarchy[$plan] ?? 0) < $minPlanLevel && empty($test['is_free']) && !$freeMockBypass) {
    echo json_encode(['error' => 'needs_subscription', 'need' => $test['min_plan']]);
    exit;
}

// Generate
if ($testId) {
    $questions = supabaseRest('questions?test_id=eq.' . $testId . '&select=*&order=position') ?? [];
} else {
    $config = $poolConfigs[$mode];
    $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
    $seenData = supabaseRest('attempt_answers?select=question_id,attempts!inner(student_id,started_at)&attempts.student_id=eq.' . $user['id'] . '&attempts.started_at=gte.' . $thirtyDaysAgo) ?? [];
    $seenIds = array_unique(array_column($seenData, 'question_id'));
    
    $weakSubjects = []; // Omitted adaptive logic to match previous fallback behavior
    
    $questions = generateTestQuestions($mode, $config['total'], $config['subjects'], $seenIds, $weakSubjects, $subject, $chapter);
}

if (empty($questions)) {
    echo json_encode(['error' => 'no_questions']); exit;
}

// Insert Attempt
$attemptData = [
    'student_id' => $user['id'],
    'mode' => $mode,
    'total_marks' => count($questions),
    'started_at' => date('c'),
    'status' => 'in_progress',
];
if ($testId) $attemptData['test_id'] = $testId;

$attemptRes = supabaseRest('attempts', 'POST', $attemptData);
if (empty($attemptRes[0]['id'])) {
    echo json_encode(['error' => 'failed_to_create_attempt']); exit;
}
$attemptId = $attemptRes[0]['id'];

// Insert Answers
$inserts = [];
foreach ($questions as $idx => $q) {
    $inserts[] = [
        'attempt_id' => $attemptId,
        'question_id' => $q['id'],
        'position' => $idx + 1,
        'status' => 'unseen'
    ];
}

$chunked = array_chunk($inserts, 50);
foreach ($chunked as $chunk) {
    supabaseRest('attempt_answers', 'POST', $chunk);
}

echo json_encode(['ok' => true, 'attempt_id' => $attemptId]);
