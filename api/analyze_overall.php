<?php
/**
 * API endpoint: Analyze a student's overall performance using Gemini AI.
 * Returns a comprehensive AI-generated performance assessment based on historical data.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/gemini.php';
$user = requireAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// 1. Fetch up to 20 most recent completed attempts
$attempts = supabaseRest('attempts?student_id=eq.' . $user['id'] . '&status=eq.completed&order=completed_at.desc&limit=20&select=id,score,total_marks,completed_at,time_spent_seconds,mode') ?? [];

if (count($attempts) === 0) {
    echo json_encode(['error' => 'Not enough data. Take a few tests first to generate an overall report.']);
    exit;
}

// 2. Fetch answers for subject and difficulty analysis (limit to recent attempts to avoid massive queries)
$recentIds = array_column(array_slice($attempts, 0, 10), 'id');
$subjectBreakdown = [];
$totalCorrect = 0;
$totalIncorrect = 0;
$totalSkipped = 0;
$totalTimeSec = 0;

if ($recentIds) {
    $answers = supabaseRest('attempt_answers?attempt_id=in.(' . implode(',', $recentIds) . ')&select=question_id,is_correct,time_spent_seconds') ?? [];
    $qIds = array_filter(array_column($answers, 'question_id'));
    
    $questionsData = [];
    if ($qIds) {
        // Chunk query to avoid URI too long
        $qChunks = array_chunk(array_unique($qIds), 100);
        foreach ($qChunks as $chunk) {
            $allQ = supabaseRest('questions?id=in.(' . implode(',', $chunk) . ')&select=id,subject') ?? [];
            foreach ($allQ as $q) $questionsData[$q['id']] = $q['subject'] ?: 'General';
        }
    }

    foreach ($answers as $a) {
        $sub = $questionsData[$a['question_id']] ?? 'General';
        $timeSec = (int)($a['time_spent_seconds'] ?? 0);
        $totalTimeSec += $timeSec;
        
        if (!isset($subjectBreakdown[$sub])) {
            $subjectBreakdown[$sub] = ['correct' => 0, 'total' => 0, 'time' => 0];
        }
        $subjectBreakdown[$sub]['total']++;
        $subjectBreakdown[$sub]['time'] += $timeSec;
        
        if ($a['is_correct'] === true) {
            $totalCorrect++;
            $subjectBreakdown[$sub]['correct']++;
        } elseif ($a['is_correct'] === false) {
            $totalIncorrect++;
        } else {
            $totalSkipped++;
        }
    }
}

// 3. Prepare summary strings
$totalAttempts = count($attempts);
$avgScorePct = 0;
$totalPct = 0;
$trendStr = "";

foreach ($attempts as $idx => $a) {
    $pct = $a['total_marks'] > 0 ? round(($a['score'] / $a['total_marks']) * 100, 1) : 0;
    $totalPct += $pct;
    // Log the 5 most recent for the trend
    if ($idx < 5) {
        $date = date('d M Y', strtotime($a['completed_at']));
        $mode = $a['mode'] ?: 'Practice';
        $trendStr .= "- $date ($mode): {$a['score']}/{$a['total_marks']} ($pct%)\n";
    }
}
$avgScorePct = round($totalPct / $totalAttempts, 1);


$reportData = "=== OVERALL PERFORMANCE DATA ===\n";
$reportData .= "Total Tests Taken: $totalAttempts (up to 20 analyzed)\n";
$reportData .= "Average Score (All Tests): $avgScorePct%\n\n";

if ($recentIds) {
    $reportData .= "=== RECENT ACTIVITY (Last 10 Tests) ===\n";
    $reportData .= "Questions Answered: " . count($answers) . "\n";
    $reportData .= "Total Correct: $totalCorrect | Incorrect: $totalIncorrect | Skipped: $totalSkipped\n";
    
    $reportData .= "\n=== SUBJECT MASTERY (Recent Tests) ===\n";
    foreach ($subjectBreakdown as $sub => $data) {
        $pct = $data['total'] > 0 ? round(($data['correct'] / $data['total']) * 100, 1) : 0;
        $avgTime = $data['total'] > 0 ? round($data['time'] / $data['total']) : 0;
        $reportData .= "$sub: {$data['correct']}/{$data['total']} correct ($pct%), avg {$avgTime}s/question\n";
    }
}

$reportData .= "\n=== RECENT TREND (Last 5 Tests) ===\n";
$reportData .= $trendStr;

$sysInstruction = "You are a professional exam coach and performance analyst for the Gujarat DDCET exam. Analyze the student's OVERALL test history and provide a comprehensive, encouraging, and actionable assessment. 

Structure your response EXACTLY like this with these section headers (use ## for headers). DO NOT use emojis in the headers.

## Overall Trajectory
A 2-3 sentence summary of their overall progress and average score trend.

## Core Strengths
Bullet points of what subjects or habits they are doing well in. Always use hyphens (-) for bullet points, not asterisks (*).

## Vulnerabilities
Bullet points of weak areas or bad habits (e.g., high skip rate, poor time management). Always use hyphens (-) for bullet points.

## Subject Mastery
Brief analysis of their performance across different subjects based on the data.

## Exam Readiness
An assessment of how prepared they look for the real DDCET exam based on their scores and volume of practice.

## Action Plan
3-5 specific, actionable study tips for the next 2 weeks. Always use hyphens (-) for bullet points.

## Final Verdict
One motivational closing line with an overall readiness grade (A+/A/B/C/D).

Use Markdown formatting. Be specific with numbers from the data. Be encouraging but honest. DO NOT use emojis.";

$prompt = "Analyze this overall DDCET student profile and provide a detailed assessment:\n\n$reportData";

// 4. Call Gemini
$analysis = callGemini($prompt, $sysInstruction);

if (str_starts_with($analysis, 'Error:')) {
    echo json_encode(['error' => $analysis]);
    exit;
}

echo json_encode(['success' => true, 'analysis' => $analysis]);
