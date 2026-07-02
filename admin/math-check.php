<?php
/* ---------------------------------------------------------------------------
 * Admin · Math Check
 *
 * Scans EVERY question, option and explanation for math markup that won't
 * render correctly on the site, and lets an admin fix each one inline with a
 * live KaTeX preview before saving back to Supabase.
 *
 * Detected problems:
 *   - Unbalanced "$"  (an odd number of inline delimiters → the dreaded
 *     "stray $" where the rest of the line shows as literal text)
 *   - Unbalanced \( \) or \[ \]
 *   - Unbalanced { }  (usually a broken \frac{..}{..})
 *   - LaTeX commands (\frac, \vec, \sqrt …) sitting in text with NO delimiters
 *     at all, so they render as raw source.
 * ------------------------------------------------------------------------- */
set_time_limit(0);
require_once __DIR__ . '/../config.php';
requireAdmin();
$pageTitle = 'Math Check';

/* ---- normalizer: pull "fill-in-the-blank" underscores OUT of math mode ----
 * A bare "_" in LaTeX is the subscript operator, so "$______$" is a KaTeX parse
 * error ("Expected group after '_'"). On throwOnError:false surfaces it renders
 * as ugly red error text; on a throwOnError:true surface it aborts rendering of
 * everything after it, leaving later spans (e.g. "$5^\circ C$") as literal "$…".
 * This rewrites each $…$ / $$…$$ span so runs of 2+ underscores live as plain
 * text and any real math on either side stays wrapped. Single-underscore
 * subscripts (x_1) are left untouched. */
function normalize_math_blanks(string $text): string {
    return preg_replace_callback('/(\${1,2})([^$]*?)\1/', function ($m) {
        $delim = $m[1]; $inner = $m[2];
        if (!preg_match('/_{2,}/', $inner)) return $m[0];
        $parts = preg_split('/(_{2,})/', $inner, -1, PREG_SPLIT_DELIM_CAPTURE);
        $out = '';
        foreach ($parts as $p) {
            if ($p === '') continue;
            if (preg_match('/^_{2,}$/', $p)) $out .= $p;     // the blank → plain text
            elseif (trim($p) === '') $out .= $p;             // spacing → keep as-is
            else $out .= $delim . trim($p) . $delim;         // real math → re-wrap
        }
        return $out;
    }, $text);
}

/* ---- paginated fetch ----------------------------------------------------
 * PostgREST caps every response at 1000 rows regardless of &limit, so a plain
 * "limit=20000" silently returns only the first 1000. Page through with offset
 * so bulk fixes and the scan actually cover the whole bank. */
function mc_fetch_all(string $table, string $select): array {
    $out = []; $offset = 0;
    do {
        $page = supabaseRest("$table?select=$select&order=id&limit=1000&offset=$offset") ?? [];
        $out = array_merge($out, $page);
        $offset += 1000;
    } while (count($page) === 1000);
    return $out;
}

/* ---- raw-LaTeX (no delimiters) detector + auto-wrapper ------------------- */
function is_undelimited_latex(string $t): bool {
    if (trim($t) === '') return false;
    $hasDelim = strpos($t, '$') !== false || strpos($t, '\\(') !== false || strpos($t, '\\[') !== false;
    if ($hasDelim) return false;
    return (bool) preg_match('/\\\\(frac|sqrt|vec|times|div|pm|mp|theta|alpha|beta|gamma|delta|lambda|mu|omega|pi|sigma|phi|sum|prod|int|cdot|leq|geq|neq|approx|infty|circ|hat|bar|overline|sin|cos|tan|cot|sec|csc|log|ln|lim|partial|nabla|rightarrow|left|right|begin|end|mathbb|text)\b/', $t);
}
// Wrap a raw-LaTeX field in $…$. Also collapses doubled backslashes (\\vec →
// \vec) from CSV double-escaping, EXCEPT when the field has a \begin{…} matrix,
// where "\\" is a legitimate row separator and must survive.
function wrap_undelimited_latex(string $t): string {
    $f = $t;
    if (strpos($f, '\\begin{') === false) $f = str_replace('\\\\', '\\', $f);
    return '$' . trim($f) . '$';
}

/* Write many heterogeneous-value rows in a few batched upserts (merge-duplicates
 * only touches the columns present in each row). Each row MUST carry every
 * NOT NULL no-default column for its table or the INSERT path fails before the
 * conflict resolves: questions → subject + question_text, options → option_text. */
