# Challenge a Friend — Implementation Plan

Head-to-head quiz duel between two **accepted friends**, **both must be Pro**.
"Live via polling" on the existing PHP + Supabase REST stack — no new infra.

## Decisions
- **Live via polling**: synchronized start, one shared countdown, opponent's live
  progress + score polled every ~3s. Resilient to disconnects (resync on next poll).
- **Challenger picks mode**: `rapid_fire` (30Q/30m), `subject_wise` (30Q/30m), or
  `full_mock` (100Q/150m) — reuses exam.php `$poolConfigs`. Same question set pinned
  for both players.
- **Pro gating, both sides**: challenger must be Pro to send; invited friend must be
  Pro to accept (else invite shows an Upgrade prompt → subscription.php).
- **Timers**: invite expires 48h if not accepted; once accepted, both have **2 min**
  to enter the lobby or the duel auto-cancels.

## What already exists (reused, not rebuilt)
- `friends` table (status=accepted) — opponent selection.
- `attempts` + `attempt_answers` — `attempt_answers.question_id` pins the exact
  ordered question set per attempt; `exam.php?attempt_id=` replays it. `mode` already
  allows `'challenge_friend'` (schema CHECK).
- `api/submit_exam.php` — deterministic scoring (+2 / −0.5), writes score to attempt.
- `notifications` table + `notifications.php` render.
- `isSubscribed('pro')`, `getSubscription()`, `modeLabel('challenge_friend')`.
- exam.php `$poolConfigs`, `modeMinPlan()`, `modeDurationMinutes()`.

## New DB — `database/challenge_friend.sql`
```sql
CREATE TABLE IF NOT EXISTS challenges (
    id SERIAL PRIMARY KEY,
    challenger_id INT REFERENCES students(id) ON DELETE CASCADE,  -- sender
    opponent_id   INT REFERENCES students(id) ON DELETE CASCADE,  -- invited friend
    mode VARCHAR(50) NOT NULL,             -- rapid_fire | subject_wise | full_mock
    subject VARCHAR(100),                  -- only for subject_wise
    status VARCHAR(20) NOT NULL DEFAULT 'pending'
        CHECK (status IN ('pending','accepted','live','completed','expired','cancelled')),
    -- the pinned shared question set (JSON array of question_ids), set at accept
    question_ids JSONB,
    -- per-side attempt + presence + live progress (polled)
    challenger_attempt_id INT REFERENCES attempts(id) ON DELETE SET NULL,
    opponent_attempt_id   INT REFERENCES attempts(id) ON DELETE SET NULL,
    challenger_ready BOOLEAN DEFAULT FALSE,
    opponent_ready   BOOLEAN DEFAULT FALSE,
    challenger_progress INT DEFAULT 0,     -- # answered, updated by poll
    opponent_progress   INT DEFAULT 0,
    challenger_score NUMERIC(6,2),
    opponent_score   NUMERIC(6,2),
    winner_id INT REFERENCES students(id) ON DELETE SET NULL,  -- NULL = tie/undecided
    started_at TIMESTAMP,                  -- synchronized duel start (set when both ready)
    expires_at TIMESTAMP,                  -- invite 48h deadline
    lobby_deadline TIMESTAMP,              -- accept + 2 min
    created_at TIMESTAMP DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_challenges_opponent  ON challenges(opponent_id, status);
CREATE INDEX IF NOT EXISTS idx_challenges_challenger ON challenges(challenger_id, status);

-- tag attempts so result.php / history can identify duel attempts
-- (attempts.mode already allows 'challenge_friend'; add a back-link)
ALTER TABLE attempts ADD COLUMN IF NOT EXISTS challenge_id INT REFERENCES challenges(id) ON DELETE SET NULL;
```

## State machine
`pending` → (opponent accepts, both Pro) → `accepted` (lobby_deadline = now+2m)
→ (both ready in lobby) → `live` (started_at set, shared timer begins)
→ (both attempts submitted) → `completed` (winner computed)
Side exits: invite not accepted by `expires_at` → `expired`; lobby not filled by
`lobby_deadline` → `cancelled`; either party cancels while pending → `cancelled`.

## New endpoints (`api/`)
All `requireAuth()` + `requireCsrf()` (POST) + rate-limited, mirroring existing APIs.
1. `challenge_create.php` — challenger picks friend + mode (+subject). Guards:
   friendship is `accepted`, challenger `isSubscribed('pro')`, no existing open
   challenge between the pair. Inserts `pending` row, sets `expires_at=+48h`,
   notifies opponent.
