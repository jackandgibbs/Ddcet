<?php
/**
 * Machine pre-fill of the Gujarati medium (see database/gujarati_medium.sql).
 *
 * Fills questions.question_text_gu / explanation_gu and options.option_text_gu
 * by translating the English text to Gujarati with Bhashini (the Government of
 * India's free Indic NMT — ai4bharat / IndicTrans via the ULCA + Dhruva APIs).
 *
 * KaTeX math is protected: every $...$ / $$...$$ span is masked out before the
 * text is sent to the translator and restored afterwards, so formulas, numbers
 * and units inside math are never altered. The output is a DRAFT — every DDCET
 * paper is reviewed, so a human still proofs the Gujarati afterwards.
 *
 * Run from the project root:
 *   php database/translate_gujarati.php           # dry run — translates a few, writes nothing
 *   php database/translate_gujarati.php --apply    # actually write the *_gu columns
 *   php database/translate_gujarati.php --apply --limit=200
 *
 * Idempotent: only touches rows whose *_gu column is still NULL/empty, so it is
 * safe to re-run (e.g. after new questions are uploaded).
 *
 * Requires Bhashini ULCA credentials in .env (no-ops with instructions if unset,
 * same graceful pattern as sendEmail()):
 *   BHASHINI_USER_ID   = <userID from bhashini.gov.in → My Profile → Generate>
 *   BHASHINI_API_KEY   = <ulcaApiKey from the same screen>
 *   BHASHINI_PIPELINE_ID = 64392f96daac500b55c543cd   # optional; this public MeitY pipeline is the default
 *
 * NOTE: Bhashini's free keys are licensed for proof-of-concept use. For the full
 * bank at production volume, request a paid plan from the Bhashini team.
 */

require __DIR__ . '/../config.php';

if (PHP_SAPI !== 'cli') { http_response_code(404); exit("CLI only\n"); }

$apply   = in_array('--apply', $argv ?? [], true);
$limitOpt = 0;
foreach ($argv ?? [] as $a) {
    if (preg_match('/^--limit=(\d+)$/', $a, $m)) $limitOpt = (int) $m[1];
}
$BATCH = 10;                          // strings per Bhashini inference call
$cap   = $apply ? ($limitOpt ?: PHP_INT_MAX) : ($limitOpt ?: 6); // dry run: just a taste

$USER_ID  = $_ENV['BHASHINI_USER_ID'] ?? '';
$API_KEY  = $_ENV['BHASHINI_API_KEY'] ?? '';
$PIPELINE = $_ENV['BHASHINI_PIPELINE_ID'] ?? '64392f96daac500b55c543cd';

if ($USER_ID === '' || $API_KEY === '') {
    fwrite(STDERR,
        "Bhashini credentials are not set — nothing to do.\n\n" .
        "Add these to .env, then re-run:\n" .
        "  BHASHINI_USER_ID=...\n" .
        "  BHASHINI_API_KEY=...\n\n" .
        "Get them from bhashini.gov.in → sign up → My Profile → Generate API key.\n");
    exit(1);
}

/* ---------------------------------------------------------------------------
 * Math protection: mask $...$ / $$...$$ so the translator never sees LaTeX.
 * Placeholders are tolerant on restore (the NMT may add spaces around them).
 * ------------------------------------------------------------------------- */
function maskMath(string $text, array &$store): string {
    return preg_replace_callback('/\$\$.*?\$\$|\$[^$]*\$/s', function ($m) use (&$store) {
        $key = '@@' . count($store) . '@@';
        $store[] = $m[0];
        return $key;
    }, $text) ?? $text;
}
function restoreMath(string $text, array $store): string {
    // Callback form: no replacement-string escaping of $ / \ in the LaTeX.
    return preg_replace_callback('/@@\s*(\d+)\s*@@/', function ($m) use ($store) {
        return $store[(int) $m[1]] ?? $m[0];
    }, $text) ?? $text;
}

/* ---------------------------------------------------------------------------
 * Bhashini — call 1: resolve the translation pipeline (serviceId + inference
 * endpoint + auth). Done once per run.
 * ------------------------------------------------------------------------- */
function bhashiniConfig(string $userId, string $apiKey, string $pipelineId): array {
    $body = [
        'pipelineTasks' => [[
            'taskType' => 'translation',
            'config' => ['language' => ['sourceLanguage' => 'en', 'targetLanguage' => 'gu']],
        ]],
        'pipelineRequestConfig' => ['pipelineId' => $pipelineId],
    ];
    $ch = curl_init('https://meity-auth.ulcacontrib.org/ulca/apis/v0/model/getModelsPipeline');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'userID: ' . $userId,
            'ulcaApiKey: ' . $apiKey,
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code >= 400 || !$resp) {
        throw new RuntimeException("Pipeline config call failed (HTTP $code): " . substr((string) $resp, 0, 300));
    }
    $data = json_decode($resp, true);

    $serviceId = null;
    foreach ($data['pipelineResponseConfig'] ?? [] as $task) {
        if (($task['taskType'] ?? '') === 'translation') {
            $serviceId = $task['config'][0]['serviceId'] ?? null;
            break;
        }
    }
    $ep   = $data['pipelineInferenceAPIEndPoint'] ?? [];
    $url  = $ep['callbackUrl'] ?? 'https://dhruva-api.bhashini.gov.in/services/inference/pipeline';
    $auth = $ep['inferenceApiKey'] ?? [];
    if (!$serviceId || empty($auth['value'])) {
        throw new RuntimeException('Pipeline config response missing serviceId / inference key: ' . substr($resp, 0, 300));
    }
    return [
        'serviceId'  => $serviceId,
        'callbackUrl'=> $url,
        'authName'   => $auth['name'] ?? 'Authorization',
        'authValue'  => $auth['value'],
    ];
}

