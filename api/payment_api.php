<?php
// api/payment_api.php
// Admin-only: Config CRUD for payment API settings + trigger payment endpoint

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once 'cors.php';

header('Content-Type: application/json');

session_start();

require_once '../config/database.php';

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

function read_payment_config($file, $defaults) {
    if (is_file($file)) {
        $data = json_decode(file_get_contents($file), true);
        if (is_array($data)) return array_merge($defaults, $data);
    }
    return $defaults;
}

function write_payment_config($file, $data) {
    if (!is_dir(dirname($file))) @mkdir(dirname($file), 0775, true);
    $tmp = $file . '.tmp';
    file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT));
    rename($tmp, $file);
}

function require_admin_session() {
    if (empty($_SESSION['admin_logged_in'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
}

function ensure_payment_logs_table($conn) {
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

try {
    $method = $_SERVER['REQUEST_METHOD'];

    // ── GET: Return current config (mask secret) ──
    if ($method === 'GET') {
        require_admin_session();

        $action = $_GET['action'] ?? '';

        // Return payment logs for a specific submission
        if ($action === 'logs') {
            $db = new Database();
            $conn = $db->getConnection();
            ensure_payment_logs_table($conn);

            $submission_id = $_GET['submission_id'] ?? '';
            if ($submission_id) {
                $stmt = $conn->prepare("SELECT * FROM payment_api_logs WHERE submission_id = ? ORDER BY created_at DESC LIMIT 20");
                $stmt->execute([$submission_id]);
            } else {
                $stmt = $conn->query("SELECT * FROM payment_api_logs ORDER BY created_at DESC LIMIT 50");
            }
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'logs' => $logs]);
            exit;
        }

        $cfg = read_payment_config($configFile, $defaults);

        // Mask the secret for frontend display
        $masked = $cfg;
        if (!empty($masked['api_secret'])) {
            $len = strlen($masked['api_secret']);
            $masked['api_secret'] = str_repeat('•', min($len, 20));
            $masked['has_secret'] = true;
        } else {
            $masked['has_secret'] = false;
        }

        echo json_encode(['success' => true, 'data' => $masked]);
        exit;
    }

    // ── POST: Save config or trigger payment ──
    if ($method === 'POST') {
        require_admin_session();

        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true);
        if (!is_array($input)) {
            throw new Exception('Invalid JSON body');
        }

        $action = $input['action'] ?? 'save';

        // ── Save config ──
        if ($action === 'save') {
            $cfg = read_payment_config($configFile, $defaults);

            if (isset($input['enabled']))      $cfg['enabled']      = (bool)$input['enabled'];
            if (isset($input['provider']))     $cfg['provider']     = trim($input['provider']);
            if (isset($input['api_url']))      $cfg['api_url']      = trim($input['api_url']);
            if (isset($input['api_key']))      $cfg['api_key']      = trim($input['api_key']);
            if (isset($input['callback_url'])) $cfg['callback_url'] = trim($input['callback_url']);
            if (isset($input['notes']))        $cfg['notes']        = trim($input['notes']);

            // Only update secret if a new value is explicitly provided (not the masked placeholder)
            if (isset($input['api_secret']) && $input['api_secret'] !== '' && strpos($input['api_secret'], '•') === false) {
                $cfg['api_secret'] = trim($input['api_secret']);
            }

            write_payment_config($configFile, $cfg);

            // Return masked version
            $masked = $cfg;
            if (!empty($masked['api_secret'])) {
                $masked['api_secret'] = str_repeat('•', min(strlen($masked['api_secret']), 20));
                $masked['has_secret'] = true;
            } else {
                $masked['has_secret'] = false;
            }

            echo json_encode(['success' => true, 'data' => $masked, 'message' => 'Payment API settings saved']);
            exit;
        }

        // ── Trigger payment ──
        if ($action === 'trigger_payment') {
            $db = new Database();
            $conn = $db->getConnection();
            ensure_payment_logs_table($conn);

            $cfg = read_payment_config($configFile, $defaults);

            $submission_id  = $input['submission_id'] ?? '';
            $amount         = $input['amount'] ?? 0;
            $account_number = $input['account_number'] ?? '';
            $account_name   = $input['account_name'] ?? '';
            $channel        = $input['channel'] ?? '';

            if (!$submission_id) {
                throw new Exception('submission_id is required');
            }
            if (!$amount || $amount <= 0) {
                throw new Exception('Valid amount is required');
            }
            if (!$account_number) {
                throw new Exception('account_number is required');
            }

            // Generate a unique reference
            $reference = 'PAY-' . strtoupper(bin2hex(random_bytes(4))) . '-' . $submission_id;

            // Check if API is enabled
            if (!$cfg['enabled']) {
                // Log the attempt as "api_disabled"
                $logStmt = $conn->prepare("INSERT INTO payment_api_logs (submission_id, provider, account_number, account_name, channel, amount, reference, status, error_message) VALUES (?, ?, ?, ?, ?, ?, ?, 'api_disabled', 'Payment API is not enabled. Configure it in Admin Settings.')");
                $logStmt->execute([$submission_id, $cfg['provider'] ?: 'none', $account_number, $account_name, $channel, $amount, $reference]);

                echo json_encode([
                    'success' => false,
                    'error' => 'Payment API is not enabled. Go to Admin → Settings → Payment API to configure it.',
                    'reference' => $reference
                ]);
                exit;
            }

            // Check if provider/URL are configured
            if (empty($cfg['api_url']) || empty($cfg['api_key'])) {
                $logStmt = $conn->prepare("INSERT INTO payment_api_logs (submission_id, provider, account_number, account_name, channel, amount, reference, status, error_message) VALUES (?, ?, ?, ?, ?, ?, ?, 'not_configured', 'API URL or API Key is missing.')");
                $logStmt->execute([$submission_id, $cfg['provider'] ?: 'none', $account_number, $account_name, $channel, $amount, $reference]);

                echo json_encode([
                    'success' => false,
                    'error' => 'Payment API credentials are incomplete. Please configure the API URL and API Key in Settings.',
                    'reference' => $reference
                ]);
                exit;
            }

            // ═══════════════════════════════════════════════════════
            // PLACEHOLDER: Replace this block with actual API call
            // when the real payment provider is integrated.
            //
            // Expected flow:
            //   1. Build request payload per provider spec
            //   2. cURL POST to $cfg['api_url']
            //   3. Parse response
            //   4. Update log with result
            //   5. If success, auto-mark submission completed
            // ═══════════════════════════════════════════════════════

            $requestPayload = json_encode([
                'provider'       => $cfg['provider'],
                'api_url'        => $cfg['api_url'],
                'account_number' => $account_number,
                'account_name'   => $account_name,
                'amount'         => $amount,
                'channel'        => $channel,
                'reference'      => $reference,
            ]);

            // Log the attempt as "placeholder"
            $logStmt = $conn->prepare("INSERT INTO payment_api_logs (submission_id, provider, account_number, account_name, channel, amount, reference, status, api_request, api_response, error_message) VALUES (?, ?, ?, ?, ?, ?, ?, 'placeholder', ?, ?, ?)");
            $logStmt->execute([
                $submission_id,
                $cfg['provider'],
                $account_number,
                $account_name,
                $channel,
                $amount,
                $reference,
                $requestPayload,
                json_encode(['message' => 'Placeholder - no real API call made']),
                'Payment provider "' . $cfg['provider'] . '" is not yet implemented. The request was logged for when the integration is completed.'
            ]);

            $logId = $conn->lastInsertId();

            echo json_encode([
                'success'   => false,
                'error'     => 'Payment provider "' . ($cfg['provider'] ?: 'unknown') . '" is not yet implemented. Your request has been logged (ref: ' . $reference . '). Once the API integration is complete, this will work automatically.',
                'reference' => $reference,
                'log_id'    => $logId,
                'provider'  => $cfg['provider'],
            ]);
            exit;
        }

        throw new Exception('Unknown action: ' . $action);
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
