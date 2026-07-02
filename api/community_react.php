<?php
// Toggle a community post reaction (helpful/great) for the current user and
// return fresh counts. UNIQUE(post_id, student_id) means one reaction per user
// per post, so tapping a different reaction switches it, and tapping the same
// one removes it.
require_once __DIR__ . '/../config.php';
$user = requireAuth();
header('Content-Type: application/json');
requireCsrf();

$data = json_decode(file_get_contents('php://input'), true);
$postId = (int) ($data['post_id'] ?? 0);
$reaction = $data['reaction'] ?? '';
$me = (int) $user['id'];

if (!$postId || !in_array($reaction, ['helpful', 'great'], true)) {
    echo json_encode(['error' => 'Bad request.']);
    exit;
}

// Existing reaction by this user on this post (fresh read).
$existing = supabaseRest('post_reactions?post_id=eq.' . $postId . '&student_id=eq.' . $me . '&select=id,reaction&limit=1', 'GET', null, ['no_cache' => true]);
$row = $existing[0] ?? null;
$mine = $reaction;

if ($row) {
    if ($row['reaction'] === $reaction) {
        // Same reaction tapped again -> remove it.
        supabaseRest('post_reactions?id=eq.' . (int) $row['id'], 'DELETE');
        $mine = '';
    } else {
        // Switch to the other reaction.
        supabaseRest('post_reactions?id=eq.' . (int) $row['id'], 'PATCH', ['reaction' => $reaction]);
    }
} else {
    supabaseRest('post_reactions', 'POST', [
        'post_id' => $postId,
        'student_id' => $me,
        'reaction' => $reaction,
    ]);
}

// Recount fresh (avoid the 10s GET cache so the UI shows the just-made change).
$all = supabaseRest('post_reactions?post_id=eq.' . $postId . '&select=reaction', 'GET', null, ['no_cache' => true]) ?? [];
$helpful = 0; $great = 0;
foreach ($all as $r) {
    if ($r['reaction'] === 'helpful') $helpful++;
    elseif ($r['reaction'] === 'great') $great++;
}

echo json_encode(['ok' => true, 'helpful' => $helpful, 'great' => $great, 'mine' => $mine]);
