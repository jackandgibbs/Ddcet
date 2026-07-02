# Security Audit & Remediation — DDCET Prep

**Date:** 2026-06-14
**Scope:** Whole directory (`/c/xampp/htdocs/Dddcet`) — ~60 PHP/HTML/config files.
**Stack:** PHP on XAMPP, Supabase (PostgREST) as the datastore via a hand-rolled
SQL→REST translation layer (`config.php` → `SupaStatement`), Google/Supabase OAuth,
Razorpay payments, TOTP admin 2FA.

This document has two parts:
1. **Fixes already applied** in this pass (safe, isolated, verified — all files still
   `php -l` clean).
2. **Remediation plan** for the systemic / higher-risk items that need coordinated
   changes, secret rotation, or testing before rollout.

---

## 1. Fixes applied in this pass

| # | File(s) | Issue | Severity | Fix |
|---|---------|-------|----------|-----|
| 1 | `wb-admin/index.php`, `.env`, `config.php` | **Hardcoded admin credentials** (`admin` / `ddcet@2026`) in source | CRITICAL | Moved to `.env` (`WB_ADMIN_USERNAME`, `WB_ADMIN_PASSWORD_HASH` = bcrypt). Now uses `password_verify()` + `hash_equals()`, and `session_regenerate_id(true)` on login (anti-fixation). **You must still rotate the password — see plan §A.** |
| 2 | `admin/predictor.php` | Broken authz: referenced undefined `$user['role']`, so the page **always redirected** (dead) and the role check was non-functional; REST helper concatenated filter values without encoding | HIGH | Assigned `$user = requireAdmin();`, removed the dead/broken role block, `urlencode()` on filter keys/values |
| 3 | `api/rate_question.php` | INSERT routed through `SupaStatement`, which maps **param names to column names** (`:qid`→`qid`), so it wrote nonexistent columns and silently failed | HIGH (functional) | Rewrote to call `supabaseRest()` with real column names + `resolution=merge-duplicates` upsert |
| 4 | `profile.php` | **IDOR over-fetch**: `select=*` for any `?id=`, pulling email / mobile / google_id / referral data of other users | MEDIUM | Restricted columns to a public-safe subset unless viewing your own profile |
| 5 | `exam.php` | `$questionsJson` echoed in `<script>` without `JSON_HEX_TAG` → a stored `</script>` in any question/option breaks out of the script block | MEDIUM | Added `JSON_HEX_TAG | JSON_HEX_AMP` to `json_encode` |
| 6 | `admin/students.php` | Reflected XSS: `$success` (built from POST `email`) echoed unescaped | HIGH | `htmlspecialchars($success)` |
| 7 | `admin/community.php`, `materials.php`, `tests.php`, `resources.php`, `payments.php`, `questions.php`, `result.php` | Stored XSS: `category` / `type` / `mode` / `razorpay_order_id` / `difficulty` echoed unescaped | MEDIUM | Wrapped each in `htmlspecialchars()` |
| 8 | `config.php` + all 9 admin mutating pages | **No CSRF protection** anywhere despite helpers existing — forged POSTs could ban users, grant free Pro, or **delete the entire question bank** (`admin/dedup.php` `delete_all`) | HIGH | Added `requireCsrf()` helper to `config.php`; added `requireCsrf()` to every admin POST handler and a hidden CSRF token to every admin form (`dedup, students, community, materials, tests, resources, colleges, doubts, questions`) |

### How the new CSRF helper works
```php
// config.php
requireCsrf();   // call at top of any POST handler
// forms:
<input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
// JS/fetch callers can instead send the header:  X-CSRF-Token: <token>
```

---

## 2. Remediation plan (not yet applied — needs your action / testing)

### A. Rotate all exposed production secrets — **URGENT**
`.env` contains **live** credentials that have been sitting in the working tree:
- `RAZORPAY_KEY_ID=rzp_live_…` + `RAZORPAY_KEY_SECRET` (live payment keys)
- `SUPABASE_KEY` (anon JWT) and `SUPABASE_DB_PASS`
- `GOOGLE_CLIENT_SECRET`
- `ADMIN_TOTP_SECRET`
- the wb-admin password (now hashed, but the plaintext `ddcet@2026` is known)

`.htaccess` blocks `.env` over the web **only if `AllowOverride` is on** and Apache is
serving the site — on nginx, or with `AllowOverride None`, `.env` is downloadable.

**Plan:**
1. Rotate **every** secret above in its provider console (Razorpay, Supabase,
   Google Cloud), regenerate `ADMIN_TOTP_SECRET`, and set a new wb-admin password
   (`php -r 'echo password_hash("NEW", PASSWORD_BCRYPT);'`).
2. Move `.env` **outside** the web root, or confirm the `<Files .env>` deny rule is
   actually enforced (`curl http://localhost/Dddcet/.env` must 403).
