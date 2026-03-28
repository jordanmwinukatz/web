<?php
// api/cors.php — Centralized CORS configuration
// Replace wildcard (*) with allowed origins for security.

$allowed_origins = [
    'https://jordanmwinukatz.com',
    'https://www.jordanmwinukatz.com',
    'http://localhost',
    'http://localhost:3000',
    'http://localhost:5173',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Vary: Origin');
} else {
    // For same-origin requests (no Origin header), allow by default
    if (empty($origin)) {
        header('Access-Control-Allow-Origin: https://jordanmwinukatz.com');
    }
    // Otherwise, no CORS header = browser blocks the request
}

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-TOKEN');
header('Access-Control-Allow-Credentials: true');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
