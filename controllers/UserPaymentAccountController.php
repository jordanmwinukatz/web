<?php
/**
 * controllers/UserPaymentAccountController.php
 */

class UserPaymentAccountController extends Controller {

    public function handle($input, $requestedRoute = null) {
        require_auth();

        $database = new Database();
        $pdo = $database->getConnection();
        
        // Ensure table exists safely
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_payment_accounts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            payment_method VARCHAR(100) NOT NULL,
            account_name VARCHAR(255) NOT NULL,
            account_number VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_payment_method (payment_method),
            INDEX idx_user_payment (user_id, payment_method)
        )");

        $method = $_SERVER['REQUEST_METHOD'];
        $action = isset($_GET['action']) ? $_GET['action'] : (isset($input['action']) ? $input['action'] : '');
        $user_id = get_current_user_id();

        if (!$user_id) {
            $this->error('User not authenticated', 401);
        }

        if ($method === 'GET' || $action === 'get') {
            $stmt = $pdo->prepare("SELECT id, payment_method, account_name, account_number FROM user_payment_accounts WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([$user_id]);
            $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $savedAccounts = [];
            foreach ($accounts as $account) {
                $pm = $account['payment_method'];
                if (!isset($savedAccounts[$pm])) {
                    $savedAccounts[$pm] = [];
                }
                $savedAccounts[$pm][] = [
                    'id' => $account['id'],
                    'name' => $account['account_name'],
                    'number' => $account['account_number']
                ];
            }
            
            return $this->success(['accounts' => $savedAccounts]);
            
        } elseif ($method === 'POST' || $action === 'save') {
            $payment_method = trim($input['payment_method'] ?? '');
            $account_name = trim($input['account_name'] ?? '');
            $account_number = trim($input['account_number'] ?? '');
            
            if (empty($payment_method)) {
                $this->error('Payment method is required');
            }
            if (empty($account_name) || empty($account_number)) {
                $this->error('Account name and number are required');
            }
            
            $checkStmt = $pdo->prepare("SELECT id FROM user_payment_accounts WHERE user_id = ? AND payment_method = ? AND account_name = ? AND account_number = ?");
            $checkStmt->execute([$user_id, $payment_method, $account_name, $account_number]);
            if ($checkStmt->fetch()) {
                return $this->success([
                    'message' => 'Account already exists',
                    'account' => [
                        'payment_method' => $payment_method,
                        'account_name' => $account_name,
                        'account_number' => $account_number
                    ]
                ]);
            }
            
            $stmt = $pdo->prepare("INSERT INTO user_payment_accounts (user_id, payment_method, account_name, account_number) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $payment_method, $account_name, $account_number]);
            $account_id = $pdo->lastInsertId();
            
            return $this->success([
                'message' => 'Account saved successfully',
                'account' => [
                    'id' => $account_id,
                    'payment_method' => $payment_method,
                    'account_name' => $account_name,
                    'account_number' => $account_number
                ]
            ]);
            
        } elseif ($method === 'DELETE' || $action === 'delete') {
            $account_id = $input['account_id'] ?? $_GET['account_id'] ?? null;
            $payment_method = trim($input['payment_method'] ?? $_GET['payment_method'] ?? '');
            
            if ($account_id) {
                $stmt = $pdo->prepare("DELETE FROM user_payment_accounts WHERE id = ? AND user_id = ?");
                $stmt->execute([$account_id, $user_id]);
            } elseif ($payment_method) {
                $stmt = $pdo->prepare("DELETE FROM user_payment_accounts WHERE user_id = ? AND payment_method = ?");
                $stmt->execute([$user_id, $payment_method]);
            } else {
                $this->error('Account ID or payment method is required for deletion');
            }
            
            return $this->success(['message' => 'Account(s) deleted successfully']);
        }

        $this->error('Method not allowed', 405);
    }
}
?>
