<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/api/db.php';

$db = Database::getConnection();
$totalPhotos = (int)$db->query("SELECT COUNT(*) FROM photos")->fetchColumn();
$eventBanner = get_setting('event_banner', '');
$organizersBanner = get_setting('organizers_banner', '');
$siteName = 'MYPIC';
$googleVerification = get_setting('google_site_verification', '');
if (get_setting('site_name', '') !== 'MYPIC') {
    @$db->exec("UPDATE settings SET key_value = 'MYPIC' WHERE key_name = 'site_name'");
}

// Compute Banner URLs for instant preloading & display
$eventBannerUrl = '';
if (!empty($eventBanner) && (file_exists(ROOT_DIR . '/' . $eventBanner) || strpos($eventBanner, 'http') === 0)) {
    $eventBannerUrl = (strpos($eventBanner, 'http') === 0 ? $eventBanner : BASE_URL . '/' . $eventBanner);
} else {
    $eventBannerUrl = BASE_URL . '/assets/images/promo-banner.jpg';
}

$organizersBannerUrl = '';
if (!empty($organizersBanner) && (file_exists(ROOT_DIR . '/' . $organizersBanner) || strpos($organizersBanner, 'http') === 0)) {
    $organizersBannerUrl = (strpos($organizersBanner, 'http') === 0 ? $organizersBanner : BASE_URL . '/' . $organizersBanner);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    
    <!-- Primary SEO Meta Tags (Optimized for Google Top Ranking for 'MYPIC') -->
    <title>MYPIC - AI Face Recognition Event Photo Finder</title>
    <meta name="title" content="MYPIC - AI Face Recognition Event Photo Finder">
    <meta name="description" content="MYPIC is the premier AI face recognition event photo finder. Scan your face or upload a photo to find and download all your event pictures in seconds.">
    <meta name="keywords" content="MYPIC, mypic, my pic, AI photo finder, face recognition photo finder, event photo finder, facial recognition search, eppe tarun, event photography portal, photo retrieval">
    <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
    <link rel="canonical" href="<?php echo BASE_URL; ?>/">
    <meta name="author" content="Eppe Tarun">
    <meta name="application-name" content="MYPIC">
    <meta name="apple-mobile-web-app-title" content="MYPIC">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#4f46e5">

    <?php if (!empty($googleVerification)): ?>
    <!-- Google Search Console Ownership Verification -->
    <meta name="google-site-verification" content="<?php echo htmlspecialchars($googleVerification); ?>">
    <?php endif; ?>

    <!-- Favicon & Brand Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo BASE_URL; ?>/assets/images/favicon.png">
    <link rel="apple-touch-icon" href="<?php echo BASE_URL; ?>/assets/images/favicon.png">
    <link rel="shortcut icon" href="<?php echo BASE_URL; ?>/favicon.ico">

    <!-- OpenGraph / Facebook / WhatsApp -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo BASE_URL; ?>/">
    <meta property="og:site_name" content="MYPIC">
    <meta property="og:title" content="MYPIC - AI Face Recognition Event Photo Finder">
    <meta property="og:description" content="Find and download all your event photos instantly with MYPIC AI biometric facial recognition. Fast, private, and effortless.">
    <meta property="og:image" content="<?php echo BASE_URL; ?>/assets/images/logo.png">
    <meta property="og:image:width" content="1024">
    <meta property="og:image:height" content="1024">
    <meta property="og:image:alt" content="MYPIC Official Logo">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?php echo BASE_URL; ?>/">
    <meta name="twitter:title" content="MYPIC - AI Face Recognition Event Photo Finder">
    <meta name="twitter:description" content="Find and download all your event photos instantly with MYPIC AI biometric facial recognition.">
    <meta name="twitter:image" content="<?php echo BASE_URL; ?>/assets/images/logo.png">

    <!-- Schema.org JSON-LD Structured Data for Google Rich Results & Knowledge Graph -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@graph": [
        {
          "@type": "WebSite",
          "@id": "<?php echo BASE_URL; ?>/#website",
          "url": "<?php echo BASE_URL; ?>/",
          "name": "MYPIC",
          "alternateName": ["MYPIC", "mypic", "MyPic", "MY PIC", "MYPIC AI Photo Finder", "MYPIC Face Recognition"],
          "description": "Premier AI Face Recognition Event Photo Finder to search and download event photos instantly.",
          "inLanguage": "en"
        },
        {
          "@type": "Organization",
          "@id": "<?php echo BASE_URL; ?>/#organization",
          "name": "MYPIC",
          "url": "<?php echo BASE_URL; ?>/",
          "logo": {
            "@type": "ImageObject",
            "url": "<?php echo BASE_URL; ?>/assets/images/logo.png",
            "caption": "MYPIC Official Logo"
          },
          "founder": {
            "@type": "Person",
            "name": "Eppe Tarun"
          }
        },
        {
          "@type": "WebApplication",
          "@id": "<?php echo BASE_URL; ?>/#webapp",
          "name": "MYPIC",
          "url": "<?php echo BASE_URL; ?>/",
          "applicationCategory": "MultimediaApplication",
          "operatingSystem": "All",
          "description": "Instant biometric face recognition search to find and download all your event photos.",
          "image": "<?php echo BASE_URL; ?>/assets/images/logo.png",
          "offers": {
            "@type": "Offer",
            "price": "0",
            "priceCurrency": "USD"
          }
        }
      ]
    }
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css?v=<?php echo time(); ?>">

    <!-- Preload Instant AI Face Models for Sub-Second Detection -->
    <link rel="preload" href="<?php echo BASE_URL; ?>/assets/models/tiny_face_detector_model-weights_manifest.json" as="fetch" crossorigin>
    <link rel="preload" href="<?php echo BASE_URL; ?>/assets/models/tiny_face_detector_model-shard1" as="fetch" crossorigin>
    <link rel="preload" href="<?php echo BASE_URL; ?>/assets/models/face_landmark_68_model-weights_manifest.json" as="fetch" crossorigin>
    <link rel="preload" href="<?php echo BASE_URL; ?>/assets/models/face_landmark_68_model-shard1" as="fetch" crossorigin>

    <!-- Preload Banners for Instant 0ms Paint & Elimination of Slow Dropping -->
    <?php if (!empty($eventBannerUrl)): ?>
    <link rel="preload" as="image" href="<?php echo htmlspecialchars($eventBannerUrl); ?>" fetchpriority="high">
    <?php endif; ?>
    <?php if (!empty($organizersBannerUrl)): ?>
    <link rel="preload" as="image" href="<?php echo htmlspecialchars($organizersBannerUrl); ?>">
    <?php endif; ?>
