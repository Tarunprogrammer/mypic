<?php
$pageTitle = 'Photo Library & Face Toolkit';
require_once __DIR__ . '/header.php';

$db = Database::getConnection();

$search = trim($_GET['search'] ?? '');

$query = "SELECT p.* FROM photos p WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND p.original_name LIKE ?";
    $params[] = "%$search%";
}

$query .= " ORDER BY p.id DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$photos = $stmt->fetchAll();
$totalCount = count($photos);

// Compute Quick Face Statistics
$withFacesCount = 0;
$missingFacesCount = 0;
$groupFacesCount = 0;

foreach ($photos as $p) {
    $fc = (int)$p['face_count'];
    if ($fc > 0) $withFacesCount++;
    if ($fc === 0) $missingFacesCount++;
    if ($fc >= 3) $groupFacesCount++;
}
?>

<div class="admin-card">
    <div class="card-header-row" style="flex-wrap: wrap; gap: 14px; align-items: center; justify-content: space-between; margin-bottom: 20px;">
        <div>
            <div class="card-title">
                <i class="ri-gallery-line" style="color: var(--accent-pink);"></i>
                <span>Photo Library & Face Biometric Toolkit</span>
            </div>
            <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 2px;">
                Total <strong id="libraryTotalCount" style="color: #ffffff;"><?php echo $totalCount; ?></strong> photos | 
                <span style="color: #34d399; font-weight: 700;"><?php echo $withFacesCount; ?> Indexed</span> | 
                <span style="color: #fda4af; font-weight: 700;"><?php echo $missingFacesCount; ?> Missing Faces</span>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <!-- AI Batch Re-Scan Toolkit Button -->
            <button type="button" class="btn btn-primary btn-sm" onclick="openBatchRescanModal()" style="font-weight: 800; padding: 8px 18px; box-shadow: 0 0 20px rgba(99, 102, 241, 0.4);">
                <i class="ri-cpu-line"></i> ⚡ AI Re-Scan Toolkit
            </button>

            <?php if ($totalCount > 0): ?>
                <!-- Select All Control -->
                <label id="adminSelectAllWrapper" style="display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.08); padding: 8px 16px; border-radius: var(--radius-full); cursor: pointer; border: 1px solid var(--border-admin); font-size: 0.85rem; font-weight: 700; color: #f8fafc; user-select: none; transition: var(--transition);">
                    <input type="checkbox" id="adminSelectAll" style="width: 17px; height: 17px; accent-color: #f43f5e; cursor: pointer;">
                    <span id="adminSelectAllLabel">Select All</span>
                </label>

                <!-- Dynamic Delete Button -->
                <button type="button" class="btn btn-sm" id="adminDeleteBtn" style="display: none; background: #e11d48; color: #ffffff; border: 1px solid #be123c; font-weight: 800; padding: 8px 20px; box-shadow: 0 0 16px rgba(225, 29, 72, 0.4); border-radius: var(--radius-full); transition: var(--transition);" onclick="executeDeletePhotos()">
                    <i class="ri-delete-bin-6-fill"></i> <span id="adminDeleteBtnText">Delete Selected</span>
                </button>
            <?php endif; ?>

            <!-- Search Form -->
            <form method="GET" style="display: flex; gap: 8px; align-items: center;">
                <input type="text" name="search" class="input-control" placeholder="Search filename..." value="<?php echo htmlspecialchars($search); ?>" style="width: 180px; padding: 8px 14px; font-size: 0.85rem; border-radius: var(--radius-full);">
                <button type="submit" class="btn btn-secondary btn-sm" style="padding: 8px 16px;"><i class="ri-search-line"></i></button>
                <?php if ($search): ?>
                    <a href="<?php echo BASE_URL; ?>/admin/photos.php" class="btn btn-secondary btn-sm" style="color: #f43f5e; padding: 8px 14px;">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Interactive Filter Tabs -->
    <div style="display: flex; gap: 10px; margin-bottom: 22px; flex-wrap: wrap; border-bottom: 1px solid var(--border-admin); padding-bottom: 14px;">
        <button type="button" class="face-filter-btn active" data-filter="all" onclick="filterPhotos('all', this)">
            <i class="ri-gallery-line"></i> All Photos (<span id="count-all"><?php echo $totalCount; ?></span>)
        </button>
        <button type="button" class="face-filter-btn" data-filter="has-faces" onclick="filterPhotos('has-faces', this)">
            <i class="ri-user-smile-line" style="color: #34d399;"></i> With Faces (<span id="count-with-faces"><?php echo $withFacesCount; ?></span>)
        </button>
        <button type="button" class="face-filter-btn" data-filter="missing-faces" onclick="filterPhotos('missing-faces', this)">
            <i class="ri-error-warning-line" style="color: #fda4af;"></i> Missing Faces (0) (<span id="count-missing-faces"><?php echo $missingFacesCount; ?></span>)
        </button>
        <button type="button" class="face-filter-btn" data-filter="group-faces" onclick="filterPhotos('group-faces', this)">
            <i class="ri-group-line" style="color: #818cf8;"></i> Group Photos (3+) (<span id="count-group-faces"><?php echo $groupFacesCount; ?></span>)
        </button>
    </div>

    <!-- Empty State Placeholder -->
    <div id="noPhotosPlaceholder" style="<?php echo empty($photos) ? 'display: block;' : 'display: none;'; ?> text-align: center; padding: 64px 20px; color: var(--text-secondary);">
        <div style="font-size: 48px; margin-bottom: 14px; color: var(--text-muted);"><i class="ri-image-line"></i></div>
        <div style="font-weight: 700; color: #ffffff; font-size: 1.2rem; margin-bottom: 6px;">No photos found</div>
        <p style="font-size: 0.9rem; margin-bottom: 20px;">Upload photos to populate your gallery.</p>
        <a href="<?php echo BASE_URL; ?>/admin/upload.php" class="btn btn-primary btn-sm"><i class="ri-upload-2-line"></i> Upload Photos</a>
    </div>

    <!-- Filter Empty Notice -->
    <div id="filterEmptyNotice" style="display: none; text-align: center; padding: 48px 20px; color: var(--text-secondary);">
        <div style="font-size: 42px; margin-bottom: 10px; color: #818cf8;"><i class="ri-filter-off-line"></i></div>
        <div style="font-weight: 700; color: #ffffff; font-size: 1.1rem; margin-bottom: 4px;">No photos match this filter</div>
        <p style="font-size: 0.85rem;">Try switching to "All Photos".</p>
    </div>

    <?php if (!empty($photos)): ?>
        <div class="photo-grid" id="adminPhotoGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 20px;">
            <?php foreach ($photos as $p): 
                $faceCount = (int)$p['face_count'];
                $isMissing = ($faceCount === 0);
                $isGroup = ($faceCount >= 3);
            ?>
                <div class="photo-card" id="photo-card-<?php echo $p['id']; ?>" 
                     data-id="<?php echo $p['id']; ?>"
                     data-faces="<?php echo $faceCount; ?>"
                     data-full-url="<?php echo BASE_URL . '/' . htmlspecialchars($p['file_path']); ?>"
                     data-thumb-url="<?php echo BASE_URL . '/' . htmlspecialchars($p['thumbnail_path']); ?>"
                     data-name="<?php echo htmlspecialchars($p['original_name']); ?>"
                     style="background: rgba(15, 23, 42, 0.85); border: 1px solid <?php echo $isMissing ? 'rgba(244, 63, 94, 0.35)' : 'var(--border-admin)'; ?>; border-radius: var(--radius-md); overflow: hidden; color: #fff; transition: var(--transition);">
                    
                    <div class="photo-img-wrapper" style="height: 190px; position: relative; cursor: pointer;" onclick="inspectPhotoFaces(<?php echo $p['id']; ?>)">
                        <!-- Checkbox Overlay -->
                        <div class="card-checkbox-container" onclick="event.stopPropagation();" style="display: flex; position: absolute; top: 8px; left: 8px; z-index: 20; background: rgba(15, 23, 42, 0.9); backdrop-filter: blur(10px); border-radius: 6px; padding: 5px; border: 1px solid rgba(255, 255, 255, 0.3);">
                            <input type="checkbox" class="admin-photo-chk" id="adminChk-<?php echo $p['id']; ?>" data-id="<?php echo $p['id']; ?>" style="width: 17px; height: 17px; accent-color: #f43f5e; cursor: pointer;" onchange="handleAdminPhotoCheck(<?php echo $p['id']; ?>, this.checked)">
                        </div>

                        <img src="<?php echo BASE_URL . '/' . htmlspecialchars($p['thumbnail_path']); ?>" alt="<?php echo htmlspecialchars($p['original_name']); ?>" class="photo-img" loading="lazy" style="width: 100%; height: 100%; object-fit: cover;">
                        
                        <!-- Interactive Face Count Badge (Clickable to inspect) -->
                        <div id="face-badge-<?php echo $p['id']; ?>" class="status-badge" onclick="event.stopPropagation(); inspectPhotoFaces(<?php echo $p['id']; ?>)" 
                             style="position: absolute; top: 8px; right: 8px; font-size: 0.72rem; padding: 3px 8px; backdrop-filter: blur(8px); cursor: pointer; border-radius: var(--radius-full); <?php echo $isMissing ? 'background: rgba(244, 63, 94, 0.25); border: 1px solid rgba(244, 63, 94, 0.5); color: #fda4af;' : 'background: rgba(16, 185, 129, 0.2); border: 1px solid rgba(16, 185, 129, 0.4); color: #34d399;'; ?>" title="Click to inspect face crops & bounding boxes">
                            <i class="<?php echo $isMissing ? 'ri-error-warning-line' : 'ri-user-smile-line'; ?>"></i> 
                            <span id="face-badge-text-<?php echo $p['id']; ?>"><?php echo $faceCount; ?> Faces</span>
                        </div>
                    </div>

                    <div class="photo-info" style="padding: 14px;">
                        <div>
                            <div style="font-weight: 700; font-size: 0.9rem; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #f8fafc;" title="<?php echo htmlspecialchars($p['original_name']); ?>">
                                <?php echo htmlspecialchars($p['original_name']); ?>
                            </div>
                            <div style="font-size: 0.74rem; color: var(--text-secondary); margin-bottom: 10px;">
                                <i class="ri-calendar-line"></i> <?php echo date('M d, Y', strtotime($p['created_at'])); ?> • <?php echo round($p['file_size'] / (1024*1024), 2); ?> MB
                            </div>
                        </div>

                        <!-- Action Toolbar -->
                        <div class="photo-actions" style="display: grid; grid-template-columns: 1fr 1fr; gap: 6px;">
                            <button type="button" class="btn btn-secondary btn-sm" style="padding: 6px 8px; font-size: 0.78rem;" onclick="inspectPhotoFaces(<?php echo $p['id']; ?>)" title="Inspect Face Chips">
                                <i class="ri-scan-2-line"></i> Inspect
                            </button>
                            
                            <button type="button" class="btn btn-secondary btn-sm" id="rescan-btn-<?php echo $p['id']; ?>" style="padding: 6px 8px; font-size: 0.78rem; background: rgba(99, 102, 241, 0.15); color: #c7d2fe; border-color: rgba(99, 102, 241, 0.35);" onclick="rescanSinglePhoto(<?php echo $p['id']; ?>)" title="Re-scan with Deep Multi-Scale AI">
                                <i class="ri-refresh-line"></i> Re-Scan
                            </button>

                            <button type="button" class="btn btn-secondary btn-sm" style="padding: 6px 8px; font-size: 0.78rem;" onclick="Lightbox.open('<?php echo BASE_URL . '/' . htmlspecialchars($p['file_path']); ?>', '<?php echo htmlspecialchars(addslashes($p['original_name'])); ?>', '<?php echo BASE_URL; ?>/api/download_photo.php?id=<?php echo (int)$p['id']; ?>')">
                                <i class="ri-eye-line"></i> View
                            </button>

                            <button type="button" class="btn btn-secondary btn-sm" style="background: rgba(244, 63, 94, 0.15); color: #fda4af; border-color: rgba(244, 63, 94, 0.3); padding: 6px 8px; font-size: 0.78rem;" onclick="deleteSinglePhoto(<?php echo $p['id']; ?>)" title="Delete Photo">
                                <i class="ri-delete-bin-line"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ====================================================================
     FACE INSPECTOR MODAL
     ==================================================================== -->
