<?php
/**
 * controllers/HealthController.php
 */

class HealthController extends Controller {

    public function handle($input, $requestedRoute = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->error('Method not allowed', 405);
        }

        try {
            $database = new Database();
            $conn = $database->getConnection();

            if (!$conn) {
                throw new Exception("Database strictly unavailable.");
            }

            // Quick sanity check (if users table exists, DB is highly likely intact)
            $stmt = $conn->query("SHOW TABLES LIKE 'users'");
            $tableExists = $stmt->rowCount() > 0;

            if (!$tableExists) {
                throw new Exception("Database connected, but core tables are missing or inaccessible.");
            }

            echo json_encode([
                'status' => 'healthy',
                'database' => 'connected',
                'timestamp' => time(),
                'version' => '1.0.0'
            ]);

        } catch (Exception $e) {
            http_response_code(503);
            echo json_encode([
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'timestamp' => time()
            ]);
        }
    }
}
?>
