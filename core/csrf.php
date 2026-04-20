<?php
// api/csrf.php — CSRF protection using the double-submit cookie pattern
// 
// How it works:
//   1. On session start, a random CSRF token is generated and stored in $_SESSION
//   2. The token is also set as a cookie (X-CSRF-TOKEN) readable by JavaScript
//   3. On state-changing requests (POST/PUT/DELETE), the client must send
//      the token as an X-CSRF-TOKEN header
//   4. The server compares the header value against the session value
//
// Frontend usage:
//   - Read the cookie: document.cookie.match(/X-CSRF-TOKEN=([^;]+)/)?.[1]
//   - Send it as a header: fetch(url, { headers: { 'X-CSRF-TOKEN': token } })

/**
 * Generate and set the CSRF token cookie if not already present.
 * Should be called after session_start().
 */
function csrf_init() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // Set/refresh the cookie so JS can read it
    $token = $_SESSION['csrf_token'];
    if (!isset($_COOKIE['X-CSRF-TOKEN']) || $_COOKIE['X-CSRF-TOKEN'] !== $token) {
        setcookie('X-CSRF-TOKEN', $token, [
            'path'     => '/',
            'httponly'  => false,  // JS needs to read this
            'samesite' => 'Lax',
            'secure'   => isset($_SERVER['HTTPS']),
        ]);
    }
}

/**
 * Validate the CSRF token on state-changing requests.
 * Call this at the top of POST/PUT/DELETE handlers.
 * GET/OPTIONS requests are skipped automatically.
 */
function csrf_verify() {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // Safe methods don't need CSRF protection
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'])) {
        return;
    }

    $sessionToken = $_SESSION['csrf_token'] ?? null;
    $headerToken  = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

    // If the session token is missing or user is not logged in, the session likely expired
    if (!isset($_SESSION['user_id']) && !in_array($action ?? '', ['login', 'register', 'google_login'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error'   => 'Session expired. Please log in again.'
        ]);
        exit;
    }

    if (!$sessionToken || !$headerToken || !hash_equals($sessionToken, $headerToken)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error'   => 'Invalid or missing CSRF token. Please refresh the page and try again.'
        ]);
        exit;
    }
}
