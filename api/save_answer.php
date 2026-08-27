<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$user = currentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'not_logged_in']);
    exit;
}
requireCsrf();

$data = json_decode(file_get_contents('php://input'), true);
$attemptId = (int) ($data['attempt_id'] ?? 0);

// Verify ownership — also pull timing fields so we can enforce the clock.
$attempts = supabaseRest('attempts?id=eq.' . $attemptId . '&student_id=eq.' . $user['id'] . '&status=eq.in_progress&select=id,started_at,mode,test_id,tab_switches&limit=1');
$attempt = $attempts[0] ?? null;
if (!$attempt) {
    http_response_code(403);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

// Log tab switch — increment SERVER-SIDE. The client number is not trusted;
// it can only ask us to add one. This makes tab_switches a real signal.
if (!empty($data['log_tab_switch'])) {
    $res = supabaseRpc('increment_tab_switches', ['p_attempt_id' => $attemptId]);
    echo json_encode(['ok' => true, 'tab_switches' => $res]);
    exit;
}

$questionId = (int) ($data['question_id'] ?? 0);
$optionId = !empty($data['option_id']) ? (int) $data['option_id'] : null;
$isFlagged = !empty($data['is_flagged']);
$timeSpent = max(0, (int) ($data['time_spent'] ?? 0));

// Constrain the question to one that actually belongs to this attempt — a user
// can't inject answers for arbitrary questions.
$owns = supabaseRest('attempt_answers?attempt_id=eq.' . $attemptId . '&question_id=eq.' . $questionId . '&select=id&limit=1');
if (empty($owns)) {
    http_response_code(403);
    echo json_encode(['error' => 'question_not_in_attempt']);
    exit;
}

// If an option is supplied, it must be a valid option of that question.
if ($optionId !== null) {
    $validOpt = supabaseRest('options?id=eq.' . $optionId . '&question_id=eq.' . $questionId . '&select=id&limit=1');
    if (empty($validOpt)) {
        http_response_code(422);
        echo json_encode(['error' => 'invalid_option']);
        exit;
    }
}

$status = $optionId ? 'answered' : ($isFlagged ? 'flagged' : 'skipped');

supabaseRest(
    'attempt_answers?attempt_id=eq.' . $attemptId . '&question_id=eq.' . $questionId,
    'PATCH',
    [
        'selected_option_id' => $optionId,
        'is_flagged' => $isFlagged,
        'time_spent_seconds' => $timeSpent,
        'status' => $status,
    ]
);

echo json_encode(['ok' => true]);
