<?php
/**
 * controllers/ConfigController.php
 */

class ConfigController extends Controller {

    public function handle($input, $requestedRoute = null) {
        $method = $_SERVER['REQUEST_METHOD'];
        $configFile = __DIR__ . '/../config/p2p.json';
        
        $defaults = [
            'buyCode' => 'j15stHvu1u4',
            'sellCode' => 'HrOzHzvC5uj',
        ];

        if ($method === 'GET') {
            $cfg = $this->readConfig($configFile, $defaults);
            return $this->success(['data' => $cfg]);
        }

        if ($method === 'POST') {
            if (empty($_SESSION['admin_logged_in'])) {
                $this->error('Unauthorized', 401);
            }

            if (!is_array($input)) {
                $this->error('Invalid JSON body');
            }

            $buy = isset($input['buyCode']) ? trim($input['buyCode']) : '';
            $sell = isset($input['sellCode']) ? trim($input['sellCode']) : '';

            if ($buy !== '' && !preg_match('/^[A-Za-z0-9_-]{6,}$/', $buy)) {
                $this->error('Invalid buyCode');
            }
            if ($sell !== '' && !preg_match('/^[A-Za-z0-9_-]{6,}$/', $sell)) {
                $this->error('Invalid sellCode');
            }

            $cfg = $this->readConfig($configFile, $defaults);
            if ($buy !== '') $cfg['buyCode'] = $buy;
            if ($sell !== '') $cfg['sellCode'] = $sell;

            $this->writeConfig($configFile, $cfg);
            return $this->success(['data' => $cfg]);
        }

        $this->error('Method not allowed', 405);
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
