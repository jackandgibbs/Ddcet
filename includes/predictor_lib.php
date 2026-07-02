<?php
/**
 * Predictor library — reusable college-cutoff data + a marks→college projection.
 *
 * predictor.php remains the full interactive tool; this lib lets other pages
 * (notably result.php) "close the loop" after a mock: take the marks a student
 * just scored and show the best college/branch that score projects to, plus how
 * many more marks unlock the next tier. Data is the same DDCET admission CSVs.
 *
 * DDCET is scored out of 200 (100 questions × 2 marks) and the CSV cutoffs are
 * on that same 0–200 scale, so a full-mock score maps directly. Callers should
 * normalise any other mock to /200 before projecting (score / total_marks * 200).
 */

/** Load + cache the admission rows from both CSVs (deduped). */
function predictorRows(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $rows = [];
    $seen = [];
    foreach (['ddcet_full_data_2024.csv', 'ddcet_full_data_2025.csv'] as $file) {
        $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . $file;
        if (!file_exists($path) || ($h = fopen($path, 'r')) === false) continue;
        $year = preg_match('/(\d{4})/', $file, $m) ? $m[1] : '';
        fgetcsv($h); // header
        while (($r = fgetcsv($h)) !== false) {
            if (count($r) < 10) continue;
            $key = $year . '|' . implode('|', $r);
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $inst  = $r[1];
            $parts = explode(',', $inst, 2);
            $rows[] = [
                'year'        => $year,
                'institute'   => $inst,
                'college'     => trim($parts[0]),
                'city'        => isset($parts[1]) ? trim($parts[1]) : '',
                'inst_type'   => $r[2],
                'branch'      => $r[3],
                'category'    => $r[4],
                'quota'       => $r[5],
                'first_marks' => (float)$r[6],
                'first_rank'  => (int)$r[7],
                'last_marks'  => (float)$r[8],
                'last_rank'   => (int)$r[9],
            ];
        }
        fclose($h);
    }
    $cache = $rows;
    return $cache;
}

/** Distinct admission categories present in the data, in counselling order. */
function predictorCategories(): array {
    $order = ['OP' => 0, 'EW' => 1, 'SC' => 2, 'SE' => 3, 'ST' => 4, 'TF' => 5];
    $cats  = array_values(array_unique(array_column(predictorRows(), 'category')));
    usort($cats, fn($a, $b) => ($order[$a] ?? 99) <=> ($order[$b] ?? 99) ?: strcmp($a, $b));
    return $cats;
}

/**
 * Project a DDCET marks total (0–200 scale) to COLLEGES for a given category.
 *
 * Branch-level cutoffs sit only ~0.5 marks apart, so a seat-by-seat "next" is
 * always +1 and meaningless. Instead we work at the college level: a college's
 * entry threshold is the closing cutoff of its EASIEST qualifying branch (the
 * lowest last_marks you'd need to get in at all). That makes "improve by X marks
 * to unlock [a new college]" a real, motivating jump.
 *
 * Returns:
 *   [
 *     'best'         => row|null,   // most competitive college you already clear (its entry branch)
 *     'next'         => row|null,   // nearest college just out of reach (its entry branch)
 *     'marks_needed' => int,        // marks to reach 'next' (0 if none)
 *     'safe_count'   => int,        // how many distinct colleges you clear
 *   ]
 * The 'best'/'next' rows are full CSV rows for the branch at that college's entry
 * threshold, so callers can show college + branch + cutoff. $branch (optional)
 * narrows to one branch across all colleges; null considers every branch.
 */
function projectFromMarks(float $marks, string $category = 'OP', ?string $branch = null): array {
    // 'best' is computed at SEAT level (specific branch+college you can grab) so a
    // strong score shows an impressive, concrete seat. 'next' is computed at
    // COLLEGE level (a college's easiest qualifying branch) so the "unlock" is a
    // real jump, not the +0.5-mark seat sitting right above you.
    $best = null;        // best seat cleared (highest last_marks among cleared seats)
    $entry = [];         // college => its easiest-branch row, for the college-level next
    foreach (predictorRows() as $row) {
        if ($row['category'] !== $category) continue;
        if ($branch !== null && $branch !== 'all' && $row['branch'] !== $branch) continue;
        $cutoff = $row['last_marks'];
        if ($cutoff <= 0) continue;

        if ($marks >= $cutoff && ($best === null || $cutoff > $best['last_marks'])) {
            $best = $row;
        }
        $key = $row['college'];
        if (!isset($entry[$key]) || $cutoff < $entry[$key]['last_marks']) {
            $entry[$key] = $row;
        }
    }

    $next = null; $safe = 0;
    foreach ($entry as $row) {
        if ($marks >= $row['last_marks']) {
            $safe++;                                  // distinct colleges you can enter
        } elseif ($next === null || $row['last_marks'] < $next['last_marks']) {
            $next = $row;                             // nearest college out of reach
        }
    }
    $marksNeeded = $next ? (int)ceil($next['last_marks'] - $marks) : 0;
    return [
        'best'         => $best,
        'next'         => $next,
        'marks_needed' => max(0, $marksNeeded),
        'safe_count'   => $safe,
    ];
}
