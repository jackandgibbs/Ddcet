-- Atomic XP increment + streak update
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
          WHEN daily_streak_date = p_today - INTERVAL '1 day' THEN daily_streak + 1
          WHEN daily_streak_date = p_today THEN daily_streak
          ELSE 1
        END
      ELSE daily_streak
    END,
    daily_streak_date = CASE 
      WHEN p_is_daily_challenge THEN p_today
      ELSE daily_streak_date
    END
  WHERE id = p_student_id;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Atomic discount redemption increment with cap check
CREATE OR REPLACE FUNCTION increment_discount_redemption(
  p_table TEXT, p_id INT
) RETURNS BOOLEAN AS $$
DECLARE
  updated INT;
BEGIN
  IF p_table = 'organizations' THEN
    UPDATE organizations SET discount_redemptions = COALESCE(discount_redemptions, 0) + 1
    WHERE id = p_id AND (discount_max_redemptions IS NULL OR COALESCE(discount_redemptions, 0) < discount_max_redemptions);
  ELSIF p_table = 'colleges' THEN
    UPDATE colleges SET discount_redemptions = COALESCE(discount_redemptions, 0) + 1
    WHERE id = p_id AND (discount_max_redemptions IS NULL OR COALESCE(discount_redemptions, 0) < discount_max_redemptions);
  ELSE
    RETURN FALSE;
  END IF;
  GET DIAGNOSTICS updated = ROW_COUNT;
  RETURN updated > 0;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Atomic tab switch increment
CREATE OR REPLACE FUNCTION increment_tab_switches(p_attempt_id INT)
RETURNS INT AS $$
UPDATE attempts SET tab_switches = COALESCE(tab_switches, 0) + 1
WHERE id = p_attempt_id
RETURNING tab_switches;
$$ LANGUAGE sql SECURITY DEFINER;
