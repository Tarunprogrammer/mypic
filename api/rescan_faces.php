<?php
/**
 * Face Re-Scan & Embedding Update API
 * Allows admin to update/re-index extracted faces for any photo
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
require_admin_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'POST method required'], 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$photoId = isset($input['photo_id']) ? (int)$input['photo_id'] : 0;
$faces = isset($input['faces']) ? $input['faces'] : [];

if (is_string($faces)) {
    $faces = json_decode($faces, true) ?? [];
}

if ($photoId <= 0) {
    json_response(['success' => false, 'message' => 'Valid photo_id required'], 400);
}

$db = Database::getConnection();

// Verify photo exists
$stmt = $db->prepare("SELECT id, original_name FROM photos WHERE id = ? LIMIT 1");
$stmt->execute([$photoId]);
$photo = $stmt->fetch();

if (!$photo) {
    json_response(['success' => false, 'message' => 'Photo not found'], 404);
}

try {
    $db->beginTransaction();

    // 1. Delete existing face embeddings for this photo
    $delStmt = $db->prepare("DELETE FROM photo_faces WHERE photo_id = ?");
    $delStmt->execute([$photoId]);

    // 2. Insert new face embeddings
    $faceCount = count($faces);
    if ($faceCount > 0) {
        $insertStmt = $db->prepare("
            INSERT INTO photo_faces (photo_id, face_index, box_x, box_y, box_w, box_h, score, descriptor)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($faces as $idx => $face) {
            $box = $face['box'] ?? ['x' => 0, 'y' => 0, 'width' => 0, 'height' => 0];
            $score = isset($face['score']) ? (float)$face['score'] : 0.0;
            $descriptor = isset($face['descriptor']) ? json_encode($face['descriptor']) : '[]';

            $insertStmt->execute([
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

    // 3. Update face count on the photo
    $updateStmt = $db->prepare("UPDATE photos SET face_count = ? WHERE id = ?");
    $updateStmt->execute([$faceCount, $photoId]);

    $db->commit();

    json_response([
        'success' => true,
        'message' => "Successfully indexed $faceCount face(s) for photo #$photoId",
        'photo_id' => $photoId,
        'face_count' => $faceCount
    ]);

} catch (Exception $e) {
    $db->rollBack();
    json_response([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ], 500);
}
