<?php
/**
 * controllers/PricingController.php
 */

class PricingController extends Controller {

    public function handle($input, $requestedRoute = null) {
        $method = $_SERVER['REQUEST_METHOD'];
        $action = isset($_GET['action']) ? $_GET['action'] : (isset($input['action']) ? $input['action'] : 'get');

        if ($method === 'GET' && ($requestedRoute === 'binance_price' || $action === 'get')) {
            return $this->fetchPrice();
        } elseif ($method === 'POST' && ($requestedRoute === 'set_override_price' || $action === 'override')) {
            return $this->setOverride($input);
        }

        $this->error('Method not allowed', 405);
    }

    private function fetchPrice() {
        if (!isset($_SESSION['binance_price_limit'])) {
            $_SESSION['binance_price_limit'] = ['time' => time(), 'count' => 0];
        }
        if (time() - $_SESSION['binance_price_limit']['time'] > 60) {
            $_SESSION['binance_price_limit'] = ['time' => time(), 'count' => 1];
        } else {
            $_SESSION['binance_price_limit']['count']++;
            if ($_SESSION['binance_price_limit']['count'] > 20) {
                $this->error('Rate limit exceeded. Try again in a minute.', 429);
            }
        }

        $code = isset($_GET['code']) ? trim($_GET['code']) : '';
        if ($code === '') {
            $this->error('Missing code parameter');
        }

        $cacheDir = __DIR__ . '/../cache';
        if (!is_dir($cacheDir)) @mkdir($cacheDir, 0775, true);
        $cacheFile = $cacheDir . '/binance_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $code) . '.json';
        $overrideFile = $cacheDir . '/headless_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $code) . '.json';
        $cacheTtl = 15;

        // Check manual override first
        if (is_file($overrideFile)) {
            $over = json_decode(file_get_contents($overrideFile), true);
            if (is_array($over) && isset($over['price'])) {
                // Return override format mimicking binance response
                echo json_encode([
                    'success' => true,
                    'source' => 'manual_override',
                    'price' => (float)$over['price'],
                    'fiat' => $over['fiat'] ?? 'TZS',
                    'asset' => 'USDT',
                    'limits' => [
                        'minFiat' => 0,
                        'maxFiat' => 9999999,
                        'availableFiat' => 9999999,
                    ],
                    'cached' => false
                ]);
                exit;
            }
        }

        // Check cache
        if (is_file($cacheFile) && (time() - filemtime($cacheFile) < $cacheTtl)) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if (is_array($cached) && !empty($cached['success'])) {
                echo json_encode($cached);
                exit;
            }
        }

        $codeToAdvNo = [
            'd52wtrt1Sus' => '13793140957964742656', 
            'j15stHvu1u4' => '13793140957964742656', 
            'HrOzHzvC5uj' => '12790984677311205376', 
        ];

        $advNo = null;
        if (ctype_digit($code)) {
            $advNo = $code;
        } elseif (isset($codeToAdvNo[$code])) {
            $advNo = $codeToAdvNo[$code];
        }

