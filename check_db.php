<?php
/**
 * Script to check the database connection
 * using the project's environment configuration.
 * 
 * You can run this from your browser: http://localhost/web/check_db.php
 * Or from the command line: php check_db.php
 */

require_once __DIR__ . '/config/env.php';

// Force showing errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    // Load environment variables from .env
    Env::load();

    $host = Env::get('DB_HOST', 'localhost');
    $db_name = Env::get('DB_DATABASE', 'u234315390_main');
    $username = Env::get('DB_USERNAME', 'root');
    $password = Env::get('DB_PASSWORD', '');

    echo "Attempting to connect to database...\n<br>";
    echo "Host: {$host}\n<br>";
    echo "Database: {$db_name}\n<br>";
    echo "User: {$username}\n<br>";
    echo str_repeat('-', 40) . "\n<br>";

    // Attempt the connection using PDO
    $dsn = "mysql:host={$host};dbname={$db_name};charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    echo "✅ SUCCESS: Database connected successfully!\n<br>";

} catch (Exception $e) {
    echo "❌ FAILED: Could not connect to the database.\n<br>";
    echo "Error message: " . $e->getMessage() . "\n<br>";
}
