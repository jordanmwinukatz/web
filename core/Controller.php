<?php
/**
 * core/Controller.php
 * Base class for all MVC controllers.
 */

require_once __DIR__ . '/auth_middleware.php';

abstract class Controller {
    
    /**
     * Send a standard JSON response.
     */
    protected function jsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Helper to return a success response.
     */
    protected function success($data = [], $message = '') {
        $response = ['success' => true];
        if ($message) $response['message'] = $message;
        $response = array_merge($response, $data);
        $this->jsonResponse($response);
    }

    /**
     * Helper to return an error response.
     */
    protected function error($message, $statusCode = 400) {
        $this->jsonResponse([
            'success' => false,
            'error' => $message
        ], $statusCode);
    }

    /**
     * Protect route requiring standard authentication.
     */
    protected function requireAuth() {
        require_auth();
    }

    /**
     * Protect route requiring administrator privileges.
     */
    protected function requireAdmin() {
        require_admin();
    }
    
    /**
     * Retrieve current user ID from session.
     */
    protected function currentUserId() {
        return get_current_user_id();
    }
}
