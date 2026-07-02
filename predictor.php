<?php
$pageTitle = 'College Predictor';
include 'includes/header.php';

function loadCSVData() {
    $data = [];
    $csvFiles = ['ddcet_full_data_2024.csv', 'ddcet_full_data_2025.csv'];
    $seen = [];

    foreach ($csvFiles as $csvFile) {
        $csvPath = __DIR__ . DIRECTORY_SEPARATOR . $csvFile;
        if (!file_exists($csvPath)) continue;
        if (($handle = fopen($csvPath, 'r')) === false) continue;

        // Pull the 4-digit year out of the filename (e.g. ..._2024.csv -> 2024)
        $year = preg_match('/(\d{4})/', $csvFile, $m) ? $m[1] : '';

        $headers = fgetcsv($handle);
        while (($row = fgetcsv($handle)) !== false) {
            // Dedupe: skip if this exact 10-col row was already loaded from an earlier file
            $key = $year . '|' . implode('|', array_map('strval', $row));
            if (isset($seen[$key])) continue;
            $seen[$key] = true;

            $inst = $row[1];
            $parts = explode(',', $inst, 2);
            $city = isset($parts[1]) ? trim($parts[1]) : '';
            $data[] = [
                'year' => $year,
                'institute' => $inst,
                'college'   => trim($parts[0]),
                'city'      => $city,
                'inst_type' => $row[2],
                'branch' => $row[3],
                'category' => $row[4],
                'quota' => $row[5],
                'first_marks' => $row[6],
                'first_rank' => $row[7],
                'last_marks' => $row[8],
                'last_rank' => $row[9]
            ];
        }
        fclose($handle);
    }
    return $data;
}

$allData = loadCSVData();
$branches = array_values(array_unique(array_column($allData, 'branch')));
$institutes = array_values(array_unique(array_column($allData, 'institute')));
$institutes = array_map(function($name) {
    return trim(explode(',', $name, 2)[0]);
}, $institutes);
$cities = array_values(array_unique(array_filter(array_column($allData, 'city'))));
sort($branches);
sort($institutes);
sort($cities);

$predictions = null;
$accuracy = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rank = (int)$_POST['rank'];
    $category = $_POST['category'];
    $branch = $_POST['branch'];
    $institute = $_POST['institute'] ?? '';
    $instType = $_POST['inst_type'] ?? 'all';
    $city = $_POST['city'] ?? 'all';

    $filtered = array_filter($allData, function($row) use ($category, $branch, $institute, $instType, $city) {
        if ($row['category'] !== $category) return false;
        if ($branch !== 'all' && $row['branch'] !== $branch) return false;
        if ($institute !== 'all' && trim(explode(',', $row['institute'], 2)[0]) !== $institute) return false;
        if ($instType !== 'all' && $row['inst_type'] !== $instType) return false;
        if ($city !== 'all' && $row['city'] !== $city) return false;
        return true;
    });
    
    $predictions = [];
    foreach ($filtered as $row) {
        $lr = (int)$row['last_rank'];
        if ($lr <= 0) continue; // no usable cutoff
        $margin = $lr - $rank;

        // Counseling-style buckets, centred on the closing cutoff:
        //   Safe   = comfortably inside (rank <= 90% of cutoff)
        //   Target = right around the cutoff (90%–110%) — realistic, could go either way
        //   Reach  = above the cutoff (>110%) — a stretch, depends on cutoffs loosening
        if ($rank <= $lr * 0.90) {
            $chance = 'Safe';
            $confidence = min(99, 80 + ($margin / $lr) * 20);
        } elseif ($rank <= $lr * 1.10) {
            $chance = 'Target';
            $confidence = max(45, 75 - (abs($rank - $lr) / max($lr * 0.10, 1)) * 25);
        } else {
            $chance = 'Reach';
            $confidence = max(3, 40 - (($rank - $lr * 1.10) / $lr) * 35);
        }

        $predictions[] = array_merge($row, [
            'chance' => $chance,
            'confidence' => round($confidence, 1),
            'margin' => $margin
        ]);
    }

    usort($predictions, fn($a, $b) => $b['confidence'] <=> $a['confidence']);

    $safe   = count(array_filter($predictions, fn($p) => $p['chance'] === 'Safe'));
    $target = count(array_filter($predictions, fn($p) => $p['chance'] === 'Target'));
    $reach  = count(array_filter($predictions, fn($p) => $p['chance'] === 'Reach'));
    $total  = count($predictions);

    $accuracy = $total > 0 ? round((($safe * 1.0 + $target * 0.6 + $reach * 0.2) / $total) * 100, 1) : 0;
}

