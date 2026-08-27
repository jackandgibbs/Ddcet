<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$user = currentUser();
if (!$user) { http_response_code(401); echo json_encode(['error' => 'not_logged_in']); exit; }
requireCsrf();

$data = json_decode(file_get_contents('php://input'), true);
$attemptId = (int) ($data['attempt_id'] ?? 0);
if (!$attemptId) { http_response_code(400); echo json_encode(['error' => 'no_attempt_id']); exit; }

// Verify ownership — pull timing + mode so we can enforce the clock server-side.
$attempts = supabaseRest('attempts?id=eq.' . $attemptId . '&student_id=eq.' . $user['id'] . '&status=eq.in_progress&select=id,started_at,mode,test_id,tab_switches,challenge_id&limit=1');
$attempt = $attempts[0] ?? null;
if (!$attempt) {
    $check = supabaseRest('attempts?id=eq.' . $attemptId . '&student_id=eq.' . $user['id'] . '&status=eq.completed&limit=1');
    if (!empty($check)) { echo json_encode(['ok' => true, 'already_submitted' => true]); exit; }
    http_response_code(403); echo json_encode(['error' => 'not_found']); exit;
}

// Get all answers in ONE call
$answers = supabaseRest('attempt_answers?attempt_id=eq.' . $attemptId . '&select=id,question_id,selected_option_id,time_spent_seconds') ?? [];
if (empty($answers)) { http_response_code(400); echo json_encode(['error' => 'no_answers']); exit; }

// Get ALL correct options for these questions in ONE call
$qIds = array_column($answers, 'question_id');
$allOptions = supabaseRest('options?question_id=in.(' . implode(',', $qIds) . ')&is_correct=eq.true&select=id,question_id') ?? [];
$correctOptionMap = []; // question_id => correct_option_id
foreach ($allOptions as $o) $correctOptionMap[$o['question_id']] = $o['id'];

// Server-side time enforcement. The client timer is advisory; the authoritative
// elapsed time is now() - started_at. We cap recorded time at the allotted
// duration so a user who submits late (or replays the request hours later)
// cannot claim impossibly long/short timing.
$durationMode = $attempt['mode'] ?? '';
$allottedSec  = $durationMode ? modeDurationMinutes($durationMode) * 60 : 0;
$startedTs    = strtotime($attempt['started_at'] ?? 'now');
$serverElapsed = max(0, time() - $startedTs);
$cappedElapsed = $allottedSec > 0 ? min($serverElapsed, $allottedSec) : $serverElapsed;

// Score: +2 correct, -0.5 wrong, 0 skipped
$score = 0; $correct = 0; $incorrect = 0; $skipped = 0; $clientTime = 0;
$correctIds = []; $incorrectIds = []; // attempt_answers.id, for persisting is_correct

foreach ($answers as $ans) {
    $clientTime += (int)($ans['time_spent_seconds'] ?? 0);
    $qId = $ans['question_id'];

    if (empty($ans['selected_option_id'])) {
        $skipped++;
    } elseif ($ans['selected_option_id'] == ($correctOptionMap[$qId] ?? null)) {
        $score += 2;
        $correct++;
        $correctIds[] = $ans['id'];
    } else {
        $score -= 0.5;
        $incorrect++;
        $incorrectIds[] = $ans['id'];
    }
}
$score = max(0, $score);

// Use the server clock as the source of truth, but never below the sum the
// client reported (handles clock skew gracefully).
$totalTime = max($cappedElapsed, 0);

// Persist per-answer correctness so result.php shows accurate Correct/Incorrect badges
// (two batched PATCHes instead of one call per answer)
if ($correctIds) {
    supabaseRest('attempt_answers?id=in.(' . implode(',', $correctIds) . ')', 'PATCH', ['is_correct' => true]);
}
if ($incorrectIds) {
    supabaseRest('attempt_answers?id=in.(' . implode(',', $incorrectIds) . ')', 'PATCH', ['is_correct' => false]);
}

// XP
$xp = 10 + ($correct * 2);

