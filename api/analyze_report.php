<?php
/**
 * API endpoint: Analyze a student's test report using Gemini AI.
 * Returns a comprehensive AI-generated performance assessment.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/gemini.php';
$user = requireAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$attemptId = (int)($input['attempt_id'] ?? 0);

if (!$attemptId) {
    echo json_encode(['error' => 'Invalid attempt ID']);
    exit;
}

// 1. Load attempt (only if it belongs to this user)
$attemptData = supabaseRest('attempts?id=eq.' . $attemptId . '&student_id=eq.' . $user['id'] . '&status=eq.completed&limit=1');
$attempt = $attemptData[0] ?? null;
if (!$attempt) {
    echo json_encode(['error' => 'Attempt not found']);
    exit;
}

// 2. Load test info
$testInfo = [];
if (!empty($attempt['test_id'])) {
    $testData = supabaseRest('tests?id=eq.' . $attempt['test_id'] . '&select=title,mode,total_questions,duration_minutes&limit=1');
    $testInfo = $testData[0] ?? [];
}

// 3. Load answers with question details
$answers = supabaseRest('attempt_answers?attempt_id=eq.' . $attemptId . '&select=*&order=question_id') ?? [];
$qIds = array_column($answers, 'question_id');
$questionsData = [];
if ($qIds) {
    $allQ = supabaseRest('questions?id=in.(' . implode(',', $qIds) . ')&select=id,question_text,subject,difficulty,marks,negative_marks') ?? [];
    foreach ($allQ as $q) $questionsData[$q['id']] = $q;
}

// 4. Build subject-wise breakdown
$subjectBreakdown = [];
$difficultyBreakdown = [];
$totalCorrect = 0;
$totalIncorrect = 0;
$totalSkipped = 0;
$totalTimeSec = 0;
$questionDetails = [];

foreach ($answers as $a) {
    $q = $questionsData[$a['question_id']] ?? [];
    $sub = $q['subject'] ?? 'General';
    $diff = $q['difficulty'] ?? 'medium';
    $timeSec = (int)($a['time_spent_seconds'] ?? 0);
    $totalTimeSec += $timeSec;
    
    if (!isset($subjectBreakdown[$sub])) {
        $subjectBreakdown[$sub] = ['correct' => 0, 'incorrect' => 0, 'skipped' => 0, 'total' => 0, 'time' => 0];
    }
    $subjectBreakdown[$sub]['total']++;
    $subjectBreakdown[$sub]['time'] += $timeSec;
    
    if (!isset($difficultyBreakdown[$diff])) {
        $difficultyBreakdown[$diff] = ['correct' => 0, 'total' => 0];
    }
    $difficultyBreakdown[$diff]['total']++;
    
    if ($a['is_correct'] === true) {
        $totalCorrect++;
        $subjectBreakdown[$sub]['correct']++;
        $difficultyBreakdown[$diff]['correct']++;
    } elseif ($a['is_correct'] === false) {
        $totalIncorrect++;
        $subjectBreakdown[$sub]['incorrect']++;
    } else {
        $totalSkipped++;
        $subjectBreakdown[$sub]['skipped']++;
    }
    
    $questionDetails[] = [
        'q' => substr($q['question_text'] ?? '', 0, 60),
        'subject' => $sub,
        'difficulty' => $diff,
        'correct' => $a['is_correct'] === true,
        'skipped' => $a['is_correct'] === null,
        'time' => $timeSec
    ];
}

// 5. Load previous attempts for comparison (up to 5 most recent)
$previousAttempts = [];
if (!empty($attempt['test_id'])) {
    $prevData = supabaseRest('attempts?student_id=eq.' . $user['id'] . '&test_id=eq.' . $attempt['test_id']
        . '&status=eq.completed&order=created_at.desc&limit=5&select=id,score,total_marks,correct_count,incorrect_count,skipped_count,time_spent_seconds,created_at');
} else {
    $prevData = supabaseRest('attempts?student_id=eq.' . $user['id']
        . '&status=eq.completed&order=created_at.desc&limit=5&select=id,score,total_marks,correct_count,incorrect_count,skipped_count,time_spent_seconds,created_at,mode');
}
if (!empty($prevData)) {
    foreach ($prevData as $p) {
        $pct = $p['total_marks'] > 0 ? round(($p['score'] / $p['total_marks']) * 100, 1) : 0;
        $previousAttempts[] = [
            'id' => $p['id'],
            'score' => $p['score'] . '/' . $p['total_marks'],
            'percent' => $pct . '%',
            'correct' => $p['correct_count'] ?? 0,
            'incorrect' => $p['incorrect_count'] ?? 0,
            'skipped' => $p['skipped_count'] ?? 0,
            'time' => round(($p['time_spent_seconds'] ?? 0) / 60, 1) . ' min',
            'date' => date('d M Y', strtotime($p['created_at'])),
            'is_current' => $p['id'] === $attemptId
        ];
    }
}

// 6. Build comprehensive prompt
$scorePercent = $attempt['total_marks'] > 0 ? round(($attempt['score'] / $attempt['total_marks']) * 100, 1) : 0;
$testTitle = $testInfo['title'] ?? ($attempt['mode'] ?? 'Practice Test');

$reportData = "=== TEST REPORT DATA ===\n";
$reportData .= "Test: $testTitle\n";
$reportData .= "Score: {$attempt['score']}/{$attempt['total_marks']} ($scorePercent%)\n";
$reportData .= "Correct: $totalCorrect | Incorrect: $totalIncorrect | Skipped: $totalSkipped\n";
$reportData .= "Total time: " . round($totalTimeSec / 60, 1) . " minutes\n";
$reportData .= "Avg time per question: " . (count($answers) > 0 ? round($totalTimeSec / count($answers)) : 0) . " seconds\n";
$reportData .= "Tab switches: " . ($attempt['tab_switches'] ?? 0) . "\n\n";

$reportData .= "=== SUBJECT-WISE BREAKDOWN ===\n";
foreach ($subjectBreakdown as $sub => $data) {
    $pct = $data['total'] > 0 ? round(($data['correct'] / $data['total']) * 100, 1) : 0;
    $avgTime = $data['total'] > 0 ? round($data['time'] / $data['total']) : 0;
    $reportData .= "$sub: {$data['correct']}/{$data['total']} correct ($pct%), {$data['incorrect']} wrong, {$data['skipped']} skipped, avg {$avgTime}s/question\n";
}

$reportData .= "\n=== DIFFICULTY BREAKDOWN ===\n";
foreach ($difficultyBreakdown as $diff => $data) {
    $pct = $data['total'] > 0 ? round(($data['correct'] / $data['total']) * 100, 1) : 0;
    $reportData .= "$diff: {$data['correct']}/{$data['total']} correct ($pct%)\n";
}

if (count($previousAttempts) > 1) {
    $reportData .= "\n=== PREVIOUS ATTEMPTS (most recent first) ===\n";
    foreach ($previousAttempts as $pa) {
        $marker = $pa['is_current'] ? ' ← CURRENT' : '';
        $reportData .= "- {$pa['date']}: {$pa['score']} ({$pa['percent']}), Correct: {$pa['correct']}, Incorrect: {$pa['incorrect']}, Skipped: {$pa['skipped']}, Time: {$pa['time']}$marker\n";
    }
}

$sysInstruction = "You are a professional exam coach and performance analyst for the Gujarat DDCET exam (Diploma-to-Degree Common Entrance Test). Analyze the student's test report data and provide a comprehensive, encouraging, and actionable assessment. 

Structure your response EXACTLY like this with these section headers (use ## for headers). DO NOT use emojis in the headers.

## Overall Performance
A 2-3 sentence summary of how they did.

## Strengths
Bullet points of what they did well (subjects, speed, accuracy). Always use hyphens (-) for bullet points, not asterisks (*).

## Areas for Improvement
Bullet points of weak areas with specific advice. Always use hyphens (-) for bullet points.

## Subject Analysis
Brief analysis of each subject's performance.

## Time Management
Analysis of their time usage — too fast, too slow, or well-paced.

## Progress Comparison
(Only include this if multiple attempts are available) Compare their attempts and explain the trend.

## Recommendations
3-5 specific, actionable study tips based on their weak areas. Always use hyphens (-) for bullet points.

## Final Verdict
One motivational closing line with their overall grade (A+/A/B/C/D).

Use Markdown formatting. Be specific with numbers from the data. Be encouraging but honest. DO NOT use emojis.";

$prompt = "Analyze this DDCET test report and provide a detailed assessment:\n\n$reportData";

// 7. Call Gemini
$analysis = callGemini($prompt, $sysInstruction);

if (str_starts_with($analysis, 'Error:')) {
    echo json_encode(['error' => $analysis]);
    exit;
}

echo json_encode(['success' => true, 'analysis' => $analysis]);
