<?php

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';

class CoreTest {
    public function testEnvLoader() {
        Env::load();
        // Since we force APP_ENV=testing in the test runner, this should be true.
        $env = Env::get('APP_ENV');
        assertEquals('testing', $env, "Env loader should return 'testing', got '{$env}'");
    }

    public function testDatabaseSingleton() {
        $db1 = Database::shared();
        $db2 = Database::shared();
        
        assertTrue($db1 === $db2, "Database::shared() did not return the exact same instance");

        $conn1 = $db1->getConnection();
        $conn2 = $db2->getConnection();

        assertTrue($conn1 === $conn2, "Database->getConnection() did not return the exact same PDO instance");
        assertTrue($conn1 instanceof PDO, "Connection should be a PDO object");
    }

    public function testDatabaseQuery() {
        $db = Database::shared();
        $conn = $db->getConnection();
        
        $stmt = $conn->query("SELECT 1 as val");
        $result = $stmt->fetch();
        
        assertEquals(1, $result['val'], "Simple query execution failed");
    }
}
