<?php
/**
 * Machine pre-fill of the Gujarati medium.
 * Uses Gemini API to translate questions and options to Gujarati.
 * Run from the project root:
 *   php database/translate_gujarati.php           # dry run
 *   php database/translate_gujarati.php --apply    # actually write the *_gu columns
 */

require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/gemini.php';

if (PHP_SAPI !== 'cli') { http_response_code(404); exit("CLI only\n"); }

$apply   = in_array('--apply', $argv ?? [], true);
$limitOpt = 0;
foreach ($argv ?? [] as $a) {
    if (preg_match('/^--limit=(\d+)$/', $a, $m)) $limitOpt = (int) $m[1];
}
$BATCH = 5;
$cap   = $apply ? ($limitOpt ?: PHP_INT_MAX) : ($limitOpt ?: 6);

// Instruction for Gemini
$sysInstruction = "You are a professional English to Gujarati translator specializing in educational content. Your task is to translate the provided text into Gujarati. Preserve any LaTeX math wrapped in $ or $$. Do not explain or add anything else. Output ONLY the translated Gujarati text.";

function translateGuard($text) {
    global $sysInstruction;
    $text = trim((string) $text);
    if ($text === '') return null;
    
    // Quick heuristic: if it's purely math, numbers, or English alphabets like A/B/C/D, don't translate
    if (preg_match('/^[\d\s\$\.\+\-\*\/\(\)\=a-zA-Z]+$/', $text)) {
        return null;
    }
    
    $prompt = "Translate this text to Gujarati:\n\n" . $text;
    try {
        $gu = callGemini($prompt, $sysInstruction);
        // User requested 30-35 requests per second.
        // We will remove the artificial 4-second wait completely.
        usleep(30000); // Tiny 30ms sleep to prevent local socket exhaustion
        
        if (str_starts_with($gu, 'Error:')) {
            // If we get a rate limit error, wait a few seconds and retry once
            if (str_contains($gu, '429')) {
                echo "\n[Rate Limit] Too fast! Pausing for 5 seconds...\n";
                sleep(5);
                $gu = callGemini($prompt, $sysInstruction);
                if (str_starts_with($gu, 'Error:')) {
                    throw new RuntimeException($gu);
                }
            } else {
                throw new RuntimeException($gu);
            }
        }
        return trim($gu);
    } catch (Throwable $e) {
        throw $e;
    }
}

echo ($apply ? "APPLY" : "DRY RUN") . " - Gujarati pre-fill via Gemini API (cap $cap rows/table)\n";

if (empty($_ENV['GEMINI_API_KEY'])) {
    fwrite(STDERR, "GEMINI_API_KEY is not set in .env! Cannot translate.\n");
    exit(1);
}

$qDone = 0; $oDone = 0;

/* ---- Questions ----------------------------------------------------------- */
echo "\nQuestions:\n";
while ($qDone < $cap) {
    $rows = supabaseRest(
        'questions?question_text_gu=is.null&select=id,question_text,explanation&order=id&limit=' . $BATCH,
        'GET', null, ['no_cache' => true]
    ) ?? [];
    if (!$rows) break;

    foreach ($rows as $q) {
        if ($qDone >= $cap) break;
        try {
            $gu = translateGuard($q['question_text'] ?? '');
        } catch (Throwable $e) {
            fwrite(STDERR, "  q#{$q['id']} failed: {$e->getMessage()}\n");
            break 2; // Stop on API limits/errors
        }
        
        if ($gu === null) {
            if ($apply) supabaseRest('questions?id=eq.' . (int) $q['id'], 'PATCH', ['question_text_gu' => $q['question_text']]);
            continue;
        }
        
        $patch = ['question_text_gu' => $gu];
        if (!empty($q['explanation'])) {
            try { 
                $expGu = translateGuard($q['explanation']);
                if ($expGu) $patch['explanation_gu'] = $expGu;
            } catch (Throwable $e) {}
        }
        
        echo "  q#{$q['id']}: " . mb_substr($gu, 0, 60) . "\n";
        if ($apply) supabaseRest('questions?id=eq.' . (int) $q['id'], 'PATCH', $patch);
        $qDone++;
    }
    if (!$apply) break;
}

/* ---- Options ------------------------------------------------------------- */
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
            $gu = translateGuard($o['option_text'] ?? '');
        } catch (Throwable $e) {
            fwrite(STDERR, "  o#{$o['id']} failed: {$e->getMessage()}\n");
            break 2;
        }
        
        $val = $gu ?? $o['option_text'];
        echo "  o#{$o['id']}: " . mb_substr((string) $val, 0, 40) . "\n";
        if ($apply) supabaseRest('options?id=eq.' . (int) $o['id'], 'PATCH', ['option_text_gu' => $val]);
        $oDone++;
    }
    if (!$apply) break;
}

echo "\nDone. Questions: $qDone, Options: $oDone" . ($apply ? " written." : " (dry run — nothing written).") . "\n";
if (!$apply) echo "Re-run with --apply to write.\n";