</head>
<body>

    <!-- Global Website Loading Screen with Animated Logo -->
    <div id="pagePreloader" class="page-preloader" aria-hidden="false">
        <div class="preloader-content">
            <div class="preloader-logo-wrap">
                <div class="preloader-aura"></div>
                <picture>
                    <source srcset="<?php echo BASE_URL; ?>/assets/images/logo-icon-opt.webp" type="image/webp">
                    <img src="<?php echo BASE_URL; ?>/assets/images/logo-icon-opt.png" alt="MYPIC" class="preloader-logo" width="76" height="76" fetchpriority="high">
                </picture>
            </div>
            <div class="preloader-brand">
                <span class="preloader-brand-title">MYPIC</span>
                <span class="preloader-brand-sub">FEEL THE PIXEL IN EVERY MOVEMENT</span>
            </div>
            <div class="preloader-bar">
                <div class="preloader-bar-fill"></div>
            </div>
        </div>
    </div>

    <!-- Top Navigation Bar -->
    <nav class="navbar">
        <div class="container nav-content">
            <a href="<?php echo BASE_URL; ?>/" class="brand" aria-label="MYPIC Home" title="MYPIC - AI Face Recognition Photo Finder">
                <img src="<?php echo BASE_URL; ?>/assets/images/logo-horizontal.png" alt="MYPIC" class="brand-logo-img">
                <span class="sr-only"><?php echo htmlspecialchars($siteName); ?></span>
            </a>
        </div>
    </nav>

    <!-- Main Content Container (Compact Width) -->
    <main class="container">
        
        <!-- Accessible SEO Heading Structure for Search Engines -->
        <header class="sr-only">
            <h1>MYPIC - AI Face Recognition Event Photo Finder</h1>
            <p>Welcome to MYPIC, the official AI-powered biometric face recognition portal. Find, view, and download all your event and party photos in seconds using state-of-the-art computer vision.</p>
            <h2>Instant Biometric Search for Event Photos</h2>
        </header>
        
        <!-- Event / Website Promo Banner -->
        <div class="event-banner-container">
            <img src="<?php echo htmlspecialchars($eventBannerUrl); ?>" alt="Event Banner" class="event-banner-img" fetchpriority="high" decoding="async">
        </div>

        <!-- Redesigned Biometric HUD Scanning Container -->
        <section class="scanner-card" id="scannerCard">
            <!-- 1. Mode Switcher Tabs -->
            <div class="scanner-tabs">
                <button class="tab-btn active" data-tab="camera">
                    <i class="ri-flashlight-fill"></i>
                    <span>Auto Scan</span>
                </button>
                <button class="tab-btn" data-tab="upload">
                    <i class="ri-upload-cloud-2-line"></i>
                    <span>Upload Photo</span>
                </button>
            </div>

            <!-- 2. Live Camera Scanner Viewport & HUD Deck -->
            <div id="cameraView">
                <!-- Clean Camera Viewfinder Viewport -->
                <div class="camera-wrapper" id="cameraWrapper">
                    <video id="webcamVideo" class="camera-video" autoplay playsinline webkit-playsinline muted style="display: none;"></video>
                    <canvas id="cameraLiveCanvas" class="camera-live-canvas"></canvas>
                    
                    <div class="camera-overlay" id="cameraOverlay" style="display: none;">
                        <div class="scanner-hud-box" id="scannerHudBox">
                            <div class="hud-corner top-left"></div>
                            <div class="hud-corner top-right"></div>
                            <div class="hud-corner bottom-left"></div>
                            <div class="hud-corner bottom-right"></div>

                            <div class="face-guide-oval" id="faceGuideOval">
                                <div class="laser-scanner-beam" id="laserScannerBeam"></div>
                            </div>
                        </div>

                        <div class="face-scan-timer" id="faceScanTimer">
                            <i class="ri-loader-4-line ri-spin" id="scanTimerIcon"></i>
                            <div class="scan-timer-content">
                                <span id="faceScanTimerText">Scanning your face, please wait...</span>
                                <div class="scan-progress-line" id="scanProgressLine">
                                    <div class="scan-progress-bar" id="scanProgressBar"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 1. Initial State Card: Camera is OFF by default until user taps "Scan Face" -->
                    <div class="camera-prompt-card" id="cameraPromptCard">
                        <div class="prompt-camera-icon"><i class="ri-user-search-line"></i></div>
                        <div style="font-weight: 700; font-size: 1.1rem; color: #ffffff; margin-top: 8px;">Scan Your Face Here</div>
                        <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 4px; margin-bottom: 16px;">Tap to open camera & auto-find your photos</div>
                        <button type="button" class="btn btn-primary" id="startScanBtn" onclick="FaceScanner.startCameraAndAutoScan()">
                            <i class="ri-camera-lens-line"></i> Scan Face
                        </button>
                    </div>

                    <!-- 2. Scanning Complete State: Shown after successful scan -->
                    <div class="camera-success-card" id="cameraSuccessCard" style="display: none;">
                        <div class="success-check-icon"><i class="ri-checkbox-circle-fill"></i></div>
                        <div style="font-weight: 700; font-size: 1.05rem; color: var(--accent-emerald); margin-top: 6px;">Scanning Successful!</div>
                        <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 2px; margin-bottom: 14px;">Camera is turned off</div>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="FaceScanner.startCameraAndAutoScan()">
                            <i class="ri-camera-lens-line"></i> Scan Again
                        </button>
                    </div>

                    <!-- Top-Right Floating Camera Switcher Button (shown only when camera is live) -->
                    <button type="button" class="switch-camera-btn" id="switchCameraBtn" title="Switch Front/Rear Camera" aria-label="Switch Camera" style="display: none;">
                        <i class="ri-camera-switch-line"></i>
                    </button>
                </div>

                <!-- Live Scanner Status Feedback -->
                <div class="scanner-status-deck">
                    <span class="status-dot active" id="scannerStatusDot"></span>
                    <span id="scannerStatusText" style="font-size: 0.82rem; font-weight: 600; color: var(--text-secondary);">Tap "Scan Face" to find your photos</span>
                </div>
            </div>

            <!-- 3. Selfie Upload Dropzone View -->
            <div id="uploadView" style="display: none;">
                <div class="upload-dropzone" id="uploadDropzone">
                    <input type="file" id="selfieFileInput" accept="image/*" style="display: none;">
                    <div class="dropzone-icon"><i class="ri-image-add-line"></i></div>
                    <h3>Upload a Photo or Selfie</h3>
                    <p>Tap to choose from photo gallery or camera</p>
                    <button type="button" class="btn btn-secondary btn-sm"><i class="ri-folder-image-line"></i> Choose Photo</button>
                </div>

                <!-- Detected Faces in Uploaded Photo -->
                <div id="uploadedFacesSection" style="display: none; margin-top: 14px;">
                    <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-secondary); margin-bottom: 6px;">
                        Multiple faces found. Tap your face to search:
                    </div>
                    <div class="face-picker-grid" id="facePickerGrid"></div>
                </div>

                <!-- Preview of single uploaded selfie -->
                <div id="uploadPreviewWrapper" style="display: none; text-align: center; margin-top: 16px;">
                    <img id="uploadPreviewImg" style="width: 90px; height: 90px; border-radius: 50%; border: 2px solid var(--primary); object-fit: cover; box-shadow: var(--shadow-sm);">
                    <div style="font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-top: 6px;">Processing Face Vectors...</div>
                </div>
            </div>

            <!-- Privacy Assurance Badge -->
            <div class="scanner-privacy-bar">
                <i class="ri-shield-check-fill"></i>
                <span>Private & Secure • Biometric data never stored</span>
            </div>
        </section>

        <!-- Search Results Grid Section -->
        <section class="results-section" id="resultsSection" style="display: none;">
            <div class="results-header">
                <div>
                    <div class="results-title">
                        <i class="ri-sparkling-fill" style="color: var(--primary);"></i>
                        <span>Matched Photos</span>
                        <span class="results-count-badge" id="resultsCount">0 Photos</span>
                    </div>
                    <div style="font-size: 0.82rem; font-weight: 500; color: var(--text-secondary); margin-top: 2px;">
                        All photos identified with your face
                    </div>
                </div>

                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <button type="button" class="btn btn-secondary btn-sm" id="toggleSelectModeBtn" style="display: none;">
                        <i class="ri-checkbox-multiple-line"></i> Select
                    </button>
                    <label id="selectAllWrapper" style="display: none; align-items: center; gap: 8px; background: #ffffff; padding: 6px 14px; border-radius: var(--radius-full); cursor: pointer; border: 1px solid var(--border-subtle); font-size: 0.8rem; font-weight: 700; color: var(--text-primary); user-select: none;">
                        <input type="checkbox" id="selectAllCheckbox" style="width: 15px; height: 15px; accent-color: var(--primary); cursor: pointer;">
                        <span id="selectAllLabel">All</span>
                    </label>
                    <button class="btn btn-primary btn-sm" id="downloadSelectedBtn" style="display: none;">
                        <i class="ri-download-2-line"></i> Download (<span id="selectedCountNum">0</span>)
                    </button>
                </div>
            </div>

            <div class="photo-grid" id="resultsGrid"></div>
        </section>

        <!-- Organizers & Sponsors Banner (Displayed only if uploaded by Admin) -->
        <?php if (!empty($organizersBannerUrl)): ?>
            <section class="organizers-banner-section" aria-label="Event Organizers and Partners">
                <div class="organizers-banner-container">
                    <img src="<?php echo htmlspecialchars($organizersBannerUrl); ?>" alt="Event Organizers & Sponsors" class="organizers-banner-img" fetchpriority="high" decoding="async">
                </div>
            </section>
        <?php endif; ?>

    </main>

    <!-- Developer Footer -->
    <footer style="border-top: 1px solid var(--border-subtle); padding: 24px 0 28px; margin-top: 48px; text-align: center; color: var(--text-muted); font-size: 0.85rem; background: #ffffff;">
        <div class="container" style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px;">
            <div style="display: inline-flex; align-items: center; gap: 10px; background: var(--bg-surface); padding: 6px 16px; border-radius: 50px; border: 1px solid var(--border-subtle);">
                <img src="<?php echo BASE_URL; ?>/assets/images/developer.jpg" alt="Eppe Tarun" title="Developer Profile" onclick="Lightbox.open('<?php echo BASE_URL; ?>/assets/images/developer.jpg', 'Eppe Tarun', '', true)" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; object-position: center top; border: 2px solid var(--primary); cursor: pointer;" loading="lazy" decoding="async">
                <span style="font-size: 0.84rem; color: var(--text-secondary); font-weight: 500;">
                    Developed by <strong style="color: var(--text-primary); font-weight: 700; cursor: pointer;" onclick="Lightbox.open('<?php echo BASE_URL; ?>/assets/images/developer.jpg', 'Eppe Tarun', '', true)">Eppe Tarun</strong>
                </span>
            </div>
            <p style="font-size: 0.8rem; font-weight: 500; color: var(--text-muted); display: flex; align-items: center; justify-content: center; gap: 8px; flex-wrap: wrap;">
                <span>&copy; <?php echo date('Y'); ?> MYPIC • All rights reserved.</span>
                <span>•</span>
                <a href="<?php echo BASE_URL; ?>/admin/login.php" style="color: var(--primary); text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                    <i class="ri-shield-keyhole-line"></i> Admin Login
                </a>
            </p>
        </div>
    </footer>

    <!-- Full-Screen Fit-to-Screen Photo Viewer Modal with Zoom, Swipe & Top-Right Download Icon -->
    <div class="lightbox-modal" id="lightboxModal">
        <div class="lightbox-content">
            <!-- Loading Spinner while fetching next/prev image -->
            <div class="lightbox-spinner" id="lightboxSpinner">
                <div class="spinner-ring"></div>
            </div>

            <!-- Top Right Small Transparent Download Icon -->
            <a href="#" id="lightboxDownload" class="lightbox-download-icon" download title="Download Photo" aria-label="Download Photo" onclick="event.stopPropagation();">
                <i class="ri-download-2-line"></i>
            </a>
            
            <div class="lightbox-img-container" id="lightboxImgContainer">
                <img src="" class="lightbox-img" id="lightboxImg" alt="Full Photo">
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="<?php echo BASE_URL; ?>/assets/js/face-api.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/main.js?v=<?php echo time(); ?>"></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/face-scanner.js?v=<?php echo time(); ?>"></script>
    <script>
        // Page Preloader Dismissal (Waits until website is fully loaded)
        // Page Preloader Dismissal (Synchronized with banner & critical DOM readiness)
        (function() {
            const preloader = document.getElementById('pagePreloader');
            if (!preloader) return;
            let isDismissed = false;

            function dismissPreloader() {
                if (isDismissed) return;
                isDismissed = true;
                preloader.classList.add('fade-out');
                preloader.setAttribute('aria-hidden', 'true');
                setTimeout(() => {
                    if (preloader.parentNode) preloader.parentNode.removeChild(preloader);
                }, 450);
            }

            // Ensure event banner is decoded before revealing to eliminate slow dropping
            const bannerImg = document.querySelector('.event-banner-img');
            if (bannerImg && !bannerImg.complete) {
                bannerImg.addEventListener('load', () => setTimeout(dismissPreloader, 80), { once: true });
                bannerImg.addEventListener('error', () => dismissPreloader(), { once: true });
            } else {
                if (document.readyState === 'complete') {
                    setTimeout(dismissPreloader, 150);
                } else {
                    window.addEventListener('load', () => setTimeout(dismissPreloader, 150), { once: true });
                }
            }

            // Rapid safety timeout (maximum 1.2s so user is never kept waiting)
            setTimeout(dismissPreloader, 1200);
        })();

        document.addEventListener('DOMContentLoaded', () => {
            FaceScanner.init('<?php echo BASE_URL; ?>/assets/models');
        });
    </script>
</body>
</html>
