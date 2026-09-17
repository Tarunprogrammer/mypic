<?php
/**
 * Delete Photo API (Single & Batch Deletion - Admin Only)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
require_admin_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    json_response(['success' => false, 'message' => 'Invalid method'], 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// Support both 'ids' (array/string) and single 'id'
$ids = [];
if (!empty($input['ids']) || !empty($_GET['ids'])) {
    $rawIds = $input['ids'] ?? $_GET['ids'];
    if (is_string($rawIds)) {
        $ids = explode(',', $rawIds);
    } elseif (is_array($rawIds)) {
        $ids = $rawIds;
    }
} elseif (!empty($input['id']) || !empty($_GET['id'])) {
    $ids = [$input['id'] ?? $_GET['id']];
}

$photoIds = array_filter(array_map('intval', $ids));

if (empty($photoIds)) {
    json_response(['success' => false, 'message' => 'Photo ID(s) are required'], 400);
}

$db = Database::getConnection();

try {
    $placeholders = str_repeat('?,', count($photoIds) - 1) . '?';
    $stmt = $db->prepare("SELECT * FROM photos WHERE id IN ($placeholders)");
    $stmt->execute($photoIds);
    $photos = $stmt->fetchAll();

    if (empty($photos)) {
        json_response(['success' => false, 'message' => 'No photos found to delete'], 404);
    }

    // Delete physical files
    foreach ($photos as $photo) {
        $filePath = ROOT_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $photo['file_path']);
        $thumbPath = ROOT_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $photo['thumbnail_path']);

        if (file_exists($filePath)) {
            @unlink($filePath);
        }
        if (file_exists($thumbPath)) {
            @unlink($thumbPath);
        }
    }

    // Delete database records (CASCADE will delete linked photo_faces)
    $delStmt = $db->prepare("DELETE FROM photos WHERE id IN ($placeholders)");
    $delStmt->execute($photoIds);

    $deletedCount = count($photos);

    json_response([
        'success' => true, 
        'deleted_count' => $deletedCount,
        'message' => "Successfully deleted {$deletedCount} photo(s) and their face indexes"
    ]);

} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Error deleting photos: ' . $e->getMessage()], 500);
}
