<?php
/**
 * Minimal, dependency-free TOTP (RFC 6238) implementation for admin 2FA.
 * Compatible with Google Authenticator, Authy, Microsoft Authenticator, etc.
 *
 * Algorithm: HMAC-SHA1, 6 digits, 30-second time step (the authenticator defaults).
 */

/** Decode a Base32 (RFC 4648, no padding required) string to raw bytes. */
function totp_base32_decode(string $b32): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $b32));
    if ($b32 === '') return '';
    $bits = '';
    foreach (str_split($b32) as $char) {
        $bits .= str_pad(decbin(strpos($alphabet, $char)), 5, '0', STR_PAD_LEFT);
    }
    $bytes = '';
    foreach (str_split($bits, 8) as $chunk) {
        if (strlen($chunk) === 8) {
            $bytes .= chr(bindec($chunk));
        }
    }
    return $bytes;
}

/** Generate the TOTP code for a given secret and time counter. */
function totp_code(string $secret, int $counter, int $digits = 6): string {
    $key = totp_base32_decode($secret);
    // 8-byte big-endian counter
    $binCounter = pack('N*', 0) . pack('N*', $counter);
    $hash = hash_hmac('sha1', $binCounter, $key, true);
    $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
    $truncated = (
        ((ord($hash[$offset])     & 0x7F) << 24) |
        ((ord($hash[$offset + 1]) & 0xFF) << 16) |
        ((ord($hash[$offset + 2]) & 0xFF) << 8)  |
         (ord($hash[$offset + 3]) & 0xFF)
    );
    $code = $truncated % (10 ** $digits);
    return str_pad((string) $code, $digits, '0', STR_PAD_LEFT);
}

/**
 * Verify a user-supplied TOTP code against the secret.
 * A ±1 step window (±30s) tolerates clock drift between phone and server.
 * Uses hash_equals for constant-time comparison.
 */
function totp_verify(string $secret, string $code, int $window = 1, int $timeStep = 30): bool {
    $code = preg_replace('/\D/', '', $code);
    if ($secret === '' || strlen($code) !== 6) return false;
    $current = (int) floor(time() / $timeStep);
    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(totp_code($secret, $current + $i), $code)) {
            return true;
        }
    }
    return false;
}

/** Build the otpauth:// URI that authenticator apps scan from a QR code. */
function totp_provisioning_uri(string $secret, string $account, string $issuer): string {
    $label = rawurlencode($issuer) . ':' . rawurlencode($account);
    $query = http_build_query([
        'secret'    => $secret,
        'issuer'    => $issuer,
        'algorithm' => 'SHA1',
        'digits'    => 6,
        'period'    => 30,
    ]);
    return "otpauth://totp/{$label}?{$query}";
}