function mc_bulk_upsert(string $table, array $rows): int {
    $n = 0;
    foreach (array_chunk($rows, 500) as $batch) {
        supabaseRest($table . '?on_conflict=id', 'POST', $batch, ['prefer' => 'resolution=merge-duplicates,return=minimal']);
        $n += count($batch);
    }
    return $n;
}

/* ---- validator ---------------------------------------------------------- */
function math_issues(string $t): array {
    $issues = [];
    if (trim($t) === '') return $issues;

    // Don't count escaped \$ as a delimiter.
    $stripped = str_replace('\\$', '', $t);
    if (substr_count($stripped, '$') % 2 !== 0) $issues[] = 'unbalanced_dollar';

    // Fill-in-the-blank underscores *inside* a math span → KaTeX parse error.
    // Flag exactly the cases the auto-fixer would change. A naive regex
    // false-positives on blanks that sit in the TEXT region between two separate
    // $…$ spans (e.g. "...$g(x)$ simplifies to ______ (for $x\ne-2$)"), which
    // already render fine — so defer to the normalizer as the source of truth.
    if (normalize_math_blanks($t) !== $t) $issues[] = 'math_mode_blank';

    if (substr_count($t, '\\(') !== substr_count($t, '\\)')) $issues[] = 'unbalanced_paren';
    if (substr_count($t, '\\[') !== substr_count($t, '\\]')) $issues[] = 'unbalanced_bracket';

    $hasLatex = strpos($t, '\\') !== false || strpos($t, '$') !== false;
    if ($hasLatex && substr_count($t, '{') !== substr_count($t, '}')) $issues[] = 'unbalanced_braces';

    // Raw LaTeX command with no delimiter anywhere → renders as literal source.
    if (is_undelimited_latex($t)) $issues[] = 'undelimited_latex';
    return $issues;
}

$ISSUE_LABELS = [
    'unbalanced_dollar'  => 'Unbalanced $ (stray dollar)',
    'unbalanced_paren'   => 'Unbalanced \\( \\)',
    'unbalanced_bracket' => 'Unbalanced \\[ \\]',
    'unbalanced_braces'  => 'Unbalanced { }',
    'undelimited_latex'  => 'LaTeX not wrapped in $…$',
    'math_mode_blank'    => 'Blank ( ___ ) inside $…$ — breaks KaTeX',
];

/* ---- save handler ------------------------------------------------------- */
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'fix') {
        $target = $_POST['target'] ?? '';
        $id     = (int) ($_POST['id'] ?? 0);
        $text   = (string) ($_POST['text'] ?? '');
        $ok = false;
        if ($id) {
            if ($target === 'question_text') {
                $ok = supabaseRest('questions?id=eq.' . $id, 'PATCH', ['question_text' => $text]) !== null;
            } elseif ($target === 'explanation') {
                $ok = supabaseRest('questions?id=eq.' . $id, 'PATCH', ['explanation' => $text]) !== null;
            } elseif ($target === 'option') {
                $ok = supabaseRest('options?id=eq.' . $id, 'PATCH', ['option_text' => $text]) !== null;
            }
        }
        // Bust the 10s GET cache so the freshly-fixed row drops off the rescan.
        foreach (glob(sys_get_temp_dir() . '/ddcet_*.json') ?: [] as $f) @unlink($f);
        $msg = $ok ? 'success:Saved. Rescanned below.' : 'error:Could not save — check the value and try again.';
    } elseif ($action === 'fix_blanks') {
        // Bulk: pull fill-in-the-blank underscores out of math mode across the
        // whole bank. Instead of one PATCH per row (~216 round-trips, ~1 min),
        // collect every changed row and write them in a few batched upserts.
        // Each batch is homogeneous (same single column) so merge-duplicates
        // only ever touches that one column — never NULLs a sibling field.
        // Upsert needs every NOT NULL no-default column or the INSERT path fails
        // before the conflict resolves: questions → subject + question_text,
        // options → option_text. We send them all so each row is a valid tuple.
        $qRows = []; $oRows = [];
        foreach (mc_fetch_all('questions', 'id,subject,question_text,explanation') as $r) {
            $nt = normalize_math_blanks((string) $r['question_text']);
            $ne = isset($r['explanation']) && $r['explanation'] !== null
                ? normalize_math_blanks((string) $r['explanation']) : $r['explanation'];
            if ($nt !== (string) $r['question_text'] || $ne !== ($r['explanation'] ?? null)) {
                $qRows[] = [
                    'id' => $r['id'],
                    'subject' => $r['subject'],          // NOT NULL — must be present
                    'question_text' => $nt,
                    'explanation' => $ne,
                ];
            }
        }
        foreach (mc_fetch_all('options', 'id,option_text') as $r) {
            $no = normalize_math_blanks((string) $r['option_text']);
            if ($no !== (string) $r['option_text']) $oRows[] = ['id' => $r['id'], 'option_text' => $no];
        }
        $fixed = mc_bulk_upsert('questions', $qRows) + mc_bulk_upsert('options', $oRows);

        foreach (glob(sys_get_temp_dir() . '/ddcet_*.json') ?: [] as $f) @unlink($f);
        $msg = 'success:Fixed math-mode blanks in ' . $fixed . ' row(s).';
    } elseif ($action === 'wrap_latex') {
        // Bulk: wrap every raw-LaTeX field (no $…$ delimiters) in $…$ so it
        // renders. Covers the whole bank via pagination. Questions/explanations
        // are prose and are NOT auto-wrapped (only options are pure expressions);
        // any flagged question is left for manual inline edit.
        $oRows = [];
        foreach (mc_fetch_all('options', 'id,option_text') as $r) {
            $t = (string) $r['option_text'];
            if (is_undelimited_latex($t)) $oRows[] = ['id' => $r['id'], 'option_text' => wrap_undelimited_latex($t)];
        }
        $fixed = mc_bulk_upsert('options', $oRows);

        foreach (glob(sys_get_temp_dir() . '/ddcet_*.json') ?: [] as $f) @unlink($f);
        $msg = 'success:Wrapped ' . $fixed . ' option(s) in $…$.';
    }
    // Fall through to re-render the (now refreshed) scan.
}