<div id="faceInspectorModal" class="admin-modal" style="display: none;">
    <div class="admin-modal-content" style="max-width: 900px; width: 95%;">
        <div class="admin-modal-header" style="border-bottom: 1px solid var(--border-admin); padding-bottom: 14px; margin-bottom: 18px;">
            <div>
                <h3 class="admin-modal-title" style="display: flex; align-items: center; gap: 8px; font-size: 1.25rem;">
                    <i class="ri-scan-2-line" style="color: var(--accent-pink);"></i> 
                    <span>Face Biometric Inspector</span>
                </h3>
                <div style="font-size: 0.85rem; color: var(--text-secondary);" id="inspectorPhotoName">Loading photo...</div>
            </div>
            <button type="button" class="admin-modal-close" onclick="closeFaceInspector()">&times;</button>
        </div>

        <div style="display: grid; grid-template-columns: minmax(300px, 1.4fr) minmax(260px, 1fr); gap: 20px; align-items: start;">
            <!-- Left: Full Photo Viewport with Canvas Bounding Box Overlays -->
            <div style="position: relative; background: #000; border-radius: var(--radius-md); overflow: hidden; border: 1px solid var(--border-admin); display: flex; align-items: center; justify-content: center; min-height: 280px; max-height: 480px;">
                <img id="inspectorImg" src="" style="max-width: 100%; max-height: 480px; object-fit: contain; display: block;">
                <canvas id="inspectorCanvas" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none;"></canvas>
            </div>

            <!-- Right: Extracted Face Chips / Crops & Details -->
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <span style="font-weight: 700; color: #ffffff; font-size: 0.95rem;">Extracted Faces</span>
                    <span id="inspectorFaceBadge" class="status-badge badge-pass" style="font-size: 0.75rem;">0 Faces</span>
                </div>

                <div id="inspectorFaceChipsGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(90px, 1fr)); gap: 12px; max-height: 320px; overflow-y: auto; padding-right: 4px; margin-bottom: 16px;">
                    <!-- Face crops injected here -->
                </div>

                <!-- One-Click Re-Scan Tool inside Inspector -->
                <button type="button" id="inspectorRescanBtn" class="btn btn-primary" style="width: 100%; font-weight: 800; padding: 10px; font-size: 0.88rem;" onclick="rescanCurrentInspectedPhoto()">
                    <i class="ri-refresh-line"></i> ⚡ Re-Scan With Deep Multi-Scale AI
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ====================================================================
     BATCH AI RE-SCAN TOOLKIT MODAL
     ==================================================================== -->