// ---- View routing: predict (rank-based) | browse (one college) | compare (side-by-side) ----
$view = in_array($_GET['view'] ?? '', ['browse', 'compare'], true) ? $_GET['view'] : 'predict';

// Unique college list (display name => meta) for the browse/compare pickers
$collegeList = [];
foreach ($allData as $row) {
    $name = trim(explode(',', $row['institute'], 2)[0]);
    if (!isset($collegeList[$name])) {
        $collegeList[$name] = ['city' => $row['city'], 'type' => $row['inst_type']];
    }
}
ksort($collegeList);

$years = array_values(array_unique(array_column($allData, 'year')));
sort($years);

// Categories present in the data, common ones first
$allCategories = array_values(array_unique(array_column($allData, 'category')));
$catOrder = ['OP', 'EW', 'SC', 'ST', 'SE', 'SEBC', 'TF', 'TFW', 'PH'];
usort($allCategories, function ($a, $b) use ($catOrder) {
    $ia = array_search($a, $catOrder); $ib = array_search($b, $catOrder);
    if ($ia === false) $ia = 99; if ($ib === false) $ib = 99;
    return $ia === $ib ? strcmp($a, $b) : $ia - $ib;
});

$selectedCollege = $_GET['college'] ?? '';
$collegeBranches = null;   // [branch][category][year] = ['first_rank'=>, 'last_rank'=>, ...]
$collegeCategories = [];   // ordered list of categories present for this college
$collegeMeta = null;

if ($view === 'browse' && $selectedCollege !== '') {
    $collegeBranches = [];
    $catSeen = [];
    foreach ($allData as $row) {
        if (trim(explode(',', $row['institute'], 2)[0]) !== $selectedCollege) continue;
        if ($collegeMeta === null) {
            $collegeMeta = ['city' => $row['city'], 'type' => $row['inst_type']];
        }
        $collegeBranches[$row['branch']][$row['category']][$row['year']] = $row;
        $catSeen[$row['category']] = true;
    }
    ksort($collegeBranches);
    // Show common categories first in a sensible order, then any extras alphabetically
    $preferred = ['OP', 'EW', 'SC', 'ST', 'SE', 'SEBC', 'TF', 'TFW', 'PH'];
    foreach ($preferred as $c) {
        if (isset($catSeen[$c])) { $collegeCategories[] = $c; unset($catSeen[$c]); }
    }
    $rest = array_keys($catSeen);
    sort($rest);
    $collegeCategories = array_merge($collegeCategories, $rest);
}

// ---- Compare mode: 2–3 colleges side by side, branch-wise cutoffs for one category ----
$compareColleges = array_values(array_filter((array)($_GET['c'] ?? []), fn($n) => isset($collegeList[$n])));
$compareColleges = array_slice(array_unique($compareColleges), 0, 3);
$compareCat = in_array($_GET['cat'] ?? '', $allCategories, true) ? $_GET['cat'] : ($allCategories[0] ?? 'OP');
$compareData = null;    // [college][branch][year] = row
$compareBranches = [];  // union of branches across the selected colleges

if ($view === 'compare' && count($compareColleges) >= 1) {
    $compareData = [];
    $branchSet = [];
    foreach ($allData as $row) {
        $name = trim(explode(',', $row['institute'], 2)[0]);
        if (!in_array($name, $compareColleges, true)) continue;
        if ($row['category'] !== $compareCat) continue;
        $compareData[$name][$row['branch']][$row['year']] = $row;
        $branchSet[$row['branch']] = true;
    }
    ksort($branchSet);
    $compareBranches = array_keys($branchSet);
}
?>

