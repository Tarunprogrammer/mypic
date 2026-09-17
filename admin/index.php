<?php
$pageTitle = 'Dashboard Overview';
require_once __DIR__ . '/header.php';

$db = Database::getConnection();

// Fetch statistics
$totalPhotos = (int)$db->query("SELECT COUNT(*) FROM photos")->fetchColumn();
$totalFaces = (int)$db->query("SELECT COUNT(*) FROM photo_faces")->fetchColumn();

// Calculate storage size
$storageBytes = 0;
if (is_dir(PHOTO_DIR)) {
    $files = scandir(PHOTO_DIR);
    foreach ($files as $f) {
        if ($f !== '.' && $f !== '..') {
            $storageBytes += filesize(PHOTO_DIR . DIRECTORY_SEPARATOR . $f);
        }
    }
}
$storageMb = round($storageBytes / (1024 * 1024), 2);

// Recent photos
$recentPhotosStmt = $db->query("
    SELECT p.* 
    FROM photos p 
    ORDER BY p.id DESC 
    LIMIT 8
");
$recentPhotos = $recentPhotosStmt->fetchAll();
?>

<!-- Metric Stat Cards -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
    <div class="stat-card">
        <div class="stat-icon"><i class="ri-image-2-line"></i></div>
        <div>
            <div class="stat-value"><?php echo number_format($totalPhotos); ?></div>
            <div class="stat-label">Total Photos Indexed</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon emerald"><i class="ri-user-smile-line"></i></div>
        <div>
            <div class="stat-value"><?php echo number_format($totalFaces); ?></div>
            <div class="stat-label">Faces Extracted</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon amber"><i class="ri-hard-drive-2-line"></i></div>
        <div>
            <div class="stat-value"><?php echo $storageMb; ?> <span style="font-size: 1rem; font-weight: 600; color: #94a3b8;">MB</span></div>
            <div class="stat-label">Storage Consumed</div>
        </div>
    </div>
</div>

<!-- Quick Action Banner -->
<div style="background: linear-gradient(135deg, #6366f1 0%, #06b6d4 100%); border-radius: 20px; padding: 32px 36px; color: white; margin-bottom: 32px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px; box-shadow: 0 10px 30px rgba(99, 102, 241, 0.3);">
    <div>
        <h2 style="font-family: var(--font-heading); font-size: 1.55rem; font-weight: 700; margin-bottom: 6px; letter-spacing: -0.02em;">Ready to index new photos?</h2>
        <p style="opacity: 0.95; font-size: 0.95rem; max-width: 580px;">Drag and drop batch photos to automatically extract AI face vectors and make them instantly searchable.</p>
    </div>
    <div>
        <a href="<?php echo BASE_URL; ?>/admin/upload.php" class="btn" style="background: white; color: #4f46e5; box-shadow: 0 4px 16px rgba(0,0,0,0.2); font-weight: 700;">
            <i class="ri-upload-cloud-2-line"></i> Batch Upload Photos
        </a>
    </div>
</div>

<!-- Recent Uploads Card -->
<div class="admin-card">
    <div class="card-header-row">
        <div>
            <div class="card-title">Recent Uploaded Photos</div>
            <div style="font-size: 0.85rem; color: #94a3b8; margin-top: 2px;">Latest images processed and indexed by AI</div>
        </div>
        <a href="<?php echo BASE_URL; ?>/admin/photos.php" class="btn btn-secondary btn-sm">View All Photos <i class="ri-arrow-right-line"></i></a>
    </div>

    <?php if (empty($recentPhotos)): ?>
        <div style="text-align: center; padding: 56px 20px; color: #94a3b8;">
            <div style="font-size: 48px; margin-bottom: 12px; color: #64748b;"><i class="ri-image-line"></i></div>
            <div style="font-weight: 700; color: #ffffff; font-size: 1.2rem; margin-bottom: 4px;">No photos uploaded yet</div>
            <p style="font-size: 0.9rem; margin-bottom: 20px;">Upload your first batch of photos to see face recognition in action.</p>
            <a href="<?php echo BASE_URL; ?>/admin/upload.php" class="btn btn-primary btn-sm"><i class="ri-upload-2-line"></i> Upload Photos</a>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width: 80px;">Preview</th>
                        <th>File Name</th>
                        <th>Faces Detected</th>
                        <th>Dimensions</th>
                        <th>Uploaded Date</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentPhotos as $p): ?>
                        <tr>
                            <td>
                                <img src="<?php echo BASE_URL . '/' . htmlspecialchars($p['thumbnail_path']); ?>" 
                                     style="width: 52px; height: 52px; object-fit: cover; border-radius: 10px; cursor: pointer; border: 1px solid var(--border-admin);"
                                     onclick="Lightbox.open('<?php echo BASE_URL . '/' . htmlspecialchars($p['file_path']); ?>', '<?php echo htmlspecialchars($p['original_name']); ?>')">
                            </td>
                            <td>
                                <div style="font-weight: 700; color: #ffffff;"><?php echo htmlspecialchars($p['original_name']); ?></div>
                                <div style="font-size: 0.75rem; color: #94a3b8;"><?php echo round($p['file_size'] / (1024*1024), 2); ?> MB</div>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $p['face_count'] > 0 ? 'badge-pass' : 'badge-fail'; ?>">
                                    <i class="ri-user-smile-line"></i> <?php echo $p['face_count']; ?> Face(s)
                                </span>
                            </td>
                            <td style="color: #94a3b8; font-size: 0.85rem;">
                                <?php echo $p['width']; ?> × <?php echo $p['height']; ?> px
                            </td>
                            <td style="color: #94a3b8; font-size: 0.85rem;">
                                <?php echo date('M d, Y H:i', strtotime($p['created_at'])); ?>
                            </td>
                            <td style="text-align: right;">
                                <button class="btn btn-secondary btn-sm" onclick="Lightbox.open('<?php echo BASE_URL . '/' . htmlspecialchars($p['file_path']); ?>', '<?php echo htmlspecialchars($p['original_name']); ?>')">
                                    <i class="ri-eye-line"></i> View
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
