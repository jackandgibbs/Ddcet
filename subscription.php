<?php
require_once __DIR__ . '/config.php';
$user = requireAuth();
$db = getDB();
$pageTitle = 'Subscription';

$subscription = getSubscription();

// College discount preview. This is DISPLAY ONLY — the authoritative discount is
// computed server-side in api/create_order.php from the verified org_id. Shown
// only for org members whose org has a live discount and remaining cap.
$orgDiscount = 0;
$discountOrg = currentOrg();
if ($discountOrg && !empty($discountOrg['is_active'])) {
    $pct = (int) ($discountOrg['discount_percent'] ?? 0);
    $cap = $discountOrg['discount_max_redemptions'];
    $used = (int) ($discountOrg['discount_redemptions'] ?? 0);
    if ($pct >= 1 && $pct <= 100 && ($cap === null || $cap === '' || $used < (int) $cap)) {
        $orgDiscount = $pct;
    }
}

$plans = [
    'basic' => ['name' => 'Basic', 'price' => 149, 'features' => ['Full Mock Tests', 'Rapid Fire & Subject Tests', 'Weekly Challenges', 'Community Read Access', 'Basic Analytics', '10 AI Tutor Requests/mo']],
    'pro' => ['name' => 'Pro', 'price' => 299, 'features' => ['Everything in Basic', 'Previous Year Papers', 'Revision Mode', 'Challenge a Friend', 'Full Analytics & PDF Reports', 'Unlimited AI Explanations', 'Leaderboard Access', 'Priority Support']],
];

include __DIR__ . '/includes/header.php';
?>

<?php if ($subscription): ?>
<div class="card" style="margin-bottom: 24px; border-color: var(--green);">
    <div style="display: flex; align-items: center; justify-content: space-between;">
        <div>
            <span class="badge badge-green" style="font-size: 13px;"><?= ucfirst($subscription['plan']) ?> Plan — Active</span>
            <p style="color: var(--text-secondary); font-size: 13px; margin-top: 8px;">Expires: <?= date('d M Y', strtotime($subscription['expires_at'])) ?></p>
        </div>
        <div style="text-align: right;">
            <p style="font-family: var(--font-mono); font-size: 12px; color: var(--text-muted);">
                <?= max(0, (int)(new DateTime())->diff(new DateTime($subscription['expires_at']))->days) ?> days remaining
            </p>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($orgDiscount): ?>
<div class="card" style="margin-bottom: 24px; border-color: var(--accent);">
    <div style="display: flex; align-items: center; gap: 12px;">
        <span class="badge badge-accent" style="font-size: 13px;"><?= $orgDiscount ?>% OFF</span>
        <p style="font-size: 13px; color: var(--text-secondary); margin: 0;">
            <strong><?= htmlspecialchars($discountOrg['name']) ?></strong> students get <?= $orgDiscount ?>% off Basic & Pro —
            applied automatically at checkout.
        </p>
    </div>
</div>
<?php endif; ?>

