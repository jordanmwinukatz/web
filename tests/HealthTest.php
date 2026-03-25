<?php

class HealthTest {
    public function testHealthEndpointReturnsOk() {
        // We will execute the health.php endpoint in a controlled output buffer
        // to verify it correctly connects to the DB and returns JSON.

        // Setup mock environment to avoid headers_sent issues
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_ORIGIN'] = 'http://localhost';
        
        ob_start();
        require __DIR__ . '/../api/health.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        assertTrue(json_last_error() === JSON_ERROR_NONE, "Health endpoint must return valid JSON");
        assertEquals('ok', $data['status'], "Health status should be 'ok'");
        assertEquals('connected', $data['database'], "Database should be 'connected'");
        assertTrue(isset($data['storage']), "Storage writability checks should be present");
    }
}
