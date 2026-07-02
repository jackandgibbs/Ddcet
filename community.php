<?php
require_once __DIR__ . '/config.php';
$user = requireAuth();
$pageTitle = 'Community';
$me = (int) $user['id'];

// Student-selectable post categories (whitelist — admins have their own labels).
$CATEGORIES = [
    'discussion' => 'Discussion',
    'study_tip'  => 'Study Tip',
    'doubt'      => 'Quick Doubt',
    'motivation' => 'Motivation',
    'resource'   => 'Resource',
];

// --- POST actions (all CSRF-protected; create actions are rate-limited) -------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'post') {
        $body = trim($_POST['body'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $cat = $_POST['category'] ?? 'discussion';
        if (!isset($CATEGORIES[$cat])) $cat = 'discussion';
        if ($body !== '' && rateLimit('community_post', 15, 3600)) {
            supabaseRest('community_posts', 'POST', [
                'student_id'  => $me,
                'author_type' => 'student',
                'title'       => $title !== '' ? mb_substr($title, 0, 200) : null,
                'body'        => mb_substr($body, 0, 5000),
                'category'    => $cat,
                'is_visible'  => true,
            ]);
        }
    } elseif ($action === 'comment') {
        $body = trim($_POST['body'] ?? '');
        $postId = (int) ($_POST['post_id'] ?? 0);
        $parentId = (int) ($_POST['parent_id'] ?? 0); // 0 = top-level comment
        // A reply's parent must be a comment on THIS post; otherwise drop the link
        // (avoids orphaned/cross-post threads from a forged parent_id).
        if ($parentId) {
            $parent = supabaseRest('community_comments?id=eq.' . $parentId . '&post_id=eq.' . $postId . '&select=id&limit=1');
            if (empty($parent)) $parentId = 0;
        }
        if ($body !== '' && $postId && rateLimit('community_comment', 40, 3600)) {
            supabaseRest('community_comments', 'POST', [
                'post_id'    => $postId,
                'student_id' => $me,
                'parent_id'  => $parentId ?: null,
                'body'       => mb_substr($body, 0, 2000),
            ]);
        }
    } elseif ($action === 'delete_post') {
        // Own posts only (the student_id filter is the authorization).
        supabaseRest('community_posts?id=eq.' . (int) $_POST['post_id'] . '&student_id=eq.' . $me, 'DELETE');
    } elseif ($action === 'delete_comment') {
        supabaseRest('community_comments?id=eq.' . (int) $_POST['comment_id'] . '&student_id=eq.' . $me, 'DELETE');
    }
    $back = ($_POST['tab'] ?? '') === 'mine' ? '?tab=mine' : '';
    header('Location: ' . BASE_PATH . 'community.php' . $back);
    exit;
}

// --- Feed --------------------------------------------------------------------
$tab = ($_GET['tab'] ?? 'all') === 'mine' ? 'mine' : 'all';
$filter = 'community_posts?is_visible=eq.true&order=is_pinned.desc,created_at.desc&limit=50&select=*';
if ($tab === 'mine') $filter .= '&student_id=eq.' . $me;
$posts = supabaseRest($filter) ?? [];

$postIds = array_column($posts, 'id');

// Comments for these posts (one batched call). Build:
//  - $commentsByPost: flat list per post (used only for the total reply count)
//  - $rootsByPost: top-level comments (parent_id NULL) per post, in order
//  - $kidsOf: child comments keyed by their parent comment id (Reddit nesting)
$commentsByPost = [];
$rootsByPost = [];
$kidsOf = [];
$comments = [];
if ($postIds) {
    $comments = supabaseRest('community_comments?post_id=in.(' . implode(',', $postIds) . ')&order=created_at.asc&select=*') ?? [];
    foreach ($comments as $c) {
        $commentsByPost[$c['post_id']][] = $c;
        if (!empty($c['parent_id'])) $kidsOf[(int) $c['parent_id']][] = $c;
        else $rootsByPost[$c['post_id']][] = $c;
    }
}

// Author identities for posts AND comments, batched into one lookup.
$peopleIds = array_filter(array_merge(
    array_column($posts, 'student_id'),
    array_column($comments, 'student_id')
));
$peopleIds = array_unique(array_map('intval', $peopleIds));
$people = [];
if ($peopleIds) {
    $rows = supabaseRest('students?id=in.(' . implode(',', $peopleIds) . ')&select=id,name,avatar_url') ?? [];
    foreach ($rows as $s) $people[(int) $s['id']] = $s;
}

