<?php
require_once __DIR__ . '/../config.php';
$user = requireAuth();

header('Content-Type: application/json');

// Throttle order creation: max 8 orders per 10 minutes per user. Stops a script
// (or a stuck retry loop) from flooding Razorpay and our payments table.
if (!rateLimit('create_order', 8, 600)) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many payment attempts. Please wait a few minutes and try again.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$plan = $data['plan'] ?? '';

// Server-side pricing (in ₹). Never trust a client-supplied amount — it lets a
// user pay ₹1 for the Pro plan. The client `amount` is ignored.
$PRICES = ['basic' => 149, 'pro' => 299, 'custom_test' => 29];

if (!isset($PRICES[$plan])) {
    echo json_encode(['error' => 'Invalid plan']);
    exit;
}

$amountPaise = $PRICES[$plan] * 100;

// --- Discounts (server-side only) ------------------------------------------
// Decided here, never by the client, and only for subscription plans (not the
// one-off custom_test). Two independent, un-forgeable paths exist; we apply the
// BETTER of the two, never stacking:
//   1. Institution membership — auto, from org_id set via a faculty join code.
//   2. College coupon code — entered at checkout; possession of the code is the
//      proof. A self-reported college_id is deliberately ignored either way.
// The order amount sent to Razorpay is already discounted.
$discountPct = 0;
$discountOrgId = null;
$discountCollegeId = null;
if ($plan !== 'custom_test') {
    // Path 1: institution membership (no code entered).
    $orgPct = 0; $orgId = null;
    $org = currentOrg(); // resolves from the session user's org_id
    if ($org && !empty($org['is_active'])) {
        $p = (int) ($org['discount_percent'] ?? 0);
        $cap = $org['discount_max_redemptions'];
        $used = (int) ($org['discount_redemptions'] ?? 0);
        if ($p >= 1 && $p <= 100 && ($cap === null || $cap === '' || $used < (int) $cap)) {
            $orgPct = $p; $orgId = (int) $org['id'];
        }
    }
    // Path 2: college coupon code from the checkout form.
    $colPct = 0; $colId = null;
    $coupon = resolveCollegeCoupon((string) ($data['coupon'] ?? ''));
    if ($coupon) { $colPct = (int) $coupon['discount_percent']; $colId = (int) $coupon['id']; }

    // Pick the better discount and tag its source for the redemption counter.
    if ($orgPct >= $colPct) {
        $discountPct = $orgPct; $discountOrgId = $orgId;
    } else {
        $discountPct = $colPct; $discountCollegeId = $colId;
    }
    if ($discountPct > 0) {
        // ₹1 (100 paise) floor: Razorpay rejects smaller orders.
        $amountPaise = max(100, (int) round($amountPaise * (100 - $discountPct) / 100));
    }
}

// --- Basic -> Pro upgrade credit -------------------------------------------
// Both plans expire on the same 30 June, so a user already on Basic only pays
// the difference to move up to Pro: their Basic fare is credited against the
// (already-discounted) Pro price. Applied AFTER any discount so a coupon's value
// isn't eaten by the credit. Server-side only; the client can't fake it.
$upgradeCredit = 0;
if ($plan === 'pro' && isSubscribed('basic') && !isSubscribed('pro')) {
    $upgradeCredit = $PRICES['basic'] * 100;
    $amountPaise = max(100, $amountPaise - $upgradeCredit);
}
// --------------------------------------------------------------------------

// Create Razorpay order via API
$ch = curl_init('https://api.razorpay.com/v1/orders');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_USERPWD => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode([
        'amount' => $amountPaise,
        'currency' => 'INR',
        'receipt' => 'rcpt_' . $user['id'] . '_' . time(),
    ]),
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode(['error' => 'Curl error: ' . $curlError]);
    exit;
}

$order = json_decode($response, true);

if (empty($order['id'])) {
    echo json_encode(['error' => 'Failed to create order: ' . ($order['error']['description'] ?? $response)]);
    exit;
}

// Store pending payment (amount is the discounted total actually charged).
$payIns = supabaseRest('payments', 'POST', [
    'student_id' => $user['id'],
    'razorpay_order_id' => $order['id'],
    'amount' => $amountPaise,
    'plan' => $plan,
    'status' => 'pending',
    'org_id' => $discountOrgId,
    'discount_college_id' => $discountCollegeId,
    'discount_percent' => $discountPct,
]);

// CRITICAL: if we couldn't record the order, do NOT hand it to the client. The
// Razorpay order exists, but verify_payment looks the order up in our payments
// table — a missing row means the user would pay and get nothing (no subscription,
// no receipt). Fail loudly here instead of charging silently.
if (empty($payIns)) {
    echo json_encode(['error' => 'Checkout is temporarily unavailable. Please try again shortly.']);
    exit;
}

echo json_encode(['order_id' => $order['id'], 'amount' => $amountPaise]);
