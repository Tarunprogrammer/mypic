<?php
/**
 * Global Configuration & Helper Functions
 * Face Recognition Photo Retrieval System
 */

// Prevent direct script execution if accessed via command line or include guards
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Credentials (InfinityFree Production DB)
define('DB_HOST', 'sql213.infinityfree.com');
define('DB_PORT', '3306');
define('DB_NAME', 'if0_41878641_mypic_db');
define('DB_USER', 'if0_41878641');
define('DB_PASS', 'q69AIGiRBD');
define('DB_CHARSET', 'utf8mb4');

// Base Paths
define('ROOT_DIR', dirname(__DIR__));
define('UPLOAD_DIR', ROOT_DIR . DIRECTORY_SEPARATOR . 'uploads');
define('PHOTO_DIR', UPLOAD_DIR . DIRECTORY_SEPARATOR . 'photos');
define('THUMB_DIR', UPLOAD_DIR . DIRECTORY_SEPARATOR . 'thumbnails');
define('FACE_DIR', UPLOAD_DIR . DIRECTORY_SEPARATOR . 'faces');
define('WATERMARK_DIR', UPLOAD_DIR . DIRECTORY_SEPARATOR . 'watermarks');
define('MODEL_DIR', ROOT_DIR . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'models');

// Base URL Auto-detection (Supports root domain like eppe.gt.tc and subfolder like localhost/mypic)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$scriptDir = dirname($scriptName);
if ($scriptDir === '/' || $scriptDir === '\\' || $scriptDir === '.') {
    $scriptDir = '';
}
$basePath = preg_replace('#/(api|admin|config)(/.*)?$#i', '', $scriptDir);
if ($basePath === '/' || $basePath === '\\' || $basePath === '.') {
    $basePath = '';
}
define('BASE_URL', rtrim($protocol . $host . $basePath, '/'));

// Upload limits
define('MAX_UPLOAD_SIZE', 25 * 1024 * 1024); // 25MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp']);

// Gemini AI API Key
define('DEFAULT_GEMINI_API_KEY', 'AQ.Ab8RN6JxLJvDNhOdzs-VQgsAPQCS3iHuiu8MqKqw6Gi4MjyvHg');

/**
 * Return JSON response and exit
 */
function json_response($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/**
 * Sanitize string output
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Check if admin is logged in
 */
function is_admin_logged_in() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true && !empty($_SESSION['admin_id']);
}

/**
 * Require admin authentication
 */
function require_admin_login() {
    if (!is_admin_logged_in()) {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' || strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
            json_response(['success' => false, 'message' => 'Unauthorized. Please log in as admin.'], 401);
        } else {
            header('Location: ' . BASE_URL . '/admin/login.php');
            exit;
        }
    }
}

/**
 * Calculate Euclidean distance between two 128-dimensional float arrays
 * Distance <= 0.6 is typically considered a match with face-api.js / dlib models
 */
function euclidean_distance($vec1, $vec2) {
    $count = count($vec1);
    if ($count !== count($vec2) || $count === 0) {
        return 999.0;
    }
    $sum = 0.0;
    for ($i = 0; $i < $count; $i++) {
        $diff = $vec1[$i] - $vec2[$i];
        $sum += $diff * $diff;
    }
    return sqrt($sum);
}

/**
 * Convert Euclidean distance (0.0 to 1.0) into calibrated match confidence percentage
 * Standard 128D ResNet / dlib embeddings:
 * Distance <= 0.50 represents the verified identity boundary.
 * Distance 0.00 - 0.32 -> 92% - 100% Match (Exact match)
 * Distance 0.32 - 0.42 -> 80% - 91% Match (High confidence)
 * Distance 0.42 - 0.50 -> 65% - 79% Match (Moderate confidence)
 * Distance > 0.50 -> 0% Match (Different person / Reject)
 */
function distance_to_confidence($distance, $threshold = 0.50) {
    if ($distance <= 0) return 100.0;
    if ($distance > $threshold) return 0.0;
    
    // Linear interpolation between 0 (100%) and threshold (65%)
    $confidence = 100.0 - ($distance / $threshold) * 35.0;
    return max(65.0, min(100.0, round($confidence, 1)));
}

/**
 * Generate Thumbnail for uploaded image using GD
 */