<!-- College coupon: possession of the code (given out by the college) is the proof. -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header"><h3>Have a college coupon?</h3></div>
    <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 12px;">Enter the code your college gave you to unlock its discount on Basic & Pro.</p>
    <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
        <input id="couponInput" type="text" class="form-control" maxlength="24" placeholder="e.g. GPGANDHINAGAR50" style="max-width: 260px; text-transform: uppercase; font-family: var(--font-mono); letter-spacing: 1px;">
        <button id="couponApplyBtn" class="btn btn-secondary btn-sm" onclick="applyCoupon()">Apply</button>
        <span id="couponMsg" style="font-size: 13px;"></span>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 24px;">
    <!-- Free -->
    <div class="card">
        <h3 style="font-size: 18px; margin-bottom: 4px;">Free</h3>
        <div style="font-family: var(--font-mono); font-size: 28px; font-weight: 700; margin-bottom: 16px;">₹0</div>
        <ul style="list-style: none; margin-bottom: 20px;">
            <li style="padding: 6px 0; font-size: 13px; color: var(--text-secondary);"><?= icon('check', 12) ?> Daily Challenge only</li>
            <li style="padding: 6px 0; font-size: 13px; color: var(--text-muted);"><?= icon('x', 12) ?> No mock tests</li>
            <li style="padding: 6px 0; font-size: 13px; color: var(--text-muted);"><?= icon('x', 12) ?> No community access</li>
        </ul>
        <button class="btn btn-secondary" disabled style="width: 100%; opacity: 0.5;">Current (Free)</button>
    </div>

    <?php
    // A user already on Basic upgrades to Pro for just the difference — their
    // Basic fare is credited (both plans expire the same 30 June).
    $isBasicActive = $subscription && ($subscription['plan'] ?? '') === 'basic';
    foreach ($plans as $key => $plan):
        $finalPrice = $orgDiscount ? (int) round($plan['price'] * (100 - $orgDiscount) / 100) : $plan['price'];
        $isUpgrade = ($key === 'pro' && $isBasicActive);
        if ($isUpgrade) $finalPrice = max(1, $finalPrice - $plans['basic']['price']);
    ?>
    <div class="card" style="<?= $key === 'pro' ? 'border-color: var(--accent);' : '' ?>">
        <?php if ($key === 'pro'): ?><span class="badge badge-accent" style="margin-bottom: 8px;">Most Popular</span><?php endif; ?>
        <h3 style="font-size: 18px; margin-bottom: 4px;"><?= $plan['name'] ?></h3>
        <div id="price-<?= $key ?>" style="font-family: var(--font-mono); font-size: 28px; font-weight: 700; margin-bottom: 4px;">
            <?php if ($orgDiscount || $isUpgrade): ?><span style="font-size: 16px; text-decoration: line-through; color: var(--text-muted); font-weight: 500;">₹<?= $plan['price'] ?></span> <?php endif; ?>₹<?= $finalPrice ?>
        </div>
        <?php if ($isUpgrade): ?>
            <p style="font-size: 12px; color: var(--green); font-weight: 600; margin-bottom: 2px;">Upgrade price — ₹<?= $plans['basic']['price'] ?> Basic credit applied</p>
        <?php endif; ?>
        <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 16px;">per year · valid till 30 June</p>
        <ul style="list-style: none; margin-bottom: 20px;">
            <?php foreach ($plan['features'] as $f): ?>
                <li style="padding: 6px 0; font-size: 13px; color: var(--text-secondary);"><?= icon('check', 12) ?> <?= $f ?></li>
            <?php endforeach; ?>
        </ul>
        <?php if ($subscription && $subscription['plan'] === $key): ?>
            <button class="btn btn-secondary btn-block" disabled>Current Plan</button>
        <?php else: ?>
            <button id="btn-<?= $key ?>" class="btn <?= $key === 'pro' ? 'btn-dark' : 'btn-outline' ?> btn-block" onclick="initPayment('<?= $key ?>')">
                <?= $isUpgrade ? 'Upgrade' : 'Subscribe' ?> — ₹<?= $finalPrice ?>/yr
            </button>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<!-- Referral Section -->
<div class="card">
    <div class="card-header"><h3>Refer & Earn</h3></div>
    <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 12px;">Share your code with friends. They get a discount on their first Pro subscription.</p>
    <div style="display: flex; gap: 12px; align-items: center;">
        <input type="text" class="form-control" value="<?= htmlspecialchars($user['referral_code']) ?>" readonly style="max-width: 200px; font-family: var(--font-mono); font-weight: 700; letter-spacing: 2px;">
        <button class="btn btn-secondary btn-sm" onclick="navigator.clipboard.writeText('<?= $user['referral_code'] ?>'); this.textContent='Copied!'">Copy</button>
    </div>