        if ($advNo) {
            $detailUrl = 'https://c2c.binance.com/bapi/c2c/v2/public/c2c/adv/detail-with-advertiser?channel=c2c&advNo=' . urlencode($advNo) . '&area=shareAds';
            $ch = curl_init($detailUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 12,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_HTTPHEADER => [
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
                    'Accept: application/json',
                    'Accept-Language: en-US,en;q=0.9',
                ],
            ]);
            $resp = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($resp !== false && $http === 200) {
                $j = json_decode($resp, true);
                if (isset($j['code']) && $j['code'] === '000000' && isset($j['data']['adv']['price'])) {
                    $price = (float)$j['data']['adv']['price'];
                    $fiat  = $j['data']['adv']['fiatUnit'] ?? 'TZS';
                    $asset = $j['data']['adv']['asset'] ?? 'USDT';
                    
                    $adv = $j['data']['adv'];
                    $minSingleData = (float)($adv['minSingleTransQuantity'] ?? 0);
                    $maxSingleData = (float)($adv['maxSingleTransQuantity'] ?? 0);
                    $dynamicMaxData = (float)($adv['dynamicMaxSingleTransQuantity'] ?? $maxSingleData);
                    $availAsset = (float)($adv['surplusAmount'] ?? $adv['tradableQuantity'] ?? 0);
                    $minAmount = round($minSingleData * $price, 2);
                    $maxAmount = round($dynamicMaxData * $price, 2);
                    $availableFiat = round($availAsset * $price, 2);
                    if ($maxAmount > $availableFiat) {
                        $maxAmount = $availableFiat;
                    }

                    $methods = [];
                    if (isset($adv['tradeMethods']) && is_array($adv['tradeMethods'])) {
                        foreach ($adv['tradeMethods'] as $m) {
                            $mName = $m['payType'] ?? $m['identifier'] ?? '';
                            if ($mName && !in_array($mName, $methods)) {
                                $methods[] = $mName;
                            }
                        }
                    }

                    if ($price > 0) {
                        $result = [
                            'success' => true,
                            'source' => 'binance_detail_api',
                            'price' => $price,
                            'fiat' => $fiat,
                            'asset' => $asset,
                            'limits' => [
                                'minFiat' => $minAmount,
                                'maxFiat' => $maxAmount,
                                'availableFiat' => $availableFiat,
                            ],
                            'paymentMethods' => $methods,
                            'cached' => true
                        ];
                        file_put_contents($cacheFile, json_encode($result));
                        $result['cached'] = false;
                        echo json_encode($result);
                        exit;
                    }
                }
            }
        }

        // Fallback to HTML parsing if API fails or advNo not matched
        $url = 'https://p2p.binance.com/en/trade/all-cryptocurrencies?registerChannel=shareAd&uid=13495034&adCode=' . urlencode($code);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
            ],
        ]);
        $html = curl_exec($ch);
        curl_close($ch);

        if ($html !== false && preg_match('/<script id="__APP_DATA" type="application\/json">\s*(.*?)\s*<\/script>/is', $html, $matches)) {
            $appData = json_decode($matches[1], true);
            if (isset($appData['appState']['loader']['dataByRouteId'])) {
                foreach ($appData['appState']['loader']['dataByRouteId'] as $routeData) {
                    if (isset($routeData['adDetail']['price'])) {
                        $price = (float)$routeData['adDetail']['price'];
                        $fiat = $routeData['adDetail']['fiatUnit'] ?? 'TZS';
                        $asset = $routeData['adDetail']['asset'] ?? 'USDT';
                        
                        $adv = $routeData['adDetail'];
                        $minSingleData = (float)($adv['minSingleTransQuantity'] ?? 0);
                        $maxSingleData = (float)($adv['maxSingleTransQuantity'] ?? 0);
                        $dynamicMaxData = (float)($adv['dynamicMaxSingleTransQuantity'] ?? $maxSingleData);
                        $availAsset = (float)($adv['surplusAmount'] ?? $adv['tradableQuantity'] ?? 0);
                        $minAmount = round($minSingleData * $price, 2);
                        $maxAmount = round($dynamicMaxData * $price, 2);
                        $availableFiat = round($availAsset * $price, 2);
                        if ($maxAmount > $availableFiat) {
                            $maxAmount = $availableFiat;
                        }

                        $methods = [];
                        if (isset($adv['tradeMethods']) && is_array($adv['tradeMethods'])) {
                            foreach ($adv['tradeMethods'] as $m) {
                                $mName = $m['payType'] ?? $m['identifier'] ?? '';
                                if ($mName && !in_array($mName, $methods)) {
                                    $methods[] = $mName;
                                }
                            }
                        }

                        if ($price > 0) {
                            $result = [
                                'success' => true,
                                'source' => 'html_injection',
                                'price' => $price,
                                'fiat' => $fiat,
                                'asset' => $asset,
                                'limits' => [
                                    'minFiat' => $minAmount,
                                    'maxFiat' => $maxAmount,
                                    'availableFiat' => $availableFiat,
                                ],
                                'paymentMethods' => $methods,
                                'cached' => true
                            ];
                            file_put_contents($cacheFile, json_encode($result));
                            $result['cached'] = false;
                            echo json_encode($result);
                            exit;
                        }
                    }
                }
            }
        }

        $this->error('Failed to fetch price');
    }

    private function setOverride($input) {
        if (empty($_SESSION['admin_logged_in'])) {
            $this->error('Unauthorized', 401);
        }

        $code = trim($input['code'] ?? '');
        $price = $input['price'] ?? null;
        $fiat = $input['fiat'] ?? 'TZS';
        if ($code === '' || $price === null || !is_numeric($price)) {
            $this->error('Missing code or price');
        }

        $cacheDir = __DIR__ . '/../cache';
        if (!is_dir($cacheDir)) @mkdir($cacheDir, 0775, true);
        if (!is_dir($cacheDir)) $this->error('Cache dir not writable');

        $file = $cacheDir . '/headless_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $code) . '.json';
        $payload = [
            'success' => true,
            'code' => $code,
            'role' => null,
            'price' => (float)$price,
            'fiat' => $fiat,
            'url' => 'override:local',
            'screenshot' => null,
            'updatedAt' => time(),
        ];
        if (file_put_contents($file, json_encode($payload)) === false) {
            $this->error('Failed to write override');
        }

        return $this->success(['message' => 'Override saved', 'file' => basename($file)]);
    }
}
