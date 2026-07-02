<?php
/**
 * Repair two math-markup corruptions left by a CSV upload, across the whole bank:
 *
 *   1) Display math used inline.  "$$...$$" renders as a centered block on its own
 *      line, so "For data $$5,8,12$$, the mode is ___" breaks mid-sentence. We
 *      downgrade every "$$" to inline "$". (Standalone display equations render
 *      fine inline too — KaTeX draws bmatrix/vmatrix the same way.)
 *
 *   2) Collapsed matrix row separators.  Inside \begin{…matrix} … \end{…matrix},
 *      a row break is "\\". The upload collapsed some to a single "\", so "3\1"
 *      (was "3\\1") makes KaTeX read "\1" — an undefined command — and render red
 *      error text. We restore "\\" but ONLY inside matrix environments and ONLY
 *      where a lone "\" precedes a digit/sign (never touching \sin, \frac, …).
 *
 * Run:  php database/fix_math_dollars.php            # dry run — shows samples + counts
 *       php database/fix_math_dollars.php --apply     # write the fixes
 */

require __DIR__ . '/../config.php';
if (PHP_SAPI !== 'cli') { http_response_code(404); exit("CLI only\n"); }
$apply = in_array('--apply', $argv ?? [], true);

/* ---- the fix ------------------------------------------------------------- */
function fix_math(string $t): string {
    if ($t === '') return $t;

    // (1) display -> inline. Collapse runs of $ first so "$$$$" can't appear.
    $t = preg_replace('/\${2,}/', '$', $t);

    // (2) restore "\\" row separators inside matrix environments only.
    $t = preg_replace_callback('/\\\\begin\{([pbvVB]?matrix)\}(.*?)\\\\end\{\1\}/s', function ($m) {
        // A lone "\" (not already part of "\\") before a digit / + / - is a
        // collapsed row break; double it. Callback replacement = literal, so no
        // PCRE replacement-string escaping headaches.
        $body = preg_replace_callback('/(?<!\\\\)\\\\(?=[-+0-9])/', fn() => '\\\\', $m[2]);
        return '\\begin{' . $m[1] . '}' . $body . '\\end{' . $m[1] . '}';
    }, $t);

    return $t;
}

/* ---- paginated fetch (PostgREST 1000-row cap) ---------------------------- */
function fa(string $table, string $select): array {
    $out = []; $offset = 0;
    do {
        $page = supabaseRest("$table?select=$select&order=id&limit=1000&offset=$offset", 'GET', null, ['no_cache' => true]) ?? [];
        $out = array_merge($out, $page);
        $offset += 1000;
    } while (count($page) === 1000);
    return $out;
}
/* Batched merge-duplicates upsert (same approach as admin/math-check.php). Each
 * row must carry every NOT NULL no-default column for its table. */
function bulk_upsert(string $table, array $rows): int {
    $n = 0;
    foreach (array_chunk($rows, 500) as $batch) {
        supabaseRest($table . '?on_conflict=id', 'POST', $batch, ['prefer' => 'resolution=merge-duplicates,return=minimal']);
        $n += count($batch);
    }
    return $n;
}

/* ---- scan + collect changes --------------------------------------------- */
$qRows = []; $oRows = []; $samples = [];
foreach (fa('questions', 'id,subject,question_text,explanation') as $r) {
    $nt = fix_math((string) $r['question_text']);
    $ne = ($r['explanation'] ?? null) !== null ? fix_math((string) $r['explanation']) : $r['explanation'];
    if ($nt !== (string) $r['question_text'] || $ne !== ($r['explanation'] ?? null)) {
        $qRows[] = ['id' => $r['id'], 'subject' => $r['subject'], 'question_text' => $nt, 'explanation' => $ne];
        if (count($samples) < 12 && $nt !== (string) $r['question_text']) {
            $samples[] = ['id' => 'Q#' . $r['id'], 'before' => $r['question_text'], 'after' => $nt];
        }
    }
}
foreach (fa('options', 'id,option_text') as $r) {
    $no = fix_math((string) $r['option_text']);
    if ($no !== (string) $r['option_text']) {
        $oRows[] = ['id' => $r['id'], 'option_text' => $no];
        if (count($samples) < 16) $samples[] = ['id' => 'opt#' . $r['id'], 'before' => $r['option_text'], 'after' => $no];
    }
}

echo ($apply ? 'APPLY' : 'DRY RUN') . " — math fix\n";
echo 'Questions/explanations to fix: ' . count($qRows) . "\n";
echo 'Options to fix:                ' . count($oRows) . "\n\n";

foreach ($samples as $s) {
    echo $s['id'] . "\n  before: " . preg_replace('/\s+/', ' ', $s['before']) . "\n  after:  " . preg_replace('/\s+/', ' ', $s['after']) . "\n\n";
}

if (!$apply) {
    echo "Dry run — nothing written. Re-run with --apply to save.\n";
    exit;
}

$done = bulk_upsert('questions', $qRows) + bulk_upsert('options', $oRows);
// Bust the 10s GET cache so fixed rows show immediately on the site / re-scan.
foreach (glob(sys_get_temp_dir() . '/ddcet_*.json') ?: [] as $f) @unlink($f);
echo "Wrote $done row(s).\n";
