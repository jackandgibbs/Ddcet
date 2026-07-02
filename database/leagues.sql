-- DDCET Platform — Weekly Leagues (Duolingo-style)
-- Run this in the Supabase SQL Editor.
--
-- Every student sits in a tier (Bronze → Silver → Gold → Platinum → Diamond).
-- Within a tier they are ranked each week by XP earned THAT WEEK (summed live
-- from xp_log — no snapshot needed, it resets by date window). At week rollover
-- the top promote and the bottom relegate; that is an admin-triggered action
-- (admin/leagues.php). `league_week` marks the last Monday a student was rolled,
-- which makes the rollover idempotent within a week.

ALTER TABLE students ADD COLUMN IF NOT EXISTS league_tier VARCHAR(20) DEFAULT 'Bronze';
ALTER TABLE students ADD COLUMN IF NOT EXISTS league_week DATE;

CREATE INDEX IF NOT EXISTS idx_students_league ON students(league_tier);
