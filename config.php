<?php
// Session hardening — must come BEFORE session_start().
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '1');
// BUG-036 fix: auto-detect HTTPS instead of hardcoding '0'.
$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
ini_set('session.cookie_secure', $isSecure ? '1' : '0');

session_start();
date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/includes/icons.php';

// Security headers — applied to every response that includes config.php.
// CSP allows inline styles/scripts (legacy codebase), CDN assets, and Razorpay.
if (!headers_sent()) {
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://checkout.razorpay.com https://*.msg91.com https://*.hostnsoft.com https://*.hcaptcha.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://*.hcaptcha.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' https://api.razorpay.com https://lumberjack.razorpay.com https://*.supabase.co https://*.msg91.com https://*.hcaptcha.com https://*.hostnsoft.com; frame-src 'self' https://*.msg91.com https://api.razorpay.com https://*.hcaptcha.com;");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
    header("Cache-Control: no-cache, no-store, must-revalidate");
}

// Load .env
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || str_starts_with($line, '#')) continue;
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);
        if ((str_starts_with($val, '"') && str_ends_with($val, '"')) || 
            (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
            $val = substr($val, 1, -1);
        }
        $_ENV[$key] = $val;
    }
}

// Constants
define('SUPABASE_URL', $_ENV['SUPABASE_URL'] ?? '');
define('SUPABASE_KEY', $_ENV['SUPABASE_KEY'] ?? '');
define('GEMINI_API_KEY', $_ENV['GEMINI_API_KEY'] ?? '');
define('APP_URL', $_ENV['APP_URL'] ?? 'http://localhost:8000');
define('APP_NAME', $_ENV['APP_NAME'] ?? 'DDCET Prep');
define('DDCET_EXAM_DATE', $_ENV['DDCET_EXAM_DATE'] ?? '2027-06-15');
// define('BASE_PATH', '/Dddcet/');
// define('BASE_PATH', '/');
define(
    'BASE_PATH',
    $_ENV['BASE_PATH'] ?? '/'
);

function redirect(string $path): void {
    $path = ltrim($path, '/');
    if (str_starts_with($path, trim(BASE_PATH, '/'))) {
        header('Location: /' . $path);
    } else {
        header('Location: ' . BASE_PATH . $path);
    }
    exit;
}

define('GOOGLE_CLIENT_ID', $_ENV['GOOGLE_CLIENT_ID'] ?? '');
define('GOOGLE_CLIENT_SECRET', $_ENV['GOOGLE_CLIENT_SECRET'] ?? '');
define('GOOGLE_REDIRECT_URI', $_ENV['GOOGLE_REDIRECT_URI'] ?? '');

define('RAZORPAY_KEY_ID', $_ENV['RAZORPAY_KEY_ID'] ?? '');
define('RAZORPAY_KEY_SECRET', $_ENV['RAZORPAY_KEY_SECRET'] ?? '');

// Razorpay's payment-gateway fee, as a percent of the charged amount, deducted
// before the money is settled to us. Standard pricing is 2% (Razorpay also adds
// 18% GST on that fee, i.e. ~2.36% all-in — set RAZORPAY_FEE_PERCENT=2.36 in
// .env if you want net revenue to reflect GST too). Used only for admin revenue
// reporting; it does NOT change what the student is charged.
define('RAZORPAY_FEE_PERCENT', (float)($_ENV['RAZORPAY_FEE_PERCENT'] ?? 2));

// Transactional email via Brevo (free tier ~300/day). All optional: if
// BREVO_API_KEY is empty, sendEmail() is a graceful no-op so signup/payment
// never break. MAIL_FROM_EMAIL must be a Brevo-verified sender; the display
// name (MAIL_FROM_NAME) is what users see ("DDCET Prep").
define('BREVO_API_KEY', $_ENV['BREVO_API_KEY'] ?? '');
define('MAIL_FROM_EMAIL', $_ENV['MAIL_FROM_EMAIL'] ?? '');
define('MAIL_FROM_NAME', $_ENV['MAIL_FROM_NAME'] ?? APP_NAME);
// Optional: a PUBLIC https URL to the logo PNG for email headers (email clients
// can't load http://localhost, so leave empty in dev — the template falls back
// to a clean CSS wordmark. After deploy set e.g. https://yoursite/assets/logo.png).
define('MAIL_LOGO_URL', $_ENV['MAIL_LOGO_URL'] ?? '');
// Shared secret guarding the public reminder cron endpoint + signing unsubscribe
// links. Generate a long random string and keep it secret.
define('CRON_SECRET', $_ENV['CRON_SECRET'] ?? '');

// Admin 2FA: TOTP (Google Authenticator). A single shared secret is enrolled
// once via admin/2fa-setup.php, regardless of how the admin signed in (Google
// or wb-admin password login). Generate a Base32 secret and put it in .env as
// ADMIN_TOTP_SECRET. If unset, 2FA is effectively disabled until enrolled.
define('ADMIN_TOTP_SECRET', $_ENV['ADMIN_TOTP_SECRET'] ?? '');
define('ADMIN_TOTP_ISSUER', APP_NAME . ' Admin');

// wb-admin fallback login. Credentials live in .env (never hardcode them in
// source). Password is stored as a bcrypt hash and checked with password_verify.
define('WB_ADMIN_USERNAME', $_ENV['WB_ADMIN_USERNAME'] ?? '');
define('WB_ADMIN_PASSWORD_HASH', $_ENV['WB_ADMIN_PASSWORD_HASH'] ?? '');

// Test Mode
define('TEST_MODE', false);

/* ============================================================================
 * Logging — lightweight, zero-dependency error log.
 * Writes to PHP's configured error_log (syslog / file). Every supabaseRest()
 * failure and every caught exception should call this.
 * ==========================================================================*/
function appLog(string $level, string $msg, array $ctx = []): void {
    $entry = '[' . date('Y-m-d H:i:s') . '] [' . strtoupper($level) . '] ' . $msg;
    if ($ctx) $entry .= ' ' . json_encode($ctx, JSON_UNESCAPED_SLASHES);
    error_log($entry);
}

/**
 * Simple file cache for GET requests (5 second TTL)
 */
function cacheGet(string $key): ?array {
    $file = sys_get_temp_dir() . '/ddcet_' . md5($key) . '.json';
    if (file_exists($file) && (time() - filemtime($file)) < 10) {
        $data = json_decode(file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }
    return null;
}
function cacheSet(string $key, array $data): void {
    $file = sys_get_temp_dir() . '/ddcet_' . md5($key) . '.json';
    file_put_contents($file, json_encode($data));
}

/**
 * Persistent curl handle, reused across every supabaseRest() call in a request.
 * Reusing one handle keeps the TCP + TLS connection to Supabase alive (HTTP
 * keep-alive), so only the FIRST call per page pays the handshake cost — the
 * rest reuse the open connection. This is the single biggest page-load win.
 */
function supabaseHandle() {
    static $ch = null;
    if ($ch === null) $ch = curl_init();
    return $ch;
}

/**
 * Make a Supabase REST API request
 */
function supabaseRest(string $path, string $method = 'GET', ?array $data = null, array $extra = []): ?array {
    // Cache GET requests (unless the caller needs a guaranteed-fresh read, e.g.
    // live-duel polling and read-after-write, where a 10s stale cache is wrong).
    $noCache = !empty($extra['no_cache']);
    if ($method === 'GET' && !$noCache) {
        $cached = cacheGet($path);
        if ($cached !== null) return $cached;
    }

    $url = SUPABASE_URL . '/rest/v1/' . ltrim($path, '/');
    $headers = [
        'apikey: ' . SUPABASE_KEY,
        'Authorization: Bearer ' . SUPABASE_KEY,
        'Content-Type: application/json',
    ];
    // BUG-006 fix: merge Prefer directives into a single header to avoid
    // duplication (some proxies/edge functions only read the last Prefer header).
    $preferParts = [];
    if (in_array($method, ['POST', 'PATCH', 'PUT'])) {
        $preferParts[] = 'return=representation';
    }
    if (!empty($extra['prefer'])) {
        // The extra may already contain return=representation; deduplicate.
        foreach (explode(',', $extra['prefer']) as $p) {
            $p = trim($p);
            if ($p !== '' && !in_array($p, $preferParts)) $preferParts[] = $p;
        }
    }
    if ($preferParts) {
        $headers[] = 'Prefer: ' . implode(', ', $preferParts);
    }

    // Reuse the persistent handle; curl_reset() clears per-call options but keeps
    // the live connection in curl's pool so keep-alive still applies.
    $ch = supabaseHandle();
    curl_reset($ch);
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TCP_KEEPALIVE => 1,
        CURLOPT_DNS_CACHE_TIMEOUT => 300,
        CURLOPT_TCP_NODELAY => 1,
        CURLOPT_ENCODING => '',   // accept gzip/deflate — smaller, faster transfers
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($data !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // NOTE: intentionally NOT curl_close()'d — the handle is reused next call.

    if ($httpCode >= 400) {
        appLog('error', 'supabaseRest failed', ['path' => $path, 'method' => $method, 'http' => $httpCode]);
        return null;
    }
    $decoded = json_decode($response, true);
    $result = is_array($decoded) ? $decoded : [];

    // Cache successful GET responses
    if ($method === 'GET' && !$noCache) cacheSet($path, $result);

    return $result;
}

/**
 * Execute multiple GET requests concurrently using curl_multi.
 * Accepts an associative array: ['key1' => 'path1', 'key2' => 'path2']
 * Returns an associative array of results with the same keys.
 * Uses a persistent pool of cURL handles to maintain HTTP keep-alive across requests.
 */
function supabaseRestMulti(array $requests, array $extra = []): array {
    $results = [];
    $noCache = !empty($extra['no_cache']);
    $pending = [];
    
    foreach ($requests as $key => $path) {
        if (!$noCache) {
            $cached = cacheGet($path);
            if ($cached !== null) {
                $results[$key] = $cached;
                continue;
            }
        }
        $pending[$key] = $path;
    }
    
    if (empty($pending)) return $results;
    
    static $multi = null;
    static $pool = [];
    if ($multi === null) $multi = curl_multi_init();
    
    while (count($pool) < count($pending)) {
        $pool[] = curl_init();
    }
    
    $handles = [];
    $poolIdx = 0;
    foreach ($pending as $key => $path) {
        $ch = $pool[$poolIdx++];
        curl_reset($ch);
        $url = SUPABASE_URL . '/rest/v1/' . ltrim($path, '/');
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . SUPABASE_KEY,
                'Authorization: Bearer ' . SUPABASE_KEY,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TCP_KEEPALIVE => 1,
            CURLOPT_DNS_CACHE_TIMEOUT => 300,
            CURLOPT_TCP_NODELAY => 1,
            CURLOPT_ENCODING => '',
        ]);
        curl_multi_add_handle($multi, $ch);
        $handles[$key] = ['ch' => $ch, 'path' => $path];
    }
    
    $running = null;
    do {
        curl_multi_exec($multi, $running);
        curl_multi_select($multi);
    } while ($running > 0);
    
    foreach ($handles as $key => $meta) {
        $ch = $meta['ch'];
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $response = curl_multi_getcontent($ch);
        
        if ($httpCode >= 400 || $response === false) {
            appLog('error', 'supabaseRestMulti failed', ['path' => $meta['path'], 'http' => $httpCode]);
            $results[$key] = null;
        } else {
            $decoded = json_decode($response, true);
            $res = is_array($decoded) ? $decoded : [];
            if (!$noCache) cacheSet($meta['path'], $res);
            $results[$key] = $res;
        }
        curl_multi_remove_handle($multi, $ch);
    }
    
    return $results;
}



