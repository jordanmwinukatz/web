<?php
/**
 * api/index.php
 * The Front Controller for the MVC Framework.
 * 
 * All traffic historically sent to /api/file.php is now routed here 
 * seamlessly via .htaccess rewrites.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Custom error handler to catch warnings/notices and convert them to exceptions
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return; // This error code is not included in error_reporting
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

require_once __DIR__ . '/../core/cors.php';
require_once __DIR__ . '/../core/Router.php';
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Model.php';

header('Content-Type: application/json');

$router = new Router();

// --------------------------------------------------------------------------
// Route Definitions
// These map the old file basenames to the new MVC Controller classes.
// e.g. /api/auth.php -> route=auth -> AuthController
// --------------------------------------------------------------------------
$router->register('auth', 'AuthController');
$router->register('submissions', 'SubmissionController');
$router->register('upload', 'UploadController');
$router->register('upload_profile', 'UploadController');
$router->register('analytics', 'AnalyticsController');
$router->register('binance_price', 'PricingController');
$router->register('set_override_price', 'PricingController');
$router->register('p2p_config', 'ConfigController');
$router->register('payment_api', 'PaymentApiController');
$router->register('payment_accounts', 'UserPaymentAccountController');
$router->register('health', 'HealthController');

// Retrieve the requested route from the rewrite
$route = $_GET['route'] ?? null;

if (!$route) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'API Route not specified']);
    exit;
}

// Ensure the controller file actually exists before dispatching
$controllerPath = __DIR__ . '/../controllers/' . $router->routes[$route] . '.php';

if (!isset($router->routes[$route])) {
    // If not registered, we fall-back to the flat PHP file for incremental migration!
    // This allows us to migrate the API files one by one while keeping the unmigrated ones working.
    $originalFile = __DIR__ . '/' . $route . '.php';
    if (file_exists($originalFile)) {
        require $originalFile;
        exit;
    }
}

// Dispatch to the MVC Engine
$router->dispatch($route);
