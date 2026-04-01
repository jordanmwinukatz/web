<?php
// Script to test Database Connection and Environment Configuration
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/env.php';

echo "<div style='font-family: sans-serif; max-width: 800px; margin: 0 auto; padding: 20px;'>";
echo "<h2>Database Connection Status Check</h2>";

// 1. Check Environment Variables
echo "<h3>1. Environment Configuration (from .env)</h3>";
Env::load();
$host = Env::get('DB_HOST', 'localhost');
$dbName = Env::get('DB_DATABASE', 'u234315390_main');
$username = Env::get('DB_USERNAME', 'root');

echo "<ul>";
echo "<li><strong>DB_HOST:</strong> " . htmlspecialchars($host) . "</li>";
echo "<li><strong>DB_DATABASE:</strong> " . htmlspecialchars($dbName) . "</li>";
echo "<li><strong>DB_USERNAME:</strong> " . htmlspecialchars($username) . "</li>";
echo "<li><strong>DB_PASSWORD:</strong> " . (Env::get('DB_PASSWORD') ? 'Set (Hidden)' : 'Not Set / Empty') . "</li>";
echo "</ul>";

// 2. Test Connection
echo "<h3>2. Connection Test</h3>";
try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    if ($pdo) {
        echo "<div style='padding: 15px; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 4px;'>";
        echo "<strong>Success!</strong> The database connection is working perfectly.";
        echo "</div>";
        
        // Let's get the tables to prove it works
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<p>Found <strong>" . count($tables) . "</strong> tables in the database:</p>";
        echo "<ul style='columns: 2;'>";
        foreach ($tables as $table) {
            echo "<li>" . htmlspecialchars($table) . "</li>";
        }
        echo "</ul>";
    }
} catch (Exception $e) {
    echo "<div style='padding: 15px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px;'>";
    echo "<strong>Error:</strong> Failed to connect to the database.<br><br>";
    echo "<strong>Message:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
    
    echo "<h4>Troubleshooting:</h4>";
    echo "<ul>";
    echo "<li>If you see 'Access denied for user', your DB_USERNAME or DB_PASSWORD in the .env file might be wrong.</li>";
    echo "<li>If you see 'Connection refused', the MySQL server might not be running.</li>";
    echo "</ul>";
}

echo "</div>";
?>