/**
 * Count rows matching a PostgREST filter WITHOUT transferring them. Uses
 * Prefer: count=exact and reads the total from the Content-Range header, so
 * "how many students rank above me?" is one tiny request instead of pulling
 * tens of thousands of rows just to count() them in PHP. Returns null on error.
 * Cached like GETs (~10s) unless $extra['no_cache'] is set.
 */
function supabaseCount(string $path, array $extra = []): ?int {
    $sep = strpos($path, '?') === false ? '?' : '&';
    $reqPath = $path . $sep . 'select=id&limit=1';
    $cacheKey = 'count:' . $reqPath;

    if (empty($extra['no_cache'])) {
        $cached = cacheGet($cacheKey);
        if ($cached !== null) return (int)($cached['count'] ?? 0);
    }

    // BUG-012 fix: use a dedicated persistent handle for count queries instead
    // of curl_init()+curl_close() every call (saves TLS handshake on repeated
    // calls within the same request — dashboard calls this 3-4 times).
    static $countCh = null;
    if ($countCh === null) $countCh = curl_init();
    $url = SUPABASE_URL . '/rest/v1/' . ltrim($reqPath, '/');
    curl_reset($countCh);
    curl_setopt_array($countCh, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,   // we need the Content-Range response header
        CURLOPT_NOBODY => false,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_KEY,
            'Authorization: Bearer ' . SUPABASE_KEY,
            'Prefer: count=exact',
        ],
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TCP_KEEPALIVE => 1,
        CURLOPT_DNS_CACHE_TIMEOUT => 300,
        CURLOPT_ENCODING => '',
    ]);
    $response = curl_exec($countCh);
    $httpCode = curl_getinfo($countCh, CURLINFO_HTTP_CODE);
    // NOTE: intentionally NOT curl_close()'d — reused next call.

    if ($response === false || $httpCode >= 400) return null;

    // Content-Range looks like "0-0/3573" (or "*/3573" when no rows match).
    if (preg_match('#content-range:\s*[\d*\-]+/(\d+)#i', $response, $m)) {
        $count = (int)$m[1];
        cacheSet($cacheKey, ['count' => $count]);
        return $count;
    }
    return null;
}

/**
 * Execute multiple GET requests in parallel — returns array of results in same order
 */
