<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/icons.php';
$user = requireAuth();
$pageTitle = 'Challenges';
$me = (int) $user['id'];
$isPro = isSubscribed('pro');

// --- Lobby view: ?lobby=ID renders the synchronized waiting room. ----------
$lobbyId = (int) ($_GET['lobby'] ?? 0);
if ($lobbyId) {
    $rows = supabaseRest('challenges?id=eq.' . $lobbyId . '&select=*&limit=1');
    $ch = $rows[0] ?? null;
    if (!$ch || !challengeRole($ch, $me)) { header('Location: ' . BASE_PATH . 'challenges.php'); exit; }
    $status = challengeResolveTimeouts($ch);
    if ($status === 'live') {
        // Already running — go straight to my attempt.
        $mine = challengeRole($ch, $me) === 'challenger' ? $ch['challenger_attempt_id'] : $ch['opponent_attempt_id'];
        if ($mine) { header('Location: ' . BASE_PATH . 'exam.php?attempt_id=' . (int) $mine); exit; }
    }
    if (!in_array($status, ['accepted', 'live'], true)) {
        header('Location: ' . BASE_PATH . 'challenges.php?msg=' . urlencode('That challenge is no longer in the lobby.')); exit;
    }
    include __DIR__ . '/includes/header.php';
    ?>
    <div class="card" style="max-width: 520px; margin: 0 auto; text-align: center;">
        <div class="card-header" style="justify-content:center;"><h3>⚔️ Duel Lobby</h3></div>
        <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 16px;"><?= htmlspecialchars(challengeModes()[$ch['mode']] ?? $ch['mode']) ?></p>
        <div id="lobbyStatus" style="font-size: 15px; font-weight: 600; margin-bottom: 8px;">Getting ready…</div>
        <div id="lobbyTimer" style="font-family: var(--font-mono); font-size: 28px; font-weight: 700; color: var(--accent); margin-bottom: 16px;">2:00</div>
        <div style="display:flex; gap:16px; justify-content:center; margin-bottom: 20px;">
            <div style="flex:1; max-width:160px;">
                <div style="font-size:12px; color:var(--text-muted);">You</div>
                <div id="meReady" style="font-weight:600; color:var(--text-muted);">Not ready</div>
            </div>
            <div style="flex:1; max-width:160px;">
                <div style="font-size:12px; color:var(--text-muted);" id="oppName">Opponent</div>
                <div id="oppReady" style="font-weight:600; color:var(--text-muted);">Not ready</div>
            </div>
        </div>
        <button id="readyBtn" class="btn btn-primary btn-block" onclick="markReady()">I'm Ready</button>
        <p id="lobbyHint" style="font-size: 12px; color: var(--text-muted); margin-top: 12px;">The duel starts automatically once you're both ready.</p>
    </div>
    <script>
    const CID = <?= $lobbyId ?>;
    const CSRF = '<?= htmlspecialchars(csrfToken()) ?>';
    const BASE = '<?= BASE_PATH ?>';
    let polling = null, ready = false, lobbyDeadline = null, clockSkew = 0;

    function fmt(s){ s=Math.max(0,Math.floor(s)); return Math.floor(s/60)+':'+String(s%60).padStart(2,'0'); }
    function tickTimer(){
        if(!lobbyDeadline) return;
        const left = lobbyDeadline - (Date.now()/1000 + clockSkew);
        document.getElementById('lobbyTimer').textContent = fmt(left);
    }
    setInterval(tickTimer, 1000);

    function markReady(){
        document.getElementById('readyBtn').disabled = true;
        document.getElementById('readyBtn').textContent = 'Waiting…';
        fetch(BASE+'api/challenge_ready.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},body:JSON.stringify({challenge_id:CID})})
            .then(r=>r.json()).then(d=>{
                if(d.error==='needs_pro'){ window.location.href=BASE+'subscription.php?need=pro'; return; }
                if(d.status==='live' && d.attempt_id){ go(d.attempt_id); }
                ready = true;
            });
    }
    function go(attemptId){ if(polling) clearInterval(polling); window.location.href=BASE+'exam.php?attempt_id='+attemptId; }

    function poll(){
        fetch(BASE+'api/challenge_state.php?challenge_id='+CID).then(r=>r.json()).then(d=>{
            if(!d.ok) return;
            clockSkew = d.now - (Date.now()/1000);
            if(d.lobby_deadline) lobbyDeadline = d.lobby_deadline;
            tickTimer();
            document.getElementById('oppName').textContent = d.opponent_name || 'Opponent';
            document.getElementById('meReady').textContent = d.me_ready ? 'Ready ✓' : 'Not ready';
            document.getElementById('meReady').style.color = d.me_ready ? 'var(--green)' : 'var(--text-muted)';
            document.getElementById('oppReady').textContent = d.opponent_ready ? 'Ready ✓' : 'Waiting…';
            document.getElementById('oppReady').style.color = d.opponent_ready ? 'var(--green)' : 'var(--text-muted)';
            if(d.status==='live' && d.my_attempt_id){ go(d.my_attempt_id); return; }
            if(d.status==='cancelled' || d.status==='expired'){
                document.getElementById('lobbyStatus').textContent = 'Challenge cancelled (opponent didn\'t join in time).';
                document.getElementById('readyBtn').style.display='none';
                if(polling) clearInterval(polling);
            }
        });
    }
    // Lobby countdown (2 min from accept). Seed from server on first poll.
    fetch(BASE+'api/challenge_state.php?challenge_id='+CID).then(r=>r.json()).then(d=>{
        document.getElementById('lobbyStatus').textContent = 'Press “I’m Ready” to enter the duel.';
    });
    poll();
    polling = setInterval(poll, 3000);
    </script>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

// --- List view -------------------------------------------------------------
// Incoming pending (invites to me), outgoing pending (sent by me), live, history.
$all = supabaseRest('challenges?or=(challenger_id.eq.' . $me . ',opponent_id.eq.' . $me . ')&order=created_at.desc&select=*&limit=100') ?? [];

// Resolve timeouts lazily as we read, and bucket.
$invites = []; $sent = []; $liveOnes = []; $history = [];
$peopleIds = [];
foreach ($all as $ch) {
    $st = challengeResolveTimeouts($ch);
    $ch['status'] = $st;
    $peopleIds[] = (int) $ch['challenger_id'];
    $peopleIds[] = (int) $ch['opponent_id'];
    if ($st === 'pending' && (int) $ch['opponent_id'] === $me) $invites[] = $ch;
    elseif ($st === 'pending' && (int) $ch['challenger_id'] === $me) $sent[] = $ch;
    elseif (in_array($st, ['accepted', 'live'], true)) $liveOnes[] = $ch;
    elseif ($st === 'completed') $history[] = $ch;
}
$nameMap = [];
if ($peopleIds) {
    $ppl = supabaseRest('students?id=in.(' . implode(',', array_unique($peopleIds)) . ')&select=id,name,avatar_url') ?? [];
    foreach ($ppl as $p) $nameMap[(int) $p['id']] = $p;
}
$oppName = function (array $ch) use ($me, $nameMap) {
    $oid = (int) $ch['challenger_id'] === $me ? (int) $ch['opponent_id'] : (int) $ch['challenger_id'];
    return $nameMap[$oid]['name'] ?? 'Friend';
};

// Friends list for the "New Challenge" picker (accepted only).
$friendRows = supabaseRest('friends?or=(student_id.eq.' . $me . ',friend_id.eq.' . $me . ')&status=eq.accepted&select=student_id,friend_id') ?? [];
$friendIds = [];
foreach ($friendRows as $f) $friendIds[] = ((int) $f['student_id'] === $me) ? (int) $f['friend_id'] : (int) $f['student_id'];
$friends = $friendIds ? (supabaseRest('students?id=in.(' . implode(',', $friendIds) . ')&select=id,name&order=name') ?? []) : [];

include __DIR__ . '/includes/header.php';
?>

<?php if (!empty($_GET['msg'])): ?>
<div class="card" style="margin-bottom: 16px; padding: 12px 16px; font-size: 13px; color: var(--text-secondary);"><?= htmlspecialchars($_GET['msg']) ?></div>
<?php endif; ?>

<?php if (!$isPro): ?>
<div class="card" style="margin-bottom: 20px; border-color: var(--accent);">
    <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
        <span class="badge badge-accent">PRO</span>
        <p style="font-size:13px; color:var(--text-secondary); margin:0; flex:1;">Challenge a Friend is a Pro feature. You and your friend both need Pro to duel.</p>
        <a href="subscription.php?need=pro" class="btn btn-dark btn-sm">Upgrade to Pro</a>
    </div>
</div>
<?php endif; ?>

<!-- New Challenge -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header"><h3>New Challenge</h3></div>
    <?php if (empty($friends)): ?>
        <p style="font-size:13px; color:var(--text-muted);">Add friends first on the <a href="friends.php">Friends</a> page, then challenge them here.</p>
    <?php else: ?>
    <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:end;">
        <div class="form-group" style="margin-bottom:0;">
            <label>Friend</label>
            <?php $preFriend = (int) ($_GET['friend'] ?? 0); ?>
            <select id="ncFriend" class="form-control" style="min-width:180px;">
                <?php foreach ($friends as $f): ?><option value="<?= (int) $f['id'] ?>" <?= $preFriend === (int) $f['id'] ? 'selected' : '' ?>><?= htmlspecialchars($f['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin-bottom:0;">
            <label>Mode</label>
            <select id="ncMode" class="form-control" onchange="document.getElementById('ncSubjectWrap').style.display=this.value==='subject_wise'?'block':'none'">
                <?php foreach (challengeModes() as $k => $label): ?><option value="<?= $k ?>"><?= htmlspecialchars($label) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" id="ncSubjectWrap" style="margin-bottom:0; display:none;">
            <label>Subject</label>
            <select id="ncSubject" class="form-control">
                <?php foreach (['Physics','Chemistry','Maths','English','Computers','Environment'] as $s): ?><option value="<?= $s ?>"><?= $s ?></option><?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary btn-sm" onclick="sendChallenge()" <?= $isPro ? '' : 'disabled title="Pro required"' ?>>Send Challenge</button>
    </div>
    <?php endif; ?>
</div>

<!-- Invites -->
<?php if ($invites): ?>
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header"><h3>Invites (<?= count($invites) ?>)</h3></div>
    <?php foreach ($invites as $ch): ?>
    <div style="display:flex; align-items:center; gap:12px; padding:10px 0; border-bottom:1px solid var(--border);">
        <div style="flex:1;">
            <strong><?= htmlspecialchars($oppName($ch)) ?></strong> challenged you
            <div style="font-size:12px; color:var(--text-muted);"><?= htmlspecialchars(challengeModes()[$ch['mode']] ?? $ch['mode']) ?></div>
        </div>
        <button class="btn btn-primary btn-sm" onclick="respond(<?= (int) $ch['id'] ?>,'accept')">Accept</button>
        <button class="btn btn-secondary btn-sm" onclick="respond(<?= (int) $ch['id'] ?>,'decline')">Decline</button>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- In Lobby / Live -->
<?php if ($liveOnes): ?>
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header"><h3>Active</h3></div>
    <?php foreach ($liveOnes as $ch): ?>
    <div style="display:flex; align-items:center; gap:12px; padding:10px 0; border-bottom:1px solid var(--border);">
        <div style="flex:1;">
            vs <strong><?= htmlspecialchars($oppName($ch)) ?></strong>
            <span class="badge <?= $ch['status']==='live' ? 'badge-green' : 'badge-accent' ?>"><?= $ch['status']==='live' ? 'Live' : 'Lobby' ?></span>
            <div style="font-size:12px; color:var(--text-muted);"><?= htmlspecialchars(challengeModes()[$ch['mode']] ?? $ch['mode']) ?></div>
        </div>
        <a class="btn btn-primary btn-sm" href="challenges.php?lobby=<?= (int) $ch['id'] ?>"><?= $ch['status']==='live' ? 'Resume' : 'Enter Lobby' ?></a>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Sent -->
<?php if ($sent): ?>
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header"><h3>Sent</h3></div>
    <?php foreach ($sent as $ch): ?>
    <div style="display:flex; align-items:center; gap:12px; padding:10px 0; border-bottom:1px solid var(--border);">
        <div style="flex:1;">
            Waiting for <strong><?= htmlspecialchars($oppName($ch)) ?></strong> to accept
            <div style="font-size:12px; color:var(--text-muted);"><?= htmlspecialchars(challengeModes()[$ch['mode']] ?? $ch['mode']) ?></div>
        </div>
        <span class="badge badge-blue">Pending</span>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- History -->
<div class="card">
    <div class="card-header"><h3>History</h3></div>
    <?php if (empty($history)): ?>
        <p style="font-size:13px; color:var(--text-muted);">No completed duels yet. Challenge a friend above!</p>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Opponent</th><th>Mode</th><th>Result</th><th>Score</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($history as $ch):
                $iAmChallenger = (int) $ch['challenger_id'] === $me;
                $myScore  = $iAmChallenger ? $ch['challenger_score'] : $ch['opponent_score'];
                $oppScore = $iAmChallenger ? $ch['opponent_score'] : $ch['challenger_score'];
                $myAttempt = $iAmChallenger ? $ch['challenger_attempt_id'] : $ch['opponent_attempt_id'];
                $win = $ch['winner_id'] === null ? 'tie' : ((int) $ch['winner_id'] === $me ? 'win' : 'loss');
            ?>
                <tr>
                    <td><?= htmlspecialchars($oppName($ch)) ?></td>
                    <td style="color:var(--text-muted);"><?= htmlspecialchars(challengeModes()[$ch['mode']] ?? $ch['mode']) ?></td>
                    <td>
                        <?php if ($win==='win'): ?><span class="badge badge-green">Won 🏆</span>
                        <?php elseif ($win==='loss'): ?><span class="badge badge-red">Lost</span>
                        <?php else: ?><span class="badge badge-blue">Tie</span><?php endif; ?>
                    </td>
                    <td style="font-family:var(--font-mono);"><?= $myScore !== null ? (float) $myScore : '—' ?> vs <?= $oppScore !== null ? (float) $oppScore : '—' ?></td>
                    <td><?php if ($myAttempt): ?><a href="result.php?attempt_id=<?= (int) $myAttempt ?>" class="btn btn-secondary btn-sm">View</a><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
const BASE = '<?= BASE_PATH ?>';
const CSRF = '<?= htmlspecialchars(csrfToken()) ?>';
function sendChallenge(){
    const body = {
        opponent_id: parseInt(document.getElementById('ncFriend').value,10),
        mode: document.getElementById('ncMode').value,
        subject: document.getElementById('ncMode').value==='subject_wise' ? document.getElementById('ncSubject').value : null
    };
    fetch(BASE+'api/challenge_create.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},body:JSON.stringify(body)})
        .then(r=>r.json()).then(d=>{
            if(d.error==='needs_pro'){ window.location.href=BASE+'subscription.php?need=pro'; return; }
            if(d.error){ showToast(d.error,'error'); return; }
            showToast('Challenge sent!','success'); setTimeout(()=>location.reload(),900);
        });
}
function respond(id,decision){
    fetch(BASE+'api/challenge_respond.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},body:JSON.stringify({challenge_id:id,decision:decision})})
        .then(r=>r.json()).then(d=>{
            if(d.error==='needs_pro'){ window.location.href=BASE+'subscription.php?need=pro'; return; }
            if(d.error){ showToast(d.error,'error'); return; }
            if(decision==='accept' && d.status==='accepted'){ window.location.href=BASE+'challenges.php?lobby='+id; return; }
            location.reload();
        });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
