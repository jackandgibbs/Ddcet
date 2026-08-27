<?php
require_once '../config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

requireAuth();
requireCsrf();

$data = json_decode(file_get_contents('php://input'), true);
$rank = (int)($data['rank'] ?? 0);
$category = $data['category'] ?? '';
$branch = $data['branch'] ?? 'all';
$institute = $data['institute'] ?? 'all';

if (!$rank || !$category) {
    http_response_code(400);
    echo json_encode(['error' => 'Rank and category required']);
    exit;
}

$filters = ["category=eq." . urlencode($category)];
if ($branch !== 'all') $filters[] = "branch=eq." . urlencode($branch);
if ($institute !== 'all') $filters[] = "institute_name=eq." . urlencode($institute);

$url = SUPABASE_URL . "/rest/v1/ddcet_admissions?select=*&" . implode('&', $filters);
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['apikey: ' . SUPABASE_KEY]);
$allData = json_decode(curl_exec($ch), true) ?? [];
curl_close($ch);

$predictions = [];
$total = count($allData);
$high = $medium = $low = 0;

foreach ($allData as $row) {
    $margin = $row['last_rank'] - $rank;
    $percentage = $row['last_rank'] > 0 ? (($rank / $row['last_rank']) * 100) : 0;
    
    if ($rank <= $row['last_rank']) {
        $chance = 'High';
        $confidence = min(100, 70 + ($margin / $row['last_rank']) * 30);
        $high++;
    } elseif ($rank <= $row['last_rank'] + 1000) {
        $chance = 'Medium';
        $confidence = max(30, 70 - (($rank - $row['last_rank']) / 1000) * 40);
        $medium++;
    } else {
        $chance = 'Low';
        $confidence = max(5, 30 - (($rank - $row['last_rank']) / 2000) * 25);
        $low++;
    }
    
    $predictions[] = [
        'institute' => $row['institute_name'],
        'branch' => $row['branch'],
        'quota' => $row['quota'],
        'inst_type' => $row['inst_type'],
        'first_rank' => $row['first_rank'],
        'last_rank' => $row['last_rank'],
        'first_marks' => $row['first_marks'],
        'last_marks' => $row['last_marks'],
        'chance' => $chance,
        'confidence' => round($confidence, 1),
        'margin' => $margin
    ];
}

usort($predictions, fn($a, $b) => $b['confidence'] <=> $a['confidence']);

$accuracy = $total > 0 ? round((($high * 1.0 + $medium * 0.6 + $low * 0.2) / $total) * 100, 1) : 0;

echo json_encode([
    'success' => true,
    'predictions' => $predictions,
    'accuracy' => $accuracy,
    'stats' => [
        'total' => $total,
        'high' => $high,
        'medium' => $medium,
        'low' => $low
    ]
]);