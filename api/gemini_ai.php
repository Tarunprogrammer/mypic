<?php
/**
 * Gemini AI Vision & Face Intelligence API
 * Powered by Google Gemini API
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$db = Database::getConnection();

// Fetch Gemini API Key from settings or fallback to default
$apiKey = get_setting('gemini_api_key', 'AQ.Ab8RN6JxLJvDNhOdzs-VQgsAPQCS3iHuiu8MqKqw6Gi4MjyvHg');

/**
 * Helper to call Gemini REST API
 */
function call_gemini_api($prompt, $imageBase64 = null, $mimeType = 'image/jpeg', $apiKey = '') {
    if (empty($apiKey)) {
        return ['success' => false, 'message' => 'Gemini API Key is not configured.'];
    }

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . urlencode($apiKey);

    $parts = [];
    $parts[] = ['text' => $prompt];

    if ($imageBase64) {
        $parts[] = [
            'inline_data' => [
                'mime_type' => $mimeType,
                'data' => $imageBase64
            ]
        ];
    }

    $payload = [
        'contents' => [
            [
                'parts' => $parts
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.4,
            'maxOutputTokens' => 800
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['success' => false, 'message' => 'cURL error: ' . $curlErr];
    }

    $resData = json_decode($response, true);
    if ($httpCode !== 200) {
        $errorMsg = $resData['error']['message'] ?? ('HTTP Error ' . $httpCode . ': ' . substr($response, 0, 200));
        return ['success' => false, 'message' => $errorMsg, 'http_code' => $httpCode];
    }

    $generatedText = $resData['candidates'][0]['content']['parts'][0]['text'] ?? '';
    return ['success' => true, 'text' => $generatedText, 'raw' => $resData];
}

switch ($action) {
    case 'test':
        require_admin_login();
        $testKey = $_POST['api_key'] ?? $apiKey;
        $testResult = call_gemini_api('Respond with "Gemini AI connection successful!" in one sentence.', null, 'image/jpeg', $testKey);
        
        if ($testResult['success']) {
            json_response(['success' => true, 'message' => $testResult['text']]);
        } else {
            json_response(['success' => false, 'message' => $testResult['message']], 400);
        }
        break;

    case 'analyze_photo':
        require_admin_login();
        $photoId = (int)($_POST['photo_id'] ?? ($_GET['photo_id'] ?? 0));
        if (!$photoId) {
            json_response(['success' => false, 'message' => 'Photo ID is required'], 400);
        }

        $stmt = $db->prepare("SELECT * FROM photos WHERE id = ? LIMIT 1");
        $stmt->execute([$photoId]);
        $photo = $stmt->fetch();

        if (!$photo) {
            json_response(['success' => false, 'message' => 'Photo not found'], 404);
        }

        $filePath = ROOT_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $photo['file_path']);
        if (!file_exists($filePath)) {
            json_response(['success' => false, 'message' => 'Photo file does not exist on disk'], 404);
        }

        $imageData = base64_encode(file_get_contents($filePath));
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mime = ($ext === 'png') ? 'image/png' : (($ext === 'webp') ? 'image/webp' : 'image/jpeg');

        $prompt = "Analyze this event photo with a focus on face analysis and scene detection. Provide a structured response in JSON format with the following keys:
- people_count: (number of detected people)
- facial_features: (brief description of visible faces, hair, glasses, expressions)
- emotions: (e.g. smiling, neutral, happy)
- scene_description: (one sentence describing setting or event)
- tags: (array of 4-6 relevant keywords e.g. ['smiling', 'blue shirt', 'indoor', 'portrait'])
Only return valid JSON.";

        $aiResult = call_gemini_api($prompt, $imageData, $mime, $apiKey);

        if ($aiResult['success']) {
            $rawText = $aiResult['text'];
            // Strip markdown code fences if present
            $cleanJson = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($rawText));
            $parsed = json_decode($cleanJson, true);

            json_response([
                'success' => true,
                'photo_id' => $photoId,
                'analysis' => $parsed ?? ['raw_text' => $rawText]
            ]);
        } else {
            json_response(['success' => false, 'message' => $aiResult['message']], 500);
        }
        break;

    case 'smart_search':
        $query = trim($_POST['query'] ?? ($_GET['query'] ?? ''));
        if (empty($query)) {
            json_response(['success' => false, 'message' => 'Search query is required'], 400);
        }

        $photos = $db->query("SELECT id, original_name, file_path, thumbnail_path, face_count FROM photos ORDER BY id DESC LIMIT 50")->fetchAll();

        json_response([
            'success' => true,
            'message' => 'Smart search processed',
            'query' => $query,
            'photos' => $photos
        ]);
        break;

    default:
        json_response(['success' => false, 'message' => 'Invalid action specified'], 400);
}
