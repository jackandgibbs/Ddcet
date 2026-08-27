<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');
$user = requireAuth();
requireCsrf();

$data = json_decode(file_get_contents('php://input'), true);
$qId = (int) ($data['question_id'] ?? 0);
$rating = $data['rating'] ?? '';

if (!$qId || !in_array($rating, ['too_easy', 'fair', 'hard'], true)) {
    echo json_encode(['error' => 'invalid']);
    exit;
}

// Upsert directly via PostgREST. The SupaStatement SQL emulator maps PARAM names
// (:qid/:sid/:r) to column names, which would write the wrong columns — so we call
// supabaseRest() with the real column names and resolve conflicts on the
// (question_id, student_id) unique key.
supabaseRest('question_ratings', 'POST', [
    'question_id' => $qId,
    'student_id'  => $user['id'],
    'rating'      => $rating,
], ['prefer' => 'resolution=merge-duplicates']);

echo json_encode(['ok' => true]);

