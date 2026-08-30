<?php
/**
 * Just-In-Time (JIT) Translation API
 * Translates a specific question and its options into Gujarati on-demand.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/sarvam.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$questionId = (int)($input['question_id'] ?? 0);

if (!$questionId) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid question ID']);
    exit;
}

try {
    // 1. Fetch Question
    $qData = supabaseRest('questions?id=eq.' . $questionId . '&select=id,question_text,question_text_gu,explanation,explanation_gu');
    if (empty($qData)) {
        throw new Exception("Question not found");
    }
    $q = $qData[0];

    // 2. Translate Question Text & Explanation if needed
    $patch = [];
    $translatedQuestion = $q['question_text_gu'];
    $translatedExp = $q['explanation_gu'];

    if ($q['question_text_gu'] === null) {
        $gu = callSarvamTranslate($q['question_text']);
        if ($gu !== null) {
            $translatedQuestion = $gu;
            $patch['question_text_gu'] = $gu;
        } else {
            $translatedQuestion = $q['question_text']; // Fallback for purely math
            $patch['question_text_gu'] = $q['question_text'];
        }
    }

    if (!empty($q['explanation']) && $q['explanation_gu'] === null) {
        $expGu = callSarvamTranslate($q['explanation']);
        if ($expGu !== null) {
            $translatedExp = $expGu;
            $patch['explanation_gu'] = $expGu;
        }
    }

    if (!empty($patch)) {
        supabaseRest('questions?id=eq.' . $questionId, 'PATCH', $patch);
    }

    // 3. Fetch Options
    $oData = supabaseRest('options?question_id=eq.' . $questionId . '&select=id,option_text,option_text_gu');
    $translatedOptions = [];
    
    foreach ($oData as $opt) {
        $translatedOptText = $opt['option_text_gu'];
        if ($opt['option_text_gu'] === null) {
            $optGu = callSarvamTranslate($opt['option_text']);
            if ($optGu !== null) {
                $translatedOptText = $optGu;
                supabaseRest('options?id=eq.' . (int)$opt['id'], 'PATCH', ['option_text_gu' => $optGu]);
            } else {
                $translatedOptText = $opt['option_text'];
                supabaseRest('options?id=eq.' . (int)$opt['id'], 'PATCH', ['option_text_gu' => $opt['option_text']]);
            }
        }
        $translatedOptions[] = [
            'id' => $opt['id'],
            'text_gu' => $translatedOptText
        ];
    }

    echo json_encode([
        'success' => true,
        'question' => [
            'id' => $questionId,
            'text_gu' => $translatedQuestion,
            'explanation_gu' => $translatedExp,
            'options' => $translatedOptions
        ]
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
