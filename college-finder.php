<?php
require_once __DIR__ . '/config.php';
$pageTitle = 'College Finder';

// Load CSV data (the actual files are split by year)
$csvFiles = ['ddcet_full_data_2024.csv', 'ddcet_full_data_2025.csv'];
$colleges = [];
$branches = $cities = $types = $categories = [];
$seen = [];

foreach ($csvFiles as $csvName) {
    $csvFile = __DIR__ . '/' . $csvName;
    if (!file_exists($csvFile)) continue;
    $file = fopen($csvFile, 'r');
    if ($file === false) continue;
    fgetcsv($file); // Skip header
    while (($row = fgetcsv($file)) !== FALSE) {
        if (count($row) < 10) continue; // skip malformed rows
        // Dedupe identical rows that appear in more than one file
        $key = implode('|', array_map('strval', $row));
        if (isset($seen[$key])) continue;
        $seen[$key] = true;

        $city = trim(explode(',', $row[1])[1] ?? 'Unknown');
        $colleges[] = [
            'name' => $row[1],
            'city' => $city,
            'type' => $row[2],
            'branch' => $row[3],
            'category' => $row[4],
            'quota' => $row[5],
            'first_marks' => (float)$row[6],
            'first_rank' => (int)$row[7],
            'last_marks' => (float)$row[8],
            'last_rank' => (int)$row[9]
        ];
        $branches[$row[3]] = 1;
        $cities[$city] = 1;
        $types[$row[2]] = 1;
        $categories[$row[4]] = 1;
    }
    fclose($file);
}

ksort($branches);
ksort($cities);
ksort($types);
ksort($categories);

