<?php
// Persist the medium ('en' | 'gu') a student is taking an attempt in. Mirrors
// save_answer.php: trust comes from the ownership-filtered PATCH, not CSRF.
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$user = currentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'not_logged_in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$attemptId = (int) ($data['attempt_id'] ?? 0);
$lang = (($data['language'] ?? 'en') === 'gu') ? 'gu' : 'en';

if ($attemptId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'bad_request']);
    exit;
}

// Only the attempt's owner can set its language (the student_id filter is the
// authorization check — a PATCH that matches no row simply does nothing).
supabaseRest('attempts?id=eq.' . $attemptId . '&student_id=eq.' . (int) $user['id'], 'PATCH', ['language' => $lang]);

// Remember it as the student's default medium for next time.
supabaseRest('students?id=eq.' . (int) $user['id'], 'PATCH', ['preferred_language' => $lang]);

echo json_encode(['success' => true, 'language' => $lang]);
