<?php
/**
 * bin/migrate.php
 * A zero-dependency database migration runner.
 * 
 * Usage from CLI: php bin/migrate.php
 */

require_once __DIR__ . '/../config/env.php';

// Force CLI context
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

Env::load();

$host = Env::get('DB_HOST', 'localhost');
$db_name = Env::get('DB_DATABASE', 'u234315390_main');
$username = Env::get('DB_USERNAME', 'root');
$password = Env::get('DB_PASSWORD', '');

echo "\n🚀 Starting Database Migrations\n";
echo "Host: {$host}\n";
echo "Database: {$db_name}\n";
echo str_repeat('-', 40) . "\n";

try {
    $dsn = "mysql:host={$host};dbname={$db_name};charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("❌ Connection failed: " . $e->getMessage() . "\n");
}

// 1. Ensure migrations table exists
$pdo->exec("
    CREATE TABLE IF NOT EXISTS migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(255) NOT NULL UNIQUE,
        executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// 2. Scan migrations directory
$migrationsDir = __DIR__ . '/../database/migrations';
if (!is_dir($migrationsDir)) {
    mkdir($migrationsDir, 0755, true);
}

$files = scandir($migrationsDir);
$sqlFiles = array_filter($files, function($f) {
    return pathinfo($f, PATHINFO_EXTENSION) === 'sql';
});
sort($sqlFiles); // Ensure sequential execution

// 3. Get already executed migrations
$stmt = $pdo->query("SELECT migration FROM migrations");
$executedRaw = $stmt->fetchAll(PDO::FETCH_COLUMN);
$executed = array_flip($executedRaw); // O(1) lookup

// 4. Run pending migrations
$ranCount = 0;

foreach ($sqlFiles as $file) {
    if (isset($executed[$file])) {
        continue;
    }

    echo "⏳ Running: {$file}... ";
    $sql = file_get_contents($migrationsDir . '/' . $file);
    
    // We don't use a strict transaction across the entire file because 
    // DDL statements (CREATE TABLE, ALTER TABLE) implicitly commit in MySQL.
    try {
        $pdo->exec($sql);
        
        // Record migration
        $stmt = $pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
        $stmt->execute([$file]);
        
        echo "✅ Done\n";
        $ranCount++;
    } catch (PDOException $e) {
        echo "❌ FAILED\n";
        echo "Error: " . $e->getMessage() . "\n";
        die("Migration aborted to prevent data inconsistency.\n");
    }
}

if ($ranCount === 0) {
    echo "✨ Nothing to migrate. Database is up to date!\n";
} else {
    echo "✨ Completed {$ranCount} migration(s) successfully.\n";
}
echo str_repeat('-', 40) . "\n\n";
