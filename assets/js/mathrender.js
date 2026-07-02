/* ----------------------------------------------------------------------------
 * Site-wide math rendering (KaTeX).
 *
 * DDCET is Physics/Maths heavy, so formulas should render anywhere content is
 * shown — not just inside the exam. This module exposes a single helper,
 * window.renderMath(root), and auto-renders the page's main content on load.
 *
 * Two layers:
 *   1) Delimiter rendering ($...$, $$...$$, \(...\), \[...\]) — run on the whole
 *      content area. KaTeX's auto-render only touches text BETWEEN delimiters,
 *      so plain prose is never altered. Safe everywhere.
 *   2) Unicode "smart" formatting (×, ÷, ±, °C, a/b, 3x10^8, ≤, ≥, ∞) — only
 *      applied to elements explicitly opted in with class="mathy", because a
 *      blind unicode→TeX pass over a whole page could mangle ordinary text.
 *
 * Depends on KaTeX + its auto-render contrib being loaded first (see header.php).
 * Degrades silently if KaTeX is unavailable.
 * ------------------------------------------------------------------------- */
(function () {
    'use strict';

    var DELIMITERS = [
        { left: '$$', right: '$$', display: true },
        { left: '$',  right: '$',  display: false },
        { left: '\\(', right: '\\)', display: false },
        { left: '\\[', right: '\\]', display: true }
    ];

    // Convert common unicode/shorthand science notation into TeX, but only for
    // text that doesn't already contain explicit $ delimiters (so we never
    // double-process author-written math). Mirrors the logic used in exam.php.
    function smartFormat(el) {
        var t = el.innerHTML;
        if (t.indexOf('$') !== -1) return;            // author already used delimiters
        // NOTE: in a JS replacement string "$$" emits a literal "$", so a "$"
        // delimiter immediately followed by capture group 1 must be written
        // "$$$1" — "$$1" would emit a literal "1" and drop the mantissa
        // (turning "3 x 10^8" into "1 x 10^8"). Keep this block in sync with the
        // copy in exam.php.
        t = t.replace(/(\d+)\s*[x×]\s*10\^(-?\d+)/g, '$$$1 \\times 10^{$2}$$');
        t = t.replace(/(?<![a-zA-Z])(\d+)\/(\d+)(?![a-zA-Z])/g, '$\\frac{$1}{$2}$');
        t = t.replace(/√\s*(\d+(?:\.\d+)?)/g, '$\\sqrt{$1}$');
        t = t.replace(/±/g, '$\\pm$');
        t = t.replace(/×/g, '$\\times$');
        t = t.replace(/÷/g, '$\\div$');
        t = t.replace(/≤/g, '$\\leq$');
        t = t.replace(/≥/g, '$\\geq$');
        t = t.replace(/≠/g, '$\\neq$');
        t = t.replace(/∞/g, '$\\infty$');
        t = t.replace(/\/°C/g, '/$^\\circ$C');
        t = t.replace(/°C/g, '$^\\circ$C');
        t = t.replace(/°/g, '$^\\circ$');
        el.innerHTML = t;
    }

    // Pull "fill-in-the-blank" underscores OUT of math mode. A bare "_" is the
    // TeX subscript operator, so "$______$" is a KaTeX parse error. Mirrors
    // normalize_math_blanks() in admin/math-check.php (and fixMathBlanks in
    // exam.php). Single-underscore subscripts (x_1) are left untouched.
    function fixMathBlanks(el) {
        var t = el.innerHTML;
        if (t.indexOf('_') === -1 || t.indexOf('$') === -1) return;
        var out = t.replace(/(\${1,2})([^$]*?)\1/g, function (whole, delim, inner) {
            if (!/_{2,}/.test(inner)) return whole;
            return inner.split(/(_{2,})/).map(function (p) {
                if (p === '') return '';
                if (/^_{2,}$/.test(p)) return p;
                if (p.trim() === '') return p;
                return delim + p.trim() + delim;
            }).join('');
        });
        if (out !== t) el.innerHTML = out;
    }

    // Render math inside `root` (defaults to the page content area).
    function renderMath(root) {
        if (typeof window.renderMathInElement !== 'function') return;
        root = root || document.querySelector('.page-content') || document.body;
        if (!root) return;

        // Layer 0: repair fill-in-the-blank underscores that sit inside math.
        root.querySelectorAll('.mathy').forEach(fixMathBlanks);

        // Layer 2: opt-in unicode smart-formatting.
        root.querySelectorAll('.mathy').forEach(smartFormat);

        // Layer 1: delimiter rendering across the whole subtree.
        try {
            window.renderMathInElement(root, {
                delimiters: DELIMITERS,
                throwOnError: false,
                // Don't try to render math inside code/inputs.
                ignoredTags: ['script', 'noscript', 'style', 'textarea', 'pre', 'code', 'option']
            });
        } catch (e) { /* never let a math error break the page */ }
    }

    window.renderMath = renderMath;

    // KaTeX scripts are loaded with `defer`, so they may not be ready at
    // DOMContentLoaded. Retry briefly until renderMathInElement exists.
    function autoRender(attempt) {
        if (typeof window.renderMathInElement === 'function') {
            renderMath();
        } else if ((attempt || 0) < 20) {
            setTimeout(function () { autoRender((attempt || 0) + 1); }, 100);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { autoRender(0); });
    } else {
        autoRender(0);
    }
})();
