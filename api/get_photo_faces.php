<?php
/**
 * Get Extracted Faces for Photo
 * Returns coordinates and scores of all indexed faces for inspector tool
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
require_admin_login();

$photoId = isset($_GET['photo_id']) ? (int)$_GET['photo_id'] : 0;

if ($photoId <= 0) {
    json_response(['success' => false, 'message' => 'Valid photo_id required'], 400);
}

$db = Database::getConnection();

// Fetch photo
$stmt = $db->prepare("SELECT id, original_name, file_path, thumbnail_path, width, height, face_count, created_at FROM photos WHERE id = ? LIMIT 1");
$stmt->execute([$photoId]);
$photo = $stmt->fetch();

if (!$photo) {
    json_response(['success' => false, 'message' => 'Photo not found'], 404);
}

// Fetch indexed faces
$faceStmt = $db->prepare("SELECT id, face_index, box_x, box_y, box_w, box_h, score FROM photo_faces WHERE photo_id = ? ORDER BY face_index ASC");
$faceStmt->execute([$photoId]);
$faces = $faceStmt->fetchAll();

json_response([
    'success' => true,
    'photo' => [
        'id' => (int)$photo['id'],
        'original_name' => $photo['original_name'],
        'photo_url' => BASE_URL . '/' . $photo['file_path'],
        'thumbnail_url' => BASE_URL . '/' . $photo['thumbnail_path'],
        'width' => (int)$photo['width'],
        'height' => (int)$photo['height'],
        'face_count' => (int)$photo['face_count'],
        'created_at' => $photo['created_at']
    ],
    'faces' => array_map(function($f) {
        return [
            'id' => (int)$f['id'],
            'face_index' => (int)$f['face_index'],
            'box' => [
                'x' => (float)$f['box_x'],
                'y' => (float)$f['box_y'],
                'width' => (float)$f['box_w'],
                'height' => (float)$f['box_h']
            ],
            'score' => round((float)$f['score'], 3)
        ];
    }, $faces)
]);