<div id="batchRescanModal" class="admin-modal" style="display: none;">
    <div class="admin-modal-content" style="max-width: 580px; width: 95%;">
        <div class="admin-modal-header" style="border-bottom: 1px solid var(--border-admin); padding-bottom: 14px; margin-bottom: 18px;">
            <h3 class="admin-modal-title" style="display: flex; align-items: center; gap: 8px; font-size: 1.25rem;">
                <i class="ri-cpu-line" style="color: var(--primary);"></i> 
                <span>AI Face Re-Scan Toolkit</span>
            </h3>
            <button type="button" class="admin-modal-close" onclick="closeBatchRescanModal()">&times;</button>
        </div>

        <div id="batchRescanOptions">
            <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 16px;">
                Use the high-precision Multi-Scale Pyramid AI algorithm (512px + 320px + IoU NMS) to extract missing faces, angled faces, and small group faces across your photo library.
            </p>

            <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 24px;">
                <button type="button" class="btn btn-secondary" style="justify-content: flex-start; padding: 14px; text-align: left;" onclick="startBatchRescan('missing')">
                    <div>
                        <div style="font-weight: 700; color: #fda4af; font-size: 0.95rem; margin-bottom: 2px;">
                            <i class="ri-error-warning-line"></i> Re-Scan Missing Faces (0 Faces only)
                        </div>
                        <div style="font-size: 0.8rem; color: var(--text-secondary);">
                            Scans only the <strong style="color: #ffffff;"><?php echo $missingFacesCount; ?></strong> photo(s) currently registered with zero faces.
                        </div>
                    </div>
                </button>

                <button type="button" class="btn btn-secondary" style="justify-content: flex-start; padding: 14px; text-align: left;" onclick="startBatchRescan('selected')">
                    <div>
                        <div style="font-weight: 700; color: #c7d2fe; font-size: 0.95rem; margin-bottom: 2px;">
                            <i class="ri-checkbox-multiple-line"></i> Re-Scan Selected Photos
                        </div>
                        <div style="font-size: 0.8rem; color: var(--text-secondary);">
                            Scans only the photos currently checked via selection checkboxes (<strong id="rescanSelectedCountLabel" style="color: #ffffff;">0</strong> selected).
                        </div>
                    </div>
                </button>

                <button type="button" class="btn btn-secondary" style="justify-content: flex-start; padding: 14px; text-align: left;" onclick="startBatchRescan('all')">
                    <div>
                        <div style="font-weight: 700; color: #a7f3d0; font-size: 0.95rem; margin-bottom: 2px;">
                            <i class="ri-refresh-line"></i> Re-Scan All Library Photos
                        </div>
                        <div style="font-size: 0.8rem; color: var(--text-secondary);">
                            Full index refresh for all <strong style="color: #ffffff;"><?php echo $totalCount; ?></strong> photos in the library.
                        </div>
                    </div>
                </button>
            </div>
        </div>

        <!-- Live Progress Section -->
        <div id="batchRescanProgress" style="display: none;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span id="batchProgressLabel" style="font-weight: 700; font-size: 0.9rem; color: #ffffff;">Scanning Photos...</span>
                <span id="batchProgressPercent" style="font-weight: 800; color: #818cf8; font-size: 1rem;">0%</span>
            </div>

            <div style="background: rgba(255,255,255,0.08); border-radius: 9999px; height: 10px; overflow: hidden; margin-bottom: 16px;">
                <div id="batchProgressBarFill" style="height: 100%; width: 0%; background: var(--grad-primary); border-radius: 9999px; transition: width 0.2s ease;"></div>
            </div>

            <div id="batchCurrentPhotoName" style="font-size: 0.82rem; color: var(--text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 14px;">
                Processing...
            </div>

            <div style="background: rgba(15, 23, 42, 0.6); border: 1px solid var(--border-admin); border-radius: var(--radius-md); padding: 12px; font-size: 0.85rem;">
                <div style="color: #34d399; font-weight: 700; margin-bottom: 4px;" id="batchStatsFaces">Faces Extracted: 0</div>
                <div style="color: #c7d2fe;" id="batchStatsPhotos">Photos Scanned: 0 / 0</div>
            </div>
        </div>
    </div>