</div>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
// Base (undiscounted) prices, kept in sync with the PHP $plans array.
const PLANS = <?= json_encode(array_map(fn($p) => $p['price'], $plans)) ?>;
// Auto org discount (0 if none). The coupon, once applied, may beat it.
const ORG_PCT = <?= (int) $orgDiscount ?>;
// Basic->Pro upgrade: credit the Basic fare against Pro (both expire 30 June).
const UPGRADE_CREDIT = <?= $isBasicActive ? (int) $plans['basic']['price'] : 0 ?>;
let couponPct = 0, couponCode = '';

// Effective discount = the better of the org auto-discount and an applied coupon.
function effectivePct() { return Math.max(ORG_PCT, couponPct); }

function renderPrices() {
    const pct = effectivePct();
    for (const key in PLANS) {
        const base = PLANS[key];
        let final = pct ? Math.round(base * (100 - pct) / 100) : base;
        const isUpgrade = (key === 'pro' && UPGRADE_CREDIT > 0);
        if (isUpgrade) final = Math.max(1, final - UPGRADE_CREDIT); // apply credit after discount
        const priceEl = document.getElementById('price-' + key);
        const btnEl = document.getElementById('btn-' + key);
        if (priceEl) {
            priceEl.innerHTML = ((pct || isUpgrade)
                ? '<span style="font-size:16px;text-decoration:line-through;color:var(--text-muted);font-weight:500;">₹' + base + '</span> '
                : '') + '₹' + final;
        }
        if (btnEl) btnEl.textContent = (isUpgrade ? 'Upgrade' : 'Subscribe') + ' — ₹' + final + '/yr';
    }
}

function applyCoupon() {
    const code = (document.getElementById('couponInput').value || '').trim().toUpperCase();
    const msg = document.getElementById('couponMsg');
    if (!code) { couponPct = 0; couponCode = ''; msg.textContent = ''; renderPrices(); return; }
    fetch('api/validate_coupon.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': '<?= htmlspecialchars(csrfToken()) ?>'
        },
        body: JSON.stringify({ coupon: code })
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) {
            couponPct = d.percent; couponCode = code;
            msg.style.color = 'var(--green)';
            msg.textContent = d.percent + '% off — ' + d.college;
        } else {
            couponPct = 0; couponCode = '';
            msg.style.color = 'var(--red)';
            msg.textContent = d.error || 'Invalid code';
        }
        renderPrices();
    })
    .catch(() => { msg.style.color = 'var(--red)'; msg.textContent = 'Could not validate. Try again.'; });
}

document.addEventListener('DOMContentLoaded', renderPrices);

function initPayment(plan) {
    fetch('api/create_order.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': '<?= htmlspecialchars(csrfToken()) ?>'
        },
        // Send the coupon code; the server re-validates it (never trusts a price).
        body: JSON.stringify({ plan: plan, coupon: couponCode })
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) { showToast(data.error, 'error'); return; }

        const options = {
            key: '<?= RAZORPAY_KEY_ID ?>',
            amount: data.amount,
            currency: 'INR',
            name: '<?= APP_NAME ?>',
            description: plan.charAt(0).toUpperCase() + plan.slice(1) + ' Plan - 1 Month',
            order_id: data.order_id,
            handler: function(response) {
                fetch('api/verify_payment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': '<?= htmlspecialchars(csrfToken()) ?>'
                    },
                    body: JSON.stringify({
                        razorpay_payment_id: response.razorpay_payment_id,
                        razorpay_order_id: response.razorpay_order_id,
                        razorpay_signature: response.razorpay_signature,
                        plan: plan
                    })
                })
                .then(r => r.json())
                .then(res => {
                    if (res.ok) {
                        showToast('Payment successful! Plan activated.', 'success');
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        showToast('Payment verification failed. Contact support.', 'error');
                    }
                });
            },
            prefill: {
                name: '<?= htmlspecialchars($user['name']) ?>',
                email: '<?= htmlspecialchars($user['email']) ?>'
            },
            theme: { color: '#4361ee' }
        };

        const rzp = new Razorpay(options);
        rzp.open();
    })
    .catch(err => { showToast('Error creating order: ' + err.message, 'error'); });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
