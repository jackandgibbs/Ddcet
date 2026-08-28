<?php
require_once __DIR__ . '/config.php';
$user = requireAuth();
$pageTitle = 'Custom Test Generator';

// Get available subjects and chapters from pool
$poolData = supabaseRest('questions?test_id=is.null&select=subject,chapter,difficulty&limit=5000') ?? [];
$subjects = [];
$chapters = [];
foreach ($poolData as $q) {
    $s = $q['subject'] ?? '';
    $c = $q['chapter'] ?? '';
    if ($s) $subjects[$s] = ($subjects[$s] ?? 0) + 1;
    if ($s && $c) $chapters[$s][] = $c;
}
foreach ($chapters as $s => &$arr) $arr = array_unique($arr);
unset($arr);
ksort($subjects);

// Handle payment verification and test start
$testStarted = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate') {
    // --- CSRF ---
    if (!csrfValid($_POST['csrf'] ?? null)) {
        $error = 'Your session expired. Please reload the page and try again.';
        goto render_page;
    }

    // --- Access control (server-side, authoritative) ---
    // Custom tests are free for active subscribers. Otherwise the student must
    // have a captured-but-unconsumed ₹29 custom_test payment, which we consume
    // here. The old client-only `payment_verified` field is ignored entirely.
    $hasSub = getSubscription() !== null;
    $creditPaymentId = null;
    if (!$hasSub) {
        $credit = supabaseRest(
            'payments?student_id=eq.' . $user['id']
            . '&plan=eq.custom_test&status=eq.captured&consumed_at=is.null'
            . '&order=created_at.desc&select=id&limit=1'
        ) ?? [];
        if (empty($credit)) {
            $error = 'payment_required';
            goto render_page;
        }
        $creditPaymentId = $credit[0]['id'];
    }

    $selectedSubjects = $_POST['subjects'] ?? [];
    $selectedDifficulty = $_POST['difficulty'] ?? 'all';
    $questionCount = min(50, max(5, (int)($_POST['count'] ?? 20)));

    // Generate custom test from pool
    $filter = 'test_id=is.null&select=*&limit=1000';
    if ($selectedSubjects) {
        $filter .= '&subject=in.(' . implode(',', array_map('urlencode', $selectedSubjects)) . ')';
    }
    if ($selectedDifficulty !== 'all') {
        $filter .= '&difficulty=eq.' . urlencode($selectedDifficulty);
    }
    $poolQ = supabaseRest('questions?' . $filter) ?? [];
    shuffle($poolQ);
    $questions = array_slice($poolQ, 0, $questionCount);

    if (count($questions) < 5) {
        $error = 'Not enough questions found for your selection. Try broader filters.';
    } else {
        $attempt = supabaseRest('attempts', 'POST', [
            'student_id' => $user['id'],
            'test_id' => null,
            'mode' => 'custom',
            'total_marks' => count($questions),
            'started_at' => date('c'),
            'status' => 'in_progress',
        ]);
        if ($attempt && !empty($attempt[0]['id'])) {
            $aid = $attempt[0]['id'];
            $answerRows = [];
            foreach ($questions as $i => $q) {
                $answerRows[] = ['attempt_id' => $aid, 'question_id' => $q['id'], 'position' => $i];
            }
            foreach (array_chunk($answerRows, 50) as $chunk) {
                supabaseRest('attempt_answers', 'POST', $chunk);
            }
            // Consume the one-off custom_test credit now that the test exists.
            if ($creditPaymentId) {
                supabaseRest('payments?id=eq.' . $creditPaymentId, 'PATCH', ['consumed_at' => date('c')]);
            }
            header('Location: ' . BASE_PATH . 'exam.php?attempt_id=' . $aid);
            exit;
        } else {
            $error = 'Failed to create test attempt. Please try again.';
        }
    }
}
render_page:

include __DIR__ . '/includes/header.php';
?>

<div class="card" style="margin-bottom: 20px; border-color: var(--accent);">
    <div style="display: flex; align-items: center; justify-content: space-between;">
        <div style="flex: 1; min-width: 0;">
            <h3 style="font-size: 16px; margin-bottom: 4px;">Custom Test Generator</h3>
            <p style="font-size: 13px; color: var(--text-secondary);">Pick your subjects, difficulty, and question count. Get a personalized test instantly.</p>
        </div>
        <div style="text-align: right; flex-shrink: 0; margin-left: 12px;">
            <?php if (getSubscription()): ?>
                <span class="badge badge-green">Free with Pro</span>
            <?php else: ?>
                <span style="font-family: var(--font-mono); font-size: 20px; font-weight: 700; color: var(--accent); white-space: nowrap;">₹29</span>
                <div style="font-size: 11px; color: var(--text-muted); white-space: nowrap;">per test</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($error === 'payment_required'): ?>
    <div class="card" style="border-color: var(--orange); margin-bottom: 16px; padding: 12px; font-size: 13px; color: var(--orange);">Payment required. Click <strong>Generate My Test</strong> to pay ₹29 for this custom test, or upgrade to Pro for unlimited custom tests.</div>
