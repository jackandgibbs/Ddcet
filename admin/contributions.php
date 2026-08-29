<?php
require_once __DIR__ . '/../config.php';
requireAdmin();
$pageTitle = 'Contributions';

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $id = (int)$_POST['contribution_id'];
    $action = $_POST['action'] ?? '';

    if ($action === 'reject') {
        supabaseRest('contributions?id=eq.' . $id, 'PATCH', ['status' => 'rejected']);
        $msg = 'Contribution rejected.';
    } elseif ($action === 'approve') {
        // Fetch the contribution
        $conts = supabaseRest('contributions?id=eq.' . $id . '&select=*') ?? [];
        if (!empty($conts)) {
            $c = $conts[0];
            $content = $c['content'];
            
            if ($c['type'] === 'question') {
                // Insert question
                $q = supabaseRest('questions', 'POST', [
                    'subject' => $content['subject'] ?? 'General',
                    'chapter' => $content['chapter'] ?? null,
                    'question_text' => $content['question_text'],
                    'explanation' => $content['explanation'] ?? null
                ], ['prefer' => 'return=representation']);
                
                if ($q && isset($q[0]['id'])) {
                    $qid = $q[0]['id'];
                    $opts = [];
                    for ($i = 1; $i <= 4; $i++) {
                        $char = chr(96 + $i); // a, b, c, d
                        $opts[] = [
                            'question_id' => $qid,
                            'option_text' => $content['option_' . $char],
                            'is_correct' => ((int)$content['correct_option'] === $i),
                            'position' => $i
                        ];
                    }
                    supabaseRest('options', 'POST', $opts);
                }
            }
            
            // Mark approved
            supabaseRest('contributions?id=eq.' . $id, 'PATCH', ['status' => 'approved']);
            
            // Award XP (e.g. +50 XP)
            $students = supabaseRest('students?id=eq.' . $c['student_id'] . '&select=xp') ?? [];
            if (!empty($students)) {
                $newXp = (int)$students[0]['xp'] + 50;
                supabaseRest('students?id=eq.' . $c['student_id'], 'PATCH', ['xp' => $newXp]);
            }
            
            $msg = 'Contribution approved and 50 XP awarded to the student!';
        }
    }
}

// Fetch pending contributions
$pending = supabaseRest('contributions?status=eq.pending&select=*,students(id,name,email)&order=created_at.asc') ?? [];

// Fetch recent history
$history = supabaseRest('contributions?status=neq.pending&select=*,students(id,name,email)&order=created_at.desc&limit=20') ?? [];

include __DIR__ . '/includes/header.php';
?>

<?php if ($msg): ?>
<div class="card" style="border-color: var(--green); color: var(--green); text-align: center; margin-bottom: 24px; padding: 12px; font-weight: 500;">
    <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom: 24px;">
    <div class="card-header"><h3>Pending Contributions (<?= count($pending) ?>)</h3></div>
    <?php if (empty($pending)): ?>
        <div style="padding: 20px; text-align: center; color: var(--text-muted);">No pending contributions to review.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <th style="padding: 12px;">Date</th>
                        <th style="padding: 12px;">Student</th>
                        <th style="padding: 12px;">Type</th>
                        <th style="padding: 12px;">Content</th>
                        <th style="padding: 12px; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending as $p): ?>
                        <?php 
                            $content = $p['content'] ?? [];
                            $typeLabel = '';
                            $contentHtml = '';
                            if ($p['type'] === 'question') {
                                $typeLabel = '<span class="badge badge-accent">Question</span>';
                                $contentHtml = '<strong>' . htmlspecialchars($content['subject'] ?? '') . '</strong>: ' . htmlspecialchars(substr($content['question_text'] ?? '', 0, 60)) . '...';
                            } elseif ($p['type'] === 'note') {
                                $typeLabel = '<span class="badge badge-green">Note</span>';
                                $contentHtml = '<strong>' . htmlspecialchars($content['title'] ?? '') . '</strong> <a href="' . htmlspecialchars($content['link'] ?? '#') . '" target="_blank">[View Link]</a>';
                            } elseif ($p['type'] === 'error') {
                                $typeLabel = '<span class="badge badge-red">Error</span>';
                                $contentHtml = '<strong>' . htmlspecialchars($content['test_title'] ?? '') . '</strong>: ' . htmlspecialchars(substr($content['description'] ?? '', 0, 60)) . '...';
                            }
                        ?>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 12px; font-size: 13px; color: var(--text-muted);"><?= date('M j, Y', strtotime($p['created_at'])) ?></td>
                        <td style="padding: 12px;">
                            <strong><?= htmlspecialchars($p['students']['name'] ?? 'Unknown') ?></strong><br>
                            <span style="font-size: 12px; color: var(--text-muted);"><?= htmlspecialchars($p['students']['email'] ?? '') ?></span>
                        </td>
                        <td style="padding: 12px;"><?= $typeLabel ?></td>
                        <td style="padding: 12px; font-size: 13px; max-width: 400px;"><?= $contentHtml ?></td>
                        <td style="padding: 12px; text-align: right;">
                            <form method="POST" style="display: inline-block;">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
                                <input type="hidden" name="contribution_id" value="<?= $p['id'] ?>">
                                <button type="submit" name="action" value="approve" class="btn btn-sm btn-primary" onclick="return confirm('Approve this contribution? It will reward 50 XP.');">Approve</button>
                                <button type="submit" name="action" value="reject" class="btn btn-sm btn-danger" onclick="return confirm('Reject this contribution?');" style="margin-left: 4px;">Reject</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header"><h3>Recent History</h3></div>
    <div class="table-wrap">
        <table style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead>
                <tr style="border-bottom: 1px solid var(--border);">
                    <th style="padding: 12px;">Date</th>
                    <th style="padding: 12px;">Student</th>
                    <th style="padding: 12px;">Type</th>
                    <th style="padding: 12px;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $h): ?>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 12px; font-size: 13px; color: var(--text-muted);"><?= date('M j, Y', strtotime($h['created_at'])) ?></td>
                        <td style="padding: 12px;"><?= htmlspecialchars($h['students']['name'] ?? 'Unknown') ?></td>
                        <td style="padding: 12px;"><?= htmlspecialchars(ucfirst($h['type'])) ?></td>
                        <td style="padding: 12px;">
                            <?php if ($h['status'] === 'approved'): ?>
                                <span class="badge badge-green">Approved</span>
                            <?php else: ?>
                                <span class="badge badge-red">Rejected</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