function supabaseMulti(array $paths): array {
    $mh = curl_multi_init();
    $handles = [];

    foreach ($paths as $i => $path) {
        $url = SUPABASE_URL . '/rest/v1/' . ltrim($path, '/');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['apikey: ' . SUPABASE_KEY, 'Authorization: Bearer ' . SUPABASE_KEY],
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[$i] = $ch;
    }

    $running = null;
    do { curl_multi_exec($mh, $running); curl_multi_select($mh); } while ($running > 0);

    $results = [];
    foreach ($handles as $i => $ch) {
        $response = curl_multi_getcontent($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode >= 400) {
            appLog('error', 'supabaseMulti failed', ['http' => $httpCode, 'response' => $response]);
            $results[$i] = [];
        } else {
            $decoded = json_decode($response, true);
            $results[$i] = is_array($decoded) ? $decoded : [];
            // If it decoded to an associative array with an error (e.g. Supabase returned 200 with an error object, though rare), ensure it's a list if it's meant to be a list? 
            // Most endpoints return lists. If it's associative and not empty, and lacks 'message' or 'error', we'll just keep it.
            // A safer approach: if isset($decoded['message']) or isset($decoded['error']) it's an error object.
            if (isset($results[$i]['message']) || isset($results[$i]['error'])) {
                $results[$i] = [];
            }
        }
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    return $results;
}

/**
 * PDO-compatible statement that works via Supabase REST API
 */
class SupaStatement {
    private string $sql;
    private array $params = [];
    private array $result = [];

    public function __construct(string $sql) { $this->sql = $sql; }

    public function execute(array $params = []): bool {
        $this->params = $params;
        $this->result = $this->run();
        return true;
    }

    public function fetch($mode = PDO::FETCH_ASSOC): array|false {
        if (empty($this->result)) return false;
        return array_shift($this->result) ?: false;
    }

    public function fetchAll($mode = PDO::FETCH_ASSOC): array {
        if ($mode === PDO::FETCH_COLUMN) {
            return array_map(fn($row) => reset($row), $this->result);
        }
        return $this->result;
    }

    public function fetchColumn($col = 0): mixed {
        if (empty($this->result)) return false;
        $row = array_shift($this->result);
        if (!$row) return false;
        $values = array_values($row);
        return $values[$col] ?? false;
    }

    private function run(): array {
        $sql = trim($this->sql);
        // Substitute params into SQL for parsing
        $resolved = $sql;
        foreach ($this->params as $k => $v) {
            $resolved = str_replace($k, is_null($v) ? 'NULL' : (is_int($v) || is_float($v) ? (string)$v : "'" . addslashes((string)$v) . "'"), $resolved);
        }

        // Detect operation type
        if (preg_match('/^SELECT\s+COUNT\s*\(\s*\*?\s*\)/i', $resolved)) {
            return $this->handleCount($resolved);
        }
        if (preg_match('/^SELECT\s+/i', $resolved)) {
            return $this->handleSelect($resolved);
        }
        if (preg_match('/^INSERT\s+/i', $resolved)) {
            return $this->handleInsert($resolved);
        }
        if (preg_match('/^UPDATE\s+/i', $resolved)) {
            return $this->handleUpdate($resolved);
        }
        if (preg_match('/^DELETE\s+/i', $resolved)) {
            return $this->handleDelete($resolved);
        }
        return [];
    }

    private function handleCount(string $sql): array {
        // Extract table and WHERE from: SELECT COUNT(*) FROM table WHERE ...
        if (!preg_match('/FROM\s+(\w+)(?:\s+(?:\w+\s+)?WHERE\s+(.+?))?(?:\s+(?:GROUP|ORDER|LIMIT)|$)/is', $sql, $m)) {
            return [['count' => 0]];
        }
        $table = $m[1];
        $where = $m[2] ?? '';
        $filter = $this->whereToFilter($where);
        $path = $table . '?select=count';
        if ($filter) $path .= '&' . $filter;
        
        $url = SUPABASE_URL . '/rest/v1/' . $path;
        $headers = [
            'apikey: ' . SUPABASE_KEY,
            'Authorization: Bearer ' . SUPABASE_KEY,
            'Prefer: count=exact',
            'Content-Type: application/json',
        ];
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 15, CURLOPT_NOBODY => false]);
        $response = curl_exec($ch);
        curl_close($ch);
        
        // Parse content-range header for count
        if (preg_match('/content-range:\s*(?:\d*-?\d*|\*)\/(\d+)/i', $response, $rm)) {
            return [['count' => (int)$rm[1]]];
        }
        // Fallback: count the returned rows
        $body = substr($response, strpos($response, "\r\n\r\n") + 4);
        $data = json_decode($body, true);
        return [['count' => is_array($data) ? count($data) : 0]];
    }

    private function handleSelect(string $sql): array {
        // Parse: SELECT columns FROM table [JOIN...] [WHERE ...] [ORDER BY ...] [LIMIT ...]
        // Simple single-table queries
        if (!preg_match('/FROM\s+(\w+)/i', $sql, $m)) return [];
        $table = $m[1];
        
        // Detect select columns
        $select = '*';
        if (preg_match('/^SELECT\s+(.+?)\s+FROM/is', $sql, $sm)) {
            $cols = trim($sm[1]);
            if ($cols !== '*') {
                // Handle aggregate functions - just pass through simple columns
                if (!preg_match('/\b(COUNT|AVG|SUM|MAX|MIN|COALESCE|CASE)\b/i', $cols)) {
                    $select = $cols;
                }
            }
        }

        // Handle JOINs by requesting foreign key relations
        $joinSelect = '';
        if (preg_match_all('/JOIN\s+(\w+)\s+\w*\s*ON\s+\w+\.(\w+)\s*=\s*\w+\.(\w+)/i', $sql, $jm, PREG_SET_ORDER)) {
            foreach ($jm as $join) {
                $joinTable = $join[1];
                $joinSelect .= ',' . $joinTable . '(*)';
            }
        }

        $where = '';
        if (preg_match('/WHERE\s+(.+?)(?:\s+(?:GROUP|ORDER|LIMIT|RETURNING)|$)/is', $sql, $wm)) {
            $where = trim($wm[1]);
        }
        $filter = $this->whereToFilter($where);

        $order = '';
        if (preg_match('/ORDER\s+BY\s+(.+?)(?:\s+LIMIT|$)/is', $sql, $om)) {
            $order = $this->orderToParam(trim($om[1]));
        }

        $limit = '';
        if (preg_match('/LIMIT\s+(\d+)/i', $sql, $lm)) {
            $limit = 'limit=' . $lm[1];
        }

        $path = $table . '?select=' . urlencode($select . $joinSelect);
        if ($filter) $path .= '&' . $filter;
        if ($order) $path .= '&' . $order;
        if ($limit) $path .= '&' . $limit;

        $result = supabaseRest($path);
        return $result ?? [];
    }

    private function handleInsert(string $sql): array {
        // INSERT INTO table (cols) VALUES (vals) [RETURNING ...]
        if (!preg_match('/INSERT\s+INTO\s+(\w+)/i', $sql, $m)) return [];
        $table = $m[1];

        // Use params directly
        $data = [];
        foreach ($this->params as $k => $v) {
            $col = ltrim($k, ':');
            $data[$col] = $v;
        }
        
        // If ON CONFLICT, use upsert
        $extra = [];
        if (preg_match('/ON\s+CONFLICT\s*\(([^)]+)\)\s*DO\s+UPDATE/i', $sql, $cm)) {
            $extra['prefer'] = 'return=representation,resolution=merge-duplicates';
        } elseif (preg_match('/ON\s+CONFLICT.*DO\s+NOTHING/i', $sql)) {
            $extra['prefer'] = 'return=representation,resolution=ignore-duplicates';
        }

        $result = supabaseRest($table, 'POST', $data, $extra);
        return $result ?? [];
    }

    private function handleUpdate(string $sql): array {
        // UPDATE table SET col=val WHERE ...
        if (!preg_match('/UPDATE\s+(\w+)\s+SET\s+(.+?)\s+WHERE\s+(.+?)(?:\s+RETURNING|$)/is', $sql, $m)) return [];
        $table = $m[1];
        $where = trim($m[3]);
        $filter = $this->whereToFilter($where);

        // Build update data from params
        $setClause = $m[2];
        $data = [];
        // Parse SET col = :param patterns
        if (preg_match_all('/(\w+)\s*=\s*:(\w+)/i', $setClause, $sets, PREG_SET_ORDER)) {
            foreach ($sets as $s) {
                $col = $s[1];
                $param = ':' . $s[2];
                if (isset($this->params[$param])) {
                    $data[$col] = $this->params[$param];
                }
            }
        }
        
        // Handle SET col = value (literal)
        if (preg_match_all("/(\w+)\s*=\s*(?:TRUE|FALSE|NULL|CURRENT_DATE|NOW\(\))/i", $setClause, $lits, PREG_SET_ORDER)) {
            foreach ($lits as $l) {
                $val = strtoupper($l[0]);
                if (str_contains($val, 'TRUE')) $data[$l[1]] = true;
                elseif (str_contains($val, 'FALSE')) $data[$l[1]] = false;
                elseif (str_contains($val, 'NULL')) $data[$l[1]] = null;
                elseif (str_contains($val, 'CURRENT_DATE')) $data[$l[1]] = date('Y-m-d');
                elseif (str_contains($val, 'NOW()')) $data[$l[1]] = date('c');
            }
        }

        // BUG-021 fix: actually perform the increment by fetching the current row first.
        // The REST API doesn't support atomic increments, so we read-modify-write.
        if (preg_match_all('/(\w+)\s*=\s*\w+\s*\+\s*:(\w+)/i', $setClause, $incs, PREG_SET_ORDER)) {
            $current = supabaseRest($table . '?' . $filter . '&select=*&limit=1');
            if (!empty($current[0])) {
                foreach ($incs as $i) {
                    $col = $i[1];
                    $param = ':' . $i[2];
                    if (isset($this->params[$param])) {
                        $data[$col] = (float)($current[0][$col] ?? 0) + (float)$this->params[$param];
                    }
                }
            }
        }

        if (empty($data) || empty($filter)) return [];
        $result = supabaseRest($table . '?' . $filter, 'PATCH', $data);
        return $result ?? [];
    }

    private function handleDelete(string $sql): array {
        if (!preg_match('/DELETE\s+FROM\s+(\w+)\s+WHERE\s+(.+)/is', $sql, $m)) return [];
        $table = $m[1];
        $filter = $this->whereToFilter(trim($m[2]));
        if (!$filter) return [];
        supabaseRest($table . '?' . $filter, 'DELETE');
        return [];
    }

    private function parseCond(string $cond): string {
        $cond = trim($cond);
        if (empty($cond)) return '';
        if (preg_match("/^(\w+)\s*=\s*'([^']+)'/", $cond, $m)) return $m[1] . '=eq.' . $m[2];
        if (preg_match('/^(\w+)\s*=\s*(\d+)/', $cond, $m)) return $m[1] . '=eq.' . $m[2];
        if (preg_match('/^(\w+)\s*=\s*TRUE/i', $cond, $m)) return $m[1] . '=eq.true';
        if (preg_match('/^(\w+)\s*=\s*FALSE/i', $cond, $m)) return $m[1] . '=eq.false';
        if (preg_match("/^(\w+)\s*>\s*'([^']+)'/", $cond, $m)) return $m[1] . '=gt.' . $m[2];
        if (preg_match("/^(\w+)\s*>\s*NOW\(\)/i", $cond, $m)) return $m[1] . '=gt.' . date('c');
        if (preg_match('/^NOT\s+(\w+)/i', $cond, $m)) return $m[1] . '=eq.false';
        if (preg_match("/^(\w+)\s*!=\s*(\d+)/", $cond, $m)) return $m[1] . '=neq.' . $m[2];
        if (preg_match('/^(\w+)\s+IS\s+NULL/i', $cond, $m)) return $m[1] . '=is.null';
        if (preg_match('/^(\w+)\s+IS\s+NOT\s+NULL/i', $cond, $m)) return $m[1] . '=not.is.null';
        if (preg_match("/^(\w+)\.(\w+)\s*=\s*'([^']+)'/", $cond, $m)) return $m[2] . '=eq.' . $m[3];
        return '';
    }

    private function whereToFilter(string $where): string {
        if (empty($where)) return '';
        
        // BUG-020 fix: parse OR blocks like `(col = 'val' OR col = 'val2')`
        // into PostgREST syntax: `or=(col.eq.val,col.eq.val2)`
        if (preg_match_all('/\(([^)]+)\)/', $where, $orBlocks, PREG_OFFSET_CAPTURE)) {
            $parts = [];
            $lastIdx = 0;
            foreach ($orBlocks[0] as $i => $match) {
                $blockStr = $match[0];
                $blockOffset = $match[1];
                $inner = $orBlocks[1][$i][0];
                
                // Parse the AND conditions before this OR block
                $pre = trim(substr($where, $lastIdx, $blockOffset - $lastIdx));
                if (str_ends_with(strtoupper($pre), ' AND')) $pre = substr($pre, 0, -4);
                if ($pre) {
                    foreach (preg_split('/\s+AND\s+/i', $pre) as $cond) {
                        $p = $this->parseCond($cond);
                        if ($p) $parts[] = $p;
                    }
                }
                
                // Parse the OR conditions inside the block
                $orParts = [];
                foreach (preg_split('/\s+OR\s+/i', $inner) as $cond) {
                    $p = $this->parseCond($cond);
                    if ($p) $orParts[] = $p;
                }
                if ($orParts) {
                    $parts[] = 'or=(' . implode(',', $orParts) . ')';
                }
                
                $lastIdx = $blockOffset + strlen($blockStr);
            }
            
            // Parse any trailing AND conditions
            $post = trim(substr($where, $lastIdx));
            if (str_starts_with(strtoupper($post), 'AND ')) $post = substr($post, 4);
            if ($post) {
                foreach (preg_split('/\s+AND\s+/i', $post) as $cond) {
                    $p = $this->parseCond($cond);
                    if ($p) $parts[] = $p;
                }
            }
            
            return implode('&', $parts);
        }

        // Simple approach without OR blocks
        $parts = [];
        foreach (preg_split('/\s+AND\s+/i', $where) as $cond) {
            $p = $this->parseCond($cond);
            if ($p) $parts[] = $p;
        }
        return implode('&', $parts);
    }

    private function orderToParam(string $orderClause): string {
        $parts = [];
        $items = explode(',', $orderClause);
        foreach ($items as $item) {
            $item = trim($item);
            if (preg_match('/^(\w+)(?:\s+(ASC|DESC))?/i', $item, $m)) {
                $dir = strtolower($m[2] ?? 'asc');
                $parts[] = $m[1] . '.' . $dir;
            }
        }
        return $parts ? 'order=' . implode(',', $parts) : '';
    }
}

