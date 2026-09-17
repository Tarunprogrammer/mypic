<?php
$pageTitle = 'System & AI Settings';
require_once __DIR__ . '/header.php';

$db = Database::getConnection();
$message = '';
$error = '';

/**
 * Automatically resize & compress uploaded banner images to optimize loading speed
 */
function optimize_banner_upload($tmpPath, $targetPath, $maxWidth = 1400, $maxHeight = 800, $quality = 85) {
    if (!extension_loaded('gd')) {
        return @move_uploaded_file($tmpPath, $targetPath);
    }
    
    $info = @getimagesize($tmpPath);
    if (!$info) {
        return @move_uploaded_file($tmpPath, $targetPath);
    }
    
    $width = $info[0];
    $height = $info[1];
    $mime = $info['mime'];
    
    $srcImg = null;
    switch ($mime) {
        case 'image/jpeg':
            $srcImg = @imagecreatefromjpeg($tmpPath);
            break;
        case 'image/png':
            $srcImg = @imagecreatefrompng($tmpPath);
            break;
        case 'image/webp':
            $srcImg = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmpPath) : null;
            break;
    }
    
    if (!$srcImg) {
        return @move_uploaded_file($tmpPath, $targetPath);
    }
    
    $ratio = min($maxWidth / max(1, $width), $maxHeight / max(1, $height), 1.0);
    $newWidth = max(1, (int)round($width * $ratio));
    $newHeight = max(1, (int)round($height * $ratio));
    
    $dstImg = imagecreatetruecolor($newWidth, $newHeight);
    
    $ext = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
    if ($mime === 'image/png' || $ext === 'png') {
        imagealphablending($dstImg, false);
        imagesavealpha($dstImg, true);
        $transparent = imagecolorallocatealpha($dstImg, 255, 255, 255, 127);
        imagefilledrectangle($dstImg, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
    $saved = false;
    if ($ext === 'png' || $mime === 'image/png') {
        // High compression level 9 for PNG
        $saved = @imagepng($dstImg, $targetPath, 9);
    } elseif ($ext === 'webp' && function_exists('imagewebp')) {
        $saved = @imagewebp($dstImg, $targetPath, $quality);
    } else {
        @imageinterlace($dstImg, true);
        $saved = @imagejpeg($dstImg, $targetPath, $quality);
    }
    
    @imagedestroy($srcImg);
    @imagedestroy($dstImg);
    
    if (!$saved || !file_exists($targetPath)) {
        return @move_uploaded_file($tmpPath, $targetPath);
    }
    return true;
}

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        $siteName = trim($_POST['site_name'] ?? 'MYPIC');
        $matchThreshold = (float)($_POST['match_threshold'] ?? 0.50);
        $allowPublic = isset($_POST['allow_public_search']) ? '1' : '0';

        $stmt = $db->prepare("UPDATE settings SET key_value = ? WHERE key_name = 'site_name'");
        $stmt->execute([$siteName]);

        $stmt = $db->prepare("UPDATE settings SET key_value = ? WHERE key_name = 'match_threshold'");
        $stmt->execute([(string)$matchThreshold]);

        $stmt = $db->prepare("UPDATE settings SET key_value = ? WHERE key_name = 'allow_public_search'");
        $stmt->execute([$allowPublic]);

        $geminiKey = trim($_POST['gemini_api_key'] ?? '');
        $stmt = $db->prepare("INSERT INTO settings (key_name, key_value, description) VALUES ('gemini_api_key', ?, 'Google Gemini AI API Key') ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)");
        $stmt->execute([$geminiKey]);

        $googleVer = trim($_POST['google_site_verification'] ?? '');
        $stmt = $db->prepare("INSERT INTO settings (key_name, key_value, description) VALUES ('google_site_verification', ?, 'Google Search Console Verification Tag') ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)");
        $stmt->execute([$googleVer]);

        $message = 'Settings saved successfully!';
    } elseif ($action === 'remove_banner' || ($action === 'save_banner' && isset($_POST['remove_banner']))) {
        $stmt = $db->prepare("SELECT key_value FROM settings WHERE key_name = 'event_banner' LIMIT 1");
        $stmt->execute();
        $oldBanner = $stmt->fetchColumn();
        if ($oldBanner && file_exists(ROOT_DIR . '/' . $oldBanner)) {
            @unlink(ROOT_DIR . '/' . $oldBanner);
        }
        $stmt = $db->prepare("DELETE FROM settings WHERE key_name IN ('event_banner', 'event_banners')");
        $stmt->execute();
        $message = 'Event banner removed successfully! Default website promo banner is now active.';
    } elseif ($action === 'save_banner') {
        if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
            $bannerFile = $_FILES['banner_image'];
            $ext = strtolower(pathinfo($bannerFile['name'], PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];

            if (!in_array($ext, $allowedExts)) {
                $error = 'Invalid image format. Please upload JPG, PNG, or WEBP.';
            } else {
                $bannerDir = ROOT_DIR . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'banners';
                if (!is_dir($bannerDir)) {
                    @mkdir($bannerDir, 0777, true);
                }

                $bannerFilename = 'banner_' . time() . '.' . $ext;
                $targetPath = $bannerDir . DIRECTORY_SEPARATOR . $bannerFilename;

                if (optimize_banner_upload($bannerFile['tmp_name'], $targetPath)) {
                    $relPath = 'uploads/banners/' . $bannerFilename;
                    $stmt = $db->prepare("INSERT INTO settings (key_name, key_value, description) VALUES ('event_banner', ?, 'Public Event Banner Image') ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)");
                    $stmt->execute([$relPath]);
                    $message = 'Event banner uploaded, optimized, and applied successfully to index page!';
                } else {
                    $error = 'Failed to save uploaded banner file.';
                }
            }
        } else {
            $error = 'Please select a banner image file to upload.';
        }
    } elseif ($action === 'remove_organizers_banner') {
        $stmt = $db->prepare("SELECT key_value FROM settings WHERE key_name = 'organizers_banner' LIMIT 1");
        $stmt->execute();
        $oldBanner = $stmt->fetchColumn();
        if ($oldBanner && file_exists(ROOT_DIR . '/' . $oldBanner)) {
            @unlink(ROOT_DIR . '/' . $oldBanner);
        }
        $stmt = $db->prepare("DELETE FROM settings WHERE key_name = 'organizers_banner'");
        $stmt->execute();
        $message = 'Organizers banner removed successfully! The bottom of the index page will now remain clean as usual.';
    } elseif ($action === 'save_organizers_banner') {
        if (isset($_FILES['organizers_banner_image']) && $_FILES['organizers_banner_image']['error'] === UPLOAD_ERR_OK) {
            $bannerFile = $_FILES['organizers_banner_image'];
            $ext = strtolower(pathinfo($bannerFile['name'], PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];

            if (!in_array($ext, $allowedExts)) {
                $error = 'Invalid image format. Please upload JPG, PNG, or WEBP.';
            } else {
                $bannerDir = ROOT_DIR . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'banners';
                if (!is_dir($bannerDir)) {
                    @mkdir($bannerDir, 0777, true);
                }

                $stmt = $db->prepare("SELECT key_value FROM settings WHERE key_name = 'organizers_banner' LIMIT 1");
                $stmt->execute();
                $oldBanner = $stmt->fetchColumn();
                if ($oldBanner && file_exists(ROOT_DIR . '/' . $oldBanner)) {
                    @unlink(ROOT_DIR . '/' . $oldBanner);
                }

                $bannerFilename = 'organizers_banner_' . time() . '.' . $ext;
                $targetPath = $bannerDir . DIRECTORY_SEPARATOR . $bannerFilename;

                if (optimize_banner_upload($bannerFile['tmp_name'], $targetPath)) {
                    $relPath = 'uploads/banners/' . $bannerFilename;
                    $stmt = $db->prepare("INSERT INTO settings (key_name, key_value, description) VALUES ('organizers_banner', ?, 'Public Organizers and Sponsors Banner') ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)");
                    $stmt->execute([$relPath]);
                    $message = 'Organizers banner uploaded, optimized, and activated successfully at the bottom of the index page!';
                } else {
                    $error = 'Failed to save uploaded organizers banner file.';
                }
            }
        } else {
            $error = 'Please select an image file to upload as organizers banner.';
        }
    } elseif ($action === 'remove_watermark') {
        $stmt = $db->prepare("SELECT key_value FROM settings WHERE key_name = 'watermark_image' LIMIT 1");
        $stmt->execute();
        $oldWm = $stmt->fetchColumn();
        if ($oldWm && file_exists(ROOT_DIR . '/' . $oldWm)) {
            @unlink(ROOT_DIR . '/' . $oldWm);
        }
        $stmt = $db->prepare("DELETE FROM settings WHERE key_name = 'watermark_image'");
        $stmt->execute();
        $message = 'Watermark removed successfully! Downloaded photos will no longer include a watermark.';
    } elseif ($action === 'save_watermark') {
        $position = $_POST['watermark_position'] ?? 'bottom-right';
        $allowedPositions = ['bottom-right', 'bottom-left', 'top-right', 'top-left', 'center'];
        if (!in_array($position, $allowedPositions)) {
            $position = 'bottom-right';
        }

        $size = (int)($_POST['watermark_size'] ?? 18);
        $size = max(8, min(50, $size));

        $stmt = $db->prepare("INSERT INTO settings (key_name, key_value, description) VALUES ('watermark_position', ?, 'Watermark placement corner') ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)");
        $stmt->execute([$position]);

        $stmt = $db->prepare("INSERT INTO settings (key_name, key_value, description) VALUES ('watermark_size', ?, 'Watermark scale percentage') ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)");
        $stmt->execute([(string)$size]);

        if (isset($_FILES['watermark_image']) && $_FILES['watermark_image']['error'] === UPLOAD_ERR_OK) {
            $wmFile = $_FILES['watermark_image'];
            $ext = strtolower(pathinfo($wmFile['name'], PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];

            if (!in_array($ext, $allowedExts)) {
                $error = 'Invalid watermark format. Please upload PNG, WEBP, or JPG.';
            } else {
                $wmDir = ROOT_DIR . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'watermarks';
                if (!is_dir($wmDir)) {
                    @mkdir($wmDir, 0777, true);
                }

                $stmt = $db->prepare("SELECT key_value FROM settings WHERE key_name = 'watermark_image' LIMIT 1");
                $stmt->execute();
                $oldWm = $stmt->fetchColumn();
                if ($oldWm && file_exists(ROOT_DIR . '/' . $oldWm)) {
                    @unlink(ROOT_DIR . '/' . $oldWm);
                }

                $wmFilename = 'watermark_' . time() . '.' . $ext;
                $targetPath = $wmDir . DIRECTORY_SEPARATOR . $wmFilename;

                if (move_uploaded_file($wmFile['tmp_name'], $targetPath)) {
                    $relPath = 'uploads/watermarks/' . $wmFilename;
                    $stmt = $db->prepare("INSERT INTO settings (key_name, key_value, description) VALUES ('watermark_image', ?, 'Watermark Image for Photo Downloads') ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)");
                    $stmt->execute([$relPath]);
                    $message = 'Watermark uploaded and applied successfully! All downloaded photos will now feature this watermark.';
                } else {
                    $error = 'Failed to save uploaded watermark file.';
                }
            }
        } else {
            $message = 'Watermark settings (position & scale) updated successfully!';
        }
    } elseif ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';

        $adminId = $_SESSION['admin_id'];
        $stmt = $db->prepare("SELECT * FROM admins WHERE id = ? LIMIT 1");
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch();

        if (!empty($newPass)) {
            if (empty($currentPass) || !password_verify($currentPass, $admin['password'])) {
                $error = 'Current password is incorrect.';
            } else {
                $newHash = password_hash($newPass, PASSWORD_BCRYPT);
                $stmt = $db->prepare("UPDATE admins SET name = ?, email = ?, password = ? WHERE id = ?");
                $stmt->execute([$name, $email, $newHash, $adminId]);
                $_SESSION['admin_name'] = $name;
                $message = 'Profile and password updated successfully!';
            }
        } else {
            $stmt = $db->prepare("UPDATE admins SET name = ?, email = ? WHERE id = ?");
            $stmt->execute([$name, $email, $adminId]);
            $_SESSION['admin_name'] = $name;
            $message = 'Profile updated successfully!';
        }
    }
}

