-- DDCET Platform — Scheduled Mock Series (foundation)
-- Run this in the Supabase SQL Editor.
--
-- A "scheduled mock" reuses the existing tests + attempts flow. It is just a
-- normal `tests` row (mode = 'full_mock') that has a fixed window: it opens at
-- opens_at, closes at closes_at, and reveals All-India Rank + solutions at
-- results_at (or as soon as an admin flips results_published). Everyone takes
-- the same paper in the same window, so exactly one attempt counts and AIR is
-- fair. AIR is computed live from `attempts` (no snapshot needed).

ALTER TABLE tests ADD COLUMN IF NOT EXISTS is_scheduled       BOOLEAN   DEFAULT FALSE;
ALTER TABLE tests ADD COLUMN IF NOT EXISTS opens_at           TIMESTAMP;
ALTER TABLE tests ADD COLUMN IF NOT EXISTS closes_at          TIMESTAMP;
ALTER TABLE tests ADD COLUMN IF NOT EXISTS results_at         TIMESTAMP;
ALTER TABLE tests ADD COLUMN IF NOT EXISTS results_published  BOOLEAN   DEFAULT FALSE;
ALTER TABLE tests ADD COLUMN IF NOT EXISTS series_label       VARCHAR(100);

CREATE INDEX IF NOT EXISTS idx_tests_scheduled ON tests(is_scheduled, opens_at);

-- One registration per student per scheduled mock. Powers the "X,XXX aspirants
-- registered" FOMO counter and lets us notify/remind registrants later.
CREATE TABLE IF NOT EXISTS mock_registrations (
    id          SERIAL PRIMARY KEY,
    test_id     INT REFERENCES tests(id) ON DELETE CASCADE,
    student_id  INT REFERENCES students(id) ON DELETE CASCADE,
    created_at  TIMESTAMP DEFAULT NOW(),
    UNIQUE(test_id, student_id)
);

CREATE INDEX IF NOT EXISTS idx_mock_reg_test ON mock_registrations(test_id);
