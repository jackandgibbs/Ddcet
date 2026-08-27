<?php
/**
 * Helper to call Supabase PostgREST RPC endpoints.
 *
 * BUG-002 fix: uses its OWN dedicated persistent cURL handle. The old code
 * called supabaseHandle() — the same static handle that supabaseRest() uses —
 * and set CURLOPT_CUSTOMREQUEST / POSTFIELDS on it without curl_reset(). This
 * corrupted the shared handle so that the NEXT supabaseRest() call (even a GET)
 * would inherit the POST method, silently failing or sending data to the wrong
 * endpoint.
 */
function supabaseRpc(string $fnName, array $params = []): mixed {
    static $rpcCh = null;
    if ($rpcCh === null) $rpcCh = curl_init();

    $url = SUPABASE_URL . '/rest/v1/rpc/' . $fnName;
    curl_reset($rpcCh);
    curl_setopt_array($rpcCh, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($params),
        CURLOPT_HTTPHEADER     => [
            'apikey: ' . SUPABASE_KEY,
            'Authorization: Bearer ' . SUPABASE_KEY,
            'Content-Type: application/json',
            'Prefer: return=representation',
        ],
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TCP_KEEPALIVE  => 1,
        CURLOPT_DNS_CACHE_TIMEOUT => 300,
        CURLOPT_ENCODING       => '',
    ]);

    $response = curl_exec($rpcCh);
    $httpCode = curl_getinfo($rpcCh, CURLINFO_HTTP_CODE);
    // NOTE: intentionally NOT curl_close()'d — reused next call.

    if ($httpCode >= 400) {
        appLog('error', 'supabaseRpc failed', ['fn' => $fnName, 'http' => $httpCode, 'body' => $response]);
        return null;
    }

    return json_decode($response, true);
}
