<?php
/**
 * Advanced Face Recognition Search & Matching Engine
 * Supports multi-descriptor matching, variable precision thresholds, and distance re-ranking
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'POST method required'], 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// Support both 'descriptors' (array of vectors) and 'descriptor' (single vector)
$probeVectors = [];

if (!empty($input['descriptors']) && is_array($input['descriptors'])) {
    foreach ($input['descriptors'] as $v) {
        if (is_string($v)) $v = json_decode($v, true);
        if (is_array($v) && count($v) === 128) {
            $probeVectors[] = $v;
        }
    }
} elseif (!empty($input['descriptor'])) {
    $v = $input['descriptor'];
    if (is_string($v)) $v = json_decode($v, true);
    if (is_array($v) && count($v) === 128) {
        $probeVectors[] = $v;
    }
}

if (empty($probeVectors)) {
    json_response([
        'success' => false,
        'message' => 'Valid 128-dimensional face embedding descriptor(s) required.'
    ], 400);
}

$startTime = microtime(true);
$eventId = !empty($input['event_id']) ? (int)$input['event_id'] : null;

// Get configured threshold or strict 0.50 default
$db = Database::getConnection();
$configuredThreshold = (float)get_setting('match_threshold', 0.50);
$threshold = isset($input['threshold']) && is_numeric($input['threshold']) 
    ? (float)$input['threshold'] 
    : $configuredThreshold;

// Clamp threshold to high-precision bounds (0.35 = ultra strict, 0.52 = max allowed boundary)
$threshold = max(0.35, min(0.52, $threshold));

try {
    // Fetch all faces and photo info
    $query = "
        SELECT 
            pf.id AS face_id,
            pf.photo_id,
            pf.box_x,
            pf.box_y,
            pf.box_w,
            pf.box_h,
            pf.descriptor,
            p.filename,
            p.original_name,
            p.file_path,
            p.thumbnail_path,
            p.width,
            p.height,
            p.created_at
        FROM photo_faces pf
        JOIN photos p ON pf.photo_id = p.id
    ";

    $params = [];
    if ($eventId) {
        $query .= " WHERE p.event_id = ?";
        $params[] = $eventId;
    }

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $allFaces = $stmt->fetchAll();

    $matchedPhotos = [];
    $totalFacesScanned = count($allFaces);

    foreach ($allFaces as $row) {
        $dbDescriptor = json_decode($row['descriptor'], true);
        if (!is_array($dbDescriptor) || count($dbDescriptor) !== 128) {
            continue;
        }

        // Calculate minimum distance across all probe vectors (multi-angle matching)
        $minDistance = 999.0;
        foreach ($probeVectors as $probe) {
            $dist = euclidean_distance($probe, $dbDescriptor);
            if ($dist < $minDistance) {
                $minDistance = $dist;
            }
        }

        $confidence = distance_to_confidence($minDistance, $threshold);

        // Fetch accurate photos strictly matching within verified distance threshold
        if ($minDistance <= $threshold && $confidence >= 65.0) {
            $photoId = $row['photo_id'];

            // Bounding box relative to image
            $box = [
                'x' => (float)$row['box_x'],
                'y' => (float)$row['box_y'],
                'width' => (float)$row['box_w'],
                'height' => (float)$row['box_h']
            ];

            // If photo already in matches, keep the best (lowest distance / highest confidence)
            if (!isset($matchedPhotos[$photoId]) || $minDistance < $matchedPhotos[$photoId]['distance']) {
                $matchedPhotos[$photoId] = [
                    'id' => $photoId,
                    'filename' => $row['filename'],
                    'original_name' => $row['original_name'],
                    'photo_url' => BASE_URL . '/' . $row['file_path'],
                    'thumbnail_url' => BASE_URL . '/' . $row['thumbnail_path'],
                    'download_url' => BASE_URL . '/api/download_photo.php?id=' . $photoId,
                    'width' => (int)$row['width'],
                    'height' => (int)$row['height'],
                    'created_at' => $row['created_at'],
                    'distance' => round($minDistance, 4),
                    'confidence' => $confidence,
                    'match_percentage' => $confidence,
                    'matched_box' => $box
                ];
            }
        }
    }

    // Sort matching photos by highest confidence (lowest distance)
    $results = array_values($matchedPhotos);
    usort($results, function($a, $b) {
        return $a['distance'] <=> $b['distance'];
    });

    $durationMs = round((microtime(true) - $startTime) * 1000, 2);

    json_response([
        'success' => true,
        'matches_count' => count($results),
        'total_faces_scanned' => $totalFacesScanned,
        'threshold_used' => $threshold,
        'probe_angles_count' => count($probeVectors),
        'search_time_ms' => $durationMs,
        'photos' => $results
    ]);

} catch (Exception $e) {
    json_response([
        'success' => false,
        'message' => 'Error during face search: ' . $e->getMessage()
    ], 500);
}