</div>

<style>
.face-filter-btn {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--border-admin);
    color: var(--text-secondary);
    padding: 8px 16px;
    border-radius: var(--radius-full);
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: var(--transition);
}
.face-filter-btn:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #ffffff;
}
.face-filter-btn.active {
    background: var(--grad-primary);
    color: #ffffff;
    border-color: transparent;
    box-shadow: 0 0 14px rgba(99, 102, 241, 0.4);
}
</style>

<script>
window.adminSelectedPhotoIds = new Set();
window.currentInspectedPhotoId = null;

document.addEventListener('DOMContentLoaded', () => {
    initAdminSelection();
    if (window.FaceEngine) {
        window.FaceEngine.init('<?php echo BASE_URL; ?>/assets/models');
    }
});

// =========================================================================
// 1. FILTERING & PERFORMANCE OPTIMIZATION
// =========================================================================
function filterPhotos(type, btn) {
    document.querySelectorAll('.face-filter-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');

    const cards = document.querySelectorAll('#adminPhotoGrid .photo-card');
    let visibleCount = 0;

    cards.forEach(card => {
        const faces = parseInt(card.dataset.faces || '0');
        let show = false;

        if (type === 'all') {
            show = true;
        } else if (type === 'has-faces') {
            show = (faces > 0);
        } else if (type === 'missing-faces') {
            show = (faces === 0);
        } else if (type === 'group-faces') {
            show = (faces >= 3);
        }

        card.style.display = show ? 'block' : 'none';
        if (show) visibleCount++;
    });

    const emptyNotice = document.getElementById('filterEmptyNotice');
    if (emptyNotice) {
        emptyNotice.style.display = (visibleCount === 0 && cards.length > 0) ? 'block' : 'none';
    }
}