/* ---------------------------------------------------------------------------
 * Bhashini — call 2: translate a batch of strings. Returns gu[] aligned to in[].
 * ------------------------------------------------------------------------- */
function bhashiniTranslate(array $cfg, array $sources): array {
    if (!$sources) return [];
    $body = [
        'pipelineTasks' => [[
            'taskType' => 'translation',
            'config' => [
                'language'  => ['sourceLanguage' => 'en', 'targetLanguage' => 'gu'],
                'serviceId' => $cfg['serviceId'],
            ],
        ]],
        'inputData' => ['input' => array_map(fn($s) => ['source' => $s], array_values($sources))],
    ];
    $ch = curl_init($cfg['callbackUrl']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            $cfg['authName'] . ': ' . $cfg['authValue'],
        ],
        CURLOPT_TIMEOUT => 60,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code >= 400 || !$resp) {
        throw new RuntimeException("Inference call failed (HTTP $code): " . substr((string) $resp, 0, 300));
    }
    $data = json_decode($resp, true);
    $out = $data['pipelineResponse'][0]['output'] ?? [];
    $targets = [];
    foreach ($out as $o) $targets[] = $o['target'] ?? '';
    return $targets;
}

/**
 * Translate one English string to Gujarati with math masked out. Returns null
 * on empty/whitespace input so callers can skip it.
 */
function translateGuard(array $cfg, ?string $text): ?string {
    $text = trim((string) $text);
    if ($text === '') return null;
    $store = [];
    $masked = maskMath($text, $store);
    $gu = bhashiniTranslate($cfg, [$masked]);
    $g = $gu[0] ?? '';
    return $g === '' ? null : restoreMath($g, $store);
}

/* ------------------------------------------------------------------------- */

echo ($apply ? "APPLY" : "DRY RUN") . " — Gujarati pre-fill via Bhashini (cap $cap rows/table)\n";
try {
    $cfg = bhashiniConfig($USER_ID, $API_KEY, $PIPELINE);
} catch (Throwable $e) {
    fwrite(STDERR, "Could not resolve Bhashini pipeline: {$e->getMessage()}\n");
    exit(1);
}
echo "Pipeline ready (serviceId: {$cfg['serviceId']}).\n";

$qDone = 0; $oDone = 0;

/* ---- Questions: question_text_gu (+ explanation_gu when present) ---------- */
echo "\nQuestions:\n";
while ($qDone < $cap) {
    // no_cache so freshly-written rows drop out of the is.null filter next loop.
    $rows = supabaseRest(
        'questions?question_text_gu=is.null&select=id,question_text,explanation&order=id&limit=' . $BATCH,
        'GET', null, ['no_cache' => true]
    ) ?? [];
    if (!$rows) break;

    foreach ($rows as $q) {
        if ($qDone >= $cap) break;
        try {
            $gu = translateGuard($cfg, $q['question_text'] ?? '');
        } catch (Throwable $e) {
            fwrite(STDERR, "  q#{$q['id']} failed: {$e->getMessage()}\n");
            break 2; // likely rate-limit / auth — stop cleanly, resume later
        }
        if ($gu === null) { // nothing translatable; skip so we don't loop forever
            if ($apply) supabaseRest('questions?id=eq.' . (int) $q['id'], 'PATCH', ['question_text_gu' => $q['question_text']]);
            continue;
        }
        $patch = ['question_text_gu' => $gu];
        if (!empty($q['explanation'])) {
            try { $patch['explanation_gu'] = translateGuard($cfg, $q['explanation']); } catch (Throwable $e) {}
        }
        echo "  q#{$q['id']}: " . mb_substr($gu, 0, 60) . "\n";
        if ($apply) supabaseRest('questions?id=eq.' . (int) $q['id'], 'PATCH', $patch);
        $qDone++;
    }
    if (!$apply) break; // dry run: one batch is enough to eyeball quality
}

/* ---- Options: option_text_gu --------------------------------------------- */
echo "\nOptions:\n";
while ($oDone < $cap) {
    $rows = supabaseRest(
        'options?option_text_gu=is.null&select=id,option_text&order=id&limit=' . $BATCH,
        'GET', null, ['no_cache' => true]
    ) ?? [];
    if (!$rows) break;

    foreach ($rows as $o) {
        if ($oDone >= $cap) break;
        try {
            $gu = translateGuard($cfg, $o['option_text'] ?? '');
        } catch (Throwable $e) {
            fwrite(STDERR, "  o#{$o['id']} failed: {$e->getMessage()}\n");
            break 2;
        }
        $val = $gu ?? $o['option_text']; // pure-math/empty options: keep as-is
        echo "  o#{$o['id']}: " . mb_substr((string) $val, 0, 40) . "\n";
        if ($apply) supabaseRest('options?id=eq.' . (int) $o['id'], 'PATCH', ['option_text_gu' => $val]);
        $oDone++;
    }
    if (!$apply) break;
}

echo "\nDone. Questions: $qDone, Options: $oDone" . ($apply ? " written." : " (dry run — nothing written).") . "\n";
if (!$apply) echo "Re-run with --apply to write. Review the Gujarati before publishing.\n";