// Real percentile: compare this score against peers' completed attempts of the
// same test/mode. percentile = % of peers who scored <= this score. With too
// few peers (<5) we leave it null rather than show a misleading number.
// BUG-019 fix: use supabaseCount() instead of fetching all peer rows. The old
// code fetched all rows (which PostgREST truncated at 1000 anyway) and did an
// O(N) array_filter in PHP, wasting bandwidth and memory.
$percentile = null;
$peerFilterBase = 'status=eq.completed';
if (!empty($attempt['test_id'])) {
    $peerFilterBase .= '&test_id=eq.' . (int)$attempt['test_id'];
} elseif ($durationMode) {
    $peerFilterBase .= '&mode=eq.' . urlencode($durationMode) . '&test_id=is.null';
} else {
    $peerFilterBase = ''; // No peers to compare to
}

if ($peerFilterBase) {
    $totalPeers = supabaseCount('attempts?' . $peerFilterBase);
    if ($totalPeers >= 5) {
        $peersAtOrBelow = supabaseCount('attempts?' . $peerFilterBase . '&score=lte.' . $score);
        $percentile = round(($peersAtOrBelow / $totalPeers) * 100, 2);
    }
}

// ONE update for attempt (total_marks = questions × 2)
$attemptPatch = [
    'score' => $score, 'correct_count' => $correct, 'incorrect_count' => $incorrect,
    'skipped_count' => $skipped, 'time_spent_seconds' => $totalTime,
    'total_marks' => count($answers) * 2,
    'completed_at' => date('c'), 'status' => 'completed', 'xp_earned' => $xp,
];
if ($percentile !== null) $attemptPatch['percentile'] = $percentile;
supabaseRest('attempts?id=eq.' . $attemptId, 'PATCH', $attemptPatch);

// ONE update for student XP (Atomic RPC)
$today = date('Y-m-d');
$isDaily = ($durationMode === 'daily_challenge');
supabaseRpc('increment_student_xp', [
    'p_student_id' => (int) $user['id'],
    'p_xp' => $xp,
    'p_is_daily_challenge' => $isDaily,
    'p_today' => $today
]);

// XP log
supabaseRest('xp_log', 'POST', [
    'student_id' => $user['id'], 'amount' => $xp, 'reason' => 'Test completed', 'source_type' => 'test', 'source_id' => $attemptId,
]);

// --- Friend duel: record this side's score; finish + crown winner if both done.
if (!empty($attempt['challenge_id'])) {
    $cid = (int) $attempt['challenge_id'];
    $ch = challengeLoad($cid);   // always fresh
    if ($ch && in_array($ch['status'], ['live', 'completed'], true)) {
        $isChallenger = (int) $ch['challenger_id'] === (int) $user['id'];
        $scoreCol = $isChallenger ? 'challenger_score' : 'opponent_score';
        supabaseRest('challenges?id=eq.' . $cid, 'PATCH', [$scoreCol => $score]);

        // Re-read AFTER writing our score so a near-simultaneous opponent submit
        // is visible here (otherwise both read each other's score as null and the
        // duel would never complete).
        $ch = challengeLoad($cid) ?: $ch;
        $challengerScore = $ch['challenger_score'];
        $opponentScore   = $ch['opponent_score'];

        // Both finished? Atomically CLAIM completion so exactly one request crowns
        // the winner and sends notifications (the status=eq.live filter is the CAS).
        if ($challengerScore !== null && $opponentScore !== null) {
            $winnerId = challengeWinnerId((float) $challengerScore, (float) $opponentScore, (int) $ch['challenger_id'], (int) $ch['opponent_id']);
            $claim = supabaseRest('challenges?id=eq.' . $cid . '&status=eq.live', 'PATCH', [
                'status' => 'completed',
                'winner_id' => $winnerId,
            ]);
            if (!empty($claim)) {   // we won the CAS — notify both players once
                foreach ([(int) $ch['challenger_id'], (int) $ch['opponent_id']] as $pid) {
                    $msg = $winnerId === null ? "It's a tie! Great match." : ($winnerId === $pid ? 'You won the duel! 🏆' : 'You lost this one — rematch?');
                    supabaseRest('notifications', 'POST', [
                        'student_id' => $pid,
                        'title' => 'Challenge complete',
                        'body' => $msg,
                        'type' => 'challenge',
                        'link' => BASE_PATH . 'result.php?attempt_id=' . ($pid === (int) $ch['challenger_id'] ? (int) $ch['challenger_attempt_id'] : (int) $ch['opponent_attempt_id']),
                    ]);
                }
            }
        }
    }
}

echo json_encode(['ok' => true, 'score' => $score, 'correct' => $correct, 'incorrect' => $incorrect, 'skipped' => $skipped, 'xp' => $xp]);
