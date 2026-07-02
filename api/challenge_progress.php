<?php
// Lightweight POST from exam.php during a live duel: update the caller's
// answered-count so the opponent's live bar can show progress. Cheap and
// idempotent; the authoritative score is still written at submit time.
require_once __DIR__ . '/../config.php';
$user = requireAuth();
header('Content-Type: application/json');
requireCsrf();

$data = json_decode(file_get_contents('php://input'), true);
$challengeId = (int) ($data['challenge_id'] ?? 0);
$answered = max(0, (int) ($data['answered'] ?? 0));
if (!$challengeId) { echo json_encode(['error' => 'Bad request.']); exit; }

$ch = challengeLoad($challengeId);
if (!$ch) { http_response_code(404); echo json_encode(['error' => 'Not found.']); exit; }
$role = challengeRole($ch, (int) $user['id']);
if (!$role) { http_response_code(403); echo json_encode(['error' => 'Not your challenge.']); exit; }
if (($ch['status'] ?? '') !== 'live') { echo json_encode(['ok' => true]); exit; }

supabaseRest('challenges?id=eq.' . $challengeId, 'PATCH', [$role . '_progress' => $answered]);
echo json_encode(['ok' => true]);
