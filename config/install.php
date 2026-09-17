<?php
/**
 * Database Auto-Installer & System Diagnostic Tool
 * Face Recognition Photo Retrieval System
 */

require_once __DIR__ . '/config.php';

$message = '';
$error = '';
$isInstalled = false;
$diagnostics = [];

// Diagnostic checks
$diagnostics['php_version'] = [
    'name' => 'PHP Version (>= 7.4)',
    'status' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'detail' => PHP_VERSION
];

$diagnostics['pdo_mysql'] = [
    'name' => 'PDO MySQL Extension',
    'status' => extension_loaded('pdo_mysql'),
    'detail' => extension_loaded('pdo_mysql') ? 'Enabled' : 'Disabled'
];

$diagnostics['gd'] = [
    'name' => 'GD Image Processing Extension',
    'status' => extension_loaded('gd'),
    'detail' => extension_loaded('gd') ? 'Enabled' : 'Disabled'
];

$diagnostics['uploads_dir'] = [
    'name' => 'Upload Directories Writable',
    'status' => is_writable(ROOT_DIR),
    'detail' => is_writable(ROOT_DIR) ? 'Writable' : 'Permission Error'
];

// Check model files
$requiredModels = [
    'ssd_mobilenetv1_model-weights_manifest.json',
    'ssd_mobilenetv1_model-shard1',
    'face_landmark_68_model-weights_manifest.json',
    'face_recognition_model-weights_manifest.json'
];
$modelsOk = true;
foreach ($requiredModels as $mf) {
    if (!file_exists(MODEL_DIR . '/' . $mf)) {
        $modelsOk = false;
        break;
    }
}
$diagnostics['ai_models'] = [
    'name' => 'Pre-trained AI Face Recognition Models',
    'status' => $modelsOk,
    'detail' => $modelsOk ? 'All Installed in /assets/models' : 'Missing some model files'
];

// Handle installation POST or CLI
$doInstall = (isset($_POST['install']) || php_sapi_name() === 'cli');

if ($doInstall) {
    try {
        // Create necessary upload directories
        $dirs = [UPLOAD_DIR, PHOTO_DIR, THUMB_DIR, FACE_DIR, MODEL_DIR];
        foreach ($dirs as $d) {
            if (!is_dir($d)) {
                mkdir($d, 0777, true);
            }
        }

        // 1. Connect without db specified to create database
        $pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4", DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        // 2. Connect to the created database
        $db = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // 3. Read and execute database.sql
        $sqlFile = ROOT_DIR . '/database.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception("database.sql file not found in " . ROOT_DIR);
        }

        $sql = file_get_contents($sqlFile);
        // Remove comments and execute statements
        $db->exec($sql);

        // Ensure clean, verified password hash for default admin
        $adminHash = password_hash('admin123', PASSWORD_BCRYPT);
        $updateAdmin = $db->prepare("UPDATE admins SET password = ? WHERE username = 'admin'");
        $updateAdmin->execute([$adminHash]);

        $message = "Database & tables created successfully! Default admin account is ready.";
        $isInstalled = true;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
} else {
    // Check if already installed
    try {
        $testDb = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $stmt = $testDb->query("SHOW TABLES LIKE 'photos'");
        if ($stmt && $stmt->rowCount() > 0) {
            $isInstalled = true;
        }
    } catch (Exception $e) {
        $isInstalled = false;
    }
}

if (php_sapi_name() === 'cli') {
    if ($message) echo "[SUCCESS] " . $message . "\n";
    if ($error) echo "[ERROR] " . $error . "\n";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - Face Recognition Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <style>
        .install-container {
            max-width: 680px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 20px;
            padding: 36px;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.08), 0 0 1px 1px rgba(0, 0, 0, 0.05);
        }
        .diag-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            background: #f8fafc;
            border-radius: 12px;
            margin-bottom: 10px;
            font-size: 0.95rem;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .badge-pass { background: #dcfce7; color: #15803d; }
        .badge-fail { background: #fee2e2; color: #b91c1c; }
        .btn-install {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: #ffffff;
            border: none;
            padding: 14px 28px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            width: 100%;
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px 0 rgba(79, 70, 229, 0.35);
        }
        .btn-install:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px 0 rgba(79, 70, 229, 0.45);
        }
    </style>
</head>
<body style="background: #f1f5f9; min-height: 100vh; display: flex; align-items: center; padding: 20px;">
    <div class="install-container">
        <div style="text-align: center; margin-bottom: 28px;">
            <div style="width: 60px; height: 60px; border-radius: 16px; background: linear-gradient(135deg, #4f46e5, #7c3aed); display: inline-flex; align-items: center; justify-content: center; color: white; font-size: 28px; margin-bottom: 14px;">
                ⚡
            </div>
            <h1 style="font-size: 1.75rem; font-weight: 800; color: #0f172a; margin-bottom: 6px;">System Installation & Setup</h1>
            <p style="color: #64748b; font-size: 0.95rem;">Face Recognition Photo Retrieval & Management Portal</p>
        </div>

        <?php if ($message): ?>
            <div style="background: #ecfdf5; border: 1px solid #6ee7b7; color: #065f46; padding: 16px 20px; border-radius: 12px; margin-bottom: 24px;">
                <strong>🎉 Success!</strong> <?php echo htmlspecialchars($message); ?>
                <div style="margin-top: 14px; display: flex; gap: 12px;">
                    <a href="<?php echo BASE_URL; ?>/admin/login.php" class="btn-install" style="text-align: center; text-decoration: none; display: block; flex: 1;">Go to Admin Login</a>
                    <a href="<?php echo BASE_URL; ?>/" class="btn-install" style="background: #e2e8f0; color: #1e293b; text-align: center; text-decoration: none; display: block; flex: 1; box-shadow: none;">Go to Photo Finder</a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div style="background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; padding: 16px 20px; border-radius: 12px; margin-bottom: 24px;">
                <strong>⚠️ Setup Error:</strong> <?php echo htmlspecialchars($error); ?>
                <p style="margin-top: 8px; font-size: 0.85rem;">Make sure MySQL is started in your XAMPP Control Panel and database credentials in <code>config/config.php</code> are correct.</p>
            </div>
        <?php endif; ?>

        <h3 style="font-size: 1.05rem; font-weight: 700; color: #334155; margin-bottom: 14px;">System Diagnostics</h3>
        <div style="margin-bottom: 24px;">
            <?php foreach ($diagnostics as $key => $item): ?>
                <div class="diag-item">
                    <span style="font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($item['name']); ?></span>
                    <span class="status-badge <?php echo $item['status'] ? 'badge-pass' : 'badge-fail'; ?>">
                        <?php echo $item['status'] ? '✓ ' . htmlspecialchars($item['detail']) : '✕ ' . htmlspecialchars($item['detail']); ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px; margin-bottom: 24px; font-size: 0.9rem; color: #475569;">
            <div style="font-weight: 700; color: #0f172a; margin-bottom: 6px;">Default Admin Credentials:</div>
            <div>Username: <code style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px; color: #0f172a;">admin</code></div>
            <div style="margin-top: 4px;">Password: <code style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px; color: #0f172a;">admin123</code></div>
            <div style="margin-top: 4px;">Database: <code style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px; color: #0f172a;"><?php echo DB_NAME; ?></code> on <code><?php echo DB_HOST; ?></code></div>
        </div>

        <form method="POST">
            <button type="submit" name="install" value="1" class="btn-install">
                <?php echo $isInstalled ? '🔄 Re-initialize Database & Tables' : '🚀 Install Database & Tables'; ?>
            </button>
        </form>
    </div>
</body>
</html>
