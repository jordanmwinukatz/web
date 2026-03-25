<?php

require_once __DIR__ . '/../config/env.php';
Env::load();
require_once __DIR__ . '/../core/csrf.php';

class SecurityTest {
    
    public function setUp() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // Force server vars
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['REQUEST_METHOD'] = 'POST';
    }

    public function testCsrfTokenGeneration() {
        $this->setUp();
        
        csrf_init();
        
        assertTrue(isset($_SESSION['csrf_token']), "CSRF token should be generated in session");
        assertTrue(strlen($_SESSION['csrf_token']) === 64, "Token should be a 64-character hex string");
        
        // Cannot strictly test setcookie() in CLI without headers_sent warnings,
        // but we verify the session logic is solid.
    }
}
