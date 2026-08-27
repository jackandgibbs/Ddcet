<?php
require_once __DIR__ . '/../config.php';
$user = requireAuth();
requireCsrf();

$data = json_decode(file_get_contents('php://input'), true);
$paymentId = $data['razorpay_payment_id'] ?? '';
$orderId = $data['razorpay_order_id'] ?? '';
$signature = $data['razorpay_signature'] ?? '';

if (!$paymentId || !$orderId || !$signature) {
    echo json_encode(['error' => 'Missing data']);
    exit;
}

// Verify signature
$expectedSignature = hash_hmac('sha256', $orderId . '|' . $paymentId, RAZORPAY_KEY_SECRET);
if (!hash_equals($expectedSignature, $signature)) {
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

// Authoritative plan comes from the payment row we created server-side — never from the client,
// otherwise a user could order 'basic' and verify as 'pro'.
$paymentRow = supabaseRest('payments?razorpay_order_id=eq.' . urlencode($orderId) . '&student_id=eq.' . $user['id'] . '&select=plan,status,org_id,discount_college_id,discount_percent,amount&limit=1');
$plan = $paymentRow[0]['plan'] ?? '';
if (!in_array($plan, ['basic', 'pro', 'custom_test'])) {
    echo json_encode(['error' => 'Invalid or unknown order']);
    exit;
}
$alreadyCaptured = ($paymentRow[0]['status'] ?? '') === 'captured';

// Update payment as captured.
supabaseRest('payments?razorpay_order_id=eq.' . urlencode($orderId) . '&student_id=eq.' . $user['id'], 'PATCH', [
    'razorpay_payment_id' => $paymentId,
    'status' => 'captured',
]);

// Count the discount redemption against its source cap — but only the first time
// this order is captured, and only if it actually carried a discount. Counting
// here (not at order creation) means abandoned checkouts never burn the cap.
// Exactly one of org_id / discount_college_id is set (create_order picks one).
$discOrgId = (int) ($paymentRow[0]['org_id'] ?? 0);
$discCollegeId = (int) ($paymentRow[0]['discount_college_id'] ?? 0);
$discPct = (int) ($paymentRow[0]['discount_percent'] ?? 0);
if (!$alreadyCaptured && $discPct > 0) {
    if ($discOrgId > 0) {
        supabaseRpc('increment_discount_redemption', ['p_table' => 'organizations', 'p_id' => $discOrgId]);
    } elseif ($discCollegeId > 0) {
        supabaseRpc('increment_discount_redemption', ['p_table' => 'colleges', 'p_id' => $discCollegeId]);
    }
}

// A custom_test purchase is a one-off credit, NOT a subscription. It is consumed
// later by custom-test.php (which sets consumed_at). Just confirm capture here.
if ($plan === 'custom_test') {
    echo json_encode(['ok' => true, 'plan' => 'custom_test']);
    exit;
}

// Create subscription — annual, valid until the coming 30 June (exam cycle).
$expiresAt = subscriptionExpiryDate();
$subResult = supabaseRest('subscriptions', 'POST', [
    'student_id' => $user['id'],
    'plan' => $plan,
    'status' => 'active',
    'expires_at' => $expiresAt,
]);
$subId = $subResult[0]['id'] ?? null;

// Upgrading to Pro supersedes any active Basic plan (the user was credited the
// Basic fare at checkout). Cancel it so the dashboard and getSubscription() show
// Pro cleanly — both share the same 30 June expiry, so a tie-break could
// otherwise surface the old Basic row.
if ($subId && $plan === 'pro') {
    supabaseRest('subscriptions?student_id=eq.' . $user['id'] . '&plan=eq.basic&status=eq.active', 'PATCH', ['status' => 'cancelled']);
}

// Link payment to subscription
if ($subId) {
    supabaseRest('payments?razorpay_order_id=eq.' . urlencode($orderId), 'PATCH', ['subscription_id' => $subId]);
}

// Send notification
supabaseRest('notifications', 'POST', [
    'student_id' => $user['id'],
    'title' => 'Plan Activated!',
    'body' => ucfirst($plan) . ' plan active until ' . date('d M Y', strtotime($expiresAt)),
    'type' => 'subscription',
]);

// Email receipt (transactional — always sent regardless of opt-out).
if (!empty($user['email'])) {
    $rupees = number_format(((int) ($paymentRow[0]['amount'] ?? 0)) / 100, 2);
    $validTill = date('d M Y', strtotime($expiresAt));
    $body = 'Your <strong>' . htmlspecialchars(ucfirst($plan)) . ' plan</strong> is now active. Here\'s your receipt:'
          . '<br><br><table cellpadding="0" cellspacing="0" style="font-size:14px;">'
          . '<tr><td style="padding:4px 16px 4px 0;color:#888;">Plan</td><td><strong>' . htmlspecialchars(ucfirst($plan)) . '</strong></td></tr>'
          . '<tr><td style="padding:4px 16px 4px 0;color:#888;">Amount paid</td><td>₹' . $rupees . '</td></tr>'
          . '<tr><td style="padding:4px 16px 4px 0;color:#888;">Valid until</td><td>' . $validTill . '</td></tr>'
          . '<tr><td style="padding:4px 16px 4px 0;color:#888;">Payment ID</td><td style="font-family:monospace;font-size:12px;">' . htmlspecialchars($paymentId) . '</td></tr>'
          . '</table><br>Subscriptions don\'t auto-renew — you\'re in full control.';
    sendEmail(
        $user['email'], $user['name'] ?? '',
        ucfirst($plan) . ' plan activated — ' . MAIL_FROM_NAME . ' receipt',
        emailTemplate('Payment confirmed', $body, 'Go to dashboard', APP_URL . BASE_PATH . 'dashboard.php')
    );
}

echo json_encode(['ok' => true, 'plan' => $plan, 'expires_at' => $expiresAt]);
