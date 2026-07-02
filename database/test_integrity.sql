-- DDCET Platform — Test integrity & attempt-limit migration
-- Run this in the Supabase SQL Editor AFTER schema.sql.
--
-- Why: pool-based test modes (full_mock, rapid_fire, daily_challenge, ...) were
-- never recorded on the attempts row, so the app could not count how many times
-- a student took a given mode. That made daily/weekly limits and XP-farming
-- protection impossible. This adds a `mode` column plus an index to make
-- per-mode, per-day limit checks cheap.

-- 1) Record the mode on every attempt (NULL for legacy rows / fixed tests).
ALTER TABLE attempts ADD COLUMN IF NOT EXISTS mode VARCHAR(50);

-- 2) Track whether a paid custom-test credit has been consumed.
--    A captured `custom_test` payment grants exactly one custom test.
ALTER TABLE payments ADD COLUMN IF NOT EXISTS consumed_at TIMESTAMP;

-- 3) Fast lookups for "how many <mode> attempts has this student started today".
CREATE INDEX IF NOT EXISTS idx_attempts_student_mode_started
    ON attempts (student_id, mode, started_at);

-- 4) Allow the 'custom_test' plan value on payments (schema.sql has no CHECK on
--    payments.plan, so nothing to alter there — documented for clarity).

-- 5) Backfill: best-effort tag of historical pool attempts as unknown.
--    (Optional — leaves existing analytics intact; safe to skip.)
-- UPDATE attempts SET mode = 'legacy' WHERE mode IS NULL AND test_id IS NULL;