// Reaction counts per post + my own reaction per post (one call each).
$reactCounts = [];
$myReactions = [];
if ($postIds) {
    $allR = supabaseRest('post_reactions?post_id=in.(' . implode(',', $postIds) . ')&select=post_id,student_id,reaction') ?? [];
    foreach ($allR as $r) {
        $reactCounts[$r['post_id']][$r['reaction']] = ($reactCounts[$r['post_id']][$r['reaction']] ?? 0) + 1;
        if ((int) $r['student_id'] === $me) $myReactions[$r['post_id']] = $r['reaction'];
    }
}

// Avatar/name helper for a person id (falls back to the DDCET team for admin posts).
function avatarChip(?array $person, string $fallbackName = 'DDCET Team'): string {
    $name = $person['name'] ?? $fallbackName;
    $av = $person['avatar_url'] ?? '';
    if ($av) {
        $img = '<img src="' . htmlspecialchars($av) . '" style="width:36px;height:36px;border-radius:50%;">';
    } else {
        $img = '<div style="width:36px;height:36px;border-radius:50%;background:var(--accent-light);display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--accent);font-size:14px;">'
             . strtoupper(substr($name, 0, 1)) . '</div>';
    }
    return $img;
}

/**
 * Render a comment and its nested replies (Reddit-style). Each level is indented
 * with a left thread-line connecting replies to their parent. Returns HTML.
 *   $c       — the comment row
 *   $kidsOf  — map of parent_comment_id => [child rows]
 *   $people  — id => student row (name/avatar)
 *   $me      — current user id (to show Delete on own comments)
 *   $tab     — current feed tab, preserved through form posts
 *   $depth   — recursion depth (caps indentation so deep threads stay readable)
 */
function renderCommentTree(array $c, array $kidsOf, array $people, int $me, string $tab, int $depth = 0): string {
    $cid = (int) $c['id'];
    $author = $people[(int) $c['student_id']] ?? null;
    $name = htmlspecialchars($author['name'] ?? 'User');
    $mine = (int) $c['student_id'] === $me;
    $csrf = htmlspecialchars(csrfToken());
    $when = date('d M, H:i', strtotime($c['created_at']));
    $body = nl2br(htmlspecialchars($c['body']));

    $h  = '<div style="margin-top:10px;">';
    $h .= '<div style="display:flex; gap:8px; align-items:start;">';
    $h .= '<div style="flex-shrink:0;">' . avatarChip($author) . '</div>';
    $h .= '<div style="flex:1; min-width:0;">';
    $h .= '<div style="font-size:12px;"><strong>' . $name . '</strong>'
        . '<span style="color:var(--text-muted); margin-left:6px;">' . $when . '</span></div>';
    $h .= '<div style="font-size:13px; color:var(--text-secondary);">' . $body . '</div>';
    // Action row: Reply (+ Delete on own).
    $h .= '<div style="display:flex; gap:10px; align-items:center; margin-top:2px;">';
    $h .= '<button type="button" class="reply-toggle" data-cid="' . $cid . '" style="background:none;border:none;padding:0;color:var(--accent);font-size:11px;font-weight:600;cursor:pointer;">Reply</button>';
    if ($mine) {
        $h .= '<form method="POST" onsubmit="return confirm(\'Delete this reply?\')" style="display:inline;">'
            . '<input type="hidden" name="csrf" value="' . $csrf . '">'
            . '<input type="hidden" name="action" value="delete_comment">'
            . '<input type="hidden" name="comment_id" value="' . $cid . '">'
            . '<input type="hidden" name="tab" value="' . htmlspecialchars($tab) . '">'
            . '<button style="background:none;border:none;padding:0;color:var(--red);font-size:11px;cursor:pointer;">Delete</button></form>';
    }
    $h .= '</div>';
    // Hidden reply box, targeting THIS comment as parent.
    $h .= '<form method="POST" class="reply-form" id="reply-' . $cid . '" style="display:none; gap:8px; margin-top:8px;">'
        . '<input type="hidden" name="csrf" value="' . $csrf . '">'
        . '<input type="hidden" name="action" value="comment">'
        . '<input type="hidden" name="post_id" value="' . (int) $c['post_id'] . '">'
        . '<input type="hidden" name="parent_id" value="' . $cid . '">'
        . '<input type="hidden" name="tab" value="' . htmlspecialchars($tab) . '">'
        . '<input type="text" name="body" required maxlength="2000" class="form-control" placeholder="Reply to ' . $name . '..." style="font-size:13px;">'
        . '<button class="btn btn-primary btn-sm">Reply</button></form>';
    $h .= '</div></div>'; // close text col + row

    // Children: indented with a left thread-line. Cap indent depth for readability.
    $kids = $kidsOf[$cid] ?? [];
    if ($kids) {
        $ml = $depth < 6 ? 16 : 0;
        $h .= '<div style="border-left:2px solid var(--border); margin-left:' . $ml . 'px; padding-left:10px;">';
        foreach ($kids as $kid) $h .= renderCommentTree($kid, $kidsOf, $people, $me, $tab, $depth + 1);
        $h .= '</div>';
    }
    $h .= '</div>';
    return $h;
}