/* ---- scan --------------------------------------------------------------- */
// Paginated so the scan covers ALL options, not just the first 1000 (the
// PostgREST row cap that made earlier bulk fixes miss most option rows).
$questions = mc_fetch_all('questions', 'id,subject,question_text,explanation');
$options   = mc_fetch_all('options', 'id,question_id,option_text,position');

$optsByQ = [];
foreach ($options as $o) { $optsByQ[$o['question_id']][] = $o; }
foreach ($optsByQ as &$os) { usort($os, fn($a, $b) => ($a['position'] ?? 0) <=> ($b['position'] ?? 0)); }
unset($os);

$flagged = [];          // [ ['q'=>..., 'fields'=>[ ['target','id','label','text','issues'] ]] ]
$scannedFields = 0;
$flaggedFields = 0;
foreach ($questions as $q) {
    $fields = [];

    $scannedFields++;
    $iss = math_issues((string) $q['question_text']);
    if ($iss) { $fields[] = ['target' => 'question_text', 'id' => $q['id'], 'label' => 'Question', 'text' => (string) $q['question_text'], 'issues' => $iss]; }

    if (!empty($q['explanation'])) {
        $scannedFields++;
        $iss = math_issues((string) $q['explanation']);
        if ($iss) { $fields[] = ['target' => 'explanation', 'id' => $q['id'], 'label' => 'Explanation', 'text' => (string) $q['explanation'], 'issues' => $iss]; }
    }

    $letters = ['A','B','C','D','E','F'];
    foreach ($optsByQ[$q['id']] ?? [] as $oi => $o) {
        $scannedFields++;
        $iss = math_issues((string) $o['option_text']);
        if ($iss) { $fields[] = ['target' => 'option', 'id' => $o['id'], 'label' => 'Option ' . ($letters[$oi] ?? '?'), 'text' => (string) $o['option_text'], 'issues' => $iss]; }
    }

    if ($fields) {
        $flaggedFields += count($fields);
        $flagged[] = ['q' => $q, 'fields' => $fields];
    }
}

// Counts of the two bulk-fixable kinds.
$blankFields = 0; $wrapFields = 0;
foreach ($flagged as $row) {
    foreach ($row['fields'] as $f) {
        if (in_array('math_mode_blank', $f['issues'], true)) $blankFields++;
        if (in_array('undelimited_latex', $f['issues'], true) && $f['target'] === 'option') $wrapFields++;
    }
}