<?php elseif ($error): ?>
    <div class="card" style="border-color: var(--red); margin-bottom: 16px; padding: 12px; font-size: 13px; color: var(--red);"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card">
    <form method="POST" id="customTestForm" data-subscribed="<?= getSubscription() ? '1' : '0' ?>">
        <input type="hidden" name="action" value="generate">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">

        <!-- Subject Selection -->
        <div class="form-group">
            <label style="font-weight: 600; margin-bottom: 10px; display: block;">Select Subjects</label>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <?php foreach ($subjects as $s => $count): ?>
                <label style="display: flex; align-items: center; gap: 8px; background: var(--bg-primary); padding: 10px 16px; border-radius: 8px; cursor: pointer; border: 2px solid var(--border); transition: all 0.15s;">
                    <input type="checkbox" name="subjects[]" value="<?= htmlspecialchars($s) ?>" checked style="accent-color: var(--accent);">
                    <span style="font-size: 13px; font-weight: 500;"><?= htmlspecialchars($s) ?></span>
                    <span style="font-size: 11px; color: var(--text-muted);">(<?= $count ?>)</span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Difficulty -->
        <div class="form-group" style="margin-top: 20px;">
            <label style="font-weight: 600; margin-bottom: 10px; display: block;">Difficulty</label>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <label style="display: flex; align-items: center; gap: 6px; background: var(--bg-primary); padding: 10px 16px; border-radius: 8px; cursor: pointer; border: 2px solid var(--border); white-space: nowrap; flex: 1; min-width: 80px; justify-content: center;">
                    <input type="radio" name="difficulty" value="all" checked style="accent-color: var(--accent);"> <span style="font-size: 13px;">All</span>
                </label>
                <label style="display: flex; align-items: center; gap: 6px; background: var(--bg-primary); padding: 10px 16px; border-radius: 8px; cursor: pointer; border: 2px solid var(--border); white-space: nowrap; flex: 1; min-width: 80px; justify-content: center;">
                    <input type="radio" name="difficulty" value="easy" style="accent-color: var(--green);"> <span style="font-size: 13px;">Easy</span>
                </label>
                <label style="display: flex; align-items: center; gap: 6px; background: var(--bg-primary); padding: 10px 16px; border-radius: 8px; cursor: pointer; border: 2px solid var(--border); white-space: nowrap; flex: 1; min-width: 80px; justify-content: center;">
                    <input type="radio" name="difficulty" value="medium" style="accent-color: var(--orange);"> <span style="font-size: 13px;">Medium</span>
                </label>
                <label style="display: flex; align-items: center; gap: 6px; background: var(--bg-primary); padding: 10px 16px; border-radius: 8px; cursor: pointer; border: 2px solid var(--border); white-space: nowrap; flex: 1; min-width: 80px; justify-content: center;">
                    <input type="radio" name="difficulty" value="hard" style="accent-color: var(--red);"> <span style="font-size: 13px;">Hard</span>
                </label>
            </div>
        </div>

        <!-- Question Count -->
        <div class="form-group" style="margin-top: 20px;">
            <label style="font-weight: 600; margin-bottom: 10px; display: block;">Number of Questions</label>
            <div style="display: flex; align-items: center; gap: 12px;">
                <input type="range" name="count" min="5" max="50" value="20" id="countSlider" style="flex: 1; accent-color: var(--accent);">
                <span id="countDisplay" style="font-family: var(--font-mono); font-size: 18px; font-weight: 700; color: var(--accent); min-width: 40px; text-align: center;">20</span>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 11px; color: var(--text-muted); margin-top: 4px;">
                <span>5 questions</span>
                <span>50 questions</span>
            </div>
        </div>

        <div style="margin-top: 28px;">
            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 14px; font-size: 15px;">Generate My Test →</button>
        </div>
    </form>
</div>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
const form = document.getElementById('customTestForm');
const isSubscribed = form.dataset.subscribed === '1';
let paymentDone = false;

document.getElementById('countSlider').addEventListener('input', function() {
    document.getElementById('countDisplay').textContent = this.value;
});

// Subscribers submit directly. Everyone else must pay (and have the payment
// VERIFIED server-side) before the form is allowed to submit. The server
// re-checks for a captured, unconsumed credit regardless of this JS, so a
// bypass of the client just lands on the payment_required page.
form.addEventListener('submit', function(e) {
    if (isSubscribed || paymentDone) return; // allow submit
    e.preventDefault();
    payAndGenerate();
});

function payAndGenerate() {
    fetch('<?= BASE_PATH ?>api/create_order.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ plan: 'custom_test' })
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) { showToast(data.error, 'error'); return; }
        const options = {
            key: '<?= RAZORPAY_KEY_ID ?>',
            amount: data.amount,
            currency: 'INR',
            name: '<?= APP_NAME ?>',
            description: 'Custom Test Generator',
            order_id: data.order_id,
            handler: function(response) {
                // Verify the payment signature SERVER-SIDE before submitting.
                fetch('<?= BASE_PATH ?>api/verify_payment.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        razorpay_payment_id: response.razorpay_payment_id,
                        razorpay_order_id: response.razorpay_order_id,
                        razorpay_signature: response.razorpay_signature
                    })
                })
                .then(r => r.json())
                .then(v => {
                    if (v.ok) { paymentDone = true; form.submit(); }
                    else { showToast(v.error || 'Payment verification failed', 'error'); }
                })
                .catch(err => showToast('Verification error: ' + err.message, 'error'));
            },
            prefill: { name: '<?= htmlspecialchars($user['name']) ?>', email: '<?= htmlspecialchars($user['email']) ?>' },
            theme: { color: '#4361ee' }
        };
        new Razorpay(options).open();
    })
    .catch(err => showToast('Error: ' + err.message, 'error'));
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