<style>
body { background: white; min-height: 100vh; font-family: 'DM Sans', sans-serif; color: black; }
.container { max-width: 1200px; margin: 0 auto; padding: 40px 20px; }
.card { background: white; border-radius: 20px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 30px; border: 1px solid #e2e8f0; }
h1 { font-size: 32px; font-weight: 800; color: black; margin: 0 0 10px; }
.subtitle { color: #4a5568; font-size: 16px; margin-bottom: 30px; }
.form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
.form-group label { display: block; font-weight: 600; color: black; margin-bottom: 8px; font-size: 14px; }
.form-group input, .form-group select { width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px; transition: all 0.3s; }
.form-group input:focus, .form-group select:focus { outline: none; border-color: #000; box-shadow: 0 0 0 3px rgba(0,0,0,0.1); }
.btn { background: black; color: white; border: none; padding: 14px 30px; border-radius: 10px; font-weight: 600; cursor: pointer; width: 100%; font-size: 16px; transition: transform 0.2s; }
.btn:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
.btn:disabled { opacity: 0.85; cursor: not-allowed; transform: none; box-shadow: none; }
.btn .spinner { display: inline-block; width: 16px; height: 16px; margin-right: 10px; vertical-align: -3px; border: 2px solid rgba(255,255,255,0.35); border-top-color: #fff; border-radius: 50%; animation: btnspin 0.6s linear infinite; }
@keyframes btnspin { to { transform: rotate(360deg); } }
.result-item { background: #f7fafc; border-left: 4px solid black; padding: 20px; border-radius: 10px; margin-bottom: 15px; }
.result-item h3 { font-size: 18px; font-weight: 700; color: black; margin: 0 0 10px; }
.result-meta { display: flex; gap: 20px; flex-wrap: wrap; font-size: 14px; color: #4a5568; }
.badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.badge-high, .badge-safe { background: #c6f6d5; color: #22543d; }
.badge-medium, .badge-target { background: #feebc8; color: #744210; }
.badge-low, .badge-reach { background: #fed7d7; color: #742a2a; }
.legend { display: flex; gap: 18px; flex-wrap: wrap; margin-top: 18px; font-size: 13px; color: #4a5568; }
.legend span { display: inline-flex; align-items: center; gap: 7px; }
.legend .dot { width: 10px; height: 10px; border-radius: 50%; }
.legend .dot-safe { background: #38a169; }
.legend .dot-target { background: #dd6b20; }
.legend .dot-reach { background: #e53e3e; }
.accuracy { background: white; color: black; padding: 20px; border-radius: 15px; text-align: center; margin-bottom: 30px; border: 2px solid black; }
.accuracy h2 { font-size: 48px; font-weight: 800; margin: 0; }
.accuracy p { margin: 5px 0 0; opacity: 0.8; }

/* Mode tabs */
.tabs { display: inline-flex; background: #f1f5f9; border-radius: 12px; padding: 5px; margin-bottom: 25px; gap: 4px; }
.tabs a { padding: 10px 22px; border-radius: 8px; font-weight: 600; font-size: 14px; color: #475569; text-decoration: none; transition: all 0.2s; }
.tabs a.active { background: black; color: white; }

/* Browse: college search + grid */
.search-box { width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 15px; margin-bottom: 20px; }
.search-box:focus { outline: none; border-color: #000; }
.college-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 14px; }
.college-tile { display: block; padding: 18px; border: 1px solid #e2e8f0; border-radius: 12px; text-decoration: none; color: black; transition: all 0.2s; background: white; }
.college-tile:hover { border-color: #000; transform: translateY(-2px); box-shadow: 0 8px 18px rgba(0,0,0,0.08); }
.college-tile .name { font-weight: 700; font-size: 15px; margin-bottom: 6px; }
.college-tile .meta { font-size: 13px; color: #64748b; }

/* Browse: cutoff table */
.back-link { display: inline-block; margin-bottom: 18px; color: #475569; text-decoration: none; font-weight: 600; font-size: 14px; }
.back-link:hover { color: black; }
.cutoff-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.cutoff-table th, .cutoff-table td { border: 1px solid #e2e8f0; padding: 10px 12px; text-align: center; }
.cutoff-table th { background: #f8fafc; font-weight: 700; }
.cutoff-table td.branch-cell { text-align: left; font-weight: 600; background: #fafafa; min-width: 200px; }
.cutoff-table tr:hover td { background: #f7fafc; }
.yr { display: flex; justify-content: space-between; gap: 8px; padding: 2px 0; }
.yr .y { color: #94a3b8; font-size: 11px; font-weight: 600; }
.yr .v { font-weight: 700; font-family: 'DM Mono', monospace; }
.yr.empty .v { color: #cbd5e1; font-weight: 400; }
.table-wrap { overflow-x: auto; }
.table-note { font-size: 13px; color: #64748b; margin: 0 0 18px; }

/* keep the branch (first) column visible while the table scrolls sideways */
.cutoff-table td.branch-cell, .cutoff-table th.branch-cell { position: sticky; left: 0; z-index: 1; }

@media (max-width: 640px) {
    .container { padding: 24px 14px; }
    .card { padding: 20px 16px; border-radius: 16px; }
    h1 { font-size: 24px; }
    .subtitle { font-size: 14px; margin-bottom: 20px; }

    /* tabs: scroll horizontally instead of overflowing/wrapping awkwardly */
    .tabs { display: flex; width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; gap: 4px; }
    .tabs::-webkit-scrollbar { display: none; }
    .tabs a { flex: 1 0 auto; text-align: center; padding: 10px 14px; font-size: 13px; white-space: nowrap; }

    .form-grid { grid-template-columns: 1fr; gap: 14px; margin-bottom: 16px; }
    .btn { padding: 14px 20px; }

    .accuracy h2 { font-size: 38px; }
    .legend { flex-direction: column; gap: 8px; }

    /* result cards stack the title and badge vertically */
    .result-item { padding: 16px; }
    .result-item > div:first-child { flex-direction: column; gap: 10px; }
    .result-item > div:first-child > div[style*="text-align:right"] { text-align: left !important; }
    .result-meta { gap: 8px 16px; }

    /* college browse grid: one per row */
    .college-grid { grid-template-columns: 1fr; }

    /* compact, scrollable cutoff tables */
    .cutoff-table { font-size: 12px; }
    .cutoff-table th, .cutoff-table td { padding: 8px 8px; }
    .cutoff-table td.branch-cell { min-width: 140px; font-size: 12px; }
    .yr { gap: 6px; }
}
</style>

<div class="container">
    <div class="tabs">
        <a href="predictor.php" class="<?= $view === 'predict' ? 'active' : '' ?>">Predict by Rank</a>
        <a href="predictor.php?view=browse" class="<?= $view === 'browse' ? 'active' : '' ?>">Browse Colleges</a>
        <a href="predictor.php?view=compare" class="<?= $view === 'compare' ? 'active' : '' ?>">Compare</a>
    </div>

    <?php if ($view === 'predict'): ?>
    <div class="card">
        <h1>DDCET College Predictor</h1>
        <p class="subtitle">Enter your details to predict admission chances</p>

        <form method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label>DDCET Rank *</label>
                    <input type="number" name="rank" required value="<?= htmlspecialchars($_POST['rank'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Category *</label>
                    <select name="category" required>
                        <option value="OP" <?= ($_POST['category'] ?? '') === 'OP' ? 'selected' : '' ?>>Open (OP)</option>
                        <option value="EW" <?= ($_POST['category'] ?? '') === 'EW' ? 'selected' : '' ?>>EWS (EW)</option>
                        <option value="SC" <?= ($_POST['category'] ?? '') === 'SC' ? 'selected' : '' ?>>SC</option>
                        <option value="SE" <?= ($_POST['category'] ?? '') === 'SE' ? 'selected' : '' ?>>SEBC (SE)</option>
                        <option value="TF" <?= ($_POST['category'] ?? '') === 'TF' ? 'selected' : '' ?>>TFWS (TF)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <select name="inst_type">
                        <option value="all" <?= ($_POST['inst_type'] ?? 'all') === 'all' ? 'selected' : '' ?>>All Types</option>
                        <option value="SFI" <?= ($_POST['inst_type'] ?? '') === 'SFI' ? 'selected' : '' ?>>SFI (Self-Financed)</option>
                        <option value="Home State" <?= ($_POST['inst_type'] ?? '') === 'Home State' ? 'selected' : '' ?>>Home State</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Branch</label>
                    <select name="branch">
                        <option value="all">All Branches</option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= htmlspecialchars($b) ?>" <?= ($_POST['branch'] ?? '') === $b ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>College</label>
                    <select name="institute">
                        <option value="all">All Colleges</option>
                        <?php foreach ($institutes as $i): ?>
                            <option value="<?= htmlspecialchars($i) ?>" <?= ($_POST['institute'] ?? '') === $i ? 'selected' : '' ?>><?= htmlspecialchars($i) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>City</label>
                    <select name="city">
                        <option value="all" <?= ($_POST['city'] ?? 'all') === 'all' ? 'selected' : '' ?>>All Cities</option>
                        <?php foreach ($cities as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>" <?= ($_POST['city'] ?? '') === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn" data-loading="Predicting…">Predict Now</button>
        </form>
    </div>

    <?php if ($predictions !== null): ?>
        <div class="accuracy">
            <h2><?= $accuracy ?>%</h2>
            <p>Model Accuracy Based on Historical Data</p>
            <div style="margin-top:15px;display:flex;gap:20px;justify-content:center;font-size:14px;">
                <span>Safe: <?= $safe ?? 0 ?></span>
                <span>Target: <?= $target ?? 0 ?></span>
                <span>Reach: <?= $reach ?? 0 ?></span>
            </div>
        </div>

        <div class="card">
            <h2 style="margin-top:0;">Predicted Results (<?= count($predictions) ?>)</h2>
            <div class="legend">
                <span><span class="dot dot-safe"></span><strong>Safe</strong> — comfortably inside the cutoff</span>
                <span><span class="dot dot-target"></span><strong>Target</strong> — right around the cutoff</span>
                <span><span class="dot dot-reach"></span><strong>Reach</strong> — a stretch above the cutoff</span>
            </div>
            <?php if (empty($predictions)): ?>
                <p>No colleges found matching your criteria.</p>
            <?php else: ?>
                <?php foreach ($predictions as $p): ?>
                    <div class="result-item">
                        <div style="display:flex;justify-content:space-between;align-items:start;">
                            <h3><?= htmlspecialchars(trim(explode(',', $p['institute'], 2)[0])) ?></h3>
                            <div style="text-align:right;">
                                <span class="badge badge-<?= strtolower($p['chance']) ?>"><?= $p['chance'] ?></span>
                                <div style="font-size:12px;color:#718096;margin-top:5px;">Confidence: <?= $p['confidence'] ?>%</div>
                            </div>
                        </div>
                        <div class="result-meta">
                            <span><strong>Branch:</strong> <?= htmlspecialchars($p['branch']) ?></span>
                            <span><strong>Type:</strong> <?= htmlspecialchars($p['inst_type']) ?></span>
                            <span><strong>Quota:</strong> <?= htmlspecialchars($p['quota']) ?></span>
                            <?php if (!empty($p['city'])): ?>
                                <span><strong>City:</strong> <?= htmlspecialchars($p['city']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="result-meta" style="margin-top:10px;padding-top:10px;border-top:1px solid #e2e8f0;">
                            <span><strong>First Rank:</strong> <?= $p['first_rank'] ?> (<?= $p['first_marks'] ?>)</span>
                            <span><strong>Last Rank:</strong> <?= $p['last_rank'] ?> (<?= $p['last_marks'] ?>)</span>
                            <span><strong>Your Margin:</strong> <?= $p['margin'] > 0 ? '+' . $p['margin'] : $p['margin'] ?> ranks</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php elseif ($view === 'browse'): /* ===================== BROWSE MODE ===================== */ ?>

        <?php if ($selectedCollege === '' || $collegeBranches === null): ?>
            <div class="card">
                <h1>Browse Colleges</h1>
                <p class="subtitle">Pick a college to see every branch's cutoff ranks (2024 &amp; 2025) by category</p>

                <input type="text" id="collegeSearch" class="search-box" placeholder="Search by college name or city..." onkeyup="filterColleges()">

                <div class="college-grid" id="collegeGrid">
                    <?php foreach ($collegeList as $name => $info): ?>
                        <a class="college-tile"
                           href="predictor.php?view=browse&amp;college=<?= urlencode($name) ?>"
                           data-search="<?= htmlspecialchars(strtolower($name . ' ' . $info['city'])) ?>">
                            <div class="name"><?= htmlspecialchars($name) ?></div>
                            <div class="meta"><?= htmlspecialchars($info['city'] ?: 'Gujarat') ?> &middot; <?= htmlspecialchars($info['type']) ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <script>
            function filterColleges() {
                var q = document.getElementById('collegeSearch').value.toLowerCase().trim();
                document.querySelectorAll('#collegeGrid .college-tile').forEach(function(tile) {
                    tile.style.display = tile.getAttribute('data-search').indexOf(q) !== -1 ? '' : 'none';
                });
            }
            </script>

        <?php else: ?>
            <div class="card">
                <a href="predictor.php?view=browse" class="back-link">&larr; All colleges</a>
                <h1><?= htmlspecialchars($selectedCollege) ?></h1>
                <p class="subtitle">
                    <?= htmlspecialchars(($collegeMeta['city'] ?? '') ?: 'Gujarat') ?> &middot; <?= htmlspecialchars($collegeMeta['type'] ?? '') ?>
                </p>
                <p class="table-note">Each cell shows the <strong>last admitted rank</strong> (closing cutoff) for that branch &amp; category. &ndash; means no admission recorded that year.</p>

                <?php if (empty($collegeBranches)): ?>
                    <p>No data found for this college.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="cutoff-table">
                            <thead>
                                <tr>
                                    <th class="branch-cell">Branch</th>
                                    <?php foreach ($collegeCategories as $cat): ?>
                                        <th><?= htmlspecialchars($cat) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($collegeBranches as $branch => $byCat): ?>
                                    <tr>
                                        <td class="branch-cell"><?= htmlspecialchars($branch) ?></td>
                                        <?php foreach ($collegeCategories as $cat): ?>
                                            <td>
                                                <?php foreach ($years as $yr): ?>
                                                    <?php $cell = $byCat[$cat][$yr] ?? null; ?>
                                                    <div class="yr <?= $cell ? '' : 'empty' ?>">
                                                        <span class="y">'<?= substr($yr, 2) ?></span>
                                                        <span class="v" title="<?= $cell ? 'First rank: ' . htmlspecialchars($cell['first_rank']) : '' ?>">
                                                            <?= $cell ? number_format((int)$cell['last_rank']) : '&ndash;' ?>
                                                        </span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php else: /* ===================== COMPARE MODE ===================== */ ?>

        <div class="card">
            <h1>Compare Colleges</h1>
            <p class="subtitle">Pick up to 3 colleges and a category to see their branch cutoffs (2024 &amp; 2025) side by side</p>

            <form method="GET">
                <input type="hidden" name="view" value="compare">
                <div class="form-grid">
                    <?php for ($i = 0; $i < 3; $i++): $sel = $compareColleges[$i] ?? ''; ?>
                        <div class="form-group">
                            <label>College <?= $i + 1 ?><?= $i < 2 ? ' *' : ' (optional)' ?></label>
                            <select name="c[]">
                                <option value="">— Select college —</option>
                                <?php foreach ($collegeList as $name => $info): ?>
                                    <option value="<?= htmlspecialchars($name) ?>" <?= $sel === $name ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endfor; ?>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="cat">
                            <?php foreach ($allCategories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>" <?= $compareCat === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn" data-loading="Comparing…">Compare</button>
            </form>
        </div>

        <?php if ($compareData !== null && !empty($compareColleges)): ?>
            <div class="card">
                <h2 style="margin-top:0;">Cutoffs for category: <?= htmlspecialchars($compareCat) ?></h2>
                <p class="table-note">Each cell shows the <strong>last admitted rank</strong> for that branch. &ndash; means the college didn't offer that branch / category that year.</p>

                <?php if (empty($compareBranches)): ?>
                    <p>No cutoff data for the selected colleges in this category.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="cutoff-table">
                            <thead>
                                <tr>
                                    <th class="branch-cell">Branch</th>
                                    <?php foreach ($compareColleges as $name): ?>
                                        <th>
                                            <?= htmlspecialchars($name) ?>
                                            <div style="font-weight:400;font-size:11px;color:#94a3b8;margin-top:3px;"><?= htmlspecialchars($collegeList[$name]['city'] ?: 'Gujarat') ?></div>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($compareBranches as $branch): ?>
                                    <tr>
                                        <td class="branch-cell"><?= htmlspecialchars($branch) ?></td>
                                        <?php foreach ($compareColleges as $name): ?>
                                            <td>
                                                <?php foreach ($years as $yr): ?>
                                                    <?php $cell = $compareData[$name][$branch][$yr] ?? null; ?>
                                                    <div class="yr <?= $cell ? '' : 'empty' ?>">
                                                        <span class="y">'<?= substr($yr, 2) ?></span>
                                                        <span class="v"><?= $cell ? number_format((int)$cell['last_rank']) : '&ndash;' ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<script>
// Show a spinner + loading label on the submit button while the page processes
document.querySelectorAll('form').forEach(function (form) {
    form.addEventListener('submit', function () {
        var btn = form.querySelector('button[type="submit"]');
        if (!btn || btn.dataset.loading === undefined) return;
        if (typeof form.checkValidity === 'function' && !form.checkValidity()) return; // let HTML5 validation block first
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span>' + btn.dataset.loading;
    });
});
</script>

<?php include 'includes/footer.php'; ?>