function create_thumbnail($sourcePath, $targetPath, $maxDim = 400) {
    if (!file_exists($sourcePath)) return false;
    
    // If GD is not available, safely fallback to copying the file
    if (!function_exists('imagecreatetruecolor') || !function_exists('getimagesize')) {
        return @copy($sourcePath, $targetPath);
    }
    
    $info = @getimagesize($sourcePath);
    if (!$info) {
        return @copy($sourcePath, $targetPath);
    }
    
    $origWidth = $info[0];
    $origHeight = $info[1];
    $mime = $info['mime'];
    
    if ($origWidth <= 0 || $origHeight <= 0) {
        return @copy($sourcePath, $targetPath);
    }
    
    $srcImg = null;
    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':
            if (function_exists('imagecreatefromjpeg')) {
                $srcImg = @imagecreatefromjpeg($sourcePath);
            }
            break;
        case 'image/png':
            if (function_exists('imagecreatefrompng')) {
                $srcImg = @imagecreatefrompng($sourcePath);
            }
            break;
        case 'image/webp':
            if (function_exists('imagecreatefromwebp')) {
                $srcImg = @imagecreatefromwebp($sourcePath);
            }
            break;
    }
    
    if (!$srcImg) {
        return @copy($sourcePath, $targetPath);
    }
    
    // Calculate new dimensions preserving aspect ratio
    $ratio = $origWidth / $origHeight;
    if ($origWidth > $origHeight) {
        $newWidth = min($origWidth, $maxDim);
        $newHeight = (int)($newWidth / $ratio);
    } else {
        $newHeight = min($origHeight, $maxDim);
        $newWidth = (int)($newHeight * $ratio);
    }
    
    $thumbImg = imagecreatetruecolor($newWidth, $newHeight);
    if (!$thumbImg) {
        imagedestroy($srcImg);
        return @copy($sourcePath, $targetPath);
    }
    
    // Preserve PNG transparency
    if ($mime === 'image/png' || $mime === 'image/webp') {
        imagealphablending($thumbImg, false);
        imagesavealpha($thumbImg, true);
        $transparent = imagecolorallocatealpha($thumbImg, 255, 255, 255, 127);
        imagefilledrectangle($thumbImg, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    imagecopyresampled($thumbImg, $srcImg, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
    
    // Save as JPEG thumbnail with quality 85
    $saved = imagejpeg($thumbImg, $targetPath, 85);
    
    imagedestroy($srcImg);
    imagedestroy($thumbImg);
    
    return $saved ?: @copy($sourcePath, $targetPath);
}

/**
 * Load an image from file path using GD with proper format detection
 */
function load_gd_image($path, &$mime = null) {
    if (!file_exists($path)) return null;
    $info = @getimagesize($path);
    if (!$info) return null;
    $mime = $info['mime'];
    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':
            return function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : null;
        case 'image/png':
            return function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : null;
        case 'image/webp':
            return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null;
        default:
            return null;
    }
}

/**
 * Correct EXIF orientation for JPEG images
 */
function fix_gd_orientation($image, $filePath) {
    if (!function_exists('exif_read_data') || !function_exists('imagerotate')) {
        return $image;
    }
    $exif = @exif_read_data($filePath);
    if (empty($exif['Orientation'])) {
        return $image;
    }
    $orientation = (int)$exif['Orientation'];
    switch ($orientation) {
        case 3:
            $rotated = @imagerotate($image, 180, 0);
            if ($rotated) { imagedestroy($image); return $rotated; }
            break;
        case 6:
            $rotated = @imagerotate($image, -90, 0);
            if ($rotated) { imagedestroy($image); return $rotated; }
            break;
        case 8:
            $rotated = @imagerotate($image, 90, 0);
            if ($rotated) { imagedestroy($image); return $rotated; }
            break;
    }
    return $image;
}

/**
 * Apply admin watermark onto a photo file and return binary string (JPEG)
 * If no watermark is configured or GD is missing, returns raw original file contents.
 *
 * @param string $sourcePath Absolute or relative path to the original photo
 * @param int|null $quality JPEG quality (null for adaptive high quality up to 2MB)
 * @return string Binary JPEG or raw file string
 */
function apply_watermark($sourcePath, $quality = null) {
    @ini_set('memory_limit', '256M');

    if (strpos($sourcePath, ROOT_DIR) !== 0 && !file_exists($sourcePath)) {
        $sourcePath = ROOT_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($sourcePath, '/\\'));
    }

    if (!file_exists($sourcePath)) {
        return '';
    }

    // Ensure get_setting is available
    if (!function_exists('get_setting')) {
        $dbFile = ROOT_DIR . '/api/db.php';
        if (file_exists($dbFile)) {
            require_once $dbFile;
        }
    }

    // Check if watermark setting is active
    $wmRelPath = function_exists('get_setting') ? get_setting('watermark_image', '') : '';
    if (empty($wmRelPath)) {
        return file_get_contents($sourcePath);
    }

    $wmFullPath = ROOT_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($wmRelPath, '/\\'));
    if (!file_exists($wmFullPath)) {
        return file_get_contents($sourcePath);
    }

    // Verify GD availability
    if (!function_exists('imagecreatetruecolor') || !function_exists('getimagesize')) {
        return file_get_contents($sourcePath);
    }

    $srcMime = null;
    $photoImg = load_gd_image($sourcePath, $srcMime);
    if (!$photoImg) {
        return file_get_contents($sourcePath);
    }

    // Correct EXIF orientation for JPEGs
    if ($srcMime === 'image/jpeg' || $srcMime === 'image/jpg') {
        $photoImg = fix_gd_orientation($photoImg, $sourcePath);
    }

    $photoW = imagesx($photoImg);
    $photoH = imagesy($photoImg);
    if ($photoW <= 0 || $photoH <= 0) {
        imagedestroy($photoImg);
        return file_get_contents($sourcePath);
    }

    $wmMime = null;
    $wmImg = load_gd_image($wmFullPath, $wmMime);
    if (!$wmImg) {
        imagedestroy($photoImg);
        return file_get_contents($sourcePath);
    }

    $wmOrigW = imagesx($wmImg);
    $wmOrigH = imagesy($wmImg);
    if ($wmOrigW <= 0 || $wmOrigH <= 0) {
        imagedestroy($photoImg);
        imagedestroy($wmImg);
        return file_get_contents($sourcePath);
    }

    // Configured watermark size percentage (default 18% of photo width)
    $sizePercent = function_exists('get_setting') ? (int)get_setting('watermark_size', 18) : 18;
    $sizePercent = max(8, min(50, $sizePercent));

    $targetWmW = max(60, (int)($photoW * ($sizePercent / 100)));
    $scale = $targetWmW / $wmOrigW;
    $targetWmH = (int)($wmOrigH * $scale);

    // Prevent watermark from exceeding 40% of photo height
    if ($targetWmH > (int)($photoH * 0.40)) {
        $targetWmH = (int)($photoH * 0.40);
        $targetWmW = (int)($wmOrigW * ($targetWmH / $wmOrigH));
    }

    // Create scaled watermark canvas preserving transparency
    $scaledWm = imagecreatetruecolor($targetWmW, $targetWmH);
    imagealphablending($scaledWm, false);
    imagesavealpha($scaledWm, true);
    $transColor = imagecolorallocatealpha($scaledWm, 0, 0, 0, 127);
    imagefilledrectangle($scaledWm, 0, 0, $targetWmW, $targetWmH, $transColor);
    imagecopyresampled($scaledWm, $wmImg, 0, 0, 0, 0, $targetWmW, $targetWmH, $wmOrigW, $wmOrigH);

    // Responsive padding margin (approx 3% of smaller dimension, clamped 12px - 50px)
    $margin = max(12, min(50, (int)(min($photoW, $photoH) * 0.03)));

    $position = function_exists('get_setting') ? get_setting('watermark_position', 'bottom-right') : 'bottom-right';

    switch ($position) {
        case 'bottom-left':
            $dstX = $margin;
            $dstY = $photoH - $targetWmH - $margin;
            break;
        case 'top-right':
            $dstX = $photoW - $targetWmW - $margin;
            $dstY = $margin;
            break;
        case 'top-left':
            $dstX = $margin;
            $dstY = $margin;
            break;
        case 'center':
            $dstX = (int)(($photoW - $targetWmW) / 2);
            $dstY = (int)(($photoH - $targetWmH) / 2);
            break;
        case 'bottom-right':
        default:
            $dstX = $photoW - $targetWmW - $margin;
            $dstY = $photoH - $targetWmH - $margin;
            break;
    }

    // Ensure within bounds
    $dstX = max(0, min($photoW - $targetWmW, $dstX));
    $dstY = max(0, min($photoH - $targetWmH, $dstY));

    // Enable alpha blending on the main photo and copy the watermark
    imagealphablending($photoImg, true);
    imagecopy($photoImg, $scaledWm, $dstX, $dstY, 0, 0, $targetWmW, $targetWmH);

    // Enable progressive JPEG for sharp interlaced delivery
    if (function_exists('imageinterlace')) {
        @imageinterlace($photoImg, true);
    }

    $targetMaxBytes = 2 * 1024 * 1024; // 2 MB target boundary
    $outputData = null;

    if ($quality !== null && is_numeric($quality) && $quality > 0) {
        ob_start();
        imagejpeg($photoImg, null, (int)$quality);
        $outputData = ob_get_clean();
    } else {
        // Adaptive high-fidelity encoding up to ~2MB
        // 1. Pristine near-lossless pass (Quality 97)
        ob_start();
        imagejpeg($photoImg, null, 97);
        $candidate = ob_get_clean();

        if (strlen($candidate) <= $targetMaxBytes) {
            $outputData = $candidate;
        } else {
            // 2. High quality pass (Quality 95)
            ob_start();
            imagejpeg($photoImg, null, 95);
            $candidate = ob_get_clean();

            if (strlen($candidate) <= $targetMaxBytes) {
                $outputData = $candidate;
            } else {
                // 3. Balanced quality pass (Quality 92)
                ob_start();
                imagejpeg($photoImg, null, 92);
                $outputData = ob_get_clean();
            }
        }
    }

    // Clean up memory
    imagedestroy($photoImg);
    imagedestroy($wmImg);
    imagedestroy($scaledWm);

    return $outputData ?: file_get_contents($sourcePath);
}