/**
 * PDO-like database class using Supabase REST API
 */
class SupabaseDB {
    public function prepare(string $sql, array $options = []): SupaStatement {
        return new SupaStatement($sql);
    }

    public function query(string $sql): SupaStatement {
        $stmt = new SupaStatement($sql);
        $stmt->execute();
        return $stmt;
    }
}

function getDB(): SupabaseDB {
    static $db = null;
    if ($db === null) $db = new SupabaseDB();
    return $db;
}

// Auth helpers
function currentUser(): ?array {
    if (TEST_MODE && empty($_SESSION['user'])) {
        $_SESSION['user'] = [
            'id' => 1,
            'google_id' => 'test123',
            'email' => 'test@example.com',
            'name' => 'Test Student',
            'avatar_url' => 'https://ui-avatars.com/api/?name=Test+Student&background=f0a500&color=1a1a1a',
            'college_id' => 1,
            'onboarded' => true,
            'xp' => 1250,
            'level' => 'Silver',
            'streak' => 7,
            'referral_code' => 'TEST1234',
            'is_admin' => true,
            'is_banned' => false,
        ];
    }
    
    // Auto-login via Remember Me cookie
    if (empty($_SESSION['user']) && !empty($_COOKIE['ddcet_remember'])) {
        $token = $_COOKIE['ddcet_remember'];
        $hash = hash('sha256', $token);
        
        $users = supabaseRest('students?remember_token=eq.' . $hash . '&select=*&limit=1');
        if (!empty($users[0])) {
            if (empty($users[0]['is_banned'])) {
                $_SESSION['user'] = $users[0];
                $_SESSION['_user_refreshed_at'] = time();
            } else {
                setcookie('ddcet_remember', '', time() - 3600, '/', '', true, true);
            }
        }
    }
    
    // Mobile app authentication via header
    if (empty($_SESSION['user']) && !empty($_SERVER['HTTP_X_STUDENT_ID'])) {
        $sid = (int) $_SERVER['HTTP_X_STUDENT_ID'];
        $users = supabaseRest('students?id=eq.' . $sid . '&select=*&limit=1');
        if (!empty($users[0]) && empty($users[0]['is_banned'])) {
            return $users[0];
        }
    }
    
    return $_SESSION['user'] ?? null;
}

function requireAuth(): array {
    $user = currentUser();
    if (!$user) {
        // Remember where the guest was headed so we can send them back after
        // a successful login (used by auth/callback.php).
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            $_SESSION['post_login_redirect'] = $_SERVER['REQUEST_URI'] ?? null;
        }
        header('Location: ' . BASE_PATH . 'auth/login.php');
        exit;
    }

    // BUG-007 fix: periodically refresh the session from the DB (every 5 min)
    // so profile changes, bans, XP updates, and admin grants take effect
    // without requiring a re-login.
    $lastRefresh = $_SESSION['_user_refreshed_at'] ?? 0;
    if (!TEST_MODE && (time() - $lastRefresh) > 300) {
        $fresh = supabaseRest('students?id=eq.' . (int)$user['id'] . '&select=*&limit=1');
        if (!empty($fresh[0])) {
            // Check ban immediately.
            if (!empty($fresh[0]['is_banned'])) {
                session_destroy();
                header('Location: ' . BASE_PATH . 'auth/login.php?error=banned');
                exit;
            }
            // Preserve admin flag (comes from the admins table, set at login).
            $fresh[0]['is_admin'] = $user['is_admin'] ?? false;
            $_SESSION['user'] = $fresh[0];
            $user = $fresh[0];
        }
        $_SESSION['_user_refreshed_at'] = time();
    }

    return $user;
}

/**
 * Gate admin pages. By default this also enforces email-based two-factor
 * authentication: an admin who hasn't entered the emailed OTP this session is
 * sent to the verification page. That page calls requireAdmin(false) to avoid
 * a redirect loop.
 */
function requireAdmin(bool $enforce2FA = true): array {
    $user = requireAuth();
    if (!TEST_MODE && empty($user['is_admin'])) {
        http_response_code(403);
        exit('Forbidden');
    }
    if ($enforce2FA && !TEST_MODE && empty($_SESSION['admin_2fa_ok'])) {
        redirect('admin/2fa-verify.php');
    }
    return $user;
}

/* ============================================================================
 * Institution (B2B) layer — see database/institutions.sql
 *
 * An "institution" is a normal student account with is_institution = TRUE.
 * It owns an `organizations` row and runs a private batch: its own questions
 * (always attached to org-owned tests so they never leak into the public
 * pool), its own assignments, and analytics scoped to its own students.
 * ==========================================================================*/

/** Gate the institution portal. Mirrors requireAdmin() but on is_institution. */
function requireInstitution(): array {
    $user = requireAuth();
    if (!TEST_MODE && empty($user['is_institution'])) {
        http_response_code(403);
        exit('Forbidden — this area is for partner institutions only.');
    }
    return $user;
}

/**
 * The organization the current user belongs to (owner or member), or null.
 * Reads org_id off the session row (kept fresh by login / org creation).
 */
function currentOrg(): ?array {
    $user = currentUser();
    if (empty($user['org_id'])) return null;
    $org = supabaseRest('organizations?id=eq.' . (int) $user['org_id'] . '&select=*&limit=1');
    return $org[0] ?? null;
}

/**
 * Resolve a college discount coupon code to its college row, or null if the
 * code is unknown, the discount is disabled, or the redemption cap is spent.
 *
 * Possession of the code IS the verification — the college distributes it only
 * to its own students. A self-reported students.college_id grants nothing; only
 * a valid code does. Used by both api/create_order.php (authoritative) and
 * api/validate_coupon.php (preview), so the rules live in exactly one place.
 */
function resolveCollegeCoupon(string $code): ?array {
    $code = strtoupper(trim($code));
    if ($code === '') return null;
    $rows = supabaseRest('colleges?discount_code=eq.' . urlencode($code)
        . '&select=id,name,discount_percent,discount_max_redemptions,discount_redemptions&limit=1');
    $c = $rows[0] ?? null;
    if (!$c) return null;
    $pct = (int) ($c['discount_percent'] ?? 0);
    if ($pct < 1 || $pct > 100) return null;
    $cap = $c['discount_max_redemptions']; // null/'' = unlimited
    $used = (int) ($c['discount_redemptions'] ?? 0);
    if (!($cap === null || $cap === '' || $used < (int) $cap)) return null;
    return $c;
}

/* ============================================================================
 * Challenge a Friend (head-to-head duel) helpers
 *
 * A duel reuses an ordinary pool mode (rapid_fire / subject_wise / full_mock)
 * for its questions, scoring, and timer. The duel attempts therefore store that
 * UNDERLYING mode (so submit_exam.php timing/percentile work unchanged) and are
 * marked as duels by attempts.challenge_id, not by a special mode string.
 * ========================================================================== */

/** Modes a player may pick when challenging a friend, with display labels. */
function challengeModes(): array {
    return [
        'rapid_fire'   => 'Rapid Fire · 30Q / 30 min',
        'subject_wise' => 'Subject Practice · 30Q / 30 min',
        'full_mock'    => 'Full Mock · 100Q / 150 min',
    ];
}

