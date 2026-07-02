<?php
// Mark the caller "ready" in the lobby. When BOTH players are ready, create the
// two attempts from the pinned question set, set started_at, and flip to 'live'.
// Both clients poll, so the go-live transition is guarded to run exactly once.
require_once __DIR__ . '/../config.php';
$user = requireAuth();
header('Content-Type: application/json');
requireCsrf();

$data = json_decode(file_get_contents('php://input'), true);
$challengeId = (int) ($data['challenge_id'] ?? 0);
if (!$challengeId) { echo json_encode(['error' => 'Bad request.']); exit; }

// challengeLoad() is always fresh — this flow does read-after-write and races the
// other player's concurrent call, so every read must reflect the latest DB state.
$ch = challengeLoad($challengeId);
if (!$ch) { http_response_code(404); echo json_encode(['error' => 'Not found.']); exit; }
$role = challengeRole($ch, (int) $user['id']);
if (!$role) { http_response_code(403); echo json_encode(['error' => 'Not your challenge.']); exit; }

// Helper: return the caller's attempt id from a (fresh) challenge row.
$myAttemptOf = fn($c) => (int) ($role === 'challenger' ? $c['challenger_attempt_id'] : $c['opponent_attempt_id']);

$status = challengeResolveTimeouts($ch);

// Already live — return my attempt id once it's been written (the other player
// may have claimed go-live and not yet stored ids; client keeps polling on 0).
if ($status === 'live') {
    echo json_encode(['ok' => true, 'status' => 'live', 'attempt_id' => $myAttemptOf($ch)]);
    exit;
}
if ($status !== 'accepted') { echo json_encode(['error' => 'Challenge not in lobby.']); exit; }

// Both players must be Pro right up to go-live (a sub could have lapsed).
if (!isSubscribed('pro')) { echo json_encode(['error' => 'needs_pro']); exit; }

// Mark this side ready, then RE-READ so bothReady reflects the other player's
// concurrent write (avoids a simultaneous-ready deadlock where each call only
// sees its own flag set and neither triggers the start).
$readyCol = $role . '_ready';
if (empty($ch[$readyCol])) {
    supabaseRest('challenges?id=eq.' . $challengeId, 'PATCH', [$readyCol => true]);
}
$ch = challengeLoad($challengeId) ?: $ch;

if (empty($ch['challenger_ready']) || empty($ch['opponent_ready'])) {
    echo json_encode(['ok' => true, 'status' => 'waiting', 'challenger_ready' => !empty($ch['challenger_ready']), 'opponent_ready' => !empty($ch['opponent_ready'])]);
    exit;
}

// Both ready. If the other call already started the duel, just return my id.
if (($ch['status'] ?? '') === 'live') {
    echo json_encode(['ok' => true, 'status' => 'live', 'attempt_id' => $myAttemptOf($ch)]);
    exit;
}

// Atomically CLAIM the go-live transition: this PATCH only matches while status
// is still 'accepted', so of two concurrent callers exactly one gets a row back
// and becomes the attempt creator; the other falls through to polling.
$claim = supabaseRest('challenges?id=eq.' . $challengeId . '&status=eq.accepted', 'PATCH', ['status' => 'live']);
if (empty($claim)) {
    // Lost the race — the winner is creating attempts. Return my id (likely 0
    // for a moment); the lobby keeps polling challenge_state until it appears.
    echo json_encode(['ok' => true, 'status' => 'live', 'attempt_id' => $myAttemptOf(challengeLoad($challengeId) ?: $ch)]);
    exit;
}

// I won the claim — create both attempts from the SAME pinned question set, in
// the underlying mode so existing timer/scoring/percentile logic works.
$qIds = is_array($ch['question_ids']) ? $ch['question_ids'] : json_decode($ch['question_ids'] ?? '[]', true);
$qIds = array_map('intval', $qIds ?: []);
if (count($qIds) < 5) {
    supabaseRest('challenges?id=eq.' . $challengeId, 'PATCH', ['status' => 'accepted']); // release claim
    echo json_encode(['error' => 'Question set missing.']); exit;
}

$startedAt = date('c');
$makeAttempt = function (int $studentId) use ($ch, $qIds, $startedAt, $challengeId): ?int {
    $a = supabaseRest('attempts', 'POST', [
        'student_id' => $studentId,
        'test_id' => null,
        'mode' => $ch['mode'],
        'challenge_id' => $challengeId,
        'total_marks' => count($qIds),
        'started_at' => $startedAt,
        'status' => 'in_progress',
    ]);
    $aid = $a[0]['id'] ?? null;
    if (!$aid) return null;
    $answerRows = [];
    foreach ($qIds as $pos => $qid) $answerRows[] = ['attempt_id' => $aid, 'question_id' => $qid, 'position' => $pos];
    supabaseRest('attempt_answers', 'POST', $answerRows);
    return (int) $aid;
};

$challengerAttempt = $makeAttempt((int) $ch['challenger_id']);
$opponentAttempt   = $makeAttempt((int) $ch['opponent_id']);
if (!$challengerAttempt || !$opponentAttempt) {
    // Roll the claim back so the duel can be retried rather than stuck 'live'.
    supabaseRest('challenges?id=eq.' . $challengeId, 'PATCH', ['status' => 'accepted']);
    echo json_encode(['error' => 'Could not start the duel. Try again.']); exit;
}

supabaseRest('challenges?id=eq.' . $challengeId, 'PATCH', [
    'started_at' => $startedAt,
    'challenger_attempt_id' => $challengerAttempt,
    'opponent_attempt_id' => $opponentAttempt,
]);

$mine = $role === 'challenger' ? $challengerAttempt : $opponentAttempt;
echo json_encode(['ok' => true, 'status' => 'live', 'attempt_id' => (int) $mine]);
