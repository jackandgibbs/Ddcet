<?php
/**
 * Claim the "Top 10 of a FREE mock → 1 month Pro free" reward.
 *
 * Everything is re-validated server-side — the button is just a trigger. A claim
 * grants a 30-day Pro subscription and is recorded in mock_rewards, whose UNIQUE
 * (test_id, student_id) makes a second claim a no-op. POST { test_id }, CSRF via
 * the X-CSRF-Token header.
 */
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$user = currentUser();
if (!$user) { http_response_code(401); echo json_encode(['error' => 'not_logged_in']); exit; }

if (!csrfValid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    http_response_code(403); echo json_encode(['error' => 'bad_csrf']); exit;
}

$data   = json_decode(file_get_contents('php://input'), true);
$testId = (int) ($data['test_id'] ?? 0);
if (!$testId) { echo json_encode(['error' => 'no_test']); exit; }

// The mock must be a real, FREE, scheduled mock with results out.
$tests = supabaseRest('tests?id=eq.' . $testId
    . '&is_scheduled=eq.true&is_published=eq.true&is_free=eq.true'
    . '&select=id,title,series_label,opens_at,closes_at,results_at,results_published&limit=1');
$test = $tests[0] ?? null;
if (!$test)                  { http_response_code(404); echo json_encode(['error' => 'not_eligible']); exit; }
if (!mockResultsOut($test))  { echo json_encode(['error' => 'results_not_out']); exit; }

// The student must have a completed attempt that finished in the Top 10 AIR.
$myAtt = supabaseRest('attempts?student_id=eq.' . $user['id'] . '&test_id=eq.' . $testId
    . '&status=eq.completed&select=score&order=score.desc&limit=1');
if (empty($myAtt)) { echo json_encode(['error' => 'no_attempt']); exit; }
$air = mockAIR($testId, (float)$myAtt[0]['score']);
if (!$air || $air['rank'] > 10) { echo json_encode(['error' => 'not_top10']); exit; }

// Already claimed? The unique key would reject a duplicate insert anyway, but
// check first so we return a clean "already claimed" instead of a swallowed error.
$existing = supabaseRest('mock_rewards?test_id=eq.' . $testId
    . '&student_id=eq.' . $user['id'] . '&select=id&limit=1');
if (!empty($existing)) { echo json_encode(['ok' => true, 'already_claimed' => true]); exit; }

// Grant: 30-day Pro subscription + record the claim + notify.
$expires = date('c', strtotime('+30 days'));
$sub = supabaseRest('subscriptions', 'POST', [
    'student_id' => $user['id'], 'plan' => 'pro', 'status' => 'active',
    'starts_at' => date('c'), 'expires_at' => $expires,
]);

$reward = supabaseRest('mock_rewards', 'POST', [
    'test_id' => $testId, 'student_id' => $user['id'],
    'reward' => 'pro_1_month', 'rank_at_claim' => $air['rank'],
], ['prefer' => 'resolution=ignore-duplicates']);

if (empty($sub) && empty($reward)) {
    // Both writes failed — surface an error rather than a false success.
    http_response_code(500); echo json_encode(['error' => 'grant_failed']); exit;
}

$label = $test['series_label'] ?: $test['title'];
supabaseRest('notifications', 'POST', [
    'student_id' => $user['id'],
    'title'      => '1 month Pro unlocked 🎉',
    'body'       => 'You finished #' . $air['rank'] . ' in ' . $label . ' — enjoy 30 days of Pro on us.',
    'type'       => 'reward',
    'link'       => BASE_PATH . 'mocks.php',
]);

echo json_encode(['ok' => true, 'expires_at' => $expires, 'rank' => $air['rank']]);
