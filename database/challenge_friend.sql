-- ============================================================================
-- Challenge a Friend — head-to-head quiz duel between two ACCEPTED friends.
--
-- Both players must hold an active Pro subscription (enforced in PHP at create,
-- accept, and go-live). "Live" is implemented as short polling on the existing
-- PHP + Supabase REST stack (no websockets): both accept, enter a lobby, and on
-- a synchronized start take the SAME pinned question set under one shared timer,
-- each seeing the opponent's score/progress refreshed every few seconds.
--
-- Run this whole file once in the Supabase SQL Editor (after schema.sql).
-- ============================================================================

CREATE TABLE IF NOT EXISTS challenges (
    id SERIAL PRIMARY KEY,
    challenger_id INT REFERENCES students(id) ON DELETE CASCADE,   -- who sent it
    opponent_id   INT REFERENCES students(id) ON DELETE CASCADE,   -- invited friend
    mode VARCHAR(50) NOT NULL,            -- rapid_fire | subject_wise | full_mock
    subject VARCHAR(100),                 -- only meaningful for subject_wise
    status VARCHAR(20) NOT NULL DEFAULT 'pending'
        CHECK (status IN ('pending','accepted','live','completed','expired','cancelled')),

    -- The shared question set, pinned SERVER-SIDE when the invite is accepted, so
    -- neither player can swap in easier questions. JSON array of question ids.
    question_ids JSONB,

    -- Per-side attempt links + lobby presence + live progress (updated by polls).
    challenger_attempt_id INT REFERENCES attempts(id) ON DELETE SET NULL,
    opponent_attempt_id   INT REFERENCES attempts(id) ON DELETE SET NULL,
    challenger_ready BOOLEAN DEFAULT FALSE,
    opponent_ready   BOOLEAN DEFAULT FALSE,
    challenger_progress INT DEFAULT 0,    -- # answered so far (live)
    opponent_progress   INT DEFAULT 0,
    challenger_score NUMERIC(6,2),        -- final score, written at submit
    opponent_score   NUMERIC(6,2),
    winner_id INT REFERENCES students(id) ON DELETE SET NULL,  -- NULL = tie/undecided

    started_at     TIMESTAMP,   -- synchronized duel start (set when both ready)
    expires_at     TIMESTAMP,   -- invite must be accepted before this (now + 48h)
    lobby_deadline TIMESTAMP,   -- both must be ready before this (accept + 2 min)
    created_at     TIMESTAMP DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_challenges_opponent   ON challenges(opponent_id, status);
CREATE INDEX IF NOT EXISTS idx_challenges_challenger ON challenges(challenger_id, status);

-- Back-link an attempt to its duel so submit_exam.php / result.php can tell a
-- challenge attempt apart and find the opponent. attempts.mode already allows
-- 'challenge_friend' (see schema.sql CHECK), so only the FK is new.
ALTER TABLE attempts ADD COLUMN IF NOT EXISTS challenge_id INT REFERENCES challenges(id) ON DELETE SET NULL;

-- Preserve the question SEQUENCE per attempt. exam.php pins a question set into
-- attempt_answers at start; on resume it now reads them back in this order so the
-- sequence is stable (previously questions reshuffled on refresh) and so both
-- duel players see the SAME order. Rows created before this migration have NULL
-- position and fall back to id order (unchanged behaviour) via `order=position,id`.
ALTER TABLE attempt_answers ADD COLUMN IF NOT EXISTS position INT;
