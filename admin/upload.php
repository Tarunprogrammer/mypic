<?php
$pageTitle = 'Batch Upload Photos';
require_once __DIR__ . '/header.php';
?>

<div class="admin-card">
    <div class="card-header-row" style="flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between;">
        <div>
            <div class="card-title">⚡ Turbo AI Batch Photo Uploader & Face Indexer</div>
            <div style="font-size: 0.85rem; color: #94a3b8; margin-top: 2px;">
                High-Speed Multi-Threaded Ingestion Engine with automatic face extraction and MySQL embedding indexing.
            </div>
        </div>
        <div id="modelStatusIndicator" class="status-badge badge-pass" style="background: rgba(16, 185, 129, 0.15); color: #34d399; border-color: rgba(16, 185, 129, 0.3);">
            ⚡ Turbo Ingestion Ready (3x Parallel)
        </div>
    </div>

    <!-- Speed Boost Info Pills -->
    <div style="display: flex; gap: 8px; flex-wrap: wrap; margin: 16px 0 20px;">
        <span style="display: inline-flex; align-items: center; gap: 6px; background: rgba(99, 102, 241, 0.15); border: 1px solid rgba(99, 102, 241, 0.3); color: #c7d2fe; font-size: 0.78rem; font-weight: 700; padding: 4px 12px; border-radius: 9999px;">
            <i class="ri-cpu-line"></i> 3x Parallel AI Scanning Agents
        </span>
        <span style="display: inline-flex; align-items: center; gap: 6px; background: rgba(236, 72, 153, 0.15); border: 1px solid rgba(236, 72, 153, 0.3); color: #fbcfe8; font-size: 0.78rem; font-weight: 700; padding: 4px 12px; border-radius: 9999px;">
            <i class="ri-flashlight-line"></i> Fast Scaled Offscreen Vector Scan
        </span>
        <span style="display: inline-flex; align-items: center; gap: 6px; background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #a7f3d0; font-size: 0.78rem; font-weight: 700; padding: 4px 12px; border-radius: 9999px;">
            <i class="ri-hd-line"></i> High-Fidelity Original Quality (Up to ~2MB)
        </span>
    </div>

    <!-- Drag & Drop Zone -->
    <div class="upload-dropzone" id="adminDropzone" style="background: rgba(8, 12, 22, 0.55); border: 2px dashed rgba(255, 255, 255, 0.18); padding: 54px 20px; border-radius: 18px; cursor: pointer; transition: all 0.2s;">
        <input type="file" id="adminFileInput" multiple accept="image/jpeg,image/png,image/webp" style="display: none;">
        <div class="dropzone-icon" style="font-size: 52px; color: #ec4899; margin-bottom: 12px;"><i class="ri-upload-cloud-2-line"></i></div>
        <h3 style="font-family: var(--font-heading); font-size: 1.35rem; font-weight: 800; color: #ffffff; margin-bottom: 6px;">Drop Multiple Photos Here for Instant Upload</h3>
        <p style="color: #94a3b8; font-size: 0.9rem; margin-bottom: 18px;">Select 10, 50, or 100+ event photos at once (JPG, PNG, WEBP)</p>
        <span class="btn btn-primary btn-sm" style="padding: 9px 22px; font-weight: 800;"><i class="ri-folder-open-line"></i> Choose Photos</span>
    </div>

    <!-- Queue & Progress Section -->
    <div id="queueStatsSection" style="display: none; margin-top: 28px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
            <span id="queueSummaryText" style="font-weight: 700; color: #ffffff; font-size: 0.95rem;">⚡ Queue: 0 photos</span>
            <div>
                <button id="clearQueueBtn" class="btn btn-secondary btn-sm" style="background: rgba(244, 63, 94, 0.15); color: #fda4af; border-color: rgba(244, 63, 94, 0.3);"><i class="ri-delete-bin-line"></i> Clear Queue</button>
            </div>
        </div>

        <div class="progress-bar-container" style="background: rgba(255,255,255,0.08); border-radius: 9999px; height: 10px; overflow: hidden; margin-bottom: 20px;">
            <div class="progress-bar-fill" id="batchProgressBar" style="height: 100%; width: 0%; background: var(--grad-primary); border-radius: 9999px; transition: width 0.3s ease;"></div>
        </div>

        <!-- Preview Grid of Queued Photos with Face Boxes -->
        <div class="upload-queue-grid" id="uploadQueueGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px;"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    if (window.AdminUploader) {
        window.AdminUploader.init('<?php echo BASE_URL; ?>/assets/models');
    }
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