$predictions = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rank = (int)$_POST['rank'];
    $category = $_POST['category'];
    $branch = $_POST['branch'] ?? '';
    $city = $_POST['city'] ?? '';
    $type = $_POST['type'] ?? '';
    $sortBy = $_POST['sort'] ?? 'confidence';
    
    $filtered = array_filter($colleges, function($c) use ($category, $branch, $city, $type) {
        if ($c['category'] !== $category) return false;
        if ($branch && $c['branch'] !== $branch) return false;
        if ($city && $c['city'] !== $city) return false;
        if ($type && $c['type'] !== $type) return false;
        return true;
    });
    
    $predictions = array_map(function($c) use ($rank) {
        $margin = $c['last_rank'] - $rank;
        
        if ($rank <= $c['last_rank']) {
            $chance = 'High';
            $confidence = min(100, 70 + ($margin / max($c['last_rank'], 1)) * 30);
        } elseif ($rank <= $c['last_rank'] + 1000) {
            $chance = 'Medium';
            $confidence = max(30, 70 - (($rank - $c['last_rank']) / 1000) * 40);
        } else {
            $chance = 'Low';
            $confidence = max(5, 30 - (($rank - $c['last_rank']) / 2000) * 25);
        }
        
        return array_merge($c, [
            'chance' => $chance,
            'confidence' => round($confidence, 1),
            'margin' => $margin
        ]);
    }, $filtered);
    
    // Sort results
    usort($predictions, function($a, $b) use ($sortBy) {
        if ($sortBy === 'rank') return $a['last_rank'] - $b['last_rank'];
        if ($sortBy === 'name') return strcmp($a['name'], $b['name']);
        return $b['confidence'] - $a['confidence'];
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> — DDCET Prep</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800;900&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f8f9fa; --card: #ffffff; --accent: #4361ee; --accent-hover: #3a56d4;
            --green: #10b981; --orange: #f59e0b; --purple: #8b5cf6; --red: #ef4444;
            --text: #1a1a2e; --text-sec: #6c757d; --text-muted: #adb5bd;
            --border: #e9ecef; --radius: 12px;
            --font: 'DM Sans', sans-serif; --mono: 'DM Mono', monospace;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: var(--bg); color: var(--text); font-family: var(--font); line-height: 1.6; }
        
        .nav { position: fixed; top: 0; left: 0; right: 0; z-index: 100; background: rgba(255,255,255,0.85); backdrop-filter: blur(20px); border-bottom: 1px solid var(--border); }
        .nav-inner { max-width: 1200px; margin: 0 auto; padding: 16px 32px; display: flex; align-items: center; justify-content: space-between; }
        .nav-brand { font-size: 22px; font-weight: 800; color: var(--text); }
        .nav-brand span { color: var(--accent); }
        .nav-back { color: var(--text-sec); font-size: 14px; font-weight: 500; transition: color 0.2s; }
        .nav-back:hover { color: var(--accent); }
        
        .container { max-width: 1200px; margin: 0 auto; padding: 100px 32px 80px; }
        .hero { text-align: center; margin-bottom: 48px; }
        .hero h1 { font-size: 48px; font-weight: 900; line-height: 1.1; margin-bottom: 12px; color: var(--accent); }
        .hero p { font-size: 18px; color: var(--text-sec); }
        
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 32px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; color: var(--text); margin-bottom: 8px; font-size: 14px; }
        .form-group input, .form-group select { width: 100%; padding: 12px 16px; border: 2px solid var(--border); border-radius: 10px; font-size: 14px; font-family: var(--font); transition: all 0.2s; background: var(--card); color: var(--text); }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(67,97,238,0.1); }
        
        .btn { background: var(--accent); color: #fff; border: none; padding: 14px 32px; border-radius: 10px; font-weight: 700; cursor: pointer; font-size: 16px; transition: all 0.2s; font-family: var(--font); }
        .btn:hover { background: var(--accent-hover); transform: translateY(-1px); box-shadow: 0 8px 20px rgba(67,97,238,0.3); }
        .btn-full { width: 100%; }
        
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; margin-bottom: 32px; }
        .stat-card { background: var(--card); border: 2px solid var(--border); color: var(--text); padding: 24px; border-radius: 12px; text-align: center; }
        .stat-card.green { border-color: var(--green); }
        .stat-card.orange { border-color: var(--orange); }
        .stat-card.red { border-color: var(--red); }
        .stat-card.accent { border-color: var(--accent); }
        .stat-card .num { font-family: var(--mono); font-size: 36px; font-weight: 700; line-height: 1; }
        .stat-card.green .num { color: var(--green); }
        .stat-card.orange .num { color: var(--orange); }
        .stat-card.red .num { color: var(--red); }
        .stat-card.accent .num { color: var(--accent); }
        .stat-card .label { font-size: 13px; color: var(--text-sec); margin-top: 6px; }
        
        .result-item { background: var(--bg); border: 2px solid var(--border); border-radius: 12px; padding: 24px; margin-bottom: 16px; transition: all 0.2s; }
        .result-item:hover { border-color: var(--accent); box-shadow: 0 4px 12px rgba(67,97,238,0.1); }
        .result-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 16px; flex-wrap: wrap; gap: 12px; }
        .result-title { font-size: 18px; font-weight: 700; color: var(--text); }
        .result-city { font-size: 14px; color: var(--text-sec); margin-top: 4px; }
        
        .badge { display: inline-flex; align-items: center; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; gap: 6px; }
        .badge-high { background: #d1fae5; color: #065f46; }
        .badge-medium { background: #fef3c7; color: #92400e; }
        .badge-low { background: #fee2e2; color: #991b1b; }
        .dot { width: 8px; height: 8px; border-radius: 50%; }
        .dot-high { background: var(--green); }
        .dot-medium { background: var(--orange); }
        .dot-low { background: var(--red); }
        
        .result-meta { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; font-size: 14px; }
        .meta-item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--border); }
        .meta-label { color: var(--text-sec); font-weight: 500; }
        .meta-value { font-family: var(--mono); font-weight: 600; color: var(--text); }
        
        .confidence-bar { margin-top: 12px; }
        .confidence-label { display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 6px; color: var(--text-sec); }
        .confidence-track { height: 8px; background: var(--border); border-radius: 4px; overflow: hidden; }
        .confidence-fill { height: 100%; background: var(--accent); border-radius: 4px; transition: width 0.3s; }
        
        .empty { text-align: center; padding: 60px 32px; color: var(--text-sec); }
        .empty-icon { font-size: 64px; margin-bottom: 16px; opacity: 0.3; }
        
        @media (max-width: 768px) {
            .hero h1 { font-size: 32px; }
            .form-grid { grid-template-columns: 1fr; }
            .stats { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <nav class="nav">
        <div class="nav-inner">
            <div class="nav-brand">DDCET<span>Prep</span></div>
            <a href="<?= BASE_PATH ?>dashboard.php" class="nav-back">← Back to Dashboard</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="hero">
            <h1>College Finder</h1>
            <p>Predict your admission chances based on rank and category</p>
        </div>
        
        <?php if (empty($colleges)): ?>
            <div class="card" style="border-color: var(--red);">
                <strong>College data is currently unavailable.</strong>
                <p style="color: var(--text-sec); margin-top: 6px;">Please try again later or contact support.</p>
            </div>
        <?php endif; ?>

        <div class="card">
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Your DDCET Rank *</label>
                        <input type="number" name="rank" required min="1" value="<?= htmlspecialchars($_POST['rank'] ?? '') ?>" placeholder="e.g., 5000">
                    </div>
                    <div class="form-group">
                        <label>Category *</label>
                        <select name="category" required>
                            <option value="">Select Category</option>
                            <?php foreach (array_keys($categories) as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>" <?= ($_POST['category'] ?? '') === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Branch (Optional)</label>
                        <select name="branch">
                            <option value="">All Branches</option>
                            <?php foreach (array_keys($branches) as $b): ?>
                                <option value="<?= htmlspecialchars($b) ?>" <?= ($_POST['branch'] ?? '') === $b ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>City (Optional)</label>
                        <select name="city">
                            <option value="">All Cities</option>
                            <?php foreach (array_keys($cities) as $c): ?>
                                <option value="<?= htmlspecialchars($c) ?>" <?= ($_POST['city'] ?? '') === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>College Type (Optional)</label>
                        <select name="type">
                            <option value="">All Types</option>
                            <?php foreach (array_keys($types) as $t): ?>
                                <option value="<?= htmlspecialchars($t) ?>" <?= ($_POST['type'] ?? '') === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Sort By</label>
                        <select name="sort">
                            <option value="confidence" <?= ($_POST['sort'] ?? 'confidence') === 'confidence' ? 'selected' : '' ?>>Best Match</option>
                            <option value="rank" <?= ($_POST['sort'] ?? '') === 'rank' ? 'selected' : '' ?>>Cutoff Rank</option>
                            <option value="name" <?= ($_POST['sort'] ?? '') === 'name' ? 'selected' : '' ?>>College Name</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-full">Find Colleges</button>
            </form>
        </div>
        
        <?php if ($predictions !== null): ?>
            <?php 
                $high = count(array_filter($predictions, fn($p) => $p['chance'] === 'High'));
                $medium = count(array_filter($predictions, fn($p) => $p['chance'] === 'Medium'));
                $low = count(array_filter($predictions, fn($p) => $p['chance'] === 'Low'));
                $total = count($predictions);
            ?>
            
            <div class="stats">
                <div class="stat-card accent">
                    <div class="num"><?= $total ?></div>
                    <div class="label">Total Matches</div>
                </div>
                <div class="stat-card green">
                    <div class="num"><?= $high ?></div>
                    <div class="label">High Chance</div>
                </div>
                <div class="stat-card orange">
                    <div class="num"><?= $medium ?></div>
                    <div class="label">Medium Chance</div>
                </div>
                <div class="stat-card red">
                    <div class="num"><?= $low ?></div>
                    <div class="label">Low Chance</div>
                </div>
            </div>
            
            <?php if (empty($predictions)): ?>
                <div class="card empty">
                    <div class="empty-icon">No Results</div>
                    <h3>No colleges found</h3>
                    <p>Try adjusting your filters or selecting a different category</p>
                </div>
            <?php else: ?>
                <div class="card">
                    <h2 style="margin-bottom: 24px; font-size: 24px;">Your College Options (<?= $total ?>)</h2>
                    
                    <?php foreach ($predictions as $p): ?>
                        <div class="result-item">
                            <div class="result-header">
                                <div>
                                    <div class="result-title"><?= htmlspecialchars($p['name']) ?></div>
                                    <div class="result-city"><?= htmlspecialchars($p['city']) ?> • <?= htmlspecialchars($p['type']) ?></div>
                                </div>
                                <span class="badge badge-<?= strtolower($p['chance']) ?>">
                                    <span class="dot dot-<?= strtolower($p['chance']) ?>"></span>
                                    <?= $p['chance'] ?> Chance
                                </span>
                            </div>
                            
                            <div style="background: var(--card); padding: 16px; border-radius: 8px; margin-bottom: 12px;">
                                <strong style="font-size: 14px; color: var(--text-sec);">Branch:</strong>
                                <div style="font-size: 16px; font-weight: 600; margin-top: 4px;"><?= htmlspecialchars($p['branch']) ?></div>
                            </div>
                            
                            <div class="result-meta">
                                <div class="meta-item">
                                    <span class="meta-label">First Rank</span>
                                    <span class="meta-value"><?= number_format($p['first_rank']) ?></span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Last Rank</span>
                                    <span class="meta-value"><?= number_format($p['last_rank']) ?></span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Your Margin</span>
                                    <span class="meta-value" style="color: <?= $p['margin'] >= 0 ? 'var(--green)' : 'var(--red)' ?>">
                                        <?= $p['margin'] > 0 ? '+' : '' ?><?= number_format($p['margin']) ?>
                                    </span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Quota</span>
                                    <span class="meta-value"><?= htmlspecialchars($p['quota']) ?></span>
                                </div>
                            </div>
                            
                            <div class="confidence-bar">
                                <div class="confidence-label">
                                    <span>Confidence Score</span>
                                    <strong><?= $p['confidence'] ?>%</strong>
                                </div>
                                <div class="confidence-track">
                                    <div class="confidence-fill" style="width: <?= $p['confidence'] ?>%"></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
