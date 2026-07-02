<?php
// Challenger sends a duel invite to an accepted friend. Guards: caller is Pro,
// the two are accepted friends, the chosen mode is valid, and there isn't
// already an open (pending/accepted/live) challenge between the pair.
require_once __DIR__ . '/../config.php';
$user = requireAuth();
header('Content-Type: application/json');
requireCsrf();

if (!rateLimit('challenge_create', 20, 3600)) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many challenges sent. Please wait a while.']);
    exit;
}

if (!isSubscribed('pro')) {
    echo json_encode(['error' => 'needs_pro', 'message' => 'Challenge a Friend is a Pro feature.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$opponentId = (int) ($data['opponent_id'] ?? 0);
$mode = (string) ($data['mode'] ?? '');
$subject = trim((string) ($data['subject'] ?? '')) ?: null;

if (!$opponentId || $opponentId === (int) $user['id']) { echo json_encode(['error' => 'Invalid opponent.']); exit; }
if (!array_key_exists($mode, challengeModes())) { echo json_encode(['error' => 'Invalid mode.']); exit; }

// Must be ACCEPTED friends (either direction).
$me = (int) $user['id'];
$friend = supabaseRest('friends?status=eq.accepted&or=(and(student_id.eq.' . $me . ',friend_id.eq.' . $opponentId . '),and(student_id.eq.' . $opponentId . ',friend_id.eq.' . $me . '))&select=id&limit=1');
if (empty($friend)) { echo json_encode(['error' => 'You can only challenge accepted friends.']); exit; }

// No duplicate open challenge between the pair (either direction).
$open = supabaseRest('challenges?status=in.(pending,accepted,live)&or=(and(challenger_id.eq.' . $me . ',opponent_id.eq.' . $opponentId . '),and(challenger_id.eq.' . $opponentId . ',opponent_id.eq.' . $me . '))&select=id&limit=1', 'GET', null, ['no_cache' => true]);
if (!empty($open)) { echo json_encode(['error' => 'You already have an active challenge with this friend.']); exit; }

$expiresAt = date('c', strtotime('+48 hours'));
$created = supabaseRest('challenges', 'POST', [
    'challenger_id' => $me,
    'opponent_id' => $opponentId,
    'mode' => $mode,
    'subject' => $subject,
    'status' => 'pending',
    'expires_at' => $expiresAt,
]);
$challenge = $created[0] ?? null;
if (!$challenge) { echo json_encode(['error' => 'Could not create challenge.']); exit; }

// Notify the opponent.
supabaseRest('notifications', 'POST', [
    'student_id' => $opponentId,
    'title' => $user['name'] . ' challenged you! ⚔️',
    'body' => 'A ' . challengeModes()[$mode] . ' duel awaits. Accept within 48 hours.',
    'type' => 'challenge',
    'link' => BASE_PATH . 'challenges.php',
]);

// Email the opponent too (pulls them back) — skip if they opted out of mail.
$opp = supabaseRest('students?id=eq.' . $opponentId . '&select=email,name,email_opt_out&limit=1');
if (!empty($opp[0]['email']) && empty($opp[0]['email_opt_out'])) {
    $body = '<strong>' . htmlspecialchars($user['name']) . '</strong> challenged you to a head-to-head '
          . htmlspecialchars(challengeModes()[$mode]) . ' duel. Same questions, shared timer — fastest, sharpest wins.'
          . '<br><br>Accept within 48 hours to lock it in.';
    sendEmail(
        $opp[0]['email'], $opp[0]['name'] ?? '',
        $user['name'] . ' challenged you to a DDCET duel',
        emailTemplate('You have a new challenge', $body, 'Accept the challenge', APP_URL . BASE_PATH . 'challenges.php',
            emailUnsubFooter($opponentId))
    );
}

echo json_encode(['ok' => true, 'challenge_id' => (int) $challenge['id']]);
