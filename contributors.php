<?php
require_once __DIR__ . '/config.php';
$pageTitle = 'Hall of Fame';

// Fetch approved contributions and the student details via Supabase foreign key join
$contributions = supabaseRest('contributions?status=eq.approved&select=student_id,type,students(id,name,avatar_url)') ?? [];

$contributors = [];
foreach ($contributions as $c) {
    if (empty($c['students'])) continue;
    $sid = $c['student_id'];
    if (!isset($contributors[$sid])) {
        $contributors[$sid] = [
            'name' => $c['students']['name'],
            'avatar_url' => $c['students']['avatar_url'],
            'total' => 0,
            'questions' => 0,
            'notes' => 0,
            'errors' => 0
        ];
    }
    $contributors[$sid]['total']++;
    if ($c['type'] === 'question') $contributors[$sid]['questions']++;
    if ($c['type'] === 'note') $contributors[$sid]['notes']++;
    if ($c['type'] === 'error') $contributors[$sid]['errors']++;
}

// Rank contributors
usort($contributors, function($a, $b) {
    return $b['total'] <=> $a['total'];
});

include __DIR__ . '/includes/header.php';
?>

<div class="page-header" style="text-align: center; margin-bottom: 40px; padding: 40px 20px;">
    <h1 style="font-size: 36px; letter-spacing: -1px; margin-bottom: 16px; font-weight: 800;">
        Built for Students, <span class="accent">by Students</span>.
    </h1>
    <p style="color: var(--text-muted); font-size: 16px; max-width: 600px; margin: 0 auto 24px; line-height: 1.6;">
        These are the legendary students who go above and beyond to help the community by submitting challenging questions, study notes, and reporting errors.
    </p>
    <a href="contribute.php" class="btn btn-primary" style="padding: 12px 24px; font-size: 16px; font-weight: 600; border-radius: 30px;">
        Start Contributing
    </a>
</div>

<?php if (empty($contributors)): ?>
<div style="text-align: center; color: var(--text-muted); padding: 40px;">
    <?= icon('star') ?>
    <h3 style="margin-top: 16px;">Be the first contributor!</h3>
    <p>No contributions have been approved yet. Make history by submitting yours today.</p>
</div>
<?php else: ?>
    <div style="max-width: 900px; margin: 0 auto; display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
        <?php foreach (array_slice($contributors, 0, 3) as $index => $c): ?>
            <?php 
                $rankColor = '';
                $rankIcon = '';
                if ($index === 0) { $rankColor = 'var(--yellow)'; $rankIcon = '🏆 1st'; }
                elseif ($index === 1) { $rankColor = 'var(--text-muted)'; $rankIcon = '🥈 2nd'; }
                elseif ($index === 2) { $rankColor = 'var(--accent)'; $rankIcon = '🥉 3rd'; }
            ?>
            <div class="card" style="text-align: center; padding: 30px 20px; border-top: 4px solid <?= $rankColor ?>;">
                <div style="position: absolute; top: 12px; right: 12px; font-size: 14px; font-weight: 700; color: <?= $rankColor ?>;">
                    <?= $rankIcon ?>
                </div>
                
                <?php if (!empty($c['avatar_url'])): ?>
                    <img src="<?= htmlspecialchars($c['avatar_url']) ?>" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; margin: 0 auto 16px; border: 2px solid <?= $rankColor ?>; padding: 2px;">
                <?php else: ?>
                    <div style="width: 80px; height: 80px; border-radius: 50%; background: var(--bg-tertiary); margin: 0 auto 16px; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: 700; color: var(--text-muted); border: 2px solid <?= $rankColor ?>; padding: 2px;">
                        <?= strtoupper(substr($c['name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
                
                <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 4px;"><?= htmlspecialchars($c['name']) ?></h3>
                <div style="color: var(--accent); font-weight: 700; font-size: 14px; margin-bottom: 16px;">
                    <?= $c['total'] ?> Contributions
                </div>
                
                <div style="display: flex; flex-wrap: wrap; justify-content: center; gap: 8px;">
                    <?php if ($c['questions'] > 0): ?>
                        <span class="badge badge-accent" title="<?= $c['questions'] ?> Questions">🧠 Question Guru</span>
                    <?php endif; ?>
                    <?php if ($c['notes'] > 0): ?>
                        <span class="badge badge-green" title="<?= $c['notes'] ?> Notes">📝 Notes Master</span>
                    <?php endif; ?>
                    <?php if ($c['errors'] > 0): ?>
                        <span class="badge badge-red" title="<?= $c['errors'] ?> Errors Found">🐞 Bug Hunter</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <?php if (count($contributors) > 3): ?>
    <div class="card" style="max-width: 900px; margin: 30px auto 0; padding: 0;">
        <div class="card-header" style="padding: 20px;">
            <h3 style="margin: 0;">More Awesome Contributors</h3>
        </div>
        <div class="table-wrap">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <th style="padding: 12px 20px;">Rank</th>
                        <th style="padding: 12px 20px;">Student</th>
                        <th style="padding: 12px 20px;">Contributions</th>
                        <th style="padding: 12px 20px;">Badges</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($contributors, 3) as $index => $c): ?>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 12px 20px; font-weight: 600; color: var(--text-muted);">#<?= $index + 4 ?></td>
                        <td style="padding: 12px 20px;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <?php if (!empty($c['avatar_url'])): ?>
                                    <img src="<?= htmlspecialchars($c['avatar_url']) ?>" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
                                <?php else: ?>
                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--bg-tertiary); display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 700; color: var(--text-muted);">
                                        <?= strtoupper(substr($c['name'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <span style="font-weight: 500;"><?= htmlspecialchars($c['name']) ?></span>
                            </div>
                        </td>
                        <td style="padding: 12px 20px; font-weight: 600;"><?= $c['total'] ?></td>
                        <td style="padding: 12px 20px; display: flex; gap: 6px;">
                            <?php if ($c['questions'] > 0): ?><span class="badge badge-accent">🧠</span><?php endif; ?>
                            <?php if ($c['notes'] > 0): ?><span class="badge badge-green">📝</span><?php endif; ?>
                            <?php if ($c['errors'] > 0): ?><span class="badge badge-red">🐞</span><?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
