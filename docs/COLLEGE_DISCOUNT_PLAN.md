# College Discount Coupon — Implementation Plan

## The vulnerability (why the obvious approach fails)
`students.college_id` is **self-reported** (`auth/onboarding.php` dropdown, and "Other"
even lets a user create a brand-new college). Gating any discount on it is unsafe:
anyone makes/edits an account, picks the target college, and claims the coupon.

**Rule applied:** never grant a benefit from a value the user can set themselves.

## The anchor we trust instead: `org_id`
A student's `org_id` is set **only** by entering a faculty-issued `join_code`
(`join-org.php:16-18`). That code is a shared secret distributed by the college, not
selectable in any UI. So `org_id` is a forge-proof "this student really belongs to
college X" signal. We gate the discount on it.

**Where the discount is decided:** server-side in `api/create_order.php`, which already
ignores client-supplied amounts (line 19-20). The browser never sends a price or a
discount flag, so there is nothing to tamper with.

## Decisions locked in
- Discount applies to **basic + pro only** (not the one-off custom_test).
- Percent is set via an **admin UI** (reusable for future colleges).
- Each org has a **redemption cap** to bound damage if the join code leaks.

## Changes

### 1. DB migration — `database/college_discount.sql` (new)
Add per-org discount + cap to `organizations`:
```sql
ALTER TABLE organizations ADD COLUMN IF NOT EXISTS discount_percent INT DEFAULT 0;   -- 1..100, 0 = off
ALTER TABLE organizations ADD COLUMN IF NOT EXISTS discount_max_redemptions INT;       -- NULL = unlimited
ALTER TABLE organizations ADD COLUMN IF NOT EXISTS discount_redemptions INT DEFAULT 0; -- counter
-- audit columns on payments
ALTER TABLE payments ADD COLUMN IF NOT EXISTS org_id INT REFERENCES organizations(id);
ALTER TABLE payments ADD COLUMN IF NOT EXISTS discount_percent INT DEFAULT 0;
```

### 2. `api/create_order.php` — apply discount server-side (core security point)
After resolving `$amountPaise`, before creating the Razorpay order:
- Skip entirely if `plan === 'custom_test'`.
- Load the user's org: `currentOrg()` (already in config.php).
- Apply only if ALL true: `org` exists, `is_active`, `discount_percent` in 1..100,
  and cap not exhausted (`discount_max_redemptions IS NULL` OR
  `discount_redemptions < discount_max_redemptions`):
  - `$amountPaise = max(100, (int) round($amountPaise * (100 - $pct) / 100));` (₹1 floor)
- Persist `org_id` + `discount_percent` on the `payments` row for audit.
- **Cap is counted at capture, not order creation** — increment
  `organizations.discount_redemptions` in `verify_payment.php` only when a discounted
  payment is confirmed, so abandoned checkouts don't burn the cap. (Read-modify-write;
  acceptable for this volume.)
The order amount Razorpay sees is already discounted; `verify_payment.php` keeps its
existing plan-from-server logic (never trusts client) and just adds the counter bump.

### 3. `subscription.php` — show the discount (display only, not trusted)
If `currentOrg()` has `discount_percent > 0`, show a banner ("Your college
<name> gets <pct>% off — applied automatically at checkout") and optionally a struck
price. Purely cosmetic; the real math is in create_order.php.

### 4. Admin UI — `admin/colleges.php` (extend) or new `admin/org-discounts.php`
Reuse the existing admin pattern (CSRF + POST action, like `admin/colleges.php`).
List organizations with editable `discount_percent` and `discount_max_redemptions`,
showing `discount_redemptions` used. One inline form per org row that PATCHes
`organizations`. Admin can also create the org + join code for the target college here
(reusing `generateJoinCode()` and the uniqueness loop from `institution/index.php:14-22`).

## Abuse limits
The join code is a shared secret and can leak. Bound the blast radius:
- Discount only reduces price; subscription length unchanged.
- `org.is_active` gates membership; deactivating kills the discount.
- Redemption cap (`discount_max_redemptions`) hard-stops once reached.

## Out of scope / untouched
- `auth/onboarding.php` college_id stays as-is (it's for personalization, not entitlement).
- No change to Razorpay verification/signature flow.

## Files
- NEW `database/college_discount.sql` — run once in Supabase SQL editor
- EDIT `api/create_order.php` — server-side discount (core)
- EDIT `api/verify_payment.php` — bump redemption counter on captured discounted payment
- EDIT `subscription.php` — auto-applied discount banner (display only)
- EDIT `admin/colleges.php` (or NEW `admin/org-discounts.php`) — set percent + cap, create org

## Test
- Member of discounted org → create_order returns discounted paise; non-member → full price.
- Tamper attempt: change `college_id` / send client `amount` → no effect (proves the fix).
- Cap reached → next member pays full price; counter only moves on capture.
