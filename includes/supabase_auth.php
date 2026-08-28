<?php
// Supabase Auth Helper Functions

/**
 * BUG-005 fix: uses cURL with explicit timeouts instead of file_get_contents()
 * (which had no timeout and would hang the login flow indefinitely on slow
 * mobile networks, especially on iOS PWAs where Safari kills the WebView).
 */
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

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_ENCODING       => '',
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode >= 400) {
        appLog('error', 'supabaseRequest (auth) failed', [
            'endpoint' => $endpoint, 'method' => $method, 'http' => $httpCode,
        ]);
        return null;
    }
    return json_decode($response, true);
}

function getSupabaseAuthUrl() {
    $params = http_build_query([
        'provider' => 'google',
        // 'redirect_to' => APP_URL . BASE_PATH . 'auth/callback.php',
            'redirect_to' => APP_URL . '/auth/callback.php',
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

