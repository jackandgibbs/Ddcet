-- 1. Add missing daily_streak columns to the students table
ALTER TABLE students ADD COLUMN IF NOT EXISTS daily_streak INT DEFAULT 0;
ALTER TABLE students ADD COLUMN IF NOT EXISTS daily_streak_date DATE;

-- 2. Re-run the atomic function now that the columns exist
CREATE OR REPLACE FUNCTION increment_student_xp(
  p_student_id INT, p_xp INT, p_is_daily_challenge BOOLEAN, p_today DATE
) RETURNS void AS $$
BEGIN
  UPDATE students SET
    xp = xp + p_xp,
    -- General streak: +1 if last_active was yesterday, reset to 1 if older, unchanged if today
    streak = CASE 
      WHEN last_active_date = p_today - INTERVAL '1 day' THEN streak + 1
      WHEN last_active_date = p_today THEN streak
      ELSE 1
    END,
    last_active_date = p_today,
    -- Daily challenge streak: +1 if completed today and last was yesterday, etc.
    daily_streak = CASE 
      WHEN p_is_daily_challenge THEN
        CASE 
          WHEN daily_streak_date = p_today - INTERVAL '1 day' THEN COALESCE(daily_streak, 0) + 1
          WHEN daily_streak_date = p_today THEN COALESCE(daily_streak, 0)
          ELSE 1
        END
      ELSE COALESCE(daily_streak, 0)
    END,
    daily_streak_date = CASE 
      WHEN p_is_daily_challenge THEN p_today
      ELSE daily_streak_date
    END
  WHERE id = p_student_id;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- 3. Cleanup existing duplicate in_progress attempts
-- We keep the newest attempt (max id) for each grouping, and mark the older duplicates as 'abandoned'
UPDATE attempts
SET status = 'abandoned'
WHERE status = 'in_progress'
  AND id NOT IN (
    SELECT MAX(id)
    FROM attempts
    WHERE status = 'in_progress'
    GROUP BY student_id, COALESCE(test_id, 0), mode
  );

-- 4. Now safely create the unique index since duplicates are resolved
CREATE UNIQUE INDEX IF NOT EXISTS idx_unique_inprogress_attempt
ON attempts(student_id, COALESCE(test_id, 0), mode)
WHERE status = 'in_progress';
