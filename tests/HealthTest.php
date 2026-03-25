<?php

class HealthTest {
    public function testHealthEndpointReturnsOk() {
        // We will execute the health.php endpoint in a controlled output buffer
        // to verify it correctly connects to the DB and returns JSON.

        // Setup mock environment to avoid headers_sent issues
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_ORIGIN'] = 'http://localhost';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        ob_start();
        require_once __DIR__ . '/../core/Controller.php';
        require_once __DIR__ . '/../controllers/HealthController.php';
        $controller = new HealthController();
        $controller->handle([], 'health');
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        assertTrue(json_last_error() === JSON_ERROR_NONE, "Health endpoint must return valid JSON");
        assertEquals('healthy', $data['status'], "Health status should be 'healthy'");
    }
}
