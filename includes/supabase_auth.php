<?php
// Supabase Auth Helper Functions

function supabaseRequest($endpoint, $method = 'GET', $data = null, $token = null) {
    $url = SUPABASE_URL . $endpoint;
    $headers = [
        'apikey: ' . SUPABASE_KEY,
        'Content-Type: application/json',
    ];
    
    if ($method === 'POST') {
        $headers[] = 'Prefer: return=representation';
    }
    
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    
    $options = [
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'ignore_errors' => true,
        ],
    ];
    
    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        $options['http']['content'] = json_encode($data);
    }
    
    $response = file_get_contents($url, false, stream_context_create($options));
    return json_decode($response, true);
}

function getSupabaseAuthUrl() {
    $params = http_build_query([
        'provider' => 'google',
        'redirect_to' => APP_URL . BASE_PATH . 'auth/callback.php',
    ]);
    return SUPABASE_URL . '/auth/v1/authorize?' . $params;
}

function exchangeSupabaseCode($code) {
    return supabaseRequest('/auth/v1/token?grant_type=authorization_code', 'POST', [
        'auth_code' => $code,
    ]);
}

function getSupabaseUser($accessToken) {
    return supabaseRequest('/auth/v1/user', 'GET', null, $accessToken);
}
