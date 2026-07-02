<?php
// Poll endpoint (GET) for the lobby, the in-exam opponent bar, and the result
// page. Returns the duel status, both sides' readiness/progress/score, the
// synchronized start time + server clock (for timer sync), and the winner.
// Only aggregate progress is exposed mid-duel — never the opponent's answers.
require_once __DIR__ . '/../config.php';
$user = requireAuth();
header('Content-Type: application/json');

$challengeId = (int) ($_GET['challenge_id'] ?? 0);
if (!$challengeId) { echo json_encode(['error' => 'Bad request.']); exit; }

$ch = challengeLoad($challengeId);   // always fresh — this is the live poll
if (!$ch) { http_response_code(404); echo json_encode(['error' => 'Not found.']); exit; }
$role = challengeRole($ch, (int) $user['id']);
if (!$role) { http_response_code(403); echo json_encode(['error' => 'Not your challenge.']); exit; }

$status = challengeResolveTimeouts($ch);

// Names for display.
$ids = [(int) $ch['challenger_id'], (int) $ch['opponent_id']];
$people = supabaseRest('students?id=in.(' . implode(',', $ids) . ')&select=id,name,avatar_url') ?? [];
$nameMap = [];
foreach ($people as $p) $nameMap[(int) $p['id']] = $p;

$isChallenger = $role === 'challenger';
$myAttempt  = $isChallenger ? $ch['challenger_attempt_id'] : $ch['opponent_attempt_id'];
$oppId      = $isChallenger ? (int) $ch['opponent_id'] : (int) $ch['challenger_id'];
$myScore    = $isChallenger ? $ch['challenger_score'] : $ch['opponent_score'];
$oppScore   = $isChallenger ? $ch['opponent_score'] : $ch['challenger_score'];
$myProgress = $isChallenger ? $ch['challenger_progress'] : $ch['opponent_progress'];
$oppProgress= $isChallenger ? $ch['opponent_progress'] : $ch['challenger_progress'];
$oppReady   = $isChallenger ? !empty($ch['opponent_ready']) : !empty($ch['challenger_ready']);
$meReady    = $isChallenger ? !empty($ch['challenger_ready']) : !empty($ch['opponent_ready']);

echo json_encode([
    'ok' => true,
    'status' => $status,
    'mode' => $ch['mode'],
    'now' => time(),                       // server clock for timer sync
    'lobby_deadline' => $ch['lobby_deadline'] ? strtotime($ch['lobby_deadline']) : null,
    'started_at' => $ch['started_at'] ? strtotime($ch['started_at']) : null,
    'duration' => modeDurationMinutes($ch['mode']) * 60,
    'my_attempt_id' => (int) $myAttempt,
    'me_ready' => $meReady,
    'opponent_ready' => $oppReady,
    'opponent_name' => $nameMap[$oppId]['name'] ?? 'Opponent',
    'opponent_avatar' => $nameMap[$oppId]['avatar_url'] ?? null,
    'my_progress' => (int) $myProgress,
    'opponent_progress' => (int) $oppProgress,
    'my_score' => $myScore !== null ? (float) $myScore : null,
    'opponent_score' => $oppScore !== null ? (float) $oppScore : null,
    'winner_id' => $ch['winner_id'] !== null ? (int) $ch['winner_id'] : null,
    'is_me_winner' => $ch['winner_id'] !== null ? ((int) $ch['winner_id'] === (int) $user['id']) : null,
]);