include __DIR__ . '/includes/header.php';
$uid = 0; // unique id counter for textarea/preview pairing
?>

<?php if ($msg): $type = str_starts_with($msg, 'success') ? 'green' : 'red'; ?>
<div class="card" style="border-color: var(--<?= $type ?>); margin-bottom: 16px; padding: 12px 16px; font-size: 13px; color: var(--<?= $type ?>);"><?= htmlspecialchars(substr($msg, strpos($msg, ':') + 1)) ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom: 20px;">
    <div class="card-header"><h3>Math Check</h3></div>
    <p style="font-size: 13px; color: var(--text-muted); margin: 8px 0 12px;">
        Scanned <strong><?= number_format(count($questions)) ?></strong> questions
        (<?= number_format($scannedFields) ?> text fields).
        Found <strong style="color: var(--<?= $flaggedFields ? 'red' : 'green' ?>);"><?= number_format($flaggedFields) ?></strong>
        field(s) with math problems across <strong><?= number_format(count($flagged)) ?></strong> question(s).
    </p>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a href="admin/math-check.php" class="btn btn-secondary btn-sm"><?= icon('refresh') ?> Re-scan</a>
        <?php if ($blankFields): ?>
        <form method="POST" onsubmit="return confirm('Auto-fix <?= $blankFields ?> field(s) by moving fill-in-the-blank underscores out of math mode? This updates the database.')">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
            <input type="hidden" name="action" value="fix_blanks">
            <button type="submit" class="btn btn-primary btn-sm"><?= icon('check', 14) ?> Auto-fix all blanks (<?= number_format($blankFields) ?>)</button>
        </form>
        <?php endif; ?>
        <?php if ($wrapFields): ?>
        <form method="POST" onsubmit="return confirm('Wrap <?= $wrapFields ?> raw-LaTeX option(s) in $…$ so they render? This updates the database.')">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
            <input type="hidden" name="action" value="wrap_latex">
            <button type="submit" class="btn btn-primary btn-sm"><?= icon('check', 14) ?> Wrap all raw-LaTeX options (<?= number_format($wrapFields) ?>)</button>
        </form>
        <?php endif; ?>
    </div>
    <?php if ($blankFields): ?>
    <p style="font-size:12px; color:var(--text-muted); margin-top:10px;">
        <strong style="color:var(--red);"><?= number_format($blankFields) ?></strong> field(s) have a fill-in-the-blank
        (<code>______</code>) written <em>inside</em> <code>$…$</code>, which KaTeX can't parse. The button moves the blank
        out of math mode (leaving real formulas intact) — this is the main cause of the broken-looking questions.
    </p>
    <?php endif; ?>
    <?php if ($wrapFields): ?>
    <p style="font-size:12px; color:var(--text-muted); margin-top:10px;">
        <strong style="color:var(--red);"><?= number_format($wrapFields) ?></strong> option(s) contain raw LaTeX
        (e.g. <code>\frac{3}{2}</code>) with no <code>$…$</code> around it, so it shows as literal source. The button
        wraps each in <code>$…$</code> (and repairs CSV double-backslashes outside matrices) so they render.
    </p>
    <?php endif; ?>
</div>

<?php if (!$flagged): ?>
    <div class="card" style="text-align:center; padding:40px; color:var(--green);">
        <?= icon('check', 28) ?>
        <p style="margin-top:8px; font-size:14px;">All clear — every question, option and explanation has balanced, well-formed math.</p>
    </div>
<?php else: ?>

