<?php
/**
 * Photo Upload & Face Embeddings Ingestion API
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
require_admin_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'POST method required'], 405);
}

if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['photo']['error'] ?? 'NO_FILE';
    json_response(['success' => false, 'message' => 'Upload failed or no file uploaded (Code: ' . $errCode . ')'], 400);
}

$file = $_FILES['photo'];
$eventId = !empty($_POST['event_id']) ? (int)$_POST['event_id'] : null;
$facesJson = $_POST['faces'] ?? '[]';
$faces = json_decode($facesJson, true) ?? [];

// Validate file size
if ($file['size'] > MAX_UPLOAD_SIZE) {
    json_response(['success' => false, 'message' => 'File exceeds max allowed size (' . (MAX_UPLOAD_SIZE / (1024*1024)) . 'MB)'], 400);
}

// Validate file extension and MIME
$origName = basename($file['name']);
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

if (!in_array($ext, ALLOWED_EXTENSIONS)) {
    json_response(['success' => false, 'message' => 'Invalid image format. Allowed: ' . implode(', ', ALLOWED_EXTENSIONS)], 400);
}

// Verify actual image
$imgInfo = @getimagesize($file['tmp_name']);
if (!$imgInfo) {
    json_response(['success' => false, 'message' => 'Corrupt or invalid image file'], 400);
}

$width = $imgInfo[0];
$height = $imgInfo[1];

$db = Database::getConnection();

// Check for duplicate photo (same original filename and exact file size)
$checkStmt = $db->prepare("SELECT id, filename, file_path, thumbnail_path, face_count FROM photos WHERE original_name = ? AND file_size = ? LIMIT 1");
$checkStmt->execute([$origName, $file['size']]);
$existingPhoto = $checkStmt->fetch();

if ($existingPhoto) {
    $existingFilePath = ROOT_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $existingPhoto['file_path']);
    if (file_exists($existingFilePath)) {
        json_response([
            'success' => true,
            'skipped' => true,
            'message' => 'Skipped: Photo already uploaded and indexed.',
            'data' => [
                'id' => (int)$existingPhoto['id'],
                'filename' => $existingPhoto['filename'],
                'file_path' => BASE_URL . '/' . $existingPhoto['file_path'],
                'thumbnail_path' => BASE_URL . '/' . $existingPhoto['thumbnail_path'],
                'face_count' => (int)$existingPhoto['face_count'],
                'width' => $width,
                'height' => $height
            ]
        ]);
    }
}

// Generate unique filename
$uniqueId = bin2hex(random_bytes(8));
$newFilename = 'img_' . date('Ymd_His') . '_' . $uniqueId . '.' . $ext;
$thumbFilename = 'thumb_' . date('Ymd_His') . '_' . $uniqueId . '.jpg';

// Ensure directories exist
if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0777, true);
if (!is_dir(PHOTO_DIR)) @mkdir(PHOTO_DIR, 0777, true);
if (!is_dir(THUMB_DIR)) @mkdir(THUMB_DIR, 0777, true);

$destPhotoPath = PHOTO_DIR . DIRECTORY_SEPARATOR . $newFilename;
$destThumbPath = THUMB_DIR . DIRECTORY_SEPARATOR . $thumbFilename;

if (!move_uploaded_file($file['tmp_name'], $destPhotoPath)) {
    json_response(['success' => false, 'message' => 'Failed to move uploaded photo to storage directory'], 500);
}

// Generate thumbnail
create_thumbnail($destPhotoPath, $destThumbPath, 450);

try {
    $db->beginTransaction();

    // 1. Insert into photos table
    $relPhotoPath = 'uploads/photos/' . $newFilename;
    $relThumbPath = 'uploads/thumbnails/' . $thumbFilename;
    $faceCount = is_array($faces) ? count($faces) : 0;

    $stmt = $db->prepare("
        INSERT INTO photos (event_id, filename, original_name, file_path, thumbnail_path, file_size, width, height, face_count)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $eventId,
        $newFilename,
        $origName,
        $relPhotoPath,
        $relThumbPath,
        $file['size'],
        $width,
        $height,
        $faceCount
    ]);
    $photoId = (int)$db->lastInsertId();

    // 2. Insert face embeddings into photo_faces table
    if ($faceCount > 0 && is_array($faces)) {
        $faceStmt = $db->prepare("
            INSERT INTO photo_faces (photo_id, face_index, box_x, box_y, box_w, box_h, score, descriptor)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($faces as $idx => $face) {
            $box = $face['box'] ?? ['x' => 0, 'y' => 0, 'width' => 0, 'height' => 0];
            $score = isset($face['score']) ? (float)$face['score'] : 0.0;
            $descriptor = isset($face['descriptor']) ? json_encode($face['descriptor']) : '[]';

            $faceStmt->execute([
                $photoId,
                $idx,
                (float)($box['x'] ?? 0),
                (float)($box['y'] ?? 0),
                (float)($box['width'] ?? 0),
                (float)($box['height'] ?? 0),
                $score,
                $descriptor
            ]);
        }
    }

    // 3. Update event photo count
    if ($eventId) {
        $updateEvent = $db->prepare("UPDATE events SET photo_count = (SELECT COUNT(*) FROM photos WHERE event_id = ?) WHERE id = ?");
        $updateEvent->execute([$eventId, $eventId]);
    }

    $db->commit();

    json_response([
        'success' => true,
        'message' => 'Photo uploaded and indexed successfully',
        'data' => [
            'id' => $photoId,
            'filename' => $newFilename,
            'file_path' => BASE_URL . '/' . $relPhotoPath,
            'thumbnail_path' => BASE_URL . '/' . $relThumbPath,
            'face_count' => $faceCount,
            'width' => $width,
            'height' => $height
        ]
    ]);

} catch (Exception $e) {
    $db->rollBack();
    @unlink($destPhotoPath);
    @unlink($destThumbPath);
    json_response(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
