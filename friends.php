<?php
require_once __DIR__ . '/config.php';
$user = requireAuth();
$db = getDB();
$pageTitle = 'Friends';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' && !empty($_POST['email'])) {
        $email = trim($_POST['email']);
        if (strcasecmp($email, $user['email'] ?? '') === 0) {
            $error = "You can't send a friend request to yourself.";
        } else {
            // no_cache: a friend who just signed up must be findable immediately.
            $target = supabaseRest('students?email=eq.' . urlencode($email) . '&id=neq.' . $user['id'] . '&select=id&limit=1', 'GET', null, ['no_cache' => true]);
            if (empty($target[0])) {
                $error = 'No user found with that email.';
            } else {
                $targetId = (int) $target[0]['id'];
                // Is there already a link either direction (any status)?
                $existing = supabaseRest('friends?or=(and(student_id.eq.' . $user['id'] . ',friend_id.eq.' . $targetId . '),and(student_id.eq.' . $targetId . ',friend_id.eq.' . $user['id'] . '))&select=status&limit=1', 'GET', null, ['no_cache' => true]);
                if (!empty($existing[0])) {
                    $st = $existing[0]['status'] ?? '';
                    $error = $st === 'accepted' ? "You're already friends with this user."
                           : ($st === 'pending' ? 'There is already a pending request between you two.'
                           : 'A previous request exists between you two.');
                } else {
                    $ins = supabaseRest('friends', 'POST', [
                        'student_id' => $user['id'],
                        'friend_id' => $targetId,
                        'status' => 'pending',
                    ]);
                    // Only claim success if the row was actually created.
                    if (!empty($ins)) {
                        $success = 'Friend request sent!';
                    } else {
                        $error = 'Could not send the request. Please try again.';
                    }
                }
            }
        }
    } elseif ($action === 'accept') {
        supabaseRest('friends?id=eq.' . (int)$_POST['request_id'] . '&friend_id=eq.' . $user['id'], 'PATCH', ['status' => 'accepted']);
    } elseif ($action === 'reject') {
        supabaseRest('friends?id=eq.' . (int)$_POST['request_id'] . '&friend_id=eq.' . $user['id'], 'PATCH', ['status' => 'rejected']);
    }
}

// Pending requests and friends - parallel fetch
$results = supabaseMulti([
    'friends?friend_id=eq.' . $user['id'] . '&status=eq.pending&select=id,student_id',
    'friends?or=(student_id.eq.' . $user['id'] . ',friend_id.eq.' . $user['id'] . ')&status=eq.accepted&select=student_id,friend_id',
    'friends?student_id=eq.' . $user['id'] . '&status=eq.pending&select=id,friend_id',
]);

$pendingRows = $results[0] ?? [];
$friendRows = $results[1] ?? [];
$sentRows = $results[2] ?? [];

// Outgoing requests this user has sent (so the sender gets feedback after sending).
$sentRequests = [];
$sentIds = array_column($sentRows, 'friend_id');
if ($sentIds) {
    $sStudents = supabaseRest('students?id=in.(' . implode(',', $sentIds) . ')&select=id,name,avatar_url,email');
    $sMap = [];
    if ($sStudents) foreach ($sStudents as $s) $sMap[$s['id']] = $s;
    foreach ($sentRows as $fr) {
        if (isset($sMap[$fr['friend_id']])) {
            $sentRequests[] = array_merge(['id' => $fr['id']], $sMap[$fr['friend_id']]);
        }
    }
}

// Get pending request student info
$pendingRequests = [];
$pendingStudentIds = array_column($pendingRows, 'student_id');
if ($pendingStudentIds) {
    $pStudents = supabaseRest('students?id=in.(' . implode(',', $pendingStudentIds) . ')&select=id,name,avatar_url,email');
    $pMap = [];
    if ($pStudents) foreach ($pStudents as $s) $pMap[$s['id']] = $s;
    foreach ($pendingRows as $fr) {
        if (isset($pMap[$fr['student_id']])) {
            $pendingRequests[] = array_merge(['id' => $fr['id']], $pMap[$fr['student_id']]);
        }
    }
}