// =========================================================================
// 2. FACE INSPECTOR TOOL
// =========================================================================
async function inspectPhotoFaces(photoId) {
    window.currentInspectedPhotoId = photoId;
    const modal = document.getElementById('faceInspectorModal');
    const nameEl = document.getElementById('inspectorPhotoName');
    const imgEl = document.getElementById('inspectorImg');
    const canvasEl = document.getElementById('inspectorCanvas');
    const badgeEl = document.getElementById('inspectorFaceBadge');
    const chipsGrid = document.getElementById('inspectorFaceChipsGrid');

    modal.style.display = 'flex';
    nameEl.textContent = 'Loading face telemetry...';
    chipsGrid.innerHTML = '<div style="color: var(--text-secondary); font-size: 0.85rem; grid-column: 1/-1;">Loading faces...</div>';

    try {
        const res = await fetch(`<?php echo BASE_URL; ?>/api/get_photo_faces.php?photo_id=${photoId}`);
        const data = await res.json();

        if (!data.success) {
            alert(data.message || 'Error fetching face data');
            closeFaceInspector();
            return;
        }

        const photo = data.photo;
        const faces = data.faces;

        nameEl.textContent = `${photo.original_name} (${photo.width} × ${photo.height})`;
        badgeEl.textContent = `${faces.length} Faces Indexed`;
        badgeEl.className = 'status-badge ' + (faces.length > 0 ? 'badge-pass' : 'badge-fail');

        imgEl.onload = () => {
            drawInspectorOverlays(imgEl, canvasEl, faces, photo.width, photo.height);
            renderInspectorChips(imgEl, chipsGrid, faces);
        };
        imgEl.src = photo.photo_url;

    } catch (err) {
        alert('Network error: ' + err.message);
        closeFaceInspector();
    }
}

function drawInspectorOverlays(imgEl, canvasEl, faces, origW, origH) {
    canvasEl.width = imgEl.clientWidth;
    canvasEl.height = imgEl.clientHeight;
    const ctx = canvasEl.getContext('2d');
    ctx.clearRect(0, 0, canvasEl.width, canvasEl.height);

    const scaleX = canvasEl.width / (origW || imgEl.naturalWidth);
    const scaleY = canvasEl.height / (origH || imgEl.naturalHeight);

    faces.forEach((face, idx) => {
        const b = face.box;
        const bx = b.x * scaleX;
        const by = b.y * scaleY;
        const bw = b.width * scaleX;
        const bh = b.height * scaleY;

        // Draw neon bounding box
        ctx.strokeStyle = '#10b981';
        ctx.lineWidth = 2.5;
        ctx.strokeRect(bx, by, bw, bh);

        // Draw index label
        ctx.fillStyle = '#10b981';
        ctx.fillRect(bx, Math.max(0, by - 22), 26, 22);
        ctx.fillStyle = '#000000';
        ctx.font = 'bold 12px sans-serif';
        ctx.fillText(`${idx + 1}`, bx + 8, Math.max(0, by - 22) + 16);
    });
}

function renderInspectorChips(imgEl, chipsGrid, faces) {
    chipsGrid.innerHTML = '';
    if (faces.length === 0) {
        chipsGrid.innerHTML = `
            <div style="grid-column: 1/-1; padding: 20px; text-align: center; background: rgba(244, 63, 94, 0.1); border: 1px dashed rgba(244, 63, 94, 0.3); border-radius: var(--radius-md); color: #fda4af;">
                <div style="font-weight: 700; margin-bottom: 4px;">⚠️ No Faces Extracted</div>
                <div style="font-size: 0.8rem;">Click the re-scan button below to re-detect with the Multi-Scale Pyramid AI engine.</div>
            </div>
        `;
        return;
    }

    faces.forEach((face, idx) => {
        const cropData = window.FaceEngine ? window.FaceEngine.generateFaceCropDataUrl(imgEl, face.box, 100) : '';
        const chip = document.createElement('div');
        chip.style.cssText = 'background: rgba(15, 23, 42, 0.8); border: 1px solid var(--border-admin); border-radius: 8px; padding: 6px; text-align: center;';
        chip.innerHTML = `
            <div style="width: 76px; height: 76px; border-radius: 6px; overflow: hidden; margin: 0 auto 6px; background: #000; border: 1px solid #10b981;">
                ${cropData ? `<img src="${cropData}" style="width: 100%; height: 100%; object-fit: cover;">` : '<div style="padding-top:24px;font-size:10px;">Face</div>'}
            </div>
            <div style="font-size: 0.72rem; font-weight: 700; color: #ffffff;">#${idx + 1}</div>
            <div style="font-size: 0.68rem; color: #34d399;">${Math.round((face.score || 0.95) * 100)}% Match</div>
        `;
        chipsGrid.appendChild(chip);
    });
}