<?php foreach ($flagged as $row): $q = $row['q']; ?>
<div class="card" style="margin-bottom: 16px;">
    <div style="display:flex; align-items:center; gap:8px; margin-bottom:10px;">
        <span class="tag" style="font-family: var(--font-mono);">#<?= $q['id'] ?></span>
        <span class="tag"><?= htmlspecialchars($q['subject'] ?? '') ?></span>
    </div>

    <?php foreach ($row['fields'] as $f): $uid++; ?>
    <div style="border:1px solid var(--border); border-radius:8px; padding:12px; margin-bottom:12px;">
        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:8px;">
            <strong style="font-size:13px;"><?= htmlspecialchars($f['label']) ?></strong>
            <?php foreach ($f['issues'] as $code): ?>
                <span class="badge badge-red" style="font-size:11px;"><?= htmlspecialchars($ISSUE_LABELS[$code] ?? $code) ?></span>
            <?php endforeach; ?>
        </div>

        <form method="POST" onsubmit="return mcConfirm(<?= $uid ?>)">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
            <input type="hidden" name="action" value="fix">
            <input type="hidden" name="target" value="<?= htmlspecialchars($f['target']) ?>">
            <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">

            <textarea id="mcTa<?= $uid ?>" name="text" rows="3" class="form-control"
                style="font-family: var(--font-mono); font-size:13px;"
                oninput="mcPreview(<?= $uid ?>)"><?= htmlspecialchars($f['text']) ?></textarea>

            <div style="display:flex; gap:8px; flex-wrap:wrap; margin:8px 0;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="mcWrap(<?= $uid ?>)" title="Wrap the selected text (or whole field) in $…$">Wrap sel in $…$</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="mcStrip(<?= $uid ?>)" title="Remove every $ from the field">Strip all $</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="mcPreview(<?= $uid ?>)">Refresh preview</button>
            </div>

            <div style="font-size:11px; color:var(--text-muted); margin-bottom:4px;">Live preview (exactly how students will see it):</div>
            <div id="mcPv<?= $uid ?>" class="mc-preview" style="background:var(--bg-primary); border:1px dashed var(--border); border-radius:6px; padding:10px; min-height:34px; font-size:14px; line-height:1.6;"></div>

            <div style="margin-top:10px;">
                <button type="submit" class="btn btn-primary btn-sm"><?= icon('check', 14) ?> Save fix</button>
            </div>
        </form>
    </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>

<?php endif; ?>

<script>
// Escape user text before injecting into the preview node (mirrors the
// htmlspecialchars() the live pages apply to question/option/explanation).
function mcEsc(s) {
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function mcPreview(n) {
    var ta = document.getElementById('mcTa' + n);
    var pv = document.getElementById('mcPv' + n);
    if (!ta || !pv) return;
    // Inner .mathy span so mathrender.js's smart unicode formatting runs too.
    pv.innerHTML = '<span class="mathy">' + mcEsc(ta.value) + '</span>';
    if (window.renderMath) {
        window.renderMath(pv);
    } else if (window.renderMathInElement) {
        window.renderMathInElement(pv, {
            delimiters: [
                {left: '$$', right: '$$', display: true},
                {left: '$', right: '$', display: false},
                {left: '\\(', right: '\\)', display: false},
                {left: '\\[', right: '\\]', display: true}
            ],
            throwOnError: false
        });
    }
}

// Wrap the current selection (or the whole field, if nothing is selected) in $…$.
function mcWrap(n) {
    var ta = document.getElementById('mcTa' + n);
    if (!ta) return;
    var s = ta.selectionStart, e = ta.selectionEnd;
    if (s === e) { s = 0; e = ta.value.length; }     // nothing selected → wrap all
    ta.value = ta.value.slice(0, s) + '$' + ta.value.slice(s, e) + '$' + ta.value.slice(e);
    mcPreview(n);
}

function mcStrip(n) {
    var ta = document.getElementById('mcTa' + n);
    if (!ta) return;
    ta.value = ta.value.replace(/\$/g, '');
    mcPreview(n);
}

// Warn if the admin is about to save text that still has an odd number of $.
function mcConfirm(n) {
    var ta = document.getElementById('mcTa' + n);
    if (!ta) return true;
    var dollars = (ta.value.replace(/\\\$/g, '').match(/\$/g) || []).length;
    if (dollars % 2 !== 0) {
        return confirm('This text still has an odd number of "$". It will likely render with a stray $. Save anyway?');
    }
    return true;
}

// Render previews LAZILY — only when a card scrolls into view — so a page with
// hundreds of flagged fields doesn't run hundreds of KaTeX renders up front.
(function ready(a) {
    if (!window.renderMathInElement) {
        if ((a || 0) < 30) setTimeout(function () { ready((a || 0) + 1); }, 100);
        return;
    }
    var previews = document.querySelectorAll('.mc-preview');
    if (!('IntersectionObserver' in window)) {
        // Fallback: render everything (older browsers).
        previews.forEach(function (el) { mcPreview(el.id.replace('mcPv', '')); });
        return;
    }
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                mcPreview(entry.target.id.replace('mcPv', ''));
                io.unobserve(entry.target);   // render once
            }
        });
    }, { rootMargin: '200px' });              // warm up just before they appear
    previews.forEach(function (el) { io.observe(el); });
})(0);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
