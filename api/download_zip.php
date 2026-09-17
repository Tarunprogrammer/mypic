<?php
/**
 * ZIP Downloader for Batch Matched Photos
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';

$idsParam = $_GET['ids'] ?? ($_POST['ids'] ?? '');
if (empty($idsParam)) {
    die("No photos selected for download.");
}

if (is_string($idsParam)) {
    $ids = explode(',', $idsParam);
} elseif (is_array($idsParam)) {
    $ids = $idsParam;
} else {
    die("Invalid photo IDs format.");
}

$photoIds = array_filter(array_map('intval', $ids));
if (empty($photoIds)) {
    die("No valid photos provided.");
}

if (!class_exists('ZipArchive')) {
    die("PHP ZipArchive extension is not enabled on this server.");
}

$db = Database::getConnection();
$placeholders = str_repeat('?,', count($photoIds) - 1) . '?';
$stmt = $db->prepare("SELECT * FROM photos WHERE id IN ($placeholders)");
$stmt->execute($photoIds);
$photos = $stmt->fetchAll();

if (empty($photos)) {
    die("Selected photos were not found.");
}

$zipFileName = 'MYPIC_Photos_' . date('Ymd_His') . '.zip';
$tempZipPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $zipFileName;

$zip = new ZipArchive();
if ($zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("Cannot create temporary ZIP archive.");
}

$wmRel = get_setting('watermark_image', '');
$wmFullPath = !empty($wmRel) ? ROOT_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($wmRel, '/\\')) : '';
$hasWatermark = !empty($wmRel) && file_exists($wmFullPath);

$addedFiles = [];
foreach ($photos as $photo) {
    $filePath = ROOT_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $photo['file_path']);
    if (file_exists($filePath)) {
        $entryName = !empty($photo['original_name']) ? $photo['original_name'] : ('photo_' . $photo['id'] . '.jpg');
        
        // If watermark is active, ensure extension is .jpg
        if ($hasWatermark) {
            $base = pathinfo($entryName, PATHINFO_FILENAME);
            $entryName = $base . '.jpg';
        }

        // Ensure unique name inside zip
        if (isset($addedFiles[$entryName])) {
            $addedFiles[$entryName]++;
            $ext = pathinfo($entryName, PATHINFO_EXTENSION);
            $base = pathinfo($entryName, PATHINFO_FILENAME);
            $entryName = $base . '_' . $addedFiles[$entryName] . '.' . $ext;
        } else {
            $addedFiles[$entryName] = 1;
        }

        if ($hasWatermark) {
            $watermarkedBytes = apply_watermark($filePath);
            $zip->addFromString($entryName, $watermarkedBytes);
        } else {
            $zip->addFile($filePath, $entryName);
        }
    }
}

$zip->close();

if (!file_exists($tempZipPath)) {
    die("Error generating ZIP archive.");
}

// Clear any buffered output
if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipFileName . '"');
header('Content-Length: ' . filesize($tempZipPath));
header('Pragma: no-cache');
header('Expires: 0');

readfile($tempZipPath);
@unlink($tempZipPath);
exit;
