-- ============================================================================
-- College discount coupon
--
-- A discount that can ONLY be claimed by students who genuinely belong to a
-- college, verified through the institution join code (join-org.php). The
-- student's self-reported college_id (auth/onboarding.php dropdown) is NOT
-- trusted for this — anyone can pick or invent a college there. The trusted
-- anchor is students.org_id, which is set only by entering the faculty-issued
-- join_code. The discount is applied server-side in api/create_order.php.
--
-- Run this whole file once in the Supabase SQL Editor. Requires the
-- institutions layer (database/institutions.sql) to have been run first.
-- ============================================================================

-- Per-org discount settings.
--   discount_percent          1..100 -> percent off basic/pro. 0 = no discount.
--   discount_max_redemptions  NULL   -> unlimited. Otherwise a hard cap on how
--                                       many discounted subscriptions this org
--                                       can produce (bounds a leaked join code).
--   discount_redemptions      counter, incremented only when a discounted
--                                       payment is actually CAPTURED.
ALTER TABLE organizations ADD COLUMN IF NOT EXISTS discount_percent INT DEFAULT 0;
ALTER TABLE organizations ADD COLUMN IF NOT EXISTS discount_max_redemptions INT;
ALTER TABLE organizations ADD COLUMN IF NOT EXISTS discount_redemptions INT DEFAULT 0;

-- Audit trail on each payment: which org granted the discount and how much.
-- Lets us reconcile org.discount_redemptions and answer "why was this cheaper?".
ALTER TABLE payments ADD COLUMN IF NOT EXISTS org_id INT REFERENCES organizations(id) ON DELETE SET NULL;
ALTER TABLE payments ADD COLUMN IF NOT EXISTS discount_percent INT DEFAULT 0;