include __DIR__ . '/includes/header.php';
?>

<!-- Composer -->
<div class="card" style="margin-bottom: 16px;">
    <form method="POST">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
        <input type="hidden" name="action" value="post">
        <input type="hidden" name="tab" value="<?= $tab ?>">
        <div style="display: flex; gap: 12px; align-items: start;">
            <?= avatarChip(['name' => $user['name'] ?? 'U', 'avatar_url' => $user['avatar_url'] ?? '']) ?>
            <div style="flex:1;">
                <input type="text" name="title" class="form-control" maxlength="200" placeholder="Title (optional)" style="margin-bottom: 8px;">
                <textarea name="body" class="form-control" rows="2" required maxlength="5000" placeholder="Share a tip, ask a question, or motivate others..." style="resize: vertical; margin-bottom: 10px;"></textarea>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <select name="category" class="form-control" style="width: auto; padding: 6px 12px; font-size: 12px;">
                        <?php foreach ($CATEGORIES as $val => $label): ?>
                            <option value="<?= $val ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm" style="margin-left: auto;">Post</button>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Tabs -->
<div style="display:flex; gap:8px; margin-bottom: 16px;">
    <a href="community.php" class="btn btn-sm <?= $tab === 'all' ? 'btn-primary' : 'btn-secondary' ?>">All Posts</a>
    <a href="community.php?tab=mine" class="btn btn-sm <?= $tab === 'mine' ? 'btn-primary' : 'btn-secondary' ?>">My Posts</a>
</div>

<?php if (empty($posts)): ?>
    <div class="card" style="text-align: center; padding: 60px 20px; color: var(--text-muted);">
        <?= icon('chat', 40) ?>
        <h3 style="margin: 12px 0 4px; color: var(--text-secondary);"><?= $tab === 'mine' ? "You haven't posted yet" : 'No posts yet' ?></h3>
        <p style="font-size: 13px;"><?= $tab === 'mine' ? 'Share your first idea above.' : 'Be the first to share something with the community!' ?></p>
    </div>
<?php else: ?>
<?php foreach ($posts as $p):
    $pid = (int) $p['id'];
    $isStudentPost = !empty($p['student_id']);
    $author = $isStudentPost ? ($people[(int) $p['student_id']] ?? null) : null;
    $mine = $isStudentPost && (int) $p['student_id'] === $me;
    $cmts = $commentsByPost[$pid] ?? [];
    $hCount = $reactCounts[$pid]['helpful'] ?? 0;
    $gCount = $reactCounts[$pid]['great'] ?? 0;
    $myR = $myReactions[$pid] ?? '';
