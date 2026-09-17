<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../api/db.php';
require_admin_login();

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : ''; ?>MYPIC Admin</title>

    <!-- Favicon & Brand Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo BASE_URL; ?>/assets/images/favicon.png">
    <link rel="apple-touch-icon" href="<?php echo BASE_URL; ?>/assets/images/favicon.png">
    <link rel="shortcut icon" href="<?php echo BASE_URL; ?>/favicon.ico">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/admin.css?v=<?php echo time(); ?>">

    <!-- Preload Instant AI Face Models for High-Speed Admin Batch Ingestion -->
    <link rel="preload" href="<?php echo BASE_URL; ?>/assets/models/ssd_mobilenetv1_model-weights_manifest.json" as="fetch" crossorigin>
    <link rel="preload" href="<?php echo BASE_URL; ?>/assets/models/ssd_mobilenetv1_model-shard1" as="fetch" crossorigin>
    <link rel="preload" href="<?php echo BASE_URL; ?>/assets/models/ssd_mobilenetv1_model-shard2" as="fetch" crossorigin>
    <link rel="preload" href="<?php echo BASE_URL; ?>/assets/models/tiny_face_detector_model-weights_manifest.json" as="fetch" crossorigin>
    <link rel="preload" href="<?php echo BASE_URL; ?>/assets/models/tiny_face_detector_model-shard1" as="fetch" crossorigin>
    <link rel="preload" href="<?php echo BASE_URL; ?>/assets/models/face_landmark_68_model-weights_manifest.json" as="fetch" crossorigin>
    <link rel="preload" href="<?php echo BASE_URL; ?>/assets/models/face_landmark_68_model-shard1" as="fetch" crossorigin>

    <!-- AI Models & Ingestion Scripts -->
    <script>window.BASE_URL = '<?php echo BASE_URL; ?>';</script>
    <script src="<?php echo BASE_URL; ?>/assets/js/face-api.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/face-engine.js?v=<?php echo time(); ?>"></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/main.js?v=<?php echo time(); ?>"></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/admin-uploader.js?v=<?php echo time(); ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (window.AdminUploader) {
                window.AdminUploader.init('<?php echo BASE_URL; ?>/assets/models');
            }
        });
    </script>
</head>
<body class="admin-body">
    <!-- Sidebar -->
    <aside class="admin-sidebar" id="adminSidebar">
        <a href="<?php echo BASE_URL; ?>/admin/index.php" class="sidebar-brand">
            <img src="<?php echo BASE_URL; ?>/assets/images/logo-icon.png" alt="MYPIC" class="sidebar-brand-logo">
            <div class="sidebar-brand-info">
                <span class="sidebar-brand-name">MYPIC</span>
                <span class="sidebar-brand-badge">ADMIN</span>
            </div>
        </a>

        <ul class="sidebar-menu">
            <li>
                <a href="<?php echo BASE_URL; ?>/admin/index.php" class="sidebar-link <?php echo $currentPage === 'index' ? 'active' : ''; ?>">
                    <i class="ri-dashboard-3-line"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>/admin/upload.php" class="sidebar-link <?php echo $currentPage === 'upload' ? 'active' : ''; ?>">
                    <i class="ri-upload-cloud-2-line"></i>
                    <span>Batch Upload</span>
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>/admin/photos.php" class="sidebar-link <?php echo $currentPage === 'photos' ? 'active' : ''; ?>">
                    <i class="ri-gallery-line"></i>
                    <span>Photo Library</span>
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>/admin/events.php" class="sidebar-link <?php echo $currentPage === 'events' ? 'active' : ''; ?>">
                    <i class="ri-calendar-event-line"></i>
                    <span>Events & Albums</span>
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>/admin/settings.php" class="sidebar-link <?php echo $currentPage === 'settings' ? 'active' : ''; ?>">
                    <i class="ri-settings-3-line"></i>
                    <span>System Settings</span>
                </a>
            </li>
            <li style="margin-top: 14px; border-top: 1px solid var(--border-admin); padding-top: 14px;">
                <a href="<?php echo BASE_URL; ?>/" target="_blank" class="sidebar-link">
                    <i class="ri-external-link-line"></i>
                    <span>View Public Site</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-footer">
            <div style="font-size: 0.85rem;">
                <div style="font-weight: 700; color: var(--text-primary);"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></div>
                <div style="color: var(--text-muted); font-size: 0.75rem;">@<?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'admin'); ?></div>
            </div>
            <a href="<?php echo BASE_URL; ?>/admin/logout.php" style="color: #f43f5e; text-decoration: none; font-size: 1.25rem; display: flex; align-items: center;" title="Logout">
                <i class="ri-logout-box-r-line"></i>
            </a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="admin-main">
        <header class="admin-header">
            <div style="display: flex; align-items: center;">
                <button type="button" class="sidebar-toggle-btn" id="sidebarToggleBtn" title="Toggle Menu" aria-label="Toggle Menu">
                    <i class="ri-menu-line"></i>
                </button>
                <div id="adminPageHeaderTitle" style="font-family: var(--font-heading); font-size: 1.25rem; font-weight: 800; color: var(--text-primary); letter-spacing: -0.01em;">
                    <?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Admin Panel'; ?>
                </div>
            </div>
            
            <!-- Global Persistent Upload Badge -->
            <div id="globalUploadProgress" style="display: none; align-items: center; gap: 8px; background: rgba(99, 102, 241, 0.2); border: 1px solid rgba(99, 102, 241, 0.45); color: #c7d2fe; padding: 6px 16px; border-radius: 9999px; font-size: 0.82rem; font-weight: 700;">
                <span style="display: inline-block; animation: spin 1s linear infinite;"><i class="ri-loader-4-line"></i></span>
                <span id="globalUploadCount">Processing photos...</span>
            </div>
        </header>

        <div class="admin-container">
