<?php
// config/database.php — Database connection with singleton pattern
// Uses environment variables from .env via the Env helper.

require_once __DIR__ . '/env.php';

class Database {
    private static $instance = null;
    private $conn;

    private $host;
    private $db_name;
    private $username;
    private $password;

    public function __construct() {
        Env::load();

        // Auto-detect production: if .env is missing or has local defaults,
        // check if we're on the production server and use correct credentials.
        $isProduction = !file_exists(dirname(__DIR__) . '/.env')
                     || (php_uname('n') !== 'Owens-Air.lan' && !str_contains(($_SERVER['HTTP_HOST'] ?? ''), 'localhost'));

        if ($isProduction) {
            // Production defaults (Hostinger)
            $this->host     = Env::get('DB_HOST', 'localhost');
            $this->db_name  = Env::get('DB_DATABASE', 'u234315390_main');
            $this->username = Env::get('DB_USERNAME', 'u234315390_main');
            $this->password = Env::get('DB_PASSWORD', 'Nb/1S2QbiR');
        } else {
            // Local development defaults (XAMPP)
            $this->host     = Env::get('DB_HOST', 'localhost');
            $this->db_name  = Env::get('DB_DATABASE', 'u234315390_main');
            $this->username = Env::get('DB_USERNAME', 'root');
            $this->password = Env::get('DB_PASSWORD', '');
        }
    }

    /**
     * Get a PDO connection. Uses singleton to avoid multiple connections
     * per request. Always returns the same connection instance.
     */
    public function getConnection() {
        if ($this->conn !== null) {
            return $this->conn;
        }

        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Log the real error, but never expose it to the client
            error_log('Database connection failed: ' . $e->getMessage());
            http_response_code(503);
            echo json_encode(['success' => false, 'error' => 'Service temporarily unavailable']);
            exit;
        }

        return $this->conn;
    }

    /**
     * Get a shared Database instance (true singleton).
     * Avoids creating multiple Database objects across the same request.
     */
    public static function shared() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
