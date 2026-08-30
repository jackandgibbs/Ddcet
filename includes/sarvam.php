<?php
/**
 * Sarvam AI Translation Helper
 */

function callSarvamTranslate($text, $sourceLang = 'en-IN', $targetLang = 'gu-IN') {
    $text = trim((string) $text);
    if ($text === '') return null;
    
    // Quick heuristic: if it's purely math, numbers, or single alphabets, don't translate
    if (preg_match('/^[\d\s\$\.\+\-\*\/\(\)\=a-zA-Z]+$/', $text)) {
        return null; // Return null to indicate no translation needed
    }

    $apiKey = $_ENV['SARVAM_API_KEY'] ?? 'sk_6opkha4d_oSb9EDG8Hi2xOQLDsvNqYCBZ'; // Fallback to provided key
    $url = 'https://api.sarvam.ai/translate';
    
    // Protect Math equations
    $store = [];
    $masked = preg_replace_callback('/\$\$.*?\$\$|\$[^$]*\$/s', function ($m) use (&$store) {
        $key = '@@' . count($store) . '@@';
        $store[] = $m[0];
        return $key;
    }, $text) ?? $text;

    $data = [
        'input' => $masked,
        'source_language_code' => $sourceLang,
        'target_language_code' => $targetLang,
        'model' => 'mayura:v1',
        'numerals_format' => 'native',
        'mode' => 'formal'
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'api-subscription-key: ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 15 // Fail fast if JIT takes too long
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 400 || !$response) {
        throw new RuntimeException("Sarvam API Error: " . $response);
    }

    $resData = json_decode($response, true);
    $translatedText = $resData['translated_text'] ?? '';
    
    // Restore Math equations
    $restored = preg_replace_callback('/@@\s*(\d+)\s*@@/', function ($m) use ($store) {
        return $store[(int) $m[1]] ?? $m[0];
    }, $translatedText) ?? $translatedText;
    
    return trim($restored);
}
