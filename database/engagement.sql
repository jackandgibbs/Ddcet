-- DDCET Platform — Engagement & Conversion (daily streak + Top-10 rewards)
-- Run this in the Supabase SQL Editor.
--
-- 1) Daily-challenge streak. The existing students.streak tracks ANY activity;
--    these two columns track the consecutive-day streak of completing the once-
--    a-day Daily Challenge specifically (the "Day 14 🔥" habit driver). They are
--    bumped server-side in api/submit_exam.php on a daily_challenge completion.
ALTER TABLE students ADD COLUMN IF NOT EXISTS daily_streak       INT  DEFAULT 0;
ALTER TABLE students ADD COLUMN IF NOT EXISTS daily_streak_date  DATE;

-- 2) Mock rewards. Records that a student has claimed a reward for a scheduled
--    mock (currently "finish Top 10 of a FREE mock → 1 month Pro free"). The
--    UNIQUE(test_id, student_id) makes the grant idempotent: a student can claim
--    a given mock's reward exactly once. The actual entitlement is a normal
--    subscriptions row inserted alongside this; this table just stops re-claims.
CREATE TABLE IF NOT EXISTS mock_rewards (
    id            SERIAL PRIMARY KEY,
    test_id       INT REFERENCES tests(id)    ON DELETE CASCADE,
    student_id    INT REFERENCES students(id) ON DELETE CASCADE,
    reward        VARCHAR(50) DEFAULT 'pro_1_month',
    rank_at_claim INT,
    created_at    TIMESTAMP DEFAULT NOW(),
    UNIQUE(test_id, student_id)
);

CREATE INDEX IF NOT EXISTS idx_mock_rewards_student ON mock_rewards(student_id);