/**
 * The official GTU DDCET per-subject question counts for paper-style pool modes
 * (syllabus published 18.01.2024). SINGLE SOURCE OF TRUTH — used by exam.php's
 * $poolConfigs and by challengeQuestionIds() so a duel never silently diverges
 * from a normal exam of the same mode. Returns null for modes with no fixed
 * subject split (rapid_fire, subject_wise, etc.).
 */
function ddcetPoolDistribution(string $mode): ?array {
    $map = [
        'full_mock'  => ['Physics' => 30, 'Chemistry' => 10, 'Computers' => 5,
                         'Environment' => 5, 'Maths' => 25, 'English' => 25], // 100Q
        'be01_paper' => ['Physics' => 30, 'Chemistry' => 10, 'Computers' => 5,
                         'Environment' => 5],                                 // 50Q
        'be02_paper' => ['Maths' => 25, 'English' => 25],                     // 50Q
    ];
    return $map[$mode] ?? null;
}

/**
 * Pin the shared question set for a duel, SERVER-SIDE, mirroring the pool logic
 * exam.php uses for the same mode. Returns an ordered array of question ids
 * (shuffled once here, then identical for both players). Empty array = the pool
 * didn't have enough questions, caller should refuse to start the duel.
 */
function challengeQuestionIds(string $mode, ?string $subject = null): array {
    $counts = ['rapid_fire' => 30, 'subject_wise' => 30, 'full_mock' => 100];
    $total = $counts[$mode] ?? 30;

    if ($mode === 'full_mock') {
        // Mirror exam.php's per-subject distribution for a full mock (shared source).
        $subjectConfig = ddcetPoolDistribution('full_mock');
        $ids = [];
        foreach ($subjectConfig as $sub => $count) {
            $rows = supabaseRest('questions?test_id=is.null&subject=eq.' . urlencode($sub) . '&select=id&limit=500') ?? [];
            shuffle($rows);
            foreach (array_slice($rows, 0, $count) as $r) $ids[] = (int) $r['id'];
        }
        shuffle($ids);
        return $ids;
    }

    if ($mode === 'subject_wise' && $subject) {
        $rows = supabaseRest('questions?test_id=is.null&subject=eq.' . urlencode($subject) . '&select=id&limit=500') ?? [];
    } else {
        $rows = supabaseRest('questions?test_id=is.null&select=id&limit=1000') ?? [];
    }
    shuffle($rows);
    return array_map(fn($r) => (int) $r['id'], array_slice($rows, 0, $total));
}

/**
 * Lazily resolve invite/lobby/duel timeouts on read. There is no cron, so a
 * stale row is resolved the moment anyone observes it past its deadline:
 *   pending  past expires_at         -> expired
 *   accepted past lobby_deadline     -> cancelled (opponent never joined)
 *   live     past start+duration+grace -> completed/cancelled (player abandoned;
 *       without this an abandoned duel would hang the opponent's result page
 *       forever). A submitted score beats a missing one; both missing -> cancelled.
 * Mutates the DB and returns the (possibly new) status string. The row must come
 * from a select=* read (needs mode, started_at, and both scores).
 */
function challengeResolveTimeouts(array $ch): string {
    $status = $ch['status'] ?? '';
    $now = time();
    if ($status === 'pending' && !empty($ch['expires_at']) && strtotime($ch['expires_at']) < $now) {
        supabaseRest('challenges?id=eq.' . (int) $ch['id'], 'PATCH', ['status' => 'expired']);
        return 'expired';
    }
    if ($status === 'accepted' && !empty($ch['lobby_deadline']) && strtotime($ch['lobby_deadline']) < $now) {
        supabaseRest('challenges?id=eq.' . (int) $ch['id'], 'PATCH', ['status' => 'cancelled']);
        return 'cancelled';
    }
    if ($status === 'live' && !empty($ch['started_at']) && !empty($ch['mode'])) {
        $grace = 120; // allow for the timer's auto-submit + network before we step in
        $deadline = strtotime($ch['started_at']) + modeDurationMinutes($ch['mode']) * 60 + $grace;
        if ($now > $deadline) {
            $cs = $ch['challenger_score']; $os = $ch['opponent_score'];
            if ($cs === null && $os === null) {
                // Both abandoned — nothing to score.
                supabaseRest('challenges?id=eq.' . (int) $ch['id'] . '&status=eq.live', 'PATCH', ['status' => 'cancelled']);
                return 'cancelled';
            }
            // A missing score loses to any real one (incl. 0, which means "played").
            $csv = $cs === null ? -1.0 : (float) $cs;
            $osv = $os === null ? -1.0 : (float) $os;
            $winnerId = challengeWinnerId($csv, $osv, (int) $ch['challenger_id'], (int) $ch['opponent_id']);
            // CAS on status=live so this races safely with a late submit_exam.
            supabaseRest('challenges?id=eq.' . (int) $ch['id'] . '&status=eq.live', 'PATCH', [
                'status' => 'completed', 'winner_id' => $winnerId,
            ]);
            return 'completed';
        }
    }
    return $status;
}

/**
 * Fetch a challenge row by id, ALWAYS fresh (duel logic is race- and
 * read-after-write-sensitive, so it must never be served from the GET cache).
 * Centralised so no caller forgets the no_cache flag. Returns null if not found.
 */
function challengeLoad(int $challengeId): ?array {
    $rows = supabaseRest('challenges?id=eq.' . $challengeId . '&select=*&limit=1', 'GET', null, ['no_cache' => true]);
    return $rows[0] ?? null;
}

/**
 * Decide a duel winner from two final scores. Returns the winning student id, or
 * null for a tie. Callers resolve null/missing scores to a numeric value first
 * (e.g. an unsubmitted side -> -1) so this stays a pure comparison — one source
 * of truth for the "higher score wins" rule.
 */
function challengeWinnerId(float $challengerScore, float $opponentScore, int $challengerId, int $opponentId): ?int {
    if ($challengerScore > $opponentScore) return $challengerId;
    if ($opponentScore > $challengerScore) return $opponentId;
    return null;
}

/**
 * Reorder fetched rows to match a desired id sequence. PostgREST's `id=in.(...)`
 * filter ignores the order of the in-list, so callers that need a specific order
 * (e.g. a pinned/seeded question sequence) must re-sort client-side. Rows whose
 * id isn't in $ids are dropped; ids with no matching row are skipped.
 */
function orderRowsByIds(array $rows, array $ids, string $idKey = 'id'): array {
    $byId = [];
    foreach ($rows as $r) $byId[(int) $r[$idKey]] = $r;
    $out = [];
    foreach ($ids as $id) if (isset($byId[(int) $id])) $out[] = $byId[(int) $id];
    return $out;
}

/** Is the given user one of the two players in this challenge row? */
function challengeRole(array $ch, int $userId): ?string {
    if ((int) $ch['challenger_id'] === $userId) return 'challenger';
    if ((int) $ch['opponent_id'] === $userId) return 'opponent';
    return null;
}

/** A short, unambiguous join code (no easily-confused chars like O/0, I/1). */
function generateJoinCode(int $len = 6): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < $len; $i++) {
        $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $code;
}

function isSubscribed(?string $plan = null): bool {
    if (TEST_MODE) return true;
    $user = currentUser();
    if (!$user) return false;
    $filter = 'student_id=eq.' . $user['id'] . '&status=eq.active&expires_at=gt.' . urlencode(date('c'));
    if ($plan) $filter .= '&plan=eq.' . $plan;
    $result = supabaseRest('subscriptions?' . $filter . '&limit=1');
    return !empty($result);
}

function getSubscription(): ?array {
    if (TEST_MODE) return ['id' => 1, 'plan' => 'pro', 'status' => 'active', 'expires_at' => subscriptionExpiryDate()];
    $user = currentUser();
    if (!$user) return null;
    $filter = 'student_id=eq.' . $user['id'] . '&status=eq.active&expires_at=gt.' . urlencode(date('c')) . '&order=expires_at.desc&limit=1';
    $result = supabaseRest('subscriptions?' . $filter);
    return !empty($result) ? $result[0] : null;
}

/**
 * Subscriptions follow the DDCET exam cycle: every paid plan is valid until the
 * coming 30 June (23:59:59 IST). Buying in, say, May yields ~1 month; buying in
 * July rolls to next year's 30 June. Returns an ISO-8601 timestamp for the
 * subscriptions.expires_at column. (Topper rewards and admin grants intentionally
 * stay duration-based — they are not the paid annual plan.)
 */
function subscriptionExpiryDate(): string {
    $y = (int) date('Y');
    $june30 = mktime(23, 59, 59, 6, 30, $y);
    if (time() > $june30) $june30 = mktime(23, 59, 59, 6, 30, $y + 1); // past this year -> next cycle
    return date('c', $june30);
}

/**
 * Every non-subscribed user gets ONE free full mock (100Q) to try the platform.
 * Eligible while they have no COMPLETED full_mock attempt — the pool-mode resume
 * logic in exam.php means a refresh resumes the in-progress mock rather than
 * spawning a new one, so this can't be farmed, and finishing it ends eligibility.
 */
