<?php
/* ----------------------------------------------------------------------------
 * Branch-specific study plans.
 *
 * The DDCET paper itself is common to all diploma branches (BE-01 Science +
 * BE-02 Aptitude = 100 Q). But a diploma student's *starting point* depends
 * heavily on their engineering branch: a Computer-branch student already knows
 * the Computers section cold, while a Civil student usually needs more time on
 * Maths and Computers. This module turns a student's branch (the `department`
 * captured at onboarding) into a personalized focus plan.
 *
 * It is advisory/UI only — it does not change scoring or the question pool. The
 * subjects referenced match config.php's ddcetSyllabusDistribution() exactly:
 *   Physics, Chemistry, Computers, Environment (BE-01) · Maths, English (BE-02)
 * ------------------------------------------------------------------------- */

/** Canonical diploma branches, aligned with auth/onboarding.php's selector. */
function diplomaBranches(): array {
    return ['Computer', 'Mechanical', 'Civil', 'Electrical', 'EC', 'Chemical', 'Other'];
}

/** Display label for a stored branch code. */
function branchLabel(?string $dept): string {
    $map = [
        'Computer'   => 'Computer Engineering',
        'Mechanical' => 'Mechanical Engineering',
        'Civil'      => 'Civil Engineering',
        'Electrical' => 'Electrical Engineering',
        'EC'         => 'Electronics & Communication',
        'Chemical'   => 'Chemical Engineering',
        'Other'      => 'General (All Branches)',
    ];
    return $map[$dept] ?? 'General (All Branches)';
}

/**
 * Branch-specific plan. Returns:
 *   label    => human branch name
 *   intro    => one-line framing
 *   edge     => [subjects this branch usually finds easier]   (your head start)
 *   focus    => [subjects to prioritize, highest-impact first] (where to invest)
 *   split    => [subject => suggested % of study time]         (sums ~100)
 *   tip      => one actionable sentence
 *
 * Weighting logic blends exam marks (Physics 30, Maths 25, English 25,
 * Chemistry 10, Computers 5, Environment 5) with the typical branch gap.
 */
function branchPlan(?string $dept): array {
    $dept = $dept ?: 'Other';

    // Per-branch [edge, focus] curated from the marks weights + branch overlap.
    $plans = [
        'Computer' => [
            'edge'  => ['Computers', 'Maths'],
            'focus' => ['Physics', 'Chemistry', 'English', 'Environment'],
            'split' => ['Physics' => 35, 'Maths' => 20, 'English' => 20, 'Chemistry' => 12, 'Environment' => 8, 'Computers' => 5],
            'tip'   => 'You already own the Computers section — bank those 5 marks fast, then pour your hours into Physics, the single biggest scorer.',
        ],
        'Mechanical' => [
            'edge'  => ['Physics', 'Maths'],
            'focus' => ['English', 'Computers', 'Chemistry', 'Environment'],
            'split' => ['Physics' => 25, 'Maths' => 22, 'English' => 23, 'Chemistry' => 12, 'Computers' => 10, 'Environment' => 8],
            'tip'   => 'Physics and Maths are your strengths — protect them, and close the gap on English (25 marks) and Computers where mechanical diplomas usually lag.',
        ],
        'Civil' => [
            'edge'  => ['Physics', 'Environment'],
            'focus' => ['Maths', 'Computers', 'English', 'Chemistry'],
            'split' => ['Physics' => 22, 'Maths' => 28, 'English' => 22, 'Chemistry' => 10, 'Computers' => 12, 'Environment' => 6],
            'tip'   => 'Environment is free marks for you. The real swing is Maths (25 marks) and Computers — give them the most table time.',
        ],
        'Electrical' => [
            'edge'  => ['Physics', 'Maths'],
            'focus' => ['English', 'Chemistry', 'Computers', 'Environment'],
            'split' => ['Physics' => 26, 'Maths' => 22, 'English' => 22, 'Chemistry' => 12, 'Computers' => 10, 'Environment' => 8],
            'tip'   => 'Lean on your Physics/Maths edge to free up time for English and Chemistry, which decide the close finishes.',
        ],
        'EC' => [
            'edge'  => ['Physics', 'Computers', 'Maths'],
            'focus' => ['English', 'Chemistry', 'Environment'],
            'split' => ['Physics' => 28, 'Maths' => 20, 'English' => 22, 'Chemistry' => 12, 'Computers' => 10, 'Environment' => 8],
            'tip'   => 'Three of your strong areas (Physics, Maths, Computers) overlap the BE-01/BE-02 weightage — convert that into a high baseline, then grind English.',
        ],
        'Chemical' => [
            'edge'  => ['Chemistry', 'Physics'],
            'focus' => ['Maths', 'English', 'Computers', 'Environment'],
            'split' => ['Physics' => 24, 'Maths' => 24, 'English' => 22, 'Chemistry' => 14, 'Computers' => 10, 'Environment' => 6],
            'tip'   => 'Chemistry is your comfort zone — but it is only 10 marks. Make sure Maths (25) and English (25) get the lion\'s share of your prep.',
        ],
        'Other' => [
            'edge'  => ['—'],
            'focus' => ['Physics', 'Maths', 'English', 'Chemistry', 'Computers', 'Environment'],
            'split' => ['Physics' => 30, 'Maths' => 25, 'English' => 25, 'Chemistry' => 10, 'Computers' => 5, 'Environment' => 5],
            'tip'   => 'A balanced, marks-weighted plan: Physics, Maths and English together are 80 of the 100 marks — prioritize them in that order.',
        ],
    ];

    $p = $plans[$dept] ?? $plans['Other'];
    return [
        'label' => branchLabel($dept),
        'code'  => $dept,
        'intro' => $dept === 'Other'
            ? 'A marks-weighted plan covering the full DDCET syllabus.'
            : 'Tuned for ' . branchLabel($dept) . ' diploma students.',
        'edge'  => $p['edge'],
        'focus' => $p['focus'],
        'split' => $p['split'],
        'tip'   => $p['tip'],
    ];
}
