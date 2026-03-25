<?php
// api/health.php — Health check endpoint for monitoring
// Returns 200 OK if the app and database are reachable.
// Usage: GET /api/health.php

require_once 'cors.php';
header('Content-Type: application/json');

$status = ['status' => 'ok', 'timestamp' => time()];
$httpCode = 200;

// Check database connectivity
try {
    require_once '../config/database.php';
    $db = new Database();
    $conn = $db->getConnection();
    $stmt = $conn->query('SELECT 1');
    $status['database'] = 'connected';
} catch (Exception $e) {
    $status['status'] = 'degraded';
    $status['database'] = 'unreachable';
    $httpCode = 503;
}

// Check writable directories
$writableDirs = [
    'uploads' => __DIR__ . '/../uploads',
    'cache'   => __DIR__ . '/../cache',
    'config'  => __DIR__ . '/../config',
];

$status['storage'] = [];
foreach ($writableDirs as $name => $path) {
    $status['storage'][$name] = is_dir($path) && is_writable($path) ? 'writable' : 'not_writable';
}

// PHP version
$status['php_version'] = PHP_VERSION;

http_response_code($httpCode);
echo json_encode($status, JSON_PRETTY_PRINT);
?>
