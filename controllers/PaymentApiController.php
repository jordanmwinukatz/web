<?php
/**
 * controllers/PaymentApiController.php
 */

class PaymentApiController extends Controller {

    public function handle($input, $requestedRoute = null) {
        $method = $_SERVER['REQUEST_METHOD'];
        $configFile = __DIR__ . '/../config/payment_api.json';
        
        $defaults = [
            'enabled'      => false,
            'provider'     => '',
            'api_url'      => '',
            'api_key'      => '',
            'api_secret'   => '',
            'callback_url' => '',
            'notes'        => '',
        ];

        // ── GET: Return current config (mask secret) or logs ──
        if ($method === 'GET') {
            if (empty($_SESSION['admin_logged_in'])) {
                $this->error('Unauthorized', 401);
            }

            $action = isset($_GET['action']) ? $_GET['action'] : '';

            if ($action === 'logs') {
                $db = new Database();
                $conn = $db->getConnection();
                $this->ensureLogsTable($conn);

                $submission_id = isset($_GET['submission_id']) ? $_GET['submission_id'] : '';
                if ($submission_id) {
                    $stmt = $conn->prepare("SELECT * FROM payment_api_logs WHERE submission_id = ? ORDER BY created_at DESC LIMIT 20");
                    $stmt->execute([$submission_id]);
                } else {
                    $stmt = $conn->query("SELECT * FROM payment_api_logs ORDER BY created_at DESC LIMIT 50");
                }
                $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                return $this->success(['logs' => $logs]);
            }

            $cfg = $this->readConfig($configFile, $defaults);
            if (!empty($cfg['api_secret'])) {
                $cfg['api_secret'] = '********' . substr($cfg['api_secret'], -4);
            }
            return $this->success(['config' => $cfg]);
        }

        // ── POST: Either save config OR trigger a payment ──
        if ($method === 'POST') {
            if (empty($_SESSION['admin_logged_in'])) {
                $this->error('Unauthorized', 401);
            }

            if (!is_array($input)) {
                $this->error('Invalid JSON body');
            }

            $action = isset($input['action']) ? $input['action'] : 'save_config';

            if ($action === 'trigger_payment') {
                return $this->processTriggerPayment($input, $configFile, $defaults);
            }

            // Save config
            $cfg = $this->readConfig($configFile, $defaults);
            if (isset($input['enabled'])) $cfg['enabled'] = (bool)$input['enabled'];
            if (isset($input['provider'])) $cfg['provider'] = trim($input['provider']);
            if (isset($input['api_url'])) $cfg['api_url'] = trim($input['api_url']);
            if (isset($input['api_key'])) $cfg['api_key'] = trim($input['api_key']);
            if (isset($input['api_secret']) && $input['api_secret'] !== '' && !str_starts_with($input['api_secret'], '********')) {
                $cfg['api_secret'] = trim($input['api_secret']);
            }
            if (isset($input['callback_url'])) $cfg['callback_url'] = trim($input['callback_url']);
            if (isset($input['notes'])) $cfg['notes'] = trim($input['notes']);

            $this->writeConfig($configFile, $cfg);
            return $this->success(['message' => 'Payment API config saved successfully.']);
        }

        $this->error('Method not allowed', 405);
    }

    private function processTriggerPayment($input, $configFile, $defaults) {
        $payment_id = $input['payment_id'] ?? '';
        if (!$payment_id) {
            $this->error('Missing payment_id');
        }

        $cfg = $this->readConfig($configFile, $defaults);
        if (!$cfg['enabled']) {
            $this->error('Payment API is disabled in settings');
        }

        $db = new Database();
        $conn = $db->getConnection();
        $this->ensureLogsTable($conn);

        $stmt = $conn->prepare("SELECT * FROM user_submissions WHERE id = ?");
        $stmt->execute([$payment_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            $this->error('Order not found');
        }
        if ($order['submission_type'] !== 'Sell') {
            $this->error('Only Sell orders require payouts');
        }
        if ($order['p2p_status'] !== 'payment_pending') {
            $this->error('Order is not in payment_pending status');
        }

        // Example implementation for logging
        $amount = (float)$order['amount'];
        $currency = $order['currency'] ?? 'TZS';
        
        $provider = $cfg['provider'] ?? 'unknown';
        $apiUrl = $cfg['api_url'] ?? '';
        
        if (empty($apiUrl)) {
            $this->error('API URL is not configured');
        }

        sleep(1); 
        $success = rand(1, 100) > 10;
        
        $simulatedResponse = [
            'status' => $success ? 'SUCCESS' : 'FAILED',
            'transaction_id' => 'TXN' . time() . rand(1000, 9999),
            'message' => $success ? 'Transaction processed successfully via ' . $provider : 'Failed to connect to provider network',
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $reqJson = json_encode(['amount' => $amount, 'currency' => $currency, 'account' => '...']);
        $resJson = json_encode($simulatedResponse);
        
        $logStmt = $conn->prepare("INSERT INTO payment_api_logs 
            (submission_id, provider, account_number, account_name, channel, amount, currency, reference, status, api_request, api_response)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
        $statusStr = $success ? 'success' : 'failed';
        $logStmt->execute([
            $order['id'],
            $provider,
            $order['payment_account_number'] ?? '',
            $order['payment_account_name'] ?? '',
            $order['payment_method'] ?? '',
            $amount,
            $currency,
            $simulatedResponse['transaction_id'],
            $statusStr,
            $reqJson,
            $resJson
        ]);

        if ($success) {
            $updateStmt = $conn->prepare("UPDATE user_submissions SET p2p_status = 'payment_completed' WHERE id = ?");
            $updateStmt->execute([$order['id']]);
        }

        return $this->success([
            'message' => 'Payment API triggered successfully',
            'result' => $simulatedResponse,
            'api_status' => $statusStr
        ]);
    }

    private function ensureLogsTable($conn) {
        $conn->exec("CREATE TABLE IF NOT EXISTS payment_api_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            submission_id INT NOT NULL,
            provider VARCHAR(100),
            account_number VARCHAR(255),
            account_name VARCHAR(255),
            channel VARCHAR(100),
            amount DECIMAL(18,2),
            currency VARCHAR(10) DEFAULT 'TZS',
            reference VARCHAR(100),
            status VARCHAR(50) DEFAULT 'pending',
            api_request TEXT,
            api_response TEXT,
            error_message TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_submission_id (submission_id),
            INDEX idx_status (status)
        )");
    }

    private function readConfig($file, $defaults) {
        if (is_file($file)) {
            $data = json_decode(file_get_contents($file), true);
            if (is_array($data)) return array_merge($defaults, $data);
        }
        return $defaults;
    }

    private function writeConfig($file, $data) {
        if (!is_dir(dirname($file))) @mkdir(dirname($file), 0775, true);
        $tmp = $file . '.tmp';
        file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT));
        rename($tmp, $file);
    }
}
