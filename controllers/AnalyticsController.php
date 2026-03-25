<?php
/**
 * controllers/AnalyticsController.php
 */

class AnalyticsController extends Controller {

    public function handle($input, $requestedRoute = null) {
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        $database = new Database();
        $conn = $database->getConnection();

        $method = $_SERVER['REQUEST_METHOD'];
        if ($method === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        if ($method !== 'POST') {
            $this->error('Method not allowed', 405);
        }

        if (empty($input)) {
            $this->error('Invalid JSON payload');
        }

        try {
            if ($action === 'page_view') {
                $query = "INSERT INTO analytics_page_views (session_id, page_url, page_title, time_on_page) 
                          VALUES (:session_id, :page_url, :page_title, :time_on_page)";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':session_id', $input['session_id']);
                $stmt->bindParam(':page_url', $input['page_url']);
                $stmt->bindParam(':page_title', $input['page_title']);
                $stmt->bindParam(':time_on_page', $input['time_on_page']);

                if ($stmt->execute()) {
                    // Also track abstractly
                    $this->trackEvent($conn, array_merge($input, [
                        'event_type' => 'page_view',
                        'event_name' => 'page_view',
                        'user_id' => null, 'referrer' => null, 'user_agent' => null,
                        'ip_address' => null, 'country' => null, 'city' => null,
                        'device_type' => null, 'browser' => null, 'event_data' => null
                    ]));
                    return $this->success(['message' => 'Page view tracked']);
                }
            } elseif ($action === 'wizard_step') {
                $query = "INSERT INTO analytics_wizard (session_id, wizard_step, action_type, payment_method, route_type, step_data) 
                          VALUES (:session_id, :wizard_step, :action_type, :payment_method, :route_type, :step_data)";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':session_id', $input['session_id']);
                $stmt->bindParam(':wizard_step', $input['wizard_step']);
                $stmt->bindParam(':action_type', $input['action_type']);
                $stmt->bindParam(':payment_method', $input['payment_method']);
                $stmt->bindParam(':route_type', $input['route_type']);
                $stepDataJson = isset($input['step_data']) ? json_encode($input['step_data']) : null;
                $stmt->bindParam(':step_data', $stepDataJson);

                if ($stmt->execute()) {
                    return $this->success(['message' => 'Wizard step tracked']);
                }
            } else {
                // Default general event
                $this->trackEvent($conn, $input);
                return $this->success(['message' => 'Event tracked successfully']);
            }
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }
    }

    private function trackEvent($conn, $input) {
        $query = "INSERT INTO analytics_events 
                 (event_type, event_name, user_id, session_id, page_url, referrer, user_agent, ip_address, country, city, device_type, browser, event_data) 
                 VALUES (:event_type, :event_name, :user_id, :session_id, :page_url, :referrer, :user_agent, :ip_address, :country, :city, :device_type, :browser, :event_data)";

        $stmt = $conn->prepare($query);
        $stmt->bindParam(':event_type', $input['event_type']);
        $stmt->bindParam(':event_name', $input['event_name']);
        $stmt->bindParam(':user_id', $input['user_id']);
        $stmt->bindParam(':session_id', $input['session_id']);
        $stmt->bindParam(':page_url', $input['page_url']);
        $stmt->bindParam(':referrer', $input['referrer']);
        $stmt->bindParam(':user_agent', $input['user_agent']);
        $stmt->bindParam(':ip_address', $input['ip_address']);
        $stmt->bindParam(':country', $input['country']);
        $stmt->bindParam(':city', $input['city']);
        $stmt->bindParam(':device_type', $input['device_type']);
        $stmt->bindParam(':browser', $input['browser']);
        $eventDataJson = (isset($input['event_data']) && is_array($input['event_data'])) ? json_encode($input['event_data']) : ($input['event_data'] ?? null);
        $stmt->bindParam(':event_data', $eventDataJson);
        $stmt->execute();
    }
}
?>
