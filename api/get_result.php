<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$user = currentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'not_logged_in']);
    exit;
}

$attemptId = (int) ($_GET['attempt_id'] ?? 0);
if (!$attemptId) {
    echo json_encode(['error' => 'missing_attempt_id']);
    exit;
}

// Load attempt
$attemptData = supabaseRest('attempts?id=eq.' . $attemptId . '&student_id=eq.' . $user['id'] . '&status=eq.completed&limit=1');
$attempt = $attemptData[0] ?? null;
if (!$attempt) {
    echo json_encode(['error' => 'not_found']);
    exit;
}

// Load answers
$answers = supabaseRest('attempt_answers?attempt_id=eq.' . $attemptId . '&select=*&order=question_id') ?? [];

// Load test info
$testInfo = [];
if (!empty($attempt['test_id'])) {
    $testData = supabaseRest('tests?id=eq.' . $attempt['test_id'] . '&select=title,mode,total_questions,duration_minutes,series_label,is_scheduled,is_free,opens_at,closes_at,results_at,results_published&limit=1');
    $testInfo = $testData[0] ?? [];
}

$poolMode = $attempt['mode'] ?? null;
$attempt['title'] = $testInfo['title'] ?? modeLabel($poolMode);
$attempt['mode'] = $testInfo['mode'] ?? ($poolMode ?: 'practice');
$attempt['total_questions'] = $testInfo['total_questions'] ?? count($answers ?? []);
$attempt['duration_minutes'] = $testInfo['duration_minutes'] ?? 0;

// AIR logic
$isScheduledMock = !empty($testInfo['is_scheduled']);
$mockResultsOut  = $isScheduledMock ? mockResultsOut($testInfo) : true;
$air = null;
if ($isScheduledMock && $mockResultsOut && !empty($attempt['test_id'])) {
    $air = mockAIR((int)$attempt['test_id'], (float)$attempt['score']);
}

// Question data
$qIds = array_column($answers, 'question_id');
$questionsData = [];
if ($qIds) {
    $allQ = supabaseRest('questions?id=in.(' . implode(',', $qIds) . ')&select=*') ?? [];
    foreach ($allQ as $q) $questionsData[$q['id']] = $q;
}

// Options data
$optionsMap = [];
if ($qIds) {
    $allOpts = supabaseRest('options?question_id=in.(' . implode(',', $qIds) . ')&select=*&order=position') ?? [];
    foreach ($allOpts as $o) $optionsMap[$o['question_id']][] = $o;
}

$lang = $attempt['language'] ?? 'en';
$formattedAnswers = [];
foreach ($answers as $a) {
    $q = $questionsData[$a['question_id']] ?? [];
    
    $text = $lang === 'gu' && !empty($q['question_text_gu']) ? $q['question_text_gu'] : ($q['question_text'] ?? '');
    $exp = $lang === 'gu' && !empty($q['explanation_gu']) ? $q['explanation_gu'] : ($q['explanation'] ?? '');
    
    $opts = $optionsMap[$a['question_id']] ?? [];
    $formattedOpts = [];
    foreach ($opts as $opt) {
        $optText = $lang === 'gu' && !empty($opt['option_text_gu']) ? $opt['option_text_gu'] : ($opt['option_text'] ?? '');
        $formattedOpts[] = [
            'id' => $opt['id'],
            'text' => $optText,
            'image' => $opt['option_image'] ?? null,
            'is_correct' => $opt['is_correct']
        ];
    }
    
    $formattedAnswers[] = [
        'question_id' => $a['question_id'],
        'question_text' => $text,
        'question_image' => $q['question_image'] ?? null,
        'subject' => $q['subject'] ?? 'General',
        'explanation' => $exp,
        'explanation_image' => $q['explanation_image'] ?? null,
        'difficulty' => $q['difficulty'] ?? '',
        'marks' => $q['marks'] ?? 1,
        'negative_marks' => $q['negative_marks'] ?? 0,
        'is_correct' => $a['is_correct'],
        'selected_option_id' => $a['selected_option_id'],
        'time_spent' => (int) ($a['time_spent_seconds'] ?? 0),
        'options' => $formattedOpts
    ];
}

// Subject breakdown
$subjectBreakdown = [];
foreach ($formattedAnswers as $a) {
    $sub = $a['subject'];
    if (!isset($subjectBreakdown[$sub])) {
        $subjectBreakdown[$sub] = ['correct' => 0, 'total' => 0, 'time' => 0];
    }
    $subjectBreakdown[$sub]['total']++;
    $subjectBreakdown[$sub]['time'] += $a['time_spent'];
    if ($a['is_correct']) $subjectBreakdown[$sub]['correct']++;
}

$scorePercent = $attempt['total_marks'] > 0 ? round(($attempt['score'] / $attempt['total_marks']) * 100) : 0;
$avgTimePerQ = count($answers) > 0 ? round($attempt['time_spent_seconds'] / count($answers)) : 0;

$subscription = getSubscription();
$isPro = ($subscription['plan'] ?? 'free') === 'pro';
$solutionsLocked = $isScheduledMock && !$isPro;

echo json_encode([
    'ok' => true,
    'attempt' => $attempt,
    'air' => $air,
    'subject_breakdown' => $subjectBreakdown,
    'score_percent' => $scorePercent,
    'avg_time_per_q' => $avgTimePerQ,
    'solutions_locked' => $solutionsLocked,
    'is_scheduled_mock' => $isScheduledMock,
    'mock_results_out' => $mockResultsOut,
    'answers' => $solutionsLocked ? [] : $formattedAnswers
]);
