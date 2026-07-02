<?php
// Opponent accepts or declines a pending challenge. On accept: require the
// opponent to be Pro, pin the shared question set SERVER-SIDE, move to the lobby
// (2-minute join window), and notify the challenger.
require_once __DIR__ . '/../config.php';
$user = requireAuth();
header('Content-Type: application/json');
requireCsrf();

$data = json_decode(file_get_contents('php://input'), true);
$challengeId = (int) ($data['challenge_id'] ?? 0);
$decision = (string) ($data['decision'] ?? ''); // 'accept' | 'decline'
if (!$challengeId || !in_array($decision, ['accept', 'decline'], true)) {
    echo json_encode(['error' => 'Bad request.']); exit;
}

$ch = challengeLoad($challengeId);
if (!$ch || (int) $ch['opponent_id'] !== (int) $user['id']) {
    http_response_code(403); echo json_encode(['error' => 'Not your challenge.']); exit;
}

$status = challengeResolveTimeouts($ch);
if ($status !== 'pending') { echo json_encode(['error' => 'This challenge is no longer open.']); exit; }

if ($decision === 'decline') {
    supabaseRest('challenges?id=eq.' . $challengeId, 'PATCH', ['status' => 'cancelled']);
    supabaseRest('notifications', 'POST', [
        'student_id' => (int) $ch['challenger_id'],
        'title' => $user['name'] . ' declined your challenge',
        'body' => 'Maybe next time. Send another whenever you like.',
        'type' => 'challenge',
        'link' => BASE_PATH . 'challenges.php',
    ]);
    echo json_encode(['ok' => true, 'status' => 'cancelled']);
    exit;
}

// Accept path — opponent must also be Pro.
if (!isSubscribed('pro')) {
    echo json_encode(['error' => 'needs_pro', 'message' => 'You need Pro to accept a challenge.']);
    exit;
}

// Pin the shared question set now (single source — both players get this exact set).
// Require the FULL expected count for the mode, otherwise the duel would start
// mislabeled (e.g. a "100Q Full Mock" silently running 40 questions on a 150-min
// timer). Both players share one set so it's fair either way, but the stated mode
// must be honoured — refuse and tell them to pick another mode.
$expectedCount = ['rapid_fire' => 30, 'subject_wise' => 30, 'full_mock' => 100][$ch['mode']] ?? 30;
$qIds = challengeQuestionIds($ch['mode'], $ch['subject'] ?? null);
if (count($qIds) < $expectedCount) {
    echo json_encode(['error' => 'Not enough questions available for this mode right now. Try a different mode.']);
    exit;
}

supabaseRest('challenges?id=eq.' . $challengeId, 'PATCH', [
    'status' => 'accepted',
    'question_ids' => $qIds,
    'lobby_deadline' => date('c', strtotime('+2 minutes')),
]);

supabaseRest('notifications', 'POST', [
    'student_id' => (int) $ch['challenger_id'],
    'title' => $user['name'] . ' accepted your challenge! 🔥',
    'body' => 'Jump into the lobby now — the duel starts when you both join.',
    'type' => 'challenge',
    'link' => BASE_PATH . 'challenges.php?lobby=' . $challengeId,
]);

echo json_encode(['ok' => true, 'status' => 'accepted', 'challenge_id' => $challengeId]);
