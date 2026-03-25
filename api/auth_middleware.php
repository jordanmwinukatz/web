<?php
// api/auth_middleware.php
// Centralized authentication validation for API endpoints

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', 1);
    session_start();
}

require_once __DIR__ . '/csrf.php';
csrf_init();
csrf_verify();

/**
 * Requires a valid user session.
 * Halts execution and returns 401 JSON if unauthorized.
 */
function require_auth() {
    if (!isset($_SESSION['user_id'])) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode([
            'success' => false, 
            'error' => 'Unauthorized access. Please log in.'
        ]);
        exit;
    }
}

/**
 * Requires a valid admin session.
 * Halts execution and returns 403 JSON if forbidden.
 */
function require_admin() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode([
            'success' => false, 
            'error' => 'Forbidden. Administrator privileges required.'
        ]);
        exit;
    }
}

/**
 * Returns the currently authenticated user ID or null.
 * @return int|null
 */
function get_current_user_id() {
    return $_SESSION['user_id'] ?? null;
}
?>