2. `challenge_respond.php` — opponent accept/decline. On accept: require opponent
   Pro (else return needsUpgrade), set `accepted`, `lobby_deadline=+2m`, **pin
   question_ids server-side** from the mode's pool (single source so both share the
   exact set), notify challenger.
3. `challenge_ready.php` — mark caller ready in lobby. When BOTH ready: create both
   `attempts` rows + their `attempt_answers` from `question_ids`, set `live`,
   `started_at=now`, store the two attempt ids.
4. `challenge_state.php` — **the poll endpoint** (GET). Returns status, both
   ready/progress/score, started_at, server time (for timer sync), winner. Used by
   lobby + in-exam opponent bar + result.
5. `challenge_progress.php` — lightweight POST from exam.php to update caller's
   `*_progress` (answered count) during the duel.
6. Winner computed in `submit_exam.php` extension (below), not a separate call.

## Edits to existing files
- **config.php**:
  - `modeMinPlan()`: add `'challenge_friend' => 'pro'`.
  - Helper `challengeQuestionIds($mode,$subject)` — builds the pinned set reusing the
    same pool logic exam.php uses (extract to shared helper to avoid drift).
  - Helper `currentChallengeFor($attemptId)` for submit/result.
- **exam.php**: accept `?attempt_id=` that belongs to a challenge (already supported
  via resume path). Add a thin **opponent live bar** (name + live score + progress)
  that polls `challenge_state.php` every 3s, and a shared timer seeded from
  `started_at + duration`. Reuses existing question rendering untouched.
- **api/submit_exam.php**: after writing the attempt score, if the attempt has a
  `challenge_id`, write that side's score into `challenges`; if both sides done, set
  `completed` + compute `winner_id` (higher score; tie = NULL), notify both, award a
  small XP/streak bonus to the winner (optional, follow existing xp pattern).
- **result.php**: if `mode==='challenge_friend'`, render a **side-by-side comparison**
  (you vs opponent: score, correct, time) + Win/Loss/Tie banner. If opponent not
  finished yet, show "waiting for opponent" with a poll.
- **friends.php**: add a **"Challenge"** button per accepted friend (opens a small
  mode picker → calls `challenge_create.php`). Show Pro lock if viewer not Pro.
- **subscription.php**: "Challenge a Friend" already listed; link it to the new page.
- **includes/header.php**: nav link **"Challenges"** → new `challenges.php`, with a
  pending-invite count badge (optional).

## New page — `challenges.php`
Tabbed list: **Invites** (incoming pending → Accept/Decline, Pro gate), **Sent**
(outgoing pending → Cancel), **Live/Lobby** (resume), **History** (completed with
W/L/T + scores). "New Challenge" picks a friend + mode. The lobby view (waiting for
both ready, 2-min countdown, auto-start when `live`) lives here, then redirects both
to `exam.php?attempt_id=...` when the duel goes live.

## Anti-abuse / integrity
- Question set pinned **server-side at accept**, never sent by client → neither player
  can swap to easier questions.
- Both `attempts` created server-side from the same `question_ids` → identical paper.
- Pro re-checked at create AND accept AND go-live (subscription could lapse between).
- Rate-limit `challenge_create` (e.g. 20/hour) to stop invite spam.
- `attempt_id` ownership enforced (existing `student_id=eq` filters) so a player can't
  read/submit the opponent's attempt.
- Poll endpoints return only non-sensitive aggregate progress (count + score), not the
  opponent's answers, during the live duel.

## Build order (tasks)
1. `database/challenge_friend.sql` (run once in Supabase).
2. config.php helpers + `modeMinPlan` gate + shared pool helper.
3. API: create → respond → ready → state → progress.
4. submit_exam.php winner/score write-back.
5. challenges.php (list + lobby) and friends.php Challenge button.
6. exam.php opponent bar + shared timer; result.php comparison.
7. header nav + subscription.php link.
8. Lint all PHP (`/c/xampp/php/php.exe -l`).

## Notes / limits to call out
- "Live" is ~3s-latency polling, not sockets — opponent score lags a few seconds.
- Expiry/lobby timeouts are enforced lazily (checked on read in `challenge_state.php`
  and on the challenges page), since there is no cron. A stale `pending`/`accepted`
  row is treated as expired/cancelled when next observed past its deadline.
