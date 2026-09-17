<?php
/**
 * Single Photo Downloader with Dynamic Watermark Support
 * Streams photo with Content-Disposition: attachment and applies admin watermark if configured.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';

$photoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($photoId <= 0) {
    http_response_code(400);
    die("Invalid photo ID requested.");
}

$db = Database::getConnection();
$stmt = $db->prepare("SELECT * FROM photos WHERE id = ? LIMIT 1");
$stmt->execute([$photoId]);
$photo = $stmt->fetch();

if (!$photo) {
    http_response_code(404);
    die("Requested photo not found.");
}

$filePath = ROOT_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $photo['file_path']);
if (!file_exists($filePath)) {
    http_response_code(404);
    die("Photo file not found on disk.");
}

// Clean filename for Content-Disposition header
$originalName = !empty($photo['original_name']) ? $photo['original_name'] : 'photo_' . $photo['id'] . '.jpg';
$cleanName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $originalName);
if (!preg_match('/\.(jpg|jpeg|png|webp)$/i', $cleanName)) {
    $cleanName .= '.jpg';
}

// Clear any buffered output before streaming binary
if (ob_get_level()) {
    ob_end_clean();
}

$wmRel = get_setting('watermark_image', '');
$wmFullPath = !empty($wmRel) ? ROOT_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($wmRel, '/\\')) : '';

if (!empty($wmRel) && file_exists($wmFullPath)) {
    // Watermark active: process through GD with adaptive quality up to 2MB and stream JPEG
    $imageData = apply_watermark($filePath);
    
    // Ensure filename ends in .jpg for converted JPEG stream
    $baseName = pathinfo($cleanName, PATHINFO_FILENAME);
    $dlName = $baseName . '.jpg';

    header('Content-Type: image/jpeg');
    header('Content-Disposition: attachment; filename="' . $dlName . '"');
    header('Content-Length: ' . strlen($imageData));
    header('Cache-Control: private, no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo $imageData;
    exit;
} else {
    // No watermark: stream original file directly
    $mime = function_exists('mime_content_type') ? (@mime_content_type($filePath) ?: 'image/jpeg') : 'image/jpeg';

    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $cleanName . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: private, no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    readfile($filePath);
    exit;
}
