<?php
/**
 * Admin Login Page
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../api/db.php';

if (is_admin_logged_in()) {
    header('Location: ' . BASE_URL . '/admin/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM admins WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_name'] = $admin['name'];
            $_SESSION['admin_role'] = $admin['role'];

            header('Location: ' . BASE_URL . '/admin/index.php');
            exit;
        } else {
            $error = 'Invalid username or password';
        }
    } else {
        $error = 'Please enter both username and password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Admin Login - MYPIC</title>

    <!-- Favicon & Brand Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo BASE_URL; ?>/assets/images/favicon.png">
    <link rel="apple-touch-icon" href="<?php echo BASE_URL; ?>/assets/images/favicon.png">
    <link rel="shortcut icon" href="<?php echo BASE_URL; ?>/favicon.ico">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/admin.css?v=<?php echo time(); ?>">
    <style>
        body.login-body {
            background-color: var(--admin-bg);
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background-image: 
                radial-gradient(circle at 50% 20%, rgba(99, 102, 241, 0.08) 0%, transparent 50%),
                radial-gradient(circle at 20% 80%, rgba(79, 70, 229, 0.05) 0%, transparent 45%);
            background-attachment: fixed;
            font-family: var(--font-body);
        }
        .login-card {
            width: 100%;
            max-width: 440px;
            background: #ffffff;
            border: 1px solid var(--border-admin);
            border-radius: var(--radius-xl);
            padding: 42px 36px;
            box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.1), 0 0 0 1px rgba(0, 0, 0, 0.04);
        }
    </style>
</head>
<body class="login-body">
    <div class="login-card">
        <div style="text-align: center; margin-bottom: 28px;">
            <a href="<?php echo BASE_URL; ?>/" style="display: inline-block; text-decoration: none;" title="MYPIC Home">
                <img src="<?php echo BASE_URL; ?>/assets/images/logo.png" alt="MYPIC" style="height: 86px; width: auto; object-fit: contain; margin-bottom: 12px; filter: drop-shadow(0 6px 16px rgba(0, 0, 0, 0.08)); transition: transform 0.2s ease;" onmouseover="this.style.transform='scale(1.04)'" onmouseout="this.style.transform='scale(1)'">
            </a>
            <h1 style="font-family: var(--font-heading); font-size: 1.65rem; font-weight: 800; color: var(--text-primary); letter-spacing: -0.02em;">Admin Control Center</h1>
            <p style="color: var(--text-secondary); font-size: 0.88rem; margin-top: 4px;">Sign in to access control center</p>
        </div>

        <?php if ($error): ?>
            <div style="background: #fff1f2; border: 1px solid #fecdd3; color: #e11d48; padding: 12px 16px; border-radius: var(--radius-md); font-size: 0.88rem; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; font-weight: 600;">
                <i class="ri-error-warning-fill"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="input-control" placeholder="Enter username" required autofocus value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <div style="position: relative; display: flex; align-items: center;">
                    <input type="password" name="password" id="passwordInput" class="input-control" placeholder="••••••••" required style="padding-right: 46px;">
                    <button type="button" id="togglePasswordBtn" onclick="togglePasswordVisibility()" style="position: absolute; right: 12px; background: transparent; border: none; color: #94a3b8; font-size: 1.25rem; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 4px;" title="Show/Hide Password" aria-label="Toggle Password Visibility">
                        <i class="ri-eye-line" id="passwordEyeIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 0.95rem; margin-top: 8px;">
                <i class="ri-login-box-line"></i> Sign In to Dashboard
            </button>
        </form>

        <div style="margin-top: 24px; text-align: center; font-size: 0.85rem; color: var(--text-muted); border-top: 1px solid var(--border-admin); padding-top: 18px;">
            <p><a href="<?php echo BASE_URL; ?>/" style="color: var(--primary); text-decoration: none; font-weight: 600;"><i class="ri-arrow-left-line"></i> Back to Public Face Finder</a></p>
        </div>
    </div>

    <script>
    function togglePasswordVisibility() {
        const passwordInput = document.getElementById('passwordInput');
        const eyeIcon = document.getElementById('passwordEyeIcon');
        if (!passwordInput || !eyeIcon) return;

        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            eyeIcon.className = 'ri-eye-off-line';
            eyeIcon.style.color = '#ec4899';
        } else {
            passwordInput.type = 'password';
            eyeIcon.className = 'ri-eye-line';
            eyeIcon.style.color = '#94a3b8';
        }
    }
    </script>
</body>
</html>
