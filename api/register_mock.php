<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$user = currentUser();
if (!$user) { http_response_code(401); echo json_encode(['error' => 'not_logged_in']); exit; }

// CSRF — token comes in the X-CSRF-Token header for JSON callers.
if (!csrfValid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    http_response_code(403); echo json_encode(['error' => 'bad_csrf']); exit;
}

$data   = json_decode(file_get_contents('php://input'), true);
$testId = (int) ($data['test_id'] ?? 0);
if (!$testId) { echo json_encode(['error' => 'no_test']); exit; }

// Only allow registering for a real, published, scheduled mock.
$tests = supabaseRest('tests?id=eq.' . $testId
    . '&is_scheduled=eq.true&is_published=eq.true&select=id&limit=1');
if (empty($tests)) { http_response_code(404); echo json_encode(['error' => 'not_found']); exit; }

// Idempotent: the (test_id, student_id) unique key ignores duplicates.
supabaseRest('mock_registrations', 'POST', [
    'test_id' => $testId, 'student_id' => $user['id'],
], ['prefer' => 'resolution=ignore-duplicates']);

echo json_encode(['ok' => true, 'count' => mockRegistrationCount($testId)]);
