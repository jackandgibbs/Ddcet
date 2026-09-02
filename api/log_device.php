<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$user = currentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'not_logged_in']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$fingerprint = $input['fingerprint'] ?? '';
$deviceType = $input['device_type'] ?? 'web';

if (empty($fingerprint)) {
    echo json_encode(['error' => 'missing_fingerprint']);
    exit;
}

// Ensure the table exists in Supabase
// We'll just try to insert. If it fails (table not created), we just return success anyway
// so we don't break the client.
$insert = supabaseRest('device_fingerprints', 'POST', [
    'student_id' => $user['id'],
    'fingerprint' => $fingerprint,
    'device_type' => $deviceType
]);

if ($insert === false) {
    // Possibly table doesn't exist yet, silently fail
    echo json_encode(['success' => true]);
    exit;
}

// Check how many unique fingerprints the user has used in the last 7 days
// Supabase REST doesn't easily support COUNT DISTINCT with a date filter directly,
// so we'll fetch the last 50 logins for this user in the last 7 days and count them in PHP.
$sevenDaysAgo = date('Y-m-d\TH:i:sP', strtotime('-7 days'));
$history = supabaseRest('device_fingerprints?student_id=eq.' . $user['id'] . '&created_at=gt.' . urlencode($sevenDaysAgo) . '&select=fingerprint&limit=50');

if (is_array($history)) {
    $uniqueFingerprints = array_unique(array_column($history, 'fingerprint'));
    if (count($uniqueFingerprints) >= 4) {
        // Flag account for sharing
        supabaseRest('students?id=eq.' . $user['id'], 'PATCH', [
            'is_flagged_for_sharing' => true
        ]);
    }
}

echo json_encode(['success' => true]);