function hasFreeMock(?array $user = null): bool {
    $user = $user ?? currentUser();
    if (!$user || isSubscribed()) return false;
    $done = supabaseRest('attempts?student_id=eq.' . (int) $user['id']
        . '&mode=eq.full_mock&status=eq.completed&select=id&limit=1');
    return empty($done);
}

/* ============================================================================
 * Email (Brevo transactional) — branded "DDCET Prep" sender.
 * sendEmail() no-ops until BREVO_API_KEY + MAIL_FROM_EMAIL are configured, so
 * the rest of the app works whether or not email is set up.
 * ========================================================================== */

/** Send one transactional email. Returns true on a 2xx from Brevo. */
function sendEmail(string $toEmail, string $toName, string $subject, string $htmlContent): bool {
    if (BREVO_API_KEY === '' || MAIL_FROM_EMAIL === '' || $toEmail === '') return false;
    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['api-key: ' . BREVO_API_KEY, 'Content-Type: application/json', 'accept: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'sender' => ['name' => MAIL_FROM_NAME, 'email' => MAIL_FROM_EMAIL],
            'to' => [['email' => $toEmail, 'name' => $toName !== '' ? $toName : $toEmail]],
            'subject' => $subject,
            'htmlContent' => $htmlContent,
        ]),
        // Keep it snappy so a slow mail API never stalls signup/checkout.
        CURLOPT_TIMEOUT => 6,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code >= 200 && $code < 300;
}

/**
 * Wrap body HTML in a clean, business-grade, email-safe layout (table-based,
 * inline styles, web-safe fonts — no SVG, no flexbox, which email clients strip).
 * Brand header uses the hosted PNG logo if MAIL_LOGO_URL is set, otherwise a
 * crisp CSS wordmark lockup that renders identically everywhere.
 *   $ctaText/$ctaUrl — optional primary button
 *   $footerExtra     — appended inside the footer (e.g. an unsubscribe link)
 */
function emailTemplate(string $heading, string $bodyHtml, string $ctaText = '', string $ctaUrl = '', string $footerExtra = ''): string {
    $ink   = '#0e1330';   // brand ink (deep navy)
    $muted = '#5b6270';
    $line  = '#e7eaef';
    $bg    = '#eef1f5';
    $font  = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";

    // Brand lockup: real logo image if we have a public URL, else CSS wordmark.
    if (MAIL_LOGO_URL !== '') {
        $brand = '<img src="' . htmlspecialchars(MAIL_LOGO_URL) . '" alt="' . htmlspecialchars(MAIL_FROM_NAME)
               . '" height="38" style="height:38px;display:inline-block;border:0;outline:none;text-decoration:none;">';
    } else {
        $brand = '<table role="presentation" cellpadding="0" cellspacing="0" align="center"><tr>'
               . '<td style="vertical-align:middle;padding-right:11px;">'
               . '<div style="width:42px;height:42px;background:' . $ink . ';border-radius:10px;color:#fff;'
               . 'font:800 22px/42px ' . $font . ';text-align:center;">D</div></td>'
               . '<td style="vertical-align:middle;text-align:left;">'
               . '<div style="font:800 18px/1 ' . $font . ';letter-spacing:3px;color:' . $ink . ';">DDCET&nbsp;PREP</div>'
               . '<div style="font:600 10px/1.4 ' . $font . ';letter-spacing:2px;color:#9aa1b0;text-transform:uppercase;margin-top:4px;">Diploma &rarr; Degree Entrance</div>'
               . '</td></tr></table>';
    }

    $cta = '';
    if ($ctaText !== '' && $ctaUrl !== '') {
        // Bulletproof (table-wrapped) button.
        $cta = '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:28px 0 4px;"><tr>'
             . '<td style="background:' . $ink . ';border-radius:6px;">'
             . '<a href="' . htmlspecialchars($ctaUrl) . '" style="display:inline-block;padding:14px 32px;'
             . 'font:700 13px/1 ' . $font . ';letter-spacing:1.5px;text-transform:uppercase;color:#ffffff;text-decoration:none;">'
             . htmlspecialchars($ctaText) . '</a></td></tr></table>';
    }

    return '<!doctype html><html><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="color-scheme" content="light only"></head>'
        . '<body style="margin:0;padding:0;background:' . $bg . ';">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . $bg . ';padding:32px 12px;"><tr><td align="center">'

        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border:1px solid ' . $line . ';border-radius:12px;overflow:hidden;">'

        // Header / brand
        . '<tr><td align="center" style="padding:34px 40px 26px;border-bottom:1px solid ' . $line . ';">' . $brand . '</td></tr>'

        // Body
        . '<tr><td style="padding:38px 40px 34px;">'
        . '<h1 style="margin:0 0 16px;font:700 23px/1.3 ' . $font . ';color:' . $ink . ';">' . htmlspecialchars($heading) . '</h1>'
        . '<div style="font:400 15px/1.75 ' . $font . ';color:' . $muted . ';">' . $bodyHtml . '</div>'
        . $cta
        . '</td></tr>'

        // Footer (dark)
        . '<tr><td style="padding:26px 40px;background:' . $ink . ';">'
        . '<div style="font:700 13px/1 ' . $font . ';letter-spacing:2px;color:#ffffff;">DDCET&nbsp;PREP</div>'
        . '<div style="font:400 12px/1.7 ' . $font . ';color:#9aa1b8;margin-top:8px;">'
        . 'Gujarat\'s dedicated Diploma-to-Degree (DDCET) preparation platform.<br>'
        . 'Questions? Just reply to this email — we read every one.'
        . '</div>'
        . '<div style="font:400 11px/1.6 ' . $font . ';color:#717892;margin-top:12px;">'
        . '&copy; ' . date('Y') . ' ' . htmlspecialchars(MAIL_FROM_NAME) . ' &middot; Gujarat, India' . $footerExtra
        . '</div></td></tr>'

        . '</table>'
        . '<div style="font:400 11px/1.5 ' . $font . ';color:#9aa1b0;padding:16px 8px 0;">You\'re receiving this because you have a ' . htmlspecialchars(MAIL_FROM_NAME) . ' account.</div>'
        . '</td></tr></table></body></html>';
}

/**
 * HMAC token tying an action to a student id (used for one-click unsubscribe
 * links in non-transactional mail — no login needed, but unforgeable).
 */
function mailToken(int $studentId, string $purpose = 'unsub'): string {
    // If CRON_SECRET is empty (not yet configured), derive a fallback key so
    // tokens are not trivially forgeable with an empty HMAC key.
    $key = CRON_SECRET !== '' ? CRON_SECRET : hash('sha256', SUPABASE_KEY . ':mail_fallback');
    return substr(hash_hmac('sha256', $purpose . ':' . $studentId, $key), 0, 32);
}

/** Footer snippet with a one-click, tokenised unsubscribe link for marketing mail. */
function emailUnsubFooter(int $studentId): string {
    return ' &middot; <a href="' . APP_URL . BASE_PATH . 'unsubscribe.php?u=' . $studentId
         . '&t=' . mailToken($studentId) . '" style="color:#aeb4c6;text-decoration:underline;">Unsubscribe</a>';
}

/* ============================================================================
 * Scheduled Mock Series
 *
 * A scheduled mock is a normal `tests` row (see database/mock_series.sql) with
 * is_scheduled=true and a fixed window. Everyone takes the same paper in the
 * same window, so one attempt counts and the All-India Rank is fair. Status is
 * derived from the wall clock (Asia/Kolkata) — no cron required — and AIR is
 * computed live from completed attempts.
 * ==========================================================================*/

/**
 * Lifecycle status of a scheduled mock, derived from the current time.
 *   'upcoming' — before opens_at (register + countdown)
 *   'live'     — between opens_at and closes_at (take the paper)
 *   'grading'  — window closed, results not out yet
 *   'results'  — AIR + solutions revealed (results_at passed OR admin published)
 * A test with no window behaves as always-'live'.
 */
function mockStatus(array $test): string {
    $now    = time();
    $opens  = !empty($test['opens_at'])   ? strtotime($test['opens_at'])  : null;
    $closes = !empty($test['closes_at'])  ? strtotime($test['closes_at']) : null;
    $resAt  = !empty($test['results_at']) ? strtotime($test['results_at']) : $closes;

    if ($opens !== null && $now < $opens)  return 'upcoming';
    if ($closes !== null && $now >= $closes) {
        if (!empty($test['results_published']) || ($resAt !== null && $now >= $resAt)) {
            return 'results';
        }
        return 'grading';
    }
    return 'live';
}

/** True once AIR + solutions should be visible for a scheduled mock. */
function mockResultsOut(array $test): bool {
    return mockStatus($test) === 'results';
}

/**
 * Live All-India Rank for a score on a given test. Counts every completed
 * attempt of that test (test_id) and ranks by score (standard competition
 * ranking: ties share a rank). Returns null when there are no peers yet.
 * Returns ['rank'=>int, 'total'=>int, 'percentile'=>float].
 */