function closeFaceInspector() {
    const modal = document.getElementById('faceInspectorModal');
    if (modal) modal.style.display = 'none';
    window.currentInspectedPhotoId = null;
}

// =========================================================================
// 3. RE-SCAN ENGINE (Single & Batch)
// =========================================================================
async function rescanSinglePhoto(photoId) {
    const btn = document.getElementById(`rescan-btn-${photoId}`);
    const card = document.getElementById(`photo-card-${photoId}`);
    if (!card) return;

    const fullUrl = card.dataset.fullUrl;
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="ri-loader-4-line ri-spin"></i>';
    }

    try {
        const faces = await extractFacesFromUrl(fullUrl);
        await saveRescannedFaces(photoId, faces);

        // Update card UI
        card.dataset.faces = faces.length;
        const badge = document.getElementById(`face-badge-${photoId}`);
        const badgeText = document.getElementById(`face-badge-text-${photoId}`);
        if (badge && badgeText) {
            badgeText.textContent = `${faces.length} Faces`;
            if (faces.length > 0) {
                badge.style.background = 'rgba(16, 185, 129, 0.2)';
                badge.style.borderColor = 'rgba(16, 185, 129, 0.4)';
                badge.style.color = '#34d399';
                card.style.borderColor = 'var(--border-admin)';
            }
        }

        updateLibraryStatPills();

    } catch (err) {
        alert('Re-scan error: ' + err.message);
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="ri-refresh-line"></i> Re-Scan';
        }
    }
}

async function rescanCurrentInspectedPhoto() {
    if (!window.currentInspectedPhotoId) return;
    const btn = document.getElementById('inspectorRescanBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="ri-loader-4-line ri-spin"></i> Extracting Faces via Multi-Scale Pyramid...';
    }

    try {
        const photoId = window.currentInspectedPhotoId;
        const imgEl = document.getElementById('inspectorImg');
        const faces = await window.FaceEngine.extractFacesAccurate(imgEl);
        await saveRescannedFaces(photoId, faces);

        // Refresh Inspector View
        await inspectPhotoFaces(photoId);

        // Update card in grid
        const card = document.getElementById(`photo-card-${photoId}`);
        if (card) {
            card.dataset.faces = faces.length;
            const badgeText = document.getElementById(`face-badge-text-${photoId}`);
            if (badgeText) badgeText.textContent = `${faces.length} Faces`;
        }

        updateLibraryStatPills();

    } catch (err) {
        alert('Inspection Re-scan error: ' + err.message);
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="ri-refresh-line"></i> ⚡ Re-Scan With Deep Multi-Scale AI';
        }
    }
}

async function extractFacesFromUrl(url) {
    try {
        const res = await fetch(url);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const blob = await res.blob();
        const objectUrl = URL.createObjectURL(blob);

        return await new Promise((resolve, reject) => {
            const img = new Image();
            img.onload = async () => {
                try {
                    const faces = await window.FaceEngine.extractFacesAccurate(img);
                    URL.revokeObjectURL(objectUrl);
                    resolve(faces);
                } catch (e) {
                    URL.revokeObjectURL(objectUrl);
                    reject(e);
                }
            };
            img.onerror = () => {
                URL.revokeObjectURL(objectUrl);
                reject(new Error('Failed to load image blob'));
            };
            img.src = objectUrl;
        });
    } catch (fetchErr) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = async () => {
                try {
                    const faces = await window.FaceEngine.extractFacesAccurate(img);
                    resolve(faces);
                } catch (e) {
                    reject(e);
                }
            };
            img.onerror = () => reject(new Error('Failed to load image from ' + url));
            img.src = url;
        });
    }
}

async function saveRescannedFaces(photoId, faces) {
    const payloadFaces = faces.map(f => ({
        box: f.box,
        score: f.score,
        descriptor: f.descriptor
    }));

    const res = await fetch('<?php echo BASE_URL; ?>/api/rescan_faces.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            photo_id: photoId,
            faces: payloadFaces
        })
    });

    const data = await res.json();
    if (!data.success) {
        throw new Error(data.message || 'Server error updating faces');
    }
    return data;
}

function updateLibraryStatPills() {
    let withFaces = 0;
    let missingFaces = 0;
    let groupFaces = 0;

    const cards = document.querySelectorAll('#adminPhotoGrid .photo-card');
    cards.forEach(c => {
        const fc = parseInt(c.dataset.faces || '0');
        if (fc > 0) withFaces++;
        if (fc === 0) missingFaces++;
        if (fc >= 3) groupFaces++;
    });

    const wfEl = document.getElementById('count-with-faces');
    const mfEl = document.getElementById('count-missing-faces');
    const gfEl = document.getElementById('count-group-faces');

    if (wfEl) wfEl.textContent = withFaces;
    if (mfEl) mfEl.textContent = missingFaces;
    if (gfEl) gfEl.textContent = groupFaces;
}

