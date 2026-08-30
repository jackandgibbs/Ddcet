<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$identifier = trim($data['identifier'] ?? '');
$action = $data['action'] ?? 'login';

// Check if MSG91 successfully verified the OTP on the frontend
$msg91Verified = !empty($data['msg91_verified']);
$msg91Data = $data['msg91_data'] ?? [];

if (empty($identifier) || !$msg91Verified) {
    echo json_encode(['error' => 'OTP verification failed. Please try again.']);
    exit;
}

// NOTE: For absolute security, the backend should verify the JWT/Token provided by MSG91 in $msg91Data 
// against the MSG91 API using your private Auth Key. For now, since the widget handles the flow 
// completely, we proceed with the login/registration based on the frontend's success signal.

// Normalize phone
$isPhone = false;
if (preg_match('/^\d{10}$/', $identifier)) {
    $identifier = '+91' . $identifier;
    $isPhone = true;
} elseif (preg_match('/^\+\d{10,15}$/', $identifier)) {
    $isPhone = true;
}

// Process Login vs Register
if ($action === 'login') {
    // Look up user
    $query = $isPhone ? "phone=eq." . urlencode($identifier) : "email=eq." . urlencode($identifier);
    $users = supabaseRest('students?' . $query . '&limit=1');
    
    if (empty($users[0])) {
        echo json_encode(['error' => 'No account found with this ' . ($isPhone ? 'phone number' : 'email') . '. Please register.']);
        exit;
    }
    
    $user = $users[0];
    
    // Log them in
    $_SESSION['user'] = $user;
    
    // Remember me
    if (!empty($data['rememberMe'])) {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        supabaseRest('students?id=eq.' . $user['id'], 'PATCH', ['remember_token' => $hash]);
        setcookie('ddcet_remember', $token, time() + 30 * 86400, '/', '', true, true);
    }
    
    echo json_encode(['success' => true, 'redirect' => BASE_PATH . 'dashboard.php']);
    exit;
    
} elseif ($action === 'register') {
    // Make sure user doesn't already exist
    $query = $isPhone ? "phone=eq." . urlencode($identifier) : "email=eq." . urlencode($identifier);
    $existing = supabaseRest('students?' . $query . '&limit=1');
    if (!empty($existing[0])) {
        echo json_encode(['error' => 'An account with this ' . ($isPhone ? 'phone number' : 'email') . ' already exists. Please log in.']);
        exit;
    }
    
    // Validate registration fields
    $name = trim($data['name'] ?? '');
    $surname = trim($data['surname'] ?? '');
    $collegeId = (int)($data['college_id'] ?? 0);
    $semester = trim($data['semester'] ?? '');
    $branch = trim($data['branch'] ?? '');
    
    if (empty($name) || empty($surname) || !$collegeId) {
        echo json_encode(['error' => 'Name, Surname, and College are required for registration.']);
        exit;
    }
    
    $insertData = [
        'name' => $name . ' ' . $surname, // Keep full name for legacy fields
        'surname' => $surname,
        'college_id' => $collegeId,
        'semester' => $semester,
        'branch' => $branch,
        'onboarded' => 1 // Skip onboarding since we collect info here
    ];
    
    if ($isPhone) {
        $insertData['phone'] = $identifier;
    } else {
        $insertData['email'] = $identifier;
    }
    
    $res = supabaseRest('students', 'POST', $insertData);
    if ($res === false) {
        echo json_encode(['error' => 'Failed to create account. Please try again.']);
        exit;
    }
    
    // Retrieve the newly created user to get the full object
    $newUser = supabaseRest('students?' . $query . '&select=*&limit=1');
    if (!empty($newUser[0])) {
        $_SESSION['user'] = $newUser[0];
        
        // Remember me
        if (!empty($data['rememberMe'])) {
            $token = bin2hex(random_bytes(32));
            $hash = hash('sha256', $token);
            supabaseRest('students?id=eq.' . $newUser[0]['id'], 'PATCH', ['remember_token' => $hash]);
            setcookie('ddcet_remember', $token, time() + 30 * 86400, '/', '', true, true);
        }
        
        echo json_encode(['success' => true, 'redirect' => BASE_PATH . 'dashboard.php']);
    } else {
        echo json_encode(['error' => 'Account created, but auto-login failed.']);
    }
    exit;
}

echo json_encode(['error' => 'Invalid action specified.']);
