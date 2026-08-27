<?php
// Preview a college coupon code so subscription.php can show the discounted price
// before checkout. This is NOT authoritative — api/create_order.php re-validates
// the same code server-side via resolveCollegeCoupon() when the order is created.
require_once __DIR__ . '/../config.php';
requireAuth();
requireCsrf();

header('Content-Type: application/json');

// Rate-limit to stop someone brute-forcing the code space: 15 tries / 5 min.
if (!rateLimit('validate_coupon', 15, 300)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Too many attempts. Please wait a few minutes.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$coupon = resolveCollegeCoupon((string) ($data['coupon'] ?? ''));

if (!$coupon) {
    echo json_encode(['ok' => false, 'error' => 'Invalid or expired coupon code.']);
    exit;
}

echo json_encode([
    'ok' => true,
    'percent' => (int) $coupon['discount_percent'],
    'college' => $coupon['name'],
]);
