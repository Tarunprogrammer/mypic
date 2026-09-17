<?php
/**
 * Authentication API (Admin Login, Session Check, Logout)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

$db = Database::getConnection();

switch ($action) {
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['success' => false, 'message' => 'Invalid request method'], 405);
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';

        if (empty($username) || empty($password)) {
            json_response(['success' => false, 'message' => 'Username and password are required'], 400);
        }

        $stmt = $db->prepare("SELECT * FROM admins WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_name'] = $admin['name'];
            $_SESSION['admin_role'] = $admin['role'];

            json_response([
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => $admin['id'],
                    'username' => $admin['username'],
                    'name' => $admin['name'],
                    'role' => $admin['role']
                ]
            ]);
        } else {
            json_response(['success' => false, 'message' => 'Invalid username or password'], 401);
        }
        break;

    case 'check':
        if (is_admin_logged_in()) {
            json_response([
                'success' => true,
                'authenticated' => true,
                'user' => [
                    'id' => $_SESSION['admin_id'],
                    'username' => $_SESSION['admin_username'],
                    'name' => $_SESSION['admin_name'],
                    'role' => $_SESSION['admin_role']
                ]
            ]);
        } else {
            json_response([
                'success' => true,
                'authenticated' => false
            ]);
        }
        break;

    case 'logout':
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();

        if (isset($_GET['redirect'])) {
            header('Location: ' . BASE_URL . '/admin/login.php');
            exit;
        }

        json_response(['success' => true, 'message' => 'Logged out successfully']);
        break;

    case 'update_profile':
        require_admin_login();
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $currentPass = $input['current_password'] ?? '';
        $newPass = $input['new_password'] ?? '';

        if (empty($name)) {
            json_response(['success' => false, 'message' => 'Name cannot be empty'], 400);
        }

        $adminId = $_SESSION['admin_id'];
        $stmt = $db->prepare("SELECT * FROM admins WHERE id = ? LIMIT 1");
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch();

        if (!$admin) {
            json_response(['success' => false, 'message' => 'User not found'], 404);
        }

        if (!empty($newPass)) {
            if (empty($currentPass) || !password_verify($currentPass, $admin['password'])) {
                json_response(['success' => false, 'message' => 'Current password is incorrect'], 400);
            }
            $newHash = password_hash($newPass, PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE admins SET name = ?, email = ?, password = ? WHERE id = ?");
            $stmt->execute([$name, $email, $newHash, $adminId]);
        } else {
            $stmt = $db->prepare("UPDATE admins SET name = ?, email = ? WHERE id = ?");
            $stmt->execute([$name, $email, $adminId]);
        }

        $_SESSION['admin_name'] = $name;

        json_response(['success' => true, 'message' => 'Profile updated successfully']);
        break;

    default:
        json_response(['success' => false, 'message' => 'Invalid action parameter'], 400);
}