3. Treat the current secrets as compromised (they are in this repo's history).

### B. Finish the CSRF rollout to user-facing handlers
The admin panel is done. The same pattern must be applied to these POST handlers
(all currently unprotected):
`friends.php` (add/accept/reject), `community.php` (post/react/bookmark),
`doubts.php` (ask), `bookmarks.php` (remove), `notifications.php` (mark_read),
`profile.php` (update_profile), `auth/onboarding.php`.
Also convert `api/dismiss_popup.php` from GET to POST + token.
For the `api/*.php` JSON endpoints (`save_answer`, `submit_exam`, `create_order`,
`verify_payment`, `rate_question`) add the `X-CSRF-Token` header check via
`requireCsrf()` and emit the token into the exam/checkout pages for the fetch calls.

### C. Replace or harden the `SupaStatement` SQL-emulator — **architectural**
`config.php`'s `SupaStatement` is a regex-based SQL→PostgREST translator and is the
single biggest source of latent bugs **and** security risk:
- `handleInsert()` uses **param names as column names** (bug that already broke
  `rate_question` — there may be others; grep for `->prepare(` / `->execute(`).
- `whereToFilter()` only understands a fixed set of simple conditions and **silently
  drops** anything else (`OR`, `IN`, `LIKE`, functions, un-substituted `:params`).
  A dropped `WHERE` on an UPDATE/DELETE can hit far more rows than intended, and a
  dropped ownership clause defeats access control (the team already worked around
  this manually in `api/result_pdf.php`).
- `SET col = col + :param` increments are silently skipped.
- Param substitution uses `addslashes()`, not real parameterization.

**Plan:** stop using `getDB()->prepare()` for anything with a non-trivial `WHERE`.
Migrate all data access to direct `supabaseRest()` calls with explicit, `urlencode()`d
/ `(int)`-cast PostgREST filters (the API endpoints already do this well — use them as
the template). As an interim guard, make `whereToFilter()` **fail closed** (throw)
on any condition it can't translate, instead of dropping it. Audit every remaining
`->prepare(`/`->query(` call site after that.

### D. Make XP / badge / streak awards atomic and server-authoritative
`achievements.php` runs `checkBadges()` on **every page GET** and awards XP via a
non-atomic read-modify-write (`read xp` → `xp = xp + reward`). Concurrent requests
(parallel tabs) double-award badges and XP.
**Plan:** (1) add a DB unique constraint on `student_badges(student_id, badge_id)`;
(2) award XP via an atomic Postgres RPC (`xp = xp + n` in the DB), not PHP
read-modify-write; (3) trigger progression only from the server-side
attempt-completion path (`api/submit_exam.php`), not from page views.

### E. Decide auth posture for `predictor.php` and `college-finder.php`
Neither calls `requireAuth()` — the predictor/college-finder pages and their data are
fully public. If that's intended (marketing/SEO), leave it but add light rate-limiting.
If these are meant to be member features, add `$user = requireAuth();` at the top.
*(Left as-is pending your decision — changing it affects UX.)*

### F. Smaller hardening items
- **`auth/callback.php`**: call `session_regenerate_id(true)` right after setting
  `$_SESSION['user']` (anti session-fixation); avoid passing `access_token` in the
  query string (it lands in logs/history) — post it or keep it in the hash exchange.
- **`config.php` cache**: `supabaseRest()` caches **all** GET responses to a shared
  temp file for 10s, including `isSubscribed()` / `attemptsStartedToday()`. This lets a
  user briefly bypass the per-day attempt cap by spamming refresh, and risks
  cross-user staleness. Don't cache auth/subscription/limit queries (or key the cache
  per user).
- **`admin/materials.php` / `admin/resources.php`**: stored `file_url` / `video_url` /
  `url` are rendered into `href` without scheme validation — a `javascript:` URL would
  execute on click. Validate `http(s)` scheme on insert.
- **`custom-test.php`**: credit consumption (check `consumed_at` → later set it) is a
  TOCTOU race; two concurrent requests can spend one ₹29 credit twice. Make the PATCH
  conditional on `&consumed_at=is.null` and require it to affect a row.
- **`auth/onboarding.php`**: referrals are marked `completed` immediately at signup —
  enables referral farming with throwaway accounts. Mark `pending` and complete only
  after a qualifying action/payment.
- **`exam.php` (client)**: `q.text` / `opt.text` are injected via `innerHTML`. Since
  content is admin-sourced the risk is limited, but for defense-in-depth render via a
  sanitizer or `textContent` (note: naive escaping will break intended `<sub>`/`<sup>`
  and KaTeX formatting — use an allow-list sanitizer).
- **`auth/logout.php`**: calls `session_start()` again after `config.php` already
  started it (harmless notice); minor cleanup.

---

## 3. What was checked and found OK
- **Payment verification** (`api/verify_payment.php`): Razorpay HMAC signature verified
  with `hash_equals`; plan read **server-side** from the order row, not the client. Sound
  (consider adding idempotency + amount cross-check — see note).
- **Order creation** (`api/create_order.php`): server-side pricing; client `amount` ignored.
- **Exam submit / save answer** (`api/submit_exam.php`, `api/save_answer.php`): ownership
  checks, server-side scoring, server clock enforcement, option/question validation. Solid.
- **Admin gating**: `requireAdmin()` (is_admin + TOTP 2FA) is called **before** input
  processing on every admin page; `admin/includes/header.php` re-enforces it.
- **TOTP** (`includes/totp.php`): correct RFC-6238 implementation, constant-time compare,
  rate-limited verify with lockout, `session_regenerate_id` on success.
- **result.php / dashboard.php / history.php / billing.php / leaderboard.php**: queries
  scoped to the session user id; output escaped. No IDOR/injection found.
- No RCE sinks (`eval`/`exec`/`system`/`shell_exec`/`include $_GET`) anywhere.

---

## Severity tally (whole codebase)
- **CRITICAL:** 2 (hardcoded admin creds ✅fixed; exposed live secrets → plan §A)
- **HIGH:** ~6 (CSRF systemic ✅admin fixed/▶user-facing planned; students XSS ✅; predictor authz ✅; rate_question ✅; achievements atomicity ▶plan)
- **MEDIUM:** ~10 (stored/reflected XSS ✅admin escapes; profile IDOR ✅; SupaStatement ▶plan; cache ▶plan; URL-scheme ▶plan; custom-test TOCTOU ▶plan; public predictor/college-finder ▶decision)
- **LOW:** several (referral farming, logout double-start, JS-context echoes) ▶plan

✅ = fixed this pass · ▶ = in remediation plan
