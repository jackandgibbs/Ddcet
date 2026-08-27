<?php
/**
 * Wrapper for the Google Gemini API.
 */

function callGemini($prompt, string $systemInstruction = '') {
    if (empty(GEMINI_API_KEY)) {
        return "Error: Gemini API key is not configured.";
    }

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent?key=" . GEMINI_API_KEY;

    if (is_array($prompt)) {
        $contents = $prompt;
    } else {
        $contents = [
            [
                "role" => "user",
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ];
    }

    $payload = [
        "contents" => $contents
    ];

    if (!empty($systemInstruction)) {
        $payload["systemInstruction"] = [
            "parts" => [
                ["text" => $systemInstruction]
            ]
        ];
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 45);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Fix for local XAMPP SSL issues
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode >= 400 || $response === false) {
        appLog('error', 'Gemini API failed', ['http' => $httpCode, 'response' => $response, 'curl_error' => $curlError]);
        return "Error: Failed connecting to AI service. " . $curlError;
    }

    $data = json_decode($response, true);
    if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        return trim($data['candidates'][0]['content']['parts'][0]['text']);
    }

    return "Error: Unable to parse AI response.";
}
