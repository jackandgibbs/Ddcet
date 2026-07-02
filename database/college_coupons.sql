-- ============================================================================
-- College discount coupons
--
-- A discount coupon scoped to a specific COLLEGE (the colleges table from
-- auth/onboarding.php), as opposed to the institution/organization path in
-- database/college_discount.sql.
--
-- The vulnerability this design avoids: students.college_id is self-reported
-- and forgeable — anyone can pick or invent a college in onboarding, so it can
-- never gate a benefit. The trusted anchor here is POSSESSION OF A SECRET CODE:
-- the college distributes its discount_code only to its own students, and the
-- student enters it at checkout. Setting college_id to the target college does
-- nothing without the code. A leaked code is bounded by discount_max_redemptions.
--
-- Run this whole file once in the Supabase SQL Editor.
-- ============================================================================

-- Per-college coupon settings.
--   discount_code             the secret code the college hands to its students.
--                             NULL = this college has no coupon. Stored UPPERCASE.
--   discount_percent          1..100 -> percent off basic/pro. 0 = no discount.
--   discount_max_redemptions  NULL   -> unlimited. Otherwise a hard cap on how
--                                       many discounted subscriptions the code
--                                       can produce (bounds a leaked code).
--   discount_redemptions      counter, incremented only when a discounted
--                                       payment is actually CAPTURED.
ALTER TABLE colleges ADD COLUMN IF NOT EXISTS discount_code VARCHAR(24);
ALTER TABLE colleges ADD COLUMN IF NOT EXISTS discount_percent INT DEFAULT 0;
ALTER TABLE colleges ADD COLUMN IF NOT EXISTS discount_max_redemptions INT;
ALTER TABLE colleges ADD COLUMN IF NOT EXISTS discount_redemptions INT DEFAULT 0;

-- One code maps to at most one college (case-normalised to uppercase on write).
CREATE UNIQUE INDEX IF NOT EXISTS idx_colleges_discount_code
    ON colleges(discount_code) WHERE discount_code IS NOT NULL;

-- payments audit: which college coupon (if any) granted the discount. The org
-- path already records org_id + discount_percent (database/college_discount.sql).
ALTER TABLE payments ADD COLUMN IF NOT EXISTS discount_college_id INT REFERENCES colleges(id) ON DELETE SET NULL;
