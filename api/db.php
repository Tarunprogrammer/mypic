<?php
/**
 * Database Connection using PDO
 * Face Recognition Photo Retrieval System
 */

require_once __DIR__ . '/../config/config.php';

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 4
            ];

            try {
                $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // If running on local machine and remote InfinityFree DB blocks external connection, fallback to local MySQL
                $isLocal = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']) || php_sapi_name() === 'cli';
                if ($isLocal && DB_HOST !== '127.0.0.1' && DB_HOST !== 'localhost') {
                    try {
                        $localDsn = "mysql:host=127.0.0.1;port=3306;dbname=mypic_db;charset=utf8mb4";
                        self::$instance = new PDO($localDsn, 'root', '', $options);
                        return self::$instance;
                    } catch (PDOException $localEx) {
                        // pass through
                    }
                }

                $isCli = php_sapi_name() === 'cli';
                $errorMsg = $e->getMessage();
                
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' || strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
                    json_response([
                        'success' => false,
                        'message' => 'Database connection failed: ' . $errorMsg,
                        'hint' => 'Check your database credentials in config/config.php'
                    ], 500);
                } else {
                    die("<h3>Database Connection Error</h3><p>" . htmlspecialchars($errorMsg) . "</p><p>Please ensure MySQL is running or verify credentials in <code>config/config.php</code>.</p>");
                }
            }
        }
        return self::$instance;
    }
}

/**
 * Get setting value from database (with in-memory bulk cache for high performance)
 */
function get_setting($key, $default = null) {
    static $settingsCache = null;
    if ($settingsCache === null) {
        try {
            $db = Database::getConnection();
            $stmt = $db->query("SELECT key_name, key_value FROM settings");
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_KEY_PAIR) : [];
            $settingsCache = is_array($rows) ? $rows : [];
        } catch (Exception $e) {
            $settingsCache = [];
        }
    }
    return array_key_exists($key, $settingsCache) ? $settingsCache[$key] : $default;
}