?>
<div class="card" style="margin-bottom: 12px;<?= !empty($p['is_pinned']) ? ' border-color: var(--accent);' : '' ?>">
    <!-- Author row -->
    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
        <?= avatarChip($author) ?>
        <div>
            <span style="font-weight: 600; font-size: 13px;"><?= htmlspecialchars($author['name'] ?? 'DDCET Team') ?></span>
            <span style="font-size: 11px; color: var(--text-muted); margin-left: 8px;"><?= date('d M, H:i', strtotime($p['created_at'])) ?></span>
        </div>
        <?php if (!empty($p['is_pinned'])): ?><span class="badge badge-accent" style="margin-left:8px; font-size:10px;">Pinned</span><?php endif; ?>
        <span class="tag" style="margin-left: auto; font-size: 10px;"><?= htmlspecialchars($CATEGORIES[$p['category']] ?? ($p['category'] ?? 'post')) ?></span>
    </div>
    <!-- Content -->
    <?php if (!empty($p['title'])): ?><h3 style="font-size: 15px; margin-bottom: 6px;"><?= htmlspecialchars($p['title']) ?></h3><?php endif; ?>
    <p style="color: var(--text-secondary); font-size: 14px; line-height: 1.7;"><?= nl2br(htmlspecialchars($p['body'] ?? '')) ?></p>
    <!-- Actions -->
    <div style="display: flex; gap: 8px; margin-top: 12px; align-items: center;">
        <button type="button" class="btn btn-sm react-btn <?= $myR === 'helpful' ? 'btn-primary' : 'btn-secondary' ?>" data-post="<?= $pid ?>" data-reaction="helpful" style="font-size:12px; padding:5px 12px;">
            <?= icon('thumbs-up', 12) ?> <span class="rc-helpful"><?= $hCount ?></span>
        </button>
        <button type="button" class="btn btn-sm react-btn <?= $myR === 'great' ? 'btn-orange' : 'btn-secondary' ?>" data-post="<?= $pid ?>" data-reaction="great" style="font-size:12px; padding:5px 12px;">
            <?= icon('fire', 12) ?> <span class="rc-great"><?= $gCount ?></span>
        </button>
        <button type="button" class="btn btn-secondary btn-sm toggle-comments" data-post="<?= $pid ?>" style="font-size:12px; padding:5px 12px;">
            <?= icon('chat', 12) ?> <?= count($cmts) ?> <?= count($cmts) === 1 ? 'reply' : 'replies' ?>
        </button>
        <?php if ($mine): ?>
        <form method="POST" style="display:inline; margin-left:auto;" onsubmit="return confirm('Delete this post?')">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
            <input type="hidden" name="action" value="delete_post">
            <input type="hidden" name="post_id" value="<?= $pid ?>">
            <input type="hidden" name="tab" value="<?= $tab ?>">
            <button class="btn btn-sm btn-secondary" style="font-size:12px; padding:5px 12px; color:var(--red);">Delete</button>
        </form>
        <?php endif; ?>
    </div>
    <!-- Comments (collapsed) — nested Reddit-style threads -->
    <div class="comments" id="comments-<?= $pid ?>" style="display:none; margin-top:12px; border-top:1px solid var(--border); padding-top:12px;">
        <?php
        // Top-level add-comment form (a reply with no parent).
        ?>
        <form method="POST" style="display:flex; gap:8px; margin-bottom:8px;">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
            <input type="hidden" name="action" value="comment">
            <input type="hidden" name="post_id" value="<?= $pid ?>">
            <input type="hidden" name="tab" value="<?= $tab ?>">
            <input type="text" name="body" class="form-control" required maxlength="2000" placeholder="Add a comment..." style="font-size:13px;">
            <button class="btn btn-primary btn-sm">Comment</button>
        </form>
        <?php
        foreach (($rootsByPost[$pid] ?? []) as $root) {
            echo renderCommentTree($root, $kidsOf, $people, $me, $tab);
        }
        ?>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<script>
const CSRF = '<?= htmlspecialchars(csrfToken()) ?>';
const BASE = '<?= BASE_PATH ?>';

// Toggle the comments block for a post.
document.querySelectorAll('.toggle-comments').forEach(function(btn){
    btn.addEventListener('click', function(){
        var box = document.getElementById('comments-' + btn.dataset.post);
        if (box) box.style.display = box.style.display === 'none' ? 'block' : 'none';
    });
});

// Show/hide the inline reply box under a specific comment (event-delegated, so it
// works for every nesting level without per-node listeners).
document.addEventListener('click', function(e){
    var btn = e.target.closest('.reply-toggle');
    if (!btn) return;
    var form = document.getElementById('reply-' + btn.dataset.cid);
    if (!form) return;
    var open = form.style.display !== 'none';
    form.style.display = open ? 'none' : 'flex';
    if (!open) { var inp = form.querySelector('input[name=body]'); if (inp) inp.focus(); }
});

// React (helpful/great) without a page reload. Toggles off if you tap the same one.
document.querySelectorAll('.react-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
        var card = btn.closest('.card');
        fetch(BASE + 'api/community_react.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-Token': CSRF},
            body: JSON.stringify({ post_id: btn.dataset.post, reaction: btn.dataset.reaction })
        }).then(r => r.json()).then(function(d){
            if (!d.ok) return;
            card.querySelector('.rc-helpful').textContent = d.helpful;
            card.querySelector('.rc-great').textContent = d.great;
            // Highlight the active reaction.
            var hb = card.querySelector('.react-btn[data-reaction="helpful"]');
            var gb = card.querySelector('.react-btn[data-reaction="great"]');
            hb.className = 'btn btn-sm react-btn ' + (d.mine === 'helpful' ? 'btn-primary' : 'btn-secondary');
            gb.className = 'btn btn-sm react-btn ' + (d.mine === 'great' ? 'btn-orange' : 'btn-secondary');
        }).catch(function(){});
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