function mockAIR(int $testId, float $score): ?array {
    // Use count-based queries instead of fetching all rows into memory.
    $base = 'attempts?test_id=eq.' . $testId . '&status=eq.completed';
    $total = supabaseCount($base);
    if ($total === null || $total === 0) return null;

    // Standard competition ranking: count everyone strictly ahead.
    $better = supabaseCount($base . '&score=gt.' . $score) ?? 0;
    return [
        'rank'       => $better + 1,
        'total'      => $total,
        'percentile' => round((($total - $better) / $total) * 100, 1),
    ];
}

/**
 * Canonical DDCET full-mock blueprint: subject => question count. This is the
 * real exam pattern (BE-01 + BE-02 = 100 Q) and is the single source of truth
 * for auto-generating a scheduled mock from the pool. Scales proportionally for
 * a non-100 total, absorbing any rounding drift into the largest subject.
 *
 * Per the official GTU DDCET syllabus (published 18.01.2024):
 *   BE-01 Basics of Science & Engineering (50 Q): Physics 60%, Chemistry 20%,
 *         Computer Practice 10%, Environmental Sciences 10%
 *         → Physics 30, Chemistry 10, Computers 5, Environment 5
 *   BE-02 Aptitude Test (50 Q): Mathematics 50%, Soft Skill/English 50%
 *         → Maths 25, English 25
 */
function ddcetSyllabusDistribution(int $total = 100): array {
    $base = [
        'Physics'     => 30,  // BE-01
        'Chemistry'   => 10,  // BE-01
        'Computers'   => 5,   // BE-01
        'Environment' => 5,   // BE-01
        'Maths'       => 25,  // BE-02
        'English'     => 25,  // BE-02 (Mathematics + Soft Skill)
    ]; // = 100
    if ($total <= 0 || $total === 100) return $base;

    $scaled = []; $sum = 0;
    foreach ($base as $sub => $count) {
        $scaled[$sub] = (int) round($count / 100 * $total);
        $sum += $scaled[$sub];
    }
    $scaled['Physics'] += $total - $sum; // keep the grand total exact
    return $scaled;
}

/** Number of students registered for a scheduled mock (FOMO counter). */
function mockRegistrationCount(int $testId): int {
    $rows = supabaseRest('mock_registrations?test_id=eq.' . $testId . '&select=id') ?? [];
    return is_array($rows) ? count($rows) : 0;
}

/* ============================================================================
 * Weekly Leagues (Duolingo-style)
 *
 * Every student sits in a tier. Each week they are ranked WITHIN their tier by
 * XP earned that week (summed live from xp_log). At rollover the top promote and
 * the bottom relegate — admin-triggered (admin/leagues.php). See database/leagues.sql.
 * ==========================================================================*/

/** Tiers from lowest to highest. */
function leagueTiers(): array {
    return ['Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond'];
}

/** Icon name + accent colour for a tier (falls back gracefully). */
function leagueMeta(string $tier): array {
    $map = [
        'Bronze'   => ['icon' => 'bronze',  'color' => '#cd7f32'],
        'Silver'   => ['icon' => 'silver',  'color' => '#9ca3af'],
        'Gold'     => ['icon' => 'gold',    'color' => '#f0a500'],
        'Platinum' => ['icon' => 'medal',   'color' => '#5eead4'],
        'Diamond'  => ['icon' => 'diamond', 'color' => '#60a5fa'],
    ];
    return $map[$tier] ?? ['icon' => 'trophy', 'color' => '#888'];
}

/** Monday (00:00) of the week containing $ref (default now), as Y-m-d. */
function weekStart(?int $ref = null): string {
    return date('Y-m-d', strtotime('monday this week', $ref ?? time()));
}

/** How many promote / relegate per tier at each weekly rollover. */
function leaguePromoteCount(): int { return 7; }
function leagueRelegateCount(): int { return 5; }

/**
 * Sum XP per student from xp_log within [$since, $until) (until defaults to now).
 * Returns [student_id => xp]. Optionally restrict to $ids for a single tier.
 */
function weeklyXp(string $since, ?string $until = null, ?array $ids = null): array {
    $path = 'xp_log?created_at=gte.' . urlencode($since) . '&select=student_id,amount&limit=50000';
    if ($until) $path .= '&created_at=lt.' . urlencode($until);
    if ($ids !== null) {
        if (!$ids) return [];   // explicit empty set → no rows
        $path .= '&student_id=in.(' . implode(',', array_map('intval', $ids)) . ')';
    }
    $rows = supabaseRest($path) ?? [];
    $tally = [];
    foreach ($rows as $r) {
        $sid = (int) ($r['student_id'] ?? 0);
        if ($sid) $tally[$sid] = ($tally[$sid] ?? 0) + (int) ($r['amount'] ?? 0);
    }
    return $tally;
}

/* ============================================================================
 * Ranking ladders
 *
 * Parallel leaderboards so more than the top 1% gets a "win". These compute a
 * single student's standing live (no snapshot table) and are cheap enough to
 * call inline on the dashboard / result page.
 * ==========================================================================*/

/**
 * A student's all-time XP rank across all non-banned students (1 = highest XP).
 * Standard competition ranking. Returns null if the student isn't found.
 */
function globalRank(int $studentId, ?int $knownXp = null): ?int {
    // The caller often already has the student's XP (e.g. from the session) —
    // accept it to skip a redundant round trip.
    $myXp = $knownXp;
    if ($myXp === null) {
        $me = supabaseRest('students?id=eq.' . $studentId . '&select=xp&limit=1');
        if (empty($me)) return null;
        $myXp = (int)($me[0]['xp'] ?? 0);
    }
    // Standard competition ranking: count everyone strictly ahead of me, then +1.
    // A count-only request keeps this O(1) over the wire instead of pulling every
    // higher-XP student row back just to count them.
    $ahead = supabaseCount('students?is_banned=eq.false&xp=gt.' . (int)$myXp);
    if ($ahead === null) return null;
    return $ahead + 1;
}

/**
 * A student's rank within their own college by XP. "You're #3 in your college"
 * is a strong, attainable motivator. Returns ['rank'=>int, 'total'=>int] or null
 * when the student has no college set.
 */
function collegeRank(int $studentId, ?int $collegeId): ?array {
    if (!$collegeId) return null;
    // Get this student's XP first.
    $me = supabaseRest('students?id=eq.' . $studentId . '&select=xp&limit=1');
    if (empty($me)) return null;
    $myXp = (int)($me[0]['xp'] ?? 0);
    // Count peers in same college with higher XP (standard competition ranking).
    $base = 'students?is_banned=eq.false&college_id=eq.' . (int)$collegeId;
    $total = supabaseCount($base);
    if ($total === null || $total === 0) return null;
    $ahead = supabaseCount($base . '&xp=gt.' . $myXp) ?? 0;
    return ['rank' => $ahead + 1, 'total' => $total];
}

/**
 * A student's rank on TODAY's Daily Challenge, by score (ties share a rank).
 * Powers "ranked #87 today". Returns ['rank'=>int, 'total'=>int, 'score'=>float]
 * or null if the student hasn't completed today's challenge.
 */
function dailyChallengeRank(int $studentId): ?array {
    $today = date('Y-m-d');
    // Get this student's best daily challenge score today.
    $me = supabaseRest('attempts?mode=eq.daily_challenge&status=eq.completed'
        . '&student_id=eq.' . $studentId . '&started_at=gte.' . $today
        . '&select=score&order=score.desc&limit=1');
    if (empty($me)) return null;
    $myScore = (float)($me[0]['score'] ?? 0);
    // Count-based ranking instead of fetching all rows.
    $base = 'attempts?mode=eq.daily_challenge&status=eq.completed&started_at=gte.' . $today;
    $total = supabaseCount($base);
    if ($total === null || $total === 0) return null;
    $better = supabaseCount($base . '&score=gt.' . $myScore) ?? 0;
    return ['rank' => $better + 1, 'total' => $total, 'score' => $myScore];
}

require_once __DIR__ . '/includes/rpc.php';

/* ============================================================================
 * CSRF protection
 * ==========================================================================*/