// My friends
$friendIds = [];
foreach ($friendRows as $f) {
    $fid = ($f['student_id'] == $user['id']) ? $f['friend_id'] : $f['student_id'];
    $friendIds[] = $fid;
}
$myFriends = [];
if ($friendIds) {
    $myFriends = supabaseRest('students?id=in.(' . implode(',', $friendIds) . ')&select=id,name,avatar_url,xp,level,streak&order=xp.desc') ?? [];
}

include __DIR__ . '/includes/header.php';
?>

<?php if (!empty($success)): ?><div class="card" style="border-color: var(--green); margin-bottom: 12px; padding: 12px; font-size: 13px; color: var(--green);"><?= $success ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="card" style="border-color: var(--red); margin-bottom: 12px; padding: 12px; font-size: 13px; color: var(--red);"><?= $error ?></div><?php endif; ?>

<!-- Add Friend -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header"><h3>Add Friend</h3></div>
    <form method="POST" style="display: flex; gap: 12px;">
        <input type="hidden" name="action" value="add">
        <input type="email" name="email" class="form-control" placeholder="Friend's email address" required style="max-width: 300px;">
        <button type="submit" class="btn btn-primary btn-sm">Send Request</button>
    </form>
</div>

<!-- Pending Requests -->
<?php if (!empty($pendingRequests)): ?>
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header"><h3>Friend Requests (<?= count($pendingRequests) ?>)</h3></div>
    <?php foreach ($pendingRequests as $r): ?>
    <div style="display: flex; align-items: center; gap: 12px; padding: 8px 0; border-bottom: 1px solid var(--border);">
        <?php if ($r['avatar_url']): ?><img src="<?= htmlspecialchars($r['avatar_url']) ?>" style="width:32px;height:32px;border-radius:50%;"><?php endif; ?>
        <div style="flex:1;">
            <div style="font-weight: 500;"><?= htmlspecialchars($r['name']) ?></div>
            <div style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($r['email']) ?></div>
        </div>
        <form method="POST" style="display:inline;"><input type="hidden" name="action" value="accept"><input type="hidden" name="request_id" value="<?= $r['id'] ?>"><button class="btn btn-primary btn-sm">Accept</button></form>
        <form method="POST" style="display:inline;"><input type="hidden" name="action" value="reject"><input type="hidden" name="request_id" value="<?= $r['id'] ?>"><button class="btn btn-secondary btn-sm">Decline</button></form>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Sent Requests (outgoing, still pending) -->
<?php if (!empty($sentRequests)): ?>
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header"><h3>Sent Requests (<?= count($sentRequests) ?>)</h3></div>
    <?php foreach ($sentRequests as $r): ?>
    <div style="display: flex; align-items: center; gap: 12px; padding: 8px 0; border-bottom: 1px solid var(--border);">
        <?php if ($r['avatar_url']): ?><img src="<?= htmlspecialchars($r['avatar_url']) ?>" style="width:32px;height:32px;border-radius:50%;"><?php endif; ?>
        <div style="flex:1;">
            <div style="font-weight: 500;"><?= htmlspecialchars($r['name']) ?></div>
            <div style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($r['email']) ?></div>
        </div>
        <span class="badge" style="background: var(--bg-tertiary); color: var(--text-muted);">Pending</span>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Friends List -->
<div class="card">
    <div class="card-header"><h3>My Friends (<?= count($myFriends) ?>)</h3></div>
    <?php if (empty($myFriends)): ?>
        <p style="color: var(--text-muted); font-size: 13px;">No friends yet. Add some to compare scores!</p>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Friend</th><th>Level</th><th>XP</th><th>Streak</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($myFriends as $f): ?>
                <tr>
                    <td style="display: flex; align-items: center; gap: 8px;">
                        <?php if ($f['avatar_url']): ?><img src="<?= htmlspecialchars($f['avatar_url']) ?>" style="width:24px;height:24px;border-radius:50%;"><?php endif; ?>
                        <?= htmlspecialchars($f['name']) ?>
                    </td>
                    <td><span class="tag"><?= htmlspecialchars($f['level']) ?></span></td>
                    <td style="font-family: var(--font-mono); color: var(--accent);"><?= number_format($f['xp']) ?></td>
                    <td style="font-family: var(--font-mono);"><?= icon('fire', 14) ?> <?= $f['streak'] ?></td>
                    <td><a href="challenges.php?friend=<?= (int) $f['id'] ?>" class="btn btn-dark btn-sm"><?= icon('swords', 14) ?> Challenge</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