$siteName = get_setting('site_name', 'MYPIC');
$matchThreshold = get_setting('match_threshold', '0.50');
$allowPublic = get_setting('allow_public_search', '1');
$geminiApiKey = get_setting('gemini_api_key', DEFAULT_GEMINI_API_KEY);
$googleVerification = get_setting('google_site_verification', '');

$eventBanner = get_setting('event_banner', '');
$organizersBanner = get_setting('organizers_banner', '');
$watermarkImage = get_setting('watermark_image', '');
$watermarkPosition = get_setting('watermark_position', 'bottom-right');
$watermarkSize = (int)get_setting('watermark_size', 18);

// Get current admin
$stmt = $db->prepare("SELECT * FROM admins WHERE id = ? LIMIT 1");
$stmt->execute([$_SESSION['admin_id']]);
$currentAdmin = $stmt->fetch();
?>

<?php if ($message): ?>
    <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.4); color: #34d399; padding: 14px 20px; border-radius: var(--radius-md); margin-bottom: 24px; font-weight: 700;">
        ✓ <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div style="background: rgba(244, 63, 94, 0.15); border: 1px solid rgba(244, 63, 94, 0.4); color: #fda4af; padding: 14px 20px; border-radius: var(--radius-md); margin-bottom: 24px; font-weight: 700;">
        ⚠️ <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px;">

    <!-- Public Event Banner Card -->
    <div class="admin-card" style="grid-column: 1 / -1;">
        <div class="card-header-row">
            <div>
                <div class="card-title">
                    <i class="ri-image-2-line" style="color: #818cf8;"></i>
                    <span>Public Event Banner (Index Page)</span>
                </div>
                <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 2px;">
                    Upload an event poster or banner image to display prominently at the top of the search page (index.php)
                </div>
            </div>
        </div>

        <?php if (!empty($eventBanner) && (file_exists(ROOT_DIR . '/' . $eventBanner) || strpos($eventBanner, 'http') === 0)): ?>
            <div style="margin-bottom: 18px;">
                <div style="font-size: 0.82rem; font-weight: 700; color: var(--text-secondary); margin-bottom: 8px;">Active Custom Live Banner:</div>
                <div style="max-height: 220px; border-radius: var(--radius-md); overflow: hidden; border: 1px solid var(--border-admin); background: #000; position: relative; display: flex; align-items: center; justify-content: center;">
                    <img src="<?php echo (strpos($eventBanner, 'http') === 0 ? htmlspecialchars($eventBanner) : BASE_URL . '/' . htmlspecialchars($eventBanner)); ?>" style="width: 100%; height: auto; max-height: 220px; object-fit: cover; display: block;">
                </div>
                <div style="margin-top: 12px;">
                    <form method="POST" onsubmit="return confirm('Are you sure you want to remove the custom event banner? The default website promo banner will be shown.')">
                        <input type="hidden" name="action" value="remove_banner">
                        <button type="submit" class="btn btn-secondary btn-sm" style="color: #ef4444; border-color: rgba(239, 68, 68, 0.4); cursor: pointer;">
                            <i class="ri-delete-bin-line"></i> Remove Custom Banner
                        </button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div style="margin-bottom: 18px;">
                <div style="font-size: 0.82rem; font-weight: 700; color: var(--text-secondary); margin-bottom: 8px;">Active Live Banner (Default Website Promo):</div>
                <div style="max-height: 180px; border-radius: var(--radius-md); overflow: hidden; border: 1px solid var(--border-admin); background: #000; position: relative; display: flex; align-items: center; justify-content: center;">
                    <img src="<?php echo BASE_URL; ?>/assets/images/promo-banner.jpg" style="width: 100%; height: auto; max-height: 180px; object-fit: cover; display: block;">
                </div>
                <p style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 6px;">
                    The default website promo banner is currently active on the home page. Upload a custom event image below to replace it.
                </p>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" style="border-top: 1px solid var(--border-admin); padding-top: 16px; margin-top: 16px;">
            <input type="hidden" name="action" value="save_banner">

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label">Upload New Event Banner Image (JPG, PNG, WEBP)</label>
                <input type="file" name="banner_image" accept="image/jpeg,image/png,image/webp" class="input-control" style="padding: 8px;" required>
                <p style="font-size: 0.76rem; color: var(--text-secondary); margin-top: 6px;">
                    💡 Recommended size: 1200×400px or 16:9 ratio. Scales automatically on all mobile and desktop screens.
                </p>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="ri-upload-cloud-2-line"></i> Upload & Apply Banner
            </button>
        </form>
    </div>

    <!-- Organizers & Sponsors Banner Card (Bottom of Index Page) -->
    <div class="admin-card" style="grid-column: 1 / -1;">
        <div class="card-header-row">
            <div>
                <div class="card-title">
                    <i class="ri-team-line" style="color: #ec4899;"></i>
                    <span>Organizers & Sponsors Banner (Bottom of Index Page)</span>
                </div>
                <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 2px;">
                    Upload an organizers, partners, or sponsors banner to display at the bottom of the public search page (above the footer). If no banner is uploaded, the page remains clean as usual.
                </div>
            </div>
        </div>

        <?php if (!empty($organizersBanner) && (file_exists(ROOT_DIR . '/' . $organizersBanner) || strpos($organizersBanner, 'http') === 0)): ?>
            <div style="margin-bottom: 18px;">
                <div style="font-size: 0.82rem; font-weight: 700; color: var(--text-secondary); margin-bottom: 8px;">Active Live Organizers Banner:</div>
                <div style="max-height: 240px; border-radius: var(--radius-md); overflow: hidden; border: 1px solid var(--border-admin); background: #000; position: relative; display: flex; align-items: center; justify-content: center;">
                    <img src="<?php echo (strpos($organizersBanner, 'http') === 0 ? htmlspecialchars($organizersBanner) : BASE_URL . '/' . htmlspecialchars($organizersBanner)); ?>" style="width: 100%; height: auto; max-height: 240px; object-fit: cover; display: block;" alt="Organizers Banner">
                </div>
                <div style="margin-top: 12px;">
                    <form method="POST" onsubmit="return confirm('Are you sure you want to remove the organizers banner? The bottom of the index page will remain clean as usual.')">
                        <input type="hidden" name="action" value="remove_organizers_banner">
                        <button type="submit" class="btn btn-secondary btn-sm" style="color: #ef4444; border-color: rgba(239, 68, 68, 0.4); cursor: pointer;">
                            <i class="ri-delete-bin-line"></i> Remove Organizers Banner
                        </button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div style="margin-bottom: 18px; padding: 16px; border-radius: var(--radius-md); background: rgba(255, 255, 255, 0.03); border: 1px dashed var(--border-admin);">
                <div style="font-size: 0.85rem; color: var(--text-secondary);">
                    ℹ️ <strong>No organizers banner is currently active.</strong> The bottom of the index page is currently clean without any banner. Upload an image below if you want to display organizers, sponsors, or partners.
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" style="border-top: 1px solid var(--border-admin); padding-top: 16px; margin-top: 16px;">
            <input type="hidden" name="action" value="save_organizers_banner">

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label">Upload Organizers / Sponsors Banner Image (JPG, PNG, WEBP)</label>
                <input type="file" name="organizers_banner_image" accept="image/jpeg,image/png,image/webp" class="input-control" style="padding: 8px;" required>
                <p style="font-size: 0.76rem; color: var(--text-secondary); margin-top: 6px;">
                    💡 Recommended size: 1200×300px or wide strip showing logos. Automatically scales down to fit mobile and desktop screens.
                </p>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="ri-upload-cloud-2-line"></i> Upload & Apply Organizers Banner
            </button>
        </form>
    </div>

    <!-- Photo Download Watermark Card -->
    <div class="admin-card" style="grid-column: 1 / -1;">
        <div class="card-header-row">
            <div>
                <div class="card-title">
                    <i class="ri-shield-star-line" style="color: #38bdf8;"></i>
                    <span>Photo Download Watermark (Custom Logo / Stamp)</span>
                </div>
                <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 2px;">
                    Upload a custom watermark or logo to automatically overlay onto all photos when downloaded by visitors (both single photo and batch ZIP)
                </div>
            </div>
        </div>

        <?php if (!empty($watermarkImage) && file_exists(ROOT_DIR . '/' . $watermarkImage)): ?>
            <div style="margin-bottom: 18px;">
                <div style="font-size: 0.82rem; font-weight: 700; color: var(--text-secondary); margin-bottom: 8px;">Active Watermark:</div>
                <div style="max-width: 360px; height: 120px; border-radius: var(--radius-md); overflow: hidden; border: 1px solid var(--border-admin); background: repeating-conic-gradient(#1e293b 0% 25%, #0f172a 0% 50%) 50% / 20px 20px; position: relative; display: flex; align-items: center; justify-content: center; padding: 12px;">
                    <img src="<?php echo BASE_URL . '/' . htmlspecialchars($watermarkImage); ?>" style="max-height: 100%; max-width: 100%; object-fit: contain; display: block; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.5));" alt="Active Watermark">
                </div>
                <div style="display: flex; gap: 12px; align-items: center; margin-top: 12px; flex-wrap: wrap;">
                    <span class="badge" style="background: rgba(56, 189, 248, 0.15); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.3); font-size: 0.78rem; padding: 4px 10px; border-radius: var(--radius-full);">
                        Position: <?php echo htmlspecialchars(ucwords(str_replace('-', ' ', $watermarkPosition))); ?>
                    </span>
                    <span class="badge" style="background: rgba(99, 102, 241, 0.15); color: #a5b4fc; border: 1px solid rgba(99, 102, 241, 0.3); font-size: 0.78rem; padding: 4px 10px; border-radius: var(--radius-full);">
                        Scale: <?php echo (int)$watermarkSize; ?>% width
                    </span>
                    <form method="POST" onsubmit="return confirm('Are you sure you want to remove the watermark? Downloaded photos will no longer include a watermark.')" style="display: inline;">
                        <input type="hidden" name="action" value="remove_watermark">
                        <button type="submit" class="btn btn-secondary btn-sm" style="color: #ef4444; border-color: rgba(239, 68, 68, 0.4); cursor: pointer;">
                            <i class="ri-delete-bin-line"></i> Remove Watermark
                        </button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div style="margin-bottom: 18px; padding: 16px; border-radius: var(--radius-md); background: rgba(255, 255, 255, 0.03); border: 1px dashed var(--border-admin);">
                <div style="font-size: 0.85rem; color: var(--text-secondary);">
                    ℹ️ <strong>No watermark is currently active.</strong> All photos will be downloaded in their clean original state without any overlay. Upload a watermark below to activate dynamic watermarking on downloads.
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" style="border-top: 1px solid var(--border-admin); padding-top: 16px; margin-top: 16px;">
            <input type="hidden" name="action" value="save_watermark">

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 16px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Select Watermark Image (PNG, WEBP, JPG)</label>
                    <input type="file" name="watermark_image" accept="image/png,image/webp,image/jpeg" class="input-control" style="padding: 8px;" <?php echo empty($watermarkImage) ? 'required' : ''; ?>>
                    <p style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 6px;">
                        💡 <strong>Transparent PNG or WEBP</strong> is recommended for clean logos and signatures.
                    </p>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Watermark Position</label>
                    <select name="watermark_position" class="input-control" style="padding: 9px 12px;">
                        <option value="bottom-right" <?php echo $watermarkPosition === 'bottom-right' ? 'selected' : ''; ?>>Bottom Right (Recommended)</option>
                        <option value="bottom-left" <?php echo $watermarkPosition === 'bottom-left' ? 'selected' : ''; ?>>Bottom Left</option>
                        <option value="top-right" <?php echo $watermarkPosition === 'top-right' ? 'selected' : ''; ?>>Top Right</option>
                        <option value="top-left" <?php echo $watermarkPosition === 'top-left' ? 'selected' : ''; ?>>Top Left</option>
                        <option value="center" <?php echo $watermarkPosition === 'center' ? 'selected' : ''; ?>>Center</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Relative Scale (% of photo width)</label>
                    <select name="watermark_size" class="input-control" style="padding: 9px 12px;">
                        <option value="12" <?php echo $watermarkSize === 12 ? 'selected' : ''; ?>>Compact (12% width)</option>
                        <option value="18" <?php echo $watermarkSize === 18 ? 'selected' : ''; ?>>Standard (18% width) — Recommended</option>
                        <option value="25" <?php echo $watermarkSize === 25 ? 'selected' : ''; ?>>Prominent (25% width)</option>
                        <option value="35" <?php echo $watermarkSize === 35 ? 'selected' : ''; ?>>Large (35% width)</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="ri-shield-check-line"></i> <?php echo empty($watermarkImage) ? 'Upload & Activate Watermark' : 'Update Watermark Settings'; ?>
            </button>
        </form>
    </div>

    <!-- AI & System Settings -->
    <div class="admin-card">
        <div class="card-header-row">
            <div>
                <div class="card-title">
                    <i class="ri-cpu-line" style="color: var(--primary);"></i>
                    <span>Face Recognition & AI Vision</span>
                </div>
                <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 2px;">Tune AI matching sensitivity & Gemini API integration</div>
            </div>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="save_settings">

            <div class="form-group">
                <label class="form-label">Portal Title</label>
                <input type="text" name="site_name" class="input-control" value="<?php echo htmlspecialchars($siteName); ?>" required>
            </div>

            <!-- Gemini AI Key -->
            <div style="margin-bottom: 20px; background: rgba(99, 102, 241, 0.1); border: 1px solid rgba(99, 102, 241, 0.3); padding: 16px; border-radius: var(--radius-md);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <label class="form-label" style="color: #c7d2fe; margin-bottom: 0;">✨ Google Gemini AI API Key</label>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="testGeminiConnection()" style="font-size: 0.75rem; padding: 4px 10px;">
                        ⚡ Test Key
                    </button>
                </div>
                <input type="text" id="geminiApiKeyInput" name="gemini_api_key" class="input-control" value="<?php echo htmlspecialchars($geminiApiKey); ?>" style="font-family: monospace; font-size: 0.85rem;" placeholder="Enter your Gemini API key">
                <div id="geminiTestStatus" style="font-size: 0.8rem; margin-top: 6px; display: none;"></div>
                <p style="font-size: 0.75rem; color: #a5b4fc; margin-top: 6px;">
                    Used for Gemini AI photo intelligence, emotion analysis, and scene understanding.
                </p>
            </div>

            <!-- Google Search Console SEO Verification -->
            <div style="margin-bottom: 20px; background: rgba(66, 133, 244, 0.08); border: 1px solid rgba(66, 133, 244, 0.25); padding: 16px; border-radius: var(--radius-md);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <label class="form-label" style="color: var(--text-primary); margin-bottom: 0; display: flex; align-items: center; gap: 6px;">
                        <i class="ri-google-fill" style="color: #4285F4; font-size: 1.1rem;"></i> Google Search Console Verification Code
                    </label>
                    <a href="https://search.google.com/search-console" target="_blank" style="font-size: 0.75rem; color: var(--primary); text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                        Search Console <i class="ri-external-link-line"></i>
                    </a>
                </div>
                <input type="text" name="google_site_verification" class="input-control" value="<?php echo htmlspecialchars($googleVerification); ?>" style="font-family: monospace; font-size: 0.85rem;" placeholder="e.g. google-site-verification token">
                <p style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 6px;">
                    Enter your Google verification token to verify ownership and rank <strong>MYPIC</strong> at the top of Google search results.
                </p>
            </div>

            <div style="margin-bottom: 24px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <label class="form-label" style="margin-bottom: 0;">Match Distance Threshold (Euclidean)</label>
                    <span id="thresholdVal" style="font-weight: 800; color: #818cf8; font-size: 1.1rem;"><?php echo $matchThreshold; ?></span>
                </div>
                <input type="range" name="match_threshold" id="thresholdSlider" min="0.40" max="0.65" step="0.01" value="<?php echo $matchThreshold; ?>" style="width: 100%; accent-color: var(--primary); cursor: pointer;" oninput="document.getElementById('thresholdVal').textContent = this.value">
                <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: var(--text-secondary); margin-top: 6px;">
                    <span>0.40 (Strict / Zero False Matches)</span>
                    <span>0.50 (Recommended)</span>
                    <span>0.65 (Lenient / Catch More)</span>
                </div>
                <p style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 8px;">
                    💡 Lower distance values (0.48-0.50) guarantee verified facial matches without false positives.
                </p>
            </div>

            <div style="margin-bottom: 24px;">
                <label style="display: flex; align-items: center; gap: 10px; font-size: 0.9rem; font-weight: 600; color: var(--text-primary); cursor: pointer;">
                    <input type="checkbox" name="allow_public_search" value="1" <?php echo $allowPublic == '1' ? 'checked' : ''; ?> style="width: 18px; height: 18px; accent-color: var(--primary);">
                    <span>Allow Public Guest Search without logging in</span>
                </label>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <i class="ri-save-line"></i> Save Settings
            </button>
        </form>
    </div>

    <!-- Admin Profile & Security -->
    <div class="admin-card">
        <div class="card-header-row">
            <div>
                <div class="card-title">
                    <i class="ri-shield-user-line" style="color: var(--accent-pink);"></i>
                    <span>Admin Account & Security</span>
                </div>
                <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 2px;">Update profile and credentials</div>
            </div>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="update_profile">

            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" name="name" class="input-control" value="<?php echo htmlspecialchars($currentAdmin['name'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="input-control" value="<?php echo htmlspecialchars($currentAdmin['email'] ?? ''); ?>">
            </div>

            <div style="border-top: 1px solid var(--border-admin); margin: 24px 0 20px; padding-top: 16px;">
                <div style="font-weight: 700; font-size: 0.95rem; color: #ffffff; margin-bottom: 14px;">Change Password (Optional)</div>

                <div class="form-group">
                    <label class="form-label">Current Password</label>
                    <input type="password" name="current_password" class="input-control" placeholder="••••••••">
                </div>

                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" class="input-control" placeholder="Leave blank to keep unchanged">
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <i class="ri-user-settings-line"></i> Update Profile
            </button>
        </form>
    </div>
</div>

<script>
async function testGeminiConnection() {
    const keyInput = document.getElementById('geminiApiKeyInput');
    const statusDiv = document.getElementById('geminiTestStatus');
    const apiKey = keyInput ? keyInput.value.trim() : '';

    if (!apiKey) {
        alert('Please enter an API key first');
        return;
    }

    statusDiv.style.display = 'block';
    statusDiv.style.color = '#a5b4fc';
    statusDiv.textContent = '⏳ Testing connection to Google Gemini API...';

    const formData = new FormData();
    formData.append('api_key', apiKey);

    try {
        const res = await fetch('../api/gemini_ai.php?action=test', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            statusDiv.style.color = '#34d399';
            statusDiv.textContent = '✓ ' + data.message;
        } else {
            statusDiv.style.color = '#fda4af';
            statusDiv.textContent = '✕ Error: ' + (data.message || 'Connection failed');
        }
    } catch (err) {
        statusDiv.style.color = '#fda4af';
        statusDiv.textContent = '✕ Network error: ' + err.message;
    }
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
