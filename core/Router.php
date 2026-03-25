<?php
/**
 * core/Router.php
 * Basic MVC Router.
 */

class Router {
    public $routes = [];

    /**
     * Register a route mapping a path (or backwards-compatible endpoint) 
     * to a specific Controller class.
     */
    public function register($route, $controllerClass) {
        $this->routes[$route] = $controllerClass;
    }

    /**
     * Dispatch the current request.
     */
    public function dispatch($requestedRoute) {
        // Find controller
        if (!isset($this->routes[$requestedRoute])) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => "Endpoint '{$requestedRoute}' not found or replaced by Router"]);
            exit;
        }

        $controllerName = $this->routes[$requestedRoute];
        require_once __DIR__ . '/../controllers/' . $controllerName . '.php';

        $controller = new $controllerName();

        // Check if the request is sending JSON and has an 'action' property.
        // This is to maintain 100% backwards compatibility with the old app.js payload style.
        $method = $_SERVER['REQUEST_METHOD'];
        $input = [];

        if ($method === 'POST' || $method === 'PUT') {
            $raw = file_get_contents('php://input');
            $input = json_decode($raw, true) ?? [];
        }

        // Default method to call on the controller, can be overridden by $_POST['action']
        // or $input['action']. Wait, existing scripts just checked $action.
        $action = $input['action'] ?? $_GET['action'] ?? $_POST['action'] ?? null;
        
        if ($action && method_exists($controller, $action)) {
            // Dispatch to the named action
            try {
                $controller->$action($input);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        } else {
            // Default to handle() method if no action specified or action missing
            if (method_exists($controller, 'handle')) {
                try {
                    $controller->handle($input, $requestedRoute);
                } catch (Exception $e) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => "Action '{$action}' not supported on this endpoint. Request rejected by MVC Router."]);
            }
        }
    }
}
