<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/gemini.php';
$user = requireAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$messages = $input['messages'] ?? [];

if (empty($messages)) {
    echo json_encode(['error' => 'Empty chat history']);
    exit;
}

// Convert frontend messages format to Gemini format
// Gemini requires: [ ["role" => "user"|"model", "parts" => [["text" => "..."]]] ]
$geminiHistory = [];
foreach ($messages as $msg) {
    if (empty($msg['text'])) continue;
    $role = ($msg['role'] === 'user') ? 'user' : 'model';
    $geminiHistory[] = [
        "role" => $role,
        "parts" => [
            ["text" => $msg['text']]
        ]
    ];
}

$sysInstruction = "You are a friendly, encouraging, and highly competent AI study assistant for students preparing for the Gujarat Diploma-to-Degree Common Entrance Test (DDCET).
Your goal is to help them with their doubts, explain concepts clearly, and provide study strategies.
Be concise. Format your answers beautifully using Markdown. Use LaTeX for math enclosed in $ (inline) or $$ (block).
If they ask about the DDCET syllabus, remind them it consists of BE-01 (Basics of Science & Engineering) and BE-02 (Aptitude Test: Maths & English).";

$aiResponse = callGemini($geminiHistory, $sysInstruction);

if (str_starts_with($aiResponse, 'Error:')) {
    echo json_encode(['error' => $aiResponse]);
    exit;
}

echo json_encode(['success' => true, 'text' => $aiResponse]);
