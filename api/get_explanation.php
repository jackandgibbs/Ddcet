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
$questionId = (int)($input['question_id'] ?? 0);
$lang = $input['language'] ?? 'en';

if (!$questionId) {
    echo json_encode(['error' => 'Invalid question ID']);
    exit;
}

// 1. Fetch question and check if explanation already exists
$questionData = supabaseRest('questions?id=eq.' . $questionId . '&select=*');
if (empty($questionData)) {
    echo json_encode(['error' => 'Question not found']);
    exit;
}
$question = $questionData[0];

// Return existing AI explanation if it's already there (and matches the requested language if Gujarati is asked)
if ($lang === 'gu' && !empty($question['explanation_gu']) && str_starts_with($question['explanation_gu'], '[AI]')) {
    echo json_encode(['success' => true, 'explanation' => $question['explanation_gu']]);
    exit;
} elseif ($lang !== 'gu' && !empty($question['explanation']) && str_starts_with($question['explanation'], '[AI]')) {
    echo json_encode(['success' => true, 'explanation' => $question['explanation']]);
    exit;
}

// 2. Fetch options
$optionsData = supabaseRest('options?question_id=eq.' . $questionId . '&order=position.asc&select=*');
$correctOption = 'Unknown';
$optionsText = [];
$labels = ['A', 'B', 'C', 'D'];

foreach ($optionsData as $idx => $opt) {
    $label = $labels[$idx] ?? '?';
    if ($opt['is_correct']) {
        $correctOption = $label;
    }
    $optText = ($lang === 'gu' && !empty($opt['option_text_gu'])) ? $opt['option_text_gu'] : $opt['option_text'];
    $optStr = "- $label: " . $optText;
    if ($opt['is_correct']) $optStr .= " (Correct Answer)";
    $optionsText[] = $optStr;
}

// 3. Build Prompt for Gemini
$sysInstruction = "You are an expert tutor for the Gujarat Diploma-to-Degree Common Entrance Test (DDCET). Your goal is to explain the correct answer to multiple choice questions in a clear, concise, and highly educational step-by-step manner. Use LaTeX for math enclosed in $ (inline) or $$ (block). Do not use \\( or \\). Keep the explanation under 250 words and focus on the conceptual 'why'. Do not just restate the question.";
if ($lang === 'gu') {
    $sysInstruction .= " IMPORTANT: YOU MUST WRITE YOUR ENTIRE RESPONSE IN THE GUJARATI LANGUAGE.";
}

$prompt = "Please explain the solution for the following question.\n\n";
$prompt .= "**Question:**\n" . $question['question_text'] . "\n\n";
if (!empty($question['question_text_gu'])) {
    $prompt .= "**Question (Gujarati):**\n" . $question['question_text_gu'] . "\n\n";
}
$prompt .= "**Options:**\n" . implode("\n", $optionsText) . "\n\n";
$prompt .= "**Task:**\nExplain step-by-step why the correct answer is '$correctOption'.";

// 4. Check AI credits if not Pro
$isPro = isSubscribed('pro');
$credits = 0;
if (!$isPro) {
    $studentData = supabaseRest('students?id=eq.' . $user['id'] . '&select=ai_credits');
    $credits = (int)($studentData[0]['ai_credits'] ?? 0);
    
    if ($credits <= 0) {
        echo json_encode(['error' => 'limit_reached']);
        exit;
    }
}

// 5. Call Gemini
$aiExplanation = callGemini($prompt, $sysInstruction);

if (str_starts_with($aiExplanation, 'Error:')) {
    echo json_encode(['error' => $aiExplanation]);
    exit;
}

// Prefix to indicate AI generation
$aiExplanation = "[AI] **AI Explanation:**\n\n" . $aiExplanation;

// 6. Save back to database
$patchField = ($lang === 'gu') ? 'explanation_gu' : 'explanation';
$update = supabaseRest('questions?id=eq.' . $questionId, 'PATCH', [$patchField => $aiExplanation]);
if ($update === null) {
    appLog('error', 'Failed to save AI explanation to DB', ['question_id' => $questionId]);
}

// 7. Decrement credit if not Pro
if (!$isPro) {
    supabaseRest('students?id=eq.' . $user['id'], 'PATCH', ['ai_credits' => $credits - 1]);
}

echo json_encode(['success' => true, 'explanation' => $aiExplanation]);
