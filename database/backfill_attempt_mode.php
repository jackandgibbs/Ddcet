<?php
/**
 * One-time backfill for attempts.mode.
 *
 * Older attempts were created before the app persisted `mode`, so they have
 * mode = NULL and render as a generic "Practice Test" in history/results.
 * This script infers the mode from each attempt's question count + the subject
 * spread of its answered questions, and writes it back. It ONLY touches rows
 * where mode IS NULL, and only sets a value when the data is conclusive — rows
 * with no stored answers are left as-is (nothing reliable to infer from).
 *
 * Run from the project root:  php database/backfill_attempt_mode.php
 * Add --apply to actually write; without it, this is a dry run.
 */

require __DIR__ . '/../config.php';

$apply = in_array('--apply', $argv ?? [], true);

$BE01 = ['Physics', 'Chemistry', 'Computers'];
$BE02 = ['Maths', 'English', 'Environment'];

/** Infer a mode from question count + distinct subject names. null = unknown. */
function inferMode(int $cnt, array $subjectNames, array $be01, array $be02): ?string {
    $distinct = count($subjectNames);
    $isSubsetOf = fn(array $set) => $subjectNames && !array_diff($subjectNames, $set);

    if ($cnt >= 80) return 'full_mock';                 // ~100 Q
    if ($cnt >= 44 && $cnt <= 56) {                      // ~50 Q
        if ($isSubsetOf($be01)) return 'be01_paper';
        if ($isSubsetOf($be02)) return 'be02_paper';
        return 'weekly_challenge';
    }
    if ($cnt >= 25 && $cnt <= 35) {                      // ~30 Q
        return $distinct === 1 ? 'subject_wise' : 'rapid_fire';
    }
    if ($cnt >= 16 && $cnt <= 22) return 'revision';     // ~20 Q
    if ($cnt >= 8  && $cnt <= 12) return 'daily_challenge'; // ~10 Q
    return null;
}

$rows = supabaseRest('attempts?mode=is.null&select=id&order=id.asc&limit=2000') ?? [];
echo 'NULL-mode attempts: ' . count($rows) . ($apply ? "  (APPLYING)\n" : "  (dry run — pass --apply to write)\n");

$updated = 0; $skipped = 0; $tally = [];
foreach ($rows as $r) {
    $id = $r['id'];
    $ans = supabaseRest('attempt_answers?attempt_id=eq.' . $id . '&select=questions(subject)&limit=300') ?? [];
    $subs = [];
    foreach ($ans as $a) {
        $s = $a['questions']['subject'] ?? null;
        if ($s) $subs[$s] = true;
    }
    $cnt = count($ans);
    $mode = inferMode($cnt, array_keys($subs), $BE01, $BE02);

    if ($mode === null) {
        $skipped++;
        printf("  #%-4d  q=%-3d  -> (skip: no/insufficient data)\n", $id, $cnt);
        continue;
    }
    $tally[$mode] = ($tally[$mode] ?? 0) + 1;
    printf("  #%-4d  q=%-3d  subj=[%s]  -> %s\n", $id, $cnt, implode(',', array_keys($subs)), $mode);
    if ($apply) {
        supabaseRest('attempts?id=eq.' . $id, 'PATCH', ['mode' => $mode]);
    }
    $updated++;
}

echo "\nSummary:\n";
foreach ($tally as $m => $c) echo "  $m: $c\n";
echo "  (skipped: $skipped)\n";
echo $apply ? "Done — $updated rows updated.\n" : "Dry run — $updated rows WOULD be updated. Re-run with --apply.\n";
