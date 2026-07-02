<?php
/**
 * Reminder cron — there is no native scheduler on this stack, so an external
 * pinger (e.g. cron-job.org, free) calls this URL every ~30–60 min:
 *     https://YOURSITE/Dddcet/cron/send_reminders.php?key=CRON_SECRET
 *
 * Two jobs, both opt-out aware and capped per run to protect the Brevo free
 * quota (~300/day) and to finish within the pinger's HTTP timeout:
 *   A) "Mock starts soon"  — registrants of a scheduled mock opening within 60m.
 *   B) Inactivity nudge     — users idle ≥3 days, not nudged in the last 7.
 *
 * Output is a plain-text summary for the cron log.
 */
require_once __DIR__ . '/../config.php';
header('Content-Type: text/plain');

// --- Auth: shared secret (query param for HTTP pingers, or CLI arg). ---
$key = $_GET['key'] ?? ($argv[1] ?? '');
if (CRON_SECRET === '' || !hash_equals(CRON_SECRET, (string) $key)) {
    http_response_code(403);
    exit("forbidden\n");
}
if (BREVO_API_KEY === '' || MAIL_FROM_EMAIL === '') {
    exit("email not configured (set BREVO_API_KEY + MAIL_FROM_EMAIL)\n");
}

$MAX_PER_RUN = 50;          // hard cap: protects the daily quota + the HTTP timeout
$sent = 0; $skipped = 0;
$nowIso = date('c');

// ===== Job A: scheduled mocks opening within the next 60 minutes =====
$soonIso = date('c', time() + 3600);
$dueMocks = supabaseRest('tests?is_scheduled=eq.true&reminder_sent_at=is.null'
    . '&opens_at=gte.' . urlencode($nowIso) . '&opens_at=lte.' . urlencode($soonIso)
    . '&select=id,title,series_label,opens_at') ?? [];

foreach ($dueMocks as $m) {
    $label = $m['series_label'] ?: $m['title'];
    $mins = max(1, (int) round((strtotime($m['opens_at']) - time()) / 60));
    $regs = supabaseRest('mock_registrations?test_id=eq.' . (int) $m['id'] . '&select=student_id') ?? [];
    $ids = array_filter(array_map(fn($r) => (int) $r['student_id'], $regs));
    if ($ids) {
        $studs = supabaseRest('students?id=in.(' . implode(',', $ids) . ')'
            . '&email_opt_out=eq.false&is_banned=eq.false&select=id,email,name') ?? [];
        foreach ($studs as $s) {
            if (empty($s['email'])) continue;
            if ($sent >= $MAX_PER_RUN) { $skipped++; continue; }
            $body = 'Your scheduled mock <strong>' . htmlspecialchars($label) . '</strong> opens in about <strong>'
                  . $mins . ' minutes</strong>. Everyone takes the same paper in the same window — be there for a fair All-India Rank.';
            if (sendEmail($s['email'], $s['name'] ?? '', 'Starts soon: ' . $label,
                emailTemplate('Your mock starts soon', $body, 'Enter the mock',
                    APP_URL . BASE_PATH . 'mocks.php', emailUnsubFooter((int) $s['id'])))) {
                $sent++;
            }
        }
    }
    // Mark reminded so it can't blast again on the next tick.
    supabaseRest('tests?id=eq.' . (int) $m['id'], 'PATCH', ['reminder_sent_at' => $nowIso]);
}

// ===== Job B: inactivity nudges (fill remaining budget) =====
if ($sent < $MAX_PER_RUN) {
    $inactiveSince = date('Y-m-d', strtotime('-3 days'));   // last_active_date is a DATE
    $nudgeBefore   = date('c', strtotime('-7 days'));        // re-nudge no more than weekly
    $remaining = $MAX_PER_RUN - $sent;
    $cands = supabaseRest('students?last_active_date=lte.' . $inactiveSince
        . '&email_opt_out=eq.false&is_banned=eq.false'
        . '&or=(last_reminder_sent_at.is.null,last_reminder_sent_at.lt.' . urlencode($nudgeBefore) . ')'
        . '&select=id,email,name&limit=' . $remaining) ?? [];
    foreach ($cands as $s) {
        if ($sent >= $MAX_PER_RUN) break;
        if (empty($s['email'])) continue;
        $body = 'Your DDCET prep is waiting. A few questions today keeps your streak alive and your rank climbing — '
              . 'jump back in with a quick practice set.';
        if (sendEmail($s['email'], $s['name'] ?? '', 'Pick up your DDCET prep where you left off',
            emailTemplate('Ready for your next session?', $body, 'Resume practice',
                APP_URL . BASE_PATH . 'dashboard.php', emailUnsubFooter((int) $s['id'])))) {
            $sent++;
            // Stamp so this user isn't nudged again for 7 days.
            supabaseRest('students?id=eq.' . (int) $s['id'], 'PATCH', ['last_reminder_sent_at' => $nowIso]);
        }
    }
}

echo "ok sent={$sent} skipped={$skipped} due_mocks=" . count($dueMocks) . "\n";