/** Return (and lazily create) this session's CSRF token. */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Constant-time CSRF check for a submitted token. */
function csrfValid(?string $token): bool {
    return !empty($_SESSION['csrf_token']) && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Enforce CSRF on a state-changing request. Reads the token from POST['csrf']
 * (falling back to the X-CSRF-Token header for fetch/JSON callers) and aborts
 * with 403 if it is missing or invalid. Call this at the top of every POST
 * handler. Pair it with a hidden <input name="csrf" value="<?= csrfToken() ?>">
 * in the form, or send the token in the X-CSRF-Token header from JS.
 */
function requireCsrf(): void {
    if (TEST_MODE || !empty($_SERVER['HTTP_X_STUDENT_ID'])) return;
    $token = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    if (!csrfValid($token)) {
        http_response_code(403);
        exit('Invalid or missing CSRF token.');
    }
}

/* ============================================================================
 * Test attempt limits
 *
 * Pool test modes were previously unlimited and each completion granted XP,
 * which let users farm XP and made the leaderboard meaningless. These caps are
 * enforced SERVER-SIDE in exam.php before an attempt row is created. The
 * tests.php UI mirrors them but is advisory only.
 *
 * Counts are "attempts STARTED today" (server local day, Asia/Kolkata) so a
 * user can't dodge the cap by abandoning attempts.
 * ==========================================================================*/

/**
 * Per-mode daily caps. null = unlimited for that tier.
 * 'group' lets several modes share one bucket (the DDCET mock papers).
 */
function attemptLimits(): array {
    return [
        // mode               => ['free' => N|null, 'pro' => N|null, 'group' => ?string, 'pro_only' => bool]
        'daily_challenge'  => ['free' => 1,    'pro' => 1,    'group' => null,        'pro_only' => false],
        'weekly_challenge' => ['free' => 0,    'pro' => 1,    'group' => null,        'pro_only' => true],
        'full_mock'        => ['free' => 3,    'pro' => null, 'group' => 'mock',      'pro_only' => false],
        'be01_paper'       => ['free' => 3,    'pro' => null, 'group' => 'mock',      'pro_only' => false],
        'be02_paper'       => ['free' => 3,    'pro' => null, 'group' => 'mock',      'pro_only' => false],
        'rapid_fire'       => ['free' => 5,    'pro' => null, 'group' => 'practice',  'pro_only' => false],
        'subject_wise'     => ['free' => 5,    'pro' => null, 'group' => 'practice',  'pro_only' => false],
        'previous_year'    => ['free' => 5,    'pro' => null, 'group' => 'practice',  'pro_only' => false],
        'revision'         => ['free' => 5,    'pro' => null, 'group' => 'practice',  'pro_only' => false],
    ];
}

/**
 * Minimum plan required to start a pool mode. 'free' = anyone logged in.
 * Keep this as the single source of truth; exam.php enforces it and tests.php
 * mirrors it in the UI.
 */
function modeMinPlan(string $mode): string {
    $map = [
        // Free tier gets ONLY the daily challenge. Everything else needs Basic+.
        'daily_challenge'  => 'free',
        'full_mock'        => 'basic',
        'be01_paper'       => 'basic',
        'be02_paper'       => 'basic',
        'rapid_fire'       => 'basic',
        'subject_wise'     => 'basic',
        'revision'         => 'basic',
        'previous_year'    => 'pro',   // Previous-year papers are a Pro perk.
        'weekly_challenge' => 'pro',   // Pro-only (also Sundays only).
        'challenge_friend' => 'pro',   // Friend duels are a Pro perk (both sides).
    ];
    return $map[$mode] ?? 'basic';
}

/** Duration in MINUTES for a pool mode (mirrors exam.php pool configs). */
function modeDurationMinutes(string $mode): int {
    $map = [
        'full_mock' => 150, 'be01_paper' => 75, 'be02_paper' => 75,
        'rapid_fire' => 30, 'subject_wise' => 30, 'daily_challenge' => 10,
        'weekly_challenge' => 60, 'revision' => 20, 'previous_year' => 150,
    ];
    return $map[$mode] ?? 60;
}

/**
 * Human-friendly label for a pool test mode. Used wherever an attempt has no
 * fixed `tests` row (pool modes) so history/results show the real mode the
 * student took instead of a generic "Practice Test".
 */
function modeLabel(?string $mode): string {
    $map = [
        'full_mock'        => 'Full DDCET Mock',
        'be01_paper'       => 'BE-01 Paper',
        'be02_paper'       => 'BE-02 Paper',
        'rapid_fire'       => 'Rapid Fire',
        'subject_wise'     => 'Subject-wise Practice',
        'previous_year'    => 'Previous Year Paper',
        'daily_challenge'  => 'Daily Challenge',
        'weekly_challenge' => 'Weekly Challenge',
        'revision'         => 'Revision Mode',
        'challenge_friend' => 'Friend Challenge',
        'custom'           => 'Custom Test',
    ];
    if (!empty($map[$mode])) return $map[$mode];
    if ($mode) return ucwords(str_replace('_', ' ', $mode));
    return 'Practice Test';
}

/** Count attempts a student STARTED today for a given mode (or shared group). */
function attemptsStartedToday(int $studentId, string $mode): int {
    $limits = attemptLimits();
    $group  = $limits[$mode]['group'] ?? null;

    // Which modes count toward this bucket?
    $modes = [$mode];
    if ($group) {
        $modes = array_keys(array_filter($limits, fn($c) => ($c['group'] ?? null) === $group));
    }

    $modeFilter = 'mode=in.(' . implode(',', array_map('urlencode', $modes)) . ')';
    $rows = supabaseRest(
        'attempts?student_id=eq.' . $studentId
        . '&' . $modeFilter
        . '&started_at=gte.' . date('Y-m-d')
        . '&select=id'
    ) ?? [];
    return is_array($rows) ? count($rows) : 0;
}

/**
 * Decide whether a student may start `$mode` right now.
 * Returns ['allowed'=>bool, 'reason'=>?string, 'remaining'=>?int (null=unlimited)].
 */
function canAttempt(string $mode, array $user): array {
    if (TEST_MODE) return ['allowed' => true, 'reason' => null, 'remaining' => null];

    $limits = attemptLimits();
    // Unknown / fixed-test modes are not capped here.
    if (!isset($limits[$mode])) return ['allowed' => true, 'reason' => null, 'remaining' => null];

    $cfg  = $limits[$mode];
    $plan = getSubscription()['plan'] ?? 'free';
    $tier = ($plan === 'pro') ? 'pro' : 'free'; // 'basic' shares free test caps
    $cap  = $cfg[$tier];

    if (!empty($cfg['pro_only']) && $tier !== 'pro') {
        return ['allowed' => false, 'reason' => 'This mode is available to Pro members only.', 'remaining' => 0];
    }
    if ($cap === null) {
        return ['allowed' => true, 'reason' => null, 'remaining' => null]; // unlimited
    }

    $used = attemptsStartedToday((int)$user['id'], $mode);
    $remaining = max(0, $cap - $used);
    if ($remaining <= 0) {
        return ['allowed' => false, 'reason' => 'Daily limit reached for this mode. Try again tomorrow or upgrade to Pro.', 'remaining' => 0];
    }
    return ['allowed' => true, 'reason' => null, 'remaining' => $remaining];
}

/* ============================================================================
 * Spam protection: honeypot + rate limiting
 *
 * Lightweight, dependency-free bot defence for form/endpoint submissions.
 * No third-party CAPTCHA, no DB writes (state lives in the PHP session, which
 * every protected endpoint already has via requireAuth()).
 *
 *   - Honeypot: render a hidden field real users never see. Bots that fill
 *     every input trip it. Pair honeypotField() in the form with
 *     honeypotTripped() in the handler.
 *   - Rate limit: cap how often a given action may run per rolling window,
 *     keyed per session (i.e. per logged-in user).
 * ==========================================================================*/

/**
 * Hidden honeypot input. Named to look tempting to bots ("website") but kept
 * off-screen and out of the tab order for humans. Drop this inside any <form>.
 */
function honeypotField(string $name = 'website'): string {
    // aria-hidden + tabindex=-1 + autocomplete=off keeps screen readers and
    // password managers from ever touching it.
    return '<div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;height:0;width:0;overflow:hidden;">'
        . '<label>Leave this field empty</label>'
        . '<input type="text" name="' . htmlspecialchars($name) . '" tabindex="-1" autocomplete="off" value="">'
        . '</div>';
}

/** True if the honeypot field came back non-empty — i.e. a bot filled it. */
function honeypotTripped(string $name = 'website'): bool {
    return trim((string)($_POST[$name] ?? '')) !== '';
}

/**
 * Sliding-window rate limit, keyed per session. Returns true if the action is
 * ALLOWED (and records the hit); false if the caller has exceeded $max hits in
 * the last $windowSeconds. Safe to call on every submission.
 *
 *   if (!rateLimit('contact_submit', 5, 300)) { // max 5 per 5 min
 *       // too many — reject
 *   }
 */
function rateLimit(string $key, int $max, int $windowSeconds): bool {
    $now = time();
    $bucket = $_SESSION['_rl'][$key] ?? [];
    // Drop timestamps that have aged out of the window.
    $bucket = array_values(array_filter($bucket, fn($ts) => ($now - $ts) < $windowSeconds));
    if (count($bucket) >= $max) {
        $_SESSION['_rl'][$key] = $bucket; // persist the pruned list
        return false;
    }
    $bucket[] = $now;
    $_SESSION['_rl'][$key] = $bucket;
    return true;
}

/** Seconds until the rate-limit window frees up a slot (0 if not limited). */
function rateLimitRetryAfter(string $key, int $windowSeconds): int {
    $bucket = $_SESSION['_rl'][$key] ?? [];
    if (empty($bucket)) return 0;
    $oldest = min($bucket);
    return max(0, $windowSeconds - (time() - $oldest));
}