// =========================================================================
// 4. BATCH RE-SCAN MODAL & QUEUE
// =========================================================================
function openBatchRescanModal() {
    const modal = document.getElementById('batchRescanModal');
    const selectedCountLabel = document.getElementById('rescanSelectedCountLabel');
    if (selectedCountLabel) {
        selectedCountLabel.textContent = window.adminSelectedPhotoIds.size;
    }
    document.getElementById('batchRescanOptions').style.display = 'block';
    document.getElementById('batchRescanProgress').style.display = 'none';
    modal.style.display = 'flex';
}

function closeBatchRescanModal() {
    const modal = document.getElementById('batchRescanModal');
    if (modal) modal.style.display = 'none';
}

async function startBatchRescan(mode) {
    const cards = Array.from(document.querySelectorAll('#adminPhotoGrid .photo-card'));
    let targetCards = [];

    if (mode === 'missing') {
        targetCards = cards.filter(c => parseInt(c.dataset.faces || '0') === 0);
    } else if (mode === 'selected') {
        targetCards = cards.filter(c => window.adminSelectedPhotoIds.has(parseInt(c.dataset.id)));
    } else {
        targetCards = cards;
    }

    if (targetCards.length === 0) {
        alert('No photos found matching this criteria.');
        return;
    }

    // Switch view to progress
    document.getElementById('batchRescanOptions').style.display = 'none';
    const progressEl = document.getElementById('batchRescanProgress');
    progressEl.style.display = 'block';

    const progressBar = document.getElementById('batchProgressBarFill');
    const progressPercent = document.getElementById('batchProgressPercent');
    const photoNameEl = document.getElementById('batchCurrentPhotoName');
    const statsFacesEl = document.getElementById('batchStatsFaces');
    const statsPhotosEl = document.getElementById('batchStatsPhotos');

    let totalFacesIndexed = 0;

    for (let i = 0; i < targetCards.length; i++) {
        const card = targetCards[i];
        const photoId = parseInt(card.dataset.id);
        const name = card.dataset.name;
        const fullUrl = card.dataset.fullUrl;

        const percent = Math.round(((i + 1) / targetCards.length) * 100);
        if (progressBar) progressBar.style.width = percent + '%';
        if (progressPercent) progressPercent.textContent = percent + '%';
        if (photoNameEl) photoNameEl.textContent = `(${i + 1}/${targetCards.length}) Scanning ${name}...`;

        try {
            const faces = await extractFacesFromUrl(fullUrl);
            await saveRescannedFaces(photoId, faces);

            totalFacesIndexed += faces.length;
            card.dataset.faces = faces.length;
            const badgeText = document.getElementById(`face-badge-text-${photoId}`);
            if (badgeText) badgeText.textContent = `${faces.length} Faces`;

        } catch (err) {
            console.warn(`Failed re-scanning photo #${photoId}:`, err);
        }

        if (statsFacesEl) statsFacesEl.textContent = `Faces Extracted: ${totalFacesIndexed}`;
        if (statsPhotosEl) statsPhotosEl.textContent = `Photos Scanned: ${i + 1} / ${targetCards.length}`;
    }

    updateLibraryStatPills();
    alert(`✓ Re-scan Complete! Indexed ${totalFacesIndexed} face(s) across ${targetCards.length} photo(s).`);
    closeBatchRescanModal();
}

// =========================================================================
// 5. SELECTION & DELETION CONTROLS
// =========================================================================
function initAdminSelection() {
    window.adminSelectedPhotoIds.clear();
    const selectAllCheckbox = document.getElementById('adminSelectAll');
    
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = false;
        selectAllCheckbox.addEventListener('change', (e) => {
            const isChecked = e.target.checked;
            const allCheckboxes = document.querySelectorAll('.admin-photo-chk');
            
            allCheckboxes.forEach(chk => {
                chk.checked = isChecked;
                const photoId = parseInt(chk.dataset.id);
                const card = document.getElementById(`photo-card-${photoId}`);
                
                if (isChecked) {
                    window.adminSelectedPhotoIds.add(photoId);
                    if (card) {
                        card.style.borderColor = '#f43f5e';
                        card.style.boxShadow = '0 0 16px rgba(244, 63, 94, 0.4)';
                    }
                } else {
                    window.adminSelectedPhotoIds.delete(photoId);
                    if (card) {
                        card.style.borderColor = 'var(--border-admin)';
                        card.style.boxShadow = 'none';
                    }
                }
            });
            
            updateAdminSelectionToolbar();
        });
    }
}

