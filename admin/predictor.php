<?php
require_once '../config.php';
require_once '../includes/supabase_auth.php';
$user = requireAdmin();          // gates on is_admin + 2FA, and gives us $user
$pageTitle = 'Predictor Admin';
include '../includes/header.php';

function supabaseQuery($table, $filters = []) {
    $url = SUPABASE_URL . "/rest/v1/$table?select=*";
    foreach ($filters as $key => $val) $url .= "&" . urlencode($key) . "=" . urlencode($val);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['apikey: ' . SUPABASE_KEY]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true) ?? [];
}

$stats = [
    'total' => count(supabaseQuery('ddcet_admissions')),
    'institutes' => count(array_unique(array_column(supabaseQuery('ddcet_admissions', [], 'institute_name'), 'institute_name'))),
    'branches' => count(array_unique(array_column(supabaseQuery('ddcet_admissions', [], 'branch'), 'branch'))),
];

$recentData = supabaseQuery('ddcet_admissions', ['order' => 'created_at.desc', 'limit' => 10]);
?>

<style>
body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; font-family: 'DM Sans', sans-serif; }
.container { max-width: 1200px; margin: 0 auto; padding: 40px 20px; }
.card { background: white; border-radius: 20px; padding: 30px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); margin-bottom: 30px; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
.stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 15px; text-align: center; }
.stat-card h3 { font-size: 36px; font-weight: 800; margin: 0; }
.stat-card p { margin: 5px 0 0; opacity: 0.9; font-size: 14px; }
.btn { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 12px 24px; border-radius: 10px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; }
.btn:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(102,126,234,0.3); }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
th { font-weight: 600; color: #2d3748; background: #f7fafc; }
</style>

<div class="container">
    <div class="card">
        <h1>🎓 Predictor Admin Dashboard</h1>
        <p style="color:#718096;margin-bottom:30px;">Manage college admission data and predictions</p>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?= $stats['total'] ?></h3>
                <p>Total Records</p>
            </div>
            <div class="stat-card">
                <h3><?= $stats['institutes'] ?></h3>
                <p>Institutes</p>
            </div>
            <div class="stat-card">
                <h3><?= $stats['branches'] ?></h3>
                <p>Branches</p>
            </div>
        </div>
        
        <div style="margin-bottom:20px;">
            <a href="<?= BASE_PATH ?>predictor.php" class="btn">🔍 View Predictor</a>
            <button class="btn" onclick="if(confirm('Import data from college_data.csv?')) location.href='run_import.php'">📥 Import Data</button>
            <a href="<?= BASE_PATH ?>college_data.csv" class="btn" download>📄 Download CSV</a>
        </div>
    </div>
    
    <div class="card">
        <h2 style="margin-top:0;">Recent Admissions Data</h2>
        <div class="table-wrap"><table>
            <thead>
                <tr>
                    <th>Institute</th>
                    <th>Branch</th>
                    <th>Category</th>
                    <th>Quota</th>
                    <th>Last Rank</th>
                    <th>Last Marks</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentData as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['institute_name']) ?></td>
                    <td><?= htmlspecialchars($row['branch']) ?></td>
                    <td><?= htmlspecialchars($row['category']) ?></td>
                    <td><?= htmlspecialchars($row['quota']) ?></td>
                    <td><?= $row['last_rank'] ?></td>
                    <td><?= $row['last_marks'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