function handleAdminPhotoCheck(photoId, isChecked) {
    const card = document.getElementById(`photo-card-${photoId}`);
    if (isChecked) {
        window.adminSelectedPhotoIds.add(photoId);
        if (card) {
            card.style.borderColor = '#f43f5e';
            card.style.boxShadow = '0 0 16px rgba(244, 63, 94, 0.4)';
        }
    } else {
        window.adminSelectedPhotoIds.delete(photoId);
        if (card) {
            card.style.borderColor = 'var(--border-admin)';
            card.style.boxShadow = 'none';
        }
    }

    const allCheckboxes = document.querySelectorAll('.admin-photo-chk');
    const selectAllCheckbox = document.getElementById('adminSelectAll');
    if (selectAllCheckbox && allCheckboxes.length > 0) {
        selectAllCheckbox.checked = (window.adminSelectedPhotoIds.size === allCheckboxes.length);
    }

    updateAdminSelectionToolbar();
}

function updateAdminSelectionToolbar() {
    const deleteBtn = document.getElementById('adminDeleteBtn');
    const deleteBtnText = document.getElementById('adminDeleteBtnText');
    const allCheckboxes = document.querySelectorAll('.admin-photo-chk');
    const totalPhotos = allCheckboxes.length;
    const selectedCount = window.adminSelectedPhotoIds.size;

    if (!deleteBtn) return;

    if (selectedCount === 0) {
        deleteBtn.style.display = 'none';
    } else {
        deleteBtn.style.display = 'inline-flex';
        if (selectedCount === totalPhotos && totalPhotos > 1) {
            deleteBtnText.textContent = `Delete All (${totalPhotos} Photos)`;
        } else {
            deleteBtnText.textContent = `Delete Selected (${selectedCount})`;
        }
    }
}

async function executeDeletePhotos() {
    const count = window.adminSelectedPhotoIds.size;
    if (count === 0) return;

    const allCheckboxes = document.querySelectorAll('.admin-photo-chk');
    const isAll = (count === allCheckboxes.length);
    const confirmMsg = isAll 
        ? `⚠️ Are you sure you want to PERMANENTLY DELETE ALL ${count} PHOTOS and their entire face index database? This cannot be undone!`
        : `⚠️ Are you sure you want to permanently delete all ${count} selected photo(s)?`;

    if (!confirm(confirmMsg)) return;

    const idsArray = Array.from(window.adminSelectedPhotoIds);
    const deleteBtn = document.getElementById('adminDeleteBtn');
    if (deleteBtn) {
        deleteBtn.disabled = true;
        deleteBtn.innerHTML = '<i class="ri-loader-4-line ri-spin"></i> Deleting...';
    }

    try {
        const res = await fetch('<?php echo BASE_URL; ?>/api/delete_photo.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids: idsArray })
        });
        const data = await res.json();

        if (data.success) {
            idsArray.forEach(id => {
                const card = document.getElementById(`photo-card-${id}`);
                if (card) card.remove();
            });

            window.adminSelectedPhotoIds.clear();
            updateAdminSelectionToolbar();

            const selectAllCheckbox = document.getElementById('adminSelectAll');
            if (selectAllCheckbox) selectAllCheckbox.checked = false;

            const remaining = document.querySelectorAll('.admin-photo-chk').length;
            const totalCountEl = document.getElementById('libraryTotalCount');
            if (totalCountEl) totalCountEl.textContent = remaining;

            updateLibraryStatPills();

            if (remaining === 0) {
                const grid = document.getElementById('adminPhotoGrid');
                if (grid) grid.style.display = 'none';
                const placeholder = document.getElementById('noPhotosPlaceholder');
                if (placeholder) placeholder.style.display = 'block';
                const selectWrapper = document.getElementById('adminSelectAllWrapper');
                if (selectWrapper) selectWrapper.style.display = 'none';
            }
        } else {
            alert(data.message || 'Error deleting photos');
        }
    } catch (err) {
        alert('Network error: ' + err.message);
    } finally {
        if (deleteBtn) {
            deleteBtn.disabled = false;
            updateAdminSelectionToolbar();
        }
    }
}

async function deleteSinglePhoto(id) {
    if (!confirm('Are you sure you want to permanently delete this photo and its face embeddings?')) return;

    try {
        const res = await fetch(`<?php echo BASE_URL; ?>/api/delete_photo.php?id=${id}`, {
            method: 'DELETE'
        });
        const data = await res.json();
        if (data.success) {
            const card = document.getElementById(`photo-card-${id}`);
            if (card) card.remove();

            window.adminSelectedPhotoIds.delete(id);
            updateAdminSelectionToolbar();

            const remaining = document.querySelectorAll('.admin-photo-chk').length;
            const totalCountEl = document.getElementById('libraryTotalCount');
            if (totalCountEl) totalCountEl.textContent = remaining;

            updateLibraryStatPills();

            if (remaining === 0) {
                const grid = document.getElementById('adminPhotoGrid');
                if (grid) grid.style.display = 'none';
                const placeholder = document.getElementById('noPhotosPlaceholder');
                if (placeholder) placeholder.style.display = 'block';
                const selectWrapper = document.getElementById('adminSelectAllWrapper');
                if (selectWrapper) selectWrapper.style.display = 'none';
            }
        } else {
            alert(data.message || 'Error deleting photo');
        }
    } catch (err) {
        alert('Network error: ' + err.message);
    }
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
